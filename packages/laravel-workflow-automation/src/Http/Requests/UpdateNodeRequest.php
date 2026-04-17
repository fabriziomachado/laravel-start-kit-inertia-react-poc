<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
        ];
    }
}
