<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Stocksync extends Module
{
    public function __construct()
    {
        $this->name = 'stocksync';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Stocksync');
        $this->description = $this->l('Stocksyncer module for syncing stocks');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('STOCKSYNC_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('actionUpdateQuantity');
    }

    public function uninstall()
    {
        Configuration::deleteByName('STOCKSYNC_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitStocksyncModule')) == true) {
            $this->postProcess();
        }

        $sync_url = Tools::getShopDomain(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_);
        $sync_url.= '/stocksync/sync.php?secure_key='.md5(_COOKIE_KEY_.'STOCKSYNC');

        $this->context->smarty->assign(array(
            'sync_url' => $sync_url,
            'module_dir' => $this->_path
        ));

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitStocksyncModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'STOCKSYNC_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable stock syncing'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-truck"></i>',
                        'desc' => $this->l('Enter the endpoint to post the updates to'),
                        'name' => 'STOCKSYNC_ENDPOINT',
                        'label' => $this->l('Endpoint URL'),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'STOCKSYNC_ENABLED' => Configuration::get('STOCKSYNC_ENABLED'),
            'STOCKSYNC_ENDPOINT' => Configuration::get('STOCKSYNC_ENDPOINT', 'http://www.yourdomain.com/stocksync/sync.php?secret_key=13bfasdasdfs4hsrthstha')
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Send stock updates to the other end
     * Executes hook: actionUpdateQuantity
     * @param array $param
     */
    public function hookActionUpdateQuantity($param)
    {
        if((int)Configuration::get('STOCKSYNC_ENABLED') !== 1) {
            return false;
        }

        $url = Configuration::get('STOCKSYNC_ENDPOINT');
        $fields = array(
          'id_product' => urlencode($param['id_product']),
          'id_product_attribute' => urlencode($param['id_product_attribute']),
          'quantity' => urlencode($param['quantity'])
        );

        $fields_string = '';
        foreach($fields as $key=>$value) {
                $fields_string .= $key.'='.$value.'&';
        }
        rtrim($fields_string, '&');

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);
    }

    /**
     * Updates the stock to the POST-ed amount
     * TODO Might need some handling for stock movement etc. in Advanced Stock Management
     */
    public static function update()
    {
        error_log("Updating stock");
        $id_product = (int) $_POST['id_product'];
        $id_product_attribute = (int) $_POST['id_product_attribute'];
        $quantity = (int) $_POST['quantity'];

        $current = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);

        if($current != $quantity) {
            StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity);
        }
    }
}
