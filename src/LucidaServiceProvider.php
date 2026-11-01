<?php
declare(strict_types=1);

namespace Lucida\Laravel;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lucida\Client;

final class LucidaServiceProvider extends ServiceProvider
{
    /** Queue events after which a worker flushes (a worker never reaches the HTTP terminate hook). */
    public const FLUSH_EVENTS = [JobProcessed::class, JobFailed::class, JobExceptionOccurred::class, Looping::class];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lucida.php', 'lucida');
        $this->app->singleton(Client::class, function ($app): Client {
            $cfg = $app['config']->get('lucida');
            $client = new Client(...Config::clientArgs($cfg));
            if (!empty($cfg['secret_key'])) {
                $client->reloadFeatureFlags();
            }
            return $client;
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/lucida.php' => config_path('lucida.php')], 'lucida-config');
        // @lucidaBootstrap emits flags for the visitor the middleware resolved on this request.
        Blade::directive('lucidaBootstrap', fn () => '<?php echo \\Lucida\\Laravel\\LucidaMiddleware::bootstrapHtml(); ?>');
        if ($this->app->runningInConsole() && config('lucida.flush_on_queue', true)) {
            Event::listen(self::FLUSH_EVENTS, fn () => $this->app->make(Client::class)->flush());
        }
    }
}
