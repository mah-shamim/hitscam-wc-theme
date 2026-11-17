# HitsCam — WooCommerce Theme v2.0.0

ইনস্টল: Appearance → Themes → Upload → অ্যাক্টিভেট। থিম অ্যাক্টিভেটে অটো:
1. WooCommerce আছে কিনা চেক; না থাকলে wordpress.org থেকে ইনস্টল+অ্যাক্টিভেট (ব্যর্থ হলে অ্যাডমিন নোটিসে "আবার চেষ্টা" বাটন)।
2. বান্ডেল প্লাগিন কপি+অ্যাক্টিভেট: hitscam-tracking, hitscam-seo, hitscam-api।
3. স্টোর সেটআপ (একবার): BDT/বাংলাদেশ, COD, ক্লাসিক কার্ট/চেকআউট, পেজ (ট্র্যাক, কম্পেয়ার, FAQ, কন্টাক্ট, উইশলিস্ট), মেনু, শিপিং জোন।

## প্লাগিন
- **HitsCam Tracking** (মেনু: HitsCam Tracking): Meta Settings / Google Tags / GTM / Filter & Hook List / Logs / HELP। Meta Pixel + Conversions API (event_id ডিডুপ, হ্যাশড PII), GA4/Ads, GTM dataLayer, কনসেন্ট, রোল-এক্সক্লুশন।
- **HitsCam SEO** (মেনু: HitsCam SEO): মেটাবক্স+কনটেন্ট অ্যানালাইসিস, টাইটেল ভেরিয়েবল, OG/X/Dublin Core, JSON-LD+Knowledge Graph+Local Business, ব্রেডক্রাম্ব, সাইটম্যাপ (পোস্ট/প্রোডাক্ট/ট্যাক্সো/News/Video/RSS), IndexNow+Google Indexing, 404+রিডাইরেক্ট, robots/.htaccess এডিটর, Yoast/RankMath/SEOPress/AIOSEO ইমপোর্ট, PageSpeed, Search Console স্ট্যাটস, AI টাইটেল, ওয়াইজার্ড।
- **HitsCam API** (WooCommerce → HitsCam API): `/wp-json/hitscam/v1` — নিজস্ব API key (হ্যাশড, স্কোপ, রেট-লিমিট), products/orders/customers/coupons/reports/stock, HMAC-সাইনড ওয়েবহুক (রিট্রাই), OpenAPI: `/wp-json/hitscam/v1/openapi.json`।
  `curl -H "Authorization: Bearer <key>" https://site/wp-json/hitscam/v1/products`
