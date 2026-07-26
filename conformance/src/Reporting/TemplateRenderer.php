<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use RuntimeException;
use Throwable;
use function is_file;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function sprintf;

/**
 * Render a plain PHP template file to a string.
 *
 * The report is static HTML with no client-side state, so a templating engine
 * would only add a dependency and a second syntax; PHP is already a template
 * language. Templates live next to the stylesheet in templates/ and see only
 * the variables handed to them.
 */
final class TemplateRenderer
{
    public function __construct(private readonly string $templateDir)
    {
    }

    /**
     * The full path of a file in the template directory.
     *
     * Also the way anything else in that directory — the stylesheet — is
     * located, so the directory itself stays known in one place only.
     */
    public function path(string $file): string
    {
        return $this->templateDir . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * @param array<string, mixed> $vars each key becomes a variable in the template
     */
    public function render(string $template, array $vars = []): string
    {
        $path = $this->path($template);

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template not found: %s', $path));
        }

        // A static closure, so a template can reach neither $this nor the
        // renderer's own locals. The `__` names are the only ones it could
        // collide with, and EXTR_SKIP keeps them from being overwritten.
        $render = static function (string $__path, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();

            try {
                require $__path;
            } catch (Throwable $error) {
                ob_end_clean();

                throw $error;
            }

            return (string) ob_get_clean();
        };

        return $render($path, $vars);
    }
}
