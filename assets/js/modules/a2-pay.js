/* a2 — চেকআউট পেমেন্ট UI (app.js payUi/slip আচরণ) + ডেমো গেটওয়ে পেজ */
jQuery(function ($) {
  if (!window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  let slipTok = '', slipPrev = null;   // WC payment ফিল্ড রি-রেন্ডার হলেও স্লিপ হারায় না

  function restore() {
    const $x = $('[data-pay-extra]'); if (!$x.length) return;
    if (slipTok) { $x.find('[name^=hc_slip_token]').val(slipTok); if (slipPrev) $x.find('[data-slip-prev]').html(slipPrev); }
    syncAdv();
  }
  function syncAdv() {
    const $j = $('[data-adv-nums]'); if (!$j.length) return;
    let d; try { d = JSON.parse($j.text()); } catch (e) { return; }
    const m = $('[name=hc_advm]:checked').val() || 'বিকাশ';
    $('[data-pay-hint]').html(`<b>${m}</b> পার্সোনাল নাম্বার <b class="text-brand user-select-all">${$('<i>').text(d.nums[m] || '').html()}</b> এ <b>Send Money</b> করে নিচে TrxID ও স্লিপ দিন। অগ্রিম: <b>${d.adv}</b> · বাকি <b>${d.due}</b> ডেলিভারিতে`);
  }
  $(document.body).on('updated_checkout payment_method_selected', restore);
  $(document).on('change', '[name=hc_advm]', syncAdv);
  $(document).on('input', '[name^=hc_txn]', function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20); });

  $(document).on('change', '[data-hc-slip]', function () {
    const fl = this.files[0], $b = $(this).closest('[data-pay-extra]'), $m = $b.find('[data-slip-msg]').text(''), $p = $b.find('[data-slip-prev]').empty();
    slipTok = ''; slipPrev = null; $b.find('[name^=hc_slip_token]').val('');
    if (!fl) return;
    if (!((/^image\//.test(fl.type) || fl.type === 'application/pdf') && fl.size <= 5 * 1048576)) { $m.text('স্লিপের ছবি বা PDF আপলোড করুন (সর্বোচ্চ ৫MB)'); this.value = ''; return; }
    const $pv = /^image\//.test(fl.type) ? $('<img class="rounded-3 border" style="max-height:120px" alt="পেমেন্ট স্লিপের প্রিভিউ">').attr('src', URL.createObjectURL(fl)) : $('<span class="badge bg-secondary"></span>').text(fl.name);
    $p.append($pv);
    const fd = new FormData(); fd.append('action', 'hc_slip'); fd.append('nonce', HCT.nonce); fd.append('slip', fl);
    $m.text('আপলোড হচ্ছে…').removeClass('text-danger').addClass('text-muted');
    $.ajax({ url: HCT.ajax, method: 'POST', data: fd, processData: false, contentType: false }).done(r => {
      if (r.success) { slipTok = r.data.token; slipPrev = $p.html(); $b.find('[name^=hc_slip_token]').val(slipTok); $m.text('✓ আপলোড হয়েছে').removeClass('text-muted text-danger').addClass('text-success'); }
      else { $m.text((r.data && r.data.msg) || 'আপলোড হয়নি').removeClass('text-muted').addClass('text-danger'); $p.empty(); }
    }).fail(() => { $m.text('নেটওয়ার্ক সমস্যা').removeClass('text-muted').addClass('text-danger'); });
  });

  if (/[?&]payfail=1/.test(location.search)) toast('অনলাইন পেমেন্ট সম্পন্ন হয়নি। আবার চেষ্টা করুন বা অন্য মাধ্যম বাছুন।');

  const $pd = $('[data-paydemo]');
  if ($pd.length) {
    const go = res => $.post(HCT.ajax, { action: 'hc_paydemo', nonce: HCT.nonce, o: $pd.data('o'), key: $pd.data('key'), res, pgm: $('[name=pgm]:checked').val() }).done(r => { if (r.success) location.assign(r.data.url); else toast((r.data && r.data.msg) || 'ত্রুটি'); });
    $('#pgOk').on('click', function () { $(this).prop('disabled', true); go('ok'); });
    $('#pgFail').on('click', () => go('fail'));
  }
});
