<?php

namespace App\Services\Documents;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Document;
use App\Models\Project;
use App\Services\AiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ferramentas do Agente Nonna para criar campanha (24/11): o que ele precisa saber além da
 * reunião e do planejamento para pensar como um estrategista de resposta direta.
 *
 *  - performance(): o que os anúncios do cliente entregaram de verdade nos últimos 90 dias
 *    (Central de Performance: gasto, resultados, custo por resultado, CTR; melhores e piores
 *    anúncios). O nome do anúncio costuma dizer o ângulo.
 *  - previousCampaigns(): documentos das outras campanhas do cliente (o que já foi tentado).
 *  - market(): pesquisa na internet (mercado, concorrentes, como o público fala, regras do
 *    setor). Fica guardada no documento por 30 dias para não pagar de novo.
 */
class CreativeContext
{
    public const RESEARCH_TRIGGER = 'documento:pesquisa_mercado';
    private const RESEARCH_DAYS   = 30;

    public function __construct(private AiService $ai) {}

    public function hasPerformance(Client $client): bool
    {
        return DB::connection('pgsql')->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->where('a.client_id', $client->id)
            ->where('s.snapshot_date', '>=', now()->subDays(90)->toDateString())
            ->exists();
    }

    public function performance(Client $client, int $days = 90): ?string
    {
        $db   = DB::connection('pgsql');
        $base = fn (string $level) => $db->table('ad_daily_snapshots as s')
            ->join('client_ad_accounts as a', 'a.id', '=', 's.client_ad_account_id')
            ->where('a.client_id', $client->id)
            ->where('s.entity_level', $level)
            ->where('s.snapshot_date', '>=', now()->subDays($days)->toDateString());
        $sums = 'sum(s.spend) as spend, sum(s.impressions) as imp, sum(s.clicks) as clk, sum(s.conversions) as conv';

        $byPlatform = $base('campaign')->groupBy('s.platform')->selectRaw("s.platform, {$sums}")->get();
        if ($byPlatform->isEmpty()) {
            return null;
        }
        $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $line  = function ($r) use ($money) {
            $ctr = $r->imp > 0 ? number_format(100 * $r->clk / $r->imp, 2, ',', '.') . '%' : '—';
            $cpa = $r->conv > 0 ? $money($r->spend / $r->conv) : 'sem resultado';
            return "gasto {$money($r->spend)} · {$this->int($r->conv)} resultados · custo por resultado {$cpa} · CTR {$ctr} · {$this->int($r->imp)} impressões";
        };
        $platform = fn ($p) => ['meta_ads' => 'Meta', 'google_ads' => 'Google'][$p] ?? $p;

        $out = ["Período: últimos {$days} dias. \"Resultado\" é a conversão configurada na conta (em geral, lead ou conversa)."];
        foreach ($byPlatform as $r) {
            $out[] = "- {$platform($r->platform)}: " . $line($r);
        }

        $campaigns = $base('campaign')->groupBy('s.platform', 's.entity_name')->selectRaw("s.platform, s.entity_name as name, {$sums}")
            ->orderByDesc('spend')->limit(8)->get();
        if ($campaigns->isNotEmpty()) {
            $out[] = "\nCampanhas que mais gastaram:";
            foreach ($campaigns as $r) {
                $out[] = "- [{$platform($r->platform)}] {$r->name}: " . $line($r);
            }
        }

        $ads = $base('ad')->groupBy('s.platform', 's.entity_id', 's.entity_name')
            ->selectRaw("s.platform, s.entity_name as name, {$sums}")
            ->havingRaw('sum(s.impressions) >= 1000')->get();
        if ($ads->isNotEmpty()) {
            $withConv = $ads->filter(fn ($r) => $r->conv >= 3)->sortBy(fn ($r) => $r->spend / $r->conv);
            if ($withConv->isNotEmpty()) {
                $out[] = "\nAnúncios com MENOR custo por resultado (o que funcionou; o nome costuma dizer o ângulo):";
                foreach ($withConv->take(6) as $r) {
                    $out[] = "- [{$platform($r->platform)}] {$r->name}: " . $line($r);
                }
                if ($withConv->count() > 8) {
                    $out[] = "\nAnúncios com MAIOR custo por resultado (o que não funcionou):";
                    foreach ($withConv->reverse()->take(4) as $r) {
                        $out[] = "- [{$platform($r->platform)}] {$r->name}: " . $line($r);
                    }
                }
            }
            $spentNoConv = $ads->filter(fn ($r) => $r->conv == 0)->sortByDesc('spend')->take(4);
            if ($spentNoConv->isNotEmpty()) {
                $out[] = "\nAnúncios que gastaram e não trouxeram resultado:";
                foreach ($spentNoConv as $r) {
                    $out[] = "- [{$platform($r->platform)}] {$r->name}: " . $line($r);
                }
            }
            $byCtr = $ads->filter(fn ($r) => $r->imp >= 2000)->sortByDesc(fn ($r) => $r->clk / max(1, $r->imp))->take(5);
            if ($byCtr->isNotEmpty()) {
                $out[] = "\nAnúncios que mais chamaram atenção (maior CTR):";
                foreach ($byCtr as $r) {
                    $out[] = "- [{$platform($r->platform)}] {$r->name}: " . $line($r);
                }
            }
        }

        return implode("\n", $out);
    }

