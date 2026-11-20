<?php
/**
 * স্ট্রিম A — টেমপ্লেটের পেজ পোর্ট: ক্যামেরা-ফাইন্ডার, হেল্প সেন্টার, কম্পেটিবিলিটি (ডিভাইস/সোশ্যাল),
 * + পেজ রেজিস্ট্রেশন (track-order, help, compat…) ও প্রোডাক্ট পেজে "সাধারণ প্রশ্ন"।
 * RMA → a-rma.php, ট্র্যাক/সেলফ-সার্ভিস → a-track.php, চেকআউট অগ্রিম → a-advance.php
 */
defined( 'ABSPATH' ) || exit;

const HITSCAM_MOD_A_VER = 6;

/* ---------- পেজ রেজিস্ট্রেশন ---------- */
add_filter( 'hitscam_extra_pages', function ( $pages ) {
	$pages['track-order']          = array( 'অর্ডার ট্র্যাক করুন', '[hitscam_track]' );
	$pages['help']                 = array( 'হেল্প সেন্টার', '[hitscam_help]' );
	$pages['device-compatibility'] = array( 'ডিভাইস ও ব্রাউজার কম্পেটিবিলিটি', '[hitscam_compat type="device"]' );
	$pages['social-compatibility'] = array( 'সোশ্যাল মিডিয়া অ্যাপ, ব্রাউজার ও ডিভাইস কম্পেটিবিলিটি', '[hitscam_compat type="social"]' );
	return $pages;
} );

/** থিম আপডেটে একবার: পেজ বানানো + পুরোনো ডিফল্ট track-order কনটেন্ট বদল */
add_action( 'init', function () {
	if ( (int) get_option( 'hitscam_mod_a_v', 0 ) >= HITSCAM_MOD_A_VER || ! function_exists( 'hitscam_ensure_extra_pages' ) || ! hitscam_is_wc() ) {
		return;
	}
	hitscam_ensure_extra_pages();
	$ids = (array) get_option( 'hitscam_page_ids', array() );
	$tp  = get_page_by_path( 'track-order' );
	if ( $tp ) {
		$ids['track-order'] = $tp->ID;
		if ( in_array( trim( $tp->post_content ), array( '', '[woocommerce_order_tracking]' ), true ) ) {
			wp_update_post( array( 'ID' => $tp->ID, 'post_content' => '[hitscam_track]' ) );
		}
	}
	update_option( 'hitscam_page_ids', $ids );
	update_option( 'hitscam_mod_a_v', HITSCAM_MOD_A_VER );
}, 30 );

function hitscam_page_url( $slug ) {
	$ids = (array) get_option( 'hitscam_page_ids', array() );
	return ! empty( $ids[ $slug ] ) ? get_permalink( $ids[ $slug ] ) : home_url( '/' . $slug . '/' );
}

/** নির্ভরযোগ্য ফোন-সাধারণীকরণ */
function hitscam_a_phone( $raw ) {
	return function_exists( 'hitscam_normalize_bd_phone' ) ? hitscam_normalize_bd_phone( $raw ) : preg_replace( '/\D/', '', (string) $raw );
}

/* ---------- কম্পেটিবিলিটি পেজ ---------- */
add_shortcode( 'hitscam_compat', function ( $a ) {
	$a = shortcode_atts( array( 'type' => 'device' ), $a );
	$f = HITSCAM_DIR . '/inc/pages/' . ( 'social' === $a['type'] ? 'social-compat' : 'device-compat' ) . '.html';
	return file_exists( $f ) ? file_get_contents( $f ) : ''; // phpcs:ignore
} );

