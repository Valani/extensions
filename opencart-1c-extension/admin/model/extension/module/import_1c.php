<?php
class ModelExtensionModuleImport1C extends Model {
    // Константи для типів цін
    const PRICE_TYPE_REGULAR = '000000001';
    const PRICE_TYPE_SPECIAL = '000000005';
    
    // Базова конфігурація
    private $batch_size = 1000;
    private $default_config = [
        'price_file' => '/home/ilweb/nawiteh.ua/prices/Prices.xml',
        'quantity_file' => '/home/ilweb/nawiteh.ua/quantities/ZalyshokXML.xml',
        'default_category_id' => 455,
        'default_language_id' => 3,
        'default_store_id' => 0,
        'default_layout_id' => 0,
        'article_attribute_id' => 1958
    ];
    
    /**
     * Отримує конфіг значення з налаштувань або за замовчуванням
     * @param string $key
     * @return mixed
     */
    private function getConfig($key) {
        $config_key = 'module_import_1c_' . $key;
        return $this->config->get($config_key) ?: $this->default_config[$key];
    }
    
    /**
     * Перевіряє наявність файлу
     * @param string $file_path
     * @return SimpleXMLElement
     * @throws Exception
     */
    private function loadXmlFile($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('Файл не знайдено: ' . $file_path);
        }
        
        $content = file_get_contents($file_path);
        if (!$content) {
            throw new Exception('Не вдалося прочитати файл: ' . $file_path);
        }
        
        $xml = simplexml_load_string($content);
        if (!$xml) {
            throw new Exception('Не вдалося розпарсити XML файл: ' . $file_path);
        }
        
