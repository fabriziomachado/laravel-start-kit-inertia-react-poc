<?php

declare(strict_types=1);

use Aftandilmmd\WorkflowAutomation\Models\Workflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('workflow-automation.tables.workflows', 'workflows');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Workflow::withoutEvents(function (): void {
            foreach (Workflow::query()->orderBy('id')->cursor() as $workflow) {
                $base = Str::slug($workflow->name) ?: 'workflow';
                $slug = $base;
                $i = 1;
                while (
                    Workflow::query()
                        ->where('slug', $slug)
                        ->where('id', '!=', $workflow->id)
                        ->exists()
                ) {
                    $slug = $base.'-'.$i;
                    $i++;
                }
                $workflow->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        $tableName = config('workflow-automation.tables.workflows', 'workflows');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
