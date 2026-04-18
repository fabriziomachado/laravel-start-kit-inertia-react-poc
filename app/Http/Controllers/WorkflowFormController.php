<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Enums\NodeRunStatus;
use Aftandilmmd\WorkflowAutomation\Enums\RunStatus;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowNodeRun;
use Aftandilmmd\WorkflowAutomation\Services\WorkflowService;
use App\Support\WorkflowFormProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class WorkflowFormController
{
    public function show(Request $request, string $token): Response
    {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);

        if ($nodeRun === null) {
            abort(404);
        }

        $main = $nodeRun->output['main'][0] ?? [];

        $fields = is_array($main['fields'] ?? null) ? $main['fields'] : [];

        $step = [
            'title' => (string) ($main['title'] ?? ''),
            'description' => $main['description'] ?? null,
            'submit_label' => (string) ($main['submit_label'] ?? 'Continuar'),
            'fields' => $fields,
        ];

        $run = $nodeRun->workflowRun;
        $run->refresh();
        $workflow = $run->workflow()->with(['nodes', 'edges'])->firstOrFail();

        $viewerName = ($u = $request->user()) !== null ? mb_trim((string) $u->name) : '';
        $viewerDisplayName = $viewerName !== '' ? $viewerName : null;

        return Inertia::render('workflow-forms/Show', [
            'token' => $token,
            'step' => $step,
            'run_id' => $nodeRun->workflow_run_id,
            'prefill' => WorkflowFormProgress::prefillForFields($run, $nodeRun, $fields),
            'previous_token' => WorkflowFormProgress::previousFormResumeToken($run, $workflow, $nodeRun),
            'progress' => [
                'workflow_name' => $workflow->name,
                'workflow_description' => is_string($workflow->description) && mb_trim($workflow->description) !== ''
                    ? mb_trim($workflow->description)
                    : null,
                'steps' => WorkflowFormProgress::timeline($run, $workflow, $nodeRun, $viewerDisplayName),
            ],
        ]);
    }

    public function submit(Request $request, string $token, WorkflowService $workflowService): RedirectResponse
    {
        $nodeRun = $this->findWaitingFormNodeRunByToken($token);

        if ($nodeRun === null) {
            abort(404);
        }

        $main = $nodeRun->output['main'][0] ?? [];
        $fields = $main['fields'] ?? [];

        $rules = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }

            $key = (string) $field['key'];
            $type = (string) ($field['type'] ?? 'string');
            $required = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $fieldRules = [];
            if ($required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules = match ($type) {
                'email' => array_merge($fieldRules, ['string', 'email']),
                'number' => array_merge($fieldRules, ['numeric']),
                'boolean' => $required
                    ? ['accepted']
                    : array_merge($fieldRules, ['boolean']),
                'textarea', 'string' => array_merge($fieldRules, ['string']),
                'select' => $this->rulesForSelect($fieldRules, $field),
                'choice_cards' => $this->rulesForChoiceCards($fieldRules, $field),
                default => array_merge($fieldRules, ['string']),
            };

            $rules[$key] = $fieldRules;
        }

        $validated = $request->validate($rules);

        $payload = $validated;
        $actor = $request->user();
        if ($actor !== null) {
            $payload['_submitted_by_id'] = (string) $actor->getKey();
            $name = mb_trim((string) $actor->name);
            if ($name !== '') {
                $payload['_submitted_by_name'] = $name;
            }
        }

        $run = $nodeRun->workflowRun;
        $run = $workflowService->resume($run, $token, $payload);
        $run->refresh();

        if ($run->isWaiting()) {
            $latest = $run->nodeRuns()
                ->where('status', NodeRunStatus::Completed)
                ->orderByDesc('id')
                ->get()
                ->first(static function (WorkflowNodeRun $nr): bool {
                    return isset($nr->output['main'][0]['resume_token']);
                });

            $nextToken = $latest?->output['main'][0]['resume_token'] ?? null;

            if (is_string($nextToken) && $nextToken !== $token) {
                return redirect()->route('workflow-forms.show', ['token' => $nextToken]);
            }
        }

        return redirect()
            ->route('flows.index')
            ->with('flows_success', 'Fluxo concluído. A execução aparece na listagem abaixo.');
    }

    private function findWaitingFormNodeRunByToken(string $token): ?WorkflowNodeRun
    {
        return WorkflowNodeRun::query()
            ->where('status', NodeRunStatus::Completed)
            ->with('workflowRun')
            ->get()
            ->first(static function (WorkflowNodeRun $run) use ($token): bool {
                if (($run->output['main'][0]['resume_token'] ?? null) !== $token) {
                    return false;
                }

                return $run->workflowRun?->status === RunStatus::Waiting;
            });
    }

    /**
     * @param  list<string>  $fieldRules
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function rulesForSelect(array $fieldRules, array $field): array
    {
        $opts = $this->selectOptionsFromCsv($field['options'] ?? '');

        if ($opts === []) {
            return array_merge($fieldRules, ['string']);
        }

        return array_merge($fieldRules, ['string', Rule::in($opts)]);
    }

    /**
     * @param  list<string>  $fieldRules
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function rulesForChoiceCards(array $fieldRules, array $field): array
    {
        $values = [];
        $choices = $field['choices'] ?? [];
        if (is_array($choices)) {
            foreach ($choices as $row) {
                if (is_array($row) && isset($row['value']) && is_string($row['value']) && $row['value'] !== '') {
                    $values[] = $row['value'];
                }
            }
        }

        if ($values === []) {
            return array_merge($fieldRules, ['string']);
        }

        return array_merge($fieldRules, ['string', Rule::in($values)]);
    }

    /**
     * @return list<string>
     */
    private function selectOptionsFromCsv(mixed $csv): array
    {
        if (! is_string($csv) || $csv === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }
}
