<?php
/**
 * শপ / ক্যাটেগরি আর্কাইভ: বাম ফিল্টার (দাম, রেটিং, স্টক, ক্যাটেগরি) + গ্রিড।
 * ফিল্টার GET প্যারামিটার: min_price, max_price, rating_filter, instock, product_cat — WooCommerce কোর কোয়েরি নিজেই প্রয়োগ করে।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;
get_header( 'shop' );
global $wpdb;
$bounds = $wpdb->get_row( "SELECT FLOOR(MIN(min_price)) AS lo, CEIL(MAX(max_price)) AS hi FROM {$wpdb->wc_product_meta_lookup} WHERE min_price > 0" ); // phpcs:ignore
$pmin   = $bounds && $bounds->lo ? (int) floor( $bounds->lo / 50 ) * 50 : 0;
$pmax   = $bounds && $bounds->hi ? (int) ceil( $bounds->hi / 100 ) * 100 : 10000;
$cur_min = isset( $_GET['min_price'] ) ? max( $pmin, (int) $_GET['min_price'] ) : $pmin; // phpcs:ignore
$cur_max = isset( $_GET['max_price'] ) ? min( $pmax, (int) $_GET['max_price'] ) : $pmax; // phpcs:ignore
$rate    = isset( $_GET['rating_filter'] ) ? (int) $_GET['rating_filter'] : 0; // phpcs:ignore
$instock = ! empty( $_GET['instock'] ); // phpcs:ignore
$base    = is_product_category() || is_product_tag() ? get_term_link( get_queried_object() ) : wc_get_page_permalink( 'shop' );
$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
$title   = is_search() ? 'খোঁজ: ' . get_search_query() : ( is_shop() ? 'সব প্রোডাক্ট' : single_term_title( '', false ) );
$desc    = is_shop() ? '' : term_description();
?>
<main id="main" class="container pb-5"><?php hitscam_breadcrumbs(); ?>
<div class="page-banner mb-4"><h1 class="page-title mb-1"><?php echo esc_html( $title ); ?></h1><?php if ( $desc ) : ?><div class="mb-0 opacity-75"><?php echo wp_kses_post( $desc ); ?></div><?php endif; ?></div>
<div class="row g-4">
	<aside class="col-lg-3">
		<button class="btn btn-soft w-100 d-lg-none mb-2" data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters"><i class="bi bi-funnel"></i> ফিল্টার</button>
		<div id="filters" class="collapse d-lg-block">
			<form class="filter-box" method="get" action="<?php echo esc_url( $base ); ?>" id="hcFilter">
				<?php if ( is_search() ) : ?><input type="hidden" name="s" value="<?php echo esc_attr( get_search_query() ); ?>"><input type="hidden" name="post_type" value="product"><?php endif; ?>
				<?php if ( isset( $_GET['orderby'] ) ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) ); ?>"><?php endif; // phpcs:ignore ?>
				<div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold m-0">ফিল্টার</h2><a class="btn btn-sm btn-link text-brand p-0" href="<?php echo esc_url( $base ); ?>">রিসেট</a></div>
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?><div class="mb-4"><h3 class="h6">ক্যাটাগরি</h3>
					<?php foreach ( $cats as $c ) : ?><div class="form-check"><a class="<?php echo is_product_category( $c->slug ) ? 'fw-bold text-brand' : ''; ?>" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <small class="text-muted">(<?php echo esc_html( hitscam_bn_digits( $c->count ) ); ?>)</small></a></div><?php endforeach; ?></div><?php endif; ?>
				<div class="mb-4"><h3 class="h6">মূল্য (৳) <small class="text-muted fw-normal" id="fPriceLbl"></small></h3>
					<div class="range2" id="fPriceRange" data-min="<?php echo (int) $pmin; ?>" data-max="<?php echo (int) $pmax; ?>"><div class="r2-track"><div class="r2-fill"></div></div>
						<input type="range" id="fMinR" min="<?php echo (int) $pmin; ?>" max="<?php echo (int) $pmax; ?>" step="50" value="<?php echo (int) $cur_min; ?>" aria-label="সর্বনিম্ন মূল্য">
						<input type="range" id="fMaxR" min="<?php echo (int) $pmin; ?>" max="<?php echo (int) $pmax; ?>" step="50" value="<?php echo (int) $cur_max; ?>" aria-label="সর্বোচ্চ মূল্য"></div>
					<div class="input-group input-group-sm mt-2"><span class="input-group-text">৳</span><input type="number" class="form-control" name="min_price" id="fMin" min="<?php echo (int) $pmin; ?>" max="<?php echo (int) $pmax; ?>" step="50" value="<?php echo (int) $cur_min; ?>" aria-label="সর্বনিম্ন"><span class="input-group-text">–</span><input type="number" class="form-control" name="max_price" id="fMax" min="<?php echo (int) $pmin; ?>" max="<?php echo (int) $pmax; ?>" step="50" value="<?php echo (int) $cur_max; ?>" aria-label="সর্বোচ্চ"></div></div>
				<div class="mb-4"><h3 class="h6"><label for="fRate">রেটিং</label></h3><select class="form-select form-select-sm" name="rating_filter" id="fRate"><option value="">সব</option><?php for ( $i = 4; $i >= 1; $i-- ) : ?><option value="<?php echo (int) $i; ?>" <?php selected( $rate, $i ); ?>><?php echo esc_html( hitscam_bn_digits( $i ) ); ?>★ ও তার বেশি</option><?php endfor; ?></select></div>
				<div class="mb-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="instock" value="1" id="fStock" <?php checked( $instock ); ?>><label class="form-check-label" for="fStock">শুধু স্টকে আছে</label></div></div>
				<button class="btn btn-brand w-100" type="submit">প্রয়োগ করুন</button>
			</form>
		</div>
	</aside>
	<section class="col-lg-9">
		<?php if ( woocommerce_product_loop() ) : ?>
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><?php woocommerce_result_count(); woocommerce_catalog_ordering(); ?></div>
			<div class="row g-3">
			<?php
			while ( have_posts() ) {
				the_post();
				echo '<div class="col-12 col-sm-6 col-md-4">';
				wc_get_template_part( 'content', 'product' );
				echo '</div>';
			}
			?>
			</div>
			<div class="mt-4"><?php woocommerce_pagination(); ?></div>
		<?php else : ?>
			<div class="panel text-center py-5"><i class="bi bi-search fs-1 text-muted"></i><p class="mt-2 mb-3">কোনো প্রোডাক্ট পাওয়া যায়নি।</p><a class="btn btn-brand" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">সব প্রোডাক্ট দেখুন</a></div>
		<?php endif; ?>
	</section>
</div>
<?php hitscam_place( is_product_category() ? 'category' : 'shop' ); ?>
</main>
<?php get_footer( 'shop' ); ?>
