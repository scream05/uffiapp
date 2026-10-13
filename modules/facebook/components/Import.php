<?php

namespace humhub\modules\facebook\components;

use humhub\modules\content\models\Content;
use humhub\modules\facebook\models\FbBeitrag;
use humhub\modules\facebook\Module;
use humhub\modules\file\models\File;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Facebook-Beiträge → HumHub-Beiträge. Erkennung über fb_beitrag (Facebook-ID), geänderte Beiträge werden
 * aktualisiert. Beiträge, die nur auf einen schon übernommenen Artikel von uffenheim.de verlinken, werden
 * übersprungen (der Website-Import hat sie schon). Bilder werden heruntergeladen und angehängt, damit
 * niemand beim Lesen Inhalte von Facebook nachlädt.
 */
class Import
{
    /** @var callable(string): void */
    private $log;

    public function __construct(private Module $module, callable $log)
    {
        $this->log = $log;
    }

    /**
     * @param array $beitraege Beiträge im Format der Graph-API (neueste zuerst)
     * @return array [neu, geändert, übersprungen, fehlgeschlagen]
     */
    public function uebernehmen(array $beitraege, bool $probelauf = false): array
    {
        $space = Space::findOne(['url' => $this->module->space]) ?? throw new \RuntimeException("Space {$this->module->space} fehlt");
        $konto = User::findOne(['username' => $this->module->konto]);
        if ($konto === null && !$probelauf) {
            throw new \RuntimeException("Konto {$this->module->konto} fehlt, zuerst /app/yii facebook/konto ausführen");
        }
        if ($konto !== null) {
            Yii::$app->user->switchIdentity($konto);
        }

        $ersterLauf = !FbBeitrag::find()->exists();
        $neu = $geaendert = $uebersprungen = $fehler = 0;
        // Älteste zuerst, damit neuere Beiträge im Stream oben stehen
        foreach (array_reverse($beitraege) as $b) {
            $fbId = (string)($b['id'] ?? '');
            $zeit = strtotime($b['created_time'] ?? '') ?: time();
            if ($fbId === '') {
                continue;
            }
            $vorhanden = FbBeitrag::findOne(['fb_id' => $fbId]);
            if ($vorhanden === null && $ersterLauf && $zeit < time() - $this->module->erstImportTage * 86400) {
                continue;
            }
            [$text, $bilder] = $this->inhalt($b);
            $doppelt = $this->websiteArtikel($b, $space);

            if ($probelauf) {
                ($this->log)("----- " . date('Y-m-d H:i', $zeit) . ($doppelt ? " (übersprungen: schon als Website-Artikel da)" : '')
                    . ($text === '' && !$bilder ? ' (übersprungen: leer)' : '') . "\n$text\n" . implode("\n", $bilder) . "\n");
                continue;
            }

            if ($vorhanden !== null) {
                $post = $vorhanden->post_id ? Post::findOne($vorhanden->post_id) : null;
                if ($post !== null && $vorhanden->fb_updated !== ($b['updated_time'] ?? null) && $post->message !== $text) {
                    $post->message = $text;
                    $post->save();
                    ($this->log)("~ $fbId\n");
                    $geaendert++;
                }
                $vorhanden->updateAttributes(['fb_updated' => $b['updated_time'] ?? null]);
                continue;
            }

            $eintrag = new FbBeitrag(['fb_id' => $fbId, 'fb_updated' => $b['updated_time'] ?? null, 'created_at' => date('Y-m-d H:i:s')]);
            if ($doppelt || ($text === '' && !$bilder)) {
                $eintrag->status = $doppelt ? 'doppelt' : 'leer';
                $eintrag->save();
                ($this->log)("= $fbId ({$eintrag->status})\n");
                $uebersprungen++;
                continue;
            }

            $post = new Post($space);
            $post->message = $text;
            $post->silentContentCreation = $ersterLauf || $zeit < time() - 7 * 86400;
            $post->content->visibility = Content::VISIBILITY_PUBLIC; // wie die Website-Nachrichten: auch für Gäste
            if (!$post->save()) {
                ($this->log)("! $fbId: " . json_encode($post->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
                $fehler++;
                continue;
            }
            foreach ($bilder as $i => $url) {
                $this->bildAnhaengen($post, $url, $fbId . '-' . ($i + 1));
            }
            $datum = date('Y-m-d H:i:s', $zeit);
            $post->content->updateAttributes(['created_at' => $datum, 'updated_at' => $datum, 'stream_sort_date' => $datum]);
            $eintrag->post_id = $post->id;
            $eintrag->save();
            ($this->log)("+ $fbId" . ($post->silentContentCreation ? ' (still)' : '') . "\n");
            $neu++;
        }
        return [$neu, $geaendert, $uebersprungen, $fehler];
    }

    /**
     * Beitragstext (HumHub-Markdown) und Bild-URLs
     * @return array [string Text, string[] Bilder]
     */
    public function inhalt(array $b): array
    {
        $text = trim((string)($b['message'] ?? ''));
        // Zeilen mit # am Anfang (Hashtags) wären in Markdown Überschriften
        $text = preg_replace('/^#/m', '\\#', $text);
        $bilder = [];
        $links = [];
        foreach ($b['attachments']['data'] ?? [] as $a) {
            $teile = !empty($a['subattachments']['data']) ? $a['subattachments']['data'] : [$a];
            foreach ($teile as $t) {
                $typ = $t['media_type'] ?? $t['type'] ?? '';
                $src = $t['media']['image']['src'] ?? null;
                if ($src && in_array($typ, ['photo', 'album', 'video', 'link', 'share', 'video_inline', 'animated_image_share'], true)) {
                    $bilder[] = $src;
                }
                if (str_contains($typ, 'video')) {
                    $links['Video auf Facebook ansehen'] = $b['permalink_url'] ?? null;
                }
            }
            $ziel = $a['unshimmed_url'] ?? $a['url'] ?? '';
            if (in_array($a['type'] ?? '', ['share', 'link'], true) && $ziel !== '' && !str_contains($ziel, 'facebook.com')) {
                $links[trim((string)($a['title'] ?? '')) ?: 'Link öffnen'] = $ziel;
            }
        }
        if (!$bilder && !empty($b['full_picture'])) {
            $bilder[] = $b['full_picture'];
        }
        $bilder = array_slice(array_values(array_unique($bilder)), 0, $this->module->maxBilder);
        if ($text === '' && !$bilder) {
            return ['', []];
        }
        foreach (array_filter($links) as $titel => $url) {
            $text .= "\n\n[" . str_replace(['[', ']'], '', $titel) . "]($url)";
        }
        if (!empty($b['permalink_url'])) {
            $text .= "\n\n[Beitrag auf Facebook](" . $b['permalink_url'] . ')';
        }
        return [trim($text), $bilder];
    }

    /** Verlinkt der Beitrag einen Artikel von uffenheim.de, der schon im Stream steht? */
    private function websiteArtikel(array $b, Space $space): bool
    {
        $texte = (string)($b['message'] ?? '');
        foreach ($b['attachments']['data'] ?? [] as $a) {
            $texte .= ' ' . ($a['unshimmed_url'] ?? $a['url'] ?? '');
        }
        if (!preg_match_all('#uffenheim\.de/nachrichten/artikel/[^\s"\')?&]+#i', $texte, $m)) {
            return false;
        }
        foreach (array_unique($m[0]) as $pfad) {
            if (Post::find()->contentContainer($space)->andWhere(['like', 'post.message', rtrim($pfad, '/.,')])->exists()) {
                return true;
            }
        }
        return false;
    }

    private function bildAnhaengen(Post $post, string $url, string $name): void
    {
        try {
            $data = Api::laden($url);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            if (!str_starts_with($mime, 'image/')) {
                return;
            }
            $file = new File([
                'file_name' => 'facebook-' . preg_replace('/[^0-9a-z_-]/i', '', $name) . '.' . (explode('/', $mime)[1] ?? 'jpg'),
                'mime_type' => $mime,
                'size' => strlen($data),
            ]);
            $file->save();
            $file->setStoredFileContent($data);
            $post->fileManager->attach($file);
        } catch (\Throwable $e) {
            ($this->log)("! Bild zu Beitrag {$post->id}: {$e->getMessage()}\n");
        }
    }
}
