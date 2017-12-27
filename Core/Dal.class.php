<?php
namespace Spartan\Core;

defined('APP_PATH') OR exit('404 Not Found');

abstract class Dal {
    public $table = '';
    public $condition = Array();
    /**
     * Dal层，对外的接口，在此做CURD的权限判断
     * @param array $options
     * @param string $action
     * @param array $data
     * @return array
     */
    final protected function getResult($options=[],$action='find',$data=[]){
        if ($action != 'update' && $action != 'insert' && $this->condition){
            $options['where'] = $this->parseCondition($options['where'],$action);
        }
        $result = $this->curd($options,$action,$data);
        if ($result===false){
            $result = $this->Db()->getError();
            $status = 0;
        }else{
            $status = 1;
        }
        $sql = APP_DEBUG?$this->Db()->getLastSql():'';
        if ($action == 'update' || $action == 'insert'){
            $info = $status?'保存成功。'.$action:'保存失败。'.$action;
        }elseif($action == 'select'|| $action == 'find'){
            $info = $status?'查询成功。'.$action:'查询失败。'.$action;
        }else{
            $info = $status?'删除成功。'.$action:'删除失败。'.$action;
        }
        return Array($info,$status,$result);
       // return Array("The operation ".($status?'successfully.':'failed.')."{$sql} {$action}ed",$status,$result);
    }

    /**
     * 对数据库直接的CURD操作
     * @param array $options
     * @param string $action
     * @param array $data
     * @return array|string
     */
    private function curd($options=[],$action='',$data=[]){
        switch($action){
            case $action=='insert'||$action=='update':
                $result = $this->Db()->$action($this->table,$data,$options);
                break;
            case $action=='find'||$action=='delete':
                $result = $this->Db()->$action($this->table,$options);
                break;
            case 'select':
                $arrUi = $this->miniUI();
                if (isset($options['limit']) && intval($options['limit']) > 0){
                    $arrUi['limit'] = $options['limit'];
                    unset($options['limit']);
                }
                if (isset($options['join']) && isset($arrUi['order'])){//如果有join和order
                    $arrUi['order'] = '@.'.$this->table.'.'.$arrUi['order'];
                }

                if (isset($options['where']) && isset($arrUi['where'])){
                    $arrUi['where'] = array_merge($arrUi['where'],$options['where']);
                    unset($options['where']);
                }
                $options = array_merge($arrUi, $options);
                if(!isset($options['order'])){
                    $options['order'] = 'id desc';
                }
                $intCount = $this->Db()->find($this->table,$options,'count');
                $rsList = $this->Db()->select($this->table,$options);
                if ($rsList==false){$rsList = [];}
                $result = Array('total' => $intCount,'data' => $rsList);
                APP_DEBUG && $result['sql'] = $this->Db()->getLastSql();
                break;
            default:
                $result = false;
        }
        return $result;
    }
    /**
     * 自动提取MiniUI的搜索、分页、排序
     * @return mixed
     */
    private function miniUI(){
        $data['page'] = max(0, $this->getData('pageIndex'));
        $data['limit'] = $this->getData('pageSize',100);
        $data['order'] = $this->getData('sortField','');
        if ($data['order']) {
            $data['order'] .= ' ' . $this->getData('sortOrder');
        }else{
            unset($data['order']);
        }

        $arrSymbol = Array('eq','like','gt','lt','neq','egt','elt');
        $search_type = $this->getData('search_type');
        $search_symbol = $this->getData('search_symbol');
        $search_key = trim($this->getData('search_key',''));
        if (stripos($search_key,'\u')===0){
            $search_key = json_decode('"'.$search_key.'"');
        }
        !in_array($search_symbol, $arrSymbol) && $search_symbol = 'eq';
        if ($search_type && $search_key!='') {
            if (stripos($search_key,' ')!==false && $search_symbol=='like'){
                $arrSearchKey = explode(' ',$search_key);
                if ($search_symbol=='like'){
                    $arrSearchSymbol = array("%{$arrSearchKey[0]}%");
                }else{
                    $arrSearchSymbol = $search_key;
                }
                isset($arrSearchKey[1]) && $arrSearchSymbol[] = "%{$arrSearchKey[1]}%";
                isset($arrSearchKey[2]) && $arrSearchSymbol[] = "%{$arrSearchKey[2]}%";
                $data['where'][$search_type] = Array(
                    $search_symbol,
                    $arrSearchSymbol,
                    'and'
                );
            }else{
                $data['where'][$search_type] = Array($search_symbol, $search_symbol=='like'?"%{$search_key}%":$search_key);
            }
        }
        return $data;
    }


} 