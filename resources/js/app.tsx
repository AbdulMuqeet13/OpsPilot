import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings-layout';

const appName = import.meta.env.VITE_APP_NAME || 'OpsPilot';

void createInertiaApp({
    title: (title) => title || appName,
    layout: (name) => {
        if (name === 'welcome' || name.startsWith('public/')) {
            return null;
        }

        if (name.startsWith('auth/')) {
            return AuthLayout;
        }

        if (name.startsWith('settings/')) {
            return [AppLayout, SettingsLayout];
        }

        return AppLayout;
    },
    progress: {
        color: '#64748b',
    },
});
