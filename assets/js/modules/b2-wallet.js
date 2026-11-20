/* b2 — কুপন ওয়ালেট + কর্মঘণ্টা ব্যাজ (extras.js আচরণ) */
jQuery(function ($) {
  if (!window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const refresh = () => { const $b = $('[name=update_cart]'); if ($b.length) $b.prop('disabled', false).trigger('click'); else if ($('form.checkout').length) { $(document.body).trigger('update_checkout'); location.reload(); } else location.reload(); };
  $(document).on('click', '.wal-use', function () {
    const on = +$(this).data('on'), code = $(this).data('code');
    $.post(HCT.ajax, { action: 'hc_wallet', nonce: HCT.nonce, code, do: on ? 'remove' : 'use' }).done(r => { if (r.success) { if (r.data && r.data.msg) toast(r.data.msg); refresh(); } else toast((r.data && r.data.msg) || 'ত্রুটি'); });
  });
  $(document).on('change', '#walAuto', function () { $.post(HCT.ajax, { action: 'hc_wallet', nonce: HCT.nonce, do: 'auto', v: this.checked ? 1 : 0 }).done(refresh); });

  /* কর্মঘণ্টা */
  const H = window.HCB_HOURS; if (!H) return;
  function open(now) {
    let h; try { h = +new Intl.DateTimeFormat('en-GB', { timeZone: H.tz, hour: 'numeric', hourCycle: 'h23' }).format(now || new Date()); } catch (e) { h = new Date().getHours(); }
    return h >= H.open && h < H.close;
  }
  const bn = n => String(n).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]);
  const t12 = h => (h % 12 || 12) + (h < 12 ? ' AM' : ' PM');
  function ui() {
    const on = open(), sub = (H.open < 12 ? 'সকাল ' : 'দুপুর ') + bn(H.open % 12 || 12) + 'টা – ' + (H.close >= 18 ? 'রাত ' : 'বিকাল ') + bn(H.close % 12 || 12) + 'টা';
    if (!$('.hrs-badge').length) $('.topbar .container').first().find('> span').first().after('<span class="hrs-badge" role="status"></span>');
    $('.hrs-badge').toggleClass('on', on).html('<i class="hrs-dot"></i> ' + (on ? 'এখন অনলাইনে' : 'এখন অফলাইন') + ' <small>(' + sub + ')</small>');
    $('#waChat').toggleClass('is-online', on).attr('title', on ? 'আমরা এখন অনলাইনে — মেসেজ করুন' : 'এখন অফলাইন — মেসেজ রাখুন, ' + bn(H.open) + 'টায় উত্তর দেব');
  }
  ui(); setInterval(ui, 60000);
});
