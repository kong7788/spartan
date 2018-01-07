<?php
namespace Spartan\Lib;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Request {
    private $arrPost = [];
    private $arrGet = [];
    private $arrFile = [];
    private $arrServer = [];
    private $arrCookie = [];
    private $arrSession = [];
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
        $this->arrPost = $_POST;
        $this->arrGet = $_GET;
        $this->arrFile = $_FILES;
        $this->arrServer = $_SERVER;
        $this->arrCookie = $_COOKIE;
        $this->arrSession = $_SESSION;
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
        if(strpos($name,'.')) {
            list($method,$name) = explode('.',$name,2);
        }else{
            $method = 'post';
        }
        !in_array($method,['get','post','file','server','session','cookies','put']) && $method = 'post';
        return $this->{$method}($name,$default);
    }

    public function get($name,$default=null){
        return $this->getValue($_GET,$name,$default);
    }

    public function post($name,$default=null){
        return $this->getValue($_POST,$name,$default);
    }

    public function file($name,$default=null){
        return $this->getValue($_FILES,$name,$default);
    }

    public function server($name,$default=null){
        return $this->getValue($_SERVER,$name,$default);
    }

    public function session($name,$default=null){
        return $this->getValue($_SESSION,$name,$default);
    }

    public function cookies($name,$default=null){
        return $this->getValue($_COOKIE,$name,$default);
    }

    public function put($name,$default=null){
        parse_str(file_get_contents('php://input'), $input);
        return $this->getValue($input,$name,$default);
    }


    private function getValue(&$source,$name,$default=null){
        $value = null;
        if (isset($source[$name])){
            $value = $source[$name];
        }else{
            $value = $default;
        }
        if (is_int($default)){
            $value = intval($value);
        }elseif (is_string($default)){
            $value = trim($value);
        }
        return $value;
    }
}