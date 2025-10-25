<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

final class SanitizerConfig
{
    /** @var list<string> */
    public array $enabledRules = ['secrets', 'pii', 'payment', 'network'];

    /** @var array<string,string> */
    public array $masks = [
        'default' => '***REDACTED***',
        'email' => '***@***',
        'phone' => '+** **** ****',
    ];

    /** @var list<string> */
    public array $allowlistedKeys = ['file', 'line', 'function', 'class'];

    /** @var list<string> */
    public array $denylistedKeys = ['password', 'pwd', 'pass', 'secret', 'token', 'api_key', 'authorization', 'cookie', 'set-cookie'];

    /** @var array<string,string> */
    public array $customRegex = [];
}
