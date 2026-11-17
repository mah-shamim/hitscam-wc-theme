<?php
/**
 * REST রুট: /wp-json/hitscam/v1/...
 */
defined( 'ABSPATH' ) || exit;

/* ---------------- ফরম্যাটার ---------------- */
function hcapi_format_product( WC_Product $p ) {
	$img = array();
	foreach ( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) as $iid ) {
		$img[] = array( 'id' => (int) $iid, 'src' => wp_get_attachment_url( $iid ) );
	}
	$cats = array();
	foreach ( (array) get_the_terms( $p->get_id(), 'product_cat' ) as $t ) {
		if ( $t && ! is_wp_error( $t ) ) {
			$cats[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
		}
	}
	$m = $p->get_date_modified();
	return array(
		'id' => $p->get_id(), 'parent_id' => $p->get_parent_id(), 'name' => $p->get_name(), 'slug' => $p->get_slug(),
		'permalink' => $p->get_permalink(), 'type' => $p->get_type(), 'status' => $p->get_status(), 'sku' => $p->get_sku(),
		'price' => $p->get_price(), 'regular_price' => $p->get_regular_price(), 'sale_price' => $p->get_sale_price(),
		'on_sale' => $p->is_on_sale(), 'currency' => get_woocommerce_currency(),
		'manage_stock' => $p->get_manage_stock(), 'stock_quantity' => $p->get_stock_quantity(), 'stock_status' => $p->get_stock_status(),
		'categories' => $cats, 'images' => $img,
		'short_description' => $p->get_short_description(), 'description' => $p->get_description(),
		'average_rating' => $p->get_average_rating(), 'rating_count' => $p->get_rating_count(), 'total_sales' => (int) $p->get_total_sales(),
		'variations' => $p->is_type( 'variable' ) ? array_map( 'intval', $p->get_children() ) : array(),
		'date_modified' => $m ? $m->date( 'c' ) : null,
	);
}

function hcapi_format_order( WC_Order $o ) {
	$items = array();
	foreach ( $o->get_items() as $it ) {
		$prod    = $it->get_product();
		$items[] = array(
			'id' => $it->get_id(), 'name' => $it->get_name(), 'product_id' => $it->get_product_id(), 'variation_id' => $it->get_variation_id(),
			'sku' => $prod ? $prod->get_sku() : '', 'quantity' => $it->get_quantity(), 'subtotal' => $it->get_subtotal(), 'total' => $it->get_total(),
		);
	}
	$ship = array();
	foreach ( $o->get_shipping_methods() as $s ) {
		$ship[] = array( 'method_title' => $s->get_method_title(), 'total' => $s->get_total() );
	}
	$d = $o->get_date_created();
	return array(
		'id' => $o->get_id(), 'number' => $o->get_order_number(), 'status' => $o->get_status(), 'currency' => $o->get_currency(),
		'total' => $o->get_total(), 'subtotal' => $o->get_subtotal(), 'discount_total' => $o->get_discount_total(), 'shipping_total' => $o->get_shipping_total(),
		'payment_method' => $o->get_payment_method(), 'payment_method_title' => $o->get_payment_method_title(), 'paid' => (bool) $o->get_date_paid(),
		'customer_id' => $o->get_customer_id(), 'customer_note' => $o->get_customer_note(),
		'billing' => $o->get_address( 'billing' ), 'shipping' => $o->get_address( 'shipping' ),
		'line_items' => $items, 'shipping_lines' => $ship, 'coupons' => $o->get_coupon_codes(),
		'date_created' => $d ? $d->date( 'c' ) : null,
	);
}

function hcapi_format_customer( WC_Customer $c ) {
	$d = $c->get_date_created();
	return array(
		'id' => $c->get_id(), 'email' => $c->get_email(), 'first_name' => $c->get_first_name(), 'last_name' => $c->get_last_name(),
		'username' => $c->get_username(), 'billing' => $c->get_billing(), 'shipping' => $c->get_shipping(),
		'orders_count' => $c->get_order_count(), 'total_spent' => $c->get_total_spent(), 'date_created' => $d ? $d->date( 'c' ) : null,
	);
}

