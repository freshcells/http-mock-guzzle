<?php

declare(strict_types=1);

namespace Jfalque\HttpMock\Guzzle\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Jfalque\HttpMock\Guzzle\Exception\RuntimeException;
use Jfalque\HttpMock\Guzzle\HttpMockHandler;
use Jfalque\HttpMock\Server;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * {@see HttpMockHandler} tests.
 */
class HttpMockHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        if (\in_array(vfsStream::SCHEME, stream_get_wrappers(), true)) {
            vfsStreamWrapper::unregister();
        }
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvoke(): void
    {
        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response())
        );

        $result = $handler(new Request('GET', 'http://foo'), []);

        self::assertSame($response, $result->wait());
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithNoResponse(): void
    {
        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response())
        );

        $result = $handler(new Request('GET', 'http://bar'), []);

        $this->expectException(RejectionException::class);
        $this->expectExceptionMessage('The server returned no response for request GET http://bar.');

        $result->wait();
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithServerException(): void
    {
        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return(function () {
                    throw new \RuntimeException('Foo');
                })
        );

        $result = $handler(new Request('GET', 'http://foo'), []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Foo');

        $result->wait();
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithOnStatsOption(): void
    {
        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response())
        );

        $onStatsInvoked = false;

        $result = $handler($request = new Request('GET', 'http://foo'), [
            RequestOptions::ON_STATS => function ($stats) use (&$onStatsInvoked, $request, $response) {
                self::assertSame(1, \func_num_args());
                self::assertInstanceOf(TransferStats::class, $stats);
                self::assertSame($request, $stats->getRequest());
                self::assertSame($response, $stats->getResponse());
                self::assertNull($stats->getHandlerErrorData());
                /** @var int|float $transferTime */
                $transferTime = $stats->getTransferTime();
                self::assertSame(0.0, $transferTime);

                $onStatsInvoked = true;
            },
        ]);

        self::assertSame($response, $result->wait());
        self::assertTrue($onStatsInvoked);
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithOnStatsOptionError(): void
    {
        $exception = new \RuntimeException('Foo');

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return(function () use ($exception) {
                    throw $exception;
                })
        );

        $onStatsInvoked = false;

        $result = $handler($request = new Request('GET', 'http://foo'), [
            RequestOptions::ON_STATS => function ($stats) use (&$onStatsInvoked, $request, $exception) {
                self::assertSame(1, \func_num_args());
                self::assertInstanceOf(TransferStats::class, $stats);
                self::assertSame($request, $stats->getRequest());
                self::assertNull($stats->getResponse());
                self::assertSame($exception, $stats->getHandlerErrorData());
                /** @var int|float $transferTime */
                $transferTime = $stats->getTransferTime();
                self::assertSame(0.0, $transferTime);

                $onStatsInvoked = true;
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Foo');

        try {
            $result->wait();
        } catch (\Exception $exception) {
            self::assertTrue($onStatsInvoked);

            throw $exception;
        }
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsString(): void
    {
        $file = vfsStream::setup()->url().'/foo';

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => $file,
        ]);

        self::assertFileNotExists($file);

        self::assertSame($response, $result->wait());
        self::assertFileExists($file);
        self::assertSame('Foo', file_get_contents($file));
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsStringError(): void
    {
        $file = vfsStream::setup()->url().'/foo';
        file_put_contents($file, 'Bar');
        chmod($file, 0);

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => $file,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Could not write response into %s.', $file));

        try {
            $result->wait();
        } catch (\Exception $exception) {
            chmod($file, 0666);
            self::assertSame('Bar', file_get_contents($file));

            throw $exception;
        }
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsResource(): void
    {
        $file = vfsStream::setup()->url().'/foo';

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => fopen($file, 'w+'),
        ]);

        self::assertSame('', file_get_contents($file));

        self::assertSame($response, $result->wait());
        self::assertSame('Foo', file_get_contents($file));
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsResourceError(): void
    {
        $file = vfsStream::setup()->url().'/foo';
        file_put_contents($file, 'Bar');

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => fopen($file, 'r'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not write response into resource.');

        try {
            $result->wait();
        } catch (\Exception $exception) {
            self::assertSame('Bar', file_get_contents($file));

            throw $exception;
        }
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsStream(): void
    {
        $file = vfsStream::setup()->url().'/foo';

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $resource = fopen($file, 'w+');

        if (false === $resource) {
            throw new \RuntimeException("Could not open file {$file}");
        }

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => new Stream($resource),
        ]);

        self::assertSame('', file_get_contents($file));

        self::assertSame($response, $result->wait());
        self::assertSame('Foo', file_get_contents($file));
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithSinkOptionAsStreamError(): void
    {
        $file = vfsStream::setup()->url().'/foo';
        file_put_contents($file, 'Bar');

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response(200, [], 'Foo'))
        );

        $resource = fopen($file, 'r');

        if (false === $resource) {
            throw new \RuntimeException("Could not open file {$file}");
        }

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::SINK => new Stream($resource),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not write response into stream.');

        try {
            $result->wait();
        } catch (\Exception $exception) {
            self::assertSame('Bar', file_get_contents($file));

            throw $exception;
        }
    }

    /**
     * {@see HttpMockHandler::__invoke()} test.
     */
    public function testInvokeWithDelayOption(): void
    {
        ClockMock::withClockMock(true);
        ClockMock::register(self::class);

        $handler = new HttpMockHandler(
            (new Server())
                ->whenUri('http://foo')->return($response = new Response())
        );

        $start = microtime(true);

        $result = $handler(new Request('GET', 'http://foo'), [
            RequestOptions::DELAY => 2000,
        ]);

        self::assertSame($response, $result->wait());
        self::assertSame(2.0, microtime(true) - $start);
    }

    /**
     * {@see HttpMockHandler::createStack()} test.
     */
    public function testCreateStack(): void
    {
        $server = (new Server())
            ->whenUri('http://foo')
            ->andWhenBody('Foo')
            ->return($response = new Response())
        ;

        $stack = HttpMockHandler::createStack($server);

        self::assertSame($response, $stack(new Request('GET', 'http://foo', [], 'Foo'), [])->wait());
    }

    /**
     * {@see HttpMockHandler} test.
     */
    public function testWithClient(): void
    {
        $server = (new Server())
            ->whenUri('http://foo')
            ->andWhenBody('Foo')
            ->return($response = new Response())
        ;

        $client = new Client([
            'handler' => HandlerStack::create(new HttpMockHandler($server)),
        ]);

        self::assertSame($response, $client->get('http://foo', ['body' => 'Foo']));
        self::assertSame($response, $client->getAsync('http://foo', ['body' => 'Foo'])->wait());

        $request = new Request('GET', 'http://foo', [], 'Foo');

        self::assertSame($response, $client->send($request));
        self::assertSame($response, $client->sendAsync($request)->wait());

        $client = new Client([
            'handler' => HttpMockHandler::createStack($server),
        ]);

        self::assertSame($response, $client->get('http://foo', ['body' => 'Foo']));
        self::assertSame($response, $client->getAsync('http://foo', ['body' => 'Foo'])->wait());

        self::assertSame($response, $client->send($request));
        self::assertSame($response, $client->sendAsync($request)->wait());

        $client = new Client([
            'handler' => new HttpMockHandler($server),
        ]);

        self::assertSame($response, $client->get('http://foo', ['body' => 'Foo']));
        self::assertSame($response, $client->getAsync('http://foo', ['body' => 'Foo'])->wait());

        self::assertSame($response, $client->send($request));
        self::assertSame($response, $client->sendAsync($request)->wait());
    }
}
