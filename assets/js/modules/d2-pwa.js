/* d2 — সম্মতি ব্যানার (analytics.js), অফলাইন ব্যানার + অর্ডার নোটিফিকেশন (pro.js), service worker, ইনস্টল প্রম্পট */
jQuery(function ($) {
  if (!window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const S = { get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };
  const C = window.HCB_CONSENT || { required: false, cookie: 'hc_consent' }, P = window.HCB_PWA || {};

  /* ---- কুকি সম্মতি ---- */
  const setCookie = v => { document.cookie = C.cookie + '=' + v + ';path=/;max-age=' + 31536000 + ';SameSite=Lax'; };
  const getCookie = () => (document.cookie.match(new RegExp('(?:^|; )' + C.cookie + '=([^;]*)')) || [])[1] || '';
  function bar() {
    if (!C.required || $('#consentBar').length) return;
    $('body').append('<div class="consent-bar" id="consentBar" role="dialog" aria-label="কুকি সম্মতি"><div class="in"><span><i class="bi bi-shield-check text-brand"></i> আমরা সাইটের মান ও বিজ্ঞাপন উন্নত করতে কুকি/অ্যানালিটিক্স ব্যবহার করতে চাই। আপনার অনুমতি ছাড়া কোনো থার্ড-পার্টি ট্র্যাকিং চালু হবে না।</span><span class="d-flex gap-2"><button type="button" class="btn btn-sm btn-soft" id="consentNo">না, ধন্যবাদ</button><button type="button" class="btn btn-sm btn-brand" id="consentYes">অনুমতি দিন</button></span></div></div>');
  }
  $(document).on('click', '#consentYes', function () { setCookie('granted'); S.set('hc_consent', 'granted'); $('#consentBar').remove(); document.dispatchEvent(new Event('hitscam:consent')); });
  $(document).on('click', '#consentNo', function () { setCookie('denied'); S.set('hc_consent', 'denied'); $('#consentBar').remove(); });
  $(document).on('click', '.cookie-settings', function (e) { e.preventDefault(); setCookie(''); bar(); });
  if (!getCookie()) bar();

  /* ---- অফলাইন ব্যানার ---- */
  function setOffline(off) {
    if (off) { if (!$('#offlineBar').length) $('body').prepend('<div class="offline-bar" id="offlineBar" role="status"><i class="bi bi-wifi-off"></i> আপনি অফলাইনে আছেন — আগে দেখা পেজ ক্যাশ থেকে দেখাচ্ছে। অর্ডার করতে ইন্টারনেট লাগবে।</div>'); }
    else if ($('#offlineBar').length) { $('#offlineBar').remove(); toast('আবার অনলাইনে ✓'); }
  }
  window.addEventListener('offline', () => setOffline(true)); window.addEventListener('online', () => setOffline(false));
  if (navigator.onLine === false) setOffline(true);

  /* ---- service worker ---- */
  if ('serviceWorker' in navigator && P.sw && location.protocol !== 'file:') navigator.serviceWorker.register(P.sw, { scope: '/' }).catch(() => {});

  /* ---- ইনস্টল প্রম্পট ---- */
  let dip = null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); dip = e; if (S.get('hc_install_dismiss')) return; if (!$('#installBar').length) $('body').append('<div class="consent-bar" id="installBar" role="dialog"><div class="in"><span><i class="bi bi-phone text-brand"></i> অ্যাপের মতো ব্যবহার করতে হোম স্ক্রিনে যোগ করুন</span><span class="d-flex gap-2"><button type="button" class="btn btn-sm btn-soft" id="instNo">পরে</button><button type="button" class="btn btn-sm btn-brand" id="instYes">ইনস্টল</button></span></div></div>'); });
  $(document).on('click', '#instYes', async function () { if (dip) { dip.prompt(); await dip.userChoice; dip = null; } $('#installBar').remove(); });
  $(document).on('click', '#instNo', function () { S.set('hc_install_dismiss', 1); $('#installBar').remove(); });

  /* ---- অর্ডার আপডেট নোটিফিকেশন ---- */
  const b64 = s => { const p = '='.repeat((4 - s.length % 4) % 4), r = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from(r, c => c.charCodeAt(0)); };
  async function notifyLocal(title, body) {
    try { if (!('Notification' in window) || Notification.permission !== 'granted') return; const reg = 'serviceWorker' in navigator && navigator.serviceWorker.controller ? await navigator.serviceWorker.ready : null; if (reg && reg.showNotification) await reg.showNotification(title, { body, icon: P.icon, badge: P.icon, data: { url: P.track || '/' } }); else new Notification(title, { body, icon: P.icon }); } catch (e) {}
  }
  async function enable() {
    if (!('Notification' in window)) { toast('এই ব্রাউজারে নোটিফিকেশন সাপোর্ট নেই'); return 'unsupported'; }
    const res = await Notification.requestPermission();
    if (res !== 'granted') { S.set('hc_notif', { state: 'denied', ts: Date.now() }); return res; }
    S.set('hc_notif', { state: 'on', ts: Date.now() });
    if (P.vapid && 'serviceWorker' in navigator && 'PushManager' in window) { try { const reg = await navigator.serviceWorker.ready; const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(P.vapid) }); $.post(HCT.ajax, { action: 'hc_push_sub', nonce: HCT.nonce, sub: JSON.stringify(sub.toJSON()) }); } catch (e) {} }
    notifyLocal('নোটিফিকেশন চালু হয়েছে ✓', 'অর্ডারের আপডেট এখান থেকে জানানো হবে।');
    return 'granted';
  }
  const $or = $('.order-received');
  if ($or.length && +$or.data('nb') !== 0 && 'Notification' in window && !$('#notifBox').length) {
    const st = S.get('hc_notif'), on = st && st.state === 'on' && Notification.permission === 'granted';
    $or.find('.row.g-2.mb-5').first().before(`<div class="notif-box no-print" id="notifBox"><div><b><i class="bi bi-bell-fill text-brand"></i> অর্ডারের আপডেট নোটিফিকেশনে পান</b><div class="small text-muted" id="notifTxt">${on ? 'চালু আছে ✓' : 'কনফার্ম, কুরিয়ারে পাঠানো ও ডেলিভারির খবর এই ডিভাইসে জানানো হবে।'}</div></div><button type="button" class="btn btn-sm ${on ? 'btn-soft' : 'btn-brand'}" id="notifBtn" ${on ? 'disabled' : ''}>${on ? 'চালু আছে' : 'চালু করুন'}</button></div>`);
  }
  $(document).on('click', '#notifBtn', async function () { const r = await enable(); $('#notifTxt').text(r === 'granted' ? 'চালু হয়েছে ✓' : r === 'denied' ? 'ব্রাউজারে নোটিফিকেশন বন্ধ করা আছে — সেটিংস থেকে চালু করুন' : 'সাপোর্ট নেই'); if (r === 'granted') $(this).text('চালু আছে').removeClass('btn-brand').addClass('btn-soft').prop('disabled', true); });
});
