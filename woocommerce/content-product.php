<?php
/**
 * প্রোডাক্ট কার্ড (HitsCam টেমপ্লেটের .product-card মার্কআপ)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;
global $product;
if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_visible() ) {
	return;
}
$id   = $product->get_id();
$reg  = (float) $product->get_regular_price();
$sale = (float) $product->get_price();
$disc = ( $product->is_on_sale() && $reg > 0 && $product->is_type( 'simple' ) ) ? round( ( 1 - $sale / $reg ) * 100 ) : 0;
if ( $product->is_on_sale() && $product->is_type( 'variable' ) ) {
	$disc = 0;
	foreach ( $product->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( $v && (float) $v->get_regular_price() > 0 && $v->is_on_sale() ) {
			$disc = max( $disc, round( ( 1 - (float) $v->get_price() / (float) $v->get_regular_price() ) * 100 ) );
		}
	}
}
$cats = wc_get_product_category_list( $id, ', ', '', '' );
$sold = (int) $product->get_total_sales();
$can_ajax = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock();
?>
<div <?php wc_product_class( 'product-card', $product ); ?> data-id="<?php echo (int) $id; ?>">
	<?php if ( $disc > 0 ) : ?><span class="pc-badge">-<?php echo esc_html( hitscam_bn_digits( $disc ) ); ?>%</span><?php endif; ?>
	<button class="wish" type="button" aria-label="উইশলিস্ট" data-id="<?php echo (int) $id; ?>"><i class="bi bi-heart-fill"></i></button>
	<div class="pc-tools"><button class="pc-tool qv-btn" type="button" aria-label="কুইক ভিউ" title="কুইক ভিউ" data-id="<?php echo (int) $id; ?>"><i class="bi bi-eye"></i></button><button class="pc-tool cmp-btn" type="button" aria-label="তুলনা করুন" title="তুলনা করুন" data-id="<?php echo (int) $id; ?>"><i class="bi bi-arrow-left-right"></i></button></div>
	<a class="pc-img" href="<?php the_permalink(); ?>"><?php echo wp_kses_post( $product->get_image( 'hitscam-card', array( 'loading' => 'lazy' ) ) ); ?></a>
	<div class="pc-body">
		<?php if ( $cats ) : ?><div class="pc-cat"><?php echo wp_kses_post( strip_tags( $cats ) ); ?></div><?php endif; ?>
		<a class="pc-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		<div class="pc-rate"><?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating() ) ); ?> <span class="text-muted">(<?php echo esc_html( hitscam_bn_digits( $product->get_review_count() ) ); ?>)</span>
			<?php if ( $sold > 0 ) : ?><span class="pc-sold" title="টোটাল সোল্ড"><i class="bi bi-bag-check-fill"></i> <?php echo esc_html( hitscam_bn_digits( $sold ) ); ?> বিক্রি</span><?php endif; ?></div>
		<?php echo hitscam_stock_html( $product ); // phpcs:ignore ?>
		<div class="pc-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
		<div class="pc-actions">
			<?php if ( $can_ajax ) : ?>
				<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1" class="btn btn-brand btn-add add_to_cart_button ajax_add_to_cart" data-product_id="<?php echo (int) $id; ?>" data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?> কার্টে যোগ করুন" rel="nofollow">অ্যাড টু কার্ট</a>
			<?php elseif ( $product->is_in_stock() ) : ?>
				<a class="btn btn-brand btn-add" href="<?php the_permalink(); ?>"><?php echo esc_html( $product->is_type( 'variable' ) ? 'অপশন বাছাই করুন' : $product->add_to_cart_text() ); ?></a>
			<?php else : ?>
				<a class="btn btn-brand btn-add disabled" aria-disabled="true" href="<?php the_permalink(); ?>">স্টক শেষ</a>
			<?php endif; ?>
		</div>
	</div>
</div>
