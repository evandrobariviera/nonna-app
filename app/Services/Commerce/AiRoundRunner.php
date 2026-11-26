<?php

namespace App\Services\Commerce;

use App\Models\AiAgent;
use App\Models\AiTokenUsage;
use App\Models\CommerceAiAnswer;
use App\Models\CommerceAiRound;
use App\Models\CommerceCity;
use App\Models\CommercePlace;
use App\Models\CommerceSegment;
use App\Services\AiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rodadas de IA — etapa 4 da Central de Comércio (26/11/2026).
 *
 * Pergunta ao ChatGPT (com busca na web, AiService::webResearch, agente "Agente Nonna") o que um
 * cliente perguntaria — "qual o melhor dentista em Videira SC?" — cada pergunta N vezes, porque a
 * resposta muda a cada vez. Depois procura nas respostas o nome de cada empresa mapeada daquele
 * segmento e cidade: "apareceu em X de 15 respostas" vira a parte "Nas IAs" da Nota Nonna (20 pts).
 *
 * Nada de dado do Google vai para a IA (termos do Google): a pergunta é só segmento + cidade; o
 * cruzamento com os nomes é feito aqui, no App.
 */
class AiRoundRunner
{
    /** Custo da ferramenta de busca da OpenAI por chamada (além dos tokens, que vêm do registro). */
    public const SEARCH_FEE_USD = 0.01;

    /**
     * Modelo da rodada: o mais barato da OpenAI cadastrado (Evandro achou caro com o do Agente Nonna,
     * ~US$ 0,055 por resposta). Para "pesquise e diga quem indica" ele dá conta; esforço baixo e
     * busca curta. Se não estiver cadastrado no provedor, usa o modelo do próprio agente.
     */
    public const MODEL = 'gpt-5.6-luna';

    /** Proporção de respostas que já vale a nota cheia (20): aparecer em 60% ou mais. */
    private const FULL_RATIO = 0.6;

    private const INSTRUCTIONS = <<<'TXT'
Você está respondendo a uma pessoa comum que quer encontrar um serviço na cidade dela. Pesquise na internet e responda como responderia normalmente a essa pessoa, em português do Brasil, indicando os estabelecimentos pelo nome, com uma frase curta sobre cada um.
Na ÚLTIMA linha da resposta escreva "NOMES: " seguido dos nomes exatos dos estabelecimentos ou profissionais que você indicou, separados por " ; ". Se não indicou nenhum, escreva "NOMES: nenhum".
TXT;

    public function __construct(private AiService $ai) {}

    /** Perguntas padrão: como um cliente perguntaria (usa o 1º termo de busca do segmento). */
    public static function defaultQuestions(CommerceSegment $segment, CommerceCity $city): array
    {
        $term = mb_strtolower(trim((string) (($segment->search_terms ?? [])[0] ?? $segment->name)));
        $where = "{$city->name} {$city->state}";

        // 3 perguntas (× 2 repetições = 6 respostas) por padrão, para a rodada sair barata.
        return [
            "Qual o melhor {$term} em {$where}?",
            "Me indica um {$term} bem avaliado em {$city->name}?",
            "Preciso de um {$term} em {$city->name}, {$city->state}. Quem você recomenda?",
        ];
    }

    /** Uma resposta (pergunta × repetição). Erro fica registrado na resposta; a rodada segue. */
    public function ask(CommerceAiRound $round, int $q, int $rep): void
    {
        if (CommerceAiAnswer::where('round_id', $round->id)->where('question_index', $q)->where('repetition', $rep)->exists()) {
            return; // já respondida (job repetido)
        }
        CommerceAiRound::withoutGlobalScopes()->whereKey($round->id)->where('status', 'queued')->update(['status' => 'running']);

        $data = ['round_id' => $round->id, 'question_index' => $q, 'repetition' => $rep];
        try {
            $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT);
            if (!$agent) {
                throw new \RuntimeException('O Agente Nonna (usado para pesquisar na internet) não está ativo em Agentes & IA.');
            }
            $r = $this->ai->webResearch(self::cheap($agent), self::INSTRUCTIONS, $round->questions[$q], 3000, $round->created_by, null, $round->trigger(), 'low', 'low');
            [$answer, $names] = self::splitNames($r['text']);
            $data += ['answer' => $answer, 'names' => $names, 'sources' => array_slice($r['sources'], 0, 10)];
        } catch (\Throwable $e) {
            $data += ['error' => mb_substr($e->getMessage(), 0, 500)];
        }
        CommerceAiAnswer::create($data);

