<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Textos padrão por gatilho e canal — só preenche o que ainda não
     * existe, então nunca sobrescreve um texto que a agência já tenha
     * customizado em Configurações > Mensagens Padrão.
     *
     * Roda em TODO boot do container (entrypoint.sh) — por isso monta a
     * lista inteira em memória e faz UMA query de leitura + UMA de escrita
     * (insertOrIgnore em lote), em vez de um firstOrCreate() por
     * organização × tipo × canal (era ~150-200 round-trips sequenciais ao
     * banco por boot, sem nunca inserir nada depois do primeiro deploy —
     * suspeito real de lentidão no deploy, achado em 2026-07-30).
     */
    public function run(): void
    {
        $templates = [
            'onboarding_boas_vindas' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! 🎉 Seja muito bem-vindo(a) à Nonna! A partir de agora, {{cliente}} conta com a gente pra cuidar da sua presença digital. Nos próximos dias vamos te chamar por aqui pra combinar os primeiros passos — qualquer dúvida, é só chamar!\n\nUm abraço,\n{{responsavel_agencia}}",
                ],
                'email' => [
                    'subject' => 'Bem-vindo(a) à Nonna, {{contato}}!',
                    'body'    => "Olá {{contato}}, tudo bem?\n\nÉ com muita alegria que damos as boas-vindas à {{cliente}} na Nonna Agência Digital! A partir de agora, nosso time vai cuidar de perto da presença digital de vocês.\n\nNos próximos dias, {{responsavel_agencia}} vai entrar em contato pra alinhar os primeiros passos do onboarding. Qualquer dúvida, estamos à disposição.\n\nSeja bem-vindo(a)!\nEquipe Nonna",
                ],
            ],
            'chamado_aberto' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Recebemos seu chamado \"{{chamado_titulo}}\" e nosso time já está por dentro. Assim que tivermos novidades, avisamos por aqui.\n\nPra acompanhar: {{link_chamado}}",
                ],
                'email' => [
                    'subject' => 'Recebemos seu chamado: {{chamado_titulo}}',
                    'body'    => "Olá {{contato}},\n\nConfirmamos o recebimento do seu chamado \"{{chamado_titulo}}\". Nosso time já foi notificado e vai começar a tratar sua solicitação.\n\nVocê pode acompanhar o andamento por aqui: {{link_chamado}}\n\nQualquer novidade, avisamos por aqui.\n\nEquipe Nonna",
                ],
            ],
            'chamado_concluido' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Seu chamado \"{{chamado_titulo}}\" foi concluído ✅.\n\nDá uma olhada nos detalhes aqui: {{link_chamado}}\n\nQualquer coisa, é só chamar!",
                ],
                'email' => [
                    'subject' => 'Chamado concluído: {{chamado_titulo}}',
                    'body'    => "Olá {{contato}},\n\nSeu chamado \"{{chamado_titulo}}\" foi concluído pela nossa equipe.\n\nVocê pode conferir os detalhes por aqui: {{link_chamado}}\n\nSe precisar de algo mais, é só nos chamar.\n\nEquipe Nonna",
                ],
            ],
            'reuniao_lembrete' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Passando pra lembrar da nossa reunião marcada pra {{data_reuniao}}.\n\nNos vemos lá: {{link_reuniao}}\n\nAté breve! 😊",
                ],
                'email' => [
                    'subject' => 'Lembrete: reunião marcada para {{data_reuniao}}',
                    'body'    => "Olá {{contato}},\n\nEste é um lembrete da nossa reunião agendada para {{data_reuniao}}.\n\nLink de acesso: {{link_reuniao}}\n\nQualquer imprevisto, nos avise com antecedência.\n\nAté breve!\nEquipe Nonna",
                ],
            ],
            'aprovacao' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! O material \"{{tarefa}}\" está pronto e aguardando sua aprovação.\n\nDá uma olhada aqui: {{link_aprovacao}}",
                ],
                'email' => [
                    'subject' => 'Material aguardando aprovação: {{tarefa}}',
                    'body'    => "Olá {{contato}},\n\nO material \"{{tarefa}}\" está pronto e aguardando sua aprovação.\n\nAcesse o link abaixo pra revisar e aprovar (ou solicitar ajustes):\n{{link_aprovacao}}\n\nFicamos no aguardo do seu retorno.\n\nEquipe Nonna",
                ],
            ],
            'financeiro' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Sua nota referente aos anúncios já está disponível — valor de {{valor}}, vencimento em {{vencimento}}.\n\nAcesse aqui: {{link_fatura}}",
                ],
                'email' => [
                    'subject' => 'Nova nota disponível — {{cliente}}',
                    'body'    => "Olá {{contato}},\n\nInformamos que uma nova nota referente aos investimentos em anúncios de {{cliente}} está disponível.\n\nValor: {{valor}}\nVencimento: {{vencimento}}\n\nAcesse aqui: {{link_fatura}}\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna",
                ],
            ],
            'cobranca' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Passando pra lembrar do vencimento da fatura de {{cliente}} — valor {{valor}}, vencimento {{vencimento}}.\n\nAcesse aqui: {{link_fatura}}",
                ],
                'email' => [
                    'subject' => 'Lembrete de vencimento — {{cliente}}',
                    'body'    => "Olá {{contato}},\n\nEste é um lembrete de que a fatura de {{cliente}} vence em {{vencimento}}, no valor de {{valor}}.\n\nAcesse aqui: {{link_fatura}}\n\nQualquer dúvida sobre o pagamento, é só nos chamar.\n\nEquipe Nonna",
                ],
            ],
            'cs_survey' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Queremos saber como está sendo sua experiência com a Nonna. Pode responder essa pesquisa rapidinha? 🙏\n\n{{link_pesquisa}}",
                ],
                'email' => [
                    'subject' => 'Como está sendo sua experiência com a Nonna?',
                    'body'    => "Olá {{contato}},\n\nSua opinião é muito importante pra gente! Preparamos uma pesquisa rápida pra entender como está sendo sua experiência com a Nonna.\n\nResponda aqui: {{link_pesquisa}}\n\nObrigado pelo seu tempo!\nEquipe Nonna",
                ],
            ],
            'offboarding' => [
                'whatsapp' => [
                    'body' => "Oi {{contato}}! Confirmando o encerramento do contrato de {{cliente}}, previsto para {{data_encerramento}}. Vamos te acompanhar de perto nessa transição — qualquer dúvida, estamos à disposição.",
                ],
                'email' => [
                    'subject' => 'Encerramento de contrato — {{cliente}}',
                    'body'    => "Olá {{contato}},\n\nConfirmamos o encerramento do contrato de {{cliente}} com a Nonna, previsto para {{data_encerramento}}.\n\nVamos acompanhar de perto essa transição pra garantir que tudo corra da melhor forma possível.\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna",
                ],
            ],
        ];

        $organizationIds = Organization::pluck('id');
        if ($organizationIds->isEmpty()) {
            return;
        }

        // Uma leitura só: todos os (organization_id, type, channel) já gravados,
        // pra saber o que falta sem perguntar linha por linha.
        $existing = NotificationTemplate::whereIn('organization_id', $organizationIds)
            ->get(['organization_id', 'type', 'channel'])
            ->map(fn ($row) => $row->organization_id . '|' . $row->type . '|' . $row->channel)
            ->flip();

        $now = now();
        $rows = [];

        foreach ($organizationIds as $organizationId) {
            foreach ($templates as $type => $channels) {
                foreach ($channels as $channel => $content) {
                    if (isset($existing["{$organizationId}|{$type}|{$channel}"])) {
                        continue;
                    }

                    $rows[] = [
                        'id'              => (string) Str::uuid(),
                        'organization_id' => $organizationId,
                        'type'            => $type,
                        'channel'         => $channel,
                        'subject'         => $content['subject'] ?? null,
                        'body'            => $content['body'],
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }
            }
        }

        if (empty($rows)) {
            return;
        }

        // Uma escrita só (lote) — insertOrIgnore por segurança contra corrida
        // entre containers subindo ao mesmo tempo num rolling update.
        foreach (array_chunk($rows, 200) as $chunk) {
            NotificationTemplate::query()->insertOrIgnore($chunk);
        }
    }
}
