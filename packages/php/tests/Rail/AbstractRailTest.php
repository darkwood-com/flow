<?php

declare(strict_types=1);

namespace RFBP\Test\Rail;

use PHPUnit\Framework\TestCase;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\IpStrategy\LinearIpStrategy;
use RFBP\IpStrategy\MaxIpStrategy;
use RFBP\IpStrategy\StackIpStrategy;

abstract class AbstractRailTest extends TestCase
{
    /**
     * @param array<array>      $datas
     * @param array<mixed>|null $mix
     *
     * @return array<array>
     */
    protected function matrix(array $datas, ?array $mix = null): array
    {
        if (null === $mix) {
            $drivers = [
                'amp' => static function () { return new AmpDriver(); },
                'react' => static function () { return new ReactDriver(); },
                'swoole' => static function () { return new SwooleDriver(); },
            ];

            $strategies = [
                'linear' => static function () { return new LinearIpStrategy(); },
                'max' => static function () { return new MaxIpStrategy(); },
                'stack' => static function () { return new StackIpStrategy(); },
            ];

            return $this->matrix($this->matrix($datas, $strategies), $drivers);
        }

        $mixDatas = [];
        foreach ($datas as $dataKey => $dataValues) {
            foreach ($mix as $mixKey => $mixValue) {
                $mixDatas["$mixKey.$dataKey"] = [$mixValue(), ...$dataValues];
            }
        }

        return $mixDatas;
    }
}
