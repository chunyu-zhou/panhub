<?php
namespace app\api\model;

use think\Model;
use think\Db;

class DiskManage extends Model{

	private $myPath;
	public $userObj;

	function __construct($path) {

	}

	static function getMemory($driver='local') {
		$total = 0;
		$used = 0;
		switch ($driver) {
			case 'local':
				$total = disk_total_space('/');
				$used = $total - disk_free_space('/');
			default:
				$ret = [
					'rate' => 0,
					'total' => "0Mb",
					'used' => "0Mb"
				];
		}

		$ret = [
			'rate' => $total == 0 ? '1' : $used / $total * 100,
			'total' => countSize($total),
			'used' => countSize($used)
		];
		return $ret;
	}
}
?>