/* ---------------- রুট ---------------- */
function hcapi_route( $path, $methods, $scope, $cb, $args = array() ) {
	register_rest_route( 'hitscam/v1', $path, array(
		'methods'             => $methods,
		'callback'            => function ( $req ) use ( $cb ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return new WP_Error( 'hcapi_wc', 'WooCommerce সক্রিয় নেই।', array( 'status' => 503 ) );
			}
			return call_user_func( $cb, $req );
		},
		'permission_callback' => $scope ? function ( $req ) use ( $scope ) { return hcapi_authorize( $req, $scope ); } : '__return_true',
		'args'                => $args,
	) );
}

function hcapi_paged( $items, $total, $per ) {
	$r = new WP_REST_Response( $items );
	$r->header( 'X-WP-Total', (string) $total );
	$r->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / max( 1, $per ) ) ) );
	return $r;
}

add_action( 'rest_api_init', function () {
	hcapi_route( '/ping', 'GET', '', fn() => array( 'ok' => true, 'version' => HCAPI_VERSION, 'wc' => WC()->version, 'time' => gmdate( 'c' ), 'currency' => get_woocommerce_currency() ) );
	hcapi_route( '/me', 'GET', '', function ( $req ) {
		$k = hcapi_find_key( hcapi_extract_key( $req ) );
		return $k ? array( 'name' => $k['name'], 'scopes' => $k['scopes'], 'rate_limit_per_min' => $k['rate'] ) : new WP_Error( 'hcapi_bad_key', 'API key সঠিক নয়।', array( 'status' => 401 ) );
	} );
	register_rest_route( 'hitscam/v1', '/openapi.json', array( 'methods' => 'GET', 'callback' => 'hcapi_openapi', 'permission_callback' => '__return_true' ) );

	/* ---- পণ্য ---- */
	hcapi_route( '/products', 'GET', 'products:read', 'hcapi_products_list' );
	hcapi_route( '/products/(?P<id>\d+)', 'GET', 'products:read', function ( $r ) {
		$p = wc_get_product( (int) $r['id'] );
		return $p ? hcapi_format_product( $p ) : new WP_Error( 'hcapi_404', 'পণ্য পাওয়া যায়নি।', array( 'status' => 404 ) );
	} );
	hcapi_route( '/products', 'POST', 'products:write', fn( $r ) => hcapi_product_save( $r, 0 ) );
	hcapi_route( '/products/(?P<id>\d+)', 'PUT,PATCH', 'products:write', fn( $r ) => hcapi_product_save( $r, (int) $r['id'] ) );
	hcapi_route( '/products/(?P<id>\d+)', 'DELETE', 'products:write', function ( $r ) {
		$p = wc_get_product( (int) $r['id'] );
		if ( ! $p ) { return new WP_Error( 'hcapi_404', 'পণ্য পাওয়া যায়নি।', array( 'status' => 404 ) ); }
		$p->delete( (bool) $r->get_param( 'force' ) );
		return array( 'deleted' => true, 'id' => (int) $r['id'] );
	} );
	hcapi_route( '/products/(?P<id>\d+)/stock', 'POST', 'stock:write', function ( $r ) {
		$p = wc_get_product( (int) $r['id'] );
		if ( ! $p ) { return new WP_Error( 'hcapi_404', 'পণ্য পাওয়া যায়নি।', array( 'status' => 404 ) ); }
		$qty = $r->get_param( 'quantity' );
		if ( null === $qty ) { return new WP_Error( 'hcapi_param', 'quantity লাগবে।', array( 'status' => 400 ) ); }
		$mode = 'set' === $r->get_param( 'operation' ) || null === $r->get_param( 'operation' ) ? 'set' : ( 'increase' === $r->get_param( 'operation' ) ? 'increase' : 'decrease' );
		if ( ! $p->get_manage_stock() ) { $p->set_manage_stock( true ); $p->save(); }
		wc_update_product_stock( $p, (int) $qty, $mode );
		return hcapi_format_product( wc_get_product( $p->get_id() ) );
	} );
	hcapi_route( '/categories', 'GET', 'products:read', function () {
		$out = array();
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
			$out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => $t->parent, 'count' => $t->count );
		}
		return $out;
	} );

	/* ---- অর্ডার ---- */
	hcapi_route( '/orders', 'GET', 'orders:read', function ( $r ) {
		$per = min( 100, max( 1, (int) ( $r['per_page'] ?: 20 ) ) );
		$a   = array( 'limit' => $per, 'paged' => max( 1, (int) $r['page'] ), 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' );
		if ( $r['status'] ) { $a['status'] = array_map( 'sanitize_key', explode( ',', $r['status'] ) ); }
		if ( $r['customer'] ) { $a['customer_id'] = (int) $r['customer']; }
		if ( $r['after'] ) { $a['date_created'] = '>=' . strtotime( $r['after'] ); }
		$res = wc_get_orders( $a );
		return hcapi_paged( array_map( 'hcapi_format_order', $res->orders ), $res->total, $per );
	} );
	hcapi_route( '/orders/(?P<id>\d+)', 'GET', 'orders:read', function ( $r ) {
		$o = wc_get_order( (int) $r['id'] );
		return $o instanceof WC_Order ? hcapi_format_order( $o ) : new WP_Error( 'hcapi_404', 'অর্ডার পাওয়া যায়নি।', array( 'status' => 404 ) );
	} );
	hcapi_route( '/orders', 'POST', 'orders:write', 'hcapi_order_create' );
	hcapi_route( '/orders/(?P<id>\d+)', 'PUT,PATCH', 'orders:write', function ( $r ) {
		$o = wc_get_order( (int) $r['id'] );
		if ( ! $o instanceof WC_Order ) { return new WP_Error( 'hcapi_404', 'অর্ডার পাওয়া যায়নি।', array( 'status' => 404 ) ); }
		if ( $r['status'] ) {
			$st = sanitize_key( $r['status'] );
			if ( ! array_key_exists( 'wc-' . $st, wc_get_order_statuses() ) ) { return new WP_Error( 'hcapi_status', 'অবৈধ স্ট্যাটাস।', array( 'status' => 400 ) ); }
			$o->update_status( $st, 'API: ' );
		}
		if ( $r['note'] ) { $o->add_order_note( sanitize_text_field( $r['note'] ), (bool) $r['customer_note'] ); }
		return hcapi_format_order( wc_get_order( $o->get_id() ) );
	} );

	/* ---- কাস্টমার, কুপন, রিপোর্ট ---- */
	hcapi_route( '/customers', 'GET', 'customers:read', function ( $r ) {
		$per = min( 100, max( 1, (int) ( $r['per_page'] ?: 20 ) ) );
		$q   = new WP_User_Query( array( 'role' => 'customer', 'number' => $per, 'paged' => max( 1, (int) $r['page'] ), 'search' => $r['search'] ? '*' . $r['search'] . '*' : '', 'count_total' => true ) );
		return hcapi_paged( array_map( fn( $u ) => hcapi_format_customer( new WC_Customer( $u->ID ) ), $q->get_results() ), $q->get_total(), $per );
	} );
	hcapi_route( '/customers/(?P<id>\d+)', 'GET', 'customers:read', function ( $r ) {
		$u = get_userdata( (int) $r['id'] );
		return $u ? hcapi_format_customer( new WC_Customer( $u->ID ) ) : new WP_Error( 'hcapi_404', 'কাস্টমার পাওয়া যায়নি।', array( 'status' => 404 ) );
	} );
	hcapi_route( '/coupons', 'GET', 'coupons:read', function () {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'shop_coupon', 'numberposts' => 100, 'post_status' => 'publish' ) ) as $p ) {
			$c = new WC_Coupon( $p->ID );
			$out[] = array( 'id' => $c->get_id(), 'code' => $c->get_code(), 'type' => $c->get_discount_type(), 'amount' => $c->get_amount(), 'usage_count' => $c->get_usage_count(), 'usage_limit' => $c->get_usage_limit(), 'expires' => $c->get_date_expires() ? $c->get_date_expires()->date( 'c' ) : null );
		}
		return $out;
	} );
	hcapi_route( '/reports/sales', 'GET', 'reports:read', function ( $r ) {
		$from = $r['from'] ? strtotime( $r['from'] ) : strtotime( '-30 days' );
		$to   = $r['to'] ? strtotime( $r['to'] . ' 23:59:59' ) : time();
		$os   = wc_get_orders( array( 'limit' => -1, 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ), 'date_created' => $from . '...' . $to ) );
		$days = array(); $tot = 0.0; $items = 0;
		foreach ( $os as $o ) {
			$d = $o->get_date_created()->date( 'Y-m-d' );
			$days[ $d ] = $days[ $d ] ?? array( 'date' => $d, 'orders' => 0, 'sales' => 0.0 );
			$days[ $d ]['orders']++;
			$days[ $d ]['sales'] += (float) $o->get_total();
			$tot   += (float) $o->get_total();
			$items += $o->get_item_count();
		}
		ksort( $days );
		return array( 'from' => gmdate( 'Y-m-d', $from ), 'to' => gmdate( 'Y-m-d', $to ), 'currency' => get_woocommerce_currency(), 'orders' => count( $os ), 'items_sold' => $items, 'gross_sales' => round( $tot, 2 ), 'average_order' => $os ? round( $tot / count( $os ), 2 ) : 0, 'by_day' => array_values( $days ) );
	} );

	/* ---- ওয়েবহুক ---- */
	hcapi_route( '/webhooks', 'GET', 'webhooks:manage', fn() => array_map( fn( $w ) => array_diff_key( $w, array( 'secret' => 1 ) ), array_values( hcapi_get_webhooks() ) ) );
	hcapi_route( '/webhooks', 'POST', 'webhooks:manage', function ( $r ) {
		$w = hcapi_save_webhook( $r->get_json_params() ?: $r->get_params() );
		return is_wp_error( $w ) ? $w : new WP_REST_Response( $w, 201 ); // secret শুধু তৈরির সময় ফেরত
	} );
	hcapi_route( '/webhooks/(?P<id>w\w+)', 'DELETE', 'webhooks:manage', function ( $r ) {
		hcapi_delete_webhook( $r['id'] );
		return array( 'deleted' => true );
	} );
	hcapi_route( '/events', 'GET', 'webhooks:manage', fn() => hcapi_webhook_events() );
} );

