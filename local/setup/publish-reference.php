<?php
if(wp_get_environment_type()!=='local'){WP_CLI::error('Operação restrita à apresentação local.');}
$count=0;foreach(wc_get_products(array('limit'=>-1,'status'=>'draft','meta_key'=>'_ja_reference_import','meta_value'=>'yes')) as $product){$product->set_status('publish');$product->save();$count++;}
WP_CLI::success($count.' produtos de referência visíveis. Estoque permanece zero; informe contagens reais no painel.');
