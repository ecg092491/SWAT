(() => {
	'use strict';

	const app = document.querySelector('[data-swat-app]');
	if (!app || typeof SWATGameDay === 'undefined') return;

	const state = {
		data: null,
		alert: null,
		view: (app.dataset.game && app.dataset.game !== '0') ? 'game' : (() => { const params = new URLSearchParams(window.location.search); const queryView = params.get('view'); const hashView = window.location.hash.slice(1); const allowed = ['today','schedule','scores','standings','fields','weather','pitching','more','rules']; return allowed.includes(queryView) ? queryView : (allowed.includes(hashView) ? hashView : (app.dataset.initialView || 'today')); })(),
		team: app.dataset.team || '',
		field: app.dataset.field || '',
		game: app.dataset.game && app.dataset.game !== '0' ? app.dataset.game : '',
		event: new URLSearchParams(window.location.search).get('event') || '',
		filters: { division: '', team: '', field: '', date: '' },
 selectedTeam: '',
		quick: 'all',
		bracketDivision: '',
		postseasonTabChosen: false,
		roundOpen: {},
		dayInit: false,
		lastLoaded: null,
		timer: null,
		deferredInstall: null
	};

	const q = (s, r) => (r || document).querySelector(s);
	const qa = (s, r) => Array.from((r || document).querySelectorAll(s));
	const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

	const initialPayload = q('[data-swat-initial-payload]');
	if (initialPayload) {
		try {
			state.data = JSON.parse(initialPayload.textContent || '{}');
		} catch (_) {
			state.data = null;
		}
	}

	const bootGames = state.data && Array.isArray(state.data.games) ? state.data.games.slice() : [];

	function canonicalGames() {
		const current = state.data && Array.isArray(state.data.games) ? state.data.games : [];
		return state.data && Array.isArray(state.data.games) ? current : bootGames;
	}

	function previewAware(url) {
		const token = new URLSearchParams(window.location.search).get('wpvibe_preview');
		try {
			const target = new URL(url, window.location.origin);
			if (token) target.searchParams.set('wpvibe_preview', token);
			target.searchParams.set('_swat_data', '8');
			return target.toString();
		} catch (_) {
			return url;
		}
	}

	function appUrl(path, params) {
		try {
			const target = new URL(path, window.location.origin);
			const current = new URL(window.location.href);
			const previewToken = current.searchParams.get('wpvibe_preview');
			if (previewToken) target.searchParams.set('wpvibe_preview', previewToken);
			if (state.event) target.searchParams.set('event', state.event);
			Object.entries(params || {}).forEach(([key,value]) => {
				if (value !== undefined && value !== null && String(value) !== '') target.searchParams.set(key, String(value));
			});
			return target.toString();
		} catch (_) {
			return path;
		}
	}

	const norm = (v) => String(v || '').trim().toUpperCase().replace(/[_-]+/g, ' ');
	const isLive = (g) => ['LIVE','IN PROGRESS'].includes(norm(g.status));
	const isFinal = (g) => ['FINAL','COMPLETE','COMPLETED'].includes(norm(g.status));

	// 1.0.19: game times are Eastern wall-clock times. Convert them to an absolute
	// instant in America/New_York so "next game", "result pending" and sorting are
	// correct on phones set to any other time zone.
	const EVENT_TZ = 'America/New_York';
	const tzParts = new Intl.DateTimeFormat('en-US', {timeZone:EVENT_TZ, hourCycle:'h23', year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit', second:'2-digit'});
	function easternInstant(dateText, timeText) {
		const d = String(dateText || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if (!d) return null;
		const t = String(timeText || '00:00').match(/^(\d{1,2}):(\d{2})/) || [0, '0', '0'];
		const wall = Date.UTC(Number(d[1]), Number(d[2]) - 1, Number(d[3]), Number(t[1]), Number(t[2]));
		let instant = wall - easternOffset(wall);
		instant = wall - easternOffset(instant);
		return new Date(instant);
	}
	// Milliseconds Eastern time is ahead of UTC at an instant (negative: -4h or -5h).
	function easternOffset(instant) {
		const p = {};
		tzParts.formatToParts(new Date(instant)).forEach((part) => { p[part.type] = part.value; });
		return Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day), Number(p.hour) % 24, Number(p.minute), Number(p.second)) - Math.floor(instant / 1000) * 1000;
	}
	// When the phone is not on Eastern time, label times "ET" so nobody misreads them.
	const viewerIsEastern = (() => {
		try { return -new Date().getTimezoneOffset() * 60000 === easternOffset(Date.now()); } catch (_) { return true; }
	})();
	const tzSuffix = viewerIsEastern ? '' : ' ET';

	function gameDate(g) {
		if (!g || !g.date) return null;
		const d = easternInstant(g.date, g.time || '00:00');
		return d && !Number.isNaN(d.getTime()) ? d : null;
	}

	function isPastUnresolved(g) {
			const status = norm(g && g.status);
			if (isFinal(g) || isLive(g) || status.includes('DELAY') || status.includes('CANCEL')) return false;
			const when = gameDate(g);
			return Boolean(when && when.getTime() < Date.now() - (90 * 60 * 1000));
		}

		function displayStatus(g) {
			if (isFinal(g)) return 'FINAL';
			if (isLive(g)) return 'LIVE';
			const raw = norm(g && g.status);
            if (!raw) return 'STATUS TBD';
			if (isPastUnresolved(g)) return 'SCHEDULED · UPDATE OVERDUE';
			return raw === 'SCHEDULED' ? 'UPCOMING' : (raw || 'STATUS TBD');
		}

		function formatTime(value) {
			const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
			if (!match) return String(value || '');
			const hour = Number(match[1]);
			const minute = Number(match[2]);
			return (hour % 12 || 12) + ':' + String(minute).padStart(2, '0') + ' ' + (hour >= 12 ? 'PM' : 'AM');
		}

	function relative(date) {
		if (!date) return 'Not updated';
		const seconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));
		if (seconds < 10) return 'Updated just now';
		if (seconds < 60) return 'Updated just now';
		return 'Updated ' + Math.floor(seconds / 60) + ' min ago';
	}

	function setConnection(label, mode) {
		const el = q('[data-swat-connection]');
		if (!el) return;
		el.dataset.connectionMode = mode || '';
        if (mode === 'error') state.syncFailed = true;
        if (mode === 'online') state.syncFailed = false;
        el.textContent = label;
			qa('[data-swat-refresh]').forEach((button) => button.setAttribute('aria-busy', mode === 'loading' ? 'true' : 'false'));
		el.className = 'swat-status-pill' + (mode ? ' is-' + mode : '');
	}

    function updateAge() {
        const el = q('[data-swat-updated]'), chip = q('[data-swat-connection]'), bar = q('.swat-app-statusbar');
        if (!el || !chip || !bar) return;
        const info = SWATGameDayModel.syncSummary(state.data, Date.now(), navigator.onLine, state.syncFailed, chip.dataset.connectionMode === 'loading');
        bar.hidden = false;
        chip.hidden = !info.warning;
        chip.textContent = info.label;
        bar.classList.toggle('is-stale-today', info.warning);
        el.textContent = info.age;
        el.title = info.label + '. Last checked: ' + relative(state.lastLoaded);
        qa('[data-swat-refresh]').forEach(button => {
            button.textContent = info.warning ? 'Retry' : 'Refresh';
            button.setAttribute('aria-label', info.loading ? 'Checking tournament updates' : 'Refresh tournament data');
            button.setAttribute('aria-busy', String(info.loading));
        });
        const loading = q('[data-swat-loading]');
        if (loading) {
            loading.hidden = Boolean(state.data);
            loading.setAttribute('aria-busy', String(!state.data && info.loading));
            if (!state.data) loading.innerHTML = state.syncFailed ? empty('Tournament data unavailable', 'Use Retry above to check again. Scores have not been loaded.') : '<div class="swat-skeleton" role="status"><span class="screen-reader-text">Loading tournament games</span><div class="swat-skeleton-heading"></div><div class="swat-skeleton-row"></div><div class="swat-skeleton-row"></div><div class="swat-skeleton-actions"></div></div>';
        }
    }

	function empty(title, text) {
		return '<div class="swat-empty-state swat-app-empty"><strong>' + esc(title) + '</strong><p>' + esc(text) + '</p></div>';
	}

	function statusClass(g) {
		if (isLive(g)) return 'is-live';
		if (isFinal(g)) return 'is-final';
		if (norm(g.status).indexOf('DELAY') !== -1) return 'is-delayed';
		if (norm(g.status).indexOf('CANCEL') !== -1) return 'is-cancelled';
		if (isPastUnresolved(g)) return 'is-pending';
			return 'is-upcoming';
	}

	function fieldDirectionsUrl(field) {
			const map = SWATGameDay.fieldDirections && typeof SWATGameDay.fieldDirections === 'object' ? SWATGameDay.fieldDirections : {};
			if (map[field]) return String(map[field]);
			const code = String(field || '').trim().toUpperCase();
			if (/^SW\s*[- ]?\d/.test(code)) return 'https://www.google.com/maps/search/?api=1&query=3801%20Dillon%20Industrial%20Dr%2C%20Clemmons%2C%20NC%2027012';
			if (/^WP\s*[- ]?\d/.test(code)) return 'https://www.google.com/maps/search/?api=1&query=7605%20Topsider%20Dr%2C%20Clemmons%2C%20NC%2027012';
				if (code.indexOf('WFHS') === 0) return 'https://www.google.com/maps/search/?api=1&query=1735%20Lewisville%20Clemmons%20Road%2C%20Clemmons%2C%20NC%2027012';
			return '';
		}

		function facilityName(field) {
			const code = String(field || '').trim().toUpperCase();
			if (/^SW\s*[- ]?\d/.test(code)) return 'Southwest Athletics Complex';
			if (/^WP\s*[- ]?\d/.test(code)) return 'Wilson Park';
			if (code.indexOf('WFHS') === 0) return 'West Forsyth High School';
			return '';
		}

	// ---- Game Day Hub (1.0.17): shared card helpers ----
	function isPlaceholderTeam(name) {
		const value = String(name || '').trim();
		return !value || /^(tbd|tba|bye|winner\b|loser\b|seed\b|division place\b|#?\d+(st|nd|rd|th)?\s*seed\b|#?\d+(st|nd|rd|th)\b|pool\s+[a-z0-9]+\s+#?\d)/i.test(value);
	}

	function hasScore(value) {
		return SWATGameDayModel.hasScore(value);
	}

	function gameRound(g) {
		return String((g && (g.round || g.path_round)) || '').trim();
	}

	function isChampionship(g) {
		return SWATGameDayModel.isChampionship(g);
	}

	function gameLabel(g) {
		const round = gameRound(g);
		return [g.bracket_name || g.division, round || (isPoolGame(g) ? 'Pool play' : 'Bracket')].filter(Boolean).join(' · ');
	}

	function pillLabel(g) {
		if (isLive(g)) return 'Live';
		if (isFinal(g)) return 'Final';
		const raw = norm(g.status);
		if (!raw) return 'Status TBD';
		if (raw.indexOf('DELAY') !== -1) return 'Delayed';
		if (raw.indexOf('CANCEL') !== -1) return 'Cancelled';
		if (raw.indexOf('POSTPON') !== -1) return 'Postponed';
		if (isPastUnresolved(g)) return 'Result pending';
		return g.time ? formatTime(g.time) : 'Time TBD';
	}

	function shortDate(value) {
		const d = new Date(String(value) + 'T12:00:00');
		return Number.isNaN(d.getTime()) ? String(value || 'Date TBD') : d.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
	}

	function todayKey() {
		return new Intl.DateTimeFormat('en-CA', {timeZone:'America/New_York', year:'numeric', month:'2-digit', day:'2-digit'}).format(new Date());
	}

	function starButton(name, id) {
		const on = isFollowedTeam(name, id);
		return '<button type="button" class="swat-star' + (on ? ' is-on' : '') + '" data-swat-follow data-team-id="' + esc(id || name) + '" data-team-name="' + esc(name) + '" aria-pressed="' + (on ? 'true' : 'false') + '" aria-label="' + esc((on ? 'Unfollow ' : 'Follow ') + name) + '"><span aria-hidden="true">' + (on ? '★' : '☆') + '</span></button>';
	}

	function teamLine(g, side, winner) {
		const raw = String(g[side] || '');
		const name = bracketTeamLabel(raw);
		const id = String(g[side + '_id'] || '');
		const score = side === 'away' ? g.away_score : g.home_score;
		const showScore = (isFinal(g) || isLive(g)) && hasScore(score);
		const state = winner ? (winner === side ? ' is-win' : ' is-lose') : '';
		const mine = teamSide(g) === side ? ' is-mine' : '';
		let html = '<div class="swat-gc-team' + state + mine + '">';
		if (isPlaceholderTeam(raw) || isPlaceholderTeam(name)) {
			html += '<span class="swat-star-spacer" aria-hidden="true"></span><span class="swat-gc-name is-placeholder">' + esc(name) + '</span>';
		} else {
			html += starButton(name, id);
			html += '<a class="swat-gc-name" href="' + esc(appUrl(SWATGameDay.gamedayUrl, {team:id || name, event:g.event_slug || state.event})) + '">' + esc(name) + '</a>';
		}
		html += '<span class="swat-gc-score' + (showScore ? '' : ' is-missing') + '">' + (showScore ? esc(Number(score)) : 'TBD') + '</span>';
		return html + '</div>';
	}

    function scoreLabel(g) {
        const score = side => (isLive(g) || isFinal(g)) && hasScore(g[side + '_score']) ? g[side + '_score'] : 'score TBD';
        return (g.away || 'TBD') + ' ' + score('away') + ', ' + (g.home || 'TBD') + ' ' + score('home') + ', ' + displayStatus(g);
    }
	function gameCard(g, opts) {
		opts = opts || {};
		const final = isFinal(g);
		const id = String(g.id || '');
		const cls = statusClass(g);
		let winner = '';
		if (final && hasScore(g.away_score) && hasScore(g.home_score) && Number(g.away_score) !== Number(g.home_score)) {
			winner = Number(g.away_score) > Number(g.home_score) ? 'away' : 'home';
		}
		const followed = isFollowedTeam(g.away, g.away_id) || isFollowedTeam(g.home, g.home_id);
		const field = String(g.field || '');
		const directions = field ? fieldDirectionsUrl(field) : '';
		const hub = window.SWATGameDayHub && typeof window.SWATGameDayHub === 'object' ? window.SWATGameDayHub : {};
		const sponsors = hub.fieldSponsors && typeof hub.fieldSponsors === 'object' ? hub.fieldSponsors : {};
		const realGame = id && Number(id) < 900000000;
		const classes = ['swat-gc', cls];
		if (followed) classes.push('is-followed');
		if (!isPoolGame(g)) classes.push('is-bracket');
		if (isChampionship(g)) classes.push('is-championship');
		let html = '<article class="' + classes.join(' ') + '" data-game-id="' + esc(id) + '" aria-label="' + esc(scoreLabel(g)) + '">';
		html += '<div class="swat-gc-head"><span class="swat-gc-where">' +
			(field ? '<a class="swat-gc-field" href="' + esc(appUrl(SWATGameDay.gamedayUrl, {field:field, event:g.event_slug || state.event})) + '">' + esc(field) + '</a>' : '<span class="swat-gc-field">Field TBD</span>') +
			'<span class="swat-gc-label">' + esc(gameLabel(g)) + (opts.showDate ? ' · ' + esc(shortDate(g.date)) : '') + '</span></span>' +
			'<span class="swat-pill ' + cls + '">' + esc(displayStatus(g)) + '</span></div>';
		if (cls === 'is-delayed' && g.time) html += '<p class="swat-gc-alert">Scheduled ' + esc(formatTime(g.time)) + '. Watch for SWAT updates.</p>';
		if (cls === 'is-cancelled') html += '<p class="swat-gc-alert">This game is cancelled.</p>';
		const changed = changeNote(g);
		if (changed) html += '<p class="swat-gc-alert">' + esc(changed) + '</p>';
		if (!isPoolGame(g) && (isPlaceholderTeam(g.away) || isPlaceholderTeam(g.home)) && !final) html += '<p class="swat-gc-cond">' + (/^division place/i.test(String(g.away)) && /^division place/i.test(String(g.home)) ? 'Matchup set by final pool standings' : 'Matchup depends on advancement') + '</p>';
		html += '<p class="swat-gc-when">' + esc(shortDate(g.date)) + ' · ' + esc(g.time ? formatTime(g.time) + tzSuffix : 'Time TBD') + '</p>';
		html += teamLine(g, 'away', winner) + teamLine(g, 'home', winner);
		const note = [facilityName(field) || 'Venue TBD', g.event || eventLabel()].filter(Boolean).map(esc).join(' · ') + (sponsors[field] ? ' · Presented by ' + esc(sponsors[field]) : '');
		let actions = field ? '<a href="' + esc(appUrl('/gameday/help/',{field:field,event:g.event_slug || state.event})) + '">Field help</a>' : '';
		if (directions) actions += '<a href="' + esc(directions) + '" target="_blank" rel="noopener">Directions</a>';
		if (realGame && hub.pitchingEnabled !== false) actions += '<a href="' + esc(appUrl(SWATGameDay.gamedayUrl, {game:id, event:g.event_slug || state.event})) + '#game">Game + pitching</a>';
		if (realGame) actions += '<a' + (final ? ' class="swat-action-placeholder" tabindex="-1" aria-hidden="true"' : '') + ' href="' + esc(appUrl('/gameday/report-score/', {game:id, event:g.event_slug || state.event})) + '">Report score</a>';
		if (note || actions) html += '<div class="swat-gc-foot"><span class="swat-gc-note">' + note + '</span><span class="swat-gc-actions">' + actions + '</span></div>';
		return html + '</article>';
	}

	function groupedByTime(games, opts) {
		let html = '';
		let lastDate = null, lastTime = null;
		const multiDay = unique(games.map((g) => g.date)).length > 1 || Boolean(state.team);
		games.forEach((g) => {
			if (multiDay && g.date !== lastDate) {
				html += '<h3 class="swat-dayhead">' + esc(g.date === todayKey() ? 'Today · ' + shortDate(g.date) : shortDate(g.date)) + '</h3>';
				lastDate = g.date;
				lastTime = null;
			}
			const key = String(g.time || '');
			if (key !== lastTime) {
				html += '<h4 class="swat-timehead">' + esc(key ? formatTime(key) + tzSuffix : 'Time TBD') + '</h4>';
				lastTime = key;
			}
			html += gameCard(g, opts);
		});
		return html;
	}

	function renderEventMeta(all) {
		const places = q('[data-swat-event-places]');
		if (places) {
			const names = unique(all.map((g) => facilityName(g.field || '')));
			places.textContent = names.join(' · ');
			places.hidden = !names.length;
		}
		const live = q('[data-swat-event-live]');
		if (live) {
			const count = all.filter(isLive).length;
			live.textContent = count ? count + ' live' : (all.length ? 'Schedule posted' : 'Schedule pending');
			live.className = 'swat-pill ' + (count ? 'is-live' : 'is-quiet');
		}
	}

	function renderChips(games) {
		const root = q('[data-swat-chips]');
		if (!root) return;
		const divisions = unique(games.map((g) => g.division));
		const chips = [['all', 'All games'], ['live', 'Live now'], ['mine', 'My teams']].concat(divisions.length > 1 ? divisions.map((d) => ['div:' + d, d.replace(/\s+Open$/i, '')]) : []);
		const active = state.filters.division ? 'div:' + state.filters.division : (state.quick || 'all');
		root.innerHTML = chips.map(([value, label]) => '<button type="button" class="swat-chip" data-swat-chip="' + esc(value) + '" aria-pressed="' + (active === value ? 'true' : 'false') + '">' + esc(label) + '</button>').join('');
	}

	// 1.0.19: exact team identity. A synced team id matches by id; its name (resolved
	// from that id) matches bracket fixtures, which carry names but no ids. Substring
	// matching is gone: "Canes Triad Gold" must not pull in "9U Canes Triad Gold".
	function selectedTeamName() {
		if (!state.team) return '';
		for (const g of canonicalGames()) {
			if (String(g.home_id || '') === state.team) return String(g.home || '');
			if (String(g.away_id || '') === state.team) return String(g.away || '');
		}
		return /^\d+$/.test(state.team) ? '' : state.team;
	}
	function teamSide(g) {
		if (!state.team || !g) return '';
		const name = norm(selectedTeamName());
		if (String(g.away_id || '') === state.team || (name && norm(g.away) === name)) return 'away';
		if (String(g.home_id || '') === state.team || (name && norm(g.home) === name)) return 'home';
		return '';
	}

	function byContext(games) {
		let out = games.slice();
		if (state.team) {
			out = out.filter((g) => Boolean(teamSide(g)));
		}
		if (state.field) {
			const needle = state.field.toLowerCase();
			out = out.filter((g) => String(g.field || '').toLowerCase() === needle);
		}
		if (state.game && state.game !== '0') out = out.filter((g) => String(g.id || '') === String(state.game));
		return out;
	}

	function sorted(games) {
		return games.slice().sort((a,b) => {
			const da = gameDate(a), db = gameDate(b);
			if (!da && !db) return 0;
			if (!da) return 1;
			if (!db) return -1;
			return da.getTime() - db.getTime();
		});
	}

	function unique(values) {
		return Array.from(new Set(values.filter(Boolean).map((v) => String(v).trim()))).sort((a,b) => a.localeCompare(b, undefined, {numeric:true}));
	}

	function sanitizeEventSlug(value) {
			return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
		}

		function contextualizeAlert(alert, games) {
			if (!alert || typeof alert !== 'object') return null;
			const a = Object.assign({}, alert);
			const currentGames = Array.isArray(games) ? games : [];
			const level = norm(a.level || a.type || '');
			const audience = norm(a.audience || '');
			const declaredEvent = String(a.event_slug || a.event || a.tournament_slug || '').trim();

			if (declaredEvent && state.event && sanitizeEventSlug(declaredEvent) !== sanitizeEventSlug(state.event)) {
				return null;
			}

			const body = String(a.body || '');
			const ids = Array.from(new Set((body.match(/\b\d{5,}\b/g) || []).map(String)));
			let matchedGame = null;

			if (ids.length >= 2) {
				matchedGame = currentGames.find((game) => {
					const homeId = String(game.home_id || '');
					const awayId = String(game.away_id || '');
					return ids.includes(homeId) && ids.includes(awayId);
				}) || null;
			}

			if ((level.includes('SCHEDULE') || audience === 'TEAM') && ids.length >= 2 && !matchedGame) {
				return null;
			}

			if (matchedGame) {
				const when = matchedGame.date ? new Date(String(matchedGame.date) + 'T12:00:00') : null;
				const dateLabel = when && !Number.isNaN(when.getTime())
					? when.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'})
					: String(matchedGame.date || '');
				a.title = level.includes('SCHEDULE') ? 'SCHEDULE UPDATE' : (a.title || 'SWAT UPDATE');
				a.body = String(matchedGame.away || 'Away') + ' vs ' + String(matchedGame.home || 'Home') +
					' · ' + dateLabel + ' · ' + formatTime(matchedGame.time) + ' · ' + String(matchedGame.field || 'Field TBD');
				a.game_id = matchedGame.id || '';
			}

			return a;
		}

		function renderAlert() {
		const root = q('[data-swat-alerts]');
		if (!root) return;
		const a = state.alert;
		if (!a || (!a.title && !a.body) || norm(a.title).toUpperCase() === 'SWAT SCHEDULE POSTED') {
			root.innerHTML = '';
			return;
		}
		const level = norm(a.level || a.type || 'information').toLowerCase().replace(/\s+/g, '-');
		let html = '<article class="swat-alert swat-alert-' + esc(level) + '" role="alert">';
		html += '<span class="swat-alert-icon" aria-hidden="true">!</span><div class="swat-alert-body">';
		html += '<p class="swat-alert-kicker">' + esc(String(a.level || a.type || 'Information')) + '</p><strong>' + esc(a.title || 'SWAT Update') + '</strong>';
		if (a.body) html += '<p>' + esc(a.body) + '</p>';
		if (a.sent_at) html += '<small>Posted by SWAT · ' + esc(new Date(a.sent_at).toLocaleString([], {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})) + '</small>';
		html += '</div></article>';
		root.innerHTML = html;
	}

	// ---- 1.0.19: schedule changes since this phone last saw a game ----
	// Compares the latest synchronized date/time/field with what this device last
	// displayed. It is labelled "since you last checked" because that is exactly
	// what it knows; SWAT's own alerts stay in the alert banner.
	const SEEN_KEY = 'swat_seen_games_v1';
	state.changes = state.changes || {};
	function trackChanges(games) {
		let seen = {};
		try { seen = JSON.parse(localStorage.getItem(SEEN_KEY) || '{}') || {}; } catch (_) { seen = {}; }
		const now = Date.now();
		games.forEach((g) => {
			const id = String(g.id || '');
			if (!id) return;
			const prev = seen[id];
			if (prev && (prev.date !== String(g.date || '') || prev.time !== String(g.time || '') || prev.field !== String(g.field || ''))) {
				state.changes[id] = {date:prev.date, time:prev.time, field:prev.field};
			}
			seen[id] = {date:String(g.date || ''), time:String(g.time || ''), field:String(g.field || ''), at:now};
		});
		// Keep the store small: drop entries older than 21 days.
		Object.keys(seen).forEach((id) => { if (!seen[id] || now - Number(seen[id].at || 0) > 21 * 864e5) delete seen[id]; });
		try { localStorage.setItem(SEEN_KEY, JSON.stringify(seen)); } catch (_) {}
	}
	function changeNote(g) {
		const prev = state.changes[String(g.id || '')];
		if (!prev) return '';
		const was = [];
		if (prev.date !== String(g.date || '')) was.push(shortDate(prev.date));
		if (prev.time !== String(g.time || '')) was.push(prev.time ? formatTime(prev.time) : 'time TBD');
		if (prev.field !== String(g.field || '')) was.push(prev.field || 'field TBD');
		return was.length ? 'Changed since you last checked — was ' + was.join(' · ') : '';
	}

	function placeholderLabel(raw) {
		const label = bracketTeamLabel(raw);
		return /^tbd$/i.test(label) ? 'TBD' : label;
	}

	function teamGames() {
		return sorted(byContext(scheduleGames()));
	}

	function isOff(g) {
		return /CANCEL|POSTPON/.test(norm(g && g.status));
	}

	function nextTeamGame(games) {
		return games.find(isLive) || games.find((g) => !isFinal(g) && !isOff(g) && !isPastUnresolved(g)) || null;
	}

	function eventLabel() {
		const ev = (state.data && (state.data.event || state.data.loaded_event)) || {};
		return String(ev.name || '');
	}

	function directionsButton(g, cls) {
		const url = g && g.field ? fieldDirectionsUrl(g.field) : '';
		return url ? '<a class="' + cls + '" href="' + esc(url) + '" target="_blank" rel="noopener">GET DIRECTIONS<span class="screen-reader-text"> to ' + esc(facilityName(g.field) || g.field) + ' (opens maps)</span></a>' : '';
	}

	function whereLine(g) {
		const field = String(g.field || '').trim();
		const place = facilityName(field);
		if (!field) return '<strong>Field TBD</strong>';
		return place ? '<strong>' + esc(place) + '</strong> · <span class="swat-nowrap">Field ' + esc(field) + '</span>' : '<strong class="swat-nowrap">Field ' + esc(field) + '</strong>';
	}

	function opponentLine(g, side) {
		const other = side === 'home' ? 'away' : 'home';
		const raw = String(g[other] || '');
		if (isPlaceholderTeam(raw)) {
			const label = placeholderLabel(raw);
			return 'Opponent <strong>TBD</strong>' + (label && label !== 'TBD' ? ' <span class="swat-tv-dim">(' + esc(label) + ')</span>' : '') + ' — depends on other results';
		}
		return 'vs. <strong>' + esc(raw) + '</strong>';
	}

	function renderTeamPanel(root) {
		const name = selectedTeamName();
		const games = teamGames();
		const allPosted = canonicalGames().length + bracketGames().length;
		trackChanges(games);
		const following = name && isFollowedTeam(name, state.team);
		const divisions = unique(games.map((g) => g.division)).map((d) => d.replace(/\s+Open$/i, ''));
		const sub = [divisions.join(', '), eventLabel()].filter(Boolean).join(' · ');
		const findAnother = '<a class="swat-tv-another" href="' + esc(appUrl(SWATGameDay.gamedayUrl, {view:'schedule'})) + '#find-team-gameday">Find another team</a>';
		let html = '<div class="swat-tv">';
		html += '<div class="swat-tv-head"><div class="swat-tv-id"><p class="swat-app-eyebrow">YOUR TEAM</p><h2>' + esc(name || 'Team not found') + '</h2>' + (sub ? '<p class="swat-tv-sub">' + esc(sub) + '</p>' : '') + '</div>';
		if (name) html += '<button type="button" class="swat-tv-follow" data-swat-favorite aria-pressed="' + (following ? 'true' : 'false') + '"><span aria-hidden="true">' + (following ? '★' : '☆') + '</span> ' + (following ? 'Following' : 'Follow') + '</button>';
		html += '</div>';

		const next = nextTeamGame(games);
		if (next) {
			const side = teamSide(next);
			const live = isLive(next);
			const raw = norm(next.status);
			const delayed = raw.indexOf('DELAY') !== -1;
			const label = live ? 'PLAYING NOW' : (delayed ? 'NEXT GAME · DELAYED' : 'NEXT GAME');
			const bracket = !isPoolGame(next);
			const change = changeNote(next);
			const score = live && hasScore(next.away_score) && hasScore(next.home_score) ? '<p class="swat-tv-score">' + esc(next.away) + ' ' + esc(next.away_score) + ' · ' + esc(next.home) + ' ' + esc(next.home_score) + '</p>' : '';
			html += '<article class="swat-tv-next' + (live ? ' is-live' : '') + (delayed ? ' is-delayed' : '') + '" aria-label="' + esc(label.toLowerCase()) + '">';
			html += '<p class="swat-tv-label">' + esc(label) + (bracket ? ' · ' + esc([next.bracket_name, gameRound(next) || 'Bracket'].filter(Boolean).join(' ')) : '') + '</p>';
			html += '<p class="swat-tv-when"><span class="swat-tv-day">' + esc(next.date === todayKey() ? 'Today · ' + shortDate(next.date) : shortDate(next.date)) + '</span> <span class="swat-tv-time">' + esc(next.time ? formatTime(next.time) + tzSuffix : 'Time TBD') + '</span></p>';
			if (delayed) html += '<p class="swat-tv-alert">Delayed from its ' + esc(formatTime(next.time)) + ' scheduled start. SWAT will post the new time — watch for updates.</p>';
			if (change) html += '<p class="swat-tv-alert">' + esc(change) + '</p>';
			html += '<p class="swat-tv-vs">' + opponentLine(next, side) + '</p>';
			html += '<p class="swat-tv-where">' + whereLine(next) + '</p>' + score;
			const realGame = String(next.id || '') && Number(next.id) < 900000000;
			html += '<div class="swat-tv-actions">' + directionsButton(next, 'swat-tv-btn is-primary') + (realGame ? '<a class="swat-tv-btn" href="' + esc(appUrl(SWATGameDay.gamedayUrl, {game:next.id})) + '#game">Game details</a>' : '') + '</div>';
			if (!fieldDirectionsUrl(next.field || '') && next.field) html += '<p class="swat-tv-dim">Directions for this field are not on file. See Fields &amp; Facilities.</p>';
			html += '</article>';
		} else {
			let title = '';
			let text = '';
			if (!allPosted) {
				title = 'Schedule not posted yet';
				text = (eventLabel() || 'This event') + ' has no published games yet. Times and fields appear here as soon as SWAT posts them.';
			} else if (!games.length) {
				title = name ? name + ' is not in this event’s posted schedule' : 'This team is not in this event’s posted schedule';
				text = 'Check the team name or the event, then search again.';
			} else if (games.every((g) => isFinal(g) || isOff(g))) {
				title = 'No more games scheduled';
				text = 'All of ' + (name || 'this team') + '’s posted games in this event are complete' + (games.some(isOff) ? ' or cancelled' : '') + '.';
			} else {
				title = 'No upcoming game posted';
				text = 'Earlier games are waiting on final results. New games will appear here when SWAT posts them.';
			}
			html += '<div class="swat-tv-empty"><strong>' + esc(title) + '</strong><p>' + esc(text) + '</p></div>';
		}

		// Bracket games in the team's division whose teams depend on advancement.
		const teamDivisions = unique(games.map((g) => g.division));
		const pending = bracketGames().concat(canonicalGames().filter((g) => !isPoolGame(g)))
			.filter((g, i, arr) => arr.findIndex((o) => String(o.id) === String(g.id)) === i)
			.filter((g) => teamDivisions.includes(g.division) && !isFinal(g) && !teamSide(g) && (isPlaceholderTeam(g.away) || isPlaceholderTeam(g.home)));
		if (pending.length) {
			html += '<details class="swat-tv-bracket"' + (next ? '' : ' open') + '><summary><span>Possible bracket games · ' + esc(teamDivisions.map((d) => d.replace(/\s+Open$/i, '')).join(', ')) + '</span><small>' + pending.length + (pending.length === 1 ? ' game' : ' games') + ' · depend on advancement</small></summary>';
			html += '<p class="swat-tv-dim">Matchups are set by pool results and earlier bracket games. Times and fields below are SWAT’s published slots.</p><ul>';
			sorted(pending).forEach((g) => {
				html += '<li><span class="swat-tv-bwhen">' + esc(shortDate(g.date)) + ' · ' + esc(g.time ? formatTime(g.time) + tzSuffix : 'Time TBD') + '</span><span class="swat-tv-bwhat">' + esc([g.bracket_name, gameRound(g) || 'Bracket'].filter(Boolean).join(' ')) + ': ' + esc(placeholderLabel(g.away)) + ' vs ' + esc(placeholderLabel(g.home)) + '</span><span class="swat-tv-bwhere">' + esc(g.field ? (facilityName(g.field) ? facilityName(g.field) + ' · Field ' + g.field : 'Field ' + g.field) : 'Field TBD') + (isOff(g) ? ' · ' + esc(displayStatus(g)) : '') + '</span></li>';
			});
			html += '</ul></details>';
		}
		const hubCfg = window.SWATGameDayHub || {};
		if (name && hubCfg.teamsUrl) {
			try {
				const profile = new URL(hubCfg.teamsUrl, window.location.origin);
				profile.searchParams.set('team', name);
				html += '<a class="swat-tv-another" href="' + esc(profile.toString()) + '">Season record &amp; results</a>';
			} catch (_) {}
		}
		html += findAnother;
		if (games.length) html += '<h3 class="swat-tv-listhead">Full schedule' + (name ? ' · ' + esc(name) : '') + '</h3>';
		html += '</div>';
		root.innerHTML = html;

		const btn = q('[data-swat-favorite]', root);
		if (btn) btn.addEventListener('click', () => {
			const before = isFollowedTeam(name, state.team);
			if (!before && followedTeamsSnapshot().length >= 3) { toast('You can follow up to 3 teams. Unfollow one first.'); return; }
			window.dispatchEvent(new CustomEvent('swat:toggle-team', {detail:{id:String(state.team), name:String(name)}}));
			const after = isFollowedTeam(name, state.team);
			if (after !== before) toast((after ? 'Following ' : 'Unfollowed ') + name);
			renderTeamPanel(root);
		});
	}

	function renderContext() {
		const root = q('[data-swat-context]');
		if (!root) return;
		const all = canonicalGames();
		if (!state.team && !state.field && !state.game) {
			root.hidden = true;
			return;
		}
		root.hidden = false;

		if (state.team) {
			renderTeamPanel(root);
			return;
		}

		if (state.field) {
			const matches = byContext(all);
			const live = matches.find(isLive);
			const next = sorted(matches.filter((g) => !isFinal(g) && !isLive(g) && !isPastUnresolved(g)))[0];
			let text = 'No active games currently listed.';
			if (live) text = esc(live.away) + ' vs ' + esc(live.home) + ' · LIVE';
			else if (next) text = 'Next: ' + esc(formatTime(next.time)) + ' · ' + esc(next.away) + ' vs ' + esc(next.home);
			const directions = fieldDirectionsUrl(state.field);
				root.innerHTML = '<div><p class="swat-app-eyebrow">FIELD MODE</p><h2>' + esc(state.field.toUpperCase()) + '</h2><p>' + text + '</p></div><div class="swat-context-actions">' + (directions ? '<a href="' + esc(directions) + '" target="_blank" rel="noopener">DIRECTIONS</a>' : '') + '<a href="' + esc(appUrl('/gameday/help/', {field:state.field})) + '">FIELD HELP</a></div>';
			return;
		}

		const game = byContext(all)[0];
		if (!game) {
				root.innerHTML = empty('Game not found','This game is not available in the selected tournament.');
				return;
			}
			const final = isFinal(game);
			const live = isLive(game);
			const rawStatus = norm(game.status);
			const status = displayStatus(game);
			const gameType = isPoolGame(game) ? 'POOL PLAY' : 'BRACKET';
			const directions = fieldDirectionsUrl(game.field || '');
			const dateObj = game.date ? new Date(String(game.date) + 'T12:00:00') : null;
			const dateText = dateObj && !Number.isNaN(dateObj.getTime()) ? dateObj.toLocaleDateString([], {weekday:'long', month:'short', day:'numeric'}) : '';
			const score = (final || live)
				? '<div class="swat-game-detail-score"><div><span>' + esc(game.away || 'Away') + '</span><strong>' + esc(game.away_score == null ? '—' : game.away_score) + '</strong></div><div><span>' + esc(game.home || 'Home') + '</span><strong>' + esc(game.home_score == null ? '—' : game.home_score) + '</strong></div></div>'
				: '';
			let actions = '<button type="button" data-swat-view="today">BACK TO GAME DAY</button>';
			if (directions) actions += '<a href="' + esc(directions) + '" target="_blank" rel="noopener">DIRECTIONS</a>';
			actions += '<a href="' + esc(appUrl(SWATGameDay.gamedayUrl, {field:game.field || ''})) + '">FIELD VIEW</a>';
			if (!final) actions += '<a href="' + esc(appUrl('/gameday/report-score/', {game:game.id || ''})) + '">REPORT SCORE</a>';
			root.innerHTML =
				'<div class="swat-game-detail">' +
					'<div class="swat-game-detail-head"><div><p class="swat-app-eyebrow">GAME ' + esc(game.id) + ' · ' + esc(gameType) + '</p><h2>' + esc(game.away) + ' vs ' + esc(game.home) + '</h2></div><span class="swat-game-detail-status ' + statusClass(game) + '">' + esc(status) + '</span></div>' +
					score +
					'<div class="swat-game-detail-meta"><span>' + esc(dateText) + '</span><strong>' + esc(formatTime(game.time)) + '</strong><a href="' + esc(appUrl(SWATGameDay.gamedayUrl, {field:game.field || ''})) + '">' + esc(game.field || 'Field TBD') + '</a><span>' + esc(facilityName(game.field || '') || game.division || '') + '</span><span>' + esc(game.division || '') + '</span></div>' +
					'<div class="swat-context-actions">' + actions + '</div>' +
				'</div>';
	}

	function renderToday(games) {
		const live = sorted(games.filter(isLive));
		const upcoming = sorted(games.filter((g) => !isFinal(g) && !isLive(g) && !isPastUnresolved(g))).slice(0,6);
		const finals = games.filter(isFinal).sort((a,b) => {
			const da = gameDate(a), db = gameDate(b);
			return (db ? db.getTime() : 0) - (da ? da.getTime() : 0);
		}).slice(0,5);
		const a = q('[data-swat-playing]'), b = q('[data-swat-upcoming]'), c = q('[data-swat-finals]'), count = q('[data-swat-live-count]');
		if (count) count.textContent = live.length + ' live';
		if (a) a.innerHTML = live.length ? live.map((g) => gameCard(g)).join('') : empty('No live games','No games are currently marked in progress.');
		if (b) b.innerHTML = upcoming.length ? upcoming.map((g) => gameCard(g, {showDate:true})).join('') : empty('No upcoming games','No upcoming games are currently listed.');
		if (c) c.innerHTML = finals.length ? finals.map((g) => gameCard(g)).join('') : empty('No finals yet','Completed games will appear here.');
	}

    function renderFindTeam() {
        const root = q('[data-swat-team-strips]'), manage = q('[data-swat-manage-teams]');
        if (!root || !manage) return;
        const teams = followedTeamsSnapshot().slice(0, 3);
        manage.textContent = teams.length ? 'Manage teams' : 'Follow your team';
        manage.classList.toggle('swat-follow-primary', !teams.length);
        manage.classList.toggle('swat-manage-link', Boolean(teams.length));
        q('[data-swat-my-weekend]')?.classList.toggle('has-followed-teams', Boolean(teams.length));
        root.hidden = false;
        if (!teams.length) {
            root.innerHTML = empty('Follow a team to see its games here', 'Use Follow your team above. Choose up to three teams.');
        } else {
            const currentSlug = String(state.event || state.data?.event?.slug || '');
            const weekend = Array.isArray(window.SWATMyWeekendPayload?.timeline) ? window.SWATMyWeekendPayload.timeline : [];
            const here = scheduleGames().map(g => Object.assign({}, g, {event_slug:currentSlug}));
            const elsewhere = weekend.filter(g => String(g.event_slug || '') !== currentSlug);
            const seen = new Set();
            const games = sorted(here.concat(elsewhere)).filter(g => {
                if (!isFollowedTeam(g.away,g.away_id) && !isFollowedTeam(g.home,g.home_id)) return false;
                const key = String(g.event_slug || '') + ':' + String(g.playbook_id || g.id || [g.date,g.time,g.field,g.away,g.home].join('|'));
                if (seen.has(key)) return false;
                seen.add(key); return true;
            });
            const live = games.filter(isLive);
            const upcoming = games.filter(g => !isLive(g) && !isFinal(g) && !isOff(g) && !isPastUnresolved(g));
            const next = upcoming[0];
            const later = upcoming.filter(g => g !== next && g.date === todayKey());
            let html = window.SWATMyWeekendStale ? '<p class="swat-personal-warning">Other-event games may be out of date. <button type="button" data-swat-retry-weekend>Retry</button></p>' : '';
            if (live.length) html += '<h3>Playing now</h3><div class="swat-game-list">' + live.map(g => gameCard(g,{showTime:true,showDate:true})).join('') + '</div>';
            html += '<h3>Next game</h3>' + (next ? gameCard(next,{showTime:true,showDate:true,priority:true}) : empty('No upcoming game posted', 'Check Schedule for completed games or follow another team.'));
            if (later.length) html += '<h3>Later today</h3><div class="swat-game-list">' + later.map(g => gameCard(g,{showTime:true})).join('') + '</div>';
            root.innerHTML = html;
        }
        const itinerary = q('.swat-team-all-games'), timeline = q('[data-swat-weekend-timeline]');
        if (itinerary) itinerary.hidden = !teams.length || !timeline || timeline.hidden;
    }

const findTeam = q('[data-swat-find-team]');
if (findTeam) findTeam.addEventListener('change', () => {
 state.selectedTeam = findTeam.value;
try {sessionStorage.setItem('swat-selected-team:' + (state.event || (state.data && state.data.event && state.data.event.slug) || ''),state.selectedTeam);} catch (_) {}
 const option = findTeam.selectedOptions[0];
 const name = option ? option.textContent.split(' · ')[0] : '';
 if (state.selectedTeam && !isFollowedTeam(name,state.selectedTeam) && followedTeamsSnapshot().length < 3) window.dispatchEvent(new CustomEvent('swat:toggle-team',{detail:{id:state.selectedTeam,name:name}}));
 state.filters.team = name;
 const selectedGame = canonicalGames().find((g) => String(g.home_id || g.home) === state.selectedTeam || String(g.away_id || g.away) === state.selectedTeam);
 state.filters.division = selectedGame ? String(selectedGame.division || '') : '';
 state.quick = 'all';
 const teamFilter = q('[data-swat-filter="team"]'); if (teamFilter) teamFilter.value = name;
 const divisionFilter = q('[data-swat-filter="division"]'); if (divisionFilter) divisionFilter.value = state.filters.division;
 renderFindTeam();
 renderSchedule(byContext(scheduleGames()));
});
function populateFilters(games) {
		const values = {
			division: unique(games.map((g) => g.division)),
			team: unique(games.reduce((arr,g) => arr.concat([g.home,g.away]), [])),
			field: unique(games.map((g) => g.field)),
				date: unique(games.map((g) => g.date))
		};
		Object.keys(values).forEach((name) => {
			const select = q('[data-swat-filter="' + name + '"]');
			if (!select) return;
			const signature = values[name].join('|');
			if (select.dataset.signature !== signature) {
				const previous = state.filters[name] || select.value;
				let label = 'All ' + name + 's';
				if (name === 'team') label = 'All teams';
				if (name === 'field') label = 'All fields';
					if (name === 'date') label = 'All dates';
				select.innerHTML = '<option value="">' + label + '</option>' + values[name].map((v) => {
						let optionLabel = v;
						if (name === 'date') {
							const d = new Date(String(v) + 'T12:00:00');
							if (!Number.isNaN(d.getTime())) optionLabel = d.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
						}
						return '<option value="' + esc(v) + '">' + esc(optionLabel) + '</option>';
					}).join('');
				select.dataset.signature = signature;
				if (previous && values[name].includes(previous)) select.value = previous;
				else {
					select.value = '';
					state.filters[name] = '';
				}
			}
			if (select.dataset.bound !== '1') {
				select.dataset.bound = '1';
				select.addEventListener('change', () => {
					state.filters[name] = select.value;
					renderSchedule(byContext(scheduleGames()));
				});
			}
		});
	}

	function scheduleGames() {
            const fixtures = state.data && Array.isArray(state.data.bracket_fixtures) ? state.data.bracket_fixtures : [];
            const seen = new Set();
            // 1.0.17: canonical games stay authoritative; only borrow the round label (Semifinal, Championship) from the matching fixture.
            const roundById = {};
            fixtures.forEach((fixture) => {
                if (fixture.round && fixture.id) roundById['id:' + fixture.id] = fixture.round;
                if (fixture.round && fixture.playbook_id) roundById['playbook:' + fixture.playbook_id] = fixture.round;
            });
            const canonical = canonicalGames().map((game) => {
                if (game.round) return game;
                const round = roundById['id:' + game.id] || (game.playbook_id ? roundById['playbook:' + game.playbook_id] : '');
                return round ? Object.assign({}, game, {round:round}) : game;
            });
            return canonical.concat(fixtures).filter((game) => {
                const keys = [game.id ? 'id:' + game.id : '', game.playbook_id ? 'playbook:' + game.playbook_id : ''].filter(Boolean);
                const duplicate = keys.some((key) => seen.has(key));
                keys.forEach((key) => seen.add(key));
                return !duplicate;
            }).map(withBracketLabel);
        }

	function renderSchedule(games) {
		const daysRoot = q('[data-swat-day-tabs]');
		const days = unique(games.map((game) => game.date)).sort();
		if (!state.dayInit && days.length) {
			state.dayInit = true;
			if (!state.team && !state.filters.date && days.length > 1 && days.includes(todayKey())) state.filters.date = todayKey();
		}
		if (daysRoot) {
			const options = [{value:'',label:'All days'}].concat(days.map((value) => ({value, label:value === todayKey() ? 'Today' : shortDate(value)})));
			daysRoot.hidden = days.length < 2;
			daysRoot.classList.toggle('is-many', options.length > 4);
			daysRoot.style.setProperty('--swat-days', String(options.length));
			daysRoot.innerHTML = options.map((day) => '<button type="button" data-swat-schedule-day="' + esc(day.value) + '" aria-pressed="' + (state.filters.date === day.value) + '">' + esc(day.label) + '</button>').join('');
			qa('[data-swat-schedule-day]', daysRoot).forEach((button) => button.addEventListener('click', () => {
				state.filters.date = button.dataset.swatScheduleDay;
				const select = q('[data-swat-filter="date"]');
				if (select) select.value = state.filters.date;
				renderSchedule(byContext(scheduleGames()));
			}));
		}
        populateFilters(games);
        const filterBar = q('#swat-schedule .swat-filter-bar');
        if (filterBar) filterBar.hidden = !games.length;
        const filterDisclosure = q('[data-swat-filter-disclosure]');
        if (filterDisclosure) filterDisclosure.hidden = !games.length;
        const filterLabel = q('[data-swat-filter-label]');
        if (filterLabel) {
            const activeFilters = ['division', 'team', 'field'].filter((key) => Boolean(state.filters[key])).length;
            filterLabel.textContent = activeFilters ? 'Filters (' + activeFilters + ')' : 'Filters';
        }
        const clearFilters = q('[data-swat-clear]');
        if (clearFilters) {
            clearFilters.hidden = !games.length || (!Object.values(state.filters).some(Boolean) && state.quick === 'all');
            const heading = clearFilters.closest('.swat-app-section-heading');
            if (heading) heading.hidden = clearFilters.hidden;
        }
        renderChips(games);
		let out = games.slice();
		if (state.filters.division) out = out.filter((g) => g.division === state.filters.division);
		if (state.filters.team) out = out.filter((g) => g.home === state.filters.team || g.away === state.filters.team);
		if (state.filters.field) out = out.filter((g) => g.field === state.filters.field);
		if (state.filters.date) out = out.filter((g) => g.date === state.filters.date);
		if (state.quick === 'live') out = out.filter(isLive);
		if (state.quick === 'mine') out = out.filter((g) => isFollowedTeam(g.away, g.away_id) || isFollowedTeam(g.home, g.home_id));
		const root = q('[data-swat-schedule]');
		if (!root) return;
		if (out.length) {
			root.innerHTML = groupedByTime(sorted(out));
			return;
		}
		if (!games.length) {
			root.innerHTML = empty('Schedule not posted yet.', "It'll appear here as soon as SWAT publishes it.");
			return;
		}
		let message = 'Clear a filter to see more of the schedule.';
		if (state.quick === 'mine') message = followedTeamsSnapshot().length ? 'None of your teams play in this view. Try another day or All days.' : 'Tap the star next to a team to follow it.';
		if (state.quick === 'live') message = 'No games are in progress right now.';
		root.innerHTML = empty(state.quick === 'live' ? 'No live games' : 'No matching games', message);
	}

	function renderScores(games) {
		const source = games.length ? games : canonicalGames();
		const live = sorted(source.filter(isLive));
		const finals = source.filter(isFinal).sort((a,b) => {
			const da = gameDate(a), db = gameDate(b);
			return (db ? db.getTime() : 0) - (da ? da.getTime() : 0);
		});
		const root = q('[data-swat-scores]');
		if (!root) return;
		if (!live.length && !finals.length) {
			root.innerHTML = empty('No scores yet.','Live and final scores show up here during games.');
			return;
		}
		let html = live.length ? '<h3 class="swat-dayhead">Live now</h3>' + live.map((g) => gameCard(g)).join('') : '';
		let lastDate = null;
		finals.forEach((g) => {
			if (g.date !== lastDate) { html += '<h3 class="swat-dayhead">Finals · ' + esc(shortDate(g.date)) + '</h3>'; lastDate = g.date; }
			html += gameCard(g);
		});
		root.innerHTML = html;
	}

	function isPoolGame(game) {
		const typeId = Number(game && game.game_type_id);
		const type = norm(game && game.game_type);
		if (Number.isFinite(typeId) && typeId > 0) return typeId === 1;
		if (type) return type.includes('POOL');
		return true;
	}


	function bracketGroupLabel(game) {
		return game.bracket_name || game.division || 'Tournament';
	}

	function correctedBracketFixture(game) {
		// This event's published 10U schedule contains two separate title paths.
		// Scope by event, schedule, date and source game ID; keep pool divisions intact.
		const event = state.data && state.data.loaded_event || {};
		const matchesEvent = event.slug === 'autumn-harvest-series' || (event.name === 'Autumn Harvest Series' && event.start === '2026-10-03');
		if (!matchesEvent || game.division !== '10U Open' || game.date !== '2026-10-04' || String(game.schedule_id) !== '1479') return game;
		const sourceId = String(game.playbook_id);
		if (!['7830','7831','7832','7833','7834'].includes(sourceId)) return game;
		const silver = ['7830','7831'].includes(sourceId);
		return Object.assign({}, game, {
			bracket_name: silver ? '10U Silver' : '10U Gold',
			round: sourceId === '7831' || sourceId === '7834' ? 'Championship' : (sourceId === '7830' ? 'Semifinal' : game.round)
		});
	}

	// ---- 1.0.19: separate Gold / Silver (/ Bronze) title games ----
	// Playbook publishes a split final (seeds 1 v 2 and 3 v 4) as games in ONE
	// schedule with no bracket names: game 1 arrives as "Championship" and game 2
	// as "Bracket Game", so Game Day showed one combined bracket. When every
	// bracket game in a division schedule is a standalone final (no game feeds
	// it and it feeds nothing), they are separate title games, ordered by
	// Playbook game number: Gold, Silver, Bronze. This reads the bracket's
	// structure, not seed names, so it still holds after seeds become teams.
	// Per-game overrides: filter swat_child_bracket_labels (inc/gameday-hub.php).
	const TIERS = ['Gold', 'Silver', 'Bronze'];
	let bracketLabelCache = {source:null, map:{}};
	function bracketLabelMap() {
		const fixtures = state.data && Array.isArray(state.data.bracket_fixtures) ? state.data.bracket_fixtures : [];
		if (bracketLabelCache.source === fixtures) return bracketLabelCache.map;
		const map = {};
		const put = (g, label) => {
			if (g.id) map['id:' + g.id] = label;
			if (g.playbook_id) map['playbook:' + g.playbook_id] = label;
		};
		const groups = {};
		fixtures.filter((g) => !isPoolGame(g)).forEach((g) => {
			const key = String(g.division || '') + '|' + String(g.schedule_id || '');
			(groups[key] = groups[key] || []).push(g);
		});
		Object.values(groups).forEach((games) => {
			// Connected paths in one unnamed schedule: the published championship
			// identifies Gold; the other path is Silver. Preserve explicit names.
			const byRef = new Map(), links = new Map(games.map(g => [g, new Set()]));
			games.forEach(g => { [g.id, g.playbook_id].filter(Boolean).forEach(id => byRef.set(String(id), g)); });
			games.forEach(g => {
				const target = byRef.get(String(g.winner_to || ''));
				if (target) { links.get(g).add(target); links.get(target).add(g); }
			});
			const unseen = new Set(games), paths = [];
			while (unseen.size) {
				const path = [], visit = g => { if (!unseen.delete(g)) return; path.push(g); links.get(g).forEach(visit); };
				visit(unseen.values().next().value); paths.push(path);
			}
			const titlePaths = paths.filter(path => path.some(isChampionship));
			if (paths.length === 2 && titlePaths.length === 1 && paths.some(path => path.length > 1) && games.every(g => !g.bracket_name)) {
				paths.forEach(path => path.forEach(g => put(g, {bracket_name: (g.division || 'Tournament') + ' · ' + (path === titlePaths[0] ? 'Gold' : 'Silver') + ' Bracket'})));
				return;
			}
			if (games.length < 2 || games.length > TIERS.length) return;
			const targets = new Set();
			games.forEach((g) => {
				if (Number(g.winner_to)) targets.add(String(g.winner_to));
				if (Number(g.loser_to)) targets.add(String(g.loser_to));
			});
			const standalone = games.every((g) => !Number(g.winner_to) && !Number(g.loser_to) && !targets.has(String(g.id)) && !targets.has(String(g.playbook_id || '')));
			if (!standalone) return;
			const ordered = games.slice().sort((a, b) => (Number(a.game_number) || 99) - (Number(b.game_number) || 99) || String(a.time || '').localeCompare(String(b.time || '')) || (Number(a.playbook_id) || 0) - (Number(b.playbook_id) || 0));
			const base = String(ordered[0].division || '').replace(/\s+Open$/i, '');
			ordered.forEach((g, i) => put(g, {bracket_name: (base ? base + ' ' : '') + TIERS[i], round: 'Championship'}));
		});
		const overrides = window.SWATGameDayHub && window.SWATGameDayHub.bracketLabels && typeof window.SWATGameDayHub.bracketLabels === 'object' ? window.SWATGameDayHub.bracketLabels : {};
		Object.keys(overrides).forEach((playbookId) => {
			const o = overrides[playbookId] || {};
			const label = {};
			if (o.bracket) label.bracket_name = String(o.bracket);
			if (o.round) label.round = String(o.round);
			if (Object.keys(label).length) map['playbook:' + playbookId] = Object.assign({}, map['playbook:' + playbookId] || {}, label);
		});
		bracketLabelCache = {source:fixtures, map:map};
		return map;
	}
	function withBracketLabel(g) {
		if (!g || isPoolGame(g) || g.bracket_name) return g;
		const map = bracketLabelMap();
		const label = map['playbook:' + (g.playbook_id || '')] || map['id:' + (g.id || '')];
		return label ? Object.assign({}, g, label) : g;
	}

	function bracketGames() {
		const cached = state.data && Array.isArray(state.data.bracket_fixtures) ? state.data.bracket_fixtures : [];
		const source = cached.length ? cached : canonicalGames().filter((game) => !isPoolGame(game));
		const seen = new Set();
		return sorted(source.filter((game) => {
			const key = String(game.playbook_id || game.id || '');
			if (key && seen.has(key)) return false;
			if (key) seen.add(key);
			return true;
		}).map(correctedBracketFixture).map(withBracketLabel));
	}

	function bracketTeamLabel(value) {
		const raw = String(value || '').trim();
		const place = raw.match(/^Division Place\s+(\d+)$/i);
		if (place) return '#' + place[1] + ' Seed';
		return raw || 'TBD';
	}

	function followedTeamsSnapshot() {
		try {
			const value = JSON.parse(localStorage.getItem('swat_followed_teams') || '{}');
			return value && Array.isArray(value.followedTeams) ? value.followedTeams : [];
		} catch (_) {
			return [];
		}
	}

	function isFollowedTeam(name, id) {
		const targetName = String(name || '').trim().toLowerCase();
		const targetId = String(id || '');
		return followedTeamsSnapshot().some((team) => {
			const registrations = Array.isArray(team.knownRegistrations) ? team.knownRegistrations : [];
			const sameId = targetId && registrations.some((registration) => String(registration.teamId || '') === targetId);
			const sameName = targetName && String(team.displayName || team.name || '').trim().toLowerCase() === targetName;
			return sameId || sameName;
		});
	}

	function standingRecordCompare(a,b) {
		return b.w - a.w || a.l - b.l || b.t - a.t || String(a.team).localeCompare(String(b.team));
	}

	function standingSecondaryCompare(a,b) {
		const diffA = a.rf - a.ra;
		const diffB = b.rf - b.ra;
		return a.ra - b.ra || diffB - diffA || b.rf - a.rf || String(a.team).localeCompare(String(b.team));
	}

	function headToHeadCompare(teamA, teamB, games) {
		let aWins = 0, bWins = 0;
		games.forEach((g) => {
			const home = String(g.home || ''), away = String(g.away || '');
			const matchup = (home === teamA && away === teamB) || (home === teamB && away === teamA);
			if (!matchup) return;
			const hs = Number(g.home_score), as = Number(g.away_score);
			if (!Number.isFinite(hs) || !Number.isFinite(as) || hs === as) return;
			const winner = hs > as ? home : away;
			if (winner === teamA) aWins++;
			else if (winner === teamB) bWins++;
		});
		if (aWins === bWins) return 0;
		return aWins > bWins ? -1 : 1;
	}

	function markCoinFlipGroups(rows) {
		const groups = {};
		rows.forEach((row, index) => {
			const key = [row.ra, row.rf-row.ra, row.rf].join('|');
			if (!groups[key]) groups[key] = [];
			groups[key].push(index);
		});
		Object.values(groups).forEach((indexes) => {
			if (indexes.length < 2) return;
			indexes.forEach((index) => { rows[index].coinFlip = true; });
		});
	}

	function rankStandingRows(rows, games) {
		const base = rows.slice().sort(standingRecordCompare);
		const groups = [];
		base.forEach((row) => {
			const key = [row.w,row.l,row.t].join('|');
			const last = groups[groups.length - 1];
			if (!last || last.key !== key) groups.push({key:key, rows:[row]});
			else last.rows.push(row);
		});

		const ranked = [];
		groups.forEach((entry) => {
			let group = entry.rows.slice();
			if (group.length === 2) {
				const h2h = headToHeadCompare(group[0].team, group[1].team, games);
				if (h2h === 1) group = [group[1], group[0]];
				else if (h2h === 0) {
					group.sort(standingSecondaryCompare);
					markCoinFlipGroups(group);
				}
			} else if (group.length > 2) {
				group.sort(standingSecondaryCompare);
				markCoinFlipGroups(group);
			}
			ranked.push(...group);
		});
		return ranked;
	}

	function standingsFrom(games) {
		const divisions = {};
		games.filter((g) => isFinal(g) && isPoolGame(g)).forEach((g) => {
			const d = g.division || 'Tournament';
			if (!divisions[d]) divisions[d] = {table:{},games:[]};
			const table = divisions[d].table, home = g.home || 'Home', away = g.away || 'Away';
			const hs = Number(g.home_score), as = Number(g.away_score);
			if (!Number.isFinite(hs) || !Number.isFinite(as)) return;
			[home,away].forEach((t) => { if (!table[t]) table[t] = {team:t,w:0,l:0,t:0,rf:0,ra:0,coinFlip:false}; });
			table[home].rf += hs; table[home].ra += as;
			table[away].rf += as; table[away].ra += hs;
			if (hs > as) { table[home].w++; table[away].l++; }
			else if (as > hs) { table[away].w++; table[home].l++; }
			else { table[home].t++; table[away].t++; }
			divisions[d].games.push({home:home,away:away,home_score:hs,away_score:as});
		});
		return divisions;
	}

	function followedDivisions(source) {
		return unique(source.filter((g) => isFollowedTeam(g.home, g.home_id) || isFollowedTeam(g.away, g.away_id)).map((g) => g.division));
	}

	function setPostseasonTab(mode) {
		qa('[data-swat-postseason-tab]').forEach((tab) => {
			const active = tab.dataset.swatPostseasonTab === mode;
			tab.classList.toggle('is-active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
			tab.tabIndex = active ? 0 : -1;
		});
		qa('[data-swat-postseason-panel]').forEach((panel) => {
			panel.hidden = panel.dataset.swatPostseasonPanel !== mode;
		});
	}

	function poolKey(g) {
		return String((g && (g.pool_name || g.pool)) || '').trim();
	}

	function renderStandings(games) {
		const root = q('[data-swat-standings]');
		if (!root) return;
		const source = canonicalGames();
		const division = state.bracketDivision;
		const inDivision = source.filter((g) => !division || (g.division || 'Tournament') === division);
		// Pools are optional in the feed; without them the whole division is one table.
		const pools = unique(inDivision.filter(isPoolGame).map(poolKey)).filter(Boolean);
		const groups = pools.length ? pools.sort((a,b) => a.localeCompare(b, undefined, {numeric:true})).map((p) => ({label: /^pool\b/i.test(p) ? p : 'Pool ' + p, games: inDivision.filter((g) => poolKey(g) === p)})) : [{label: division || 'Pool play', games: inDivision}];
		const ids = {};
		source.forEach((g) => { if (g.home) ids[g.home] = g.home_id || ''; if (g.away) ids[g.away] = g.away_id || ''; });
		let html = '';
		groups.forEach((group) => {
			const tables = standingsFrom(group.games);
			const table = Object.values(tables)[0];
			if (!table) return;
			const rows = rankStandingRows(Object.values(table.table), table.games);
			const pending = group.games.some((g) => isPoolGame(g) && !isFinal(g) && norm(g.status).indexOf('CANCEL') === -1);
			html += '<section class="swat-ps-pool" id="swat-div-' + esc(sanitizeEventSlug(group.label)) + '"><div class="swat-ps-pool-head"><h3>' + esc(group.label) + '</h3><span>' + (pending ? 'Pool play in progress' : 'Pool play final') + '</span></div>' +
				'<table class="swat-ps-table"><caption class="swat-ps-sr">' + esc(group.label) + ' standings</caption><thead><tr><th scope="col" class="swat-ps-rank"><abbr title="Seed">#</abbr></th><th scope="col" class="swat-ps-team">Team</th><th scope="col" class="swat-ps-num"><abbr title="Wins-Losses' + (rows.some((r) => r.t) ? '-Ties' : '') + '">W-L</abbr></th><th scope="col" class="swat-ps-num"><abbr title="Run differential">RD</abbr></th></tr></thead><tbody>';
			rows.forEach((r, index) => {
				const id = ids[r.team] || '';
				const diff = r.rf - r.ra;
				const leader = index === 0 && !r.coinFlip;
				const record = r.w + '-' + r.l + (r.t ? '-' + r.t : '');
				html += '<tr class="' + [leader ? 'is-leader' : '', isFollowedTeam(r.team, id) ? 'is-followed-team' : ''].filter(Boolean).join(' ') + '"><td class="swat-ps-rank">' + (r.coinFlip ? '—' : String(index + 1)) + '</td><td class="swat-ps-team"><a href="' + esc(appUrl(SWATGameDay.gamedayUrl, {team:id || r.team})) + '">' + esc(r.team) + '</a>' + (r.coinFlip ? '<small class="swat-coin">Coin flip required</small>' : '') + '<small class="swat-ps-runs">RS ' + r.rf + ' · RA ' + r.ra + '</small></td><td class="swat-ps-num">' + record + '</td><td class="swat-ps-num">' + (diff > 0 ? '+' : '') + diff + '</td></tr>';
			});
			html += '</tbody></table></section>';
		});
		if (!html) {
			root.innerHTML = empty('Standings appear after pool play.','');
			return;
		}
		html += '<details class="swat-ps-rules"><summary>How SWAT seeds pool play</summary><ol><li>Win–loss record</li><li>Head-to-head (two-team ties only)</li><li>Fewest runs allowed</li><li>Run differential</li><li>Most runs scored</li></ol><p>Only final pool-play scores count.</p></details>';
		root.innerHTML = html;
	}


	function traditionalBracketSvg() {
		const games = bracketGames().filter(g => SWATGameDayModel.belongsToDivision(g, state.bracketDivision));
		if (!games.length) return null;
		const groups = new Map();
		games.forEach(g => { const key = bracketGroupLabel(g) + '|' + (g.schedule_id || ''); if (!groups.has(key)) groups.set(key, []); groups.get(key).push(g); });
		let body = '', offset = 112, width = 1000;
		const text = (x,y,value,size=15,weight=400) => '<text x="'+x+'" y="'+y+'" font-size="'+size+'" font-weight="'+weight+'">'+esc(value)+'</text>';
		groups.forEach(rows => {
			const byId = new Map(rows.map(g => [String(g.id),g])), incoming = new Map(rows.map(g => [String(g.id),[]]));
			rows.forEach(g => { if (byId.has(String(g.winner_to))) incoming.get(String(g.winner_to)).push(g); });
			const remaining = new Set(rows.map(g=>String(g.id)));
			while (remaining.size) {
				const component = [], visit = id => { if (!remaining.has(id)) return; remaining.delete(id); const g=byId.get(id); component.push(g); incoming.get(id).forEach(p=>visit(String(p.id))); if(byId.has(String(g.winner_to))) visit(String(g.winner_to)); };
				visit(remaining.values().next().value);
				let leaf = 0; const positions=new Map(), active=new Set();
				const locate=g=>{
					const id=String(g.id); if(positions.has(id)) return positions.get(id);
					if(active.has(id)) return {x:32,y:offset+65+(leaf++)*140,depth:0};
					active.add(id);
					const parents=incoming.get(id).map(locate), depth=parents.length ? Math.max(...parents.map(p=>p.depth))+1 : 0;
					const p={x:32+depth*340,y:parents.length ? parents.reduce((sum,p)=>sum+p.y,0)/parents.length : offset+65+(leaf++)*140,depth};
					active.delete(id); positions.set(id,p); return p;
				};
				component.forEach(locate);
				const top=offset, height=Math.max(1,leaf)*140+90;
				body+=text(32,top+25,bracketGroupLabel(component[0]) + (/\b(Gold|Silver|Bronze)\b/i.test(bracketGroupLabel(component[0])) ? '' : ' · Bracket'),22,700);
				component.forEach(g=>{
					const p=positions.get(String(g.id)), target=positions.get(String(g.winner_to));
					if(target && target.x>p.x) body+='<path d="M'+(p.x+290)+' '+(p.y+46)+' H'+(p.x+315)+' V'+(target.y+46)+' H'+target.x+'" fill="none" stroke="#7b8490" stroke-width="2"/>';
				});
				component.forEach(g=>{
					const p=positions.get(String(g.id)); width=Math.max(width,p.x+330);
					body+='<rect x="'+p.x+'" y="'+p.y+'" width="290" height="102" rx="5" fill="#fff" stroke="#aab2bb"/><rect x="'+p.x+'" y="'+p.y+'" width="290" height="28" rx="5" fill="#e8edf2"/>';
					body+=text(p.x+9,p.y+19,(g.game_number ? 'G'+g.game_number+' · ' : '')+shortDate(g.date)+' '+formatTime(g.time)+' · '+(g.field || 'TBD'),12,600);
					['away','home'].forEach((side,index)=>{
						const raw=bracketTeamLabel(g[side]), label=(Number(g[side+'_seed'])>0 ? '#'+g[side+'_seed']+' ' : '')+raw;
						const name=label.length>32 ? label.slice(0,30)+'…' : label;
						body+='<g><title>'+esc(label)+'</title>'+text(p.x+9,p.y+51+index*25,name,14,600)+'</g>';
						if((isFinal(g)||isLive(g)) && hasScore(g[side+'_score'])) body+=text(p.x+260,p.y+51+index*25,g[side+'_score'],14,700);
					});
					body+=text(p.x+9,p.y+94,gameRound(g)+' · '+displayStatus(g),11);
				});
				offset+=height;
			}
		});
		const title=(state.data && state.data.loaded_event && state.data.loaded_event.name) || 'SWAT Game Day';
		const stamp='Snapshot '+new Date().toLocaleString('en-US',{timeZone:'America/New_York'})+' ET · Return to Game Day for updates';
		return {width,height:offset+35,title,svg:'<svg xmlns="http://www.w3.org/2000/svg" width="'+width+'" height="'+(offset+35)+'" viewBox="0 0 '+width+' '+(offset+35)+'"><rect width="100%" height="100%" fill="#fff"/><g font-family="Arial,sans-serif" fill="#17212b">'+text(32,38,'SWAT TOURNAMENTS',22,700)+text(32,68,title,20,700)+text(32,92,stamp,13)+body+'</g></svg>'};
	}

	document.addEventListener('click', event => {
		const button=event.target.closest('[data-swat-bracket-download]');
		if(!button) return;
		const drawing=traditionalBracketSvg();
		if(!drawing) { alert('No bracket games are published for this division yet.'); return; }
		const dialog=document.createElement('dialog');
		dialog.style.cssText='width:94vw;max-width:1200px;max-height:90vh;padding:16px;border:1px solid #aab2bb;border-radius:12px;background:white;color:#17212b;';
		dialog.innerHTML='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px"><button type="button" data-export-png>Download image</button><button type="button" data-export-print>Print / Save PDF</button><button type="button" data-export-close>Close</button></div><p style="font:14px Arial">Traditional bracket · Scroll to see all rounds. Connector lines follow published game advancement.</p><div style="overflow:auto;max-height:65vh;border:1px solid #ddd">'+drawing.svg+'</div><p data-export-status role="status" style="font:14px Arial"></p>';
		dialog.querySelectorAll('button').forEach(b=>{b.style.cssText='padding:12px 16px;min-height:44px;border:1px solid #8894a2;border-radius:6px;background:#f5f7fa;color:#17212b;font:600 14px Arial;cursor:pointer';});
		document.body.appendChild(dialog); dialog.addEventListener('close',()=>{dialog.remove();button.focus();}); dialog.querySelector('[data-export-close]').onclick=()=>dialog.close(); dialog.showModal();
		dialog.querySelector('[data-export-print]').onclick=()=>{
			const win=window.open('','_blank');
			if(!win) { dialog.querySelector('[data-export-status]').textContent='Allow pop-ups to open the print view.';return; }
			win.document.open();win.document.write('<!doctype html><html><head><title>'+esc(drawing.title)+' bracket</title><style>@page{size:landscape;margin:10mm}body{margin:0;font:14px Arial}svg{width:100%;height:auto}button{padding:12px;margin:12px}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save PDF</button>'+drawing.svg+'</body></html>');win.document.close();
		};
		dialog.querySelector('[data-export-png]').onclick=()=>{
			const status=dialog.querySelector('[data-export-status]');status.textContent='Preparing image…';
			const url=URL.createObjectURL(new Blob([drawing.svg],{type:'image/svg+xml;charset=utf-8'})), img=new Image();
			img.onerror=()=>{URL.revokeObjectURL(url);status.textContent='Image export failed. Use Print / Save PDF instead.';};
			img.onload=()=>{
				try {
					const scale=Math.min(2,8192/drawing.width,8192/drawing.height),canvas=document.createElement('canvas');
					canvas.width=Math.ceil(drawing.width*scale);canvas.height=Math.ceil(drawing.height*scale);const ctx=canvas.getContext('2d');ctx.scale(scale,scale);ctx.drawImage(img,0,0);URL.revokeObjectURL(url);
					canvas.toBlob(blob=>{if(!blob){status.textContent='Use Print / Save PDF for this bracket.';return;}const link=document.createElement('a'),downloadUrl=URL.createObjectURL(blob);link.href=downloadUrl;link.download=sanitizeEventSlug(drawing.title+'-'+(state.bracketDivision||'all-divisions'))+'-bracket.png';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(downloadUrl),60000);status.textContent='Image ready. On iPhone, use Share to save it to Photos or Files.';},'image/png');
				} catch (_) {URL.revokeObjectURL(url);status.textContent='Use Print / Save PDF for this bracket.';}
			};img.src=url;
		};
	});

	function renderBracket(games) {
		const root = q('[data-swat-bracket]');
		if (!root) return;
		// Keep each round's open/closed state when a score refresh re-renders the view.
		qa('details[data-swat-round]', root).forEach(el => { state.roundOpen[el.dataset.swatRound] = el.open; });
		const gamesForBracket = bracketGames();
		const all = canonicalGames();
		const availableDivisions = SWATGameDayModel.divisionOptions(all.concat(gamesForBracket)).sort((a,b) => a.localeCompare(b, undefined, {numeric:true}));
		if (!availableDivisions.includes(state.bracketDivision)) {
			const followed = followedDivisions(all.concat(gamesForBracket));
			const selected = all.find(g => state.selectedTeam && [String(g.away_id || g.away), String(g.home_id || g.home)].includes(state.selectedTeam));
			state.bracketDivision = selected && availableDivisions.includes(selected.division) ? selected.division : (availableDivisions.find(d => followed.includes(d)) || availableDivisions[0] || '');
		}
		const filter = q('[data-swat-bracket-division]');
		if (filter) {
			filter.innerHTML = availableDivisions.length ? availableDivisions.map(d => '<option value="' + esc(d) + '">' + esc(d) + '</option>').join('') : '<option value="">No divisions posted</option>';
			filter.value = state.bracketDivision;
			filter.disabled = !availableDivisions.length;
		}
        const postseasonControls = q('.swat-postseason-controls'), postseasonTabs = q('.swat-postseason-tabs');
        if (postseasonControls) postseasonControls.hidden = !availableDivisions.length;
        if (postseasonTabs) postseasonTabs.hidden = !availableDivisions.length;
		const selectedGames = gamesForBracket.filter(g => SWATGameDayModel.belongsToDivision(g, state.bracketDivision));
		const download = q('[data-swat-bracket-download]');
		if (download) {
			download.disabled = !selectedGames.length;
            download.hidden = !selectedGames.length;
            download.setAttribute('aria-disabled', String(!selectedGames.length));
			download.setAttribute('aria-label', 'Download bracket for ' + (state.bracketDivision || 'selected division'));
		}
		const title = q('[data-swat-ps-title]');
		if (title && state.data && state.data.loaded_event && state.data.loaded_event.name) title.textContent = state.data.loaded_event.name;
		renderStandings(byContext(all));
		// Open on Bracket once games are under way there; until the user picks a tab themselves.
		if (!state.postseasonTabChosen) setPostseasonTab(selectedGames.some(g => isFinal(g) || isLive(g)) ? 'bracket' : 'pool');
		if (!selectedGames.length) {
			root.innerHTML = empty('The bracket appears after pool play.','');
			return;
		}
		const tiers = {};
		selectedGames.forEach(g => {
			const label = bracketGroupLabel(g);
			if (!tiers[label]) tiers[label] = [];
			tiers[label].push(g);
		});
		const tierOrder = (label) => { const m = String(label).match(/\b(Gold|Silver|Bronze|Copper)\b/i); return m ? ['gold','silver','bronze','copper'].indexOf(m[1].toLowerCase()) : 9; };
		const labels = Object.keys(tiers).sort((a,b) => tierOrder(a) - tierOrder(b) || a.localeCompare(b, undefined, {numeric:true}));
		const tierName = (label) => { const m = String(label).match(/\b(Gold|Silver|Bronze|Copper)\b/i); return m ? m[1].charAt(0).toUpperCase() + m[1].slice(1).toLowerCase() + ' Bracket' : (labels.length > 1 ? label : 'Bracket'); };
		const when = (g) => [g.field || 'Field TBD', (shortDate(g.date).split(',')[0] + ' ' + formatTime(g.time)).trim()].filter(Boolean).join(' · ');
		let cards = '', earlier = '';
		labels.forEach(label => {
			const rows = tiers[label];
			const members = SWATGameDayModel.divisionMembers(rows[0]);
			const name = members.length > 1 ? members.join(' / ') + ' ' + tierName(label) + ' (combined)' : tierName(label);
			rows.filter(isChampionship).forEach(g => {
				const decided = SWATGameDayModel.championSide(g);
				const href = appUrl(SWATGameDay.gamedayUrl, {game: g.id});
				if (decided) {
					const winner = decided;
					const loser = winner === 'home' ? 'away' : 'home';
					cards += '<article class="swat-ps-champ is-final" data-game-id="' + esc(g.id) + '" aria-label="' + esc(scoreLabel(g)) + '"><p class="swat-ps-tier">' + esc(name) + '</p><p class="swat-ps-k">Champion</p><p class="swat-ps-winner">' + esc(bracketTeamLabel(g[winner])) + '</p><p class="swat-ps-k is-quiet">Runner-Up</p><p class="swat-ps-runner">' + esc(bracketTeamLabel(g[loser])) + '</p><p class="swat-ps-meta"><a href="' + esc(href) + '"><strong>' + esc(g[winner + '_score']) + '–' + esc(g[loser + '_score']) + '</strong> final</a> · <span class="swat-ps-when">' + esc(when(g)) + '</span></p></article>';
				} else {
					const live = (isLive(g) || isFinal(g)) && hasScore(g.away_score) && hasScore(g.home_score);
					cards += '<article class="swat-ps-champ' + (isLive(g) ? ' is-live' : '') + '" data-game-id="' + esc(g.id) + '" aria-label="' + esc(scoreLabel(g)) + '"><p class="swat-ps-tier">' + esc(name) + '</p><p class="swat-ps-k">Championship' + (isLive(g) ? ' · Live' : '') + '</p><p class="swat-ps-matchup">' + esc(bracketTeamLabel(g.away)) + (live ? ' <b>' + esc(g.away_score) + '</b>' : '') + '<span>vs</span>' + esc(bracketTeamLabel(g.home)) + (live ? ' <b>' + esc(g.home_score) + '</b>' : '') + '</p><p class="swat-ps-meta"><a href="' + esc(href) + '">' + (isLive(g) ? 'Live' : (isFinal(g) ? 'Final · no champion declared' : (norm(g.status).indexOf('DELAY') !== -1 ? 'Delayed' : (g.status ? 'Upcoming' : 'Status TBD')))) + '</a> · <span class="swat-ps-when">' + esc(when(g)) + '</span></p></article>';
				}
			});
			const rounds = [];
			rows.filter(g => !isChampionship(g)).forEach(g => {
				const roundLabel = gameRound(g) || 'Bracket · ' + shortDate(g.date);
				let bucket = rounds.find(r => r.label === roundLabel);
				if (!bucket) { bucket = {label: roundLabel, games: []}; rounds.push(bucket); }
				bucket.games.push(g);
			});
			if (!rounds.length) return;
			const hasTitle = rows.some(isChampionship);
			earlier += '<section class="swat-ps-rounds"><h3>' + esc(name) + (hasTitle ? ' — Earlier rounds' : '') + '</h3>';
			rounds.forEach(round => {
				const done = round.games.every(isFinal);
				const live = round.games.filter(isLive).length;
				const key = [state.event, label, round.label].join('|');
				const open = Object.prototype.hasOwnProperty.call(state.roundOpen, key) ? state.roundOpen[key] : !done;
				const count = round.games.length + (round.games.length === 1 ? ' game' : ' games');
				const status = done ? 'completed' : (live ? live + ' live' : 'upcoming');
				earlier += '<details class="swat-ps-round" data-swat-round="' + esc(key) + '"' + (open ? ' open' : '') + '><summary><span class="swat-ps-round-name">' + esc(round.label) + '</span><span class="swat-ps-round-meta">' + count + ' · ' + status + '</span><span class="swat-ps-round-icon" aria-hidden="true"></span></summary><div class="swat-game-list">' + round.games.map(g => gameCard(g, {showTime:true})).join('') + '</div></details>';
			});
			earlier += '</section>';
		});
		root.innerHTML = (cards ? '<div class="swat-ps-champs">' + cards + '</div>' : '') + earlier;
	}

	function renderChampionships() {
		const wrap = q('[data-swat-championships-wrap]');
		const root = q('[data-swat-championships]');
		if (!wrap || !root) return;
		const championships = bracketGames().filter(isChampionship);
		if (!championships.length) {
			wrap.hidden = true;
			root.innerHTML = '';
			return;
		}
		wrap.hidden = false;
		const count = q('[data-swat-championship-count]', wrap);
		if (count) count.textContent = championships.length + (championships.length === 1 ? ' title game' : ' title games');
		root.innerHTML = championships.map((g) => gameCard(g, {showTime:true})).join('');
	}

	function renderFields(games) {
		const root = q('[data-swat-fields]');
		if (!root) return;
		const fields = unique(games.map((g) => g.field));
		if (!fields.length) {
			root.innerHTML = empty('No fields listed','Field information will appear with the published schedule.');
			return;
		}
		const groups = {};
		fields.forEach((field) => {
			const place = facilityName(field) || 'Other SWAT fields';
			if (!groups[place]) groups[place] = [];
			groups[place].push(field);
		});
		let html = '';
		Object.keys(groups).forEach((place) => {
			const placeDirections = fieldDirectionsUrl(groups[place][0]);
			html += '<section class="swat-facility"><div class="swat-facility-head"><h3>' + esc(place) + '</h3>' + (placeDirections ? '<a class="swat-btn-primary" href="' + esc(placeDirections) + '" target="_blank" rel="noopener">Directions</a>' : '') + '</div>';
			groups[place].forEach((field) => {
				const fieldGames = sorted(games.filter((g) => g.field === field));
				const live = fieldGames.find(isLive);
				const next = fieldGames.find((g) => !isFinal(g) && !isLive(g) && !isPastUnresolved(g));
				let summary = 'No upcoming game listed.';
				if (live) summary = esc(live.away) + ' vs ' + esc(live.home);
				else if (next) summary = 'Next ' + (next.date === todayKey() ? '' : esc(shortDate(next.date)) + ', ') + esc(formatTime(next.time)) + ': ' + esc(next.away) + ' vs ' + esc(next.home);
				const directions = fieldDirectionsUrl(field);
				html += '<article class="swat-fd-card"><div class="swat-fd-top"><h4>' + esc(field) + '</h4>' + (live ? '<span class="swat-pill is-live">Live</span>' : '') + '</div><p>' + summary + '</p><div class="swat-fd-actions">' +
					(directions && directions !== placeDirections ? '<a href="' + esc(directions) + '" target="_blank" rel="noopener">Directions</a>' : '') +
					'<a href="' + esc(appUrl(SWATGameDay.gamedayUrl, {field:field, event:state.event})) + '">Field schedule</a><a href="' + esc(appUrl('/gameday/help/', {field:field, event:state.event})) + '">Field help</a></div></article>';
			});
			html += '</section>';
		});
		root.innerHTML = html;
	}

	function renderWeather() {
		const root = q('[data-swat-weather]');
			const message = root ? (q('[data-swat-weather-message]', root) || root) : null;
		if (!root) return;
		const a = state.alert || {};
		const text = String((a.title || '') + ' ' + (a.body || '') + ' ' + (a.level || '')).toUpperCase();
		const active = /WEATHER|LIGHTNING|RAIN|DELAY|STORM/.test(text);
		if (!active) {
			root.className = 'swat-weather-card';
			message.innerHTML = '<strong>NO ACTIVE WEATHER ALERT</strong><p>For official SWAT weather updates, delays, field closures, and restart information, check the SWAT Facebook page.</p><p>For the fastest updates, subscribe to SWAT Game Day Alerts and receive official tournament notifications directly.</p>';
			return;
		}
		root.className = 'swat-weather-card is-active';
		message.innerHTML = '<strong>' + esc(a.title || 'SWAT WEATHER UPDATE') + '</strong><p>' + esc(a.body || '') + '</p>' + (a.sent_at ? '<small>' + esc(new Date(a.sent_at).toLocaleString()) + '</small>' : '');
	}

	function populateForms(games) {
		const scoreSelect = q('[data-swat-score-game]');
		if (scoreSelect && scoreSelect.dataset.ready !== '1') {
			const candidates = sorted(games.filter((g) => !isFinal(g) || String(g.id) === String(state.game)));
			scoreSelect.innerHTML = '<option value="">Select your game</option>' + candidates.map((g) => '<option value="' + esc(g.id) + '">' + esc(formatTime(g.time)) + ' · ' + esc(g.field || '') + ' · ' + esc(g.away) + ' vs ' + esc(g.home) + '</option>').join('');
			scoreSelect.dataset.ready = '1';
			if (state.game) scoreSelect.value = String(state.game);
			const update = () => {
				const g = games.find((x) => String(x.id) === scoreSelect.value);
				const away = q('[data-swat-away-label]'), home = q('[data-swat-home-label]');
				if (away) away.textContent = g ? String(g.away) + ' score' : 'Away score';
				if (home) home.textContent = g ? String(g.home) + ' score' : 'Home score';
			};
			scoreSelect.addEventListener('change', update);
			update();
		}
		const fieldSelect = q('[data-swat-help-field]');
		if (fieldSelect && fieldSelect.dataset.ready !== '1') {
			const fields = unique(games.map((g) => g.field));
			fieldSelect.innerHTML = '<option value="">Select field</option>' + fields.map((f) => '<option value="' + esc(f) + '">' + esc(f) + '</option>').join('');
			fieldSelect.dataset.ready = '1';
			if (state.field && fields.indexOf(state.field) !== -1) fieldSelect.value = state.field;
		}
	}

    function updateStickyOffsets() {
        const admin = q('#wpadminbar'), site = q('.swat-site-header');
        const adminHeight = admin && getComputedStyle(admin).display !== 'none' ? admin.getBoundingClientRect().height : 0;
        document.body.style.setProperty('--gd-admin-height', adminHeight + 'px');
        document.body.style.setProperty('--gd-site-height', (site ? site.getBoundingClientRect().height : 0) + 'px');
    }
    updateStickyOffsets();
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(updateStickyOffsets);
        [q('.swat-site-header'),q('#wpadminbar')].filter(Boolean).forEach(el => observer.observe(el));
    }
    window.addEventListener('resize', updateStickyOffsets);
	function setView(view, updateHash) {
		state.view = view || 'today';
        app.dataset.uxView = state.view;
 const summary = q('[data-swat-team-summary]');
 if (summary) summary.hidden = state.view !== 'today' || Boolean(state.game || state.field || state.team);
		qa('[data-swat-section]').forEach((section) => { section.hidden = section.dataset.swatSection !== state.view; if (['today','schedule','scores','standings','more'].includes(section.dataset.swatSection)) { section.id = section.id || 'swat-view-' + section.dataset.swatSection; section.setAttribute('role','tabpanel'); section.setAttribute('aria-labelledby','swat-tab-' + section.dataset.swatSection); const tab = q('.swat-bottom-nav [data-swat-view="' + section.dataset.swatSection + '"]'); if (tab) tab.setAttribute('aria-controls',section.id); } });
		qa('[data-swat-home-only]').forEach((element) => { element.hidden = state.view !== 'today' || Boolean(state.game || state.field || state.team); });
		qa('[data-swat-view]').forEach((control) => {
			const active = control.dataset.swatView === state.view;
			control.classList.toggle('is-active', active);
			control.setAttribute('aria-current', active ? 'page' : 'false');
            if (control.closest('.swat-bottom-nav')) {
                control.setAttribute('role', 'tab');
                control.setAttribute('aria-selected', String(active));
                control.id = 'swat-tab-' + control.dataset.swatView;
                control.setAttribute('aria-controls', q('[data-swat-section="' + control.dataset.swatView + '"]')?.id || 'swat-view-' + control.dataset.swatView);
                control.tabIndex = active || (!['today','schedule','scores','standings','more'].includes(state.view) && control.dataset.swatView === 'more') ? 0 : -1;
            }
		});
		if (updateHash !== false && history.replaceState) {
				const currentUrl = new URL(window.location.href);
				currentUrl.searchParams.set('view', state.view);
					currentUrl.hash = '';
				history.replaceState(null, '', currentUrl.pathname + currentUrl.search);
			}

		if (updateHash !== false) {
			requestAnimationFrame(() => {
				const target = state.view === 'today' ? app : q('[data-swat-section="' + state.view + '"]');
				if (!target) return;
				const header = q('.swat-site-header');
				const compact = q('.swat-event-head');
                const offset = (header ? header.getBoundingClientRect().height : 0) + (compact ? compact.getBoundingClientRect().height : 0) + (q('#wpadminbar')?.getBoundingClientRect().height || 0) + 8;
				const top = state.view === 'today' ? 0 : Math.max(0, target.getBoundingClientRect().top + window.scrollY - offset);
				window.scrollTo({top: top, behavior: 'auto'});
			});
		}
	}

    function rememberView() {
        const active = document.activeElement;
        const controls = qa('button,a,input,select,summary', app);
        const key = el => JSON.stringify([el.tagName,el.getAttribute('href'),el.id,el.getAttribute('data-team-id'),el.getAttribute('data-swat-follow'),el.getAttribute('data-swat-schedule-day'),el.getAttribute('data-swat-chip'),el.getAttribute('aria-label')]);
        const match = active && app.contains(active) ? key(active) : null;
        const ordinal = match ? controls.filter(el => key(el) === match).indexOf(active) : -1;
        const disclosures = qa('details',app).map(el => ({el,open:el.open,id:el.id,round:el.dataset.swatRound}));
        return () => {
            disclosures.forEach(item => { const el = item.el.isConnected ? item.el : item.id ? document.getElementById(item.id) : item.round ? qa('details[data-swat-round]',app).find(d => d.dataset.swatRound === item.round) : null; if (el) el.open = item.open; });
            if (match && !active.isConnected) qa('button,a,input,select,summary',app).filter(el => key(el) === match)[ordinal]?.focus({preventScroll:true});
        };
    }
	function render() {
        const restore = rememberView();
		const all = canonicalGames();
        updateAge();
		if (!state.data && !all.length) return;
 renderFindTeam();
		const games = byContext(all);
		renderAlert();
		renderEventMeta(all);
		renderContext();
		renderChampionships();
		renderToday(games);
		renderSchedule(byContext(scheduleGames()));
		renderScores(games);
		renderStandings(games);
			renderBracket(games);
		renderFields(games);
		renderWeather();
		populateForms(all);
		setView(state.view, false);
        restore();
		if (state.timer) clearTimeout(state.timer);
		state.timer = setTimeout(load, all.some(isLive) ? 30000 : 60000);
	}

	let requestSequence = 0;
	let refreshController = null;
	async function load() {
		const sequence = ++requestSequence;
		const requestedEvent = state.event;
		if (refreshController) refreshController.abort();
		const controller = new AbortController();
		refreshController = controller;
		const timeout = setTimeout(() => controller.abort(), 15000);
		setConnection('UPDATING', 'loading');
        updateAge();
		try {
			const dataUrl = previewAware(requestedEvent ? SWATGameDay.gamesEndpoint + '?event=' + encodeURIComponent(requestedEvent) : SWATGameDay.publicEndpoint);
			const options = {cache:'no-store', credentials:'same-origin', signal:controller.signal};
			const responses = await Promise.all([
				fetch(dataUrl, options),
				fetch(previewAware(SWATGameDay.alertEndpoint + (requestedEvent ? '?event=' + encodeURIComponent(requestedEvent) : '')), options).catch(() => null)
			]);
			if (!responses[0].ok) throw new Error('Tournament updates unavailable');
			const fresh = await responses[0].json();
			if (sequence !== requestSequence || state.event !== requestedEvent) return;
			if (!fresh || !Array.isArray(fresh.games)) throw new Error('Invalid tournament response');
            if (fresh.sync_status === 'error') throw new Error('Playbook refresh failed; saved data retained');
			if (requestedEvent && (!fresh.event || fresh.event.slug !== requestedEvent)) throw new Error('Tournament response did not match the selected event');
			if (!requestedEvent && fresh.event && fresh.event.slug) {
				const target = new URL(window.location.href);
				target.searchParams.set('event', fresh.event.slug);
				['team','field','game'].forEach((key) => target.searchParams.delete(key));
				target.searchParams.set('view', state.view || 'today');
				target.hash = '';
				window.location.replace(target.toString());
				return;
			}
			const stale = responses[0].headers.get('X-SWAT-Stale') === '1';
            const previous = new Map(canonicalGames().concat(state.data?.bracket_fixtures || []).map(g => [String(g.id), g]));
            const changed = !stale ? (fresh.games || []).concat(fresh.bracket_fixtures || []).filter(g => { const old = previous.get(String(g.id)); return old && ['status','home_score','away_score'].some(k => String(old[k] ?? '') !== String(g[k] ?? '')); }) : [];
			if (!stale || !state.data) state.data = Object.assign({}, state.data || {}, fresh);
			if (responses[1] && responses[1].ok) {
				const alertData = await responses[1].json();
				if (sequence !== requestSequence || state.event !== requestedEvent) return;
				state.alert = contextualizeAlert(alertData && alertData.alert ? alertData.alert : alertData, canonicalGames());
			}
			if (!stale) state.lastLoaded = new Date();
			setConnection(stale ? 'SAVED DATA · RETRY' : 'ONLINE', stale ? 'error' : 'online');
			updateAge();
			render();
            const changedIds = new Set(changed.map(g => String(g.id)));
            qa('[data-game-id]').forEach(card => { if (changedIds.has(card.dataset.gameId)) { card.classList.add('is-updated'); setTimeout(() => card.classList.remove('is-updated'), 1800); } });
            const announcement = q('[data-swat-refresh-announcement]');
            if (announcement && changed.length) announcement.textContent = changed.map(scoreLabel).join('. ');
		} catch (error) {
			if (sequence !== requestSequence || state.event !== requestedEvent) return;
			setConnection(navigator.onLine ? 'UPDATES FAILED · RETRY' : 'OFFLINE · SAVED DATA', 'error');
			updateAge();
            render();
            const announcement = q('[data-swat-refresh-announcement]');
            if (announcement) announcement.textContent = 'Sync failed. Saved data may be out of date. Use Retry.';
		} finally {
			clearTimeout(timeout);
			if (sequence === requestSequence) {
				if (state.timer) clearTimeout(state.timer);
				state.timer = setTimeout(load, canonicalGames().some(isLive) ? 30000 : 60000);
			}
		}
	}

	document.addEventListener('click', (event) => {
		const rulesLink = event.target.closest('a[href="#swat-gameday-rules"]');
			if (rulesLink) {
				const rules = q('#swat-gameday-rules');
				if (rules) rules.open = true;
			}
			const control = event.target.closest('[data-swat-view]');
		if (!control) return;
		event.preventDefault();
		const requestedView = control.dataset.swatView || 'today';
			if (state.game && requestedView !== 'game') {
				state.game = '';
				state.view = requestedView;
				const target = new URL(window.location.href);
				target.searchParams.delete('game');
				target.searchParams.set('view', requestedView);
					target.hash = '';
				if (history.replaceState) history.replaceState(null, '', target.pathname + target.search + target.hash);
				render();
				return;
			}
			setView(requestedView, true);
        if (control.hasAttribute('data-swat-open-bracket')) {
            const bracketTab = q('[data-swat-postseason-tab="bracket"]');
            if (bracketTab) bracketTab.click();
        }
	});
    document.addEventListener('keydown', event => {
        const tab = event.target.closest('[role="tab"]');
        if (!tab || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
        const tabs = qa('[role="tab"]', tab.closest('[role="tablist"]'));
        const i = tabs.indexOf(tab);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (i + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault(); tabs[next].click(); tabs[next].focus({preventScroll:true});
    });
	// Event-page layout: section tabs sit at the top of the content; refresh lives in the status bar
	// (the minimal app header that used to hold it is replaced by the site header).
	(function () {
		// 1.0.17: section tabs stay a bottom bar (Game Day Hub design); only the refresh control is added here.
		const bar = q('.swat-app-statusbar');
		if (bar && !bar.querySelector('[data-swat-refresh]')) {
			const refresh = document.createElement('button');
			refresh.type = 'button';
			refresh.className = 'swat-refresh-link';
			refresh.setAttribute('data-swat-refresh', '');
			refresh.setAttribute('aria-label','Refresh tournament data');
            refresh.textContent = 'Refresh';
			bar.appendChild(refresh);
		}
	}());
	qa('[data-swat-refresh]').forEach((button) => button.addEventListener('click', load));

		const eventSwitches = qa('[data-swat-event-switch]');
		eventSwitches.forEach((select) => {

			select.addEventListener('change', () => {
				const slug = String(select.value || '').trim();
				if (!slug) return;
				state.event = slug;
				const target = new URL(window.location.href);
				target.searchParams.set('event', slug);
				target.searchParams.delete('team');
				target.searchParams.delete('field');
				target.searchParams.delete('game');
				target.searchParams.set('view', state.view || 'today');
					target.hash = '';
				window.location.assign(target.toString());
			});
		});

		qa('[data-swat-event-link]').forEach((link) => {
			link.href = appUrl(link.href);
		});

		qa('[data-swat-postseason-tab]').forEach((button) => {
			button.addEventListener('click', () => {
				state.postseasonTabChosen = true;
				setPostseasonTab(button.dataset.swatPostseasonTab || 'pool');
			});
		});

	let toastTimer = null;
	function toast(message) {
		const el = q('[data-swat-toast]');
		if (!el) return;
		el.textContent = message;
		el.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(() => el.classList.remove('is-visible'), 2600);
	}

	function rerenderPersonal() {
        renderFindTeam();
		const all = canonicalGames();
		if (!all.length && !scheduleGames().length) return;
		const games = byContext(all);
 renderFindTeam();
		renderChampionships();
		renderToday(games);
		renderSchedule(byContext(scheduleGames()));
		renderScores(games);
		renderStandings(games);
		renderBracket(games);
	}

	// Star to follow/unfollow. The follow store and its 3-team limit stay in swat-my-weekend.js.
	document.addEventListener('click', (event) => {
		const star = event.target.closest('[data-swat-follow]');
		if (!star) return;
		event.preventDefault();
		const id = String(star.dataset.teamId || '');
		const name = String(star.dataset.teamName || id);
		if (!id) return;
		const following = isFollowedTeam(name, id);
		if (!following && followedTeamsSnapshot().length >= 3) {
			toast('You can follow up to 3 teams. Unfollow one first.');
			return;
		}
		window.dispatchEvent(new CustomEvent('swat:toggle-team', {detail:{id:id, name:name}}));
		const after = isFollowedTeam(name, id);
		toast(after === following ? 'Could not update your teams. Try again.' : (after ? 'Following ' : 'Unfollowed ') + name);
		rerenderPersonal();
	});
	window.addEventListener('swat:teams-changed', rerenderPersonal);
    document.addEventListener('swat:ux-rendered', renderFindTeam);

	document.addEventListener('click', (event) => {
		const chip = event.target.closest('[data-swat-chip]');
		if (chip) {
			const value = chip.dataset.swatChip || 'all';
			if (value.indexOf('div:') === 0) {
				const division = value.slice(4);
				state.quick = 'all';
				state.filters.division = state.filters.division === division ? '' : division;
			} else {
				state.quick = value;
				state.filters.division = '';
			}
			const select = q('[data-swat-filter="division"]');
			if (select) select.value = state.filters.division;
			renderSchedule(byContext(scheduleGames()));
			return;
		}
		if (event.target.closest('[data-swat-open-picker]')) {
			event.preventDefault();
			const manage = q('[data-swat-manage-teams]');
			if (manage) manage.click();
			return;
		}
		if (event.target.closest('[data-swat-change-event]')) {
			event.preventDefault();
			const select = q('[data-swat-event-switch]');
			if (!select) { toast('Only one SWAT tournament is available right now.'); return; }
			window.scrollTo({top:0, behavior:'auto'});
			select.focus();
			try { if (typeof select.showPicker === 'function') select.showPicker(); } catch (_) {}
		}
	});

	if (location.hash === '#brackets' || location.hash === '#bracket') {
        state.view = 'standings';
        const bracketTab = q('[data-swat-postseason-tab="bracket"]');
        if (bracketTab) bracketTab.click();
    }
    const hash = location.hash.replace('#','');
	if (['today','schedule','scores','standings','weather','fields','more','game','pitching'].includes(hash)) state.view = hash;

	const bracketDivision = q('[data-swat-bracket-division]');
if (bracketDivision) bracketDivision.addEventListener('change', () => {
 state.bracketDivision = bracketDivision.value;
 renderBracket(canonicalGames());
 renderStandings(canonicalGames());
});
const clear = q('[data-swat-clear]');
	if (clear) clear.addEventListener('click', () => {
		state.filters = {division:'',team:'',field:'',date:''};
		state.quick = 'all';
		qa('[data-swat-filter]').forEach((select) => { select.value = ''; });
		renderSchedule(byContext(scheduleGames()));
	});

	const scoreForm = q('[data-swat-score-form]');
	if (scoreForm) scoreForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const result = q('[data-swat-score-result]');
		const payload = Object.fromEntries(new FormData(scoreForm).entries());
		if (!payload.game_id || payload.away_score === '' || payload.home_score === '') {
			result.innerHTML = '<div class="is-error">Select a game and enter both scores.</div>';
			return;
		}
		const button = q('button[type="submit"]', scoreForm);
		button.disabled = true;
		button.textContent = 'SUBMITTING...';
		try {
			const response = await fetch(SWATGameDay.scoreEndpoint, {method:'POST',headers:{'Content-Type':'application/json','X-SWAT-Nonce':SWATGameDay.submitNonce},body:JSON.stringify(payload),credentials:'same-origin'});
			const data = await response.json();
			if (!response.ok) throw new Error((data && data.message) || 'Unable to submit score.');
			result.innerHTML = '<div class="is-success"><strong>SCORE SUBMITTED</strong><p>' + esc(data.message || 'Pending SWAT review.') + '</p></div>';
			scoreForm.reset();
		} catch (error) {
			result.innerHTML = '<div class="is-error">' + esc(error.message) + '</div>';
		} finally {
			button.disabled = false;
			button.textContent = 'VERIFY & SUBMIT SCORE';
		}
	});

	const helpForm = q('[data-swat-help-form]');
	if (helpForm) helpForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const result = q('[data-swat-help-result]');
		const payload = Object.fromEntries(new FormData(helpForm).entries());
		if (!payload.field || !payload.category) {
			result.innerHTML = '<div class="is-error">Select a field and issue type.</div>';
			return;
		}
		const button = q('button[type="submit"]', helpForm);
		button.disabled = true;
		button.textContent = 'SENDING...';
		try {
			const response = await fetch(SWATGameDay.helpEndpoint, {method:'POST',headers:{'Content-Type':'application/json','X-SWAT-Nonce':SWATGameDay.submitNonce},body:JSON.stringify(payload),credentials:'same-origin'});
			const data = await response.json();
			if (!response.ok) throw new Error((data && data.message) || 'Unable to send request.');
			result.innerHTML = '<div class="is-success"><strong>REQUEST SENT</strong><p>' + esc(data.message || 'SWAT operations has the request.') + '</p></div>';
			const selectedField = q('[name="field"]', helpForm).value;
			helpForm.reset();
			q('[name="field"]', helpForm).value = selectedField;
		} catch (error) {
			result.innerHTML = '<div class="is-error">' + esc(error.message) + '</div>';
		} finally {
			button.disabled = false;
			button.textContent = 'SEND TO SWAT OPS';
		}
	});

	window.addEventListener('beforeinstallprompt', (event) => {
		event.preventDefault();
		state.deferredInstall = event;
		const button = q('[data-swat-install]');
		if (button) button.hidden = false;
	});

	const install = q('[data-swat-install]');
	if (install) install.addEventListener('click', async () => {
		if (!state.deferredInstall) return;
		state.deferredInstall.prompt();
		await state.deferredInstall.userChoice;
		state.deferredInstall = null;
		install.hidden = true;
	});

	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.getRegistration('/').then((registration) => {
			if (registration) registration.update().catch(() => null);
		}).catch(() => null);
	}

	window.addEventListener('online', load);
	window.addEventListener('offline', () => { setConnection('OFFLINE','error'); updateAge(); });
	setInterval(updateAge, 15000);
	if (state.data) {
		const synced = state.data.last_sync ? new Date(String(state.data.last_sync).replace(' ', 'T') + 'Z') : null;
		state.lastLoaded = synced && !Number.isNaN(synced.getTime()) ? synced : null;
		setConnection('SAVED DATA', 'loading');
		updateAge();
		render();
	}
	load();
})();


