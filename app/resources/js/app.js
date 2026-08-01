import './bootstrap';

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

// Reordenar movimientos del mismo día en la vista de cuenta
window.Sortable = Sortable;

window.Alpine = Alpine;

Alpine.start();