/* ---------- হেল্প সেন্টার ---------- */
function hitscam_help_items() {
	$f = HITSCAM_DIR . '/inc/pages/help-items.json';
	$d = file_exists( $f ) ? json_decode( file_get_contents( $f ), true ) : array(); // phpcs:ignore
	$d = is_array( $d ) ? $d : array();
	// অ্যাডমিনের FAQ (hc_faq) ও যোগ হয়: বিভাগ = FAQ বিভাগ (না থাকলে "অন্যান্য")
	$q = get_posts( array( 'post_type' => 'hc_faq', 'numberposts' => 100, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	foreach ( $q as $p ) {
		$t   = wp_get_post_terms( $p->ID, 'hc_faq_cat', array( 'fields' => 'names' ) );
		$d[] = array( 'c' => ( $t && ! is_wp_error( $t ) ) ? $t[0] : 'অন্যান্য', 'q' => get_the_title( $p ), 'a' => wp_strip_all_tags( $p->post_content ), 't' => '' );
	}
	return apply_filters( 'hitscam_help_items', $d );
}

add_shortcode( 'hitscam_help', function () {
	ob_start(); ?>
	<p class="text-muted">প্রশ্ন লিখে খুঁজুন (বাংলা বা Banglish — যেমন "kamera install")।</p>
	<div data-help-page style="max-width:860px" data-items="<?php echo esc_attr( wp_json_encode( hitscam_help_items(), JSON_UNESCAPED_UNICODE ) ); ?>">
		<div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="helpQ" type="search" placeholder="যেমন: ওয়ারেন্টি, delivery, cancel…" aria-label="হেল্প সার্চ" autocomplete="off"></div>
		<div class="d-flex flex-wrap gap-2 mb-3" id="helpCats" role="group" aria-label="বিষয়"></div>
		<div class="small text-muted mb-2" id="helpCount" aria-live="polite"></div>
		<div class="accordion mb-4" id="helpList"></div>
		<div class="panel d-flex flex-wrap gap-2 align-items-center"><div class="me-auto"><b>উত্তর পাননি?</b><div class="small text-muted">আমাদের টিম সরাসরি সাহায্য করবে।</div></div><a href="#" id="helpWa" class="btn btn-wa"><i class="bi bi-whatsapp"></i> WhatsApp এ জিজ্ঞাসা করুন</a><a href="<?php echo esc_url( hitscam_page_url( 'rma' ) ); ?>" class="btn btn-soft">রিটার্ন / রিপেয়ার</a><a href="<?php echo esc_url( hitscam_page_url( 'stores' ) ); ?>" class="btn btn-soft">স্টোর</a></div>
	</div>
	<?php return ob_get_clean();
} );

/** প্রোডাক্ট পেজে "এই পণ্য নিয়ে সাধারণ প্রশ্ন" (টেমপ্লেটের productHelp) — JS "আপনার পছন্দ হতে পারে" সেকশনের আগে বসায় */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$terms = wp_get_post_terms( get_the_ID(), 'product_cat', array( 'fields' => 'all' ) );
	$terms = is_wp_error( $terms ) ? array() : $terms;
	$hay   = mb_strtolower( get_the_title() . ' ' . implode( ' ', wp_list_pluck( $terms, 'name' ) ) . ' ' . implode( ' ', wp_list_pluck( $terms, 'slug' ) ) );
	$cam   = (bool) preg_match( '/ক্যামেরা|camera|cam|cctv|ptz|lens|dvr|nvr|সিকিউরিটি|security/u', $hay );
	$want  = $cam ? array( 'ইনস্টলেশন', 'ওয়ারেন্টি', 'ডেলিভারি' ) : ( preg_match( '/মেমোরি|memory|sd|card/u', $hay ) ? array( 'ইনস্টলেশন', 'ওয়ারেন্টি' ) : array( 'ডেলিভারি', 'ওয়ারেন্টি', 'পেমেন্ট' ) );
	$list  = array_slice( array_values( array_filter( hitscam_help_items(), fn( $h ) => in_array( $h['c'], $want, true ) ) ), 0, 4 );
	if ( ! $list ) {
		return;
	}
	$h = '<div class="panel my-4" id="pHelp"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 fw-bold m-0"><i class="bi bi-question-circle text-brand"></i> এই পণ্য নিয়ে সাধারণ প্রশ্ন</h2><a href="' . esc_url( hitscam_page_url( 'help' ) ) . '" class="small">সব প্রশ্ন →</a></div><div class="accordion accordion-flush" id="pHelpAcc">';
	foreach ( $list as $i => $it ) {
		$h .= '<div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ph' . (int) $i . '" aria-expanded="false">' . esc_html( $it['q'] ) . '</button></h3><div id="ph' . (int) $i . '" class="accordion-collapse collapse"><div class="accordion-body">' . esc_html( $it['a'] ) . '</div></div></div>';
	}
	$h .= '</div></div>';
	echo '<template id="hcPHelpTpl">' . $h . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
} );

