/* c2 — কার্টে সেবা প্যানেল (services.js এর আচরণ; অবস্থা সার্ভার-সেশনে, মোট WC ফি দিয়ে) */
jQuery(function ($) {
  if (!window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const refresh = () => {
    const $b = $('[name=update_cart]');
    if ($b.length) { $b.prop('disabled', false).trigger('click'); } else { $(document.body).trigger('update_checkout'); location.reload(); }
  };
  const set = (k, v) => $.post(HCT.ajax, { action: 'hc_svc', nonce: HCT.nonce, k, v });
  $(document).on('change', '#svcInstall', function () { $('#svcSlotWrap').prop('hidden', !this.checked); set('install', this.checked ? 1 : 0).done(refresh); });
  $(document).on('change', '#svcSlot', function () { set('slot', this.value); });
  $(document).on('change', '.svc-w', function () { set('warranty', this.value).done(refresh); });
  $(document).on('change', '#svcPts', function () { set('pts', this.checked ? 1 : 0).done(refresh); });
  $(document).on('click', '#refApply', function () {
    set('ref', $('#refInput').val()).done(r => { if (r.success) { toast(r.data.msg || 'কোড যুক্ত হয়েছে ✓'); refresh(); } else $('#refMsg').text((r.data && r.data.msg) || 'ত্রুটি'); });
  });
  $(document).on('keydown', '#refInput', e => { if (e.key === 'Enter') { e.preventDefault(); $('#refApply').trigger('click'); } });
  $(document).on('click', '#refClear', () => set('ref', '').done(refresh));
  $(document).on('click', '#refShare', function (e) { e.preventDefault(); window.open('https://wa.me/?text=' + encodeURIComponent($(this).data('text')), '_blank', 'noopener'); });
});
