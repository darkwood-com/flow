<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\RevoltDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Data;
use Flow\Examples\YFlowData;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;

$driver = match (3) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new RevoltDriver(),
    5 => new SpatieDriver(),
    6 => new SwooleDriver(),
};
printf("Use %s\n", $driver::class);

function factorial(int $n) {
	// echo $n; // for debug

	return ($n <= 1) ? 1 : $n * factorial($n - 1);
}

function Ywrap(callable $f, callable $wrapperFunc) {
	$U = fn ($x) => $x($x);
	return $U(fn ($x) => $f($wrapperFunc(fn ($y) => $U($x)($y))));
}

function memoWrapperGenerator(callable $f) {
	static $cache = [];

	return function ($y) use ($f, &$cache) {
		if (!isset($cache[$y])) {
			$cache[$y] = $f($y);
		}

		return $cache[$y];
	};
}

function Ymemo($f) {
	return Ywrap($f, 'memoWrapperGenerator');
}

function factorialGen(callable $func) {
	return function (int $n) use ($func) {
		// echo $n; // for debug

    	return ($n <= 1) ? 1 : $n * $func($n - 1);
	};
}

function factorialYMemo(int $n) {
	return Ymemo('factorialGen')($n);
}

/*
use Amp\Promise;
use Amp\Deferred;

function Ywrap(callable $f, callable $wrapperFunc) {
    $U = fn($x) => $x($x);
    return $U(fn($x) => $f($wrapperFunc(fn($y) => Promise\wait($U($x)($y)))));
}

function asyncWrapper(callable $f) {
    return function($y) use ($f) {
        $deferred = new Deferred();
        $deferred->resolve($f($y));  // Resolve immediately
        return $deferred->promise();
    };
}

function Ymemo($f) {
    return Ywrap($f, 'asyncWrapper');
}

function factorialGen(callable $func) {
    return function (int $n) use ($func) {
        $deferred = new Deferred();
        $result = ($n <= 1) ? 1 : $n * Promise\wait($func($n - 1));
        $deferred->resolve($result);  // Resolve immediately
        return $deferred->promise();
    };
}

function factorialYMemo(int $n) {
    return Promise\wait(Ymemo('factorialGen')($n));
}

// Usage
Amp\Loop::run(function() {
    $result = factorialYMemo(5);
    echo $result; // Expected: 120
});
*/

/*
use Amp\Promise;

class Flow {
    // ... other combinators ...

    public static function Y($f) {
        return (function ($x) use ($f) {
            return $f(function ($y) use ($x) {
                return Promise\wait($x($x)($y));
            });
        })(function ($x) {
            return $f(function ($y) use ($x) {
                return Promise\wait($x($x)($y));
            });
        });
    }

    // ... rest of the class ...
}

use Amp\Loop;

Loop::run(function() {
    $factorial = Flow::Y(function ($f) {
        return function ($x) use ($f) {
            return $x == 0 ? 1 : $x * $f($x - 1);
        };
    });

    echo $factorial(5); // Outputs: 120
});
*/

factorialYMemo(6) . ' ' . factorialYMemo(5);

$factorialJob = static function (YFlowData $data) use ($driver): void {
    printf("*. #%d - Job 1 : Calculating factorial(%d)\n", $data->id, $data->number);

    // simulating calculating some "light" operation from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $result = factorial($data->number);

    // simulating 1 chance on 5 to produce an exception from the "light" operation
    if (1 === random_int(1, 5)) {
        //throw new Error('Failure when processing "Job1"');
    }

    printf("*. #%d - Job 1 : Result for factorial(%d) = %d and took %.01f seconds\n", $data->id, $data->number, $result, $delay);
};

$factorialYMemoJob = static function (YFlowData $data) use ($driver): void {
    printf(".* #%d - Job 2 : Calculating factorialYMemo(%d)\n", $data->id, $data->number, $data->number);

    // simulating calculating some "heavy" operation from from 1 to 5 seconds
    $delay = random_int(1, 5);
    $driver->delay($delay);
    $result = factorialYMemo($data->number);

    // simulating 1 chance on 5 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 5)) {
        //throw new Error('Failure when processing "Job2"');
    }

    printf(".* #%d - Job 2 : Result for factorialYMemo(%d) = %d and took %.01f seconds\n", $data->id, $data->number, $result, $delay);
};

/**
 * @param Ip<YFlowData> $ip
 */
$factorialErrorJob = static function (Ip $ip, ExceptionInterface $exception): void {
    printf("*. #%d - Error Job : Exception %s\n", $ip->data->id, $exception->getMessage());

    $ip->data->number = null;
};

/**
 * @param Ip<YFlowData> $ip
 */
$factorialYMemoErrorJob = static function (Ip $ip, ExceptionInterface $exception): void {
    printf(".* #%d - Error Job : Exception %s\n", $ip->data->id, $exception->getMessage());

    $ip->data->number = null;
};

$flow = Flow::do(static function () use ($factorialJob, $factorialYMemoJob, $factorialErrorJob, $factorialYMemoErrorJob) {
    yield [$factorialJob, $factorialErrorJob, new MaxIpStrategy(2)];
    yield [$factorialYMemoJob, $factorialYMemoErrorJob, new MaxIpStrategy(2)];
}, ['driver' => $driver]);

$ipPool = new SplObjectStorage();

for ($i = 1; $i <= 5; $i++) {
    $ip = new Ip(new YFlowData($i, $i));
    $ipPool->offsetSet($ip, true);
    $flow($ip, static fn ($ip) => $ipPool->offsetUnset($ip));
}

$driver->tick(1, static function () use ($driver, $ipPool) {
    if ($ipPool->count() === 0) {
        $driver->stop();
    }
});
$driver->start();
