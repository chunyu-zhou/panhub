<?php
namespace app\api\controller;

use app\common\controller\Common;
use app\api\model\FileManage;
use app\api\model\DiskManage;

class Api extends Common
{

    /**
     * 获取存储容量
     */
    public function memory() {
        $driver = input('?get.driver') == false ? 'local' : input('get.driver');
        $data = DiskManage::getMemory($driver);
        success($data);
    }

    /**
     * 远程下载
     */
    public function remoteDownload() {

    }

    /**
     * 清空回收站
     */
    public function clearRecycle() {

    }

    /**
     * 列表
     */
    public function getList() {
        $reqPath = stripslashes(input('?post.path') == false ? '/' : input('post.path'));
        $action = stripslashes(input('?post.action') == false ? 'list' : input('post.action'));
        $ret = FileManage::ListFile($reqPath,$action);
        success($ret);
    }

    /**
     * 分片上传
     */
    public function upload() {

    }

    /**
     * 合并分片
     */
    public function merge() {

    }

    /**
     * 移动
     */
    public function move() {
        $newPath = input('?post.newPath') == false ? '' : input('post.newPath');
        $dirs = input('?post.dirs') == false ? [] : input('post.dirs/a');
        $files = input('?post.items') == false ? [] : input('post.items/a');
        return FileManage::MoveHandler($files,$dirs,$newPath);
    }

    /**
     * 复制
     */
    public function copy() {

    }


    /**
     * 删除
     */
    public function remove() {

    }

    /**
     * 重命名
     */
    public function rename() {
        $newName = input('?post.newName') == false ? '' : input('post.newName');
        $type = input('?post.type') == false ? '' : input('post.type');
        $id = input('?post.id') == false ? '' : input('post.id');

        if($newName == '') {
            error('新的名称不能为空', 403);
        } else if(strlen($newName) > 255) {
            error('新的名称不能超过255个字符: ' . strlen($newName) , 403);
        } else if(preg_match('/\//', $newName)) {
            error('文件名不能包含以下字符之一: \ /', 403);
        } else if($type != 'file' && $type != 'dir') {
            error('未知的文件类型', 403);
        } else if($id == '') {
            error('未知的文件', 403);
        }

        if($type == 'file') {
            return FileManage::MoveHandler($files,$dirs,$newPath);
        } else {
            return DiskManage::dirRename($id, $newName);
        }

    }
}
