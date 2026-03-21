<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $user = $_SESSION['user'] ?? null;

        ob_start();
        $templateFile = APP_PATH . '/Views/' . $template . '.php';
        if (!file_exists($templateFile)) {
            throw new \RuntimeException("Vista no encontrada: $template");
        }
        require $templateFile;
        $content = ob_get_clean();

        $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            echo $content;
            return;
        }
        require $layoutFile;
    }

    public static function partial(string $template, array $data = []): void
    {
        extract($data);
        $user = $_SESSION['user'] ?? null;
        $file = APP_PATH . '/Views/partials/' . $template . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $base = rtrim(getenv('APP_URL') ?: '', '/');
        return $base . '/assets/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $base = rtrim(getenv('APP_URL') ?: '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}
