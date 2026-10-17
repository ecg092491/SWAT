const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs');
const css=fs.readFileSync(require('node:path').join(__dirname,'../assets/css/gameday-tokens.css'),'utf8');
const block=selector=>css.slice(css.indexOf(selector)+selector.length).split('}')[0];
const parse=text=>Object.fromEntries([...text.matchAll(/(--gd-[\w-]+):([^;]+);/g)].map(m=>[m[1],m[2]]));
const dark=parse(block('body.swat-hub-ui{')),light={...dark,...parse(block('body.swat-hub-ui[data-swat-appearance="light"]{'))};
const resolve=(map,key)=>map[key].startsWith('var(')?resolve(map,map[key].slice(4,-1)):map[key];
function luminance(hex){if(hex.length===4)hex='#'+[...hex.slice(1)].map(c=>c+c).join('');const a=hex.slice(1).match(/../g).map(c=>parseInt(c,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return a[0]*.2126+a[1]*.7152+a[2]*.0722;}
function contrast(a,b){const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
for(const [theme,map] of Object.entries({dark,light}))test(theme+' shared token text and control contrast',()=>{
 for(const fg of ['--gd-ink','--gd-muted','--gd-gold-text'])for(const bg of ['--gd-bg','--gd-surface','--gd-surface-2'])assert.ok(contrast(resolve(map,fg),resolve(map,bg))>=4.5,theme+' '+fg+' on '+bg);
 assert.ok(contrast(resolve(map,'--gd-accent-ink'),resolve(map,'--gd-accent'))>=4.5);
 for(const bg of ['--gd-bg','--gd-surface','--gd-surface-2'])assert.ok(contrast(resolve(map,'--gd-line-strong'),resolve(map,bg))>=3,theme+' control boundary on '+bg);
});
