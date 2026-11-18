/* HitsCam — প্রোডাক্ট পেজ: জুম/থাম্ব, ভ্যারিয়েশন, qty, অ্যাড/অর্ডার, WhatsApp, ডেলিভারি, EMI, শেয়ার, বাই-বার, অ্যালার্ট, সম্প্রতি দেখা */
(function ($) {
  'use strict';
  const $m = $('[data-pdp]'); if (!$m.length) return;
  const S = window.HCS, toast = S.toast, fmt = S.fmt, store = S.store;
  const D = JSON.parse($('#pdpVars').text() || '{}'), vars = D.vars || [], attrs = D.attrs || [];
  const pid = +$m.data('id'), basePrice = +$m.data('price'), name = $m.data('name');
  let sel = vars.length ? D.sel : -1;

  // গ্যালারি
  const imgs = $('#zoomBox').data('imgs') || [];
  const show = i => { $('#zoomBox').css({ 'background-image': 'url("' + imgs[i] + '")', 'background-size': 'cover', 'background-position': 'center' }); $('#thumbs .thumb').removeClass('active').eq(i).addClass('active'); };
  $('#thumbs').owlCarousel && $('#thumbs').owlCarousel({ margin: 10, dots: false, nav: true, navText: ['‹', '›'], responsive: { 0: { items: 4 }, 576: { items: 5 } } });
  $(document).on('click', '#thumbs .thumb', function () { show(+$(this).data('i')); });
  $('#zoomBox').on('mousemove', function (e) {
    const r = this.getBoundingClientRect(), x = (e.clientX - r.left) / r.width * 100, y = (e.clientY - r.top) / r.height * 100;
    $(this).addClass('zooming').css('background-position', x + '% ' + y + '%');
  }).on('mouseleave', function () { $(this).removeClass('zooming').css('background-position', 'center'); });

  // ভ্যারিয়েশন
  const match = at => vars.findIndex(v => attrs.every(a => !v.attrs[a.key] || v.attrs[a.key] === at[a.key]));
  const cur = () => (sel >= 0 ? vars[sel] : null);
  function curAttrs() { const o = {}; attrs.forEach(a => { o[a.key] = ($('.attr-radio[data-key="' + a.key + '"]:checked').val()) || ''; }); return o; }
  function paintOpts() {
    if (!attrs.length || sel < 0) return;
    const v = vars[sel], at = Object.assign({}, curAttrs());
    attrs.forEach(a => {
      $('.attr-radio[data-key="' + a.key + '"]').each(function () {
        const val = this.value, test = Object.assign({}, v.attrs.length ? {} : {}, attrsOf(v), { [a.key]: val }), j = match(test);
        this.checked = (attrsOf(v)[a.key] || '') === val;
        const $l = $(this).closest('label'); $l.toggleClass('na', j < 0);
        $l.find('.attr-price').text(j >= 0 ? fmt(vars[j].price) : '—');
      });
    });
  }
  function attrsOf(v) { const o = {}; attrs.forEach(a => { o[a.key] = v.attrs[a.key] || (curAttrs()[a.key] || ''); }); return o; }
  function pickVar(key, val) {
    const at = Object.assign({}, attrsOf(vars[sel]), { [key]: val }); let i = match(at); if (i >= 0) return i;
    let b = -1; vars.forEach((v, j) => { if ((v.attrs[key] || '') === val && (b < 0 || v.price < vars[b].price)) b = j; }); return b;
  }
  $(document).on('change', '.attr-radio', function () {
    const i = pickVar(this.dataset.key, this.value); if (i < 0) return; sel = i; render();
  });
  function price() { return cur() ? cur().price : basePrice; }
  function render() {
    const v = cur();
    if (v) {
      $('#pPrice').html(v.html); if (v.sku) $('#pSku').text(v.sku);
      if (v.image) { imgs.unshift(v.image); $('#zoomBox').css('background-image', 'url("' + v.image + '")'); imgs.shift(); }
      $('.p-add,.p-order,#bbBtn').prop('disabled', !v.stock);
      paintOpts();
    }
    $('#bbPrice').text(fmt(price()));
    renderExtras();
  }

  // qty
  $('#qMinus').on('click', () => $('#pQty').val(Math.max(1, +$('#pQty').val() - 1)));
  $('#qPlus').on('click', () => $('#pQty').val(+$('#pQty').val() + 1));
  const qty = () => Math.max(1, +$('#pQty').val() || 1);

  // অ্যাড / অর্ডার
  function addMain(buyNow, $btn) {
    let vid = 0;
    if (vars.length) { const v = cur(); if (!v || attrs.some(a => !$('.attr-radio[data-key="' + a.key + '"]:checked').length)) { toast('অপশন বেছে নিন'); return; } vid = v.id; }
    let pidAdd = pid; if (D.grouped && vid) { const cv = cur(); if (cv && cv.child) { pidAdd = cv.child; } else { pidAdd = vid; vid = 0; } } // গ্রুপড: বেছে নেওয়া চাইল্ডই কার্টে যায়
    const at = {}; if (vid) attrs.forEach(a => { at[a.key] = $('.attr-radio[data-key="' + a.key + '"]:checked').val() || ''; });
    const extra = $('.up-chk:checked').map(function () { return $(this).data('id'); }).get();
    $btn && $btn.prop('disabled', true);
    S.post('hc_add', { product_id: pidAdd, variation_id: vid, quantity: qty(), attrs: at, extra, buy_now: buyNow ? 1 : 0 }).done(r => {
      if (r.success) { try { S.applyFragments(r.data); $(document.body).trigger('added_to_cart', [r.data.fragments, r.data.cart_hash, null]); } catch (e) { /* UI আপডেটে সমস্যা হলেও অর্ডার নাউ চলবে */ } if (buyNow || r.data.redirect) { location.assign(r.data.redirect || HCT.checkout || '/checkout/'); } }
      else toast((r.data && r.data.msg) || 'কার্টে যোগ করা যায়নি');
    }).always(() => $btn && $btn.prop('disabled', false));
  }
  $('.p-add').on('click', function () { addMain(false, $(this)); });
  $('.p-order').on('click', function () { addMain(true, $(this)); });
  $('#bbBtn').on('click', () => $('.p-order').trigger('click'));

  // WhatsApp
  $('#pWa').on('click', function () {
    const v = cur(), q = qty(), p = price();
    const msg = 'আসসালামু আলাইকুম, আমি এই পণ্যটি অর্ডার করতে চাই:\n1. ' + name + (v ? ' (' + v.label + ')' : '') + ' × ' + q + ' = ' + fmt(p * q) + '\nমোট: ' + fmt(p * q) + '\nলিংক: ' + location.href.split('#')[0];
    window.open('https://wa.me/' + $m.data('wa') + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
  });

  // ডেলিভারি এস্টিমেট + EMI
  const ZONES = { 70: { min: 1, max: 2 }, 130: { min: 2, max: 4 } }, FREE = +(HCT.freeShip || 5000), EMI_MIN = 5000, PLANS = [{ m: 3, fee: 0 }, { m: 6, fee: 0 }, { m: 12, fee: 0.06 }];
  const addBiz = (d, n) => { const x = new Date(d); while (n > 0) { x.setDate(x.getDate() + 1); if (x.getDay() !== 5) n--; } return x; };
  const fday = d => d.toLocaleDateString('bn-BD', { day: 'numeric', month: 'short' });
  function renderExtras() {
    if (!$('#pDeliv').length) return;
    const z = +store.get('hc_ship', 70) === 130 ? 130 : 70, p = price(), Z = ZONES[z], n = new Date();
    $('#pDeliv .zone-btn').each(function () { const on = +$(this).data('z') === z; $(this).toggleClass('active', on).attr('aria-pressed', on); });
    $('#delivText').text(fday(addBiz(n, Z.min)) + ' – ' + fday(addBiz(n, Z.max)) + ' এর মধ্যে · ' + (p >= FREE ? 'ডেলিভারি চার্জ ফ্রি' : 'ডেলিভারি চার্জ ' + fmt(z)));
    const show = p >= EMI_MIN; $('#pEmi').toggle(show);
    if (show) {
      const pl = PLANS.map(x => ({ m: x.m, fee: x.fee, month: Math.ceil(p * (1 + x.fee) / x.m), total: Math.round(p * (1 + x.fee)) }));
      $('#emiFrom').text(fmt(pl[pl.length - 1].month));
      $('#emiRows').html(pl.map(x => '<tr><td>' + x.m + ' মাস</td><td><b>' + fmt(x.month) + '</b></td><td>' + fmt(x.total) + (x.fee ? ' <small class="text-muted">(ফি ' + Math.round(x.fee * 100) + '%)</small>' : '') + '</td></tr>').join(''));
    }
  }
  $(document).on('click', '#pDeliv .zone-btn', function () { store.set('hc_ship', +$(this).data('z')); renderExtras(); });

  // শেয়ার কপি
  $('#shCopy').on('click', function () { const u = location.href.split('#')[0]; navigator.clipboard && navigator.clipboard.writeText ? navigator.clipboard.writeText(u).then(() => toast('লিংক কপি হয়েছে ✓'), () => toast(u)) : toast(u); });

  // স্টক/দাম অ্যালার্ট
  $('#notifyForm, #priceAlertForm').on('submit', function (e) {
    e.preventDefault(); const f = this, $f = $(f), type = $f.data('type');
    S.post('hc_lead', { product_id: pid, type, phone: f.phone.value, target: f.target ? f.target.value : '' }).done(r => {
      if (r.success) { $f.hide(); $(type === 'price' ? '#priceOk' : '#notifyOk').text('✓ ধন্যবাদ! নাম্বারটি সংরক্ষিত হয়েছে, আপডেট জানানো হবে।').show(); }
      else { $(f.phone).addClass('is-invalid'); toast((r.data && r.data.msg) || 'সমস্যা হয়েছে'); }
    });
  });

  // স্টিকি বাই-বার (মোবাইল)
  const bb = () => { const el = document.getElementById('pBuy'); $('#buyBar').toggleClass('show', !!el && el.getBoundingClientRect().bottom < 0); };
  let tick = false; window.addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(() => { tick = false; bb(); }); } }, { passive: true }); window.addEventListener('resize', bb);

  // রেটিং ইনপুটের ভ্যালিডেশন
  $('#reviewForm').on('submit', function (e) { if (!$(this).find('[name=rating]:checked').length) { e.preventDefault(); toast('রেটিং দিন'); } });

  // সম্প্রতি দেখা পণ্য
  let rec = store.get('hc_recent', []).filter(x => x !== pid);
  if (rec.length) S.post('hc_cards', { ids: rec.slice(0, 4) }).done(r => { if (r.success && r.data) { $('#secRecent').html(r.data); $('#recentWrap').show(); } });
  rec.unshift(pid); store.set('hc_recent', rec.slice(0, 12));

  render();
})(jQuery);

/* তথ্য-কলাম লম্বা হলে ভেতরে স্ক্রল না করে স্বাভাবিকভাবে নামবে — অ্যাড/অর্ডার বাটন যেন কখনো কাটা না পড়ে (ছবি-কলাম স্টিকি থাকে) */
(function () {
  function fit() {
    const e = document.querySelector('.pi-scroll'); if (!e) return;
    e.classList.remove('flow');
    if (window.innerWidth >= 992 && e.scrollHeight > e.clientHeight + 4) e.classList.add('flow');
  }
  window.addEventListener('load', fit); window.addEventListener('resize', fit); document.addEventListener('DOMContentLoaded', fit);
  new MutationObserver(function () { clearTimeout(fit.t); fit.t = setTimeout(fit, 120); }).observe(document.querySelector('.pi-scroll') || document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'style'] });
})();
