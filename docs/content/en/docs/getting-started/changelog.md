---
title: "Changelog"
description: "Changelog."
lead: "Changelog."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 60
toc: true
---

### 1.1.3

- Upgrade to Symfony 6.3

### 1.1.2

- Update to PHP 8.2
- Upgrade from amphp/amp v2 to amphp/amp v3 that use PHP Fibers
- Upgrade from react/event-loop v1 to reactphp/async v4 that use PHP Fibers
- Upgrade from Swoole v5 to Openswoole v22
- Rename function `corouting` to `async` in Flow\DriverInterface
- Add function `sleep` in Flow\DriverInterface

### 1.1.1

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

### 1.1.0

- Release MIT License
- Update dependencies to PHP 8.1

### 1.0.9

- Add `Flow\Flow\YFlow` that allows introduce recursivity into Flow language approach

### 1.0.8

- Add code of conduct

### 1.0.7

- Define Monads

### 1.0.6

- Add `Flow\TransportFlow`
- Flow can process multiple jobs in parallel

### 1.0.5

- Add Symfony integration
- Define monads

### 1.0.4

- Refactor structure
- Decouple integration

### 1.0.3

- Add `Flow\IpStrategy` add several Ip strategy for data processing

### 1.0.2

- Add `Flow\Driver\DriverInterface`
- Add `Flow\Driver\AmpDriver`
- Add `Flow\Driver\ReactDriver`
- Add `Flow\Driver\SwooleDriver`

### 1.0.1

- Add `Flow\Producer\CollectionConsumer`
- Add `Flow\Producer\CollectionProducer`
- Add `Flow\Transport\CollectionTransport`

# 1.0.0

- Initial release : Flow, Supervisor, Client