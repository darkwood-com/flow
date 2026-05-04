<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use Closure;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategy\MaxIpStrategy;
use Flow\IpStrategy\StackIpStrategy;

trait FlowTrait
{
    /**
     * @return array<array<mixed>>
     */
    protected static function matrix(Closure $datas): array
    {
        $drivers = [
            'amp' => static fn (): AmpDriver => new AmpDriver(),
            'fiber' => static fn (): FiberDriver => new FiberDriver(),
            'react' => static fn (): ReactDriver => new ReactDriver(),
            // 'spatie' => static fn (): SpatieDriver => new SpatieDriver(),
            // 'swoole' => static fn (): SwooleDriver => new SwooleDriver(),
        ];

        $strategies = [
            'linear' => static fn (): LinearIpStrategy => new LinearIpStrategy(),
            'max' => static fn (): MaxIpStrategy => new MaxIpStrategy(),
            'stack' => static fn (): StackIpStrategy => new StackIpStrategy(),
        ];

        $matrixDatas = [];
        foreach ($drivers as $keyDriver => $driverBuilder) {
            foreach ($strategies as $keyStrategy => $strategyBuilder) {
                $driver = $driverBuilder();
                $dataValues = $datas($driver, $strategyBuilder);
                foreach ($dataValues as $key => $values) {
                    $matrixDatas["{$keyDriver}.{$keyStrategy}.{$key}"] = [$driver, ...$values];
                }
            }
        }

        return $matrixDatas;
    }
}
