<?php
/** Gestão própria JA. Nenhuma integração com ERP externo. */
defined('ABSPATH') || exit;

function ja_manage_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate(); $p = $wpdb->prefix;
    dbDelta("CREATE TABLE {$p}ja_registers (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint unsigned NOT NULL,
        state varchar(16) NOT NULL DEFAULT 'open',
        opening bigint NOT NULL DEFAULT 0,
        counted bigint DEFAULT NULL,
        expected bigint DEFAULT NULL,
        opened datetime NOT NULL,
        closed datetime DEFAULT NULL,
        PRIMARY KEY (id), KEY user_state (user_id,state)
    ) $c;");
    dbDelta("CREATE TABLE {$p}ja_cash (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        register_id bigint unsigned NOT NULL,
        actor bigint unsigned NOT NULL,
        kind varchar(24) NOT NULL,
        amount bigint NOT NULL,
        order_id bigint unsigned DEFAULT NULL,
        reason text NOT NULL,
        created datetime NOT NULL,
        PRIMARY KEY (id), KEY register_id (register_id)
    ) $c;");
    dbDelta("CREATE TABLE {$p}ja_operations (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        token varchar(64) NOT NULL,
        actor bigint unsigned NOT NULL,
        action varchar(32) NOT NULL,
        result longtext NOT NULL,
        created datetime NOT NULL,
        PRIMARY KEY (id), UNIQUE KEY token (token)
    ) $c;");
    dbDelta("CREATE TABLE {$p}ja_audit (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        actor bigint unsigned NOT NULL,
        action varchar(48) NOT NULL,
        subject bigint unsigned NOT NULL DEFAULT 0,
        detail longtext NOT NULL,
        created datetime NOT NULL,
        PRIMARY KEY (id), KEY action (action)
    ) $c;");
    dbDelta("CREATE TABLE {$p}ja_suppliers (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        name varchar(200) NOT NULL,
        document varchar(32) NOT NULL DEFAULT '',
        contact text NOT NULL,
        active tinyint NOT NULL DEFAULT 1,
        PRIMARY KEY (id)
    ) $c;");
    dbDelta("CREATE TABLE {$p}ja_purchases (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        supplier_id bigint unsigned NOT NULL,
        actor bigint unsigned NOT NULL,
        state varchar(16) NOT NULL DEFAULT 'pending',
        reference varchar(200) NOT NULL DEFAULT '',
        items longtext NOT NULL,
        total bigint NOT NULL,
        created datetime NOT NULL,
        received datetime DEFAULT NULL,
        PRIMARY KEY (id), KEY state (state)
    ) $c;");
    add_role('ja_cashier', 'Caixa JA', array('read' => true, 'ja_operate_register' => true));
    foreach (array('administrator','shop_manager') as $role_name) {
        $role = get_role($role_name);
        if ($role) { $role->add_cap('ja_operate_register'); $role->add_cap('ja_manage_store'); }
    }
    update_option('ja_management_version', '0.2.0');
}
register_activation_hook(dirname(__DIR__) . '/ja-loja-core.php', 'ja_manage_install');
add_action('init', function () { if (get_option('ja_management_version') !== '0.2.0') { ja_manage_install(); } });

