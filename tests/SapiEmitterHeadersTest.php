<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Tests;

use PhpSoftBox\Http\Emitter\SapiEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function explode;
use function fclose;
use function file_put_contents;
use function is_executable;
use function preg_grep;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * Заголовки проверяются через php-cgi: в CLI SAPI функция header() ничего не отправляет и headers_list() пуст.
 */
#[CoversClass(SapiEmitter::class)]
#[CoversMethod(SapiEmitter::class, 'emit')]
final class SapiEmitterHeadersTest extends TestCase
{
    /**
     * Проверим, что заголовок, выставленный PHP до emit(), заменяется значением из response, а не дублируется.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    public function headerSetBeforeEmitIsReplaced(): void
    {
        $headers = $this->emitViaCgi(<<<'PHP'
            header('Content-Type: text/html');

            $response = new PhpSoftBox\Http\Message\Response(200, ['Content-Type' => 'application/json']);
            PHP);

        self::assertSame(['Content-Type: application/json'], $this->linesOf($headers, 'Content-Type'));
    }

    /**
     * Проверим, что все значения многозначного заголовка отправляются, а не только последнее.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    public function allValuesOfHeaderAreEmitted(): void
    {
        $headers = $this->emitViaCgi(<<<'PHP'
            $response = new PhpSoftBox\Http\Message\Response(200, ['X-Value' => ['1', '2']]);
            PHP);

        self::assertSame(['X-Value: 1', 'X-Value: 2'], $this->linesOf($headers, 'X-Value'));
    }

    /**
     * Проверим, что Set-Cookie из response добавляется к cookie, выставленной PHP, а не заменяет её.
     *
     * @see SapiEmitter::emit()
     */
    #[Test]
    public function setCookieFromPhpIsKept(): void
    {
        $headers = $this->emitViaCgi(<<<'PHP'
            setcookie('php', '1');

            $response = new PhpSoftBox\Http\Message\Response(200, ['Set-Cookie' => 'app=2']);
            PHP);

        self::assertSame(['Set-Cookie: php=1', 'Set-Cookie: app=2'], $this->linesOf($headers, 'Set-Cookie'));
    }

    /**
     * Выполняет код в php-cgi и возвращает блок заголовков его ответа. Код должен определить `$response`.
     */
    private function emitViaCgi(string $code): string
    {
        $cgi = dirname(PHP_BINARY) . '/php-cgi';
        if (!is_executable($cgi)) {
            self::markTestSkipped('Для проверки заголовков нужен php-cgi рядом с PHP_BINARY.');
        }

        $script = tempnam(sys_get_temp_dir(), 'psb-emitter');
        self::assertIsString($script);

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        file_put_contents(
            $script,
            "<?php\nrequire '{$autoload}';\n{$code}\nnew PhpSoftBox\\Http\\Emitter\\SapiEmitter()->emit(\$response);\n",
        );

        try {
            $process = proc_open([$cgi, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } finally {
            unlink($script);
        }

        return explode("\r\n\r\n", $output, 2)[0];
    }

    /**
     * @return list<string>
     */
    private function linesOf(string $headers, string $name): array
    {
        $lines = [];
        foreach (preg_grep('/^' . $name . ':/i', explode("\r\n", $headers)) ?: [] as $line) {
            // php-cgi может изменить регистр имени заголовка: сравниваем по каноническому имени.
            $lines[] = $name . substr($line, strlen($name));
        }

        return $lines;
    }
}
