<p align="center">
  <a href="https://github.com/darkwood-fr/flow">
    <img src="docs/images/logo.png" width="auto" height="128px" alt="Flow">
  </a>
</p>

## Why ?

Flow concept aims to solve

- Adopt asynchronous as native implementation
- Build your code with functional programming and monads
- Assemble your code visually

## Installation

PHP 8.1 is the minimal version to use _Flow_ is 8.1  
The recommended way to install it through [Composer](http://getcomposer.org/) and execute

```bash
composer require darkwood/flow
```

## Usage

A working script is available in the bundled `examples` directory in `packages/php`

- Run Flow : `php examples/flow.php`
- Start Server : `php examples/server.php`  
  Start Client(s) : `php examples/client.php`

Messaging part require to install [Docker](https://www.docker.com) and execute `docker-compose up -d`

## Documentation

[https://darkwood-fr.github.io/flow](https://darkwood-fr.github.io/flow)

## License

_Flow_ is released under the MIT License.
