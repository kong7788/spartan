<?php
namespace Spartan\Driver\Db;

defined('APP_PATH') or exit();

interface Db{
    public function __construct($_arrConfig = []);//初始化
    public function connect();//连接数据库
    public function parseKey($key);
    public function escapeString($key,$intLinkID = null);
    public function parseLimit($limit);
    public function isReTry($intLinkID);
    public function query($intLinkID,$strSql);
    public function getNumRows($queryID);
    public function getAffectedRows($intLinkID);
    public function getAll($queryID);
    public function getInsertId($intLinkID);
    public function error($intLinkID);
    public function free($queryID);
    public function close($intLinkID);
    public function getFields($intLinkID,$strTableName);
    public function getTables($intLinkID,$strDbName);

}