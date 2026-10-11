<?php
// Instala sem senha em argumentos, prompts ou saída do terminal.
define('WP_INSTALLING',true);
define('WP_SITEURL','http://localhost:8090');
define('WP_HOME','http://localhost:8090');
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
$config=json_decode(file_get_contents('/run/secrets/bootstrap'),true);
if(!isset($config['password'])||!is_string($config['password'])||strlen($config['password'])<24){fwrite(STDERR,"Configuração local inválida.\n");exit(1);}
add_filter('pre_wp_mail','__return_true');
wp_install('JA Eletrônicos','ja-admin','local@example.invalid',false,'',$config['password']);
echo "WordPress instalado. A senha está somente no arquivo de acesso local.\n";
