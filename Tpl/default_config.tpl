<?php
/*
*项目的基础配置，如果使用SVN或GIT更新生产环境，忽略该文件即可，非常实用
*/
defined('APP_PATH') or die('404 Not Found');
return Array(
    'SITE'=>Array(//站点设置
        'NAME'=>'Spartan主页',
        'KEY_NAME'=>'spartan,framework,db orm',
        'DESCRIPTION'=>'spartan是一个轻量级的PHP框架，非常非常地轻；部署非常常方便。',
    ),
    'DB'=>Array(//数据库设置
        'TYPE'=>'mysqli',//数据库类型
        'HOST'=>'120.78.80.218',//服务器地址
        'NAME'=>'syt_pay',//数据库名
        'USER'=>'syt_pay',//用户名
        'PWD'=>'syt_pay',//密码
        'PORT'=>'3306',//端口
        'PREFIX'=>'j_',//数据库表前缀
        'CHARSET'=>'utf8',//数据库编码默认采用utf8
    ),
    'SESSION_HANDLER'=>Array(//Session服务器，如果启用，可以共享session
        'NAME'=>'redis',
        'PATH'=>'tcp://120.78.80.218:63798?auth=foobaredf23fdafasflxvxz.vaf;jdsafi2pqfjaf;;dsafj;sajfsapfisapjf',
    ),
    'EMAIL'=>Array(//邮件服务器配置
        'SERVER'=>'smtp.exmail.qq.com',
        'USER_NAME'=>'',
        'PASS_WORD'=>'',
        'PORT'=>25,
        'FROM_EMAIL'=>'',//发件人EMAIL
        'FROM_NAME'=>'Mrs Syt', //发件人名称
    ),
);
{Config}
<?php
/*
*项目的常用、公共的配置
*/
defined('APP_PATH') or die('404 Not Found');
$arrConfig = include('BaseConfig.php');
$arrTemp =  Array(

);
return array_merge($arrConfig,$arrTemp);
{Config}
<?php
/*
*站点的常用、公共的配置
*/
defined('APP_PATH') or die('404 Not Found');
$arrConfig = include(APP_ROOT.'Common'.NS.'Config.php');
$arrTemp = Array(

);
return array_merge($arrConfig,$arrTemp);
{Config}
<?php
/*
*项目的常用、公共的函数，WEB_URLS是可以在模版使用的函数。
*/
defined('APP_PATH') or die('404 Not Found');
define('WEB_URLS',',,WWW_URL,STATIC_URL,API_URL,USER_URL,ATTACHMENT_URL,ADMIN_URL,,');

function attachPath($path=''){
    return "../attachment/".trim($path,'/');
}
function attachUrl($path=''){
    return ATTACHMENT_URL().'/'.trim($path,'/');
}
function WWW_URL(){
    return 'http://www.'.DOMAIN;
}
function STATIC_URL(){
    return '/public/';
}
function USER_URL(){
    return '/account.html';
}
function ATTACHMENT_URL(){
    return 'http://attch.'.DOMAIN;
}
function API_URL(){
    return 'http://api.'.DOMAIN;
}
function ADMIN_URL(){
    return 'http://admin.'.DOMAIN;
}
{Config}
<?php
/*
*当前站点的常用、公共的函数。
*/
defined('APP_PATH') or die('404 Not Found');
/**
 * 随机字符串
 * @param $length
 * @return string
 */
function getRandomString($length){
    $arr = array_merge(range(0, 9), range('A', 'Z'));
    $str = '';
    $arr_len = count($arr);
    for ($i = 0; $i < $length; $i++) {
        $rand = mt_rand(0, $arr_len-1);
        $str .= $arr[$rand];
    }
    return $str;
}