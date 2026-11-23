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
                'help' => 'O ciclo inteiro resumido numa frase. Cliente, período e responsável vêm do cadastro do planejamento (Configurações).',
                'blocks' => [
                    ['type' => 'field', 'key' => 'objetivo', 'label' => 'O que queremos neste ciclo', 'input' => 'line', 'required' => true,
                     'placeholder' => 'Em uma frase. Ex.: Aumentar os pedidos de orçamento sem subir o custo'],
                ],
            ],
            [
                'key' => 's01', 'num' => '01', 'title' => 'Onde estamos e aonde vamos',
                'help' => 'Como o cliente está agora e o que vai ser diferente neste ciclo, em poucas linhas cada. O foco é o que, se der certo, faz o ciclo valer a pena.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'situacao', 'label' => 'Como o cliente está hoje', 'input' => 'text', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'o_que_muda', 'label' => 'O que muda neste ciclo', 'input' => 'text', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'foco', 'label' => 'Foco do ciclo', 'input' => 'line', 'required' => true, 'placeholder' => 'Uma frase: a coisa mais importante deste ciclo'],
                ],
            ],
            [
                'key' => 's02', 'num' => '02', 'title' => 'Metas do ciclo',
                'help' => 'Meta é um RESULTADO que dá para medir, não uma tarefa: mais pedidos, mais vendas, custo menor. "Publicar artigos no blog" é tarefa (fica no projeto do blog); a meta seria, por exemplo, "visitas vindas do Google". Cada meta tem quanto queremos, até quando e o ponto de partida (como está antes do ciclo), para dar para comparar no fim. Quem ajuda a bater cada meta se marca nas campanhas e projetos da seção 04. Não sabe o ponto de partida? Escreva "A confirmar" e peça ao cliente na seção 06.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'metas', 'item_label' => 'Meta', 'title_field' => 'nome',
                     'add' => [['label' => 'Meta', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'O que queremos alcançar', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Ex.: Mais pedidos de orçamento pelo WhatsApp'],
                         ['key' => 'alvo', 'label' => 'Quanto', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Ex.: 60 por mês'],
                         ['key' => 'ate', 'label' => 'Até quando', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'hoje', 'label' => 'Ponto de partida (como está antes do ciclo)', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: 25 pedidos por mês em outubro (ou "A confirmar")', 'half' => true],
                         ['key' => 'fonte', 'label' => 'Onde vemos esse número (no começo e no fim)', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Gerenciador de Anúncios da Meta, Central de Leads, GA4. É uma ferramenta, não a reunião'],
                     ]],
                ],
            ],
            [
                'key' => 's03', 'num' => '03', 'title' => 'Como vamos fazer',
                'help' => 'O caminho do ciclo, em um ou dois parágrafos: o que vem primeiro, o que roda junto e por quê. Sem listar peças: elas ficam no documento de cada campanha.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'estrategia', 'label' => 'O caminho do ciclo', 'input' => 'text', 'required' => true],
                ],
            ],
            [
                'key' => 's04', 'num' => '04', 'title' => 'Campanhas e projetos',
                'help' => 'Um cartão para cada campanha ou projeto do ciclo. Campanha é comunicação com prazo que precisa trazer resultado; projeto é algo que fica pronto e continua servindo (site, CRM, automação). Toda campanha ajuda a bater pelo menos uma meta; projeto ajuda a bater uma meta ou deixa tudo pronto para uma campanha. Campanha que continua do ciclo passado é a mesma campanha: não crie outra. A tabela de verba se monta sozinha; peças e entregas ficam no documento de cada um.',
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
                         ['key' => 'inicio', 'label' => 'Começa em', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'fim', 'label' => 'Termina em', 'input' => 'date', 'required' => true, 'half' => true],
                         ['key' => 'objetivo', 'label' => 'Para que serve', 'input' => 'line', 'required' => true, 'placeholder' => 'Em uma frase'],
                         ['key' => 'metas', 'label' => 'Ajuda a bater a meta', 'input' => 'refs', 'refs' => 'metas'],
                         ['key' => 'metrica', 'label' => 'Como vamos saber se deu certo', 'input' => 'line', 'required' => true,
                          'placeholder' => 'Ex.: pedidos de orçamento: 60 por mês até 31/03', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'plataformas', 'label' => 'Onde vai anunciar', 'input' => 'line', 'half' => true,
                          'placeholder' => 'Meta, Google... (vazio se não tiver anúncio pago)', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'verba_mensal', 'label' => 'Verba de anúncio por mês', 'input' => 'money', 'half' => true,
                          'placeholder' => 'R$', 'only' => ['tipo' => 'campanha']],
                         ['key' => 'prepara', 'label' => 'Deixa pronto para a campanha', 'input' => 'refs', 'refs' => 'itens',
                          'refs_filter' => ['tipo' => 'campanha'], 'only' => ['tipo' => 'projeto']],
                         ['key' => 'pronto_quando', 'label' => 'Está pronto quando', 'input' => 'text', 'required' => true,
                          'placeholder' => 'O que o cliente precisa ver funcionando e aprovar', 'only' => ['tipo' => 'projeto']],
                         ['key' => 'beneficio', 'label' => 'O que melhora depois de pronto', 'input' => 'line', 'required' => true, 'half' => true,
                          'placeholder' => 'Ex.: mais pedidos vindos do site', 'only' => ['tipo' => 'projeto']],
                         ['key' => 'beneficio_em', 'label' => 'Quando vamos conferir se melhorou', 'input' => 'date', 'required' => true, 'half' => true,
                          'only' => ['tipo' => 'projeto']],
                     ]],
                ],
            ],
            [
                'key' => 's05', 'num' => '05', 'title' => 'Cronograma',
                'help' => 'Uma linha por mês ou fase: o nome da fase e o que acontece nela. No fim, duas datas que todo ciclo tem: a conversa com o cliente no meio do caminho e o fechamento, quando olhamos o resultado de tudo e começamos o próximo planejamento.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'fases', 'item_label' => 'Fase', 'title_field' => 'nome',
                     'add' => [['label' => 'Fase', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'quando', 'label' => 'Quando', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Janeiro/2027', 'half' => true],
                         ['key' => 'nome', 'label' => 'Nome da fase', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'texto', 'label' => 'O que acontece', 'input' => 'line', 'required' => true],
                     ]],
                    ['type' => 'field', 'key' => 'revisao_meio', 'label' => 'Conversa do meio do ciclo com o cliente', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'fechamento', 'label' => 'Fechamento do ciclo', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 's06', 'num' => '06', 'title' => 'O que precisamos de você',
                'help' => 'O que depende do cliente, com prazo: acessos, materiais, aprovações e os números que ficaram "A confirmar" nas metas. Sem pedido? Deixe vazio.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'list', 'key' => 'pedidos', 'item_label' => 'Pedido', 'title_field' => 'o_que',
                     'add' => [['label' => 'Pedido', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'o_que', 'label' => 'O que precisamos do cliente', 'input' => 'line', 'required' => true],
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
                    'text' => "{$nome}: nenhuma campanha ou projeto ajuda a bater esta meta. Marque em \"Ajuda a bater a meta\" na seção 04."];
            }
        }

        if (!$itens) {
            $issues[] = ['section' => 's04', 'level' => 'erro', 'text' => 'O planejamento precisa de pelo menos uma campanha ou projeto.', 'path' => null];
        }
        foreach ($itens as $item) {
            $nome = $item['nome'] ?: ($item['tipo'] === 'projeto' ? 'Projeto' : 'Campanha') . ' sem nome';
            if ($item['tipo'] === 'campanha' && empty($item['metas'])) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.metas",
                    'text' => "{$nome}: toda campanha ajuda a bater pelo menos uma meta (marque em \"Ajuda a bater a meta\")."];
            }
            if ($item['tipo'] === 'projeto' && empty($item['metas']) && empty($item['prepara'])) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.metas",
                    'text' => "{$nome}: o projeto precisa ajudar a bater uma meta ou deixar tudo pronto para uma campanha."];
            }
            if (!empty($item['inicio']) && !empty($item['fim']) && $item['fim'] < $item['inicio']) {
                $issues[] = ['section' => 's04', 'level' => 'erro', 'path' => "itens.{$item['id']}.fim",
                    'text' => "{$nome}: o fim está antes do início."];
            }
            if ($item['tipo'] === 'campanha' && filled($item['plataformas'] ?? null) && empty($item['verba_mensal'])) {
                $issues[] = ['section' => 's04', 'level' => 'falta', 'path' => "itens.{$item['id']}.verba_mensal",
                    'text' => "{$nome}: diz onde vai anunciar mas falta a verba de anúncio por mês."];
            }
        }

        $pending = array_filter($metas, fn ($m) => is_string($m['hoje'] ?? null) && mb_strtolower(trim($m['hoje'])) === mb_strtolower(self::PENDING));
        if ($pending && empty($content['pedidos'])) {
            $issues[] = ['section' => 's06', 'level' => 'falta', 'path' => null,
                'text' => count($pending) . ' meta(s) sem ponto de partida (está "A confirmar"): peça esse número ao cliente aqui.'];
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
