<?php

class ControllerExtensionModuleImportYmlOc extends Controller {
    private $error = array();
    private $this_version = '1';
    private $this_extension = 'import_yml_oc';
    private $this_ocext_host = 'localhost';
    private $debug_mode = 0;
    private $demo_mode = 1;

    public function index() {
        $this->load->language('extension/module/import_yml_oc');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('tool/import_yml_oc');

        $data['open_tab'] = 'tab_yml_import';
        $data['import_yml_oc_format_data'][] = 'yml';

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->session->data['success'] = $this->language->get('text_success');

            foreach ($this->request->post['import_yml_oc_update_yml_link'] as $key => $value) {
                if (!$value['token'] && !$value['template_data_id'] && !$value['status']) {
                    unset($this->request->post['import_yml_oc_update_yml_link'][$key]);
                } elseif ($value['status'] == 3) {
                    unset($this->request->post['import_yml_oc_update_yml_link'][$key]);
                }
            }

            $this->model_setting_setting->editSetting('import_yml_oc_update_yml_link', $this->request->post);
            $this->response->redirect($this->url->link('extension/module/import_yml_oc', 'user_token=' . $this->session->data['user_token'], 'SSL'));
        }

        $data['debug_mode'] = $this->debug_mode;
        $data['demo_mode'] = $this->demo_mode;

        $data['import_yml_oc_update_yml_link'] = array();
        if ($this->config->get('import_yml_oc_update_yml_link')) {
            $data['import_yml_oc_update_yml_link'] = $this->config->get('import_yml_oc_update_yml_link');
        }

        $data['import_yml_oc_update_yml_link_template_data'] = array();
        if ($this->config->get('import_yml_oc_template_data_yml')) {
            $data['import_yml_oc_update_yml_link_template_data'] = $this->config->get('import_yml_oc_template_data_yml');
        }

