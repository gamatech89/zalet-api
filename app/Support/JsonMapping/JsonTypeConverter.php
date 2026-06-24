<?php

namespace App\Support\JsonMapping;

use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

class JsonTypeConverter
{
    private static array $subtypeMap = [];

    public static function fromArray(string $baseClass, array $data): object
    {
        $reflection = new ReflectionClass($baseClass);
        $typeAttribute = self::findJsonType($reflection);

        $discriminator = $data[$typeAttribute->field]
            ?? throw new InvalidArgumentException("Missing discriminator field: {$typeAttribute->field}");

        $targetClass = self::resolveSubType($baseClass, $typeAttribute, $discriminator);

        return self::hydrate($targetClass, $data);
    }

    public static function toArray(object $instance): array
    {
        $reflection = new ReflectionClass($instance);
        $typeAttribute = self::findJsonType($reflection);
        $subTypeAttribute = self::findJsonSubType($reflection);

        $result = [$typeAttribute->field => $subTypeAttribute->value];

        foreach (self::getAllProperties($reflection) as $property) {
            $property->setAccessible(true);

            $jsonKey = self::getJsonKeyFromProperty($property);
            $value = $property->getValue($instance);
            $fieldAttribute = self::getJsonFieldFromProperty($property);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif ($fieldAttribute?->arrayOf && is_array($value)) {
                $value = array_map(fn (object $item) => self::toArray($item), $value);
            }

            $result[$jsonKey] = $value;
        }

        return $result;
    }

    private static function hydrate(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return new $class;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $jsonKey = self::getJsonKeyFromParameter($parameter);
            $fieldAttribute = self::getJsonFieldFromParameter($parameter);

            if (! array_key_exists($jsonKey, $data)) {
                if ($parameter->isDefaultValueAvailable()) {
                    continue;
                }

                throw new InvalidArgumentException("Missing required field: {$jsonKey}");
            }

            $value = $data[$jsonKey];

            $parameterType = $parameter->getType();

            if (self::isBackedEnum($parameterType)) {
                $value = $parameterType->getName()::tryFrom($value)
                    ?? throw new InvalidArgumentException("Invalid enum value '{$value}' for {$parameterType->getName()}");
            } elseif ($fieldAttribute?->arrayOf && is_array($value)) {
                $value = array_map(
                    fn (array $item) => self::fromArray($fieldAttribute->arrayOf, $item),
                    $value,
                );
            }

            $arguments[$parameter->getName()] = $value;
        }

        return new $class(...$arguments);
    }

    private static function resolveSubType(string $baseClass, JsonType $typeAttribute, string $value): string
    {
        if (! isset(self::$subtypeMap[$baseClass])) {
            $map = [];

            foreach ($typeAttribute->subtypes as $subClass) {
                $reflection = new ReflectionClass($subClass);
                $subTypeAttribute = self::findJsonSubType($reflection);
                $map[$subTypeAttribute->value] = $subClass;
            }

            self::$subtypeMap[$baseClass] = $map;
        }

        return self::$subtypeMap[$baseClass][$value]
            ?? throw new InvalidArgumentException("Unknown subtype: {$value}");
    }

    private static function findJsonType(ReflectionClass $reflection): JsonType
    {
        $current = $reflection;

        while ($current) {
            $attributes = $current->getAttributes(JsonType::class);

            if (! empty($attributes)) {
                return $attributes[0]->newInstance();
            }

            $current = $current->getParentClass() ?: null;
        }

        throw new InvalidArgumentException("No #[JsonType] attribute found on {$reflection->getName()} or its parents");
    }

    private static function findJsonSubType(ReflectionClass $reflection): JsonSubType
    {
        $attributes = $reflection->getAttributes(JsonSubType::class);

        if (! empty($attributes)) {
            return $attributes[0]->newInstance();
        }

        throw new InvalidArgumentException("No #[JsonSubType] attribute found on {$reflection->getName()}");
    }

    private static function getAllProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        while ($reflection) {
            foreach ($reflection->getProperties() as $property) {
                if (! isset($properties[$property->getName()])) {
                    $properties[$property->getName()] = $property;
                }
            }

            $reflection = $reflection->getParentClass() ?: null;
        }

        return $properties;
    }

    private static function getJsonKeyFromParameter(ReflectionParameter $parameter): string
    {
        $fieldAttribute = self::getJsonFieldFromParameter($parameter);

        return $fieldAttribute?->name ?? $parameter->getName();
    }

    private static function getJsonFieldFromParameter(ReflectionParameter $parameter): ?JsonField
    {
        $attribute = $parameter->getAttributes(JsonField::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    private static function getJsonKeyFromProperty(ReflectionProperty $property): string
    {
        $fieldAttribute = self::getJsonFieldFromProperty($property);

        return $fieldAttribute?->name ?? $property->getName();
    }

    private static function getJsonFieldFromProperty(ReflectionProperty $property): ?JsonField
    {
        $attribute = $property->getAttributes(JsonField::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    private static function isBackedEnum(?ReflectionNamedType $type): bool
    {
        return $type !== null
            && ! $type->isBuiltin()
            && is_subclass_of($type->getName(), BackedEnum::class);
    }
}
