<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Flow\DriverInterface;

/**
 * Cooperative generator jobs with stream_select() readiness.
 *
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
final class StreamSelectDriver implements DriverInterface
{
    use StreamDriverTrait;

    private function pollStreams(array $read, array $write, ?float $timeoutSeconds): array
    {
        $except = [];
        $selectRead = array_values($read);
        $selectWrite = array_values($write);
        // Keep the legacy select slice bounded even when there is no deadline.
        $microseconds = (int) clamp(($timeoutSeconds ?? 0.02) * 1_000_000, 1, 20_000);

        $selected = @stream_select($selectRead, $selectWrite, $except, 0, $microseconds);
        if ($selected === false || $selected === 0) {
            return [[], []];
        }

        $readyRead = [];
        foreach ($selectRead as $streamResource) {
            $readyRead[(int) $streamResource] = true;
        }
        $readyWrite = [];
        foreach ($selectWrite as $streamResource) {
            $readyWrite[(int) $streamResource] = true;
        }

        return [$readyRead, $readyWrite];
    }
}
