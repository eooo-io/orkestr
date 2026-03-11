<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'max_projects',
        'max_skills_per_project',
        'max_providers',
        'max_members',
        'marketplace_publish',
        'ai_generation',
        'webhook_access',
        'bundle_export',
        'repository_access',
        'priority_support',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'max_projects' => 'integer',
            'max_skills_per_project' => 'integer',
            'max_providers' => 'integer',
            'max_members' => 'integer',
            'marketplace_publish' => 'boolean',
            'ai_generation' => 'boolean',
            'webhook_access' => 'boolean',
            'bundle_export' => 'boolean',
            'repository_access' => 'boolean',
            'priority_support' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public function formattedMonthlyPrice(): string
    {
        return '$' . number_format($this->price_monthly / 100, 2);
    }

    public function formattedYearlyPrice(): string
    {
        return '$' . number_format($this->price_yearly / 100, 2);
    }
}
