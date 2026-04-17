<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Credentials;

final class CredentialTypeRegistry
{
    /** @var array<string, class-string<CredentialTypeInterface>> */
    private array $types = [];

    /**
     * @param  class-string<CredentialTypeInterface>  $class
     */
    public function register(string $class): void
    {
        $this->types[$class::getKey()] = $class;
    }

    /**
     * @return array<string, array{key: string, label: string, schema: array}>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->types as $key => $class) {
            $result[$key] = [
                'key' => $class::getKey(),
                'label' => $class::getLabel(),
                'schema' => $class::schema(),
            ];
        }

        return $result;
    }

    /**
     * @return array{key: string, label: string, schema: array}|null
     */
    public function get(string $key): ?array
    {
        if (! isset($this->types[$key])) {
            return null;
        }

        $class = $this->types[$key];

        return [
            'key' => $class::getKey(),
            'label' => $class::getLabel(),
            'schema' => $class::schema(),
        ];
    }
}
