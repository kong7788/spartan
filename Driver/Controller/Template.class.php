<?php
namespace Spartan\Driver\Controller;

/**
 * '<', // 标签库标签开始标记
 * '>', // 标签库标签结束标记
 * '{', // 模板引擎普通标签开始标记
 * '}', // 模板引擎普通标签结束标记
 */

class Template{
    public $cachePath = 'Runtime/Cache/';//模板缓存位置
    private $templateFile = '';//当前模板文件
    private $tVar = Array();//模板变量
    private $literal = Array();
    private $block = Array();
    private $comparison = Array(//标签定义
        ' nheq '=>' !== ',
        ' heq '=>' === ',
        ' neq '=>' != ',
        ' eq '=>' == ',
        ' egt '=>' >= ',
        ' gt '=>' > ',
        ' elt '=>' <= ',
        ' lt '=>' < '
    );
    //标签定义：attr属性列表,close是否闭合（0 或者1 默认1）,alias标签别名,level嵌套层次
    private $tags = Array(
        'php' => Array(),
        'volist' => Array('attr'=>'name,id,offset,length,key,mod','level'=>3,'alias'=>'iterate'),
        'foreach' => Array('attr'=>'name,item,key','level'=>3),
        'if' => Array('attr'=>'condition','level'=>2),
        'elseif' => Array('attr'=>'condition','close'=>0),
        'else' => Array('attr'=>'','close'=>0),
        'switch' => Array('attr'=>'name','level'=>2),
        'case' => Array('attr'=>'value,break'),
        'default' => Array('attr'=>'','close'=>0),
        'compare' => Array('attr'=>'name,value,type','level'=>3,'alias'=>'eq,equal,notequal,neq,gt,lt,egt,elt,heq,nheq'),
        'range' => Array('attr'=>'name,value,type','level'=>3,'alias'=>'in,notin,between,notbetween'),
        'empty' => Array('attr'=>'name','level'=>3),
        'notempty' => Array('attr'=>'name','level'=>3),
        'present' => Array('attr'=>'name','level'=>3),
        'notpresent' => Array('attr'=>'name','level'=>3),
        'defined' => Array('attr'=>'name','level'=>3),
        'notdefined' => Array('attr'=>'name','level'=>3),
        'import' => Array('attr'=>'file,href,type,value,basepath','close'=>0,'alias'=>'load,css,js'),
        'for' => Array('attr'=>'start,end,name,comparison,step', 'level'=>3),
    );

    /**
     * Template
     * @param array $arrConfig
     * @return Template
     */
    public static function instance($arrConfig = []) {
        return \Spt::getInstance(__CLASS__,$arrConfig);
    }

    /**
     * Template constructor.
     * @param array $arrConfig
     */
    public function __construct($arrConfig = []){
        (isset($arrConfig['CACHE_PATH']) && $arrConfig['CACHE_PATH']) && $this->cachePath = $arrConfig['CACHE_PATH'];
    }

    /**
     * 获取模板变量
     * @param $name
     * @param string $default
     * @return string|mixed
     */
    public function get($name,$default = '') {
        return isset($this->tVar[$name])?$this->tVar[$name]:$default;
    }

    /**
     * 设置模板变量
     * @param $name
     * @param $value
     */
    public function set($name,$value) {
        $this->tVar[$name]= $value;
    }

    /**
     * 加载模板，模块解释的入口
     * @access public
     * @param string $templateFile 模板文件
     * @param array  $templateVar 模板变量
     * @return void
     */
    public function fetch($templateFile,$templateVar){
        $this->tVar = $templateVar;
        $templateCacheFile = $this->loadTemplate($templateFile);
        File::instance()->load($templateCacheFile,$this->tVar);
    }
    /**
     * 加载主模板并缓存
     * @access public
     * @param string $templateFile 模板文件
     * @return string
     */
    public function loadTemplate ($templateFile) {
        if(is_file($templateFile)){
            $this->templateFile = $templateFile;
            $tmpContent = file_get_contents($templateFile);
        }else{
            $tmpContent =  $templateFile;
        }
        //根据模版文件名定位缓存文件
        $tmpCacheFile = APP_PATH.$this->cachePath.md5($templateFile).'.cache';
        //编译模板内容
        $this->compiler($tmpContent);
        File::instance()->put($tmpCacheFile,trim($tmpContent));
        return $tmpCacheFile;
    }
    /**
     * 编译模板文件内容
     * @access protected
     * @param mixed $tmpContent 模板内容
     * @return string
     */
    protected function compiler(&$tmpContent) {
        //模板解析
        $tmpContent = $this->parse($tmpContent);
        // 还原被替换的Literal标签
        $tmpContent = preg_replace_callback('/<!--###literal(\d+)###-->/is', array($this,'restoreLiteral'),$tmpContent);
        // 添加安全代码
        $tmpContent = '<?php if (!defined(\'APP_PATH\')) exit();?>'.$tmpContent;
        // 优化生成的php代码
        $tmpContent = str_replace('?><?php','',$tmpContent);
        // 模版编译过滤标签
        return strip_whitespace($tmpContent);
    }

