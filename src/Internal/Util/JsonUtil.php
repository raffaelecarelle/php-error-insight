<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function is_array;

final class JsonUtil
{
    /**
     * Serializza un array in JSON restituendo stringa vuota in caso di errore.
     * Perché: `json_encode()` può restituire false; normalizziamo a stringa per evitare controlli
     * ripetitivi e tenere l'errore a livello di util.
     *
     * @param array<string,mixed> $value
     */
    public function encode(array $value, int $flags = 0): string
    {
        return json_encode($value, $flags) ?: '';
    }

    /**
     * Decodifica JSON in array associativo oppure null se non è un oggetto valido.
     * Perché: chiediamo `true` per avere array associativi e verifichiamo che il risultato sia un
     * array prima di restituirlo; altrimenti ritorniamo null come segnale chiaro di errore.
     *
     * @return array<string,mixed>|null
     */
    public function decodeObject(string $json): ?array
    {
        $v = json_decode($json, true);

        return is_array($v) ? $v : null;
    }
}
