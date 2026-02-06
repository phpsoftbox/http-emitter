<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Exception;

use RuntimeException;

use function sprintf;

final class HeadersAlreadySentException extends RuntimeException
{
    public static function at(string $file, int $line): self
    {
        if ($file === '') {
            return new self('Response headers have already been sent.');
        }

        return new self(sprintf(
            'Response headers have already been sent at %s:%d.',
            $file,
            $line,
        ));
    }
}
