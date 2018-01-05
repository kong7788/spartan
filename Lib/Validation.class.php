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
        !isset($_arrConfig['bail']) && $_arrConfig['bail'] = true;
        $this->arrConfig = $_arrConfig;
    }

    /**
     * 重新初始化类
     */
    public function reset(){
        unset($this->arrConfig,$this->arrFun,$this->arrError,$this->arrResult);
    }

    /**
     * @param $name
     * @param $value
     */
    public function setConfig($name,$value){
        $this->arrConfig[$name] = $value;
    }
    /**
     * 返回所有的已验证结果
     * @param $name string
     * @return array
     */
    public function allResult($name){
        return isset($this->arrResult[$name])?$this->arrResult[$name]:$this->arrResult;
    }

    /**
     * 返回所有错误
     * @return array
     */
    public function allError(){
        return $this->arrError;
    }

    /**
     * 最后的错误
     * @return array
     */
    public function error(){
        return $this->arrError[0];
    }

    /**
     * 设置一个变量值
     * @param $name
     * @param string $value
     * @return $this
     */
    public function setValue($name,$value=''){
        $this->clsRequest->setValue($name,$value);
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
     * 'email'=>Array('without','email',['phone'],'请输入邮箱'),
     * 'phone'=>Array('without','phone',['email'],'请输入手机'),
     * 'real_name'=>Array('null','length',[2,10],'请输入真实姓名'),
     * 'password'=>Array('null','length',[2,10],'请输入密码'),
     * 're_password'=>Array('null','same',['$password'],'请输入名称'),
     */
    public function authorize($_arrRule){
        foreach($_arrRule as $k => $v) {
            $strValue = $this->clsRequest->input($k);
            list($strCondition,$strFun,$arrValue,$strMsg) = $v;
            $arrValue = $this->parseValue($arrValue);//解析变量
            $arrMsg = Array(call_user_func('sprintf',$strMsg,$arrValue),$k,$strValue);
            !$strCondition && $strCondition = 'null';
            if (!in_array($strCondition,['required','without','null'])){
                $this->arrError[] = Array("变量{$k}中规则{$strCondition}未支持。",$k,$strValue);
                return false;
            }
            //如果为必填写即跳过
            if (is_null($strValue) || $strValue == ''){
                if ($strCondition == 'required'){
                    $this->arrError[] = $arrMsg;
                    $this->arrResult[$k] = false;
                }elseif ($strCondition == 'null') {//可以为空的
                    $this->arrResult[$k] = true;
                }
            }else{
                //下面解析所有的函数，得到一个boolean值，判断是否通过
                //$strFun = length&num|length&word
                $arrFun = explode('|',$strFun);
                $this->arrResult[$k] = false;//默认不通过
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
                            $bolAndResult = $this->$fun($strValue,isset($arrValue[$fun])?$arrValue[$fun]:$arrValue);
                        }elseif (isset($this->arrFun[$fun]) && $this->arrFun[$fun]){
                            $bolAndResult = $this->arrFun[$fun]($strValue,isset($arrValue[$fun])?$arrValue[$fun]:$arrValue);
                        }
                        $strLeft && $bolAndResult = !$bolAndResult;
                        //对结果算进行判断
                        if (!$bolAndResult){
                            $bolOrResult = false;
                            break;//这里是and判断，如果有一个为false，就整个都为false
                        }
                    }
                    if ($bolOrResult){
                        $this->arrResult[$k] = true;
                        break;//最后的是Or操作，只要有一个true，整个都是true
                    }
                }
                //得到结果后，做最后的判断
                if ($strCondition == 'without'){
                    if (!$this->arrResult[$k] && isset($this->arrResult[$arrValue[0]]) && !$this->arrResult[$arrValue[0]]){
                        $this->arrError[] = $arrMsg;
                        return false;
                    }
                }else{
                    if (!$this->arrResult[$k]){//验证没有通过
                        $this->arrError[] = $arrMsg;
                        return false;
                    }
                }
                if (isset($this->arrConfig['bail']) && $this->arrConfig['bail'] == true && $this->arrError){
                    return false;
                }
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
            if (is_array($value)){
                foreach ($value as &$item){
                    if (substr($item,0,1) === '$'){
                        $item = $this->clsRequest->input(substr($item,1));
                    }
                }
                unset($item);
            }else{
                if (substr($value,0,1) === '$'){
                    $value = $this->clsRequest->input(substr($value,1));
                }
            }
        }
        unset($value);
        return $arrValue;
    }

    /**
     * 长度在某一范围内
     * @param $value
     * @param $arrValue array
     * @return bool,是否符合，false为不符合
     */
    private function length($value,$arrValue){
        $intLength = mb_strlen($value,'utf-8');
        if ($intLength < $arrValue[0]){
            return false;
        }
        if (isset($arrValue[1]) && $arrValue[1] && $intLength > $arrValue[1]){
            return false;
        }
        return true;
    }

    /**
     * 判断是否为邮箱
     * @param $value
     * @return bool
     */
    private function email($value){
        return filter_var($value, FILTER_VALIDATE_EMAIL)?true:false;
    }

    /**
     * 判断是否为手机号码
     * @param $value
     * @return bool
     */
    private function phone($value){
        $strPreg = "/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * 判断和某个变量是否相等
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function same($value,$arrValue){
        return $value == $arrValue[0];
    }

    /**
     * 验证的字段必须为 yes、 on、 1、或 true
     * @param $value
     * @return bool
     */
    private function accepted($value){
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        return $value == null?false:$value;
    }

    /**
     * 验证的字段必须是指定长度的数字
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function num($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[0-9]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * 验证的字段必须是指定长度的字母
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function alpha($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[A-Za-z]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * 验证的字段可能具有字母、数字、破折号（ - ）以及下划线（ _ ）。
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function alpha_dash($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[A-Za-z-_]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * 验证的字段必须完全是字母、数字。
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function alpha_num($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[A-Za-z0-9]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * 验证的字段必须完全是字母、数字、下划线。
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function alpha_num_dash($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[A-Za-z0-9-_]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?true:false;
    }

    /**
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function date($value,$arrValue){

        return true;
    }

    /**
     * 验证的字段的大小必须在给定的 min 和 max 之间。数字、数组。
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function between($value,$arrValue){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 0;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = 0;
        if (is_array($value)){
            $value = count($value);
        }elseif (!is_numeric($value)){
            return false;
        }
        return $arrValue[0] <= $value && $value <= $arrValue[1];
    }

    /**
     * 验证的字段必须能够被转换为布尔值
     * @param $value
     * @return bool
     */
    private function boolean($value){
        return (bool)$value;
    }

    /**
     * 验证是否为身份证号码
     * @param $value
     * @return bool
     */
    private function idcard($value){
        if (mb_strlen($value, 'UTF-8') != 18){
            return false;
        }
        $wi = [7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2];//加权因子
        $ai = ['1','0','X','9','8','7','6','5','4','3','2'];//校验码串
        $sigma = 0;//按顺序循环处理前17位
        for($i = 0;$i < 17;$i++) {
            $b = (int)$value{$i};//提取前17位的其中一位，并将变量类型转为实数
            $w = $wi[$i];//提取相应的加权因子
            $sigma += $b * $w;//把从身份证号码中提取的一位数字和加权因子相乘，并累加
        }
        $sNumber = $sigma % 11;//计算序号
        if($value{17} == $ai[$sNumber]){//按照序号从校验码串中提取相应的字符。
            return true;
        }else{
            return false;
        }
    }

    /**
     * 验证是否中文
     * @param $value
     * @return bool
     */
    private function chinese($value){
        (!isset($arrValue[0]) && $arrValue[0]) && $arrValue[0] = 1;
        (!isset($arrValue[1]) && $arrValue[1]) && $arrValue[1] = '';
        $strPreg = "/^[^\x80-\xff]{{$arrValue[0]},{$arrValue[1]}}$/";
        return preg_match($strPreg, $value)?false:true;
    }

    /**
     * 验证手机浏览器
     * @param $value
     * @return bool
     */
    private function mobile_agent($value){
        return preg_match('/(Phone|iPad|iPod|Android|ios|SymbianOS|mobile)/i',$value)?true:false;
    }

    /**
     * 判断是否在一个范围内
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function in($value,$arrValue){
        !is_array($arrValue) && $arrValue = [$arrValue];
        return in_array($value,$arrValue)?true:false;
    }

    /**
     * 验证是否IP地址
     * @param $value
     * @param $arrValue
     * FILTER_FLAG_IPV4 - 要求值是合法的 IPv4 IP（比如 255.255.255.255）
     * FILTER_FLAG_IPV6 - 要求值是合法的 IPv6 IP（比如	2001:0db8:85a3:08d3:1319:8a2e:0370:7334）
     * FILTER_FLAG_NO_PRIV_RANGE - 要求值是 RFC 指定的私域 IP （比如 192.168.0.1）
     * FILTER_FLAG_NO_RES_RANGE - 要求值不在保留的 IP 范围内。该标志接受 IPV4 和 IPV6 值。
     * @return bool
     */
    private function ip($value,$arrValue){
        !isset($arrValue[0]) && $arrValue[0] = null;
        if ($arrValue[0] == 'v4'){
            $arrValue[0] = FILTER_FLAG_IPV4;
        }elseif ($arrValue[0] == 'v6'){
            $arrValue[0] = FILTER_FLAG_IPV6;
        }elseif ($arrValue[0] == 'private'){
            $arrValue[0] = FILTER_FLAG_NO_PRIV_RANGE;
        }
        return filter_var($value, FILTER_VALIDATE_IP, $arrValue[0])?true:false;
    }

    /**
     * 验证是否URL地址
     * @param $value
     * @param $arrValue
     * FILTER_FLAG_SCHEME_REQUIRED - 要求 URL 是 RFC 兼容 URL。（比如：http://example）
     * FILTER_FLAG_HOST_REQUIRED - 要求 URL 包含主机名（http://www.example.com）
     * FILTER_FLAG_PATH_REQUIRED - 要求 URL 在主机名后存在路径（比如：eg.com/example1/）
     * FILTER_FLAG_QUERY_REQUIRED - 要求 URL 存在查询字符串（比如："eg.php?age=37"）
     * @return bool
     */
    private function url($value,$arrValue){
        !isset($arrValue[0]) && $arrValue[0] = null;
        if ($arrValue[0] == 'host'){
            $arrValue[0] = FILTER_FLAG_HOST_REQUIRED;
        }elseif ($arrValue[0] == 'path'){
            $arrValue[0] = FILTER_FLAG_PATH_REQUIRED;
        }elseif ($arrValue[0] == 'query'){
            $arrValue[0] = FILTER_FLAG_QUERY_REQUIRED;
        }
        return filter_var($value, FILTER_VALIDATE_URL, $arrValue[0])?true:false;
    }

    /**
     * 是否大于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function gt($value,$arrValue){
        $arrValue[0] = intval(isset($arrValue[0])?$arrValue[0]:0);
        return $value > $arrValue[0];
    }

    /**
     * 是否大于等于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function egt($value,$arrValue){
        $arrValue[0] = intval(isset($arrValue[0])?$arrValue[0]:0);
        return $value >= $arrValue[0];
    }

    /**
     * 是否小于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function lt($value,$arrValue){
        $arrValue[0] = intval(isset($arrValue[0])?$arrValue[0]:0);
        return $value < $arrValue[0];
    }

    /**
     * 是否小于等于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function elt($value,$arrValue){
        $arrValue[0] = intval(isset($arrValue[0])?$arrValue[0]:0);
        return $value <= $arrValue[0];
    }

    /**
     * 是否不等于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function neq($value,$arrValue){
        $arrValue[0] = isset($arrValue[0])?$arrValue[0]:'';
        return $value != $arrValue[0];
    }

    /**
     * 是否不恒等
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function nheq($value,$arrValue){
        $arrValue[0] = isset($arrValue[0])?$arrValue[0]:'';
        return $value !== $arrValue[0];
    }

    /**
     * 是否等于
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function eq($value,$arrValue){
        $arrValue[0] = isset($arrValue[0])?$arrValue[0]:'';
        return $value == $arrValue[0];
    }

    /**
     * 是否恒等
     * @param $value
     * @param $arrValue
     * @return bool
     */
    private function heq($value,$arrValue){
        $arrValue[0] = isset($arrValue[0])?$arrValue[0]:'';
        return $value === $arrValue[0];
    }

}