    /**
     * 模板解析入口
     * 支持普通标签和TagLib解析 支持自定义标签库
     * @access public
     * @param string $content 要解析的模板内容
     * @return string
     */
    public function parse(&$content){
        if(empty($content)) return '';// 内容为空不解析
        $content = $this->parseInclude($content);// 检查include语法
        $content = $this->parsePhp($content);// 检查PHP语法
        // 首先替换literal标签内容
        $content = preg_replace_callback('/<literal>(.*?)<\/literal>/is',Array($this,'parseLiteral'),$content);

        //解析指定的标签库
        foreach ($this->tags as $name=>$val){
            $tags = array($name);
            if(isset($val['alias'])) {// 别名设置
                $tags       = explode(',',$val['alias']);
                $tags[]     =  $name;
            }
            $level      =   isset($val['level'])?$val['level']:1;
            $closeTag   =   isset($val['close'])?$val['close']:true;
            foreach ($tags as $tag){
                $parseTag = $tag;// 实际要解析的标签名称
                if(!method_exists($this,'_'.$tag)) {
                    $tag  =  $name;// 别名可以无需定义解析方法
                }
                $n1 = empty($val['attr'])?'(\s*?)':'\s([^>]*)';

                if (!$closeTag){
                    $patterns = '/<'.$parseTag.$n1.'\/(\s*?)>/is';

                    $content = preg_replace_callback($patterns, function($matches) use($tag){
                        return $this->parseXmlTag($tag,$matches[1],$matches[2]);
                    },$content);

                }else{
                    $patterns = '/<'.$parseTag.$n1.'>(.*?)<\/'.$parseTag.'(\s*?)'.'>/is';
                    for($i=0;$i<$level;$i++) {
                        $content=preg_replace_callback($patterns,function($matches) use($tag){
                            return $this->parseXmlTag($tag,$matches[1],$matches[2]);
                        },$content);

                    }
                }
            }
        }
        //解析普通模板标签 {tagName}
        $content = preg_replace_callback('/(\{)([^\d\s\{\}].+?)(\})/is', Array($this,'parseTag'),$content);
        return $content;
    }

    // 检查PHP语法
    protected function parsePhp(&$content) {
        if(ini_get('short_open_tag')){
            // 开启短标签的情况要将<?标签用echo方式输出 否则无法正常输出xml标识
            $content = preg_replace('/(<\?(?!php|=|$))/i', '<?php echo \'\\1\'; ?>'."\n", $content );
        }
        // PHP语法检查
        if(C('TMPL_DENY_PHP') && false !== strpos($content,'<?php')) {
            \Spt::halt('template file not allow php');
        }
        return $content;
    }

    /**
     * 解析模板中的布局标签
     * @param $content
     * @return mixed
     */
    protected function parseLayout($content) {
        //读取模板中的布局标签
        $find = preg_match('/<layout\s(.+?)\s*?\/>/is',$content,$matches);
        if($find) {//替换Layout标签
            $content = str_replace($matches[0],'',$content);//解析Layout标签
            $array = $this->parseXmlAttrs($matches[1]);
            if('layout' != $array['name'] ) {// 读取布局模板
                $layoutFile = $this->parseTemplateName($array['name'].'.html');
                is_file($layoutFile) && $layoutFile = file_get_contents($layoutFile);
                $replace = isset($array['replace'])?$array['replace']:'{__CONTENT__}';
                $content = str_ireplace($replace,$content,$layoutFile);// 替换布局的主体内容
            }
        }else{
            $content = str_replace('{__NOLAYOUT__}','',$content);
        }
        return $content;
    }

    /**
     * 解析模板中的include标签
     * @param $content
     * @param bool $extend
     * @return mixed|string
     */
    protected function parseInclude($content, $extend = true) {
        $extend && $content = $this->parseExtend($content);// 解析继承
        $content = $this->parseLayout($content);// 解析布局
        $find = preg_match_all('/<include\s(.+?)\s*?\/>/is',$content,$matches);//读取模板中的include标签
        if($find) {
            for($i=0;$i<$find;$i++) {
                $include    =   $matches[1][$i];
                $array      =   $this->parseXmlAttrs($include);
                $file       =   $array['file'];
                unset($array['file']);
                $content    =   str_replace($matches[0][$i],$this->parseIncludeItem($file,$array),$content);
            }
        }
        return $content;
    }

    /**
     * 解析模板中的extend标签
     * @param $content
     * @return mixed|string
     */
    protected function parseExtend($content) {
        // 读取模板中的继承标签
        $find = preg_match('/<extend\s(.+?)\s*?\/>/is',$content,$matches);
        if($find) {//替换extend标签
            $content    =   str_replace($matches[0],'',$content);
            // 记录页面中的block标签
            preg_replace_callback('/<block\sname=[\'"](.+?)[\'"]\s*?>(.*?)<\/block>/is', array($this, 'parseBlock'),$content);
            // 读取继承模板
            $array = $this->parseXmlAttrs($matches[1]);
            $content = $this->parseTemplateName($array['name']);
            $content = $this->parseInclude($content, false); //对继承模板中的include进行分析
            $content = $this->replaceBlock($content);// 替换block标签
        }else{
            $content    =   preg_replace_callback('/<block\sname=[\'"](.+?)[\'"]\s*?>(.*?)<\/block>/is', function($match){return stripslashes($match[2]);}, $content);
        }
        return $content;
    }
    /**
     * 替换继承模板中的block标签
     * @access private
     * @param string $content  模板内容
     * @return string
     */
    private function replaceBlock($content){
        static $parse = 0;
        $reg   = '/(<block\sname=[\'"](.+?)[\'"]\s*?>)(.*?)<\/block>/is';
        if(is_string($content)){
            do{
                $content = preg_replace_callback($reg, array($this, 'replaceBlock'), $content);
            } while ($parse && $parse--);
            return $content;
        } elseif(is_array($content)){
            if(preg_match('/<block\sname=[\'"](.+?)[\'"]\s*?>/is', $content[3])){ //存在嵌套，进一步解析
                $parse = 1;
                $content[3] = preg_replace_callback($reg, array($this, 'replaceBlock'), "{$content[3]}</block>");
                return $content[1] . $content[3];
            } else {
                $name    = $content[2];
                $content = $content[3];
                $content = isset($this->block[$name]) ? $this->block[$name] : $content;
                return $content;
            }
        }else{
            return $content;
        }
    }

