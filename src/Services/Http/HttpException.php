<?php
declare(strict_types=1);

namespace Ramingo\Services\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message, $status);
    }
}
