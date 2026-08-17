<x-app-layout>
<x-slot name="header">
    <span class="text-sm font-semibold" style="color:var(--text)">Agentes de IA</span>
</x-slot>

<div style="max-width:900px">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('ai.agents.index') }}"
                   class="text-sm"
                   style="color:var(--muted)">← Agentes</a>
            </div>
            <h1 class="text-xl font-bold mt-1" style="color:var(--text)">{{ $agent->name }}</h1>
            @if($agent->description)
                <p class="text-sm mt-0.5" style="color:var(--muted)">{{ $agent->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs px-2 py-1 rounded-full font-semibold"
                  style="{{ $agent->is_active ? 'background:rgba(34,197,94,.12);color:#16a34a' : 'background:rgba(239,68,68,.1);color:var(--red)' }}">
                {{ $agent->is_active ? 'Ativo' : 'Inativo' }}
            </span>
            <a href="{{ route('ai.agents.edit', $agent) }}"
               class="px-3 py-1.5 rounded text-xs font-semibold"
               style="background:var(--s2); color:var(--muted); border:1px solid var(--border2)">
                Editar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">

        {{-- Config do agente --}}
        <div class="col-span-1 grid gap-4">

            <div class="rounded-lg p-4" style="background:var(--s1); border:1px solid var(--border1)">
                <div class="text-xs font-semibold mb-3"
                     style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                    CONFIGURAÇÃO
                </div>
                <div class="grid gap-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Provider</span>
                        <span style="color:var(--text); font-weight:600">{{ $agent->provider->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Modelo</span>
                        <span style="color:var(--text); font-weight:600">{{ $agent->model }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Temperature</span>
                        <span style="color:var(--text); font-weight:600">{{ $agent->temperature }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Max tokens</span>
                        <span style="color:var(--text); font-weight:600">{{ number_format($agent->max_tokens) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Contexto</span>
                        <span style="color:var(--text); font-weight:600">{{ $agent->context_scope }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color:var(--muted)">Chave API</span>
                        <span style="color:var(--text)">
                            {{ $agent->apiKey ? $agent->apiKey->label : 'Padrão do provider' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- System Prompt --}}
            <div class="rounded-lg p-4" style="background:var(--s1); border:1px solid var(--border1)">
                <div class="text-xs font-semibold mb-2"
                     style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                    SYSTEM PROMPT
                </div>
                <pre class="text-xs whitespace-pre-wrap" style="color:var(--text2); font-family:Arial,'Segoe UI',Tahoma,sans-serif; line-height:1.6">{{ $agent->system_prompt }}</pre>
            </div>

        </div>

        {{-- Painel de teste --}}
        <div class="col-span-2" x-data="aiTestPanel()">

            <div class="rounded-lg p-5" style="background:var(--s1); border:1px solid var(--border1)">
                <div class="text-xs font-semibold mb-4"
                     style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                    PAINEL DE TESTE
                </div>

                {{-- Mensagem --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                        MENSAGEM / ENTRADA
                    </label>
                    <textarea x-model="message"
                              rows="5"
                              placeholder="Digite a mensagem para o agente... ex: 'Gere 5 opções de legenda para Instagram sobre lançamento de produto'"
                              class="w-full rounded px-3 py-2 text-sm"
                              style="background:var(--s2); border:1px solid var(--border2); color:var(--text); resize:vertical; line-height:1.6"
                              @keydown.meta.enter="run()"
                              @keydown.ctrl.enter="run()"></textarea>
                    <div class="text-xs mt-1" style="color:var(--muted2)">Ctrl+Enter / Cmd+Enter para enviar</div>
                </div>

                {{-- Contexto (opcional) --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                        CONTEXTO <span style="font-weight:400">(JSON opcional — substitui variáveis no system prompt)</span>
                    </label>
                    <input type="text"
                           x-model="contextRaw"
                           placeholder='{"client_name": "Nonna", "brand_voice": "jovem e direto"}'
                           class="w-full rounded px-3 py-2 text-sm font-mono"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Botão --}}
                <div class="flex items-center gap-3 mb-5">
                    <button @click="run()"
                            :disabled="loading || !message.trim()"
                            class="px-5 py-2 rounded text-sm font-bold flex items-center gap-2"
                            style="background:var(--purple); color:#fff; border:none; cursor:pointer"
                            :style="(loading || !message.trim()) ? 'opacity:.5;cursor:not-allowed' : ''">
                        <span x-show="!loading">▶ Executar agente</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
                            Processando… <span x-text="elapsed + 's'"></span>
                        </span>
                    </button>
                </div>

                {{-- Erro --}}
                <div x-show="error" x-cloak
                     class="mb-4 px-4 py-3 rounded text-sm"
                     style="background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
                    <span x-text="error"></span>
                </div>

                {{-- Resposta --}}
                <div x-show="response" x-cloak>
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-semibold"
                             style="color:var(--muted); font-family:Arial,'Segoe UI',Tahoma,sans-serif; letter-spacing:.05em">
                            RESPOSTA
                        </div>
                        <button @click="copy()"
                                class="text-xs px-2 py-1 rounded"
                                style="background:var(--s2); color:var(--muted); border:1px solid var(--border2); cursor:pointer">
                            Copiar
                        </button>
                    </div>
                    <div class="rounded p-4 text-sm whitespace-pre-wrap"
                         style="background:var(--s2); border:1px solid var(--border2); color:var(--text); line-height:1.7; min-height:80px; font-family:Arial,'Segoe UI',Tahoma,sans-serif"
                         x-text="response"></div>

                    <div x-show="elapsed > 0" class="text-xs mt-2" style="color:var(--muted2)">
                        Concluído em <span x-text="elapsed + 's'"></span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('aiTestPanel', () => ({
        message: '',
        contextRaw: '',
        response: '',
        loading: false,
        error: '',
        elapsed: 0,
        _timer: null,
        _agentId: '{{ $agent->id }}',
        _runUrl: '{{ route('ai.run') }}',
        _csrf: document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '',

        async run() {
            if (!this.message.trim()) return;
            this.loading = true;
            this.response = '';
            this.error = '';
            this.elapsed = 0;

            const start = Date.now();
            this._timer = setInterval(() => {
                this.elapsed = ((Date.now() - start) / 1000).toFixed(1);
            }, 100);

            let context = {};
            if (this.contextRaw.trim()) {
                try {
                    context = JSON.parse(this.contextRaw);
                } catch (e) {
                    this.error = 'Contexto inválido: deve ser JSON válido. Ex: {"client_name": "Nonna"}';
                    this.loading = false;
                    clearInterval(this._timer);
                    return;
                }
            }

            try {
                const res = await fetch(this._runUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        agent_id: this._agentId,
                        message: this.message,
                        context: context,
                        trigger: 'test_panel',
                    }),
                });

                const data = await res.json();
                if (!res.ok || data.error) {
                    this.error = data.error || 'Erro desconhecido.';
                } else {
                    this.response = data.response;
                }
            } catch (e) {
                this.error = 'Falha na conexão: ' + e.message;
            } finally {
                this.loading = false;
                clearInterval(this._timer);
            }
        },

        copy() {
            navigator.clipboard.writeText(this.response);
        },
    }));
});
</script>
</x-app-layout>