        return $xml;
    }
    
    /**
     * Отримує існуючі продукти за артикулами
     * @param array $product_codes
     * @return array
     */
    private function getExistingProducts($product_codes) {
        if (empty($product_codes)) {
            return [];
        }
        
        $existing_products = [];
        $codes_string = "'" . implode("','", array_map([$this->db, 'escape'], $product_codes)) . "'";
        $query = $this->db->query("SELECT product_id, upc FROM " . DB_PREFIX . "product WHERE upc IN (" . $codes_string . ")");
        
        foreach ($query->rows as $row) {
            $existing_products[$row['upc']] = $row['product_id'];
        }
        
        return $existing_products;
    }
    
    /**
     * Виконує масові запити до БД
     * @param string $query_prefix Початок запиту
     * @param array $values Значення для вставки
     * @param string $query_suffix Закінчення запиту (опціонально)
     * @return void
     */
    private function executeBatchQuery($query_prefix, $values, $query_suffix = '') {
        if (empty($values)) {
            return;
        }
        
        $chunks = array_chunk($values, $this->batch_size);
        foreach ($chunks as $chunk) {
            $this->db->query($query_prefix . implode(',', $chunk) . $query_suffix);
        }
    }
    
    /**
     * Нормалізує числове значення з XML
     * @param string $value
     * @param bool $is_float
     * @return float|int
     */
    private function normalizeNumericValue($value, $is_float = true) {
        $normalized = str_replace([' ', ' ', ','], ['', '', '.'], strval($value));
        return $is_float ? floatval($normalized) : intval($normalized);
    }
    
    /**
     * Імпорт цін з 1С
     * @return array
     */
    public function importPrices() {
        $updated = 0;
        $errors = 0;
        $price_updates = [];
        $special_updates = [];
        $special_product_ids = [];
        
        try {
            $price_file = $this->getConfig('price_file');
            $feed = $this->loadXmlFile($price_file);
            $products = $feed->DECLARHEAD->products->product;
            
            // Збір кодів продуктів для масового запиту
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Отримання існуючих продуктів
            $existing_products = $this->getExistingProducts($product_codes);
            
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (!isset($existing_products[$code])) {
                    continue;
                }
                
                $product_id = $existing_products[$code];
                $price_value = $this->normalizeNumericValue(strval($product->pricevalue));
                $price_type = strval($product->pricetype);
                
                if ($price_type === self::PRICE_TYPE_REGULAR) {
                    $price_updates[] = "(" . $product_id . ", " . $price_value . ")";
                    $updated++;
                } elseif ($price_type === self::PRICE_TYPE_SPECIAL) {
                    $special_updates[] = "(" . $product_id . ", 2, " . $price_value . ", 1, '0000-00-00', '0000-00-00')";
                    $special_product_ids[] = $product_id;
                    $updated++;
                }
            }
            
            // Масове оновлення звичайних цін
            $this->executeBatchQuery(
                "INSERT INTO " . DB_PREFIX . "product (product_id, price) VALUES ", 
                $price_updates, 
                " ON DUPLICATE KEY UPDATE price = VALUES(price)"
            );
            
            // Видалення старих спеціальних цін перед вставкою нових
            if (!empty($special_product_ids)) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id IN (" . implode(',', array_unique($special_product_ids)) . ")");
                
                // Масове оновлення спеціальних цін
                $this->executeBatchQuery(
                    "INSERT INTO " . DB_PREFIX . "product_special (product_id, customer_group_id, price, priority, date_start, date_end) VALUES ", 
                    $special_updates
                );
            }
            
            return ['updated' => $updated, 'errors' => $errors];
            
        } catch (Exception $e) {
            // Логування помилки
            $this->log->write('Import1C Price Error: ' . $e->getMessage());
            return ['updated' => $updated, 'errors' => $errors + 1, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Імпорт кількості з 1С
     * @return array
     */
    public function importQuantities() {
        $updated = 0;
        $errors = 0;
        $quantity_updates = [];
        $ids_exists = [];
        
        try {
            $quantity_file = $this->getConfig('quantity_file');
            $feed = $this->loadXmlFile($quantity_file);
            $products = $feed->DECLARHEAD->products->product;
            
            // Збір кодів продуктів для масового запиту
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Отримання існуючих продуктів
            $existing_products = $this->getExistingProducts($product_codes);
            
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (!isset($existing_products[$code])) {
                    continue;
                }
                
                $product_id = $existing_products[$code];
                $quantity = $this->normalizeNumericValue(strval($product->quantity), false);
                
                $ids_exists[] = $product_id;
                $quantity_updates[] = "(" . $product_id . ", " . $quantity . ")";
                $updated++;
            }
            
            // Масове оновлення залишків
            $this->executeBatchQuery(
                "INSERT INTO " . DB_PREFIX . "product (product_id, quantity) VALUES ", 
                $quantity_updates, 
                " ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)"
            );
            
            // Обнулення кількості для продуктів відсутніх у файлі
            if (!empty($ids_exists)) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = 0 WHERE product_id NOT IN (" . implode(',', $ids_exists) . ")");
            }
            
            return ['updated' => $updated, 'errors' => $errors];
            
        } catch (Exception $e) {
            // Логування помилки
            $this->log->write('Import1C Quantity Error: ' . $e->getMessage());
            return ['updated' => $updated, 'errors' => $errors + 1, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Імпорт нових товарів з 1С
     * @return array
     */
    public function importNewProducts() {
        $created = 0;
        $errors = 0;
        $product_inserts = [];
        $product_data = []; // Зберігаємо дані продукту для подальшого використання
        
        try {
            $quantity_file = $this->getConfig('quantity_file');
            $feed = $this->loadXmlFile($quantity_file);
            $products = $feed->DECLARHEAD->products->product;
            
            // Збір кодів продуктів для масового запиту
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Перевірка наявності продуктів у базі
            $existing_products = [];
            if (!empty($product_codes)) {
                $codes_string = "'" . implode("','", array_map([$this->db, 'escape'], $product_codes)) . "'";
                $query = $this->db->query("SELECT upc FROM " . DB_PREFIX . "product WHERE upc IN (" . $codes_string . ")");
                
                foreach ($query->rows as $row) {
                    $existing_products[$row['upc']] = true;
                }
            }
            
            // Підготовка даних для вставки нових продуктів
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (isset($existing_products[$code])) {
                    continue;
                }
                
                $quantity = $this->normalizeNumericValue(strval($product->quantity), false);
                $article_parts = explode(' ', strval($product['article']));
                $article = $article_parts[0];
                $productname = $article . ' ' . strval($product['productname']);
                $current_time = date('Y-m-d H:i:s');
                
                // Зберігаємо дані продукту для подальшого використання
                $product_data[] = [
                    'name' => $productname,
                    'article' => $article,
                    'code' => $code
                ];
                
                // Підготовка вставки продукту
                $product_inserts[] = "('" . $this->db->escape($article) . "', '" . 
                                    $this->db->escape($article) . "', '" . 
                                    $this->db->escape($code) . "', " . 
                                    $quantity . ", 7, 0, 0, '" . 
                                    $current_time . "', '" . $current_time . "')";
                
                $created++;
            }
            
            // Якщо є нові продукти для вставки
            if (!empty($product_inserts)) {
                // Виконуємо пакетну вставку та отримуємо створені ID
                $chunks = array_chunk($product_inserts, $this->batch_size);
                $chunk_sizes = array_map('count', $chunks);
                $data_index = 0;
                $store_inserts = [];
                $layout_inserts = [];
                $category_inserts = [];
                $description_inserts = [];
                $seo_inserts = [];
                $attribute_inserts = [];
                
                $default_category_id = $this->getConfig('default_category_id');
                $default_language_id = $this->getConfig('default_language_id');
                $default_store_id = $this->getConfig('default_store_id');
                $default_layout_id = $this->getConfig('default_layout_id');
                $article_attribute_id = $this->getConfig('article_attribute_id');
                
                foreach ($chunks as $i => $chunk) {
                    // Отримуємо поточний AUTO_INCREMENT перед вставкою
                    $query = $this->db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "product'");
                    $start_id = (int)$query->row['AUTO_INCREMENT'];
                    
                    // Виконуємо вставку товарів
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product 
                        (model, sku, upc, quantity, stock_status_id, price, status, date_added, date_modified) 
                        VALUES " . implode(',', $chunk));
                    
                    // Розраховуємо правильний діапазон вставлених ID
                    $chunk_size = $chunk_sizes[$i];
                    $product_ids = range($start_id, $start_id + $chunk_size - 1);
                    
                    // Підготовляємо пов'язані дані для вставки
                    foreach ($product_ids as $product_id) {
                        if (isset($product_data[$data_index])) {
                            $product_info = $product_data[$data_index];
                            
                            // Підготовка даних магазину
                            $store_inserts[] = "(" . $product_id . ", " . $default_store_id . ")";
                            
                            // Підготовка даних макету
                            $layout_inserts[] = "(" . $product_id . ", " . $default_store_id . ", " . $default_layout_id . ")";
                            
                            // Підготовка даних категорії
                            $category_inserts[] = "(" . $product_id . ", " . $default_category_id . ")";
                            
                            // Підготовка даних опису
                            $description_inserts[] = "(" . $product_id . ", " . $default_language_id . ", '" . 
                                                $this->db->escape($product_info['name']) . "')";
                            
                            // Генерація SEO URL
                            $slug = $this->generateSeoUrl($product_info['name']);
                            $seo_inserts[] = "(" . $default_store_id . ", " . $default_language_id . ", 'product_id=" . 
                                            $product_id . "', '" . $this->db->escape($slug) . "')";
                            
                            // Додавання артикула як атрибуту
                            $attribute_inserts[] = "(" . $product_id . ", " . $article_attribute_id . ", " . 
                                                 $default_language_id . ", '" . $this->db->escape($product_info['article']) . "')";
                            
                            $data_index++;
                        }
                    }
                }
                
                // Вставка пов'язаних даних
                if (!empty($store_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "product_to_store (product_id, store_id) VALUES ", 
                        $store_inserts
                    );
                }
                
                if (!empty($layout_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "product_to_layout (product_id, store_id, layout_id) VALUES ", 
                        $layout_inserts
                    );
                }
                
                if (!empty($category_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "product_to_category (product_id, category_id) VALUES ", 
                        $category_inserts
                    );
                }
                
                if (!empty($description_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "product_description (product_id, language_id, name) VALUES ", 
                        $description_inserts
                    );
                }
                
                if (!empty($seo_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "seo_url (store_id, language_id, query, keyword) VALUES ", 
                        $seo_inserts
                    );
                }
                
                if (!empty($attribute_inserts)) {
                    $this->executeBatchQuery(
                        "INSERT INTO " . DB_PREFIX . "product_attribute (product_id, attribute_id, language_id, text) VALUES ", 
                        $attribute_inserts
                    );
                }
            }
            
            return ['created' => $created, 'errors' => $errors];
            
        } catch (Exception $e) {
            // Логування помилки
            $this->log->write('Import1C New Products Error: ' . $e->getMessage());
            return ['created' => $created, 'errors' => $errors + 1, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Генерація SEO URL на основі назви
     * @param string $text
     * @return string
     */
    public function generateSeoUrl($text) {
        // Транслітерація українських символів
        $cyr = [
            'Є','Ї','І','Ґ','є','ї','і','ґ','ж','ч','щ','ш','ю','а','б','в','г','д','е','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ь','я',
            'Ж','Ч','Щ','Ш','Ю','А','Б','В','Г','Д','Е','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ь','Я'
        ];
        
        $lat = [
            'Ye','Yi','I','G','ye','yi','i','g','zh','ch','shch','sh','yu','a','b','v','h','d','e','z','y','i','k','l','m','n','o','p','r','s','t','u','f','kh','ts','','ia',
            'Zh','Ch','Shch','Sh','Yu','A','B','V','H','D','E','Z','Y','I','K','L','M','N','O','P','R','S','T','U','F','Kh','Ts','','Ya'
        ];
        
        // Перетворення тексту
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace($cyr, $lat, $text);
        
        // Заміна спеціальних символів
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        
        // Обмеження довжини
        $text = mb_substr($text, 0, 64, 'UTF-8');
        $text = rtrim($text, '-');
        
        // Перевірка на унікальність
        $base_slug = $text;
        $counter = 1;
        
        while ($this->isSeoUrlExists($text)) {
            $text = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $text;
    }
    
    /**
     * Перевірка існування SEO URL
     * @param string $keyword
     * @return bool
     */
    private function isSeoUrlExists($keyword) {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "seo_url WHERE `keyword` = '" . $this->db->escape($keyword) . "'");
        return (int)$query->row['total'] > 0;
    }
}