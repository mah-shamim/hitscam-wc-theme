/* HitsCam Tracking — Meta Pixel / GA4+Ads gtag / GTM. কনফিগ: window.HCTRK */
(function () {
  'use strict';
  var C = window.HCTRK; if (!C) return;
  var loaded = false, queue = [], seen = {};
  function cookie(n) { var m = document.cookie.match(new RegExp('(?:^|; )' + n.replace(/[-.]/g, '\\$&') + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; }
  function consent() {
    if (!C.consent.required) return true;
    return ['1', 'true', 'all', 'granted', 'marketing', 'accepted'].indexOf(String(cookie(C.consent.cookie)).toLowerCase()) > -1;
  }
  function dbg() { if (C.debug && window.console) console.log.apply(console, ['[HCTRK]'].concat([].slice.call(arguments))); }

  window[C.gtm.dl] = window[C.gtm.dl] || [];
  var dl = window[C.gtm.dl];
  function gtag() { dl.push(arguments); }

  function loadScript(src) { var s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); }

  function boot() {
    if (loaded) return; loaded = true;
    // Meta Pixel
    if (C.meta.ids.length) {
      !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      C.meta.ids.forEach(function (id) { fbq('init', id, C.meta.am && Object.keys(C.meta.am).length ? C.meta.am : undefined); });
    }
    // GTM
    if (C.gtm.id) {
      dl.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      loadScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(C.gtm.id) + (C.gtm.dl !== 'dataLayer' ? '&l=' + C.gtm.dl : ''));
    }
    // gtag (GA4 / Ads) — GTM থাকলে ডিফল্টে বন্ধ (ডাবল কাউন্ট এড়াতে)
    var gid = C.google.ga4 || C.google.ads;
    if (gid && !(C.gtm.id && C.gtm.noGtag)) {
      if (C.consent.mode) gtag('consent', 'default', { ad_storage: 'granted', analytics_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
      loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gid));
      gtag('js', new Date());
      if (C.google.ga4) gtag('config', C.google.ga4, { anonymize_ip: !!C.google.anon });
      if (C.google.ads) gtag('config', C.google.ads);
    }
    flush();
  }

  function fire(e) {
    if (!e || !e.id || seen[e.id]) return; seen[e.id] = 1;
    if (e.meta && window.fbq && C.meta.ids.length) {
      fbq(e.meta.custom ? 'trackCustom' : 'track', e.meta.name, e.meta.params && Object.keys(e.meta.params).length ? e.meta.params : undefined, { eventID: e.id });
      dbg('meta', e.meta.name, e.id);
    }
    if (e.ga) {
      var useGtag = e.ga.gtag && (C.google.ga4 || C.google.ads) && !(C.gtm.id && C.gtm.noGtag);
      if (useGtag) { gtag('event', e.ga.name, e.ga.params); dbg('gtag', e.ga.name); }
      if (e.ga.gtm && C.gtm.id) {
        dl.push({ ecommerce: null }); // আগের ecommerce অবজেক্ট ক্লিয়ার (GA4 সুপারিশ)
        dl.push({ event: e.ga.name, event_id: e.id, ecommerce: e.ga.params });
        dbg('gtm', e.ga.name);
      }
    }
    if (e.ads_conv && window.gtag !== undefined && C.google.ads) {
      gtag('event', 'conversion', { send_to: e.ads_conv, value: e.ga.params.value, currency: e.ga.params.currency, transaction_id: e.ga.params.transaction_id });
    }
  }
  function flush() { var q = queue; queue = []; q.forEach(function (e) { if (e.trigger === 'place_order') return hold(e); fire(e); }); }
  var held = [];
  function hold(e) { held.push(e); }

  function push(list) { (list || []).forEach(function (e) { if (loaded && consent()) { if (e.trigger === 'place_order') hold(e); else fire(e); } else queue.push(e); }); }

  // AddPaymentInfo — অর্ডার সাবমিটের সময়
  document.addEventListener('submit', function (ev) {
    if (!ev.target.matches || !ev.target.matches('form.checkout')) return;
    held.forEach(fire); held = [];
  }, true);
  if (window.jQuery) jQuery(document.body).on('checkout_place_order', function () { held.forEach(fire); held = []; });

  // AJAX AddToCart কিউ (WC fragments)
  function scan() {
    document.querySelectorAll('.hctrk-q[data-q]:not([data-done])').forEach(function (n) {
      n.setAttribute('data-done', '1');
      try { var q = JSON.parse(n.getAttribute('data-q') || '[]'); if (q.length) push(q); } catch (e) {}
    });
  }
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', scan);

  // কনসেন্ট
  function tryBoot() { if (consent()) { boot(); flush(); } }
  window.hitscamTrackingConsent = function () { tryBoot(); };
  document.addEventListener('hitscam:consent', tryBoot);
  if (C.consent.required) { var t = setInterval(function () { if (consent()) { clearInterval(t); tryBoot(); } }, 1000); }

  push(C.q);
  tryBoot();
})();
