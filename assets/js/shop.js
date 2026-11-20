/* HitsCam — কার্ড (অ্যাড/অর্ডার নাউ/অপশন), মিনি-কার্ড (পরিমাণ/মুছুন/পরে কিনব/উইশলিস্টে রাখুন), কার্টে-আছে অবস্থা */
(function ($) {
  'use strict';
  const store = { get: (k, d) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } }, set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };
  const toast = m => (window.HCT_toast || function () {})(m);
  const post = (action, data) => $.post(HCT.ajax, Object.assign({ action, nonce: HCT.nonce }, data));
  const fmt = n => '৳' + Math.round(n).toLocaleString('en-US');
  window.HCS = { post, store, toast, fmt };

  function applyFragments(d) {
    $.each(d.fragments || {}, (sel, html) => $(sel).replaceWith(html));
    syncIncart();
  }
  window.HCS.applyFragments = applyFragments;

  function inCartIds() { try { return JSON.parse($('.hc-incart-data').attr('data-ids') || '{}'); } catch (e) { return {}; } }
  function syncIncart() {
    const ids = inCartIds();
    $('.product-card').each(function () { const id = $(this).data('id'); $(this).find('.pc-actions').toggleClass('three', !!ids[id] && !!$(this).find('.btn-add').length); });
    const $pdp = $('[data-pdp]'); if ($pdp.length) {
      const q = ids[$pdp.data('id')] || 0;
      $('#pInCartTxt').text(q ? 'এই পণ্য কার্টে আছে (' + q + 'টি)' : ''); $('#pInCart').prop('hidden', !q);
    }
  }
  $(syncIncart);

  // ── কার্ড: ভ্যারিয়েশন বদলালে দাম
  $(document).on('change', '.opt-radio', function () {
    const $c = $(this).closest('.product-card'); $c.data('opt', $(this).val()); $c.find('.pc-price').html($(this).attr('data-html'));
  });

  // ── কার্ড: অ্যাড টু কার্ট / অর্ডার নাউ
  $(document).on('click', '.hc-add', function () {
    const $b = $(this), $c = $b.closest('.product-card'); let vid = 0;
    if ($c.find('.opt-radio').length) {
      const $r = $c.find('.opt-radio:checked'); if (!$r.length) { toast('অপশন বাছাই করুন'); return; } vid = $r.data('vid');
    }
    let addId = $b.data('product_id');
    const $sel = $c.find('.opt-radio:checked');
    if ($sel.data('grp')) { if ($sel.data('child')) { addId = $sel.data('child'); } else { addId = vid; vid = 0; } } // গ্রুপড: নির্বাচিত চাইল্ড (ভ্যারিয়েবল চাইল্ডের ক্ষেত্রে চাইল্ড+ভ্যারিয়েশন)
    $b.prop('disabled', true);
    post('hc_add', { product_id: addId, variation_id: vid, quantity: 1, buy_now: $b.data('buy-now') ? 1 : 0 })
      .done(r => {
        if (r.success) {
          try { applyFragments(r.data); $(document.body).trigger('added_to_cart', [r.data.fragments, r.data.cart_hash, null]); } catch (e) { /* UI সমস্যা হলেও রিডাইরেক্ট চলবে */ }
          if ($b.data('buy-now')) { (window.HCOrder ? HCOrder.open() : location.assign(r.data.redirect || HCT.checkout || '/checkout/')); return; }
        } else toast((r.data && r.data.msg) || 'কার্টে যোগ করা যায়নি');
      }).fail(() => toast('নেটওয়ার্ক সমস্যা')).always(() => $b.prop('disabled', false));
  });

  // ── মিনি-কার্ড
  $(document).on('click', '#miniCart .qty-btn, #miniCart .rm-btn', function () {
    const $b = $(this); $b.prop('disabled', true);
    post('hc_cart_qty', { key: $b.data('key'), d: $b.data('d') }).done(r => { if (r.success) applyFragments(r.data); }).always(() => $b.prop('disabled', false));
  });
  $(document).on('click', '#miniCart .wish-move', function () {
    const $l = $(this).closest('.cart-line'), pid = +$l.data('pid'); let w = store.get('hc_wish', []);
    if (!w.includes(pid)) w.push(pid); store.set('hc_wish', w); $('.wish-count').text(w.length); $('.wish[data-id=' + pid + ']').addClass('on');
    post('hc_cart_qty', { key: $l.data('key'), d: -9999 }).done(r => { if (r.success) { applyFragments(r.data); toast('উইশলিস্টে রাখা হয়েছে ♥'); } });
  });
  // পরে কিনব: কার্ট থেকে সরিয়ে স্থানীয়ভাবে রাখা; পরে এক ক্লিকে ফেরত
  const later = () => store.get('hc_later', []);
  function paintLater() {
    const l = later(), $b = $('#hcLater'); if (!$b.length) return;
    if (!l.length) { $b.empty(); return; }
    $b.html('<div class="addon-box mt-3"><h6><i class="bi bi-clock-history text-brand"></i> পরে কিনব</h6>' + l.map((x, i) => '<div class="addon-item"><span class="grow">' + $('<i>').text(x.name).html() + '<small>পরিমাণ ' + x.qty + '</small></span><button class="btn btn-sm btn-soft later-back" data-i="' + i + '" type="button">কার্টে নিন</button></div>').join('') + '</div>');
  }
  $(document).on('click', '#miniCart .later-btn', function () {
    const $l = $(this).closest('.cart-line'), l = later();
    l.push({ pid: +$l.data('pid'), vid: +$l.data('vid'), qty: +$l.find('.qty span').text() || 1, name: $.trim($l.find('.nm').text()) }); store.set('hc_later', l);
    post('hc_cart_qty', { key: $l.data('key'), d: -9999 }).done(r => { if (r.success) { applyFragments(r.data); paintLater(); toast('"পরে কিনব" তালিকায় রাখা হয়েছে'); } });
  });
  $(document).on('click', '#hcLater .later-back', function () {
    const l = later(), x = l.splice(+$(this).data('i'), 1)[0]; store.set('hc_later', l); if (!x) return;
    post('hc_add', { product_id: x.pid, variation_id: x.vid, quantity: x.qty }).done(r => { if (r.success) { applyFragments(r.data); paintLater(); } });
  });
  $(paintLater);
  $(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed', syncIncart);
})(jQuery);
