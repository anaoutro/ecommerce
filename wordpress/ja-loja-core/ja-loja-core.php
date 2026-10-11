<?php
/**
 * Plugin Name: JA Loja Core
 * Description: Liga a vitrine personalizada da JA ao catálogo e ao checkout do WooCommerce. Inclui importação de referência via WP-CLI.
 * Version: 0.2.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: JA Eletrônicos
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/management.php';
require_once __DIR__ . '/includes/panel.php';

/** Identidade assinada por visitante, para o nonce não ser compartilhado entre convidados. */
function ja_loja_guest_id() {
    static $identity = null;
    if ($identity !== null) { return $identity; }
    $cookie = isset($_COOKIE['ja_guest']) && is_string($_COOKIE['ja_guest']) ? wp_unslash($_COOKIE['ja_guest']) : '';
    $parts = explode('.', $cookie, 2);
    $valid = count($parts) === 2 && preg_match('/^[a-f0-9]{32}$/', $parts[0]) && hash_equals(hash_hmac('sha256', $parts[0], wp_salt('nonce')), $parts[1]);
    $value = $valid ? $parts[0] : bin2hex(random_bytes(16));
    if (!$valid && !headers_sent()) {
        $signed = $value . '.' . hash_hmac('sha256', $value, wp_salt('nonce'));
        setcookie('ja_guest', $signed, array('expires' => time() + MONTH_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
        $_COOKIE['ja_guest'] = $signed;
    }
    $identity = (int) hexdec(substr(hash_hmac('sha256', $value, wp_salt('nonce')), 0, 12));
    return $identity;
}
add_action('init', function () { if (!is_user_logged_in()) { ja_loja_guest_id(); } });
add_filter('nonce_user_logged_out', function ($uid, $action) { return $action === 'ja_checkout' ? ja_loja_guest_id() : $uid; }, 10, 2);

function ja_loja_ready() {
    return function_exists('WC') && function_exists('wc_get_products');
}

function ja_loja_catalog() {
    if (!ja_loja_ready()) { return array(); }
    $products = wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order', 'order' => 'ASC'));
    $categories = array('fones-de-ouvido' => 'fones', 'cabos-conexao' => 'cabos', 'garrafas-copos' => 'garrafas/copos', 'outros-acessorios' => 'diversos');
    $result = array();
    foreach ($products as $product) {
        if (!$product->is_visible()) { continue; }
        $terms = get_the_terms($product->get_id(), 'product_cat');
        $category = 'diversos';
        if (is_array($terms) && !empty($terms)) {
            $term = reset($terms);
            $key = get_term_meta($term->term_id, '_ja_category_key', true);
            $category = $key ?: ($categories[$term->slug] ?? strtolower($term->name));
        }
        $image = wp_get_attachment_image_url($product->get_image_id(), 'medium_large');
        $result[] = array(
            'id' => $product->get_sku() ?: 'WC-' . $product->get_id(),
            'woocommerceId' => $product->get_id(),
            'category' => $category,
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_short_description()),
            'price' => (float) wc_get_price_to_display($product),
            'image' => $image ?: get_theme_file_uri('assets/product-placeholder.svg'),
            'stockStatus' => $product->get_stock_status(),
            'type' => $product->get_type(),
            'permalink' => $product->get_permalink(),
            'purchasable' => $product->is_purchasable(),
        );
    }
    return $result;
}

function ja_loja_checkout_fail($message, $status = 400) {
    wp_die(esc_html($message), 'Não foi possível preparar o carrinho', array('response' => $status, 'back_link' => true));
}

function ja_loja_checkout() {
    if (!ja_loja_ready()) { ja_loja_checkout_fail('WooCommerce precisa estar ativo.', 503); }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { ja_loja_checkout_fail('Use a lista de produtos da loja.', 405); }
    $nonce = isset($_POST['ja_nonce']) ? sanitize_text_field(wp_unslash($_POST['ja_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'ja_checkout')) { ja_loja_checkout_fail('A sessão expirou. Atualize a loja e tente novamente.', 403); }
    $items = json_decode(isset($_POST['items']) && is_string($_POST['items']) ? wp_unslash($_POST['items']) : '', true);
    if (!is_array($items) || count($items) < 1 || count($items) > 100) { ja_loja_checkout_fail('Selecione entre 1 e 100 produtos diferentes.'); }
    $validated = array();
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['id'], $item['quantity']) || !is_int($item['id']) || !is_int($item['quantity'])) { ja_loja_checkout_fail('A lista contém uma quantidade ou produto inválido.'); }
        $id = $item['id']; $quantity = $item['quantity'];
        if ($id < 1 || $quantity < 1 || $quantity > 999 || isset($validated[$id])) { ja_loja_checkout_fail('Revise os produtos e as quantidades da lista.'); }
        $product = wc_get_product($id);
        if (!$product || $product->get_status() !== 'publish' || !$product->is_type('simple') || !$product->is_purchasable()) { ja_loja_checkout_fail('Um produto não está disponível para compra direta. Revise sua lista.'); }
        if (!$product->is_in_stock() || !$product->has_enough_stock($quantity)) { ja_loja_checkout_fail('A quantidade de ' . $product->get_name() . ' não está disponível.'); }
        if ($product->is_sold_individually() && $quantity !== 1) { ja_loja_checkout_fail($product->get_name() . ' permite apenas uma unidade por pedido.'); }
        $validated[$id] = $quantity;
    }
    wc_load_cart();
    WC()->session->set_customer_session_cookie(true);
    $previous = WC()->cart->get_cart();
    $previous_coupons = WC()->cart->get_applied_coupons();
    WC()->cart->empty_cart();
    try {
        foreach ($validated as $id => $quantity) {
            if (!WC()->cart->add_to_cart($id, $quantity)) { throw new Exception('Não foi possível adicionar um produto ao carrinho.'); }
        }
        foreach ($previous_coupons as $coupon) { WC()->cart->apply_coupon($coupon); }
        WC()->cart->calculate_totals();
        WC()->session->save_data();
    } catch (Throwable $error) {
        wc_get_logger()->error('Falha ao preparar carrinho: ' . $error->getMessage(), array('source' => 'ja-loja-core'));
        WC()->cart->empty_cart();
        foreach ($previous as $item) {
            $custom = array_diff_key($item, array_flip(array('key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax')));
            WC()->cart->add_to_cart($item['product_id'], $item['quantity'], $item['variation_id'] ?? 0, $item['variation'] ?? array(), $custom);
        }
        foreach ($previous_coupons as $coupon) { WC()->cart->apply_coupon($coupon); }
        WC()->cart->calculate_totals(); WC()->session->save_data();
        ja_loja_checkout_fail('Não foi possível preparar esta lista. O carrinho anterior foi restaurado; revise disponibilidade e tente novamente.');
    }
    wp_safe_redirect(wc_get_checkout_url());
    exit;
}
add_action('admin_post_ja_checkout', 'ja_loja_checkout');
add_action('admin_post_nopriv_ja_checkout', 'ja_loja_checkout');

if (defined('WP_CLI') && WP_CLI) {
    /** Importa dados de referência como rascunhos sem estoque disponível. */
    class JA_Loja_Import_Command {
        /**
         * Importa o catálogo de referência. Não substitui produtos já existentes.
         *
         * ## OPTIONS
         * --source=<path>
         * : Arquivo JSON com os produtos.
         * [--images=<path>]
         * : Diretório local com arquivos SKU.webp.
         * [--limit=<number>]
         * : Máximo de produtos a importar nesta execução.
         * [--dry-run]
         * : Conta os produtos sem gravar alterações.
         */
        public function __invoke($args, $assoc) {
            if (!ja_loja_ready()) { WP_CLI::error('Ative o WooCommerce primeiro.'); }
            $source = $assoc['source'] ?? '';
            if (!is_readable($source)) { WP_CLI::error('Arquivo JSON não encontrado.'); }
            $data = json_decode(file_get_contents($source), true);
            if (!is_array($data)) { WP_CLI::error('JSON inválido.'); }
            $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : count($data);
            $created = 0; $skipped = 0; $invalid = 0;
            if (!isset($assoc['dry-run'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            foreach (array_slice($data, 0, $limit) as $record) {
                if (!is_array($record) || !isset($record['id'], $record['name'], $record['category'], $record['price']) || !preg_match('/^JA-[0-9]{4}$/', $record['id']) || !is_numeric($record['price']) || (float) $record['price'] < 0) { $invalid++; continue; }
                if (wc_get_product_id_by_sku($record['id'])) { $skipped++; continue; }
                if (isset($assoc['dry-run'])) { $created++; continue; }
                $term = term_exists($record['category'], 'product_cat');
                if (!$term) { $term = wp_insert_term($record['category'], 'product_cat'); }
                if (is_wp_error($term)) { $invalid++; continue; }
                $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                update_term_meta($term_id, '_ja_category_key', sanitize_text_field($record['category']));
                $product = new WC_Product_Simple();
                $product->set_name(sanitize_text_field($record['name']));
                $product->set_sku($record['id']);
                $product->set_status('draft');
                $product->set_regular_price(wc_format_decimal($record['price']));
                $product->set_category_ids(array($term_id));
                $product->set_manage_stock(true);
                $product->set_stock_quantity(0);
                $product->set_stock_status('outofstock');
                $product->set_short_description(wp_kses_post($record['description'] ?? ''));
                $product->update_meta_data('_ja_reference_import', 'yes');
                $product_id = $product->save();
                $file = isset($assoc['images']) ? trailingslashit($assoc['images']) . $record['id'] . '.webp' : '';
                if ($file && is_readable($file)) {
                    $copy = wp_tempnam($file);
                    if ($copy && copy($file, $copy)) {
                        $attachment = media_handle_sideload(array('name' => basename($file), 'tmp_name' => $copy), $product_id, $product->get_name());
                        if (is_wp_error($attachment)) { if (file_exists($copy)) { unlink($copy); } WP_CLI::warning('Imagem não importada para ' . $record['id']); }
                        else { $product->set_image_id($attachment); $product->save(); }
                    }
                }
                $created++;
            }
            WP_CLI::success(sprintf('%s: %d; já existentes: %d; inválidos: %d. Produtos novos são rascunhos com estoque zero.', isset($assoc['dry-run']) ? 'A importar' : 'Importados', $created, $skipped, $invalid));
        }
    }
    WP_CLI::add_command('ja import-catalog', 'JA_Loja_Import_Command');
}
