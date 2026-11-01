<?php
declare(strict_types=1);

namespace Lucida\Laravel;

use Lucida\Context;

/** Pure request -> Lucida\Context options mapping; no framework types so it is testable without a Laravel app. */
final class RequestContext
{
    /**
     * Distinct id priority: authenticated user (capture_user_context) > browser tracing header (use_tracing_headers) >
     * the visitor id the middleware resolved. The tracing headers are client-controlled; never use them for auth.
     *
     * @param array<string, mixed> $headers request headers (any case)
     * @param array{method?: string, path?: string, url?: string, user_agent?: ?string, remote_addr?: ?string} $req
     * @return array{distinct_id: string, session_id: ?string, properties: array<string, string>, fresh: true}
     */
    public static function build(?string $userId, string $visitorId, array $headers, array $req, array $cfg): array
    {
        $traced = ($cfg['use_tracing_headers'] ?? true) ? Context::fromHeaders($headers) : ['distinct_id' => null, 'session_id' => null];
        $user = ($cfg['capture_user_context'] ?? true) && $userId !== null && $userId !== '' ? $userId : null;
        $props = [
            '$current_url' => $req['url'] ?? null, '$request_method' => $req['method'] ?? null, '$request_path' => $req['path'] ?? null,
            '$user_agent' => $req['user_agent'] ?? null, '$ip' => self::ip($headers, $req['remote_addr'] ?? null, (bool) ($cfg['trust_proxy'] ?? false)),
        ];
        return [
            'distinct_id' => $user ?? $traced['distinct_id'] ?? $visitorId,
            'session_id' => $traced['session_id'],
            'properties' => array_filter($props, fn ($v) => $v !== null && $v !== ''),
            'fresh' => true, // one request = one context, also under Octane
        ];
    }

    private static function ip(array $headers, ?string $remote, bool $trustProxy): ?string
    {
        if ($trustProxy) {
            foreach ($headers as $k => $v) {
                if (strtolower((string) $k) === 'x-forwarded-for') {
                    $first = trim(explode(',', is_array($v) ? (string) ($v[0] ?? '') : (string) $v)[0]);
                    if ($first !== '') {
                        return $first;
                    }
                }
            }
        }
        return $remote;
    }
}
