<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Tests;

use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function http_response_code;
use function ob_get_clean;
use function ob_start;

#[CoversClass(SapiEmitter::class)]
#[CoversMethod(SapiEmitter::class, 'emit')]
final class SapiEmitterWithoutBodyTest extends TestCase
{
    /**
     * Проверим, что при withoutBody (ответ на HEAD) body не выводится, а статус ответа отправляется.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function withoutBodySkipsBody(): void
    {
        $response = new Response(200, ['Content-Length' => '7'], 'payload');

        ob_start();
        new SapiEmitter()->emit($response, withoutBody: true);
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame(200, http_response_code());
    }
}
