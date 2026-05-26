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

To use Swoole Driver, you have to add the extension with your current running PHP

```bash
pecl install openswoole-22.0.0
```

More documentation can be found [https://openswoole.com](https://openswoole.com)

## Parallel Driver

To use Parallel Driver, you have to require the library with PECL

```bash
pecl install parallel
```

More documentation can be found [https://www.php.net/manual/en/book.parallel.php](https://www.php.net/manual/en/book.parallel.php)

## Make your custom driver

You can make your custom driver by implementing `Flow\DriverInterface`
