<?php
/*
Plugin Name: WooCommerce Purchase Price and Profit on Sale
Description: Adds purchase price to WooCommerce products, calculates profit on sales, and exposes these in the API.
Version: 1.2
Author: Agbo Marcel Hillary
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add purchase price field to product general tab
function add_purchase_price_field() {
    woocommerce_wp_text_input(
        array(
            'id'          => '_purchase_price',
            'label'       => __( 'Purchase Price', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Enter the purchase price of the product.', 'woocommerce' ),
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            )
        )
    );
}
add_action( 'woocommerce_product_options_pricing', 'add_purchase_price_field' );

// Save the purchase price field
function save_purchase_price_field( $post_id ) {
    $purchase_price = isset( $_POST['_purchase_price'] ) ? sanitize_text_field( $_POST['_purchase_price'] ) : '';
    update_post_meta( $post_id, '_purchase_price', $purchase_price );
}
add_action( 'woocommerce_process_product_meta', 'save_purchase_price_field' );

// Add purchase price and profit to REST API response
function add_purchase_price_to_product_api( $response, $object, $request ) {
    if ( ! empty( $object ) && is_a( $object, 'WC_Product' ) ) {
        $purchase_price = $object->get_meta( '_purchase_price' );
        $response->data['purchase_price'] = $purchase_price;
    }
    return $response;
}
add_filter( 'woocommerce_rest_prepare_product_object', 'add_purchase_price_to_product_api', 10, 3 );

// Calculate profit when an order is created
function calculate_profit_on_order( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $purchase_price = $product->get_meta( '_purchase_price' );
        $sale_price = $item->get_total() / $item->get_quantity();

        if ( $purchase_price ) {
            $profit = floatval( $sale_price ) - floatval( $purchase_price );
            wc_add_order_item_meta( $item_id, '_profit', $profit );
        }
    }
}
add_action( 'woocommerce_checkout_create_order', 'calculate_profit_on_order', 20, 1 );

// Expose profit on sale in order item meta via REST API
function add_profit_to_order_item_api( $response, $item, $request ) {
    if ( ! empty( $item ) ) {
        $profit = wc_get_order_item_meta( $item->get_id(), '_profit', true );
        if ( $profit ) {
            $response->data['profit'] = $profit;
        }
    }
    return $response;
}
add_filter( 'woocommerce_rest_prepare_shop_order_item', 'add_profit_to_order_item_api', 10, 3 );


// Add custom columns to revenue analytics table
function custom_revenue_analytics_columns( $columns ) {
    $columns['purchase_price'] = __( 'Purchase Price', 'woocommerce' );
    $columns['profit'] = __( 'Profit', 'woocommerce' );
    return $columns;
}
add_filter( 'woocommerce_analytics_revenue_by_date_columns', 'custom_revenue_analytics_columns' );

// Populate custom columns with data
function populate_custom_revenue_analytics_columns( $output, $column_id, $data, $chart_args ) {
    if ( 'purchase_price' === $column_id ) {
        $order_id = $data['order_id'];
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return $output;
        }

        // Calculate total purchase price for the order
        $purchase_price_total = 0;
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $purchase_price = get_post_meta( $product_id, '_purchase_price', true );
            $quantity = $item->get_quantity();
            $purchase_price_total += ( $purchase_price * $quantity );
        }

        return wc_price( $purchase_price_total );
    } elseif ( 'profit' === $column_id ) {
        $order_id = $data['order_id'];
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return $output;
        }

        // Calculate total profit for the order
        $profit_total = 0;
        foreach ( $order->get_items() as $item ) {
            $profit = wc_get_order_item_meta( $item->get_id(), '_profit', true );
            if ( $profit ) {
                $profit_total += $profit;
            }
        }

        return wc_price( $profit_total );
    }
    return $output;
}
add_filter( 'woocommerce_analytics_revenue_by_date_output', 'populate_custom_revenue_analytics_columns', 10, 4 );


?>
