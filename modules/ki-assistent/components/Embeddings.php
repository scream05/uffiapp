<?php

namespace humhub\modules\kiAssistent\components;

/**
 * Vektor für eine Suchanfrage vom lokalen Embedding-Dienst (Container „embeddings“, embeddings/server.py),
 * passend zu den Abschnitt-Vektoren aus scripts/stadtwissen.py. Die Frage verlässt den Server dafür nicht.
 */
class Embeddings
{
    public const URL = 'http://embeddings:8000/embed';

    /** @return array [Modellname, normierter Vektor] */
    public static function query(string $text): array
    {
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['texte' => [$text], 'art' => 'frage'], JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            // Antwortet sonst in ~0,1 s; hängt der Dienst, lieber gleich die Stichwortsuche als den Nutzer warten lassen
            CURLOPT_TIMEOUT => 3,
        ]);
        $data = json_decode((string)curl_exec($ch), true);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $v = $data['vektoren'][0] ?? null;
        if ($status !== 200 || !is_array($v)) {
            throw new \RuntimeException("Embedding-Dienst HTTP $status: " . ($data['fehler'] ?? $error ?: '?'));
        }
        return [(string)($data['modell'] ?? ''), $v];
    }
}
