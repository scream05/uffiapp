<?php

namespace humhub\modules\gewerbe\models;

use humhub\components\ActiveRecord;
use humhub\interfaces\ViewableInterface;
use humhub\modules\file\models\File;
use humhub\modules\gewerbe\components\Oeffnungszeiten;
use humhub\modules\maengelmelder\components\Osm;
use humhub\modules\gewerbe\Module;
use humhub\modules\user\helpers\AuthHelper;
use humhub\modules\user\models\User;
use Yii;
use yii\helpers\Url;

/**
 * Betrieb im Gewerbeverzeichnis.
 *
 * @property int $id
 * @property string $name
 * @property string $kategorie
 * @property string $ortsteil
 * @property string $status offen | vorgeschlagen | aktiv
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $zeiten JSON {Wochentag 1–7: [[von, bis], …]}
 * @property string|null $quelle Startliste: Seite, von der die Angaben stammen
 * @property string|null $recherchiert_am
 * @property string|null $angebot Stichworte, z. B. „Brot, Torten, Catering“
 * @property string|null $beschreibung
 * @property string|null $adresse
 * @property string|null $oeffnungszeiten mehrzeiliger Text
 * @property string|null $website
 * @property string|null $email
 * @property string|null $telefon
 * @property string|null $social JSON [{typ,url}]
 * @property string|null $logo_guid
 * @property string|null $web_quellen JSON-Liste der Seiten aus der einmaligen Web-Recherche
 * @property string|null $web_recherche_am
 * @property string|null $osm_stand JSON: zuletzt aus OSM gelesene Werte (siehe GewerbeController::uebernehmen)
 * @property string $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 */
class Betrieb extends ActiveRecord implements ViewableInterface
{
    /** aus öffentlicher Quelle übernommen, vom Betrieb noch nicht gepflegt */
    public const STATUS_OFFEN = 'offen';
    public const STATUS_VORGESCHLAGEN = 'vorgeschlagen';
    public const STATUS_AKTIV = 'aktiv';

    public const MAX_SOCIAL = 5;
    public const MAX_BILDER = 6;

    /** Formularzeilen für Social-Media-Links (werden als JSON gespeichert) */
    public array $socialRows = [];
    /** Formular: je Wochentag [von1, bis1, von2, bis2] */
    public array $zeitenRows = [];

    public static function tableName()
    {
        return 'gewerbe_betrieb';
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->socialRows = $this->getSocialList();
        foreach ($this->getZeiten() as $tag => $spannen) {
            $this->zeitenRows[$tag] = array_merge(...array_pad($spannen, 2, ['', '']));
        }
    }

