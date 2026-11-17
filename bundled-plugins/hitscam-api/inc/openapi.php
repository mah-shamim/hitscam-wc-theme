<?php
/**
 * OpenAPI 3.0 স্পেক (Postman/Swagger/কোডজেন-এ ইমপোর্ট করা যায়)।
 */
defined( 'ABSPATH' ) || exit;

function hcapi_openapi() {
	$sec = array( array( 'bearer' => array() ) );
	$q   = fn( $n, $t = 'string', $d = '' ) => array( 'name' => $n, 'in' => 'query', 'schema' => array( 'type' => $t ), 'description' => $d );
	$pg  = array( $q( 'page', 'integer' ), $q( 'per_page', 'integer', 'সর্বোচ্চ ১০০' ) );
	$ok  = array( '200' => array( 'description' => 'OK' ) );
	$body = fn( $d ) => array( 'required' => true, 'content' => array( 'application/json' => array( 'schema' => array( 'type' => 'object', 'description' => $d ) ) ) );
	$paths = array(
		'/ping'            => array( 'get' => array( 'summary' => 'হেলথ চেক (অথ লাগে না)', 'responses' => $ok ) ),
		'/me'              => array( 'get' => array( 'summary' => 'বর্তমান key-র স্কোপ', 'security' => $sec, 'responses' => $ok ) ),
		'/products'        => array(
			'get'  => array( 'summary' => 'পণ্য তালিকা [products:read]', 'security' => $sec, 'parameters' => array_merge( $pg, array( $q( 'search' ), $q( 'category', 'string', 'id বা slug' ), $q( 'min_price', 'number' ), $q( 'max_price', 'number' ), $q( 'stock_status' ), $q( 'sku' ), $q( 'orderby', 'string', 'date|price|title|modified|sales' ), $q( 'order' ), $q( 'modified_after', 'string', 'ISO তারিখ (ইনক্রিমেন্টাল সিংক)' ) ) ), 'responses' => $ok ),
			'post' => array( 'summary' => 'পণ্য তৈরি [products:write]', 'security' => $sec, 'requestBody' => $body( 'name, regular_price, sale_price, sku, categories[], images[{src}], stock_quantity…' ), 'responses' => array( '201' => array( 'description' => 'Created' ) ) ),
		),
		'/products/{id}'   => array(
			'get'    => array( 'summary' => 'একটি পণ্য [products:read]', 'security' => $sec, 'responses' => $ok ),
			'put'    => array( 'summary' => 'পণ্য আপডেট [products:write]', 'security' => $sec, 'requestBody' => $body( 'যে ফিল্ড বদলাতে চান' ), 'responses' => $ok ),
			'delete' => array( 'summary' => 'পণ্য ডিলিট [products:write] (?force=true হলে স্থায়ী)', 'security' => $sec, 'responses' => $ok ),
		),
		'/products/{id}/stock' => array( 'post' => array( 'summary' => 'স্টক আপডেট [stock:write]', 'security' => $sec, 'requestBody' => $body( '{quantity:int, operation:set|increase|decrease}' ), 'responses' => $ok ) ),
		'/categories'      => array( 'get' => array( 'summary' => 'ক্যাটাগরি [products:read]', 'security' => $sec, 'responses' => $ok ) ),
		'/orders'          => array(
			'get'  => array( 'summary' => 'অর্ডার তালিকা [orders:read]', 'security' => $sec, 'parameters' => array_merge( $pg, array( $q( 'status', 'string', 'কমা-সেপারেটেড' ), $q( 'customer', 'integer' ), $q( 'after', 'string' ) ) ), 'responses' => $ok ),
			'post' => array( 'summary' => 'অর্ডার তৈরি [orders:write]', 'security' => $sec, 'requestBody' => $body( '{line_items:[{product_id,variation_id?,quantity}], billing{}, shipping{}, payment_method, status, set_paid, coupon_lines[], shipping_lines[]}' ), 'responses' => array( '201' => array( 'description' => 'Created' ) ) ),
		),
		'/orders/{id}'     => array(
			'get' => array( 'summary' => 'একটি অর্ডার [orders:read]', 'security' => $sec, 'responses' => $ok ),
			'put' => array( 'summary' => 'স্ট্যাটাস/নোট আপডেট [orders:write]', 'security' => $sec, 'requestBody' => $body( '{status, note, customer_note}' ), 'responses' => $ok ),
		),
		'/customers'       => array( 'get' => array( 'summary' => 'কাস্টমার [customers:read]', 'security' => $sec, 'parameters' => array_merge( $pg, array( $q( 'search' ) ) ), 'responses' => $ok ) ),
		'/customers/{id}'  => array( 'get' => array( 'summary' => 'একজন কাস্টমার [customers:read]', 'security' => $sec, 'responses' => $ok ) ),
		'/coupons'         => array( 'get' => array( 'summary' => 'কুপন [coupons:read]', 'security' => $sec, 'responses' => $ok ) ),
		'/reports/sales'   => array( 'get' => array( 'summary' => 'সেলস রিপোর্ট [reports:read]', 'security' => $sec, 'parameters' => array( $q( 'from', 'string', 'YYYY-MM-DD' ), $q( 'to', 'string', 'YYYY-MM-DD' ) ), 'responses' => $ok ) ),
		'/webhooks'        => array(
			'get'  => array( 'summary' => 'ওয়েবহুক তালিকা [webhooks:manage]', 'security' => $sec, 'responses' => $ok ),
			'post' => array( 'summary' => 'ওয়েবহুক তৈরি [webhooks:manage]', 'security' => $sec, 'requestBody' => $body( '{name,url,events[],secret?}' ), 'responses' => array( '201' => array( 'description' => 'Created (secret শুধু এখানে)' ) ) ),
		),
		'/webhooks/{id}'   => array( 'delete' => array( 'summary' => 'ওয়েবহুক ডিলিট [webhooks:manage]', 'security' => $sec, 'responses' => $ok ) ),
		'/events'          => array( 'get' => array( 'summary' => 'সাপোর্টেড ওয়েবহুক ইভেন্ট', 'security' => $sec, 'responses' => $ok ) ),
	);
	return array(
		'openapi'    => '3.0.3',
		'info'       => array( 'title' => get_bloginfo( 'name' ) . ' — HitsCam API', 'version' => HCAPI_VERSION, 'description' => 'Authorization: Bearer <API key> অথবা X-HitsCam-Key। ওয়েবহুক সাইন: X-HitsCam-Signature = sha256=HMAC_SHA256(secret, timestamp + "." + body)।' ),
		'servers'    => array( array( 'url' => untrailingslashit( rest_url( 'hitscam/v1' ) ) ) ),
		'components' => array( 'securitySchemes' => array( 'bearer' => array( 'type' => 'http', 'scheme' => 'bearer' ) ) ),
		'paths'      => $paths,
	);
}
