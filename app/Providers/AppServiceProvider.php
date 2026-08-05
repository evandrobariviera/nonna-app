<?php

namespace App\Providers;

use App\Models\Opportunity;
use App\Models\Task;
use App\Observers\OpportunityObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Opportunity::observe(OpportunityObserver::class);

        // diffForHumans()/translatedFormat() usam o locale do Carbon, que não
        // segue sozinho o locale do Laravel (config('app.locale')) — sem isso,
        // "há 2 dias" sai como "2 days ago" mesmo com tudo em português.
        Carbon::setLocale(config('app.locale'));
    }
}