    public function rules()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('gewerbe');
        return [
            [['name', 'kategorie', 'ortsteil'], 'required'],
            [['name', 'angebot', 'adresse', 'website', 'email', 'telefon'], 'trim'],
            [['angebot', 'adresse', 'website', 'email', 'telefon', 'oeffnungszeiten', 'beschreibung'], 'default', 'value' => null],
            ['name', 'string', 'min' => 2, 'max' => 150],
            ['name', 'unique', 'message' => 'Diesen Betrieb gibt es schon im Verzeichnis.'],
            ['kategorie', 'in', 'range' => $module->categories],
            ['ortsteil', 'in', 'range' => $module->ortsteile],
            ['angebot', 'string', 'max' => 500],
            ['beschreibung', 'string', 'max' => 20000],
            ['adresse', 'string', 'max' => 255],
            ['oeffnungszeiten', 'string', 'max' => 1000],
            ['website', 'url', 'defaultScheme' => 'https', 'enableIDN' => true, 'message' => 'Bitte eine gültige Internetadresse angeben.'],
            ['website', 'string', 'max' => 255],
            ['email', 'email', 'enableIDN' => true, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'],
            ['telefon', 'match', 'pattern' => '/^[0-9 +\/()-]{5,50}$/', 'message' => 'Bitte nur Ziffern, Leerzeichen und + / - ( ).'],
            [['socialRows', 'zeitenRows'], 'safe'],
            ['socialRows', 'validateSocial'],
            ['zeitenRows', 'validateZeiten'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Name des Betriebs',
            'kategorie' => 'Branche',
            'ortsteil' => 'Ort / Ortsteil',
            'angebot' => 'Angebot in Stichworten',
            'beschreibung' => 'Vorstellung',
            'adresse' => 'Adresse',
            'oeffnungszeiten' => 'Hinweise zu den Öffnungszeiten',
            'zeitenRows' => 'Öffnungszeiten',
            'website' => 'Website',
            'email' => 'E-Mail',
            'telefon' => 'Telefon',
            'socialRows' => 'Social Media',
        ];
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
                $this->addError('socialRows', "Bitte vollständige Adressen mit https:// angeben: $url");
                continue;
            }
            $rows[] = ['typ' => mb_substr(trim((string)($row['typ'] ?? '')), 0, 30) ?: self::socialType($url), 'url' => $url];
        }
        $this->socialRows = $rows;
        $this->social = $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /** Formularzeilen → JSON; eine Spanne braucht Anfang und Ende */
    public function validateZeiten()
    {
        $zeiten = [];
        foreach (Oeffnungszeiten::TAGE as $tag => $name) {
            $row = array_map(fn($v) => trim((string)$v), array_pad(array_values((array)($this->zeitenRows[$tag] ?? [])), 4, ''));
            $zeiten[$tag] = [];
            foreach ([[$row[0], $row[1]], [$row[2], $row[3]]] as [$von, $bis]) {
                if ($von === '' && $bis === '') {
                    continue;
                }
                if (!Oeffnungszeiten::gueltig($von) || !Oeffnungszeiten::gueltig($bis) || $von === $bis) {
                    $this->addError('zeitenRows', "$name: Bitte Anfang und Ende als Uhrzeit angeben, z. B. 08:00 und 12:00.");
                    continue;
                }
                $zeiten[$tag][] = [$von, $bis];
            }
        }
        $this->zeiten = Oeffnungszeiten::hatZeiten($zeiten) ? json_encode($zeiten) : null;
    }

    public function getZeiten(): array
    {
        return Oeffnungszeiten::ausJson($this->zeiten);
    }

    /** „Jetzt geöffnet · bis 18 Uhr“ usw.; null ohne hinterlegte Zeiten */
    public function getStand(): ?array
    {
        return Oeffnungszeiten::stand($this->getZeiten());
    }

    public function isRecherchiert(): bool
    {
        return $this->status === self::STATUS_OFFEN && $this->quelle !== null;
    }

