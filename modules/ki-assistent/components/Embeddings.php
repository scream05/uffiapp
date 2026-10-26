<?php

namespace humhub\modules\kiAssistent\components;

/**
 * Suche nach Bedeutung im lokalen Embedding-Dienst (Container „embeddings“, embeddings/server.py): Er berechnet den
 * Vektor der Frage und vergleicht ihn mit den Abschnitt-Vektoren aus scripts/stadtwissen.py, die er im Speicher hält.
 * Die Frage verlässt den Server dafür nicht.
 */
class Embeddings
{
    public const URL = 'http://embeddings:8000/suche';

    /** @return array [Stand des Index (Änderungszeit), Abschnitt-Positionen nach Ähnlichkeit, beste zuerst] */
    public static function suche(string $text): array
    {
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['frage' => $text], JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            // Antwortet sonst in ~0,1 s; hängt der Dienst, lieber gleich die Stichwortsuche als den Nutzer warten lassen
            CURLOPT_TIMEOUT => 3,
        ]);
        $data = json_decode((string)curl_exec($ch), true);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($status !== 200 || !is_array($data['rang'] ?? null)) {
            throw new \RuntimeException("Embedding-Dienst HTTP $status: " . ($data['fehler'] ?? $error ?: '?'));
        }
        return [(int)$data['stand'], $data['rang']];
    }
}
