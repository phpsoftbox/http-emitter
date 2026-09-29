<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter;

use PhpSoftBox\Http\Emitter\Exception\HeadersAlreadySentException;
use PhpSoftBox\Http\Emitter\Exception\OutputAlreadySentException;
use Psr\Http\Message\ResponseInterface;

use function header;
use function headers_sent;
use function ob_get_length;
use function ob_get_level;
use function sprintf;
use function strtolower;

final class SapiEmitter implements EmitterInterface
{
    private const int BODY_CHUNK_SIZE = 65_536;

    /**
     * @throws HeadersAlreadySentException Если PHP уже начал отправку response.
     * @throws OutputAlreadySentException Если в буфере вывода уже есть данные.
     */
    public function emit(ResponseInterface $response, bool $withoutBody = false): void
    {
        $sentAtFile = '';
        $sentAtLine = 0;
        if (headers_sent($sentAtFile, $sentAtLine)) {
            throw HeadersAlreadySentException::at($sentAtFile, $sentAtLine);
        }

        // Вывод, накопленный в буфере до emit() (echo, var_dump, notice), оказался бы в начале body.
        $buffered = ob_get_level() > 0 ? (int) ob_get_length() : 0;
        if ($buffered > 0) {
            throw OutputAlreadySentException::withLength($buffered);
        }

        $statusCode = $response->getStatusCode();
        $statusLine = sprintf(
            'HTTP/%s %d %s',
            $response->getProtocolVersion(),
            $statusCode,
            $response->getReasonPhrase(),
        );

        header($statusLine, true, $statusCode);

        foreach ($response->getHeaders() as $name => $values) {
            // Первое значение заменяет заголовок, выставленный PHP заранее (например, Content-Type или
            // X-Powered-By), остальные добавляются. Set-Cookie всегда добавляется: cookie от setcookie() и
            // session_start() не должны теряться.
            $replace = strtolower((string) $name) !== 'set-cookie';
            foreach ($values as $value) {
                header($name . ': ' . $value, $replace, $statusCode);
                $replace = false;
            }
        }

        if ($withoutBody || !$this->canHaveBody($statusCode)) {
            return;
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(self::BODY_CHUNK_SIZE);
        }
    }

    private function canHaveBody(int $statusCode): bool
    {
        if ($statusCode >= 100 && $statusCode < 200) {
            return false;
        }

        return $statusCode !== 204
            && $statusCode !== 205
            && $statusCode !== 304;
    }
}
