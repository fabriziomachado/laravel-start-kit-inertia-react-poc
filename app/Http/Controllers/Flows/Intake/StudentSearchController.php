<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flows\Intake;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StudentSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim()->toString();

        if (mb_strlen($q) < 3) {
            return response()->json([
                'student' => null,
                'pendencies' => [],
                'override_status' => 'none',
            ]);
        }

        $enrollmentCode = '20240001';

        $student = [
            'id' => 1,
            'code' => $enrollmentCode,
            'name' => 'Maria Ferreira',
            'email' => 'maria.ferreira@example.test',
            'status' => 'Ativo',
            'course' => 'Administração',
            'semester' => '3º',
            'unit' => 'Unidade Centro',
            'avatar_url' => 'https://i.pravatar.cc/320',
            'cpf' => '123.456.789-00',
        ];

        $normalized = Str::of($q)->lower()->toString();
        $hasFinancialPendency = str_contains($normalized, 'fin')
            || str_contains($normalized, 'deb')
            || str_contains($normalized, 'div');
        $hasAcademicPendency = str_contains($normalized, 'acad') || str_contains($normalized, 'doc');

        $pendencies = [];
        if ($hasFinancialPendency) {
            $pendencies[] = [
                'id' => 'p-fin-1',
                'type' => 'financial',
                'summary' => 'Pendência financeira em aberto.',
                'amount' => 1250.50,
            ];
        }
        if ($hasAcademicPendency) {
            $pendencies[] = [
                'id' => 'p-acad-1',
                'type' => 'academic',
                'summary' => 'Documentação pendente para regularização.',
            ];
        }

        return response()->json([
            'student' => $student,
            'pendencies' => $pendencies,
            'override_status' => 'none',
        ]);
    }
}
