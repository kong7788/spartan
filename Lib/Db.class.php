<?php
namespace Spartan\Lib;
defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据库操作类
 * Class Db
 * @package Spartan\Lib
 */
class Db{
    private $arrConfig = [];//连接配置
    private $transName = '';//事务名称
    private $reTest = 0;//重连接次数
    /** @var null|\Spartan\Driver\Db\Mysqli */
    private $clsDriverInstance = null;//
    private $arrComparison = ['eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN'];// 数据库表达式
    private $arrSql = [];//所有SQL指令
    private $bolBuild = false;//是否为建造SQL模式
    private $queryStr = '';//当前SQL指令
    private $lastInsID = 0;//最后插入ID
    /** @var $queryID \mysqli_result */
    private $queryID = 0;//当前查询ID
    private $linkID = null;//当前连接ID
    private $numRows = 0;// 返回或者影响记录数
    private $transTimes = 0;// 事务指令数
    private $strError = '';// 错误信息

    /**
	 * 取得数据库类实例
	 * @param array $_arrConfig
	 * @return Db 返回数据库驱动类
	 */
	public static function instance($_arrConfig = Array()) {
        return \Spt::getInstance(__CLASS__,$_arrConfig);
    }

    /**
     * 初始化配置
     * Db constructor.
     * @param array $_arrConfig
     */
    public function __construct($_arrConfig = []) {
        $_arrConfig = array_merge(C('DB'),$_arrConfig);
        if (!isset($_arrConfig['TYPE']) || !$_arrConfig['TYPE']){
            \Spt::halt(['the database does not configure "type"']);
        }
        if (!extension_loaded($_arrConfig['TYPE'])) {
            \Spt::halt(['not support extension',$_arrConfig['TYPE']]);
        }
        !isset($_arrConfig['PREFIX']) && $_arrConfig['PREFIX'] = '';
        $clsDbType = 'Spartan\\Driver\\Db\\'.ucfirst(strtolower($_arrConfig['TYPE']));
        if (!class_exists($clsDbType)){
            \Spt::halt(['db type class not exiting',$clsDbType]);
        }
        $this->clsDriverInstance = \Spt::getInstance($clsDbType,$_arrConfig);
        $this->arrConfig = $_arrConfig;
    }

    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $bolMaster 主副服务器
     * @return void
     */
    public function initConnect($bolMaster = true) {
        if ( isset($this->arrConfig['DEPLOY_TYPE']) && $this->arrConfig['DEPLOY_TYPE'] ){
            $this->linkID = $this->multiConnect($bolMaster);
        }else{
            !$this->linkID && $this->linkID = $this->connect();
        }
    }

    /**
     * 主从数据库
     * @param boolean $bolMaster
     * @return mixed
     */
    public function multiConnect($bolMaster = true){
        if ($bolMaster){
            return $this->clsDriverInstance->connect();
        }

        return $this->clsDriverInstance->connect();
    }

    /**
     * @description 连接数据库方法
     * @return mixed
     */
    public function connect() {
        return $this->clsDriverInstance->connect();
    }

    /**
     * 重新连接数据库，累计次数
     * @param bool $bolMaster
     */
    public function reConnect($bolMaster = true){
        $this->close();
        $this->reTest = 0;
        $this->initConnect($bolMaster);
    }

    /**
     * 是否可以重连
     * @return bool
     */
    public function isReTry(){
        return $this->clsDriverInstance->isReTry($this->linkID);
    }

