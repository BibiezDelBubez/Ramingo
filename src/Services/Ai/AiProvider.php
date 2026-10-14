<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/** Un fornitore di modelli linguistici. Ogni adattatore traduce la stessa richiesta nel formato del fornitore. */
interface AiProvider
{
    /** @throws AiException */
    public function complete(string $system, string $user, int $maxTokens = 1500): AiReply;
}
