---
title: "Driver"
description: "Driver."
lead: "Driver."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 30
toc: true
---

# Driver

Flow embark multiple drivers.

## Coroutine

Drivers are useful to essentially provide asynchronous programming by using [coroutines](https://en.wikipedia.org/wiki/Coroutine).
Thus, this can be implemented in severals ways in most popular programming languages.

Coroutine are very similar to [threads](https://en.wikipedia.org/wiki/Thread_(computing)) and provide concurrency but not parallelism.
Advantage of using coroutine :
- this can be a preferred usage to thread for [hard-realtime](https://en.wikipedia.org/wiki/Real-time_computing#Hard) context.
- there is no need for synchronisation primitives such as mutexes, semaphore.
- it reduces the usage of system lock for sharing resources.

## Amp Driver

To use Amp Driver, you have to require the library with composer

```bash
composer require amphp/amp
```

More documentation can be found [https://amphp.org](https://amphp.org)

## Fiber Driver

More documentation can be found [https://www.php.net/manual/fr/language.fibers.php](https://www.php.net/manual/fr/language.fibers.php)

## ReactPHP Driver

To use ReactPHP Driver, you have to require the library with composer

```bash
composer require react/async
```

More documentation can be found [https://reactphp.org](https://reactphp.org)

## Spatie Driver

To use Spatie Driver, you have to require the library with composer

```bash
composer require spatie/async
```

More documentation can be found [https://github.com/spatie/async](https://github.com/spatie/async)

## Swoole Driver

Enable OpenSwoole in the Navi development shell via
`composer env -- --with-open-swoole` when Castor is already available.
This driver requires the extension at runtime.

More documentation can be found [https://openswoole.com](https://openswoole.com)

## Parallel Driver

To use Parallel Driver, you have to require the library with PECL

```bash
pecl install parallel
```

More documentation can be found [https://www.php.net/manual/en/book.parallel.php](https://www.php.net/manual/en/book.parallel.php)

## StreamSelect Driver

Native PHP Streams driver: cooperative **Generators** + `stream_select()`.

Jobs that need overlapping I/O must be Generators and **yield** wait tokens (Fibers are not used):

```php
use Flow\Driver\StreamSelectDriver;

$driver = new StreamSelectDriver();

// Inside a Generator job:
$ready = (yield $driver->waitReadable($stream, timeoutSeconds: 5.0));
$ready = (yield $driver->waitWritable($stream, timeoutSeconds: 5.0));
yield $driver->waitDelay(0.05);
```

`stream_set_blocking()` + `stream_select()` are the long-standing PHP async I/O primitives. The Driver only multiplexes readiness — HTTP, hashing, and persistence stay in Jobs. Pair with `MaxIpStrategy($n)` so at most `$n` IPs (and their stream waits) are in flight at a stage.

## Poll Driver

`Flow\Driver\PollDriver` uses PHP 8.6's [native polling API](https://www.php.net/releases/8.6/en.php).
Replace `new StreamSelectDriver()` with `new PollDriver()` for jobs using the
wait tokens above on supported descriptors, such as local stream sockets. PHP
selects the backend by default, or callers can pass an `Io\Poll\Backend`.

- `true` reports readiness. A readable stream may be at EOF.
- `false` reports a wait timeout. Public timeouts remain in seconds, and
  `null` means no deadline. The driver hands PHP a `Time\Duration` for bounded waits.
- `tick()` schedules callbacks at positive, finite intervals in seconds,
  including fractions, and returns a cancellation callback.
- Registration and polling failures propagate from `await()`. A failed polling
  batch releases the affected IPs. Scheduler interruption retries the wait against
  the original deadline.
- The poll context releases registrations before jobs resume. The caller
  owns the underlying streams and closes them, typically in `finally`.
- For a memory stream or a wrapper without an OS descriptor, check backend
  support before switching from another driver.

PHP's buffered bytes are considered for read readiness. `FiberDriver` remains
the default. The ReactPHP, AMPHP, OpenSwoole, Parallel and TrueAsync drivers
still serve their existing execution models.

### Structured stream errors

Configure streams you own with `StreamErrorMode::Exception` to inspect
`StreamException::getErrors()` and the `StreamErrorCode` enum in job code.
A job exception remains a Flow job-error value with the original exception
available through `getPrevious()`. Driver and listener failures still
propagate from `await()`. For an unsupported descriptor, the native poller
can throw `Io\Poll\InvalidHandleException` while PHP records
`StreamErrorCode::CastNotSupported` when the stream context enables
`StreamErrorStore::All`. Read `stream_last_errors()` immediately after the
failure. Flow leaves the caller's stream contexts and error settings intact.

Run `php examples/flow-poll.php` for local sockets, timeout and structured error
handling.

## TrueAsync Driver

Experimental driver backed by the [TrueAsync](https://github.com/true-async/php-async) `ext-async` extension. `FiberDriver` remains the default.

Requires a custom PHP 8.6+ build with the extension enabled. Composer suggests it as an optional dependency:

```bash
# ext-async is not installable via Composer — build PHP with the TrueAsync fork
# See https://true-async.github.io/download.html
```

Check availability before instantiating the driver:

```php
use Flow\Driver\TrueAsyncDriver;

if (TrueAsyncDriver::isSupported()) {
    $driver = new TrueAsyncDriver();
}
```

Usage:

```php
use Flow\Driver\TrueAsyncDriver;
use Flow\Flow\Flow;
use Flow\Ip;

$flow = (new Flow(
    job: fn (int $n) => $n * 2,
    driver: new TrueAsyncDriver(),
))->fn(fn (int $n) => $n + 1);

$flow(new Ip(21));
$flow->await();
```

Do not mix `TrueAsyncDriver` with `FiberDriver` in the same process — TrueAsync blocks userland `Fiber` while active.

A working example is available: `php examples/flow-true-async.php`

More documentation can be found [https://true-async.github.io](https://true-async.github.io)

## Make your custom driver

You can make your custom driver by implementing `Flow\DriverInterface`
