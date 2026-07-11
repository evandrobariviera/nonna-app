{{-- Partial reutilizado em create e edit --}}

@php $isEdit = isset($client) && $client->exists; @endphp

@php
$inputStyle = "background:var(--s1); border:1px solid var(--border2); color:var(--text); outline:none; font-family:'Syne',sans-serif";
$inputMono  = "background:var(--s1); border:1px solid var(--border2); color:var(--text); outline:none; font-family:'IBM Plex Mono',monospace";
$selectStyle = "background:var(--s1); border:1px solid var(--border2); color:var(--muted2); outline:none; cursor:pointer; font-family:'Syne',sans-serif";
@endphp

{{-- BLOCO 01: EMPRESA --}}
<div class="card mb-4">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">01</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Dados da Empresa</h3>
    </div>
    <div class="p-6 grid grid-cols-1 gap-5 md:grid-cols-2">

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Logomarca</label>
            <div class="flex items-center gap-3">
                @if($isEdit && $client->logoUrl())
                    <img src="{{ $client->logoUrl() }}" alt="Logo" class="h-12 w-12 rounded-lg object-cover flex-shrink-0" style="border:1px solid var(--border2)">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                    class="flex-1 text-sm" style="color:var(--muted2)">
            </div>
            @error('logo') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
            @if($isEdit && $client->logoUrl())
                <label class="flex items-center gap-2 mt-2 cursor-pointer">
                    <input type="checkbox" name="remove_logo" value="1">
                    <span class="text-xs" style="color:var(--muted)">Remover logomarca atual</span>
                </label>
            @endif
        </div>

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Razão Social <span style="color:var(--orange)">*</span></label>
            <input type="text" name="company_name" value="{{ old('company_name', $client->company_name) }}"
                placeholder="Nome jurídico da empresa"
                class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            @error('company_name') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">CPF / CNPJ</label>
            <input type="text" name="tax_id" value="{{ old('tax_id', $client->tax_id) }}"
                placeholder="000.000.000-00 ou 00.000.000/0000-00"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div>
            <label class="stat-label block mb-2">E-mail de Contato</label>
            <input type="email" name="contact_email" value="{{ old('contact_email', $client->contact_email) }}"
                placeholder="contato@empresa.com"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
            @error('contact_email') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">Telefone / WhatsApp</label>
            <input type="text" name="contact_phone" value="{{ old('contact_phone', $client->contact_phone) }}"
                placeholder="(00) 00000-0000"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div>
            <label class="stat-label block mb-2">CEP</label>
            <input type="text" name="zip_code" value="{{ old('zip_code', $client->zip_code) }}"
                placeholder="00000-000"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Endereço Completo</label>
            <input type="text" name="address" value="{{ old('address', $client->address) }}"
                placeholder="Rua, número, complemento, bairro, cidade – UF"
                class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
        </div>

    </div>
</div>

