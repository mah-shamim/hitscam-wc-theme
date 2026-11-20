/* স্ট্রিম B — ছোট শেয়ার্ড হেল্পার (টেমপ্লেটের HC.store/HC.toast/HC.digits এর সমতুল্য)। window.HCB */
(function () {
  'use strict';
  const store = {
    get: (k, d) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  window.HCB = Object.assign(window.HCB || {}, {
    store,
    toast: msg => (window.HCT_toast ? window.HCT_toast(msg) : null),
    digits: t => String(t == null ? '' : t).replace(/[০-৯]/g, d => '০১২৩৪৫৬৭৮৯'.indexOf(d)),
    post: (action, data) => jQuery.post(window.HCT.ajax, Object.assign({ action, nonce: window.HCT.nonce }, data))
  });
})();
