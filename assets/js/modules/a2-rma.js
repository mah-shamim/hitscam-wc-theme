/* a2 — RMA ফর্ম (support.js এর RMA অংশের আচরণ; ডেটা সার্ভারে) */
jQuery(function ($) {
  const $pg = $('[data-rma-page]'); if (!$pg.length || !window.HCT) return;
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const digits = t => String(t == null ? '' : t).replace(/[০-৯]/g, d => '০১২৩৪৫৬৭৮৯'.indexOf(d));
  const orders = $pg.data('orders') || [], pre = String($pg.data('pre') || '');
  const $o = $('#rmaOrder'), $p = $('#rmaProd'), $ph = $('#rmaPhone');
  if ($o.is('select')) {
    $o.html('<option value="">— অর্ডার বেছে নিন —</option>' + orders.map(o => `<option value="${o.id}" ${String(o.id) === pre ? 'selected' : ''}>${$('<i>').text(o.label).html()}</option>`).join('') + '<option value="">অন্য / পুরোনো অর্ডার</option>');
    const fill = () => { const o = orders.find(x => String(x.id) === String($o.val())); $p.html((o ? o.items : ['—']).map(n => `<option>${$('<i>').text(n).html()}</option>`).join('')); if (o && o.phone && !$ph.val()) $ph.val(o.phone); };
    $o.on('change', fill); fill();
  }
  $('#rmaPhotos').on('change', function () {
    $('#rmaThumbs').empty(); $('#rmaMsg').text('');
    [].slice.call(this.files, 0, 3).forEach(f => {
      if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size > 3 * 1048576) { $('#rmaMsg').text('শুধু JPG/PNG/WebP, সর্বোচ্চ ৩MB'); return; }
      $('#rmaThumbs').append($('<img>', { src: URL.createObjectURL(f), alt: 'আপলোড করা ছবি', width: 64, height: 64, 'class': 'rounded-3 me-1' }));
    });
  });
  $('#rmaForm').on('submit', function (e) {
    e.preventDefault(); const f = this.elements; let ok = true;
    const phv = digits(f.phone.value).replace(/[\s-]/g, '').replace(/^\+?88/, '');
    [f.prod, f.desc].forEach(el => { const bad = !$.trim(el.value) || (el === f.desc && $.trim(el.value).length < 10); $(el).toggleClass('is-invalid', bad); if (bad) ok = false; });
    const badp = !/^01[3-9]\d{8}$/.test(phv); $(f.phone).toggleClass('is-invalid', badp); if (badp) ok = false;
    if (!ok) { $('#rmaMsg').text('লাল ঘরগুলো ঠিক করুন'); return; }
    const fd = new FormData(this); fd.set('phone', phv); fd.set('action', 'hc_rma_new'); fd.set('nonce', HCT.nonce);
    const $b = $(this).find('[type=submit]').prop('disabled', true);
    $.ajax({ url: HCT.ajax, method: 'POST', data: fd, processData: false, contentType: false }).done(r => {
      if (r.success) { $('#rmaList').find('.text-muted').first().filter(function () { return !$(this).find('.rma-card').length && !$(this).hasClass('rma-card'); }).remove(); $('#rmaList').prepend(r.data.html); this.reset(); $('#rmaThumbs, #rmaMsg').empty(); toast('অনুরোধ জমা হয়েছে ✓ — ' + r.data.code); if ($o.is('select')) $o.trigger('change'); }
      else $('#rmaMsg').text((r.data && r.data.msg) || 'ত্রুটি হয়েছে');
    }).fail(() => $('#rmaMsg').text('নেটওয়ার্ক সমস্যা, আবার চেষ্টা করুন')).always(() => $b.prop('disabled', false));
  });
  $('#rmaFind').on('submit', function (e) {
    e.preventDefault();
    $.post(HCT.ajax, { action: 'hc_rma_find', nonce: HCT.nonce, rid: this.elements.rid.value, rphone: digits(this.elements.rphone.value) }).done(r => {
      $('#rmaFound').html(r.success ? r.data.html : '<div class="text-danger small">' + $('<i>').text((r.data && r.data.msg) || 'পাওয়া যায়নি').html() + '</div>');
    });
  });
});
