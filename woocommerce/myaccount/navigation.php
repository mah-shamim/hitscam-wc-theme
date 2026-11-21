<?php
/** মাই-অ্যাকাউন্ট নেভিগেশন — আইকনসহ সাইড/ট্যাব মেনু */
defined( 'ABSPATH' ) || exit;
$icons = array( 'dashboard' => 'bi-grid-1x2', 'orders' => 'bi-bag-check', 'downloads' => 'bi-download', 'edit-address' => 'bi-geo-alt', 'payment-methods' => 'bi-credit-card', 'edit-account' => 'bi-person-gear', 'customer-logout' => 'bi-box-arrow-right' );
do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="woocommerce-MyAccount-navigation acc-nav" aria-label="অ্যাকাউন্ট মেনু"><ul>
	<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
	<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>"><a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"><i class="bi <?php echo esc_attr( $icons[ $endpoint ] ?? 'bi-dot' ); ?>"></i> <span><?php echo esc_html( $label ); ?></span></a></li>
	<?php endforeach; ?>
	<li><a href="<?php echo esc_url( hitscam_page_url( 'rma' ) ); ?>"><i class="bi bi-arrow-repeat"></i> <span>রিটার্ন / RMA</span></a></li>
	<li><a href="<?php echo esc_url( hitscam_page_url( 'track-order' ) ); ?>"><i class="bi bi-geo"></i> <span>অর্ডার ট্র্যাক</span></a></li>
</ul></nav>
<?php do_action( 'woocommerce_after_account_navigation' ); ?>
