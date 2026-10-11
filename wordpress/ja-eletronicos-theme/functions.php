<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});
add_action('wp_enqueue_scripts', function () {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('ja-fonts', get_theme_file_uri('fonts.css'), array(), $version);
    wp_enqueue_style('ja-design', get_theme_file_uri('design.css'), array(), $version);
    wp_enqueue_style('ja-carousel-style', get_theme_file_uri('carousel.css'), array('ja-design'), $version);
    wp_enqueue_style('ja-woocommerce-style', get_theme_file_uri('woocommerce.css'), array('ja-design'), $version);
    if (!is_front_page()) { return; }
    $live = function_exists('ja_loja_ready') && ja_loja_ready();
    wp_enqueue_script('ja-carousel', get_theme_file_uri('carousel.js'), array(), $version, true);
    wp_enqueue_script('ja-commerce', get_theme_file_uri('commerce.js'), array(), $version, true);
    wp_enqueue_script('ja-app', get_theme_file_uri('app.js'), array('ja-commerce'), $version, true);
    $options = array('mode' => $live ? 'woocommerce' : 'preview');
    if ($live) {
        $options['currency'] = get_woocommerce_currency();
        $options['checkoutAction'] = admin_url('admin-post.php');
        $options['checkoutNonce'] = wp_create_nonce('ja_checkout');
        wp_add_inline_script('ja-app', 'window.JA_CATALOG=' . wp_json_encode(ja_loja_catalog(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    } else {
        $data = get_theme_file_path('preview-catalog.json');
        $products = is_readable($data) ? json_decode(file_get_contents($data), true) : array();
        foreach ($products as &$product) { $product['image'] = get_theme_file_uri($product['image']); }
        unset($product);
        wp_add_inline_script('ja-app', 'window.JA_CATALOG=' . wp_json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    wp_add_inline_script('ja-commerce', 'window.JA_RUNTIME=' . wp_json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
});
add_action('admin_notices', function () {
    if (get_stylesheet() !== 'ja-eletronicos-theme' || !current_user_can('manage_options')) { return; }
    if (!function_exists('ja_loja_ready') || !ja_loja_ready()) {
        echo '<div class="notice notice-warning"><p>JA Eletrônicos: a vitrine está em modo de demonstração. Ative WooCommerce e JA Loja Core para usar produtos e checkout reais.</p></div>';
    }
});
