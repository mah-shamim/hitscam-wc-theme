<?php
/**
 * a2 — রিটার্ন/রিপেয়ার (RMA): শর্টকোড [hitscam_rma], ডিবি টেবিল hc_rma, অ্যাডমিন লিস্ট, ইমেইল।
 * টেমপ্লেট: rma.html + support.js (RMA অংশ)।
 */
defined( 'ABSPATH' ) || exit;

const HITSCAM_A2_DB_VER = 1;

function hitscam_a2_rma_stages() {
	return array( 'অনুরোধ গৃহীত', 'যাচাই চলছে', 'অনুমোদিত', 'পিকআপ / মেরামত', 'সমাধান সম্পন্ন' );
}

add_action( 'init', function () {
	if ( (int) get_option( 'hitscam_a2_db', 0 ) >= HITSCAM_A2_DB_VER ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cc = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$wpdb->prefix}hc_rma (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(20) NOT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		phone varchar(20) NOT NULL DEFAULT '',
		product varchar(255) NOT NULL DEFAULT '',
		rtype varchar(40) NOT NULL DEFAULT '',
		reason varchar(255) NOT NULL DEFAULT '',
		descr text,
		photos text,
		stage tinyint(3) unsigned NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code),
		KEY phone (phone)
	) $cc;" );
	update_option( 'hitscam_a2_db', HITSCAM_A2_DB_VER );
}, 5 );

