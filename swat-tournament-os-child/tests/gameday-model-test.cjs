const {test} = require('node:test');
const assert = require('node:assert/strict');
const m = require('../assets/js/swat-gameday-model.js');
const final = {championship:true,status:'Final',home_score:7,away_score:4};
test('only explicit final unequal championship scores announce a champion', () => {
    assert.equal(m.championSide(final), 'home');
    assert.equal(m.championSide({...final,home_score:0,away_score:1}), 'away');
    for (const championship of [false,undefined,1,'true']) assert.equal(m.championSide({...final,championship,round:'Championship'}),'');
    for (const status of ['Live','Scheduled','',undefined,'Cancelled']) assert.equal(m.championSide({...final,status}),'');
    assert.equal(m.championSide({...final,away_score:7}),'');
    for (const value of [null,undefined,'',' ',false,NaN,Infinity,-1,'TBD']) {
        assert.equal(m.championSide({...final,home_score:value}),'');
        assert.equal(m.championSide({...final,away_score:value}),'');
    }
});
test('sync age is upstream age, warns after five minutes even with no games', () => {
    const now = Date.parse('2026-10-17T16:00:00Z');
    const s = (age,rest={}) => m.syncSummary({last_success_ts:now/1000-age,...rest},now,true,false,false);
    assert.equal(s(120).age,'Updated 2 min ago');
    assert.equal(s(300).warning,false);
    assert.equal(s(301).label,'Stale data');
    assert.equal(s(0,{source_stale:true}).warning,true);
    assert.equal(s(0,{sync_status:'error'}).label,'Sync failed');
    assert.equal(s(-61).label,'Sync unverified');
    assert.equal(m.syncSummary(null,now,true,false,false).label,'Sync unverified');
    assert.equal(m.syncSummary({last_sync:'garbage'},now,true,false,false).warning,true);
    assert.equal(m.syncSummary({last_sync:'2026-10-17 15:58:00'},now,true,false,false).age,'Updated 2 min ago');
    assert.equal(m.syncSummary({last_success_ts:now/1000},now,false,false,false).label,'Offline');
    assert.equal(m.syncSummary({last_success_ts:now/1000},now,true,true,true).label,'Sync failed');
});
test('combined source brackets appear under every member without inventing membership', () => {
    const g={division:'12U / 13U',bracket_name:'12U / 13U Silver Bracket (combined)'};
    assert.equal(m.belongsToDivision(g,'12U Open'),true);
    assert.equal(m.belongsToDivision(g,'13U'),true);
    assert.equal(m.belongsToDivision(g,'11U'),false);
    assert.equal(m.belongsToDivision({division:'12U',home:'13U team'},'13U'),false);
    assert.deepEqual(m.divisionMembers({division:'12U',member_divisions:['12U','13U']}),['12U','13U']);
});
