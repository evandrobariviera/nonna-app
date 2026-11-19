<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Task;
use App\Support\PageTitle;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class PageTitleTest extends TestCase
{
    private function openRoute(string $name, array $params = []): void
    {
        $route = (new Route('GET', 'x', fn () => null))->name($name);
        $request = Request::create('/x');
        $route->bind($request);
        foreach ($params as $key => $value) {
            $route->setParameter($key, $value);
        }
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
    }

    public function test_item_page_shows_type_and_name(): void
    {
        $this->openRoute('tasks.show', ['task' => new Task(['title' => 'Post carrossel Black Friday'])]);

        $this->assertSame('Tarefa: Post carrossel Black Friday · Nonna', PageTitle::forCurrentRoute());
    }

    public function test_list_page_shows_section(): void
    {
        $this->openRoute('macroplans.index');

        $this->assertSame('Planejamentos · Nonna', PageTitle::forCurrentRoute());
    }

    public function test_client_uses_display_name_internally(): void
    {
        $client = new Client(['company_name' => 'Padaria Pão Quente LTDA', 'nickname' => 'Pão Quente']);

        $this->openRoute('clients.show', ['client' => $client]);
        $this->assertSame('Cliente: Pão Quente · Nonna', PageTitle::forCurrentRoute());
    }

    public function test_missing_item_name_drops_the_colon(): void
    {
        $this->openRoute('tasks.show', ['task' => 'abc']);

        $this->assertSame('Tarefa · Nonna', PageTitle::forCurrentRoute());
    }

    public function test_unmapped_route_falls_back_to_section_then_brand(): void
    {
        $this->openRoute('tasks.algo-novo');
        $this->assertSame('Tarefas · Nonna', PageTitle::forCurrentRoute());

        $this->openRoute('coisa.inexistente');
        $this->assertSame('Nonna', PageTitle::forCurrentRoute());
    }

    public function test_portal_suffix_and_long_names_are_trimmed(): void
    {
        $this->openRoute('portal.meetings.show', ['meeting' => new Task(['title' => str_repeat('a', 80)])]);

        $title = PageTitle::forCurrentRoute(true);
        $this->assertStringEndsWith(' · Portal Nonna', $title);
        $this->assertStringStartsWith('Reunião: aaaa', $title);
        $this->assertLessThan(90, mb_strlen($title));
    }

    public function test_custom_title(): void
    {
        $this->assertSame('Minha tela · Nonna', PageTitle::custom('  Minha tela '));
    }
}
