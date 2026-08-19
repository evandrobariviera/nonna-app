<x-portal-layout>
    <x-slot name="title">Central de Leads</x-slot>

    <div class="max-w-2xl mx-auto py-10 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5" style="background: var(--purple-soft, rgba(100,59,142,.12))">
            <x-icon name="megaphone" size="26" style="color: var(--purple)" />
        </div>

        <h1 class="text-2xl font-black mb-3" style="color: var(--text)">Central de Leads</h1>
        <p class="text-sm leading-relaxed mb-8" style="color: var(--muted)">
            Reúna num só lugar os leads que chegam pelo site, Facebook/Instagram e WhatsApp de
            {{ $client->company_name }} — cada conversão vira um cartão num Kanban, com histórico completo
            de quem falou com quem e em que estágio cada oportunidade está. Sua equipe acompanha tudo
            direto por aqui, sem depender de planilha.
        </p>

        <div class="grid grid-cols-3 gap-4 mb-8 text-left">
            <div class="card p-4">
                <p class="text-sm font-semibold mb-1" style="color: var(--text)">Tudo num lugar só</p>
                <p class="text-xs" style="color: var(--muted)">Site, Meta e WhatsApp normalizados no mesmo Kanban.</p>
            </div>
            <div class="card p-4">
                <p class="text-sm font-semibold mb-1" style="color: var(--text)">Sem duplicidade</p>
                <p class="text-xs" style="color: var(--muted)">Mesma pessoa nunca vira lead duplicado.</p>
            </div>
            <div class="card p-4">
                <p class="text-sm font-semibold mb-1" style="color: var(--text)">Histórico de verdade</p>
                <p class="text-xs" style="color: var(--muted)">Notas e mudanças de estágio, sempre com autor.</p>
            </div>
        </div>

        @if($module?->requested_at)
            <div class="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-lg"
                 style="background: var(--s2); border: 1px solid var(--border2); color: var(--muted2)">
                Pedido enviado em {{ $module->requested_at->format('d/m/Y') }} — nosso time vai entrar em contato.
            </div>
        @else
            <form method="POST" action="{{ route('portal.leads.request-module') }}">
                @csrf
                <button type="submit" class="px-6 py-3 text-sm font-semibold text-white rounded-lg"
                        style="background: var(--purple)"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Quero contratar
                </button>
            </form>
        @endif
    </div>
</x-portal-layout>
