/* b2 — "পরে কিনব" (save for later): কার্ট পেজ + মিনি-কার্ড; extras.js আচরণ, localStorage এ */
jQuery(function ($) {
  if (!window.HCT) return;
  const K = 'hc_later';
  const get = () => { try { return JSON.parse(localStorage.getItem(K)) || []; } catch (e) { return []; } };
  const put = l => { try { localStorage.setItem(K, JSON.stringify(l)); } catch (e) {} };
  const toast = m => window.HCT_toast && window.HCT_toast(m);
  const esc = s => $('<i>').text(s == null ? '' : s).html();

  /* কার্ট লাইনে "পরে কিনব" বাটন (ক্লাসিক কার্ট টেবিল) */
  function decorate() {
    $('.woocommerce-cart-form .cart_item').each(function () {
      const $r = $(this); if ($r.find('.later-btn').length) return;
      const $x = $r.find('a.remove').first(); if (!$x.length) return;
      const $n = $r.find('.product-name'); const $nl = $n.find('a').first();
      $n.append(' <button type="button" class="lnk-btn later-btn" style="display:block"><i class="bi bi-clock-history"></i> পরে কিনব</button>');
      $r.find('.later-btn').data('item', {
        pid: $x.data('product_id'), key: $x.data('cart_item_key'), name: $nl.text() || $n.text().trim(), url: $nl.attr('href') || '#',
        img: $r.find('.product-thumbnail img').attr('src') || '', price: $r.find('.product-price').text().trim(), qty: parseInt($r.find('input.qty').val(), 10) || 1, sku: $x.data('product_sku') || ''
      });
    });
  }
  $(decorate); $(document.body).on('updated_cart_totals', decorate);

  $(document).on('click', '.later-btn', function () {
    const it = $(this).data('item'); if (!it) return;
    put(get().filter(x => x.pid !== it.pid).concat(it));
    toast('"পরে কিনব" তালিকায় রাখা হয়েছে');
    $(this).closest('.cart_item').find('a.remove').first()[0].click();
  });
  function html() {
    const l = get(); if (!l.length) return '';
    return `<div class="panel mb-3" id="laterBox"><h5 class="fw-bold mb-2"><i class="bi bi-clock-history text-brand"></i> পরে কিনব (${l.length})</h5>` + l.map(i => `<div class="cart-line later-line" data-pid="${i.pid}">
      ${i.img ? `<img src="${esc(i.img)}" alt="">` : ''}<div class="flex-grow-1"><div class="nm"><a href="${esc(i.url)}">${esc(i.name)}</a></div>
      <div class="pr"><b>${esc(i.price)}</b> <small class="text-muted">এখনকার দাম অনুযায়ী কার্টে যোগ হবে</small></div>
      <div class="line-acts"><button type="button" class="btn btn-sm btn-brand later-back" data-pid="${i.pid}"><i class="bi bi-cart-plus"></i> কার্টে নিন</button>
      <button type="button" class="lnk-btn later-del" data-pid="${i.pid}"><i class="bi bi-trash3"></i> মুছুন</button></div></div></div>`).join('') + '</div>';
  }
  function draw() {
    $('#laterBox').remove(); const h = html(); if (!h) return;
    const $c = $('.woocommerce-cart-form').first(), $e = $('.cart-empty').first(); if ($c.length && $c.find('.cart_item').length) $c.after(h); else if ($e.length) $e.after(h); else if ($('body.woocommerce-cart').length) { const $e = $('.cart-empty, .wc-empty-cart-message').first(); ($e.length ? $e : $('.woocommerce').first()).after(h); }
    $('#hcLater').html(h.replace('id="laterBox"', 'id="laterBoxMini"'));
  }
  $(draw); $(document.body).on('updated_cart_totals wc_fragments_refreshed updated_wc_div', draw);
  $(document).on('click', '.later-del', function () { put(get().filter(x => String(x.pid) !== String($(this).data('pid')))); draw(); });
  $(document).on('click', '.later-back', function () {
    const pid = $(this).data('pid'), it = get().find(x => String(x.pid) === String(pid)); if (!it) return;
    $.post(wc_add_to_cart_params_url(), { product_id: pid, quantity: it.qty || 1 }).done(r => {
      if (r && r.error && r.product_url) { toast('এই পণ্যে অপশন বাছতে হবে'); location.href = r.product_url; return; }
      put(get().filter(x => String(x.pid) !== String(pid))); toast('কার্টে যোগ হয়েছে ✓'); $(document.body).trigger('wc_fragment_refresh'); setTimeout(() => location.reload(), 400);
    });
  });
  function wc_add_to_cart_params_url() { return (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url || '/?wc-ajax=%%endpoint%%').replace('%%endpoint%%', 'add_to_cart'); }
});