/* ---------- শর্টকোড ---------- */
add_shortcode( 'hitscam_rma', function () {
	$orders = array();
	if ( is_user_logged_in() && function_exists( 'wc_get_orders' ) ) {
		foreach ( wc_get_orders( array( 'customer_id' => get_current_user_id(), 'limit' => 20, 'status' => array( 'processing', 'completed', 'on-hold' ) ) ) as $o ) {
			$items = array();
			foreach ( $o->get_items() as $it ) { $items[] = $it->get_name(); }
			$orders[] = array( 'id' => $o->get_id(), 'label' => '#' . $o->get_order_number() . ' · ' . wc_format_datetime( $o->get_date_created() ) . ' · ' . wp_strip_all_tags( $o->get_formatted_order_total() ), 'items' => $items, 'phone' => $o->get_billing_phone() );
		}
	}
	$pre = isset( $_GET['o'] ) ? absint( $_GET['o'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	ob_start(); ?>
<div data-rma-page class="row g-4" data-orders="<?php echo esc_attr( wp_json_encode( $orders ) ); ?>" data-pre="<?php echo (int) $pre; ?>" data-stages="<?php echo esc_attr( wp_json_encode( hitscam_a2_rma_stages() ) ); ?>">
	<div class="col-lg-6"><div class="panel"><h2 class="h5 fw-bold mb-3">নতুন অনুরোধ</h2>
		<form id="rmaForm" novalidate>
			<div class="mb-2"><label class="form-label small" for="rmaOrder">অর্ডার *</label>
				<?php if ( $orders ) : ?><select class="form-select" name="order" id="rmaOrder"></select>
				<?php else : ?><input class="form-control" name="order" id="rmaOrder" inputmode="numeric" placeholder="অর্ডার নম্বর (যেমন 538)" value="<?php echo $pre ? (int) $pre : ''; ?>"><?php endif; ?></div>
			<div class="mb-2"><label class="form-label small" for="rmaProd">পণ্য *</label>
				<?php if ( $orders ) : ?><select class="form-select" name="prod" id="rmaProd"></select><?php else : ?><input class="form-control" name="prod" id="rmaProd" placeholder="পণ্যের নাম"><?php endif; ?></div>
			<div class="mb-2"><label class="form-label small" for="rmaPhone">ফোন *</label><input class="form-control" name="phone" id="rmaPhone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX"></div>
			<div class="mb-2"><div class="form-label small">ধরন *</div>
				<label class="pay-opt"><input type="radio" class="form-check-input" name="type" value="রিটার্ন" checked><span>রিটার্ন (ফেরত)</span></label>
				<label class="pay-opt"><input type="radio" class="form-check-input" name="type" value="রিপ্লেসমেন্ট"><span>রিপ্লেসমেন্ট (বদল)</span></label>
				<label class="pay-opt"><input type="radio" class="form-check-input" name="type" value="রিপেয়ার"><span>রিপেয়ার / ওয়ারেন্টি সার্ভিস</span></label></div>
			<div class="mb-2"><label class="form-label small" for="rmaReason">কারণ *</label><select class="form-select" name="reason" id="rmaReason"><option>ড্যামেজড / ভাঙা এসেছে</option><option>চালু হচ্ছে না / কাজ করছে না</option><option>ভুল পণ্য এসেছে</option><option>বিবরণের সাথে মেলেনি</option><option>অন্য কারণ</option></select></div>
			<div class="mb-2"><label class="form-label small" for="rmaDesc">সমস্যার বিবরণ * <small class="text-muted">(কমপক্ষে ১০ অক্ষর)</small></label><textarea class="form-control" name="desc" id="rmaDesc" rows="3"></textarea></div>
			<div class="mb-2"><label class="form-label small" for="rmaPhotos">ছবি (সর্বোচ্চ ৩টি, JPG/PNG)</label><input class="form-control" type="file" id="rmaPhotos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><div id="rmaThumbs" class="mt-2"></div></div>
			<div class="hp-field" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
			<div class="small text-danger mb-2" id="rmaMsg" role="alert"></div>
			<button class="btn btn-brand w-100" type="submit">অনুরোধ জমা দিন</button>
		</form></div></div>
	<div class="col-lg-6">
		<div class="panel mb-3"><h2 class="h6 fw-bold mb-2">অনুরোধ খুঁজুন</h2>
			<form id="rmaFind" class="row g-2"><div class="col-6"><input class="form-control form-control-sm" name="rid" placeholder="RMA-12345" aria-label="RMA নম্বর"></div><div class="col-6"><input class="form-control form-control-sm" name="rphone" placeholder="ফোন" inputmode="tel" aria-label="ফোন"></div><div class="col-12"><button class="btn btn-soft btn-sm" type="submit">খুঁজুন</button></div></form><div id="rmaFound" class="mt-2"></div></div>
		<h2 class="h6 fw-bold mb-2">আমার অনুরোধ</h2><div id="rmaList"><?php echo hitscam_a2_rma_cards( is_user_logged_in() ? hitscam_a2_rma_rows( 'user_id', get_current_user_id() ) : array() ); // phpcs:ignore ?></div>
		<?php if ( ! is_user_logged_in() ) : ?><div class="small text-muted">লগইন করলে আপনার সব অনুরোধ এখানে দেখা যাবে; অথবা RMA নম্বর ও ফোন দিয়ে খুঁজুন।</div><?php endif; ?>
	</div>
</div>
	<?php
	return ob_get_clean();
} );

function hitscam_a2_rma_rows( $col, $val ) {
	global $wpdb;
	$col = 'user_id' === $col ? 'user_id' : 'phone';
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hc_rma WHERE $col = %s ORDER BY id DESC LIMIT 20", $val ) ); // phpcs:ignore WordPress.DB
}

function hitscam_a2_rma_card( $r ) {
	$st = hitscam_a2_rma_stages(); $stage = (int) $r->stage; $h = '';
	foreach ( $st as $i => $s ) {
		$cl = $i < $stage ? 'done' : ( $i === $stage ? 'current' : '' );
		$h .= '<li class="' . $cl . '"><span class="dot">' . ( $i < $stage ? '<i class="bi bi-check-lg"></i>' : ( $i + 1 ) ) . '</span><span class="lbl">' . esc_html( $s ) . '</span></li>';
	}
	$ph = '';
	foreach ( (array) json_decode( (string) $r->photos, true ) as $u ) { $ph .= '<img src="' . esc_url( $u ) . '" width="48" height="48" class="rounded-3 me-1" alt="">'; }
	return '<div class="rma-card panel mb-3" data-id="' . esc_attr( $r->code ) . '"><div class="d-flex flex-wrap justify-content-between gap-2"><div><b class="text-brand">' . esc_html( $r->code ) . '</b> <span class="badge bg-dark">' . esc_html( $r->rtype ) . '</span></div><small class="text-muted">' . esc_html( wp_date( 'd M Y', strtotime( $r->created ) ) ) . ' · অর্ডার ' . ( $r->order_id ? '#' . (int) $r->order_id : '—' ) . '</small></div>'
		. '<div class="small mt-1">' . esc_html( $r->product ) . ' — ' . esc_html( $r->reason ) . '</div><ol class="rma-line mt-3">' . $h . '</ol>' . ( $ph ? '<div class="mt-2">' . $ph . '</div>' : '' ) . '</div>';
}
function hitscam_a2_rma_cards( $rows ) {
	if ( ! $rows ) { return '<div class="text-muted">এখনও কোনো অনুরোধ নেই।</div>'; }
	return implode( '', array_map( 'hitscam_a2_rma_card', $rows ) );
}

/* ---------- AJAX ---------- */
function hitscam_a2_limit( $key, $max, $ttl ) {
	$k = 'hca2_' . $key . '_' . md5( WC_Geolocation::get_ip_address() );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) { return false; }
	set_transient( $k, $n + 1, $ttl );
	return true;
}

add_action( 'wp_ajax_hc_rma_new', 'hitscam_a2_ajax_rma_new' );
add_action( 'wp_ajax_nopriv_hc_rma_new', 'hitscam_a2_ajax_rma_new' );
function hitscam_a2_ajax_rma_new() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! empty( $_POST['website'] ) ) { wp_send_json_error( array( 'msg' => 'ত্রুটি।' ) ); }
	if ( ! hitscam_a2_limit( 'rma', 5, HOUR_IN_SECONDS ) ) { wp_send_json_error( array( 'msg' => 'অনেকগুলো অনুরোধ হয়েছে, কিছুক্ষণ পরে চেষ্টা করুন।' ) ); }
	$phone = hitscam_normalize_bd_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	$desc  = sanitize_textarea_field( wp_unslash( $_POST['desc'] ?? '' ) );
	$prod  = sanitize_text_field( wp_unslash( $_POST['prod'] ?? '' ) );
	$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
	$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
	if ( ! $phone ) { wp_send_json_error( array( 'msg' => 'সঠিক মোবাইল নাম্বার দিন' ) ); }
	if ( ! in_array( $type, array( 'রিটার্ন', 'রিপ্লেসমেন্ট', 'রিপেয়ার' ), true ) || ! $prod || mb_strlen( $desc ) < 10 ) { wp_send_json_error( array( 'msg' => 'লাল ঘরগুলো ঠিক করুন' ) ); }
	$oid = absint( $_POST['order'] ?? 0 ); $order = null;
	if ( $oid ) {
		$order = wc_get_order( $oid );
		$own   = $order && is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
		if ( ! $order || ( ! $own && hitscam_normalize_bd_phone( $order->get_billing_phone() ) !== $phone ) ) { wp_send_json_error( array( 'msg' => 'অর্ডার নাম্বার ও ফোন মিলছে না।' ) ); }
	}
	$urls = array();
	if ( ! empty( $_FILES['photos']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$f = $_FILES['photos']; // phpcs:ignore
		for ( $i = 0; $i < min( 3, count( (array) $f['name'] ) ); $i++ ) {
			if ( empty( $f['name'][ $i ] ) || $f['size'][ $i ] > 3 * MB_IN_BYTES ) { continue; }
			$one = array( 'name' => $f['name'][ $i ], 'type' => $f['type'][ $i ], 'tmp_name' => $f['tmp_name'][ $i ], 'error' => $f['error'][ $i ], 'size' => $f['size'][ $i ] );
			$up  = wp_handle_upload( $one, array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
			if ( ! empty( $up['url'] ) ) { $urls[] = $up['url']; }
		}
	}
	global $wpdb;
	$code = 'RMA-' . wp_rand( 10000, 99999 );
	$wpdb->insert( $wpdb->prefix . 'hc_rma', array( 'code' => $code, 'order_id' => $oid, 'user_id' => get_current_user_id(), 'phone' => $phone, 'product' => $prod, 'rtype' => $type, 'reason' => $reason, 'descr' => $desc, 'photos' => wp_json_encode( $urls ), 'stage' => 0, 'created' => current_time( 'mysql' ) ) );
	if ( ! $wpdb->insert_id ) { wp_send_json_error( array( 'msg' => 'সংরক্ষণ করা যায়নি, আবার চেষ্টা করুন।' ) ); }
	wp_mail( get_option( 'admin_email' ), "[$code] নতুন $type অনুরোধ", "$prod\nকারণ: $reason\nফোন: $phone\nঅর্ডার: " . ( $oid ?: '—' ) . "\n\n$desc\n\n" . admin_url( 'admin.php?page=hitscam-rma' ) );
	if ( $order && $order->get_billing_email() ) { wp_mail( $order->get_billing_email(), "[$code] আপনার $type অনুরোধ গৃহীত হয়েছে", "আপনার অনুরোধ নম্বর: $code\nআমরা যাচাই করে জানাব।" ); }
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hc_rma WHERE code=%s", $code ) ); // phpcs:ignore
	wp_send_json_success( array( 'code' => $code, 'html' => hitscam_a2_rma_card( $row ) ) );
}

add_action( 'wp_ajax_hc_rma_find', 'hitscam_a2_ajax_rma_find' );
add_action( 'wp_ajax_nopriv_hc_rma_find', 'hitscam_a2_ajax_rma_find' );
function hitscam_a2_ajax_rma_find() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! hitscam_a2_limit( 'rmaf', 20, 600 ) ) { wp_send_json_error( array( 'msg' => 'কিছুক্ষণ পরে চেষ্টা করুন।' ) ); }
	global $wpdb;
	$code = strtoupper( sanitize_text_field( wp_unslash( $_POST['rid'] ?? '' ) ) );
	$ph   = hitscam_normalize_bd_phone( wp_unslash( $_POST['rphone'] ?? '' ) );
	$row  = $ph ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hc_rma WHERE code=%s AND phone=%s", $code, $ph ) ) : null; // phpcs:ignore
	if ( ! $row ) { wp_send_json_error( array( 'msg' => 'এই নম্বর ও ফোনে কোনো অনুরোধ পাওয়া যায়নি।' ) ); }
	wp_send_json_success( array( 'html' => hitscam_a2_rma_card( $row ) ) );
}

