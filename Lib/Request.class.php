<?php
namespace Spartan\Lib;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Request {
    private $arrRequest = [];//
    /**
     * @param array $arrConfig
     * @return Validation
     */
    public static function instance($arrConfig = []) {
        return \Spt::getInstance(__CLASS__,$arrConfig);
    }

    /**
     * Validation constructor.
     * @param array $_arrConfig
     */
    public function __construct($_arrConfig = []){

    }

    public function setValue($name,$value){
        if (is_array($name)){
            $this->arrRequest = array_merge($this->arrRequest,$name);
        }else{
            $this->arrRequest[$name] = $value;
        }
    }

    public function all(){


    }

    public function input($name,$default=null){

    }

    public function get($name,$default=null){

    }

    public function post($name,$default=null){

    }

    public function server($name,$default=null){

    }

    public function session($name,$default=null){

    }

    public function cookies($name,$default=null){

    }
}