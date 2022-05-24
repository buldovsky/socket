<?php

namespace Bumax;

use Amp\Deferred;
use Amp\Loop as AmpLoop;
use Amp\Success;
use function Amp\asyncCall;
use function Amp\call;


class Loop
{

    /**
     * Запускаем главную петлю
     * @param $callback
     * @param int|null $timer таймер нужен для тестов (уже не надо)
     * @return void
     */
    static function run($callback, int $timer = null)
    {
        AmpLoop::run(function (...$args) use ($callback, $timer){

            $callback(...$args);

            if(!isset($timer)) return;
            AmpLoop::delay($timer, '\App\Loop::stop');
        });
    }

    /**
     * Алиас
     * @param $callback
     * @param ...$args
     * @return void
     */
    static function async($callback, ...$args)
    {
        asyncCall($callback, ...$args);
    }

    /**
     * Останавливаем основную петлю
     * @return void
     */
    static function stop()
    {
        AmpLoop::stop();
    }

}
