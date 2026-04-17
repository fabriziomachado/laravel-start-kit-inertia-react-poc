<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use ReflectionClass;

final class MetadataController extends Controller
{
    public function models(): JsonResponse
    {
        $models = [];

        foreach (config('workflow-automation.metadata.model_paths', [app_path('Models')]) as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $class = $this->resolveClassFromFile($file->getPathname(), $path);

                if (! $class || ! is_a($class, \Illuminate\Database\Eloquent\Model::class, true)) {
                    continue;
                }

                $models[] = $class;
            }
        }

        sort($models);

        return response()->json(['data' => $models]);
    }

    public function modelEvents(): JsonResponse
    {
        return response()->json([
            'data' => ['created', 'updated', 'deleted', 'restored', 'saving', 'saved', 'creating', 'deleting', 'forceDeleted'],
        ]);
    }

    private function resolveClassFromFile(string $path, string $basePath): ?string
    {
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

        if (! class_exists($fqcn)) {
            return null;
        }

        $ref = new ReflectionClass($fqcn);

        return $ref->isAbstract() ? null : $fqcn;
    }
}
