<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('ja-native'); ?>><?php wp_body_open(); ?>
<div class="topbar"><div class="container"><span>JA ELETRÔNICOS · TECNOLOGIA PARA A VIDA REAL.</span><span class="location">Volta Redonda, RJ</span></div></div>
<header class="header"><div class="container header-inner"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(get_theme_file_uri('assets/logo.webp')); ?>" alt="JA Eletrônicos" width="210" height="70"></a><nav class="native-links" aria-label="Navegação da loja"><a href="<?php echo esc_url(home_url('/#catalogo')); ?>">Explorar produtos</a><?php if (function_exists('wc_get_cart_url')) : ?><a href="<?php echo esc_url(wc_get_cart_url()); ?>">Carrinho</a><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Minha conta</a><?php endif; ?></nav></div></header>
<main class="container native-content">
