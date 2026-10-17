/* npm-provided Playwright is test tooling only. No application dependencies. */
const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const theme=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const out=path.resolve(process.argv[3]||'gameday-evidence');
const revision=process.argv[4]||'after';
const widths=[375,390,768,1280],themes=['dark','light'];
const states=['pregame','live','final','empty','sync-failed','stale','no-data','unverified','loading'];
const views=['today','schedule','scores','standings','division','more'];
fs.mkdirSync(out,{recursive:true});
let browser,server;
(async()=>{
 browser=await chromium.launch({headless:true,...(process.env.SWAT_CHROME?{executablePath:process.env.SWAT_CHROME}:{})});
 const port=18765;
 server=spawn(process.env.PHP||'php',['-S','127.0.0.1:'+port,path.join(__dirname,'fixture.php')],{env:{...process.env,SWAT_FIXTURE_ENABLED:'1',SWAT_FIXTURE_THEME:theme},stdio:['ignore','ignore','pipe']});
 await new Promise((resolve,reject)=>{server.once('error',reject);server.stderr.on('data',b=>{if(String(b).includes('started'))resolve();});});
 const results=[];
 for(const width of widths)for(const appearance of themes)for(const state of states){
  const ctx=await browser.newContext({viewport:{width,height:844},colorScheme:appearance,timezoneId:'America/New_York',serviceWorkers:'block'});
  await ctx.addInitScript(({appearance,state})=>{
   localStorage.setItem('swatAppearanceV1',appearance);
   localStorage.setItem('swat_gameday_migrated_v1','1');
   localStorage.setItem('swat_followed_teams',JSON.stringify({version:1,followedTeams:state==='empty'?[]:[{teamKey:'winston-salem-elite-baseball-academy-black',displayName:'Winston-Salem Elite Baseball Academy Black',knownRegistrations:[{teamId:'11'}]}]}));
  },{appearance,state});
  for(const view of views){
   const page=await ctx.newPage(),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   if(state==='loading')await page.route('**/__fixture/loading/games*',()=>new Promise(()=>{}));
   await page.goto('http://127.0.0.1:'+port+'/?event=fixture-event&case='+state+'&view='+(view==='division'?'standings':view),{waitUntil:state==='loading'?'domcontentloaded':'networkidle'});
   if(view==='division' && state!=='no-data' && state!=='loading')await page.locator('[data-swat-postseason-tab="bracket"]').click();
   if(view==='standings' && state!=='no-data' && state!=='loading')await page.locator('[data-swat-postseason-tab="pool"]').click();
   await page.evaluate(()=>document.fonts.ready);
   const name=[revision,view,state,width,appearance].join('-');
   await page.screenshot({path:path.join(out,name+'.png'),fullPage:true});
   const result=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,activeView:document.querySelector('[data-swat-app]')?.dataset.uxView,warning:document.querySelector('[data-swat-connection]')?.textContent}));
   results.push({name,...result,errors});await page.close();
  }
  await ctx.close();
 }
 fs.writeFileSync(path.join(out,revision+'-matrix.json'),JSON.stringify(results,null,2));
 if(results.some(r=>r.overflow||r.errors.length))process.exitCode=1;
})().catch(error=>{
 fs.writeFileSync(path.join(out,revision+'-blocked.json'),JSON.stringify({status:'BLOCKED',reason:error.message},null,2));
 console.error(error.message);process.exitCode=1;
}).finally(async()=>{if(server)server.kill();if(browser)await browser.close();});
