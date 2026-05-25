<?php

namespace Modules\ApprovalWorkflow\Providers;

use Illuminate\Support\ServiceProvider;

class ApprovalWorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php',
            'approvalworkflow'
        );
    }

    public function registerViews()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'approvalworkflow');
    }

    public function registerTranslations()
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'approvalworkflow');
    }
}
