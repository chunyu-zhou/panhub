<?php
namespace app\api\controller;

use app\common\controller\Common;
use app\api\model\DiskManage;

class Disk extends Common
{
    public function memory() {
        $driver = input('?get.driver') == false ? 'local' : input('get.driver');
        $data = DiskManage::getMemory($driver);
        success($data);
    }
}
