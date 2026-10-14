/* Optional analytics: no identifiers, tags or measurement requests before consent. */
(() => {
    'use strict';
    const configNode = document.getElementById('tabeeb-analytics-config');
    if (!configNode || /\/(admin|api|config|scripts|data)(\/|$)/.test(location.pathname)) return;
    const config = JSON.parse(configNode.textContent);
    const allowed = new Set(['page_view','form_start','form_error','generate_lead','contact_click','scroll_depth','engagement','web_vital','page_error']);
    const read = key => { try { return JSON.parse(localStorage.getItem(key)); } catch (_) { return null; } };
    const write = (key,value) => { try { localStorage.setItem(key,JSON.stringify(value)); } catch (_) {} };
    const random = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), x => x.toString(16).padStart(2,'0')).join('');
    const cleanLabel = value => /^[a-z0-9][a-z0-9_-]{0,79}$/i.test(value || '') && !/\d{7,}/.test(value) ? value.toLowerCase() : '';
    const path = /^[a-z0-9_/-]{1,200}$/i.test(location.pathname) && !/\d{7,}/.test(location.pathname) ? location.pathname : '/';
    const preferences = read('tabeeb_analytics_consent');
    let granted = preferences?.allowed === true && Date.now() - preferences.at < 180*86400000;
    let started = false, googleLoaded = false, identity, attribution, heartbeat, observersStarted = false;
    const pageId = random(), seen = new Set();
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
    gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
    function source() {
        const query = new URLSearchParams(location.search);
        const src = cleanLabel(query.get('utm_source')), medium = cleanLabel(query.get('utm_medium')), campaign = cleanLabel(query.get('utm_campaign'));
        let channel = 'direct';
        if (/^(cpc|ppc|paidsearch)$/.test(medium)) channel='paid_search';
        else if (/^(paid_social|paidsocial)$/.test(medium)) channel='paid_social';
        else if (medium==='email') channel='email';
        else if (/^(social|organic_social)$/.test(medium)) channel='organic_social';
        else if (medium==='organic') channel='organic_search';
        else if (src) channel='other';
        else if (document.referrer) {
            try {
                const ref=new URL(document.referrer);
                if (ref.origin!==location.origin) channel=/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|yahoo\.com)$/.test(ref.hostname)?'organic_search':/(^|\.)(facebook\.com|instagram\.com|linkedin\.com|t\.co)$/.test(ref.hostname)?'organic_social':'referral';
            } catch (_) {}
        }
        return {source:src,medium,campaign,channel};
    }
    function loadGoogle() {
        if (!config.is_configured) return;
        window['ga-disable-'+config.ga4_id]=false;
        gtag('consent','update',{analytics_storage:'granted',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
        if (googleLoaded) return;
        googleLoaded=true;
        const script=document.createElement('script');script.async=true;
        if (config.mode==='direct_ga4') {
            script.src='https://www.googletagmanager.com/gtag/js?id='+config.ga4_id;
            gtag('js',new Date());
            gtag('config',config.ga4_id,{send_page_view:false,allow_google_signals:false,allow_ad_personalization_signals:false,page_location:location.origin+path,page_referrer:'',campaign_source:attribution.source || undefined,campaign_medium:attribution.medium || undefined,campaign_name:attribution.campaign || undefined});
        } else {
            script.src='https://www.googletagmanager.com/gtm.js?id='+config.gtm_id;
            dataLayer.push({page_location:location.origin+path,page_referrer:'',page_path:path});
            dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'});
        }
        document.head.appendChild(script);
    }
    function track(name,params={}) {
        if (!granted || !identity || !allowed.has(name)) return;
        const details = {contact_click:['phone','whatsapp','email','maps'],form_error:['validation','network','server'],web_vital:['lcp','cls','inp','ttfb','fcp']};
        const detail = (details[name] || []).includes(params.detail) ? params.detail : name==='page_error'?'not_found':'';
        const value=Number.isFinite(params.value) ? params.value : undefined;
        try { const session=JSON.parse(sessionStorage.getItem('tabeeb_analytics_session'));if(session){if(Date.now()-session.at>30*60000){session.id=random();session.attribution=source();identity.session=session.id;attribution=session.attribution;}session.at=Date.now();sessionStorage.setItem('tabeeb_analytics_session',JSON.stringify(session));} }catch(_){}
        const eventId=name==='web_vital'?pageId+detail.padEnd(32,'0'):random();
        if (['page_view','page_error'].includes(name) && seen.has(name)) return;
        seen.add(name);
        const safe={page_location:location.origin+path,page_path:path,contact_type:name==='contact_click'?detail:undefined,error_type:name==='form_error'?detail:undefined,metric_name:name==='web_vital'?detail:undefined,value,traffic_source:attribution.source || undefined,traffic_medium:attribution.medium || undefined,campaign_name:attribution.campaign || undefined};
        if (config.is_configured) {
            if (config.mode==='direct_ga4') gtag('event',name,{...safe,send_to:config.ga4_id});
            else dataLayer.push({event:name,...safe});
        }
        if (config.first_party_enabled) {
            const payload={event_id:eventId,session_id:identity.session,visitor_id:identity.visitor,event_name:name,page_path:path,device_type:innerWidth<768?'mobile':innerWidth<1024?'tablet':'desktop',...attribution,detail,metric_value:value,csrf_token:config.csrf_token,analytics_consent:true};
            fetch('/api/analytics',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),keepalive:true}).catch(()=>{});
        }
    }
    function start() {
        if (started || !granted) return;
        started=true;
        let visitor=read('tabeeb_analytics_visitor');
        if (!visitor || Date.now()-visitor.at>90*86400000) visitor={id:random(),at:Date.now()};
        write('tabeeb_analytics_visitor',visitor);
        let session;
        try {session=JSON.parse(sessionStorage.getItem('tabeeb_analytics_session'));}catch(_){}
        if (!session || Date.now()-session.at>30*60000) session={id:random(),at:Date.now(),attribution:source()};
        session.at=Date.now();attribution=session.attribution || source();identity={visitor:visitor.id,session:session.id};
        try {sessionStorage.setItem('tabeeb_analytics_session',JSON.stringify(session));}catch(_){}
        loadGoogle();track('page_view');
        if (document.querySelector('[data-page-error="not_found"]')) track('page_error');
        heartbeat=setInterval(()=>{ if(document.visibilityState==='visible') track('engagement',{value:15}); },15000);
        if (!observersStarted && window.webVitals) {
            observersStarted=true;
            ['onLCP','onCLS','onINP','onTTFB','onFCP'].forEach(fn=>webVitals[fn](metric=>track('web_vital',{detail:metric.name.toLowerCase(),value:metric.value})));
        }
    }
    function setConsent(allowed) {
        granted=allowed;write('tabeeb_analytics_consent',{allowed,at:Date.now()});
        if (allowed) start();
        else {
            gtag('consent','update',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
            if(config.ga4_id) window['ga-disable-'+config.ga4_id]=true;
            clearInterval(heartbeat);identity=null;started=false;seen.clear();
            try {localStorage.removeItem('tabeeb_analytics_visitor');sessionStorage.removeItem('tabeeb_analytics_session');}catch(_){}
            document.cookie.split(';').forEach(cookie=>{const name=cookie.split('=')[0].trim();if(/^_ga(?:_|$)/.test(name)){document.cookie=name+'=; Max-Age=0; Path=/';document.cookie=name+'=; Max-Age=0; Path=/; Domain='+location.hostname;}});
        }
        panel.hidden=true;
    }
    const panel=document.createElement('section');panel.setAttribute('aria-label','Analytics preferences');panel.setAttribute('role','region');
    panel.style.cssText='position:fixed;bottom:16px;left:16px;right:16px;max-width:660px;margin:auto;z-index:10000;background:white;color:#07274d;padding:20px;border:1px solid #cbd5e1;border-radius:12px;box-shadow:0 4px 25px #0003;font:14px/1.5 sans-serif';
    panel.innerHTML='<strong>Optional analytics</strong><p>Allow anonymous browsing and performance measurements to help improve this website. Quote forms work without analytics. <a href="/privacy">Privacy policy</a></p><div style="display:flex;gap:12px;margin-top:12px"><button type="button" data-consent="allow">Allow analytics</button><button type="button" data-consent="deny">Decline analytics</button></div>';
    panel.querySelectorAll('button').forEach(button=>{button.style.cssText='padding:10px 14px;cursor:pointer;border:1px solid #07274d;border-radius:6px;background:white;color:#07274d';button.onclick=()=>setConsent(button.dataset.consent==='allow');});
    panel.hidden=Boolean(preferences && Date.now()-preferences.at<180*86400000);document.body.appendChild(panel);
    const preferencesButton=document.createElement('button');preferencesButton.type='button';preferencesButton.textContent='Analytics preferences';preferencesButton.style.cssText='display:block;margin:16px auto;padding:8px;border:1px solid #cbd5e1;background:white;border-radius:6px;cursor:pointer';preferencesButton.onclick=()=>{panel.hidden=false;panel.querySelector('button').focus();};document.body.appendChild(preferencesButton);
    let scrollSent=new Set();addEventListener('scroll',()=>{const percent=100*(scrollY+innerHeight)/Math.max(document.documentElement.scrollHeight,1);[25,50,75,90].forEach(value=>{if(granted && percent>=value && !scrollSent.has(value)){scrollSent.add(value);track('scroll_depth',{value});}});},{passive:true});
    window.TabeebAnalytics={track,setConsent};if(granted)start();
})();
