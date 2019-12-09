<?php

class YandexApi {
    const URL = 'https://translate.yandex.net/api/v1.5/tr.json/translate';
    const TEXT_FORMAT_PLAIN ='plain';
    const TEXT_FORMAT_XML = 'xml';
    private $keys = array(
        array('expired' => false, 'key' => 'trnsl.1.1.20191206T153608Z.ad3375b66a0ee151.6e6606f00a22641e96b512ace931abed5f840115'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191206T153509Z.8c624e2a13bad083.28e4c7302b1a8c6dbadba3d7bd0b94ed8f904c54'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191206T153917Z.2df316128ef217c8.0c0daec331ec08c8c19ad2cdeb24b3aed4a6c00d'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191206T153806Z.62d6d35ce8cde938.081f9c2aa575dd9d5bfd09c20942064797cd8e82'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191206T154430Z.266910a0c81f67ce.afc79a185aac8318af296815d7965173746357d8')
    );
    private $key = '';
    private static $instance;
    private $languages = [];
    private $err_msg = '';

    private function __construct() {
        $this->key = $this->keys[0]['key'];
        if (!$this->getSupportedlanguages()) {
            $this->languages = array('ru' => 'русский');
        }
    }

    public static function Factory() {
        if (!isset(self::$instance)) {
            self::$instance = new YandexApi();
        }
        return self::$instance;
    }

    public function plainTranslate($text, $source_language, $dest_language, &$translatedText) {
        if (array_key_exists($source_language, $this->languages) && array_key_exists($dest_language, $this->languages)) {
            try {
                $url = self::URL;
                $key = $this->key;
                $lang = $source_language . "-" . $dest_language;
                $format = self::TEXT_FORMAT_PLAIN;
                $data = "key=$key&text=$text&lang=$lang&format=$format";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                $ob = json_decode($response);
                if (isset($ob->code) && $ob->code != 200) {
                    if ($ob->code == 404) {
                        $idx = $this->findIndexCurrentKey();
                        if ($idx !== false) {
                            $this->setExpiredByIndex($idx);
                            $nonExpiredKey = $this->getNonExpiredKey();
                            if ($nonExpiredKey !== false) {
                                $this->setCurrentKey($nonExpiredKey);
                                $this->plainTranslate($text, $source_language, $dest_language, $translatedText);
                            } else {
                                $this->err_msg = $ob->message;
                                return false;
                            }
                        } else {
                            $this->err_msg = $ob->message;
                            return false;
                        }
                    }
                    $this->err_msg = $ob->message;
                    return false;
                }
                $translatedText = $ob->text[0];
                return true;
            } catch (\Exception $e) {
                $this->err_msg = "exception : " . $e->getMessage();
                return false;
            }
        }
    }

    public function getSupportedlanguages() {
        try {
            $url="https://translate.yandex.net/api/v1.5/tr.json/getLangs";
            $data ="key=" . $this->key . "&ui=en";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            $ob = json_decode($response);
            if (isset($ob->code) && $ob->code != 200) {
                $this->err_msg = $ob->message;
                return false;
            }
            $this->languages = $ob->langs;
            return true;
        } catch (\Exception $e) {
            $this->err_msg = "exception : " . $e->getMessage();
            return false;
        }
    }

//    public function detect($text, &$lang, &$msg, &$err_msg) {
//        try {
//            $url = "https://translate.yandex.net/api/v1.5/tr.json/detect";
//            $data = "key=" . $this->key;
//            $data .= "&text=$text";
//            $ch = curl_init($url);
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//            curl_setopt($ch, CURLOPT_HEADER, 0);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//            $response = curl_exec($ch);
//            $ob = json_decode($response);
//            if (isset($ob->code) && $ob->code != 200) {
//                $err_msg = $ob->message;
//                return false;
//            }
//            $lang = $ob->lang;
//            return true;
//        } catch (\Exception $e) {
//            $err_msg = "exception : " . $e->getMessage();
//            return false;
//        }
//    }

    public function getErrorMsg() {
        return $this->err_msg;
    }

    private function setCurrentKey($k) {
        $this->key = $k;
    }

    private function setExpiredByIndex($index) {
        foreach ($this->keys as $idx => $keyArr) {
            if ($idx == $index) {
                $keyArr['expired'] = true;
            }
        }
    }

    private function findIndexCurrentKey() {
        foreach ($this->keys as $idx => $keyArr) {
            foreach ($keyArr as $k => $v) {
                if ($k == 'key' && $v == $this->key) {
                    return $idx;
                }
            }
        }
        return false;
    }

    private function getNonExpiredKey() {
        foreach ($this->keys as $keyArr) {
            foreach ($keyArr as $k => $v) {
                if ($k == 'expired' && $v == false) {
                    return $keyArr['key'];
                }
            }
        }
        return false;
    }
}