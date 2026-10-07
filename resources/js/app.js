import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

// El plugin de Vite de Inertia resuelve las páginas desde ./Pages y se encarga
// del montaje, así que no hace falta resolve/setup manual.
createInertiaApp();
