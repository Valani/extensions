<?php
class ControllerExtensionModuleOcProductZoom extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/oc_product_zoom');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_oc_product_zoom', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

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
            'href' => $this->url->link('extension/module/oc_product_zoom', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/oc_product_zoom', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        if (isset($this->request->post['module_oc_product_zoom_status'])) {
            $data['module_oc_product_zoom_status'] = $this->request->post['module_oc_product_zoom_status'];
        } else {
            $data['module_oc_product_zoom_status'] = $this->config->get('module_oc_product_zoom_status');
        }

        if (isset($this->request->post['module_oc_product_zoom_factor'])) {
            $data['module_oc_product_zoom_factor'] = $this->request->post['module_oc_product_zoom_factor'];
        } else {
            $data['module_oc_product_zoom_factor'] = $this->config->get('module_oc_product_zoom_factor') ?: '2';
        }

        if (isset($this->request->post['module_oc_product_zoom_width'])) {
            $data['module_oc_product_zoom_width'] = $this->request->post['module_oc_product_zoom_width'];
        } else {
            $data['module_oc_product_zoom_width'] = $this->config->get('module_oc_product_zoom_width') ?: '200';
        }

        if (isset($this->request->post['module_oc_product_zoom_height'])) {
            $data['module_oc_product_zoom_height'] = $this->request->post['module_oc_product_zoom_height'];
        } else {
            $data['module_oc_product_zoom_height'] = $this->config->get('module_oc_product_zoom_height') ?: '200';
        }

        if (isset($this->request->post['module_oc_product_zoom_border_color'])) {
            $data['module_oc_product_zoom_border_color'] = $this->request->post['module_oc_product_zoom_border_color'];
        } else {
            $data['module_oc_product_zoom_border_color'] = $this->config->get('module_oc_product_zoom_border_color') ?: '#999';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/oc_product_zoom', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/oc_product_zoom')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function install() {
        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        
        // Add event to inject zoom JS and CSS
        $this->model_setting_event->addEvent('oc_product_zoom', 'catalog/view/product/product/after', 'extension/module/oc_product_zoom/injectZoom');
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        
        // Remove event
        $this->model_setting_event->deleteEventByCode('oc_product_zoom');
    }
}