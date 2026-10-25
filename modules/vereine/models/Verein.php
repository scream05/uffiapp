<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;
use humhub\interfaces\ViewableInterface;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\file\models\File;
use humhub\modules\user\helpers\AuthHelper;
use humhub\modules\user\models\User;
use humhub\modules\vereine\Module;
use Yii;
use yii\helpers\Url;

/**
 * @property int $id
 * @property string $name
 * @property string $kategorie
 * @property string $ortsteil
 * @property string $status offen | vorgeschlagen | aktiv
 * @property string|null $beschreibung
 * @property string|null $treffpunkt
 * @property string|null $website
 * @property string|null $email
 * @property string|null $telefon
 * @property string $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 * @property string|null $ansprechpartner JSON [{funktion,name,email,telefon}]
 * @property string|null $social JSON [{typ,url}]
 * @property string|null $logo_guid
 * @property string|null $logo_quelle
 * @property string|null $bildnachweise JSON {guid: {beschreibung,urheber,lizenz,seite}}
 * @property string|null $quellen JSON [url]
 * @property string|null $recherchiert_am
 * @property string|null $bfv_id Kennung bei der Spielquelle (BFV-Kennung, DBB-Vereinsnummer, Suchbegriff beim BVV,
 *                            Vereinsnummer beim BBV), gesetzt = Spiele werden abgerufen
 * @property string|null $bfv_name Vereinsname bei der Spielquelle
 * @property string $spiele_quelle bfv | dbb | bvv | bbv (components/Spielquellen.php)
 * @property string|null $ergebnisse_url Ergebnisse/Tabellen bei einer Quelle ohne Abruf (nur Link), z. B. RWK-Onlinemelder
 * @property string|null $ergebnisse_text wo, z. B. „beim Schützengau Uffenheim“
 */
class Verein extends ActiveRecord implements ViewableInterface
{
    public const STATUS_OFFEN = 'offen';
    public const STATUS_VORGESCHLAGEN = 'vorgeschlagen';
    public const STATUS_AKTIV = 'aktiv';

    public const MAX_ANSPRECHPARTNER = 8;
    public const MAX_SOCIAL = 5;
    public const MAX_BILDER = 8;

    /** Formularzeilen für Ansprechpartner und Social-Media-Links (werden als JSON gespeichert) */
    public array $apRows = [];
    public array $socialRows = [];

    public static function tableName()
    {
        return 'verein';
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->apRows = $this->getAnsprechpartnerList();
        $this->socialRows = $this->getSocialList();
    }

    public function rules()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('vereine');
        return [
            [['name', 'kategorie', 'ortsteil'], 'required'],
            [['name', 'treffpunkt', 'website', 'email', 'telefon'], 'trim'],
            ['name', 'string', 'min' => 3, 'max' => 150],
            ['name', 'unique', 'message' => Yii::t('VereineModule.base', 'Diesen Verein gibt es schon im Verzeichnis.')],
            ['kategorie', 'in', 'range' => $module->categories],
            ['ortsteil', 'in', 'range' => $module->ortsteile],
            ['beschreibung', 'string', 'max' => 20000],
            ['treffpunkt', 'string', 'max' => 255],
            ['website', 'url', 'defaultScheme' => 'https', 'enableIDN' => true, 'message' => Yii::t('VereineModule.base', 'Bitte eine gültige Internetadresse angeben.')],
            ['website', 'string', 'max' => 255],
            ['email', 'email', 'enableIDN' => true, 'message' => Yii::t('VereineModule.base', 'Bitte eine gültige E-Mail-Adresse angeben.')],
            ['telefon', 'match', 'pattern' => '/^[0-9 +\/()-]{5,50}$/', 'message' => Yii::t('VereineModule.base', 'Bitte nur Ziffern, Leerzeichen und + / - ( ).')],
            [['apRows', 'socialRows'], 'safe'],
            ['apRows', 'validateAnsprechpartner'],
            ['socialRows', 'validateSocial'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => Yii::t('VereineModule.base', 'Name des Vereins'),
            'kategorie' => Yii::t('VereineModule.base', 'Kategorie'),
            'ortsteil' => Yii::t('VereineModule.base', 'Ort / Ortsteil'),
            'beschreibung' => Yii::t('VereineModule.base', 'Vorstellung'),
            'treffpunkt' => Yii::t('VereineModule.base', 'Vereinsheim / Treffpunkt'),
            'website' => Yii::t('VereineModule.base', 'Website'),
            'email' => Yii::t('VereineModule.base', 'E-Mail des Vereins'),
            'telefon' => Yii::t('VereineModule.base', 'Telefon des Vereins'),
            'apRows' => Yii::t('VereineModule.base', 'Ansprechpartner'),
            'socialRows' => Yii::t('VereineModule.base', 'Social Media'),
        ];
    }

