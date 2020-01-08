<?php
class ControllerExtensionFeedImportYmlOc extends Controller {
    public function index() {
        $this->load->language('extension/module/import_yml_oc');
        $token = '';

        if (isset($this->request->get['user_token'])) {
            $token = trim($this->request->get['user_token']);
        } else {
            exit($this->language->get('error_no_token'));
        }

        if ($this->config->get('import_yml_oc_update_yml_link') && $this->config->get('import_yml_oc_template_data_yml')) {
            $update_settings = $this->config->get('import_yml_oc_update_yml_link');
            $update_setting = array();

            foreach ($update_settings as $setting) {
                if ($setting['token'] == $token) {
                    $update_setting = $setting;

                    if (!$update_setting['status']) {
                        exit($this->language->get('error_status'));
                    }

                    $templates_data = $this->config->get('import_yml_oc_template_data_yml');
                    $template_data = array();

                    if (isset($templates_data[$update_setting['template_data_id']])) {
                        $template_data = $templates_data[$update_setting['template_data_id']];
                    }
                }
            }

            if (!$update_setting) {
                exit($this->language->get('error_no_token'));
            }

            if (!$template_data) {
                exit($this->language->get('error_template_data'));
            }

        } else {
            exit($this->language->get('error_no_import_yml_oc_update_yml_link'));
        }

        $this->request->post['import_yml_oc_template_data_yml'] = $template_data;

        if (!isset($this->request->get['start'])) {
            $this->request->get['start'] = $template_data['start'];
        }

        $this->startImport();

    }

    private function checkCURL(){
        if (function_exists('curl_version')) {
            return true;
        } else {
            return false;
        }
    }

