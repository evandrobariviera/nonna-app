<?php

namespace App\Services\Documents\Schemas;

use App\Services\Documents\DocumentSchema;

/**
 * Documento de campanha — modelo .claude/docs/templates/nonna-campanha-modelo.html (9 seções).
 * Nome, datas e objetivo espelham a campanha no App (projects.*). Ajuste de 23/11 já aplicado:
 * Ganchos são só as primeiras FRASES (a cena de cada ângulo fica no Conceito criativo).
 */
class CampanhaSchema extends DocumentSchema
{
    public const MOTIVOS = [
        'dor' => 'Dor', 'desejo' => 'Desejo', 'objecao' => 'Objeção', 'comparacao' => 'Comparação',
        'prova_social' => 'Prova social', 'oferta' => 'Oferta ou urgência', 'autoridade' => 'Autoridade',
        'demonstracao' => 'Bastidor ou demonstração', 'identidade' => 'Identidade',
    ];

    public const MOMENTOS = [
        'nao_percebeu' => '1. Não percebeu o problema', 'sabe_do_problema' => '2. Sabe do problema',
        'procura_solucao' => '3. Procura solução', 'conhece_a_gente' => '4. Conhece a gente',
        'pronto_para_comprar' => '5. Pronta para comprar',
    ];

    public const ENTRADAS = ['automatico' => 'O App puxa sozinho', 'informado' => 'A equipe lança', 'avaliado' => 'A equipe dá uma nota'];

    public function kind(): string
    {
        return 'campanha';
    }

    public function version(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Documento da campanha';
    }

    public function clientView(): string
    {
        return 'documents.render.campanha';
    }

    public function mirrors(): array
    {
        return ['nome' => 'title', 'objetivo' => 'objective', 'inicio' => 'start_date', 'fim' => 'end_date', 'entrega_pecas' => 'pieces_due_date'];
    }

