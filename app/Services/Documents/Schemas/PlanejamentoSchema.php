<?php

namespace App\Services\Documents\Schemas;

use App\Services\Documents\DocumentSchema;

/**
 * Planejamento do ciclo — modelo v4 (.claude/docs/templates/nonna-planejamento-modelo-v4.html).
 * Capa + 6 seções, só o global estratégico. Período, cliente e responsável moram no
 * MacroPlan (não se repetem aqui). As chaves seguem o bloco nonna-dados do modelo v4.
 */
class PlanejamentoSchema extends DocumentSchema
{
    public function kind(): string
    {
        return 'planejamento';
    }

    public function version(): int
    {
        return 1;
    }

    public function clientView(): string
    {
        return 'documents.render.planejamento';
    }

    public function title(): string
    {
        return 'Documento do planejamento';
    }

    public function sections(): array
    {
        return [
            [
                'key' => 'capa', 'num' => null, 'title' => 'Capa',
                'help' => 'O objetivo do ciclo inteiro, numa frase. Cliente, período e responsável vêm do cadastro do planejamento (Configurações).',
                'blocks' => [
                    ['type' => 'field', 'key' => 'objetivo', 'label' => 'Objetivo do ciclo', 'input' => 'line', 'required' => true,
                     'placeholder' => 'O objetivo do ciclo em uma frase'],
                ],
            ],
            [
                'key' => 's01', 'num' => '01', 'title' => 'Onde estamos e aonde vamos',
                'help' => 'A situação de hoje e o que muda neste ciclo, em poucas linhas cada. O foco é a frase que resume o ciclo inteiro.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'situacao', 'label' => 'Situação atual', 'input' => 'text', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'o_que_muda', 'label' => 'O que muda neste ciclo', 'input' => 'text', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'foco', 'label' => 'Foco do ciclo', 'input' => 'line', 'required' => true, 'placeholder' => 'Uma frase'],
                ],
            ],
            [
                'key' => 's02', 'num' => '02', 'title' => 'Metas do ciclo',
                'help' => 'Um cartão por meta. Cada uma com alvo e data, o número de hoje (do Briefing do Kickoff ou do ciclo anterior) e de onde vem o número. Quem carrega cada meta se marca nas campanhas e projetos da seção 04. "Em queda" ou "melhorar" não são metas: transforme em número ("20% a menos"). Número que não se sabe: escreva "A confirmar" e peça na seção 06.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'metas', 'item_label' => 'Meta', 'title_field' => 'nome',
                     'add' => [['label' => 'Meta', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Nome da meta', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Ex.: Pedidos de orçamento'],
                         ['key' => 'alvo', 'label' => 'Alvo', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Ex.: 60 por mês'],
                         ['key' => 'ate', 'label' => 'Até', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'hoje', 'label' => 'Hoje', 'input' => 'line', 'required' => true, 'placeholder' => 'Número de hoje ou "A confirmar"', 'half' => true],
                         ['key' => 'fonte', 'label' => 'Fonte', 'input' => 'line', 'required' => true, 'placeholder' => 'De onde vem o número'],
                     ]],
                ],
            ],
            [
                'key' => 's03', 'num' => '03', 'title' => 'Como vamos fazer',
                'help' => 'A estratégia geral do ciclo, em um ou dois parágrafos: o que vem primeiro, o que roda em paralelo e por quê. Sem cartões nem listas de peças.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'estrategia', 'label' => 'Estratégia do ciclo', 'input' => 'text', 'required' => true],
                ],
            ],
            [
                'key' => 's04', 'num' => '04', 'title' => 'Campanhas e projetos',
                'help' => 'Um cartão por campanha ou projeto. Campanha diz quais metas carrega e a métrica principal. Projeto diz quais metas carrega ou para qual campanha prepara o terreno, quando está pronto e qual benefício será conferido, e quando. Campanha com anúncio pago informa plataformas e verba: a tabela de verba se monta sozinha. As entregas e peças ficam no documento de cada item.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'itens', 'item_label' => 'Campanha ou projeto', 'title_field' => 'nome', 'switch' => 'tipo',
                     'add' => [
                         ['label' => 'Campanha', 'attrs' => ['tipo' => 'campanha']],
                         ['label' => 'Projeto', 'attrs' => ['tipo' => 'projeto']],
                     ],
                     'start' => [['tipo' => 'campanha']],
                     'fields' => [
                         ['key' => 'tipo', 'label' => 'Tipo', 'input' => 'select', 'required' => true,
                          'options' => ['campanha' => 'Campanha', 'projeto' => 'Projeto']],
                         ['key' => 'nome', 'label' => 'Nome', 'input' => 'line', 'required' => true],
                         ['key' => 'inicio', 'label' => 'Início', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'fim', 'label' => 'Fim', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'objetivo', 'label' => 'Objetivo', 'input' => 'line', 'required' => true, 'placeholder' => 'Objetivo em uma frase'],
                         ['key' => 'metas', 'label' => 'Carrega', 'input' => 'refs', 'refs' => 'metas'],
                         ['key' => 'metrica', 'label' => 'Métrica principal', 'input' => 'line', 'required' => true,
                          'placeholder' => 'Métrica: alvo até dd/mm', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'plataformas', 'label' => 'Plataformas de anúncio', 'input' => 'line', 'half' => true,
                          'placeholder' => 'Meta, Google... (vazio = sem anúncio)', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'verba_mensal', 'label' => 'Verba mensal', 'input' => 'money', 'half' => true,
                          'placeholder' => 'R$', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'prepara', 'label' => 'Prepara o terreno para', 'input' => 'refs', 'refs' => 'itens',
                          'refs_filter' => ['tipo' => 'campanha'], 'only' => ['tipo' => 'projeto']],
                         ['key' => 'pronto_quando', 'label' => 'Pronto quando', 'input' => 'text', 'required' => true,
                          'placeholder' => 'O que precisa estar entregue e aprovado', 'only' => ['tipo' => 'projeto']],
                         ['key' => 'beneficio', 'label' => 'Benefício', 'input' => 'line', 'required' => true, 'half' => true,
                          'placeholder' => 'O que deve melhorar depois da entrega', 'only' => ['tipo' => 'projeto']],
                         ['key' => 'beneficio_em', 'label' => 'Conferir o benefício em', 'input' => 'date', 'required' => true, 'half' => true,
                          'only' => ['tipo' => 'projeto']],
                     ]],
                ],
            ],
            [
                'key' => 's05', 'num' => '05', 'title' => 'Cronograma',
                'help' => 'Uma linha por mês ou fase, com o nome da fase e o que acontece. No fim, as duas datas fixas: a revisão do meio do ciclo, feita com o cliente, e o fechamento do ciclo, que vira a base do próximo planejamento.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'fases', 'item_label' => 'Fase', 'title_field' => 'nome',
                     'add' => [['label' => 'Fase', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'quando', 'label' => 'Quando', 'input' => 'line', 'required' => true, 'placeholder' => 'Mês/aaaa', 'half' => true],
                         ['key' => 'nome', 'label' => 'Nome da fase', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'texto', 'label' => 'O que acontece', 'input' => 'line', 'required' => true],
                     ]],
                    ['type' => 'field', 'key' => 'revisao_meio', 'label' => 'Revisão do meio do ciclo', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'fechamento', 'label' => 'Fechamento do ciclo', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 's06', 'num' => '06', 'title' => 'O que precisamos de você',
                'help' => 'O que depende do cliente, com prazo. Inclui os números de hoje que ficaram como "A confirmar" nas metas. Sem pedido? Deixe a lista vazia.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'list', 'key' => 'pedidos', 'item_label' => 'Pedido', 'title_field' => 'o_que',
                     'add' => [['label' => 'Pedido', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'o_que', 'label' => 'O que precisamos', 'input' => 'line', 'required' => true],
                         ['key' => 'ate', 'label' => 'Até quando', 'input' => 'date', 'required' => true, 'half' => true],
                     ]],
                ],
            ],
        ];
    }

    /** Regras do modelo v4 (as mesmas da caixa "Como usar este modelo"). */
    protected function rules(array $content): array
    {
        $issues = [];
        $metas  = $content['metas'] ?? [];
        $itens  = $content['itens'] ?? [];
        $byMeta = $this->carriers($content);

        if (!$metas) {
            $issues[] = ['section' => 's02', 'level' => 'erro', 'text' => 'O planejamento precisa de pelo menos uma meta.', 'path' => null];
        }
        foreach ($metas as $meta) {
            if (empty($byMeta[$meta['id']])) {
                $nome = $meta['nome'] ?: 'Meta sem nome';
                $issues[] = ['section' => 's02', 'level' => 'erro', 'path' => "metas.{$meta['id']}.nome",
                    'text' => "{$nome}: nenhuma campanha ou projeto carrega esta meta. Marque em \"Carrega\" na seção 04."];
            }
        }

        if (!$itens) {
            $issues[] = ['section' => 's04', 'level' => 'erro', 'text' => 'O planejamento precisa de pelo menos uma campanha ou projeto.', 'path' => null];
        }
        foreach ($itens as $item) {
            $nome = $item['nome'] ?: ($item['tipo'] === 'projeto' ? 'Projeto' : 'Campanha') . ' sem nome';
            if ($item['tipo'] === 'campanha' && empty($item['metas'])) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.metas",
                    'text' => "{$nome}: toda campanha carrega pelo menos uma meta."];
            }
            if ($item['tipo'] === 'projeto' && empty($item['metas']) && empty($item['prepara'])) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.metas",
                    'text' => "{$nome}: projeto carrega uma meta ou diz para qual campanha prepara o terreno."];
            }
            if (!empty($item['inicio']) && !empty($item['fim']) && $item['fim'] < $item['inicio']) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.fim",
                    'text' => "{$nome}: o fim está antes do início."];
            }
            if ($item['tipo'] === 'campanha' && filled($item['plataformas'] ?? null) && empty($item['verba_mensal'])) {
                $issues[] = ['section' => 's04', 'level' => 'falta', 'path' => "itens.{$item['id']}.verba_mensal",
                    'text' => "{$nome}: tem plataforma de anúncio mas falta a verba mensal."];
            }
        }

        $pending = array_filter($metas, fn ($m) => is_string($m['hoje'] ?? null) && mb_strtolower(trim($m['hoje'])) === mb_strtolower(self::PENDING));
        if ($pending && empty($content['pedidos'])) {
            $issues[] = ['section' => 's06', 'level' => 'falta', 'path' => null,
                'text' => count($pending) . ' meta(s) com o número de hoje "A confirmar": peça ao cliente aqui.'];
        }

        return $issues;
    }

    /** meta id → itens que a carregam (calculado, não guardado: "Carregada por" da seção 02). */
    public function carriers(array $content): array
    {
        $out = [];
        foreach ($content['itens'] ?? [] as $item) {
            foreach ($item['metas'] ?? [] as $metaId) {
                $out[$metaId][] = $item;
            }
        }

        return $out;
    }

    /** Linhas da tabela de verba (campanhas com plataforma ou verba) + total. */
    public function budget(array $content): array
    {
        $rows = array_values(array_filter($content['itens'] ?? [], fn ($i) => $i['tipo'] === 'campanha'
            && (filled($i['plataformas'] ?? null) || !empty($i['verba_mensal']))));

        return ['rows' => $rows, 'total' => array_sum(array_map(fn ($i) => (float) ($i['verba_mensal'] ?? 0), $rows))];
    }
}
