<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Ai\Transcription;
use Throwable;

final class TranscriptionController extends Controller
{
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:25600'],
        ]);

        try {
            $response = Transcription::fromUpload($request->file('audio'))->generate();

            return response()->json([
                'text' => $response->text,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Transcription failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
