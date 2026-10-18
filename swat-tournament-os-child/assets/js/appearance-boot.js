/* Runs in the document head to avoid a light/dark flash. */
(() => {
    let mode = 'dark';
    try { mode = localStorage.getItem('swatAppearanceV1') === 'light' ? 'light' : 'dark'; } catch (_) {}
    document.documentElement.dataset.swatAppearance = mode;
})();
