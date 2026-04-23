<?php

declare(strict_types=1);

namespace App\Services\Workflow;

interface AiFieldExtractor
{
    public function isAvailable(): bool;

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed> field_key => value (tipos alinhados ao formulário)
     */
    public function extract(array $fields, string $freeText): array;
}
