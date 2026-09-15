{{--
    Data clicável — mostra "dd/mm/aaaa" (ou —), clique vira <input type="date">, blur/change
    salva via PATCH sem reload (mesmo mecanismo de tasks/show.blade.php, generalizado em
    resources/js/inline-field.js). Usado nas linhas de Fila/Sprint e nos cards do Board.

    Props:
    - task: instância de Task
    - field: nome do atributo de data em Task (due_date | approval_date | publish_date)
    - overdue: pinta em vermelho/negrito (cor não é reativa ao editar — mesmo limite do
      detalhe da tarefa, que também calcula a cor uma vez no PHP)
--}}
@props(['task', 'field', 'overdue' => false])

<div x-data="inlineField({ url: '{{ route('tasks.update-field', $task) }}', field: '{{ $field }}', value: @js($task->{$field}?->format('Y-m-d')) })"
     @click.stop>
    <span x-show="!editing" @click="open()"
          class="text-xs cursor-pointer hover:underline {{ $overdue ? 'font-semibold' : '' }}"
          style="color: {{ $overdue ? 'var(--red)' : 'var(--muted2)' }}; font-family:Arial,'Segoe UI',Tahoma,sans-serif"
          x-text="value ? new Date(value + 'T00:00:00').toLocaleDateString('pt-BR') : '—'"
          title="Clique para editar"></span>
    <input x-show="editing" x-cloak x-ref="input" x-model="value" type="date"
           @blur="commit()" @change="commit()" @keydown.escape="cancel()"
           class="text-xs px-1 py-0.5" style="background:var(--s3); border:1px solid var(--border2); border-radius:4px; color:var(--text); max-width:125px">
</div>
