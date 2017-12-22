# 入口文件


    <?php
	    require('F:/php/core/Spartan.php');
	    Spt::start(
	    	Array(
	    		'APP_NAME'=>'DEMO',//项目目录名称
	    		'APP_ROOT'=>dirname(__DIR__).DIRECTORY_SEPARATOR.'Application',//项目根目录
	    		'HOST'=>'',//主机名
	    		'DOMAIN'=>'',//域名
	    		'DEBUG'=>true,//调试模式
	    		'SAVE_LOG'=>true,//保存异常日志
	    		'LANG'=>'zh-cn',//语言包
	    		'TIME_ZONE'=>'PRC',//时区
	    		'SERVER'=>false,//服务模式
	    		'MAIN_FUN'=>'runMain',//服务模式的入口函数
	    	)
	    );
    