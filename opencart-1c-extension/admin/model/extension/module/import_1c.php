<?php
class ModelExtensionModuleImport1C extends Model {

    // Імпорт цін з 1С
    public function importPrices() {
        $updated = 0;
        $errors = 0;
        $batch_size = 1000;
        $price_updates = [];
        $special_updates = [];
        
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
            
            // Get all product codes at once
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Get all existing products in one query
            $existing_products = [];
            $codes_string = "'" . implode("','", array_map([$this->db, 'escape'], $product_codes)) . "'";
            $query = $this->db->query("SELECT product_id, upc FROM " . DB_PREFIX . "product WHERE upc IN (" . $codes_string . ")");
            foreach ($query->rows as $row) {
                $existing_products[$row['upc']] = $row['product_id'];
            }
            
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (isset($existing_products[$code])) {
                    $product_ex_id = $existing_products[$code];
                    $pricevalue = floatval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->pricevalue)));
                    
                    if (strval($product->pricetype) == '000000001') {
                        $price_updates[] = "(" . $product_ex_id . ", " . floatval($pricevalue) . ")";
                        $updated++;
                    }
                    
                    if (strval($product->pricetype) == '000000005') {
                        $special_updates[] = "(" . $product_ex_id . ", 2, " . floatval($pricevalue) . ", 1, '0000-00-00', '0000-00-00')";
                        $updated++;
                    }
                }
            }
            
            // Batch update prices
            if (!empty($price_updates)) {
                $chunks = array_chunk($price_updates, $batch_size);
                foreach ($chunks as $chunk) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product (product_id, price) VALUES " . implode(',', $chunk) . 
                                    " ON DUPLICATE KEY UPDATE price = VALUES(price)");
                }
            }
            
            // Batch update special prices
            if (!empty($special_updates)) {
                // First delete all special prices for these products
                $product_ids = array_unique(array_map(function($item) {
                    return explode(',', trim($item, '()'))[0];
                }, $special_updates));
                
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id IN (" . implode(',', $product_ids) . ")");
                
                // Then insert new special prices in batches
                $chunks = array_chunk($special_updates, $batch_size);
                foreach ($chunks as $chunk) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_special (product_id, customer_group_id, price, priority, date_start, date_end) VALUES " . implode(',', $chunk));
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
        $batch_size = 1000;
        $quantity_updates = [];
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
            
            // Get all product codes at once
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Get all existing products in one query
            $existing_products = [];
            $codes_string = "'" . implode("','", array_map([$this->db, 'escape'], $product_codes)) . "'";
            $query = $this->db->query("SELECT product_id, upc FROM " . DB_PREFIX . "product WHERE upc IN (" . $codes_string . ")");
            foreach ($query->rows as $row) {
                $existing_products[$row['upc']] = $row['product_id'];
            }
            
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (isset($existing_products[$code])) {
                    $product_ex_id = $existing_products[$code];
                    $quantity = intval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->quantity)));
                    
                    if (!in_array($product_ex_id, $ids_exists)) {
                        $ids_exists[] = $product_ex_id;
                    }
                    
                    $quantity_updates[] = "(" . $product_ex_id . ", " . intval($quantity) . ")";
                    $updated++;
                }
            }
            
            // Batch update quantities
            if (!empty($quantity_updates)) {
                $chunks = array_chunk($quantity_updates, $batch_size);
                foreach ($chunks as $chunk) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product (product_id, quantity) VALUES " . implode(',', $chunk) . 
                                    " ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
                }
            }
            
            // Set quantity to 0 for products not in the file
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
        $batch_size = 1000;
        $product_inserts = [];
        $store_inserts = [];
        $layout_inserts = [];
        $category_inserts = [];
        $description_inserts = [];
        $seo_inserts = [];
        $attribute_inserts = [];
        
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
            
            // Get all product codes at once
            $product_codes = [];
            foreach ($products as $product) {
                $product_codes[] = strval($product['code']);
            }
            
            // Get all existing products in one query
            $existing_products = [];
            $codes_string = "'" . implode("','", array_map([$this->db, 'escape'], $product_codes)) . "'";
            $query = $this->db->query("SELECT upc FROM " . DB_PREFIX . "product WHERE upc IN (" . $codes_string . ")");
            foreach ($query->rows as $row) {
                $existing_products[$row['upc']] = true;
            }
            
            foreach ($products as $product) {
                $code = strval($product['code']);
                if (!isset($existing_products[$code])) {
                    $quantity = intval(str_replace([' ', ' ', ','], ['', '', '.'], strval($product->quantity)));
                    $article = explode(' ', strval($product['article']));
                    $productname = $article[0] . ' ' . strval($product['productname']);
                    $current_time = date('Y-m-d H:i:s');
                    
                    // Prepare product insert
                    $product_inserts[] = "('" . $this->db->escape($article[0]) . "', '" . 
                                        $this->db->escape($article[0]) . "', '" . 
                                        $this->db->escape($code) . "', " . 
                                        intval($quantity) . ", 7, 0, 0, '" . 
                                        $current_time . "', '" . $current_time . "')";
                    
                    $created++;
                }
            }
            
            // Batch insert products
            if (!empty($product_inserts)) {
                $chunks = array_chunk($product_inserts, $batch_size);
                foreach ($chunks as $chunk) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product (model, sku, upc, quantity, stock_status_id, price, status, date_added, date_modified) VALUES " . implode(',', $chunk));
                    
                    // Get the inserted product IDs
                    $last_id = $this->db->getLastId();
                    $product_ids = range($last_id - count($chunk) + 1, $last_id);
                    
                    // Prepare related inserts
                    foreach ($product_ids as $product_id) {
                        $store_inserts[] = "(" . $product_id . ", 0)";
                        $layout_inserts[] = "(" . $product_id . ", 0, 0)";
                        $category_inserts[] = "(" . $product_id . ", 455)";
                        $description_inserts[] = "(" . $product_id . ", 3, '" . $this->db->escape($productname) . "')";
                        
                        // Generate SEO URL
                        $slug = $this->generateSeoUrl($productname);
                        $seo_inserts[] = "(0, 3, 'product_id=" . $product_id . "', '" . $this->db->escape($slug) . "')";
                        
                        // Add article as attribute
                        $attribute_inserts[] = "(" . $product_id . ", 1958, 3, '" . $this->db->escape($article[0]) . "')";
                    }
                }
                
                // Batch insert related data
                if (!empty($store_inserts)) {
                    $chunks = array_chunk($store_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store (product_id, store_id) VALUES " . implode(',', $chunk));
                    }
                }
                
                if (!empty($layout_inserts)) {
                    $chunks = array_chunk($layout_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout (product_id, store_id, layout_id) VALUES " . implode(',', $chunk));
                    }
                }
                
                if (!empty($category_inserts)) {
                    $chunks = array_chunk($category_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category (product_id, category_id) VALUES " . implode(',', $chunk));
                    }
                }
                
                if (!empty($description_inserts)) {
                    $chunks = array_chunk($description_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_description (product_id, language_id, name) VALUES " . implode(',', $chunk));
                    }
                }
                
                if (!empty($seo_inserts)) {
                    $chunks = array_chunk($seo_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url (store_id, language_id, query, keyword) VALUES " . implode(',', $chunk));
                    }
                }
                
                if (!empty($attribute_inserts)) {
                    $chunks = array_chunk($attribute_inserts, $batch_size);
                    foreach ($chunks as $chunk) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute (product_id, attribute_id, language_id, text) VALUES " . implode(',', $chunk));
                    }
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