function ja_audit($action, $subject, $detail = array()) {
    global $wpdb;
    if (false === $wpdb->insert($wpdb->prefix . 'ja_audit', array('actor'=>get_current_user_id(), 'action'=>$action, 'subject'=>$subject, 'detail'=>wp_json_encode($detail), 'created'=>current_time('mysql', true)))) { throw new RuntimeException('Falha ao gravar o histórico.'); }
}
function ja_money($value) {
    if (!is_scalar($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', (string)$value)) { throw new InvalidArgumentException('Informe um valor positivo com até duas casas decimais.'); }
    return (int) round((float)$value * 100);
}
function ja_integer($value, $min=1, $max=999999) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < $min || (int)$value > $max) { throw new InvalidArgumentException('Quantidade ou identificação inválida.'); }
    return (int)$value;
}
function ja_text($data, $key, $required=false) {
    if (isset($data[$key]) && !is_scalar($data[$key])) { throw new InvalidArgumentException('Texto inválido.'); }
    $text = sanitize_text_field($data[$key] ?? '');
    if (strlen($text) > 1000 || ($required && $text === '')) { throw new InvalidArgumentException('Preencha ' . $key . '.'); }
    return $text;
}
function ja_register($id, $open=true) {
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ja_registers WHERE id=%d", ja_integer($id)));
    if (!$r || (!current_user_can('ja_manage_store') && (int)$r->user_id !== get_current_user_id())) { throw new RuntimeException('Caixa não encontrado ou sem permissão.'); }
    if ($open && $r->state !== 'open') { throw new RuntimeException('Este caixa já está fechado.'); }
    return $r;
}
function ja_cash($register, $kind, $amount, $reason, $order=null) {
    global $wpdb;
    if (false === $wpdb->insert($wpdb->prefix.'ja_cash', array('register_id'=>$register, 'actor'=>get_current_user_id(), 'kind'=>$kind, 'amount'=>$amount, 'reason'=>$reason, 'order_id'=>$order, 'created'=>current_time('mysql',true)))) { throw new RuntimeException('Falha ao registrar movimentação do caixa.'); }
}
function ja_balance($register) {
    global $wpdb;
    return (int)$register->opening + (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ja_cash WHERE register_id=%d", $register->id));
}
function ja_product_row($p) {
    return array('id'=>$p->get_id(),'name'=>$p->get_name(),'sku'=>$p->get_sku(),'barcode'=>$p->get_meta('_ja_barcode'),'price'=>(float)wc_get_price_to_display($p),'regularPrice'=>(float)$p->get_regular_price(),'stock'=>($p->get_stock_managed_by_id()!==$p->get_id()?wc_get_product($p->get_stock_managed_by_id())->get_stock_quantity():$p->get_stock_quantity()),'managed'=>$p->managing_stock(),'minimum'=>(int)($p->get_low_stock_amount()===''?get_option('woocommerce_notify_low_stock_amount',2):$p->get_low_stock_amount()),'cost'=>(float)$p->get_meta('_ja_cost'),'status'=>$p->get_status(),'type'=>$p->get_type());
}
function ja_product($id) {
    $p = wc_get_product(ja_integer($id));
    if (!$p || !$p->is_type(array('simple','variation'))) { throw new InvalidArgumentException('Escolha um produto simples ou uma variação.'); }
    return $p;
}

// Cada alteração recebe um token único. Repetir uma requisição após queda da conexão devolve o mesmo resultado.
function ja_mutate($request, $action) {
    global $wpdb;
    if (!ja_loja_ready()) { return new WP_Error('ja_dependency','Ative o WooCommerce.',array('status'=>503)); }
    $data = $request->get_json_params();
    if (!is_array($data)) { return new WP_Error('ja_input','Envie os dados do formulário.',array('status'=>400)); }
    $token = $data['token'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/', $token)) { return new WP_Error('ja_token','Identificador de operação inválido.',array('status'=>400)); }
    $lock = 'ja_' . substr(hash('sha256',DB_NAME.$wpdb->prefix),0,40);
    if (!(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock))) { return new WP_Error('ja_busy','Outra operação está em andamento. Tente novamente.',array('status'=>409)); }
    $order = null;
    try {
        $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ja_operations WHERE token=%s",$token));
        if ($previous) {
            if ((int)$previous->actor !== get_current_user_id() || $previous->action !== $action) { throw new RuntimeException('Identificador já utilizado em outra operação.'); }
            return rest_ensure_response(json_decode($previous->result,true));
        }
        wc_transaction_query('start');
        $result = ja_dispatch($action,$data,$order);
        if (false === $wpdb->insert($wpdb->prefix.'ja_operations',array('token'=>$token,'actor'=>get_current_user_id(),'action'=>$action,'result'=>wp_json_encode($result),'created'=>current_time('mysql',true)))) { throw new RuntimeException('Falha ao confirmar operação.'); }
        wc_transaction_query('commit');
        return rest_ensure_response($result);
    } catch (Throwable $e) {
        wc_transaction_query('rollback');
        if ($order) { wc_release_stock_for_order($order); }
        // Objetos WooCommerce podem ter cacheado quantidades antes do rollback.
        wp_cache_flush();
        wc_get_logger()->warning($e->getMessage(),array('source'=>'ja-gestao'));
        return new WP_Error('ja_operation',$e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'A operação falhou. Nenhuma confirmação foi gravada; tente novamente.',array('status'=>400));
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
}
function ja_dispatch($action,$d,&$order) {
    global $wpdb; $p=$wpdb->prefix; $uid=get_current_user_id();
    switch ($action) {
        case 'open':
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}ja_registers WHERE user_id=%d AND state='open'",$uid))) { throw new RuntimeException('Você já tem um caixa aberto.'); }
            $opening=ja_money($d['opening'] ?? '0');
            if (!$wpdb->insert($p.'ja_registers',array('user_id'=>$uid,'opening'=>$opening,'state'=>'open','opened'=>current_time('mysql',true)))) { throw new RuntimeException('Falha ao abrir caixa.'); }
            $id=(int)$wpdb->insert_id; ja_audit('register_open',$id,array('opening'=>$opening)); return array('id'=>$id,'message'=>'Caixa aberto.');
        case 'close':
            $r=ja_register($d['register']??0); $counted=ja_money($d['counted']??'0'); $expected=ja_balance($r);
            $wpdb->update($p.'ja_registers',array('state'=>'closed','counted'=>$counted,'expected'=>$expected,'closed'=>current_time('mysql',true)),array('id'=>$r->id));
            ja_audit('register_close',$r->id,array('expected'=>$expected,'counted'=>$counted,'difference'=>$counted-$expected)); return array('message'=>'Caixa fechado.','expected'=>$expected,'difference'=>$counted-$expected);
        case 'cash':
            $r=ja_register($d['register']??0); $amount=ja_money($d['amount']??0); $kind=ja_text($d,'kind'); $reason=ja_text($d,'reason',true);
            if (!$amount || !in_array($kind,array('withdrawal','deposit'),true)) { throw new InvalidArgumentException('Escolha sangria ou reforço e informe um valor maior que zero.'); }
            if ($kind==='withdrawal' && $amount>ja_balance($r)) { throw new RuntimeException('O valor supera o dinheiro esperado no caixa.'); }
            ja_cash($r->id,$kind,$kind==='withdrawal'?-$amount:$amount,$reason); ja_audit('cash_'.$kind,$r->id,array('amount'=>$amount,'reason'=>$reason)); return array('message'=>'Movimentação registrada.');
        case 'sale':
            $r=ja_register($d['register']??0); $method=ja_text($d,'method');
            if(get_option('woocommerce_manage_stock','yes')!=='yes') { throw new RuntimeException('Ative a gestão de estoque nas configurações do WooCommerce antes de vender.'); }
            if (!in_array($method,array('cash','pix','card'),true)) { throw new InvalidArgumentException('Escolha dinheiro, Pix ou cartão.'); }
            $items=$d['items']??array(); if (!is_array($items)||!count($items)||count($items)>100) { throw new InvalidArgumentException('Selecione entre 1 e 100 itens.'); }
            $seen=array(); $validated=array();
            foreach($items as $item) {
                if (!is_array($item)) { throw new InvalidArgumentException('Item inválido.'); }
                $product=ja_product($item['id']??0); $qty=ja_integer($item['quantity']??0,1,999);
                if (isset($seen[$product->get_id()])) { throw new InvalidArgumentException('Produto duplicado.'); } $seen[$product->get_id()]=true;
                if ($product->get_status()!=='publish' || !$product->is_purchasable() || !$product->managing_stock() || $product->backorders_allowed() || !$product->is_in_stock() || !$product->has_enough_stock($qty) || ($product->is_sold_individually() && $qty>1)) { throw new RuntimeException('Disponibilidade insuficiente ou estoque não controlado: '.$product->get_name()); }
                $validated[]=array($product,$qty);
            }
            $order=wc_create_order(array('created_via'=>'ja_pos','status'=>'pending'));
            if(is_wp_error($order)) { $error=$order; $order=null; throw new RuntimeException($error->get_error_message()); }
            $order->update_meta_data('_ja_register',(int)$r->id); $order->update_meta_data('_ja_operator',$uid); $order->update_meta_data('_ja_payment',$method);
            $order->set_payment_method('ja_'.$method); $order->set_payment_method_title(array('cash'=>'Dinheiro','pix'=>'Pix confirmado no caixa','card'=>'Cartão confirmado na maquininha')[$method]);
            foreach($validated as [$product,$qty]) {
                $line_id=$order->add_product($product,$qty); $line=$order->get_item($line_id); if($product->get_meta('_ja_cost')!==''){$line->add_meta_data('_ja_unit_cost',wc_format_decimal($product->get_meta('_ja_cost')),true);} $line->save();
            }
            // Venda presencial usa o endereço fiscal da loja para o cálculo configurado no WooCommerce.
            $order->set_billing_country(WC()->countries->get_base_country()); $order->set_billing_state(WC()->countries->get_base_state());
            $order->set_billing_postcode(get_option('woocommerce_store_postcode')); $order->set_billing_city(get_option('woocommerce_store_city'));
            $order->calculate_totals(); $total=(int)round((float)$order->get_total()*100);
            $tender=$method==='cash'?ja_money($d['tender']??0):$total;
            if($tender<$total) { throw new InvalidArgumentException('O dinheiro recebido não cobre a venda.'); }
            $order->update_meta_data('_ja_tender',$tender); $order->update_meta_data('_ja_change',$tender-$total); $order->save();
            // A reserva nativa também considera os pedidos online em andamento.
            (new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock())->reserve_stock_for_order($order,5);
            $order->payment_complete(); wc_reduce_stock_levels($order->get_id()); wc_release_stock_for_order($order);
            if($method==='cash') { ja_cash($r->id,'sale',$total,'Venda presencial',$order->get_id()); }
            ja_audit('sale',$order->get_id(),array('register'=>$r->id,'total'=>$total,'method'=>$method));
            return array('message'=>'Venda registrada.','order'=>$order->get_id(),'total'=>$total,'change'=>$tender-$total,'receipt'=>admin_url('admin-post.php?action=ja_receipt&order='.$order->get_id()));
        case 'refund':
            $original=wc_get_order(ja_integer($d['order']??0)); $reason=ja_text($d,'reason',true);
            if (!$original || $original->get_created_via()!=='ja_pos' || $original->get_total_refunded()>0 || !$original->is_paid()) { throw new RuntimeException('Escolha uma venda presencial paga que ainda não tenha devolução.'); }
            $register=null;
            if($original->get_meta('_ja_payment')==='cash') { $register=ja_register($d['register']??0); if(ja_balance($register)<(int)round($original->get_total()*100)) { throw new RuntimeException('O caixa não tem saldo para devolver este valor.'); } }
            $lines=array();foreach($original->get_items() as $id=>$item) { $lines[$id]=array('qty'=>$item->get_quantity(),'refund_total'=>$item->get_total(),'refund_tax'=>$item->get_taxes()['total']); }
            $refund=wc_create_refund(array('order_id'=>$original->get_id(),'amount'=>$original->get_total(),'reason'=>$reason,'line_items'=>$lines,'refund_payment'=>false,'restock_items'=>true));
            if(is_wp_error($refund)) { throw new RuntimeException($refund->get_error_message()); }
            $original->update_status('refunded','Devolução integral no caixa JA: '.$reason);
            if($register) { ja_cash($register->id,'refund',-(int)round($original->get_total()*100),$reason,$original->get_id()); }
            ja_audit('refund',$original->get_id(),array('refund'=>$refund->get_id(),'reason'=>$reason));
            return array('message'=>'Devolução registrada e estoque reposto. O pagamento deve ser devolvido ao cliente no dinheiro, Pix ou maquininha.');
        case 'product':
            $product=empty($d['id'])?new WC_Product_Simple():ja_product($d['id']);
            $product->set_name(ja_text($d,'name',true)); $product->set_sku(ja_text($d,'sku',true));
            $barcode=ja_text($d,'barcode');
            if($barcode) { $existing=$wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$p}postmeta WHERE meta_key='_ja_barcode' AND meta_value=%s AND post_id<>%d LIMIT 1",$barcode,$product->get_id())); if($existing) { throw new RuntimeException('Código de barras já utilizado.'); } }
            $product->update_meta_data('_ja_barcode',$barcode); $product->set_regular_price(number_format(ja_money($d['price']??0)/100,2,'.',''));
            $product->update_meta_data('_ja_cost',number_format(ja_money($d['cost']??0)/100,2,'.','')); $product->set_low_stock_amount(ja_integer($d['minimum']??0,0));
            $product->set_manage_stock(true); $product->set_backorders('no'); if(!$product->get_id()) { $product->set_stock_quantity(0); }
            $status=ja_text($d,'status'); if(!in_array($status,array('draft','publish'),true)) { throw new InvalidArgumentException('Situação inválida.'); } $product->set_status($status);
            $id=$product->save(); ja_audit('product_save',$id,array('name'=>$product->get_name(),'price'=>$product->get_regular_price(),'cost'=>$product->get_meta('_ja_cost'),'minimum'=>$product->get_low_stock_amount())); return array('message'=>'Produto salvo.','id'=>$id);
        case 'stock':
            $product=ja_product($d['id']??0); $qty=ja_integer($d['quantity']??0,0); $reason=ja_text($d,'reason',true); $mode=ja_text($d,'mode');
            if(!in_array($mode,array('set','increase','decrease'),true)) { throw new InvalidArgumentException('Tipo de ajuste inválido.'); }
            if(!$product->managing_stock()) { throw new RuntimeException('Ative o controle de estoque no produto antes de ajustar.'); }
            $stock_product=wc_get_product($product->get_stock_managed_by_id()); $before=(int)$stock_product->get_stock_quantity(); $after=$mode==='set'?$qty:($before+($mode==='increase'?$qty:-$qty));
            $held=wc_get_held_stock_quantity($product);
            if($after<0 || $after<$held) { throw new RuntimeException('O ajuste deixaria estoque negativo ou retiraria unidades reservadas por pedidos.'); }
            wc_update_product_stock($product,$after,'set'); ja_audit('stock_adjust',$product->get_id(),array('before'=>$before,'after'=>$after,'reason'=>$reason)); return array('message'=>'Estoque atualizado.','stock'=>$after);
        case 'supplier':
            $id=empty($d['id'])?0:ja_integer($d['id']); $row=array('name'=>ja_text($d,'name',true),'document'=>ja_text($d,'document'),'contact'=>ja_text($d,'contact'),'active'=>1);
            if($id) { if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}ja_suppliers WHERE id=%d",$id))) { throw new RuntimeException('Fornecedor não encontrado.'); } $wpdb->update($p.'ja_suppliers',$row,array('id'=>$id)); }
            else { $wpdb->insert($p.'ja_suppliers',$row); $id=(int)$wpdb->insert_id; }
            ja_audit('supplier_save',$id,$row); return array('message'=>'Fornecedor salvo.','id'=>$id);
        case 'purchase':
            $supplier=ja_integer($d['supplier']??0); if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}ja_suppliers WHERE id=%d AND active=1",$supplier))) { throw new RuntimeException('Selecione um fornecedor cadastrado.'); }
            $raw=$d['items']??array(); if(!is_array($raw)||!count($raw)||count($raw)>100) { throw new InvalidArgumentException('Informe os itens da compra.'); }
            $items=array();$total=0;$seen=array();foreach($raw as $item) { $product=ja_product($item['id']??0);if(isset($seen[$product->get_id()])||!$product->managing_stock()) { throw new InvalidArgumentException('Item duplicado ou sem controle de estoque.'); } $seen[$product->get_id()]=true; $qty=ja_integer($item['quantity']??0);$cost=ja_money($item['cost']??0);$items[]=array('id'=>$product->get_id(),'name'=>$product->get_name(),'quantity'=>$qty,'cost'=>$cost);$total+=$qty*$cost; }
            $wpdb->insert($p.'ja_purchases',array('supplier_id'=>$supplier,'actor'=>$uid,'reference'=>ja_text($d,'reference'),'items'=>wp_json_encode($items),'total'=>$total,'created'=>current_time('mysql',true)));$id=(int)$wpdb->insert_id;ja_audit('purchase_create',$id,array('total'=>$total));return array('message'=>'Compra registrada. O estoque só muda ao confirmar o recebimento.','id'=>$id);
        case 'receive':
            $id=ja_integer($d['id']??0);$purchase=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ja_purchases WHERE id=%d",$id));
            if(!$purchase||$purchase->state!=='pending') { throw new RuntimeException('Compra não encontrada ou já recebida/cancelada.'); }
            foreach(json_decode($purchase->items,true) as $line) { $product=ja_product($line['id']); if(!$product->managing_stock()) { throw new RuntimeException('Produto sem controle de estoque.'); } $old=(int)wc_get_product($product->get_stock_managed_by_id())->get_stock_quantity();$old_cost=(float)$product->get_meta('_ja_cost');$new=$old+$line['quantity'];$cost=($old*$old_cost+$line['quantity']*$line['cost']/100)/max(1,$new);$product->update_meta_data('_ja_cost',wc_format_decimal($cost,2));$product->save();wc_update_product_stock($product,$line['quantity'],'increase'); }
            $wpdb->update($p.'ja_purchases',array('state'=>'received','received'=>current_time('mysql',true)),array('id'=>$id));ja_audit('purchase_receive',$id,array('items'=>json_decode($purchase->items,true)));return array('message'=>'Mercadoria recebida. Estoque e custo médio atualizados.');
        case 'cancel_purchase':
            $id=ja_integer($d['id']??0);if(!$wpdb->update($p.'ja_purchases',array('state'=>'cancelled'),array('id'=>$id,'state'=>'pending'))) { throw new RuntimeException('Só compras pendentes podem ser canceladas.'); }ja_audit('purchase_cancel',$id);return array('message'=>'Compra cancelada.');
        case 'employee':
            if(!current_user_can('create_users')) { throw new RuntimeException('Somente o administrador pode cadastrar funcionários.'); }
            $email=sanitize_email(ja_text($d,'email',true));if(!is_email($email)) { throw new InvalidArgumentException('E-mail inválido.'); }
            $login=ja_text($d,'login',true);$name=ja_text($d,'name',true);
            $id=wp_insert_user(array('user_login'=>$login,'user_email'=>$email,'display_name'=>$name,'user_pass'=>wp_generate_password(32,true),'role'=>'ja_cashier'));
            if(is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
            ja_audit('employee_create',$id,array('login'=>$login));wp_new_user_notification($id,null,'user');return array('message'=>'Funcionário criado. O WordPress enviará o link para definir a senha se o e-mail da hospedagem estiver configurado.','id'=>$id);
        case 'order_status':
            $target=wc_get_order(ja_integer($d['id']??0)); $next=ja_text($d,'status');
            if(!$target || $target->get_created_via()==='ja_pos') { throw new RuntimeException('Pedido online não encontrado.'); }
            if(!in_array($next,array('processing','completed','cancelled'),true)) { throw new InvalidArgumentException('Situação inválida.'); }
            if($next==='completed' && !$target->is_paid()) { throw new RuntimeException('Confirme o recebimento do pagamento antes de concluir.'); }
            if($next==='processing' && !$target->has_status(array('pending','on-hold'))) { throw new RuntimeException('Este pedido não aguarda confirmação do pagamento.'); }
            if($next==='cancelled' && ($target->is_paid() || $target->get_total_refunded()>0)) { throw new RuntimeException('Para cancelar uma compra já paga, registre o reembolso no WooCommerce.'); }
            if($next==='processing'){$target->payment_complete();}else{$target->update_status($next,'Atualizado pelo painel JA.');}
            ja_audit('online_status',$target->get_id(),array('status'=>$next));return array('message'=>'Pedido atualizado.');
        case 'employee_access':
            if(!current_user_can('edit_users')) { throw new RuntimeException('Somente o administrador pode gerenciar funcionários.'); }$user=get_user_by('id',ja_integer($d['id']??0));
            if(!$user||!in_array('ja_cashier',$user->roles,true)) { throw new RuntimeException('Escolha um funcionário de caixa JA.'); }$disabled=!empty($d['disabled']);update_user_meta($user->ID,'_ja_disabled',$disabled?'yes':'no');if($disabled){WP_Session_Tokens::get_instance($user->ID)->destroy_all();}ja_audit('employee_access',$user->ID,array('disabled'=>$disabled));return array('message'=>'Acesso atualizado.');
    }
    throw new InvalidArgumentException('Operação desconhecida.');
}

add_filter('authenticate',function($user){if($user instanceof WP_User && get_user_meta($user->ID,'_ja_disabled',true)==='yes'){return new WP_Error('ja_disabled','Este acesso foi desativado.');}return $user;},100);
function ja_permission($manage=false) { return is_user_logged_in() && get_user_meta(get_current_user_id(),'_ja_disabled',true)!=='yes' && current_user_can($manage?'ja_manage_store':'ja_operate_register'); }
add_action('rest_api_init',function(){
    register_rest_route('ja/v1','/state',array('methods'=>'GET','callback'=>'ja_state','permission_callback'=>function(){return ja_permission();}));
    foreach(array('open','close','cash','sale','refund','product','stock','supplier','purchase','receive','cancel_purchase','employee','employee_access','order_status') as $action){
        $manage=!in_array($action,array('open','close','cash','sale'),true);
        register_rest_route('ja/v1','/'.$action,array('methods'=>'POST','callback'=>function($request)use($action){return ja_mutate($request,$action);},'permission_callback'=>function()use($manage){return ja_permission($manage);}));
    }
});
function ja_state($request){
    global $wpdb;$p=$wpdb->prefix;$admin=current_user_can('ja_manage_store');$uid=get_current_user_id();
    if(!ja_loja_ready()) { return new WP_Error('ja_dependency','Ative o WooCommerce.',array('status'=>503)); }
    $query=substr(sanitize_text_field($request->get_param('search')??''),0,100);$page=max(1,(int)$request->get_param('page'));
    $args=array('limit'=>60,'page'=>$page,'paginate'=>true,'type'=>array('simple','variation'),'status'=>$admin?array('publish','draft'):array('publish'),'orderby'=>'name','order'=>'ASC');
    // Busca em nome, SKU e código de barras, sem expor custos ao operador.
    if($query!==''){
        $like='%'.$wpdb->esc_like($query).'%';
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT x.ID FROM {$p}posts x LEFT JOIN {$p}postmeta m ON x.ID=m.post_id AND m.meta_key IN ('_sku','_ja_barcode') WHERE x.post_type IN ('product','product_variation') AND (x.post_title LIKE %s OR m.meta_value LIKE %s) ORDER BY x.post_title LIMIT 500",$like,$like));
        if(!$ids){$ids=array(0);} $args['include']=$ids;
    }
    $found=wc_get_products($args);$products=array();foreach($found->products as $product){$row=ja_product_row($product);if(!$admin){unset($row['cost']);}$products[]=$row;}
    $registers=$wpdb->get_results($admin?"SELECT * FROM {$p}ja_registers ORDER BY id DESC LIMIT 100":$wpdb->prepare("SELECT * FROM {$p}ja_registers WHERE user_id=%d ORDER BY id DESC LIMIT 40",$uid),ARRAY_A);
    foreach($registers as &$r){$r['balance']=ja_balance((object)$r);$user=get_user_by('id',$r['user_id']);$r['operator']=$user?$user->display_name:'Usuário removido';}unset($r);
    $own=null;foreach($registers as $r){if((int)$r['user_id']===$uid && $r['state']==='open'){$own=$r;break;}}
    $cash=$own?$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}ja_cash WHERE register_id=%d ORDER BY id DESC LIMIT 100",$own['id']),ARRAY_A):array();
    $orders=wc_get_orders(array('type'=>'shop_order','limit'=>30,'orderby'=>'date','order'=>'DESC','created_via'=>'ja_pos','meta_key'=>$admin?'_ja_register':'_ja_operator','meta_value'=>$admin?'':$uid));
    // Para administradores não filtramos meta; para operadores somente suas vendas.
    if($admin){$orders=wc_get_orders(array('type'=>'shop_order','limit'=>30,'orderby'=>'date','order'=>'DESC','created_via'=>'ja_pos'));}
    $sales=array();foreach($orders as $o){$sales[]=array('id'=>$o->get_id(),'date'=>$o->get_date_created()->date('c'),'total'=>(float)$o->get_total(),'status'=>$o->get_status(),'payment'=>$o->get_meta('_ja_payment'),'receipt'=>admin_url('admin-post.php?action=ja_receipt&order='.$o->get_id()));}
    $response=array('admin'=>$admin,'name'=>wp_get_current_user()->display_name,'currency'=>get_woocommerce_currency(),'products'=>$products,'total'=>$found->total,'pages'=>$found->max_num_pages,'registers'=>$registers,'own'=>$own,'cash'=>$cash,'sales'=>$sales);
    if($admin){
        $response['online']=array();foreach(wc_get_orders(array('type'=>'shop_order','limit'=>50,'orderby'=>'date','order'=>'DESC')) as $online){if($online->get_created_via()==='ja_pos'){continue;}$lines=array();foreach($online->get_items() as $line){$lines[]=array('name'=>$line->get_name(),'quantity'=>$line->get_quantity());}$response['online'][]=array('id'=>$online->get_id(),'status'=>$online->get_status(),'date'=>$online->get_date_created()->date('c'),'total'=>(float)$online->get_total(),'customer'=>$online->get_formatted_billing_full_name(),'contact'=>$online->get_billing_email().' '.$online->get_billing_phone(),'address'=>wp_strip_all_tags($online->get_formatted_shipping_address()?:$online->get_formatted_billing_address()),'payment'=>$online->get_payment_method_title(),'paid'=>$online->is_paid(),'items'=>$lines,'url'=>$online->get_edit_order_url());}
        $response['suppliers']=$wpdb->get_results("SELECT * FROM {$p}ja_suppliers ORDER BY name",ARRAY_A);
        $response['purchases']=$wpdb->get_results("SELECT * FROM {$p}ja_purchases ORDER BY id DESC LIMIT 100",ARRAY_A);
        $response['audit']=$wpdb->get_results("SELECT * FROM {$p}ja_audit ORDER BY id DESC LIMIT 100",ARRAY_A);
        $response['employees']=array();foreach(get_users(array('role'=>'ja_cashier')) as $user){$response['employees'][]=array('id'=>$user->ID,'name'=>$user->display_name,'email'=>$user->user_email,'disabled'=>get_user_meta($user->ID,'_ja_disabled',true)==='yes');}
        $low=$wpdb->get_col("SELECT x.ID FROM {$p}posts x JOIN {$p}postmeta q ON q.post_id=x.ID AND q.meta_key='_stock' LEFT JOIN {$p}postmeta l ON l.post_id=x.ID AND l.meta_key='_low_stock_amount' WHERE x.post_type IN ('product','product_variation') AND x.post_status IN ('publish','draft') AND CAST(q.meta_value AS DECIMAL(20,4))<=COALESCE(NULLIF(l.meta_value,''),".(int)get_option('woocommerce_notify_low_stock_amount',2).") LIMIT 200");
        $response['low']=array();foreach($low as $id){$product=wc_get_product($id);if($product && $product->managing_stock()){$response['low'][]=ja_product_row($product);}}
        $from=ja_report_date($request->get_param('from'),gmdate('Y-m-01'));$to=ja_report_date($request->get_param('to'),gmdate('Y-m-d'));
        $totals=array('gross'=>0,'refunds'=>0,'net'=>0,'cost'=>0,'orders'=>0,'cash'=>0,'pix'=>0,'card'=>0,'online'=>0,'margin'=>0,'incompleteCost'=>0,'merchandise'=>0);
        $page=1;do{
            $batch=wc_get_orders(array('type'=>'shop_order','limit'=>100,'page'=>$page,'paginate'=>true,'status'=>array('processing','completed','refunded'),'date_created'=>$from.'...'.$to));
            foreach($batch->orders as $o){$gross=(float)$o->get_total();$refund=(float)$o->get_total_refunded();$net=$gross-$refund;$totals['gross']+=$gross;$totals['refunds']+=$refund;$totals['net']+=$net;$totals['orders']++;
                $method=$o->get_created_via()==='ja_pos'?$o->get_meta('_ja_payment'):'online';if(isset($totals[$method])){$totals[$method]+=$net;}
                foreach($o->get_items() as $id=>$item){$totals['merchandise']+=(float)$item->get_total()-abs((float)$o->get_total_refunded_for_item($id));$cost=$item->get_meta('_ja_unit_cost');if($cost===''){$totals['incompleteCost']++;continue;}$qty=max(0,$item->get_quantity()-abs($o->get_qty_refunded_for_item($id)));$totals['cost']+=(float)$cost*$qty;}
            }$page++;
        }while($page<=$batch->max_num_pages);
        // Margem de mercadorias: exclui frete e tributos. Custos ausentes são explicitados.
        $totals['margin']=$totals['merchandise']-$totals['cost'];$response['report']=array('from'=>$from,'to'=>$to,'totals'=>$totals);
    }
    $result=rest_ensure_response($response);$result->header('Cache-Control','no-store, private');return $result;
}
function ja_report_date($raw,$default){if(!$raw){return $default;}if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$raw)){return $default;}$parts=explode('-',$raw);return checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])?$raw:$default;}
// Captura o custo do produto no pedido online para os relatórios não usarem custos futuros.
add_action('woocommerce_checkout_create_order_line_item',function($item,$key,$values){if(isset($values['data']) && $values['data']->get_meta('_ja_cost')!==''){$item->add_meta_data('_ja_unit_cost',wc_format_decimal($values['data']->get_meta('_ja_cost')),true);}},10,3);
add_action('woocommerce_reduce_order_stock',function($order){$items=array();foreach($order->get_items() as $item){$items[]=array('product'=>$item->get_product_id(),'variation'=>$item->get_variation_id(),'quantity'=>$item->get_quantity());}ja_audit('order_stock_reduce',$order->get_id(),$items);});
add_action('woocommerce_restore_order_stock',function($order){ja_audit('order_stock_restore',$order->get_id());});
// Não enviar e-mails de compra online nas vendas presenciais anônimas.
foreach(array('new_order','customer_processing_order','customer_completed_order','customer_refunded_order') as $mail){add_filter('woocommerce_email_enabled_'.$mail,function($enabled,$order){return $order instanceof WC_Order && $order->get_created_via()==='ja_pos'?false:$enabled;},10,2);}

