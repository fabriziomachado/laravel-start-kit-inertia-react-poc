<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EnsureLocalAdminSeeder::class);

        if (! app()->environment('production')) {
            $this->call(WorkflowFormWizardExampleSeeder::class);
        }

        if (app()->isLocal()) {
            $this->call(UserUpdatedEmailExampleWorkflowSeeder::class);
        }
    }
}
