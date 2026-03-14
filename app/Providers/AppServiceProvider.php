<?php

namespace App\Providers;

use App\Services\LLM\ProviderHealthMonitor;
use App\Services\Mcp\McpServerManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(McpServerManager::class);
        $this->app->singleton(ProviderHealthMonitor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
