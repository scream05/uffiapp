<?php

namespace humhub\modules\maengelmelder\models;

use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\components\Osm;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;

/**
 * Eine Mängelmeldung ist ein normaler Beitrag im Space; Kategorie, Text und Ort stecken im Beitragstext
 * („**Mängelmeldung: Kategorie**“, Beschreibung, „📍 **Ort:** Adresse – [auf der Karte anzeigen](…karte?lat=…&lng=…)“).
 */
class Mangel
{
    public Post $post;
    public string $kategorie = 'Meldung';
    public string $beschreibung = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public string $adresse = '';

    public function __construct(Post $post)
    {
        $this->post = $post;
        $msg = (string)$post->message;
        if (preg_match('/karte\?lat=(-?[\d.]+)&(?:amp;)?lng=(-?[\d.]+)/', $msg, $m)) {
            $this->lat = (float)$m[1];
            $this->lng = (float)$m[2];
        }
        if (preg_match('/^\*\*Mängelmeldung: (.+?)\*\*\s*(.*?)\s*📍/su', $msg, $k)) {
            $this->kategorie = $k[1];
            $this->beschreibung = $k[2];
        }
        if (preg_match('/📍 \*\*Ort:\*\* (.+?) – \[auf der Karte/u', $msg, $a)) {
            $this->adresse = $a[1];
        }
    }

    public static function fromContent(Content $content): ?self
    {
        $post = $content->object_model === Post::class ? Post::findOne($content->object_id) : null;
        return $post ? new self($post) : null;
    }

    /** Beiträge, die Mängelmeldungen sind (mit Ortslink) */
    public static function find(Space $space)
    {
        return Post::find()->contentContainer($space)
            ->andWhere(['like', 'post.message', 'maengelmelder/meldung/karte?lat=']);
    }

    public function getContentId(): int
    {
        return (int)$this->post->content->id;
    }

    /** Fotos der meldenden Person */
    public function getBilder(): array
    {
        return array_values(array_filter($this->post->fileManager->findAll(),
            fn(File $f) => str_starts_with((string)$f->mime_type, 'image/')));
    }

    /** Ort als Text für Listen und WhatsApp */
    public function getOrt(): string
    {
        return $this->adresse !== '' ? $this->adresse : ($this->lat ? sprintf('%.5f, %.5f', $this->lat, $this->lng) : 'ohne Ort');
    }

    /** Kurzer Auszug der Beschreibung */
    public function getAuszug(int $laenge = 120): string
    {
        return mb_strimwidth(preg_replace('/\s+/u', ' ', $this->beschreibung), 0, $laenge, '…');
    }

    /** Beitragstext einer Meldung; Adresse wird per Nominatim ermittelt, wenn nicht angegeben */
    public static function message(Space $space, string $kategorie, string $beschreibung, float $lat, float $lng, ?string $address = null): string
    {
        $lat = round($lat, 6);
        $lng = round($lng, 6);
        $address ??= Osm::address($lat, $lng);
        $url = $space->createUrl('/maengelmelder/meldung/karte', ['lat' => $lat, 'lng' => $lng], true);
        return "**Mängelmeldung: $kategorie**\n\n$beschreibung\n\n"
            . '📍 **Ort:** ' . ($address ? "$address – " : '') . "[auf der Karte anzeigen]($url)";
    }
}
