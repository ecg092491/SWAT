/**
 * SWAT homepage behaviour (child 1.0.19): accessible disclosures (collapsed by
 * default) and the hero shortcuts. Without JavaScript a <noscript> rule in
 * front-page.php shows the panels, so no content is unreachable.
 */
(() => {
	'use strict';

	const home = document.querySelector('[data-swh]');
	if (!home) return;

	function setOpen(box, open) {
		const toggle = box.querySelector('[data-swh-toggle]');
		const panel = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;
		if (!toggle || !panel) return;
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		panel.hidden = !open;
		box.classList.toggle('is-open', open);
	}

	const boxes = Array.from(home.querySelectorAll('[data-swh-disclosure]'));
	boxes.forEach((box) => {
		const toggle = box.querySelector('[data-swh-toggle]');
		if (!toggle) return;
		toggle.addEventListener('click', () => setOpen(box, toggle.getAttribute('aria-expanded') !== 'true'));
	});

	function openByName(name) {
		const box = home.querySelector('[data-swh-disclosure="' + name + '"]');
		if (box) setOpen(box, true);
		return box;
	}

	home.querySelectorAll('[data-swh-open]').forEach((link) => {
		link.addEventListener('click', () => openByName(link.dataset.swhOpen));
	});

	function focusFinder() {
		const input = home.querySelector('[data-swat-finder-input]');
		const finder = home.querySelector('#find-team');
		if (!finder) return;
		finder.scrollIntoView({block: 'start'});
		if (input) input.focus({preventScroll: true});
	}

	home.querySelectorAll('[data-swh-find]').forEach((link) => {
		link.addEventListener('click', (event) => {
			event.preventDefault();
			if (history.replaceState) history.replaceState(null, '', '#find-team');
			focusFinder();
		});
	});

	if (location.hash === '#tournaments') openByName('upcoming');
	if (location.hash === '#previous-tournaments') openByName('previous');
})();
