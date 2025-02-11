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

/**
 * Escapes HTML attribute names and values.
 *
 * Implementations should escape attribute names and values according
 * to the HTML specification. They can add additional escaping rules.
 *
 * @see https://html.spec.whatwg.org/multipage/syntax.html#attributes-2
 */
interface HtmlAttributeEscaperInterface
{
    /**
     * Escapes an HTML attribute name.
     */
    public function escapeName(string $name): string;

    /**
     * Escapes an HTML attribute value.
     */
    public function escapeValue(string $value): string;
}
