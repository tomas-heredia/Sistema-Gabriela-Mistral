/**
 * Enter en un campo de carga de datos pasa al siguiente campo del mismo
 * formulario en vez de intentar enviarlo -- pensado para cargar seguido sin
 * soltar el teclado. Los campos de búsqueda quedan afuera a propósito
 * (marcados con data-enter-busca): ya resuelven su propio Enter con
 * wire:keydown.enter en el input (ver resources/views/livewire/**\/listado.blade.php).
 */
document.addEventListener('keydown', function (evento) {
    if (evento.key !== 'Enter') {
        return;
    }

    const campo = evento.target;

    if (!(campo instanceof HTMLElement)) {
        return;
    }

    if (campo.hasAttribute('data-enter-busca')) {
        return;
    }

    if (campo.tagName !== 'INPUT' && campo.tagName !== 'SELECT') {
        return;
    }

    const formulario = campo.closest('form');

    if (!formulario) {
        return;
    }

    const campos = Array.from(formulario.querySelectorAll('input, select, textarea, button'))
        .filter((elemento) => !elemento.disabled && elemento.offsetParent !== null);

    const siguiente = campos[campos.indexOf(campo) + 1];

    if (!siguiente) {
        return;
    }

    evento.preventDefault();
    siguiente.focus();

    if (siguiente.tagName === 'INPUT' && typeof siguiente.select === 'function') {
        siguiente.select();
    }
});
