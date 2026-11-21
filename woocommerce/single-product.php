<?php
/**
 * প্রোডাক্ট পেজ — HitsCam টেমপ্লেটের product.html লেআউট।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	global $product;
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) {
		continue;
	}
	$pid      = $product->get_id();
	$vars     = hitscam_variations( $product );
	$imgs     = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
	$urls     = array_map( fn( $i ) => wp_get_attachment_image_url( $i, 'full' ), $imgs );
	$thumbs   = array_map( fn( $i ) => wp_get_attachment_image_url( $i, 'thumbnail' ), $imgs );
	if ( ! $urls ) {
		$urls   = array( wc_placeholder_img_src( 'full' ) );
		$thumbs = array( wc_placeholder_img_src( 'thumbnail' ) );
	}
	$cats     = get_the_terms( $pid, 'product_cat' );
	$cat      = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
	$in_stock = $product->is_in_stock();
	$grouped  = $product->is_type( 'grouped' );
	$buyable  = $grouped ? (bool) array_filter( $vars, fn( $v ) => $v['stock'] ) : ( $product->is_purchasable() && ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) );
	$ext      = $product->is_type( 'external' );
	$q_in     = hitscam_in_cart_qty( $pid );
	$wa       = preg_replace( '/\D/', '', hitscam_opt( 'whatsapp', '8801614747054' ) );
	$attrs    = array();
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_variation_attributes() as $an => $opts ) {
			$vals = array();
			foreach ( $opts as $o ) {
				$term   = taxonomy_exists( $an ) ? get_term_by( 'slug', $o, $an ) : null;
				$vals[] = array( 'v' => $o, 'n' => $term ? $term->name : $o );
			}
			$attrs[] = array( 'key' => 'attribute_' . sanitize_title( $an ), 'label' => wc_attribute_label( $an, $product ), 'values' => $vals );
		}
	}
	$low = 0;
	foreach ( $vars as $i => $v ) {
		if ( $v['stock'] && ( ! $vars[ $low ]['stock'] || $v['price'] < $vars[ $low ]['price'] ) ) {
			$low = $i;
		}
	}
	$ups = array_filter( array_map( 'wc_get_product', array_slice( $product->get_upsell_ids(), 0, 3 ) ), fn( $x ) => $x && $x->is_type( 'simple' ) && $x->is_in_stock() && $x->is_purchasable() );
	?>
<main id="main" class="container pb-5" data-pdp data-id="<?php echo (int) $pid; ?>" data-ext="<?php echo $ext ? 1 : 0; ?>" data-stock="<?php echo $in_stock ? 1 : 0; ?>" data-price="<?php echo esc_attr( (float) $product->get_price() ); ?>" data-name="<?php echo esc_attr( $product->get_name() ); ?>" data-wa="<?php echo esc_attr( $wa ); ?>" data-variable="<?php echo $vars ? 1 : 0; ?>">
	<?php hitscam_breadcrumbs(); ?>
	<script type="application/json" id="pdpVars"><?php echo wp_json_encode( array( 'vars' => $grouped ? array() : $vars, 'attrs' => $attrs, 'sel' => $low, 'grouped' => $grouped ? 1 : 0 ) ); ?></script>
	<div class="row g-4 mb-4">
		<div class="col-lg-6"><div class="pg-sticky">
			<div class="zoom-box" id="zoomBox" role="img" aria-label="<?php echo esc_attr( $product->get_name() ); ?>" data-imgs="<?php echo esc_attr( wp_json_encode( $urls ) ); ?>" style="background-image:url('<?php echo esc_url( $urls[0] ); ?>');background-size:cover;background-position:center"></div>
			<?php if ( count( $urls ) > 1 ) : ?>
			<div class="owl-carousel owl-theme mt-3" id="thumbs"><?php foreach ( $thumbs as $i => $t ) : ?><div class="thumb<?php echo 0 === $i ? ' active' : ''; ?>" data-i="<?php echo (int) $i; ?>"><img src="<?php echo esc_url( $t ); ?>" alt=""></div><?php endforeach; ?></div>
			<?php endif; ?>
		</div>
			<?php if ( $buyable && $in_stock && ! $ext ) : ?>
			<div class="pg-below mt-3">
			<div class="notify-box mb-3" id="pPriceAlert">
				<div class="fw-semibold mb-1"><i class="bi bi-bell text-brand"></i> দাম কমলে জানান</div>
				<div class="small text-muted mb-2">এখনকার দাম <?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?>। কমলে আপনার নাম্বারে WhatsApp/SMS এ জানানো হবে।</div>
				<form class="row g-2" id="priceAlertForm" data-type="price" novalidate><div class="col-7"><input class="form-control" name="phone" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" required></div><div class="col-5"><input class="form-control" name="target" type="number" min="1" placeholder="কাঙ্ক্ষিত দাম ৳"></div><div class="col-12"><button class="btn btn-brand w-100" type="submit">অ্যালার্ট চালু করুন</button></div></form>
				<div class="small text-success mt-1" id="priceOk" style="display:none"></div>
			</div>
			<div class="deliv-box" id="pDeliv">
				<div class="fw-semibold"><i class="bi bi-truck text-brand"></i> আনুমানিক ডেলিভারি</div>
				<div class="zones" role="group" aria-label="ডেলিভারি এলাকা"><button type="button" class="zone-btn" data-z="70">ঢাকার ভিতরে</button><button type="button" class="zone-btn" data-z="130">ঢাকার বাইরে</button></div>
				<div id="delivText" class="text-muted"></div>
			</div>
			<details class="emi-box" id="pEmi" style="display:none"><summary><i class="bi bi-credit-card-2-front text-brand"></i> ক্রেডিট কার্ড EMI: মাসে <b id="emiFrom"></b> থেকে <small class="text-muted">(দেখুন)</small></summary>
				<table class="emi-table"><thead><tr><th>মেয়াদ</th><th>মাসিক কিস্তি</th><th>মোট</th></tr></thead><tbody id="emiRows"></tbody></table><small class="text-muted">ডেমো হার। ব্যাংক ও কার্ড অনুযায়ী প্রসেসিং ফি পরিবর্তন হতে পারে।</small></details>
			</div>
			<?php endif; ?>
		</div>
		<div class="col-lg-6"><div class="pi-scroll">
			<span class="badge bg-dark mb-2" id="typeBadge"><?php echo esc_html( array( 'simple' => 'Simple Product', 'variable' => 'Variable Product', 'grouped' => 'Grouped Product', 'external' => 'External / Affiliate' )[ $product->get_type() ] ?? ucfirst( $product->get_type() ) ); ?></span>
			<h1 class="h3 fw-bold" id="pName"><?php the_title(); ?></h1>
			<div class="mb-2" id="pRating"><?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating() ) ); ?> <span class="text-muted">(<?php echo esc_html( hitscam_bn_digits( $product->get_review_count() ) ); ?> রিভিউ)</span></div>
			<div class="prod-price mb-2" id="pPrice"><?php if ( $grouped && $vars ) : $gl = $vars[ $low ]; ?><span class="fs-6 text-muted">থেকে </span><?php echo esc_html( hitscam_money( min( array_column( $vars, 'price' ) ) ) ); ?> <span class="fs-6 text-muted ms-2">| সিলেক্টেড: <b id="grpSel"><?php echo esc_html( hitscam_money( $gl['price'] ) ); ?></b></span><?php else : echo wp_kses_post( $product->get_price_html() ); endif; ?></div>
			<p class="text-muted" id="pShort"><?php echo wp_kses_post( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
			<?php if ( function_exists( 'hitscam_bulk_box' ) ) { hitscam_bulk_box(); } ?>
			<ul class="meta-list">
				<li><b>SKU:</b> <span id="pSku"><?php echo esc_html( $product->get_sku() ?: '—' ); ?></span></li>
				<?php if ( $cat ) : ?><li><b>ক্যাটাগরি:</b> <a class="text-brand" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li><?php endif; ?>
				<li id="pStock"><?php echo hitscam_stock_html( $product ); // phpcs:ignore ?></li>
			</ul>
			<?php if ( $grouped && $vars ) : ?>
			<div id="pOptions" class="mb-3"><?php echo hitscam_group_rows( $vars, $low ); // phpcs:ignore ?></div>
			<?php endif; ?>
			<?php if ( $attrs ) : ?>
			<div id="pOptions" class="mb-3"><div class="fw-semibold mb-2">অপশন বেছে নিন:</div><div class="attr-ui">
				<?php foreach ( $attrs as $a ) : ?>
				<div class="attr-row"><div class="attr-label"><?php echo esc_html( $a['label'] ); ?></div><div class="pc-group" style="--n:<?php echo 4 === count( $a['values'] ) ? 2 : min( count( $a['values'] ), 3 ); ?>">
					<?php foreach ( $a['values'] as $x ) : ?><label><span><input type="radio" class="form-check-input attr-radio me-1" name="at-<?php echo esc_attr( $a['key'] ); ?>" data-key="<?php echo esc_attr( $a['key'] ); ?>" value="<?php echo esc_attr( $x['v'] ); ?>"><?php echo esc_html( $x['n'] ); ?></span><b class="attr-price"></b></label><?php endforeach; ?>
				</div></div>
				<?php endforeach; ?>
			</div></div>
			<?php endif; ?>
			<?php if ( $ext ) : ?>
			<div class="mb-3"><a class="btn btn-brand btn-lg rounded-pill w-100" href="<?php echo esc_url( $product->get_product_url() ); ?>" target="_blank" rel="sponsored noopener"><i class="bi bi-box-arrow-up-right"></i> <?php echo esc_html( $product->single_add_to_cart_text() ); ?></a></div>
			<?php elseif ( $buyable && $in_stock ) : ?>
			<div id="pBuy" class="mb-3">
				<div class="qty qty-block mb-2" id="pQtyWrap"<?php echo $grouped ? ' hidden' : ''; ?>><button type="button" class="qty-btn" id="qMinus" aria-label="কমান">−</button><input type="number" id="pQty" value="1" min="1" aria-label="পরিমাণ"><button type="button" class="qty-btn" id="qPlus" aria-label="বাড়ান">+</button></div>
				<div class="buy-btns d-flex gap-2">
					<button class="btn btn-brand-outline btn-lg rounded-pill flex-fill p-add" type="button"><i class="bi bi-cart-plus"></i> অ্যাড টু কার্ট</button>
					<button class="btn btn-brand btn-lg rounded-pill flex-fill p-order" type="button"><i class="bi bi-lightning-charge-fill"></i> অর্ডার নাউ</button>
				</div>
				<button type="button" class="btn btn-wa-outline btn-lg rounded-pill w-100 mt-2" id="pWa"><i class="bi bi-whatsapp"></i> WhatsApp এ অর্ডার করুন</button>
				<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="in-cart-bar" id="pInCart" <?php echo $q_in ? '' : 'hidden'; ?>><span><i class="bi bi-check-circle-fill"></i> <b id="pInCartTxt"><?php echo $q_in ? 'এই পণ্য কার্টে আছে (' . esc_html( hitscam_bn_digits( $q_in ) ) . 'টি)' : ''; ?></b></span><span class="go">কার্ট দেখুন <i class="bi bi-arrow-right"></i></span></a>
			</div>
			<?php else : ?>
			<div class="notify-box" id="pNotify">
				<div class="fw-semibold mb-1"><i class="bi bi-bell text-brand"></i> স্টকে এলে জানান</div>
				<form class="d-flex gap-2" id="notifyForm" data-type="stock" novalidate><input class="form-control" name="phone" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" aria-label="ফোন নাম্বার" required><button class="btn btn-brand flex-shrink-0" type="submit">জানান</button></form>
				<div class="small text-success mt-1" id="notifyOk" style="display:none"></div>
			</div>
			<?php endif; ?>
			<div class="share-row mb-3"><span class="text-muted small">শেয়ার করুন:</span>
				<a class="share-btn" id="shFb" target="_blank" rel="noopener" aria-label="Facebook এ শেয়ার" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( get_permalink() ); ?>"><i class="bi bi-facebook"></i></a>
				<a class="share-btn" id="shWa" target="_blank" rel="noopener" aria-label="WhatsApp এ শেয়ার" href="https://wa.me/?text=<?php echo rawurlencode( get_the_title() . ' ' . get_permalink() ); ?>"><i class="bi bi-whatsapp"></i></a>
				<a class="share-btn" id="shMs" target="_blank" rel="noopener" aria-label="Messenger এ শেয়ার" href="https://www.facebook.com/dialog/send?link=<?php echo rawurlencode( get_permalink() ); ?>&redirect_uri=<?php echo rawurlencode( get_permalink() ); ?>"><i class="bi bi-messenger"></i></a>
				<button class="share-btn" id="shCopy" type="button" aria-label="লিংক কপি"><i class="bi bi-link-45deg"></i></button></div>
			<?php if ( $ups && $buyable && $in_stock ) : ?>
			<div class="addon-box" id="upsells"><h6><i class="bi bi-stars text-brand"></i> Frequently bought together</h6>
				<?php foreach ( $ups as $u ) : ?><label class="addon-item"><input type="checkbox" class="form-check-input up-chk" data-id="<?php echo (int) $u->get_id(); ?>"><span class="grow"><?php echo esc_html( $u->get_name() ); ?></span><b><?php echo wp_kses_post( wc_price( (float) $u->get_price() ) ); ?></b></label><?php endforeach; ?></div>
			<?php endif; ?>
		</div></div>
	</div>

	<?php hitscam_trust_row( 'product' ); ?>

	<div class="row g-3 mb-4">
		<div class="col-lg-6"><div class="tab-col"><h4>ওভারভিউ</h4><div id="overview"><?php echo wp_kses_post( apply_filters( 'the_content', get_the_content() ) ); ?></div></div></div>
		<div class="col-lg-6"><div class="tab-col"><h4>ডিটেলস</h4>
			<?php
			$rows = array();
			foreach ( $product->get_attributes() as $a ) {
				if ( ! is_object( $a ) || ! $a->get_visible() ) { continue; }
				$vals   = $a->is_taxonomy() ? wc_get_product_terms( $pid, $a->get_name(), array( 'fields' => 'names' ) ) : $a->get_options();
				$rows[] = array( wc_attribute_label( $a->get_name() ), implode( ' / ', array_map( 'wp_strip_all_tags', (array) $vals ) ) );
			}
			if ( $product->get_weight() ) { $rows[] = array( 'ওজন', $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' ) ); }
			if ( $product->has_dimensions() ) { $rows[] = array( 'মাপ', wc_format_dimensions( $product->get_dimensions( false ) ) ); }
			if ( $rows ) : ?><table class="table table-sm mb-0"><tbody><?php foreach ( $rows as $r ) : ?><tr><th style="width:40%"><?php echo esc_html( $r[0] ); ?></th><td><?php echo esc_html( $r[1] ); ?></td></tr><?php endforeach; ?></tbody></table>
			<?php elseif ( ! function_exists( 'hitscam_specs' ) || ! hitscam_specs( $pid ) ) : ?><p class="text-muted mb-0">স্পেসিফিকেশন যোগ করা হয়নি (প্রোডাক্ট এডিটে Attributes ট্যাব)।</p><?php endif; ?>
			<?php if ( function_exists( 'hitscam_specs_html' ) ) { echo hitscam_specs_html( $pid ); } // phpcs:ignore ?>
		</div></div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-lg-6"><div class="tab-col"><h4>রিভিউ (<span id="rvCount"><?php echo (int) $product->get_review_count(); ?></span>)</h4><div id="reviewList">
			<?php
			$rv = get_comments( array( 'post_id' => $pid, 'status' => 'approve', 'type' => 'review', 'number' => 20 ) );
			if ( ! $rv ) { echo '<p class="text-muted">এখনো কোনো রিভিউ নেই। প্রথম রিভিউ আপনিই দিন!</p>'; }
			foreach ( $rv as $c ) : $rate = (int) get_comment_meta( $c->comment_ID, 'rating', true ); ?>
				<div class="review-item"><div class="d-flex justify-content-between"><span><b><?php echo esc_html( $c->comment_author ); ?></b> <?php if ( wc_review_is_from_verified_owner( $c->comment_ID ) ) : ?><span class="rv-badge"><i class="bi bi-patch-check-fill"></i> ভেরিফাইড ক্রেতা</span><?php endif; ?></span><small class="text-muted"><?php echo esc_html( get_comment_date( '', $c ) ); ?></small></div>
				<?php echo wp_kses_post( wc_get_rating_html( $rate ) ); ?><p class="mb-0 mt-1"><?php echo esc_html( $c->comment_content ); ?></p></div>
			<?php endforeach; ?>
		</div></div></div>
		<div class="col-lg-6"><div class="tab-col"><h4>Add a review</h4>
			<?php if ( comments_open( $pid ) && 'yes' === get_option( 'woocommerce_enable_reviews' ) ) : ?>
			<form id="reviewForm" method="post" action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>">
				<div class="mb-2"><label class="form-label d-block">আপনার রেটিং *</label>
					<div class="rating-input"><?php foreach ( array( 5, 4, 3, 2, 1 ) as $n ) : ?><input type="radio" name="rating" id="r<?php echo (int) $n; ?>" value="<?php echo (int) $n; ?>"><label for="r<?php echo (int) $n; ?>">★</label><?php endforeach; ?></div></div>
				<div class="mb-3"><label class="form-label" for="rvText">আপনার রিভিউ *</label><textarea id="rvText" class="form-control" name="comment" rows="4" required></textarea></div>
				<div class="row g-2 mb-3"><div class="col-md-6"><input class="form-control" name="author" placeholder="নাম *" required value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"></div><div class="col-md-6"><input class="form-control" type="email" name="email" placeholder="ই-মেইল *" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"></div></div>
				<input type="hidden" name="comment_post_ID" value="<?php echo (int) $pid; ?>"><input type="hidden" name="comment_parent" value="0">
				<button class="btn btn-brand" type="submit">রিভিউ জমা দিন</button>
			</form>
			<?php else : ?><p class="text-muted mb-0">এই পণ্যে রিভিউ বন্ধ আছে।</p><?php endif; ?>
		</div></div>
	</div>

	<?php
	$faqs = get_posts( array( 'post_type' => 'hc_faq', 'numberposts' => 4, 'orderby' => 'menu_order date', 'order' => 'ASC' ) );
	if ( $faqs ) : $faq_page = get_page_by_path( 'faq' ); ?>
	<div class="panel my-4" id="pHelp"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 fw-bold m-0"><i class="bi bi-question-circle text-brand"></i> এই পণ্য নিয়ে সাধারণ প্রশ্ন</h2><?php if ( $faq_page ) : ?><a href="<?php echo esc_url( get_permalink( $faq_page ) ); ?>" class="small">সব প্রশ্ন →</a><?php endif; ?></div>
		<div class="accordion accordion-flush" id="pHelpAcc"><?php foreach ( $faqs as $i => $f ) : ?>
			<div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ph<?php echo (int) $i; ?>"><?php echo esc_html( get_the_title( $f ) ); ?></button></h3><div id="ph<?php echo (int) $i; ?>" class="accordion-collapse collapse" data-bs-parent="#pHelpAcc"><div class="accordion-body"><?php echo wp_kses_post( wpautop( $f->post_content ) ); ?></div></div></div>
		<?php endforeach; ?></div></div>
	<?php endif; ?>
	<?php hitscam_place( 'product' ); ?>
</main>
<?php if ( ! $ext && $buyable && $in_stock ) : ?>
<div class="buybar" id="buyBar" aria-hidden="true"><div class="bb-info"><div class="bb-name"><?php echo esc_html( wp_trim_words( get_the_title(), 7, '…' ) ); ?></div><div class="bb-price" id="bbPrice"></div></div><button class="btn btn-brand rounded-pill bb-btn" id="bbBtn" type="button">অর্ডার নাউ</button></div>
<?php endif; ?>
<?php endwhile; get_footer(); ?>
