<?php
namespace Spartan\Driver\Db;

defined('APP_PATH') or exit();

interface Db{
    public function parseKey($key);
    public function escapeString($key);
    public function parseLimit($limit);
    public function connect($_arrConfig);
    public function execute($strSql);
    public function query($intLinkID,$strSql);
    public function free($queryID);
    public function close($linkId);

}