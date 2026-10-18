/** Actual-site screenshots at 390px and 1440px. Needs npm package playwright.
 * node tests/browser/gameday-views.cjs https://STAGING autumn-harvest-series OUTPUT
 * Set SWAT_STORAGE_STATE to a Playwright storage-state JSON for signed-in staging.
 * Set SWAT_CHROME to use an installed Chromium executable.
 * Coming Soon or sign-in pages fail; they are never counted as a Game Day pass.
 */
const {chromium}=require('playwright');
const fs=require('fs'),path=require('path');
const [base,event='autumn-harvest-series',out='gameday-screenshots']=process.argv.slice(2);
if(!base)throw new Error('Supply the full staging base URL');
const url=new URL(base.endsWith('/')?base:base+'/');
url.pathname+='gameday/';url.searchParams.set('event',event);
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.SWAT_CHROME?{executablePath:process.env.SWAT_CHROME}:{})});
 fs.mkdirSync(out,{recursive:true});let fail=false,results=[];
 try{
  for(const width of [390,1440]){
   const ctx=await browser.newContext({viewport:{width,height:1000},serviceWorkers:'block',...(process.env.SWAT_STORAGE_STATE?{storageState:process.env.SWAT_STORAGE_STATE}:{})});
   const page=await ctx.newPage(),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   for(const view of ['today','schedule','scores','pool','bracket','more','fields','weather','pitching']){
    const target=new URL(url);target.searchParams.set('view',['pool','bracket'].includes(view)?'standings':view);
    const response=await page.goto(target.toString(),{waitUntil:'networkidle'});
    if(!response?.ok())throw new Error('HTTP failure: '+target);
    if(!await page.locator('[data-swat-app]').count())throw new Error('Game Day unavailable: Coming Soon/sign-in/incorrect route');
    if(new URL(page.url()).pathname!==target.pathname)throw new Error('Unexpected redirect: '+page.url());
    // updateAge() relabels the chip Live / Stale after a successful load (ONLINE only flashes), so wait for any loaded state.
    await page.waitForFunction(()=>/^(ONLINE|Live|Stale)$/i.test((document.querySelector('[data-swat-connection]')||{}).textContent||''),null,{timeout:30000});
    if(['pool','bracket'].includes(view))await page.locator('[data-swat-postseason-tab="'+view+'"]').click();
    const section=page.locator('[data-swat-section="'+(['pool','bracket'].includes(view)?'standings':view)+'"]');
    if(!await section.isVisible())throw new Error('Selected view not visible: '+view);
    await page.screenshot({path:path.join(out,view+'-'+width+'.png'),fullPage:true});
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
    const result={width,view,overflow,errors:[...errors]};results.push(result);
    if(overflow||errors.length)fail=true;
   }
   await ctx.close();
  }
 }finally{await browser.close();}
 fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(results,null,2));
 console.log(JSON.stringify(results,null,2));
 if(fail)process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
