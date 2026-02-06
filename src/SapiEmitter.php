<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter;

use PhpSoftBox\Http\Emitter\Exception\HeadersAlreadySentException;
use Psr\Http\Message\ResponseInterface;

use function header;
use function headers_sent;
use function sprintf;

final class SapiEmitter implements EmitterInterface
{
    private const int BODY_CHUNK_SIZE = 65_536;

    /**
     * @throws HeadersAlreadySentException Если PHP уже начал отправку response.
     */
    public function emit(ResponseInterface $response): void
    {
        $sentAtFile = '';
        $sentAtLine = 0;
        if (headers_sent($sentAtFile, $sentAtLine)) {
            throw HeadersAlreadySentException::at($sentAtFile, $sentAtLine);
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
            foreach ($values as $value) {
                header($name . ': ' . $value, false, $statusCode);
            }
        }

        if (!$this->canHaveBody($statusCode)) {
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
