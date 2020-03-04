<?php

class ModelToolImportYmlOc extends Model {
    protected $registry;
    private $categories = array();
    private $keywords = array();
    private $yandexApi = false;

    public function __construct($registry) {
        $this->registry = $registry;

        require_once(DIR_SYSTEM . 'library/urlify.php');
        require_once(DIR_SYSTEM . 'library/yandexapi.php');

        $this->yandexApi = YandexApi::Factory();
    }

    // seo keyword
    private function checkKeywordForDuplicate($keyword, $group_id = false) {
        $duplicate = false;

        if (!empty($this->keywords)) {
            while(in_array($keyword, $this->keywords)) {
                if (!$group_id) {
                    $duplicate = true;

                    return $duplicate;
                }
            }
        } else {
            $query = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($keyword) . "' ");
            if ($query->num_rows > 0) {
                if (!$group_id) {
                    $duplicate = true;

                    return $duplicate;
                }
            }
        }

        return $duplicate;
    }

    private function loadKeywords() {
        $this->keywords = array();
        $query = $this->db->query("SELECT `query`, LOWER(`keyword`) as 'keyword' FROM " . DB_PREFIX . "seo_url ");
        foreach($query->rows as $row) {
            $this->keywords[$row['query']] = $row['keyword'];
        }
        return $query;
    }

    private function urlify($keyword, $group_product_id) {
        $counter_url = 0;
        $urlify = URLify::filter($keyword);
        $k = $url = URLify::transliterate($urlify);
        $this->loadKeywords();

        if ($this->checkKeywordForDuplicate($url, $group_product_id)) {
            do {
                $url =  $k . '-' . ++$counter_url;
            } while ($this->checkKeywordForDuplicate($url, $group_product_id));
        }

        return $url;
    }
    // END seo keyword

    public function getColumns($type_data, $format_data, $import_yml_oc_template_data) {
        $result = array();
        $abstract_field = array();
        $this->load->language('extension/module/' . $format_data . '_import_yml_oc');

        if ($format_data == 'yml') {
            $abstract_field = array(
                'attribute' => array(
                    'attribute_name' => array(
                        'name' => $this->language->get('entry_abstract_field_product_attribute_name'),
                        'field' => 'attribute_name',
                    ),
                    'attribute_group_name' => array(
                        'name' => $this->language->get('entry_abstract_field_product_attribute_group_name'),
                        'field' => 'attribute_group_name',
                    ),
                    'attribute_group_id' => array(
                        'name' => $this->language->get('entry_abstract_field_product_attribute_group_id'),
                        'field' => 'attribute_group_id',
                    ),
                    'text' => array(
                        'name' => $this->language->get('entry_abstract_field_product_attribute_value'),
                        'field' => 'attribute_value',
                    ),
                ),
                'image' => array(
                    'image' => array(
                        'field' => 'images',
                        'name' => $this->language->get('entry_abstract_field_product_images')
                    ),
                ),
                'category' => array(
                    'image' => array(
                        'name' => $this->language->get('entry_abstract_field_category_image'),
                        'field' => 'image',
                    ),
                    'category_whis_path' => array(
                        'field' => 'category_whis_path',
                        'name' => $this->language->get('entry_abstract_field_category_whis_path')
                    ),
                ),
                'filter' => array(
                    'filter_name' => array(
                        'name' => $this->language->get('entry_abstract_field_product_filter_name'),
                        'field' => 'filter_name',
                    ),
                    'filter_group_name' => array(
                        'name' => $this->language->get('entry_abstract_field_product_filter_group_name'),
                        'field' => 'filter_group_name',
                    )
                ),
                'manufacturer' => array(
                    'image' => array(
                        'name' => $this->language->get('entry_abstract_field_manufacturer_image'),
                        'field' => 'image',
                    ),
                ),
                'identificator' => array(
                    'identificator' => array(
                        'name' => $this->language->get('entry_abstract_field_identificator'),
                        'field' => 'identificator',
                    ),
                )
            );
        }

        if ($type_data == 'filter' || $type_data == 'review' || $type_data == 'filter_group' || $type_data == 'option_value' || $type_data == 'option' || $type_data == 'attribute_group' || $type_data == 'attribute' || $type_data == 'category' || $type_data == 'product' || $type_data == 'information' || $type_data == 'manufacturer') {
            $language_exists = FALSE;

            if ($type_data == 'option_value') {
                $this->load->language('catalog/option');
            } else {
                $this->load->model('localisation/language');
                $languages = $this->model_localisation_language->getLanguages();
                $select_language_id = $import_yml_oc_template_data['language_id'];

                foreach ($languages as $language_localisation) {
                    if ($language_localisation['language_id'] == $select_language_id) {
                        if (isset($language_localisation['directory']) && file_exists(DIR_LANGUAGE . $language_localisation['directory'] . '/catalog/' . $type_data . '.php')) {
                            $this->load->language('catalog/' . $type_data);
                            $language_exists = TRUE;
                        }
                    }
                }

                if (!$language_exists) {
                    foreach ($languages as $language_localisation) {
                        if (isset($language_localisation['directory']) && file_exists(DIR_LANGUAGE . $language_localisation['directory'] . '/catalog/' . $type_data . '.php')) {
                            $this->load->language('catalog/' . $type_data);
                            $language_exists = TRUE;
                        }
                    }
                }
            }

            $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data);

            foreach ($columns->rows as $column) {
                if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                    $result[$type_data][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                    $result[$type_data][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                    $result[$type_data][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                }
            }

            ksort($result[$type_data]);

            if ($this->showTable($type_data . '_description', DB_PREFIX)) {
                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_description');

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_description'][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_description'][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_description'][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_description']);
            }

            if ($this->showTable($type_data . '_filter', DB_PREFIX)) {
                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_filter');
                $result[$type_data . '_filter'] = array();

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_filter'][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_filter'][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_filter'][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_filter']);
            }

            if ($type_data == 'category') {
                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_path');
                $result[$type_data . '_path'] = array();

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_path'][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_path'][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_path'][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_path']);
            }
            if ($type_data == 'product') {
                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_image');

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_image'][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_image'][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_image'][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_image']);

                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_option_value');

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_option_value'][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_option_value'][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_option_value'][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_option_value']);

                $type_data_prefix_db = 'attribute';

                $columns = $this->db->query('SHOW COLUMNS FROM ' . DB_PREFIX . $type_data . '_' . $type_data_prefix_db);

                foreach ($columns->rows as $column) {
                    if ($language_exists && $this->language->get('column_' . $column['Field']) && $this->language->get('column_' . $column['Field']) != 'column_' . $column['Field']) {
                        $result[$type_data . '_' . $type_data_prefix_db][$column['Field']] = $this->getInstructionToOption($this->language->get('column_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($language_exists && $this->language->get('entry_' . $column['Field']) && $this->language->get('entry_' . $column['Field']) != 'entry_' . $column['Field']) {
                        $result[$type_data . '_' . $type_data_prefix_db][$column['Field']] = $this->getInstructionToOption($this->language->get('entry_' . $column['Field']), $column['Field'], $column['Type'], $format_data, $type_data);
                    } elseif ($import_yml_oc_template_data['level'] || !$language_exists) {
                        $result[$type_data . '_' . $type_data_prefix_db][$column['Field']] = $this->getInstructionToOption('', $column['Field'], $column['Type'], $format_data, $type_data);
                    }
                }

                ksort($result[$type_data . '_' . $type_data_prefix_db]);
            }
        }

        foreach ($result as $key => $value) {
            // insert id
            $key_parts = explode('_', $key);
            $result[$key_parts[0] . '_identificator']['identificator'] = $abstract_field['identificator']['identificator']['name'];


            foreach ($value as $key2 => $value2) {
                // replacing fields with abstract ones, into which more extended information may come
                if (isset($abstract_field[$key][$key2])) {
                    unset($result[$key][$key2]);
                    $result[$key][$abstract_field[$key][$key2]['field']] = mb_strtoupper($abstract_field[$key][$key2]['name'], 'UTF-8');

                }

                // adding fields to which more advanced information may come
                if (isset($abstract_field[$key])) {
                    foreach ($abstract_field[$key] as $absctract_field_field => $absctract_field__row) {
                        $result[$key][$absctract_field__row['field']] = mb_strtoupper($absctract_field__row['name'], 'UTF-8');
                    }
                }
            }
        }

        $dublicates = array();

        foreach ($result as $key => $value) {
            foreach ($value as $key2 => $value2) {
                if ($key2 == $key . '_id' && !isset($dublicates[$key2])) {
                    $dublicates[$key2] = TRUE;
                } elseif (isset($dublicates[$key2])) {
                    unset($result[$key][$key2]);
                } elseif (($key == 'attribute_description' || $key == 'filter_description') && $key2 == 'name') {
                    unset($result[$key][$key2]);
                } elseif ($key == 'attribute' && $key2 == 'attribute_value') {
                    unset($result[$key][$key2]);
                }
            }
        }

        foreach ($result as $key => $value) {
            if (!$value) {
                unset($result[$key]);
            }
        }

        return $result;
    }

    public function getInstructionToOption($title = '', $field, $type, $format_data, $type_data) {
        $this->load->language('extension/module/import_yml_oc');

        $type_parts = explode('(', $type);

        if (isset($type_parts[0]) && isset($type_parts[1])) {
            $type_parts[1] = str_replace(')', '', $type_parts[1]);
        }

        if ($type_parts[0] == 'int' || $type_parts[0] == 'tinyint' || $type_parts[0] == 'varchar') {
            $type_instruction = sprintf($this->language->get('entry_instruction_to_select_option_' . $type_parts[0]), $type_parts[1]);
        } elseif ($type_parts[0] == 'date' || $type_parts[0] == 'text') {
            $type_instruction = $this->language->get('entry_instruction_to_select_option_' . $type_parts[0]);
        } elseif ($type_parts[0] == 'decimal') {
            $type_parts[1] = explode(',', $type_parts[1]);
            $type_instruction = sprintf($this->language->get('entry_instruction_to_select_option_' . $type_parts[0]), $type_parts[1][1]);
        } else {
            $type_instruction = $type;
        }

        if ($title) {
            $title = mb_strtoupper($title, 'UTF-8') . '. ';
        }

        return $title . $this->language->get('entry_instruction_to_select_option_field_to_db') . ' ' . $field . ' (' . $type_instruction . ')';
    }

    public function showTable($table, $prefix) {
        $query = $query = $this->db->query('SHOW TABLES from `' . DB_DATABASE . '` like "' . $prefix . $table . '" ');

        if ($query->num_rows) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function prepareValue($value, $types_prepare = array('csv'), $clean_all_html = FALSE, $length = 0, $add_str_end = '', $htmlentities = FALSE) {
        foreach ($types_prepare as $type) {
            if ($type == 'csv') {
                $value = trim($value);

                if ($clean_all_html) {
                    $value = strip_tags($value);
                } elseif ($htmlentities) {
                    $value = htmlentities($value, ENT_QUOTES, 'UTF-8');
                }

                if ($length > 0) {
                    $last_value = $value;
                    $value = mb_strcut($value, 0, $length, 'UTF-8');

                    if ($add_str_end && $last_value != $value) {
                        $value .= $add_str_end;
                    }
                }

                $first_letter = mb_strcut($value, 0, 1, 'UTF-8');
                $last_letter = mb_strcut($value, (mb_strlen($value, 'UTF-8') - 1), mb_strlen($value, 'UTF-8'), 'UTF-8');

                if ($first_letter == '"') {
                    $value = mb_strcut($value, 1, mb_strlen($value, 'UTF-8'), 'UTF-8');
                }
                if ($last_letter == '"') {
                    $value = mb_strcut($value, 0, (mb_strlen($value, 'UTF-8') - 1), 'UTF-8');
                }
            }
        }

        return $value;
    }

    public function getXMLRows($start, $limit, $template_data, $url = '', $clean_all_html = FALSE, $length = 0, $add_str_end = '', $htmlentities = FALSE, $file_info = FALSE, $file_upload = '') {
        $result['count_offers'] = 0;
        $result['count_categories'] = 0;
        $result['offer_elements'] = array();
        $result['offer_attributes'] = array();
        $result['offer_params'] = array();
        $result['yml_categories'] = array();
        $result['xml'] = array();

        if ($url) {
            $xml = simplexml_load_file(html_entity_decode($url));
        } elseif ($file_upload) {
            $xml = simplexml_load_file(DIR_DOWNLOAD . $file_upload);
        }

        if ($xml) {
            if ($file_info) {
                if (isset($xml->shop->categories->category)) {
                    $result['count_categories'] = count($xml->shop->categories->category);
                    $result['yml_categories'] = $this->updateCategories($template_data, $xml->shop->categories, TRUE, "&nbsp;&nbsp;&lt;&nbsp;&nbsp;");
                }

                if (isset($xml->shop->offers->offer)) {
                    $result['count_offers'] = count($xml->shop->offers->offer);

                    if (!$result['offer_elements']) {
                        $result['offer_attributes']['offer'] = array();
                        foreach ($xml->shop->offers->offer as $offer) {

                            $attributes = (array)$offer->attributes();
                            if (isset($attributes["@attributes"])) {
                                $result['offer_attributes']['offer'] += $attributes["@attributes"];
                            }

                            foreach ($offer as $offer_element => $tmp) {
                                $attributes = (array)$tmp;
                                if (isset($attributes["@attributes"]) && isset($result['offer_attributes'][(string)$offer_element])) {
                                    $result['offer_attributes'][(string)$offer_element] += $attributes["@attributes"];
                                } elseif (isset($attributes["@attributes"]) && !isset($result['offer_attributes'][(string)$offer_element])) {
                                    $result['offer_attributes'][(string)$offer_element] = $attributes["@attributes"];
                                } elseif (!isset ($result['offer_attributes'][(string)$offer_element])) {
                                    $result['offer_elements'][(string)$offer_element] = (string)$offer_element;
                                }
                            }

                            if (isset($offer->param)) {
                                foreach ($offer->param as $key => $value) {
                                    if (isset($value['name'])) {
                                        $name_param = (string)$value['name'];
                                        $value_param = (string)$value;
                                        $result['offer_params'][$name_param] = $name_param;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if (isset($xml->shop->categories->category)) {
                    $result['count_categories'] = count($xml->shop->categories->category);
                    $result['categories'] = $xml->shop->categories->category;
                }
                if (isset($xml->shop->offers->offer)) {
                    $result['count_offers'] = count($xml->shop->offers->offer);
                }
                if (isset($xml->shop->offers->offer) || isset($xml->shop->categories->category)) {
                    $result['xml'] = $xml;
                }
            }
        }

        return $result;
    }

    private function updateCategories($import_yml_oc_template_data, $yml_categories_xml, $only_xml_categories = FALSE, $separator = '~~~') {
        $yml_categories = array();

        // translate
        $translate_from = '';
        if (isset($import_yml_oc_template_data['product']['translate'])) {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
            foreach ($languages as $language) {
                if ($import_yml_oc_template_data['language_id'] == $language['language_id']) {
                    $translate_from = explode('-',$language['code']);
                    $translate_from = $translate_from[0];
                }
            }
        }

        if ($yml_categories_xml) {
            foreach ($yml_categories_xml->category as $category_row) {
                $category_name = (string)$category_row;
                $category_name = $this->prepareValue2($category_name);
                $category_name = $this->prepareFieldForDb($category_name);

                $yml_categories[(int)$category_row['id']] = array(
                    'parent_id' => (int)$category_row['parentId'],
                    'name' => $category_name,
                    'category_id' => (int)$category_row['id']
                );
            }
        }

        $yml_categories_on_file = array();

        foreach ($yml_categories as $yml_category_id => $yml_category) {
            $yml_categories_on_file[] = array(
                'name' => $yml_category['name'] . $separator . $this->getCategoriesPathName($yml_categories, $yml_category['parent_id'], '', $separator),
                'yml_category_id' => $yml_category['category_id']
            );
        }

        if ($only_xml_categories) {
            foreach ($yml_categories_on_file as $yml_category_key => $yml_category) {
                $new_name_parts = explode($separator, $yml_category['name']);

                foreach ($new_name_parts as $key_part => $new_name_part) {
                    $new_name_part = trim($new_name_part);

                    if (!$new_name_part) {
                        unset($new_name_parts[$key_part]);
                    }
                }

                $yml_categories_on_file[$yml_category_key]['name'] = implode($separator, $new_name_parts);
            }

            return $yml_categories_on_file;
        }

        $yml_categories_whis_parent_categories = array();

        if ($yml_categories_on_file) {
            foreach ($yml_categories_on_file as $yml_category_on_file) {
                $yml_categories_path_array = explode($separator, $yml_category_on_file['name']);

                foreach ($yml_categories_path_array as $yml_category_path_array_key => $yml_category_path_array) {
                    $yml_category_path_array = trim($yml_category_path_array);

                    if (!$yml_category_path_array) {
                        unset($yml_categories_path_array[$yml_category_path_array_key]);
                    }
                }

                krsort($yml_categories_path_array);

                $yml_categories_path_array_tmp = $yml_categories_path_array;
                $yml_categories_path_array = array();

                foreach ($yml_categories_path_array_tmp as $yml_categories_path_array_tmp_row) {
                    $yml_categories_path_array[] = $yml_categories_path_array_tmp_row;
                }

                $yml_categories_whis_parent_categories[$yml_category_on_file['yml_category_id']]['path'] = $yml_categories_path_array;
                $yml_categories_whis_parent_categories[$yml_category_on_file['yml_category_id']]['yml_category_name'] = array_shift($yml_categories_path_array);
            }
        }

        $language_id = $import_yml_oc_template_data['language_id'];
        $categories_on_site = $this->getCategories($language_id);
        $categories_whis_parent_categories = array();

        if ($categories_on_site) {
            foreach ($categories_on_site as $category_on_site) {
                $categories_path_array = explode('~~~', $category_on_site['name']);
                $categories_whis_parent_categories[$category_on_site['category_id']]['path'] = $categories_path_array;
                $categories_whis_parent_categories[$category_on_site['category_id']]['category_name'] = array_shift($categories_path_array);
            }
        }

        $insert = FALSE;

        if ($import_yml_oc_template_data['category']['import_status']) {
            $insert = TRUE;
        }

        $store_ids = $import_yml_oc_template_data['store_id'];

        if ($yml_categories_whis_parent_categories) {
            foreach ($yml_categories_whis_parent_categories as $yml_category_id => $yml_category_whis_path) {
                foreach ($categories_whis_parent_categories as $category_id => $category_whis_path) {
                    if ($category_whis_path['category_name'] == $yml_category_whis_path['yml_category_name']) {
                        if ($category_whis_path['path'] === $yml_category_whis_path['path']) {
                            $this->categories[$yml_category_id][$category_id] = $category_id;

                            unset($yml_categories_whis_parent_categories[$yml_category_id]);
                        }
                    }
                }
            }
        }

        if (isset($import_yml_oc_template_data['category']['category_matching'])) {
            $categories_matching = $import_yml_oc_template_data['category']['category_matching'];

            foreach ($categories_matching as $yml_category_id => $categories_matching_selected) {
                // working field, without category value
                unset($categories_matching_selected['select']);

                if ($categories_matching_selected) {
                    $this->categories[$yml_category_id] = array();

                    foreach ($categories_matching_selected as $category_id_matching) {
                        $this->categories[$yml_category_id][$category_id_matching] = $category_id_matching;
                    }

                    if (isset($yml_categories_whis_parent_categories[$yml_category_id])) {
                        unset($yml_categories_whis_parent_categories[$yml_category_id]);
                    }
                }
            }
        }

        // if there are unrecognized categories with put
        if ($yml_categories_whis_parent_categories && $insert) {
            foreach ($yml_categories_whis_parent_categories as $yml_categories_whis_parent_category_id => $yml_categories_whis_parent_category) {
                $this->getCategoryIdByNameAndParentCategoryName($language_id, $yml_categories_whis_parent_category_id, $yml_categories_whis_parent_category, $store_ids, $import_yml_oc_template_data['category'], $translate_from, $import_yml_oc_template_data['product']['translate']);
            }
        }
    }

    public function prepareValue2($field) {
        if (is_string($field)) {
            $field = strip_tags(htmlspecialchars_decode($field));
            $from = array('"', '&', '>', '<', '\'', '`', '&acute;');
            $to = array('&quot;', '&amp;', '&gt;', '&lt;', '&apos;', '', '');
            $field = str_replace($from, $to, $field);
            $field = trim($field);
            $field = ltrim($field);
        }

        return $field;
    }

    private function prepareFieldForDb($field) {
        if (is_string($field)) {
            $to = array('"', '&', '>', '<', '\'', '`', '&acute;');
            $from = array('&quot;', '&amp;', '&gt;', '&lt;', '&apos;', '', '');
            $field = str_replace($from, $to, $field);
            $field = trim($field);
        }

        return $field;
    }

    private function getCategoriesPathName($categories, $parent_id, $path, $separator = '~~~') {
        if (isset($categories[$parent_id]) && $parent_id && isset($categories[$parent_id]['name'])) {
            $path .= $categories[$parent_id]['name'] . $separator;
            return $this->getCategoriesPathName($categories, $categories[$parent_id]['parent_id'], $path, $separator);
        }

        return $path;
    }

    public function getCategories($language_id, $separator = '~~~', $category_id_key = FALSE) {
        $sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '" . $separator . "') AS name, c1.parent_id, c1.sort_order FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category c2 ON (cp.path_id = c2.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$language_id . "' AND cd2.language_id = '" . (int)$language_id . "'";
        $sql .= " GROUP BY cp.category_id";
        $query = $this->db->query($sql);
        $categories = $query->rows;

        if ($category_id_key && $query->rows) {
            $categories = array();

            foreach ($query->rows as $category) {
                $categories[$category['category_id']] = $category;
            }
        }

        return $categories;
    }

    public function getCategoryIdByNameAndParentCategoryName($language_id, $yml_category_id, $yml_categories, $store_ids, $import_yml_oc_template_data_category, $translate_from, $translate) {
        $parent_id = 0;

        foreach ($yml_categories['path'] as $yml_category_name) {
            $sql = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id AND cd.name = '" . $this->db->escape($yml_category_name) . "' )  WHERE cd.language_id = '" . (int)$language_id . "' AND c.parent_id = '" . $parent_id . "' ";
            $category = $this->db->query($sql);

            if ($category->row) {
                $parent_id = $category->row['category_id'];
                $this->categories[$yml_category_id][$parent_id] = $parent_id;
            } else {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '" . $parent_id . "', `top` = '0', `column` = '1',`status` = '1', sort_order = '0', date_modified = NOW(), date_added = NOW()");
                $parent_id = $this->db->getLastId();

                // translate
                if (isset($translate) && $translate) {
                    //$this->log->write('before translate getCategoryIdByNameAndParentCategoryName');

                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();
                    foreach ($languages as $language) {
                        $translate_to = explode('-',$language['code']);
                        $translate_to = $translate_to[0];

                        if ($translate_to != $translate_from) {
                            //$this->log->write('before translate_to != translate_from getCategoryIdByNameAndParentCategoryName');

                            $translate_name = '';

                            $result_translate_name = $this->yandexApi->plainTranslate($yml_category_name, $translate_from, $translate_to,  $translate_name);

                            if ($result_translate_name) {

                                // translation
                                // seo title
                                if (isset($import_yml_oc_template_data_category['seo_title']) && $import_yml_oc_template_data_category['seo_title']) {
                                    //$this->log->write('before seo title translate_to != translate_from getCategoryIdByNameAndParentCategoryName');
                                    //$this->log->write(var_export($translate_name, true));
                                    //$this->log->write('name TRANSLATE getCategoryIdByNameAndParentCategoryName');

                                    $sets = array();
                                    $category_columns = $this->getColumnsByTable('category_description');

                                    foreach ($category_columns as $column_name => $tml) {
                                        if ($column_name == 'name' || $column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title'|| $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                            if ($column_name == 'meta_title') {
                                                if (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) || $import_yml_oc_template_data_category['seo_meta_title_text_right'] == '')) {
                                                    $translate_meta_title_text_left = '';
                                                    $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);

                                                    if ($result_translate_meta_title_text_left) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($translate_name) . "' ";
                                                    }
                                                } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) || $import_yml_oc_template_data_category['seo_meta_title_text_left'] == '')) {
                                                    $translate_meta_title_text_right = '';
                                                    $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                    if ($result_translate_meta_title_text_right) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                    }
                                                } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '') {
                                                    $translate_meta_title_text_left = '';
                                                    $translate_meta_title_text_right = '';
                                                    $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);
                                                    $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                    if ($result_translate_meta_title_text_left && $result_translate_meta_title_text_right) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                    }
                                                } else {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                                }
                                            } elseif ($column_name == 'meta_description') {
                                                if (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) || $import_yml_oc_template_data_category['seo_meta_description_text_right'] == '')) {
                                                    $translate_meta_description_text_left = '';
                                                    $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);

                                                    if ($result_translate_meta_description_text_left) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($translate_name) . "' ";
                                                    }
                                                } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) || $import_yml_oc_template_data_category['seo_meta_description_text_left'] == '')) {
                                                    $translate_meta_description_text_right = '';
                                                    $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                    if ($result_translate_meta_description_text_right) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                    }
                                                } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '') {
                                                    $translate_meta_description_text_left = '';
                                                    $translate_meta_description_text_right = '';
                                                    $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);
                                                    $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($import_yml_oc_template_data_category['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                    if ($result_translate_meta_description_text_left && $result_translate_meta_description_text_right) {
                                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                    }
                                                } else {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                                }
                                            } elseif ($column_name == 'name') {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                            }
                                        }
                                    }

                                    $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                                    if ($sets) {
                                        $sql .= ", " . implode(',', $sets) . " ";
                                    }

                                    $this->db->query($sql);
                                } else {
                                    //$this->log->write('before without seo title translate_to != translate_from getCategoryIdByNameAndParentCategoryName');

                                    // translation
                                    // without seo title
                                    $sets = array();
                                    $category_columns = $this->getColumnsByTable('category_description');

                                    foreach ($category_columns as $column_name => $tml) {
                                        if ($column_name == 'name') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                        }
                                    }

                                    $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                                    if ($sets) {
                                        $sql .= ", " . implode(',', $sets) . " ";
                                    }

                                    $this->db->query($sql);
                                }
                            }
                        } else {

                            // translation
                            // seo title
                            if (isset($import_yml_oc_template_data_category['seo_title']) && $import_yml_oc_template_data_category['seo_title']) {
                                //$this->log->write('before seo title translate_to == translate_from getCategoryIdByNameAndParentCategoryName');

                                $sets = array();
                                $category_columns = $this->getColumnsByTable('category_description');

                                foreach ($category_columns as $column_name => $tml) {
                                    if ($column_name == 'name' || $column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                        if ($column_name == 'meta_title') {
                                            if (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) || $import_yml_oc_template_data_category['seo_meta_title_text_right'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_left']) . " " . $this->db->escape($yml_category_name) . "' ";
                                            } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) || $import_yml_oc_template_data_category['seo_meta_title_text_left'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_right']) . "' ";
                                            } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '') {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_left']) . " " . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_right']) . "' ";
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                            }
                                        } elseif ($column_name == 'meta_description') {
                                            if (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) || $import_yml_oc_template_data_category['seo_meta_description_text_right'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_left']) . " " . $this->db->escape($yml_category_name) . "' ";
                                            } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) || $import_yml_oc_template_data_category['seo_meta_description_text_left'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_right']) . "' ";
                                            } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '') {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_left']) . " " . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_right']) . "' ";
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                            }
                                        } elseif ($column_name == 'name') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                        }
                                    }
                                }

                                $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                //$this->log->write(var_export($sql, true));

                                $this->db->query($sql);
                            } else {
                                //$this->log->write('before without seo title translate_to == translate_from getCategoryIdByNameAndParentCategoryName');

                                // translation
                                // without seo title
                                $sets = array();
                                $category_columns = $this->getColumnsByTable('category_description');

                                foreach ($category_columns as $column_name => $tml) {
                                    if ($column_name == 'name') {
                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                    }
                                }

                                $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                $this->db->query($sql);
                            }
                        }
                    }
                } else {

                    // without translate
                    // seo title
                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();
                    foreach ($languages as $language) {
                        if (isset($import_yml_oc_template_data_category['seo_title']) && $import_yml_oc_template_data_category['seo_title']) {
                            //$this->log->write('before seo title without translate getCategoryIdByNameAndParentCategoryName');

                            $sets = array();
                            $category_columns = $this->getColumnsByTable('category_description');

                            foreach ($category_columns as $column_name => $tml) {
                                if ($column_name == 'name' || $column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                    if ($column_name == 'meta_title') {
                                        if (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) || $import_yml_oc_template_data_category['seo_meta_title_text_right'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_left']) . " " . $this->db->escape($yml_category_name) . "' ";
                                        } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) || $import_yml_oc_template_data_category['seo_meta_title_text_left'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_right']) . "' ";
                                        } elseif (isset($import_yml_oc_template_data_category['seo_meta_title_text_left']) && $import_yml_oc_template_data_category['seo_meta_title_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_title_text_right']) && $import_yml_oc_template_data_category['seo_meta_title_text_right'] != '') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_left']) . " " . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_title_text_right']) . "' ";
                                        } else {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                        }
                                    } elseif ($column_name == 'meta_description') {
                                        if (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) || $import_yml_oc_template_data_category['seo_meta_description_text_right'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_left']) . " " . $this->db->escape($yml_category_name) . "' ";
                                        } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '' && (!isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) || $import_yml_oc_template_data_category['seo_meta_description_text_left'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_right']) . "' ";
                                        } elseif (isset($import_yml_oc_template_data_category['seo_meta_description_text_left']) && $import_yml_oc_template_data_category['seo_meta_description_text_left'] != '' && isset($import_yml_oc_template_data_category['seo_meta_description_text_right']) && $import_yml_oc_template_data_category['seo_meta_description_text_right'] != '') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_left']) . " " . $this->db->escape($yml_category_name) . " " . $this->db->escape($import_yml_oc_template_data_category['seo_meta_description_text_right']) . "' ";
                                        } else {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                        }
                                    } elseif ($column_name == 'name') {
                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                    }
                                }
                            }

                            $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                            if ($sets) {
                                $sql .= ", " . implode(',', $sets) . " ";
                            }

                            $this->db->query($sql);
                        } else {
                            //$this->log->write('before without seo title without translate getCategoryIdByNameAndParentCategoryName');

                            // without translate
                            // without seo title
                            $sets = array();
                            $category_columns = $this->getColumnsByTable('category_description');

                            foreach ($category_columns as $column_name => $tml) {
                                if ($column_name == 'name') {
                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($yml_category_name) . "' ";
                                }
                            }

                            $sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language['language_id'] . "' ";

                            if ($sets) {
                                $sql .= ", " . implode(',', $sets) . " ";
                            }

                            $this->db->query($sql);
                        }
                    }
                }
            }

            if ($this->showTable('category_to_store', DB_PREFIX) && $store_ids) {
                foreach ($store_ids as $store_id) {
                    $this->db->query("REPLACE INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$parent_id . "',  store_id = '" . $store_id . "' ");
                }
            }

            $this->categories[$yml_category_id][$parent_id] = $parent_id;
        }

        $this->repairCategories();
    }

    public function getColumnsByTable($table) {
        $result = array();

        if ($this->showTable($table, DB_PREFIX)) {
            $columns = $this->db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $table . "` ");

            foreach ($columns->rows as $column) {
                $result[$column['Field']] = $column;
            }
        }

        return $result;
    }

    public function repairCategories($parent_id = 0) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category WHERE parent_id = '" . (int)$parent_id . "'");

        foreach ($query->rows as $category) {
            // Delete the path below the current one
            $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category['category_id'] . "'");

            // Fix for records with no paths
            $level = 0;

            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$parent_id . "' ORDER BY level ASC");

            foreach ($query->rows as $result) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");
                $level++;
            }

            $this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$category['category_id'] . "', level = '" . (int)$level . "'");

            $this->repairCategories($category['category_id']);
        }
    }

    public function getFileByURL($url, $httpcode = FALSE) {
        $file_exists = @fopen($url, "r");

        if (!$file_exists) {
            return FALSE;
        }

        $handle = @fopen($url, "r");

        if ($handle && $httpcode) {
            return TRUE;
        } elseif (!$handle && $httpcode) {
            return FALSE;
        }

        return $handle;
    }

    public function getFileByFileName($file_name, $httpcode = FALSE) {
        $file = DIR_DOWNLOAD . $file_name;

        if ($httpcode && !file_exists($file)) {
            return FALSE;
        } elseif ($httpcode && file_exists($file)) {
            return TRUE;
        }

        $handle = FALSE;

        if (file_exists($file)) {
            $handle = @fopen($file, 'r');
        }

        return $handle;
    }

    public function importYML($import_yml_oc_template_data, $import_data, $start, $limit) {
        if (isset($import_data['xml']->shop->categories)) {
            $this->updateCategories($import_yml_oc_template_data, $import_data['xml']->shop->categories);
        }

        if (isset($import_data['xml']->shop->offers) && $import_yml_oc_template_data['product']['import_status']) {
            $this->updateProducts($import_yml_oc_template_data, $import_data['xml']->shop->offers, $start, $limit, $import_data['count_offers']);
        }
    }

    private function updateProducts($import_yml_oc_template_data, $offers, $start, $limit, $count_offers) {
        $i = 0;
        $group_product_price = [];

        $type_change = $import_yml_oc_template_data['type_change'];
        $product_setting = $import_yml_oc_template_data['product'];
        $manufacturer_setting = $import_yml_oc_template_data['manufacturer'];
        $attribute_setting = $import_yml_oc_template_data['attribute'];
        $option_setting = array('import_status' => 0);

        if (isset($import_yml_oc_template_data['option'])) {
            $option_setting = $import_yml_oc_template_data['option'];
        }

        $language_id['product_description'] = $import_yml_oc_template_data['language_id'];

        $config_language_id = $import_yml_oc_template_data['language_id'];

        // translate
        $translate_from = '';
        if (isset($product_setting['translate']) && $product_setting['translate']) {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                if ($language['language_id'] == $config_language_id) {
                    $translate_from = explode('-', $language['code']);
                    $translate_from = $translate_from[0];
                }
            }
        }

        foreach ($offers->offer as $offer) {
            $skip_empty_group_product_id = FALSE;
            $group_import_attribute = array();

            if (isset($product_setting['group_import_attribute']) && $product_setting['group_import_attribute']) {
                $group_import_attribute_parts = explode('___', $product_setting['group_import_attribute']);
                $group_import_attribute = array(
                    'element' => $group_import_attribute_parts[0],
                    'attribute' => $group_import_attribute_parts[1],
                );
            }

            $group_import_element = array();
            if (isset($product_setting['group_import_element']) && $product_setting['group_import_element']) {
                $group_import_element = array(
                    'element' => $product_setting['group_import_element']
                );
            }

            $group_import_identificator = 0;
            if (isset($product_setting['group_import_identificator'])) {
                $group_import_identificator = ltrim(trim($product_setting['group_import_identificator']));
            }

            $group_import_prefix = '';
            if (isset($product_setting['group_import_prefix'])) {
                $group_import_prefix = ltrim(trim($product_setting['group_import_prefix']));
            }

            $group_import_status = 0;
            if (isset($product_setting['group_import'])) {
                $group_import_status = $product_setting['group_import'];
            }

            // grouping
            $group_product_id = 0;
            if ($group_import_status && ($group_import_element || $group_import_attribute)) {
                $product_id_attribute_part = '';
                $product_id_element_part = '';

                if ($group_import_attribute && $group_import_attribute['element'] == 'offer') {
                    if (isset($offer[$group_import_attribute['attribute']])) {
                        $product_id_attribute_part = (int)$offer[$group_import_attribute['attribute']];
                    }

                } elseif ($group_import_attribute) {
                    foreach ($offer as $offer_element_name => $offer_element_object) {
                        $offer_element_attributes = (array)$offer_element_object->attributes();

                        if (isset($offer_element_attributes['@attributes'])) {
                            $offer_element_attributes = $offer_element_attributes['@attributes'];
                        }
                        if ($offer_element_name == $group_import_attribute['element'] && isset($offer_element_attributes[$group_import_attribute['attribute']])) {
                            $product_id_attribute_part = (int)$offer_element_attributes[$group_import_attribute['attribute']];
                        }
                    }
                }

                if ($group_import_element) {
                    foreach ($offer as $offer_element_name => $offer_element_object) {
                        if ($offer_element_name == $group_import_element['element']) {
                            $product_id_element_part = (int)$offer_element_object;
                        }
                    }
                }

                if ($group_import_identificator) {
                    if ($group_import_identificator == 'attribute') {
                        $group_product_id = (int)($product_id_attribute_part . $group_import_prefix);
                    } elseif ($group_import_identificator == 'attribute_element') {
                        $group_product_id = (int)($product_id_attribute_part . $product_id_element_part . $group_import_prefix);
                    } elseif ($group_import_identificator == 'element') {
                        $group_product_id = (int)($product_id_element_part . $group_import_prefix);
                    } else {
                        $skip_empty_group_product_id = TRUE;
                    }
                } else {
                    $skip_empty_group_product_id = TRUE;
                }
            }

            if ($i >= $start && $i < ($limit + $start) && !$skip_empty_group_product_id) {
                $product_id = 0;
                $skip = FALSE;
                $product = array();
                $table = '';
                $identificator_field = '';
                $identificator_value = '';

                if (isset($product_setting['category_skip']) && $product_setting['category_skip']) {
                    $category_skip = $product_setting['category_skip'];
                    $skip_categories_this = array();

                    if (isset($offer->categoryId)) {
                        foreach ($offer->categoryId as $categoryId) {
                            $yml_category_id = (int)$categoryId;

                            if (isset($category_skip[$yml_category_id])) {
                                $skip_categories_this[] = TRUE;
                            }
                        }

                        if ($skip_categories_this && count($skip_categories_this) == count($offer->categoryId)) {
                            $skip = TRUE;
                        }
                    }
                }

                if ($type_change == 'update_data' || $type_change == 'only_update_data') {
                    if ($product_setting['id_column'] && $product_setting['id_column'] == 'name' && isset($offer->name)) {
                        $table = 'product_description';
                        $identificator_field = 'name';
                        $identificator_value = (string)$offer->name;
                        $identificator_value = $this->prepareValue2($identificator_value);
                    } elseif ($product_setting['id_column'] && $product_setting['id_column'] == 'aid') {
                        $table = 'product';
                        $identificator_field = 'product_id';
                        $identificator_value = (int)$offer['id'];
                    } elseif ($product_setting['id_column'] && $product_setting['id_column'] == 'model' && isset ($offer->model)) {
                        $table = 'product';
                        $identificator_field = 'model';
                        $identificator_value = (string)$offer->model;
                    } elseif ($product_setting['id_column'] && $product_setting['id_column'] == 'product_id_as_model' && isset ($offer['id'])) {
                        $table = 'product';
                        $identificator_field = 'model';
                        $identificator_value = (string)$offer['id'];
                    }

                    // change product_id if there is a group
                    if ($group_product_id) {
                        $identificator_value = $group_product_id;
                        $identificator_field = 'product_id';
                    }

                    if ($identificator_field && $identificator_value && $table) {
                        $product = $this->getIdByIdentificator($identificator_field, $identificator_value, $table, $language_id);

                        if ($product) {
                            $product_id = $product['product_id'];
                        }
                    }

                    //$this->log->write('identificator_fields updateProducts');

                    if ($type_change == 'only_update_data' && !$product_id) {
                        $skip = TRUE;
                    }

                    // import options
                    $unsets_params = array();
                    $import_option = array();

                    if (isset($option_setting['import_status']) && $option_setting['import_status'] && !$skip) {
                        $options = $option_setting['params'];

                        foreach ($options as $option_name => $option_data) {
                            if ($option_data['status']) {
                                $import_option[$option_name] = array();
                            }
                        }

                        if ($import_option && isset($offer->param)) {
                            // create missing options and get option_id
                            foreach ($import_option as $option_name => $option_data) {
                                $import_option[$option_name] = array(
                                    'name' => $option_name,
                                    'option_id' => 0,
                                    'option_value_id' => 0,
                                    'product_option_id' => 0,
                                    'product_option_value_id' => 0
                                );

                                $options_by_name = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_description WHERE language_id = " . $config_language_id . " AND name = '" . $this->db->escape($option_name) . "' ");

                                if (!$options_by_name->row) {
                                    $this->db->query("INSERT INTO " . DB_PREFIX . "option SET sort_order = 0, type = 'radio' ");
                                    $option_id = $this->db->getLastId();
                                    $import_option[$option_name]['option_id'] = $option_id;

                                    // translate
                                    if (isset($product_setting['translate']) && $product_setting['translate']) {
                                        $this->load->model('localisation/language');
                                        $languages = $this->model_localisation_language->getLanguages();
                                        foreach ($languages as $language) {
                                            $translate_option_name = '';

                                            $translate_to = explode('-',$language['code']);
                                            $translate_to = $translate_to[0];

                                            //$this->log->write('before translate translate_to != translate_from updateProduct - options');

                                            if ($translate_to != $translate_from) {
                                                $result_translate_option_name = $this->yandexApi->plainTranslate($option_name, $translate_from, $translate_to, $translate_option_name);

                                                //$this->log->write('before translate translate_to != translate_from updateProduct - options');

                                                if ($result_translate_option_name) {
                                                    $this->db->query("INSERT INTO " . DB_PREFIX . "option_description SET option_id = " . $option_id . ", name = '" . $this->db->escape($translate_option_name) . "',  language_id = " . $language['language_id'] . " ");
                                                }
                                            } else {
                                                //$this->log->write('before translate translate_to == translate_from updateProduct - options');

                                                $this->db->query("INSERT INTO " . DB_PREFIX . "option_description SET option_id = " . $option_id . ", name = '" . $this->db->escape($option_name) . "',  language_id = " . $config_language_id . " ");
                                            }
                                        }
                                    } else {
                                        //$this->log->write('before without translate updateProduct - options');

                                        // without translate
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "option_description SET option_id = " . $option_id . ", name = '" . $this->db->escape($option_name) . "',  language_id = " . $config_language_id . " ");
                                    }
                                } else {
                                    $import_option[$option_name]['option_id'] = $options_by_name->row['option_id'];
                                }
                            }

                            // create missing option values and get id of option values
                            foreach ($import_option as $option_name => $option_data) {
                                if ($option_data['option_id']) {
                                    $option_id = $option_data['option_id'];

                                    foreach ($offer->param as $param) {
                                        $option_value_name = '';

                                        if (isset($param['name'])) {
                                            $option_name_to_param = (string)$param['name'];
                                            $option_value_name = (string)$param;
                                        }
                                        if ($option_value_name && $option_name_to_param == $option_name) {
                                            // looking for all option names
                                            $option_values_by_option_id = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value_description WHERE  option_id = " . $option_id . " AND language_id = " . $config_language_id . " AND name = '" . $this->db->escape($option_value_name) . "' ");

                                            if ($option_values_by_option_id->row) {
                                                $option_value_id = $option_values_by_option_id->row['option_value_id'];
                                            } else {
                                                $this->db->query("INSERT INTO " . DB_PREFIX . "option_value SET option_id = '" . (int)$option_id . "' , image = '',  sort_order = 0 ");
                                                $option_value_id = $this->db->getLastId();

                                                // translate
                                                if (isset($product_setting['translate']) && $product_setting['translate']) {
                                                    $this->load->model('localisation/language');
                                                    $languages = $this->model_localisation_language->getLanguages();
                                                    foreach ($languages as $language) {
                                                        $translate_option_value_name = '';

                                                        $translate_to = explode('-',$language['code']);
                                                        $translate_to = $translate_to[0];

                                                        //$this->log->write('before translate_to != translate_from updateProduct - options create missing option values and get id of option values');

                                                        if ($translate_to != $translate_from) {
                                                            $result_translate_option_value_name = $this->yandexApi->plainTranslate($option_value_name, $translate_from, $translate_to, $translate_option_value_name);

                                                            //$this->log->write('before translate_to != translate_from updateProduct - options create missing option values and get id of option values');

                                                            if ($result_translate_option_value_name) {
                                                                $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description SET option_value_id = " . $option_value_id . ", option_id = '" . (int)$option_id . "', name = '" . $this->db->escape($translate_option_value_name) . "', language_id = " . $language['language_id'] . " ");
                                                            }
                                                        } else {

                                                            //$this->log->write('before translate_to == translate_from updateProduct - options create missing option values and get id of option values');

                                                            $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description SET option_value_id = " . $option_value_id . ", option_id = '" . (int)$option_id . "', name = '" . $this->db->escape($option_value_name) . "',  language_id = " . $config_language_id . " ");
                                                        }
                                                    }
                                                } else {
                                                    // without translate
                                                    //$this->log->write('before without translate updateProduct - options create missing option values and get id of option values');

                                                    $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description SET option_value_id = " . $option_value_id . ", option_id = '" . (int)$option_id . "', name = '" . $this->db->escape($option_value_name) . "',  language_id = " . $config_language_id . " ");
                                                }
                                            }

                                            $import_option[$option_name]['option_value_id'] = $option_value_id;
                                        }
                                    }
                                } else {
                                    unset($import_option[$option_name]);
                                }
                            }

                            if ($import_option) {
                                foreach ($import_option as $option_name => $option_data) {
                                    $unsets_params[$option_name] = $option_name;
                                }
                            }
                        }
                    }
                }

                if (!$skip) {
                    $sets = array();
                    $status_product = " status = 1 ";

                    if (!$product_setting['status_default']) {
                        $status_product = " status = 0 ";
                    }

                    if ($product_setting['price']) {
                        $price = 0.0;

                        if (isset($offer->price)) {
                            $price = (float)$offer->price;
                        }

                        $special = 0.0;

                        if ($product_setting['price_special'] && isset($offer->oldprice)) {
                            $special = (float)$price;
                            $price = (float)$offer->oldprice;
                            $special = round($special, 2);
                        }

                        if (isset($product_setting['price_param'])) {
                            $price_param_intervals = array();

                            foreach ($product_setting['price_param'] as $price_param) {
                                $limit_param_price = $this->getFloat($price_param['limit']);
                                $delta = $this->getFloat($price_param['delta']);
                                if ($limit_param_price > 0 && $delta != 0.0) {
                                    $price_param_intervals[$limit_param_price] = array(
                                        'limit' => $limit_param_price,
                                        'delta' => $delta,
                                        'delta_type' => $price_param['delta_type']
                                    );
                                }
                            }

                            ksort($price_param_intervals);

                            if ($price_param_intervals) {
                                $set_new_price = FALSE;

                                foreach ($price_param_intervals as $price_param_interval) {
                                    if ($price <= $price_param_interval['limit'] && !$set_new_price) {
                                        if ($price_param_interval['delta_type'] == 'percent') {
                                            $price += $price * $price_param_interval['delta'] / 100;
                                            $special += $special * $price_param_interval['delta'] / 100;
                                            $set_new_price = TRUE;
                                        } elseif ($price_param_interval['delta_type'] == 'currency' && !$set_new_price) {
                                            $price += $price_param_interval['delta'];
                                            if ($special > 0) {
                                                $special += $price_param_interval['delta'];
                                            }

                                            $set_new_price = TRUE;
                                        }
                                    }
                                }
                            }
                        }

                        if ($product_setting['price_delta']) {
                            $price_delta = $this->getFloat($product_setting['price_delta']);

                            if ($price_delta) {
                                $price *= $price_delta;
                            }

                            $special *= $price_delta;
                        }

                        if ($special > 0) {
                            $sets['product_special'][] = " `price`= '" . $special . "', customer_group_id = 1, priority = 1 ";
                        }

                        $price = round($price, 2);
                        $sets['product'][] = " `price`= '" . $price . "' ";
                    }

                    if (isset($offer->dimensions)) {
                        $dimensions_string = (string)$offer->dimensions;
                        $dimensions_parts = explode('/', $dimensions_string);

                        if (isset($dimensions_parts[0])) {
                            $sets['product'][] = " `length`= '" . (float)trim($dimensions_parts[0]) . "' ";
                        }
                        if (isset($dimensions_parts[1])) {
                            $sets['product'][] = " `width`= '" . (float)trim($dimensions_parts[1]) . "' ";
                        }
                        if (isset($dimensions_parts[2])) {
                            $sets['product'][] = " `height`= '" . (float)trim($dimensions_parts[2]) . "' ";
                        }
                    }

                    if (isset($product_setting['custom_field'])) {
                        foreach ($product_setting['custom_field'] as $custom_field) {
                            if (isset($custom_field['from']) && $custom_field['from'] && isset($custom_field['to']) && $custom_field['to']) {
                                $from = $custom_field['from'];
                                $to = $custom_field['to'];
                                $prefix_left = '';

                                if (isset($custom_field['prefix_left'])) {
                                    $prefix_left = ltrim(trim($custom_field['prefix_left']));
                                }

                                $prefix_right = '';

                                if (isset($custom_field['prefix_right'])) {
                                    $prefix_right = ltrim(trim($custom_field['prefix_right']));
                                }

                                if (isset($offer->{$from})) {
                                    $sets['product'][] = " `" . $to . "`= '" . $this->db->escape($prefix_left . (string)$offer->{$from} . $prefix_right) . "' ";
                                }
                            }
                        }
                    }

                    if (isset($product_setting['custom_field_attribute'])) {
                        foreach ($product_setting['custom_field_attribute'] as $custom_field_attribute) {
                            if (isset($custom_field_attribute['from']) && $custom_field_attribute['from'] && isset($custom_field_attribute['to']) && $custom_field_attribute['to']) {
                                $from = array();
                                $from_parts = explode('___', $custom_field_attribute['from']);
                                $from['element'] = $from_parts[0];
                                $from['attribute'] = $from_parts[1];
                                $to = $custom_field_attribute['to'];
                                $prefix_left = '';

                                if (isset($custom_field_attribute['prefix_left'])) {
                                    $prefix_left = ltrim(trim($custom_field_attribute['prefix_left']));
                                }

                                $prefix_right = '';

                                if (isset($custom_field_attribute['prefix_right'])) {
                                    $prefix_right = ltrim(trim($custom_field_attribute['prefix_right']));
                                }

                                if ($from['element'] != 'offer' && isset($offer->{$from['element']}[$from['attribute']])) {
                                    $sets['product'][] = " `" . $to . "`= '" . $this->db->escape($prefix_left . (string)$offer->{$from['element']}[$from['attribute']] . $prefix_right) . "' ";
                                } elseif ($from['element'] == 'offer') {
                                    $offer_custom_field = (array)$offer;
                                    $offer_custom_field_attribute = array();

                                    if (isset($offer_custom_field['@attributes'])) {
                                        $offer_custom_field_attribute = $offer_custom_field['@attributes'];
                                    }

                                    if (isset($offer_custom_field_attribute[$from['attribute']])) {
                                        $sets['product'][] = " `" . $to . "`= '" . $this->db->escape($prefix_left . (string)$offer_custom_field_attribute[$from['attribute']] . $prefix_right) . "' ";
                                    }
                                }
                            }
                        }
                    }

                    if ($product_setting['quantity']) {
                        $quantity = 0;

                        if (isset($offer['available'])) {
                            $available = (string)$offer['available'];

                            if ($available == 'true') {
                                $quantity = (int)$product_setting['quantity_true_100'];
                            } else {
                                if ($product_setting['quantity_false_status']) {
                                    $status_product = " status = 0 ";
                                }
                                $quantity = (int)$product_setting['quantity_false_0'];
                            }
                        }

                        $sets['product'][] = " `quantity`= '" . $quantity . "', `stock_status_id` = '7' ";
                    }

                    if ($product_setting['rec']) {
                        if (isset($offer->rec)) {
                            $rec = (string)$offer->rec;
                        } else {
                            $rec = '';
                        }

                        $this->setRecProductsIds($rec, (string)$offer['id'], $import_yml_oc_template_data);
                    }

                    if ($product_setting['image']) {
                        $images = array();
                        $pictures = array();

                        if (isset($offer->picture)) {
                            foreach ($offer->picture as $picture) {
                                $pictures[] = (string)$picture;
                            }
                        }

                        if ($pictures) {
                            foreach ($pictures as $picture) {
                                $images[] = $this->getImageByLink($picture, $product_setting);
                            }
                        }

                        if ($images) {
                            if (count($images) == 1) {
                                $sets['product'][] = " `image`= '" . $images[0] . "' ";
                            } else {
                                foreach ($images as $key => $image) {
                                    if ($key < 1) {
                                        $sets['product'][] = " `image`= '" . $image . "' ";
                                    } else {
                                        $sets['product_image'][] = " `image`= '" . $image . "' ";
                                    }
                                }
                            }
                        }
                    }

                    //$this->log->write('before translate updateProduct - all description');

                    // translate
                    if (isset($product_setting['translate']) && $product_setting['translate']) {
                        $this->load->model('localisation/language');
                        $languages = $this->model_localisation_language->getLanguages();
                        foreach ($languages as $language) {
                            $translate_to = explode('-',$language['code']);
                            $translate_to = $translate_to[0];

                            if ($product_setting['description']) {
                                $description = '';

                                $translate_description = '';

                                if ($translate_to != $translate_from) {
                                    //$this->log->write('before translate_to != translate_from updateProduct - all description');

                                    $result_translate_description = $this->yandexApi->plainTranslate((string)$offer->description, $translate_from, $translate_to,  $translate_description, 'html');

                                    if ($result_translate_description) {
                                        if (isset($offer->description) && isset($product_setting['description_cdata']) && !$product_setting['description_cdata']) {
                                            $description = $this->prepareFieldForDb(str_replace('\n', '<br>', strip_tags(str_replace('<br>', '\n', str_replace('< /', '</', $translate_description)))));
                                        } elseif (isset($offer->description) && isset($product_setting['description_cdata']) && $product_setting['description_cdata']) {
                                            $description = html_entity_decode(nl2br(str_replace('< /', '</', $translate_description)));
                                            $description = str_replace('<br />', '', $description);
                                        }

                                        $sets['product_description'][$language['language_id']][] = " `description`= '" . $this->db->escape($description) . "' ";
                                    } else {
                                        if (isset($offer->description) && isset($product_setting['description_cdata']) && !$product_setting['description_cdata']) {
                                            $description = $this->prepareFieldForDb(str_replace('\n', '<br>', strip_tags(str_replace('<br>', '\n', (string)$offer->description))));
                                        } elseif (isset($offer->description) && isset($product_setting['description_cdata']) && $product_setting['description_cdata']) {
                                            $description = html_entity_decode(nl2br((string)$offer->description));
                                            $description = str_replace('<br />', '', $description);
                                        }

                                        $sets['product_description'][$language['language_id']][] = " `description`= '" . $this->db->escape($description) . "' ";
                                    }
                                } else {
                                    //$this->log->write('before translate_to == translate_from updateProduct - all description');

                                    if (isset($offer->description) && isset($product_setting['description_cdata']) && !$product_setting['description_cdata']) {
                                        $description = $this->prepareFieldForDb(str_replace('\n', '<br>', strip_tags(str_replace('<br>', '\n', (string)$offer->description))));
                                    } elseif (isset($offer->description) && isset($product_setting['description_cdata']) && $product_setting['description_cdata']) {
                                        $description = html_entity_decode(nl2br((string)$offer->description));
                                        $description = str_replace('<br />', '', $description);
                                    }

                                    $sets['product_description'][$language['language_id']][] = " `description`= '" . $this->db->escape($description) . "' ";
                                }
                            } elseif (!$product_setting['description'] && !$product_id) {
                                $sets['product_description'][$language['language_id']][] = " `description`= '' ";
                            }

                            if ($product_setting['name']) {
                                $name = '';
                                $translate_name = '';

                                if (isset($offer->name)) {
                                    $name = (string)$offer->name;
                                }

                                if ($product_setting['name_self'] && isset($offer->{$product_setting['name_self']})) {
                                    $name = (string)$offer->{$product_setting['name_self']};
                                }

                                if ($translate_to != $translate_from) {
                                    //$this->log->write('before translate_to != translate_from updateProduct - all product_description table');

                                    $result_translate_name = $this->yandexApi->plainTranslate($name, $translate_from, $translate_to,  $translate_name);

                                    if ($result_translate_name) {
                                        $translate_name = $this->prepareValue2($translate_name);
                                        $translate_name = $this->prepareFieldForDb($translate_name);
                                        $sets['product_description'][$language['language_id']][] = " `name`= '" . $this->db->escape($translate_name) . "' ";

                                        //$this->log->write('before seo title translate_to != translate_from updateProduct - all product_description table');

                                        // seo title
                                        if (isset($product_setting['seo_title']) && $product_setting['seo_title']) {
                                            $product_columns = $this->getColumnsByTable('product_description');

                                            foreach ($product_columns as $column_name => $tml) {
                                                if ($column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                                    if ($column_name == 'meta_title') {
                                                        if (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && (!isset($product_setting['seo_meta_title_text_right']) || $product_setting['seo_meta_title_text_right'] == '')) {
                                                            $translate_meta_title_text_left = '';
                                                            $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($product_setting['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);

                                                            if ($result_translate_meta_title_text_left) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($translate_name) . "' ";
                                                            }
                                                        } elseif (isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '' && (!isset($product_setting['seo_meta_title_text_left']) || $product_setting['seo_meta_title_text_left'] == '')) {
                                                            $translate_meta_title_text_right = '';
                                                            $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($product_setting['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                            if ($result_translate_meta_title_text_right) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                            }
                                                        } elseif (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '') {
                                                            $translate_meta_title_text_left = '';
                                                            $translate_meta_title_text_right = '';
                                                            $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($product_setting['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);
                                                            $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($product_setting['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                            if ($result_translate_meta_title_text_left && $result_translate_meta_title_text_right) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                            }
                                                        } else {
                                                            $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                                        }
                                                    } elseif ($column_name == 'meta_description') {
                                                        if (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && (!isset($product_setting['seo_meta_description_text_right']) || $product_setting['seo_meta_description_text_right'] == '')) {
                                                            $translate_meta_description_text_left = '';
                                                            $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($product_setting['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);

                                                            if ($result_translate_meta_description_text_left) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($translate_name) . "' ";
                                                            }
                                                        } elseif (isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '' && (!isset($product_setting['seo_meta_description_text_left']) || $product_setting['seo_meta_description_text_left'] == '')) {
                                                            $translate_meta_description_text_right = '';
                                                            $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($product_setting['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                            if ($result_translate_meta_description_text_right) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                            }
                                                        } elseif (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '') {
                                                            $translate_meta_description_text_left = '';
                                                            $translate_meta_description_text_right = '';
                                                            $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($product_setting['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);
                                                            $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($product_setting['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                            if ($result_translate_meta_description_text_left && $result_translate_meta_description_text_right) {
                                                                $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($translate_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                            }
                                                        } else {
                                                            $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($translate_name) . "' ";
                                                        }
                                                    }
                                                }
                                            }

                                            $sets['product_description'][$language['language_id']][] = " `language_id` = '" . (int)$language['language_id'] . "' ";
                                        }
                                    }
                                } else {
                                    $name = $this->prepareValue2($name);
                                    $name = $this->prepareFieldForDb($name);
                                    $sets['product_description'][$language['language_id']][] = " `name`= '" . $this->db->escape($name) . "' ";

                                    if (isset($product_setting['seo_title']) && $product_setting['seo_title']) {
                                        //$this->log->write('before seo title translate_to == translate_from updateProduct - all product_description table');

                                        $product_columns = $this->getColumnsByTable('product_description');

                                        foreach ($product_columns as $column_name => $tml) {
                                            if ($column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                                if ($column_name == 'meta_title') {
                                                    if (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && (!isset($product_setting['seo_meta_title_text_right']) || $product_setting['seo_meta_title_text_right'] == '')) {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . "' ";
                                                    } elseif (isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '' && (!isset($product_setting['seo_meta_title_text_left']) || $product_setting['seo_meta_title_text_left'] == '')) {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($name) . " " . $this->db->escape($product_setting['seo_meta_title_text_right']) . "' ";
                                                    } elseif (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '') {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . " " . $product_setting['seo_meta_title_text_right'] . "' ";
                                                    } else {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($name) . "' ";
                                                    }
                                                } elseif ($column_name == 'meta_description') {
                                                    if (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && (!isset($product_setting['seo_meta_description_text_right']) || $product_setting['seo_meta_description_text_right'] == '')) {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . "' ";
                                                    } elseif (isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '' && (!isset($product_setting['seo_meta_description_text_left']) || $product_setting['seo_meta_description_text_left'] == '')) {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($name) . " " . $this->db->escape($product_setting['seo_meta_description_text_right']) . "' ";
                                                    } elseif (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '') {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_description_text_left']) . " " . $this->db->escape($name) . " " . $product_setting['seo_meta_description_text_right'] . "' ";
                                                    } else {
                                                        $sets['product_description'][$language['language_id']][] = " `" . $column_name . "` = '" . $this->db->escape($name) . "' ";
                                                    }
                                                }
                                            }
                                        }

                                        $sets['product_description'][$language['language_id']][] = " `language_id` = '" . (int)$language['language_id'] . "' ";
                                    }
                                }
                            } elseif (!$product_setting['name'] && !$product_id) {
                                $sets['product_description'][$language['language_id']][] = " `name`= '' ";
                            }
                        }
                    } else {
                        //$this->log->write('before without translate updateProduct - all product_description table');

                        // without translate
                        $this->load->model('localisation/language');
                        $languages = $this->model_localisation_language->getLanguages();
                        foreach ($languages as $language) {
                            if ($product_setting['description']) {
                                $description = '';

                                if (isset($offer->description) && isset($product_setting['description_cdata']) && !$product_setting['description_cdata']) {
                                    $description = $this->prepareFieldForDb(str_replace('\n', '<br>', strip_tags(str_replace('<br>', '\n', (string)$offer->description))));
                                } elseif (isset($offer->description) && isset($product_setting['description_cdata']) && $product_setting['description_cdata']) {
                                    $description = html_entity_decode(nl2br((string)$offer->description));
                                    $description = str_replace('<br />', '', $description);
                                }

                                $sets['product_description'][$language['language_id']] = " `description`= '" . $this->db->escape($description) . "' ";
                            } elseif (!$product_setting['description'] && !$product_id) {
                                $sets['product_description'][$language['language_id']] = " `description`= '' ";
                            }

                            if ($product_setting['name']) {
                                $name = '';

                                if (isset($offer->name)) {
                                    $name = (string)$offer->name;
                                }

                                if ($product_setting['name_self'] && isset($offer->{$product_setting['name_self']})) {
                                    $name = (string)$offer->{$product_setting['name_self']};
                                }

                                $name = $this->prepareValue2($name);
                                $name = $this->prepareFieldForDb($name);
                                $sets['product_description'][$language['language_id']] = " `name`= '" . $this->db->escape($name) . "' ";

                                if (isset($product_setting['seo_title']) && $product_setting['seo_title']) {
                                    //$this->log->write('before without translate seo title updateProduct - all product_description table');

                                    $product_columns = $this->getColumnsByTable('product_description');
                                    foreach ($product_columns as $column_name => $tml) {
                                        if ($column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                            if ($column_name == 'meta_title') {
                                                if (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && (!isset($product_setting['seo_meta_title_text_right']) || $product_setting['seo_meta_title_text_right'] == '')) {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . "' ";
                                                } elseif (isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '' && (!isset($product_setting['seo_meta_title_text_left']) || $product_setting['seo_meta_title_text_left'] == '')) {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($name) . " " . $this->db->escape($product_setting['seo_meta_title_text_right']) . "' ";
                                                } elseif (isset($product_setting['seo_meta_title_text_left']) && $product_setting['seo_meta_title_text_left'] != '' && isset($product_setting['seo_meta_title_text_right']) && $product_setting['seo_meta_title_text_right'] != '') {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . " " . $product_setting['seo_meta_title_text_right'] . "' ";
                                                } else {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($name) . "' ";
                                                }
                                            } elseif ($column_name == 'meta_description') {
                                                if (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && (!isset($product_setting['seo_meta_description_text_right']) || $product_setting['seo_meta_description_text_right'] == '')) {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_title_text_left']) . " " . $this->db->escape($name) . "' ";
                                                } elseif (isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '' && (!isset($product_setting['seo_meta_description_text_left']) || $product_setting['seo_meta_description_text_left'] == '')) {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($name) . " " . $this->db->escape($product_setting['seo_meta_description_text_right']) . "' ";
                                                } elseif (isset($product_setting['seo_meta_description_text_left']) && $product_setting['seo_meta_description_text_left'] != '' && isset($product_setting['seo_meta_description_text_right']) && $product_setting['seo_meta_description_text_right'] != '') {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($product_setting['seo_meta_description_text_left']) . " " . $this->db->escape($name) . " " . $product_setting['seo_meta_description_text_right'] . "' ";
                                                } else {
                                                    $sets['product_description'][$language['language_id']] = " `" . $column_name . "` = '" . $this->db->escape($name) . "' ";
                                                }
                                            }
                                        }
                                    }
                                }
                            } elseif (!$product_setting['name'] && !$product_id) {
                                $sets['product_description'][$language['language_id']] = " `name`= '' ";
                            }
                        }
                    }

                    if (isset($offer->model) && $product_setting['model']) {
                        $sets['product'][$product_setting['model']] = " `" . $product_setting['model'] . "`= '" . $this->db->escape((string)$offer->model) . "' ";
                    }

                    // if id Offer goes into the model, then we overwrite the model, which could already come to avoid twice in the SQL query
                    if ($product_setting['id_column'] && $product_setting['id_column'] == 'product_id_as_model' && isset($offer['id'])) {
                        $sets['product']['model'] = " `model`= '" . (string)$offer['id'] . "' ";
                    }

                    if (isset($offer->barcode) && $product_setting['barcode']) {
                        if ($offer->barcode != '') {
                            $symbol_rus = '';
                            $skip_symbol = false;

                            if ($offer->barcode == '1 шт') {
                                $symbol_rus = 'шт';
                            } elseif ($offer->barcode == '1 м. кв.') {
                                $symbol_rus = 'м2';
                            } elseif ($offer->barcode == '1 м. погонный') {
                                $symbol_rus = 'мп';
                            } elseif ($offer->barcode == '1 м.п.') {
                                $symbol_rus = 'мп';
                            } elseif ($offer->barcode == '0,75 л') {
                                $symbol_rus = '0.75л';
                            } elseif ($offer->barcode == '1 л') {
                                $symbol_rus = 'л';
                            } else {
                                $skip_symbol = true;
                            }

                            //$this->log->write('before product_setting["barcode"] updateProduct');

                            if (!$skip_symbol) {
                                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "unit WHERE symbol_rus = '" . $symbol_rus . "' ");
                                if ($query->num_rows > 0) {
                                    $sets['product'][$product_setting['barcode']] = " `" . $product_setting['barcode'] . "`= '" . $query->row['unit_id'] . "' ";
                                }
                            }
                        }
                    }

                    if (isset($offer->typePrefix) && $product_setting['typePrefix']) {
                        $sets['product'][$product_setting['typePrefix']] = " `" . $product_setting['typePrefix'] . "`= '" . $this->db->escape((string)$offer->typePrefix) . "' ";
                    }

                    $sets['product'][] = $status_product;

                    if ($manufacturer_setting['import_status']) {
                        $manufacturer_id = '';

                        if (isset($offer->vendor)) {
                            $manufacturer_name = (string)$offer->vendor;

                            if ($manufacturer_name) {
                                $manufacturer_id = $this->getManufacturerIdByName($manufacturer_name, $config_language_id, $import_yml_oc_template_data['store_id'], $translate_from, $manufacturer_setting);
                            }
                        }

                        $sets['product'][] = " `manufacturer_id`= '" . $manufacturer_id . "' ";

                        if ($manufacturer_setting['vendor_code'] && $manufacturer_setting['vendor_code_field'] && isset($offer->vendorCode)) {
                            $vendorCode = (string)$offer->vendorCode;
                            $sets['product'][$manufacturer_setting['vendor_code_field']] = " `" . $manufacturer_setting['vendor_code_field'] . "`= '" . $this->db->escape($vendorCode) . "' ";
                        }
                    }
                }

                if (!$skip) {
                    $where_product_id = '';
                    $new_product = TRUE;

                    if ($product_id) {
                        $where_product_id = " product_id = '" . $product_id . "' ";
                        $new_product = FALSE;
                    }

                    //$this->log->write('before sets updateProduct');

                    if (isset($offer->categoryId)) {
                        foreach ($offer->categoryId as $categoryId) {
                            $yml_category_id = (int)$categoryId;

                            if (isset($this->categories[$yml_category_id]) && $this->categories[$yml_category_id]) {
                                foreach ($this->categories[$yml_category_id] as $category_id) {
                                    $sets['product_to_category'][] = " `category_id` = '" . $category_id . "' ";
                                }
                            }
                        }
                    }

                    ksort($sets);

                    //$this->log->write('before sets updateProduct');
                    //$this->log->write(var_export($sets, true));

                    foreach ($sets as $table => $set) {
                        $sql = '';

                        if ($new_product) {
                            $sql .= " INSERT INTO `" . DB_PREFIX . $table . "` SET ";
                        } else {
                            $sql .= " UPDATE `" . DB_PREFIX . $table . "` SET ";
                        }

                        if ($table == 'product') {
                            if ($new_product && $product_id) {
                                $sql .= " product_id = '" . $product_id . "', ";
                            } elseif ($new_product && ($identificator_field == 'product_id') && !$product_id) {
                                $sql .= " product_id = '" . $identificator_value . "', ";
                            }

                            if ($identificator_value && $group_product_id) {
                                $res = $this->db->query(" SELECT * FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $identificator_value . "' ");
                                if ($res->num_rows > 0) {
                                    if ($res->row['price'] != $price) {
                                        $set[0] = " `price` = '" . $res->row['price'] . "' ";
                                        $group_product_price[$identificator_value] = $res->row['price'];
                                    }
                                }

                                $sql .= implode(',', $set);
                            } else {
                                $sql .= implode(',', $set);
                            }

                            if (!$new_product && $product_id) {
                                $sql .= " WHERE product_id = '" . $product_id . "' ";
                            } elseif (!$new_product && ($identificator_field == 'product_id')) {
                                $sql .= " WHERE product_id = '" . $identificator_value . "' ";
                            }

                            $this->db->query($sql);

                            if (!$where_product_id) {
                                //$this->log->write('before !where_product_id updateProduct');

                                $product_id = $this->db->getLastId();

                                //$this->log->write('after !where_product_id updateProduct');

                                $where_product_id = " product_id = '" . $product_id . "' ";
                            }

                            if ($product_id) {
                                //$this->log->write('before setMatchingProductsIds updateProduct');

                                $this->setMatchingProductsIds($product_id, (string)$offer['id'], $import_yml_oc_template_data);
                            }
                        }

                        if ($table == 'product_description') {
//                            if (!$product_setting['translate'] && !$new_product && $where_product_id) {
//                                $sql .= implode(',', $set);
//                                $sql .= " WHERE " . $where_product_id . " AND language_id = '" . $import_yml_oc_template_data['language_id'] . "' ";
//                                $this->db->query($sql);
//                            } elseif (!$product_setting['translate'] && $new_product && $product_id) {
//                                $sql .= implode(',', $set);
//                                $sql .= ", product_id = '" . $product_id . "' ";
//                                $this->db->query($sql);
//                            }

//                            if ($product_setting['translate']) {
                            //$this->log->write('before product_setting["translate"] updateProduct');

                            foreach ($set as $k => $s) {
                                $sql = '';

                                if (!$new_product) {
                                    $sql .= " UPDATE `" . DB_PREFIX . $table . "` SET ";
                                } else {
                                    $sql .= " INSERT INTO `" . DB_PREFIX . $table . "` SET product_id = '" . $product_id . "', ";
                                }

                                if (!is_array($s)) {
                                    $sql .= $s;
                                } else {
                                    $sql .= implode(',', $s);
                                }


                                if (!$new_product) {
                                    $sql .= " WHERE product_id = '" . $product_id . "' AND language_id = '" . $k . "' ";
                                } else {
                                    $sql .= ", language_id = '" . $k . "' ";
                                }

                                //$this->log->write(var_export($sql, true));

                                $this->db->query($sql);
                            }
//                            }
                        }

                        if ($table == 'product_to_category') {

                            //$this->log->write('in sets product_to_category updateProduct');

                            foreach ($sets['product_to_category'] as $product_to_category) {
                                $this->db->query(" DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE " . $product_to_category . " AND product_id = '" . $product_id . "' ");
                                if ($product_to_category == max($sets['product_to_category'])) {
                                    $this->db->query(" INSERT INTO `" . DB_PREFIX . "product_to_category` SET " . $product_to_category . ", product_id = '" . $product_id . "', main_category = '1' ");
                                } else {
                                    $this->db->query(" INSERT INTO `" . DB_PREFIX . "product_to_category` SET " . $product_to_category . ", product_id = '" . $product_id . "' ");
                                }
                            }
                        }

                        if ($table == 'product_special') {

                            //$this->log->write('in sets product_special updateProduct');

                            $this->db->query(" DELETE FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . $product_id . "' ");
                            $this->db->query(" INSERT INTO `" . DB_PREFIX . "product_special` SET " . end($set) . ", product_id = '" . $product_id . "' ");
                        }

                        if ($table == 'product_image') {

                            //$this->log->write('in sets product_image updateProduct');

                            $this->db->query(" DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . $product_id . "' ");
                            foreach ($sets['product_image'] as $product_image_sql_part) {
                                $this->db->query(" INSERT INTO `" . DB_PREFIX . "product_image` SET " . $product_image_sql_part . ", product_id = '" . $product_id . "' ");
                            }
                        }
                    }

                    if ($this->showTable('product_to_store', DB_PREFIX) && $import_yml_oc_template_data['store_id']) {
                        //$this->log->write('before product_to_store updateProduct');

                        foreach ($import_yml_oc_template_data['store_id'] as $store_id) {
                            $this->db->query("REPLACE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "',  store_id = '" . $store_id . "' ");
                        }
                    }

                    // seo url alias
                    if ($product_setting['seo_keyword']) {
                        //$this->log->write('before seo url alias updateProduct');

                        if ($product_setting['name'] && (isset($offer->name) || ($product_setting['name_self'] && isset($offer->{$product_setting['name_self']})))) {
                            $product_url_from_offer = (string)$offer->url;

                            //$this->log->write('before seo url alias updateProduct');

                            if (strpos($product_url_from_offer, '?') === false) {
                                //$this->log->write('before seo url alias - seo keyword updateProduct');

                                // seo keyword
                                $parse_str = parse_url($product_url_from_offer,PHP_URL_PATH);
                                $parse_arr = explode('/', trim($parse_str, '/'));
                                $str_url = end($parse_arr);

                                //$this->log->write(var_export($str_url, true));

                                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . $this->db->escape($product_id) . "' ");
                                //$this->log->write(var_export($query, true));
                                if (!($query->num_rows > 0)) {
                                    //$this->log->write('before seo url alias - seo keyword no duplicate updateProduct');

                                    // no duplicate - add new seo keyword
                                    $this->load->model('localisation/language');
                                    $languages = $this->model_localisation_language->getLanguages();
                                    foreach ($languages as $language) {
                                        foreach ($import_yml_oc_template_data['store_id'] as $store_id) {
                                            $query = $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET query = 'product_id=" . $this->db->escape($product_id) . "', keyword = '" . $this->db->escape($str_url) . "', language_id = '" . $language['language_id'] . "', store_id = '" . $store_id . "' ");
                                        }
                                    }
                                }
                            } else {
                                //$this->log->write('before seo url alias - no seo keyword - create it updateProduct');

                                // no seo keyword
                                $urlify = URLify::filter((string)$offer->name);
                                $new_urlify = URLify::transliterate($urlify);

                                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($new_urlify) . "' ");
                                if (!($query->num_rows > 0)) {
                                    //$this->log->write('before seo url alias - seo keyword no duplicate updateProduct');

                                    // no duplicate - add new seo keyword
                                    $this->load->model('localisation/language');
                                    $languages = $this->model_localisation_language->getLanguages();
                                    foreach ($languages as $language) {
                                        foreach ($import_yml_oc_template_data['store_id'] as $store_id) {
                                            $query = $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET query = 'product_id=" . $this->db->escape($product_id) . "', keyword = '" . $this->db->escape($new_urlify) . "', language_id = '" . $language['language_id'] . "', store_id = '" . $store_id . "' ");
                                        }
                                    }
                                }
                            }
                        }
                    }

                    //$this->log->write('before import attributes - updateProduct');

                    if ($attribute_setting['import_status']) {
                        $attributes = array();
                        $params = array();

                        if (isset($offer->param)) {
                            foreach ($offer->param as $key => $value) {
                                if (isset($value['name'])) {
                                    $name = (string)$value['name'];

                                    if (!isset($unsets_params[$name])) {
                                        $unit = "";

                                        if (isset($value['unit'])) {
                                            $unit = " " . (string)$value['unit'];
                                        }

                                        $params[$name] = (string)$value . $unit;
                                    }
                                }
                            }
                        }

                        if ($params && $product_id) {
                            $this->setAttributes($params, $attribute_setting['attribute_group_id'], $config_language_id, $product_id, $translate_from, $product_setting['translate']);
                        }
                    }
                }

                //$this->log->write('before import options final - updateProduct');

                if (isset($option_setting['import_status']) && $option_setting['import_status'] && $import_option && $product_id) {
                    foreach ($import_option as $option_name => $option_data) {
                        $option_id = 0;
                        $option_value_id = 0;

                        if ($group_product_id && isset($group_product_price[$identificator_value]) && $group_product_price[$identificator_value]) {
                            $option_price = $group_product_price[$identificator_value] - $price;
                            $option_price_prefix = '-';
                            if ($option_price <= 0) {
                                $option_price_prefix = '+';
                                $option_price = -($option_price);
                            }
                        }

                        if (isset($option_data['option_id']) && isset($option_data['option_value_id'])) {
                            $option_id = $option_data['option_id'];
                            $option_value_id = $option_data['option_value_id'];
                        }

                        if (isset($offer->attributes()->group_id) && $option_id && $option_value_id) {
                            $product_option_id_by_option_id = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "' AND option_id = '" . (int)$option_id . "'");

                            if ($product_option_id_by_option_id->row) {
                                $product_option_id = $product_option_id_by_option_id->row['product_option_id'];
                            } else {
                                $this->db->query("REPLACE INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', required = '1'");
                                $product_option_id = $this->db->getLastId();
                            }

                            if ($product_option_id) {
                                $product_option_value_id_by_option_value_id = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "' AND option_id = '" . (int)$option_id . "' AND option_value_id = " . $option_value_id);
                            }

                            if ($product_option_value_id_by_option_value_id->row) {
                                $product_option_value_id = $product_option_value_id_by_option_value_id->row['product_option_value_id'];

                                if ($group_product_id && isset($group_product_price[$identificator_value]) && $group_product_price[$identificator_value]) {
                                    $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', quantity = '" . 100 . "', subtract = '0', price = '". $option_price . "', price_prefix = '" . $option_price_prefix . "', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+' WHERE product_option_value_id = '" . $product_option_value_id . "' ");
                                } else {
                                    $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', quantity = '" . 100 . "', subtract = '0', price = '0', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+' WHERE product_option_value_id = '" . $product_option_value_id . "' ");
                                }
                            } else {
                                if ($group_product_id && isset($group_product_price[$identificator_value]) && $group_product_price[$identificator_value]) {
                                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', quantity = '" . 100 . "', subtract = '0', price = '". $option_price . "', price_prefix = '" . $option_price_prefix . "', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+'");
                                } else {
                                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', quantity = '" . 100 . "', subtract = '0', price = '0', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+'");
                                }

                                //$product_option_value_id = $this->db->getLastId();
                            }
                        }
                    }
                }
            }

            $i++;
            //last product - add recommended product
            if ($i == $count_offers && $product_setting['rec']) {
                if ($product_setting['rec']) {
                    $this->setRelatedProducts($import_yml_oc_template_data);
                }
            }
        }
    }

    public function getIdByIdentificator($identificator_field, $identificator_value, $table, $language_id = array()) {
        $result = FALSE;

        if ($this->showTable($table, DB_PREFIX)) {
            $sql = "  SELECT * FROM `" . DB_PREFIX . $table . "` WHERE `" . $identificator_field . "`= '" . $this->db->escape($identificator_value) . "' ";

            if (isset($language_id[$table])) {
                $sql .= " AND language_id = '" . (int)$language_id[$table] . "' ";
            }

            $query = $this->db->query($sql);

            if (count($query->rows) == 1) {
                $result = $query->row;
            }
        }

        return $result;
    }

    private function getFloat($string) {
        $string = trim($string);
        $find = array(',', ' ');
        $replace = array('.', '');
        $result = (float)str_replace($find, $replace, $string);

        return $result;
    }

    private function setRecProductsIds($rec, $yml_product_id, $import_yml_oc_template_data) {
        $rec_ids = explode(',', $rec);

        foreach ($rec_ids as $key => $value) {
            $value = (int)$value;

            if (!$value) {
                unset($rec_ids[$key]);
            }
        }

        $template_id = $import_yml_oc_template_data['id'];
        $this->load->model('setting/setting');
        $config_setting = $this->model_setting_setting->getSetting('import_yml_oc_recid');

        if ($config_setting) {
            $import_yml_oc_recid = $config_setting;
            $import_yml_oc_recid['import_yml_oc_recid'][$template_id][$yml_product_id] = array();

            foreach ($rec_ids as $key => $value) {
                $import_yml_oc_recid['import_yml_oc_recid'][$template_id][$yml_product_id][$value] = $value;
            }
        } else {
            $import_yml_oc_recid['import_yml_oc_recid'][$template_id][$yml_product_id] = array();

            foreach ($rec_ids as $key => $value) {
                $import_yml_oc_recid['import_yml_oc_recid'][$template_id][$yml_product_id][$value] = $value;
            }
        }

        $this->model_setting_setting->editSetting('import_yml_oc_recid', $import_yml_oc_recid);
    }

    public function getImageByLink($site_from_image, $product_setting) {
        $site_from_image_replacing = '';
        if ($site_from_image) {
            $site_from_image_replacing = str_replace('%20', ' ', $site_from_image);
        }

        $only_image = FALSE;
        if (isset($product_setting['image_upload']) && $product_setting['image_upload']) {
            $only_image = TRUE;
        }

        $new_image_name = FALSE;
        if (isset($product_setting['image_new_name']) && !$product_setting['image_new_name']) {
            $new_image_name = TRUE;
        }

        $image_new_path_parts = array();
        if (isset($product_setting['image_new_path']) && $product_setting['image_new_path']) {
            $image_new_path = trim($product_setting['image_new_path']);
            if ($image_new_path) {
                $image_new_path_parts = explode('/', $image_new_path);
            }
            if ($image_new_path_parts) {
                foreach ($image_new_path_parts as $key => $value) {
                    if (!$value) {
                        unset($image_new_path_parts[$key]);
                    }
                }
            }
        }

        $image_parts = explode('/', $site_from_image_replacing);
        $path_whis_path_array = array();

        if ($image_parts && is_array($image_parts)) {
            $check_url = array('http:' => 0, 'https:' => 0);

            foreach ($image_parts as $key => $image_parts_check_http) {
                if (isset($check_url[$image_parts_check_http])) {
                    unset($check_url[$image_parts_check_http]);
                }
            }

            if (count($check_url) > 1) {
                return '';
            } else {
                unset($image_parts[0]);
                unset($image_parts[1]);
                unset($image_parts[2]);
            }
        }

        if ($image_new_path_parts) {
            foreach ($image_new_path_parts as $url_part) {
                $path_whis_path_array[] = $url_part;
            }
        }

        if ($image_parts) {
            foreach ($image_parts as $url_part) {
                $path_whis_path_array[] = $url_part;
            }
        }

        if (!$path_whis_path_array) {
            return '';
        }

        $image_name = $path_whis_path_array[count($path_whis_path_array) - 1];
        unset($path_whis_path_array[count($path_whis_path_array) - 1]);
        $image_path = '';

        if ($path_whis_path_array) {
            $image_path = implode('/', $path_whis_path_array) . '/';
        }

        if ($new_image_name) {
            $image_name_parts = explode('.', $image_name);
            $image_name = md5($site_from_image_replacing) . '.' . end($image_name_parts);
        }

        $image = $image_path . $image_name;
        $server_path_and_image = DIR_IMAGE . $image;

        if (!file_exists(dirname($server_path_and_image))) {
            if ($image_path) {
                $image_path_parts = explode('/', $image_path);
                $dir_name = DIR_IMAGE;

                foreach ($image_path_parts as $new_dir_name) {
                    $dir_name .= $new_dir_name . '/';

                    if (!file_exists($dir_name)) {
                        mkdir($dir_name, 0777);
                    }
                }
            }
        }

        if (!file_exists(dirname($server_path_and_image))) {
            return '';

        } elseif (file_exists($server_path_and_image)) {
            return $image;
        }

        $b = get_headers($site_from_image);
        $imt = array('Content-Type: image/png' => '.png',
            'Content-Type: image/jpeg' => '.jpg',
            'Content-Type: image/gif' => '.gif',
            'Content-Type: image/jpeg' => '.jpeg',
            'Content-Type: image/vnd.wap.wbmp' => '.bmp');
        if ($b && is_array($b)) {
            $get_image = FALSE;

            foreach ($b as $key => $b_value) {
                if (isset($imt[$b_value])) {
                    $get_image = TRUE;
                }
            }

            if ($get_image) {
                $a = @file_get_contents($site_from_image);

                if ($a) {
                    file_put_contents($server_path_and_image, $a);
                    return $image;
                }
            }
        }

        return '';
    }

    public function getManufacturerIdByName($manufacturer_name, $language_id, $store_ids, $translate_from, $manufacturer_setting) {
        $manufacturer_id = 0;
        $query = $this->db->query(" SELECT * FROM " . DB_PREFIX . "manufacturer WHERE name = '" . $this->db->escape($manufacturer_name) . "' ");

        if ($query->row) {
            $manufacturer_id = $query->row['manufacturer_id'];
        } else {
            $this->db->query(" INSERT INTO " . DB_PREFIX . "manufacturer SET image = '', sort_order = 0, name = '" . $this->db->escape($manufacturer_name) . "' ");
            $manufacturer_id = $this->db->getLastId();

            if ($this->showTable('manufacturer_description', DB_PREFIX)) {

                if (isset($manufacturer_setting['translate']) && $manufacturer_setting['translate']) {
                    //$this->log->write('before manufacturer translate - updateProduct');


                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();
                    foreach ($languages as $language) {
                        $translate_to = explode('-',$language['code']);
                        $translate_to = $translate_to[0];

                        if ($translate_to != $translate_from) {
                            //$this->log->write('before manufacturer translate_to != translate_from - updateProduct');

                            // translate
                            // with seo title
                            if (isset($manufacturer_setting['seo_title']) && $manufacturer_setting['seo_title']) {
                                //$this->log->write('before manufacturer seo_title translate_to != translate_from - updateProduct');

                                $sets = array();
                                $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                                foreach ($manufacturer_columns as $column_name => $tml) {
                                    if ($column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                        if ($column_name == 'meta_title') {
                                            if (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_right']) || $manufacturer_setting['seo_meta_title_text_right'] == '')) {
                                                $translate_meta_title_text_left = '';
                                                $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);

                                                if ($result_translate_meta_title_text_left) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($manufacturer_name) . "' ";
                                                }
                                            } elseif (isset($product_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_left']) || $manufacturer_setting['seo_meta_title_text_left'] == '')) {
                                                $translate_meta_title_text_right = '';
                                                $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                if ($result_translate_meta_title_text_right) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                }
                                            } elseif (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '') {
                                                $translate_meta_title_text_left = '';
                                                $translate_meta_title_text_right = '';
                                                $result_translate_meta_title_text_left = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_title_text_left'], $translate_from, $translate_to,  $translate_meta_title_text_left);
                                                $result_translate_meta_title_text_right = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_title_text_right'], $translate_from, $translate_to,  $translate_meta_title_text_right);

                                                if ($result_translate_meta_title_text_left && $result_translate_meta_title_text_right) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_title_text_left) . " " . $this->db->escape($manufacturer_name) . " " . $this->db->escape($translate_meta_title_text_right) . "' ";
                                                }
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                            }
                                        } elseif ($column_name == 'meta_description') {
                                            if (isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_right']) || $manufacturer_setting['seo_meta_description_text_right'] == '')) {
                                                $translate_meta_description_text_left = '';
                                                $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);

                                                if ($result_translate_meta_description_text_left) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($manufacturer_name) . "' ";
                                                }
                                            } elseif (isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_left']) || $manufacturer_setting['seo_meta_description_text_left'] == '')) {
                                                $translate_meta_description_text_right = '';
                                                $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                if ($result_translate_meta_description_text_right) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                }
                                            } elseif (isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '') {
                                                $translate_meta_description_text_left = '';
                                                $translate_meta_description_text_right = '';
                                                $result_translate_meta_description_text_left = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_description_text_left'], $translate_from, $translate_to,  $translate_meta_description_text_left);
                                                $result_translate_meta_description_text_right = $this->yandexApi->plainTranslate($manufacturer_setting['seo_meta_description_text_right'], $translate_from, $translate_to,  $translate_meta_description_text_right);

                                                if ($result_translate_meta_description_text_left && $result_translate_meta_description_text_right) {
                                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($translate_meta_description_text_left) . " " . $this->db->escape($manufacturer_name) . " " . $this->db->escape($translate_meta_description_text_right) . "' ";
                                                }
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                            }
                                        } elseif ($column_name == 'name') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                        }
                                    }
                                }

                                $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . (int)$language['language_id'] . " ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                $this->db->query($sql);
                            } else {

                                // translate
                                // without seo title
                                $sets = array();
                                $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                                foreach ($manufacturer_columns as $column_name => $tml) {
                                    if ($column_name == 'name') {
                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                    }
                                }

                                $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . (int)$language['language_id'] . " ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                $this->db->query($sql);
                            }
                        } else {

                            // translate
                            // with seo title
                            if (isset($manufacturer_setting['seo_title']) && $manufacturer_setting['seo_title']) {
                                //$this->log->write('before manufacturer seo_title translate_to == translate_from - updateProduct');

                                $sets = array();
                                $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                                foreach ($manufacturer_columns as $column_name => $tml) {
                                    if ($column_name == 'name' || $column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                        if ($column_name == 'meta_title') {
                                            if (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_right']) || $manufacturer_setting['seo_meta_title_text_right'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_title_text_left']) . " " . $this->db->escape($manufacturer_name) . "' ";
                                            } elseif (isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_left']) || $manufacturer_setting['seo_meta_title_text_left'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_title_text_right']) . "' ";
                                            } elseif (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '') {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_title_text_left']) . " " . $this->db->escape($manufacturer_name) . " " . $manufacturer_setting['seo_meta_title_text_right'] . "' ";
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                            }
                                        } elseif ($column_name == 'meta_description') {
                                            if (isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_right']) || $manufacturer_setting['seo_meta_description_text_right'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_title_text_left']) . " " . $this->db->escape($manufacturer_name) . "' ";
                                            } elseif (isset($product_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_left']) || $manufacturer_setting['seo_meta_description_text_left'] == '')) {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_description_text_right']) . "' ";
                                            } elseif (isset($product_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '') {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_description_text_left']) . " " . $this->db->escape($manufacturer_name) . " " . $manufacturer_setting['seo_meta_description_text_right'] . "' ";
                                            } else {
                                                $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                            }
                                        } elseif ($column_name == 'name') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                        }
                                    }
                                }

                                $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . (int)$language['language_id'] . " ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                $this->db->query($sql);
                            } else {

                                // translate
                                // without seo title
                                $sets = array();
                                $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                                foreach ($manufacturer_columns as $column_name => $tml) {
                                    if ($column_name == 'name') {
                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                    }
                                }

                                $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . (int)$language['language_id'] . " ";

                                if ($sets) {
                                    $sql .= ", " . implode(',', $sets) . " ";
                                }

                                $this->db->query($sql);
                            }
                        }
                    }
                } else {
                    //$this->log->write('before manufacturer without translate seo title - updateProduct');

                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();
                    foreach ($languages as $language) {
                        if (isset($manufacturer_setting['seo_title']) && $manufacturer_setting['seo_title']) {
                            //$this->log->write('before manufacturer seo title without translate - updateProduct');

                            $sets = array();
                            $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                            foreach ($manufacturer_columns as $column_name => $tml) {
                                if ($column_name == 'name' || $column_name == 'meta_title' || $column_name == 'meta_h1' || $column_name == 'seo_title' || $column_name == 'seo_h1' || $column_name == 'meta_description') {
                                    if ($column_name == 'meta_title') {
                                        if (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_title_text_left']) . " " . $this->db->escape($manufacturer_name) . "' ";
                                        } elseif (isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_title_text_right']) . "' ";
                                        } elseif (isset($manufacturer_setting['seo_meta_title_text_left']) && $manufacturer_setting['seo_meta_title_text_left'] != '' && isset($manufacturer_setting['seo_meta_title_text_right']) && $manufacturer_setting['seo_meta_title_text_right'] != '') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_title_text_left']) . " " . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_title_text_right']) . "' ";
                                        } else {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                        }
                                    } elseif ($column_name == 'meta_description') {
                                        if (isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_description_text_left']) . " " . $this->db->escape($manufacturer_name) . "' ";
                                        } elseif (isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '' && (!isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] == '')) {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_description_text_right']) . "' ";
                                        } elseif (isset($manufacturer_setting['seo_meta_description_text_left']) && $manufacturer_setting['seo_meta_description_text_left'] != '' && isset($manufacturer_setting['seo_meta_description_text_right']) && $manufacturer_setting['seo_meta_description_text_right'] != '') {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_setting['seo_meta_description_text_left']) . " " . $this->db->escape($manufacturer_name) . " " . $this->db->escape($manufacturer_setting['seo_meta_description_text_right']) . "' ";
                                        } else {
                                            $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                        }
                                    } elseif ($column_name == 'name') {
                                        $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                    }
                                }
                            }

                            $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . $language['language_id'] . " ";

                            if ($sets) {
                                $sql .= ", " . implode(',', $sets) . " ";
                            }

                            $this->db->query($sql);
                        } else {
                            //$this->log->write('before manufacturer without translate without seo title - updateProduct');

                            $sets = array();
                            $manufacturer_columns = $this->getColumnsByTable('manufacturer_description');

                            foreach ($manufacturer_columns as $column_name => $tml) {
                                if ($column_name == 'name') {
                                    $sets[] = " `" . $column_name . "` = '" . $this->db->escape($manufacturer_name) . "' ";
                                }
                            }

                            $sql = " INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = " . $manufacturer_id . ", language_id = " . $language['language_id'] . " ";

                            if ($sets) {
                                $sql .= ", " . implode(',', $sets) . " ";
                            }

                            $this->db->query($sql);
                        }
                    }
                }
            }

            if ($this->showTable('manufacturer_to_store', DB_PREFIX)) {
                foreach ($store_ids as $store_id) {
                    $this->db->query(" INSERT INTO " . DB_PREFIX . "manufacturer_to_store SET manufacturer_id = " . $manufacturer_id . ", `store_id` = '" . $store_id . "' ");
                }
            }
        }

        return $manufacturer_id;
    }

    private function setMatchingProductsIds($product_id, $yml_product_id, $import_yml_oc_template_data) {
        $template_id = $import_yml_oc_template_data['id'];
        $this->load->model('setting/setting');
        $config_setting = $this->model_setting_setting->getSetting('import_yml_oc_matchingid');

        //$this->log->write('before setMatchingProductsIds - updateProducts');
        //$this->log->write(var_export($config_setting, true));

        if ($config_setting) {
            $import_yml_oc_matchingid['import_yml_oc_matchingid'] = $config_setting['import_yml_oc_matchingid'];
            $import_yml_oc_matchingid['import_yml_oc_matchingid'][$template_id][$yml_product_id] = $product_id;
        } else {
            $import_yml_oc_matchingid['import_yml_oc_matchingid'][$template_id][$yml_product_id] = $product_id;
        }

        $this->model_setting_setting->editSetting('import_yml_oc_matchingid', $import_yml_oc_matchingid);

        //$this->log->write('after setMatchingProductsIds - updateProducts');
        //$this->log->write(var_export($import_yml_oc_matchingid, true));
    }

    private function setAttributes($attributes, $attribute_group_id, $language_id, $product_id, $translate_from, $translate) {
        $attribute_values = array();

        foreach ($attributes as $attribute_name => $attribute_value) {
            // translate
            if (isset($translate) && $translate) {
                //$this->log->write('before import attributes translate - updateProduct');

                $this->load->model('localisation/language');
                $languages = $this->model_localisation_language->getLanguages();
                foreach ($languages as $language) {
                    $translate_attribute_name = '';
                    $translate_attribute_value = '';

                    $translate_to = explode('-',$language['code']);
                    $translate_to = $translate_to[0];

                    if ($translate_to != $translate_from) {
                        //$this->log->write('before import attributes translate_to != translate_from - updateProduct');

                        $result_translate_attr_name = $this->yandexApi->plainTranslate($attribute_name, $translate_from, $translate_to, $translate_attribute_name);
                        $result_translate_attr_value = $this->yandexApi->plainTranslate($attribute_value, $translate_from, $translate_to, $translate_attribute_value);

                        if ($result_translate_attr_name && $result_translate_attr_value) {

                            $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language_id . "' GROUP BY a.attribute_id ";
                            $query = $this->db->query($sql);

                            if ($query->row) {
                                $attribute_id_without_translate = $query->row['attribute_id'];
                                $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($translate_attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language['language_id'] . "' GROUP BY a.attribute_id ";
                                $query = $this->db->query($sql);

                                if ($query->row) {
                                    $attribute_values[] = " text = '" . $this->db->escape($translate_attribute_value) . "', language_id = " . $language['language_id'] . ", attribute_id = '" . $query->row['attribute_id'] . "' ";
                                } else {
                                    $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id = '" . $attribute_id_without_translate . "', language_id = " . $language['language_id'] . ", name = '" . $this->db->escape($translate_attribute_name) . "' ");
                                    $attribute_values[] = " text = '" . $this->db->escape($translate_attribute_value) . "', language_id = " . $language['language_id'] . ", attribute_id = '" . $attribute_id_without_translate . "' ";
                                }
                            }
                        }

                    } else {
                        //$this->log->write('before import attributes translate_to == translate_from - updateProduct');

                        $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language_id . "' GROUP BY a.attribute_id ";
                        $query = $this->db->query($sql);

                        if ($query->row) {
                            $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language_id . ", attribute_id = '" . $query->row['attribute_id'] . "' ";
                        } else {
                            $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute` SET attribute_group_id = '" . $attribute_group_id . "', sort_order = 0 ");
                            $attribute_id = $this->db->getLastId();

                            $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id = '" . $attribute_id . "', language_id = " . $language_id . ", name = '" . $this->db->escape($attribute_name) . "' ");
                            $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language_id . ", attribute_id = '" . $attribute_id . "' ";
                        }
                    }
                }
            } else {
                // without translate
                //$this->log->write('before import attributes without translate - updateProduct');

                $this->load->model('localisation/language');
                $languages = $this->model_localisation_language->getLanguages();
                foreach ($languages as $language) {
                    if ($language['language_id'] == $language_id) {
                        $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language_id . "' GROUP BY a.attribute_id ";
                        $query = $this->db->query($sql);

                        if ($query->row) {
                            $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language_id . ", attribute_id = '" . $query->row['attribute_id'] . "' ";
                        } else {
                            $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute` SET attribute_group_id = '" . $attribute_group_id . "', sort_order = 0 ");
                            $attribute_id = $this->db->getLastId();

                            $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id = '" . $attribute_id . "', language_id = " . $language_id . ", name = '" . $this->db->escape($attribute_name) . "' ");
                            $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language_id . ", attribute_id = '" . $attribute_id . "' ";
                        }
                    } else {
                        $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language['language_id'] . "' GROUP BY a.attribute_id ";
                        $query = $this->db->query($sql);

                        if ($query->row) {
                            $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language['language_id'] . ", attribute_id = '" . $query->row['attribute_id'] . "' ";
                        } else {
                            $sql = "SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id AND ad.name = '" . $this->db->escape($attribute_name) . "' ) WHERE a.attribute_group_id = '" . $attribute_group_id . "' AND ad.language_id = '" . $language_id . "' GROUP BY a.attribute_id ";
                            $query = $this->db->query($sql);

                            if ($query->row) {
                                $this->db->query(" INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id = '" . $query->row['attribute_id'] . "', language_id = " . $language['language_id'] . ", name = '" . $this->db->escape($attribute_name) . "' ");
                                $attribute_values[] = " text = '" . $this->db->escape($attribute_value) . "', language_id = " . $language['language_id'] . ", attribute_id = '" . $query->row['attribute_id'] . "' ";
                            }
                        }
                    }

                }
            }
        }

        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

        foreach ($attribute_values as $attribute_value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . $product_id . "', " . $attribute_value);
        }
    }

    private function setRelatedProducts($import_yml_oc_template_data) {
        $this->load->model('setting/setting');
        $related = $this->model_setting_setting->getSetting('import_yml_oc_recid');
        $deleted_relates = array();

        if ($related) {
            $related = $related['import_yml_oc_recid'];
        }

        $ids_matching = $this->model_setting_setting->getSetting('import_yml_oc_matchingid');

        if ($ids_matching) {
            $ids_matching = $ids_matching['import_yml_oc_matchingid'];
        }

        $template_id = $import_yml_oc_template_data['id'];

        if ($related && is_array($related) && $ids_matching && is_array($ids_matching) && isset($related[$template_id]) && isset($ids_matching[$template_id])) {
            foreach ($ids_matching[$template_id] as $yml_product_id => $product_id) {
                if (isset($related[$template_id][$yml_product_id])) {
                    if (!isset($deleted_relates[$product_id])) {
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");
                        $deleted_relates[$product_id] = $product_id;
                    }
                }
            }

            foreach ($ids_matching[$template_id] as $yml_product_id => $product_id) {
                if (isset($related[$template_id][$yml_product_id])) {
                    foreach ($related[$template_id][$yml_product_id] as $yml_product_id_related) {
                        if (isset($ids_matching[$template_id][$yml_product_id_related]) && $ids_matching[$template_id][$yml_product_id_related] && $ids_matching[$template_id][$yml_product_id_related] != $product_id) {
                            $related_id = $ids_matching[$template_id][$yml_product_id_related];
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
                        }
                    }
                }
            }
        }
    }

    public function insertProductImages($images, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

        foreach ($images as $product_image) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', " . $product_image . ", sort_order = '0'");
        }
    }

    public function insertProductCategories($categories, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
        $columns = $this->getColumnsByTable('product_to_category');
        $main_category_id = '';

        foreach ($categories as $category_id) {
            if (isset($columns['main_category'])) {
                $main_category_id = ", main_category = " . $category_id;
            }
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET " . $category_id . ", product_id = '" . $product_id . "' " . $main_category_id);
        }
    }

    public function insertFilter($filter_ids, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

        foreach ($filter_ids as $filter_id) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . $product_id . "', " . $filter_id);
        }
    }

    public function insertProductAttributeValues($atribute_values, $product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

        foreach ($atribute_values as $atribute_value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . $product_id . "', " . $atribute_value);
        }
    }

    public function getColumnIntoAbstractField($column, $type_data) {
        $check_field = str_replace($type_data . '_', '', $column);

        if ($check_field && $check_field == 'name') {
            $column = $check_field;
        }

        return $column;
    }

    public function getIdByIdentificatorForCategory($by_name, $path_for_identification, $name_for_identification, $category_id_for_identification, $delimiter, $type_data, $table, $language_id = array(), $store_id = array()) {
        $result = array();
        $sql = '';
        $result['parent_id'] = 0;
        $result['category_id'] = 0;

        if ($this->showTable($table, DB_PREFIX)) {
            // look for a category with this identifier and return the result
            if (!$by_name) {

                $sql = " SELECT * FROM " . DB_PREFIX . "category WHERE category_id = '" . (int)$category_id_for_identification . "' ";
                $category = $this->db->query($sql);

                if ($category->row) {
                    $result['parent_id'] = $category->row['parent_id'];
                    $result['category_id'] = $category->row['category_id'];
                }

            } else {
                // if path
                if ($path_for_identification) {
                    // look for a category along the path, if we don’t find it, then create a path, if necessary, an output. Category will be created in the second stage.
                    $path = explode($delimiter, $path_for_identification);

                    if ($path && is_array($path)) {
                        foreach ($path as $key => $category_name) {
                            $category_name = trim($category_name);

                            if ($category_name) {
                                $path[$key] = $category_name;
                            } else {
                                unset($path[$key]);
                            }

                        }

                        // if the path, then we look according to the path and create parents if we do not find them
                        if (count($path) > 1) {
                            $last_path = end($path);

                            foreach ($path as $category_name) {
                                // first element - must be top
                                if (!isset($parent_id)) {
                                    $sql_category_path = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id ) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND cd.language_id = '" . (int)$language_id[$table] . "' AND c.parent_id = 0 ";
                                    $parent_category = $this->db->query($sql_category_path);

                                    // if there is, leave the parent id
                                    if ($parent_category->row) {
                                        $parent_id = $parent_category->row['category_id'];
                                        $result['parent_id'] = $parent_id;
                                        $result['category_id'] = 0;
                                    } else {
                                        // otherwise, insert the parent and save its id
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '0', `top` = '0', `column` = '1',`status` = 1, sort_order = '0', date_modified = NOW(), date_added = NOW()");
                                        $parent_id = $this->db->getLastId();
                                        $result['parent_id'] = $parent_id;
                                        $result['category_id'] = 0;
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language_id[$table] . "', name = '" . $this->db->escape($category_name) . "', description = '', meta_title = '" . $this->db->escape($category_name) . "', meta_description = '', meta_keyword = ''");

                                        foreach ($store_id as $store_id_value) {
                                            $this->db->query("REPLACE INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$parent_id . "', " . $store_id_value . " ");
                                        }
                                    }
                                } else {
                                    $sql_category_path = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id ) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND cd.language_id = '" . (int)$language_id[$table] . "' AND c.parent_id = '" . $parent_id . "' ";
                                    $parent_category = $this->db->query($sql_category_path);

                                    // if there is, leave the parent id
                                    if ($parent_category->row && $category_name != $last_path) {
                                        $parent_id = $parent_category->row['category_id'];
                                        $result['parent_id'] = $parent_id;
                                        $result['category_id'] = 0;
                                    } elseif ($parent_category->row && $category_name == $last_path) {
                                        // if the last element is there, you do not need to start it
                                        $result['parent_id'] = $parent_id;
                                        $result['category_id'] = $parent_category->row['category_id'];
                                    } else {
                                        // otherwise, insert the parent and save its id
                                        if ($category_name != $last_path) {
                                            $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '" . $parent_id . "', `top` = '0', `column` = '1',`status` = 1, sort_order = '0', date_modified = NOW(), date_added = NOW()");

                                            // new parent
                                            $parent_id = $this->db->getLastId();
                                            $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language_id[$table] . "', name = '" . $this->db->escape($category_name) . "', description = '', meta_title = '" . $this->db->escape($category_name) . "', meta_description = '', meta_keyword = ''");

                                            foreach ($store_id as $store_id_value) {
                                                $this->db->query("REPLACE INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$parent_id . "',  " . $store_id_value . " ");
                                            }

                                            $result['parent_id'] = $parent_id;
                                            $result['category_id'] = 0;
                                        } else {
                                            // we don’t do insertion at the last element of the path, this will be done on the second stage
                                            $result['parent_id'] = $parent_id;
                                            $result['category_id'] = 0;
                                        }
                                    }
                                }
                            }
                        } else {
                            // if the path and one element of the path, then the top category, if we find return id, if not, then exit. Will be created in the second stage
                            $sql = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id ) WHERE cd.name = '" . $this->db->escape(end($path)) . "' AND cd.language_id = '" . (int)$language_id[$table] . "' ";
                            $category = $this->db->query($sql);

                            if ($category->row) {
                                $result['parent_id'] = $category->row['parent_id'];
                                $result['category_id'] = $category->row['category_id'];
                            }
                        }
                    }
                }
            }
        }

        $this->repairCategories();

        return $result;
    }

    public function getIdByIdentificatorForCategoryImportProduct($by_name, $path_for_identification, $name_for_identification, $category_id_for_identification, $delimiter, $type_data, $table, $language_id = array(), $store_id = array(), $save_last_element_path = FALSE) {
        $result = array();
        $sql = '';
        $result['parent_id'] = 0;
        $result['category_id'] = 0;

        if ($this->showTable($table, DB_PREFIX)) {
            // we search for a category with such identifier and return result
            if (!$by_name) {
                $sql = " SELECT * FROM " . DB_PREFIX . "category WHERE category_id = '" . (int)$category_id_for_identification . "' ";
                $category = $this->db->query($sql);

                if ($category->row) {
                    $result['parent_id'] = $category->row['parent_id'];
                    $result['category_id'] = $category->row['category_id'];
                }
            } else {
                // if path
                if ($path_for_identification) {

                    // look for a category along the path, if we don’t find it, then create a path, if necessary, an output. Category will be created in the second stage.
                    $path = explode($delimiter, $path_for_identification);

                    if ($path && is_array($path)) {
                        foreach ($path as $key => $category_name) {
                            $category_name = trim($category_name);

                            if ($category_name) {
                                $path[$key] = $category_name;
                            } else {

                                unset($path[$key]);
                            }
                        }

                        if ($path) {
                            foreach ($path as $category_name) {
                                // first element - must be top
                                if (!isset($parent_id)) {
                                    $sql_category_path = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id ) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND cd.language_id = '" . (int)$language_id[$table] . "' AND c.parent_id = 0 ";
                                    $parent_category = $this->db->query($sql_category_path);

                                    // if there is, leave the parent id
                                    if ($parent_category->row) {
                                        $parent_id = $parent_category->row['category_id'];
                                        $result['parent_id'] = $parent_category->row['parent_id'];
                                        $result['category_id'] = $parent_category->row['category_id'];
                                    } else {
                                        // otherwise, insert the parent and save its id
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '0', `top` = '0', `column` = '1',`status` = '1', sort_order = '0', date_modified = NOW(), date_added = NOW()");

                                        // if not the last element in path this category will be the parent
                                        $parent_id = $this->db->getLastId();

                                        // if the last element returns a value for this category
                                        $result['parent_id'] = 0;

                                        $result['category_id'] = $parent_id;

                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language_id[$table] . "', name = '" . $this->db->escape($category_name) . "', description = '', meta_title = '" . $this->db->escape($category_name) . "', meta_description = '', meta_keyword = ''");

                                        foreach ($store_id as $store_id_value) {
                                            $this->db->query("REPLACE INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$parent_id . "', " . $store_id_value . " ");
                                        }
                                    }

                                } else {
                                    $sql_category_path = " SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id ) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND cd.language_id = '" . (int)$language_id[$table] . "' AND c.parent_id = '" . $parent_id . "' ";
                                    $parent_category = $this->db->query($sql_category_path);

                                    // if there is, leave the parent id
                                    if ($parent_category->row) {
                                        $parent_id = $parent_category->row['category_id'];
                                        $result['parent_id'] = $parent_category->row['parent_id'];
                                        $result['category_id'] = $parent_category->row['category_id'];
                                    } else {
                                        // otherwise, insert the parent and save its id
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '" . $parent_id . "', `top` = '0', `column` = '1', sort_order = '0', status = '1', date_modified = NOW(), date_added = NOW()");
                                        $result['parent_id'] = $parent_id;

                                        // new parent
                                        $parent_id = $this->db->getLastId();
                                        $result['category_id'] = $parent_id;
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$parent_id . "', language_id = '" . (int)$language_id[$table] . "', name = '" . $this->db->escape($category_name) . "', description = '', meta_title = '" . $this->db->escape($category_name) . "', meta_description = '', meta_keyword = ''");

                                        foreach ($store_id as $store_id_value) {
                                            $this->db->query("REPLACE INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$parent_id . "',  " . $store_id_value . " ");
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->repairCategories();

        return $result;
    }

    public function getIdByIdentificatorAndGroupIdentificator($by_name, $identificator_value, $type_data, $group_identificator_value, $table, $language_id = array()) {
        $result = FALSE;

        if ($this->showTable($table, DB_PREFIX)) {
            if (!$by_name) {
                $sql = " SELECT *, (SELECT agd.name FROM " . DB_PREFIX . $type_data . "_group_description agd WHERE agd." . $type_data . "_group_id = a." . $type_data . "_group_id AND agd.language_id = '" . (int)$language_id[$table] . "') AS " . $type_data . "_group FROM " . DB_PREFIX . $type_data . " a LEFT JOIN " . DB_PREFIX . $type_data . "_description ad ON (a." . $type_data . "_id = ad." . $type_data . "_id) WHERE ad.language_id = '" . (int)$language_id[$table] . "' ";
                $sql .= " AND ad." . $type_data . "_id = '" . $this->db->escape($identificator_value) . "' ";
                $sql .= " AND a." . $type_data . "_group_id = '" . $group_identificator_value . "' ";
            } else {
                $sql = " SELECT *, (SELECT agd.name FROM " . DB_PREFIX . $type_data . "_group_description agd WHERE agd." . $type_data . "_group_id = a." . $type_data . "_group_id AND agd.language_id = '" . (int)$language_id[$table] . "') AS " . $type_data . "_group FROM " . DB_PREFIX . $type_data . " a LEFT JOIN " . DB_PREFIX . $type_data . "_description ad ON (a." . $type_data . "_id = ad." . $type_data . "_id) WHERE ad.language_id = '" . (int)$language_id[$table] . "' ";
                $sql .= " AND ad.name = '" . $this->db->escape($identificator_value) . "' ";
                $sql .= " AND a." . $type_data . "_group_id = '" . $group_identificator_value . "' ";
            }

            $query = $this->db->query($sql);

            if (count($query->rows) == 1) {
                $result = $query->row;
            }
        }

        return $result;
    }

    public function getFilterId($filter_name, $filter_group_name, $filter_group_id, $language_id) {
        $result = FALSE;

        if ($filter_name && $filter_group_name) {
            $sql = " SELECT * FROM " . DB_PREFIX . "filter_group_description WHERE name = '" . $this->db->escape($filter_group_name) . "' AND language_id = '" . $language_id . "' ";
            $query = $this->db->query($sql);

            if ($query->row) {
                $filter_group_id = $query->row['filter_group_id'];
            }

            $sql = " SELECT * FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (fd.filter_id = f.filter_id AND fd.language_id = '" . $language_id . "' ) LEFT JOIN " . DB_PREFIX . "filter_group_description fgd ON (f.filter_group_id = fgd.filter_group_id AND fgd.language_id = '" . $language_id . "' ) WHERE fd.name = '" . $this->db->escape($filter_name) . "' AND fgd.name = '" . $this->db->escape($filter_group_name) . "' ";
        } elseif ($filter_name && $filter_group_id) {
            $sql = " SELECT * FROM " . DB_PREFIX . "filter_group WHERE filter_group_id = '" . $filter_group_id . "' ";

            $query = $this->db->query($sql);

            if (!$query->row) {
                $filter_group_id = 0;
            }

            $sql = " SELECT * FROM " . DB_PREFIX . "filter f LEFT JOIN " . DB_PREFIX . "filter_description fd ON (fd.filter_id = f.filter_id AND fd.language_id = '" . $language_id . "' ) WHERE fd.name = '" . $this->db->escape($filter_name) . "' AND f.filter_group_id = '" . (int)$filter_group_id . "' ";
        }

        if ($sql && $filter_group_id) {
            $query = $this->db->query($sql);

            if (count($query->rows) == 1) {
                $result = $query->row['filter_id'];
            } elseif (!$query->row && $filter_group_id && $filter_name) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "filter SET filter_group_id = '" . (int)$filter_group_id . "', sort_order = '0'");
                $filter_id = $this->db->getLastId();
                $this->db->query("INSERT INTO " . DB_PREFIX . "filter_description SET filter_id = '" . (int)$filter_id . "', language_id = '" . (int)$language_id . "', filter_group_id = '" . (int)$filter_group_id . "', name = '" . $this->db->escape($filter_name) . "'");
                $result = $filter_id;
            }
        } elseif (!$filter_group_id && $filter_group_name && $filter_name) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group` SET sort_order = '0'");
            $filter_group_id = $this->db->getLastId();
            $this->db->query("INSERT INTO " . DB_PREFIX . "filter_group_description SET filter_group_id = '" . (int)$filter_group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($filter_group_name) . "'");
            $this->db->query("INSERT INTO " . DB_PREFIX . "filter SET filter_group_id = '" . (int)$filter_group_id . "', sort_order = '0'");
            $filter_id = $this->db->getLastId();
            $this->db->query("INSERT INTO " . DB_PREFIX . "filter_description SET filter_id = '" . (int)$filter_id . "', language_id = '" . (int)$language_id . "', filter_group_id = '" . (int)$filter_group_id . "', name = '" . $this->db->escape($filter_name) . "'");

            $result = $filter_id;
        }

        return $result;
    }

    public function getCurrencyByCode($currency) {
        $query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($currency) . "'");

        return $query->row;
    }
}

?>