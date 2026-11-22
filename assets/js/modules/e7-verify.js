/* e7 — চেকআউটে "প্লেস অর্ডার" চাপলে ফোন-ভেরিফাই পপআপ: SMS/WhatsApp/IMO… বেছে OTP → যাচাই → অর্ডার সম্পন্ন */
jQuery(function ($) {
  const toast = (m) => (window.HCT_toast ? HCT_toast(m) : null);
  const mode = () => window.HC_VERIFY || 'required';
  const phone = () => $.trim($('#billing_phone').val());
  let okPhone = ''; let modal = null, busy = false;

  const build = () => {
    if (document.getElementById('vfModal')) return;
    $('body').append('<div class="modal fade" id="vfModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
      '<div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-shield-check text-brand"></i> ফোন নাম্বার ভেরিফাই</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button></div>' +
      '<div class="modal-body"><p class="mb-2">অর্ডার সম্পন্ন করতে <b id="vfPh"></b> নাম্বারটি যাচাই করুন। কোথায় কোড পেতে চান?</p><div class="btn-row mb-3" id="vfBtns"></div>' +
      '<div id="vfInfo" class="small text-muted mb-2"></div><div id="vfCodeBox" style="display:none"><div class="input-group"><input class="form-control form-control-lg text-center" id="vfCode" inputmode="numeric" maxlength="6" placeholder="৬ সংখ্যার OTP" autocomplete="one-time-code"><button type="button" class="btn btn-brand btn-lg" id="vfCheck">যাচাই করুন</button></div><div class="small text-danger mt-1" id="vfErr"></div></div>' +
      '<div id="vfManual" style="display:none" class="mt-2"></div></div></div></div></div>');
  };
  const open = () => {
    build(); $('#vfPh').text(phone()); $('#vfCodeBox,#vfManual').hide(); $('#vfInfo,#vfErr').text(''); $('#vfCode').val('');
    const chans = window.HC_VCH || [];
    $('#vfBtns').html(chans.map(c => '<button type="button" class="btn btn-soft btn-lg rounded-pill vf-ch" data-ch="' + c.key + '"><i class="bi ' + c.icon + '"></i> ' + c.label + '</button>').join(''));
    place();
    modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('vfModal')); modal.show();
    $('#vfModal').off('shown.bs.modal.hc').on('shown.bs.modal.hc', place);
  };
  /* ইন্সট্যান্ট অর্ডার মডালের iframe-এ: পপআপ যেন অভিভাবক মডালের এখন-দেখা অংশের ভেতরে আসে (স্ক্রল করা থাকলেও) */
  const place = () => {
    try {
      if (window.parent === window || !/hc_modal/.test(location.search)) return;
      const root = window.parent.document.getElementById('orderRoot'); if (!root) return;
      $('#vfModal .modal-dialog').removeClass('modal-dialog-centered').css('margin-top', (root.scrollTop + Math.max(16, root.clientHeight / 2 - 170)) + 'px');
    } catch (e) {}
  };

  /* প্লেস অর্ডার আটকানো: ভেরিফাইড না হলে পপআপ */
  $('form.checkout').on('checkout_place_order', function () {
    if (mode() === 'off' || !$('#billing_phone').length) return true;
    if (okPhone && okPhone === phone()) return true;
    if (!/^(?:\+?88)?01[3-9]\d{8}$/.test(phone().replace(/[\s-]/g, ''))) return true; // ফিল্ড ভুল হলে WooCommerce নিজেই এরর দেখাবে
    if (busy) return false; busy = true;
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, kind: 'status', phone: phone() }).done(r => {
      busy = false;
      if (r.success && r.data.ok) { okPhone = phone(); $('form.checkout').trigger('submit'); } else open();
    }).fail(() => { busy = false; open(); });
    return false;
  });
  $(document).on('input change', '#billing_phone', function () { if (okPhone !== phone()) okPhone = ''; });

  $(document).on('click', '.vf-ch', function () {
    const $b = $(this).prop('disabled', true), ch = $b.data('ch');
    $.post(HCT.ajax, { action: 'hc_vsend', nonce: HCT.nonce, phone: phone(), ch }).done(r => {
      $b.prop('disabled', false);
      if (!r.success) { $('#vfInfo').addClass('text-danger').text((r.data && r.data.msg) || 'পাঠানো যায়নি'); return; }
      const d = r.data; $('#vfInfo').removeClass('text-danger'); $('.vf-ch').removeClass('btn-brand').addClass('btn-soft'); $b.removeClass('btn-soft').addClass('btn-brand');
      $('#vfCodeBox').show(); setTimeout(() => $('#vfCode').trigger('focus'), 100);
      if (d.mode === 'sent') { $('#vfInfo').text('OTP পাঠানো হয়েছে (' + d.via + ')। নিচে কোডটি দিন।'); $('#vfManual').hide(); }
      else {
        $('#vfInfo').text(d.via + '-এ আমাদের নাম্বারে অনুরোধ পাঠান; টিম OTP জানাবে। কোড পেলে নিচে বসান।');
        $('#vfManual').html('<a class="btn btn-brand rounded-pill" target="_blank" rel="noopener" href="' + d.link + '"><i class="bi bi-box-arrow-up-right"></i> ' + d.via + ' খুলুন</a>' + (d.soft ? ' <button type="button" class="btn btn-link" id="vfClaim">আমি মেসেজ পাঠিয়েছি, এগিয়ে যান</button>' : '')).show();
      }
    }).fail(() => $b.prop('disabled', false));
  });
  const done = () => { okPhone = phone(); modal && modal.hide(); toast('ভেরিফাই সফল ✓ — অর্ডার সম্পন্ন হচ্ছে'); setTimeout(() => $('form.checkout').trigger('submit'), 350); };
  $(document).on('click', '#vfCheck', function () {
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, phone: phone(), code: $('#vfCode').val() }).done(r => { if (r.success) done(); else $('#vfErr').text((r.data && r.data.msg) || 'কোড মেলেনি'); });
  });
  $(document).on('keydown', '#vfCode', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#vfCheck').trigger('click'); } });
  $(document).on('click', '#vfClaim', function () {
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, kind: 'claim', phone: phone() }).done(r => { if (r.success) done(); else $('#vfErr').text((r.data && r.data.msg) || 'এখন সম্ভব নয়'); });
  });
});

/* সাবস্ক্রাইব ফর্ম (হোম) */
jQuery(function ($) {
  $(document).on('submit', '#nlForm', function (e) {
    e.preventDefault(); const $f = $(this), $m = $('#nlMsg');
    $.post(HCT.ajax, { action: 'hc_subscribe', nonce: HCT.nonce, contact: $f.find('[name=contact]').val(), website: $f.find('[name=website]').val() }).done(r => {
      if (r.success) { $m.removeClass('text-danger').addClass('text-success').text((r.data && r.data.msg) || 'ধন্যবাদ!'); $f[0].reset(); } else { $m.removeClass('text-success').addClass('text-danger').text((r.data && r.data.msg) || 'আবার চেষ্টা করুন'); }
    });
  });
});
