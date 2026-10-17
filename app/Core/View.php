<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderowanie widoków PHP z app/Views. Widok trafia do layoutu
 * jako zmienna $content. Dane w widokach wypisuj zawsze przez e().
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/public'): void
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture($layout, $data + ['content' => $content]);
    }

    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \LogicException("Brak widoku: {$template}");
        }
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

    /** Fragment widoku wewnątrz innego widoku: View::partial('partials/x', [...]) */
    public static function partial(string $template, array $data = []): void
    {
        echo self::capture($template, $data);
    }
}