    /**
     * 分析XML属性
     * @access private
     * @param string $attrs  XML属性字符串
     * @return array
     */
    private function parseXmlAttrs($attrs) {
        $xml = '<tpl><tag '.$attrs.' /></tpl>';
        $xml = simplexml_load_string($xml);
        if(!$xml)
            \Spt::halt('xml tag error.');
        $xml = (array)($xml->tag->attributes());
        $array = array_change_key_case($xml['@attributes']);
        return $array;
    }

    /**
     * 替换页面中的literal标签
     * @access private
     * @param string $content  模板内容
     * @return string|false
     */
    private function parseLiteral($content) {
        if(is_array($content)){$content = $content[1];}
        if(trim($content)==''){return '';}
        $i = count($this->literal);
        $parseStr =   "<!--###literal{$i}###-->";
        $this->literal[$i] = $content;
        return $parseStr;
    }

    /**
     * 还原被替换的literal标签
     * @access private
     * @param string $tag  literal标签序号
     * @return string|false
     */
    private function restoreLiteral($tag) {
        if(is_array($tag)){$tag = $tag[1];}
        $parseStr = $this->literal[$tag];// 还原literal标签
        unset($this->literal[$tag]);// 销毁literal记录
        return $parseStr;
    }

    /**
     * 解析标签库的标签
     * 需要调用对应的标签库文件解析类
     * @param string $tag  标签名
     * @param string $attr  标签属性
     * @param string $content  标签内容
     * @return string|false
     */
    public function parseXmlTag($tag,$attr,$content) {
        if(ini_get('magic_quotes_sybase')) {
            $attr   =	str_replace('\"','\'',$attr);
        }
        $parse      =	'_'.$tag;
        $content    =	trim($content);
        $tags		=   $this->parseXmlAttr($attr,$tag);
        return $this->$parse($tags,$content);
    }

    /**
     * 模板标签解析
     * 格式： {TagName:args [|content] }
     * @access public
     * @param string $tagStr 标签内容
     * @return string
     */
    public function parseTag($tagStr){
        if(is_array($tagStr)){$tagStr = $tagStr[2];}
        $tagStr = stripslashes($tagStr);
        //还原非模板标签
        if(preg_match('/^[\s|\d]/is',$tagStr))//过滤空格和数字打头的标签
        {return '{'.$tagStr.'}';}
        $flag   =  substr($tagStr,0,1);
        $flag2  =  substr($tagStr,1,1);
        $name   = substr($tagStr,1);
        if('$' == $flag && '.' != $flag2 && '(' != $flag2){ //解析模板变量 格式 {$varName}
            return $this->parseVar($name);
        }elseif('-' == $flag || '+'== $flag){ // 输出计算
            return  '<?php echo '.$flag.$name.';?>';
        }elseif(':' == $flag){ // 输出某个函数的结果
            return  '<?php echo '.$name.';?>';
        }elseif('~' == $flag){ // 执行某个函数
            return  '<?php '.$name.';?>';
        }elseif(substr($tagStr,0,2)=='//' || (substr($tagStr,0,2)=='/*' && substr(rtrim($tagStr),-2)=='*/')){
            //注释标签
            return '';
        }
        // 未识别的标签直接返回
        return '{'.$tagStr.'}';
    }

    /**
     * 模板变量解析,支持使用函数
     * 格式： {$varname|function1|function2=arg1,arg2}
     * @access public
     * @param string $varStr 变量数据
     * @return string
     */
    public function parseVar($varStr){
        $varStr     =   trim($varStr);
        static $_varParseList = array();
        //如果已经解析过该变量字串，则直接返回变量值
        if(isset($_varParseList[$varStr]))
        {return $_varParseList[$varStr];}
        $parseStr   =   '';
        if(!empty($varStr)){
            $varArray = explode('|',$varStr);
            //取得变量名称
            $var = array_shift($varArray);
            if('Spt.' == substr($var,0,4)){
                //所有以Spt.打头的以特殊变量对待 无需模板赋值就可以输出
                $name = $this->parseSptVar($var);
            }elseif( false !== strpos($var,'.')) {
                //支持 {$var.property}
                $vars = explode('.',$var);
                $var  =  array_shift($vars);
                switch(strtolower(C('TMPL_VAR_IDENTIFY'))) {
                    case 'array': // 识别为数组
                        $name = '$'.$var;
                        foreach ($vars as $val)
                            $name .= '["'.$val.'"]';
                        break;
                    case 'obj':  // 识别为对象
                        $name = '$'.$var;
                        foreach ($vars as $val)
                            $name .= '->'.$val;
                        break;
                    default:  // 自动判断数组或对象 只支持二维
                        $name = 'is_array($'.$var.')?$'.$var.'["'.$vars[0].'"]:$'.$var.'->'.$vars[0];
                }
            }elseif(false !== strpos($var,'[')) {
                //支持 {$var['key']} 方式输出数组
                $name = "$".$var;
                preg_match('/(.+?)\[(.+?)\]/is',$var,$match);
                $var = $match[1];
            }elseif(false !==strpos($var,':') && false ===strpos($var,'(') && false ===strpos($var,'::') && false ===strpos($var,'?')){
                //支持 {$var:property} 方式输出对象的属性
                $vars = explode(':',$var);
                $var  =  str_replace(':','->',$var);
                $name = "$".$var;
                $var  = $vars[0];
            }else {
                $name = "$$var";
            }
            //对变量使用函数
            if(count($varArray)>0)
                $name = $this->parseVarFunction($name,$varArray);
            $parseStr = '<?php echo ('.$name.'); ?>';
        }
        $_varParseList[$varStr] = $parseStr;
        return $parseStr;
    }