        $data['cancel'] = $this->url->link('extension/module', 'user_token=' . $this->session->data['user_token'], 'SSL');
        $data['action_setting'] = $this->url->link('extension/module/import_yml_oc', 'user_token=' . $this->session->data['user_token'], 'SSL');

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } elseif (isset ($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        $data['user_token'] = $this->session->data['user_token'];
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
            'separator' => false
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], 'SSL'),
            'separator' => ' :: '
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/import_yml_oc', 'user_token=' . $this->session->data['user_token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->load->model('design/layout');
        $data['layouts'] = $this->model_design_layout->getLayouts();
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], 'SSL');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/import_yml_oc', $data));
    }

    private function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/import_yml_oc')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function getStepOneSettings() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['format_data'] = $this->request->post['format_data'];

        // redirect to another module
        if ($data['format_data'] && $data['format_data'] != 'yml') {
            $link = $this->url->link('extension/module/import_yml_oc', 'user_token=' . $this->request->get['user_token'], 'SSL');
            $data['entry_import_yml_oc_format_data_redirect'] = sprintf($this->language->get('entry_import_yml_oc_format_data_redirect'), $link, $data['format_data']);
            $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_one_settings_yml', $data));

            return;
        } elseif (!$data['format_data']) {
            $this->load->language('extension/module/import_yml_oc');
            $data['entry_import_yml_oc_format_data_redirect'] = $this->language->get('entry_import_yml_oc_format_select_format_data');
            $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_one_settings_yml', $data));

            return;
        }

        $template_data_selected_id = $this->request->post['template_data'];
        $data['template_data_selected_id'] = $template_data_selected_id;

        $this->load->model('setting/setting');
        $this->load->model('tool/import_yml_oc');
        $this->load->language('extension/module/import_yml_oc');

        $data['templates_data'] = array();

        // first entry, importing examples
        if (!$this->config->get('import_yml_oc_template_data_yml') && !$this->config->get('import_yml_oc_yml_first_us')) {
            $import_sample_data['import_yml_oc_template_data_yml'] = array();

            if ($this->validate()) {
                $this->model_setting_setting->editSetting('import_yml_oc', $import_sample_data);
                $this->model_setting_setting->editSetting('import_yml_oc_yml_first_us', array('import_yml_oc_yml_first_us' => 1));
            }
        }

        if ($this->config->get('import_yml_oc_template_data_yml')) {
            $data['templates_data'] = $this->config->get('import_yml_oc_template_data_yml');
        }

        $data['template_data_selected'] = array(
            'name' => $this->language->get('template_data_name_new'),
            'id' => $template_data_selected_id,
            'file_url' => '',
            'file_upload' => '',
            'store_id' => array(0),
            'currency_code' => $this->config->get('config_currency'),
            'language_id' => $this->config->get('config_language_id'),
            'encoding' => 'UTF-8',
            'level' => 0,
        );

        $data['encodings'] = array('UTF-8');

        if (isset($data['templates_data'][$template_data_selected_id])) {
            $data['template_data_selected'] = $data['templates_data'][$template_data_selected_id];
        }

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages(array('start' => 0, 'limit' => 10000));
        $data['languages'] = array();

        foreach ($languages as $language) {
            $data['languages'][$language['language_id']] = array(
                'language_id' => $language['language_id'],
                'name' => $language['name'] . (($language['code'] == $this->config->get('config_language')) ? $this->language->get('text_default') : null),
                'code' => $language['code']
            );
        }

        $this->load->model('localisation/currency');
        $currencies = $this->model_localisation_currency->getCurrencies(array('start' => 0, 'limit' => 10000));

        $data['currencies'] = array();
        foreach ($currencies as $currency) {
            $data['currencies'][$currency['code']] = array(
                'name' => $currency['title'] . (($currency['code'] == $this->config->get('config_currency')) ? $this->language->get('text_default') : null),
                'code' => $currency['code'],
            );
        }

        $this->load->model('setting/store');
        $stores = $this->model_setting_store->getStores();
        $data['stores'][] = array('store_id' => 0, 'name' => $this->language->get('entry_import_yml_oc_store_default'));

        foreach ($stores as $store) {
            $data['stores'][$store['store_id']] = $store;
        }

        $data['entry_import_yml_oc_file_upload_error_type'] = '';
        $extension_allowed = preg_replace('~\r?\n~', "\n", $this->config->get('config_file_ext_allowed'));
        $allowed = array();
        $filetypes = explode("\n", $extension_allowed);

        foreach ($filetypes as $filetype) {
            $allowed[] = trim($filetype);
        }

        if (!in_array('xml', $allowed)) {
            $link_on_setting = $link = $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'], 'SSL');
            $data['entry_import_yml_oc_file_upload_error_type'] = sprintf($this->language->get('entry_import_yml_oc_file_upload_error_type'), $link_on_setting);
        }

        $allowed = array();
        $mime_allowed = preg_replace('~\r?\n~', "\n", $this->config->get('config_file_mime_allowed'));
        $filetypes = explode("\n", $mime_allowed);

        foreach ($filetypes as $filetype) {
            $allowed[] = trim($filetype);
        }

        if (!in_array('text/xml', $allowed)) {
            $link_on_setting = $link = $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'], 'SSL');
            $data['entry_import_yml_oc_file_upload_error_type'] = sprintf($this->language->get('entry_import_yml_oc_file_upload_error_type'), $link_on_setting);
        }

        $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_one_settings_yml', $data));
    }

    public function getStepTwoSettings() {
        $this->load->model('setting/setting');
        $data['errors'] = array();

        $import_yml_oc_template_data_id = $this->request->post['import_yml_oc_template_data_yml']['id'];
        $format_data = $this->request->post['import_yml_oc_template_data_yml']['format_data'];
        $data['format_data'] = $format_data;

        if ($this->config->get('import_yml_oc_template_data_yml') && $import_yml_oc_template_data_id) {
            $import_yml_oc_templates_data = $this->config->get('import_yml_oc_template_data_yml');
            $import_yml_oc_template_data = array_merge($import_yml_oc_templates_data[$import_yml_oc_template_data_id], $this->request->post['import_yml_oc_template_data_yml']);
        } else {
            $import_yml_oc_template_data = $this->request->post['import_yml_oc_template_data_yml'];
        }

        if (!isset($import_yml_oc_template_data['start'])) {
            $import_yml_oc_template_data['start'] = 1;
        }

        if (!isset($import_yml_oc_template_data['limit'])) {
            $import_yml_oc_template_data['limit'] = 30;
        }

        $data['template_data_selected'] = $import_yml_oc_template_data;

        if ($format_data == 'yml') {
            $this->load->model('tool/import_yml_oc');
            $this->load->language('extension/module/import_yml_oc');

            foreach ($import_yml_oc_template_data as $data_field => $data_value) {
                if (($data_field == 'store_id' && !$data_value)) {
                    $data['errors'][] = $this->language->get('errors_import_yml_oc_store_id');
                }

                if (($data_field == 'encoding' || $data_field == 'language_id') && !$data_value) {
                    $data['errors'][] = $this->language->get('errors_import_yml_oc_' . $data_field);
                }

                if (!$import_yml_oc_template_data['file_url'] && !$import_yml_oc_template_data['file_upload'] && $data_field == 'file_url') {
                    $data['errors'][] = $this->language->get('errors_import_yml_oc_file_upload_file_url');
                }

                if ($import_yml_oc_template_data['file_url'] && $data_field == 'file_url') {
                    $http_code = $this->model_tool_import_yml_oc->getFileByURL($import_yml_oc_template_data['file_url'], TRUE);

                    if (!$http_code) {
                        $data['errors'][] = $this->language->get('errors_import_yml_oc_file_url_no_file');
                    }
                }

                if ($import_yml_oc_template_data['file_upload'] && $data_field == 'file_upload') {
                    $http_code = $this->model_tool_import_yml_oc->getFileByFileName($import_yml_oc_template_data['file_upload'], TRUE);

                    if (!$http_code) {
                        $data['errors'][] = $this->language->get('errors_import_yml_oc_file_fail');
                    }
                }
            }

            if (!isset($import_yml_oc_template_data['store_id'])) {
                $data['errors'][] = $this->language->get('errors_import_yml_oc_store_id');
            }

            $data['errors_import_yml_oc_title'] = $this->language->get('errors_import_yml_oc_title');

            if ($data['errors']) {
                return $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_two_settings_yml', $data));
            }

            if ($import_yml_oc_template_data['file_url']) {
                $file = $this->model_tool_import_yml_oc->getFileByURL($import_yml_oc_template_data['file_url']);
            } else {
                $file = $this->model_tool_import_yml_oc->getFileByFileName($import_yml_oc_template_data['file_upload']);
            }

            $yml_fields = $this->model_tool_import_yml_oc->getXMLRows(0, 0, $import_yml_oc_template_data, $import_yml_oc_template_data['file_url'], FALSE, 0, '', FALSE, TRUE, $import_yml_oc_template_data['file_upload']);

            if (!$yml_fields['count_categories'] && !$yml_fields['count_offers'] || !$file) {
                $data['errors'][] = $this->language->get('errors_import_yml_oc_file_fail');

                return $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_two_settings_yml', $data));
            }

            $data['offer_attributes'] = array();

            foreach ($yml_fields['offer_attributes'] as $offer_attribute_element => $offer_attribute_value) {
                if ($offer_attribute_value) {
                    foreach ($offer_attribute_value as $offer_attribute_value_name => $tmp) {
                        $data['offer_attributes'][$offer_attribute_element . '___' . $offer_attribute_value_name] = $offer_attribute_value_name . ' (attribute_field ' . $offer_attribute_element . ')';
                    }
                }
            }

            $data['offer_params'] = $yml_fields["offer_params"];
            $data['offer_elements'] = $yml_fields["offer_elements"];
            $types_data = $this->getTypesData($format_data, $import_yml_oc_template_data);
            $data['types_data'] = $types_data['types_data'];

            foreach ($data['types_data'] as $type_data => $tmp) {
                $data['unique_types_data'][$type_data] = array(
                    'aid' => sprintf($this->language->get('entry_unique_type_data_aid'), $type_data . '_id'),
                    'name' => sprintf($this->language->get('entry_unique_type_data_name'), 'name')
                );

                if ($type_data == 'product') {
                    $data['unique_types_data'][$type_data]['model'] = sprintf($this->language->get('entry_unique_type_data_model'), 'model');
                    $data['unique_types_data'][$type_data]['product_id_as_model'] = $this->language->get('text_product_id_as_model');
                    $data['unique_types_data'][$type_data]['sku'] = sprintf($this->language->get('entry_unique_type_data_sku'), 'sku');
                    $data['unique_types_data'][$type_data]['ean'] = sprintf($this->language->get('entry_unique_type_data_ean'), 'ean');

                }
            }

            $data['attribute_groups'] = $this->getAttributeOrFilterGroups(FALSE, 'attribute_name');

            $data['count_categories'] = $yml_fields['count_categories'];
            $data['count_offers'] = $yml_fields['count_offers'];

            $categories = $this->model_tool_import_yml_oc->getCategories($import_yml_oc_template_data['language_id'], '&nbsp;&nbsp;&gt;&nbsp;&nbsp;', TRUE);

            $data['categories'] = array();

            if ($categories) {
                $data['categories'] = $categories;
            }

            $data['yml_categories'] = $yml_fields['yml_categories'];

            $data['user_token'] = $this->session->data['user_token'];
            $data['types_change'] = array(
                'new_data' => $this->language->get('entry_type_change_new_data'),
                'update_data' => $this->language->get('entry_type_change_update_data'),
                'only_update_data' => $this->language->get('entry_type_change_only_update_data')
            );

            $data['entry_select'] = $this->language->get('entry_select');

            return $this->response->setOutput($this->load->view('extension/module/import_yml_oc_step_two_settings_yml', $data));
        }
    }

    public function setCategoryMatching() {
        $import_yml_oc_template_data_id = $this->request->post['import_yml_oc_template_data_yml']['id'];

        if ($this->config->get('import_yml_oc_template_data_yml') && $import_yml_oc_template_data_id) {
            $import_yml_oc_templates_data = $this->config->get('import_yml_oc_template_data_yml');
            $import_yml_oc_template_data = array_merge($import_yml_oc_templates_data[$import_yml_oc_template_data_id], $this->request->post['import_yml_oc_template_data_yml']);
        } else {
            $import_yml_oc_template_data = $this->request->post['import_yml_oc_template_data_yml'];
        }

        $data['template_data_selected'] = $import_yml_oc_template_data;
        $data['yml_category_id'] = $this->request->get['yml_category_id'];

        $this->load->model('tool/import_yml_oc');

        $categories = $this->model_tool_import_yml_oc->getCategories($import_yml_oc_template_data['language_id'], '&nbsp;&nbsp;&gt;&nbsp;&nbsp;');

        $data['categories'] = array();

        if ($categories) {
            $data['categories'] = $categories;
        }

        return $this->response->setOutput($this->load->view('extension/module/import_yml_oc_category_matching_yml', $data));
    }

    public function getTypesData($format_data = '', $import_yml_oc_template_data) {
        $data = array();
        $this->load->model('tool/import_yml_oc');
        $data['types_data']['product'] = $this->model_tool_import_yml_oc->getColumns('product', $format_data, $import_yml_oc_template_data);

        return $data;
    }

    public function getAttributeOrFilterGroups($language_id, $type_data_column) {
        if ($type_data_column == 'attribute_name') {
            $table = 'attribute_group_description';
        }

        if ($type_data_column == 'filter_name') {
            $table = 'filter_group_description';
        }

        if (!$language_id) {
            $language_id = (int)$this->config->get('config_language_id');
        }

        $sql = "SELECT * FROM " . DB_PREFIX . $table . " WHERE language_id = '" . $language_id . "' ";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTypesDataColumnAdditional() {
        $data = array();
        $type_data_and_column = $this->request->get['type_data_column'];
        $type_data_parts = explode('___', $type_data_and_column);
        $type_data = $type_data_parts[0];
        $type_data_column = '';
        if (isset($type_data_parts[1])) {
            $type_data_column = $type_data_parts[1];
        }
        $field = trim($this->request->get['field']);
        $import_yml_oc_template_data_id = $this->request->post['import_yml_oc_template_data_yml']['id'];
        $format_data = $this->request->post['import_yml_oc_template_data_yml']['format_data'];

        if ($this->config->get('import_yml_oc_template_data_yml') && $import_yml_oc_template_data_id) {
            $import_yml_oc_templates_data = $this->config->get('import_yml_oc_template_data_yml');
            $import_yml_oc_template_data = array_merge($import_yml_oc_templates_data[$import_yml_oc_template_data_id], $this->request->post['import_yml_oc_template_data_yml']);
        } else {
            $import_yml_oc_template_data = $this->request->post['import_yml_oc_template_data_yml'];
        }

        $language_id = $import_yml_oc_template_data['language_id'];

        $fields = array();

        foreach ($import_yml_oc_template_data['type_data'] as $field_this => $tmp) {
            $fields[$field_this] = $field_this;
        }

        $data['type_data'] = $type_data;

        $data['field'] = $field;

        if ($format_data == 'csv' && $field) {
            $this->load->model('tool/import_yml_oc');
            $this->load->language('extension/module/import_yml_oc');

            $type_data_and_column_additional = array();

            // delimeter
            $j = 0;
            if ($type_data_column == 'category_whis_path' || $type_data_column == 'images') {
                $type_data_and_column_additional[$j]['element'] = 'input';
                $type_data_and_column_additional[$j]['type'] = 'text';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_delimiter][' . $field . '][' . $type_data_and_column . ']';
                if (isset($import_yml_oc_template_data['type_data_column_delimiter'][$field][$type_data_and_column])) {
                    $type_data_and_column_additional[$j]['value'] = $import_yml_oc_template_data['type_data_column_delimiter'][$field][$type_data_and_column];
                } else {
                    $type_data_and_column_additional[$j]['value'] = '';
                }
                $type_data_and_column_additional[$j]['placeholder'] = $this->language->get('entry_type_data_column_delimiter_' . $type_data_column);
            }

            // price
            $j++;
            if ($type_data_column == 'price') {
                $type_data_column_id = 'price_rate';
                $type_data_and_column_additional[$j]['element'] = 'input';
                $type_data_and_column_additional[$j]['type'] = 'text';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                if (isset($import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column])) {
                    $type_data_and_column_additional[$j]['value'] = $import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column];
                } else {
                    $type_data_and_column_additional[$j]['value'] = '';
                }
                $type_data_and_column_additional[$j]['placeholder'] = $this->language->get('entry_type_data_column_' . $type_data_column_id);

                $j++;
                $type_data_column_id = 'price_delta';
                $type_data_and_column_additional[$j]['element'] = 'input';
                $type_data_and_column_additional[$j]['type'] = 'text';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                if (isset($import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column])) {
                    $type_data_and_column_additional[$j]['value'] = $import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column];
                } else {
                    $type_data_and_column_additional[$j]['value'] = '';
                }
                $type_data_and_column_additional[$j]['placeholder'] = $this->language->get('entry_type_data_column_' . $type_data_column_id);

                $j++;
                $type_data_column_id = 'price_around';
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = '';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                for ($i = 0; $i < 2; $i++) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $i;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_price_around_' . $i);
                    if (isset($import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column]) && $import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                }

            }

            // downloading images or path
            $j++;
            if ($type_data_column == 'image' || $type_data_column == 'images') {
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = '';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_image][' . $field . '][' . $type_data_and_column . ']';
                for ($i = 0; $i < 2; $i++) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $i;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_image_' . $i);
                    if (isset($import_yml_oc_template_data['type_data_column_image'][$field][$type_data_and_column]) && $import_yml_oc_template_data['type_data_column_image'][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                }
            }

            // name filter, attribute, group
            $j++;
            if ($type_data_column == 'attribute_name' || $type_data_column == 'filter_name') {
                if ($type_data_column == 'filter_name') {
                    $type_data_column_id = 'filter_group_id';
                } else {
                    $type_data_column_id = 'attribute_group_id';
                }
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = 'openElementOnNameValue(\'' . 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '_field][' . $field . '][' . $type_data_and_column . ']\',\'field\',this.value,\'select\')';

                $options = $this->getAttributeOrFilterGroups($language_id, $type_data_column);
                $i = 0;
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 0;
                if ($options) {
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_' . $type_data_column_id);
                } else {
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_' . $type_data_column_id . '_empty');
                }
                $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                $i++;
                foreach ($options as $option) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $option[$type_data_column_id];
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $option['name'];
                    if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) && $option[$type_data_column_id] == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                    $i++;
                }
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 'field';
                $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_field');
                $field_selected = FALSE;
                if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) && 'field' == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    $field_selected = TRUE;
                } else {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                }

                $j++;
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '_field][' . $field . '][' . $type_data_and_column . ']';
                if ($field_selected) {
                    $type_data_and_column_additional[$j]['style'] = 'display:block;';
                } else {
                    $type_data_and_column_additional[$j]['style'] = 'display:none;';
                }
                $type_data_and_column_additional[$j]['onchange'] = '';
                $i = 0;
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 0;
                $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_select');
                $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                $i++;
                foreach ($fields as $field_this) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $field_this;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $field_this;
                    if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id . '_field'][$field][$type_data_and_column]) && $field_this == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id . '_field'][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                    $i++;
                }
            }

            // attribute value
            $j++;
            if ($type_data_column == 'attribute_value') {

                $type_data_column_id = 'attribute_id';
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = 'openElementOnNameValue(\'' . 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '_field][' . $field . '][' . $type_data_and_column . ']\',\'field\',this.value,\'select\')';

                $options = $this->getAttributes();

                $i = 0;
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 0;
                if ($options) {
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_' . $type_data_column_id);
                } else {
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_' . $type_data_column_id . '_empty');
                }
                $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                $i++;
                foreach ($options as $option) {
                    $type_data_and_column_additional[$j]['options'][$i]['optiongroup'] = $option['attribute_group_name'];
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $option[$type_data_column_id];
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $option['name'];
                    if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) && $option[$type_data_column_id] == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                    $i++;
                }
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 'field';
                $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_group_identificator_field');
                $type_data_and_column_additional[$j]['options'][$i]['optiongroup'] = $this->language->get('entry_type_data_column_group_identificator_field');
                $field_selected = FALSE;
                if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) && 'field' == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id][$field][$type_data_and_column]) {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    $field_selected = TRUE;
                } else {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                }
                $i++;

                $j++;
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_group_identificator][' . $type_data_column_id . '_field][' . $field . '][' . $type_data_and_column . ']';
                if ($field_selected) {
                    $type_data_and_column_additional[$j]['style'] = 'display:block;';
                } else {
                    $type_data_and_column_additional[$j]['style'] = 'display:none;';
                }
                $type_data_and_column_additional[$j]['onchange'] = '';
                $i = 0;
                $type_data_and_column_additional[$j]['options'][$i]['value'] = 0;
                $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_select');
                $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                $i++;
                foreach ($fields as $field_this) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $field_this;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $field_this;
                    if (isset($import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id . '_field'][$field][$type_data_and_column]) && $field_this == $import_yml_oc_template_data['type_data_column_group_identificator'][$type_data_column_id . '_field'][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                    $i++;
                }
                /*
                $j++;
                $type_data_column_id = 'attribute_values_delimiter';
                $type_data_and_column_additional[$j]['element'] = 'input';
                $type_data_and_column_additional[$j]['type'] = 'text';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_'.$type_data_column_id.']['.$field.']['.$type_data_and_column.']';
                if(isset($import_yml_oc_template_data['type_data_column_'.$type_data_column_id][$field][$type_data_and_column])){
                    $type_data_and_column_additional[$j]['value'] = $import_yml_oc_template_data['type_data_column_'.$type_data_column_id][$field][$type_data_and_column];
                }else{
                    $type_data_and_column_additional[$j]['value'] = '';
                }
                $type_data_and_column_additional[$j]['placeholder'] = $this->language->get('entry_type_data_column_'.$type_data_column_id);
                */
            }

            // options
            $j++;
