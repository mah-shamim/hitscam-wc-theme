/* লগইন / রেজিস্টার / OTP — টেমপ্লেটের login.html ইনলাইন স্ক্রিপ্টের পোর্ট (ডেমো লজিক বদলে আসল WP AJAX) */
jQuery(function ($) {
  const $card = $('[data-auth]'); if (!$card.length) return;
  const H = window.HCB, redirect = $card.data('redirect') || '';
  const PHONE = /^(?:\+?88)?01[3-9]\d{8}$/, MAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const flag = ($el, bad) => { $el.toggleClass('is-invalid', bad); return !bad; };
  const go = r => { H.toast(r.msg); setTimeout(() => { location.href = r.redirect || '/'; }, 700); };
  const fail = x => H.toast((x && x.responseJSON && x.responseJSON.data && x.responseJSON.data.msg) || 'কিছু একটা সমস্যা হয়েছে');
  const call = (action, data, $btn) => {
    if ($btn) $btn.prop('disabled', true);
    return H.post(action, Object.assign({ redirect }, data)).always(() => { if ($btn) $btn.prop('disabled', false); });
  };

  $('.pw-toggle').on('click', function () {
    const $i = $(this).siblings('input'), show = $i.attr('type') === 'password';
    $i.attr('type', show ? 'text' : 'password'); $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
  });
  $('.auth-tabs button').on('shown.bs.tab', function () {
    $('#authTitle').text($(this).data('bs-target') === '#tabOtp' ? 'মোবাইল নাম্বার দিয়ে লগইন' : $(this).data('bs-target') === '#tabRegister' ? 'নতুন অ্যাকাউন্ট তৈরি করুন' : 'আপনার অ্যাকাউন্টে লগইন করুন');
  });
  if (location.hash === '#register') bootstrap.Tab.getOrCreateInstance($('.auth-tabs button').eq(1)[0]).show();

  $('#loginForm').on('submit', function (e) {
    e.preventDefault(); const f = this, id = $.trim(f.id.value);
    let ok = flag($(f.id), !(PHONE.test(H.digits(id).replace(/[\s-]/g, '')) || MAIL.test(id)));
    ok = flag($(f.pass), f.pass.value.length < 6) && ok;
    if (!ok) { H.toast('তথ্য ঠিক করুন (পাসওয়ার্ড কমপক্ষে ৬ অক্ষর)'); return; }
    call('hc_login', { id, pass: f.pass.value, remember: $('#remember').is(':checked') ? 1 : '' }, $(f).find('button.btn-brand'))
      .done(r => r.success ? go(r.data) : H.toast(r.data.msg)).fail(fail);
  });
  $('#registerForm').on('submit', function (e) {
    e.preventDefault(); const f = this;
    let ok = flag($(f.name), !$.trim(f.name.value));
    ok = flag($(f.phone), !PHONE.test(H.digits(f.phone.value).replace(/[\s-]/g, ''))) && ok;
    ok = flag($(f.pass), f.pass.value.length < 6) && ok;
    if (f.email.value) ok = flag($(f.email), !MAIL.test(f.email.value)) && ok;
    if (!f.agree.checked) { ok = false; H.toast('শর্তাবলী মেনে নিন'); }
    if (!ok) return;
    call('hc_register', { name: $.trim(f.name.value), phone: f.phone.value, email: f.email.value, pass: f.pass.value }, $(f).find('button.btn-brand'))
      .done(r => r.success ? go(r.data) : H.toast(r.data.msg)).fail(fail);
  });

  // ---- OTP লগইন (সার্ভার-যাচাই; ৩ ভুলে লক, ৩০ সেকেন্ড পর পুনরায় পাঠানো) ----
  let otpPhone = '', otpTry = 0, otpTimer = null;
  $('#otpBox').html([0, 1, 2, 3, 4, 5].map(i => `<input inputmode="numeric" maxlength="1" autocomplete="${i ? 'off' : 'one-time-code'}" aria-label="অঙ্ক ${i + 1}">`).join(''));
  const otpVal = () => $('#otpBox input').map((_, e) => e.value).get().join('');
  function otpCountdown() { let n = 30; clearInterval(otpTimer); $('#otpResend').prop('disabled', true).html('আবার পাঠান (<span id="otpLeft">30</span>)'); otpTimer = setInterval(() => { n--; $('#otpLeft').text(n); if (n <= 0) { clearInterval(otpTimer); $('#otpResend').prop('disabled', false).html('আবার পাঠান'); } }, 1000); }
  function sendOtp(cb) {
    call('hc_otp_send', { phone: otpPhone }).done(r => {
      if (!r.success) { H.toast(r.data.msg); return; }
      H.toast(r.data.msg); otpTry = 0; $('#otpVerify').prop('disabled', false); $('#otpErr').hide();
      $('#otpDebug').toggle(!!r.data.debug_code).html(r.data.debug_code ? 'ডিবাগ OTP (শুধু WP_DEBUG মোডে): <b>' + r.data.debug_code + '</b>' : '');
      if (cb) cb(); otpCountdown();
    }).fail(fail);
  }
  $('#otpPhoneForm').on('submit', function (e) {
    e.preventDefault(); const ph = H.digits(this.phone.value).replace(/[\s-]/g, '');
    if (!flag($(this.phone), !PHONE.test(ph))) return;
    otpPhone = ph.replace(/^\+?88/, '');
    sendOtp(() => { $(this).hide(); $('#otpTo').text(otpPhone); $('#otpCodeForm').show(); $('#otpBox input').val('').first().focus(); });
  });
  $('#otpBox').on('input', 'input', function () { this.value = H.digits(this.value).replace(/\D/g, '').slice(0, 1); if (this.value) $(this).next().focus(); })
    .on('keydown', 'input', function (e) { if (e.key === 'Backspace' && !this.value) $(this).prev().focus(); })
    .on('paste', 'input', function (e) { const t = H.digits((e.originalEvent.clipboardData || window.clipboardData).getData('text')).replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); $('#otpBox input').each((i, el) => el.value = t[i] || ''); });
  $('#otpCodeForm').on('submit', function (e) {
    e.preventDefault();
    if (otpTry >= 3) { H.toast('অনেকবার ভুল। নতুন OTP নিন।'); return; }
    call('hc_otp_verify', { phone: otpPhone, code: otpVal() }, $('#otpVerify')).done(r => {
      if (r.success) { go(r.data); return; }
      otpTry++; $('#otpErr').text(r.data.msg).show(); $('#otpBox input').val('').first().focus();
      if (r.data.lock || otpTry >= 3) $('#otpVerify').prop('disabled', true);
    }).fail(fail);
  });
  $('#otpResend').on('click', function () { sendOtp(() => $('#otpBox input').val('').first().focus()); });
  $('#otpChange').on('click', function () { clearInterval(otpTimer); $('#otpCodeForm').hide(); $('#otpPhoneForm').show(); });
  if (location.hash === '#otp') bootstrap.Tab.getOrCreateInstance($('#otpTabBtn')[0]).show();

  $('#forgot').on('click', function (e) {
    e.preventDefault(); const id = $.trim($('#loginForm [name=id]').val());
    if (!id) { flag($('#loginForm [name=id]'), true); H.toast('আগে ফোন বা ই-মেইল লিখুন'); return; }
    call('hc_forgot', { id }).done(r => H.toast(r.data.msg)).fail(fail);
  });
});