    /**
     * 对模板变量使用函数
     * 格式 {$varname|function1|function2=arg1,arg2}
     * @access public
     * @param string $name 变量名
     * @param array $varArray  函数列表
     * @return string
     */
    public function parseVarFunction($name,$varArray){
        //对变量使用函数
        $length = count($varArray);
        //取得模板禁止使用函数列表
        $template_deny_funs = explode(',',C('TMPL_DENY_FUNC_LIST'));
        for($i=0;$i<$length ;$i++ ){
            $args = explode('=',$varArray[$i],2);
            //模板函数过滤
            $fun = strtolower(trim($args[0]));
            switch($fun) {
                case 'default':  // 特殊模板函数
                    $name = '(isset('.$name.') && ('.$name.' !== ""))?('.$name.'):'.$args[1];
                    break;
                default:  // 通用模板函数
                    if(!in_array($fun,$template_deny_funs)){
                        if(isset($args[1])){
                            if(strstr($args[1],'###')){
                                $args[1] = str_replace('###',$name,$args[1]);
                                $name = "$fun($args[1])";
                            }else{
                                $name = "$fun($name,$args[1])";
                            }
                        }else if(!empty($args[0])){
                            $name = "$fun($name)";
                        }
                    }
            }
        }
        return $name;
    }

    /**
     * 特殊模板变量解析
     * 格式 以 $Spt. 打头的变量属于特殊模板变量
     * @access public
     * @param string $varStr  变量字符串
     * @return string
     */
    public function parseSptVar($varStr){
        is_array($varStr) && $varStr = isset($varStr[1])?$varStr[1]:$varStr[0];
        $vars = explode('.',$varStr);
        $vars[1] = strtoupper(trim($vars[1]));
        $parseStr = '';
        if(count($vars)>=3){
            $vars[2] = trim($vars[2]);
            switch($vars[1]){
                case 'SERVER':
                    $parseStr = '$_SERVER[\''.strtoupper($vars[2]).'\']';break;
                case 'GET':
                    $parseStr = '$_GET[\''.$vars[2].'\']';break;
                case 'POST':
                    $parseStr = '$_POST[\''.$vars[2].'\']';break;
                case 'COOKIE':
                    if(isset($vars[3])) {
                        $parseStr = '$_COOKIE[\''.$vars[2].'\'][\''.$vars[3].'\']';
                    }else{
                        $parseStr = 'cookie(\''.$vars[2].'\')';
                    }
                    break;
                case 'SESSION':
                    if(isset($vars[3])) {
                        $parseStr = 'session(\''.$vars[2].'.'.$vars[3].'\')';
                    }else{
                        $parseStr = 'session(\''.$vars[2].'\')';
                    }
                    break;
                case 'ENV':
                    $parseStr = '$_ENV[\''.strtoupper($vars[2]).'\']';break;
                case 'REQUEST':
                    $parseStr = '$_REQUEST[\''.$vars[2].'\']';break;
                case 'CONST':
                    $parseStr = strtoupper($vars[2]);break;
                case 'CONFIG':
                    if(isset($vars[3])) {
                        $vars[2] .= '.'.$vars[3];
                    }
                    $parseStr = 'C("'.$vars[2].'")';break;
                default:break;
            }
        }else if(count($vars)==2){
            switch($vars[1]){
                case 'NOW':
                    $parseStr = "date('Y-m-d g:i a',time())";
                    break;
                case 'TEMPLATE':
                    $parseStr = "'".$this->templateFile."'";
                    break;
                case 'LDELIM':
                    $parseStr = '{';
                    break;
                case 'RDELIM':
                    $parseStr = '}';
                    break;
                case stripos(WEB_URLS,$vars[1])>0:
                    $parseStr = $vars[1].'()';
                    break;
                default:
                    $parseStr = defined($vars[1])?$vars[1]:"'{$vars[1]}'";
            }
        }
        return $parseStr;
    }

    /**
     * 加载公共模板并缓存 和当前模板在同一路径，否则使用相对路径
     * @param string $tmplPublicName  公共模板文件名
     * @param array $vars  要传递的变量列表
     * @return string
     */
    private function parseIncludeItem($tmplPublicName,$vars=array()){
        // 分析模板文件名并读取内容
        $parseStr = $this->parseTemplateName($tmplPublicName);
        // 替换变量
        foreach ($vars as $key=>$val) {
            $parseStr = str_replace('['.$key.']',$val,$parseStr);
        }
        // 再次对包含文件进行模板分析
        return $this->parseInclude($parseStr);
    }

