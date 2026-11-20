<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use DomainException;
use Flow\Exception\RuntimeException;
use Flow\FlowFactory;
use Flow\Ip;
use PHPUnit\Framework\TestCase;

final class PartialApplicationTest extends TestCase
{
    public function testChainsNativeClosuresAndPassesThePayload(): void
    {
        $result = null;
        $flow = (new FlowFactory())->create(static function () use (&$result) {
            yield self::offset(?, 1);
            yield self::multiply(multiplier: 2, number: ?);
            yield sprintf('%02d', ?);
            yield static function (string $value) use (&$result): void {
                $result = $value;
            };
        });

        $flow(new Ip(3));
        $flow->await();

        self::assertSame('08', $result);
    }

    public function testPreservesJobErrorSemantics(): void
    {
        $error = null;
        $flow = (new FlowFactory())->create(static function () use (&$error) {
            yield [self::offset(?, -1), static function (RuntimeException $failure) use (&$error): void {
                $error = $failure;
            }];
        });

        $flow(new Ip(0));
        $flow->await();

        self::assertInstanceOf(RuntimeException::class, $error);
        self::assertInstanceOf(DomainException::class, $error->getPrevious());
    }

    private static function offset(int $number, int $amount): int
    {
        return $number + $amount >= 0 ? $number + $amount : throw new DomainException('Negative result');
    }

    private static function multiply(int $number, int $multiplier): int
    {
        return $number * $multiplier;
    }
}
