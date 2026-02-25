<?php
declare(strict_types=1);

namespace Vote\View;

final class Renderer
{
    public function __construct(
        private readonly string $viewsRoot
    ) {
    }

    public function render(string $template, array $data = []): string
    {
        $file = rtrim($this->viewsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException('Template introuvable: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}

