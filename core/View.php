<?php
/**
 * Template rendering.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * A view template is a plain PHP file under views/. render() runs the
 * template with the controller's data in scope, captures its output, and then
 * runs views/layout.php with that output available as $content.
 *
 * Templates receive only what the controller passed them. They have no
 * database connection and no access to the session, which is what enforces
 * the rule that a view renders and nothing else.
 */

declare(strict_types=1);

final class View
{
    private const TEMPLATE_DIR = __DIR__ . '/../views/';
    private const LAYOUT       = 'layout';

    /**
     * Render a template inside the site layout and return the HTML.
     *
     * Returns a string rather than echoing so the front controller decides
     * when output begins — which matters because a redirect must be able to
     * send headers after a controller has run.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        $content = self::capture($template, $data);

        return self::capture(self::LAYOUT, $data + ['content' => $content]);
    }

    /**
     * Run one template and capture its output.
     *
     * @param array<string,mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        // Template names come from the controllers, never from a request. The
        // check costs nothing and means a future caller cannot turn one into
        // an arbitrary file read.
        if (preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $template) !== 1) {
            throw new InvalidArgumentException('Invalid view template name: ' . $template);
        }

        $path = self::TEMPLATE_DIR . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('View template not found: ' . $template);
        }

        // EXTR_SKIP so a data key can never overwrite $path or $template.
        extract($data, EXTR_SKIP);

        // The finally matters: if the template throws part-way through, an
        // abandoned buffer would be flushed by PHP at shutdown, producing a
        // half-drawn page with the fatal appended rather than a clean failure.
        ob_start();

        try {
            require $path;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }
    }
}
