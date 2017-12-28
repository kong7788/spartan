<?php
namespace Spartan\Core;

defined('APP_PATH') OR exit('404 Not Found');

abstract class Model {
    public static $arrRequestData = [];//可用的数据池

    /**
     * 初始化，把get、pos的值都放在一起，同名优先post
     * Dal constructor.
     * @param array $_arrRequest
     */
    public function __construct($_arrRequest = []){
        self::$arrRequestData = array_merge($_GET, $_POST);
        is_array($_arrRequest) && $this->setData($_arrRequest);
    }

    /**
     * 设置一个数据组，用于和Control一样的getData
     * @param array $_arrRequest
     * @return $this
     */
    public function setData($_arrRequest = []){
        if (self::$arrRequestData){
            self::$arrRequestData = array_merge(self::$arrRequestData,$_arrRequest);
        }else{
            self::$arrRequestData = $_arrRequest;
        }
        return $this;
    }

    /**
     * 获取调用的指定信息
     * @param string $key 要获取的$this->request_data[$key]数据
     * @param string $default 当$key数据为空时，返回$value的内容
     * @return mixed
     */
    public function getData($key = null,$default = null){
        is_array($key) && list($key,$default) = $key;
        if (!$key && !$default){
            return self::$arrRequestData;
        }elseif (isset(self::$arrRequestData[$key])){
            if(is_numeric($default)){
                return intval(self::$arrRequestData[$key]);
            }elseif (is_string($default)){
                return trim(self::$arrRequestData[$key]);
            }else{
                return self::$arrRequestData[$key];
            }
        }else{
            return $default;
        }
    }

    /**
     * 返回一个Dal操作类
     * @param $tableName \stdClass|string
     * @return mixed
     */
    public function getDalClass($tableName){
        if (is_object($tableName)){//是一个类
            \Spt::setInstance(get_class($tableName),$tableName);
            return $tableName;
        }else{
            $arrTempTable = explode('_',$tableName);
            array_walk($arrTempTable,function(&$v){$v = ucfirst(strtolower($v));});
            $strPathName = array_shift($arrTempTable);
            $strClassName = implode('',$arrTempTable);
            return \Spt::getInstance('Dal\\'.$strPathName.'\\'.$strClassName);
        }
    }

    /**
     * 返回一个数据库操作类
     * @return null|\Spartan\Lib\Db;
     */
    public function Db(){
        return \Spt::getInstance('Spartan\\Lib\\Db');
    }

} 