    public function sections(): array
    {
        return [
            [
                'key' => 'capa', 'num' => null, 'title' => 'Capa',
                'help' => 'Nome, tipo e datas. O nome, as datas e o "para que serve" atualizam a campanha no resto do App.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'nome', 'label' => 'Nome da campanha', 'input' => 'line', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'tipo_campanha', 'label' => 'Tipo de campanha', 'input' => 'select', 'required' => true, 'half' => true,
                     'options' => ['venda' => 'Venda', 'marca' => 'Marca', 'lancamento' => 'Lançamento', 'relacionamento' => 'Relacionamento']],
                    ['type' => 'field', 'key' => 'inicio', 'label' => 'Vai ao ar em', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'fim', 'label' => 'Termina em', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'entrega_pecas', 'label' => 'Peças prontas até', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 's01', 'num' => '01', 'title' => 'Objetivo e como vamos medir',
                'help' => 'Para que a campanha serve e como vamos saber se deu certo: um número principal e até dois de apoio, cada um com o ponto de partida (como está antes da campanha) e quanto queremos. Campanha de venda ou lançamento se mede por contato, venda ou custo por venda, nunca por curtida ou alcance. "Quem lança o número": o App puxa sozinho (Meta, Google, GA4, Central de Leads), a equipe lança, ou a equipe dá uma nota. Combinado: campanha sem esta seção completa não vai ao ar.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'objetivo', 'label' => 'Para que serve a campanha', 'input' => 'line', 'required' => true,
                     'placeholder' => 'Começando por um verbo. Ex.: Gerar 40 pedidos de orçamento por mês'],
                    ['type' => 'field', 'key' => 'meta_planejamento', 'label' => 'Meta do planejamento que ela ajuda a bater', 'input' => 'line', 'required' => true],
                    ['type' => 'field', 'key' => 'metrica', 'label' => 'Número principal: como vamos saber se deu certo', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Pedidos de orçamento pelo WhatsApp'],
                    ['type' => 'field', 'key' => 'metrica_hoje', 'label' => 'Ponto de partida (como está antes da campanha)', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Ex.: 25 pedidos por mês em outubro (ou "A confirmar")'],
                    ['type' => 'field', 'key' => 'metrica_meta', 'label' => 'Quanto queremos', 'input' => 'line', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'metrica_ate', 'label' => 'Até quando', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'metrica_entrada', 'label' => 'Quem lança o número', 'input' => 'select', 'required' => true, 'half' => true, 'options' => self::ENTRADAS],
                    ['type' => 'field', 'key' => 'metrica_fonte', 'label' => 'Onde medimos esse número', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Central de Leads, Meta, contratos no App'],
                    ['type' => 'list', 'key' => 'apoio', 'item_label' => 'Número de apoio', 'title_field' => 'nome', 'max' => 2,
                     'add' => [['label' => 'Número de apoio', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Número de apoio', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Custo por pedido'],
                         ['key' => 'hoje', 'label' => 'Ponto de partida', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'meta', 'label' => 'Quanto queremos', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'fonte', 'label' => 'Onde medimos', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'entrada', 'label' => 'Quem lança o número', 'input' => 'select', 'required' => true, 'half' => true, 'options' => self::ENTRADAS],
                     ]],
                ],
            ],
            [
                'key' => 's02', 'num' => '02', 'title' => 'Ângulos criativos',
                'help' => 'Um ângulo criativo é um motivo diferente para a pessoa parar e agir. De 3 a 6 por campanha, partindo de Quem compra (Briefing do Kickoff). Todo ângulo tem um Motivo (por que a pessoa para) e um Momento (o quanto ela já sabe; 1 a 3 é público novo, 4 e 5 é remarketing). Regra: dois ângulos não podem ter o mesmo motivo e o mesmo momento. Ganchos são as primeiras frases (os 2 ou 3 primeiros segundos); a cena de cada ângulo fica no Conceito criativo.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'angulos', 'item_label' => 'Ângulo', 'title_field' => 'nome', 'min' => 3, 'max' => 6,
                     'add' => [['label' => 'Ângulo', 'attrs' => []]],
                     'start' => [[], [], []],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Nome curto do ângulo', 'input' => 'line', 'required' => true],
                         ['key' => 'motivo', 'label' => 'Motivo: por que a pessoa para', 'input' => 'select', 'required' => true, 'half' => true, 'options' => self::MOTIVOS],
                         ['key' => 'momento', 'label' => 'Momento: o quanto ela já sabe', 'input' => 'select', 'required' => true, 'half' => true, 'options' => self::MOMENTOS],
                         ['key' => 'mensagem', 'label' => 'O que o anúncio diz', 'input' => 'line', 'required' => true, 'placeholder' => 'A mensagem principal, numa frase'],
                         ['key' => 'para_quem', 'label' => 'Para quem é', 'input' => 'line', 'required' => true, 'placeholder' => 'Com base em Quem compra, do Briefing'],
                         ['key' => 'ganchos', 'label' => 'Primeiras frases (ganchos)', 'input' => 'text', 'required' => true, 'placeholder' => 'Como o anúncio começa. Uma frase por linha'],
                         ['key' => 'formatos', 'label' => 'Formatos', 'input' => 'line', 'required' => true, 'placeholder' => 'Vídeo falado, depoimento, card, carrossel...'],
                     ]],
                ],
            ],
            [
                'key' => 's03', 'num' => '03', 'title' => 'Conceito e racional',
                'help' => 'A grande ideia da campanha, numa frase que aparece em tudo, e por que ela deve funcionar com este público. Como a campanha soa e parece fica no Conceito criativo, feito pela Criação a partir daqui.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'frase_conceito', 'label' => 'A grande ideia, em uma frase', 'input' => 'line', 'required' => true],
                    ['type' => 'field', 'key' => 'conceito', 'label' => 'O que essa ideia quer dizer', 'input' => 'text', 'required' => true,
                     'placeholder' => 'E como ele une os ângulos criativos da seção 02'],
                    ['type' => 'field', 'key' => 'por_que', 'label' => 'Por que deve funcionar', 'input' => 'text', 'required' => true,
                     'placeholder' => 'O que se sabe do público, do momento e da concorrência que justifica esse caminho'],
                ],
            ],
            [
                'key' => 's04', 'num' => '04', 'title' => 'Mecânica',
                'help' => 'O caminho da pessoa, do primeiro contato até o objetivo da seção 01, passo a passo. Uma frente para cada caminho diferente (anúncio, prospecção, loja...). Nos passos, um por linha, assim: "Vê o anúncio: o vídeo mostra o projeto".',
                'blocks' => [
                    ['type' => 'list', 'key' => 'frentes', 'item_label' => 'Frente', 'title_field' => 'nome', 'min' => 1,
                     'add' => [['label' => 'Frente', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Nome da frente', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: Anúncios'],
                         ['key' => 'passos', 'label' => 'Passos', 'input' => 'text', 'required' => true,
                          'placeholder' => "Vê o anúncio: o vídeo mostra o projeto em 3D\nChama no WhatsApp: ..."],
                     ]],
                    ['type' => 'field', 'key' => 'encontro', 'label' => 'Onde as frentes se encontram', 'input' => 'text'],
                ],
            ],
            [
                'key' => 's05', 'num' => '05', 'title' => 'Desdobramento por disciplina',
                'help' => 'O que cada área faz nesta campanha. Só as áreas que participam.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'disciplinas', 'item_label' => 'Área', 'title_field' => 'area', 'min' => 1,
                     'add' => [['label' => 'Área', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'area', 'label' => 'Área', 'input' => 'line', 'required' => true, 'placeholder' => 'Criação, Audiovisual, Tráfego...'],
                         ['key' => 'o_que', 'label' => 'O que essa área faz', 'input' => 'text', 'required' => true],
                     ]],
                ],
            ],
            [
                'key' => 's06', 'num' => '06', 'title' => 'Tráfego pago',
                'help' => 'Uma linha para cada lugar onde vamos anunciar (Meta, Google, TikTok...), com a verba e para quê. Depois, quem queremos alcançar e onde. Sem anúncio pago? Deixe sem nenhuma linha.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'list', 'key' => 'midia', 'item_label' => 'Plataforma', 'title_field' => 'plataforma',
                     'add' => [['label' => 'Plataforma', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'plataforma', 'label' => 'Onde anuncia', 'input' => 'line', 'required' => true, 'half' => true],
                         ['key' => 'verba_mensal', 'label' => 'Verba por mês', 'input' => 'money', 'required' => true, 'half' => true],
                         ['key' => 'para', 'label' => 'Para quê', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: levar gente nova ao pedido de orçamento'],
                     ]],
                    ['type' => 'field', 'key' => 'publico', 'label' => 'Quem queremos alcançar', 'input' => 'text'],
                    ['type' => 'field', 'key' => 'regioes', 'label' => 'Cidades ou regiões', 'input' => 'line', 'placeholder' => 'Separadas por vírgula'],
                ],
            ],
            [
                'key' => 's07', 'num' => '07', 'title' => 'Peças e datas',
                'help' => 'Tudo o que vai ser produzido. Todo anúncio diz de qual ângulo criativo é; peça que não é anúncio (roteiro de abordagem, material de apoio) fica sem ângulo. A primeira peça da lista é a peça conceito.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'pecas', 'item_label' => 'Peça', 'title_field' => 'nome', 'min' => 1,
                     'add' => [['label' => 'Peça', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Peça', 'input' => 'line', 'required' => true],
                         ['key' => 'angulo', 'label' => 'De qual ângulo é', 'input' => 'ref', 'refs' => 'angulos', 'half' => true],
                         ['key' => 'formato', 'label' => 'Formato', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'card, vídeo vertical, roteiro...'],
                         ['key' => 'quantidade', 'label' => 'Quantas', 'input' => 'number', 'required' => true, 'half' => true, 'default' => 1],
                         ['key' => 'data', 'label' => 'Pronta até', 'input' => 'date', 'required' => true, 'half' => true],
                     ]],
                ],
            ],
            [
                'key' => 's08', 'num' => '08', 'title' => 'Sinais de alerta',
                'help' => 'De 1 a 3 situações que, se acontecerem, pedem ação, combinadas antes da campanha começar. Prefira sinais fáceis de ver, com prazo: "7 dias sem nenhum pedido" é melhor que "poucos pedidos". Quem age é um papel (Tráfego, Atendimento...), não uma pessoa.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'alertas', 'item_label' => 'Sinal de alerta', 'title_field' => 'se', 'min' => 1, 'max' => 3,
                     'add' => [['label' => 'Sinal de alerta', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'se', 'label' => 'Se acontecer', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: 7 dias sem nenhum pedido de orçamento'],
                         ['key' => 'fazemos', 'label' => 'O que fazemos', 'input' => 'line', 'required' => true],
                         ['key' => 'quem', 'label' => 'Quem age', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'O papel: Tráfego, Atendimento...'],
                     ]],
                ],
            ],
            [
                'key' => 's09', 'num' => '09', 'title' => 'Acompanhamento',
                'help' => 'Quem acompanha a campanha e de quanto em quanto tempo conferimos como ela vai. A conferência é rápida: verde, amarelo ou vermelho, e uma frase sobre o que mudou.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'quem_acompanha', 'label' => 'Quem acompanha', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'O papel: Tráfego, Atendimento...'],
                    ['type' => 'field', 'key' => 'checkin_dias', 'label' => 'Conferir a cada quantos dias', 'input' => 'number', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'checkin_inicio', 'label' => 'Primeira conferência', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'fechamento', 'label' => 'Data do fechamento', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 'fech', 'num' => null, 'title' => 'Fechamento (preencher quando a campanha terminar)',
                'help' => 'O que era para acontecer, o que aconteceu, por que houve diferença, qual ângulo funcionou melhor e o que levamos para o próximo ciclo. Alimenta o próximo planejamento.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'field', 'key' => 'resultado', 'label' => 'Resultado', 'input' => 'select', 'half' => true,
                     'options' => ['atingiu' => 'Atingiu', 'parcial' => 'Parcial', 'nao_atingiu' => 'Não atingiu']],
                    ['type' => 'field', 'key' => 'aconteceu', 'label' => 'O que aconteceu', 'input' => 'text', 'placeholder' => 'Os números finais de cada métrica'],
                    ['type' => 'field', 'key' => 'diferenca', 'label' => 'Por que houve diferença', 'input' => 'text'],
                    ['type' => 'field', 'key' => 'angulo_melhor', 'label' => 'Ângulo criativo que funcionou melhor', 'input' => 'text',
                     'placeholder' => 'Qual trouxe mais resultado na métrica principal, e qual não funcionou'],
                    ['type' => 'field', 'key' => 'levamos', 'label' => 'O que levamos para o próximo ciclo', 'input' => 'text'],
                ],
            ],
        ];
    }

    protected function rules(array $content): array
    {
        $issues = [];

        // Venda/lançamento não se mede por vaidade.
        if (in_array($content['tipo_campanha'] ?? null, ['venda', 'lancamento'], true)
            && preg_match('/curtid|alcance|seguidor|impress|visualiza/i', (string) ($content['metrica'] ?? ''))) {
            $issues[] = ['section' => 's01', 'level' => 'erro', 'path' => 'metrica',
                'text' => 'Campanha de venda ou lançamento se mede por contato, venda ou custo por venda, nunca por curtida ou alcance.'];
        }

        // Dois ângulos com o mesmo motivo e o mesmo momento = um ângulo só.
        $seen = [];
        foreach ($content['angulos'] ?? [] as $a) {
            if (empty($a['motivo']) || empty($a['momento'])) {
                continue;
            }
            $pair = $a['motivo'] . '|' . $a['momento'];
            if (isset($seen[$pair])) {
                $issues[] = ['section' => 's02', 'level' => 'erro', 'path' => "angulos.{$a['id']}.motivo",
                    'text' => ($a['nome'] ?: 'Ângulo') . ' tem o mesmo motivo e momento de ' . ($seen[$pair] ?: 'outro ângulo') . ': para a Meta é um ângulo só. Mude um dos dois.'];
            }
            $seen[$pair] = $a['nome'];
        }

        if (!empty($content['midia']) && blank($content['publico'] ?? null)) {
            $issues[] = ['section' => 's06', 'level' => 'falta', 'path' => 'publico', 'text' => 'Tem anúncio pago mas falta dizer quem queremos alcançar.'];
        }

        if (!empty($content['inicio']) && !empty($content['fim']) && $content['fim'] < $content['inicio']) {
            $issues[] = ['section' => 'capa', 'level' => 'erro', 'path' => 'fim', 'text' => 'O fim está antes da data em que vai ao ar.'];
        }

        return $issues;
    }
}