    /**
     * 插入记录
     * @history 1、insert 没有锁表操作 by singer
     * @param string $table 表名
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @return false | integer
     */
    public function insert($table,$data,$options = []) {
        $values = $fields = [];
        $options['table'] = $table;
        $replace = isset($options['replace'])?$options['replace']:false;//是否replace
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $fields[]   =  $this->parseKey($key);
                $values[]   =  $val[1];
            }elseif(is_scalar($val) || is_null($val)) { // 过滤非标量数据
                $fields[]   =  $this->parseKey($key);
                $values[]   =  $this->parseValue($val);
            }
        }
        $sql = ($replace?'REPLACE':'INSERT').' INTO '.$this->parseTable($options['table']).
            ' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        $sql .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        if(!in_array($table,['sys_sql_log'])){
            $this->arrSql['insert'][] = $sql;
        }
        if($this->bolBuild){
            return false;
        }
        $result = $this->execute($sql);
        if(false !== $result ){
            $result = $this->getLastInsID();
        }
        return $result;
    }

    /**
     * 更新记录,
     * @history 1、update没有锁表操作 by singer
     * @param string $table 表名
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($table,$data,$options){
        if(!isset($options['where'])){//防止误操作，条件不能为空，不使用条件时，where==false
            return false;
        }elseif(isset($options['where']) && $options['where'] == false){
            $options['where'] = '';
        }
        $table = isset($options['alias'])?Array($table,$options['alias']):$table;
        $strSql = 'UPDATE '
            .$this->parseTable($table)
            .$this->parseSet($data)
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        $this->arrSql['update'][] = $strSql;
        if($this->bolBuild){
            return false;
        }
        return $this->execute($strSql);
    }

    /**
     * 删除记录
     * @param string $table 表名
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($table,$options = []){
        if(!isset($options['where'])){//防止误操作，条件不能为空，不使用条件时，where==false
            return false;
        }elseif(isset($options['where']) && $options['where'] == false){
            $options['where'] = '';
        }
        $this->initConnect(false);
        $strSql = 'DELETE '.'FROM '
            .$this->parseTable($table)
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        $this->arrSql['delete'][] = $strSql;
        if ($this->bolBuild){
            return false;
        }
        return $this->execute($strSql);
    }

    /**
     * 选择查询语句
     * @param $table
     * @param array $options
     * @return mixed
     */
    public function select($table,$options = []){
        $options['table'] = isset($options['alias'])?Array($table,$options['alias']):$table;
        $strSql = $this->buildSelectSql($options);
        $this->arrSql['select'][] = $strSql;
        return $this->query($strSql);
    }

    /**
     * 选择一条记录的查询语句
     * @param $table
     * @param array $options
     * @param string $math 是否运算
     * @return mixed
     */
    public function find($table,$options = [],$math = ''){
        if ($math){
            $math = explode('(',$math);//count(id). sum(money). min(id)
            if (!in_array($math[0],['count','sum','min','max','avg'])){
                $math = null;
            }else{
                $math[1] = (isset($math[1]) && $math[1])?substr($math[1],-1):'*';
                $options['field'] = "$math[0]($math[1]) as tmp";
                unset($options['order']);
            }
            unset($options['lock']);
        }
        unset($options['page']);
        $options['limit'] = 1;
        $options['table'] = isset($options['alias'])?Array($table,$options['alias']):$table;
        $strSql = $this->buildSelectSql($options);
        $this->arrSql['select'][] = $strSql;
        $result = $this->query($strSql);
        if(false === $result){
            return false;
        }
        if(empty($result) || !isset($result[0])){
            return null;
        }
        return $math?(isset($result[0]['tmp'])?$result[0]['tmp']:0):$result[0];
    }

    /**
     * 启动事务,数据rollback 支持
     * @access public
     * @param string $strName 事务的名称
     * @return boolean
     */
    public function startTrans($strName = '') {
        !$this->linkID && $this->initConnect(true);
        if ($this->transTimes == 0) {
            if (false == $this->clsDriverInstance->query($this->linkID,'START TRANSACTION')){
                if ($this->reTest >= 3 || !$this->isReTry()){
                    $this->error();
                    return false;
                }else{
                    $this->reTest++;
                    $this->reConnect();
                    return $this->startTrans($strName);
                }
            }
        }
        $this->transTimes++;
        (!$this->transName && $strName) && $this->transName = $strName;
        $this->arrSql['trans'][] = "startTrans,Master:{$this->transName},Current:{$strName}";
        return true;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @param string $strName 事务的名称
     * @return boolean
     */
    public function commit($strName = '') {
        $this->arrSql['trans'][] = "commit,Master:{$this->transName},Current:{$strName}";
        if (((!$strName && !$this->transName)||($this->transName == $strName)) && $this->transTimes > 0){
            !$this->linkID && $this->initConnect(true);
            $result = $this->clsDriverInstance->query($this->linkID,'COMMIT');
            if(!$result){
                $this->error();
                return false;
            }
            $this->arrSql['trans'][] = "commit finish on:{$strName}";
            $this->transTimes = 0;
            $this->transName = '';
        }
        return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     */
    public function rollback() {
        if ($this->transTimes > 0){
            !$this->linkID && $this->initConnect(true);
            $result = $this->clsDriverInstance->query($this->linkID,'ROLLBACK');
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     * 执行一下有结果的SQL
     * @param $strSql
     * @return bool|int
     */
    public function execute($strSql){
        !$this->linkID && $this->initConnect(true);
        $this->queryStr = $strSql;
        $this->queryID && $this->free();//释放前次的查询结果
        $result = $this->clsDriverInstance->query($this->linkID,$strSql);
        if ( false === $result) {
            if ($this->reTest >= 3 || !$this->isReTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->execute($strSql);
            }
        } else {
            $this->numRows = $this->clsDriverInstance->getAffectedRows($this->linkID);
            $this->lastInsID = $this->clsDriverInstance->getInsertId($this->linkID);
            return $this->numRows;
        }
    }

    /**
     * 执行一个没有记录集的SQL
     * @param $strSql
     * @return array|bool
     */
    public function query($strSql){
        !$this->linkID && $this->initConnect(true);
        $this->queryStr = $strSql;
        $this->queryID && $this->free();//释放前次的查询结果
        $this->queryID = $this->clsDriverInstance->query($this->linkID,$strSql);
        if ( false === $this->queryID ) {
            if ($this->reTest >= 3 || !$this->isReTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->query($strSql);
            }
        } else {
            $this->numRows = $this->clsDriverInstance->getNumRows($this->queryID);
            return $this->clsDriverInstance->getAll($this->queryID);
        }
    }

    /**
     * 得到表的所有字段及注释
     * @param $table
     * @return array
     */
    public function getFullFields($table){
        !$this->linkID && $this->initConnect(true);
        return $this->clsDriverInstance->getFullFields($this->linkID,$this->arrConfig['PREFIX'].$table);
    }

    /**
     * 得到表的建表记录
     * @param $table
     * @return array
     */
    public function showCreateTable($table){
        !$this->linkID && $this->initConnect(true);
        return $this->clsDriverInstance->showCreateTable($this->linkID,$this->arrConfig['PREFIX'].$table);
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql() {
        return $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * @return string
     */
    public function getLastInsID() {
        return $this->lastInsID;
    }

    /**
     * 获取返回的影响记录数
     * @return int
     */
    public function getNumRows(){
        return $this->numRows;
    }

    /**
     * 获取最近的错误信息
     * @return string
     */
    public function getError() {
        return $this->strError;
    }

    /**
     * 提取所有的SQL语句。
     * @param string $strType
     * @return array
     */
    public function getAllSql($strType = ''){
        return isset($this->arrSql[$strType])?$this->arrSql[$strType]:$this->arrSql;
    }

    /**
     * 生成SQL的标识，所有的增删修的动作只会生成SQL，而不会运行
     * @return bool
     */
    public function buildSql(){
        $this->bolBuild = true;
        $this->arrSql = Array();
        return true;
    }

    /**
     * 取消生成SQL的标识，生成的SQL会运行
     * @param $type string 取回哪一种SQL的类型
     * @return array
     */
    public function cancelBuildSql($type=null){
        $this->bolBuild = false;
        return $this->getAllSql($type);
    }

    /**
     * 设置锁机制
     * @param $bolLock
     * @return string
     */
    private function parseLock($bolLock = false) {
        return $bolLock ? ' FOR UPDATE ' : '';
    }

    /**
     * set分析
     * @access protected
     * @param array $data
     * @return string
     */
    private function parseSet($data) {
	    $arrSet = Array();
	    foreach ($data as $k=>$v){
            if(is_array($v) && $v[0] == 'exp'){
                $arrSet[] = $this->parseKey($k) . '=' . $v[1];
            }elseif(is_scalar($v) || is_null($v)) {//过滤非标量数据
                $arrSet[] = $this->parseKey($k) . '=' . $this->parseValue($v);
            }
        }
        return ' SET '.implode(',',$arrSet);
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @return string
     */
    private function parseKey($key) {
        return $this->clsDriverInstance->parsekey($key);
    }

    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return array|string
     */
    private function parseValue($value) {
        if(is_string($value)) {
            $value =  '\''.$this->escapeString($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value =  $this->escapeString($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }

    /**
     * SQL指令安全过滤
     * @param $value
     * @return string
     */
    private function escapeString($value){
        return $this->clsDriverInstance->escapeString($value,$this->linkID);
    }

    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    private function parseField($fields) {
        if(is_string($fields) && strpos($fields,',')) {
            $fields = explode(',',$fields);
        }
        if(is_array($fields)) {//支持 'field1'=>'field2' 这样的字段别名定义
            $array = Array();
            foreach ($fields as $k=>$v){
                if(!is_numeric($k)){
                    $array[] = $this->parseKey($k).' AS '.$this->parseKey($v);
                }else{
                    $array[] =  $this->parseKey($v);
                }
            }
            $strFields = implode(',', $array);
        }elseif(is_string($fields) && !empty($fields)) {
            $strFields = $this->parseKey($fields);
        }else{
            $strFields = '*';
        }
        return $strFields;
    }

    /**
     * table分析
     * @access protected
     * @param mixed $tables
     * @return string
     */
    private function parseTable($tables) {
        if(is_array($tables)) {//支持别名定义
	        $tables = $this->parseTrueTable($tables[0]).' '.$tables[1];
        }elseif(is_string($tables)){
	        $tables = $this->parseTrueTable($tables);
        }
        return $tables;
    }

	/**
	 * 返回正真正的数据表名
	 * @param $table
	 * @return string
	 */
    private function parseTrueTable($table){
		if($this->arrConfig['PREFIX'] && stripos($table,$this->arrConfig['PREFIX']) !== 0){
			$table = $this->arrConfig['PREFIX'] . $table;
		}
		return str_replace('@.',$this->arrConfig['PREFIX'],$table);
	}

    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return string
     */
    private function parseWhere($where) {
        if(is_string($where)){//直接使用字符串条件
            return empty($where)?'':' WHERE '.$where;
        }
        $strWhere = '';
        //使用数组表达式
        $strOperate = isset($where['_logic'])?strtoupper($where['_logic']):'';
        if(in_array($strOperate,['AND','OR','XOR'])){//定义逻辑运算规则,OR XOR AND
            $strOperate = ' '. $strOperate . ' ';
            unset($where['_logic']);
        }else{//默认进行 AND 运算
            $strOperate = ' AND ';
        }
        foreach ($where as $k=>$v){
            $strWhere .= '( ';
            $k = trim($k);
            is_numeric($k) && $k  = '_complex';
            if(0 === strpos($k,'_')){// 解析特殊条件表达式
                $strWhere .= $this->parseCustomWhere($k,$v);
            }else{// 查询字段的安全过滤
                if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,@]+$/',$k)){
                    \Spt::halt(['where express error',$k]);
                }
                $bolMulti = is_array($v) &&  isset($v['_multi']);//多条件支持
                if(strpos($k,'|')) {//支持 name|title|nickname 方式定义查询字段
                    $arrTempKey = explode('|',$k);
                    $arrTempStr = Array();
                    foreach ($arrTempKey as $kk=>$vv){//name|title|nickname 每个都是字段key
                        $strValue =  $bolMulti?$v[$kk]:$v;
                        $arrTempStr[] = '('.$this->parseWhereItem($this->parseKey($vv),$strValue).')';
                    }
                    $strWhere .= implode(' OR ',$arrTempStr);
                }elseif(strpos($k,'&')){//支持 name&&title&&nickname 方式定义查询字段
                    $arrTempKey = explode('&',$k);
                    $arrTempStr = Array();
                    foreach ($arrTempKey as $kk=>$vv){//name&title&nickname 每个都是字段key
                        $strValue =  $bolMulti?$v[$kk]:$v;
                        $arrTempStr[] = '('.$this->parseWhereItem($this->parseKey($vv),$strValue).')';
                    }
                    $strWhere .= implode(' AND ',$arrTempStr);
                }else{
                    $strWhere .= $this->parseWhereItem($this->parseKey($k),$v);
                }
            }
            $strWhere .= ' )'. $strOperate;
        }
        $strWhere = substr($strWhere, 0 , -strlen($strOperate));
        return empty($strWhere) ? '' : ' WHERE '.$strWhere;
    }

    /**
     * where子单元分析
     * @param $key
     * @param $val
     * @return string
     */
    private function parseWhereItem($key,$val) {
        if(!is_array($val)) {//值不是数组
            return $key .' = '. $this->parseValue($val);
        }
        $strWhere = '';
        if (!is_string($val[0])){//值的第一个值不是字符串
            $intCount = count($val);
            $strRule = end($val);
            is_array($strRule) && $strRule = $strRule[0];
            $strRule = strtoupper($strRule);
            if(in_array($strRule,['AND','OR','XOR'])) {
                $intCount--;
            }else{
                $strRule = 'AND';
            }
            for($i = 0;$i < $intCount;$i++) {
                $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                if('exp'==strtolower($val[$i][0])) {
                    $strWhere .= '('.$key.' '.$data.') '. $strRule .' ';
                }else{
                    $strWhere .= '('.$this->parseWhereItem($key,$val[$i]).') '. $strRule .' ';
                }
            }
            return substr($strWhere,0,-4);
        }
        //值的第一个值是字符串
        if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT)$/i',$val[0])) { // 比较运算
            return $key.' '.$this->arrComparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
        }
        if(preg_match('/^(NOTLIKE|LIKE)$/i',$val[0])){// 模糊查找
            if(is_array($val[1])) {
                $likeLogic = isset($val[2])?strtoupper($val[2]):'OR';
                if(in_array($likeLogic,array('AND','OR','XOR'))){
                    $likeStr = $this->arrComparison[strtolower($val[0])];
                    $arrLike = Array();
                    foreach ($val[1] as $item){
                        $arrLike[] = $key.' '.$likeStr.' '.$this->parseValue($item);
                    }
                    $strWhere .= '('.implode(' '.$likeLogic.' ',$arrLike).')';
                }
            }else{
                $strWhere .= $key.' '.$this->arrComparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
            }
        }elseif('exp'==strtolower($val[0])){ // 使用表达式
            $strWhere .= ' ('.$key.' '.$val[1].') ';
        }elseif(preg_match('/IN/i',$val[0])){ // IN 运算
            if(isset($val[2]) && 'exp'==$val[2]) {
                $strWhere .= $key.' '.strtoupper($val[0]).' '.$val[1];
            }else{
                (is_numeric($val[1])||is_string($val[1])) && $val[1] = explode(',',$val[1]);
                $zone = implode(',',$this->parseValue($val[1]));
                $strWhere .= $key.' '.strtoupper($val[0]).' ('.$zone.')';
            }
        }elseif(preg_match('/BETWEEN/i',$val[0])){ // BETWEEN运算
            $data = is_string($val[1])? explode(',',$val[1]):$val[1];
            $strWhere .=  ' ('.$key.' '.strtoupper($val[0]).' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]).' )';
        }else{
            \Spt::halt(['parseWhereItem  express error',implode('---',$val)]);
        }
        return $strWhere;
    }

    /**
     * 特殊条件分析
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return string
     */
    private function parseCustomWhere($key,$val) {
        $strWhere = '';
        switch($key) {
            case '_string':// 字符串模式查询条件
                $strWhere = $val;
                break;
            case '_complex':// 复合查询条件
                $strWhere = is_string($val)? $val : substr($this->parseWhere($val),6);
                break;
            case '_query':// 字符串模式查询条件
                parse_str($val,$arrWhere);
                if(isset($arrWhere['_logic'])) {
                    $strOperate = ' '.strtoupper($arrWhere['_logic']).' ';
                    unset($arrWhere['_logic']);
                }else{
                    $strOperate = ' AND ';
                }
                $arrTemp = Array();
                foreach ($arrWhere as $field=>$data){
                    $arrTemp[] = $this->parseKey($field).' = '.$this->parseValue($data);
                }
                $strWhere = implode($strOperate,$arrTemp);
                break;
            default:
                \Spt::halt(['parseCustomWhere express error',$key]);
        }
        return $strWhere;
    }

    /**
     * join分析
     * @access protected
     * @param array $join
     * @return string
     */
    private function parseJoin($join) {
        if (empty($join)){
            return '';
        }
        $strJoin = '';
        if(is_array($join)) {
            foreach ($join as $_join){
                if(false !== stripos($_join,'JOIN')){
                    $strJoin .= ' '.$_join;
                }else{
                    $strJoin .= ' LEFT JOIN ' .$_join;
                }
            }
        }else{
            $strJoin .= ' LEFT JOIN ' . $join;
        }
        return $strJoin;
    }

    /**
     * order分析
     * @access private
     * @param mixed $order
     * @return string
     */
    private function parseOrder($order) {
        if(is_array($order)) {
            $arrTemp = Array();
            foreach ($order as $key=>$val){
                if(is_numeric($key)) {
                    $arrTemp[] = $this->parseKey($val);
                }else{
                    $arrTemp[] = $this->parseKey($key).' '.$val;
                }
            }
            $order = implode(',',$arrTemp);
        }
        return !empty($order)?' ORDER BY '.$order:'';
    }

    /**
     * group分析
     * @access private
     * @param mixed $group
     * @return string
     */
    private function parseGroup($group) {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }

    /**
     * having分析
     * @access private
     * @param string $having
     * @return string
     */
    private function parseHaving($having) {
        return !empty($having)? ' HAVING ' . $having : '';
    }

    /**
     * comment分析
     * @access private
     * @param string $comment
     * @return string
     */
    private function parseComment($comment) {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * distinct分析
     * @access private
     * @param mixed $distinct
     * @return string
     */
    private function parseDistinct($distinct) {
        return !empty($distinct)? ' DISTINCT ' : '';
    }

    /**
     * union分析
     * @access private
     * @param mixed $union
     * @return string
     */
    private function parseUnion($union) {
        if(empty($union)){
            return '';
        }
        if(isset($union['_all'])) {
            $str = 'UNION ALL ';
            unset($union['_all']);
        }else{
            $str = 'UNION ';
        }
        $arrSql = Array();
        foreach ($union as $u){
            $arrSql[] = $str . (is_array($u)?$this->buildSelectSql($u):$u);
        }
        return implode(' ',$arrSql);
    }

    /**
     * 生成查询SQL
     * @param array $options 表达式
     * @return string
     */
	private function buildSelectSql($options = []) {
        $this->initConnect(false);
        if(isset($options['page'])){// 根据页数计算limit
            if(strpos($options['page'],',')) {
                list($page,$listRows) = explode(',',$options['page']);
            }else{
                $page = $options['page'];
            }
            $page = $page?$page:1;
            $listRows = isset($listRows)?$listRows:(is_numeric($options['limit'])?$options['limit']:20);
            $offset = $listRows*((int)$page-1);
            $options['limit'] = $offset.','.$listRows;
        }
        $strSql = $this->parseSql($options);
        $strSql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);
        return str_ireplace('@.',$this->arrConfig['PREFIX'],$strSql);
    }

    /**
     * 替换SQL语句中表达式
     * @param array $options 表达式
     * @return string
     */
    private function parseSql($options = []){
        return str_replace(
            ['%TABLE%','%DISTINCT%','%FIELD%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%','%UNION%','%COMMENT%'],
            [
                $this->parseTable($options['table']),
                $this->parseDistinct((isset($options['distinct']) && $options['distinct'])?$options['distinct']:false),
                $this->parseField((isset($options['field']) && $options['field'])?$options['field']:'*'),
                $this->parseJoin((isset($options['join']) && $options['join'])?$options['join']:''),
                $this->parseWhere((isset($options['where']) && $options['where'])?$options['where']:''),
                $this->parseGroup((isset($options['group']) && $options['group'])?$options['group']:''),
                $this->parseHaving((isset($options['having']) && $options['having'])?$options['having']:''),
                $this->parseOrder((isset($options['order']) && $options['order'])?$options['order']:''),
                $this->parseLimit((isset($options['limit']) && $options['limit'])?$options['limit']:''),
                $this->parseUnion((isset($options['union']) && $options['union'])?$options['union']:''),
                $this->parseComment((isset($options['comment']) && $options['comment'])?$options['comment']:'')
            ],
            'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%COMMENT%'
        );
    }

    /**
     * @param $limit
     * @return string
     */
    private function parseLimit($limit){
        return $this->clsDriverInstance->parseLimit($limit);
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     */
    private function error(){
        $strError = $this->clsDriverInstance->error($this->linkID);
        $this->strError .= $this->queryStr;
        $this->strError .= "\n [ 错误信息 ] :" . $strError;
        \Spt::$arrConfig['DEBUG'] && \Spt::halt(['sql error',$this->strError]);
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free() {
        $this->clsDriverInstance->free($this->queryID);
        $this->queryID = null;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close() {
        $this->clsDriverInstance->close($this->linkID);
        $this->linkID = null;
        $this->reTest = 0;
    }

    /**
     * 析构方法
     */
    public function __destruct(){
        if ($this->queryID){
            $this->free();
        }
        $this->close();
    }
}