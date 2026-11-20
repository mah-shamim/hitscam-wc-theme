/* e4 — অর্ডার-রিসিভড পেজ: প্রিন্ট, আবার অর্ডার, রিকনফার্ম (SMS/চ্যানেল), অ্যাড-অন, নোটিফিকেশন চ্যানেল, কপি-বাটন */
jQuery(function ($) {
  const toast = (m) => (window.HCT_toast ? HCT_toast(m) : (window.HCB && HCB.toast ? HCB.toast(m) : null));
  const $or = $('.order-received[data-key]');
  const clock = () => new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  const post = (action, extra) => $.post(HCT.ajax, Object.assign({ action, nonce: HCT.nonce, order: $or.data('order'), key: $or.data('key') }, extra || {}));

  $(document).on('click', '#printBtn', () => window.print());
  /* চ্যানেল লিংকে কপি-টেক্সট (যেমন IMO — প্রি-ফিল সাপোর্ট নেই) */
  $(document).on('click', '.hc-chan[data-copy]', function () {
    const t = $(this).data('copy');
    try { navigator.clipboard.writeText(t); toast('মেসেজ কপি হয়েছে — অ্যাপে পেস্ট করে পাঠান'); } catch (e) {}
  });
  if (!$or.length) return;

  const bubble = (txt) => $('#smsThread').append($('<div class="sms-bubble"></div>').append($('<div></div>').text(txt), $('<div class="sms-meta"></div>').text('প্রাপক: ' + $or.data('phone') + ' · ' + clock())));
  const isSms = +$or.data('sms') === 1;
  bubble($or.data('msg'));
  const markDone = () => {
    $('#statusPill').text('Confirmed'); $('#alertTitle').text('অর্ডার রিকনফার্ম হয়েছে ✓');
    $('.timeline li').eq(1).removeClass('current').addClass('done').find('.tl-dot').html('<i class="bi bi-check-lg"></i>');
    $('.timeline li').eq(2).addClass('current');
    $('#reconfirmBtn').prop('disabled', true).removeClass('btn-brand').addClass('btn-success').html('<i class="bi bi-check-circle-fill"></i> রিকনফার্ম করা হয়েছে');
  };
  if (+$or.data('reconf')) { bubble($or.data('msgdone')); markDone(); }
  $('#reconfirmBtn').on('click', function () {
    if ($(this).prop('disabled')) return;
    post('hc_reconfirm').done(r => {
      if (r.success) { markDone(); if (isSms) bubble($or.data('msgdone')); toast('অর্ডার রিকনফার্ম হয়েছে ✓' + (isSms ? ' — SMS পাঠানো হয়েছে' : '')); }
      else toast((r.data && r.data.msg) || 'আবার চেষ্টা করুন');
    });
  });
  $('#resendSms').on('click', function () {
    const $b = $(this).prop('disabled', true);
    post('hc_resend_sms').done(r => { if (r.success) { bubble($or.data('msg')); toast('আপনার মোবাইলে আবার SMS পাঠানো হয়েছে'); } else toast((r.data && r.data.msg) || 'একটু পরে চেষ্টা করুন'); });
    setTimeout(() => $b.prop('disabled', false), 15000);
  });
  $('#reorderBtn').on('click', function () {
    post('hc_reorder').done(r => { if (r.success) location.href = r.data.redirect; else toast('আবার অর্ডার করা যায়নি'); });
  });
  $('#addonBtn').on('click', function () {
    const $b = $(this).prop('disabled', true);
    post('hc_addon', { pid: $b.data('pid') }).done(r => {
      if (r.success) { toast(r.data.msg); setTimeout(() => location.reload(), 700); } else { toast((r.data && r.data.msg) || 'যোগ করা যায়নি'); $b.prop('disabled', false); }
    });
  });
  $(document).on('click', '.hc-notify-ch', function () {
    const $b = $(this), href = $b.data('href');
    post('hc_notify_ch', { ch: $b.data('ch') }).done(() => {
      $('.hc-notify-ch').removeClass('btn-brand').addClass('btn-soft'); $b.removeClass('btn-soft').addClass('btn-brand');
      $('#notifTxt').text($b.text().trim() + '-এ আপডেট পাবেন ✓ — চ্যাট খুলে প্রথম মেসেজটি পাঠিয়ে দিন');
      if (href) window.open(href, '_blank', 'noopener');
    });
  });
});
