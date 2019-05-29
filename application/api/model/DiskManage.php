<?php
namespace app\api\model;

use think\Console;
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

	static function dirRename($id='', $newName='') {
		$dir = Db::name('folders')->where('d_id',$id)->find();
		if(!$dir) {
			error('文件夹不存在', 404);
		}

		$positionTemp = explode('/', $dir['position']);
		$positionTemp[count($positionTemp)-1] = $newName;
		$position = implode('/', $positionTemp);
		$isTemp = Db::name('folders')->where('position',$position)->find();
		if($isTemp) {
			error('已存在该文件夹', 403);
		}

		$folders = Db::name('folders')->where('position', 'like', $dir['position'].'/%')->select();
		$files = Db::name('files')->where('dir', 'like', $dir['position'].'/%')->select();
		Db::startTrans();
		try{

			// 更新当前文件夹
			$thisIsUpdated = Db::name('folders')->where('id', $dir['id'])->update([
				'title' => $newName,
				'lase_update_time' => time(),
				'position' => $position
			]);

			$err = 0;
			// 更改文件夹名称
			$BaseDir = ROOT_PATH .'storage/files';
			$path = $BaseDir.$dir['position'];
			if(!rename($path, $BaseDir.$position)){
				$err++;
			}

			foreach ($folders as $folder) {
				$newPosition = substr_replace($folder['position'], $position, 0, strlen($dir['position']));
				$updated = Db::name('folders')->where('id', $folder['id'])->update(['position' => $newPosition]);
				if($updated === false) {
					$err++;
				}
			}
			foreach ($files as $file) {
				$newDir = substr_replace($file['dir'], $position, 0, strlen($dir['position']));
				$updated = Db::name('folders')->where('id', $file['id'])->update(['dir' => $newDir]);
				if($updated === false) {
					$err++;
				}
			}
			
			if($err == 0 && $thisIsUpdated) {
				// 提交事务
				Db::commit();
				success(ROOT_PATH);
			} else {
				// 回滚事务
				Db::rollback();
				error(ROOT_PATH, 500);
			}
			Db::commit();
		} catch (\Exception $e) {
			// 回滚事务
			Db::rollback();
			error('修改失败', 500);
		}

		Db::name('folders')->where('owner',$uid)->where('position_absolute',$fname)->update([
			'folder_name' => $new,
			'position_absolute' => $newPositionAbsolute,
		]);
		$childFolder = Db::name('folders')->where('owner',$uid)->where('position',"like",$fname."%")->select();
		foreach ($childFolder as $key => $value) {
			$tmpPositionAbsolute = "";
			$tmpPosition = "";
			$pos = strpos($value["position_absolute"], $fname);
			if ($pos === false) {
				$tmpPositionAbsolute = $value["position_absolute"];
			}
			$tmpPositionAbsolute = substr_replace($value["position_absolute"], $newTmp, $pos, strlen($fname));
			$pos = strpos($value["position"], $fname);
			if ($pos === false) {
				$tmpPosition = $value["position"];
			}
			$tmpPosition = substr_replace($value["position"], $newTmp, $pos, strlen($fname));
			Db::name('folders')->where('id',$value["id"])->update([
				'position_absolute' => $tmpPositionAbsolute,
				'position' =>$tmpPosition,
			]);
		}
		$childFiles = Db::name('files')->where('upload_user',$uid)->where('dir',"like",$fname."%")->select();
		foreach ($childFiles as $key => $value) {
			$tmpPosition = "";
			$pos = strpos($value["dir"], $fname);
			if ($pos === false) {
				$tmpPosition = $value["dir"];
			}
			$tmpPosition = substr_replace($value["dir"], $newTmp, $pos, strlen($fname));
			Db::name('files')->where('id',$value["id"])->update([
				'dir' =>$tmpPosition,
			]);
		}
		if($notEcho){
			return '{ "result": { "success": true} }';
		}
		echo ('{ "result": { "success": true} }');
	}
}
?>
