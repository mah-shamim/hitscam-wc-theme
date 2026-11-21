<?php
/**
 * মিনি-কার্ড: HitsCam টেমপ্লেটের .cart-line মার্কআপ।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

if ( WC()->cart->is_empty() ) : ?>
	<div class="text-center text-muted py-5"><i class="bi bi-bag-x fs-1"></i><p class="mt-2">আপনার কার্ট খালি</p><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="btn btn-brand">কেনাকাটা শুরু করুন</a></div>
<?php else :
	foreach ( WC()->cart->get_cart() as $key => $it ) :
		$p = $it['data'];
		if ( ! $p || ! $p->exists() || $it['quantity'] <= 0 ) {
			continue;
		}
		$pid   = $it['product_id'];
		$link  = $p->is_visible() ? $p->get_permalink( $it ) : '';
		$reg   = (float) $p->get_regular_price();
		$sale  = (float) $p->get_price();
		$label = $p->is_type( 'variation' ) ? wc_get_formatted_variation( $p, true, false, true ) : '';
		?>
	<div class="cart-line" data-key="<?php echo esc_attr( $key ); ?>" data-pid="<?php echo (int) $pid; ?>" data-vid="<?php echo (int) $it['variation_id']; ?>">
		<a href="<?php echo esc_url( $link ); ?>"><?php echo wp_kses_post( $p->get_image( array( 62, 62 ) ) ); ?></a>
		<div class="flex-grow-1">
			<div class="nm"><?php echo esc_html( $p->get_name() ); ?><?php echo $label ? ' <small class="text-muted">(' . esc_html( $label ) . ')</small>' : ''; ?></div>
			<div class="pr"><?php if ( $reg > $sale ) : ?><del><?php echo wp_kses_post( wc_price( $reg ) ); ?></del><?php endif; ?><b><?php echo wp_kses_post( wc_price( $sale ) ); ?></b></div>
			<div class="mt-1 d-flex align-items-center gap-2">
				<div class="qty"><button type="button" class="qty-btn" data-key="<?php echo esc_attr( $key ); ?>" data-d="-1" aria-label="কমান">−</button><span><?php echo (int) $it['quantity']; ?></span><button type="button" class="qty-btn" data-key="<?php echo esc_attr( $key ); ?>" data-d="1" aria-label="বাড়ান">+</button></div>
				<button class="rm-btn" data-key="<?php echo esc_attr( $key ); ?>" data-d="-9999" type="button" aria-label="মুছুন"><i class="bi bi-trash3"></i></button>
			</div>
			<div class="line-acts"><button type="button" class="lnk-btn later-btn" data-key="<?php echo esc_attr( $key ); ?>"><i class="bi bi-clock-history"></i> পরে কিনব</button><button type="button" class="lnk-btn wish-move" data-key="<?php echo esc_attr( $key ); ?>"><i class="bi bi-heart"></i> উইশলিস্টে রাখুন</button></div>
		</div>
		<div class="fw-bold text-end"><?php echo wp_kses_post( wc_price( $sale * $it['quantity'] ) ); ?></div>
	</div>
	<?php endforeach;
	$msg = "আসসালামু আলাইকুম, আমি কার্টের এই পণ্যগুলো অর্ডার করতে চাই:\n";
	$i   = 0;
	foreach ( WC()->cart->get_cart() as $it ) {
		$msg .= ( ++$i ) . '. ' . $it['data']->get_name() . ' × ' . $it['quantity'] . "\n";
	}
	$msg .= 'মোট: ' . wp_strip_all_tags( WC()->cart->get_cart_subtotal() );
	?>
	<div class="mt-3 totals-box">
		<div class="d-flex justify-content-between"><span>সাবটোটাল</span><b><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></b></div>
		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
		<div class="d-flex justify-content-between text-success"><span><?php echo esc_html( $fee->name ); ?></span><b><?php echo wp_kses_post( wc_price( $fee->total ) ); ?></b></div>
		<?php endforeach; ?>
		<?php foreach ( WC()->cart->get_applied_coupons() as $code ) : ?>
		<div class="d-flex justify-content-between text-success"><span>কুপন: <?php echo esc_html( $code ); ?></span><b>−<?php echo wp_kses_post( wc_price( WC()->cart->get_coupon_discount_amount( $code ) ) ); ?></b></div>
		<?php endforeach; ?>
		<div class="small text-muted mt-1">ডেলিভারি চার্জ চেকআউটে যোগ হবে।</div>
	</div>
	<div class="d-grid gap-2 mt-3">
		<a class="btn btn-brand" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">Proceed to Checkout</a>
		<a class="btn btn-soft" href="<?php echo esc_url( wc_get_cart_url() ); ?>">কার্ট দেখুন</a>
		<a class="btn btn-wa-outline" target="_blank" rel="noopener" href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', hitscam_opt( 'whatsapp', '8801953073445' ) ) ); ?>?text=<?php echo rawurlencode( $msg ); ?>"><i class="bi bi-whatsapp"></i> WhatsApp এ কার্টের অর্ডার করুন</a>
	</div>
<?php endif; ?>
