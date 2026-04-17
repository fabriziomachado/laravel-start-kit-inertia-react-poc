<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Registry;

use Aftandilmmd\WorkflowAutomation\Attributes\AsWorkflowNode;
use Aftandilmmd\WorkflowAutomation\Contracts\NodeInterface;
use Aftandilmmd\WorkflowAutomation\Enums\NodeType;
use Aftandilmmd\WorkflowAutomation\Exceptions\NodeNotFoundException;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class NodeRegistry
{
    /** @var array<string, array{class: class-string<NodeInterface>, label: string, type: NodeType}> */
    private array $nodes = [];

    /**
     * Manually register a node class under a given key.
     *
     * @param  class-string<NodeInterface>  $class
     */
    public function register(string $key, string $class): void
    {
        if (! is_a($class, NodeInterface::class, true)) {
            throw new InvalidArgumentException("{$class} does not implement NodeInterface.");
        }

        $attribute = $this->readAttribute($class);

        $this->nodes[$key] = [
            'class' => $class,
            'label' => $attribute?->label ?? $key,
            'type' => $attribute?->type ?? NodeType::Action,
        ];
    }

    /**
     * Register a node class using its #[AsWorkflowNode] attribute for key/label/type.
     *
     * @param  class-string<NodeInterface>  $class
     *
     * @throws InvalidArgumentException if class lacks the attribute or doesn't implement NodeInterface
     */
    public function registerClass(string $class): void
    {
        if (! is_a($class, NodeInterface::class, true)) {
            throw new InvalidArgumentException("{$class} does not implement NodeInterface.");
        }

        $attribute = $this->readAttribute($class);

        if (! $attribute) {
            throw new InvalidArgumentException("{$class} is missing the #[AsWorkflowNode] attribute.");
        }

        $this->nodes[$attribute->key] = [
            'class' => $class,
            'label' => $attribute->label ?: $attribute->key,
            'type' => $attribute->type,
        ];
    }

    /**
     * Scan a directory for classes with the #[AsWorkflowNode] attribute
     * and register them automatically.
     */
    public function discoverNodes(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach ($this->findPhpFiles($directory) as $file) {
            $class = $this->resolveClassFromFile($file);

            if (! $class || ! is_a($class, NodeInterface::class, true)) {
                continue;
            }

            $attribute = $this->readAttribute($class);

            if (! $attribute) {
                continue;
            }

            $this->nodes[$attribute->key] = [
                'class' => $class,
                'label' => $attribute->label ?: $attribute->key,
                'type' => $attribute->type,
            ];
        }
    }

    /**
     * Resolve a node instance from the container.
     */
    public function resolve(string $key): NodeInterface
    {
        if (! isset($this->nodes[$key])) {
            throw new NodeNotFoundException("Node not found: {$key}");
        }

        return app($this->nodes[$key]['class']);
    }

    /**
     * Get metadata for all registered nodes (for UI/API consumption).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->nodes as $key => $meta) {
            $instance = app($meta['class']);

            $result[] = [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type']->value,
                'input_ports' => $instance->inputPorts(),
                'output_ports' => $instance->outputPorts(),
                'config_schema' => $meta['class']::configSchema(),
                'output_schema' => $meta['class']::outputSchema(),
                'documentation' => method_exists($meta['class'], 'documentation') ? $meta['class']::documentation() : null,
            ];
        }

        return $result;
    }

    /**
     * Get nodes of a specific type.
     */
    public function ofType(NodeType $type): array
    {
        return array_values(array_filter($this->all(), fn (array $n) => $n['type'] === $type->value));
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    /**
     * Get the metadata for a single node key.
     *
     * @return array{class: class-string<NodeInterface>, label: string, type: NodeType}|null
     */
    public function getMeta(string $key): ?array
    {
        return $this->nodes[$key] ?? null;
    }

    private function readAttribute(string $class): ?AsWorkflowNode
    {
        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(AsWorkflowNode::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }

    /**
     * @return iterable<string>
     */
    private function findPhpFiles(string $directory): iterable
    {
        if (class_exists(Finder::class)) {
            yield from Finder::create()->files()->name('*.php')->in($directory);

            return;
        }

        // Fallback without symfony/finder
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function resolveClassFromFile(string|SplFileInfo $file): ?string
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;
        $contents = file_get_contents($path);

        if (! $contents) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
            $namespace = $m[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $m)) {
            $class = $m[1];
        }

        if (! $class) {
            return null;
        }

        $fqcn = $namespace ? "{$namespace}\\{$class}" : $class;

        // Ensure the class is loaded
        if (! class_exists($fqcn, true)) {
            require_once $path;
        }

        return class_exists($fqcn) ? $fqcn : null;
    }
}
