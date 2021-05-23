# Symfony Integration

This provides integration with Symfony

## Installation

The recommended way to install it through [Composer](http://getcomposer.org/) and execute

```bash
composer require darkwood/railway-fbp-symfony
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

_darkwood/railway-fbp-symfony_ is released under the AGPL-3.0 License.
