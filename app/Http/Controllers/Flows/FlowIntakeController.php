<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flows;

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class FlowIntakeController
{
    public function create(Request $request): Response
    {
        $workflows = Workflow::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $requirements = $workflows
            ->map(static function (Workflow $w): array {
                $tags = Str::of((string) $w->name)
                    ->lower()
                    ->replace(['-', '—', '–'], ' ')
                    ->explode(' ')
                    ->filter(static fn (string $t): bool => $t !== '' && mb_strlen($t) > 3)
                    ->take(4)
                    ->values()
                    ->all();

                $group = (string) (Str::of((string) $w->name)->explode(' ')->first() ?? 'Geral');

                return [
                    'id' => (int) $w->id,
                    'title' => (string) $w->name,
                    'description' => $w->description,
                    'tags' => $tags,
                    'group' => $group,
                ];
            })
            ->values()
            ->all();

        $popularRequirementIds = array_slice(array_map(
            static fn (array $r): int => (int) $r['id'],
            $requirements,
        ), 0, 6);

        return Inertia::render('flows/New', [
            'query' => $request->string('q')->toString(),
            'requirements' => $requirements,
            'popularRequirementIds' => $popularRequirementIds,
            'requestedProcesses' => [],
        ]);
    }
}
