# Changelog

## v1.1.x

- Add Flow\FlowInterface::do notation from https://github.com/fp4php/functional
- Update Flow\FlowInterface::fn to accept as first argument
    - Closure : it's the job itself
    - array : constructor arguments for Flow instanciation
    - array (view as shape) : configuration for Flow instanciation
    - FlowInterface : the FlowInterface instance itself

## v1.1.4

- Add generic templating
- Add Flow\Driver\RevoltDriver
- Add Flow\Driver\SpatieDriver
- Add more quality tools from https://github.com/IngeniozIT/php-skeleton

## v1.1.3

- Update DX for Flow\DriverInterface
  - Update `async` that now $onResolve get called with async $callback result or Flow\Exception as first argument
  - Update `tick` that now return a Closure to cleanup tick interval
- Add Flow\Driver\FiberDriver from https://github.com/jolicode/castor/blob/main/src/functions.php
- Upgrade to Symfony 6.3 and PHPUnit 10.3
- Refactor docs structure
- Refactor tooling from https://github.com/jolicode/castor

## v1.1.2

- Update to PHP 8.2
- Upgrade from amphp/amp v2 to amphp/amp v3 that use PHP Fibers
- Upgrade from react/event-loop v1 to reactphp/async v4 that use PHP Fibers
- Upgrade from Swoole v5 to Openswoole v22
- Rename function `corouting` to `async` in Flow\DriverInterface
- Add function `sleep` in Flow\DriverInterface

## v1.1.1

- Rename entire project from `Railway FBP` to `Flow`
- Bundle `Flow` to PHP monorepository
- Merge from `packages/symfony` to `packages/php` and make Flow [Symfony](https://symfony.com) friendly
- New DX interface `Flow\FlowInterface`
- Error managment is now integrated to Flow
- Remove context associated with processing IP
- Deprecate `Flow\Flow\SequenceFlow` in favor for `Flow\Flow\Flow`
- Deprecate `Flow\Flow\ErrorFlow` in favor for `Flow\Flow\Flow`
- Update `Flow\Flow\YFlow` and make it plain native
- Update `Flow\IP` that use readonly object
- New Flow logo

## v1.1.0

- Release MIT License
- Update dependencies to PHP 8.1

## v1.0.9

- Add `Flow\Flow\YFlow` that allows introduce recursivity into Flow language approach

## v1.0.8

- Add code of conduct

## v1.0.7

- Define Monads

## v1.0.6

- Add `Flow\TransportFlow`
- Flow can process multiple jobs in parallel

## v1.0.5

- Add Symfony integration
- Define monads

## v1.0.4

- Refactor structure
- Decouple integration

## v1.0.3

- Add `Flow\IpStrategy` add several Ip strategy for data processing

## v1.0.2

- Add `Flow\Driver\DriverInterface`
- Add `Flow\Driver\AmpDriver`
- Add `Flow\Driver\ReactDriver`
- Add `Flow\Driver\SwooleDriver`

## v1.0.1

- Add `Flow\Producer\CollectionConsumer`
- Add `Flow\Producer\CollectionProducer`
- Add `Flow\Transport\CollectionTransport`

## v1.0.0

- Initial release
