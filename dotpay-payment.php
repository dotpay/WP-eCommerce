<?php
/*
Plugin Name: Dotpay Payment for WP eCommerce
Version: 1.0
Description: Payment gateway for Wp eCommerce payments.
Text Domain: dotpay
 */

/**
 * Install function for Dotpay Payment plugin
 */
function dotpay_payment_activate() {

    $path       = plugin_dir_path( __FILE__ );
    $ecommerce  = $path . '/../wp-e-commerce/wpsc-merchants/';
    $image      = $path . '/../wp-e-commerce/images/';
    $lang_path  = $path . '/../../languages/plugins/';

    if (!is_dir($ecommerce)) {
        mkdir($ecommerce,0777,true);
    }

    if (!is_dir($image)) {
        mkdir($image,0777,true);
    }

    if (!is_dir($lang_path)) {
        mkdir($lang_path,0777,true);
    }

    if (!is_file($ecommerce . 'dotpay.php')) {
        copy($path . '/wp-e-commerce/wpsc-merchants/dotpay.php', $ecommerce . 'dotpay.php');
    }

    if (!is_file($image . 'dotpay.png')) {
        copy($path . '/wp-e-commerce/images/dotpay.png', $image . 'dotpay.png');
    }

    if (!is_file($lang_path . 'dotpay-pl_PL.po')) {
        copy($path . '/languages/dotpay-pl_PL.po', $lang_path . 'dotpay-pl_PL.po');
    }

    if (!is_file($lang_path . 'dotpay-pl_PL.mo')) {
        copy($path . '/languages/dotpay-pl_PL.mo', $lang_path . 'dotpay-pl_PL.mo');
    }

    dotpay_create_callback_page();
    dotpay_flush_rewrite_rules();
}

/**
 * Deactivate Dotpay plugin
 */
function dotpay_payment_deactivate() {
    dotpay_remove_callback_page();
}

/**
 * Create rewrite for dotpay/callback page;
 *
 * @return array
 */
function dotpay_rewrite() {
    global $wp_rewrite;

    $new_rule  = array(
        'dotpay/callback/?$' => 'index.php?page_id=' . get_option('dotpay_callback_page_id')
    );

    $wp_rewrite->rules = $new_rule + $wp_rewrite->rules;

    return $wp_rewrite->rules;
}

/**
 * Update rewrite object
 */
function dotpay_flush_rewrite_rules() {
    global $wp_rewrite;

    $wp_rewrite->flush_rules();
}

/**
 * Response for dotpay/callback
 */
function dotpay_callback_status() {
    global $wp, $wpsc_gateways;

    if( (int) $wp->query_vars["page_id"] == get_option('dotpay_callback_page_id')) {

        echo $wpsc_gateways['dotpay']['dotpay_callback_status'];

        die();
    }
}

/**
 * Create page for dotpay/callback
 */
function dotpay_create_callback_page() {
    $p = array(
        'post_title' => __('Dotpay Callback','dotpay'),
        'post_content' => '',
        'post_status' => 'publish',
        'post_type' => 'page',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_category' => array(1)
    );

    $the_page_id = wp_insert_post($p);

    add_option('dotpay_callback_page_id', $the_page_id);
}

/**
 * Remove dotpay/callback page
 */
function dotpay_remove_callback_page() {
    $the_page_id = get_option('dotpay_callback_page_id');

    if( $the_page_id ) {
        wp_delete_post($the_page_id);
        delete_option('dotpay_callback_page_id');
    }
}


//Register activate function
register_activation_hook( __FILE__, 'dotpay_payment_activate');
//Register deactivate function
register_deactivation_hook( __FILE__, 'dotpay_payment_deactivate');

//Create rewrite
add_action( 'generate_rewrite_rules', 'dotpay_rewrite' );
//Update rewrite
add_action( 'init', 'dotpay_flush_rewrite_rules' );
//Remove default template for callback
add_action("template_redirect", 'dotpay_callback_status');

//Load translation for plugin
load_plugin_textdomain('dotpay');