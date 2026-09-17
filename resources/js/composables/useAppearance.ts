import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    isDark: ComputedRef<boolean>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    toggleAppearance: () => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    if (currentAppearance === 'system' || !currentAppearance) {
        updateTheme(currentAppearance || 'light');
    }
};

const appearance = ref<Appearance>(
    typeof window !== 'undefined'
        ? (getStoredAppearance() || 'light')
        : 'light',
);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance() || 'light';
    appearance.value = savedAppearance;
    updateTheme(savedAppearance);

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const isDark = computed<boolean>(() => {
        if (appearance.value === 'system') {
            return prefersDark();
        }
        return appearance.value === 'dark';
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return isDark.value ? 'dark' : 'light';
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    function toggleAppearance() {
        updateAppearance(isDark.value ? 'light' : 'dark');
    }

    return {
        appearance,
        isDark,
        resolvedAppearance,
        updateAppearance,
        toggleAppearance,
    };
}
