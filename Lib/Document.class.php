<?php
namespace Spartan\Lib;
use Spartan\Core\Model;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Document {
    private $arrConfig = [];

    public $docTag = array(
        "@example" => "例子",
        "@return" => "返回值",
        "@param" => "参数",
        "@version" => "版本信息",
        "@throws" => "抛出的错误异常",
        "@title" => "标题",
        "@desc" => "描述",
    );

    public $typeMaps = array(
        'string' => '字符串',
        'int' => '整型',
        'float' => '浮点型',
        'boolean' => '布尔型',
        'date' => '日期',
        'array' => '数组',
        'fixed' => '固定值',
        'enum' => '枚举类型',
        'object' => '对象',
    );

    public function __construct($_arrConfig = []){

        $this->arrConfig = $_arrConfig;
    }

    public function create(){
        $arrClass = $this->loadDirFile($this->arrConfig['DOC_ROOT']);
        $arrResult = $arrData = Array();
        foreach ($arrClass as $val){
            $arrTempMethods = $this->getMethods($val,'public');
            $arrMethods = [];
            foreach($arrTempMethods as $k=>$v){
                $arrMethodInfo = $this->Item($val,$v);
                $arrMethodInfo['name'] = $v;
                $arrMethods[$k] = $arrMethodInfo;
            }
            $data['title'] = $this->Ctitle($val);
            $data['class'] = $val;
            $data['method'] = $arrMethods;
            $arrResult[] = $data;
        }
        print_r($arrResult);
    }
    /**
     * 加载某一目录下所有文件，预加载
     * @param $strDir
     * @param $ext
     * @return array 返回所有已经加载的文件类名
     */
    private function loadDirFile($strDir,$ext = CLASS_EXT){
        $arrDir = is_array($strDir)?$strDir:explode(',',$strDir);
        $intExtLen = strlen($ext);
        $arrFilesName = $arrNextPath = [];
        foreach($arrDir as $dir){
            $arrCore = new \RecursiveDirectoryIterator(rtrim($dir,NS).NS);
            foreach($arrCore as $objFile){
                $strFile = $objFile->getPathname();
                if ($objFile->isDir()){
                    if (!in_array($objFile->getFilename(),['.','..'])){
                        $arrNextPath[] = $strFile;
                    };
                }else{
                    if (substr($strFile,0 - $intExtLen) == $ext){
                        include_once($strFile);
                        $arrFilesName[] = str_ireplace($ext,'',str_ireplace(APP_ROOT,'',$strFile));
                    }
                }
            }
        }
        $arrNextPath && $arrFilesName = array_merge($arrFilesName,self::loadDirFile($arrNextPath,$ext));
        return $arrFilesName;
    }

    private function Item($class,$method){
        $res = $this->getMethod($class,$method);
        $item = $this->getData($res);
        return [
            'title'=>isset($item['title'])&&!empty($item['title'])?$item['title']:'未配置标题',
            'desc'=>isset($item['desc'])&&!empty($item['desc'])?$item['desc']:'未配置描述信息',
            'params'=>isset($item['params'])&&!empty($item['params'])?$item['params']:[],
            'returns'=>isset($item['returns'])&&!empty($item['returns'])?$item['returns']:[],
        ];
    }

    /**
     * 获取类名称
     * @param $class
     * @return mixed
     */
    public function Ctitle($class){
        $res = $this->getClass($class);
        $item = $this->getData($res);
        return $item['title'];
    }

    /**
     * 获取类中非继承方法和重写方法
     * 只获取在本类中声明的方法，包含重写的父类方法，其他继承自父类但未重写的，不获取
     * 例
     * class A{
     *      public function a1(){}
     *      public function a2(){}
     * }
     * class B extends A{
     *      public function b1(){}
     *      public function a1(){}
     * }
     * getMethods('B')返回方法名b1和a1，a2虽然被B继承了，但未重写，故不返回
     * @param string $strClassName 类名
     * @param string $strAccess public or protected  or private or final 方法的访问权限
     * @return array(access)  or array($strAccess) 返回数组，如果第二个参数有效，
     * 则返回以方法名为key，访问权限为value的数组
     * @see  使用了命名空间，故在new 时类前加反斜线；如果此此函数不是作为类中方法使用，可能由于权限问题，
     *   只能获得public方法
     */
    public function getMethods($strClassName,$strAccess = ''){
        $clsReflectionClass = new \ReflectionClass($strClassName);
        $arrMethods = $clsReflectionClass->getMethods();//某个类的全部方法
        $arrReturn = Array();
        foreach($arrMethods as $value){
            if ($value->class != $strClassName){
                continue;
            }
            $objMethodAccess = new \ReflectionMethod($strClassName,$value->name);
            switch($strAccess){
                case 'public':
                    $objMethodAccess->isPublic() && $arrReturn['public'][] = $value->name;
                    break;
                case 'protected':
                    $objMethodAccess->isProtected() && $arrReturn['protected'][] = $value->name;
                    break;
                case 'private':
                    $objMethodAccess->isPrivate() && $arrReturn['private'][] = $value->name;
                    break;
                case 'final':
                    $objMethodAccess->isFinal() && $arrReturn['final'][] = $value->name;
                    break;
                default:
                    $arrReturn['other'][] = $value->name;
            }
        }
        return ($strAccess && isset($arrReturn[$strAccess]))?$arrReturn[$strAccess]:$arrReturn;
    }

    private function getData($res){
        $title = $description =  '';
        $param = $params = $return = $returns = array();
        foreach($res as $key=>$val){
            if($key=='@title'){
                $title=$val;
            }
            if($key=='@desc'){
                $description=implode("<br>",(array)json_decode($val));
            }
            if($key=='@param'){
                $param=$val;
            }
            if($key=='@return'){
                $return=$val;
            }
        }
        //过滤传入参数
        foreach ($param as $key => $rule) {
            $rule=(array)json_decode($rule);
            $name = $rule['name'];
            if (!isset($rule['type'])) {
                $rule['type'] = 'string';
            }
            $type = isset($this->typeMaps[$rule['type']]) ? $this->typeMaps[$rule['type']] : $rule['type'];
            $require = isset($rule['required']) && $rule['required'] ? '<font color="red">必须</font>' : '可选';
            $default = isset($rule['default']) ? $rule['default'] : '';
            if ($default === NULL) {
                $default = 'NULL';
            } else if (is_array($default)) {
                $default = json_encode($default);
            } else if (!is_string($default)) {
                $default = var_export($default, true);
            }
            $desc = isset($rule['desc']) ? trim($rule['desc']) : '';
            $params[]=array('name'=>$name,'type'=>$type,'require'=>$require,'default'=>$default,'desc'=>$desc);
        }
        //过滤返回参数
        foreach ($return as $item) {
            $item=(array)json_decode($item);
            $type = $item['type'];
            $name = "";
            $required = $item['required']?'是':'否';
            $detail = $item['desc'];
            for($i=1;$i<$item['level'];$i++){
                $name .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            }
            $name .= $item['name'];
            $returns[] = array('name'=>$name,'type'=>$type,'required'=>$required,'detail'=>$detail);
        }
        return array('title'=>$title,'desc'=>$description,'params'=>$params,'returns'=>$returns);
    }

    public function getClass($class)
    {
        $rc = new \ReflectionClass($class);
        $res = explode("\n", $rc->getDocComment());
        $res = $this->processor($res);
        return $res;
    }
    public function getMethod($class,$method)
    {
        $rm = new \ReflectionMethod($class, $method);
        $res = explode("\n", $rm->getDocComment());
        $res = $this->processor($res);
        return $res;
    }
    /**
     * @param $res
     * @return array|bool
     * @example start
     * @title 获取产品
     * @desc  {"0":"系统繁忙，此时请开发者稍候再试","1":"请求成功","2":"不合法的凭证类型"}
     * @throws {"-1":"系统繁忙，此时请开发者稍候再试","0":"请求成功","40002":"不合法的凭证类型"}
     * @example http://apis.juhe.cn/idcard/index?key=您申请的KEY&cardno=330326198903081211
     * @param {"name":"id","type":"string","required":true,"desc":"商品ID"}
     * @param {"name":"name","type":"string","required":true,"desc":"商品名称"}
     * @return {"name":"name","type":"string","required":true,"desc":"商品名称"}
     * @example end
     */
    private function processor($res)
    {
        $result = array();
        if (is_array($res)) {
            foreach ($res as $v) {
                $pos = 0;
                $content = "";
                preg_match("/@[a-z]*/i", $v, $tag);
                if (isset($tag[0]) && array_key_exists($tag[0], $this->docTag)) {
                    $pos = stripos($v, $tag[0]) + strlen($tag[0]);
                    if ($pos > 0) {
                        $content = trim(substr($v, $pos));
                    }
                    if ($content && ($tag[0]=='@param' || $tag[0]=='@return')) {
                        $result[$tag[0]][] = $content;
                    }elseif($content){
                        $result[$tag[0]] = $content;
                    }
                }
            }
            return $result;
        } else {
            return false;
        }
    }

}