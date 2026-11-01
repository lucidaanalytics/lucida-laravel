<?php
declare(strict_types=1);

namespace Lucida\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Lucida\Client;

/** @mixin Client */
final class Lucida extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
