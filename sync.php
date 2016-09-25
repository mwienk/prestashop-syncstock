<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/stocksync.php');

if (Tools::getIsset('secure_key')) {
    $secureKey = md5(_COOKIE_KEY_.'STOCKSYNC');
    if (!empty($secureKey) && $secureKey === $_GET['secure_key']) {
        Stocksync::update();
    }
}
