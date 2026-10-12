import Alpine from 'alpinejs';
import './echo';
import { initCharts } from './charts';
import { initChat } from './chat';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    initChat();
});
