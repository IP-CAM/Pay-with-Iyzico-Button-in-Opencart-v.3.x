<?php

class ControllerExtensionModulePaywithiyzicobutton extends Controller {
    private $error = array();

    const DEFAULT_MODULE_SETTINGS = [
        'name' => 'Pay With Iyzico + Button',
        'status' => 1,
        'module_paywithiyzicobutton_api_channel' => 'sandbox',
        'module_paywithiyzicobutton_apiKey' => '',
        'module_paywithiyzicobutton_secretKey' => '',
        'module_paywithiyzicobutton_order_status' => 5,
        'module_paywithiyzicobutton_order_cancel_status' => 7,
        'module_paywithiyzicobutton_status' => 0
    ];

    private $fields = array(
        array(
            'validateField' => 'error_api_channel',
            'name'          => 'module_paywithiyzicobutton_api_channel',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'module_paywithiyzicobutton_status',
        ),
        array(
            'validateField' => 'error_api_key',
            'name'          => 'module_paywithiyzicobutton_apiKey',
        ),
        array(
            'validateField' => 'error_secret_key',
            'name'          => 'module_paywithiyzicobutton_secretKey',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'module_paywithiyzicobutton_order_status',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'module_paywithiyzicobutton_order_cancel_status',
        )

    );

    public function addModule()
    {
        $this->load->model('setting/module');

        $this->model_setting_module->addModule('paywithiyzicobutton', self::DEFAULT_MODULE_SETTINGS);

        return $this->db->getLastId();
    }

    public function deleteModule()
    {
        $this->load->model('setting/module');

        $this->model_setting_module->deleteModulesByCode('paywithiyzicobutton');

        return $this->db->getLastId();
    }

    public function index() {

        $this->load->language('extension/module/paywithiyzicobutton');
        $this->load->model('setting/setting');
        $this->load->model('setting/module');
        $this->load->model('user/user');
        $this->load->model('extension/module/paywithiyzicobutton');

        if (!isset($this->request->get['module_id'])) {
            $module = $this->model_setting_module->getModulesByCode('paywithiyzicobutton');
            $module_id = $module[0]['module_id'];
        }else {
            $module_id = $this->request->get['module_id'];
        }


        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $request = $this->requestIyzico($this->request->post,'add','');
            $request['name'] = 'Pay With Iyzico + Button';
            $request['status'] = 1;
            $this->model_setting_module->editModule($module_id, $request);
            unset($request['status']);
            $this->model_setting_setting->editSetting('module_paywithiyzicobutton',$request);

            $this->response->redirect($this->url->link('extension/module/paywithiyzicobutton', 'user_token=' . $this->session->data['user_token'] . '&type=module'. '&module_id=' . $module_id, true));
        }

        /* Get Order Status */
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['action']         = $this->url->link('extension/module/paywithiyzicobutton', 'user_token=' . $this->session->data['user_token']. '&module_id=' . $module_id, true);
        $data['heading_title']  = $this->language->get('heading_title');
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        $data['locale']         = $this->language->get('code');

        foreach ($this->fields as $key => $field) {

            if (isset($this->error[$field['validateField']])) {
                $data[$field['validateField']] = $this->error[$field['validateField']];
            } else {
                $data[$field['validateField']] = '';
            }

            if (isset($this->request->post[$field['name']])) {
                $data[$field['name']] = $this->request->post[$field['name']];
            } else {
                $data[$field['name']] = $this->config->get($field['name']);
            }
        }



        $this->response->setOutput($this->load->view('extension/module/paywithiyzicobutton', $data));

    }

    public function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/paywithiyzicobutton')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        foreach ($this->fields as $key => $field) {

            if($field['validateField'] != 'blank') {

                if (!$this->request->post[$field['name']]){
                    $this->error[$field['validateField']] = $this->language->get($field['validateField']);
                }
            }

        }

        return !$this->error;
    }

    public function install() {
        $this->load->model('extension/module/paywithiyzicobutton');
        $this->model_extension_module_paywithiyzicobutton->install();
        $this->addModule();
    }

    public function uninstall() {
        $this->load->model('extension/module/paywithiyzicobutton');
        $this->load->model('setting/setting');

        $this->model_extension_module_paywithiyzicobutton->uninstall();
        $this->model_setting_setting->deleteSetting('module_paywithiyzicobutton');
        $this->deleteModule();
    }

    public function requestIyzico($request,$method_type,$extra_request = false) {

        $request_modify = array();

        if ($method_type == 'add') {


            foreach ($this->fields as $key => $field) {

                if(isset($request[$field['name']])) {

                    if($field['name'] == 'module_paywithiyzicobutton_api_key' || $field['name'] == 'module_paywithiyzicobutton_secret_key')
                        $request[$field['name']] = str_replace(' ','',$request[$field['name']]);

                    $request_modify[$field['name']] = $request[$field['name']];

                }

            }


        }



        return $request_modify;
    }

}