<?php
declare(strict_types=1);

namespace Lucida\Laravel;

use Closure;
use Illuminate\Http\Request;
use Lucida\Client;

final class LucidaMiddleware
{
    public function __construct(private readonly Client $client)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $cfg = config('lucida');
        $uid = $request->user()?->getAuthIdentifier();
        $v = Visitor::resolve($uid === null ? null : (string) $uid, $request->cookies->all(), $cfg);
        $request->attributes->set('lucida_distinct_id', $v['id']);
        view()->share('lucidaDistinctId', $v['id']);
        $ctx = RequestContext::build($uid === null ? null : (string) $uid, $v['id'], $request->headers->all(), [
            'method' => $request->method(), 'path' => $request->getPathInfo(), 'url' => $request->url(),
            'user_agent' => $request->userAgent(), 'remote_addr' => $request->server->get('REMOTE_ADDR'),
        ], $cfg);
        $response = Client::withContext($ctx, fn () => $next($request));
        if ($v['set_cookie']) {
            $response->headers->setCookie(cookie($cfg['cookie']['name'], $v['id'], $cfg['cookie']['max_age_minutes'], '/', null, null, true, false, 'lax'));
        }
        return $response;
    }

    /** Runs after the response is sent: flush queued events so a long-running worker never holds them. */
    public function terminate(Request $request, $response): void
    {
        $this->client->flush();
    }

    /** Called from the compiled @lucidaBootstrap directive. */
    public static function bootstrapHtml(): string
    {
        // Per-request state lives on the request itself, so nothing survives into the next request under Octane.
        $id = request()->attributes->get('lucida_distinct_id');
        if (!is_string($id) || $id === '') {
            return '';
        }
        return Visitor::bootstrapScript($id, app(Client::class)->getAllFlags($id));
    }
}