    /**
     * 加载模块中的include file
     * 分析加载的模板文件并读取内容 支持多个模板文件读取
     * @access private
     * @param string $templateName  模板文件名
     * @return string
     */
    private function parseTemplateName($templateName){
        $templatePath = dirname(dirname($this->templateFile));
        if(substr($templateName,0,1)=='$'){//支持加载变量文件名
            $templateName = $templatePath.$this->get(substr($templateName,1));
        }
        $array  = explode(',',$templateName);
        $parseStr   =   '';
        foreach ($array as $templateName){
            if (stripos($templateName,'@')>0){
                $templateName = $templatePath.'/'.str_replace('@','/',$templateName);
            }elseif(stripos($templateName,'/')===false){//不知应用场景
                $templateName = substr($this->templateFile,0,strrpos($this->templateFile,'/')+1).$templateName;
            }else{
                $templateName = $templatePath . '/' . $templateName;
            }
            if(empty($templateName)){continue;}//获取模板文件内容
            $parseStr .= file_get_contents($templateName);
        }
        return $parseStr;
    }



    /***************************以下是对各种标签的处理**********************/
    /**
     * TagLib标签属性分析 返回标签属性数组
     * @param string $attr 标签内容
     * @param string $tag 标签内容
     * @return array
     */
    public function parseXmlAttr($attr,$tag) {
        //XML解析安全过滤
        $attr   =   str_replace('&','___', $attr);
        if (is_array($attr))return($attr);
        $xml    =   '<tpl><tag '.$attr.' /></tpl>';
        $xml    =   simplexml_load_string($xml);
        if(!$xml) {
            \Spt::halt('_XML_TAG_ERROR_: '.$attr);
            return [];
        }
        $xml    =   (array)($xml->tag->attributes());
        if(isset($xml['@attributes'])){
            $array  =   array_change_key_case($xml['@attributes']);
            if($array) {
                $tag    =   strtolower($tag);
                $item = '';
                if(!isset($this->tags[$tag])){
                    // 检测是否存在别名定义
                    foreach($this->tags as $key=>$val){
                        if(isset($val['alias']) && in_array($tag,explode(',',$val['alias']))){
                            $item  =   $val;
                            break;
                        }
                    }
                }else{
                    $item  =   $this->tags[$tag];
                }
                $attrs  = explode(',',$item['attr']);
                if(isset($item['must'])){
                    $must   =   explode(',',$item['must']);
                }else{
                    $must   =   array();
                }
                foreach($attrs as $name) {
                    if( isset($array[$name])) {
                        $array[$name] = str_replace('___','&',$array[$name]);
                    }elseif(false !== array_search($name,$must)){
                        \Spt::halt('_PARAM_ERROR_'.':'.$name);
                    }
                }
                return $array;
            }
        }else{
            return [];
        }
        return [];
    }

    /**
     * 解析条件表达式
     * @access public
     * @param string $condition 表达式标签内容
     * @return array
     */
    public function parseCondition($condition) {
        $condition = str_ireplace(array_keys($this->comparison),array_values($this->comparison),$condition);
        $condition = preg_replace('/\$(\w+):(\w+)\s/is','$\\1->\\2 ',$condition);
        switch(strtolower(C('TMPL_VAR_IDENTIFY'))) {
            case 'array': // 识别为数组
                $condition  =   preg_replace('/\$(\w+)\.(\w+)\s/is','$\\1["\\2"] ',$condition);
                break;
            case 'obj':  // 识别为对象
                $condition  =   preg_replace('/\$(\w+)\.(\w+)\s/is','$\\1->\\2 ',$condition);
                break;
            default:  // 自动判断数组或对象 只支持二维
                $condition  =   preg_replace('/\$(\w+)\.(\w+)\s/is','(is_array($\\1)?$\\1["\\2"]:$\\1->\\2) ',$condition);
        }
        if(false !== strpos($condition, '$Spt'))
            $condition      =   preg_replace_callback('/(\$Spt.*?)\s/is', array($this, 'parseSptVar'), $condition);
        return $condition;
    }

