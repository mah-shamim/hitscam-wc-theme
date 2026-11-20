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
<?php
$vars    = ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) ? hitscam_variations( $product ) : array();
$info    = $vars ? array() : hitscam_card_info( $product );
$in_cart = hitscam_in_cart_qty( $id );
$type_lb = array( 'simple' => 'Simple', 'variable' => 'Variable', 'grouped' => 'Grouped', 'external' => 'Affiliate' );
$lowest  = 0;
foreach ( $vars as $i => $v ) {
	if ( $v['stock'] && ( ! $vars[ $lowest ]['stock'] || $v['price'] < $vars[ $lowest ]['price'] ) ) {
		$lowest = $i;
	}
}
$buyable = $product->is_type( 'grouped' ) ? (bool) array_filter( $vars, fn( $v ) => $v['stock'] ) : ( ( $product->is_type( 'simple' ) || $vars ) && $product->is_purchasable() && $product->is_in_stock() );
?>
<div <?php wc_product_class( 'product-card', $product ); ?> data-id="<?php echo (int) $id; ?>" data-opt="<?php echo (int) $lowest; ?>">
	<?php if ( $disc > 0 ) : ?><span class="pc-badge">-<?php echo esc_html( hitscam_bn_digits( $disc ) ); ?>%</span><?php endif; ?>
	<button class="wish" type="button" aria-label="উইশলিস্ট" data-id="<?php echo (int) $id; ?>"><i class="bi bi-heart-fill"></i></button>
	<div class="pc-tools"><button class="pc-tool qv-btn" type="button" aria-label="কুইক ভিউ" title="কুইক ভিউ" data-id="<?php echo (int) $id; ?>"><i class="bi bi-eye"></i></button><button class="pc-tool cmp-btn" type="button" aria-label="তুলনা করুন" title="তুলনা করুন" data-id="<?php echo (int) $id; ?>"><i class="bi bi-arrow-left-right"></i></button></div>
	<a class="pc-img" href="<?php the_permalink(); ?>"><?php echo wp_kses_post( $product->get_image( 'hitscam-card', array( 'loading' => 'lazy' ) ) ); ?></a>
	<div class="pc-body">
		<div class="pc-cat"><?php echo wp_kses_post( strip_tags( (string) $cats ) ); ?><?php echo $cats ? ' · ' : ''; ?><span class="text-muted"><?php echo esc_html( $type_lb[ $product->get_type() ] ?? ucfirst( $product->get_type() ) ); ?></span></div>
		<?php if ( $product->is_type( 'grouped' ) ) : ?><span class="badge bg-dark type-badge">Grouped Product</span><?php endif; ?>
		<a class="pc-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		<div class="pc-rate"><?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating() ) ); ?> <span class="text-muted">(<?php echo esc_html( hitscam_bn_digits( $product->get_review_count() ) ); ?>)</span>
			<?php if ( $sold > 0 ) : ?><span class="pc-sold" title="টোটাল সোল্ড"><i class="bi bi-bag-check-fill"></i> <?php echo esc_html( hitscam_bn_digits( $sold ) ); ?> বিক্রি</span><?php endif; ?></div>
		<?php if ( $info ) : ?><ul class="pc-info"><?php foreach ( $info as $x ) : ?><li><i class="bi <?php echo esc_attr( $x[0] ); ?>"></i><span><?php echo esc_html( $x[1] ); ?></span></li><?php endforeach; ?></ul><?php endif; ?>
		<?php if ( $vars && $product->is_type( 'grouped' ) ) : echo hitscam_group_rows( $vars, $lowest, true ); // phpcs:ignore
		elseif ( $vars ) : $gname = 'opt-' . $id . '-' . wp_rand( 100, 999 ); ?>
		<div class="pc-group" style="--n:<?php echo 4 === count( $vars ) ? 2 : min( count( $vars ), 3 ); ?>">
			<?php foreach ( array_slice( $vars, 0, 6, true ) as $i => $v ) : ?>
			<label class="<?php echo $v['stock'] ? '' : 'na'; ?>"><span><input type="radio" class="form-check-input opt-radio me-1" name="<?php echo esc_attr( $gname ); ?>" value="<?php echo (int) $i; ?>" data-vid="<?php echo (int) $v['id']; ?>" data-grp="<?php echo ! empty( $v['grp'] ) ? 1 : 0; ?>" data-child="<?php echo (int) ( $v['child'] ?? 0 ); ?>" data-html="<?php echo esc_attr( $v['html'] ); ?>" <?php checked( $i, $lowest ); ?> <?php disabled( ! $v['stock'] ); ?>><?php echo esc_html( $v['label'] ); ?></span><b><?php echo esc_html( hitscam_bn_digits( wc_format_decimal( $v['price'], 0 ) ) ); ?>৳</b></label>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<?php echo hitscam_stock_html( $product ); // phpcs:ignore ?>
		<div class="pc-price"><?php echo wp_kses_post( $vars ? ( $product->is_type( 'grouped' ) ? '<span class="text-muted small">সিলেক্টেড: </span><b class="grp-sel">' . esc_html( hitscam_money( $vars[ $lowest ]['price'] ) ) . '</b>' : $vars[ $lowest ]['html'] ) : $product->get_price_html() ); ?></div>
		<div class="pc-actions <?php echo $buyable && $in_cart ? 'three' : ''; ?>">
			<?php if ( $buyable ) : ?>
				<button class="btn btn-brand btn-add hc-add" type="button" data-product_id="<?php echo (int) $id; ?>"><?php esc_html_e( 'অ্যাড টু কার্ট', 'hitscam' ); ?></button>
				<button class="btn btn-brand-outline btn-order hc-add" type="button" data-product_id="<?php echo (int) $id; ?>" data-buy-now="1">অর্ডার নাউ</button>
				<a class="btn btn-brand btn-view" href="<?php echo esc_url( wc_get_cart_url() ); ?>">ভিউ কার্ট</a>
			<?php elseif ( $product->is_type( 'external' ) ) : ?>
				<a class="btn btn-brand btn-ext" href="<?php echo esc_url( $product->get_product_url() ); ?>" target="_blank" rel="sponsored noopener"><i class="bi bi-box-arrow-up-right"></i> <?php echo esc_html( $product->single_add_to_cart_text() ); ?></a>
			<?php elseif ( $product->is_in_stock() ) : ?>
				<a class="btn btn-brand btn-ext" href="<?php the_permalink(); ?>"><?php esc_html_e( 'অপশন বাছাই করুন', 'hitscam' ); ?></a>
			<?php else : ?>
				<a class="btn btn-brand btn-ext disabled" aria-disabled="true" href="<?php the_permalink(); ?>">স্টক শেষ</a>
			<?php endif; ?>
		</div>
	</div>
</div>
