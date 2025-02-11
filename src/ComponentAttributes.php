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

use Symfony\UX\StimulusBundle\Dto\StimulusAttributes;
use Symfony\UX\TwigComponent\Escaper\HtmlAttributeEscaperInterface;
use Symfony\WebpackEncoreBundle\Dto\AbstractStimulusDto;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @immutable
 */
final class ComponentAttributes implements \Stringable, \IteratorAggregate, \Countable
{
    private const NESTED_REGEX = '#^([\w-]+):(.+)$#';
    private const ALPINE_REGEX = '#^x-([a-z]+):[^:]+$#';
    private const VUE_REGEX = '#^v-([a-z]+):[^:]+$#';

    /** @var array<string,true> */
    private array $rendered = [];

    private readonly ?HtmlAttributeEscaperInterface $escaper;

    /**
     * @param array<string, string|bool> $attributes
     */
    public function __construct(
        private array $attributes,
        ?HtmlAttributeEscaperInterface $escaper = null,
    ) {
        // Third argument used as internal flag to prevent multiple deprecations
        if ((null === $this->escaper = $escaper) && 3 > func_num_args()) {
            trigger_deprecation('symfony/ux-twig-component', '2.24', 'Not passing an "%s" to "%s" is deprecated and will throw in 3.0.', HtmlAttributeEscaperInterface::class, self::class);
        }
    }

    public function __toString(): string
    {
        $attributes = '';

        foreach ($this->attributes as $key => $value) {
            if (isset($this->rendered[$key])) {
                continue;
            }

            if (false === $value) {
                continue;
            }

            if (
                str_contains($key, ':')
                && preg_match(self::NESTED_REGEX, $key)
                && !preg_match(self::ALPINE_REGEX, $key)
                && !preg_match(self::VUE_REGEX, $key)
            ) {
                continue;
            }

            if (null === $value) {
                trigger_deprecation('symfony/ux-twig-component', '2.8.0', 'Passing "null" as an attribute value is deprecated and will throw an exception in 3.0.');
                $value = true;
            }

            if (!\is_scalar($value) && !($value instanceof \Stringable)) {
                throw new \LogicException(\sprintf('A "%s" prop was passed when creating the component. No matching "%s" property or mount() argument was found, so we attempted to use this as an HTML attribute. But, the value is not a scalar (it\'s a "%s"). Did you mean to pass this to your component or is there a typo on its name?', $key, $key, get_debug_type($value)));
            }

            if (true === $value && str_starts_with($key, 'aria-')) {
                $value = 'true';
            }

            // Allowed characters in attribute names:
            // - common attribute names (HTML 5):
            //      id, class, style, title, lang, dir, role,...
            //      data-*, aria-*,
            //      xml:*, xmlns:*,
            // - special syntax names (Vue.js, Svelte, Alpine.js, ...)
            //      v-*, x-*, @*, :*
            if (!ctype_alpha(str_replace(['-', '_', ':', '@', '.'], '', $key))) {
                $key = $this->escaper?->escapeName($key) ?? $key;
            }

            if (true === $value) {
                $attributes .= ' '.$key;
            } else {
                $attributes .= ' '.\sprintf('%s="%s"', $key, $this->escaper?->escapeValue($value) ?? $value);
            }
        }

        return $attributes;
    }

    public function __clone(): void
    {
        $this->rendered = [];
    }

    public function render(string $attribute): ?string
    {
        if (null === $value = $this->attributes[$attribute] ?? null) {
            return null;
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (true === $value && str_starts_with($attribute, 'aria-')) {
            $value = 'true';
        }

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Can only get string attributes (%s is a "%s").', $attribute, get_debug_type($value)));
        }

        $this->rendered[$attribute] = true;

        return $value;
    }

    /**
     * @return array<string, string|bool>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Set default attributes. These are used if they are not already
     * defined.
     *
     * "class" and "data-controller" are special, these defaults are prepended to
     * the existing attribute (if available).
     */
    public function defaults(iterable $attributes): self
    {
        if ($attributes instanceof StimulusAttributes) {
            $attributes = $attributes->toEscapedArray();
        }

        if ($attributes instanceof \Traversable) {
            $attributes = iterator_to_array($attributes);
        }

        foreach ($this->attributes as $key => $value) {
            if (\in_array($key, ['class', 'data-controller', 'data-action'], true) && isset($attributes[$key])) {
                $attributes[$key] = "{$attributes[$key]} {$value}";

                continue;
            }

            $attributes[$key] = $value;
        }

        foreach (array_keys($this->rendered) as $attribute) {
            unset($attributes[$attribute]);
        }

        return new self($attributes, $this->escaper, true);
    }

    /**
     * Extract only these attributes.
     */
    public function only(string ...$keys): self
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if (\in_array($key, $keys, true)) {
                $attributes[$key] = $value;
            }
        }

        return new self($attributes, $this->escaper, true);
    }

    /**
     * Extract all but these attributes.
     */
    public function without(string ...$keys): self
    {
        $clone = clone $this;

        foreach ($keys as $key) {
            unset($clone->attributes[$key]);
        }

        return $clone;
    }

    public function add($stimulusDto): self
    {
        if ($stimulusDto instanceof AbstractStimulusDto) {
            trigger_deprecation('symfony/ux-twig-component', '2.9.0', 'Passing a StimulusDto to ComponentAttributes::add() is deprecated. Run "composer require symfony/stimulus-bundle" then use "attributes.defaults(stimulus_controller(\'...\'))".');
        } elseif ($stimulusDto instanceof StimulusAttributes) {
            trigger_deprecation('symfony/ux-twig-component', '2.9.0', 'Calling ComponentAttributes::add() is deprecated. Instead use "attributes.defaults(stimulus_controller(\'...\'))".');

            return $this->defaults($stimulusDto);
        } else {
            throw new \InvalidArgumentException(\sprintf('Argument 1 passed to "%s()" must be an instance of "%s" or "%s", "%s" given.', __METHOD__, AbstractStimulusDto::class, StimulusAttributes::class, get_debug_type($stimulusDto)));
        }

        $controllersAttributes = $stimulusDto->toArray();
        $attributes = $this->attributes;

        $attributes['data-controller'] = trim(implode(' ', array_merge(
            explode(' ', $attributes['data-controller'] ?? ''),
            explode(' ', $controllersAttributes['data-controller'] ?? [])
        )));
        unset($controllersAttributes['data-controller']);

        $clone = new self($attributes, $this->escaper, true);

        // add the remaining attributes for values/classes
        return $clone->defaults($controllersAttributes);
    }

    public function remove($key): self
    {
        $attributes = $this->attributes;

        unset($attributes[$key]);

        return new self($attributes, $this->escaper, true);
    }

    public function nested(string $namespace): self
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if (
                str_contains($key, ':')
                && preg_match(self::NESTED_REGEX, $key, $matches) && $namespace === $matches[1]
            ) {
                $attributes[$matches[2]] = $value;
            }
        }

        return new self($attributes, $this->escaper, true);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }

    public function has(string $attribute): bool
    {
        return \array_key_exists($attribute, $this->attributes);
    }

    public function count(): int
    {
        return \count($this->attributes);
    }
}
