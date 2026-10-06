import { initScrollTilt } from './scroll-tilt';

document.addEventListener('DOMContentLoaded', initScrollTilt);
document.addEventListener('livewire:navigated', initScrollTilt);