{{-- BLOCO 02: RESPONSÁVEL --}}
<div class="card mb-4">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">02</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Dados do Responsável</h3>
    </div>
    <div class="p-6 grid grid-cols-1 gap-5 md:grid-cols-2">

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Nome Completo</label>
            <input type="text" name="responsible_name" value="{{ old('responsible_name', $client->responsible_name) }}"
                placeholder="Nome completo do responsável"
                class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
        </div>

        <div>
            <label class="stat-label block mb-2">Data de Nascimento</label>
            <input type="date" name="responsible_birthdate"
                value="{{ old('responsible_birthdate', $client->responsible_birthdate?->format('Y-m-d')) }}"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div>
            <label class="stat-label block mb-2">Estado Civil</label>
            <select name="responsible_marital_status" class="w-full px-3 py-2 text-sm" style="{{ $selectStyle }}">
                <option value="">Selecione...</option>
                @foreach(App\Models\Client::$maritalStatuses as $key => $label)
                    <option value="{{ $key }}" {{ old('responsible_marital_status', $client->responsible_marital_status) === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="stat-label block mb-2">RG</label>
            <input type="text" name="responsible_rg" value="{{ old('responsible_rg', $client->responsible_rg) }}"
                placeholder="00.000.000-0"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div>
            <label class="stat-label block mb-2">CPF do Responsável</label>
            <input type="text" name="responsible_cpf" value="{{ old('responsible_cpf', $client->responsible_cpf) }}"
                placeholder="000.000.000-00"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Endereço do Responsável <span class="font-normal" style="color:var(--muted)">(se diferente da empresa)</span></label>
            <input type="text" name="responsible_address" value="{{ old('responsible_address', $client->responsible_address) }}"
                placeholder="Rua, número, complemento, bairro, cidade – UF"
                class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
        </div>

    </div>
</div>

{{-- BLOCO 03: COBRANÇA --}}
<div class="card mb-4">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">03</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Dados Financeiros</h3>
    </div>
    <div class="p-6 grid grid-cols-1 gap-5 md:grid-cols-2">

        <div>
            <label class="stat-label block mb-2">Forma de Pagamento</label>
            <select name="payment_method" class="w-full px-3 py-2 text-sm" style="{{ $selectStyle }}">
                <option value="">Selecione...</option>
                @foreach(App\Models\Client::$paymentMethods as $key => $label)
                    <option value="{{ $key }}" {{ old('payment_method', $client->payment_method) === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="stat-label block mb-2">Melhor Data para Cobrança</label>
            <select name="billing_day" class="w-full px-3 py-2 text-sm" style="{{ $selectStyle }}">
                <option value="">Selecione...</option>
                @foreach(App\Models\Client::$billingDays as $day)
                    <option value="{{ $day }}" {{ old('billing_day', $client->billing_day) == $day ? 'selected' : '' }}>
                        Dia {{ $day }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="stat-label block mb-2">E-mail de Cobrança</label>
            <input type="email" name="billing_email" value="{{ old('billing_email', $client->billing_email) }}"
                placeholder="financeiro@empresa.com"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
            @error('billing_email') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="stat-label block mb-2">WhatsApp de Cobrança</label>
            <input type="text" name="billing_whatsapp" value="{{ old('billing_whatsapp', $client->billing_whatsapp) }}"
                placeholder="(00) 00000-0000"
                class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}">
        </div>

        <div class="md:col-span-2">
            <label class="stat-label block mb-2">Observações Financeiras</label>
            <textarea name="billing_notes" rows="3"
                placeholder="Notas sobre pagamento, NF, condições especiais..."
                class="w-full px-3 py-2 text-sm resize-y"
                style="{{ $inputStyle }}; line-height:1.6">{{ old('billing_notes', $client->billing_notes) }}</textarea>
        </div>

    </div>
</div>

{{-- BLOCO 04: CONTRATO & SERVIÇOS (interno) --}}
<div class="card mb-4">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">04</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Contrato & Serviços <span class="font-normal text-xs" style="color:var(--muted)">(uso interno)</span></h3>
    </div>
    <div class="p-6 grid grid-cols-1 gap-5 md:grid-cols-2">

        <div>
            <label class="stat-label block mb-3">Serviços Contratados</label>
            <div class="grid grid-cols-2 gap-2">
                @foreach(App\Models\Client::$services as $key => $label)
                    @php $checked = in_array($key, old('contracted_services', $client->contracted_services ?? [])) @endphp
                    <label class="flex items-center gap-2.5 px-3 py-2.5 cursor-pointer transition-colors"
                           style="background:var(--s1); border:1px solid {{ $checked ? 'rgba(106,90,205,.4)' : 'var(--border)' }}">
                        <input type="checkbox" name="contracted_services[]" value="{{ $key }}"
                            {{ $checked ? 'checked' : '' }}
                            class="flex-shrink-0"
                            style="accent-color:var(--purple); width:14px; height:14px">
                        <span class="text-xs font-semibold" style="color:var(--muted2)">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <div>
                <label class="stat-label block mb-2">Status do Cliente</label>
                <select name="status" class="w-full px-3 py-2 text-sm" style="{{ $selectStyle }}">
                    @foreach(App\Models\Client::$statuses as $key => $s)
                        <option value="{{ $key }}" {{ old('status', $client->status ?? 'lead') === $key ? 'selected' : '' }}>
                            {{ $s['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="stat-label block mb-2">Segmento</label>
                <select name="segment" class="w-full px-3 py-2 text-sm" style="{{ $selectStyle }}">
                    <option value="">Selecione...</option>
                    @foreach(App\Models\Client::$segments as $seg)
                        <option value="{{ $seg }}" {{ old('segment', $client->segment) === $seg ? 'selected' : '' }}>
                            {{ $seg }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="stat-label block mb-2">Website</label>
                <input type="url" name="website" value="{{ old('website', $client->website) }}"
                    placeholder="https://..."
                    class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
                @error('website') <p class="text-xs mt-1" style="color:var(--red)">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="stat-label block mb-2">Verba Mensal em Mídia</label>
                <input type="text" name="monthly_ad_budget" value="{{ old('monthly_ad_budget', $client->monthly_ad_budget) }}"
                    placeholder="Ex: R$ 3.000 / mês"
                    class="w-full px-3 py-2 text-sm" style="{{ $inputStyle }}">
            </div>

            @if($isEdit)
            <div>
                <label class="stat-label block mb-2">ID ClickUp <span class="font-normal" style="color:var(--muted)">(correlação)</span></label>
                <input type="text" name="clickup_task_id" value="{{ old('clickup_task_id', $client->clickup_task_id) }}"
                    placeholder="Ex: 86adp72n3"
                    class="w-full px-3 py-2 text-sm" style="{{ $inputMono }}; color:var(--muted2)">
            </div>
            @endif
        </div>

    </div>
</div>

{{-- BLOCO 05: NOTAS INTERNAS --}}
<div class="card mb-6">
    <div class="card-header">
        <p class="stat-label" style="margin-bottom:2px">05</p>
        <h3 class="text-sm font-bold" style="color:var(--text)">Notas Internas</h3>
    </div>
    <div class="p-6">
        <textarea name="notes" rows="4"
            placeholder="Observações, contexto de onboarding, informações relevantes para a equipe..."
            class="w-full px-3 py-2 text-sm resize-y"
            style="{{ $inputStyle }}; line-height:1.6">{{ old('notes', $client->notes) }}</textarea>
    </div>
</div>
