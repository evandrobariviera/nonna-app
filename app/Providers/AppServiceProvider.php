<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLogin;
use App\Observers\ClientObserver;
use App\Observers\MacroPlanObserver;
use App\Observers\MeetingObserver;
use App\Observers\OpportunityObserver;
use App\Observers\TaskObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Client::observe(ClientObserver::class);
        Opportunity::observe(OpportunityObserver::class);
        Meeting::observe(MeetingObserver::class);
        MacroPlan::observe(MacroPlanObserver::class);

        // diffForHumans()/translatedFormat() usam o locale do Carbon, que não
        // segue sozinho o locale do Laravel (config('app.locale')) — sem isso,
        // "há 2 dias" sai como "2 days ago" mesmo com tudo em português.
        Carbon::setLocale(config('app.locale'));

        // Alimenta o Monitor de Trabalho — só usuário interno (guard "web"), o
        // Portal do cliente já rastreia login em contacts.portal_last_login_at.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                UserLogin::log($event->user);
            }
        });
    }
}
