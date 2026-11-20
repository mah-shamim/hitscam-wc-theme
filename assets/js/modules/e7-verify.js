/* e7 — চেকআউটে ফোন ভেরিফাই: SMS কোড (গেটওয়ে থাকলে) অথবা WhatsApp/IMO ইত্যাদি চ্যানেল */
jQuery(function ($) {
  const toast = (m) => (window.HCT_toast ? HCT_toast(m) : null);
  const mount = () => {
    if (window.HC_VERIFY === 'off') return;
    const $ph = $('#billing_phone'); if (!$ph.length || $('#hcVerify').length) return;
    $ph.closest('.form-row').after('<div id="hcVerify" class="mb-3 small"><button type="button" class="btn btn-soft btn-sm rounded-pill" id="hcVBtn"><i class="bi bi-shield-check"></i> ফোন ভেরিফাই করুন</button> <span id="hcVState" class="ms-1 text-muted"></span><div id="hcVBox" class="mt-2" style="display:none"></div></div>');
  };
  const phone = () => $.trim($('#billing_phone').val());
  const ok = (via) => { $('#hcVState').html('<span class="text-success"><i class="bi bi-check-circle-fill"></i> ভেরিফাইড' + (via ? ' (' + via + ')' : '') + '</span>'); $('#hcVBox').hide(); $('#hcVBtn').hide(); };
  $(document).on('click', '#hcVBtn', function () {
    $.post(HCT.ajax, { action: 'hc_vsend', nonce: HCT.nonce, phone: phone() }).done(r => {
      if (!r.success) { toast((r.data && r.data.msg) || 'নাম্বার ঠিক নেই'); return; }
      const d = r.data; let h = '';
      if (d.mode === 'sms') h += '<div class="mb-2">আপনার মোবাইলে ৬ সংখ্যার কোড পাঠানো হয়েছে।</div>';
      else h += '<div class="mb-2">নিচের অ্যাপে মেসেজটি পাঠান — আমাদের টিম কোড জানাবে, অথবা পাঠিয়েছেন জানিয়ে এগিয়ে যান।</div><div class="d-flex flex-wrap gap-2 mb-2">' + d.html + '</div>';
      h += '<div class="input-group input-group-sm" style="max-width:320px"><input class="form-control" id="hcVCode" inputmode="numeric" maxlength="6" placeholder="৬ সংখ্যার কোড"><button type="button" class="btn btn-brand" id="hcVCheck">যাচাই</button></div>';
      if (d.mode !== 'sms') h += '<button type="button" class="btn btn-link btn-sm px-0" id="hcVClaim">আমি মেসেজ পাঠিয়েছি</button>';
      $('#hcVBox').html(h).show();
    });
  });
  $(document).on('click', '#hcVCheck', function () {
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, phone: phone(), code: $('#hcVCode').val() }).done(r => { if (r.success) ok('কোড'); else toast((r.data && r.data.msg) || 'কোড মেলেনি'); });
  });
  $(document).on('click', '#hcVClaim', function () {
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, kind: 'claim', phone: phone() }).done(r => { if (r.success) ok('চ্যানেল'); else toast((r.data && r.data.msg) || 'এখন সম্ভব নয়'); });
  });
  $(document).on('input change', '#billing_phone', function () {
    $.post(HCT.ajax, { action: 'hc_vcheck', nonce: HCT.nonce, kind: 'status', phone: phone() }).done(r => { if (r.success && r.data.ok) ok(''); else { $('#hcVState').empty(); $('#hcVBtn').show(); } });
  });
  mount(); $(document.body).on('updated_checkout', mount);
});