add_filter('woocommerce_order_hold_stock_minutes',function($minutes,$order){return $order instanceof WC_Order && $order->get_created_via()==='ja_pos'?5:$minutes;},999,2);
add_action('rest_api_init',function(){
    register_rest_route('ja/v1','/operation',array('methods'=>'GET','permission_callback'=>function(){return ja_permission();},'callback'=>function($request){global $wpdb;$token=$request->get_param('token');if(!is_string($token)){return new WP_Error('ja_token','Token inválido.',array('status'=>400));}$row=$wpdb->get_row($wpdb->prepare("SELECT result FROM {$wpdb->prefix}ja_operations WHERE token=%s AND actor=%d",$token,get_current_user_id()));$r=rest_ensure_response(array('confirmed'=>(bool)$row,'result'=>$row?json_decode($row->result,true):null));$r->header('Cache-Control','no-store');return $r;}));
    register_rest_route('ja/v1','/export',array('methods'=>'GET','permission_callback'=>function(){return ja_permission(true);},'callback'=>function(){
        $stream=fopen('php://temp','r+');fputcsv($stream,array('ID','SKU','Nome','Código de barras','Preço','Custo','Estoque','Estoque mínimo','Situação','Imagem'),';');
        foreach(wc_get_products(array('limit'=>-1,'status'=>array('publish','draft'),'type'=>array('simple','variation'))) as $product){$p=ja_product_row($product);$fields=array($p['id'],$p['sku'],$p['name'],$p['barcode'],$p['regularPrice'],$p['cost'],$p['stock'],$p['minimum'],$p['status'],wp_get_attachment_url($product->get_image_id()));foreach($fields as &$field){if(is_string($field)&&preg_match('/^[=+@\-\t\r]/',$field)){$field="'".$field;}}unset($field);fputcsv($stream,$fields,';');}rewind($stream);$csv=stream_get_contents($stream);fclose($stream);$response=new WP_REST_Response($csv);$response->header('Content-Type','text/csv; charset=UTF-8');$response->header('Cache-Control','no-store');return $response;
    }));
});
// Resposta CSV não deve ser serializada como JSON pelo servidor REST.
add_filter('rest_pre_serve_request',function($served,$result,$request){if($request->get_route()==='/ja/v1/export' && $result->get_status()===200){echo $result->get_data();return true;}return $served;},10,3);
