<?php

declare(strict_types=1);

namespace RFBP\Test;

use function Amp\delay;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use ArrayObject;
use Closure;
use Generator;
use RFBP\Rail;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IpIdStamp;
use Throwable;

class RailTest extends AsyncTestCase
{
    /**
     * @dataProvider jobProvider
     */
    public function testIpWithoutId(Closure $job): void
    {
        $ip = new Ip(new stdClass());
        $rail = new Rail($job);
        $this->expectException(RuntimeException::class);
        ($rail)($ip);

        Loop::run();
    }

    /**
     * @dataProvider jobProvider
     */
    public function testJob(Closure $job, int $resultNumber, ?Throwable $resultException): void
    {
        $ip = Ip::wrap(new ArrayObject(['number' => 0]), [new IpIdStamp('ip_id')]);
        $rail = new Rail($job);
        $rail->pipe(function (Ip $ip, ?Throwable $exception) use ($resultNumber, $resultException) {
            self::assertSame(ArrayObject::class, $ip->getMessage()::class);
            self::assertSame($resultNumber, $ip->getMessage()['number']);
            self::assertSame($resultException, $exception);
        });
        ($rail)($ip);

        Loop::run();
    }

    /**
     * @return array<array>
     */
    public function jobProvider(): array
    {
        $exception = new RuntimeException('job error');

        return [
            'syncJob' => [static function (ArrayObject $data) {
                $data['number'] = 5;
            }, 5, null],
            'asyncJob' => [static function (ArrayObject $data): Generator {
                yield delay(10);
                $data['number'] = 12;
            }, 12, null],
            'syncExceptionJob' => [static function () use ($exception) {
                throw $exception;
            }, 0, $exception],
            'asyncExceptionJob' => [static function () use ($exception): Generator {
                yield delay(10);
                throw $exception;
            }, 0, $exception],
        ];
    }
}
