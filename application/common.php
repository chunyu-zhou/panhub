<?php

use think\Request;
use think\Db;

$is_ssl = is_ssl() ? 'https://' : 'http://';
if(!IS_CLI) {
    defined('Domain') || define('Domain',$is_ssl.$_SERVER['HTTP_HOST']);
}

function success($data=[], $code='200', $msg='') {
    json([
        'data' => $data,
        'code' => $code,
        'msg' => $msg
    ]);
}

function error($msg='', $code='403') {
    success([],$code,$msg);
}

function json($data=[]) {
    die(json_encode($data));
}

function I($key='',$is_restful=false) {
    if($is_restful) {
        return \request()->param();
    }
    return input($key);
}

/**
 * 判断是否SSL协议
 * @return boolean
 */
function is_ssl() {
    if(isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))){
        return true;
    }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'] )) {
        return true;
    }
    return false;
}

/*
 * 获取随机字符串
 * type: 0：随机字符串，1：数字，2：a-zA-Z，3：包含特殊字符的字符串
 * len: 字符串长度
 */
function RandStr($length=6,$type=0){
    $arr = array(1 => "0123456789", 2 => "abcdefghijklmnopqrstuvwxyz", 3 => "ABCDEFGHIJKLMNOPQRSTUVWXYZ", 4 => "~@#$%^&*(){}[]|");
    $code = '';
    if ($type == 0) {
        array_pop($arr);
        $string = implode("", $arr);
    } else if ($type == "-1") {
        $string = implode("", $arr);
    } else {
        $string = $arr[$type];
    }
    $count = strlen($string) - 1;
    for ($i = 0; $i < $length; $i++) {
        $str[$i] = $string[rand(0, $count)];
        $code .= $str[$i];
    }
    return $code;
}

/**
 *      把秒数转换为时分秒的格式
 *      @param Int $times 时间，单位 秒
 *      @return String
 */
function secToTime($times){
    $result = '00:00';
    if ($times>0) {
        $hour = floor($times/3600);
        $minute = floor(($times-3600 * $hour)/60);
        $second = floor((($times-3600 * $hour) - 60 * $minute) % 60);
        if($hour <= 0){
            $result = $minute.':'.$second;
        }else{
            $result = $hour.':'.$minute.':'.$second;
        }
    }
    return $result;
}


function array_sort($arr,$keys,$orderby='asc'){
    $keysvalue = $new_array = array();
    foreach ($arr as $k=>$v){
        $keysvalue[$k] = $v[$keys];
    }
    if($orderby== 'asc'){
        asort($keysvalue);
    }else{
        arsort($keysvalue);
    }
    reset($keysvalue);
    foreach ($keysvalue as $k=>$v){
        $new_array[] = $arr[$k];
    }
    return $new_array;
}

function countSize($bit,$array=false){
    $type = array(' Bytes',' KB',' MB',' GB',' TB',' PB');
    $box = array('1','1024','1048576','1073741824','1099511627776',' PB');
    for($i = 0; $bit >= 1024; $i++) {
        $bit/=1024;
    }
    if($array){
        return [(floor($bit*100)/100),$box[$i]];
    }
    return (floor($bit*100)/100).$type[$i];
}

function getDbConfig($key='') {
    if($key == '') {
        return false;
    }
    $config = Db::name('config')->where('name',$key)->find();
    if(!$config){
        return false;
    }
    return $config['val'];
}

function getWebSalt() {
    return getDbConfig('web_secret');
}

/**
 * 获取用户IP
 * @return mixed
 * @throws \GuzzleHttp\Exception\GuzzleException
 */
function getIp() {
    $Curl = new Client();
    $url = 'http://api.ip.la/cn?json';
    $res = $Curl->request('GET',$url);
    $data = $res->getBody();
    $data = json_decode($data, true) ? json_decode($data, true) : $data;
    if(isset($data['ip'])) {
        return $data['ip'];
    }
    return \request()->ip();
}

function getPassword($password='', $salt='') {
    $WebSalt = getWebSalt();
    return md5($WebSalt.$password.$salt);
}

function getDir($dir, $mode = 0777)
{
    if(is_dir($dir)) {
        return true;
    } else if(@mkdir($dir, $mode)) {
        return true;
    } else if(!mkdirs(dirname($dir), $mode)) {
        return false;
    } else {
        return @mkdir($dir, $mode);
    }
}

/**
 * 文件夹创建并且给权限
 * @param string $path 文件夹路径
 * @param int $mode 权限,默认 0777
 * @return bool
 * @author lidong<947714443@qq.com>
 */
function mkdir_chmod($path, $mode = 0777){
    if(is_dir($path)) {
        return true;
    }

    $result = mkdir($path, $mode, true);
    if($result) {
        $path_arr = explode('/',$path);
        $path_str = '';
        foreach($path_arr as $val){
            $path_str .= $val.'/';
            $result = chmod($path_str, $mode);
        }
    }
    return $result;
}


//人性化时间显示
function formatTime($time)
{
    $rtime = date("m-d H:i", $time);
    $htime = date("H:i", $time);
    $time = time() - $time;
    if ($time < 60) {
        $str = $time.'秒前';
    } elseif ($time < 60 * 60) {
        $min = floor($time / 60);
        $str = $min . '分钟前';
    } elseif ($time < 60 * 60 * 24) {
        $h = floor($time / (60 * 60));
        $str = $h . '小时前 ';
    } elseif ($time < 60 * 60 * 24 * 3) {
        $d = floor($time / (60 * 60 * 24));
        if ($d == 1) {
            $str = '昨天 ' . $rtime;
        } else {
            $str = '前天 ' . $rtime;
        }
    } else {
        $str = $rtime;
    }
    return $str;
}
