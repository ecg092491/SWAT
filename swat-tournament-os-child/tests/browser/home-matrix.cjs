/* Optional external Playwright tooling; synthetic fixture, not WordPress integration. */
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const theme=path.resolve(process.argv[2]||path.join(__dirname,'../..')),out=path.resolve(process.argv[3]||'home-evidence'),revision=process.argv[4]||'after';
fs.mkdirSync(out,{recursive:true});let browser,server;
(async()=>{
 browser=await chromium.launch({headless:true,...(process.env.SWAT_CHROME?{executablePath:process.env.SWAT_CHROME}:{})});
 server=spawn(process.env.PHP||'php',['-S','127.0.0.1:18767',path.join(__dirname,'home-fixture.php')],{env:{...process.env,SWAT_FIXTURE_ENABLED:'1',SWAT_FIXTURE_THEME:theme},stdio:['ignore','ignore','pipe']});
 await new Promise((resolve,reject)=>{server.once('error',reject);server.stderr.on('data',b=>{if(String(b).includes('started'))resolve();});});
 const results=[];
 for(const width of [375,390,768,1280])for(const appearance of ['dark','light'])for(const state of ['open','limited','sold_out','closed','empty','live']){
  const context=await browser.newContext({viewport:{width,height:844},colorScheme:appearance});
  await context.addInitScript(mode=>localStorage.setItem('swatAppearanceV1',mode),appearance);
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:18767/?case='+state,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  const name=[revision,'home',state,width,appearance].join('-');await page.screenshot({path:path.join(out,name+'.png'),fullPage:true});
  results.push({name,errors,...await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,heroCTABottom:document.querySelector('.swh-hero__ctas')?.getBoundingClientRect().bottom,viewportHeight:innerHeight,links:[...document.querySelectorAll('.swh a')].map(a=>({label:a.textContent.trim(),href:a.getAttribute('href')}))}))});await context.close();
 }
 fs.writeFileSync(path.join(out,revision+'-home-matrix.json'),JSON.stringify(results,null,2));if(results.some(r=>r.overflow||r.errors.length))process.exitCode=1;
})().catch(error=>{fs.writeFileSync(path.join(out,revision+'-home-blocked.json'),JSON.stringify({status:'BLOCKED',reason:error.message},null,2));console.error(error.message);process.exitCode=1;}).finally(async()=>{if(server)server.kill();if(browser)await browser.close();});
