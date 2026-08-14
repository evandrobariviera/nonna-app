@props(['task'])

<span {{ $attributes->class(['badge', 'gap-1']) }}>
    <x-icon :name="$task->typeIcon()" size="11" />
    {{ $task->typeLabel() }}
</span>
