<?php

namespace RFBP\Test;

use Amp\Loop;
use function Amp\delay;
use PHPUnit\Framework\TestCase;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;

class RailTest extends TestCase
{
    public function testIpWithoutId(): void
    {
        $job = static function() {};
        $ip = new Ip(new \stdClass());
        $rail = new Rail($job);
        $this->expectException(\RuntimeException::class);
        ($rail)($ip);
    }

    public function jobProvider(): array
    {
        $exception = new \RuntimeException('job error');

        return [
            'syncJob' => [static function(\ArrayObject $data) {
                $data['number'] = 5;
            }, 5, null],
            'asyncJob' => [static function(\ArrayObject $data): \Generator {
                yield delay(10);
                $data['number'] = 12;
            }, 12, null],
            'syncExceptionJob' => [static function() use ($exception) {
                throw $exception;
            }, 0, $exception],
            'asyncExceptionJob' => [static function() use ($exception): \Generator {
                yield delay(10);
                throw $exception;
            }, 0, $exception],
        ];
    }

    /**
     * @dataProvider jobProvider
     * @param \Closure $job
     * @param int $resultNumber
     * @param \Throwable $exception
     */
    public function testSyncJob($job, int $resultNumber, ?\Throwable $resultException): void
    {
        $ip = Ip::wrap(new \ArrayObject(['number' => 0]), [new IPidStamp('ip_id')]);
        $rail = new Rail($job);
        $rail->pipe(function(IP $ip, ?\Throwable $exception) use ($resultNumber, $resultException) {
            self::assertSame($resultNumber, $ip->getMessage()['number']);
            self::assertSame($resultException, $exception);
        });
        ($rail)($ip);

        Loop::run();
    }
}
