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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Event\PostMountEvent;
use Symfony\UX\TwigComponent\Event\PreMountEvent;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class ComponentFactory implements ResetInterface
{
    private static $mountMethods = [];
    private static $preMountMethods = [];
    private static $postMountMethods = [];

    /**
     * @param array<string, array>        $config
     * @param array<class-string, string> $classMap
     */
    public function __construct(
        private ComponentTemplateFinderInterface $componentTemplateFinder,
        private ServiceLocator $components,
        private PropertyAccessorInterface $propertyAccessor,
        private EventDispatcherInterface $eventDispatcher,
        private array $config,
        private array $classMap,
    ) {
    }

    public function metadataFor(string $name): ComponentMetadata
    {
        if ($config = $this->config[$name] ?? null) {
            return new ComponentMetadata($config);
        }

        if ($template = $this->componentTemplateFinder->findAnonymousComponentTemplate($name)) {
            $this->config[$name] = [
                'key' => $name,
                'template' => $template,
            ];

            return new ComponentMetadata($this->config[$name]);
        }

        if ($mappedName = $this->classMap[$name] ?? null) {
            if ($config = $this->config[$mappedName] ?? null) {
                return new ComponentMetadata($config);
            }

            throw new \InvalidArgumentException(\sprintf('Unknown component "%s".', $name));
        }

        $this->throwUnknownComponentException($name);
    }

    /**
     * Creates the component and "mounts" it with the passed data.
     */
    public function create(string $name, array $data = []): MountedComponent
    {
        $metadata = $this->metadataFor($name);

        if ($metadata->isAnonymous()) {
            return $this->mountFromObject(new AnonymousComponent(), $data, $metadata);
        }

        return $this->mountFromObject($this->components->get($metadata->getName()), $data, $metadata);
    }

    /**
     * @internal
     */
    public function mountFromObject(object $component, array $data, ComponentMetadata $componentMetadata): MountedComponent
    {
        $originalData = $data;
        $data = $this->preMount($component, $data, $componentMetadata);

        $this->mount($component, $data);

        // set data that wasn't set in mount on the component directly
        foreach ($data as $property => $value) {
            if ($this->propertyAccessor->isWritable($component, $property)) {
                $this->propertyAccessor->setValue($component, $property, $value);

                unset($data[$property]);
            }
        }

        $postMount = $this->postMount($component, $data, $componentMetadata);
        $data = $postMount['data'];
        $extraMetadata = $postMount['extraMetadata'];

        // create attributes from "attributes" key if exists
        $attributesVar = $componentMetadata->getAttributesVar();
        $attributes = $data[$attributesVar] ?? [];
        unset($data[$attributesVar]);

        // ensure remaining data is scalar
        foreach ($data as $key => $value) {
            if ($value instanceof \Stringable) {
                $data[$key] = (string) $value;
            }
        }

        return new MountedComponent(
            $componentMetadata->getName(),
            $component,
            new ComponentAttributes(array_merge($attributes, $data)),
            $originalData,
            $extraMetadata,
        );
    }

    /**
     * Returns the "unmounted" component.
     *
     * @internal
     */
    public function get(string $name): object
    {
        $metadata = $this->metadataFor($name);

        if ($metadata->isAnonymous()) {
            return new AnonymousComponent();
        }

        return $this->components->get($metadata->getName());
    }

    private function mount(object $component, array &$data): void
    {
        if ($component instanceof AnonymousComponent) {
            $component->mount($data);

            return;
        }

        if (null === (self::$mountMethods[$component::class] ?? null)) {
            try {
                $mountMethod = self::$mountMethods[$component::class] = (new \ReflectionClass($component))->getMethod('mount');
            } catch (\ReflectionException) {
                self::$mountMethods[$component::class] = false;

                return;
            }
        }

        if (false === $mountMethod ??= self::$mountMethods[$component::class]) {
            return;
        }

        $parameters = [];
        foreach ($mountMethod->getParameters() as $refParameter) {
            if (\array_key_exists($name = $refParameter->getName(), $data)) {
                $parameters[] = $data[$name];
                // remove the data element so it isn't used to set the property directly.
                unset($data[$name]);
            } elseif ($refParameter->isDefaultValueAvailable()) {
                $parameters[] = $refParameter->getDefaultValue();
            } else {
                throw new \LogicException(\sprintf('%s::mount() has a required $%s parameter. Make sure to pass it or give it a default value.', $component::class, $name));
            }
        }

        $mountMethod->invoke($component, ...$parameters);
    }

    private function preMount(object $component, array $data, ComponentMetadata $componentMetadata): array
    {
        $event = new PreMountEvent($component, $data, $componentMetadata);
        $this->eventDispatcher->dispatch($event);
        $data = $event->getData();

        $methods = self::$preMountMethods[$component::class] ??= AsTwigComponent::preMountMethods($component::class);
        foreach ($methods as $method) {
            if (null !== $newData = $method->invoke($component, $data)) {
                $data = $newData;
            }
        }

        return $data;
    }

    /**
     * @return array{data: array<string, mixed>, extraMetadata: array<string, mixed>}
     */
    private function postMount(object $component, array $data, ComponentMetadata $componentMetadata): array
    {
        $event = new PostMountEvent($component, $data, $componentMetadata);
        $this->eventDispatcher->dispatch($event);
        $data = $event->getData();

        $methods = self::$postMountMethods[$component::class] ??= AsTwigComponent::postMountMethods($component::class);
        foreach ($methods as $method) {
            if (null !== $newData = $method->invoke($component, $data)) {
                $data = $newData;
            }
        }

        return [
            'data' => $data,
            'extraMetadata' => $event->getExtraMetadata(),
        ];
    }

    /**
     * @return never
     */
    private function throwUnknownComponentException(string $name): void
    {
        $message = \sprintf('Unknown component "%s".', $name);
        $lowerName = strtolower($name);
        $nameLength = \strlen($lowerName);
        $alternatives = [];

        foreach (array_keys($this->config) as $type) {
            $lowerType = strtolower($type);
            $lev = levenshtein($lowerName, $lowerType);

            if ($lev <= $nameLength / 3 || str_contains($lowerType, $lowerName)) {
                $alternatives[] = $type;
            }
        }

        if ($alternatives) {
            if (1 === \count($alternatives)) {
                $message .= ' Did you mean this: "';
            } else {
                $message .= ' Did you mean one of these: "';
            }

            $message .= implode('", "', $alternatives).'"?';
        } else {
            $message .= ' And no matching anonymous component template was found.';
        }

        throw new \InvalidArgumentException($message);
    }

    public function reset(): void
    {
        self::$mountMethods = [];
        self::$preMountMethods = [];
        self::$postMountMethods = [];
    }
}