    /**
     * 自动识别构建变量
     * @access public
     * @param string $name 变量描述
     * @return string
     */
    public function autoBuildVar($name) {
        if('Spt.' == substr($name,0,4)){
            // 特殊变量
            return $this->parseSptVar($name);
        }elseif(strpos($name,'.')) {
            $vars = explode('.',$name);
            $var  =  array_shift($vars);
            switch(strtolower(C('TMPL_VAR_IDENTIFY'))) {
                case 'array': // 识别为数组
                    $name = '$'.$var;
                    foreach ($vars as $key=>$val){
                        if(0===strpos($val,'$')) {
                            $name .= '["{'.$val.'}"]';
                        }else{
                            $name .= '["'.$val.'"]';
                        }
                    }
                    break;
                case 'obj':  // 识别为对象
                    $name = '$'.$var;
                    foreach ($vars as $key=>$val)
                        $name .= '->'.$val;
                    break;
                default:  // 自动判断数组或对象 只支持二维
                    $name = 'is_array($'.$var.')?$'.$var.'["'.$vars[0].'"]:$'.$var.'->'.$vars[0];
            }
        }elseif(strpos($name,':')){
            // 额外的对象方式支持
            $name   =   '$'.str_replace(':','->',$name);
        }elseif(!defined($name)) {
            $name = '$'.$name;
        }
        return $name;
    }
    /**
     * php标签解析
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _php($attr,$content) {
        $parseStr = '<?php '.$content.' ?>';
        return $parseStr;
    }

    /**
     * volist标签解析 循环输出数据集
     * 格式：
     * <volist name="userList" id="user" empty="" >
     * {user.username}
     * {user.email}
     * </volist>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return mixed|string
     */
    public function _volist($attr,$content) {
        static $_iterateParseCache = array();
        //如果已经解析过，则直接返回变量值
        $cacheIterateId = md5((is_array($attr)?implode('',$attr):$attr).$content);
        if(isset($_iterateParseCache[$cacheIterateId]))
            return $_iterateParseCache[$cacheIterateId];
        $tag   =    $this->parseXmlAttr($attr,'volist');
        $name  =    $tag['name'];
        $id    =    $tag['id'];
        $empty =    isset($tag['empty'])?$tag['empty']:'';
        $key   =    !empty($tag['key'])?$tag['key']:'i';
        $mod   =    isset($tag['mod'])?$tag['mod']:'2';
        // 允许使用函数设定数据集 <volist name=":fun('arg')" id="vo">{$vo.name}</volist>
        $parseStr   =  '<?php ';
        if(0===strpos($name,':')) {
            $parseStr   .= '$_result='.substr($name,1).';';
            $name   = '$_result';
        }else{
            $name   = $this->autoBuildVar($name);
        }
        $parseStr  .=  'if(isset('.$name.') && is_array('.$name.')): $'.$key.' = 0;';
        if(isset($tag['length']) && '' !=$tag['length'] ) {
            $parseStr  .= ' $__LIST__ = array_slice('.$name.','.$tag['offset'].','.$tag['length'].',true);';
        }elseif(isset($tag['offset'])  && '' !=$tag['offset']){
            $parseStr  .= ' $__LIST__ = array_slice('.$name.','.$tag['offset'].',null,true);';
        }else{
            $parseStr .= ' $__LIST__ = '.$name.';';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo "'.$empty.'" ;';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $'.$id.'_key=>$'.$id.'): ';
        $parseStr .= '$mod = ($'.$key.' % '.$mod.' );';
        $parseStr .= '++$'.$key.';?>';
        $parseStr .= $this->parse($content);
        $parseStr .= '<?php endforeach; endif; else: echo "'.$empty.'" ;endif; ?>';
        $_iterateParseCache[$cacheIterateId] = $parseStr;

        if(!empty($parseStr)) {
            return $parseStr;
        }
        return $content;
    }

    /**
     * foreach标签解析 循环输出数据集
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return mixed|string
     */
    public function _foreach($attr,$content) {
        static $_iterateParseCache = array();
        //如果已经解析过，则直接返回变量值
        $cacheIterateId = md5((is_array($attr)?implode('',$attr):$attr).$content);
        if(isset($_iterateParseCache[$cacheIterateId]))
            return $_iterateParseCache[$cacheIterateId];
        $tag        =   $this->parseXmlAttr($attr,'foreach');
        $name       =   $tag['name'];
        $item       =   $tag['item'];
        $key        =   !empty($tag['key'])?$tag['key']:$item.'_key';
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(isset('.$name.') && is_array('.$name.')): foreach('.$name.' as $'.$key.'=>$'.$item.'): ?>';
        $parseStr  .=   $this->parse($content);
        $parseStr  .=   '<?php endforeach; endif; ?>';
        $_iterateParseCache[$cacheIterateId] = $parseStr;
        if(!empty($parseStr)) {
            return $parseStr;
        }
        return $content;
    }

    /**
     * if标签解析
     * 格式：
     * <if condition=" $a eq 1" >
     * <elseif condition="$a eq 2" />
     * <else />
     * </if>
     * 表达式支持 eq neq gt egt lt elt == > >= < <= or and || &&
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _if($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'if');
        $condition  =   $this->parseCondition($tag['condition']);
        $parseStr   =   '<?php if('.$condition.'): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * else标签解析
     * 格式：见if标签
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _elseif($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'elseif');
        $condition  =   $this->parseCondition($tag['condition']);
        $parseStr   =   '<?php elseif('.$condition.'): ?>';
        return $parseStr;
    }

    /**
     * else标签解析
     * @access public
     * @param string $attr 标签属性
     * @return string
     */
    public function _else($attr) {
        $parseStr = '<?php else: ?>';
        return $parseStr;
    }

    /**
     * switch标签解析
     * 格式：
     * <switch name="a.name" >
     * <case value="1" break="false">1</case>
     * <case value="2" >2</case>
     * <default />other
     * </switch>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _switch($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'switch');
        $name       =   $tag['name'];
        $varArray   =   explode('|',$name);
        $name       =   array_shift($varArray);
        $name       =   $this->autoBuildVar($name);
        if(count($varArray)>0)
            $name   =   $this->parseVarFunction($name,$varArray);
        $parseStr   =   '<?php switch('.$name.'): ?>'.$content.'<?php endswitch;?>';
        return $parseStr;
    }

    /**
     * case标签解析 需要配合switch才有效
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _case($attr,$content) {
        $tag    = $this->parseXmlAttr($attr,'case');
        $value  = $tag['value'];
        if('$' == substr($value,0,1)) {
            $varArray   =   explode('|',$value);
            $value	    =	array_shift($varArray);
            $value      =   $this->autoBuildVar(substr($value,1));
            if(count($varArray)>0)
                $value  =   $this->parseVarFunction($value,$varArray);
            $value      =   'case '.$value.': ';
        }elseif(strpos($value,'|')){
            $values     =   explode('|',$value);
            $value      =   '';
            foreach ($values as $val){
                $value   .=  'case "'.addslashes($val).'": ';
            }
        }else{
            $value	=	'case "'.$value.'": ';
        }
        $parseStr = '<?php '.$value.' ?>'.$content;
        $isBreak  = isset($tag['break']) ? $tag['break'] : '';
        if('' ==$isBreak || $isBreak) {
            $parseStr .= '<?php break;?>';
        }
        return $parseStr;
    }

    /**
     * default标签解析 需要配合switch才有效
     * 使用： <default />ddfdf
     * @access public
     * @param string $attr 标签属性
     * @return string
     */
    public function _default($attr) {
        $parseStr = '<?php default: ?>';
        return $parseStr;
    }

    /**
     * compare标签解析
     * 用于值的比较 支持 eq neq gt lt egt elt heq nheq 默认是eq
     * 格式： <compare name="" type="eq" value="" >content</compare>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @param string $type  比较方式
     * @return string
     */
    public function _compare($attr,$content,$type='eq') {
        $tag        =   $this->parseXmlAttr($attr,'compare');
        $name       =   $tag['name'];
        $value      =   $tag['value'];
        $type       =   isset($tag['type'])?$tag['type']:$type;
        $type       =   $this->parseCondition(' '.$type.' ');
        $varArray   =   explode('|',$name);
        $name       =   array_shift($varArray);
        $name       =   $this->autoBuildVar($name);
        if(count($varArray)>0)
            $name = $this->parseVarFunction($name,$varArray);
        if('$' == substr($value,0,1)) {
            $value  =  $this->autoBuildVar(substr($value,1));
        }else {
            $value  =   '"'.$value.'"';
        }
        $parseStr   =   '<?php if(('.$name.') '.$type.' '.$value.'): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    public function _eq($attr,$content) {
        return $this->_compare($attr,$content,'eq');
    }

    public function _equal($attr,$content) {
        return $this->_compare($attr,$content,'eq');
    }

    public function _neq($attr,$content) {
        return $this->_compare($attr,$content,'neq');
    }

    public function _notequal($attr,$content) {
        return $this->_compare($attr,$content,'neq');
    }

    public function _gt($attr,$content) {
        return $this->_compare($attr,$content,'gt');
    }

    public function _lt($attr,$content) {
        return $this->_compare($attr,$content,'lt');
    }

    public function _egt($attr,$content) {
        return $this->_compare($attr,$content,'egt');
    }

    public function _elt($attr,$content) {
        return $this->_compare($attr,$content,'elt');
    }

    public function _heq($attr,$content) {
        return $this->_compare($attr,$content,'heq');
    }

    public function _nheq($attr,$content) {
        return $this->_compare($attr,$content,'nheq');
    }

    /**
     * range标签解析
     * 如果某个变量存在于某个范围 则输出内容 type= in 表示在范围内 否则表示在范围外
     * 格式： <range name="var|function"  value="val" type='in|notin' >content</range>
     * example: <range name="a"  value="1,2,3" type='in' >content</range>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @param string $type  比较类型
     * @return string
     */
    public function _range($attr,$content,$type='in') {
        $tag        =   $this->parseXmlAttr($attr,'range');
        $name       =   $tag['name'];
        $value      =   $tag['value'];
        $varArray   =   explode('|',$name);
        $name       =   array_shift($varArray);
        $name       =   $this->autoBuildVar($name);
        if(count($varArray)>0)
            $name   =   $this->parseVarFunction($name,$varArray);

        $type       =   isset($tag['type'])?$tag['type']:$type;

        if('$' == substr($value,0,1)) {
            $value  =   $this->autoBuildVar(substr($value,1));
            $str    =   'is_array('.$value.')?'.$value.':explode(\',\','.$value.')';
        }else{
            $value  =   '"'.$value.'"';
            $str    =   'explode(\',\','.$value.')';
        }
        if($type=='between') {
            $parseStr = '<?php $_RANGE_VAR_='.$str.';if('.$name.'>= $_RANGE_VAR_[0] && '.$name.'<= $_RANGE_VAR_[1]):?>'.$content.'<?php endif; ?>';
        }elseif($type=='notbetween'){
            $parseStr = '<?php $_RANGE_VAR_='.$str.';if('.$name.'<$_RANGE_VAR_[0] && '.$name.'>$_RANGE_VAR_[1]):?>'.$content.'<?php endif; ?>';
        }else{
            $fun        =  ($type == 'in')? 'in_array'    :   '!in_array';
            $parseStr   = '<?php if('.$fun.'(('.$name.'), '.$str.')): ?>'.$content.'<?php endif; ?>';
        }
        return $parseStr;
    }

    // range标签的别名 用于in判断
    public function _in($attr,$content) {
        return $this->_range($attr,$content,'in');
    }

    // range标签的别名 用于notin判断
    public function _notin($attr,$content) {
        return $this->_range($attr,$content,'notin');
    }

    public function _between($attr,$content){
        return $this->_range($attr,$content,'between');
    }

    public function _notbetween($attr,$content){
        return $this->_range($attr,$content,'notbetween');
    }

    /**
     * present标签解析
     * 如果某个变量已经设置 则输出内容
     * 格式： <present name="" >content</present>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _present($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'present');
        $name       =   $tag['name'];
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(isset('.$name.')): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * notpresent标签解析
     * 如果某个变量没有设置，则输出内容
     * 格式： <notpresent name="" >content</notpresent>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _notpresent($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'notpresent');
        $name       =   $tag['name'];
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(!isset('.$name.')): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * empty标签解析
     * 如果某个变量为empty 则输出内容
     * 格式： <empty name="" >content</empty>
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _empty($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'empty');
        $name       =   $tag['name'];
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(empty('.$name.')): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    public function _notempty($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'notempty');
        $name       =   $tag['name'];
        $name       =   $this->autoBuildVar($name);
        $parseStr   =   '<?php if(!empty('.$name.')): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * 判断是否已经定义了该常量
     * <defined name='TXT'>已定义</defined>
     * @param <type> $attr
     * @param <type> $content
     * @return string
     */
    public function _defined($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'defined');
        $name       =   $tag['name'];
        $parseStr   =   '<?php if(defined("'.$name.'")): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    public function _notdefined($attr,$content) {
        $tag        =   $this->parseXmlAttr($attr,'_notdefined');
        $name       =   $tag['name'];
        $parseStr   =   '<?php if(!defined("'.$name.'")): ?>'.$content.'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * import 标签解析 <import file="Js.Base" />
     * <import file="Css.Base" type="css" />
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @param boolean $isFile  是否文件方式
     * @param string $type  类型
     * @return string
     */
    public function _import($attr,$content,$isFile=false,$type='') {
        $tag        =   $this->parseXmlAttr($attr,'import');
        $file       =   isset($tag['file'])?$tag['file']:$tag['href'];
        $parseStr   =   '';
        $endStr     =   '';
        // 判断是否存在加载条件 允许使用函数判断(默认为isset)
        if (isset($tag['value'])) {
            $varArray  =    explode('|',$tag['value']);
            $name      =    array_shift($varArray);
            $name      =    $this->autoBuildVar($name);
            if (!empty($varArray))
                $name  =    $this->parseVarFunction($name,$varArray);
            else
                $name  =    'isset('.$name.')';
            $parseStr .=    '<?php if('.$name.'): ?>';
            $endStr    =    '<?php endif; ?>';
        }
        if($isFile) {
            // 根据文件名后缀自动识别
            $type  = $type?$type:(!empty($tag['type'])?strtolower($tag['type']):null);
            // 文件方式导入
            $array =  explode(',',$file);
            foreach ($array as $val){
                if (!$type || isset($reset)) {
                    $type = $reset = strtolower(substr(strrchr($val, '.'),1));
                }
                switch($type) {
                    case 'js':
                        $parseStr .= '<script type="text/javascript" src="'.$val.'"></script>';
                        break;
                    case 'css':
                        $parseStr .= '<link rel="stylesheet" type="text/css" href="'.$val.'" />';
                        break;
                    case 'php':
                        $parseStr .= '<?php require_cache("'.$val.'"); ?>';
                        break;
                }
            }
        }else{
            // 命名空间导入模式 默认是js
            $type       =   $type?$type:(!empty($tag['type'])?strtolower($tag['type']):'js');
            $basepath   =   !empty($tag['basepath'])?$tag['basepath']:''.'/Public';
            // 命名空间方式导入外部文件
            $array      =   explode(',',$file);
            foreach ($array as $val){
                list($val,$version) =   explode('?',$val);
                switch($type) {
                    case 'js':
                        $parseStr .= '<script type="text/javascript" src="'.$basepath.'/'.str_replace(array('.','#'), array('/','.'),$val).'.js'.($version?'?'.$version:'').'"></script>';
                        break;
                    case 'css':
                        $parseStr .= '<link rel="stylesheet" type="text/css" href="'.$basepath.'/'.str_replace(array('.','#'), array('/','.'),$val).'.css'.($version?'?'.$version:'').'" />';
                        break;
                    case 'php':
                        $parseStr .= '<?php import("'.$val.'"); ?>';
                        break;
                }
            }
        }
        return $parseStr.$endStr;
    }

    // import别名 采用文件方式加载(要使用命名空间必须用import) 例如 <load file="__PUBLIC__/Js/Base.js" />
    public function _load($attr,$content) {
        return $this->_import($attr,$content,true);
    }

    // import别名使用 导入css文件 <css file="__PUBLIC__/Css/Base.css" />
    public function _css($attr,$content) {
        return $this->_import($attr,$content,true,'css');
    }

    // import别名使用 导入js文件 <js file="__PUBLIC__/Js/Base.js" />
    public function _js($attr,$content) {
        return $this->_import($attr,$content,true,'js');
    }

    /**
     * for标签解析
     * 格式： <for start="" end="" comparison="" step="" name="" />
     * @access public
     * @param string $attr 标签属性
     * @param string $content  标签内容
     * @return string
     */
    public function _for($attr, $content){
        //设置默认值
        $start 		= 0;
        $end   		= 0;
        $step 		= 1;
        $comparison = 'lt';
        $name		= 'i';
        $rand       = rand(); //添加随机数，防止嵌套变量冲突
        //获取属性
        foreach ($this->parseXmlAttr($attr, 'for') as $key => $value){
            $value = trim($value);
            if(':'==substr($value,0,1))
                $value = substr($value,1);
            elseif('$'==substr($value,0,1))
                $value = $this->autoBuildVar(substr($value,1));
            switch ($key){
                case 'start':
                    $start      = $value; break;
                case 'end' :
                    $end        = $value; break;
                case 'step':
                    $step       = $value; break;
                case 'comparison':
                    $comparison = $value; break;
                case 'name':
                    $name       = $value; break;
            }
        }

        $parseStr   = '<?php $__FOR_START_'.$rand.'__='.$start.';$__FOR_END_'.$rand.'__='.$end.';';
        $parseStr  .= 'for($'.$name.'=$__FOR_START_'.$rand.'__;'.$this->parseCondition('$'.$name.' '.$comparison.' $__FOR_END_'.$rand.'__').';$'.$name.'+='.$step.'){ ?>';
        $parseStr  .= $content;
        $parseStr  .= '<?php } ?>';
        return $parseStr;
    }

}