/* ---------------- হ্যান্ডলার ---------------- */
function hcapi_products_list( WP_REST_Request $r ) {
	$per  = min( 100, max( 1, (int) ( $r['per_page'] ?: 20 ) ) );
	$args = array( 'post_type' => 'product', 'posts_per_page' => $per, 'paged' => max( 1, (int) $r['page'] ), 'post_status' => $r['status'] ? sanitize_key( $r['status'] ) : 'publish', 'fields' => 'ids' );
	if ( $r['search'] ) { $args['s'] = sanitize_text_field( $r['search'] ); }
	if ( $r['category'] ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => is_numeric( $r['category'] ) ? 'term_id' : 'slug', 'terms' => $r['category'] ) );
	}
	$mq = array();
	if ( $r['min_price'] !== null || $r['max_price'] !== null ) {
		$mq[] = array( 'key' => '_price', 'value' => array( (float) ( $r['min_price'] ?: 0 ), (float) ( $r['max_price'] ?: 99999999 ) ), 'type' => 'NUMERIC', 'compare' => 'BETWEEN' );
	}
	if ( $r['stock_status'] ) { $mq[] = array( 'key' => '_stock_status', 'value' => sanitize_key( $r['stock_status'] ) ); }
	if ( $r['sku'] ) { $mq[] = array( 'key' => '_sku', 'value' => sanitize_text_field( $r['sku'] ) ); }
	if ( $mq ) { $args['meta_query'] = $mq; }
	if ( $r['modified_after'] ) { $args['date_query'] = array( array( 'column' => 'post_modified_gmt', 'after' => $r['modified_after'] ) ); }
	$map = array( 'price' => array( 'meta_value_num', '_price' ), 'date' => array( 'date', '' ), 'title' => array( 'title', '' ), 'modified' => array( 'modified', '' ), 'sales' => array( 'meta_value_num', 'total_sales' ) );
	$ob  = $map[ $r['orderby'] ] ?? $map['date'];
	$args['orderby'] = $ob[0];
	if ( $ob[1] ) { $args['meta_key'] = $ob[1]; }
	$args['order'] = 'asc' === strtolower( (string) $r['order'] ) ? 'ASC' : 'DESC';
	$q    = new WP_Query( $args );
	$out  = array();
	foreach ( $q->posts as $id ) {
		if ( $p = wc_get_product( $id ) ) { $out[] = hcapi_format_product( $p ); }
	}
	return hcapi_paged( $out, $q->found_posts, $per );
}

