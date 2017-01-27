<?php

declare(strict_types=1);

namespace Jfalque\HttpMock\Guzzle\Exception;

use GuzzleHttp\Exception\GuzzleException;
use Jfalque\HttpMock\Exception\Exception as HttpMockException;

/**
 * Interface implemented by all HttpMock Guzzle integration exceptions.
 */
interface Exception extends HttpMockException, GuzzleException
{
}
