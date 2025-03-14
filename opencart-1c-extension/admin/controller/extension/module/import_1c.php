<?php
class ControllerExtensionModuleImport1C extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/import_1c');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_import_1c', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_price_file'] = $this->language->get('entry_price_file');
        $data['entry_quantity_file'] = $this->language->get('entry_quantity_file');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_update_prices'] = $this->language->get('button_update_prices');
        $data['button_update_quantities'] = $this->language->get('button_update_quantities');
        $data['button_update_products'] = $this->language->get('button_update_products');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/import_1c', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/import_1c', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['update_prices'] = $this->url->link('extension/module/import_1c/updatePrices', 'user_token=' . $this->session->data['user_token'], true);
        $data['update_quantities'] = $this->url->link('extension/module/import_1c/updateQuantities', 'user_token=' . $this->session->data['user_token'], true);
        $data['update_products'] = $this->url->link('extension/module/import_1c/updateProducts', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->post['module_import_1c_status'])) {
            $data['module_import_1c_status'] = $this->request->post['module_import_1c_status'];
        } else {
            $data['module_import_1c_status'] = $this->config->get('module_import_1c_status');
        }

        if (isset($this->request->post['module_import_1c_price_file'])) {
            $data['module_import_1c_price_file'] = $this->request->post['module_import_1c_price_file'];
        } else {
            $data['module_import_1c_price_file'] = $this->config->get('module_import_1c_price_file');
        }

        if (isset($this->request->post['module_import_1c_quantity_file'])) {
            $data['module_import_1c_quantity_file'] = $this->request->post['module_import_1c_quantity_file'];
        } else {
            $data['module_import_1c_quantity_file'] = $this->config->get('module_import_1c_quantity_file');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/import_1c', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/import_1c')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function install() {
        // Інсталяція модуля
    }

    public function uninstall() {
        // Деінсталяція модуля
    }

    // Ручне оновлення цін
    public function updatePrices() {
        $this->load->model('extension/module/import_1c');
        $result = $this->model_extension_module_import_1c->importPrices();
        
        $this->session->data['success'] = sprintf($this->language->get('text_prices_updated'), $result['updated'], $result['errors']);
        $this->response->redirect($this->url->link('extension/module/import_1c', 'user_token=' . $this->session->data['user_token'], true));
    }

    // Ручне оновлення кількості
    public function updateQuantities() {
        $this->load->model('extension/module/import_1c');
        $result = $this->model_extension_module_import_1c->importQuantities();
        
        $this->session->data['success'] = sprintf($this->language->get('text_quantities_updated'), $result['updated'], $result['errors']);
        $this->response->redirect($this->url->link('extension/module/import_1c', 'user_token=' . $this->session->data['user_token'], true));
    }

    // Ручне оновлення/створення нових товарів
    public function updateProducts() {
        $this->load->model('extension/module/import_1c');
        $result = $this->model_extension_module_import_1c->importNewProducts();
        
        $this->session->data['success'] = sprintf($this->language->get('text_products_updated'), $result['created'], $result['errors']);
        $this->response->redirect($this->url->link('extension/module/import_1c', 'user_token=' . $this->session->data['user_token'], true));
    }
}