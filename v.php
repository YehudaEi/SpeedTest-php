<?php

function ret($val){
    echo '{"val":'.$val.'}';
}

$def = 546;

$act = rand(0, 100);
$num = rand(1, 7);

if(isset($_GET['v']) && intval($_GET['v']) > 0){
    if($act % 2 == 0){
        ret($_GET['v'] + $num);
    }
    else{
        ret($_GET['v'] - $num);
    }
}
else{
    if($act % 2 == 0){
        ret($def + $num);
    }
    else{
        ret($def - $num);
    }
}