/* গ্রুপড প্রোডাক্ট: পরিমাণ স্টেপার, সিলেক্টেড টোটাল, একসাথে কার্টে/অর্ডার */
(function ($) {
  'use strict';
  const S = window.HCS; if (!S) return;
  const money = n => '৳' + Math.round(n).toLocaleString('en-US');
  function total($scope) { let t = 0, n = 0; $scope.find('.grp-row').each(function () { const q = +$(this).find('.g-q').text() || 0; t += q * (+$(this).data('price') || 0); n += q; }); return { t, n }; }
  function paint($scope) {
    const r = total($scope);
    $scope.find('.grp-sel').text(money(r.t));
    if ($scope.is('[data-pdp]') || $scope.closest('[data-pdp]').length) { $('#grpSel').text(money(r.t)); $('#bbPrice').text(money(r.t)); }
  }
  $(document).on('click', '.grp-table .g-d', function (e) {
    e.preventDefault();
    const $row = $(this).closest('.grp-row'), $q = $row.find('.g-q'), max = +$row.data('max') || 99;
    $q.text(Math.min(max, Math.max(0, (+$q.text() || 0) + (+$(this).data('d')))));
    const $s = $(this).closest('.product-card, [data-pdp]'); paint($s);
  });
  $(function () { $('.product-card, [data-pdp]').each(function () { if ($(this).find('.grp-table').length) paint($(this)); }); });

  function items($scope) {
    return $scope.find('.grp-row').map(function () { const q = +$(this).find('.g-q').text() || 0; return q > 0 ? { id: +$(this).data('id'), child: +$(this).data('child'), qty: q } : null; }).get();
  }
  function submit($scope, buyNow, $btn) {
    const it = items($scope); if (!it.length) { S.toast('কমপক্ষে একটি আইটেম বেছে নিন'); return; }
    $btn.prop('disabled', true);
    S.post('hc_add_group', { items: JSON.stringify(it), buy_now: buyNow ? 1 : 0 }).done(r => {
      if (r.success) {
        try { S.applyFragments(r.data); $(document.body).trigger('added_to_cart', [r.data.fragments, r.data.cart_hash, null]); } catch (e) {}
        if (buyNow) { (window.HCOrder ? HCOrder.open() : location.assign(r.data.redirect || window.HCT.checkout || '/checkout/')); return; }
        S.toast('কার্টে যোগ হয়েছে ✓');
      } else S.toast((r.data && r.data.msg) || 'কার্টে যোগ করা যায়নি');
    }).fail(() => S.toast('নেটওয়ার্ক সমস্যা')).always(() => $btn.prop('disabled', false));
  }
  // capture: পুরনো হ্যান্ডলারের আগে ধরি
  document.addEventListener('click', function (e) {
    const b = e.target.closest('.hc-add, .p-add, .p-order, #bbBtn'); if (!b) return;
    const $c = $(b).closest('.product-card'); let $scope = $c.length ? $c : $('[data-pdp]');
    if (!$scope.find('.grp-table').length) return;
    e.stopImmediatePropagation(); e.preventDefault();
    const buy = $(b).is('.p-order, #bbBtn, .btn-order') || $(b).data('buy-now');
    submit($scope, !!buy, $(b));
  }, true);
})(jQuery);
