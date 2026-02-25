(() => {
    const html = document.documentElement;
    const themeKey = 'vote_theme';
    const contrastKey = 'vote_contrast_high';

    const safeGet = (key) => {
        try {
            return localStorage.getItem(key);
        } catch (_) {
            return null;
        }
    };

    const safeSet = (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch (_) {
            // ignore
        }
    };

    const themeButtons = () => Array.from(document.querySelectorAll('#btn-theme-toggle'));
    const contrastButtons = () => Array.from(document.querySelectorAll('#btn-contrast-toggle'));

    const setPressed = (els, on) => {
        els.forEach((el) => el.setAttribute('aria-pressed', on ? 'true' : 'false'));
    };

    const updateThemeIcons = (theme) => {
        const iconClass = theme === 'dark' ? 'bi-sun' : 'bi-moon-stars';
        themeButtons().forEach((btn) => {
            const icon = btn.querySelector('i');
            if (!icon) return;
            icon.className = `bi ${iconClass}`;
        });
    };

    const applyTheme = (theme) => {
        const next = theme === 'dark' ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', next);
        safeSet(themeKey, next);
        setPressed(themeButtons(), next === 'dark');
        updateThemeIcons(next);
    };

    const applyContrast = (on) => {
        const enabled = !!on;
        html.classList.toggle('contrast-high', enabled);
        safeSet(contrastKey, enabled ? '1' : '0');
        setPressed(contrastButtons(), enabled);
    };

    const preferredTheme = () => {
        const saved = safeGet(themeKey);
        if (saved === 'dark' || saved === 'light') return saved;
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    };

    applyTheme(preferredTheme());
    applyContrast(safeGet(contrastKey) === '1');

    document.addEventListener('click', (e) => {
        const target = e.target.closest?.('#btn-theme-toggle, #btn-contrast-toggle');
        if (!target) return;
        e.preventDefault();

        if (target.id === 'btn-theme-toggle') {
            const cur = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            applyTheme(cur === 'dark' ? 'light' : 'dark');
        }
        if (target.id === 'btn-contrast-toggle') {
            const enabled = html.classList.contains('contrast-high');
            applyContrast(!enabled);
        }
    });

    window.addEventListener('storage', (e) => {
        if (e.key === themeKey && (e.newValue === 'dark' || e.newValue === 'light')) {
            applyTheme(e.newValue);
        }
        if (e.key === contrastKey) {
            applyContrast(e.newValue === '1');
        }
    });
})();

