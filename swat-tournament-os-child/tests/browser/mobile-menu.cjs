/** Local header regression check using the real PHP header, parent CSS and menu JS.
 * PARENT=/absolute/parent PHP=/path/php NODE_PATH=/path/node_modules node tests/browser/mobile-menu.cjs OUTPUT
 * This fixture verifies navigation layout, not WordPress data or deployment.
 */
const {chromium}=require('playwright');
const {execFileSync}=require('child_process');
const fs=require('fs'),path=require('path'),assert=require('assert');
const root=path.resolve(__dirname,'../..'),parent=process.env.PARENT;
if(!parent)throw Error('Set PARENT to the parent theme folder');
const out=path.resolve(process.argv[2]||'mobile-menu-screenshots');fs.mkdirSync(out,{recursive:true});
// 1.0.17: the real Game Day stack (inc/gameday-hub.php) - flagship is replaced in place by the generated site chrome,
// the parent's Game Day-only sheets are not printed, and the hub loads last.
const css=[path.join(parent,'assets/css/swat-os.css'),path.join(root,'assets/css/gameday-site-chrome.css'),path.join(parent,'assets/css/swat-mobile-nav-refine.css'),path.join(root,'assets/css/child.css'),path.join(root,'assets/css/child-fonts.css'),path.join(root,'assets/css/swat-gameday-hub.css')];
const styles=css.map(p=>'<style>'+fs.readFileSync(p,'utf8').replace(/url\(['"]?\.\.\/fonts\/([^'")]+)['"]?\)/g,(_,name)=>'url(data:font/woff2;base64,'+fs.readFileSync(path.join(root,'assets/fonts',name)).toString('base64')+')')+'</style>').join('');
const stub=`define('ABSPATH', '/'); function swat_os_is_gameday_request(){return true;} function swat_os_current_event(){return [];} function language_attributes(){echo 'lang="en"';} function bloginfo($x){echo 'UTF-8';} function wp_head(){} function body_class(){echo 'class="swat-gameday-mode swat-hub-ui"';} function wp_body_open(){} function esc_html_e($s,$d){echo $s;} function esc_attr_e($s,$d){echo $s;} function esc_url($s){return $s;} function esc_attr($s){return $s;} function get_bloginfo($s){return 'SWAT Tournaments';} function home_url($s){return 'https://swatourneys.com'.$s;} function swat_os_logo_url(){return '';} function swat_os_gameday_url($args=[]){return home_url('/gameday/').(empty($args)?'':'?'.http_build_query($args));} include $argv[1];`;
const logo='data:image/png;base64,'+fs.readFileSync(path.join(parent,'assets/images/swat-primary-logo.png')).toString('base64');
const header=execFileSync(process.env.PHP||'php',['-r',stub,path.join(root,'header.php')],{encoding:'utf8'}).replace('src=""','src="'+logo+'"');
const content='<div class="swat-gameday"><section class="swat-home-hero" style="padding:32px"><h1>Game Day</h1><p>Local navigation layout fixture</p></section><nav class="swat-bottom-nav swat-app-bottom-nav swat-gameday-tabs">'+['Today','Schedule','Scores','Standings','More'].map(n=>'<button>'+n+'</button>').join('')+'</nav><section style="min-height:1800px">Schedule</section></div></main>';
(async()=>{
 const options={headless:true};
 if(process.env.SWAT_CHROME)options.executablePath=process.env.SWAT_CHROME;
 if(process.env.SWAT_CHROME_ARGS)options.args=JSON.parse(process.env.SWAT_CHROME_ARGS);
 const browser=await chromium.launch(options);const results=[];
 try{
 for(const width of [390,1024,1440]){
  const page=await browser.newPage({viewport:{width,height:844}});
  await page.setContent(header.replace('</head>',styles+'</head>')+content);
  await page.addScriptTag({path:path.join(parent,'assets/js/swat-os.js')});
  const nav=page.locator('#swat-primary-nav'),toggle=page.locator('.swat-menu-toggle');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'horizontal overflow');
  if(width<1260){
   await page.evaluate(()=>scrollTo(0,150));await toggle.click();
   assert.equal(await toggle.getAttribute('aria-expanded'),'true');assert(await nav.isVisible());
   assert.equal(await page.locator('.swat-gameday-tabs').evaluate(e=>getComputedStyle(e).visibility),'hidden');
   assert.equal(await page.evaluate(()=>getComputedStyle(document.body).overflow),'hidden');
   const geometry=await page.locator('.swat-submenu a').evaluateAll(els=>els.map(e=>({text:e.textContent,align:getComputedStyle(e).justifyContent,before:getComputedStyle(e,'::before').display,height:e.getBoundingClientRect().height,left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right})));
   for(const g of geometry){assert.equal(g.align,'flex-start');assert.equal(g.before,'none');assert(g.height>=44);assert(g.left>=0&&g.right<=width);}
   const layer=await nav.evaluate(e=>{const r=e.getBoundingClientRect();return e.contains(document.elementFromPoint(r.left+10,r.top+40));});assert(layer,'drawer on top');
   await page.screenshot({path:path.join(out,'menu-open-'+width+'.png')});
   const before=await page.evaluate(()=>scrollY);await page.mouse.wheel(0,500);await page.waitForTimeout(200);assert.equal(await page.evaluate(()=>scrollY),before,'background scroll locked');
   await nav.evaluate(e=>e.scrollTop=e.scrollHeight);await page.screenshot({path:path.join(out,'menu-bottom-'+width+'.png')});
   await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'false');assert.equal(await page.locator('.swat-gameday-tabs').evaluate(e=>getComputedStyle(e).visibility),'visible');
   await toggle.click();await nav.evaluate(e=>e.addEventListener('click',event=>event.preventDefault()));await nav.locator('a').last().click();assert.equal(await toggle.getAttribute('aria-expanded'),'false','link closes menu');
  }else{assert(await nav.isVisible());assert(!(await toggle.isVisible()));}
  // Re-create the local fixture after the link navigation.
  await page.setContent(header.replace('</head>',styles+'</head>')+content);
  await page.screenshot({path:path.join(out,'menu-closed-'+width+'.png')});
  results.push({width,status:'PASS',scope:'Local real-header/CSS/menu-JS fixture'});await page.close();
 }
 }finally{await browser.close();}
 fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify(results,null,2));
})().catch(e=>{console.error(e);process.exitCode=1;});
