<?php
$arrValue = null;
$strMsg = 'as%2fsf';
$aa = 'bb() && cc()';
$a = function()use ($aa){return $aa() ;};

function bb(){
    return 'bb';
}
function cc($value){
    return (boolval($value));
}

var_dump('aa' > 2);