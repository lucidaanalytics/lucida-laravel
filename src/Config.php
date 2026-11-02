<?php
declare(strict_types=1);

namespace Lucida\Laravel;

/** Maps config/lucida.php to Lucida\Client named constructor arguments. */
final class Config
{
    /** config key => Client argument (cast applied) for the optional delivery/flag settings. */
    private const OPTIONS = [
        'flush_at' => ['flushAt', 'intval'], 'max_batch_size' => ['maxBatchSize', 'intval'], 'max_queue_size' => ['maxQueueSize', 'intval'],
        'flush_interval' => ['flushInterval', 'floatval'], 'flag_poll_interval' => ['flagPollInterval', 'floatval'],
        'max_retries' => ['maxRetries', 'intval'], 'retry_delay' => ['retryDelay', 'floatval'], 'timeout' => ['timeout', 'floatval'],
        'debug' => ['debug', 'boolval'], 'only_evaluate_locally' => ['onlyEvaluateLocally', 'boolval'],
    ];

    public static function clientArgs(array $c): array
    {
        $args = ['apiKey' => (string) ($c['api_key'] ?? ''), 'disabled' => (bool) ($c['disabled'] ?? false)];
        if (!empty($c['host'])) {
            $args['host'] = rtrim((string) $c['host'], '/');
        }
        if (!empty($c['secret_key'])) {
            $args['secretKey'] = (string) $c['secret_key'];
        }
        if (!empty($c['fallbacks']) && is_array($c['fallbacks'])) {
            $args['fallbacks'] = $c['fallbacks'];
        }
        foreach (self::OPTIONS as $key => [$arg, $cast]) {
            if (isset($c[$key])) {
                $args[$arg] = $cast($c[$key]);
            }
        }
        return $args;
    }
}
