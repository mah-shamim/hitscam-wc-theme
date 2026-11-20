<?php
/**
 * d2 — PWA (manifest + service worker, অফলাইন ফলব্যাক), কুকি-সম্মতি কনফিগ (hitscam-tracking প্লাগইনের সাথে), পুশ (VAPID প্লাগেবল)।
 * /manifest.webmanifest ও /sw.js ডাইনামিক। টেমপ্লেট: manifest.webmanifest, sw.js, pro.js, analytics.js
 * আসল ওয়েব-পুশের জন্য: define( 'HITSCAM_VAPID_PUBLIC', 'BN…' ); — ডিভাইস সাবস্ক্রিপশন hc_push_sub AJAX এ usermeta/অপশনে জমা হয়; পাঠানোর গেটওয়ে/লাইব্রেরি আলাদা যুক্ত করুন।
 */
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	add_rewrite_rule( '^manifest\.webmanifest$', 'index.php?hc_pwa=manifest', 'top' );
	add_rewrite_rule( '^sw\.js$', 'index.php?hc_pwa=sw', 'top' );
	add_rewrite_rule( '^hc-offline/?$', 'index.php?hc_pwa=offline', 'top' );
	if ( get_option( 'hitscam_d2_rw' ) !== '1' ) { flush_rewrite_rules( false ); update_option( 'hitscam_d2_rw', '1' ); }
} );
add_filter( 'query_vars', function ( $v ) { $v[] = 'hc_pwa'; return $v; } );
add_filter( 'redirect_canonical', function ( $u ) { return get_query_var( 'hc_pwa' ) ? false : $u; } );

add_action( 'template_redirect', function () {
	$k = get_query_var( 'hc_pwa' ); if ( ! $k ) { return; }
	$i = HITSCAM_URI . '/assets/icons/';
	if ( 'manifest' === $k ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array(
			'name' => get_bloginfo( 'name' ) . ' – ' . ( get_bloginfo( 'description' ) ?: 'ক্যামেরা ও স্মার্ট গ্যাজেট শপ' ), 'short_name' => mb_substr( get_bloginfo( 'name' ), 0, 12 ), 'description' => get_bloginfo( 'description' ),
			'lang' => 'bn', 'dir' => 'ltr', 'start_url' => home_url( '/' ), 'scope' => home_url( '/' ), 'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => '#e03131',
			'icons' => array( array( 'src' => $i . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ), array( 'src' => $i . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ), array( 'src' => $i . 'maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ) ),
			'shortcuts' => array( array( 'name' => 'অর্ডার ট্র্যাক', 'url' => hitscam_page_url( 'track-order' ) ), array( 'name' => 'কার্ট', 'url' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) ) ),
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
	if ( 'offline' === $k ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="bn"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>অফলাইন</title><body style="font-family:sans-serif;text-align:center;padding:4rem 1rem"><h1>আপনি অফলাইনে আছেন</h1><p>ইন্টারনেট ফিরলে আবার চেষ্টা করুন।</p><p><a href="' . esc_url( home_url( '/' ) ) . '">হোমে যান</a></p></body></html>';
		exit;
	}
	// sw.js
	$ver  = 'hc-' . HITSCAM_VERSION . '-' . (int) get_option( 'hitscam_d2_sw', 1 );
	$core = array_values( array_filter( array( home_url( '/' ), function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '', home_url( '/hc-offline/' ), HITSCAM_URI . '/assets/css/template.css', HITSCAM_URI . '/assets/css/wc.css', HITSCAM_URI . '/assets/js/theme.js', $i . 'icon.svg' ) ) );
	header( 'Content-Type: application/javascript; charset=utf-8' ); header( 'Service-Worker-Allowed: /' ); header( 'Cache-Control: no-cache' );
	echo "const VERSION=" . wp_json_encode( $ver ) . ",CORE=" . wp_json_encode( $core ) . ",OFFLINE=" . wp_json_encode( home_url( '/hc-offline/' ) ) . ";\n";
	?>
self.addEventListener('install',e=>{e.waitUntil(caches.open(VERSION).then(c=>Promise.all(CORE.map(u=>c.add(u).catch(()=>null)))).then(()=>self.skipWaiting()));});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==VERSION).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',e=>{
  const req=e.request,url=new URL(req.url);
  if(req.method!=='GET'||url.origin!==self.location.origin)return;
  if(/\/wp-admin|\/wp-json|wc-ajax|admin-ajax|\/cart|\/checkout|\/my-account|add-to-cart|\/feed\//.test(url.pathname+url.search))return; // ডাইনামিক/ব্যক্তিগত পেজ ক্যাশ নয়
  if(req.mode==='navigate'){
    e.respondWith(fetch(req).then(res=>{const copy=res.clone();if(res.ok)caches.open(VERSION).then(c=>c.put(req,copy));return res;}).catch(()=>caches.match(req).then(r=>r||caches.match(OFFLINE))));
    return;
  }
  e.respondWith(caches.open(VERSION).then(c=>c.match(req).then(hit=>{const net=fetch(req).then(res=>{if(res.ok)c.put(req,res.clone());return res;}).catch(()=>hit);return hit||net;})));
});
self.addEventListener('push',e=>{let d={};try{d=e.data?e.data.json():{};}catch(x){d={body:e.data&&e.data.text()};}
  e.waitUntil(self.registration.showNotification(d.title||'HitsCam',{body:d.body||'',icon:'<?php echo esc_url( $i ); ?>icon-192.png',badge:'<?php echo esc_url( $i ); ?>icon-192.png',data:{url:d.url||'/'}}));});
self.addEventListener('notificationclick',e=>{e.notification.close();const url=(e.notification.data&&e.notification.data.url)||'/';
  e.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(cs=>{const c=cs.find(x=>x.url.includes(url));return c?c.focus():self.clients.openWindow(url);}));});
	<?php
	exit;
} );

