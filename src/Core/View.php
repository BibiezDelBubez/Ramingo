<?php
declare(strict_types=1);

namespace Ramingo\Core;

/**
 * Motore di template PHP puro. Le viste ricevono solo dati già pronti:
 * nessuna query o logica di business dentro i template.
 */
final class View
{
    public function __construct(private readonly string $dir)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->partial($layout, $data + ['content' => $content]);
    }

    /** Rende un frammento; usabile anche dentro le viste via $view->partial(). */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista non trovata: $template");
        }
        $data['view'] = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
