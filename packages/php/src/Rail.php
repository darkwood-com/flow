<?php

declare(strict_types=1);

namespace RFBP;

use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\DriverInterface;
use RFBP\Ip\IpTrait;
use Symfony\Component\Messenger\Envelope as Ip;
use Throwable;

class Rail
{
    use IpTrait;

    /**
     * @var array<mixed, bool>
     */
    private array $ipJobs;
    private ?Closure $pipeCallback = null;

    private DriverInterface $driver;

    public function __construct(
        private Closure $job,
        private ?int $scale = 1,
        ?DriverInterface $driver = null
    ) {
        $this->ipJobs = [];
        $this->driver = $driver ?? new AmpDriver();
    }

    public function __invoke(Ip $ip, Throwable $exception = null): void
    {
        // does the rail can scale ?
        if (count($this->ipJobs) >= $this->scale) {
            return;
        }

        // create an new job coroutine instance with Ip data if not exist
        $id = $this->getIpId($ip);
        if (!isset($this->ipJobs[$id])) {
            $this->ipJobs[$id] = true;

            $this->driver->coroutine($this->job, function (Throwable $exception = null) use ($ip, $id) {
                if ($this->pipeCallback) {
                    ($this->pipeCallback)($ip, $exception);
                }

                unset($this->ipJobs[$id]);
            })($ip->getMessage(), $exception);
        }
    }

    public function pipe(?Closure $callback = null): void
    {
        $this->pipeCallback = $callback;
    }
}
