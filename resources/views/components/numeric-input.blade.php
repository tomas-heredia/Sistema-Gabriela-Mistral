@props(['disabled' => false])

{{--
    Bloquea la tecla en sí (no solo valida después): letras, símbolos y
    pegado de texto no numérico nunca llegan a aparecer en el campo. La
    validación del lado del servidor (regex en las reglas del componente)
    sigue estando, esto es un refuerzo de UX, no un reemplazo.
--}}
<x-text-input
    :disabled="$disabled"
    inputmode="numeric"
    autocomplete="off"
    x-on:keydown="if (['Backspace','Tab','Delete','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes($event.key) || $event.ctrlKey || $event.metaKey) return; if (!/^[0-9]$/.test($event.key)) $event.preventDefault()"
    x-on:paste="$event.preventDefault(); document.execCommand('insertText', false, ($event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, ''))"
    {{ $attributes }}
/>