//            if ($type_data_column == 'option_value_type') {
//            }

            // id import data
            $j++;
            if ($type_data_column == 'identificator') {
                $types_data = $this->getTypesData($format_data);
                $data['types_data'] = $types_data['types_data'];
                $data['unique_types_data'] = array();
                foreach ($data['types_data'] as $type_data_this => $tmp) {
                    $data['unique_types_data'][$type_data_this] = array(
                        'aid' => sprintf($this->language->get('entry_unique_type_data_aid'), $type_data_this . '_id'),
                        'name' => sprintf($this->language->get('entry_unique_type_data_name'), 'name')
                    );
                    if ($type_data_this == 'product') {
                        $data['unique_types_data'][$type_data_this]['model'] = sprintf($this->language->get('entry_unique_type_data_model'), 'model');
                        //$data['unique_types_data'][$type_data_this]['sku'] = sprintf($this->language->get('entry_unique_type_data_sku'), 'sku');
                        $data['unique_types_data'][$type_data_this]['ean'] = sprintf($this->language->get('entry_unique_type_data_ean'), 'ean');
                    }
                }
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = '';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_identificator][' . $field . '][' . $type_data_and_column . ']';
                $unique_types_data = $data['unique_types_data'][str_replace('_identificator', '', $type_data)];
                $i = 0;
                foreach ($unique_types_data as $value_option => $text_option) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $value_option;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $text_option;
                    if (isset($import_yml_oc_template_data['type_data_column_identificator'][$field][$type_data_and_column]) && $value_option == $import_yml_oc_template_data['type_data_column_identificator'][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                    $i++;
                }
            }

            $j++;
            if ($type_data_column == 'quantity') {
                $type_data_column_id = 'quantity_request';
                $type_data_and_column_additional[$j]['element'] = 'select';
                $type_data_and_column_additional[$j]['style'] = '';
                $type_data_and_column_additional[$j]['onchange'] = '';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                for ($i = 0; $i < 3; $i++) {
                    $type_data_and_column_additional[$j]['options'][$i]['value'] = $i;
                    $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_quantity_request_' . $i);
                    if (isset($import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column]) && $import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column]) {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                    } else {
                        $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                    }
                }

                $j++;
                $type_data_column_id = 'quantity_update';
                $type_data_and_column_additional[$j]['element'] = 'input';
                $type_data_and_column_additional[$j]['type'] = 'text';
                $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_' . $type_data_column_id . '][' . $field . '][' . $type_data_and_column . ']';
                if (isset($import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column])) {
                    $type_data_and_column_additional[$j]['value'] = $import_yml_oc_template_data['type_data_column_' . $type_data_column_id][$field][$type_data_and_column];
                } else {
                    $type_data_and_column_additional[$j]['value'] = '';
                }
                $type_data_and_column_additional[$j]['placeholder'] = $this->language->get('entry_type_data_column_' . $type_data_column_id);
            }

            // for all required or not
            $j++;
            $type_data_and_column_additional[$j]['element'] = 'select';
            $type_data_and_column_additional[$j]['style'] = '';
            $type_data_and_column_additional[$j]['onchange'] = '';
            $type_data_and_column_additional[$j]['name'] = 'import_yml_oc_template_data_yml[type_data_column_request][' . $field . '][' . $type_data_and_column . ']';
            for ($i = 0; $i < 2; $i++) {
                $type_data_and_column_additional[$j]['options'][$i]['value'] = $i;
                $type_data_and_column_additional[$j]['options'][$i]['text'] = $this->language->get('entry_type_data_column_request_' . $i);
                if (isset($import_yml_oc_template_data['type_data_column_request'][$field][$type_data_and_column]) && $import_yml_oc_template_data['type_data_column_request'][$field][$type_data_and_column]) {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = 'selected=""';
                } else {
                    $type_data_and_column_additional[$j]['options'][$i]['selected'] = '';
                }
            }
            $data['type_data_and_column_additional'] = $type_data_and_column_additional;
            $data['entry_type_data_column_title'] = $this->language->get('entry_type_data_column_title');
        }

        $data['template_data_selected'] = $import_yml_oc_template_data;

        return $this->response->setOutput($this->load->view('extension/module/import_yml_oc_types_data_column', $data));
    }

    public function getAttributes($data = array('start' => 0, 'limit' => 10000)) {
        $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group_name FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        $sql .= " ORDER BY attribute_group_name, ad.name";
        $sql .= " ASC";
        $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        $query = $this->db->query($sql);

        $result = array();

        if ($query->rows) {
            foreach ($query->rows as $value) {
                $result[$value['attribute_group_id'] . '_' . $value['attribute_id']] = $value;
            }
        }

        ksort($result);

        return $result;
    }

    public function setTemplateData() {
        $this->load->model('setting/setting');

        $import_yml_oc_template_data_id = $this->request->post['import_yml_oc_template_data_yml']['id'];
        $import_yml_oc_template_data_name = $this->request->post['import_yml_oc_template_data_yml']['name'];
        $format_data = $this->request->post['import_yml_oc_format_data'];
        $type_action = $this->request->get['type_action'];

        if ($this->config->get('import_yml_oc_template_data_yml')) {
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'] = $this->config->get('import_yml_oc_template_data_yml');
        } else {
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'] = array();
        }

        // new template
        if (!$import_yml_oc_template_data_id) {
            $import_yml_oc_template_data_id = md5(time());
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id] = $this->request->post['import_yml_oc_template_data_yml'];
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id]['id'] = $import_yml_oc_template_data_id;
        } elseif ($import_yml_oc_template_data_id && $type_action == 'update') {
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id] = $this->request->post['import_yml_oc_template_data_yml'];
        } elseif ($import_yml_oc_template_data_id && $type_action == 'save') {
            $import_yml_oc_template_data_id = md5(time());
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id] = $this->request->post['import_yml_oc_template_data_yml'];
            $import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id]['id'] = $import_yml_oc_template_data_id;
        } elseif ($import_yml_oc_template_data_id && $type_action == 'delete') {
            unset($import_yml_oc_templates_data['import_yml_oc_template_data_yml'][$import_yml_oc_template_data_id]);
            $result['import_yml_oc_template_data_yml_id_delete'] = $import_yml_oc_template_data_id;
            $import_yml_oc_template_data_id = 0;
            $import_yml_oc_template_data_name = '';
        }

        $result['error'] = '';
        $result['success'] = '';

        $this->load->language('extension/module/import_yml_oc');

        if ($this->validate()) {
            $this->model_setting_setting->editSetting('import_yml_oc', $import_yml_oc_templates_data);
        } else {
            $result['error'] = $this->language->get('error_permission');
        }

        $result['import_yml_oc_template_data_yml_id'] = $import_yml_oc_template_data_id;
        $result['import_yml_oc_template_data_yml_name'] = $import_yml_oc_template_data_name;

        if (!$result['error']) {
            $result['success'] = $this->language->get('entry_import_yml_oc_template_data_done');
        }

        echo json_encode($result);
    }

    public function startImport() {
        $format_data = $this->request->post['import_yml_oc_template_data_yml']['format_data'];
        $import_yml_oc_template_data = $this->request->post['import_yml_oc_template_data_yml'];
        $import_data_types = array();
        $attribute_or_filter = '';
        $this->load->model('tool/import_yml_oc');
        $this->load->language('extension/module/import_yml_oc');
        $json['error'] = '';

        if (!$import_yml_oc_template_data['attribute']['import_status'] && !$import_yml_oc_template_data['manufacturer']['import_status'] && !$import_yml_oc_template_data['category']['import_status'] && !$import_yml_oc_template_data['product']['import_status']) {
            $json['error'] .= $this->language->get('text_no_data_selected_for_import');
        }

        // checking exist field with id, if data will be for updating
        if (($import_yml_oc_template_data['type_change'] == 'only_update_data')) {
            if (!$import_yml_oc_template_data['product']['id_column']) {
                $json['error'] .= $this->language->get('entry_identificator_empty');
            }
        }

        if ($import_yml_oc_template_data['product']['image_upload']) {
            $check_curl = $this->checkCURL();

            if (!$check_curl) {
                $json['error'] .= '<p>' . $this->language->get('entry_curl_exits') . '</p>';
            }
        }

        // uplift file
        if ($import_yml_oc_template_data['file_url']) {
            $file = $this->model_tool_import_yml_oc->getFileByURL($import_yml_oc_template_data['file_url']);
        } else {
            $file = $this->model_tool_import_yml_oc->getFileByFileName($import_yml_oc_template_data['file_upload']);
        }

        if (!$file) {
            $json['error'] .= '<p>' . $this->language->get('entry_file_exits') . '</p>';
        }

        if (!$this->validate()) {
            $json['error'] .= '<p>' . $this->language->get('error_permission') . '</p>';
        }

        $start = $this->request->get['start'];
        $limit = $this->request->post['import_yml_oc_template_data_yml']['limit'];

        $json['success'] = '';

        $import_result['count_offers'] = 0;
        $import_result['count_categories'] = 0;

        if (!$json['error']) {
            $import_result = $this->model_tool_import_yml_oc->getXMLRows($start, $limit, $import_yml_oc_template_data, $import_yml_oc_template_data['file_url'], FALSE, 0, '', FALSE, FALSE, $import_yml_oc_template_data['file_upload']);
            $this->model_tool_import_yml_oc->importYML($import_yml_oc_template_data, $import_result, $start, $limit);
        }

        $json['total'] = 0;
        $json['finished'] = $start + $limit;

        if ($import_yml_oc_template_data['category']['import_status'] && !$import_yml_oc_template_data['product']['import_status']) {
            $json['total'] = $import_result['count_categories'];
        }

        if ($import_yml_oc_template_data['product']['import_status']) {
            $json['total'] = $import_result['count_offers'];
        }

        if (($start + $limit) > $json['total'] && $json['total'] > 0) {
            $json['success'] = $this->language->get('import_success_accomplished');
        }

        echo json_encode($json);
    }

    private function checkCURL() {
        if (function_exists('curl_version')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function getCategories() {
        $this->load->language('extension/module/abcxyzanalysis');
        $data['text_no_manufacturers'] = $this->language->get('text_no_manufacturers');
        $data['text_select_all'] = $this->language->get('text_select_all');

        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(array('limit' => 10000, 'start' => 0));
        $data['categories_selected'] = array();

        if (isset($this->request->get['c']) && $this->request->get['c']) {
            $categories_selected = explode('_', $this->request->get['c']);
            foreach ($categories_selected as $category_selected) {
                $data['categories_selected'][$category_selected] = $category_selected;
            }
        }

        $this->response->setOutput($this->load->view('extension/module/abcxyzanalysis_categories.tpl', $data));
    }

    protected function curl_get_contents($url) {
        if (function_exists('curl_version')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            $output = curl_exec($ch);
            curl_close($ch);
            return $output;
        } else {
            $output['ru-ru'] = 'Проверка версии недоступна. Включите php расширение - CURL на Вашем хостинге';
            $output['uk-ua'] = 'Проверка версії недоступна. Включіть розширення php - CURL на вашему хостинге';
            $output['en-gb'] = 'You can not check the version. Enable php extension - CURL on your hosting';
            $language_code = $this->config->get('config_admin_language');
            if (isset($output[$language_code])) {
                return $output[$language_code];
            } else {
                return $output['en-gb'];
            }
        }
    }

    private function getFloat($string) {
        $find = array('-', ',', ' ');
        $replace = array('.', '.', '');
        $result = (float)str_replace($find, $replace, $string);

        return $result;
    }
}

?>