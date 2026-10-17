/* Pure presentation rules. Source data is never mutated or completed by inference. */
(function (root, factory) {
    const model = factory();
    if (typeof module === 'object' && module.exports) module.exports = model;
    else root.SWATGameDayModel = model;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const norm = v => String(v || '').trim().toUpperCase().replace(/[_-]+/g, ' ');
    const hasScore = v => (typeof v === 'number' || typeof v === 'string') && String(v).trim() !== '' && Number.isFinite(Number(v)) && Number(v) >= 0;
    const isChampionship = g => Boolean(g && g.championship === true);
    const championSide = g => isChampionship(g) && ['FINAL','COMPLETE','COMPLETED'].includes(norm(g.status)) && hasScore(g.home_score) && hasScore(g.away_score) && Number(g.home_score) !== Number(g.away_score) ? (Number(g.home_score) > Number(g.away_score) ? 'home' : 'away') : '';
    function syncSummary(data, now, online, failed, loading) {
        data = data || {};
        let stamp = Number(data.last_success_ts) > 0 ? Number(data.last_success_ts) * 1000 : NaN;
        if (!Number.isFinite(stamp) && data.last_sync) {
            const raw = String(data.last_sync).replace(' ', 'T');
            stamp = Date.parse(raw + (/(Z|[+-]\d\d:\d\d)$/.test(raw) ? '' : 'Z'));
        }
        const verified = Number.isFinite(stamp) && stamp <= now + 60000;
        const seconds = verified ? Math.max(0, Math.floor((now - stamp) / 1000)) : null;
        const age = verified ? 'Updated ' + (seconds < 60 ? seconds + ' sec' : Math.floor(seconds / 60) + ' min') + ' ago' : 'Update time unavailable';
        const warning = !online || failed || data.sync_status === 'error' || Boolean(data.source_stale) || !verified || seconds > 300;
        const label = !online ? 'Offline' : failed || data.sync_status === 'error' ? 'Sync failed' : !verified ? 'Sync unverified' : data.source_stale || seconds > 300 ? 'Stale data' : 'Online';
        return {warning, label, age, loading: Boolean(loading), stamp: verified ? stamp : null};
    }
    function divisionMembers(g) {
        // Explicit source members are authoritative. Never infer members from teams or standings.
        const explicit = g.member_divisions || g.combined_divisions || g.divisions;
        if (Array.isArray(explicit) && explicit.length) return [...new Set(explicit.filter(v => typeof v === 'string' && v.trim()).map(v => v.trim()))];
        const label = String(g.bracket_name || g.division || '');
        const combined = label.match(/\b\d{1,2}U(?:\s*\/\s*\d{1,2}U)+\b/i);
        return combined ? combined[0].split('/').map(v => v.trim()) : [String(g.division || 'Tournament')];
    }
    const divisionKey = v => norm(v).replace(/\s+OPEN$/, '');
    const belongsToDivision = (g, division) => !division || divisionMembers(g).some(d => divisionKey(d) === divisionKey(division));
    return {hasScore, isChampionship, championSide, syncSummary, divisionMembers, belongsToDivision};
}));
