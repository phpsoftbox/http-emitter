<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Tests;

use PhpSoftBox\Http\Emitter\Exception\HeadersAlreadySentException;
use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function flush;
use function function_exists;
use function header_remove;
use function headers_sent;
use function http_response_code;
use function ob_flush;
use function ob_get_clean;
use function ob_start;
use function str_repeat;

#[CoversClass(SapiEmitter::class)]
#[CoversMethod(SapiEmitter::class, 'emit')]
final class SapiEmitterTest extends TestCase
{
    /**
     * Проверяет, что эмиттер пишет тело ответа и устанавливает исходный статус.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitWritesBodyAndHeaders(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $response = new Response(201, ['X-Test' => 'ok'], 'payload');

        ob_start();
        new SapiEmitter()->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', $output);
        self::assertSame(201, http_response_code());
    }

    /**
     * Проверяет сохранение статуса 409 при отправке Location для Inertia-ответа.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function inertiaLocationDoesNotReplaceConflictStatusWithRedirect(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $response = new Response(409, [
            'X-Inertia-Location' => '/orders',
            'Location'           => '/orders',
        ]);

        new SapiEmitter()->emit($response);

        self::assertSame(409, http_response_code());
    }

    /**
     * Проверяет сохранение штатного redirect-статуса при отправке Location.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function redirectLocationPreservesRedirectStatus(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $response = new Response(302, ['Location' => '/login']);

        new SapiEmitter()->emit($response);

        self::assertSame(302, http_response_code());
    }

    /**
     * Проверяет потоковый вывод seekable body с начала независимо от текущей позиции.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function seekableBodyIsStreamedFromBeginning(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $contents = str_repeat('stream-body-', 12_000);
        $body     = new Stream($contents);

        $body->read(100);

        ob_start();
        new SapiEmitter()->emit(new Response(body: $body));
        $output = ob_get_clean();

        self::assertSame($contents, $output);
    }

    /**
     * Проверяет, что информационный response не выводит ошибочно заданный body.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function informationalResponseDoesNotEmitBody(): void
    {
        self::assertSame('', $this->emitAndCapture(new Response(103, body: 'must not be emitted')));
    }

    /**
     * Проверяет, что response 204 No Content не выводит ошибочно заданный body.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function noContentResponseDoesNotEmitBody(): void
    {
        self::assertSame('', $this->emitAndCapture(new Response(204, body: 'must not be emitted')));
    }

    /**
     * Проверяет, что response 205 Reset Content не выводит ошибочно заданный body.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function resetContentResponseDoesNotEmitBody(): void
    {
        self::assertSame('', $this->emitAndCapture(new Response(205, body: 'must not be emitted')));
    }

    /**
     * Проверяет, что response 304 Not Modified не выводит ошибочно заданный body.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function notModifiedResponseDoesNotEmitBody(): void
    {
        self::assertSame('', $this->emitAndCapture(new Response(304, body: 'must not be emitted')));
    }

    /**
     * Проверяет явную ошибку вместо частичного ответа после начала вывода.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function alreadySentHeadersCauseException(): void
    {
        self::expectException(HeadersAlreadySentException::class);

        echo 'premature output';
        ob_flush();
        flush();

        self::assertTrue(headers_sent());

        new SapiEmitter()->emit(new Response());
    }

    private function emitAndCapture(Response $response): string
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        ob_start();
        new SapiEmitter()->emit($response);

        return (string) ob_get_clean();
    }
}
