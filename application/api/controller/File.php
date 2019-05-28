<?php
namespace app\api\controller;

use app\common\controller\Common;
use app\api\model\FileManage;

class File extends Common
{
    public function listFile()
    {
        $reqPath = stripslashes(input('?post.path') == false ? '/' : input('post.path'));
        $action = stripslashes(input('?post.action') == false ? 'list' : input('post.action'));
        $ret = FileManage::ListFile($reqPath,$action);
        success($ret);
    }

    public function move() {
        $newPath = input('?post.newPath') == false ? '' : input('post.newPath');
        $dirs = input('?post.dirs') == false ? [] : input('post.dirs/a');
        $files = input('?post.items') == false ? [] : input('post.items/a');
        return FileManage::MoveHandler($files,$dirs,$newPath);
    }
}
