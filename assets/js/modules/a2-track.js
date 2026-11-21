/* a2 — অর্ডার ট্র্যাক পেজ + সেলফ-সার্ভিস (track-order.html ও support.js এর আচরণ) */
jQuery(function ($) {
  if (!window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const esc = s => $('<i>').text(s == null ? '' : s).html();
  const digits = t => String(t == null ? '' : t).replace(/[০-৯]/g, d => '০১২৩৪৫৬৭৮৯'.indexOf(d));
  const norm = x => digits(x).replace(/[\s-]/g, '').replace(/^\+?88/, '');
  const PHONE = /^01[3-9]\d{8}$/;
  const STEPS = [['📝', 'অর্ডার গ্রহণ', 'আপনার অর্ডার আমরা পেয়েছি'], ['📞', 'কনফার্মেশন কল', 'আমরা ফোনে কনফার্ম করব'], ['📦', 'প্যাকেজিং', 'প্রোডাক্ট প্যাক করা হবে'], ['🚚', 'কুরিয়ারে পাঠানো', 'আপনার ঠিকানায় পৌঁছাবে'], ['✅', 'ডেলিভারি সম্পন্ন', 'উপভোগ করুন!']];
  const REASONS = ['ভুল পণ্য বেছেছি', 'দাম/অন্য জায়গায় কম পেয়েছি', 'ডেলিভারি ঠিকানা ভুল', 'আর দরকার নেই', 'অন্য কারণ'];
  let ctx = null; // { oid/orderId, phone, key } — প্রতিটি সেলফ-সার্ভিস কলে আবার যাচাই হয়

  function manageHtml(o, districts) {
    if (o.cancelled) return `<div class="alert alert-secondary mb-0"><i class="bi bi-x-octagon-fill text-danger"></i> <b>এই অর্ডার বাতিল হয়েছে</b>${o.cancelReason ? ' — ' + esc(o.cancelReason) : ''}</div>`;
    let h = '';
    if (o.manage > 0) {
      h += `<div class="d-flex flex-wrap gap-2 align-items-center"><b class="me-auto"><i class="bi bi-sliders text-brand"></i> অর্ডার ম্যানেজ <small class="text-muted fw-normal">(আর ${o.manage} মিনিট)</small></b><button type="button" class="btn btn-soft btn-sm" id="ssEditBtn">ঠিকানা/ফোন বদলান</button><button type="button" class="btn btn-outline-danger btn-sm" id="ssCancelBtn">অর্ডার বাতিল</button></div>
      <form class="ss-form mt-2" id="ssCancel" hidden><label class="small text-muted" for="ssReason">বাতিলের কারণ</label><select class="form-select form-select-sm mb-2" id="ssReason">${REASONS.map(r => `<option>${r}</option>`).join('')}</select><button class="btn btn-danger btn-sm" type="submit">নিশ্চিত বাতিল করুন</button></form>
      <form class="ss-form mt-2" id="ssEdit" hidden novalidate><div class="row g-2"><div class="col-12"><input class="form-control form-control-sm" name="road" value="${esc(o.addr.road)}" placeholder="বাসা / রোড / এলাকা" aria-label="রোড"></div><div class="col-6"><input class="form-control form-control-sm" name="thana" value="${esc(o.addr.thana)}" placeholder="থানা / উপজেলা" aria-label="থানা"></div><div class="col-6"><select class="form-select form-select-sm" name="district" aria-label="জেলা">${Object.keys(districts || {}).map(k => `<option value="${esc(k)}" ${k === o.addr.state ? 'selected' : ''}>${esc(districts[k])}</option>`).join('')}</select></div><div class="col-12"><input class="form-control form-control-sm" name="phone" value="${esc(o.addr.phone)}" placeholder="01XXXXXXXXX" inputmode="tel" aria-label="ফোন"></div></div><div class="small text-danger mt-1" id="ssEditMsg"></div><button class="btn btn-brand btn-sm mt-2" type="submit">সংরক্ষণ করুন</button></form>`;
    } else h += `<div class="small text-muted"><i class="bi bi-clock-history"></i> বাতিল/সম্পাদনার সময় শেষ। প্রয়োজনে <a href="https://wa.me/${HCT.wa}?text=${encodeURIComponent('অর্ডার নিয়ে সাহায্য লাগবে')}" target="_blank" rel="noopener">WhatsApp এ জানান</a>।</div>`;
    h += `<div class="mt-2 small"><a href="${esc(o.rma)}"><i class="bi bi-arrow-return-left"></i> রিটার্ন / রিপেয়ার অনুরোধ</a> <span class="text-muted">(ডেলিভারির ৭ দিনের মধ্যে)</span></div>`;
    return h;
  }
  const call = (action, data) => $.post(HCT.ajax, Object.assign({ action, nonce: HCT.nonce }, ctx, data));

  $(document).on('click', '#ssCancelBtn', () => { $('#ssEdit').prop('hidden', true); $('#ssCancel').prop('hidden', false); });
  $(document).on('click', '#ssEditBtn', () => { $('#ssCancel').prop('hidden', true); $('#ssEdit').prop('hidden', false); });
  let districts = {};
  const afterChange = o => { drawMgr(o); if ($('#tId').length) render(o); else { $('#oAddr').text([o.addr.road, o.addr.thana].join(', ')); } };
  $(document).on('submit', '#ssCancel', function (e) {
    e.preventDefault();
    call('hc_ss_cancel', { reason: $('#ssReason').val() }).done(r => { if (r.success) { toast('অর্ডার বাতিল হয়েছে'); afterChange(r.data); if (!$('#tId').length) location.reload(); } else toast((r.data && r.data.msg) || 'ত্রুটি'); });
  });
  $(document).on('submit', '#ssEdit', function (e) {
    e.preventDefault(); const f = this.elements, ph = norm(f.phone.value);
    if (!$.trim(f.road.value) || !$.trim(f.thana.value)) { $('#ssEditMsg').text('রোড ও থানা লিখুন'); return; }
    if (!PHONE.test(ph)) { $('#ssEditMsg').text('সঠিক মোবাইল নাম্বার দিন'); return; }
    call('hc_ss_edit', { road: f.road.value, thana: f.thana.value, district: f.district.value, newphone: ph }).done(r => {
      if (r.success) { toast('ঠিকানা/ফোন হালনাগাদ হয়েছে ✓'); $('#ssEdit').prop('hidden', true); ctx.phone = ph; afterChange(r.data); $('#smsPhone').text(ph); } else $('#ssEditMsg').text((r.data && r.data.msg) || 'ত্রুটি');
    });
  });
  function drawMgr(o) { $('#selfServ').html(manageHtml(o, districts)).show(); }

  /* ---------- ট্র্যাক পেজ ---------- */
  function render(o) {
    $('#tId').text('#' + o.id); $('#tReorder').attr('data-id', o.oid); $('#tDate').text(o.date); $('#tTotal, #tTotal2').text(o.total); $('#tPay').text(o.pay);
    $('#tStatus').text(o.status); const cur = o.cur;
    $('#tCourier').toggle(!!o.courier);
    if (o.courier) { $('#tCName').text(o.courier.name); $('#tCCn').text(o.courier.cn); $('#tCLink').attr('href', o.courier.url); }
    $('#tLine').html(o.cancelled ? '<li class="current"><div class="tl-dot">✖</div><div><h6>অর্ডার বাতিল</h6><p>এই অর্ডারটি বাতিল হয়েছে</p></div></li>' : STEPS.map((s, i) => `<li class="${i < cur ? 'done' : i === cur ? 'current' : ''}"><div class="tl-dot">${i < cur && i > 0 ? '<i class="bi bi-check-lg"></i>' : s[0]}</div><div><h6>${s[1]}</h6><p>${s[2]}</p></div></li>`).join(''));
    $('#tCount').text(o.items.length);
    $('#tItems').html(o.items.map(i => `<div class="track-item">${i.img ? `<img src="${esc(i.img)}" alt="">` : ''}<div class="ti-name"><div class="fw-semibold">${esc(i.name)}</div><small class="text-muted">পরিমাণ: ${i.qty}</small></div><b class="ti-price">${esc(i.total)}</b></div>`).join(''));
    $('#tSub').text(o.sub); $('#tShip').text(o.ship);
    drawMgr(o);
    $('#trackResult').slideDown(200);
  }
  const $tw = $('[data-track]');
  if ($tw.length) {
    districts = $tw.data('districts') || {};
    $('#trackForm').on('submit', function (e) {
      e.preventDefault(); const f = this.elements, id = $.trim(f.orderId.value).replace('#', ''), ph = norm(f.phone.value);
      $(f.orderId).toggleClass('is-invalid', !id); $(f.phone).toggleClass('is-invalid', !PHONE.test(ph)); if (!id || !PHONE.test(ph)) return;
      ctx = { orderId: id, phone: ph };
      $.post(HCT.ajax, Object.assign({ action: 'hc_track', nonce: HCT.nonce }, ctx)).done(r => {
        if (r.success) { render(r.data); setTimeout(() => $('#trackResult')[0].scrollIntoView({ behavior: 'smooth', block: 'start' }), 250); }
        else { $('#trackResult').hide(); toast((r.data && r.data.msg) || 'অর্ডার পাওয়া যায়নি'); $(f.phone).addClass('is-invalid'); }
      });
    });
    if ($('#trackForm [name=orderId]').val()) { $('#trackForm [name=phone]').trigger('focus'); }
  }

  /* ---------- অর্ডার-রিসিভড পেজ ---------- */
  const $or = $('.order-received[data-key]');
  if ($or.length && $('#hcSelfMount').length) {
    districts = $('#hcSelfMount').data('districts') || {};
    ctx = { oid: $or.data('order'), key: $or.data('key') };
    if (+$or.data('mg') !== 0) {
    const $m = $('<div class="panel my-3" id="selfServ" style="display:none"></div>').insertBefore($or.find('.row.g-3.mb-4').first());
    call('hc_track', {}).done(r => { if (r.success) drawMgr(r.data); });
    }
    const pid = +$('#hcSelfMount').data('pid');   // পোস্ট-অর্ডার আপসেল: মানানসই পণ্য
    if (pid && +$or.data('cr') !== 0) $.post(HCT.ajax, { action: 'hc_coview', nonce: HCT.nonce, id: pid, near: [] }).done(r => { if (r.success && r.data && !$('#postUpsell').length) $or.find('.row.g-2.mb-5').first().before('<section class="panel mb-3 no-print" id="postUpsell"><h5 class="fw-bold mb-3"><i class="bi bi-plus-circle text-brand"></i> আপনার অর্ডারের সাথে মানানসই — মেমোরি কার্ড ও অ্যাক্সেসরিজ</h5>' + r.data + '</section>'); });
  }
});
