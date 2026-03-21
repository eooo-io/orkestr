<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Get started with Orkestr. Perfect for individual developers exploring AI skill management.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_projects' => 3,
                'max_skills_per_project' => 10,
                'max_providers' => 2,
                'max_members' => 1,
                'marketplace_publish' => false,
                'ai_generation' => false,
                'webhook_access' => false,
                'bundle_export' => false,
                'repository_access' => false,
                'priority_support' => false,
                'sort_order' => 0,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'For professional developers who need full access to all providers, AI generation, and marketplace publishing.',
                'price_monthly' => 2000, // $20.00
                'price_yearly' => 19200, // $192.00 ($16/mo)
                'max_projects' => 0, // unlimited
                'max_skills_per_project' => 0, // unlimited
                'max_providers' => 6,
                'max_members' => 1,
                'marketplace_publish' => true,
                'ai_generation' => true,
                'webhook_access' => true,
                'bundle_export' => true,
                'repository_access' => true,
                'priority_support' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'teams',
                'name' => 'Teams',
                'description' => 'For teams that need shared skill libraries, role-based access, and centralized configuration management.',
                'price_monthly' => 1500, // $15.00 per seat
                'price_yearly' => 14400, // $144.00 per seat ($12/mo)
                'max_projects' => 0, // unlimited
                'max_skills_per_project' => 0, // unlimited
                'max_providers' => 6,
                'max_members' => 0, // unlimited (billed per seat)
                'marketplace_publish' => true,
                'ai_generation' => true,
                'webhook_access' => true,
                'bundle_export' => true,
                'repository_access' => true,
                'priority_support' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );
        }
    }
}
