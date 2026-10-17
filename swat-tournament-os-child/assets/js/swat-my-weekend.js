(() => {
	'use strict';

	const app = document.querySelector('[data-swat-app]');
	const root = document.querySelector('[data-swat-my-weekend]');
	if (!app || !root || typeof SWATGameDay === 'undefined' || !SWATGameDay.myWeekendEndpoint) return;

	const MAX_TEAMS = 3;
	const STORAGE_TEAMS = 'swat_followed_teams';
	const STORAGE_MIGRATION = 'swat_gameday_migrated_v1';
	const STORAGE_LEGACY_THEME = 'swatFollowedTeams';
	const STORAGE_CACHE = 'swatMyWeekendCacheV1';
	const STORAGE_REMINDERS = 'swatWeekendReminders';
	const STORAGE_FINGERPRINT = 'swatWeekendFingerprintV1';
	const STORAGE_MODE = 'swatPersonalItineraryMode';

	let followed = [];
	let directory = [];
	let payload = null;
	let searchTerm = '';
	let activeMode = 'weekend';
	let refreshTimer = null;
	let inFlightController = null;
	if (!['fnl','snl','weekend'].includes(activeMode)) activeMode = 'weekend';

	const q = (selector, scope) => (scope || document).querySelector(selector);
	const qa = (selector, scope) => Array.from((scope || document).querySelectorAll(selector));
	const esc = (value) => String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
	const norm = (value) => String(value || '').trim().toUpperCase().replace(/[_-]+/g, ' ');
	const normalizeTeamName = (value) => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
	const safeParse = (raw, fallback) => {
		if (!raw) return fallback;
		try {
			const parsed = JSON.parse(raw);
			return parsed == null ? fallback : parsed;
		} catch (e) {
			return fallback;
		}
	};

	followed = readTeams();

	function toInternalTeam(item) {
		if (!item) return null;
		const name = String(item.displayName || item.name || '').trim();
		const id = normalizeTeamName(item.teamKey || name);
		if (!id || !name) return null;
		return {
			id,
			name,
			division: String(item.division || ''),
			followedAt: String(item.followedAt || new Date().toISOString()),
			knownRegistrations: Array.isArray(item.knownRegistrations) ? item.knownRegistrations : []
		};
	}

	function canonicalStateFrom(teams) {
		return {
			version: 1,
			followedTeams: teams.slice(0, MAX_TEAMS).map((team) => ({
				teamKey: normalizeTeamName(team.id || team.name),
				displayName: String(team.name || ''),
				division: String(team.division || ''),
				followedAt: String(team.followedAt || new Date().toISOString()),
				knownRegistrations: Array.isArray(team.knownRegistrations) ? team.knownRegistrations : []
			}))
		};
	}

	function migrateLegacyState() {
		const existing = safeParse(localStorage.getItem(STORAGE_TEAMS), null);
		if (localStorage.getItem(STORAGE_MIGRATION) === '1' && existing && Array.isArray(existing.followedTeams)) {
			return existing;
		}
		if (existing && Array.isArray(existing.followedTeams)) {
			localStorage.setItem(STORAGE_MIGRATION, '1');
			return existing;
		}

		const collected = [];
		const seen = new Set();
		const addCandidate = (candidate) => {
			if (!candidate || collected.length >= MAX_TEAMS) return;
			const raw = typeof candidate === 'string' ? {name:candidate} : candidate;
			const displayName = String(raw.displayName || raw.name || '').trim();
			const teamKey = normalizeTeamName(raw.teamKey || displayName);
			if (!displayName || !teamKey || seen.has(teamKey)) return;
			seen.add(teamKey);
			const knownRegistrations = [];
			if (raw.id) knownRegistrations.push({
				eventId: String(raw.event || raw.eventId || 'unknown'),
				teamId: String(raw.id)
			});
			collected.push({
				teamKey,
				displayName,
				division: String(raw.division || ''),
				followedAt: new Date().toISOString(),
				knownRegistrations
			});
		};

		if (Array.isArray(existing)) existing.forEach(addCandidate);

		const primaryName = localStorage.getItem('swat_team_name');
		if (primaryName) addCandidate({name:primaryName, id:localStorage.getItem('swat_team_id') || ''});

		const themeCurrent = safeParse(localStorage.getItem(STORAGE_LEGACY_THEME), []);
		if (Array.isArray(themeCurrent)) themeCurrent.forEach(addCandidate);

		const extraV2 = safeParse(localStorage.getItem('swat_extra_teams_v2'), []);
		if (Array.isArray(extraV2)) extraV2.forEach(addCandidate);

		const followedV5 = safeParse(localStorage.getItem('swat_followed_teams_v5'), []);
		if (Array.isArray(followedV5)) followedV5.forEach(addCandidate);
		else if (followedV5 && Array.isArray(followedV5.teams)) followedV5.teams.forEach(addCandidate);

		const oldFavorite = localStorage.getItem('swatFavoriteTeam');
		if (oldFavorite) addCandidate(oldFavorite);

		const canonical = {version:1, followedTeams:collected};
		localStorage.setItem(STORAGE_TEAMS, JSON.stringify(canonical));
		localStorage.setItem(STORAGE_MIGRATION, '1');
		return canonical;
	}

	function readTeams() {
		const canonical = migrateLegacyState();
		let teams = Array.isArray(canonical.followedTeams)
			? canonical.followedTeams.map(toInternalTeam).filter(Boolean).slice(0, MAX_TEAMS)
			: [];

		if (!teams.length) {
			const params = new URLSearchParams(window.location.search);
			const previewTeam = params.has('wpvibe_preview') ? (params.get('swat_preview_team') || app.dataset.team || params.get('team') || '') : '';
			if (previewTeam) {
				const id = normalizeTeamName(previewTeam);
				if (id) teams = [{id, name:String(previewTeam), division:'', followedAt:new Date().toISOString(), knownRegistrations:[]}];
			}
		}
		return teams;
	}

	function readReminders() {
		try {
			const value = JSON.parse(localStorage.getItem(STORAGE_REMINDERS) || '[]');
			return Array.isArray(value) ? value : [];
		} catch (e) {
			return [];
		}
	}

	function saveReminders(values) {
		localStorage.setItem(STORAGE_REMINDERS, JSON.stringify(Array.from(new Set(values))));
	}

	function saveTeams() {
		followed = followed.slice(0, MAX_TEAMS);
		localStorage.setItem(STORAGE_TEAMS, JSON.stringify(canonicalStateFrom(followed)));
		window.dispatchEvent(new CustomEvent('swat:teams-changed', {detail:{teams:followed.slice()}}));
		renderPicker();
		renderChips();
        renderWeekend();
		loadWeekend();
		syncPushPreferences(false);
	}

	function resolveNames() {
		if (!directory.length || !followed.length) return;
		let changed = false;
		followed = followed.map((team) => {
			const match = directory.find((item) =>
				normalizeTeamName(item.name) === team.id ||
				normalizeTeamName(String(item.id || '').replace(/^team:/, '')) === team.id
			);
			if (match && team.name !== match.name) {
				changed = true;
				return {...team, name:String(match.name)};
			}
			return team;
		});
		if (changed) localStorage.setItem(STORAGE_TEAMS, JSON.stringify(canonicalStateFrom(followed)));
	}

	function teamKeyOf(value) {
		if (value && typeof value === 'object') {
			return normalizeTeamName(value.teamKey || value.name || String(value.id || '').replace(/^team:/, ''));
		}
		return normalizeTeamName(String(value || '').replace(/^team:/, ''));
	}

	function isFollowing(value) {
		const key = teamKeyOf(value);
		return followed.some((item) => item.id === key);
	}

	function toggleTeam(team) {
		const resolved = directory.find((item) =>
			String(item.id) === String(team.id) ||
			(Array.isArray(item.local_ids) && item.local_ids.map(String).includes(String(team.id))) ||
			normalizeTeamName(item.name) === normalizeTeamName(team.name)
		);
		const candidate = resolved || team;
		const id = teamKeyOf(candidate);
		if (!id) return;
		if (isFollowing(id)) {
			followed = followed.filter((item) => item.id !== id);
			saveTeams();
			return;
		}

		if (followed.length >= MAX_TEAMS) {
			setPickerMessage('You can follow up to 3 teams. Remove one before adding another.');
			return;
		}

		followed.push({
			id,
			name:String(candidate.name || team.name || id),
			division:String(candidate.division || team.division || ''),
			followedAt:new Date().toISOString(),
			knownRegistrations:Array.isArray(candidate.local_ids)
				? candidate.local_ids.map((localId) => ({eventId:'unknown', teamId:String(localId)}))
				: (candidate.id ? [{eventId:String(candidate.event || candidate.eventId || 'unknown'), teamId:String(candidate.id)}] : [])
		});
		saveTeams();
	}

	function setPickerMessage(message) {
		const count = q('[data-swat-team-picker-count]');
		if (count) count.textContent = message;
	}

	function renderChips() {
		const chips = q('[data-swat-followed-chips]');
		if (!chips) return;
		if (!followed.length) {
			chips.innerHTML = '';
			return;
		}
        const focusKey = chips.contains(document.activeElement) ? document.activeElement.dataset.swatRemoveTeam : null;
		chips.innerHTML = followed.map((team, index) =>
			'<button type="button" class="swat-followed-chip swat-team-tone-' + (index + 1) + '" data-swat-remove-team="' + esc(team.id) + '">' +
				'<span>' + esc(team.name) + '</span><em aria-hidden="true">×</em><span class="screen-reader-text">Unfollow ' + esc(team.name) + '</span>' +
			'</button>'
		).join('');
        if (focusKey) qa('[data-swat-remove-team]',chips).find(button => button.dataset.swatRemoveTeam === focusKey)?.focus({preventScroll:true});
		qa('[data-swat-remove-team]', chips).forEach((button) => button.addEventListener('click', () => {
			followed = followed.filter((item) => String(item.id) !== String(button.dataset.swatRemoveTeam));
			saveTeams();
		}));
	}

    let pickerReturnFocus = null;
	function openPicker() {
        pickerReturnFocus = document.activeElement;
		const picker = q('[data-swat-team-picker]');
		if (!picker) return;
		picker.hidden = false;
		document.body.classList.add('swat-team-picker-open');
		renderPicker();
		window.setTimeout(() => q('[data-swat-team-search]')?.focus(), 30);
	}

	function closePicker() {
		const picker = q('[data-swat-team-picker]');
		if (!picker) return;
		picker.hidden = true;
		document.body.classList.remove('swat-team-picker-open');
        if (pickerReturnFocus && pickerReturnFocus.isConnected) pickerReturnFocus.focus();
	}

	function renderPicker() {
		const list = q('[data-swat-team-picker-list]');
		const count = q('[data-swat-team-picker-count]');
		if (!list) return;

		const needle = searchTerm.toLowerCase();
		const items = directory.filter((team) => !needle || String(team.name).toLowerCase().includes(needle));
		list.innerHTML = items.length ? items.map((team) => {
			const active = isFollowing(team);
			return '<button type="button" class="swat-team-pick-row' + (active ? ' is-followed' : '') + '" data-swat-pick-team="' + esc(team.id) + '">' +
				'<span><strong>' + esc(team.name) + '</strong><small>' + (active ? 'Following' : 'Tap to follow') + '</small></span>' +
				'<em>' + (active ? '✓' : '+') + '</em>' +
			'</button>';
		}).join('') : '<div class="swat-picker-empty">No matching synchronized team.</div>';

		qa('[data-swat-pick-team]', list).forEach((button) => button.addEventListener('click', () => {
			const team = directory.find((item) => String(item.id) === String(button.dataset.swatPickTeam));
			if (team) toggleTeam(team);
		}));

		if (count) count.textContent = followed.length + ' of ' + MAX_TEAMS + ' followed';
	}

	function formatTime(value) {
			const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
			if (!match) return String(value || '');
			const hour = Number(match[1]);
			const minute = Number(match[2]);
			return (hour % 12 || 12) + ':' + String(minute).padStart(2, '0') + ' ' + (hour >= 12 ? 'PM' : 'AM');
		}

	function formatDate(value, options) {
		if (!value) return '';
		const d = new Date(String(value) + 'T12:00:00');
		return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString([], options || {weekday:'short', month:'short', day:'numeric'});
	}

	function facilityName(field) {
			const code = String(field || '').trim().toUpperCase();
			if (/^SW\s*[- ]?\d/.test(code)) return 'Southwest Athletics Complex';
			if (/^WP\s*[- ]?\d/.test(code)) return 'Wilson Park';
			if (code.indexOf('WFHS') === 0) return 'West Forsyth High School';
			return '';
		}

		function isPastUnresolved(game) {
			const value = norm(game && game.status);
			if (['LIVE','IN PROGRESS','FINAL','COMPLETE','COMPLETED'].includes(value) || value.includes('DELAY') || value.includes('CANCEL')) return false;
			const parsed = game && game.timestamp ? new Date(game.timestamp) : new Date(String(game?.date || '') + 'T' + String(game?.time || '00:00') + ':00');
			return !Number.isNaN(parsed.getTime()) && parsed.getTime() < Date.now() - (90 * 60 * 1000);
		}

		function statusInfo(status, game) {
		const value = norm(status);
		if (['LIVE','IN PROGRESS'].includes(value)) return {label:'LIVE', cls:'is-live'};
		if (['FINAL','COMPLETE','COMPLETED'].includes(value)) return {label:'FINAL', cls:'is-final'};
		if (value.includes('DELAY')) return {label:'DELAYED', cls:'is-delayed'};
		if (value.includes('CANCEL')) return {label:'CANCELLED', cls:'is-cancelled'};
		if (!value) return {label:'STATUS TBD', cls:'is-upcoming'};
		if (value === 'SCHEDULED' && isPastUnresolved(game)) return {label:'SCHEDULED · UPDATE OVERDUE', cls:'is-pending'};
			return {label:value === 'SCHEDULED' ? 'UPCOMING' : (value || 'STATUS TBD'), cls:'is-upcoming'};
	}

	function timelineFingerprint(items) {
		return items.map((game) => [game.id,game.date,game.time,game.field,game.status].join('|')).join('::');
	}

	function scheduleChanged(items) {
		if (!items.length) return false;
		const next = timelineFingerprint(items);
		const key = STORAGE_FINGERPRINT + ':' + followed.map((team) => team.id).sort().join(',');
		const previous = localStorage.getItem(key);
		localStorage.setItem(key, next);
		return Boolean(previous && previous !== next);
	}


	function modeCopy() {
		if (activeMode === 'fnl') return {title:'MY FRIDAY NIGHT', short:'FNL', empty:'NO FNL GAMES', description:'None of your followed teams are scheduled in Friday Night Lights.'};
		if (activeMode === 'snl') return {title:'MY SATURDAY NIGHT', short:'SNL', empty:'NO SNL GAMES', description:'None of your followed teams are scheduled in Saturday Night Lights.'};
		return {title:'YOUR TEAMS', short:'ITINERARY', empty:'NO GAMES YET', description:'No games for your followed teams are scheduled this weekend. Check back when schedules are posted.'};
	}

	function timelineForMode(items) {
		if (activeMode === 'weekend') return items.slice();
		return items.filter((game) => {
			const eventName = String(game.event || '').toLowerCase();
			if (activeMode === 'fnl') return eventName.includes('friday night lights');
			if (activeMode === 'snl') return eventName.includes('saturday night lights');
			return true;
		});
	}

	function renderModeTabs() {
		const copy = modeCopy();
		const title = q('[data-swat-itinerary-title]');
		if (title) title.textContent = 'Find your team';
		qa('[data-swat-itinerary-mode]').forEach((button) => {
			const active = button.dataset.swatItineraryMode === activeMode;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
			button.setAttribute('tabindex', active ? '0' : '-1');
		});
	}

	function modeDateLabel() {
		if (!payload || !payload.weekend || !payload.weekend.start) return '';
		const thursday = new Date(String(payload.weekend.start) + 'T12:00:00');
		if (Number.isNaN(thursday.getTime())) return '';
		const friday = new Date(thursday); friday.setDate(thursday.getDate() + 1);
		const saturday = new Date(thursday); saturday.setDate(thursday.getDate() + 2);
		const sunday = new Date(thursday); sunday.setDate(thursday.getDate() + 3);
		if (activeMode === 'fnl') return friday.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
		if (activeMode === 'snl') return saturday.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
		return thursday.toLocaleDateString([], {month:'short', day:'numeric'}) + '–' + sunday.toLocaleDateString([], {month:'short', day:'numeric'});
	}

	function renderWeekend() {
		const emptyState = q('[data-swat-my-weekend-empty]');
			root.classList.toggle('has-followed-teams', followed.length > 0);
			const manageButton = q('[data-swat-manage-teams]');
			if (manageButton) manageButton.textContent = followed.length ? 'Manage teams' : 'Follow your team';
		const nextGameRoot = q('[data-swat-next-game-focus]');
			const timelineRoot = q('[data-swat-weekend-timeline]');
		const nextRoot = q('[data-swat-next-events]');
		const label = q('[data-swat-weekend-label]');
		const alertRow = q('[data-swat-smart-alert-row]');
		const copy = modeCopy();

		renderModeTabs();
		renderChips();
		if (!followed.length) {
			if (emptyState) emptyState.hidden = false;
			if (nextGameRoot) nextGameRoot.hidden = true;
				if (timelineRoot) timelineRoot.hidden = true;
			if (nextRoot) nextRoot.hidden = true;
			if (alertRow) alertRow.hidden = true;
			if (label) label.textContent = 'Choose your team below for its next game and directions.';
            document.dispatchEvent(new CustomEvent('swat:ux-rendered'));
			return;
		}

		if (emptyState) emptyState.hidden = true;
		if (alertRow) alertRow.hidden = false;
		updatePushRow();

		if (!payload) {
			if (nextGameRoot) nextGameRoot.hidden = true;
				if (label) label.textContent = 'Choose a team for its next game and directions.';
            document.dispatchEvent(new CustomEvent('swat:ux-rendered'));
			return;
		}

		if (label) label.textContent = 'Choose a team for its next game and directions.';

		const timeline = timelineForMode(Array.isArray(payload.timeline) ? payload.timeline : []);


        const identity = (game) => String(game.event_slug || game.event || '') + ':' + String(game.id || [game.date, game.time, game.field, game.away, game.home].join('|'));
        const nextGame = timeline.find((g) => ['LIVE','IN PROGRESS'].includes(norm(g.status))) || timeline.find((g) => !['FINAL','COMPLETE','COMPLETED','CANCELLED','CANCELED'].includes(norm(g.status)) && !isPastUnresolved(g));
        // Keep the original change detection; never discard games from its fingerprint.
        const changed = scheduleChanged(timeline);
        const snapshotKey = 'swatUXTimesV1:' + followed.map((t) => t.id).sort().join(',');
        let saved = {};
        try { saved = JSON.parse(sessionStorage.getItem(snapshotKey) || '{}'); } catch (_) {}
        const oldTime = (g) => {
            const item = saved[identity(g)];
            return item && item.date === g.date && item.time !== g.time ? '<del aria-label="Previous start time">' + esc(formatTime(item.time)) + '</del> ' : '';
        };
        const teamName = (g) => Array.isArray(g.matched_teams) && g.matched_teams.length ? g.matched_teams.map((t) => t.name).join(' + ') : (g.my_team?.name || 'Your team');
        const opponent = (g) => g.opponent || [g.away,g.home].filter(Boolean).join(' / ');
        const score = (g) => {
            if (!['LIVE','IN PROGRESS','FINAL','COMPLETE','COMPLETED'].includes(norm(g.status)) || g.away_score === '' || g.home_score === '' || g.away_score == null || g.home_score == null) return '';
            // 1.0.17: say it from the followed team's side ("Leading 6–4") when that side is known.
            const mine = norm(g.my_team && g.my_team.name);
            const side = mine && mine === norm(g.home) ? 'home' : (mine && mine === norm(g.away) ? 'away' : '');
            if (!side) return ' · ' + esc(g.away_score) + '–' + esc(g.home_score) + ' (away–home)';
            const us = Number(side === 'home' ? g.home_score : g.away_score), them = Number(side === 'home' ? g.away_score : g.home_score);
            const live = ['LIVE','IN PROGRESS'].includes(norm(g.status));
            const word = us > them ? (live ? 'Leading' : 'Won') : (us < them ? (live ? 'Trailing' : 'Lost') : 'Tied');
            return ' · ' + word + ' ' + esc(us) + '–' + esc(them);
        };
        const field = (g) => '<span class="swat-field-badge">' + esc(g.field || 'Field TBD') + '</span> ' + esc(facilityName(g.field) || 'Complex TBD');
        const directions = (g) => g.directions_url ? '<a class="swat-ux-directions" href="' + esc(g.directions_url) + '" target="_blank" rel="noopener">Directions <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 9-9 9-9-9 9-9Zm-4 9h8m-3-3 3 3-3 3"/></svg></a>' : '<a href="' + esc(g.field_url || '#') + '">Field info</a>';
        if (nextGameRoot) {
            nextGameRoot.hidden = !nextGame;
            if (nextGame) {
                const status = statusInfo(nextGame.status,nextGame);
                nextGameRoot.innerHTML = '<div class="swat-home-up-next-label"><h2>' + (status.cls === 'is-live' ? 'Playing now' : 'Next game') + '</h2><span>' + esc(formatDate(nextGame.date,{weekday:'short',month:'short',day:'numeric'})) + '</span></div>' +
                    '<article class="swat-home-next-card ' + status.cls + '" data-game-id="' + esc(nextGame.id) + '">' +
                    '<div class="swat-home-next-time">' + oldTime(nextGame) + '<strong>' + esc(formatTime(nextGame.time)) + '</strong><span class="swat-ux-countdown" data-start="' + esc(nextGame.timestamp || '') + '" data-status="' + esc(status.label) + '">' + esc(status.label) + '</span></div>' +
                    '<div class="swat-home-next-main"><span>' + esc(teamName(nextGame)) + '</span><h3>vs. ' + esc(opponent(nextGame)) + '</h3><p>' + field(nextGame) + '</p>' +
                    '<span class="swat-ux-game-status">' + esc(status.label) + score(nextGame) + '</span><div class="swat-home-next-actions">' + directions(nextGame) + '<a href="#swat-team-itinerary">Team schedule</a></div></div></article>';
            } else nextGameRoot.innerHTML = '';
        }
        if (timelineRoot) {
            timelineRoot.hidden = false;
            const today = new Intl.DateTimeFormat('en-CA',{timeZone:'America/New_York',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
            const todayGames = timeline.filter((g) => g.date === today);
            let html = changed ? '<div class="swat-weekend-change" role="status"><strong>Schedule updated</strong><span>A followed team’s time, field, status or game changed.</span></div>' : '';
            if (!todayGames.length) html += '<p class="swat-ux-empty">No games today for your followed teams.' + (nextGame ? ' Your next game is shown above.' : ' Check the full schedule for new games.') + '</p>';
            const expanded = new Set(Array.from(timelineRoot.querySelectorAll('details[open]')).map((el) => el.dataset.uxKey));
            const rest = timeline; // All games remain reachable inside the compact strip disclosure.
            let currentDate = '';
            rest.forEach((game) => {
                if (game.date !== currentDate) {
                    currentDate = game.date;
                    html += '<div class="swat-itinerary-day"><h3>' + (game.date === today ? 'Other games today' : esc(formatDate(game.date,{weekday:'long',month:'short',day:'numeric'}))) + '</h3></div>';
                }
                const status = statusInfo(game.status,game);
                html += '<details class="swat-itinerary-game ' + status.cls + '" data-ux-key="' + esc(identity(game)) + '" data-game-id="' + esc(game.id) + '"><summary>' +
                    '<div class="swat-itinerary-time">' + oldTime(game) + '<strong>' + esc(formatTime(game.time)) + '</strong></div>' +
                    '<div class="swat-itinerary-main"><span class="swat-itinerary-team">' + esc(teamName(game)) + '</span><strong class="swat-ux-opponent">vs. ' + esc(opponent(game)) + '</strong><span class="swat-itinerary-result">' + esc(status.label) + score(game) + '</span><p>' + field(game) + '</p></div><span class="swat-ux-chevron" aria-hidden="true">⌄</span></summary>' +
                    '<div class="swat-itinerary-actions">' + directions(game) + '<a href="' + esc(game.field_help_url || '#') + '">Field help</a><a href="' + esc(game.game_url || '#') + '">Game details</a></div></details>';
            });
            if (!rest.length && timeline.length) html += '<p class="swat-ux-empty">No other games scheduled in this itinerary.</p>';
            if (!timeline.length) html += '<div class="swat-itinerary-empty"><p>' + esc(copy.description) + '</p></div>';
            timelineRoot.innerHTML = html;
            timelineRoot.querySelectorAll('details').forEach((el) => { if(expanded.has(el.dataset.uxKey)) el.open=true; });
        }
        try {
            const times = {};
            timeline.forEach((g) => { times[identity(g)] = {date:g.date,time:g.time}; });
            // Retain an old time for this page session rather than lose it on the next poll.
            timeline.forEach((g) => { const prev=saved[identity(g)]; if(prev && prev.date===g.date && prev.time!==g.time) times[identity(g)]=prev; });
            sessionStorage.setItem(snapshotKey,JSON.stringify(times));
        } catch (_) {}
        document.dispatchEvent(new CustomEvent('swat:ux-rendered'));

		const nextEvents = activeMode === 'weekend' && Array.isArray(payload.next_events) ? payload.next_events : [];
		if (nextRoot) {
			nextRoot.hidden = !nextEvents.length;
			if (nextEvents.length) {
				const reminders = readReminders();
				nextRoot.innerHTML = '<div class="swat-next-events-head"><p class="swat-app-eyebrow">NOT PLAYING THIS WEEKEND?</p><h2>NEXT UP</h2></div>' +
					nextEvents.map((event) => {
						const reminderKey = String(event.team_id) + ':' + String(event.event_slug);
						const on = reminders.includes(reminderKey);
						return '<article class="swat-next-event-card">' +
							'<div><span>' + esc(event.team_name) + '</span><h3>' + esc(event.event) + '</h3><p>' + esc(formatDate(event.date)) + (event.division ? ' · ' + esc(event.division) : '') + '</p></div>' +
							'<div><a href="' + esc(event.event_url) + '">VIEW EVENT</a><button type="button" data-swat-remind="' + esc(reminderKey) + '" data-team-id="' + esc(event.team_id) + '">' + (on ? 'REMINDER ON' : 'REMIND ME') + '</button></div>' +
						'</article>';
					}).join('');
				qa('[data-swat-remind]', nextRoot).forEach((button) => button.addEventListener('click', async () => {
					let reminders = readReminders();
					const key = button.dataset.swatRemind;
					if (reminders.includes(key)) {
						reminders = reminders.filter((item) => item !== key);
						button.textContent = 'REMIND ME';
					} else {
						const ok = await ensurePush();
						if (!ok) return;
						reminders.push(key);
						button.textContent = 'REMINDER ON';
					}
					saveReminders(reminders);
					syncPushPreferences(false);
				}));
			}
		}
	}

	function weekendDataUrl(ids) {
		const current = new URL(window.location.href);
		const previewToken = current.searchParams.get('wpvibe_preview');

		if (previewToken) {
			const url = new URL(current.pathname || '/gameday/', current.origin);
			url.searchParams.set('wpvibe_preview', previewToken);
			url.searchParams.set('swat_my_weekend_json', '1');
			if (ids.length) url.searchParams.set('teams', ids.join(','));
			return url.toString();
		}

		const url = new URL(SWATGameDay.myWeekendEndpoint, window.location.origin);
		if (ids.length) url.searchParams.set('teams', ids.join(','));
		return url.toString();
	}

	async function loadWeekend() {
		const ids = followed.map((team) => team.id).slice(0, MAX_TEAMS);
		const url = weekendDataUrl(ids);
		if (inFlightController) inFlightController.abort();
		const controller = new AbortController();
		inFlightController = controller;
		try {
			const response = await fetch(url, {cache:'no-store', credentials:'same-origin', signal:controller.signal});
			if (!response.ok) throw new Error('weekend-data');
			const fresh = await response.json();
            if (!fresh || !Array.isArray(fresh.timeline)) throw new Error('Invalid weekend data');
            payload = fresh;
            window.SWATMyWeekendStale = response.headers.get('X-SWAT-Stale') === '1' || fresh.sync_status === 'error' || Boolean(fresh.source_stale);
			window.SWATMyWeekendPayload = payload;
			window.dispatchEvent(new CustomEvent('swat:weekend-loaded', {detail:{payload:payload}}));
			directory = Array.isArray(payload.team_directory) ? payload.team_directory : [];
			resolveNames();
			localStorage.setItem(STORAGE_CACHE, JSON.stringify({savedAt:Date.now(), payload:payload}));
		} catch (e) {
			if (e && e.name === 'AbortError') return;
            window.SWATMyWeekendStale = true;
			try {
				const cached = JSON.parse(localStorage.getItem(STORAGE_CACHE) || 'null');
				if (cached?.payload) {
					payload = cached.payload;
					window.SWATMyWeekendPayload = payload;
					window.dispatchEvent(new CustomEvent('swat:weekend-loaded', {detail:{payload:payload}}));
					directory = Array.isArray(payload.team_directory) ? payload.team_directory : [];
				}
			} catch (ignore) {}
		} finally {
			if (inFlightController === controller) inFlightController = null;
		}
		if (controller.signal.aborted) return;
		renderPicker();
		renderWeekend();
        document.dispatchEvent(new CustomEvent('swat:ux-rendered'));
	}

	function urlBase64ToUint8Array(value) {
		const padding = '='.repeat((4 - value.length % 4) % 4);
		const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
		const raw = window.atob(base64);
		return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
	}

	async function existingPushSubscription() {
		if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
		const registration = await navigator.serviceWorker.ready;
		return registration.pushManager.getSubscription();
	}

	async function postPushSubscription(subscription) {
		if (!subscription) return false;
		const json = subscription.toJSON ? subscription.toJSON() : {};
		const keys = json.keys || {};
		const reminders = readReminders();
		const followedPayload = payload && Array.isArray(payload.followed) ? payload.followed : [];
		const localIds = Array.from(new Set(followedPayload.flatMap((team) => Array.isArray(team.local_ids) ? team.local_ids.map(String) : []).filter(Boolean)));
		const body = {
			subscription: json,
			endpoint: subscription.endpoint || json.endpoint || '',
			p256dh: keys.p256dh || '',
			auth: keys.auth || '',
			team_id: localIds[0] || '',
			team_name: followed[0]?.name || '',
			team_ids: localIds,
			team_names: followed.map((team) => team.name),
			reminder_events: reminders,
			notification_mode: 'smart'
		};
		const response = await fetch(SWATGameDay.pushSubscribeEndpoint, {
			method:'POST',
			headers:{'Content-Type':'application/json'},
			body:JSON.stringify(body),
			credentials:'same-origin'
		});
		return response.ok;
	}

	async function ensurePush() {
		if (isIOSDevice() && !isStandaloneApp()) {
				setAlertButton('ADD TO HOME SCREEN');
				smartAlertFeedback('Share → Add to Home Screen; reopen and turn on alerts.');
				return false;
			}
			if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
			setAlertButton('UNAVAILABLE');
			return false;
		}
		let permission = Notification.permission;
		if (permission !== 'granted') permission = await Notification.requestPermission();
		if (permission !== 'granted') {
			setAlertButton(permission === 'denied' ? 'BLOCKED' : 'TURN ON');
			return false;
		}

		try {
			const registration = await navigator.serviceWorker.ready;
			let subscription = await registration.pushManager.getSubscription();
			if (!subscription) {
				const keyResponse = await fetch(SWATGameDay.pushPublicKeyEndpoint, {cache:'no-store', credentials:'same-origin'});
				const keyData = await keyResponse.json();
				if (!keyResponse.ok || !keyData.publicKey) throw new Error('push-key');
				subscription = await registration.pushManager.subscribe({
					userVisibleOnly:true,
					applicationServerKey:urlBase64ToUint8Array(keyData.publicKey)
				});
			}
			const ok = await postPushSubscription(subscription);
			setAlertButton(ok ? 'ON' : 'RETRY');
			return ok;
		} catch (e) {
			setAlertButton('RETRY');
			return false;
		}
	}

	async function syncPushPreferences(promptIfNeeded) {
		if (!followed.length || !('Notification' in window)) {
			updatePushRow();
			return false;
		}
		if (Notification.permission !== 'granted') {
			if (promptIfNeeded) return ensurePush();
			updatePushRow();
			return false;
		}
		try {
			const subscription = await existingPushSubscription();
			if (!subscription) {
				if (promptIfNeeded) return ensurePush();
				updatePushRow();
				return false;
			}
			const ok = await postPushSubscription(subscription);
			setAlertButton(ok ? 'ON' : 'RETRY');
			return ok;
		} catch (e) {
			setAlertButton('RETRY');
			return false;
		}
	}

	function smartAlertFeedback(message) {
			const row = q('[data-swat-smart-alert-row]');
			if (!row) return;
			let feedback = q('[data-swat-smart-alert-feedback]', row);
			if (!feedback) {
				feedback = document.createElement('small');
				feedback.className = 'swat-smart-alert-feedback';
				feedback.setAttribute('data-swat-smart-alert-feedback', '');
				const copy = row.querySelector('div');
				if (copy) copy.appendChild(feedback);
			}
			if (!feedback) return;
			feedback.textContent = message || '';
			feedback.hidden = !message;
		}

		function followedTeamLabel() {
			const names = followed.map((team) => String(team.name || team.id || '')).filter(Boolean);
			if (!names.length) return '';
			return 'Following ' + names.join(' · ');
		}

		function setAlertButton(label) {
		const button = q('[data-swat-enable-alerts]');
		if (!button) return;
		let displayLabel = label;
			if (label === 'ON') displayLabel = 'ALERTS ON';
			if (label === 'TURN ON') displayLabel = 'TURN ON ALERTS';
			if (label === 'UNAVAILABLE') displayLabel = isIOSDevice() && !isStandaloneApp() ? 'ADD TO HOME' : 'NOT SUPPORTED';
			button.textContent = displayLabel;
			if (displayLabel === 'ALERTS ON') smartAlertFeedback(followedTeamLabel());
			else if (displayLabel === 'ADD TO HOME' || displayLabel === 'ADD TO HOME SCREEN') smartAlertFeedback('Share → Add to Home Screen; reopen and turn on alerts.');
			else if (displayLabel === 'NOT SUPPORTED') smartAlertFeedback('Push alerts are not supported in this browser. You can still follow your team inside Game Day.');
			else if (displayLabel === 'BLOCKED') smartAlertFeedback('Notifications are blocked for SWAT Game Day on this device. Enable them in your browser or device settings, then try again.');
			else if (displayLabel === 'RETRY') smartAlertFeedback('We could not connect team alerts. Tap Retry to try again.');
			else if (displayLabel === 'TURN ON ALERTS') smartAlertFeedback('Tap Turn on alerts to enable notifications.');
		const enabled = label === 'ALERTS ON' || label === 'ON';
			button.classList.toggle('is-on', enabled);
			button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
	}

	function updatePushRow() {
		const row = q('[data-swat-smart-alert-row]');
		if (!row) return;
		row.hidden = !followed.length;
		if (!followed.length) return;
		if (!('Notification' in window) || !('PushManager' in window)) {
			setAlertButton('UNAVAILABLE');
		} else if (Notification.permission === 'granted') {
			setAlertButton('ON');
		} else if (Notification.permission === 'denied') {
			setAlertButton('BLOCKED');
		} else {
			setAlertButton('TURN ON');
		}
	}

	function isIOSDevice() {
			return /iphone|ipad|ipod/i.test(navigator.userAgent || '');
		}

		function isStandaloneApp() {
			return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
		}

		function weatherAlertFeedback(message) {
			const feedback = q('[data-swat-weather-alert-feedback]');
			if (!feedback) return;
			feedback.textContent = message || '';
			feedback.hidden = !message;
		}

		async function subscribeWeatherAlerts() {
			const button = q('[data-swat-weather-alerts]');
			if (!button) return;

			if (isIOSDevice() && !isStandaloneApp()) {
				button.textContent = 'ADD TO HOME SCREEN FIRST';
				weatherAlertFeedback('On iPhone, tap Share → Add to Home Screen. Open SWAT Game Day from your Home Screen, then tap Subscribe to Alerts again.');
				return;
			}

			if (!('Notification' in window) || !('PushManager' in window) || !('serviceWorker' in navigator)) {
				button.textContent = 'NOTIFICATIONS UNAVAILABLE';
				weatherAlertFeedback('Push alerts are not supported in this browser. Use the SWAT Facebook page for official weather updates.');
				return;
			}

			button.disabled = true;
			button.textContent = 'CONNECTING ALERTS…';
			weatherAlertFeedback('');

			try {
				const ok = await ensurePush();
				if (ok) {
					button.textContent = 'ALERTS ON';
					weatherAlertFeedback('Subscribed. Official SWAT Game Day alerts will be sent to this device.');
				} else if (Notification.permission === 'denied') {
					button.textContent = 'NOTIFICATIONS BLOCKED';
					weatherAlertFeedback('Notifications are blocked for SWAT Game Day on this device. Enable notifications for SWAT Game Day in your browser or device settings, then try again.');
				} else {
					button.textContent = 'RETRY SUBSCRIBE';
					weatherAlertFeedback('We could not connect push alerts. Tap the button to try again.');
				}
			} catch (e) {
				button.textContent = 'RETRY SUBSCRIBE';
				weatherAlertFeedback('We could not connect push alerts. Tap the button to try again.');
			} finally {
				button.disabled = false;
			}
		}

		async function syncWeatherAlertButton() {
			const button = q('[data-swat-weather-alerts]');
			if (!button || (isIOSDevice() && !isStandaloneApp())) return;
			if (!('Notification' in window) || Notification.permission !== 'granted') return;
			try {
				const subscription = await existingPushSubscription();
				if (subscription) button.textContent = 'ALERTS ON';
			} catch (e) {}
		}

		window.addEventListener('swat:toggle-team', (event) => {
		const detail = event.detail || {};
		if (!detail.id) return;
		toggleTeam({id:String(detail.id), name:String(detail.name || detail.id)});
	});

	qa('[data-swat-itinerary-mode]').forEach((button) => button.addEventListener('click', () => {
		activeMode = button.dataset.swatItineraryMode || 'weekend';
		localStorage.setItem(STORAGE_MODE, activeMode);
		renderWeekend();
	}));

	q('[data-swat-manage-teams]')?.addEventListener('click', openPicker);
	q('[data-swat-pick-first-team]')?.addEventListener('click', openPicker);
	qa('[data-swat-team-picker-close]').forEach((button) => button.addEventListener('click', closePicker));
	q('[data-swat-enable-alerts]')?.addEventListener('click', () => ensurePush());
		q('[data-swat-weather-alerts]')?.addEventListener('click', subscribeWeatherAlerts);
	q('[data-swat-team-search]')?.addEventListener('input', (event) => {
		searchTerm = String(event.target.value || '').trim();
		renderPicker();
	});
	document.addEventListener('keydown', (event) => {
		if (q('[data-swat-team-picker]')?.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); closePicker(); }
        if (event.key === 'Tab') {
            const items = qa('button:not([disabled]),input,a[href],select,[tabindex="0"]', q('.swat-team-picker-sheet')).filter(el => el.getClientRects().length);
            const first = items[0], last = items[items.length-1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
	});

	renderChips();
	renderWeekend();
	loadWeekend();
	syncPushPreferences(false);
	syncWeatherAlertButton();
	refreshTimer = window.setInterval(() => {
		if (document.visibilityState === 'visible') loadWeekend();
	}, 30000);
	window.addEventListener('online', loadWeekend);
    document.addEventListener('click', event => { if (event.target.closest('[data-swat-retry-weekend],[data-swat-refresh]')) loadWeekend(); });
	window.addEventListener('focus', loadWeekend);
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible') loadWeekend();
	});
})();
