<script>
    window._aiFormProviders = @json($providers);
</script>
<div style="max-width:800px"
     x-data="{
         providerId: '{{ old('provider_id', $agent?->provider_id ?? '') }}',
         providers: window._aiFormProviders ?? [],
         get models() {
             const pid = this.providerId;
             const p = this.providers.find(function(x) { return x.id === pid; });
             return p ? (p.models || []) : [];
         }
     }">

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded text-sm"
             style="background:rgba(239,68,68,.1); color:var(--red); border:1px solid rgba(239,68,68,.25)">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="grid gap-5">

            {{-- Nome + Descrição --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">NOME DO AGENTE *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $agent?->name) }}"
                           placeholder="ex: Extrator de Kickoff"
                           required
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">DESCRIÇÃO</label>
                    <input type="text" name="description"
                           value="{{ old('description', $agent?->description) }}"
                           placeholder="Para que serve este agente…"
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
            </div>

            {{-- System Prompt --}}
            <div>
                <label class="block text-xs font-semibold mb-1"
                       style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">SYSTEM PROMPT (SKILL DO AGENTE) *</label>
                <div class="text-xs mb-2" style="color:var(--muted2)">
                    Defina o comportamento, especialidade e restrições do agente. Seja específico.
                    Variáveis disponíveis: <code style="font-family:monospace; color:var(--purple)">{client_name}</code>,
                    <code style="font-family:monospace; color:var(--purple)">{dossier_content}</code>,
                    <code style="font-family:monospace; color:var(--purple)">{kickoff_content}</code>
                </div>
                <textarea name="system_prompt" rows="10" required
                          placeholder="Você é um especialista em estratégia de marca…"
                          class="w-full rounded px-3 py-2 text-sm font-mono"
                          style="background:var(--s2); border:1px solid var(--border2); color:var(--text); line-height:1.6; resize:vertical">{{ old('system_prompt', $agent?->system_prompt) }}</textarea>
            </div>

            {{-- Provider + Modelo --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">PROVIDER *</label>
                    <select name="provider_id" required x-model="providerId"
                            class="w-full rounded px-3 py-2 text-sm"
                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        <option value="">— selecione —</option>
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}"
                                    {{ old('provider_id', $agent?->provider_id) === $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">MODELO *</label>
                    <select name="model" required
                            class="w-full rounded px-3 py-2 text-sm"
                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        <option value="">— selecione o provider primeiro —</option>
                        <template x-if="models.length > 0">
                            <template x-for="m in models" :key="m.id">
                                <option :value="m.id"
                                        :selected="m.id === '{{ old('model', $agent?->model) }}'">
                                    <span x-text="m.label"></span>
                                    (<span x-text="'in $' + m.input_per_1k.toFixed(5)"></span>
                                    / <span x-text="'out $' + m.output_per_1k.toFixed(5)"></span> por 1K)
                                </option>
                            </template>
                        </template>
                    </select>
                    <div class="text-xs mt-1" style="color:var(--muted2)">
                        Modelos mais baratos = mais econômicos. Modelos maiores = mais capacidade.
                    </div>
                </div>
            </div>

            {{-- Chave de API específica --}}
            <div>
                <label class="block text-xs font-semibold mb-1"
                       style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">CHAVE DE API ESPECÍFICA <span style="font-weight:400">(opcional — usa a padrão do provider se vazio)</span></label>
                <select name="api_key_id"
                        class="w-full rounded px-3 py-2 text-sm"
                        style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                    <option value="">— usar chave padrão do provider —</option>
                    <template x-if="providerId">
                        <template x-for="p in providers.filter(p => p.id === providerId)" :key="p.id">
                            <template x-for="k in (p.api_keys ?? [])" :key="k.id">
                                <option :value="k.id"
                                        :selected="k.id === '{{ old('api_key_id', $agent?->api_key_id) }}'">
                                    <span x-text="k.label"></span>
                                </option>
                            </template>
                        </template>
                    </template>
                </select>
            </div>

            {{-- Parâmetros --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">TEMPERATURE</label>
                    <div class="text-xs mb-1" style="color:var(--muted2)">0 = determinístico · 1 = criativo · 2 = caótico</div>
                    <input type="number" name="temperature" step="0.05" min="0" max="2"
                           value="{{ old('temperature', $agent?->temperature ?? 0.70) }}"
                           required
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">MAX TOKENS</label>
                    <div class="text-xs mb-1" style="color:var(--muted2)">Limite de tokens na resposta</div>
                    <input type="number" name="max_tokens" min="128" max="128000" step="128"
                           value="{{ old('max_tokens', $agent?->max_tokens ?? 4096) }}"
                           required
                           class="w-full rounded px-3 py-2 text-sm"
                           style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1"
                           style="color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif; letter-spacing:.05em">CONTEXTO</label>
                    <div class="text-xs mb-1" style="color:var(--muted2)">Escopo do contexto injetado</div>
                    <select name="context_scope" required
                            class="w-full rounded px-3 py-2 text-sm"
                            style="background:var(--s2); border:1px solid var(--border2); color:var(--text)">
                        <option value="global"  {{ old('context_scope', $agent?->context_scope) === 'global'  ? 'selected' : '' }}>Global (sem contexto de cliente)</option>
                        <option value="client"  {{ old('context_scope', $agent?->context_scope) === 'client'  ? 'selected' : '' }}>Por Cliente (injeta dados do cliente)</option>
                    </select>
                </div>
            </div>

            @if($agent)
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $agent?->is_active ?? true) ? 'checked' : '' }}
                           class="rounded">
                    <span class="text-sm" style="color:var(--text)">Agente ativo</span>
                </label>
            </div>
            @endif

        </div>

        {{-- Ações --}}
        <div class="flex items-center gap-3 mt-6 pt-5" style="border-top:1px solid var(--border2)">
            <button type="submit"
                    class="px-5 py-2 rounded text-sm font-bold"
                    style="background:var(--purple); color:#fff; border:none; cursor:pointer">
                {{ $agent ? 'Salvar alterações' : 'Criar agente' }}
            </button>
            <a href="{{ route('ai.agents.index') }}"
               class="text-sm"
               style="color:var(--muted)">
                Cancelar
            </a>
        </div>
    </form>
</div>
