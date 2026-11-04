<?php
// Boots the package in a real Laravel app (Orchestra Testbench) and exercises it end to end: `composer install && composer test:boot`.
require __DIR__ . '/../vendor/autoload.php';
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Lucida\Laravel\LucidaMiddleware;
use Lucida\Laravel\LucidaServiceProvider;

$app = Orchestra\Testbench\Foundation\Application::create(basePath: null);
$app['config']->set('lucida.api_key', 'pk_test');
$app['config']->set('lucida.host', 'http://127.0.0.1:9');
$app['config']->set('lucida.timeout', 1);
$app->register(LucidaServiceProvider::class);
$ok = 0; $fail = 0;
$check = function (string $what, bool $c) use (&$ok, &$fail) { $c ? $ok++ : $fail++; echo ($c ? "ok   " : "FAIL ") . "$what\n"; };

$client = $app->make(Lucida\Client::class);
$check('client singleton resolves', $client === $app->make(Lucida\Client::class));
$check('facade resolves the client', Lucida\Laravel\Facades\Lucida::getFacadeRoot() === $client);
$check('blade directive registered', str_contains(Blade::compileString('@lucidaBootstrap'), 'LucidaMiddleware::bootstrapHtml()'));
$check('config merged', $app['config']->get('lucida.cookie.name') === 'lc_id');

Route::get('/t', fn () => response('<p>' . LucidaMiddleware::bootstrapHtml() . '</p>'))->middleware(LucidaMiddleware::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$r = $kernel->handle($req = Illuminate\Http\Request::create('/t'));
$check('no consent: 200', $r->getStatusCode() === 200);
$check('no consent: no cookie written', count($r->headers->getCookies()) === 0);
$kernel->terminate($req, $r);

$r = $kernel->handle($req = Illuminate\Http\Request::create('/t', 'GET', [], ['lc_consent' => 'granted']));
$names = array_map(fn ($c) => $c->getName(), $r->headers->getCookies());
$check('consent: id cookie written', in_array('lc_id', $names, true));
$check('consent: bootstrap script rendered', str_contains($r->getContent(), '<script'));
$kernel->terminate($req, $r);
$app->instance('request', Illuminate\Http\Request::create('/bare'));
$check('no state leaks past a request (Octane)', LucidaMiddleware::bootstrapHtml() === '');
$check('queue flush listener registered (console)', $app['events']->hasListeners(Illuminate\Queue\Events\JobProcessed::class));
echo "$ok passed, $fail failed\n";
exit($fail ? 1 : 0);