    public function startImport() {
        $this->load->language('extension/module/import_yml_oc');

        if (!isset($this->request->get['user_token'])) {
            exit($this->language->get('error_no_token'));
        }

        $format_data = $this->request->post['import_yml_oc_template_data_yml']['format_data'];

        $import_yml_oc_template_data_yml = $this->request->post['import_yml_oc_template_data_yml'];

        $import_data_types = array();

        $attribute_or_filter = '';


        if ($format_data == 'yml') {
            $this->load->model('tool/import_yml_oc');
            $this->load->language('extension/module/import_yml_oc');
        }

        if (isset($import_yml_oc_template_data_yml['type_data']) && !empty($import_yml_oc_template_data_yml)) {
            foreach ($import_yml_oc_template_data_yml['type_data'] as $field => $type_data) {
                $field = trim($field);

                if($type_data && $field){
                    $import_data_types[$field]['type_data'] = $type_data;

                    if ($type_data == 'attribute' || $type_data == 'filter') {
                        $attribute_or_filter = $type_data;
                    }

                    if (isset($import_yml_oc_template_data_yml['type_data_column'][$field]) && $import_yml_oc_template_data_yml['type_data_column'][$field]) {

                        $type_data_column = $import_yml_oc_template_data_yml['type_data_column'][$field];

                        $import_data_types[$field]['type_data_column'] = $type_data_column;

                        if (isset($import_yml_oc_template_data_yml['type_data_column_image'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_image'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_image_upload'] = 1;
                        } else {
                            $import_data_types[$field]['type_data_column_image_upload'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_price_rate'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_price_rate'][$field][$type_data_column]) {
                            $price_rate = $this->getFloat($import_yml_oc_template_data_yml['type_data_column_price_rate'][$field][$type_data_column]);
                            $import_data_types[$field]['type_data_column_price_rate'] = $price_rate;
                        } else {
                            $import_data_types[$field]['type_data_column_price_rate'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_price_delta'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_price_delta'][$field][$type_data_column]) {
                            $price_delta = $this->getFloat($import_yml_oc_template_data_yml['type_data_column_price_delta'][$field][$type_data_column]);
                            $import_data_types[$field]['type_data_column_price_delta'] = $price_delta;
                        } else {
                            $import_data_types[$field]['type_data_column_price_delta'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_price_around'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_price_around'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_price_around'] = 1;
                        } else {
                            $import_data_types[$field]['type_data_column_price_around'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_quantity_request'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_quantity_request'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_quantity_request'] = $import_yml_oc_template_data_yml['type_data_column_quantity_request'][$field][$type_data_column];
                        } else {
                            $import_data_types[$field]['type_data_column_quantity_request'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_quantity_update'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_quantity_update'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_quantity_update'] = (int)$import_yml_oc_template_data_yml['type_data_column_quantity_update'][$field][$type_data_column];
                        } else {
                            $import_data_types[$field]['type_data_column_quantity_update'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_request'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_request'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_request'] = 1;
                        } else {
                            $import_data_types[$field]['type_data_column_request'] = 0;
                        }

                        if (isset($import_yml_oc_template_data_yml['type_data_column_delimiter'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_delimiter'][$field][$type_data_column]) {
                            $import_data_types[$field]['type_data_column_delimiter'] = trim($import_yml_oc_template_data_yml['type_data_column_delimiter'][$field][$type_data_column]);
                        } else {
                            $import_data_types[$field]['type_data_column_delimiter'] = '';
                        }

                        /*
                        if(isset($import_yml_oc_template_data_yml['type_data_column_attribute_values_delimiter'][$field][$type_data_column]) && $import_yml_oc_template_data_yml['type_data_column_attribute_values_delimiter'][$field][$type_data_column]){

                            $import_data_types[$field]['type_data_column_attribute_values_delimiter'] = trim($import_yml_oc_template_data_yml['type_data_column_attribute_values_delimiter'][$field][$type_data_column]);

                        }else{

                            $import_data_types[$field]['type_data_column_attribute_values_delimiter'] = '';

                        }
                         */

                        $import_data_types[$field]['type_data_column_group_identificator'] = array();

                        if (isset($import_yml_oc_template_data_yml['type_data_column_group_identificator']) && $import_yml_oc_template_data_yml['type_data_column_group_identificator']) {
                            foreach ($import_yml_oc_template_data_yml['type_data_column_group_identificator'] as $type_identificator => $identificator) {
                                if (isset($identificator[$field][$type_data_column]) && $identificator[$field][$type_data_column]) {
                                    $type_identificator_parts = explode('_', $type_identificator);

                                    if(end($type_identificator_parts) == 'field'){
                                        $type_identificator = 'field';
                                    }

                                    $import_data_types[$field]['type_data_column_group_identificator']['type_group_identificator'] = $type_identificator;

                                    $import_data_types[$field]['type_data_column_group_identificator']['value_group_identificator'] = $identificator[$field][$type_data_column];
                                }
                            }
                        }

                        $column = explode('___', $type_data_column);

                        $import_data_types[$field]['column'] = $column[1];

                        $check_column = $this->model_tool_import_yml_oc->getColumnIntoAbstractField($column[1],$column[0]);

                        $table_descriptiom = '';

                        // for names need create table _description
                        if ($check_column == 'name' && ($column[0] == 'attribute' || $column[0] == 'filter')) {

                            $table_descriptiom = '_description';

                        }

                        $import_data_types[$field]['table_to_db'] = $column[0] . $table_descriptiom;

                        $identificator = array();

                        if (isset($import_yml_oc_template_data_yml['type_data_column_identificator'])) {
                            $identificator = $import_yml_oc_template_data_yml['type_data_column_identificator'];
                        }

                        if (isset($identificator[$field][$type_data_column]) && $identificator[$field][$type_data_column]) {
                            $import_data_types[$field]['identificator'][$type_data] = $identificator[$field][$type_data_column];
                        } else {
                            $import_data_types[$field]['identificator'][$type_data] = 0;
                        }

                        $import_data_types[$field]['type_change'] = $import_yml_oc_template_data_yml['type_change'];

                    } else {
                        unset($import_data_types[$field]);
                    }
                }
            }
        }

        $json['error'] = '';

        // check for the presence of a field with an identifier, if the data goes for updating
        if (($import_yml_oc_template_data_yml['type_change'] == 'only_update_data' || $import_yml_oc_template_data_yml['type_change'] == 'update_data')) {
            $identificators = array();
            $types_data = array();

            foreach($import_data_types as $field => $import) {

                if ($import['identificator'][$import['type_data']] && $import['identificator'][$import['type_data']]) {
                    if(!isset($identificator[$import['type_data']])){
                        $identificators[$import['type_data'] ] = true;
                    }
                }

                $types_data[$import['type_data']] = $import['type_data'];
            }

            foreach ($types_data as $type_data) {
                if (!isset($identificators[$type_data])) {
                    $json['error'] .= '<p>'.sprintf($this->language->get('entry_identificator_empty'),'<b>'.$this->language->get('text_type_data_'.$type_data).'</b>',$this->language->get('entry_type_change_new_data')).'</p>';
                }
            }

        }

        // validation of required fields when adding data for a specific data type
        if ($import_yml_oc_template_data_yml['type_change'] == 'new_data') {

            $types_data = array();

            foreach ($import_data_types as $field => $import) {

                // collect all data types, then to check for all whether there is $ required_fields
                $types_data[$import['type_data']] = $import['type_data'];

                if ($import['type_data']=='category') {

                    // create if not yet
                    if (!isset($required_fields)) {

                        $required_fields[$import['type_data']] = false;

                    }
                    // for this type a required name or path with a name
                    if ($import['column']=='name' || $import['column'] == 'category_whis_path') {
                        $required_fields[$import['type_data']] = true;
                    }
                }
            }

            foreach ($types_data as $type_data) {
                if(isset($required_fields[$type_data]) && !$required_fields[$type_data]){
                    $json['error'] .= '<p>'.$this->language->get('entry_'.$type_data.'_required_empty').'</p>';
                }
            }
        }

        // checking the import of filters and attributes for the presence of a related group
        if ($attribute_or_filter) {
            $error = '<p>'.sprintf($this->language->get('entry_attribute_or_filter_group_empty'),'<b>'.$this->language->get('text_type_data_'.$attribute_or_filter).'</b>').'</p>';

            foreach ($import_data_types as $field => $import) {
                if (isset($import['type_data_column_group_identificator']) && $import['type_data_column_group_identificator']) {
                    $error = "";
                }
            }

            $json['error'] .= $error;
        }

        // curl check if image loading is required somewhere
        $check_curl = false;

        foreach ($import_data_types as $field => $import) {
            if ($import['type_data_column_image_upload']) {
                $check_curl = true;
            }
        }

        if ($check_curl) {
            $check_curl = $this->checkCURL();

            if (!$check_curl) {
                $json['error'] .= '<p>'.$this->language->get('entry_curl_exits').'</p>';
            }
        }

        // upload file
        if ($import_yml_oc_template_data_yml['file_url']) {
            $file = $this->model_tool_import_yml_oc->getFileByURL($import_yml_oc_template_data_yml['file_url']);
        } else {
            $file = $this->model_tool_import_yml_oc->getFileByFileName($import_yml_oc_template_data_yml['file_upload']);
        }

        if (!$file) {
            $json['error'] .= '<p>'.$this->language->get('entry_file_exits').'</p>';
        }

        $start = $this->request->get['start'];

        $limit = $this->request->post['import_yml_oc_template_data_yml']['limit'];

        $json['success'] = '';

        $import_result['count_rows'] = 0;

        if ($format_data == 'yml' && !$json['error']) {
            $import_result = $this->model_tool_import_yml_oc->getXMLRows($start, $limit, $import_yml_oc_template_data_yml, $import_yml_oc_template_data_yml['file_url'], false, 0, '', false, false, $import_yml_oc_template_data_yml['file_upload']);
            $this->model_tool_import_yml_oc->importYML($import_yml_oc_template_data_yml, $import_result, $start, $limit);

        }

        $json['total'] = $import_result['count_offers'];

        if (!$json['error'] && (($start + $limit) > $import_result['count_offers'] && $import_result['count_offers'] > 0)) {
            $json['success'] = $this->language->get('import_success_accomplished');
        }  elseif (!$json['error'] && $import_result['count_offers'] > 0 && ($start + $limit) <= $import_result['count_offers']) {
            $this->response->redirect($this->url->link('extension/feed/import_yml_oc', 'start=' . ($start + $limit) . '&user_token=' . $this->request->get['user_token']));
        }

        if ($json['error']) {
            echo $json['error'];
        } elseif ($json['success']) {
            echo $json['success'];
        }

        exit();
    }

    private function getFloat($string) {
        $find = array('-',',',' ');
        $replace = array('.','.','');
        $result = (float)str_replace($find, $replace, $string);

        return $result;
    }

    public function getAttributeOrFilterGroups($language_id,$type_data_column) {
        $this->load->language('extension/module/import_yml_oc');

        if (!isset($this->request->get['user_token'])) {
            exit($this->language->get('error_no_token'));
        }

        if ($type_data_column == 'attribute_name') {
            $table = 'attribute_group_description';
        }

        if ($type_data_column == 'filter_name') {
            $table = 'filter_group_description';
        }

        if (!$language_id) {
            $language_id = (int)$this->config->get('config_language_id');
        }

        $sql = "SELECT * FROM " . DB_PREFIX . $table." WHERE language_id = '" . $language_id . "' ";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getAttributes($data = array('start'=>0, 'limit'=>10000)) {
        $this->load->language('extension/module/import_yml_oc');

        if (!isset($this->request->get['user_token'])) {
            exit($this->language->get('error_no_token'));
        }

        $sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group_name FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        $sql .= " ORDER BY attribute_group_name, ad.name";
        $sql .= " ASC";
        $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];

        $query = $this->db->query($sql);

        $result = array();

        if ($query->rows) {
            foreach ($query->rows as $value) {
                $result[$value['attribute_group_id'].'_'.$value['attribute_id']] = $value;
            }
        }

        ksort($result);

        return $result;
    }

    protected function curl_get_contents($url) {
        $this->load->language('extension/module/import_yml_oc');

        if (!isset($this->request->get['user_token'])) {
            exit($this->language->get('error_no_token'));
        }

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
            $output['ru'] = 'Проверка версии недоступна. Включите php расширение - CURL на Вашем хостинге';
            $output['en'] = 'You can not check the version. Enable php extension - CURL on your hosting';
            $language_code = $this->config->get( 'config_admin_language' );
            if(isset($output[$language_code])){
                return $output[$language_code];
            }else{
                return $output['en'];
            }
        }
    }
}
?>