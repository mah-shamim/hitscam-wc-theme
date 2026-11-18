<?php
defined( 'ABSPATH' ) || exit;
$phone = hitscam_opt( 'phone', '+880 1614-747054' );
$tel   = preg_replace( '/[^\d+]/', '', $phone );
$wa    = preg_replace( '/\D/', '', (string) hitscam_opt( 'whatsapp', '8801614747054' ) );
$wc    = hitscam_is_wc();
$ids   = (array) get_option( 'hitscam_page_ids', array() );
$link  = fn( $slug, $label ) => ! empty( $ids[ $slug ] ) ? '<a href="' . esc_url( get_permalink( $ids[ $slug ] ) ) . '">' . esc_html( $label ) . '</a>' : '';
?>
<footer class="site-footer"><div class="container"><div class="row g-4">
	<div class="col-lg-4"><div class="brand text-white mb-2"><span class="brand-mark"><i class="bi bi-camera-video-fill"></i></span><span><?php echo esc_html( hitscam_opt( 'brand_a', 'HITS' ) ); ?><b><?php echo esc_html( hitscam_opt( 'brand_b', 'Cam' ) ); ?></b></span></div>
		<p><?php echo esc_html( hitscam_opt( 'footer_txt' ) ); ?></p>
		<p class="mb-0"><i class="bi bi-telephone"></i> <?php echo esc_html( $phone ); ?><br><i class="bi bi-envelope"></i> <?php echo esc_html( hitscam_opt( 'email', 'support@hitscam.com' ) ); ?></p></div>
	<div class="col-6 col-lg-3"><h6>কোম্পানি</h6><a href="<?php echo esc_url( home_url( '/' ) ); ?>">হোম</a>
		<?php if ( $wc ) : ?><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">শপ</a><?php endif; ?>
		<?php echo $link( 'contact', 'যোগাযোগ' ) . $link( 'faq', 'FAQ ও হেল্প সেন্টার' ) . $link( 'compare', 'পণ্য তুলনা' ); // phpcs:ignore ?></div>
	<div class="col-6 col-lg-3"><h6>পলিসি ও সাপোর্ট</h6>
		<?php
		if ( $wc ) {
			$rp = (int) get_option( 'woocommerce_refund_returns_page_id' );
			$tp = (int) wc_terms_and_conditions_page_id();
			if ( $tp ) { echo '<a href="' . esc_url( get_permalink( $tp ) ) . '">শর্তাবলী</a>'; }
			if ( $rp ) { echo '<a href="' . esc_url( get_permalink( $rp ) ) . '">রিটার্ন ও রিফান্ড</a>'; }
			echo '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">আমার অ্যাকাউন্ট</a>';
		}
		echo $link( 'track-order', 'অর্ডার ট্র্যাক' ); // phpcs:ignore
		?></div>
	<div class="col-lg-2"><?php if ( has_nav_menu( 'footer2' ) ) { echo '<h6>আরও</h6>'; wp_nav_menu( array( 'theme_location' => 'footer2', 'container' => false, 'items_wrap' => '%3$s', 'walker' => new Hitscam_Plain_Walker() ) ); } ?></div></div>
	<div class="footer-bottom d-flex justify-content-between flex-wrap gap-2"><span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. সর্বস্বত্ব সংরক্ষিত।</span><span><?php echo esc_html( hitscam_opt( 'pay_txt' ) ); ?></span></div></div></footer>

<nav class="mobile-dock" aria-label="মোবাইল মেনু">
	<a class="dock-btn <?php echo is_front_page() ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="হোম"><i class="bi bi-house-door-fill"></i></a>
	<?php if ( $wc ) : ?>
	<a class="dock-btn <?php echo is_shop() ? 'active' : ''; ?>" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" aria-label="শপ"><i class="bi bi-grid-fill"></i></a>
	<button class="dock-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#miniCart" aria-label="কার্ট"><i class="bi bi-cart3"></i><span class="badge-count cart-count"><?php echo (int) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span></button>
	<?php endif; ?>
	<?php if ( ! empty( $ids['track-order'] ) ) : ?><a class="dock-btn" href="<?php echo esc_url( get_permalink( $ids['track-order'] ) ); ?>" aria-label="অর্ডার ট্র্যাক"><i class="bi bi-geo-alt-fill"></i></a><?php endif; ?>
	<a class="dock-btn call" href="tel:<?php echo esc_attr( $tel ); ?>" aria-label="কল করুন"><i class="bi bi-telephone-fill"></i></a></nav>

<div class="float-stack"><button class="fl-btn fl-top" id="toTop" type="button" aria-label="উপরে যান"><i class="bi bi-arrow-up"></i></button>
	<a class="fl-btn fl-wa" id="waChat" target="_blank" rel="noopener" href="https://wa.me/<?php echo esc_attr( $wa ); ?>?text=<?php echo rawurlencode( 'আসসালামু আলাইকুম, কিছু জানতে চাই।' ); ?>" aria-label="WhatsApp এ চ্যাট করুন"><i class="bi bi-whatsapp"></i></a></div>

<?php if ( $wc ) : ?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="miniCart" aria-labelledby="miniCartTitle"><div class="offcanvas-header border-bottom"><h5 class="offcanvas-title fw-bold" id="miniCartTitle">আপনার কার্ট (<span class="cart-count"><?php echo (int) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>)</h5><button class="btn-close" data-bs-dismiss="offcanvas" aria-label="বন্ধ"></button></div>
	<div class="offcanvas-body d-flex flex-column"><div class="fs-wrap"></div><div class="widget_shopping_cart_content flex-grow-1"><?php woocommerce_mini_cart(); ?></div><div id="hcLater"></div><div class="hc-incart-data" hidden data-ids="<?php echo esc_attr( wp_json_encode( (object) array_reduce( WC()->cart ? WC()->cart->get_cart() : array(), function ( $c, $i ) { $c[ (int) $i['product_id'] ] = ( $c[ (int) $i['product_id'] ] ?? 0 ) + (int) $i['quantity']; return $c; }, array() ) ) ); ?>"></div></div></div>
<?php endif; ?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="miniWish" aria-labelledby="miniWishTitle"><div class="offcanvas-header border-bottom"><h5 class="offcanvas-title fw-bold" id="miniWishTitle"><i class="bi bi-heart-fill text-brand"></i> উইশলিস্ট (<span class="wish-count">0</span>)</h5><button class="btn-close" data-bs-dismiss="offcanvas" aria-label="বন্ধ"></button></div>
	<div class="offcanvas-body"><div id="miniWishBody"></div></div></div>

<div class="modal fade" id="qvModal" tabindex="-1" aria-label="কুইক ভিউ" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><button class="btn-close qv-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button><div class="modal-body" id="qvBody"></div></div></div></div>

<div class="cmp-bar" id="cmpBar" role="region" aria-label="তুলনা"><span class="small fw-bold"><i class="bi bi-arrow-left-right text-brand"></i> তুলনা (<span id="cmpN">0</span>/3)</span><a class="btn btn-brand btn-sm" id="cmpGo" href="#">তুলনা করুন</a><button class="btn btn-soft btn-sm" id="cmpClear" type="button">মুছুন</button></div>
<div class="toast-wrap" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
