<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Exception;

use RuntimeException;

use function sprintf;

/**
 * Перед emit() в буфере вывода уже есть данные: они попали бы в начало тела ответа.
 */
final class OutputAlreadySentException extends RuntimeException
{
    public static function withLength(int $length): self
    {
        return new self(sprintf(
            'Output buffer already contains %d byte(s); the response cannot be emitted without corrupting its body.',
            $length,
        ));
    }
}
