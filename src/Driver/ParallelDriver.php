<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use parallel\Runtime;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function array_key_exists;
use function count;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class ParallelDriver implements DriverInterface
{
    use DriverTrait;

    /**
     * @var array<mixed>
     */
    private array $ticks = [];

    public function __construct()
    {
        if (!class_exists(Runtime::class)) {
            throw new NativeRuntimeException('Parallel extension is not loaded. Suggest install it with pecl install parallel');
        }
    }

    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            $runtime = new Runtime('vendor/autoload.php');

            return $runtime->run(static function () use ($callback, $args) {
                try {
                    return $callback(...$args);
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            });
        };
    }

    public function defer(Closure $callback): mixed
    {
        $runtime = new Runtime('vendor/autoload.php');

        return $runtime->run(static function () use ($callback) {
            try {
                $result = null;
                $callback(
                    static function ($value) use (&$result) {
                        $result = $value;
                    },
                    static function ($fn, $next) {
                        $fn($next);
                    }
                );

                return $result;
            } catch (Throwable $exception) {
                return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            }
        });
    }

    public function await(array &$stream): void
    {
        $async = function ($isTick) use (&$parallelDatas) {
            return function (Closure|JobInterface $job) use (&$parallelDatas, $isTick) {
                return function (mixed $data) use (&$parallelDatas, $isTick, $job) {
                    $async = $this->async($job);

                    $parallel = $async($data);

                    $next = static function ($return) {};

                    $parallelDatas[] = [
                        'parallel' => $parallel,
                        'next' => static function ($return) use (&$next) {
                            $next($return);
                        },
                    ];

                    return static function (Closure $callback) use ($isTick, &$next) {
                        if ($isTick === false) {
                            $next = static function ($return) use ($callback) {
                                $callback($return);
                            };
                        }
                    };
                };
            };
        };

        $defer = static function ($isTick) use (&$parallelDatas) {
            return static function (Closure|JobInterface $job) use ($isTick, &$parallelDatas) {
                return static function (Closure $next) use ($isTick, $job, &$parallelDatas) {
                    $parallel = new Runtime('vendor/autoload.php');
                    $parallel->run(static function () use ($isTick, $job, $next) {
                        try {
                            $job(static function ($return) use ($isTick, $next) {
                                if ($isTick === false) {
                                    $next($return);
                                }
                            }, static function ($fn, $next) {
                                $fn($next);
                            });
                        } catch (Throwable $exception) {
                            return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                        }
                    });

                    $parallelDatas[] = [
                        'parallel' => $parallel,
                        'next' => static function ($return) {}, /*function ($return) use ($isTick, $next) {
                            if ($isTick === false) {
                                $next($return);
                            }
                        },*/
                    ];
                };
            };
        };

        $tick = 0;
        $parallelDatas = [];
        do {
            foreach ($this->ticks as [
                'interval' => $interval,
                'callback' => $callback,
            ]) {
                if ($tick % $interval === 0) {
                    $ip = new Ip();
                    $async(true)($callback)($ip->data);
                }
            }

            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $stream['dispatchers'][$index]->dispatch(new AsyncEvent(static function (Closure|JobInterface $job) use ($async) {
                        return $async(false)($job);
                    }, static function (Closure|JobInterface $job) use ($defer) {
                        return $defer(false)($job);
                    }, $stream['fnFlows'][$index]['job'], $nextIp, static function ($data) use (&$stream, $index, $nextIp) {
                        if ($data instanceof RuntimeException && array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                            $stream['fnFlows'][$index]['errorJob']($data);
                        } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                            $ip = new Ip($data);
                            $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                        }

                        $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                    }), Event::ASYNC);
                }
            }

            foreach ($parallelDatas as $i => $parallelData) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/11468
                if ($parallelData['parallel']->done()) {
                    $data = $parallelData['parallel']->value();
                    $parallelData['next']($data);
                    unset($parallelDatas[$i]);
                }
            }

            $tick++;
        } while ($this->countIps($stream['dispatchers']) > 0 or count($this->ticks) > 0);
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $i = count($this->ticks) - 1;
        $this->ticks[$i] = [
            'interval' => $interval,
            'callback' => $callback,
        ];

        return function () use ($i) {
            unset($this->ticks[$i]);
        };
    }
}
