<?php
namespace Spartan\Lib;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Validation {
    private $arrConfig = [];
    /** @var null|Request  */
    private $clsRequest = null;//
    private $arrFun = [];//验证规则
    private $arrError = [];//错误信息
    private $arrResult = [];//函数比较的结果值

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
        $this->clsRequest = Request::instance($_arrConfig);
        isset($_arrConfig['reset']) && $_arrConfig['reset'] && $this->reset();
        unset($_arrConfig['reset']);
        $this->arrConfig = $_arrConfig;
        $this->arrFun = Array(
            //'email'=>$this->isEmail(),
        );
    }

    /**
     * 重新初始化类
     */
    public function reset(){
        unset($this->arrConfig,$this->arrServer,$this->arrPost,$this->arrGet);
    }

    /**
     * 设置一个变量值
     * @param $name
     * @param string $value
     * @return $this
     */
    public function setValue($name,$value=''){
        if (is_array($name)){
            //$this->arrRequest = array_merge($this->arrRequest,$name);
        }else{
            //$this->arrRequest[$name] = $value;
        }
        return $this;
    }

    /**
     * 添加自定义规则
     * @param string $name //规则名称
     * @param Function|null //处理函数
     * @return $this
     */
    public function setRules($name,$function=null){
        if (is_array($name)){
            $this->arrFun = array_merge($this->arrFun,$name);
        }else{
            $this->arrFun[$name] = $function;
        }
        return $this;
    }

    /**
     * 开始验证
     * @param $_arrRule array
     * @return boolean
     * 'name'=>Array('required','length',[2,10],'请输入登录'),
     * 'email'=>Array('without','email',['$phone'],'请输入邮箱'),
     * 'phone'=>Array('without','phone',['$email'],'请输入手机'),
     * 'real_name'=>Array('null','length',[2,10],'请输入真实姓名'),
     * 'password'=>Array('null','length',[2,10],'请输入密码'),
     * 're_password'=>Array('null','same',['$password'],'请输入名称'),
     */
    public function authorize($_arrRule){
        foreach($_arrRule as $k => $v) {
            $strValue = $this->clsRequest->get($k);
            list($strCondition,$strFun,$arrValue,$strMsg) = $v;
            $arrValue = $this->parseValue($arrValue);//解析变量
            $arrMsg = Array(call_user_func('sprintf',$strMsg,$arrValue),$k,$strValue);
            !$strCondition && $strCondition = 'null';
            if (!in_array($strCondition,['required','without','null'])){
                $this->arrError[] = Array("变量{$k}中规则{$strCondition}未支持。",$k,$strValue);
                return false;
            }
            //如果为必填写即跳过
            if (!$strValue){
                if ($strCondition == 'required'){
                    $this->arrError[] = $arrMsg;
                }elseif ($strCondition == 'null') {//可以为空的，就跳过

                }elseif ($strCondition == 'without'){//或的判断

                }
                continue;
            }
            //下面解析所有的函数，得到一个boolean值，判断是否通过
            $arrFun = explode('|',$strFun);
            $bolResult = false;//默认不通过
            foreach ($arrFun as $item) {//Or操作
                $bolOrResult = false;
                $arrItem = explode('&',$item);//And操作
                foreach ($arrItem as $fun){
                    $strLeft = substr($fun,0,1);
                    if ($strLeft === '!'){
                        $fun = substr($fun,1);
                    }else{
                        $strLeft = '';
                    }
                    $bolAndResult = false;
                    //检查该函数的值
                    if (method_exists($this,$fun)){
                        $bolAndResult = $this->$fun($strValue,$arrValue);
                    }elseif (isset($this->arrFun[$fun]) && $this->arrFun[$fun]){
                        $bolAndResult = $this->arrFun[$fun]($strValue,$arrValue);
                    }
                    $strLeft && $bolAndResult = !$bolAndResult;
                    //对结果算进行判断
                    if (!$bolAndResult){
                        $bolOrResult = false;
                        break;//这里是and判断，如果有一个为false，就整个都为false
                    }
                }
                if ($bolOrResult){
                    $bolResult = true;
                    break;//最后的是Or操作，只要有一个true，整个都是true
                }
            }
            if (!$bolResult){//验证没有通过
                $this->arrError[] = $arrMsg;
                return false;
            }
        }
        return true;
    }

    /**
     * rule的第2个参数，如果有变量，就解释
     * @param $arrValue array
     * @return array
     */
    public function parseValue($arrValue = []){
        !is_array($arrValue) && $arrValue = [$arrValue];
        foreach ($arrValue as &$value){
            if (substr($value,0,1) === '$'){
                $value = $this->clsRequest->input(substr($value,1));
            }
        }
        return $arrValue;
    }


    //验证的字段必须为 yes、 on、 1、或 true
    private function accepted(){

    }

    //验证的字段必须完全是字母的字符。
    private function alpha(){

    }

    //验证的字段可能具有字母、数字、破折号（ - ）以及下划线（ _ ）。
    private function alpha_dash(){

    }

    //验证的字段必须完全是字母、数字。
    private function alpha_num(){

    }

    private function date(){

    }

    //验证的字段的大小必须在给定的 min 和 max 之间。字符串、数字、数组或是文件大小的计算方式都用 size 方法进行评估。
    private function between(){

    }
    //验证的字段必须能够被转换为布尔值。可接受的参数为 true、false、1、0、"1" 以及 "0"。
    private function boolean(){

    }
    private function required($data,$value){

    }

    private function required_without(){

    }

    private function nullable($data,$value){

    }

    //***************************************************以下是$arrOptions['where']中直接使用组数的函数
    /**
     * $options['where'] = Array(
     *      'id'=>Array('in',xxxxx)
     * );
     * 使用了in，把第1个位置的数组转字段串
     * @param $arrValue
     * @return bool
     */
    private function inAction(&$arrValue){
        !is_string($arrValue[1]) && $arrValue[1] = strval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('between',2,5)
     * );
     * 使用了between，判断第1和第2个元素转为数字
     * @param $arrValue
     * @return bool
     */
    private function betweenAction(&$arrValue){
        if (!is_array($arrValue[1]) || count($arrValue[1]) != 2){
            return false;
        }
        $arrValue[1][0] = intval($arrValue[1][0]);
        $arrValue[1][1] = intval($arrValue[1][1]);
        return is_numeric($arrValue[1][0]) && is_numeric($arrValue[1][1])?true:false;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('exp','id+1')
     * );
     * 使用了exp，第二个元素转为字段串原样输出
     * @param $arrValue
     * @return bool
     */
    private function expAction(&$arrValue){
        $arrValue[1] = strval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('gt',1)
     * );
     * 使用了gt，第二个元素转为数字
     * @param $arrValue
     * @return bool
     */
    private function gtAction(&$arrValue){
        $arrValue[1] = intval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('gt',1)
     * );
     * 使用了egt，第二个元素转为数字，因为如果是需要 等于 的话，可以直接 'id'=>1,而不需要数组
     * @param $arrValue
     * @return bool
     */
    private function egtAction(&$arrValue){
        $arrValue[1] = intval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('lt',1)
     * );
     * 使用了lt，第二个元素转为数字
     * @param $arrValue
     * @return bool
     */
    private function ltAction(&$arrValue){
        $arrValue[1] = intval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('elt',1)
     * );
     * 使用了elt，第二个元素转为数字，因为如果是需要 等于 的话，可以直接 'id'=>1,而不需要数组
     * @param $arrValue
     * @return bool
     */
    private function eltAction(&$arrValue){
        $arrValue[1] = intval($arrValue[1]);
        return true;
    }

    /**
     * $options['where'] = Array(
     *      'id'=>Array('neq',1)
     * );
     * 使用了neq，第二个元素可为数字也可以为字段串
     * @param $arrValue
     * @return bool
     */
    private function neqAction(&$arrValue){
        return true;
    }

    //************************************************以下是arrCondition要求的变量，符合要求的才加入where
    /**
     * $arrCondition = Array(
     *      id'=>Array('int','notNull'),
     * 不为空即可，默认
     * @param $data
     * @return bool
     */
    private function notNull($data){
        return !!$data;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','gt',10),
     * 大于10
     * @param $data
     * @param $value
     * @return bool
     */
    private function gt($data,$value){
        return $data > $value;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','egt',10),
     * 大于等于10
     * @param $data
     * @param $value
     * @return bool
     */
    private function egt($data,$value){
        return $data >= $value;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','eq',10),
     * 等于10
     * @param $data
     * @param $value
     * @return bool
     */
    private function eqAction($data,$value){
        return true;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','lt',10),
     * 小于10
     * @param $data
     * @param $value
     * @return bool
     */
    private function lt($data,$value){
        return $data < $value;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','elt',10),
     * 小于等于$value
     * @param $data
     * @param $value
     * @return bool
     */
    private function elt($data,$value){
        return $data <= $value;
    }

    /**
     * $arrCondition = Array(
     *      id'=>Array('int','length',10),
     * 长度等于$value
     * @param $data
     * @param $value
     * @return bool
     */
    private function length($data,$value){
        return mb_strlen($data,'utf-8') > $value;
    }
}