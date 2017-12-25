<?php
namespace Spartan\Driver\Db;

defined('APP_PATH') or exit();

class Mysqli implements Db {
    private $arrConfig = [];//数据库配置

    /**
     * 初始化配置
     * Mysqli constructor.
     * @param array $_arrConfig
     */
    public function __construct($_arrConfig = []){
        (!isset($_arrConfig['CHARSET']) || $_arrConfig['CHARSET']) && $_arrConfig['CHARSET'] = 'utf-8';
        $this->arrConfig = $_arrConfig;
    }

    /**
     * @description 连接数据库方法
     * @return int
     */
    public function connect() {
        $intLinkID = mysqli_connect(
            $this->arrConfig['HOST'],
            $this->arrConfig['USER'],
            $this->arrConfig['PWD'],
            $this->arrConfig['NAME'],
            $this->arrConfig['PORT']
        );
        if (!$intLinkID){
            \Spt::halt(['sql connect fail',json_encode($this->arrConfig)]);
        }
        mysqli_query($intLinkID,"SET NAMES '".$this->arrConfig['CHARSET']."'");
        mysqli_query($intLinkID,"SET sql_mode=''");
        return $intLinkID;
    }

    /**
     * 释放查询结果
     * @param $intQueryID \mysqli_result
     * @return bool
     */
    public function free($intQueryID) {
	    return mysqli_free_result($intQueryID);
    }

    /**
     * 关闭数据库
     * @access public
     * @param $intLinkID
     */
    public function close($intLinkID) {
        $intLinkID && @mysqli_close($intLinkID);
    }

    /**
     * @param $intLinkID
     * @return bool
     */
    public function isReTry($intLinkID){
        $intErrNo = mysqli_errno($intLinkID);
        return ($intErrNo == 2013 || $intErrNo == 2006)?true:false;
    }
    /**
     * 执行查询 返回数据集
     * @access public
     * @param \mysqli $intLinkID 数据库连接
     * @param string $strSql sql指令
     * @return mixed
     */
    public function query($intLinkID,$strSql) {
        return mysqli_query($intLinkID,$strSql);
    }

    public function getNumRows($queryID){
        return mysqli_num_rows($queryID);
    }

    public function getAffectedRows($intLinkID){
        return mysqli_affected_rows($intLinkID);
    }

    /**
     * 用于获取最后插入的ID
     * @access public
     * @return integer
     */
    public function getInsertId($intLinkID) {
	    return mysqli_insert_id($intLinkID);
    }

    /**
     * 获得所有的查询数据
     * @param  $queryID
     * @return array
     */
    public function getAll($queryID) {
	    $arrResult = Array();
        while($row = mysqli_fetch_assoc($queryID)){
            $arrResult[] = $row;
        }
        mysqli_data_seek($queryID,0);
	    return $arrResult;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @return string
     */
    public function error($intLinkID) {
	    return mysqli_error($intLinkID);
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $value  SQL指令
     * @param \mysqli $intLinkID 资源
     * @return string
     */
    public function escapeString($value,$intLinkID = null) {
        return mysqli_real_escape_string($intLinkID,$value);
    }

    /**
     * limit
     * @param int
     * @return string
     */
    public function parseLimit($limit) {
	    return !empty($limit)?' LIMIT '.$limit.' ':'';
    }

	/**
	 * 字段和表名处理添加`
	 * @access protected
	 * @param string $key
	 * @return string
	 */
	public function parseKey($key) {
		$key = trim($key);
		if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key)) {
            $key = '`'.$key.'`';
		}
		return $key;
	}


    /**
     * 取得数据表的字段信息
     * @access public
     */
    public function getFields($intLinkID,$tableName) {
        $result = mysqli_query($intLinkID,'SHOW COLUMNS FROM '.$tableName);
        $arrInfo = Array();
        if($result) {
            foreach ($result as $key => $val) {
                $arrInfo[$val['Field']] = Array(
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'notnull' => (bool)($val['Null'] === ''), // not null is empty, null is yes
                    'default' => $val['Default'],
                    'primary' => (strtolower($val['Key']) == 'pri'),
                    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
                );
            }
        }
        return $arrInfo;
    }

    /**
     * 取得数据库的表信息
     * @access public
     */
    public function getTables($intLinkID,$strDbName = '') {
        $strSql = $strDbName?'SHOW TABLES FROM '.$strDbName:'SHOW TABLES';
        $result = mysqli_query($intLinkID,$strSql);
        $arrInfo = Array();
        foreach ($result as $key => $val) {
            $arrInfo[$key] = current($val);
        }
        return $arrInfo;
    }
}