import { createInertiaApp } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initOfflineQueue } from '@/lib/offline-queue';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Offline-Warteschlange (M9): gepufferte Baustellen-Erfassungen werden
// bei Verbindung nachsynchronisiert (Architekturblatt Abschnitt 9).
initOfflineQueue((summary) => {
    if (summary.sent > 0) {
        toast.success(
            summary.sent === 1
                ? 'Offline-Erfassung nachsynchronisiert.'
                : `${summary.sent} Offline-Erfassungen nachsynchronisiert.`,
        );
    }

    summary.dropped.forEach((label) =>
        toast.error(`„${label}“ wurde vom Server abgelehnt und verworfen.`),
    );
});

// PWA: Service Worker macht BauPilot installierbar (eigenes Fenster am
// Desktop) und zeigt ohne Netz eine Offline-Seite. Im Vite-Dev-Server
// nicht registrieren — dort stört er das Hot-Reloading nur.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js');
    });
}
