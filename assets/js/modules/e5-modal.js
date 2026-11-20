/* e5 — Instant order পপআপ মডাল (টেমপ্লেটের #orderModal): কার্টে যোগের পর চেকআউট ফর্ম মডালের ভেতরে খোলে */
(function ($) {
  let frame, modal;
  function build() {
    if (document.getElementById('orderModal')) return;
    $('body').append('<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down"><div class="modal-content">' +
      '<div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-lightning-charge-fill text-brand"></i> ইন্সট্যান্ট অর্ডার</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button></div>' +
      '<div class="modal-body bg-light p-0" id="orderRoot"><div class="text-center text-muted py-5" id="orderLoad"><span class="spinner-border spinner-border-sm"></span> লোড হচ্ছে…</div><iframe id="orderFrame" title="ইন্সট্যান্ট অর্ডার" style="width:100%;border:0;min-height:60vh;display:none"></iframe></div></div></div></div>');
    frame = document.getElementById('orderFrame');
    frame.addEventListener('load', function () {
      $('#orderLoad').hide(); $(frame).show();
      const fit = () => { try { const d = frame.contentDocument; if (d && d.body) frame.style.height = Math.max(d.documentElement.scrollHeight, 480) + 'px'; } catch (e) {} };
      fit(); clearInterval(frame._t); frame._t = setInterval(fit, 600);
      try { if (/order-received/.test(frame.contentWindow.location.href)) { $('#orderModal .modal-title').html('<i class="bi bi-check-circle-fill text-success"></i> অর্ডার সম্পন্ন'); if (window.HCT_cartRefresh) HCT_cartRefresh(); } } catch (e) {}
    });
    document.getElementById('orderModal').addEventListener('hidden.bs.modal', function () {
      clearInterval(frame._t);
      try { if (/order-received/.test(frame.contentWindow.location.href)) { location.reload(); } } catch (e) {}
    });
  }
  window.HCOrder = {
    open: function () {
      if (!window.bootstrap) { location.assign((window.HCT && HCT.checkout) || '/checkout/'); return; }
      build();
      const base = ((window.HCT && HCT.checkout) || '/checkout/'); const u = base + (base.indexOf('?') > -1 ? '&' : '?') + 'hc_modal=1';
      $('#orderLoad').show(); $(frame).hide(); frame.src = u;
      $('#orderModal .modal-title').html('<i class="bi bi-lightning-charge-fill text-brand"></i> ইন্সট্যান্ট অর্ডার');
      modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('orderModal')); modal.show();
    }
  };
})(jQuery);