    public function validateAnsprechpartner()
    {
        $rows = [];
        foreach (array_slice((array)$this->apRows, 0, self::MAX_ANSPRECHPARTNER) as $row) {
            $row = array_map(fn($v) => mb_substr(trim((string)$v), 0, 150), array_intersect_key((array)$row,
                array_flip(['funktion', 'name', 'email', 'telefon'])) + ['funktion' => '', 'name' => '', 'email' => '', 'telefon' => '']);
            if ($row['funktion'] === '' && $row['name'] === '' && $row['email'] === '' && $row['telefon'] === '') {
                continue;
            }
            if ($row['email'] !== '' && !(new \yii\validators\EmailValidator(['enableIDN' => true]))->validate($row['email'])) {
                $this->addError('apRows', Yii::t('VereineModule.base', 'Ungültige E-Mail-Adresse: {wert}', ['wert' => $row['email']]));
            }
            if ($row['telefon'] !== '' && !preg_match('/^[0-9 +\/()-]{5,50}$/', $row['telefon'])) {
                $this->addError('apRows', Yii::t('VereineModule.base', 'Ungültige Telefonnummer: {wert}', ['wert' => $row['telefon']]));
            }
            $rows[] = $row;
        }
        $this->apRows = $rows;
        $this->ansprechpartner = $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    public function validateSocial()
    {
        $rows = [];
        foreach (array_slice((array)$this->socialRows, 0, self::MAX_SOCIAL) as $row) {
            $url = trim((string)($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https://[^\s"<>]{4,300}$#', $url)) {
                $this->addError('socialRows', Yii::t('VereineModule.base', 'Bitte vollständige Adressen mit https:// angeben: {wert}', ['wert' => $url]));
                continue;
            }
            $rows[] = ['typ' => mb_substr(trim((string)($row['typ'] ?? '')), 0, 30) ?: self::socialType($url), 'url' => $url];
        }
        $this->socialRows = $rows;
        $this->social = $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    public static function socialType(string $url): string
    {
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
                     'whatsapp' => 'WhatsApp', 'x.com' => 'X', 'twitter' => 'X', 'mastodon' => 'Mastodon'] as $k => $label) {
            if (str_contains(strtolower($url), $k)) {
                return $label;
            }
        }
        return 'Link';
    }

    private static function jsonList(?string $json): array
    {
        $data = $json ? json_decode($json, true) : null;
        return is_array($data) ? $data : [];
    }

    public function getAnsprechpartnerList(): array
    {
        return self::jsonList($this->ansprechpartner);
    }

    public function getSocialList(): array
    {
        return self::jsonList($this->social);
    }

    public function getQuellenList(): array
    {
        return self::jsonList($this->quellen);
    }

    /** @return array guid => [beschreibung, urheber, lizenz, seite] */
    public function getBildnachweise(): array
    {
        return self::jsonList($this->bildnachweise);
    }

    /** Aus öffentlichen Quellen zusammengestellt und vom Verein noch nicht übernommen */
    public function isRecherchiert(): bool
    {
        return $this->recherchiert_am !== null && $this->status === self::STATUS_OFFEN;
    }

    /** Felder, in denen die Suche (Verzeichnis-Filter und Suche in der Kopfleiste) sucht */
    public const SEARCH_FIELDS = ['name', 'beschreibung', 'treffpunkt', 'kategorie', 'ortsteil', 'ansprechpartner', 'website', 'email'];

    /** Bedingung für find()->andWhere(): jedes Suchwort muss in einem der Felder vorkommen */
    public static function searchCondition(string $q): array
    {
        $cond = ['and'];
        foreach (array_slice(preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $word) {
            $cond[] = array_merge(['or'], array_map(fn($f) => ['like', $f, $word], self::SEARCH_FIELDS));
        }
        return $cond;
    }

    /** Kurzer Hinweis, wo die Suchwörter stehen, wenn nicht schon im Namen (z. B. „Ansprechpartner: Vorsitzende Erika Muster“) */
    public function searchHint(string $q): ?string
    {
        $words = array_filter(preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY), fn($w) => mb_stripos($this->name, $w) === false);
        if (!$words) {
            return null;
        }
        $count = fn(string $text) => count(array_filter($words, fn($w) => mb_stripos($text, $w) !== false));
        $best = null;
        $bestHits = 0;
        foreach ($this->getAnsprechpartnerList() as $p) {
            $label = trim(($p['funktion'] ?? '') . ' ' . ($p['name'] ?? ''));
            if (($hits = $count($label)) > $bestHits) {
                [$best, $bestHits] = [$label, $hits];
            }
        }
        if ($best !== null) {
            return Yii::t('VereineModule.base', 'Ansprechpartner') . ': ' . $best;
        }
        if ($count((string)$this->treffpunkt)) {
            return Yii::t('VereineModule.base', 'Treffpunkt') . ': ' . $this->treffpunkt;
        }
        $text = preg_replace('/[*_#>\[\]()`]+/', '', (string)$this->beschreibung);
        foreach ($words as $word) {
            if (($pos = mb_stripos($text, $word)) !== false) {
                $start = max(0, $pos - 40);
                return ($start ? '…' : '') . trim(mb_substr($text, $start, 110)) . '…';
            }
        }
        return null;
    }

    public function beforeSave($insert)
    {
        $now = date('Y-m-d H:i:s');
        $userId = Yii::$app->has('user') && !Yii::$app->user->isGuest ? Yii::$app->user->id : null;
        if ($insert) {
            $this->created_at = $now;
            $this->created_by ??= $userId;
        } else {
            $this->updated_at = $now;
            $this->updated_by = $userId;
        }
        return parent::beforeSave($insert);
    }

    public function afterDelete()
    {
        foreach ($this->fileManager->findAll() as $file) {
            $file->delete();
        }
        parent::afterDelete();
    }

    /**
     * Bestimmt u. a., wer Logo und Bilder (file/download) sehen darf. Das Verzeichnis ist auch für Gäste
     * lesbar, solange der Gastzugang an ist.
     */
    public function canView($user = null): bool
    {
        return !Yii::$app->user->isGuest || AuthHelper::isGuestAccessEnabled();
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space ? $space->createUrl('/vereine/verein/view', ['id' => $this->id], $scheme) : Url::home($scheme);
    }

    /** Seite „Spiele & Ergebnisse“ (nur bei Vereinen mit Spielquelle, bfv_id) */
    public function getSpieleUrl(bool $scheme = false): ?string
    {
        $space = Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space && $this->bfv_id ? $space->createUrl('/vereine/spiele/index', ['verein' => $this->id], $scheme) : null;
    }

    public function getMannschaften()
    {
        return $this->hasMany(VereinMannschaft::class, ['verein_id' => 'id']);
    }

    public function getVerwalter()
    {
        return $this->hasMany(VereinVerwalter::class, ['verein_id' => 'id']);
    }

    /** @return User[] bestätigte Verwalter */
    public function getConfirmedUsers(): array
    {
        return User::find()->innerJoin('verein_verwalter vv', 'vv.user_id = user.id')
            ->where(['vv.verein_id' => $this->id, 'vv.status' => VereinVerwalter::STATUS_BESTAETIGT])
            ->andWhere(['user.status' => User::STATUS_ENABLED])->all();
    }

    /** Darf der aktuelle Nutzer Seite und Termine pflegen? */
    public function canManage(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        return Module::canManageAll() || VereinVerwalter::find()
                ->where(['verein_id' => $this->id, 'user_id' => Yii::$app->user->id, 'status' => VereinVerwalter::STATUS_BESTAETIGT])
                ->exists();
    }

    /** @return File[] alle Bilder des Vereins */
    private function images(): array
    {
        return array_values(array_filter($this->fileManager->findAll(), fn($f) => str_starts_with((string)$f->mime_type, 'image/')));
    }

    public function getLogo(): ?File
    {
        if ($this->logo_guid === null) {
            return null;
        }
        foreach ($this->images() as $file) {
            if ($file->guid === $this->logo_guid) {
                return $file;
            }
        }
        return null;
    }

    /** @return File[] Bilder ohne Logo */
    public function getGallery(): array
    {
        $logo = $this->getLogo();
        return array_values(array_filter($this->images(), fn($f) => $f->guid !== $logo?->guid));
    }

    /** @return CalendarEntry[] */
    public function getUpcomingEntries(int $limit = 20): array
    {
        // readable(): nur veröffentlichte (nicht gelöschte/archivierte) und für den Nutzer sichtbare Termine
        return CalendarEntry::find()->readable()->innerJoin('verein_termin vt', 'vt.calendar_entry_id = calendar_entry.id')
            ->where(['vt.verein_id' => $this->id])
            ->andWhere(['>=', 'calendar_entry.end_datetime', date('Y-m-d H:i:s')])
            ->orderBy(['calendar_entry.start_datetime' => SORT_ASC])->limit($limit)->all();
    }

    public static function forEntry(CalendarEntry $entry): ?self
    {
        $link = VereinTermin::findOne(['calendar_entry_id' => $entry->id]);
        return $link ? static::findOne($link->verein_id) : null;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OFFEN => $this->recherchiert_am ? Yii::t('VereineModule.base', 'aus öffentlichen Quellen') : Yii::t('VereineModule.base', 'noch nicht gepflegt'),
            self::STATUS_VORGESCHLAGEN => Yii::t('VereineModule.base', 'wartet auf Freigabe'),
            default => '',
        };
    }
}
