<?php
namespace Spartan\Lib;

defined('APP_PATH') OR die('404 Not Found');

/**
 * 数据Data Access Layer
 * Class Dal
 * @package Spartan\Lib
 */
class Validation {


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