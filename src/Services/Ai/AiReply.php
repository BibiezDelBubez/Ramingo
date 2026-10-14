<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/** Risposta di un modello, con fornitore e modello che l'hanno prodotta (tracciabilità). */
final class AiReply
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
    ) {
    }

    /** Identificativo salvato con le interpretazioni: "ai:anthropic:claude-...". */
    public function generator(): string
    {
        return 'ai:' . $this->provider . ':' . $this->model;
    }

    /**
     * Estrae il primo oggetto JSON dalla risposta (i modelli a volte aggiungono testo o ```json).
     * @throws AiException
     */
    public function json(): array
    {
        $start = strpos($this->text, '{');
        $end   = strrpos($this->text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new AiException('La risposta dell\'AI non è nel formato atteso.');
        }
        $data = json_decode(substr($this->text, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            throw new AiException('La risposta dell\'AI non è JSON valido.');
        }
        return $data;
    }
}
