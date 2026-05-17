@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])
@php
$base = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 active:scale-95 disabled:opacity-50';
$variants = [
    'primary'  => 'bg-[#76a72b] hover:bg-[#659220] text-white shadow-sm',
    'secondary'=> 'bg-white dark:bg-[#2a2a2a] border border-[#ababab]/40 text-[#373737] dark:text-white hover:border-[#76a72b] hover:text-[#76a72b]',
    'danger'   => 'bg-red-50 border border-red-200 text-red-600 hover:bg-red-100',
    'ghost'    => 'text-[#878787] hover:text-[#373737] dark:hover:text-white hover:bg-[#efeded] dark:hover:bg-white/10',
];
$cls = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
