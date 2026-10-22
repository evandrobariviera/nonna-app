{{-- Pessoa em lista curta (equipe da campanha, pauta da reunião): foto + nome numa pílula.
     $highlight = destaque roxo (ex.: responsável do cliente); $note = texto miúdo após o nome. --}}
@props(['user', 'highlight' => false, 'note' => null])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full py-0.5 pl-0.5 pr-2.5 text-xs font-semibold']) }}
      style="{{ $highlight ? 'background:rgba(100, 59, 142,.12); border:1px solid rgba(100, 59, 142,.35); color:var(--purple)' : 'background:var(--s3); border:1px solid var(--border2); color:var(--text)' }}"
      title="{{ $user->name }}{{ $note ? ' — ' . $note : '' }}">
    <x-user-avatar :user="$user" size="6" />
    <span>{{ $user->name }}@if($note)<span class="font-normal" style="opacity:.75"> · {{ $note }}</span>@endif</span>
</span>
