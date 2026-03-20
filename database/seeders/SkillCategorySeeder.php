<?php

namespace Database\Seeders;

use App\Models\SkillCategory;
use Illuminate\Database\Seeder;

class SkillCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'library-api-reference', 'name' => 'Library & API Reference', 'description' => 'Skills that provide library documentation, API specs, and reference material for agents.', 'icon' => 'book-open', 'color' => '#3b82f6', 'sort_order' => 1],
            ['slug' => 'verification', 'name' => 'Product Verification', 'description' => 'Skills that describe how to test or verify that code and outputs are correct.', 'icon' => 'check-circle', 'color' => '#22c55e', 'sort_order' => 2],
            ['slug' => 'data-analysis', 'name' => 'Data & Analysis', 'description' => 'Skills for fetching, processing, and analyzing data from various sources.', 'icon' => 'bar-chart', 'color' => '#8b5cf6', 'sort_order' => 3],
            ['slug' => 'business-automation', 'name' => 'Business Automation', 'description' => 'Skills that automate repetitive workflows and business processes.', 'icon' => 'zap', 'color' => '#f59e0b', 'sort_order' => 4],
            ['slug' => 'scaffolding-templates', 'name' => 'Scaffolding & Templates', 'description' => 'Skills for generating boilerplate code, project structures, and templates.', 'icon' => 'layout', 'color' => '#06b6d4', 'sort_order' => 5],
            ['slug' => 'code-quality-review', 'name' => 'Code Quality & Review', 'description' => 'Skills that enforce code quality standards and help review code.', 'icon' => 'shield-check', 'color' => '#ef4444', 'sort_order' => 6],
            ['slug' => 'cicd-deployment', 'name' => 'CI/CD & Deployment', 'description' => 'Skills for continuous integration, deployment pipelines, and release management.', 'icon' => 'rocket', 'color' => '#ec4899', 'sort_order' => 7],
            ['slug' => 'incident-runbooks', 'name' => 'Incident Runbooks', 'description' => 'Skills that guide incident response, debugging, and operational procedures.', 'icon' => 'alert-triangle', 'color' => '#f97316', 'sort_order' => 8],
            ['slug' => 'infrastructure-ops', 'name' => 'Infrastructure Ops', 'description' => 'Skills for managing infrastructure, monitoring, and system operations.', 'icon' => 'server', 'color' => '#64748b', 'sort_order' => 9],
            ['slug' => 'general', 'name' => 'General', 'description' => 'General-purpose skills that do not fit a specific category.', 'icon' => 'folder', 'color' => '#a1a1aa', 'sort_order' => 10],
        ];

        foreach ($categories as $data) {
            SkillCategory::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
