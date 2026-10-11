<?php
if(wp_get_environment_type()!=='local'){WP_CLI::error('Este instalador é somente para ambiente local.');}
WC_Install::create_pages();
update_option('woocommerce_currency','BRL');update_option('woocommerce_default_country','BR');
update_option('woocommerce_manage_stock','yes');update_option('woocommerce_hold_stock_minutes','60');
update_option('woocommerce_hide_out_of_stock_items','no');update_option('woocommerce_coming_soon','no');
update_option('woocommerce_enable_guest_checkout','yes');update_option('blogdescription','Ambiente local de apresentação');
$home=get_page_by_path('inicio');$id=$home?$home->ID:wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Início','post_name'=>'inicio'));
update_option('show_on_front','page');update_option('page_on_front',$id);
// Pagamento manual: nenhum valor é cobrado. Não configura conta Pix nem gateway externo.
$cod=get_option('woocommerce_cod_settings',array());$cod['enabled']='yes';$cod['title']='Pagamento na retirada';$cod['description']='Apresentação local: confirme o pagamento presencialmente. Nenhuma cobrança automática.';$cod['enable_for_virtual']='yes';update_option('woocommerce_cod_settings',$cod);
// Retirada sem tarifa apenas neste ambiente local de apresentação.
$zones=WC_Shipping_Zones::get_zones();if(!get_option('ja_local_pickup_configured')){$zone=new WC_Shipping_Zone(0);$method=$zone->add_shipping_method('local_pickup');update_option('woocommerce_local_pickup_'.$method.'_settings',array('title'=>'Retirada na loja (apresentação local)','tax_status'=>'none','cost'=>'0'));update_option('ja_local_pickup_configured','yes');}
WP_CLI::success('WooCommerce conectado. Retirada e pagamento manuais configurados para a apresentação local.');
