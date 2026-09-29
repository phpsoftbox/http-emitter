<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Tests;

use PhpSoftBox\Http\Emitter\Exception\OutputAlreadySentException;
use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function ob_end_clean;
use function ob_start;

#[CoversClass(SapiEmitter::class)]
#[CoversMethod(SapiEmitter::class, 'emit')]
#[CoversClass(OutputAlreadySentException::class)]
final class SapiEmitterOutputBufferTest extends TestCase
{
    /**
     * Проверим, что непустой буфер вывода перед emit() даёт исключение, а не попадает в начало body.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function bufferedOutputCausesException(): void
    {
        ob_start();
        echo 'premature output';

        $this->expectException(OutputAlreadySentException::class);

        try {
            new SapiEmitter()->emit(new Response(body: 'payload'));
        } finally {
            ob_end_clean();
        }
    }
}
