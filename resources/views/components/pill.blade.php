@props(['color' => 'gray'])

@php
$colores = [
    'green' => 'bg-green-100 text-green-800',
    'amber' => 'bg-amber-100 text-amber-800',
    'red' => 'bg-red-100 text-red-800',
    'gray' => 'bg-gray-100 text-gray-700',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium '.($colores[$color] ?? $colores['gray'])]) }}>
    {{ $slot }}
</span>
