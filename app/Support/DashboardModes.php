<?php

namespace App\Support;

// "Modos" da Dashboard — o que a pessoa está fazendo AGORA (executar, distribuir,
// planejar, atender...), diferente do papel funcional, que é quem ela É. Modo nunca
// dá permissão nova: só reorganiza a Dashboard pro foco do momento. A pessoa troca
// de modo durante o dia pelo seletor no topo; o último usado fica salvo em
// organization_users.dashboard_mode.
//
// Quais modos cada pessoa tem: organization_users.dashboard_modes (configurado na aba
// Equipe). Vazio/null = sugerido pelos papéis funcionais (ver $roleDefaults). Admin/dono
// tem todos, sempre — inclusive "Visão geral", que é a Dashboard completa.
class DashboardModes
{
    public const ALL = [
        'execucao'     => ['label' => 'Execução',     'icon' => 'hammer',          'hint' => 'O que eu tenho que fazer'],
        'distribuicao' => ['label' => 'Distribuição', 'icon' => 'users',           'hint' => 'Quem vai fazer o quê'],
        'planejamento' => ['label' => 'Planejamento', 'icon' => 'map',             'hint' => 'O que precisa ser lançado'],
        'atendimento'  => ['label' => 'Atendimento',  'icon' => 'headphones',      'hint' => 'O que o cliente está esperando'],
        'midia_paga'   => ['label' => 'Mídia Paga',   'icon' => 'megaphone',       'hint' => 'Campanhas, verbas e criativos'],
        'visao_geral'  => ['label' => 'Visão geral',  'icon' => 'layout-dashboard', 'hint' => 'Tudo de uma vez'],
    ];

    // Só admin/dono — não aparece pra configurar em ninguém.
    public const ADMIN_ONLY = ['visao_geral'];

    // Papel funcional → modos sugeridos quando a pessoa ainda não foi configurada.
    // Execução todo mundo tem (é o "meu trabalho" de qualquer um).
    private const ROLE_DEFAULTS = [
        'head_criativa'    => ['distribuicao'],
        'head_tech'        => ['distribuicao'],
        'direcao_criativa' => ['distribuicao'],
        'coo'              => ['distribuicao'],
        'gestor_projetos'  => ['distribuicao', 'planejamento'],
        'estrategia'       => ['planejamento'],
        'direcao_geral'    => ['planejamento'],
        'atendimento'      => ['atendimento'],
        'trafego'          => ['midia_paga'],
        'gestor_campanhas' => ['midia_paga'],
    ];

    // Modos que podem ser marcados pra alguém na aba Equipe.
    public static function configurable(): array
    {
        return array_diff_key(self::ALL, array_flip(self::ADMIN_ONLY));
    }

    public static function suggestedFor(array $functionRoles): array
    {
        $modes = ['execucao'];
        foreach ($functionRoles as $role) {
            $modes = array_merge($modes, self::ROLE_DEFAULTS[$role] ?? []);
        }

        return self::ordered($modes);
    }

    /**
     * Modos que a pessoa pode usar, na ordem do catálogo.
     *
     * @param array|null $configured organization_users.dashboard_modes (null = nunca configurado)
     */
    public static function availableFor(?array $configured, array $functionRoles, bool $isAdmin): array
    {
        if ($isAdmin) {
            return array_keys(self::ALL);
        }

        $modes = $configured === null
            ? self::suggestedFor($functionRoles)
            : self::ordered(array_diff($configured, self::ADMIN_ONLY));

        return $modes ?: ['execucao'];
    }

    // Último modo usado, se ainda for permitido; senão o padrão (Visão geral pra admin,
    // o primeiro disponível pros demais).
    public static function resolveCurrent(?string $saved, array $available, bool $isAdmin): string
    {
        if ($saved && in_array($saved, $available, true)) {
            return $saved;
        }

        return $isAdmin ? 'visao_geral' : $available[0];
    }

    private static function ordered(array $modes): array
    {
        return array_values(array_filter(array_keys(self::ALL), fn ($m) => in_array($m, $modes, true)));
    }
}
