<?php
class ModelExtensionModuleImport1C extends Model {
    // Метод для імпорту цін
    public function importPrices() {
        $this->load->model('extension/module/import_1c', 'admin');
        return $this->model_extension_module_import_1c_admin->importPrices();
    }
    
    // Метод для імпорту кількості
    public function importQuantities() {
        $this->load->model('extension/module/import_1c', 'admin');
        return $this->model_extension_module_import_1c_admin->importQuantities();
    }
    
    // Метод для імпорту нових товарів
    public function importNewProducts() {
        $this->load->model('extension/module/import_1c', 'admin');
        return $this->model_extension_module_import_1c_admin->importNewProducts();
    }
}