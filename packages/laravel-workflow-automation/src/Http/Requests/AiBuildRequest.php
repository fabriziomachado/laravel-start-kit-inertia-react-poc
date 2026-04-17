<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AiBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'provider' => ['sometimes', 'string', 'max:50'],
            'model' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
