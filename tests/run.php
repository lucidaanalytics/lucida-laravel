<?php
declare(strict_types=1);

// Pure-piece tests (no Laravel app): `php tests/run.php`.
require __DIR__ . '/../src/Visitor.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/RequestContext.php';
// Monorepo checkout first; the mirrored repo uses the published lucida/lucida from vendor/.
require is_file(__DIR__ . '/../../php/src/Context.php') ? __DIR__ . '/../../php/src/Context.php' : __DIR__ . '/../vendor/lucida/lucida/src/Context.php';

use Lucida\Laravel\Config;
use Lucida\Laravel\RequestContext;
use Lucida\Laravel\Visitor;

$fails = 0;
function check(bool $ok, string $m): void
{
    global $fails;
    if (!$ok) {
        $fails++;
        fwrite(STDERR, "FAIL $m\n");
    }
}
$cfg = ['cookie' => ['name' => 'lc_id'], 'consent' => ['required' => true, 'cookie' => 'lc_consent', 'granted' => ['analytics']]];

$r = Visitor::resolve('42', [], $cfg);
check($r['id'] === '42' && !$r['set_cookie'], 'user id wins, no cookie');
$r = Visitor::resolve(null, [], $cfg);
check(str_starts_with($r['id'], 'anon_') && !$r['set_cookie'], 'no consent: no cookie');
$r = Visitor::resolve(null, ['lc_id' => 'abcdefgh12', 'lc_consent' => 'denied'], $cfg);
check($r['id'] !== 'abcdefgh12' && !$r['set_cookie'], 'denied consent ignores cookie and never sets one');
$r = Visitor::resolve(null, ['lc_consent' => 'analytics'], $cfg);
check($r['set_cookie'], 'consent: new id persisted');
$r = Visitor::resolve(null, ['lc_id' => 'abcdefgh12', 'lc_consent' => 'analytics'], $cfg);
check($r['id'] === 'abcdefgh12' && !$r['set_cookie'], 'consent: existing cookie reused');
$r = Visitor::resolve(null, ['lc_id' => '<bad>', 'lc_consent' => 'analytics'], $cfg);
check($r['id'] !== '<bad>' && $r['set_cookie'], 'malformed cookie replaced');
$cfg['consent']['required'] = false;
check(Visitor::resolve(null, [], $cfg)['set_cookie'], 'consent not required');

$html = Visitor::bootstrapScript('u"</script><script>alert(1)', ['a' => true, 'b' => "x&'\u{2028}"]);
check(substr_count($html, '</script>') === 1 && !str_contains($html, '<script>alert'), 'script breakout escaped');
check(!str_contains($html, "'") && !str_contains($html, "\u{2028}") && substr_count($html, '&') === 0, 'quote/amp/ls escaped');
check(str_contains(Visitor::bootstrapScript('x', []), '"flags":{}'), 'empty flags is an object');

$a = Config::clientArgs(['api_key' => 'k', 'host' => 'https://h/', 'secret_key' => null]);
check($a === ['apiKey' => 'k', 'disabled' => false, 'host' => 'https://h'], 'config mapping');

// request context: priority user > tracing header > visitor id; request props; proxy-aware ip
$req = ['method' => 'GET', 'path' => '/p', 'url' => 'https://x.test/p', 'user_agent' => 'UA', 'remote_addr' => '10.0.0.1'];
$hdr = ['X-Lucida-Distinct-Id' => 'anon1', 'x-lucida-session-id' => 's1', 'X-Forwarded-For' => '1.2.3.4, 5.6.7.8'];
$c = RequestContext::build('42', 'visitor', $hdr, $req, []);
check($c['distinct_id'] === '42' && $c['session_id'] === 's1' && $c['fresh'] === true, 'auth user wins, session from header');
check($c['properties'] === ['$current_url' => 'https://x.test/p', '$request_method' => 'GET', '$request_path' => '/p', '$user_agent' => 'UA', '$ip' => '10.0.0.1'], 'request props, REMOTE_ADDR without trust_proxy');
check(RequestContext::build(null, 'visitor', $hdr, $req, [])['distinct_id'] === 'anon1', 'tracing header beats visitor id');
check(RequestContext::build(null, 'visitor', [], $req, [])['distinct_id'] === 'visitor', 'visitor id fallback');
$c = RequestContext::build('42', 'visitor', $hdr, $req, ['use_tracing_headers' => false, 'capture_user_context' => false]);
check($c['distinct_id'] === 'visitor' && $c['session_id'] === null, 'both switches off');
check(RequestContext::build(null, 'v', $hdr, $req, ['trust_proxy' => true])['properties']['$ip'] === '1.2.3.4', 'trust_proxy uses first X-Forwarded-For');
check(!isset(RequestContext::build(null, 'v', [], ['method' => 'GET'], [])['properties']['$ip']), 'missing props are omitted');

$a = Config::clientArgs(['api_key' => 'k', 'flush_at' => '5', 'flush_interval' => '2.5', 'debug' => true, 'max_retries' => null, 'only_evaluate_locally' => false]);
check($a['flushAt'] === 5 && $a['flushInterval'] === 2.5 && $a['debug'] === true && $a['onlyEvaluateLocally'] === false && !isset($a['maxRetries']), 'delivery options mapped');

echo $fails ? "$fails failed\n" : "ok\n";
exit($fails ? 1 : 0);
