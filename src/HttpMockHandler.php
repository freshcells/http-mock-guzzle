<?php

declare(strict_types=1);

namespace Jfalque\HttpMock\Guzzle;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\TransferStats;
use Jfalque\HttpMock\Guzzle\Exception\RuntimeException;
use Jfalque\HttpMock\ServerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Handler that returns responses from a {@see ServerInterface} instance.
 */
class HttpMockHandler
{
    /**
     * @var ServerInterface
     */
    private $server;

    public function __construct(ServerInterface $server)
    {
        $this->server = $server;
    }

    /**
     * Creates a default handler stack that uses the provided {@see ServerInterface} via a mock handler.
     *
     * @see HandlerStack::create()
     */
    public static function createStack(ServerInterface $server): HandlerStack
    {
        return HandlerStack::create(new self($server));
    }

    /**
     * Handles a request.
     *
     * @throws RuntimeException when an error occurs while trying to write the response into a stream or file
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        if (isset($options['delay'])) {
            usleep($options['delay'] * 1000);
        }

        try {
            if (null !== $response = $this->server->handle($request)) {
                $promise = new FulfilledPromise($response);
            } else {
                $promise = new RejectedPromise(sprintf(
                    'The server returned no response for request %s %s.',
                    $request->getMethod(),
                    $request->getUri()
                ));
            }
        } catch (\Exception $exception) {
            $promise = new RejectedPromise($exception);
        }

        return $promise->then(
            function (ResponseInterface $response) use ($request, $options) {
                if (isset($options['on_stats'])) {
                    \call_user_func($options['on_stats'], new TransferStats($request, $response, 0));
                }

                if (isset($options['sink'])) {
                    $sink = $options['sink'];
                    $contents = (string) $response->getBody();

                    if (\is_resource($sink)) {
                        // See http://php.net/manual/en/function.fwrite.php#96951
                        $bytes = @fwrite($sink, $contents);
                        if (false === $bytes || (($length = \strlen($contents)) !== $bytes && 0 !== $length)) {
                            throw RuntimeException::responseWriteError('resource');
                        }
                    } elseif (\is_string($sink)) {
                        if (false === @file_put_contents($sink, $contents)) {
                            throw RuntimeException::responseWriteError($sink);
                        }
                    } elseif ($sink instanceof StreamInterface) {
                        try {
                            $sink->write($contents);
                        } catch (\RuntimeException $exception) {
                            throw RuntimeException::responseWriteError('stream', $exception);
                        }
                    }
                }

                return $response;
            },
            function ($reason) use ($request, $options) {
                if (isset($options['on_stats'])) {
                    \call_user_func($options['on_stats'], new TransferStats($request, null, 0, $reason));
                }

                return new RejectedPromise($reason);
            }
        );
    }
}
