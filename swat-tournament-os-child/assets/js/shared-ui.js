(() => {
    'use strict';
    const header = document.querySelector('.swat-site-header');
    const measure = () => document.documentElement.style.setProperty('--swat-header-height', (header?.getBoundingClientRect().height || 0) + 'px');
    if (header && window.ResizeObserver) new ResizeObserver(measure).observe(header);
    window.addEventListener('resize', measure); measure();
    const toggle = document.querySelector('.swat-menu-toggle'), nav = document.getElementById('swat-primary-nav');
    // The parent owns open/close clicks. Add Escape and outside-focus behavior without duplicate click handlers.
    const close = restore => {
        if (!toggle || !nav || toggle.getAttribute('aria-expanded') !== 'true') return;
        toggle.setAttribute('aria-expanded','false'); nav.classList.remove('is-open'); document.body.classList.remove('swat-menu-open');
        if (restore) toggle.focus();
    };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(true); });
    document.addEventListener('focusin', e => { if (header && !header.contains(e.target)) close(false); });
    document.querySelectorAll('[data-swat-home-theme]').forEach(button => button.addEventListener('click', () => {
        const mode = button.dataset.swatHomeTheme;
        document.documentElement.dataset.swatAppearance = mode; document.body.dataset.swatAppearance = mode;
        try { localStorage.setItem('swatAppearanceV1',mode); } catch (_) {}
        document.querySelectorAll('[data-swat-home-theme]').forEach(b => b.setAttribute('aria-pressed',String(b.dataset.swatHomeTheme===mode)));
    }));
    document.querySelectorAll('[data-swat-home-theme]').forEach(b => b.setAttribute('aria-pressed',String(b.dataset.swatHomeTheme===document.documentElement.dataset.swatAppearance)));
})();
