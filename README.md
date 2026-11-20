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

## v2.2.0 — টেমপ্লেটের বাকি সব ফিচার (WooCommerce ডেটায়)
কোড `inc/modules/*.php`, `assets/js/modules/*.js`, `assets/css/modules/*.css` (ফাইল দিলেই অটো-লোড)।

| ফিচার | ফাইল |
|---|---|
| ক্যামেরা-ফাইন্ডার, হেল্প সেন্টার, কম্পেটিবিলিটি (ডিভাইস/সোশ্যাল) | `a-pages.php` |
| লগইন/রেজিস্টার/OTP/রিসেট | `b-auth.php` |
| RMA (ডিবি `hc_rma`, ছবি আপলোড, অ্যাডমিন লিস্ট, ইমেইল) | `a2-rma.php` |
| অর্ডার ট্র্যাক, কুরিয়ার (Pathao/Steadfast/RedX), ৬০ মিনিটে বাতিল/ঠিকানা বদল, পোস্ট-অর্ডার আপসেল | `a2-track.php` |
| পেমেন্ট: COD+অগ্রিম, বিকাশ/নগদ (TrxID+স্লিপ), অনলাইন ডেমো গেটওয়ে, ফেক-অর্ডার গার্ড | `a2-pay.php` |
| ব্লগ/গাইড (FAQ + JSON-LD + সংশ্লিষ্ট পণ্য) | `b2-blog.php` |
| কুপন ওয়ালেট, সেরা কুপন অটো, কর্মঘণ্টা বার, দাম-কমলে/স্টকে-এলে অ্যালার্ট | `b2-wallet.php` |
| পরে-কিনব | `assets/js/modules/b2-later.js` |
| ইনস্টলেশন, এক্সটেন্ডেড ওয়ারেন্টি, লয়ালটি পয়েন্ট, রেফারেল | `c2-services.php` |
| WhatsApp টেমপ্লেট, CSV এক্সপোর্ট, ড্যাশবোর্ড, Merchant ফিড `/feed/merchant.xml` | `c2-ops.php` |
| BN/EN সুইচ | `assets/js/modules/d2-i18n.js` |
| PWA, অফলাইন, কুকি-সম্মতি, পুশ | `d2-pwa.php` |
| Banglish সার্চ, ভিডিও/৩৬০°, পালস, coview, সম্প্রতি তুলনা, সেভড ফিল্টার | `d2-extras.php` |

অ্যাডমিন: WooCommerce → RMA অনুরোধ / HitsCam সেবা / HitsCam ড্যাশবোর্ড / HitsCam অপারেশন।
SMS গেটওয়ে: `add_filter('hitscam_send_sms', fn($sent,$phone,$msg)=>…, 10, 3)`। অনলাইন গেটওয়ে: `hitscam_online_pay_url` ফিল্টার।