/* ---------- ক্যামেরা ফাইন্ডার ---------- */
/** পণ্যের "ফিট" (place/power/net): মেটা ওভাররাইড, নইলে নাম/ক্যাটেগরি/ট্যাগ/অ্যাট্রিবিউট থেকে অনুমান */
function hitscam_finder_fit( WC_Product $p ) {
	$ov = array();
	foreach ( array( 'place', 'power', 'net' ) as $k ) {
		$v = trim( (string) get_post_meta( $p->get_id(), '_hc_fit_' . $k, true ) );
		if ( '' !== $v ) { $ov[ $k ] = array_values( array_filter( array_map( 'trim', explode( ',', $v ) ) ) ); }
	}
	$hay = $p->get_name() . ' ' . wp_strip_all_tags( $p->get_short_description() ) . ' ' . wp_strip_all_tags( wc_get_product_category_list( $p->get_id(), ' ' ) ) . ' ' . wp_strip_all_tags( wc_get_product_tag_list( $p->get_id(), ' ' ) );
	foreach ( $p->get_attributes() as $at ) {
		if ( is_object( $at ) && method_exists( $at, 'get_options' ) ) {
			$hay .= ' ' . ( $at->is_taxonomy() ? implode( ' ', wc_get_product_terms( $p->get_id(), $at->get_name(), array( 'fields' => 'names' ) ) ) : implode( ' ', (array) $at->get_options() ) );
		}
	}
	$hay   = mb_strtolower( $hay );
	$m     = fn( $re ) => (bool) preg_match( $re, $hay );
	$solar = $m( '/solar|সোলার|battery|ব্যাটারি/u' );
	$sim   = $m( '/4g|sim|সিম/u' );
	$wired = $m( '/dvr|nvr|poe|cctv|সিসি|wired|তারযুক্ত/u' );
	$wifi  = $m( '/wi-?fi|ওয়াইফাই|ওয়াই-ফাই|ip\s*cam|আইপি/u' ) || ( ! $sim && ! $wired );
	$out   = $m( '/outdoor|বাইরে|waterproof|ওয়াটারপ্রুফ|ip6[5-8]|solar|সোলার|ptz|jumbo|জাম্বো|dual|ডুয়াল/u' );
	$in    = $m( '/indoor|ঘরের|baby|বেবি|mini|মিনি|smart|স্মার্ট|wi-?fi|ওয়াইফাই/u' ) || ! $out;
	$fit   = array(
		'place' => array_values( array_filter( array( $in ? 'in' : null, $out ? 'out' : null ) ) ),
		'power' => $solar ? array( 'solar', 'mains' ) : array( 'mains' ),
		'net'   => array_values( array_filter( array( $wifi ? 'wifi' : null, $sim ? 'sim' : null, $wired ? 'wired' : null ) ) ),
	);
	return apply_filters( 'hitscam_finder_fit', array_merge( $fit, $ov ), $p );
}

