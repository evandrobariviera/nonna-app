@props(['user', 'size' => 8, 'color' => 'var(--grad)'])

@php
    $px = $size * 4;
    $fontPx = round($px * 0.42);
    $url = $user?->avatarUrl();
    $initials = $user ? strtoupper(substr($user->name, 0, 2)) : '?';
@endphp

@if($url)
    <img src="{{ $url }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => 'rounded-full object-cover flex-shrink-0']) }}
         style="height:{{ $px }}px; width:{{ $px }}px">
@else
    <div {{ $attributes->merge(['class' => 'rounded-full flex items-center justify-center font-black text-white flex-shrink-0']) }}
         style="height:{{ $px }}px; width:{{ $px }}px; background:{{ $color }}; font-size:{{ $fontPx }}px">
        {{ $initials }}
    </div>
@endif