    public static function socialType(string $url): string
    {
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
                     'whatsapp' => 'WhatsApp', 'google' => 'Google', 'x.com' => 'X', 'mastodon' => 'Mastodon'] as $k => $label) {
            if (str_contains(strtolower($url), $k)) {
                return $label;
            }
        }
        return 'Link';
    }

    public function getSocialList(): array
    {
        $data = $this->social ? json_decode($this->social, true) : null;
        return is_array($data) ? $data : [];
    }

    /** Felder, in denen die Suche sucht */
    public const SEARCH_FIELDS = ['name', 'angebot', 'beschreibung', 'kategorie', 'ortsteil', 'adresse'];

    /** Bedingung für find()->andWhere(): jedes Suchwort muss in einem der Felder vorkommen */
    public static function searchCondition(string $q): array
    {
        $cond = ['and'];
        foreach (array_slice(preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $word) {
            $cond[] = array_merge(['or'], array_map(fn($f) => ['like', $f, $word], self::SEARCH_FIELDS));
        }
        return $cond;
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

    /** Neue oder geänderte Adresse: Ort für die Karte über Nominatim bestimmen (nur Stadtgebiet mit Ortsteilen) */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if (($insert || array_key_exists('adresse', $changedAttributes)) && !$this->geocodeAus) {
            $this->ortBestimmen();
        }
    }

    /** Beim Import: Ort erst danach gesammelt bestimmen */
    public bool $geocodeAus = false;

    public function ortBestimmen(): void
    {
        $hit = null;
        $adresse = trim((string)$this->adresse);
        if ($adresse !== '' && class_exists(Osm::class)) {
            // Ausweich-Schreibweisen: erster von mehreren Standorten („A 7 und B 5, …“), Ortsteil vorn („Welbhausen, Gasse 6, …“)
            $plz = preg_match('/\d{5}\s+\S+/u', $adresse, $m) ? $m[0] : '';
            $teile = array_map('trim', explode(',', $adresse));
            $versuche = array_unique(array_filter([
                $adresse,
                str_contains($adresse, ' und ') ? trim(explode(' und ', $adresse)[0]) . ($plz ? ", $plz" : '') : null,
                count($teile) > 2 ? implode(', ', array_slice($teile, 1)) . ', ' . $teile[0] : null,
            ]));
            try {
                foreach ($versuche as $versuch) {
                    if (($hit = Osm::searchThrottled($versuch, Osm::NAH_BOUNDS) ?: null) !== null) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('Gewerbe-Ort: ' . $e->getMessage(), 'gewerbe');
            }
        }
        $this->updateAttributes(['lat' => $hit[0] ?? null, 'lng' => $hit[1] ?? null]);
    }

    public function beforeDelete()
    {
        // Beiträge samt Bildern (die Zeilen löscht sonst nur der Fremdschlüssel, die Dateien blieben liegen)
        foreach (BetriebBeitrag::findAll(['betrieb_id' => $this->id]) as $b) {
            $b->delete();
        }
        return parent::beforeDelete();
    }

    public function afterDelete()
    {
        foreach ($this->fileManager->findAll() as $file) {
            $file->delete();
        }
        // Gelöschte Betriebe (geschlossen, Dublette, Widerspruch) nicht wieder aus OpenStreetMap anlegen
        if ($this->osm_ref !== null) {
            $liste = self::osmAusgeschlossen();
            $liste[$this->osm_ref] = mb_substr($this->name, 0, 150);
            Yii::$app->getModule('gewerbe')->settings->set('osmAusgeschlossen', json_encode($liste, JSON_UNESCAPED_UNICODE));
        }
        parent::afterDelete();
    }

    /** @return array osm_ref => Name gelöschter Betriebe */
    public static function osmAusgeschlossen(): array
    {
        return (array)json_decode((string)Yii::$app->getModule('gewerbe')->settings->get('osmAusgeschlossen', '[]'), true);
    }

    /** Wer Logo und Bilder (file/download) sehen darf: wie das Verzeichnis, auch Gäste bei Gastzugang */
    public function canView($user = null): bool
    {
        return !Yii::$app->user->isGuest || AuthHelper::isGuestAccessEnabled();
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/betrieb/view', ['id' => $this->id], $scheme) : Url::home($scheme);
    }

    public function getVerwalter()
    {
        return $this->hasMany(BetriebVerwalter::class, ['betrieb_id' => 'id']);
    }

    /** @return User[] bestätigte Verwalter */
    public function getConfirmedUsers(): array
    {
        return User::find()->innerJoin('gewerbe_verwalter gv', 'gv.user_id = user.id')
            ->where(['gv.betrieb_id' => $this->id, 'gv.status' => BetriebVerwalter::STATUS_BESTAETIGT])
            ->andWhere(['user.status' => User::STATUS_ENABLED])->all();
    }

    /** Darf der aktuelle Nutzer die Seite pflegen? */
    public function canManage(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        return Module::canManageAll() || BetriebVerwalter::find()
                ->where(['betrieb_id' => $this->id, 'user_id' => Yii::$app->user->id, 'status' => BetriebVerwalter::STATUS_BESTAETIGT])
                ->exists();
    }

    /** @return File[] alle Bilder des Betriebs */
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

    /** Link zur Karte (OpenStreetMap-Suche nach der Adresse) */
    public function getKartenUrl(): ?string
    {
        if (!$this->adresse) {
            return null;
        }
        $q = $this->adresse . (preg_match('/\d{5}/', $this->adresse) ? '' : ', ' . ($this->ortsteil === 'Region' ? 'Uffenheim' : $this->ortsteil));
        return 'https://www.openstreetmap.org/search?query=' . rawurlencode($q);
    }
}
