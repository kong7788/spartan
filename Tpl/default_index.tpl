<?php
namespace {App_NAME}\Controller;
use Spartan\Core\Controller;

!defined('APP_PATH') && exit('404 Not Found');

class IndexController extends Controller {

    public function index(){
        $this->display();
    }

}
{Controller}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>欢迎使用Spartan.</title>
    <style type="text/css">
        *{ padding: 0; margin: 0; }
        div{ padding: 4px 48px;}
        a{color:#2E5CD5;cursor: pointer;text-decoration: none}
        a:hover{text-decoration:underline; }
        body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;}
        h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; }
        p{ line-height: 1.6em; font-size: 42px;}
    </style>
</head>
<body>
    <div style="padding: 24px 48px;">
        <h1>:)</h1>
        <h2>欢迎使用Spartan，项目已经初始化完成。</h2>
        <p>项目地址：<a href="https://github.com/langshen/spartan" target="_blank">https://github.com/langshen/spartan</a></p>
    </div>
</body>
</html>
