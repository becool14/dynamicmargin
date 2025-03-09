<?php

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/classes/MarginCalculator.php';
require_once _PS_MODULE_DIR_ . 'dynamicmargin/classes/MarginHistory.php';

class DynamicMargin extends Module
{
    protected $config_form = false;
    
    private $config = [
        'DYNAMICMARGIN_GLOBAL_MARGIN' => 0,
    ];

    public function __construct()
    {
        $this->name = 'dynamicmargin';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap = true;

        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];

        parent::__construct();

        $this->displayName = $this->l('Dynamic Margin');
        $this->description = $this->l('Enables dynamic management of product price margins');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->initializeConfig();
    }

    public function hookDisplayHeader()
    {
        Media::addJsDef([
            'dynamicMarginAjaxUrl' => $this->context->link->getBaseLink() . 'modules/' . $this->name . '/ajax.php',
            'dynamicMarginMessages' => [
                'priceChanged' => $this->l('Prices have been updated due to margin changes')
            ]
        ]);

        $this->context->controller->registerJavascript(
            'modules-' . $this->name,
            'modules/' . $this->name . '/views/js/front.js',
            [
                'position' => 'bottom',
                'priority' => 150,
                'attribute' => 'async'
            ]
        );

        $this->context->controller->registerStylesheet(
            'modules-' . $this->name . '-style',
            'modules/' . $this->name . '/views/css/front.css',
            [
                'media' => 'all',
                'priority' => 150
            ]
        );
    }

    private function initializeConfig()
    {
        $this->config_form = true;
        $this->initializeMarginValue();
    }


    private function initializeMarginValue()
    {
        if (!Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN')) {
            Configuration::updateValue('DYNAMICMARGIN_GLOBAL_MARGIN', 0);
        }
    }

  
    public function install()
    {
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            $this->_errors[] = $this->l('This module requires PHP 7.0 or newer');
            return false;
        }

        if (!extension_loaded('curl')) {
            $this->_errors[] = $this->l('You need to enable cURL extension to use this module.');
            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');

        foreach ($this->config as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return parent::install()
            && $this->registerHook('actionProductPriceCalculation')  
            && $this->registerHook('actionProductListOverride')      
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayTop')
            && $this->registerHook('actionProductUpdate')
            && $this->installDb();
    }


    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        foreach ($this->config as $key => $value) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall() && $this->uninstallDb();
    }

    private function installDb()
    {
        $return = true;
        $sql_file = dirname(__FILE__).'/sql/install.sql';
        
        if (!file_exists($sql_file)) {
            return false;
        }
        
        $sql_content = file_get_contents($sql_file);
        if (!$sql_content) {
            return false;
        }

        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_content = str_replace('ENGINE_TYPE', _MYSQL_ENGINE_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", trim($sql_content));

        foreach ($sql_requests as $sql_request) {
            if (!empty($sql_request)) {
                $return &= Db::getInstance()->execute(trim($sql_request));
            }
        }

        return $return;
    }


    private function uninstallDb()
    {
        $sql_file = dirname(__FILE__).'/sql/uninstall.sql';
        
        if (!file_exists($sql_file)) {
            return false;
        }
        
        $sql_content = file_get_contents($sql_file);
        if (!$sql_content) {
            return false;
        }

        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", trim($sql_content));

        $return = true;
        foreach ($sql_requests as $sql_request) {
            if (!empty($sql_request)) {
                $return &= Db::getInstance()->execute(trim($sql_request));
            }
        }

        return $return;
    }


    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submitDynamicmargin')) {
            $margin_value = (float)Tools::getValue('DYNAMICMARGIN_GLOBAL_MARGIN');
            
            if ($margin_value >= 0) {
                $previous_value = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
                Configuration::updateValue('DYNAMICMARGIN_GLOBAL_MARGIN', $margin_value);
                
                Db::getInstance()->insert('dynamic_margin_history', [
                    'margin_value' => $margin_value,
                    'previous_value' => $previous_value,
                    'date_add' => date('Y-m-d H:i:s'),
                    'id_employee' => (int)Context::getContext()->employee->id
                ]);
                
                $this->updateAllProductPrices();
                $this->clearAllProductsCache();
                
                $output .= $this->displayConfirmation($this->l('Settings updated and prices recalculated'));
                
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules').'&configure='.$this->name.'&conf=4');
            }
        }

        return $output . '
        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
            <div class="panel">
                <div class="panel-heading">
                    ' . $this->l('Dynamic Margin Configuration') . '
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        ' . $this->l('Global Margin (%)') . '
                    </label>
                    <div class="col-lg-9">
                        <input type="number" 
                            name="DYNAMICMARGIN_GLOBAL_MARGIN" 
                            value="' . Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN') . '" 
                            step="0.01"
                            min="0"
                            class="form-control" />
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" 
                            name="submitDynamicmargin" 
                            class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> 
                        ' . $this->l('Save') . '
                    </button>
                </div>
            </div>
        </form>';
    }


    private function logMarginChange($new_value, $previous_value)
    {
        return Db::getInstance()->insert('dynamic_margin_history', [
            'margin_value' => (float)$new_value,
            'previous_value' => (float)$previous_value,
            'date_add' => date('Y-m-d H:i:s'),
            'id_employee' => Context::getContext()->employee->id
        ]);
    }


    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Global Margin (%)'),
                    'name' => 'DYNAMICMARGIN_GLOBAL_MARGIN',
                    'size' => 20,
                    'required' => true,
                    'desc' => $this->l('Enter the global margin percentage'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;

        $helper->fields_value['DYNAMICMARGIN_GLOBAL_MARGIN'] = 
            Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');

        $margin_history = $this->getMarginHistory();

        $this->context->smarty->assign([
            'margin_history' => $margin_history,
            'DYNAMICMARGIN_GLOBAL_MARGIN' => Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN'),
        ]);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
    }


    private function getMarginHistory()
    {
        $query = new DbQuery();
        $query->select('h.*, CONCAT(e.firstname, " ", e.lastname) as employee_name')
              ->from('dynamic_margin_history', 'h')
              ->leftJoin('employee', 'e', 'e.id_employee = h.id_employee')
              ->orderBy('h.date_add DESC')
              ->limit(10);

        return Db::getInstance()->executeS($query);
    }



    public function hookActionProductUpdate($params)
    {
        $id_product = (int)$params['id_product'];
        $product = new Product($id_product);
        $this->updateProductPrice($product);
    }

    public function updateCartPrices($cart)
    {
        if (!$cart) {
            return false;
        }

        try {
            $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
            $pricesChanged = false;

            foreach ($cart->getProducts() as $product) {
                $basePrice = $product['price_without_reduction'];
                $newPrice = $basePrice + ($basePrice * ($margin / 100));
                $newPrice = round($newPrice, 6);

                if (abs($product['price'] - $newPrice) > 0.001) {
                    $pricesChanged = true;
                    
                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'product` 
                        SET `price` = ' . $newPrice . ',
                            `date_upd` = NOW()
                        WHERE `id_product` = ' . (int)$product['id_product']
                    );

                    $this->clearProductCache($product['id_product']);
                }
            }

            if ($pricesChanged) {
                Context::getContext()->cookie->margin_price_changed = true;
                Context::getContext()->cookie->write();

                $cart->update();
            }

            return true;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error updating cart prices: ' . $e->getMessage(), 3);
            return false;
        }
    }


    private function updateProductPrice($product)
    {
        require_once dirname(__FILE__) . '/classes/MarginCalculator.php';
        
        $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
        $calculator = new MarginCalculator($margin);
        
        $basePrice = $product->price;
        $newPrice = $calculator->calculatePriceWithMargin($basePrice);
        
        if ($calculator->hasPriceChanged($product->price, $newPrice)) {
            Db::getInstance()->insert('dynamic_margin_product', [
                'id_product' => (int)$product->id,
                'margin_value' => $margin,
                'date_upd' => date('Y-m-d H:i:s')
            ]);
            
            $product->price = $newPrice;
            $product->update();
            
            $this->clearProductCache($product->id);
        }
    }


    private function updateAllProductPrices()
    {
        try {
            $products = Product::getProducts($this->context->language->id, 0, 0, 'id_product', 'ASC');
            $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
            
            foreach ($products as $product) {
                $basePrice = $product['price'];
                
                $newPrice = $basePrice + ($basePrice * ($margin / 100));
                $newPrice = round($newPrice, 6);
                
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'product` 
                    SET `price` = ' . $newPrice . ',
                        `date_upd` = NOW()
                    WHERE `id_product` = ' . (int)$product['id_product']
                );
                
                $this->clearProductCache($product['id_product']);
                
                PrestaShopLogger::addLog(
                    sprintf(
                        'Product ID %d updated: base price %.2f, margin %.2f%%, new price %.2f',
                        $product['id_product'],
                        $basePrice,
                        $margin,
                        $newPrice
                    )
                );
            }
            
            return true;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error updating prices: ' . $e->getMessage(), 3);
            return false;
        }
    }

    public function hookDisplayTop($params)
    {
        if (Context::getContext()->cookie->margin_price_changed) {
            $this->context->smarty->assign([
                'margin_notification' => $this->l('Prices have been updated due to margin changes')
            ]);
            
            Context::getContext()->cookie->margin_price_changed = null;
            Context::getContext()->cookie->write();
            
            return $this->display(__FILE__, 'views/templates/hook/notification.tpl');
        }
        return '';
    }



    private function clearProductCache($id_product)
    {
        Cache::clean('Product::getPriceStatic_' . $id_product);
        Cache::clean('Product::getPrice_' . $id_product);
    }


    public function hookDisplayProductPriceBlock($params)
    {
        if (!isset($params['product']) || !isset($params['type'])) {
            return;
        }

        if ($params['type'] === 'price') {
            $product = $params['product'];
            $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
            
            if ($product instanceof Product) {
                $basePrice = $product->price;
            } 
            else if (is_array($product) && isset($product['price'])) {
                $basePrice = $product['price'];
            } else {
                return;
            }

            $newPrice = $basePrice + ($basePrice * ($margin / 100));
            $newPrice = round($newPrice, 6);

            $product->price = $newPrice;
        }
    }

    public function hookActionCartUpdateQuantityBefore($params)
    {
        if (!isset($params['cart'])) {
            return;
        }

        $cart = $params['cart'];
        $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
        $pricesChanged = false;
        $priceChanges = [];

        foreach ($cart->getProducts() as $product) {
            $basePrice = $product['price_without_reduction'];
            $newPrice = $basePrice + ($basePrice * ($margin / 100));
            $newPrice = round($newPrice, 6);

            if (abs($product['price'] - $newPrice) > 0.001) {
                $pricesChanged = true;
                
                $priceChanges[] = [
                    'name' => $product['name'],
                    'old_price' => Tools::displayPrice($product['price']),
                    'new_price' => Tools::displayPrice($newPrice)
                ];

                Db::getInstance()->execute('
                    UPDATE `'._DB_PREFIX_.'cart_product`
                    SET `price` = '.(float)$newPrice.'
                    WHERE `id_cart` = '.(int)$cart->id.'
                    AND `id_product` = '.(int)$product['id_product'].'
                    AND `id_product_attribute` = '.(int)$product['id_product_attribute']
                );
            }
        }

        if ($pricesChanged) {
            $this->context->cookie->price_changes = json_encode($priceChanges);
            $this->context->cookie->write();
            
            $cart->update();
        }
    }


    public function hookActionProductPriceCalculation($params)
    {
        if (!isset($params['price']) || !isset($params['id_product'])) {
            return;
        }

        $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');
        $basePrice = $params['price'];
        $newPrice = $basePrice + ($basePrice * ($margin / 100));
        
        $params['price'] = round($newPrice, 6);
    }


    public function hookActionProductListOverride($params)
    {
        if (!isset($params['products'])) {
            return;
        }

        $margin = (float)Configuration::get('DYNAMICMARGIN_GLOBAL_MARGIN');

        foreach ($params['products'] as &$product) {
            $basePrice = $product['price_without_reduction'];
            $newPrice = $basePrice + ($basePrice * ($margin / 100));
            $product['price'] = round($newPrice, 6);
        }
    }

    private function clearAllProductsCache()
    {
        $sql = 'SELECT id_product FROM '._DB_PREFIX_.'product';
        $products = Db::getInstance()->executeS($sql);
        
        foreach ($products as $product) {
            $this->clearProductCache($product['id_product']);
        }
        
        Category::regenerateEntireNtree();
        
        Tools::clearAllCache();
    }
}