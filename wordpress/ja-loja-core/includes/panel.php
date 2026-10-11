<?php
defined('ABSPATH') || exit;
add_action('admin_menu',function(){add_menu_page('Gestão JA','Gestão JA','ja_operate_register','ja-gestao',function(){echo '<p><a class="button button-primary" href="'.esc_url(home_url('/?ja_management=1')).'">Abrir o painel da JA</a></p>';},'dashicons-store',3);});
add_filter('login_redirect',function($redirect,$requested,$user){return $user instanceof WP_User && in_array('ja_cashier',$user->roles,true)?home_url('/?ja_management=1'):$redirect;},10,3);
add_action('init',function(){if(is_admin() && !wp_doing_ajax() && current_user_can('ja_operate_register') && !current_user_can('ja_manage_store') && !str_contains($_SERVER['SCRIPT_NAME']??'','admin-post.php')){wp_safe_redirect(home_url('/?ja_management=1'));exit;}},1);
add_action('template_redirect',function(){
    if(!isset($_GET['ja_management'])){return;}
    if(!is_user_logged_in()){auth_redirect();}
    if(!ja_permission()){wp_die('Seu acesso não permite operar a loja.', 'Acesso restrito',array('response'=>403));}
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');
    $base=plugins_url('assets/',dirname(__DIR__).'/ja-loja-core.php');
    $config=array('api'=>rest_url('ja/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'logout'=>wp_logout_url(home_url('/')),'shop'=>home_url('/'),'woo'=>admin_url('edit.php?post_type=shop_order'),'products'=>admin_url('edit.php?post_type=product'),'settings'=>admin_url('admin.php?page=wc-settings'));
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Gestão · JA Eletrônicos</title><link rel="stylesheet" href="<?php echo esc_url($base.'management.css'); ?>?v=0.2.0"></head><body>
    <aside class="sidebar"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span>JA</span><div>ELETRÔNICOS<small>Gestão da loja</small></div></a><nav id="nav"></nav><div class="sidebar-bottom"><span id="operator"></span><a href="<?php echo esc_url($config['logout']); ?>">Sair da conta ↗</a></div></aside>
    <main><header class="top"><div><small id="eyebrow">JA ELETRÔNICOS</small><h1 id="title">Carregando sua loja…</h1></div><a class="button secondary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">Ver vitrine ↗</a></header><div id="network" class="notice" hidden>Sem conexão. As vendas estão bloqueadas até a conexão voltar.</div><div id="toast" role="status" aria-live="polite" hidden></div><section id="workspace"><p>Consultando o WooCommerce…</p></section></main>
    <dialog id="modal"><div id="modal-content"></div><button class="secondary" id="modal-close">Fechar</button></dialog>
    <script>window.JA_MANAGEMENT=<?php echo wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script><script src="<?php echo esc_url($base.'management.js'); ?>?v=0.2.0"></script></body></html><?php exit;
});
add_action('admin_post_ja_receipt',function(){
    if(!ja_permission()){wp_die('Acesso restrito.', '',array('response'=>403));}
    $order=wc_get_order(absint($_GET['order']??0));
    if(!$order||$order->get_created_via()!=='ja_pos'||(!current_user_can('ja_manage_store')&&(int)$order->get_meta('_ja_operator')!==get_current_user_id())){wp_die('Venda não encontrada.', '',array('response'=>404));}
    nocache_headers();header('X-Robots-Tag: noindex');
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Comprovante #<?php echo esc_html($order->get_id()); ?></title><style>body{font:14px monospace;max-width:340px;margin:32px auto;padding:12px}table{width:100%;border-collapse:collapse}td{padding:8px 0}td:last-child{text-align:right}small{display:block;margin:16px 0}@media print{button{display:none}body{margin:0}}</style></head><body><h2><?php echo esc_html(get_bloginfo('name')); ?></h2><p>Comprovante de venda #<?php echo esc_html($order->get_id()); ?><br><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></p><strong>DOCUMENTO NÃO FISCAL</strong><table><?php foreach($order->get_items() as $item){?><tr><td><?php echo esc_html($item->get_quantity().' × '.$item->get_name()); ?></td><td><?php echo wp_kses_post(wc_price($item->get_total()+$item->get_total_tax(),array('currency'=>$order->get_currency()))); ?></td></tr><?php } ?></table><hr><p>Total: <?php echo wp_kses_post($order->get_formatted_order_total()); ?><br><?php echo esc_html($order->get_payment_method_title()); ?><br>Troco: <?php echo wp_kses_post(wc_price((float)$order->get_meta('_ja_change')/100,array('currency'=>$order->get_currency()))); ?></p><small>Situação: <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?><br>Não substitui NF-e ou NFC-e.</small><button onclick="window.print()">Imprimir comprovante</button></body></html><?php exit;
});

// Caixa de e-mails apenas no ambiente local: mensagens não são enviadas a clientes reais.
add_action('phpmailer_init',function($mailer){
    if(wp_get_environment_type()!=='local'||!defined('JA_LOCAL_MAIL_HOST')){return;}
    $mailer->isSMTP();$mailer->Host=JA_LOCAL_MAIL_HOST;$mailer->Port=1025;$mailer->SMTPAuth=false;$mailer->SMTPAutoTLS=false;$mailer->SMTPSecure='';$mailer->From='noreply@ja.local';$mailer->FromName='JA Eletrônicos — local';
});
