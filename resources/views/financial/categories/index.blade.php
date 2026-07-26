<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Financeiro</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Categorias</h1>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Nova categoria ── --}}
    <div class="card card-body mb-6">
        <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Nova categoria</p>
        <form method="POST" action="{{ route('financial-categories.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Tipo</label>
                <select name="type" required style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                    @foreach(\App\Models\FinancialCategory::$types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Nome</label>
                <input type="text" name="name" maxlength="60" required
                       style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
            </div>
            <div>
                <label class="text-xs font-mono uppercase tracking-widest mb-1 block" style="color:var(--muted)">Cor</label>
                <select name="color" style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px">
                    @foreach(['green', 'blue', 'purple', 'orange', 'red', 'yellow', 'cyan', 'muted'] as $color)
                        <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Criar</button>
        </form>
    </div>

    <div class="grid gap-4" style="grid-template-columns: repeat(2, 1fr)">
        @foreach(\App\Models\FinancialCategory::$types as $typeKey => $typeLabel)
            <div class="card card-body">
                <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">{{ $typeLabel }}</p>
                @php $group = $categories->get($typeKey, collect()); @endphp
                @if($group->isEmpty())
                    <p class="text-sm" style="color:var(--muted)">Nenhuma categoria.</p>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach($group as $category)
                            <div class="flex items-center justify-between gap-3">
                                <span class="badge badge-{{ $category->color }}">{{ $category->name }}</span>
                                <form method="POST" action="{{ route('financial-categories.update', $category) }}"
                                      onchange="this.requestSubmit()" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="name" value="{{ $category->name }}">
                                    <input type="hidden" name="color" value="{{ $category->color }}">
                                    <label class="flex items-center gap-1.5 text-xs" style="color:var(--muted2)">
                                        <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }}>
                                        {{ $category->is_active ? 'Ativa' : 'Inativa' }}
                                    </label>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
