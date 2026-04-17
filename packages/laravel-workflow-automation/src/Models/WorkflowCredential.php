<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

final class WorkflowCredential extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('workflow-automation.tables.credentials', 'workflow_credentials');
    }

    public function setDataAttribute(array $value): void
    {
        $this->attributes['data'] = Crypt::encryptString(json_encode($value));
    }

    public function getDataAttribute(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return json_decode(Crypt::decryptString($value), true);
    }

    public function getDecryptedData(): array
    {
        return $this->data ?? [];
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
