<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\TwigComponent\Escaper;

use Symfony\UX\TwigComponent\Exception\RuntimeException;
use Twig\Runtime\EscaperRuntime;

/**
 * HTML attribute escaper using Twig EscaperRuntime.
 *
 * @internal
 */
final class TwigHtmlAttributeEscaper implements HtmlAttributeEscaperInterface
{
    public function __construct(
        private readonly EscaperRuntime $escaper,
    ) {
    }

    public function escapeName(string $name): string
    {
        if (ctype_alpha(\str_replace(['-', '_'], '', $name))) {
            return $name;
        }

        try {
            return $this->escaper->escape($name, 'html_attr');
        } catch (\Throwable $e) {
            throw new RuntimeException(\sprintf('An error occurred while escaping the attribute name "%s".', $name), 0, $e);
        }
    }

    public function escapeValue(string $value): string
    {
        if (ctype_alnum(\str_replace(['-', '_',], '', $value))) {
            return $value;
        }

        try {
            return $this->escaper->escape($value, 'html');
        } catch (\Throwable $e) {
            throw new RuntimeException(\sprintf('An error occurred while escaping the attribute value "%s".', $value), 0, $e);
        }
    }
}
