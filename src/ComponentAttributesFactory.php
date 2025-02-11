<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\TwigComponent;

use Symfony\UX\TwigComponent\Escaper\HtmlAttributeEscaperInterface;
use Symfony\UX\TwigComponent\Escaper\TwigHtmlAttributeEscaper;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

/**
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class ComponentAttributesFactory
{
    private readonly HtmlAttributeEscaperInterface $escaper;

    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function create(array $attributes = []): ComponentAttributes
    {
        return new ComponentAttributes($attributes, $this->getEscaper());
    }

    private function getEscaper(): HtmlAttributeEscaperInterface
    {
        return $this->escaper ??= new TwigHtmlAttributeEscaper($this->twig->getRuntime(EscaperRuntime::class));
    }
}
