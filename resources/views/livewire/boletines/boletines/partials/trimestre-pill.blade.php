{{--
    Un trimestre: la pill de estado (lleva a cargar/ver sus datos) y, si ya
    está enviado, un botón de imprimir al lado -- recién ahí existe el PDF
    generado (ver GenerarYEnviarBoletinPdf).
--}}
<div class="inline-flex items-center gap-1" wire:key="trimestre-pill-{{ $trimestre->id }}">
    <a href="{{ route('boletines.trimestres.cargar', $trimestre) }}" wire:navigate>
        <x-pill :color="match ($trimestre->estado->value) {
            'enviado' => 'green',
            'cargado' => 'amber',
            default => 'gray',
        }">
            {{ $trimestre->trimestre === 4 ? 'Apoyo' : "T{$trimestre->trimestre}" }}
        </x-pill>
    </a>

    @if ($trimestre->estado->value === 'enviado')
        <a
            href="{{ route('boletines.trimestres.pdf.imprimir', $trimestre) }}"
            target="_blank"
            title="Imprimir {{ $trimestre->trimestre === 4 ? 'Etapa de Apoyo' : "Trimestre {$trimestre->trimestre}" }}"
            class="text-gray-400 hover:text-indigo-600 transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 7V3.5A.5.5 0 0 1 6 3h8a.5.5 0 0 1 .5.5V7" />
                <rect x="3" y="7" width="14" height="6" rx="1" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 13v3.5a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V13" />
            </svg>
            <span class="sr-only">Imprimir</span>
        </a>
    @endif
</div>
