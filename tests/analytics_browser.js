// Execute the actual tracking script with controlled browser surfaces. No network.
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const root=fs.existsSync(__dirname+'/../application')?__dirname+'/../application':__dirname+'/..';
const script=fs.readFileSync(root+'/js/analytics.js','utf8');
let total=0;
for (const mode of ['direct_ga4','gtm']) {
 const calls=[],scripts=[],storage=new Map(),tabs=new Map(),buttons=[{dataset:{consent:'allow'},style:{}},{dataset:{consent:'deny'},style:{}}];
 const config={mode,is_configured:true,ga4_id:'G-TEST123456',gtm_id:'GTM-TEST12',first_party_enabled:true,csrf_token:'test'};
 const context={console,Set,Date,JSON,Number,Array,URL,URLSearchParams,Uint8Array,crypto:require('crypto').webcrypto,location:{pathname:'/contact',search:'?email=private@example.invalid&utm_source=google&utm_medium=cpc',origin:'https://tabeebgroup.com',hostname:'tabeebgroup.com'},innerWidth:390,innerHeight:800,scrollY:0,localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},sessionStorage:{getItem:k=>tabs.get(k),setItem:(k,v)=>tabs.set(k,v),removeItem:k=>tabs.delete(k)},fetch:(url,p)=>{calls.push(JSON.parse(p.body));return Promise.resolve();},setInterval:()=>1,clearInterval:()=>{},addEventListener:()=>{},document:{referrer:'',visibilityState:'visible',cookie:'',documentElement:{scrollHeight:2000},getElementById:id=>id==='tabeeb-analytics-config'?{textContent:JSON.stringify(config)}:null,querySelector:()=>null,createElement:tag=>tag==='script'?{}:{style:{},setAttribute:()=>{},querySelectorAll:()=>buttons,querySelector:()=>({focus:()=>{}})},head:{appendChild:s=>scripts.push(s)},body:{appendChild:()=>{}}}};
 context.window=context;vm.createContext(context);vm.runInContext(script,context);
 assert.equal(calls.length,0);assert.equal(scripts.length,0);assert.equal(storage.size,0);total+=3;
 context.TabeebAnalytics.setConsent(true);assert.equal(calls.length,1);assert.equal(calls[0].event_name,'page_view');assert.equal(scripts.length,1);total+=3;
 context.TabeebAnalytics.track('generate_lead',{name:'Private',lead_id:'CRM-PRIVATE',email:'private@example.invalid'});
 assert(!JSON.stringify(calls).includes('Private')&&!JSON.stringify(calls).includes('example.invalid')&&!JSON.stringify(context.dataLayer).includes('CRM-PRIVATE'));total++;
 assert(mode==='direct_ga4'?context.dataLayer.some(v=>v[0]==='event'&&v[1]==='generate_lead'):context.dataLayer.some(v=>v.event==='generate_lead'));total++;
 context.TabeebAnalytics.track('page_view');assert.equal(calls.filter(v=>v.event_name==='page_view').length,1);total++;
 context.TabeebAnalytics.setConsent(false);const before=calls.length;context.TabeebAnalytics.track('contact_click',{detail:'phone'});assert.equal(calls.length,before);assert.equal(tabs.size,0);total+=2;
 context.TabeebAnalytics.setConsent(true);assert.equal(scripts.length,1);assert.equal(context['ga-disable-'+config.ga4_id],false);total+=2;
}
console.log(JSON.stringify({passed:total,suite:'Actual analytics JavaScript: consent, mode dispatch, PII exclusion and deduplication'}));
