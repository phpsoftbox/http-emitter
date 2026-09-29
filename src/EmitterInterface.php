<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Отправляет response клиенту.
     *
     * @param bool $withoutBody Отправить только status line и заголовки, без body. Нужен для ответа на HEAD:
     *                          заголовки (включая Content-Length) остаются как у GET, а тело не передаётся.
     */
    public function emit(ResponseInterface $response, bool $withoutBody = false): void;
}
