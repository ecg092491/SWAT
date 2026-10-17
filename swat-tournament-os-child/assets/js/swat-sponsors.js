/**
 * Game Day sponsor view reporting (child 1.0.33).
 *
 * A view counts when at least half of a banner is on screen for one second
 * (the common viewability standard). Banners inside hidden Game Day views
 * never intersect, so they only count once the parent opens that view.
 * Each sponsor/placement is reported once per browser visit (sessionStorage),
 * so refreshing the schedule all weekend does not inflate the numbers.
 * Clicks are counted server-side by the tracked link, not here.
 */
(function () {
	'use strict';
	var cfg = window.SWATSponsors || {};
	var strips = document.querySelectorAll('.swat-sponsor-strip[data-swat-sponsor-id]');
	if (!cfg.viewUrl || !strips.length || !('IntersectionObserver' in window)) return;

	var STORE = 'swatSponsorSeenV1';
	var memory = {};
	function seen(key) {
		if (memory[key]) return true;
		try { return (JSON.parse(sessionStorage.getItem(STORE) || '{}') || {})[key] === 1; } catch (e) { return false; }
	}
	function remember(key) {
		memory[key] = true;
		try {
			var all = JSON.parse(sessionStorage.getItem(STORE) || '{}') || {};
			all[key] = 1;
			sessionStorage.setItem(STORE, JSON.stringify(all));
		} catch (e) { /* storage blocked: in-memory only */ }
	}
	function report(id, slot) {
		var body = new URLSearchParams({ id: id, slot: slot });
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(cfg.viewUrl, body)) return;
		} catch (e) { /* fall through */ }
		try { fetch(cfg.viewUrl, { method: 'POST', body: body, keepalive: true, credentials: 'omit' }); } catch (e) { /* ignore */ }
	}

	var timers = new Map();
	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			var el = entry.target;
			var id = el.getAttribute('data-swat-sponsor-id');
			var slot = el.getAttribute('data-swat-sponsor');
			var key = id + '|' + slot;
			if (seen(key)) { observer.unobserve(el); return; }
			if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
				if (!timers.has(el)) {
					timers.set(el, setTimeout(function () {
						timers.delete(el);
						if (seen(key)) return;
						remember(key);
						report(id, slot);
						observer.unobserve(el);
					}, 1000));
				}
			} else if (timers.has(el)) {
				clearTimeout(timers.get(el));
				timers.delete(el);
			}
		});
	}, { threshold: [0, 0.5, 1] });

	Array.prototype.forEach.call(strips, function (el) { observer.observe(el); });
})();
