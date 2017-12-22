<?php
namespace Spartan\Driver\Image;

defined('APP_PATH') OR exit('404 Not Found');

class Verify {
    private $arrConfig = null;

    public function __construct($_arrConfig = []){
        (!isset($_arrConfig['width']) || !is_numeric($_arrConfig['width'])) && $_arrConfig['width'] = 60;
        (!isset($_arrConfig['height']) || !is_numeric($_arrConfig['height'])) && $_arrConfig['height'] = 28;
        (!isset($_arrConfig['length']) || !is_numeric($_arrConfig['length'])) && $_arrConfig['length'] = 4;
        (!isset($_arrConfig['type']) || !$_arrConfig['type']) && $_arrConfig['type'] = 'png';
        (!isset($_arrConfig['rand_text']) || !$_arrConfig['rand_text']) && $_arrConfig['rand_text'] = 'rand';
        $this->arrConfig = $_arrConfig;
    }

    public function createIm() {
		$width = $this->arrConfig['width'];
		$height = $this->arrConfig['height'];
        $length = $this->arrConfig['length'];
        $type = $this->arrConfig['type'];
        $strRandText = $this->arrConfig['rand_text'];
		$width = ($length * 10 + 10) > $width ? $length * 10 + 10 : $width;
		if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
			$im = imagecreatetruecolor($width, $height);
		} else {
			$im = imagecreate($width, $height);
		}
		$r = Array(225, 255, 255, 223);
		$g = Array(225, 236, 237, 255);
		$b = Array(225, 236, 166, 125);
		$key = mt_rand(0, 3);
		$backColor = imagecolorallocate($im, $r[$key], $g[$key], $b[$key]);//背景色（随机）
		$borderColor = imagecolorallocate($im, 100, 100, 100);//边框色
		imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $backColor);
		imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderColor);
		$stringColor = imagecolorallocate($im, mt_rand(0, 200), mt_rand(0, 120), mt_rand(0, 120));
		for ($i = 0; $i < 10; $i++) {//干扰
			imagearc($im,mt_rand(-10, $width), mt_rand(-10, $height), mt_rand(30, 300), mt_rand(20, 200), 55, 44, $stringColor);
		}
		for ($i = 0; $i < 25; $i++) {
			imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $stringColor);
		}
		for ($i = 0; $i < $length; $i++) {
			imagestring($im, 5, $i * 10 + 5, mt_rand(1, 8), $strRandText{$i}, $stringColor);
		}
		return $im;
	}


} 