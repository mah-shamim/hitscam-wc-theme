<?php
/** মাই-অ্যাকাউন্ট ড্যাশবোর্ড — গ্রিটিং, পরিসংখ্যান, দ্রুত লিংক, সাম্প্রতিক অর্ডার, রেফারেল */
defined( 'ABSPATH' ) || exit;
$u   = wp_get_current_user(); $uid = $u->ID;
$orders = wc_get_orders( array( 'customer_id' => $uid, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
$active = array_filter( $orders, fn( $o ) => $o->has_status( array( 'pending', 'processing', 'on-hold', 'shipped', 'out-for-delivery' ) ) );
$spent  = array_sum( array_map( fn( $o ) => $o->has_status( array( 'completed', 'processing', 'shipped' ) ) ? (float) $o->get_total() : 0, $orders ) );
$pts    = function_exists( 'hitscam_points' ) ? hitscam_points( $uid ) : 0;
$ref    = function_exists( 'hitscam_ref_code' ) ? hitscam_ref_code( $uid ) : '';
$bn     = fn( $n ) => hitscam_bn_digits( $n );
$h      = (int) current_time( 'G' ); $greet = $h < 12 ? 'শুভ সকাল' : ( $h < 17 ? 'শুভ অপরাহ্ন' : 'শুভ সন্ধ্যা' );
?>
<div class="acc-hero"><div><small><?php echo esc_html( $greet ); ?> 👋</small><h2 class="h4 fw-bold mb-1"><?php echo esc_html( $u->display_name ); ?></h2><span class="text-muted small">আপনার অর্ডার, পয়েন্ট ও সেবা এক জায়গায়।</span></div><a class="btn btn-brand rounded-pill" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><i class="bi bi-bag-plus"></i> কেনাকাটা করুন</a></div>
<div class="row g-3 my-1 acc-stats">
	<div class="col-6 col-lg-3"><div class="acc-stat"><i class="bi bi-bag-check"></i><b><?php echo esc_html( $bn( count( $orders ) ) ); ?></b><small>মোট অর্ডার</small></div></div>
	<div class="col-6 col-lg-3"><div class="acc-stat"><i class="bi bi-truck"></i><b><?php echo esc_html( $bn( count( $active ) ) ); ?></b><small>চলমান অর্ডার</small></div></div>
	<div class="col-6 col-lg-3"><div class="acc-stat"><i class="bi bi-coin"></i><b><?php echo esc_html( $bn( $pts ) ); ?></b><small>লয়ালটি পয়েন্ট</small></div></div>
	<div class="col-6 col-lg-3"><div class="acc-stat"><i class="bi bi-wallet2"></i><b><?php echo wp_kses_post( wc_price( $spent ) ); ?></b><small>মোট কেনাকাটা</small></div></div>
</div>
<div class="row g-3 mb-3">
	<div class="col-lg-8"><div class="panel h-100"><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-brand"></i> সাম্প্রতিক অর্ডার</h5><a class="small text-brand" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">সব দেখুন →</a></div>
		<?php if ( ! $orders ) : ?><p class="text-muted mb-0">এখনো কোনো অর্ডার নেই। <a class="text-brand" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">প্রথম অর্ডার করুন</a></p>
		<?php else : foreach ( array_slice( $orders, 0, 3 ) as $o ) : $first = current( $o->get_items() ); $pr = $first ? $first->get_product() : null; ?>
		<div class="acc-order"><div class="thumb"><?php echo $pr ? wp_kses_post( $pr->get_image( array( 56, 56 ) ) ) : ''; ?></div><div class="grow"><b>#<?php echo esc_html( $o->get_order_number() ); ?></b> <span class="status-pill"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span><div class="small text-muted"><?php echo esc_html( wc_format_datetime( $o->get_date_created(), 'j M Y' ) ); ?> · <?php echo esc_html( $bn( $o->get_item_count() ) ); ?> আইটেম · <?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></div></div>
			<div class="d-flex gap-1 flex-wrap"><a class="btn btn-soft btn-sm rounded-pill" href="<?php echo esc_url( add_query_arg( 'order', $o->get_id(), hitscam_page_url( 'track-order' ) ) ); ?>">ট্র্যাক</a><a class="btn btn-soft btn-sm rounded-pill" href="<?php echo esc_url( $o->get_view_order_url() ); ?>">বিস্তারিত</a></div></div>
		<?php endforeach; endif; ?></div></div>
	<div class="col-lg-4"><div class="panel h-100"><h5 class="fw-bold"><i class="bi bi-lightning-charge text-brand"></i> দ্রুত কাজ</h5>
		<div class="acc-quick"><a href="<?php echo esc_url( hitscam_page_url( 'track-order' ) ); ?>"><i class="bi bi-geo"></i> অর্ডার ট্র্যাক</a><a href="<?php echo esc_url( hitscam_page_url( 'rma' ) ); ?>"><i class="bi bi-arrow-repeat"></i> রিটার্ন / রিপেয়ার</a><a href="<?php echo esc_url( hitscam_page_url( 'warranty' ) ); ?>"><i class="bi bi-shield-check"></i> ওয়ারেন্টি চেক</a><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>"><i class="bi bi-geo-alt"></i> ঠিকানা</a><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><i class="bi bi-person-gear"></i> প্রোফাইল ও পাসওয়ার্ড</a><a href="<?php echo esc_url( hitscam_page_url( 'help' ) ); ?>"><i class="bi bi-headset"></i> সাহায্য</a></div></div></div>
</div>
<?php if ( $ref ) : $rt = 'HitsCam এ ' . $ref . ' কোড দিয়ে কেনাকাটায় ছাড় পান: ' . home_url( '/?ref=' . $ref ); ?>
<div class="panel d-flex gap-3 align-items-center flex-wrap"><div class="grow"><b><i class="bi bi-gift text-brand"></i> বন্ধুকে ছাড় দিন, নিজেও পয়েন্ট পান</b><div class="small text-muted">আপনার রেফারেল কোড: <b class="text-brand"><?php echo esc_html( $ref ); ?></b></div></div><a class="btn btn-success rounded-pill" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $rt ); ?>"><i class="bi bi-whatsapp"></i> শেয়ার করুন</a></div>
<?php endif;
do_action( 'woocommerce_account_dashboard' );
