<?php
namespace Spartan\Core;

defined('APP_PATH') OR die('404 Not Found');

class ParseConfig{
    private $arrConfig = [];

    /**
     * @param array $arrConfig
     * @return ParseConfig
     */
    public static function instance($arrConfig = []) {
        return \Spt::getInstance(__CLASS__,$arrConfig);
    }

    public function __construct($_arrConfig = []){
        (!isset($_arrConfig['type']) || !$_arrConfig['type']) && $_arrConfig['type'] = 'json';
        (!isset($_arrConfig['content']) || !$_arrConfig['content']) && $_arrConfig['content'] = '';
        $_arrConfig['type'] = strtolower($_arrConfig['type']);
        $this->arrConfig = $_arrConfig;
    }

    public function parse(){
        if (!$this->arrConfig['content']){
            return [];
        }
        switch ($this->arrConfig['type']){
            case 'json':
                if (is_file($this->arrConfig['content'])) {
                    $this->arrConfig['content'] = file_get_contents($this->arrConfig['content']);
                }
                return json_decode($this->arrConfig['content'], true);
            case 'xml':
                if (is_file($this->arrConfig['content'])) {
                    $this->arrConfig['content'] = simplexml_load_file($this->arrConfig['content']);
                } else {
                    $this->arrConfig['content'] = simplexml_load_string($this->arrConfig['content']);
                }
                $arrResult = (array) $this->arrConfig['content'];
                foreach ($arrResult as $key => $val) {
                    if (is_object($val)) {
                        $arrResult[$key] = (array) $val;
                    }
                }
                return $arrResult;
            case 'ini':
                if (is_file($this->arrConfig['content'])) {
                    return parse_ini_file($this->arrConfig['content'], true);
                } else {
                    return parse_ini_string($this->arrConfig['content'], true);
                }
            default:
                return (array)$this->arrConfig['content'];
        }
    }
}