/** ফাইন্ডারে আসার যোগ্য: ক্যামেরা-জাতীয় পণ্য (বা _hc_fit_* সেট করা) */
function hitscam_finder_products() {
	if ( ! hitscam_is_wc() ) { return array(); }
	$ids = wc_get_products( array( 'status' => 'publish', 'limit' => 200, 'return' => 'ids', 'type' => array( 'simple', 'variable', 'grouped', 'external' ) ) );
	$out = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) { continue; }
		$has = get_post_meta( $id, '_hc_fit_place', true ) || get_post_meta( $id, '_hc_fit_power', true ) || get_post_meta( $id, '_hc_fit_net', true );
		$cat = mb_strtolower( $p->get_name() . ' ' . wp_strip_all_tags( wc_get_product_category_list( $id, ' ' ) ) );
		if ( ! $has && ! preg_match( '/ক্যামেরা|camera|cam\b|cctv|ptz|lens|dvr|nvr|সিকিউরিটি|security/u', $cat ) ) { continue; }
		$price = (float) $p->get_price();
		if ( $p->is_type( 'variable' ) ) {
			$price = (float) $p->get_variation_price( 'min', true );
		} elseif ( $p->is_type( 'grouped' ) ) {
			$ps    = array_filter( array_map( fn( $c ) => ( $cp = wc_get_product( $c ) ) ? (float) $cp->get_price() : 0, $p->get_children() ) );
			$price = $ps ? min( $ps ) : 0;
		}
		$out[] = array( 'id' => $id, 'name' => $p->get_name(), 'url' => get_permalink( $id ), 'img' => get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(), 'price' => $price, 'from' => $p->is_type( array( 'variable', 'grouped' ) ), 'fit' => hitscam_finder_fit( $p ) );
	}
	return $out;
}

add_shortcode( 'hitscam_finder', function () {
	$cfg = array( 'products' => hitscam_finder_products(), 'cur' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '৳', 'contact' => hitscam_page_url( 'contact' ) );
	return '<p class="text-muted">৪টি ছোট প্রশ্নের উত্তর দিন — আমরা আপনার জন্য মানানসই ক্যামেরা দেখাব।</p><div data-finder data-cfg="' . esc_attr( wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ) . '"><div id="fnd" class="fnd-step"></div><div id="fndRes" class="mt-4"></div></div>';
} );

// ফিট ওভাররাইড ফিল্ড (পণ্য এডিট → জেনারেল)
add_action( 'woocommerce_product_options_general_product_data', function () {
	echo '<div class="options_group"><p class="form-field"><strong>HitsCam ক্যামেরা-ফাইন্ডার (ঐচ্ছিক)</strong></p>';
	woocommerce_wp_text_input( array( 'id' => '_hc_fit_place', 'label' => 'জায়গা', 'placeholder' => 'in,out', 'desc_tip' => true, 'description' => 'in = ঘরের ভেতর, out = বাইরে (কমা দিয়ে)। খালি = স্বয়ংক্রিয় অনুমান' ) );
	woocommerce_wp_text_input( array( 'id' => '_hc_fit_power', 'label' => 'পাওয়ার', 'placeholder' => 'mains,solar', 'desc_tip' => true, 'description' => 'mains = বিদ্যুৎ, solar = সোলার' ) );
	woocommerce_wp_text_input( array( 'id' => '_hc_fit_net', 'label' => 'নেটওয়ার্ক', 'placeholder' => 'wifi,sim,wired', 'desc_tip' => true, 'description' => 'wifi, sim (4G), wired (DVR)' ) );
	echo '</div>';
} );
add_action( 'woocommerce_process_product_meta', function ( $id ) {
	foreach ( array( 'place' => array( 'in', 'out' ), 'power' => array( 'mains', 'solar' ), 'net' => array( 'wifi', 'sim', 'wired' ) ) as $k => $allowed ) {
		if ( ! isset( $_POST[ '_hc_fit_' . $k ] ) ) { continue; } // phpcs:ignore WordPress.Security.NonceVerification
		$v = array_intersect( array_map( 'trim', explode( ',', strtolower( sanitize_text_field( wp_unslash( $_POST[ '_hc_fit_' . $k ] ) ) ) ) ), $allowed ); // phpcs:ignore
		update_post_meta( $id, '_hc_fit_' . $k, implode( ',', $v ) );
	}
} );