add_action( 'wp_head', function () {
	echo '<link rel="manifest" href="' . esc_url( home_url( '/manifest.webmanifest' ) ) . '"><meta name="theme-color" content="#e03131"><link rel="apple-touch-icon" href="' . esc_url( HITSCAM_URI . '/assets/icons/icon-192.png' ) . '">' . "\n";
}, 3 );

/* JS কনফিগ: সম্মতি (tracking প্লাগইন সক্রিয় থাকলে তার সেটিং), পুশ কী */
add_action( 'wp_footer', function () {
	$c = array( 'required' => false, 'cookie' => 'hc_consent' );
	if ( function_exists( 'hctrk_opt' ) ) { $c['required'] = (bool) hctrk_opt( 'general', 'require_consent' ); $c['cookie'] = hctrk_opt( 'general', 'consent_cookie' ) ?: 'hc_consent'; }
	$c['required'] = (bool) apply_filters( 'hitscam_consent_required', $c['required'] );
	echo '<script>window.HCB_CONSENT=' . wp_json_encode( $c ) . ';window.HCB_PWA=' . wp_json_encode( array( 'sw' => home_url( '/sw.js' ), 'vapid' => defined( 'HITSCAM_VAPID_PUBLIC' ) ? HITSCAM_VAPID_PUBLIC : '', 'icon' => HITSCAM_URI . '/assets/icons/icon-192.png', 'track' => hitscam_page_url( 'track-order' ) ) ) . ';</script>';
}, 5 );

/* পুশ সাবস্ক্রিপশন সংরক্ষণ */
function hitscam_d2_push_sub() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$s = json_decode( wp_unslash( $_POST['sub'] ?? '' ), true );
	if ( ! is_array( $s ) || empty( $s['endpoint'] ) || 0 !== strpos( $s['endpoint'], 'https://' ) ) { wp_send_json_error(); }
	$all = (array) get_option( 'hitscam_push_subs', array() ); $all[ md5( $s['endpoint'] ) ] = array( 'sub' => $s, 'user' => get_current_user_id(), 'ts' => time() );
	update_option( 'hitscam_push_subs', array_slice( $all, -500, null, true ), false );
	wp_send_json_success();
}
add_action( 'wp_ajax_hc_push_sub', 'hitscam_d2_push_sub' );
add_action( 'wp_ajax_nopriv_hc_push_sub', 'hitscam_d2_push_sub' );
