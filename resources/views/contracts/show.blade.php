<x-app-layout>
    <x-slot name="header">Contrato — {{ $client->company_name }}</x-slot>

    {{-- BREADCRUMB + STATUS --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('clients.show', [$client, 'tab' => 'contratos']) }}" class="text-xs font-semibold transition-colors"
               style="color:var(--muted)"
               onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← {{ $client->company_name }}</a>
            <span style="color:var(--border2)">/</span>
            <span class="text-xs font-semibold" style="color:var(--text)">{{ $contract->title }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-{{ $contract->statusColor() }}">{{ $contract->statusLabel() }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col gap-6 max-w-3xl">

        {{-- DOCUMENTO GERADO (Google Docs, via esteira de onboarding / n8n) --}}
        @if($contract->document_url)
            <a href="{{ $contract->document_url }}" target="_blank" rel="noopener"
               class="card px-5 py-4 flex items-center justify-between gap-3 hover:border-[var(--purple)] transition-colors"
               style="text-decoration:none">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-xl flex-shrink-0">📄</span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold" style="color:var(--text)">Documento do contrato (Google Docs)</p>
                        <p class="text-xs truncate" style="color:var(--muted)">{{ $contract->document_url }}</p>
                    </div>
                </div>
                <span class="text-xs font-mono flex-shrink-0" style="color:var(--purple)">Abrir ↗</span>
            </a>
        @endif

        {{-- DADOS DO CONTRATO --}}
        <div class="card">
            <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                <h2 class="text-sm font-bold" style="color:var(--text)">Dados do Contrato</h2>
            </div>

            <form method="POST" action="{{ route('clients.contracts.update', [$client, $contract]) }}"
                  x-data="contractLineItems(@json($contract->line_items ?? []))"
                  @submit="$refs.lineItemsInput.value = JSON.stringify(items)"
                  class="px-5 py-5 space-y-5">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Título <span class="text-[var(--orange)]">*</span>
                    </label>
                    <input type="text" name="title" value="{{ $contract->title }}" required
                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Status</label>
                        <select name="status" class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            @foreach(\App\Models\Contract::$statuses as $key => $s)
                                <option value="{{ $key }}" {{ $contract->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Assinado em</label>
                        <input type="date" name="signed_at" value="{{ $contract->signed_at?->format('Y-m-d') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Início da vigência</label>
                        <input type="date" name="start_date" value="{{ $contract->start_date?->format('Y-m-d') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Fim da vigência</label>
                        <input type="date" name="end_date" value="{{ $contract->end_date?->format('Y-m-d') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        <p class="text-xs mt-1" style="color:var(--muted)">Deixe em branco para prazo indeterminado.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Renovação</label>
                        <select name="renewal_type" class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            <option value="">—</option>
                            @foreach(\App\Models\Contract::$renewalTypes as $key => $label)
                                <option value="{{ $key }}" {{ $contract->renewal_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Aviso prévio (dias)</label>
                        <input type="number" name="notice_period_days" min="0" value="{{ $contract->notice_period_days }}"
                               placeholder="Ex: 30"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                </div>

                <div style="border-top:1px solid var(--border2)" class="pt-5">
                    <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Financeiro</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Fee recorrente (R$)</label>
                            <input type="number" step="0.01" min="0" name="fee_value" value="{{ $contract->fee_value }}"
                                   placeholder="Ex: 3500.00"
                                   class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 font-mono focus:outline-none focus:border-[var(--purple)]">
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Tipo de fee</label>
                            <select name="fee_type" class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">—</option>
                                @foreach(\App\Models\Contract::$feeTypes as $key => $label)
                                    <option value="{{ $key }}" {{ $contract->fee_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Forma de pagamento</label>
                            <select name="payment_method" class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">—</option>
                                @foreach(\App\Models\Client::$paymentMethods as $key => $label)
                                    <option value="{{ $key }}" {{ $contract->payment_method === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Dia de cobrança</label>
                            <select name="billing_day" class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                <option value="">—</option>
                                @foreach(\App\Models\Client::$billingDays as $day)
                                    <option value="{{ $day }}" {{ (int) $contract->billing_day === $day ? 'selected' : '' }}>{{ $day }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ITENS DO CONTRATO (preço variável, sem catálogo fixo) --}}
                <div style="border-top:1px solid var(--border2)" class="pt-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                            Itens / Serviços do Contrato
                        </p>
                        <button type="button" @click="addItem()"
                                class="text-xs font-mono transition-colors" style="color:var(--purple)">
                            + Adicionar item
                        </button>
                    </div>

                    <template x-if="items.length === 0">
                        <p class="text-xs" style="color:var(--muted)">Nenhum item adicionado. Use "+ Adicionar item" para detalhar os serviços e valores deste contrato.</p>
                    </template>

                    <div class="flex flex-col gap-2">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="item.descricao" placeholder="Descrição (ex: Gestão de Tráfego Pago)"
                                       class="flex-1 bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                                <input type="number" step="0.01" min="0" x-model="item.valor" placeholder="Valor"
                                       class="w-32 bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 font-mono focus:outline-none focus:border-[var(--purple)]">
                                <select x-model="item.recorrencia"
                                        class="w-32 bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                                    <option value="mensal">Mensal</option>
                                    <option value="unico">Único</option>
                                    <option value="anual">Anual</option>
                                </select>
                                <button type="button" @click="items.splice(index, 1)" class="btn btn-danger btn-xs flex-shrink-0">✕</button>
                            </div>
                        </template>
                    </div>

                    <template x-if="items.length > 0">
                        <p class="text-xs font-mono mt-3" style="color:var(--muted2)">
                            Total dos itens: R$ <span x-text="itemsTotal().toFixed(2).replace('.', ',')"></span>
                        </p>
                    </template>

                    <input type="hidden" name="line_items" x-ref="lineItemsInput">
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Link do documento (Google Docs)</label>
                    <input type="url" name="document_url" value="{{ $contract->document_url }}"
                           placeholder="https://docs.google.com/document/d/..."
                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    <p class="text-xs mt-1" style="color:var(--muted)">Preenchido sozinho quando o contrato é gerado na esteira de onboarding (callback do n8n).</p>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Observações</label>
                    <textarea name="notes" rows="3"
                              class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2.5 focus:outline-none focus:border-[var(--purple)]">{{ $contract->notes }}</textarea>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="submit"
                            class="px-5 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background: var(--purple);">
                        Salvar Contrato
                    </button>
                </div>
            </form>

            <div class="px-5 pb-5 -mt-2">
                <form method="POST" action="{{ route('clients.contracts.destroy', [$client, $contract]) }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Remover este contrato e seus anexos?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">
                        Remover contrato
                    </button>
                </form>
            </div>
        </div>

        {{-- ANEXOS --}}
        <div class="card card-body-lg">
            <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                Anexos
                @if($contract->attachments->count() > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $contract->attachments->count() }}</span>
                @endif
            </p>

            <form method="POST" action="{{ route('contract-attachments.store', $contract) }}"
                  enctype="multipart/form-data"
                  x-data="{ dragging: false, kind: 'outro' }"
                  @dragover.prevent="dragging = true"
                  @dragleave.prevent="dragging = false"
                  @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $el.submit()"
                  class="flex flex-col gap-3">
                @csrf
                <select name="kind" x-model="kind"
                        class="w-48 bg-[var(--s3)] border border-[var(--border2)] text-xs text-[var(--text)] px-2 py-2 focus:outline-none focus:border-[var(--purple)]">
                    @foreach(\App\Models\ContractAttachment::$kinds as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label
                    :style="dragging ? 'border-color:var(--purple); background:rgba(100, 59, 142,.04)' : ''"
                    class="flex flex-col items-center justify-center w-full py-7 cursor-pointer transition-colors"
                    style="border:2px dashed var(--border2); color:var(--muted)">
                    <x-icon name="upload" size="24" class="mb-2.5" />
                    <span class="text-sm font-medium">Clique para anexar ou arraste arquivos</span>
                    <span class="text-xs mt-1" style="color:var(--muted2)">Máx. 100 MB por arquivo</span>
                    <input type="file" name="file" x-ref="fileInput" class="hidden"
                           @change="$el.closest('form').submit()">
                </label>
            </form>

            @if($contract->attachments->count() > 0)
                <div class="mt-3 flex flex-col gap-1.5">
                    @foreach($contract->attachments as $attachment)
                        <div class="flex items-center justify-between gap-3 px-4 py-3"
                             style="background:var(--s2); border:1px solid var(--border2)">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xl flex-shrink-0">{{ $attachment->icon() }}</span>
                                <div class="min-w-0">
                                    <a href="{{ $attachment->url() }}" target="_blank"
                                       class="text-sm font-medium truncate block hover:underline"
                                       style="color:var(--text)">
                                        {{ $attachment->filename }}
                                    </a>
                                    <p class="text-xs mt-0.5" style="color:var(--muted)">
                                        {{ $attachment->kindLabel() }}
                                        · {{ $attachment->sizeForHumans() }}
                                        · {{ $attachment->uploadedBy?->name }}
                                        · {{ $attachment->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <a href="{{ $attachment->url() }}" target="_blank" class="btn btn-ghost btn-xs">
                                    ↓ Baixar
                                </a>
                                <form method="POST" action="{{ route('contract-attachments.destroy', [$contract, $attachment]) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs">✕</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function contractLineItems(initialItems) {
            return {
                items: initialItems && initialItems.length ? initialItems : [],
                addItem() {
                    this.items.push({ descricao: '', valor: '', recorrencia: 'mensal' });
                },
                itemsTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.valor) || 0), 0);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
