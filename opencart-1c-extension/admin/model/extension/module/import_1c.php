<?php
class ModelExtensionModuleImport1C extends Model {

    // Імпорт цін з 1С
    public function importPrices() {
        $updated = 0;
        $errors = 0;
        
        $price_file = $this->config->get('module_import_1c_price_file');
        if (!$price_file) {
            $price_file = '/home/ilweb/nawiteh.ua/prices/Prices.xml';
        }
        
        try {
            // Перевірка наявності файлу
            if (!file_exists($price_file)) {
                throw new Exception('Файл не знайдено: ' . $price_file);
            }

            $feed = simplexml_load_string(file_get_contents($price_file));
            $products = $feed->DECLARHEAD->products->product;
            
            foreach ($products as $product) {
                // Використовуємо upc замість id_1c
                $ex_products = $this->db->query("SELECT product_id, stock_status_id FROM " . DB_PREFIX . "product WHERE upc = '" . strval($product['code']) . "'");
                $ex_products = $ex_products->rows;
                
                if (!empty($ex_products)) {
                    foreach ($ex_products as $ex_product) {
                        $product_ex_id = $ex_product['product_id'];
                        $pricevalue = floatval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->pricevalue)));
                        
                        if (strval($product->pricetype) == '000000001') {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET price = " . floatval($pricevalue) . " WHERE product_id = " . $product_ex_id);
                            $updated++;
                        }
                        
                        if (strval($product->pricetype) == '000000005') {
                            $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = " . intval($product_ex_id));
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET 
                                product_id = " . intval($product_ex_id) . ", 
                                customer_group_id = 2, 
                                price = " . floatval($pricevalue) . ",
                                priority = 1,
                                date_start = '0000-00-00',
                                date_end = '0000-00-00'");
                            $updated++;
                        }
                    }
                }
            }
            
            return ['updated' => $updated, 'errors' => $errors];
            
        } catch (Exception $e) {
            return ['updated' => $updated, 'errors' => $errors + 1];
        }
    }

    // Імпорт кількості з 1С
    public function importQuantities() {
        $updated = 0;
        $errors = 0;
        $ids_exists = [];
        
        $quantity_file = $this->config->get('module_import_1c_quantity_file');
        if (!$quantity_file) {
            $quantity_file = '/home/ilweb/nawiteh.ua/quantities/ZalyshokXML.xml';
        }
        
        try {
            // Перевірка наявності файлу
            if (!file_exists($quantity_file)) {
                throw new Exception('Файл не знайдено: ' . $quantity_file);
            }

            $feed = simplexml_load_string(file_get_contents($quantity_file));
            $products = $feed->DECLARHEAD->products->product;
            
            foreach ($products as $product) {
                // Використовуємо upc замість id_1c
                $ex_products = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE upc = '" . strval($product['code']) . "'");
                $ex_products = $ex_products->rows;
                $quantity = intval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->quantity)));
                
                if (!empty($ex_products)) {
                    foreach ($ex_products as $ex_product) {
                        $product_ex_id = $ex_product['product_id'];
                        if (!in_array($product_ex_id, $ids_exists)) {
                            $ids_exists[] = $product_ex_id;
                        }
                        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = " . intval($quantity) . " WHERE product_id = " . intval($product_ex_id));
                        $updated++;
                    }
                }
            }
            
            // Встановлення кількості 0 для товарів, яких немає в файлі
            if (!empty($ids_exists)) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = 0 WHERE product_id NOT IN (" . implode(',', $ids_exists) . ")");
            }
            
            return ['updated' => $updated, 'errors' => $errors];
            
        } catch (Exception $e) {
            return ['updated' => $updated, 'errors' => $errors + 1];
        }
    }

    // Імпорт нових товарів з 1С
    public function importNewProducts() {
        $created = 0;
        $errors = 0;
        
        $quantity_file = $this->config->get('module_import_1c_quantity_file');
        if (!$quantity_file) {
            $quantity_file = '/home/ilweb/nawiteh.ua/quantities/ZalyshokXML.xml';
        }
        
        try {
            // Перевірка наявності файлу
            if (!file_exists($quantity_file)) {
                throw new Exception('Файл не знайдено: ' . $quantity_file);
            }

            $feed = simplexml_load_string(file_get_contents($quantity_file));
            $products = $feed->DECLARHEAD->products->product;
            
            foreach ($products as $product) {
                // Використовуємо upc замість id_1c
                $ex_products = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE upc = '" . strval($product['code']) . "'");
                $ex_products = $ex_products->rows;
                $quantity = intval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->quantity)));
                $article = explode(' ', strval($product['article']));
                $productname = $article[0] . ' ' . strval($product['productname']);
                
                if (empty($ex_products)) {
                    // Створення нового товару
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product SET 
                        model = '" . $this->db->escape($article[0]) . "', 
                        sku = '" . $this->db->escape($article[0]) . "', 
                        upc = '" . $this->db->escape(strval($product['code'])) . "', 
                        quantity = " . intval($quantity) . ", 
                        stock_status_id = 7, 
                        price = 0, 
                        status = 0, 
                        date_added = '" . date('Y-m-d H:i:s') . "', 
                        date_modified = '" . date('Y-m-d H:i:s') . "'");
                    
                    $product_ex_id = $this->db->getLastId();
                    
                    // Додаткові записи
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = " . intval($product_ex_id) . ", store_id = 0");
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = " . intval($product_ex_id) . ", store_id = 0, layout_id = 0");
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = " . intval($product_ex_id) . ", category_id = 455");
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = " . intval($product_ex_id) . ", language_id = 3, `name` = '" . $this->db->escape($productname) . "'");
                    
                    // SEO URL використовуючи покращену транслітерацію
                    $slug = $this->generateSeoUrl($productname);
                    $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0, language_id = 3, `query` = 'product_id=" . $product_ex_id . "', `keyword` = '" . $this->db->escape($slug) . "'");
                    
                    // Додавання артикулу як атрибуту
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = " . intval($product_ex_id) . ", attribute_id = 1958, language_id = 3, `text` = '" . $this->db->escape($article[0]) . "'");
                    
                    $created++;
                }
            }
            
            return ['created' => $created, 'errors' => $errors];
            
        } catch (Exception $e) {
            return ['created' => $created, 'errors' => $errors + 1];
        }
    }

    // Покращена функція транслітерації для SEO-URL
    public function generateSeoUrl($text) {
        // Масив відповідності українських символів до латинських (покращений для SEO)
        $cyr = [
            'Є','Ї','І','Ґ','є','ї','і','ґ','ж','ч','щ','ш','ю','а','б','в','г','д','е','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ь','я',
            'Ж','Ч','Щ','Ш','Ю','А','Б','В','Г','Д','Е','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ь','Я'
        ];
        
        $lat = [
            'Ye','Yi','I','G','ye','yi','i','g','zh','ch','shch','sh','yu','a','b','v','h','d','e','z','y','i','k','l','m','n','o','p','r','s','t','u','f','kh','ts','','ia',
            'Zh','Ch','Shch','Sh','Yu','A','B','V','H','D','E','Z','Y','I','K','L','M','N','O','P','R','S','T','U','F','Kh','Ts','','Ya'
        ];
        
        // Переведення в нижній регістр та заміна кириличних символів
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace($cyr, $lat, $text);
        
        // Замінюємо всі спеціальні символи на дефіс
        $text = preg_replace('/[^a-z0-9]/', '-', $text);
        
        // Видаляємо повторювані дефіси
        $text = preg_replace('/-+/', '-', $text);
        
        // Видаляємо дефіси на початку і в кінці рядка
        $text = trim($text, '-');
        
        // Обмежуємо довжину slug до 64 символів (оптимально для SEO)
        $text = mb_substr($text, 0, 64, 'UTF-8');
        
        // Видаляємо дефіс в кінці, якщо він залишився після обрізання
        $text = rtrim($text, '-');
        
        // Перевірка на дублікати
        $base_slug = $text;
        $counter = 1;
        
        while (true) {
            $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "seo_url WHERE `keyword` = '" . $this->db->escape($text) . "'");
            
            if ($query->row['total'] == 0) {
                break;
            }
            
            $text = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $text;
    }
}