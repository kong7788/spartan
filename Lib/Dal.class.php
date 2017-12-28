<?php
namespace Spartan\Lib;
use Spartan\Core\Model;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Dal extends Model {

    /**
     * 读取单一记录，返回一个记录的Array();
     *
     * @param \stdClass|string $tableNameOrTableClass
     * @param array $options
     * @return mixed
     */
    public function find($tableNameOrTableClass,$options = []){
        $clsTable = $this->getDalClass($tableNameOrTableClass);
        $options = $this->parseCondition($clsTable->arrCondition,$options);
        return $this->Db()->find([$clsTable->strTable,$clsTable->strAlias],$options);
    }

    /**
     * 读取一个列表记录，返回一个列表的Array();
     * @param \stdClass|string $tableNameOrTableClass
     * @param array $options
     * @param boolean $bolNeedCount 是否需要总记录数
     * @return mixed
     */
    public function select($tableNameOrTableClass,$options = [],$bolNeedCount = false){
        $clsTable = $this->getDalClass($tableNameOrTableClass);
        $options = $this->parseCondition($clsTable->arrCondition,$options);
        $options = $this->commonVariable($options);
        $arrResult = Array([],0);
        $arrResult[0] = $this->Db()->select([$clsTable->strTable,$clsTable->strAlias],$options);
        if ($bolNeedCount){//如果需要总条数
            $arrResult[1] = $this->Db()->find(
                [$clsTable->strTable,$clsTable->strAlias],
                $options,'count('.($clsTable->strPrimaryKey?$clsTable->strPrimaryKey:'*').')'
            );
            return $arrResult;
        }else{
            return $arrResult[0];
        }
    }

    /**
     * 删除记录。
     * @param \stdClass|string $tableNameOrTableClass
     * @param array $options
     * @return mixed
     */
    public function delete($tableNameOrTableClass,$options = []){
        $clsTable = $this->getDalClass($tableNameOrTableClass);
        return $this->Db()->delete($clsTable->strTable,$options);
    }

    /**
     * @param \stdClass|string $tableNameOrTableClass
     * @param array $options
     * @param array $arrData
     * @return mixed
     */
    public function update($tableNameOrTableClass, $arrData = [], $options = []){
        $clsTable = $this->getDalClass($tableNameOrTableClass);
        $strPrimaryKey = $this->getData($clsTable->arrPrimaryKey);


        if (!$strPrimaryKey){
            return $this->Db()->insert($clsTable->strTable,$arrData,$options);
        }else{
            $options['where'][$clsTable->arrPrimaryKey[0]] = $strPrimaryKey;
            return $this->Db()->update($clsTable->strTable,$arrData,$options);
        }
    }

    /**
     * 添加和修改，返回的Data中，0-是【更新成功】或【最后插入ID】，1-是所有的SQL语句
     * @param \stdClass|string $tableNameOrTableClass
     * @param array $arrData
     * @param array $options
     * @return mixed
     */
    public function updateField($tableNameOrTableClass, $arrData = [],$options = []){
        $clsTable = $this->getDalClass($tableNameOrTableClass);
        return $this->Db()->update($clsTable->strTable,$arrData,$options);
    }

    /**
     * 自动识别常用的变量，并合并到options里
     * @param $options
     * @return mixed
     */
    private function commonVariable($options){



        return $options;
    }

    /**
     * 自动加入where条件，目前只有int和str两种，
     * @param array $arrCondition
     * @param array $arrOptions
     * @param string $strAction
     * @return array
     */
    private function parseCondition($arrCondition = [],$arrOptions = [],$strAction = ''){
        $tempWhere = [];//需要重写的where
        $tempKey = ['int','str'];//目前支持的key类型
        $tempExpKey = ['in','between','exp','gt','egt','lt','elt','neq','eq'];//支付的item对比类型
        foreach ($arrCondition as $key=>$item) {
            !is_array($item) && $item = [$item];
            if(!in_array($item[0],$tempKey)){continue;}
            $tempData = $this->getData(stripos($key,'.')>0?explode('.',$key)[1]:$key);
            //根据$arrCondition中拿到的值，如果是数组，就判断操作符
            if (is_array($tempData) && in_array(strtolower($tempData[0]),$tempExpKey)){
                !isset($tempData[1]) && $tempData[1] = '';
                if ($this->{strtolower($tempData[0]).'Action'}($tempData)){
                    $tempWhere[$key] = $tempData;
                    continue;
                }else{
                    continue;
                }
            }
            if ($item[0] == 'int'){
                !isset($item[1]) && $item[1] = 'gt';
                (!isset($item[2]) || !is_numeric($item[2])) && $item[2] = 0;
                $tempData = max(0,intval($tempData));
            }elseif ($item[0] == 'str'){
                !isset($item[1]) && $item[1] = '!';
                $item[1] == '!' && $item[1] = 'notNull';
                (!isset($item[2]) || !is_numeric($item[2])) && $item[2] = 0;
                $tempData = trim($tempData);
            }else{
                continue;
            }
            if ($this->{$item[1]}($tempData,$item[2])){
                if ($strAction == 'delete'){
                    $tempWhere[stripos($key,'.')===false?$key:array_pop(explode('.',$key))] = $tempData;
                }else{
                    $tempWhere[$key] = $tempData;
                }
            }
        }
        $arrWhere = (isset($arrOptions['where']) && is_array($arrOptions['where']))?$arrOptions['where']:[];
        $arrOptions['where'] = !$arrWhere?$tempWhere:array_merge($arrWhere,$tempWhere);
        return $arrOptions;
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