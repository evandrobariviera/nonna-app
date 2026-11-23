<?php

namespace App\Services\Documents\Schemas;

use App\Services\Documents\DocumentSchema;

/**
 * Documento de projeto — modelo .claude/docs/templates/nonna-projeto-modelo.html (6 seções,
 * qualquer natureza). Nome, datas e objetivo espelham o projeto no App (projects.*).
 * A camada técnica não fica aqui: vai para o Escopo técnico.
 */
class ProjetoSchema extends DocumentSchema
{
    public function kind(): string
    {
        return 'projeto';
    }

    public function version(): int
    {
        return 1;
    }

    public function title(): string
    {
        return 'Documento do projeto';
    }

    public function clientView(): string
    {
        return 'documents.render.projeto';
    }

    public function mirrors(): array
    {
        return ['nome' => 'title', 'objetivo' => 'objective', 'inicio' => 'start_date', 'fim' => 'end_date'];
    }

    public function sections(): array
    {
        return [
            [
                'key' => 'capa', 'num' => null, 'title' => 'Capa',
                'help' => 'Nome, tipo e datas. O nome, as datas e o "para que serve" atualizam o projeto no resto do App.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'nome', 'label' => 'Nome do projeto', 'input' => 'line', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'tipo_projeto', 'label' => 'Tipo de projeto', 'input' => 'select', 'required' => true, 'half' => true,
                     'options' => ['site' => 'Site', 'automacao' => 'Automação', 'integracao' => 'Integração', 'crm' => 'CRM', 'identidade' => 'Identidade', 'outro' => 'Outro']],
                    ['type' => 'field', 'key' => 'inicio', 'label' => 'Começa em', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'fim', 'label' => 'Entrega prevista', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 's01', 'num' => '01', 'title' => 'Objetivo e como saber que deu certo',
                'help' => 'Projeto é avaliado duas vezes: na entrega (ficou pronto?) e depois (valeu a pena?). "Está pronto quando" é o que o cliente precisa ver funcionando e aprovar. "O que melhora" é o ganho que esperamos depois da entrega, com quanto é hoje, quanto esperamos e quando vamos conferir.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'objetivo', 'label' => 'Para que serve o projeto', 'input' => 'line', 'required' => true, 'placeholder' => 'Em uma frase, começando por um verbo'],
                    ['type' => 'field', 'key' => 'carrega', 'label' => 'Ajuda a bater a meta · ou · Deixa pronto para a campanha', 'input' => 'line', 'required' => true,
                     'placeholder' => 'A meta do planejamento ou o nome da campanha'],
                    ['type' => 'field', 'key' => 'documento_tecnico', 'label' => 'Precisa de Escopo técnico?', 'input' => 'line', 'required' => true,
                     'placeholder' => 'Escopo técnico do ... ou "Não precisa"'],
                    ['type' => 'field', 'key' => 'pronto_quando', 'label' => 'Está pronto quando', 'input' => 'text', 'required' => true,
                     'placeholder' => 'O que precisa estar funcionando e aprovado pelo cliente'],
                    ['type' => 'field', 'key' => 'beneficio', 'label' => 'O que melhora depois de pronto', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: mais pedidos vindos do site'],
                    ['type' => 'field', 'key' => 'beneficio_hoje', 'label' => 'Quanto é hoje', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'O número de agora (ou "A confirmar")'],
                    ['type' => 'field', 'key' => 'beneficio_esperado', 'label' => 'Quanto esperamos', 'input' => 'line', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'conferir_em', 'label' => 'Quando vamos conferir', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'beneficio_fonte', 'label' => 'Onde medimos esse número', 'input' => 'line', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 's02', 'num' => '02', 'title' => 'Escopo',
                'help' => 'O que o projeto entrega e, tão importante quanto, o que não entrega. O "não entra" evita pedido extra e retrabalho. Um item por linha, sem detalhe técnico.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'entra', 'label' => 'O que entra', 'input' => 'text', 'required' => true, 'half' => true, 'placeholder' => 'Um item por linha'],
                    ['type' => 'field', 'key' => 'nao_entra', 'label' => 'O que não entra', 'input' => 'text', 'required' => true, 'half' => true, 'placeholder' => 'Um item por linha'],
                ],
            ],
            [
                'key' => 's03', 'num' => '03', 'title' => 'Entregas e datas',
                'help' => 'Cada entrega, a área que faz e a data.',
                'blocks' => [
                    ['type' => 'list', 'key' => 'entregas', 'item_label' => 'Entrega', 'title_field' => 'nome', 'min' => 1,
                     'add' => [['label' => 'Entrega', 'attrs' => []]],
                     'start' => [[]],
                     'fields' => [
                         ['key' => 'nome', 'label' => 'Entrega', 'input' => 'line', 'required' => true],
                         ['key' => 'area', 'label' => 'Área que faz', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'Dev, Design, Web...'],
                         ['key' => 'data', 'label' => 'Pronta até', 'input' => 'date', 'required' => true, 'half' => true],
                     ]],
                ],
            ],
            [
                'key' => 's04', 'num' => '04', 'title' => 'O que precisamos do cliente',
                'help' => 'Acessos, materiais e aprovações que dependem do cliente, com prazo. Senhas nunca entram aqui: vão para os Acessos do cliente no App.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'list', 'key' => 'cliente_precisa', 'item_label' => 'Pedido', 'title_field' => 'o_que',
                     'add' => [['label' => 'Pedido', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'o_que', 'label' => 'O que precisamos do cliente', 'input' => 'line', 'required' => true],
                         ['key' => 'ate', 'label' => 'Até quando', 'input' => 'date', 'required' => true, 'half' => true],
                     ]],
                ],
            ],
            [
                'key' => 's05', 'num' => '05', 'title' => 'Sinais de alerta',
                'help' => 'Opcional: de 0 a 3 situações que, se acontecerem, pedem ação. Projeto pequeno pode não ter nenhuma. Quem age é um papel, não uma pessoa.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'list', 'key' => 'alertas', 'item_label' => 'Sinal de alerta', 'title_field' => 'se', 'max' => 3,
                     'add' => [['label' => 'Sinal de alerta', 'attrs' => []]],
                     'fields' => [
                         ['key' => 'se', 'label' => 'Se acontecer', 'input' => 'line', 'required' => true, 'placeholder' => 'Ex.: material do cliente atrasado 5 dias'],
                         ['key' => 'fazemos', 'label' => 'O que fazemos', 'input' => 'line', 'required' => true],
                         ['key' => 'quem', 'label' => 'Quem age', 'input' => 'line', 'required' => true, 'half' => true, 'placeholder' => 'O papel: Gestor de Projetos, Web...'],
                     ]],
                ],
            ],
            [
                'key' => 's06', 'num' => '06', 'title' => 'Entrega e fechamento',
                'help' => 'A data em que o cliente aprova a entrega e a data em que conferimos se o ganho esperado (seção 01) apareceu.',
                'blocks' => [
                    ['type' => 'field', 'key' => 'aceite', 'label' => 'Cliente aprova a entrega em', 'input' => 'date', 'required' => true, 'half' => true],
                    ['type' => 'field', 'key' => 'conferir_beneficio', 'label' => 'Conferimos se melhorou em', 'input' => 'date', 'required' => true, 'half' => true],
                ],
            ],
            [
                'key' => 'fech', 'num' => null, 'title' => 'Fechamento (preencher na conferência do benefício)',
                'help' => 'O que entregamos, se o benefício apareceu e o que levamos para o próximo. Alimenta o próximo planejamento.',
                'optional' => true,
                'blocks' => [
                    ['type' => 'field', 'key' => 'resultado', 'label' => 'Resultado', 'input' => 'select', 'half' => true,
                     'options' => ['apareceu' => 'Benefício apareceu', 'em_parte' => 'Em parte', 'nao_apareceu' => 'Não apareceu']],
                    ['type' => 'field', 'key' => 'entregamos', 'label' => 'O que entregamos', 'input' => 'text', 'placeholder' => 'No prazo? O que mudou do escopo?'],
                    ['type' => 'field', 'key' => 'beneficio_apareceu', 'label' => 'O benefício apareceu?', 'input' => 'text', 'placeholder' => 'O número de hoje comparado ao esperado'],
                    ['type' => 'field', 'key' => 'levamos', 'label' => 'O que levamos para o próximo', 'input' => 'text'],
                ],
            ],
        ];
    }

    protected function rules(array $content): array
    {
        $issues = [];
        if (!empty($content['inicio']) && !empty($content['fim']) && $content['fim'] < $content['inicio']) {
            $issues[] = ['section' => 'capa', 'level' => 'erro', 'path' => 'fim', 'text' => 'A entrega prevista está antes do início.'];
        }
        if (!empty($content['aceite']) && !empty($content['conferir_beneficio']) && $content['conferir_beneficio'] < $content['aceite']) {
            $issues[] = ['section' => 's06', 'level' => 'erro', 'path' => 'conferir_beneficio', 'text' => 'A conferência do ganho vem depois de o cliente aprovar a entrega.'];
        }

        return $issues;
    }
}
