<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flows\Intake;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OverrideRequestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
            'simulate_approve' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'override_status' => $request->boolean('simulate_approve') ? 'approved' : 'requested',
        ]);
    }
}
