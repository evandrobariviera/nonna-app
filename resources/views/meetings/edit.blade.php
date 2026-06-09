<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">
                <a href="{{ route('meetings.show', $meeting) }}"
                   style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← {{ $meeting->title }}</a>
            </p>
            <h1 class="text-xl font-black" style="color:var(--text)">Editar Reunião</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl">

        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm"
                 style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meetings.update', $meeting) }}"
              x-data="{ modality: '{{ old('modality', $meeting->modality) }}' }">
            @csrf @method('PATCH')

            <div class="card p-6 space-y-5">

                {{-- Título --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $meeting->title) }}" required
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                </div>

                {{-- Tipo + Modalidade --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Tipo de Reunião <span style="color:var(--orange)">*</span>
                        </label>
                        <select name="type" required
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            @foreach(\App\Models\Meeting::$types as $key => $label)
                                <option value="{{ $key }}" {{ old('type', $meeting->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Modalidade <span style="color:var(--orange)">*</span>
                        </label>
                        <select name="modality" x-model="modality" required
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            @foreach(\App\Models\Meeting::$modalities as $key => $label)
                                <option value="{{ $key }}" {{ old('modality', $meeting->modality) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Data + Duração + Status --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Data e Hora <span style="color:var(--orange)">*</span>
                        </label>
                        <input type="datetime-local" name="scheduled_at" required
                            value="{{ old('scheduled_at', $meeting->scheduled_at->format('Y-m-d\TH:i')) }}"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Duração (min)
                        </label>
                        <input type="number" name="duration_minutes" min="5" max="480"
                            value="{{ old('duration_minutes', $meeting->duration_minutes) }}"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Status <span style="color:var(--orange)">*</span>
                        </label>
                        <select name="status" required
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            @foreach(\App\Models\Meeting::$statuses as $key => $s)
                                <option value="{{ $key }}" {{ old('status', $meeting->status) === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Link online --}}
                <div x-show="modality === 'online'" x-cloak>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Link da Reunião
                    </label>
                    <input type="url" name="online_link" value="{{ old('online_link', $meeting->online_link) }}"
                        placeholder="https://meet.google.com/..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Local físico --}}
                <div x-show="modality !== 'online'" x-cloak>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Local
                    </label>
                    <input type="text" name="location" value="{{ old('location', $meeting->location) }}"
                        placeholder="Endereço ou sala..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                </div>

                {{-- Cliente + Oportunidade --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Cliente (opcional)
                        </label>
                        <select name="client_id"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">— sem cliente —</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" {{ old('client_id', $meeting->client_id) === $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                            Oportunidade (opcional)
                        </label>
                        <select name="opportunity_id"
                            class="w-full px-4 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">— sem oportunidade —</option>
                            @foreach($opportunities as $o)
                                <option value="{{ $o->id }}" {{ old('opportunity_id', $meeting->opportunity_id) === $o->id ? 'selected' : '' }}>{{ $o->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Organizador --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Organizador <span style="color:var(--orange)">*</span>
                    </label>
                    <select name="organized_by" required
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('organized_by', $meeting->organized_by) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Participantes --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Participantes
                    </label>
                    @php $currentParticipants = $meeting->participants->pluck('id')->toArray(); @endphp
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($users as $u)
                            <label class="flex items-center gap-2 text-sm cursor-pointer py-1" style="color:var(--text)">
                                <input type="checkbox" name="participants[]" value="{{ $u->id }}"
                                    {{ in_array($u->id, old('participants', $currentParticipants)) ? 'checked' : '' }}
                                    style="accent-color:var(--purple)">
                                {{ $u->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Pauta --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                        Pauta
                    </label>
                    <textarea name="agenda" rows="5"
                        placeholder="Tópicos que serão abordados..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ old('agenda', $meeting->agenda) }}</textarea>
                </div>

            </div>

            {{-- Seção ATA --}}
            <div id="ata" class="card p-6 space-y-5 mt-5">
                <div>
                    <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">ATA da Reunião</p>
                    <p class="text-xs" style="color:var(--muted)">Preencha após a realização da reunião.</p>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">ATA</label>
                    <textarea name="ata" rows="7"
                        placeholder="Registro do que foi discutido e decidido..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ old('ata', $meeting->ata) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Próximos Passos</label>
                    <textarea name="next_steps" rows="4"
                        placeholder="Ações definidas, responsáveis, prazos..."
                        class="w-full px-4 py-2.5 text-sm focus:outline-none resize-none"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">{{ old('next_steps', $meeting->next_steps) }}</textarea>
                </div>
            </div>

            {{-- Ações --}}
            <div class="flex items-center gap-4 mt-5">
                <button type="submit"
                    class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                    style="background:var(--purple)">
                    Salvar Alterações
                </button>
                <a href="{{ route('meetings.show', $meeting) }}"
                   class="text-xs font-mono transition-colors" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    Cancelar
                </a>
            </div>

        </form>
    </div>

</x-app-layout>
