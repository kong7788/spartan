<?php
namespace Spartan\Driver\Db;

defined('APP_PATH') or exit();

class Mysqli implements Db {
    /**
     * @description 连接数据库方法
     * @param array $_arrConfig
     * @return int
     */
    public function connect($_arrConfig = []) {
        $intLinkID = mysqli_connect(
            $_arrConfig['HOST'],
            $_arrConfig['USER'],
            $_arrConfig['PWD'],
            $_arrConfig['NAME'],
            $_arrConfig['PORT']
        );
        if (!$intLinkID){
            \Spt::halt(['sql connect fail',json_encode($_arrConfig)]);
        }
        (!isset($_arrConfig['CHARSET']) || $_arrConfig['CHARSET']) && $_arrConfig['CHARSET'] = 'utf-8';
        mysqli_query($intLinkID,"SET NAMES '".$_arrConfig['CHARSET']."'");
        mysqli_query($intLinkID,"SET sql_mode=''");
        return $intLinkID;
    }

    /**
     * 释放查询结果
     * @param $intQueryID \mysqli_result
     * @return bool
     */
    public function free($intQueryID) {
	    mysqli_free_result($intQueryID);
	    return true;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param \mysqli $intLinkID 数据库连接
     * @param string $strSql sql指令
     * @return mixed
     */
    public function query($intLinkID,$strSql) {
        $intQueryID = mysqli_query($intLinkID,$strSql);
        if ( false === $this->queryID ) {
            if ($this->reTest >= 3 || !$this->reTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->query($str);
            }
        } else {
            $this->numRows = mysqli_num_rows($this->queryID);
            return $this->getAll();
        }
    }

    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @return integer
     */
    public function execute($str) {
	    $this->initConnect(true);
	    if ( !$this->_linkID ) return false;
	    $this->queryStr = $str;
	    //释放前次的查询结果
	    if ( $this->queryID ) {$this->free();}
	    $result =   mysqli_query($this->_linkID,$str) ;
	    if ( false === $result) {
            if ($this->reTest >= 3 || !$this->reTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->execute($str);
            }
	    } else {
		    $this->numRows = mysqli_affected_rows($this->_linkID);
		    $this->lastInsID = mysqli_insert_id($this->_linkID);
		    return $this->numRows;
	    }
    }

    /**
     * 用于获取最后插入的ID
     * @access public
     * @return integer
     */
    public function last_insert_id() {
	    $this->lastInsID = mysqli_insert_id($this->_linkID);
        return $this->lastInsID;
    }



    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getAll() {
	    //返回数据集
	    $result = array();
	    if($this->numRows >0) {
		    while($row = mysqli_fetch_assoc($this->queryID)){
			    $result[]   =   $row;
		    }
		    mysqli_data_seek($this->queryID,0);
	    }
	    return $result;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     */
    public function getFields($tableName) {
	    $result =   $this->query('SHOW COLUMNS FROM '.$this->parseKey($tableName));
	    $info   =   array();
	    if($result) {
		    foreach ($result as $key => $val) {
			    $info[$val['Field']] = array(
				    'name'    => $val['Field'],
				    'type'    => $val['Type'],
				    'notnull' => (bool) ($val['Null'] === ''), // not null is empty, null is yes
				    'default' => $val['Default'],
				    'primary' => (strtolower($val['Key']) == 'pri'),
				    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
			    );
		    }
	    }
	    return $info;
    }

    /**
     * 取得数据库的表信息
     * @access public
     */
    public function getTables($dbName='') {
	    if(!empty($dbName)) {
		    $sql    = 'SHOW TABLES FROM '.$dbName;
	    }else{
		    $sql    = 'SHOW TABLES ';
	    }
	    $result =   $this->query($sql);
	    $info   =   array();
	    foreach ($result as $key => $val) {
		    $info[$key] = current($val);
	    }
	    return $info;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close() {
	    if ($this->_linkID){
		    @mysqli_close($this->_linkID);
	    }
	    $this->_linkID = null;
        $this->linkID = [];
        $this->connected = false;
        $this->reTest = 0;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @return string
     */
    public function error() {
	    $this->error = mysqli_error($this->_linkID);
	    if('' != $this->queryStr){
		    $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
	    }
	    \St::halt($this->error,'','ERR');
	    return $this->error;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL指令
     * @return string
     */
    public function escapeString($value) {
	    if(!$this->_linkID) {
            $this->initConnect(true);
	    }
        return mysqli_real_escape_string($this->_linkID,$value);
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
}