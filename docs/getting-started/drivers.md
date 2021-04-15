# Drivers

Railway FBP embark multiple drivers

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

You can make your custom driver by implementing `RFBP\Driver\DriverInterface`