<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\DataA;
use Flow\Examples\Model\DataB;
use Flow\Examples\Model\DataC;
use Flow\Examples\Model\DataD;
use Flow\ExceptionInterface;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use Flow\Job\ClosureJob;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};
printf("Use %s\n", $driver::class);
printf("Calculating:\n");
printf("- DataA(a, b, c): Job1((DataA->a + DataA->b))\n");
printf("- DataB(d, e): Job2(DataB->d * DataB->e)\n");
printf("- DataC(f)\n");

$job1 = static function (DataA $dataA) use ($driver): DataB {
    printf("*. #%d - Job 1 Calculating %d + %d\n", $dataA->id, $dataA->a, $dataA->b);

    // simulating calculating some "light" operation from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $d = $dataA->a + $dataA->b;

    // simulating 1 chance on 5 to produce an exception from the "light" operation
    if (1 === random_int(1, 5)) {
        throw new Error(sprintf('#%d - Failure when processing Job1', $dataA->id));
    }

    printf("*. #%d - Job 1 Result for %d + %d = %d and took %.01f seconds\n", $dataA->id, $dataA->a, $dataA->b, $d, $delay);

    return new DataB($dataA->id, $d, $dataA->c);
};

$job2 = new ClosureJob(static function (DataB $dataB) use ($driver): DataC {
    printf(".* #%d - Job 2 Calculating %d * %d\n", $dataB->id, $dataB->d, $dataB->e);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $f = $dataB->d * $dataB->e;

    // simulating 1 chance on 5 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 5)) {
        throw new Error(sprintf('#%d - Failure when processing Job2', $dataB->id));
    }

    printf(".* #%d - Job 2 Result for %d * %d = %d and took %.01f seconds\n", $dataB->id, $dataB->d, $dataB->e, $f, $delay);

    return new DataC($dataB->id, $f);
});

$job3 = static function (DataC $dataC): DataD {
    printf("** #%d - Job 3 Result is %d\n", $dataC->id, $dataC->f);

    return new DataD($dataC->id);
};

/**
 * @param Ip<Data> $ip
 */
$errorJob1 = static function (ExceptionInterface $exception): void {
    printf("*. %s\n", $exception->getMessage());
};

/**
 * @param Ip<Data> $ip
 */
$errorJob2 = static function (ExceptionInterface $exception): void {
    printf(".* %s\n", $exception->getMessage());
};

echo "begin - synchronous\n";
$asyncTask = static function ($job1, $job2, $job3, $errorJob1, $errorJob2, $driver) {
    echo "begin - flow asynchronous\n";

    $flow = (new FlowFactory())->create(static function () use ($job1, $job2, $job3, $errorJob1, $errorJob2) {
        yield [$job1, $errorJob1, new MaxIpStrategy(2)];
        yield [$job2, $errorJob2, new MaxIpStrategy(2)];
        yield $job3;
    }, ['driver' => $driver]);

    for ($id = 1; $id <= 5; $id++) {
        $ip = new Ip(new DataA($id, random_int(1, 10), random_int(1, 10), random_int(1, 5)));
        $flow($ip);
    }
    $flow->await();

    echo "ended - flow asynchronous\n";
};
$asyncTask($job1, $job2, $job3, $errorJob1, $errorJob2, $driver);
echo "ended - synchronous\n";
