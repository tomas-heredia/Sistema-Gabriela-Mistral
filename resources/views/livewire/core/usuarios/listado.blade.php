<div>
    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="w-full max-w-sm">
                <label for="busqueda" class="sr-only">Buscar por nombre o correo</label>
                <input
                    type="text"
                    id="busqueda"
                    wire:model.live.debounce.400ms="busqueda"
                    placeholder="Buscar por nombre o correo…"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            <a href="{{ route('usuarios.crear') }}" wire:navigate>
                <x-primary-button>Nuevo usuario</x-primary-button>
            </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($usuarios->isEmpty())
                <p class="text-center text-gray-500 py-12">No encontramos ningún usuario.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($usuarios as $usuario)
                            <tr wire:key="usuario-{{ $usuario->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $usuario->name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $usuario->email }}</td>
                                <td class="px-6 py-3 text-sm">
                                    <x-pill :color="match ($usuario->roles->first()?->name) {
                                        'administrador' => 'green',
                                        'cobrador' => 'amber',
                                        'profesor' => 'gray',
                                        default => 'gray',
                                    }">
                                        {{ ucfirst($usuario->roles->first()?->name ?? 'Sin rol') }}
                                    </x-pill>
                                </td>
                                <td class="px-6 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    <a href="{{ route('usuarios.editar', $usuario) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">
                                        Editar
                                    </a>
                                    @if (! auth()->user()->is($usuario))
                                        <button
                                            type="button"
                                            wire:click="eliminar({{ $usuario->id }})"
                                            wire:confirm="¿Eliminar a {{ $usuario->name }}?"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            Eliminar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $usuarios->links() }}
        </div>
    </div>
</div>
