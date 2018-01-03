<?php
$arrValue = null;
$strMsg = 'as%2fsf';
$a = call_user_func('sprintf',$strMsg,$arrValue);


print_r($a);