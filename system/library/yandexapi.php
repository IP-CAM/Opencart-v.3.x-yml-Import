<?php

class YandexApi {
    const URL = 'https://translate.yandex.net/api/v1.5/tr.json/translate?';
    private $keys = array(
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T204933Z.50c69975ad08dd54.548ace68df8e2ff352e714550f2ce4f4493af9c6'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T210935Z.93a49a5eedb85bff.2f9e7ca2a5eea01f9e8939fe7a53d3cfdbdb22b3'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211121Z.7599108471aecc09.c37363dde086ec1e1dfdb17947cb8a3556a0d3e0'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211243Z.390349354cd2f4b8.f27097f1907f3ebdc880752241248e021e5c3f5c'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211404Z.ec35364cb260a617.4f32129eef68961374f403cb18d3d0c0b4b50420'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211505Z.4a60ed87a1165579.d0f0bc06df28ea36910a4150c92ddf7f65a4b2d1'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211600Z.4153a8e92fb56681.ae1eaa21ffa2b11de0894073f4731804eeeded7f'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211730Z.f3d4d69879bb66ff.204399bbb3cfa489f27e9030cd9201126ce2e459'),
        array('expired' => false, 'key' => 'trnsl.1.1.20191222T211823Z.e09a80a1ee9118c3.a6b78f4518bb705bd3735a463a3637a5c2492567')
    );
    private $key = '';
    private static $instance;
    private $languages = [];
    private $err_msg = '';

    private function __construct() {
        $this->key = $this->keys[0]['key'];
        if (!$this->getSupportedlanguages()) {
            $this->languages = array('ru' => 'русский', 'uk' => 'украинский', 'en' => 'английский');
        }
    }

    public static function Factory() {
        if (!isset(self::$instance)) {
            self::$instance = new YandexApi();
        }
        return self::$instance;
    }

    public function plainTranslate($text, $source_language, $dest_language, &$translatedText, $format = 'plain') {
        if (array_key_exists($source_language, $this->languages) && array_key_exists($dest_language, $this->languages)) {
            try {
                $url = self::URL;
                $key = $this->key;
                $lang = $source_language . "-" . $dest_language;
                $data = "key=$key&lang=$lang&text=$text&format=$format";
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
                                $this->plainTranslate($text, $source_language, $dest_language, $translatedText, $format);
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
            } catch (Exception $e) {
                $this->err_msg = "exception : " . $e->getMessage();
                return false;
            }
        }
    }

    public function getSupportedlanguages() {
        try {
            $url="https://translate.yandex.net/api/v1.5/tr.json/getLangs?";
            $data ="key=" . $this->key . "&ui=ru";
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
        } catch (Exception $e) {
            $this->err_msg = "exception : " . $e->getMessage();
            return false;
        }
    }

    public function getErrorMsg() {
        return $this->err_msg;
    }

    private function setCurrentKey($k) {
        $this->key = $k;
    }

    private function setExpiredByIndex($index) {
        foreach ($this->keys as $idx => &$keyArr) {
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

?>