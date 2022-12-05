---
title: "Symfony Integration"
description: "This provide integration in Symfony."
lead: "This provide integration in Symfony."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "integrations"
weight: 90
toc: true
---

# Symfony Integration

This provide integration with Symfony

## Installation

The recommended way to install it through [Composer](http://getcomposer.org/) and execute

```bash
composer require darkwood/flow-symfony
```

## Messaging support

To use symfony messenger integration

```bash
composer require symfony/messenger
```

## Usage

A working script is available in the bundled `examples` directory

- Start Server : `php examples/server.php`
- Start Client(s) : `php examples/client.php`

Messaging part require to install [Docker](https://www.docker.com) and execute `docker-compose up -d`

## License

_darkwood/flow-symfony_ is released under the MIT License.
