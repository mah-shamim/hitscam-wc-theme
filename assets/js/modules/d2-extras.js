/* d2 — PDP: ভিডিও/৩৬০°, পালস, coview; সার্চ: জনপ্রিয় সার্চ; কম্পেয়ার: সম্প্রতি তুলনা; শপ: সেভড ফিল্টার (support.js / product.html / shop.js আচরণ) */
jQuery(function ($) {
  if (!window.HCT) return;
  const S = { get(k, d) { try { const v = JSON.parse(localStorage.getItem(k)); return v === null ? d : v; } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };
  const esc = s => $('<i>').text(s == null ? '' : s).html();
  const D = window.HCB_PDP;

  /* ---- PDP গ্যালারি: ভিডিও + ৩৬০° ---- */
  if (D && $('#zoomBox').length) {
    const $z = $('#zoomBox'), $t = $('#thumbs'), extra = [];
    if (D.video) extra.push({ t: 'video', id: D.video });
    if (D.spin && D.spin.length) extra.push({ t: 'spin', f: D.spin });
    const add = html => { if ($t.hasClass('owl-loaded')) $t.trigger('add.owl.carousel', [$(html)]).trigger('refresh.owl.carousel'); else $t.append(html); };
    extra.forEach((it, i) => add(`<div class="thumb thumb-${it.t}" data-x="${i}" role="button" aria-label="${it.t === 'video' ? 'ভিডিও' : '৩৬০° ভিউ'}"><i class="bi ${it.t === 'video' ? 'bi-play-circle-fill' : 'bi-arrow-repeat'}"></i><small>${it.t === 'video' ? 'ভিডিও' : '৩৬০°'}</small></div>`));
    let spinF = [];
    const spinSet = k => $('.spin-img').attr('src', spinF[k]);
    $(document).on('click', '.thumb[data-x]', function (e) {
      e.stopPropagation(); const it = extra[+$(this).data('x')];
      $('#thumbs .thumb').removeClass('active'); $(this).addClass('active'); $z.removeClass('zooming spin-on video-on').empty();
      if (it.t === 'video') $z.addClass('video-on').css('background-image', 'none').html('<iframe title="প্রোডাক্ট ভিডিও" src="https://www.youtube-nocookie.com/embed/' + esc(it.id) + '?rel=0" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe>');
      else { spinF = it.f; $z.addClass('spin-on').css('background-image', 'none').html('<img class="spin-img" alt="৩৬০° ভিউ" draggable="false" src="' + esc(spinF[0]) + '"><div class="spin-ui"><span><i class="bi bi-arrow-repeat"></i> টেনে ঘোরান</span><input type="range" class="spin-range" min="0" max="' + (spinF.length - 1) + '" value="0" aria-label="৩৬০° ঘোরান"></div>'); spinF.forEach(u => { new Image().src = u; }); }
    });
    $(document).on('click', '#thumbs .thumb:not([data-x])', function () { $z.removeClass('spin-on video-on').find('iframe,.spin-img,.spin-ui').remove(); });
    $(document).on('input', '.spin-range', function () { spinSet(+this.value); });
    let down = false, x0 = 0, k0 = 0;
    $(document).on('pointerdown', '.spin-img', e => { down = true; x0 = e.clientX; k0 = +$('.spin-range').val(); });
    $(document).on('pointermove', e => { if (!down) return; const n = spinF.length, w = $z.width() || 400; let k = (k0 + Math.round((e.clientX - x0) / (w / n))) % n; if (k < 0) k += n; $('.spin-range').val(k); spinSet(k); });
    $(document).on('pointerup pointercancel', () => { down = false; });

    /* পালস */
    if (D.views > 1 || D.last) {
      const t = (D.views > 1 ? 'আজ ' + String(D.views).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]) + ' জন দেখেছেন' : '') + (D.views > 1 && D.last ? ' · ' : '') + (D.last ? 'সর্বশেষ অর্ডার ' + D.last : '');
      $('.meta-list').first().append('<li id="pPulse" class="pulse-line"><i class="bi bi-eye"></i> ' + esc(t) + '</li>');
    }

    /* সহ-ভিউ: "অন্য ক্রেতারা এগুলোও দেখেছেন" */
    const id = +D.id;
    const rec = S.get('hc_recent_ids', []).filter(r => r !== id).slice(0, 3);
    if (id) { S.set('hc_recent_ids', [id].concat(S.get('hc_recent_ids', []).filter(r => r !== id)).slice(0, 8));
      $.post(HCT.ajax, { action: 'hc_coview', nonce: HCT.nonce, id, near: rec }).done(r => {
        if (r.success && r.data) { const $a = $('.related.products, #related, .related-products').first(); const h = '<div class="section-head"><h2>অন্য ক্রেতারা এগুলোও দেখেছেন</h2></div>' + r.data; const $slot = $('.hc-coview-slot').first(); if ($slot.length) { $slot.html('<div class="section-head"><h2>' + esc($slot.data('title') || 'অন্য ক্রেতারা এগুলোও দেখেছেন') + '</h2></div>' + r.data); return; } if ($a.length) $a.before(h); else $('.pi-scroll, .single-product').last().after(h); }
      }); }
  }

  /* ---- জনপ্রিয় সার্চ (ফোকাসে, ফাঁকা ইনপুটে) + সার্চ রেকর্ড ---- */
  const POP0 = ['সোলার ক্যামেরা', 'মেমোরি কার্ড', 'WiFi ক্যামেরা', 'DVR', 'মিনি UPS'];
  const pop = () => { const c = S.get('hc_searches', {}); const top = Object.keys(c).sort((a, b) => c[b] - c[a]).slice(0, 5); return top.concat(POP0.filter(x => top.indexOf(x) < 0)).slice(0, 5); };
  $(document).on('submit', '.search-box', function () { const q = $.trim($(this).find('input[name=s]').val()); if (q.length < 2) return; const c = S.get('hc_searches', {}); c[q] = (c[q] || 0) + 1; S.set('hc_searches', c); });
  $(document).on('focus', '.search-box input[name=s]', function () {
    const $b = $(this).closest('.search-box'); if ($.trim(this.value) || $b.find('.sugg').length) return;
    $b.css('position', 'relative').append('<div class="sugg" role="listbox"><div class="sugg-pop"><div class="small text-muted mb-1">জনপ্রিয় সার্চ</div>' + pop().map(x => `<a class="chip-link" href="${esc(HCT.shop)}${HCT.shop.indexOf('?') > -1 ? '&' : '?'}s=${encodeURIComponent(x)}&post_type=product">${esc(x)}</a>`).join('') + '</div></div>');
  });

  /* ---- কম্পেয়ার: সম্প্রতি তুলনা ---- */
  const cmpNow = () => { try { return JSON.parse(localStorage.getItem('hc_cmp') || '[]'); } catch (e) { return []; } };
  if (cmpNow().length) { const h = S.get('hc_cmp_hist', []); S.set('hc_cmp_hist', cmpNow().concat(h.filter(i => cmpNow().indexOf(i) < 0)).slice(0, 8)); }
  $(document).on('hc:cmpchange cmpchange', () => { const c = cmpNow(), h = S.get('hc_cmp_hist', []); if (c.length) S.set('hc_cmp_hist', c.concat(h.filter(i => c.indexOf(i) < 0)).slice(0, 8)); });
  const $cp = $('.compare-page, #cmpTable, [data-compare]').first();
  if ($cp.length && HCT.cmpUrl) {
    const cur = cmpNow(), l = S.get('hc_cmp_hist', []).filter(i => cur.indexOf(i) < 0).slice(0, 6);
    if (l.length) $.post(HCT.ajax, { action: 'hc_cards', nonce: HCT.nonce, ids: l.slice(0, 3) }).done(r => { if (r.success && r.data) $cp.after('<section class="mt-4" id="cmpHist"><h2 class="h6 fw-bold mb-2"><i class="bi bi-clock-history text-brand"></i> সম্প্রতি তুলনা করা পণ্য</h2>' + r.data + '</section>'); });
  }

  /* ---- সেভড ফিল্টার (শপ) ---- */
  const $f = $('.shop-filters, form.shop-filter, #shopFilters').first();
  if (document.body.classList.contains('post-type-archive-product') || document.body.classList.contains('tax-product_cat')) {
    const KEY = 'hc_filters_' + (location.pathname.replace(/\/$/, '').split('/').pop() || 'shop'), keep = ['min_price', 'max_price', 'instock', 'rating_filter', 'orderby'];
    const qs = new URLSearchParams(location.search), has = keep.some(k => qs.has(k));
    if (has) { const o = {}; keep.forEach(k => { if (qs.get(k)) o[k] = qs.get(k); }); S.set(KEY, o); }
    else { const o = S.get(KEY, null); if (o && Object.keys(o).length && !qs.has('s') && !qs.has('paged') && !/\/page\//.test(location.pathname)) {
      $('main').first().prepend('<div class="alert alert-light border small d-flex justify-content-between align-items-center gap-2" id="savedFilt"><span><i class="bi bi-funnel text-brand"></i> আপনার আগের ফিল্টার সংরক্ষিত আছে</span><span><a class="btn btn-sm btn-brand rounded-pill" href="?' + $.param(o) + '">প্রয়োগ করুন</a> <button type="button" class="btn btn-sm btn-soft rounded-pill" id="savedFiltX">মুছুন</button></span></div>');
      $(document).on('click', '#savedFiltX', () => { S.set(KEY, null); $('#savedFilt').remove(); }); } }
  }
});
