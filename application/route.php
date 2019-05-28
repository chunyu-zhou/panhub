<?php
use think\Route;
Route::rule([
    'disk/memory/'=>'api/Disk/memory',
    'file/ListFile/'=>'api/File/listFile',
    'file/Move/'=>'api/File/move',
]);