function hcapi_product_save( WP_REST_Request $r, $id ) {
	$d = $r->get_json_params() ?: $r->get_params();
	if ( $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) { return new WP_Error( 'hcapi_404', 'পণ্য পাওয়া যায়নি।', array( 'status' => 404 ) ); }
	} else {
		if ( empty( $d['name'] ) ) { return new WP_Error( 'hcapi_param', 'name লাগবে।', array( 'status' => 400 ) ); }
		$type = $d['type'] ?? 'simple';
		$p    = 'external' === $type ? new WC_Product_External() : new WC_Product_Simple();
	}
	$set = array(
		'name' => 'set_name', 'slug' => 'set_slug', 'status' => 'set_status', 'sku' => 'set_sku', 'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price',
		'description' => 'set_description', 'short_description' => 'set_short_description', 'manage_stock' => 'set_manage_stock', 'stock_quantity' => 'set_stock_quantity', 'stock_status' => 'set_stock_status',
	);
	foreach ( $set as $k => $fn ) {
		if ( array_key_exists( $k, $d ) ) {
			$v = $d[ $k ];
			if ( in_array( $k, array( 'description', 'short_description' ), true ) ) { $v = wp_kses_post( $v ); } elseif ( is_string( $v ) ) { $v = sanitize_text_field( $v ); }
			try { $p->$fn( $v ); } catch ( Exception $e ) { return new WP_Error( 'hcapi_param', $e->getMessage(), array( 'status' => 400 ) ); }
		}
	}
	if ( array_key_exists( 'stock_quantity', $d ) && ! array_key_exists( 'manage_stock', $d ) ) { $p->set_manage_stock( true ); }
	if ( isset( $d['categories'] ) ) {
		$ids = array();
		foreach ( (array) $d['categories'] as $c ) {
			if ( is_numeric( $c ) ) { $ids[] = (int) $c; } else {
				$t = term_exists( $c, 'product_cat' ) ?: wp_insert_term( $c, 'product_cat' );
				if ( ! is_wp_error( $t ) ) { $ids[] = (int) ( is_array( $t ) ? $t['term_id'] : $t ); }
			}
		}
		$p->set_category_ids( $ids );
	}
	if ( ! empty( $d['images'] ) && is_array( $d['images'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$ids = array();
		foreach ( $d['images'] as $im ) {
			if ( ! empty( $im['id'] ) ) { $ids[] = (int) $im['id']; continue; }
			if ( ! empty( $im['src'] ) ) {
				$aid = media_sideload_image( esc_url_raw( $im['src'] ), 0, null, 'id' );
				if ( ! is_wp_error( $aid ) ) { $ids[] = (int) $aid; }
			}
		}
		if ( $ids ) { $p->set_image_id( array_shift( $ids ) ); $p->set_gallery_image_ids( $ids ); }
	}
	try { $p->save(); } catch ( Exception $e ) { return new WP_Error( 'hcapi_save', $e->getMessage(), array( 'status' => 400 ) ); }
	return new WP_REST_Response( hcapi_format_product( wc_get_product( $p->get_id() ) ), $id ? 200 : 201 );
}

function hcapi_order_create( WP_REST_Request $r ) {
	$d = $r->get_json_params() ?: $r->get_params();
	if ( empty( $d['line_items'] ) || ! is_array( $d['line_items'] ) ) {
		return new WP_Error( 'hcapi_param', 'line_items লাগবে।', array( 'status' => 400 ) );
	}
	$order = wc_create_order( array( 'customer_id' => (int) ( $d['customer_id'] ?? 0 ) ) );
	if ( is_wp_error( $order ) ) { return $order; }
	foreach ( $d['line_items'] as $li ) {
		$prod = wc_get_product( (int) ( $li['variation_id'] ?? 0 ) ?: (int) ( $li['product_id'] ?? 0 ) );
		if ( ! $prod ) { $order->delete( true ); return new WP_Error( 'hcapi_item', 'পণ্য পাওয়া যায়নি: ' . wp_json_encode( $li ), array( 'status' => 400 ) ); }
		$order->add_product( $prod, max( 1, (int) ( $li['quantity'] ?? 1 ) ) );
	}
	foreach ( array( 'billing', 'shipping' ) as $t ) {
		if ( ! empty( $d[ $t ] ) && is_array( $d[ $t ] ) ) { $order->set_address( array_map( 'sanitize_text_field', $d[ $t ] ), $t ); }
	}
	if ( empty( $d['shipping'] ) && ! empty( $d['billing'] ) ) { $order->set_address( array_map( 'sanitize_text_field', $d['billing'] ), 'shipping' ); }
	foreach ( (array) ( $d['shipping_lines'] ?? array() ) as $sl ) {
		$it = new WC_Order_Item_Shipping();
		$it->set_method_title( sanitize_text_field( $sl['method_title'] ?? 'Shipping' ) );
		$it->set_total( (float) ( $sl['total'] ?? 0 ) );
		$order->add_item( $it );
	}
	foreach ( (array) ( $d['coupon_lines'] ?? array() ) as $code ) { $order->apply_coupon( sanitize_text_field( is_array( $code ) ? $code['code'] : $code ) ); }
	if ( ! empty( $d['payment_method'] ) ) { $order->set_payment_method( sanitize_key( $d['payment_method'] ) ); }
	if ( ! empty( $d['payment_method_title'] ) ) { $order->set_payment_method_title( sanitize_text_field( $d['payment_method_title'] ) ); }
	elseif ( ! empty( $d['payment_method'] ) && ( $gw = WC()->payment_gateways()->payment_gateways()[ sanitize_key( $d['payment_method'] ) ] ?? null ) ) { $order->set_payment_method_title( $gw->get_title() ); }
	if ( ! $order->get_billing_country() ) { $order->set_billing_country( WC()->countries->get_base_country() ); }
	if ( ! $order->get_shipping_country() ) { $order->set_shipping_country( $order->get_billing_country() ); }
	if ( ! empty( $d['note'] ) ) { $order->set_customer_note( sanitize_textarea_field( $d['note'] ) ); }
	$order->calculate_totals();
	$status = sanitize_key( $d['status'] ?? 'processing' );
	if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) { $status = 'pending'; }
	if ( ! empty( $d['set_paid'] ) ) { $order->payment_complete(); }
	$order->update_status( $status, 'API: ' );
	$order->save();
	return new WP_REST_Response( hcapi_format_order( wc_get_order( $order->get_id() ) ), 201 );
}
