<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:sync-ad-platforms')->dailyAt('05:30');
Schedule::command('campaigns:generate-insights')->dailyAt('08:00');
Schedule::command('financial:generate-contract-transactions')->dailyAt('06:00');
Schedule::command('automations:check-date-triggers')->dailyAt('07:00');
// Reunião terminou sem ATA → pendência "Registrar a ATA" pro organizador.
Schedule::command('meetings:ata-pendencies')->everyFifteenMinutes()->withoutOverlapping();
// Planejamento/campanha/projeto entrou em execução → aviso ao cliente, depois da espera.
Schedule::command('client-notices:send')->everyFiveMinutes()->withoutOverlapping();
// "Seu foco" da Dashboard — cedo pra planejar o dia, meio-dia pra ajustar a tarde.
Schedule::command('attention:generate-digests')->weekdays()->at('07:30')->withoutOverlapping();
Schedule::command('attention:generate-digests')->weekdays()->at('12:00')->withoutOverlapping();
// Central de Leads: IA avalia leads novos e conversas que andaram, pela régua de cada cliente.
Schedule::command('leads:qualify')->hourly()->withoutOverlapping();
