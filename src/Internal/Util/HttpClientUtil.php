<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function is_array;
use function strlen;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;

/**
 * Small HTTP JSON client built on cURL, wrapped for testability and to isolate PHP core calls.
 */
final class HttpClientUtil
{
    /**
     * Esegue una richiesta HTTP con payload JSON e decodifica la risposta JSON.
     * Perché:
     * - Usiamo cURL per affidabilità e controllo di timeouts.
     * - Impostiamo `Content-Length` per compatibilità con alcuni server/proxy.
     * - `CURLOPT_CONNECTTIMEOUT` è limitato a max 5s per fail-fast nella fase di handshake,
     *   mentre `CURLOPT_TIMEOUT` governa la durata complessiva secondo `timeoutSec`.
     * - Torniamo `null` in caso di errori di rete o status non 2xx per segnalare fallimento
     *   senza lanciare eccezioni in utility di basso livello.
     * - Decodifichiamo come array associativo e verifichiamo che il risultato sia effettivamente un array.
     *
     * @param array<string,mixed> $payload Dati da serializzare in JSON nel body
     * @param array<int,string>   $headers Header addizionali da inviare
     *
     * @return array<string,mixed>|null JSON decodificato oppure null se errore/non 2xx/JSON non valido
     */
    public function requestJson(string $method, string $url, array $payload, array $headers, int $timeoutSec): ?array
    {
        if (!function_exists('curl_init')) {
            // Per ambienti senza estensione cURL preferiamo un fallback neutro (null)
            return null;
        }

        $ch = curl_init();
        if (false === $ch) {
            return null;
        }

        $json = json_encode($payload);
        $baseHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen((string) $json),
        ];
        $allHeaders = array_merge($baseHeaders, $headers);

        // Usiamo CUSTOMREQUEST per supportare anche metodi oltre POST/GET (PUT, PATCH, DELETE)
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeoutSec));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        $result = curl_exec($ch);
        if (false === $result) {
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string) $result, true);

        return is_array($decoded) ? $decoded : null;
    }
}
