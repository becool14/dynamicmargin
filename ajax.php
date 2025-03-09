<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');

header('Content-Type: application/json');

try {
    $action = Tools::getValue('action');
    $context = Context::getContext();

    switch ($action) {
        case 'checkPrices':
            $context = Context::getContext();
            $cart = $context->cart;
            
            if (!$cart) {
                die(json_encode([
                    'pricesChanged' => false,
                    'message' => 'No cart found'
                ]));
            }
        
            $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
            $changes = [];
            $pricesChanged = false;

            $lastChange = Db::getInstance()->getValue('
                SELECT date_add 
                FROM `'._DB_PREFIX_.'dynamic_margin_history`
                ORDER BY date_add DESC 
                LIMIT 1
            ');

            if ($lastChange === false) {
                PrestaShopLogger::addLog('SQL error: ' . Db::getInstance()->getMsgError(), 1);
            } else {
                PrestaShopLogger::addLog('Last change date: ' . print_r($lastChange, true), 1);
            }

            PrestaShopLogger::addLog('Last change query result: ' . print_r($lastChange, true), 1);

            if ($lastChange) {
                $currentTime = time();
                $changeTime = strtotime($lastChange);
                
                if (($currentTime - $changeTime) < 86400) {
                    $cartProducts = $cart->getProducts();
                    if (!empty($cartProducts)) {
                        foreach ($cartProducts as $product) {
                            $basePrice = (float)$product['price_without_reduction'];
                            $newPrice = $basePrice + ($basePrice * ($margin / 100));
                            $newPrice = round($newPrice, 2); 

                            $currentPrice = (float)$product['price'];
                            if (abs($currentPrice - $newPrice) > 0.01) { 
                                $pricesChanged = true;
                                $changes[] = [
                                    'name' => $product['name'],
                                    'old_price' => Tools::displayPrice($currentPrice),
                                    'new_price' => Tools::displayPrice($newPrice),
                                    'product_id' => $product['id_product']
                                ];

                                Db::getInstance()->execute('
                                    UPDATE '._DB_PREFIX_.'product
                                    SET price = '.(float)$newPrice.'
                                    WHERE id_product = '.(int)$product['id_product']
                                );
                            }
                        }
                    }
                }
            }

            if ($pricesChanged) {
                Tools::clearCache();
            }

            die(json_encode([
                'pricesChanged' => $pricesChanged,
                'message' => $pricesChanged ? 'Prices were updated due to margin change' : 'Prices did not change',
                'changes' => $changes,
                'timestamp' => time()
            ]));
            break;

        case 'getPrices':
            $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
            $products = Product::getProducts($context->language->id, 0, 0, 'id_product', 'ASC');
            
            $prices = [];
            if (!empty($products)) {
                foreach ($products as $product) {
                    $basePrice = (float)$product['price'];
                    $newPrice = $basePrice + ($basePrice * ($margin / 100));
                    $prices[$product['id_product']] = [
                        'display_price' => Tools::displayPrice(round($newPrice, 2)),
                        'price' => round($newPrice, 2),
                        'product_id' => $product['id_product']
                    ];
                }
            }
            
            die(json_encode([
                'success' => true,
                'prices' => $prices,
                'timestamp' => time()
            ]));
            break;

        default:
            die(json_encode([
                'error' => true,
                'message' => 'Unknown action'
            ]));
    }
} catch (Exception $e) {
    PrestaShopLogger::addLog('DynamicMargin Ajax Error: ' . $e->getMessage(), 3);
    die(json_encode([
        'error' => true,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]));
}