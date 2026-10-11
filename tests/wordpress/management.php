<?php
function check($value,$message){if(!$value){throw new Exception('FAIL: '.$message);}echo 'PASS: '.$message."\n";}
do_action('rest_api_init');
$admin=get_user_by('login','ja-local-qa');wp_set_current_user($admin->ID);
$operator=wp_insert_user(array('user_login'=>'local_cashier','user_pass'=>'local-fixture-only-2026','user_email'=>'cashier@example.invalid','role'=>'ja_cashier'));
if(is_wp_error($operator)){$operator=get_user_by('login','local_cashier')->ID;}
function call_ja($action,$data=array(),$token=null){$r=new WP_REST_Request('POST','/ja/v1/'.$action);$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode(array_merge($data,array('token'=>$token?:wp_generate_uuid4()))));$response=rest_do_request($r);return array($response->get_status(),$response->get_data());}
function snapshot(){return rest_do_request(new WP_REST_Request('GET','/ja/v1/state'))->get_data();}
$id=wc_get_product_id_by_sku('QA-IN');
[$code,$r]=call_ja('product',array('id'=>$id,'name'=>'Fone de teste local','sku'=>'QA-IN','barcode'=>'7890000000001','price'=>'19.90','cost'=>'8.00','minimum'=>2,'status'=>'publish'));check($code===200,'Cadastro, custo, código de barras e estoque mínimo');
[$code,$r]=call_ja('stock',array('id'=>$id,'mode'=>'set','quantity'=>5,'reason'=>'Contagem física local'));check($code===200,'Contagem física define estoque real');
wp_set_current_user($operator);
[$code,$r]=call_ja('stock',array('id'=>$id,'mode'=>'set','quantity'=>100,'reason'=>'Tentativa proibida'));check($code===403,'Funcionário não ajusta estoque');
[$code,$r]=call_ja('supplier',array('name'=>'Proibido'));check($code===403,'Funcionário não acessa compras');
$s=snapshot();check(!isset($s['products'][0]['cost'])&&!isset($s['report'])&&!isset($s['employees']),'Custos, relatórios e usuários ocultos para funcionário');
[$code,$r]=call_ja('open',array('opening'=>'100.00'));check($code===200,'Funcionário abre próprio caixa');$register=$r['id'];
[$code,$r]=call_ja('open',array('opening'=>'0.00'));check($code===400,'Impede dois caixas abertos pelo mesmo operador');
$token=wp_generate_uuid4();$sale=array('register'=>$register,'method'=>'cash','tender'=>'50.00','items'=>array(array('id'=>$id,'quantity'=>2,'price'=>0)));
[$code,$r]=call_ja('sale',$sale,$token);check($code===200,'Venda presencial cria pedido pago real');$order=$r['order'];check($r['total']===3980 && $r['change']===1020,'Preço vem do WooCommerce e troco calculado');check((int)wc_get_product($id)->get_stock_quantity()===3,'Venda desconta estoque exatamente uma vez');
[$code,$r]=call_ja('sale',$sale,$token);check($code===200 && $r['order']===$order && (int)wc_get_product($id)->get_stock_quantity()===3,'Repetição não duplica pedido nem estoque');
[$code,$r]=call_ja('sale',array('register'=>$register,'method'=>'cash','tender'=>'1.00','items'=>array(array('id'=>$id,'quantity'=>1))));check($code===400 && (int)wc_get_product($id)->get_stock_quantity()===3,'Pagamento insuficiente não altera estoque');
[$code,$r]=call_ja('sale',array('register'=>$register,'method'=>'pix','items'=>array(array('id'=>$id,'quantity'=>4))));check($code===400,'Impede venda sem estoque suficiente');
[$code,$r]=call_ja('cash',array('register'=>$register,'kind'=>'withdrawal','amount'=>'20.00','reason'=>'Retirada local'));check($code===200,'Sangria de dinheiro registrada');
[$code,$r]=call_ja('cash',array('register'=>$register,'kind'=>'withdrawal','amount'=>'200.00','reason'=>'Excesso'));check($code===400,'Sangria não excede saldo');
check(snapshot()['own']['balance']===11980,'Saldo do caixa soma fundo e venda, desconta sangria');
wp_set_current_user($admin->ID);
[$code,$r]=call_ja('supplier',array('name'=>'Fornecedor QA','document'=>'00000000000000','contact'=>'Teste local'));check($code===200,'Fornecedor cadastrado');$supplier=$r['id'];
[$code,$r]=call_ja('purchase',array('supplier'=>$supplier,'reference'=>'PEDIDO-QA','items'=>array(array('id'=>$id,'quantity'=>2,'cost'=>'10.00'))));check($code===200 && (int)wc_get_product($id)->get_stock_quantity()===3,'Compra pendente não aumenta estoque');$purchase=$r['id'];
[$code,$r]=call_ja('receive',array('id'=>$purchase));check($code===200 && (int)wc_get_product($id)->get_stock_quantity()===5,'Recebimento aumenta estoque real');check(abs((float)wc_get_product($id)->get_meta('_ja_cost')-8.8)<0.001,'Custo médio ponderado atualizado');
[$code,$r]=call_ja('receive',array('id'=>$purchase));check($code===400 && (int)wc_get_product($id)->get_stock_quantity()===5,'Recebimento não pode ser duplicado');
[$code,$r]=call_ja('refund',array('order'=>$order,'register'=>$register,'reason'=>'Devolução local'));check($code===200 && (int)wc_get_product($id)->get_stock_quantity()===7,'Devolução integral repõe estoque');
[$code,$r]=call_ja('refund',array('order'=>$order,'register'=>$register,'reason'=>'Duplicada'));check($code===400 && (int)wc_get_product($id)->get_stock_quantity()===7,'Devolução não repõe estoque duas vezes');
wp_set_current_user($operator);check(snapshot()['own']['balance']===8000,'Dinheiro devolvido sai do caixa');
[$code,$r]=call_ja('close',array('register'=>$register,'counted'=>'79.00'));check($code===200 && $r['difference']===-100,'Fechamento registra diferença de contagem');
[$code,$r]=call_ja('sale',$sale);check($code===400,'Caixa fechado não vende');
wp_set_current_user($admin->ID);
[$code,$r]=call_ja('open',array('opening'=>'0.00'));check($code===200,'Administrador também opera caixa');$ownerRegister=$r['id'];
wp_set_current_user($operator);
[$code,$r]=call_ja('close',array('register'=>$ownerRegister,'counted'=>'0.00'));check($code===400,'Funcionário não fecha caixa de outro usuário');
wp_set_current_user($admin->ID);
$held=wc_create_order();$held->add_product(wc_get_product($id),7);$held->calculate_totals();$held->save();wc_reserve_stock_for_order($held);
[$code,$r]=call_ja('sale',array('register'=>$ownerRegister,'method'=>'pix','items'=>array(array('id'=>$id,'quantity'=>1))));check($code===400 && (int)wc_get_product($id)->get_stock_quantity()===7,'Reserva de pedido online impede venda presencial das mesmas unidades');
[$code,$r]=call_ja('stock',array('id'=>$id,'mode'=>'set','quantity'=>6,'reason'=>'Retirar reserva'));check($code===400,'Inventário preserva unidades reservadas');
wc_release_stock_for_order($held);$held->update_status('cancelled');
[$code,$r]=call_ja('sale',array('register'=>$ownerRegister,'method'=>'pix','items'=>array(array('id'=>$id,'quantity'=>1))));check($code===200 && snapshot()['own']['balance']===0,'Pix baixa estoque sem aumentar dinheiro na gaveta');
$s=snapshot();check(count($s['audit'])>=12 && count($s['suppliers'])===1 && $s['report']['totals']['orders']>=1,'Relatórios e histórico consultam operações reais');
$csv=rest_do_request(new WP_REST_Request('GET','/ja/v1/export'));check($csv->get_status()===200 && str_contains($csv->get_data(),'7890000000001'),'Exportação contém código de barras e estoque');
wp_set_current_user(0);check(rest_do_request(new WP_REST_Request('GET','/ja/v1/state'))->get_status()===401,'Visitante não acessa gestão');
echo "ALL MANAGEMENT CHECKS PASSED\n";
