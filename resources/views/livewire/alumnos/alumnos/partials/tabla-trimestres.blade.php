<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trimestre</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
            <th class="px-4 py-2"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach ($boletin->trimestres->sortBy('trimestre') as $trimestre)
            <tr wire:key="trimestre-{{ $trimestre->id }}">
                <td class="px-4 py-2 text-sm text-gray-900">
                    {{ $trimestre->etiqueta() }}
                </td>
                <td class="px-4 py-2 text-sm">
                    <x-pill :color="match ($trimestre->estado->value) {
                        'enviado' => 'green',
                        'cargado' => 'amber',
                        default => 'gray',
                    }">
                        {{ ucfirst($trimestre->estado->value) }}
                    </x-pill>
                </td>
                <td class="px-4 py-2 text-right text-sm">
                    @if ($trimestre->estado->value !== 'enviado')
                        <a href="{{ route('boletines.trimestres.cargar', $trimestre) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">
                            Cargar
                        </a>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
