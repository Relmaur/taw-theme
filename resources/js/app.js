// 1. Import Styles (so Vite knows to compile them)
import '../css/app.css';   // Tailwind v4 utilities
import '../scss/app.scss'; // Custom SCSS (fonts, etc.)

// 2. Import Alpine
import Alpine from 'alpinejs';

// 3. Expose Alpine globally so block scripts can register components via Alpine.data()
window.Alpine = Alpine;

// 4. Start Alpine on DOMContentLoaded — this fires after ALL deferred scripts
// (both ES modules and classic defer) have executed, so every block script
// has had a chance to call Alpine.data() inside its alpine:init listener.
document.addEventListener('DOMContentLoaded', () => Alpine.start());

console.log('Vite is running. Alpine is active.');