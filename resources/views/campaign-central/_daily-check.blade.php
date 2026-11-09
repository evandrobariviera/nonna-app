{{-- Conferência diária: checklist de 5 minutos, marcado por gestor e por dia.
     Na conferência só se olha, não se mexe — o que estiver errado já aparece
     em "Resolver agora". Salva a cada clique (POST campaign-central.check). --}}
@php $_dcChecked = collect($check?->items ?? []); @endphp
<section class="card p-5"
         x-data="{ items: @js($_dcChecked->values()), saving: false,
                   async toggle(item, checked) {
                       this.saving = true;
                       const res = await fetch('{{ route('campaign-central.check') }}', {
                           method: 'POST',
                           headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                           body: JSON.stringify({ item, checked }),
                       }).catch(() => null);
                       if (res && res.ok) this.items = (await res.json()).items;
                       this.saving = false;
                   } }">
    <h2 class="card-title">Conferência diária <small class="font-normal text-xs" style="color:var(--muted)">5 min · só sua</small></h2>
    <p class="text-xs mt-1 mb-2" style="color:var(--muted2)">Na conferência só se olha, não se mexe. Se algo estiver errado, já está em "Resolver agora".</p>
    @foreach(\App\Models\CampaignDailyCheck::$items as $key => $label)
        <label class="flex gap-2 items-start py-2 text-sm cursor-pointer" style="{{ !$loop->first ? 'border-top:1px solid var(--border)' : '' }}">
            <input type="checkbox" class="mt-0.5" style="accent-color:var(--purple)"
                   :checked="items.includes('{{ $key }}')" @change="toggle('{{ $key }}', $event.target.checked)">
            <span :style="items.includes('{{ $key }}') ? 'color:var(--muted); text-decoration:line-through' : ''">{{ $label }}</span>
        </label>
    @endforeach
    <p class="text-xs mt-2" style="color:var(--muted)">
        <span x-text="items.length"></span> de {{ count(\App\Models\CampaignDailyCheck::$items) }} conferidos hoje
        <span x-show="saving">· salvando…</span>
    </p>
</section>
