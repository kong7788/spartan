<?php
namespace Spartan\Lib;

defined('APP_PATH') OR exit('404 Not Found');

class Image{
    private $arrConfig = [];

    /**
     * @param array $arrConfig
     * @return Image
     */
    public static function instance($arrConfig = []) {
        return \Spt::getInstance(__CLASS__,$arrConfig);
    }

    public function __construct($_arrConfig = []){
        (!isset($_arrConfig['width']) || !is_numeric($_arrConfig['width'])) && $_arrConfig['width'] = 60;
        (!isset($_arrConfig['height']) || !is_numeric($_arrConfig['height'])) && $_arrConfig['height'] = 28;
        (!isset($_arrConfig['length']) || !is_numeric($_arrConfig['length'])) && $_arrConfig['length'] = 4;
        (!isset($_arrConfig['mode']) || !is_numeric($_arrConfig['mode'])) && $_arrConfig['mode'] = 1;
        (!isset($_arrConfig['verify_name']) || !$_arrConfig['verify_name']) && $_arrConfig['verify_name'] = 'verifyCode';
        (!isset($_arrConfig['driver']) || !$_arrConfig['driver']) && $_arrConfig['driver'] = 'Verify';
        (!isset($_arrConfig['type']) || !$_arrConfig['type']) && $_arrConfig['type'] = 'png';
        $_arrConfig['rand_text'] = $this->getText($_arrConfig['mode'],$_arrConfig['length']);
        $_arrConfig['driver'] = ucfirst(strtolower($_arrConfig['driver']));
        session($_arrConfig['verify_name'], $_arrConfig['rand_text']);
        $this->arrConfig = $_arrConfig;

        $this->output(
            \Spt::getInstance('Spartan\\Driver\\Image\\'.$_arrConfig['driver'],$this->arrConfig)->createIm(),
            $_arrConfig['type'],
            ''
        );
    }

    private function output($im, $type = 'png', $filename = ''){
        header("Content-type: image/" . $type);
        $ImageFun = 'image' . $type;
        if (empty($filename)) {
            $ImageFun($im);
        } else {
            $ImageFun($im, $filename);
        }
        imagedestroy($im);
    }

    /**
     * 取得字符串
     * @param $textType 1为数字，2为文本
     * @param $textCount
     * @return string
     */
    private function getText($textType,$textCount){
        $strText = '';
        if($textType == 1){
            for($i = 0;$i < $textCount;$i++){
                $strText .= chr(mt_rand(48,57));
            }
        }elseif($textType == 2){
            for($i = 0;$i < $textCount;$i++){
                $strText .= chr(mt_rand(97,122));
            }
        }
        return strtolower($strText);
    }
}