<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;

final class Translator
{
    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    /**
     * Translate a string key using the locale from Config, with fallback to Italian.
     * Supports dot-notation keys and {placeholder} replacements.
     * @param Config $config
     * @param string $key
     * @param array<string,string|int|float> $replacements
     */
    public static function t(Config $config, string $key, array $replacements = []): string
    {
        $locale = $config->language ?: 'en';
        $val = self::getValue($locale, $key);
        if (!is_string($val) || $val === '') {
            // fallback to en
            if ($locale !== 'en') {
                $val = self::getValue('en', $key);
            }
        }
        if (!is_string($val) || $val === '') {
            // final fallback to key
            $val = $key;
        }
        // Replace placeholders
        if ($replacements) {
            foreach ($replacements as $k => $v) {
                $val = str_replace('{' . $k . '}', (string)$v, $val);
            }
        }
        return $val;
    }

    /**
     * Translate a list (array of strings). Returns empty array if not found.
     * @param Config $config
     * @param string $key
     * @return array<int,string>
     */
    public static function tList(Config $config, string $key): array
    {
        $locale = $config->language ?: 'it';
        $val = self::getValue($locale, $key);
        if (!is_array($val)) {
            if ($locale !== 'it') {
                $val = self::getValue('it', $key);
            }
        }
        if (!is_array($val)) {
            return [];
        }
        // cast all items to string
        $out = [];
        foreach ($val as $item) {
            if (is_scalar($item)) {
                $out[] = (string)$item;
            }
        }
        return $out;
    }

    /**
     * @return mixed|null
     */
    private static function getValue(string $locale, string $key)
    {
        $catalog = self::loadCatalog($locale);
        $parts = explode('.', $key);
        $node = $catalog;
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) {
                return null;
            }
            $node = $node[$p];
        }
        return $node;
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadCatalog(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }
        $baseDir = dirname(__DIR__, 2) . '/resources/lang';
        $path = $baseDir . '/' . $locale . '.json';
        $data = [];
        if (is_file($path)) {
            $json = file_get_contents($path);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        self::$cache[$locale] = $data;
        return $data;
    }
}
