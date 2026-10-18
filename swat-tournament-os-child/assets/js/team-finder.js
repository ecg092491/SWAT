/**
 * SWAT "Find my team's schedule" (child 1.0.19).
 * Filters the server-rendered weekend team index and links each result to the
 * Game Day team view for the right event. Also lists followed teams (the same
 * localStorage store Game Day uses) so returning families are one tap away.
 */
(() => {
	'use strict';

	const roots = Array.from(document.querySelectorAll('[data-swat-finder]'));
	if (!roots.length) return;

	const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
	const fold = (v) => String(v || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[’']/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
	const MAX_RESULTS = 15;

	function followedTeams() {
		try {
			const value = JSON.parse(localStorage.getItem('swat_followed_teams') || '{}');
			return value && Array.isArray(value.followedTeams) ? value.followedTeams : [];
		} catch (_) {
			return [];
		}
	}

	function resultRow(item) {
		const meta = [item.division, item.event, item.when].filter(Boolean).join(' · ');
		return '<li><a class="swat-finder__result" href="' + esc(item.url) + '"><span class="swat-finder__result-main"><strong>' + esc(item.name) + '</strong><span>' + esc(meta) + '</span></span><span class="swat-finder__go" aria-hidden="true">›</span></a></li>';
	}

	function score(item, tokens, query) {
		const name = item._name;
		let total = 0;
		for (const token of tokens) {
			const inName = name.indexOf(token);
			if (inName === -1 && item._all.indexOf(token) === -1) return -1;
			if (inName === 0) total += 6;
			else if (inName > 0 && name.charAt(inName - 1) === ' ') total += 4;
			else if (inName > 0) total += 2;
			else total += 1; // matched division or event only
		}
		if (name === query) total += 20;
		else if (name.indexOf(query) === 0) total += 8;
		return total;
	}

	roots.forEach((root) => {
		const input = root.querySelector('[data-swat-finder-input]');
		const list = root.querySelector('[data-swat-finder-results]');
		const status = root.querySelector('[data-swat-finder-status]');
		const followedRoot = root.querySelector('[data-swat-finder-followed]');
		let index = [];
		const data = root.querySelector('[data-swat-finder-index]');
		if (data) {
			try { index = JSON.parse(data.textContent || '[]'); } catch (_) { index = []; }
		}
		index.forEach((item) => {
			item._name = fold(item.name);
			item._all = fold([item.name, item.division, item.event].join(' '));
		});

		function renderFollowed() {
			// Game Day already shows followed teams in its own strip above the finder.
			if (!followedRoot || root.classList.contains('swat-finder--gameday')) return;
			const teams = followedTeams();
			if (!teams.length) { followedRoot.hidden = true; followedRoot.innerHTML = ''; return; }
			const rows = [];
			teams.forEach((team) => {
				const name = String(team.displayName || team.name || '').trim();
				if (!name) return;
				const key = fold(name);
				const ids = (Array.isArray(team.knownRegistrations) ? team.knownRegistrations : []).map((r) => String(r.teamId || '')).filter(Boolean);
				const matches = index.filter((item) => item._name === key || ids.includes(String(item.team)));
				if (matches.length) {
					matches.forEach((item) => rows.push(resultRow(item)));
				} else {
					rows.push('<li><div class="swat-finder__result is-muted"><span class="swat-finder__result-main"><strong>' + esc(name) + '</strong><span>' + (index.length ? 'Not in this weekend’s posted schedules' : 'Schedule not posted yet') + '</span></span></div></li>');
				}
			});
			followedRoot.innerHTML = '<p class="swat-finder__label">Your teams</p><ul class="swat-finder__results">' + rows.join('') + '</ul>';
			followedRoot.hidden = false;
		}

		function search() {
			if (!input || !list) return;
			const query = fold(input.value);
			if (!query) {
				list.hidden = true;
				list.innerHTML = '';
				if (status) status.textContent = '';
				return;
			}
			const tokens = query.split(' ').filter(Boolean);
			const found = index
				.map((item) => ({item, s: score(item, tokens, query)}))
				.filter((row) => row.s >= 0)
				.sort((a, b) => b.s - a.s || a.item.name.localeCompare(b.item.name, undefined, {numeric:true}) || String(a.item.date).localeCompare(String(b.item.date)))
				.map((row) => row.item);
			list.innerHTML = found.slice(0, MAX_RESULTS).map(resultRow).join('');
			list.hidden = !found.length;
			if (status) {
				status.textContent = found.length
					? (found.length === 1 ? '1 team matches.' : (found.length > MAX_RESULTS ? 'Showing ' + MAX_RESULTS + ' of ' + found.length + ' teams. Keep typing to narrow it down.' : found.length + ' teams match.'))
					: 'No team matches “' + input.value.trim() + '”. Check the spelling, or open a full schedule below.';
				status.classList.toggle('is-empty', !found.length);
			}
		}

		if (input) {
			input.addEventListener('input', search);
			input.addEventListener('keydown', (event) => {
				if (event.key === 'Enter') {
					const links = list ? list.querySelectorAll('a') : [];
					if (links.length === 1) { event.preventDefault(); window.location.assign(links[0].href); }
				} else if (event.key === 'ArrowDown' && list && !list.hidden) {
					const first = list.querySelector('a');
					if (first) { event.preventDefault(); first.focus(); }
				}
			});
			if (input.value) search();
		}
		if (list) {
			list.addEventListener('keydown', (event) => {
				if (!['ArrowDown','ArrowUp'].includes(event.key)) return;
				const links = Array.from(list.querySelectorAll('a'));
				const at = links.indexOf(document.activeElement);
				if (at === -1) return;
				event.preventDefault();
				if (event.key === 'ArrowUp' && at === 0 && input) { input.focus(); return; }
				const next = links[Math.max(0, Math.min(links.length - 1, at + (event.key === 'ArrowDown' ? 1 : -1)))];
				if (next) next.focus();
			});
		}
		renderFollowed();
		window.addEventListener('swat:teams-changed', renderFollowed);
		window.addEventListener('storage', (event) => { if (event.key === 'swat_followed_teams') renderFollowed(); });
	});
})();
