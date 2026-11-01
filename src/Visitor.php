<?php
declare(strict_types=1);

namespace Lucida\Laravel;

/** Pure visitor-id and bootstrap logic; no framework types so it is testable without a Laravel app. */
final class Visitor
{
    public static function consentGranted(array $consentCfg, array $cookies): bool
    {
        if (!($consentCfg['required'] ?? true)) {
            return true;
        }
        $v = $cookies[$consentCfg['cookie'] ?? 'lc_consent'] ?? null;
        return is_string($v) && in_array($v, $consentCfg['granted'] ?? [], true);
    }

    /**
     * @return array{id: string, set_cookie: bool} Auth user id wins; else the existing cookie; else a new id that is
     *         flagged for persistence only when consent is granted (without consent the id lives for this request only).
     */
    public static function resolve(?string $userId, array $cookies, array $config): array
    {
        if ($userId !== null && $userId !== '') {
            return ['id' => $userId, 'set_cookie' => false];
        }
        $name = $config['cookie']['name'] ?? 'lc_id';
        $consent = self::consentGranted($config['consent'] ?? [], $cookies);
        $existing = $cookies[$name] ?? null;
        if ($consent && is_string($existing) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $existing)) {
            return ['id' => $existing, 'set_cookie' => false];
        }
        return ['id' => 'anon_' . bin2hex(random_bytes(12)), 'set_cookie' => $consent];
    }

    /** JSON safe inside a <script> element: < > & ' " become \uXXXX; U+2028/2029 stay escaped (no UNESCAPED_UNICODE). */
    public static function safeJson(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    public static function bootstrapScript(string $distinctId, array $flags): string
    {
        return '<script>window.__LUCIDA_BOOTSTRAP__=' . self::safeJson(['distinctId' => $distinctId, 'flags' => (object) $flags]) . ';</script>';
    }
}
