# Driver

Railway FBP embark multiple drivers.

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

## Swoole Driver

To use Swoole Driver, you have to add the extension with your current running PHP

```bash
pecl install swoole
```

More documentation can be found [https://www.swoole.co.uk](https://www.swoole.co.uk)

## ReactPHP Driver

To use ReactPHP Driver, you have to require the library with composer

```bash
composer require react/event-loop
```

More documentation can be found [https://reactphp.org](https://reactphp.org)

## Make your custom driver

You can make your custom driver by implementing `RFBP\DriverInterface`