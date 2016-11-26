<?php
/**
 * Determina si el valor a consultar de temperatura para equipos arris es valido
 * @param $v
 * @return array
 */
function arrisCmtsTemp($v){
    $v=trim($v);
    if (in_array($v,array('','999'))){
        $bool=false;
    }else{
        $bool=true;
    }

    return array('result'=>$bool);
}

/**
 * Determina si esl valor a consultar de cpu para equipos arris es valido
 * @param $v
 * @return array
 */
function arrisCmtsCpu($v){
    $v=trim($v);

    if (in_array($v,array(''))){
        $bool=false;
    }else{
        $bool=true;
    }

    $v=abs($v-100);

    return array('result'=>$bool,'value'=>$v);
}