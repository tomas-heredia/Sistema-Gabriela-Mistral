<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sistema Gabriela Mistral</title>

        @include('partials.iconos-pagina')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans">
        <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
            <div class="relative min-h-screen flex flex-col items-center justify-center">
                <div class="w-full max-w-sm px-6 text-center">
                    <h1 class="text-xl font-semibold text-black dark:text-white">Sistema Gabriela Mistral</h1>
                    <p class="mt-2 text-sm">Gestión de mora, recibos de sueldo y libretas.</p>

                    <div class="mt-8">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                class="inline-flex items-center justify-center rounded-md bg-[#FF2D20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#FF2D20]/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF2D20]"
                            >
                                Ir al panel
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center rounded-md bg-[#FF2D20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#FF2D20]/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF2D20]"
                            >
                                Iniciar sesión
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
