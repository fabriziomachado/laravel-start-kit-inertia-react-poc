<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flows\Intake;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class NegotiationArtifactsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'option_id' => ['required', 'string'],
        ]);

        return response()->json([
            'boleto_url' => url('/__mock/boleto/'.Str::uuid()),
            'pix_qr_url' => url('/__mock/pix/'.Str::uuid()),
            'contrato_url' => url('/__mock/contrato/'.Str::uuid()),
        ]);
    }
}
