<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function is_array;

use const PHP_SESSION_ACTIVE;

final class SessionUtil
{
    /**
     * Verifica se la sessione è attiva.
     * Perché: `session_status()` può non esistere su alcuni ambienti; controlliamo prima con
     * `function_exists` e poi confrontiamo con `PHP_SESSION_ACTIVE` per evitare errori.
     */
    public function sessionActive(): bool
    {
        return function_exists('session_status') && PHP_SESSION_ACTIVE === session_status();
    }

    /**
     * Restituisce `$_SESSION` se disponibile, altrimenti un array vuoto.
     * Perché: l'accesso diretto a `$_SESSION` può causare notice quando la sessione non è avviata;
     * questo wrapper fornisce un fallback sicuro e tipizzato.
     *
     * @return array<mixed,mixed>
     */
    public function getSessionOrEmpty(): array
    {
        if ($this->sessionActive() && isset($_SESSION) && is_array($_SESSION)) {
            return $_SESSION;
        }

        return [];
    }
}