        $this->finishIfComplete($round);
    }

    /** Cópia do agente (sem salvar) com o modelo barato, se o provedor tiver ele cadastrado. */
    public static function cheap(AiAgent $agent): AiAgent
    {
        $copy = clone $agent;
        if (collect($agent->provider->models ?? [])->contains('id', self::MODEL)) {
            $copy->model = self::MODEL;
        }

        return $copy;
    }

    /** Quando todas as respostas chegaram: conta as citações e atualiza a nota das empresas. */
    public function finishIfComplete(CommerceAiRound $round): void
    {
        if (CommerceAiAnswer::where('round_id', $round->id)->count() < $round->expected()) {
            return;
        }
        // Só um job fecha a rodada (o último a chegar).
        if (!CommerceAiRound::withoutGlobalScopes()->whereKey($round->id)->whereIn('status', ['queued', 'running'])->update(['status' => 'done', 'finished_at' => now()])) {
            return;
        }
        $round = CommerceAiRound::withoutGlobalScopes()->find($round->id);
        $ok = CommerceAiAnswer::where('round_id', $round->id)->whereNull('error')->get();
        if ($ok->isEmpty()) {
            $first = CommerceAiAnswer::where('round_id', $round->id)->value('error');
            $round->update(['status' => 'error', 'error' => 'Nenhuma resposta da IA: ' . $first]);

            return;
        }
        $this->score($round, $ok);
    }

    /**
     * Cruza as respostas com as empresas do segmento e cidade e grava "Nas IAs" na nota.
     *
     * @param \Illuminate\Support\Collection<int, CommerceAiAnswer> $answers
     */
    public function score(CommerceAiRound $round, $answers): void
    {
        $city = CommerceCity::withoutGlobalScopes()->find($round->city_id);
        $texts = $answers->map(fn ($a) => ' ' . self::norm($a->answer . ' ' . implode(' ; ', $a->names ?? [])) . ' ');
        $total = $texts->count();

        $places = CommercePlace::withoutGlobalScopes()
            ->where('organization_id', $round->organization_id)->where('city_id', $round->city_id)
            ->when($round->segment_id, fn ($q, $v) => $q->where('segment_id', $v), fn ($q) => $q->whereNull('segment_id'))
            ->get();

        DB::connection('pgsql')->transaction(function () use ($round, $places, $texts, $total, $city) {
            DB::connection('pgsql')->table('commerce_ai_round_place')->where('round_id', $round->id)->delete();
            foreach ($places as $p) {
                $keys = self::nameKeys((string) ($p->google_data['name'] ?? ''), $city?->name);
                $mentions = $keys ? $texts->filter(fn ($t) => collect($keys)->contains(fn ($k) => str_contains($t, " {$k} ")))->count() : 0;
                if ($mentions > 0) {
                    DB::connection('pgsql')->table('commerce_ai_round_place')->insert(['round_id' => $round->id, 'place_id' => $p->id, 'mentions' => $mentions]);
                }
                self::applyAiPoints($p, $mentions, $total, $round->id);
            }
        });
    }

    /** Grava os pontos "Nas IAs" na empresa; com nota já calculada, a nota passa a ir até 100. */
    public static function applyAiPoints(CommercePlace $p, int $mentions, int $total, string $roundId): void
    {
        $points = $mentions === 0 ? 0 : max(2, (int) round(20 * min(1, ($mentions / max(1, $total)) / self::FULL_RATIO)));
        $parts = $p->score_parts ?? [];
        $parts['parts']['ai'] = $points;
        $parts['ai_detail'] = ['mentions' => $mentions, 'answers' => $total, 'round_id' => $roundId, 'at' => now()->toIso8601String()];
        $update = ['score_parts' => $parts];
        if ($p->score !== null && isset($parts['parts']['profile'])) {
            $parts['total'] = (int) ($parts['parts']['profile'] + $parts['parts']['reputation'] + $parts['parts']['site'] + $points);
            $parts['max'] = 100;
            $update = ['score_parts' => $parts, 'score' => $parts['total']];
        }
        $p->forceFill($update)->saveQuietly();
    }

    /** Custo da rodada em US$: tokens registrados + taxa da busca por resposta. */
    public static function cost(CommerceAiRound $round, int $answered): float
    {
        return (float) AiTokenUsage::where('trigger', $round->trigger())->sum('estimated_cost_usd') + $answered * self::SEARCH_FEE_USD;
    }

    /** Separa a linha "NOMES:" do fim da resposta. @return array{0: string, 1: array<int, string>} */
    public static function splitNames(string $text): array
    {
        $names = [];
        if (preg_match('/^\s*\**NOMES\**\s*:\s*(.+)$/miu', $text, $m, PREG_OFFSET_CAPTURE)) {
            $raw = trim($m[1][0]);
            if (!preg_match('/^nenhum/i', $raw)) {
                $names = array_values(array_filter(array_map(fn ($n) => trim($n, " \t*.-"), preg_split('/\s*;\s*/', $raw))));
            }
            $text = rtrim(substr($text, 0, $m[0][1])); // offset do preg_match é em bytes
        }

        return [trim($text), array_slice($names, 0, 20)];
    }

    /** Texto sem acento, minúsculo, só letras/números separados por um espaço. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(Str::ascii($s));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $s)));
    }

    /**
     * A parte do nome que identifica a empresa: tira palavras genéricas ("Clínica", "Dr.",
     * "Odontologia", a cidade...). "Maurício Mioranzza Odontologia" → "mauricio mioranzza".
     */
    public static function nameKey(string $name, ?string $city = null): ?string
    {
        $generic = ['dr', 'dra', 'doutor', 'doutora', 'clinica', 'clinicas', 'consultorio', 'odontologia', 'odontologica', 'odontologico',
            'odonto', 'especializada', 'especializado', 'ltda', 'me', 'eireli', 'epp', 'de', 'da', 'do', 'das', 'dos', 'e', 'em',
            'centro', 'saude', 'estetica', 'oficina', 'mecanica', 'auto', 'centro', 'studio', 'estudio', 'salao', 'unidade', 'filial', 'sc', 'rs', 'pr'];
        $cityWords = $city ? explode(' ', self::norm($city)) : [];
        $all = array_values(array_filter(explode(' ', self::norm($name))));
        $kept = array_values(array_filter($all, fn ($w) => !in_array($w, $generic, true) && !in_array($w, $cityWords, true)));
        $key = implode(' ', $kept);
        if (mb_strlen($key) < 4) {
            $key = implode(' ', $all); // nome todo genérico ("Clínica Odontológica"): usa o nome inteiro
        }

        return mb_strlen($key) >= 4 ? $key : null;
    }

    /**
     * Formas do nome que contam como citação: a parte que identifica a empresa e, quando ela tem
     * 3 palavras ou mais, só as duas primeiras (a IA costuma encurtar: "Dr. Fábio Niederauer").
     *
     * @return array<int, string>
     */
    public static function nameKeys(string $name, ?string $city = null): array
    {
        $key = self::nameKey($name, $city);
        if (!$key) {
            return [];
        }
        $words = explode(' ', $key);
        $keys = [$key];
        if (count($words) >= 3 && mb_strlen($words[0] . $words[1]) >= 8) {
            $keys[] = $words[0] . ' ' . $words[1];
        }

        return $keys;
    }
}
