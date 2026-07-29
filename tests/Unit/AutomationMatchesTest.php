<?php

namespace Tests\Unit;

use App\Models\Automation;
use App\Models\Task;
use Tests\TestCase;

class AutomationMatchesTest extends TestCase
{
    private function automation(array $triggerConfig): Automation
    {
        $automation = new Automation();
        $automation->forceFill([
            'trigger_type'   => 'status_changed',
            'trigger_config' => $triggerConfig,
        ]);

        return $automation;
    }

    private function task(string $destination): Task
    {
        $task = new Task();
        $task->forceFill(['destination' => $destination]);

        return $task;
    }

    public function test_matches_when_destination_filter_is_equal(): void
    {
        $automation = $this->automation(['to' => 'concluido', 'destination' => 'campanhas_patrocinadas']);
        $task = $this->task('campanhas_patrocinadas');

        $this->assertTrue($automation->matches('status_changed', ['to' => 'concluido'], $task));
    }

    public function test_does_not_match_when_destination_filter_differs(): void
    {
        $automation = $this->automation(['to' => 'concluido', 'destination' => 'campanhas_patrocinadas']);
        $task = $this->task('projeto_web');

        $this->assertFalse($automation->matches('status_changed', ['to' => 'concluido'], $task));
    }

    public function test_ignores_destination_filter_when_not_set(): void
    {
        $automation = $this->automation(['to' => 'concluido']);
        $task = $this->task('projeto_web');

        $this->assertTrue($automation->matches('status_changed', ['to' => 'concluido'], $task));
    }

    public function test_ignores_destination_filter_when_wildcard(): void
    {
        $automation = $this->automation(['to' => 'concluido', 'destination' => '*']);
        $task = $this->task('projeto_web');

        $this->assertTrue($automation->matches('status_changed', ['to' => 'concluido'], $task));
    }

    public function test_does_not_match_when_no_entity_given_but_destination_filter_is_set(): void
    {
        $automation = $this->automation(['to' => 'concluido', 'destination' => 'campanhas_patrocinadas']);

        $this->assertFalse($automation->matches('status_changed', ['to' => 'concluido'], null));
    }
}
