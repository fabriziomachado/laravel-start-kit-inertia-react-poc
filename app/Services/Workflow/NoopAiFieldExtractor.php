<?php

declare(strict_types=1);

namespace App\Services\Workflow;

final class NoopAiFieldExtractor implements AiFieldExtractor
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function extract(array $fields, string $freeText): array
    {
        return [];
    }
}
