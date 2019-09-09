<?php return array (
  'logs' => 
  array (
    'path' => 'backup/logs',
    'type' => 'file',
  ),
  'DB' => 
  array (
    'type' => 'mysqli',
    'tablePre' => 'aisino_',
    'read' => 
    array (
      0 => 
      array (
        'host' => '47.93.220.161:3689',
        'user' => 'lingcai',
        'passwd' => 'Fitk7oI-CV9#rl2Qhb#SI[6PSt2yun9l',
        'name' => 'db_aisino',
      ),
    ),
    'write' => 
    array (
      'host' => '47.93.220.161:3689',
      'user' => 'lingcai',
      'passwd' => 'Fitk7oI-CV9#rl2Qhb#SI[6PSt2yun9l',
      'name' => 'db_aisino',
    ),
  ),
  'REDIS' => 
  array (
    'scheme' => 'tcp',
    'host' => '47.94.3.191',
    'port' => 9735,
    'database' => 0,
    'password' => 'XoJg2Yoybaq4Vcqg',
  ),
  'interceptor' => 
  array (
    0 => 'themeroute@onCreateController',
    1 => 'layoutroute@onCreateView',
    2 => 'plugin',
  ),
  'langPath' => 'language',
  'viewPath' => 'views',
  'skinPath' => 'skin',
  'classes' => 'classes.*',
  'rewriteRule' => 'pathinfo',
  'theme' => 
  array (
    'pc' => 
    array (
      'huawei' => 'default',
      'sysdm' => 'default',
      'sysseller' => 'default',
    ),
    'mobile' => 
    array (
      'mobile' => 'default',
      'sysdm' => 'default',
      'sysseller' => 'default',
    ),
  ),
  'timezone' => 'Etc/GMT-8',
  'upload' => 'upload',
  'dbbackup' => 'backup/database',
  'safe' => 'cookie',
  'lang' => 'zh_sc',
  'debug' => '0',
  'configExt' => 
  array (
    'site_config' => 'config/site_config.php',
  ),
  'encryptKey' => 'b0944e407ae1a5ebc2229a22dee75c94',
  'authorizeCode' => '',
  'uploadSize' => '10',
  'low_withdraw' => '1',
  'low_bill' => '0',
  'urlRoute' => 
  array (
    'article.html' => 'site/article',
    'article-<id:\\d+>.html' => 'site/article_detail',
    'item-<id:\\d+>.html' => 'site/products',
    'list-<cat:\\d+>.html' => 'site/pro_list',
    'tuan.html' => 'site/groupon',
    'brand.html' => 'site/brand',
    'brand-zone.html' => 'site/brand_zone',
    'cart.html' => 'simple/cart',
    'login.html' => 'simple/login',
    'help.html' => 'site/help',
    'help-index.html' => 'site/help_list',
    'notice.html' => 'site/notice',
    'tuan-list.html' => 'site/groupon_list',
    'search.html' => 'site/search_list',
    'error.html' => 'errors/error',
    'notice-<id:\\d+>.html' => 'site/notice_detail',
  ),
  'api_account' => '',
  'api_key' => '',
)?>