/* ---------- অ্যাডমিন ---------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'RMA অনুরোধ', 'RMA অনুরোধ', 'manage_woocommerce', 'hitscam-rma', 'hitscam_a2_rma_admin' );
} );
add_action( 'admin_post_hc_rma_stage', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'অনুমতি নেই' ); }
	check_admin_referer( 'hc_rma_stage' );
	global $wpdb;
	$id = absint( $_POST['id'] ?? 0 ); $st = max( 0, min( 4, absint( $_POST['stage'] ?? 0 ) ) );
	$wpdb->update( $wpdb->prefix . 'hc_rma', array( 'stage' => $st ), array( 'id' => $id ) );
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hc_rma WHERE id=%d", $id ) ); // phpcs:ignore
	if ( $r && $r->order_id && ( $o = wc_get_order( $r->order_id ) ) && $o->get_billing_email() ) {
		$s = hitscam_a2_rma_stages();
		wp_mail( $o->get_billing_email(), "[{$r->code}] অনুরোধের অবস্থা: " . $s[ $st ], "আপনার {$r->rtype} অনুরোধ ({$r->code}) এখন: " . $s[ $st ] );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=hitscam-rma&done=1' ) );
	exit;
} );
function hitscam_a2_rma_admin() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hc_rma ORDER BY id DESC LIMIT 200" ); // phpcs:ignore
	$st   = hitscam_a2_rma_stages();
	echo '<div class="wrap"><h1>RMA অনুরোধ</h1><table class="widefat striped"><thead><tr><th>কোড</th><th>তারিখ</th><th>অর্ডার</th><th>ফোন</th><th>ধরন</th><th>পণ্য / কারণ</th><th>ছবি</th><th>অবস্থা</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><b>' . esc_html( $r->code ) . '</b></td><td>' . esc_html( $r->created ) . '</td><td>' . ( $r->order_id ? '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $r->order_id . '&action=edit' ) ) . '">#' . (int) $r->order_id . '</a>' : '—' ) . '</td><td>' . esc_html( $r->phone ) . '</td><td>' . esc_html( $r->rtype ) . '</td><td>' . esc_html( $r->product ) . '<br><small>' . esc_html( $r->reason ) . ' — ' . esc_html( $r->descr ) . '</small></td><td>';
		foreach ( (array) json_decode( (string) $r->photos, true ) as $u ) { echo '<a href="' . esc_url( $u ) . '" target="_blank"><img src="' . esc_url( $u ) . '" width="40" height="40" style="object-fit:cover;margin-right:3px"></a>'; }
		echo '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'hc_rma_stage' );
		echo '<input type="hidden" name="action" value="hc_rma_stage"><input type="hidden" name="id" value="' . (int) $r->id . '"><select name="stage">';
		foreach ( $st as $i => $s ) { echo '<option value="' . (int) $i . '"' . selected( (int) $r->stage, $i, false ) . '>' . esc_html( $s ) . '</option>'; }
		echo '</select> <button class="button">হালনাগাদ</button></form></td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="8">কোনো অনুরোধ নেই।</td></tr>'; }
	echo '</tbody></table></div>';
}