    /** Outras campanhas do cliente com documento em campos (o que já foi tentado e como fechou). */
    public function previousCampaigns(Project $project, int $limit = 3): ?string
    {
        $others = Project::where('client_id', $project->client_id)
            ->where('id', '!=', $project->id)
            ->where('type', 'campanha')
            ->where('status', '!=', 'cancelado')
            ->latest('created_at')->limit(12)->get()
            ->map(fn (Project $p) => [$p, ClientDocuments::docFor($p)])
            ->filter(fn ($pair) => $pair[1] && $pair[1]->kind === 'campanha')
            ->take($limit);
        if ($others->isEmpty()) {
            return null;
        }

        return $others->map(function ($pair) {
            [$p, $doc] = $pair;
            return "### {$p->title} (" . (\App\Models\Project::$statuses[$p->status]['label'] ?? $p->status) . ")\n"
                . mb_substr($doc->schema()->toText($doc->content), 0, 3500);
        })->implode("\n\n");
    }

    /**
     * Pesquisa de mercado na internet para esta campanha. Reaproveita a guardada no documento
     * se tiver menos de 30 dias. Falha na pesquisa nunca derruba o rascunho: volta null.
     *
     * @return array{text: string, sources: array, at: string}|null
     */
    public function market(Document $document, Project $project, ?int $userId): ?array
    {
        $cached = $document->agent_state['pesquisa'] ?? null;
        if ($cached && now()->diffInDays(\Carbon\Carbon::parse($cached['at']), true) < self::RESEARCH_DAYS) {
            return $cached;
        }
        $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT);
        if (!$agent) {
            return null;
        }
        $project->loadMissing('client', 'macroPlan');
        $c = $project->client;
        $content = $document->content ?? [];
        // O planejamento diz do que a campanha trata de verdade (o cadastro pode ser só o nome do dono).
        $planDoc  = $project->macroPlan ? ClientDocuments::docFor($project->macroPlan) : null;
        $planText = $planDoc ? mb_substr($planDoc->schema()->toText($planDoc->content), 0, 3500) : null;

        $question = collect([
            "Cliente: {$c->company_name}",
            $c->segment ? "Segmento: {$c->segment}" : null,
            $c->website ? "Site: {$c->website}" : null,
            $c->address ? "Endereço da empresa: {$c->address}" : null,
            "Campanha que vamos criar: {$project->title}",
            filled($content['objetivo'] ?? null) ? "Para que serve: {$content['objetivo']}" : null,
            filled($content['publico'] ?? null) ? "Quem queremos alcançar: {$content['publico']}" : null,
            filled($content['regioes'] ?? null) ? "Regiões: {$content['regioes']}" : null,
            filled($c->briefing) ? "Briefing do cliente (resumo):\n" . mb_substr(trim(SourceText::html((string) $c->briefing)), 0, 2500) : null,
            $planText ? "Planejamento do ciclo (diz quais serviços e temas a campanha trabalha):\n{$planText}" : null,
        ])->filter()->implode("\n")
            . "\n\nIMPORTANTE: pesquise o mercado dos SERVIÇOS E TEMAS desta campanha (os que aparecem no planejamento e na campanha), na região do cliente. O nome do cadastro pode ser o do dono ou de um profissional: não pesquise a especialidade dele se não for o tema da campanha.";

        $instructions = <<<'TXT'
Você é o pesquisador de mercado de uma agência de marketing digital. Pesquise na internet para a equipe criativa que vai criar a campanha descrita. Entregue em português do Brasil, em tópicos curtos, no máximo 700 palavras, nesta ordem:
1. CONCORRENTES DIRETOS na mesma cidade/região e segmento (3 a 6): nome, como se posicionam, que promessa e que oferta usam nos anúncios, site e redes.
2. COMO O PÚBLICO FALA: dores, desejos, medos e objeções com as palavras das pessoas (avaliações no Google, Reclame Aqui, comentários, fóruns). Cite frases reais curtas quando achar.
3. O QUE ESTÁ FUNCIONANDO em anúncios deste segmento (formatos, tipos de gancho, provas) — Biblioteca de Anúncios da Meta e conteúdos de referência, se encontrar.
4. ESPAÇO LIVRE: o que nenhum concorrente está dizendo e o cliente pode ocupar.
5. REGRAS DE PUBLICIDADE do setor que a campanha precisa respeitar (ex.: conselho profissional).
Só o que você encontrou: não invente nome, número nem citação. Se não achar algo, diga que não achou.
TXT;

        try {
            $r = $this->ai->webResearch($agent, $instructions, $question, 8000, $userId, $project->client_id, self::RESEARCH_TRIGGER);
        } catch (\Throwable $e) {
            Log::warning("CreativeContext::market falhou ({$project->id}): {$e->getMessage()}");

            return null;
        }
        if (trim($r['text']) === '') {
            return null;
        }
        $research = ['text' => $r['text'], 'sources' => array_slice($r['sources'], 0, 15), 'at' => now()->toIso8601String()];

        $state = Document::withoutGlobalScopes()->find($document->id)->agent_state ?? [];
        $state['pesquisa'] = $research;
        Document::withoutGlobalScopes()->whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);

        return $research;
    }

    private function int($v): string
    {
        return number_format((float) $v, 0, ',', '.');
    }
}
