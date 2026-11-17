/* HitsCam থিম — ফ্রন্ট-এন্ড (jQuery)। কার্ট সার্ভার-সাইড (WooCommerce); উইশলিস্ট/তুলনা localStorage। */
(function ($) {
  'use strict';
  const store = {
    get: (k, d) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  const toast = msg => { const $w = $('.toast-wrap'); const $t = $('<div class="hc-toast"></div>').text(msg).appendTo($w); setTimeout(() => $t.fadeOut(300, () => $t.remove()), 2200); };
  window.HCT_toast = toast;
  const post = (action, data) => $.post(HCT.ajax, Object.assign({ action, nonce: HCT.nonce }, data));

  // ── থিম
  $(document).on('click', '#themeBtn', function () {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', cur); document.documentElement.setAttribute('data-bs-theme', cur);
    try { localStorage.setItem('hc_theme', cur); } catch (e) {}
  });

  // ── হেডার উচ্চতা (স্টিকি ফ্রি-শিপ বারের জন্য), টপে-যান
  function hdr() { const h = document.querySelector('.site-header'); if (h) document.documentElement.style.setProperty('--hdr-h', h.offsetHeight + 'px'); }
  $(window).on('load resize', hdr); $(hdr);
  $(document).on('click', '#toTop', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  $(window).on('scroll', () => $('#toTop').toggleClass('show', (window.pageYOffset || document.documentElement.scrollTop) > 400));

  // ── ক্যাটেগরি সাবমেনু
  const subSet = open => { $('#navSub').toggleClass('open', open); $('.sub-toggle').attr('aria-expanded', open).toggleClass('on', open); };
  const canHover = () => window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  $(document).on('click', '.sub-toggle', function (e) { e.stopPropagation(); (canHover() && e.detail > 0) ? subSet(true) : subSet(!$('#navSub').hasClass('open')); });
  $(document).on('click', e => { if (!$(e.target).closest('.main-nav').length) subSet(false); });
  $(document).on('keydown', e => { if (e.key === 'Escape') subSet(false); });
  $('.has-sub').on('mouseenter', () => canHover() && subSet(true)); $('.main-nav').on('mouseleave', () => canHover() && subSet(false));

  // ── ক্যারোসেল
  $(function () {
    $('.hero-owl').owlCarousel({ items: 1, loop: $('.hero-owl .hero-slide').length > 1, autoplay: true, autoplayTimeout: 5000, autoplayHoverPause: true, dots: true, nav: false });
    $('.owl-dot').each(function (i) { if (!this.getAttribute('aria-label')) this.setAttribute('aria-label', 'স্লাইড ' + (i + 1)); });
  });

  // ── উইশলিস্ট
  const wish = () => store.get('hc_wish', []);
  function paintWish() {
    const w = wish(); $('.wish-count').text(w.length);
    $('.wish').each(function () { $(this).toggleClass('on', w.includes(+$(this).data('id'))); });
  }
  $(document).on('click', '.wish', function (e) {
    e.preventDefault(); const id = +$(this).data('id'); let w = wish();
    if (w.includes(id)) { w = w.filter(x => x !== id); toast(HCT.i18n.wishDel); } else { w.push(id); toast(HCT.i18n.wishAdd); }
    store.set('hc_wish', w); paintWish(); loadWish();
  });
  function loadWish() {
    const ids = wish(); const $b = $('#miniWishBody, #wishPage');
    if (!$b.length) return;
    if (!ids.length) { $b.html('<p class="text-muted">উইশলিস্ট ফাঁকা। কার্ডের ♥ বাটনে ক্লিক করুন।</p>'); return; }
    post('hc_cards', { ids }).done(r => { if (r.success) { $b.html(r.data); paintWish(); paintCmp(); } });
  }
  $('#miniWish').on('show.bs.offcanvas', loadWish);
  $(function () { paintWish(); if ($('#wishPage').length) loadWish(); });

  // ── তুলনা (সর্বোচ্চ ৩)
  const cmp = () => store.get('hc_cmp', []);
  function paintCmp() {
    const c = cmp(); $('#cmpN').text(c.length); $('#cmpBar').toggleClass('show', c.length > 0);
    $('.cmp-btn').each(function () { $(this).toggleClass('on', c.includes(+$(this).data('id'))); });
    if (HCT.cmpUrl) $('#cmpGo').attr('href', HCT.cmpUrl);
  }
  $(document).on('click', '.cmp-btn', function () {
    const id = +$(this).data('id'); let c = cmp();
    if (c.includes(id)) c = c.filter(x => x !== id); else { if (c.length >= 3) { toast(HCT.i18n.cmpMax); return; } c.push(id); toast(HCT.i18n.cmpAdd); }
    store.set('hc_cmp', c); paintCmp();
  });
  $(document).on('click', '#cmpClear', () => { store.set('hc_cmp', []); paintCmp(); });
  $(document).on('click', '.cmp-remove', function () { store.set('hc_cmp', cmp().filter(x => x !== +$(this).data('id'))); paintCmp(); loadCmp(); });
  function loadCmp() { if (!$('#cmpPage').length) return; post('hc_compare', { ids: cmp() }).done(r => r.success && $('#cmpPage').html(r.data)); }
  $(function () { paintCmp(); loadCmp(); });

  // ── কুইক ভিউ
  $(document).on('click', '.qv-btn', function () {
    const id = +$(this).data('id'); const $b = $('#qvBody').html('<div class="text-center py-5"><div class="spinner-border text-danger" role="status"></div></div>');
    bootstrap.Modal.getOrCreateInstance('#qvModal').show();
    post('hc_quickview', { id }).done(r => $b.html(r.success ? r.data : '<p>লোড করা যায়নি।</p>')).fail(() => $b.html('<p>লোড করা যায়নি।</p>'));
  });

  // ── AJAX অ্যাড-টু-কার্ট ফিডব্যাক (WooCommerce ইভেন্ট)
  $(document.body).on('added_to_cart', function (e, fragments, hash, $btn) {
    toast(HCT.i18n.added);
    if ($btn && $btn.data('hc-close')) { const m = bootstrap.Modal.getInstance('#qvModal'); m && m.hide(); }
    $btn && $btn.removeClass('added').next('.added_to_cart').remove();
  });

  // ── সার্চ সাজেশন
  let tmr;
  $(document).on('input', '.search-box input[name=s]', function () {
    const $in = $(this), $box = $in.closest('.search-box'), q = $.trim($in.val());
    clearTimeout(tmr); $box.find('.sugg').remove(); if (q.length < 2) return;
    tmr = setTimeout(() => $.getJSON(HCT.rest + 'suggest', { q }).done(list => {
      $box.find('.sugg').remove();
      const rows = list.map(p => `<a class="sugg-item" href="${p.url}"><img src="${p.img}" alt=""><span class="sg-name"></span><b class="sg-price"></b></a>`);
      const $s = $('<div class="sugg" role="listbox"></div>');
      if (!list.length) $s.append($('<div class="sugg-empty"></div>').text(HCT.i18n.noresult));
      list.forEach((p, i) => { const $a = $(rows[i]); $a.find('.sg-name').text(p.name); $a.find('.sg-price').text(p.price); $s.append($a); });
      $box.css('position', 'relative').append($s);
    }), 220);
  });
  $(document).on('click', e => { if (!$(e.target).closest('.search-box').length) $('.sugg').remove(); });

  // ── শপ ফিল্টার: ডুয়াল রেঞ্জ ↔ নম্বর ইনপুট
  $(function () {
    const $r = $('#fPriceRange'); if (!$r.length) return;
    const MIN = +$r.data('min'), MAX = +$r.data('max'), span = Math.max(1, MAX - MIN);
    const $a = $('#fMinR'), $b = $('#fMaxR'), $n1 = $('#fMin'), $n2 = $('#fMax');
    const paint = () => { let lo = +$a.val(), hi = +$b.val(); if (lo > hi) [lo, hi] = [hi, lo];
      $r.find('.r2-fill').css({ left: ((lo - MIN) / span * 100) + '%', right: (100 - (hi - MIN) / span * 100) + '%' });
      $('#fPriceLbl').text('৳' + lo + ' – ৳' + hi); };
    $a.add($b).on('input', () => { let lo = Math.min(+$a.val(), +$b.val()), hi = Math.max(+$a.val(), +$b.val()); $n1.val(lo); $n2.val(hi); paint(); });
    $n1.add($n2).on('input', () => { $a.val($n1.val()); $b.val($n2.val()); paint(); });
    paint();
    // দাম ডিফল্ট রেঞ্জে থাকলে URL পরিষ্কার রাখতে নাম বাদ
    $('#hcFilter').on('submit', function () {
      if (+$n1.val() <= MIN) $n1.prop('disabled', true); if (+$n2.val() >= MAX) $n2.prop('disabled', true);
      if (!$('#fRate').val()) $('#fRate').prop('disabled', true);
    });
  });
  $(document).on('change', '.woocommerce-ordering select', function () { this.form.submit(); });

  // ── FAQ সার্চ
  $(document).on('input', '#hcFaqSearch', function () {
    const q = $.trim(this.value).toLowerCase(); let n = 0;
    $('#hcFaq .accordion-item').each(function () { const hit = !q || ($(this).data('q') || '').indexOf(q) > -1; $(this).toggle(hit); if (hit) n++; });
    $('#hcFaqNone').toggleClass('d-none', n > 0);
  });

})(jQuery);
