import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeFlashToast } from '@/lib/flashToast';
import { layout, title, withApp } from '@/lib/inertia';
import { initializeLocaleSync } from '@/lib/locale';

void createInertiaApp({
    title,
    layout,
    withApp,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Reload the translations when a page arrives in another language...
initializeLocaleSync();
