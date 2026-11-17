(function () {
  var box = document.querySelector('.hcseo-box'); if (!box) return;
  var $ = function (s) { return box.querySelector(s); }, $$ = function (s) { return [].slice.call(box.querySelectorAll(s)); };
  var f = function (n) { return box.querySelector('[name="hcseo[' + n + ']"]'); };
  $$('.hcseo-tabs button').forEach(function (b) { b.onclick = function () {
    $$('.hcseo-tabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
    $$('.hcseo-pane').forEach(function (p) { p.hidden = p.dataset.p !== b.dataset.t; });
  }; });
  function pageTitle() { var t = document.getElementById('title') || document.getElementById('name'); if (t && t.value) return t.value;
    try { return wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) { return ''; } }
  function pageContent() {
    try { var c = wp.data.select('core/editor').getEditedPostContent(); if (c) return c; } catch (e) {}
    var t = document.getElementById('content'); if (t && t.value) return t.value;
    var d = document.getElementById('tag-description') || document.getElementById('description'); return d ? d.value : '';
  }
  function tpl(s) { var site = box.dataset.site;
    return (s || '').replace(/%%sitetitle%%/g, site).replace(/%%sep%%/g, HCSEO.sep).replace(/%%post_title%%|%%term_title%%/g, pageTitle()).replace(/%%[a-z_]+%%/g, '').replace(/\s+/g, ' ').trim(); }
  function render() {
    var t = tpl(f('title').value || HCSEO.tplTitle) || pageTitle(), d = tpl(f('desc').value || HCSEO.tplDesc);
    $('.hcseo-snip .u').textContent = box.dataset.url; $('.hcseo-snip .t').textContent = t; $('.hcseo-snip .d').textContent = d;
    var ot = f('og_title').value || t, od = f('og_desc').value || d, im = f('og_image').value;
    var card = $('.hcseo-card'); card.querySelector('b').textContent = ot; card.querySelector('span').textContent = od; card.querySelector('i').textContent = location.hostname;
    var img = card.querySelector('img'); img.hidden = !im; if (im) img.src = im;
    [['title', t, 30, 60], ['desc', d, 120, 160]].forEach(function (x) { var c = box.querySelector('.c[data-for="' + x[0] + '"]'); if (!c) return; c.textContent = x[1].length + ' / ' + x[3]; c.className = 'c ' + (x[1].length >= x[2] && x[1].length <= x[3] ? 'ok' : 'bad'); });
  }
  box.addEventListener('input', render); render();
  function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function analyze(html, slug) {
    var tmp = document.createElement('div'); tmp.innerHTML = html;
    var text = (tmp.textContent || '').replace(/\s+/g, ' ').trim(), words = text ? text.split(' ').length : 0;
    var title = tpl(f('title').value || HCSEO.tplTitle) || pageTitle(), desc = tpl(f('desc').value || HCSEO.tplDesc);
    var kws = f('keywords').value.split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);
    var out = [];
    function add(c, m) { out.push([c, m]); }
    add(words >= 300 ? 'g' : (words >= 150 ? 'o' : 'r'), 'কনটেন্ট দৈর্ঘ্য: ' + words + ' শব্দ (ন্যূনতম ৩০০ ভালো)');
    add(title.length >= 30 && title.length <= 60 ? 'g' : 'o', 'টাইটেল দৈর্ঘ্য ' + title.length + ' (আদর্শ ৩০–৬০)');
    add(desc.length >= 120 && desc.length <= 160 ? 'g' : (desc.length ? 'o' : 'r'), 'ডেসক্রিপশন দৈর্ঘ্য ' + desc.length + ' (আদর্শ ১২০–১৬০)');
    var imgs = tmp.querySelectorAll('img'), noalt = [].filter.call(imgs, function (i) { return !i.getAttribute('alt'); }).length;
    add(imgs.length ? (noalt ? 'o' : 'g') : 'o', imgs.length ? (noalt ? noalt + 'টি ছবিতে alt নেই' : 'সব ছবিতে alt আছে') : 'কোনো ছবি নেই');
    var links = tmp.querySelectorAll('a[href]'), ext = [].filter.call(links, function (a) { return a.hostname && a.hostname !== location.hostname; }).length;
    add(links.length - ext > 0 ? 'g' : 'o', 'অভ্যন্তরীণ লিংক: ' + (links.length - ext) + ', বাহ্যিক: ' + ext);
    add(tmp.querySelector('h2,h3') ? 'g' : 'o', tmp.querySelector('h2,h3') ? 'সাবহেডিং আছে' : 'H2/H3 সাবহেডিং যোগ করুন');
    if (!kws.length) add('o', 'ফোকাস কিওয়ার্ড দিন — কিওয়ার্ড-ভিত্তিক চেক চালু হবে');
    kws.forEach(function (k) {
      var re = new RegExp(esc(k), 'gi'), n = (text.match(re) || []).length, dens = words ? (n / words * 100) : 0;
      var fp = (tmp.querySelector('p') || {}).textContent || '', hs = [].map.call(tmp.querySelectorAll('h1,h2,h3'), function (h) { return h.textContent; }).join(' ');
      add(title.toLowerCase().indexOf(k) > -1 ? 'g' : 'r', '“' + k + '” টাইটেলে ' + (title.toLowerCase().indexOf(k) > -1 ? 'আছে' : 'নেই'));
      add(desc.toLowerCase().indexOf(k) > -1 ? 'g' : 'o', '“' + k + '” ডেসক্রিপশনে ' + (desc.toLowerCase().indexOf(k) > -1 ? 'আছে' : 'নেই'));
      add(fp.toLowerCase().indexOf(k) > -1 ? 'g' : 'o', '“' + k + '” প্রথম অনুচ্ছেদে ' + (fp.toLowerCase().indexOf(k) > -1 ? 'আছে' : 'নেই'));
      add(hs.toLowerCase().indexOf(k) > -1 ? 'g' : 'o', '“' + k + '” হেডিংয়ে ' + (hs.toLowerCase().indexOf(k) > -1 ? 'আছে' : 'নেই'));
      add((slug || '').indexOf(k.replace(/\s+/g, '-')) > -1 ? 'g' : 'o', '“' + k + '” URL-এ ' + ((slug || '').indexOf(k.replace(/\s+/g, '-')) > -1 ? 'আছে' : 'নেই'));
      add(dens >= .5 && dens <= 2.5 ? 'g' : 'o', '“' + k + '” ঘনত্ব ' + dens.toFixed(1) + '% (' + n + ' বার; আদর্শ ০.৫–২.৫%)');
    });
    var ul = $('.hcseo-res'); ul.innerHTML = '';
    out.forEach(function (x) { var li = document.createElement('li'); li.className = x[0] === 'g' ? 'g' : (x[0] === 'r' ? 'r' : ''); li.textContent = x[1]; ul.appendChild(li); });
  }
  $('.hcseo-run').onclick = function () {
    var html = pageContent(), slug = '';
    var sl = document.getElementById('post_name'); if (sl) slug = sl.value;
    if (html && html.length > 80) return analyze(html, slug);
    var fd = new FormData(); fd.append('action', 'hcseo_content'); fd.append('nonce', HCSEO.nonce); fd.append('id', box.dataset.id);
    fetch(HCSEO.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { analyze(j.success ? j.data.html : '', j.success ? j.data.slug : slug); });
  };
  var ai = $('.hcseo-ai'); if (ai) ai.onclick = function () {
    var m = $('.hcseo-ai-msg'); m.textContent = '…'; var fd = new FormData();
    fd.append('action', 'hcseo_ai'); fd.append('nonce', HCSEO.nonce); fd.append('content', pageTitle() + '\n' + pageContent()); fd.append('keywords', f('keywords').value);
    fetch(HCSEO.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.success) { f('title').value = j.data.title; f('desc').value = j.data.description; m.textContent = 'হয়েছে — রিভিউ করে সেভ করুন'; render(); } else m.textContent = j.data || 'ব্যর্থ';
    }).catch(function () { m.textContent = 'নেটওয়ার্ক ত্রুটি'; });
  };
})();
