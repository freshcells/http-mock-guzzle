# HttpMock Guzzle Integration

[![Build status](https://travis-ci.org/julienfalque/http-mock-guzzle.svg?branch=master)](https://travis-ci.org/julienfalque/http-mock-guzzle)
[![Latest Stable Version](https://poser.pugx.org/jfalque/http-mock-guzzle/v/stable)](https://packagist.org/packages/jfalque/http-mock-guzzle)
[![License](https://poser.pugx.org/jfalque/http-mock-guzzle/license)](https://packagist.org/packages/jfalque/http-mock-guzzle)

Provides a [Guzzle](https://github.com/guzzle/guzzle) handler that integrates [HttpMock](https://github.com/julienfalque/http-mock).

## Installation

Run the following [Composer](https://getcomposer.org) command:

`$ composer require --dev jfalque/http-mock-guzzle`

## Usage

The easiest way to use the HttpMock handler is to create a default stack with the dedicated `HttpMockHandler::createStack()` method:

```php
use GuzzleHttp\Client;
use Jfalque\HttpMock\Guzzle\HttpMockHandler;
use Jfalque\HttpMock\Server;

$server = new Server();

$client = new Client([
    'handler' => HttpMockHandler::createStack($server),
]);
```

The handler can be created manually and used with an existing stack:

```php
$server = new Server();
$handler = new HttpMockHandler($server);
$stack->setHandler($handler);
```

Or injected in a client without using a stack:

```php
$server = new Server();
$client = new Client([
    'handler' => new HttpMockHandler($server),
]);
```
