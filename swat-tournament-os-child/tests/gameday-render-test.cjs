/* Exercise real render functions in a minimal DOM contract; not a browser/layout test. */
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/swat-gameday.js'),'utf8');
const model=require('../assets/js/swat-gameday-model.js');
function fn(name){
 const start=new RegExp('^([ \\t]+)function '+name+'\\(', 'm').exec(source);
 assert.ok(start,'real function '+name+' exists');
 const end=source.indexOf('\n'+start[1]+'}',start.index);
 assert.ok(end>start.index);
 return source.slice(start.index,end+start[1].length+2);
}
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function context(games){
 const elements=new Map();const node=()=>({innerHTML:'',textContent:'',hidden:false,value:'',dataset:{},classList:{toggle(){}},setAttribute(){}});
 const ctx={SWATGameDayModel:model,SWATGameDay:{gamedayUrl:'/',fieldDirections:{}},window:{location:{origin:'https://fixture.test',href:'https://fixture.test/?event=fixture'},SWATGameDayHub:{}},state:{event:'fixture',roundOpen:{},data:{loaded_event:{name:'Test <event>'}}},URL,URLSearchParams,Date,Intl,console,
 q:s=>{if(!elements.has(s))elements.set(s,node());return elements.get(s);},qa:()=>[],esc,norm:v=>String(v||'').toUpperCase(),isLive:g=>g.status==='Live',isFinal:g=>g.status==='Final',isPastUnresolved:()=>false,teamSide:()=>'',isFollowedTeam:()=>true,eventLabel:()=> 'Test <event>',changeNote:()=>'',gameDate:()=>null,tzSuffix:'',bracketGames:()=>games,canonicalGames:()=>games,followedDivisions:()=>[],byContext:g=>g,renderStandings:()=>{},setPostseasonTab:()=>{},unique:a=>[...new Set(a)]};
 vm.createContext(ctx);
 const names=['appUrl','formatTime','displayStatus','hasScore','isChampionship','gameRound','isPoolGame','gameLabel','pillLabel','shortDate','todayKey','isPlaceholderTeam','bracketTeamLabel','fieldDirectionsUrl','facilityName','starButton','teamLine','statusClass','scoreLabel','gameCard','empty','bracketGroupLabel','renderBracket'];
 vm.runInContext(names.map(fn).join('\n'),ctx);
 return {ctx,elements};
}
const game={id:1,home:'Home',away:'Away',home_score:7,away_score:4,status:'Final',division:'12U Open',date:'2026-10-17',time:'12:00',field:'SW 1',game_type_id:2,round:'Championship'};
test('real bracket renderer does not announce an inferred champion',()=>{
 const {ctx,elements}=context([game]);ctx.renderBracket([game]);
 assert.doesNotMatch(elements.get('[data-swat-bracket]').innerHTML,/>Champion<|swat-ps-winner/);
});
test('real bracket renderer puts explicitly decided title ahead of collapsed rounds',()=>{
 const g={...game,championship:true};const semi={...game,id:2,championship:false,round:'Semifinal'};
 const {ctx,elements}=context([semi,g]);ctx.renderBracket([semi,g]);
 const html=elements.get('[data-swat-bracket]').innerHTML;
 assert.match(html,/>Champion</);assert.ok(html.indexOf('swat-ps-winner')<html.indexOf('Earlier rounds'));
 assert.match(html,/<details[^>]*data-swat-round=[^>]*>/);assert.doesNotMatch(html,/<details[^>]* open/);
});
test('real game card escapes names and event names and keeps two score cells',()=>{
 const {ctx}=context([]);
 for(const status of ['Scheduled','Live','Final']) {
  const html=ctx.gameCard({...game,status,home:'<img src=x onerror=alert(1)>',away:'A & B',event:'<svg onload=alert(1)>',home_score:null},{});
  assert.doesNotMatch(html,/<img|<svg onload/);assert.match(html,/&lt;img/);assert.match(html,/A &amp; B/);
  assert.equal((html.match(/class="swat-gc-score/g)||[]).length,2);assert.match(html,/TBD/);
  assert.match(html,/aria-label=/);assert.match(html,/Directions/);assert.match(html,/Field help/);
 }
});
test('real combined bracket renderer appears under either member',()=>{
 const g={...game,championship:true,division:'12U / 13U',bracket_name:'12U / 13U Silver Bracket (combined)'};
 for(const division of ['12U','13U']) {
  const {ctx,elements}=context([g]);ctx.state.bracketDivision=division;ctx.renderBracket([g]);
  assert.match(elements.get('[data-swat-bracket]').innerHTML,/12U \/ 13U Silver Bracket \(combined\)/);
 }
});
