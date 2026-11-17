<?php
function psStoreSettings(){
    global $mysqli;
    $defaults = array(
        'level_enabled' => 1,
        'level_min_rank' => 1,
        'likes_enabled' => 0,
        'likes_min_rank' => 1,
    );
    $q = $mysqli->query("SELECT * FROM boom_purchase_store_settings WHERE id = 1 LIMIT 1");
    if($q && $q->num_rows > 0){
        return array_merge($defaults, $q->fetch_assoc());
    }
    return $defaults;
}

function psStoreServiceEnabled($service, $settings = null){
    global $setting;
    if($settings === null){
        $settings = psStoreSettings();
    }
    if($service === 'level'){
        return !empty($settings['level_enabled']) && !empty($setting['use_level']);
    }
    if($service === 'likes'){
        return !empty($settings['likes_enabled']) && !empty($setting['use_like']);
    }
    return false;
}

function psStoreServiceRank($service, $settings = null){
    if($settings === null){
        $settings = psStoreSettings();
    }
    if($service === 'level'){
        return (int)$settings['level_min_rank'];
    }
    if($service === 'likes'){
        return (int)$settings['likes_min_rank'];
    }
    return 999;
}

function psStoreAllProducts($service = ''){
    global $mysqli;
    $products = array();
    if($service === 'level' || $service === 'likes'){
        $service = escape($service);
        $q = $mysqli->query("SELECT * FROM boom_purchase_store_products WHERE service = '$service' ORDER BY sort_order ASC, product_id ASC");
    }
    else {
        $q = $mysqli->query("SELECT * FROM boom_purchase_store_products ORDER BY service ASC, sort_order ASC, product_id ASC");
    }
    if($q){
        while($row = $q->fetch_assoc()){
            $products[] = $row;
        }
    }
    return $products;
}

function psStoreAvailableProducts($service, $rank){
    global $mysqli;
    if($service !== 'level' && $service !== 'likes'){
        return array();
    }
    $rank = (int)$rank;
    $service = escape($service);
    $q = $mysqli->query("SELECT * FROM boom_purchase_store_products WHERE service = '$service' AND enabled = 1 AND min_rank <= '$rank' ORDER BY sort_order ASC, product_id ASC");
    $products = array();
    if($q){
        while($row = $q->fetch_assoc()){
            $products[] = $row;
        }
    }
    return $products;
}

function psStoreCurrencyName($currency){
    return ((int)$currency === 2) ? 'الياقوت' : 'الذهب';
}

function psStoreCurrencyIcon($currency){
    return ((int)$currency === 2) ? rubyIcon() : goldIcon();
}
?>
