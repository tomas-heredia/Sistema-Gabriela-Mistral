{{--
    Un trimestre: la pill de estado (lleva a cargar/ver sus datos) y, si ya
    está enviado, un botón de descarga al lado -- recién ahí existe el PDF
    generado (ver GenerarYEnviarBoletinPdf).
--}}
<div class="inline-flex items-center gap-1" wire:key="trimestre-pill-{{ $trimestre->id }}">
    <a href="{{ route('boletines.trimestres.cargar', $trimestre) }}" wire:navigate>
        <x-pill :color="match ($trimestre->estado->value) {
            'enviado' => 'green',
            'cargado' => 'amber',
            default => 'gray',
        }">
            {{ $trimestre->etiquetaCorta() }}
        </x-pill>
    </a>

    @if ($trimestre->requiere_reenvio)
        <span title="Tiene una corrección pendiente de reenviar al tutor" class="text-amber-500">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
            <span class="sr-only">Corrección pendiente de reenviar</span>
        </span>
    @endif

    @if ($trimestre->estado->value === 'enviado')
        <a
            href="{{ route('boletines.trimestres.pdf.descargar', $trimestre) }}"
            title="Descargar {{ $trimestre->etiqueta() }}"
            class="text-gray-400 hover:text-indigo-600 transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 3v9m0 0-3-3m3 3 3-3" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 14v1.5A1.5 1.5 0 0 0 5.5 17h9a1.5 1.5 0 0 0 1.5-1.5V14" />
            </svg>
            <span class="sr-only">Descargar</span>
        </a>
    @endif
</div>
