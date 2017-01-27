<?php

declare(strict_types=1);

namespace Jfalque\HttpMock\Guzzle\Exception;

/**
 * Exception thrown if an error which can only be found on runtime occurs.
 */
class RuntimeException extends \RuntimeException implements Exception
{
    /**
     * Creates an instance for an error while trying to write a response into a stream or file.
     *
     * @param string          $target
     * @param \Exception|null $previous
     *
     * @return self
     */
    public static function responseWriteError(string $target, \Exception $previous = null): self
    {
        return new self(sprintf('Could not write response into %s.', $target), 0, $previous);
    }
}
