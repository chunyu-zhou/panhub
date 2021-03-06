<?php
namespace app\api\model;

use think\Model;
use think\Db;
use think\Validate;
use \app\index\model\Option;

class FileManage extends Model{

	public $filePath;
	public $fileData;
	public $userID;
	public $userData;
	public $policyData;
	public $deleteStatus = true;

	private $adapter;

	/**
	 * construct function
	 *
	 * @param string $path 文件路径/文件ID
	 * @param int $uid 用户ID
	 * @param boolean $byId 是否根据文件ID寻找文件
	 */
	public function __construct($path,$uid,$byId=false){
		if($byId){
			$fileRecord = Db::name('files')->where('id',$path)->find();
			$this->filePath = rtrim($fileRecord["dir"],"/")."/".$fileRecord["orign_name"];
		}else{
			$this->filePath = $path;
			$fileInfo = $this->getFileName($path);
			$fileName = $fileInfo[0];
			$path = $fileInfo[1];
			$fileRecord = Db::name('files')->where('upload_user',$uid)->where('orign_name',$fileName)->where('dir',$path)->find();
		}
		if (empty($fileRecord)){
			die('{ "result": { "success": false, "error": "文件不存在" } }');
		}
		$this->fileData = $fileRecord;
		$this->userID = $uid;
		$this->userData = Db::name('users')->where('id',$uid)->find();
		$this->policyData = Db::name('policy')->where('id',$this->fileData["policy_id"])->find();
		switch ($this->policyData["policy_type"]) {
			case 'local':
				$this->adapter = new \app\index\model\LocalAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 'qiniu':
				$this->adapter = new \app\index\model\QiniuAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 'oss':
				$this->adapter = new \app\index\model\OssAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 'upyun':
				$this->adapter = new \app\index\model\UpyunAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 's3':
				$this->adapter = new \app\index\model\S3Adapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 'remote':
				$this->adapter = new \app\index\model\RemoteAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			case 'onedrive':
				$this->adapter = new \app\index\model\OnedriveAdapter($this->fileData,$this->policyData,$this->userData);
				break;
			default:
				# code...
				break;
		}
	}

	/**
	 * 获取文件外链地址
	 *
	 * @return void
	 */
	public function Source(){
		if(!$this->policyData["origin_link"]){
			die('{"url":"此文件不支持获取源文件URL"}');
		}else{
			echo ('{"url":"'.$this->policyData["url"].$this->fileData["pre_name"].'"}');
		}
	}

	/**
	 * 获取可编辑文件内容
	 *
	 * @return void
	 */
	public function getContent(){
		$sizeLimit=(int)Option::getValue("maxEditSize");
		if($this->fileData["size"]>$sizeLimit){
			die('{ "result": { "success": false, "error": "您当前用户组最大可编辑'.$sizeLimit.'字节的文件"} }');
		}else{
			try{
				$fileContent = $this->adapter->getFileContent();
			}catch(\Exception $e){
				die('{ "result": { "success": false, "error": "'.$e->getMessage().'"} }');
			}
			$fileContent = $this->adapter->getFileContent();
			$result["result"] = $fileContent;
			if(empty(json_encode($result))){
				$result["result"] = iconv('gb2312','utf-8',$fileContent);
			}
			echo json_encode($result);
		}
	}

	/**
	 * 保存可编辑文件
	 *
	 * @param string $content 要保存的文件内容
	 * @return void
	 */
	public function saveContent($content){
		$contentSize = strlen($content);
		$originSize = $this->fileData["size"];
		if(!FileManage::sotrageCheck($this->userID,$contentSize)){
			die('{ "result": { "success": false, "error": "空间容量不足" } }');
		}
		$this->adapter->saveContent($content);
		FileManage::storageGiveBack($this->userID,$originSize);
		FileManage::storageCheckOut($this->userID,$contentSize);
		Db::name('files')->where('id', $this->fileData["id"])->update(['size' => $contentSize]);
		echo ('{ "result": { "success": true} }');
	}

	/**
	 * 文件名合法性初步检查
	 *
	 * @param string $value 文件名
	 * @return bool 检查结果
	 */
	static function fileNameValidate($value){
		$validate = new Validate([
			'val'  => 'require|max:250',
			'val' => 'chsDash'
		]);
		$data = [
			'val'  => $value
		];
		if (!$validate->check($data)) {
			return false;
		}
		return true;
	}

	/**
	 * 处理重命名
	 *
	 * @param string $fname    原文件路径
	 * @param string $new      新文件路径
	 * @param int $uid         用户ID
	 * @param boolean $notEcho 过程中是否不直接输出结果
	 * @return mixed
	 */
	static function RenameHandler($fname,$new,$uid,$notEcho = false){
		$folderTmp = $new;
		$originFolder = $fname;
		$new = str_replace("/", "", self::getFileName($new)[0]);
		if(!$notEcho){
			$newToBeVerify = str_replace(" ", "", $new);
		}
		//检查是否全为空格
		$varifyExplode = explode(".",$newToBeVerify);
		$isFullBlackspace = false;
		foreach ($varifyExplode as $key => $value) {
			if($value == ""){
				$isFullBlackspace = true;
				break;
			}
		}
		$toBeValidated = str_replace(".","",$newToBeVerify);
		if(!self::fileNameValidate($toBeValidated) || $isFullBlackspace){
			if($notEcho){
				return '{ "result": { "success": false, "error": "文件名只支持汉字、字母、数字和下划线_及破折号-" } }';
			}
			die('{ "result": { "success": false, "error": "文件名只支持汉字、字母、数字和下划线_及破折号-" } }');
		}
		$path = self::getFileName($fname)[1];
		$fname = self::getFileName($fname)[0];
		$fileRecord = Db::name('files')->where('upload_user',$uid)->where('orign_name',$fname)->where('dir',$path)->find();
		if (empty($new)){
			if($notEcho){
					return '{ "result": { "success": false, "error": "文件重名或文件名非法" } }';
			}
			die('{ "result": { "success": false, "error": "文件重名或文件名非法" } }');
		}
		if(empty($fileRecord)){
			self::folderRename($originFolder,$folderTmp,$uid,$notEcho);
			die();
		}
		$newSuffix = explode(".",$new);
		$originSuffix = explode(".",$fileRecord["orign_name"]);
		if(end($originSuffix) != end($newSuffix)){
			if($notEcho){
					return '{ "result": { "success": false, "error": "请不要更改文件扩展名" } }';
			}
			die('{ "result": { "success": false, "error": "请不要更改文件扩展名" } }');
		}
		Db::name('files')->where([
			'upload_user' => $uid,
			'dir' => $path,
			'orign_name' =>$fname,
		])->setField('orign_name', $new);
		if($notEcho){
				return '{ "result": { "success": true} }';
		}
		echo ('{ "result": { "success": true} }');
	}

	/**
	 * 处理目录重命名
	 *
	 * @param string $fname    原文件路径
	 * @param string $new      新文件路径
	 * @param int $uid         用户ID
	 * @param boolean $notEcho 过程中是否不直接输出结果
	 * @return void
	 */
	static function folderRename($fname,$new,$uid,$notEcho = false){

	}

	/**
	 * 根据文件路径获取文件名和父目录路径
	 *
	 * @param string 文件路径
	 * @return array 
	 */
	static function getFileName($path){
		$pathSplit = explode("/",$path);
		$fileName = end($pathSplit);
		$pathSplitDelete = array_pop($pathSplit);
		$path="";
		foreach ($pathSplit as $key => $value) {
			if (empty($value)){

			}else{
				$path =$path."/".$value;
			}
		} 
		$path = empty($path)?"/":$path;
		return [$fileName,$path];
	}

	/**
	 * 处理文件预览
	 *
	 * @param boolean $isAdmin 是否为管理员预览
	 * @return array 重定向信息
	 */
	public function PreviewHandler($isAdmin=false){
		return $this->adapter->Preview($isAdmin);
	}

	/**
	 * 获取图像缩略图
	 *
	 * @return array 重定向信息
	 */
	public function getThumb(){
		return $this->adapter->getThumb();
	}

	/**
	 * 处理文件下载
	 *
	 * @param boolean $isAdmin 是否为管理员请求
	 * @return array 文件下载URL
	 */
	public function Download($isAdmin=false){
		return $this->adapter->Download($isAdmin);
	}

	/**
	 * 处理目录删除
	 *
	 * @param string $path 目录路径
	 * @param int $uid     用户ID
	 * @return void
	 */
	static function DirDeleteHandler($path,$uid){
		global $toBeDeleteDir;
		global $toBeDeleteFile;
		$toBeDeleteDir = [];
		$toBeDeleteFile = [];
		foreach ($path as $key => $value) {
			array_push($toBeDeleteDir,$value);
		}
		
		foreach ($path as $key => $value) {
			self::listToBeDelete($value,$uid);
		}
		if(!empty($toBeDeleteFile)){
			self::DeleteHandler($toBeDeleteFile,$uid);
		}
		if(!empty($toBeDeleteDir)){
			self::deleteDir($toBeDeleteDir,$uid);
		}
	}

	/**
	 * 列出待删除文件或目录
	 *
	 * @param string $path 对象路径
	 * @param int $uid     用户ID
	 * @return void
	 */
	static function listToBeDelete($path,$uid){
		global $toBeDeleteDir;
		global $toBeDeleteFile;
		$fileData = Db::name('files')->where([
		'dir' => $path,
		'upload_user' => $uid,
		])->select();
		foreach ($fileData as $key => $value) {
			array_push($toBeDeleteFile,$path."/".$value["orign_name"]);
		}
		$dirData = Db::name('folders')->where([
		'position' => $path,
		'owner' => $uid,
		])->select();
		foreach ($dirData as $key => $value) {
			array_push($toBeDeleteDir,$value["position_absolute"]);
			self::listToBeDelete($value["position_absolute"],$uid);
		}
	}

	/**
	 * 删除目录
	 *
	 * @param string $path 目录路径
	 * @param int $uid     用户ID
	 * @return void
	 */
	static function deleteDir($path,$uid){
		Db::name('folders')
		->where("owner",$uid)
		->where([
		'position_absolute' => ["in",$path],
		])->delete();
	}

	/**
	 * 处理删除请求
	 *
	 * @param string $path 路径
	 * @param int $uid     用户ID
	 * @return array
	 */
	static function DeleteHandler($path,$uid){
		if(empty($path)){
			return ["result"=>["success"=>true,"error"=>null]];
		}
		foreach ($path as $key => $value) {
			$fileInfo = self::getFileName($value);
			$fileName = $fileInfo[0];
			$filePath = $fileInfo[1];
			$fileNames[$key] = $fileName;
			$filePathes[$key] = $filePath;
		}
		$fileData = Db::name('files')->where([
		'orign_name' => ["in",$fileNames],
		'dir' => ["in",$filePathes],
		'upload_user' => $uid,
		])->select();
		$fileListTemp=[];
		$uniquePolicy = self::uniqueArray($fileData);
		foreach ($fileData as $key => $value) {
			if(empty($fileListTemp[$value["policy_id"]])){
				$fileListTemp[$value["policy_id"]] = [];
			}
			array_push($fileListTemp[$value["policy_id"]],$value);
		}
		foreach ($fileListTemp as $key => $value) {
			if(in_array($key,$uniquePolicy["qiniuList"])){
				QiniuAdapter::DeleteFile($value,$uniquePolicy["qiniuPolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["localList"])){
				LocalAdapter::DeleteFile($value,$uniquePolicy["localPolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["ossList"])){
				OssAdapter::DeleteFile($value,$uniquePolicy["ossPolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["upyunList"])){
				UpyunAdapter::DeleteFile($value,$uniquePolicy["upyunPolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["s3List"])){
				S3Adapter::DeleteFile($value,$uniquePolicy["s3PolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["remoteList"])){
				RemoteAdapter::DeleteFile($value,$uniquePolicy["remotePolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}else if(in_array($key,$uniquePolicy["onedriveList"])){
				OnedriveAdapter::DeleteFile($value,$uniquePolicy["onedrivePolicyData"][$key][0]);
				self::deleteFileRecord(array_column($value, 'id'),array_sum(array_column($value, 'size')),$value[0]["upload_user"]);
			}
		}
		return ["result"=>["success"=>true,"error"=>null]];
	}

	/**
	 * 处理移动
	 *
	 * @param array $file 文件路径列表
	 * @param array $dir  目录路径列表
	 * @param string $new 新路径
	 * @param int $uid    用户ID
	 * @return void
	 */
	static function MoveHandler($file,$dirs,$new){
		if($new == ''){
			error('目标目录不存在', 404);
		}

		$NewPath = Db::name('folders')->where('position', $new)->find();
		if(!$NewPath){
			error('目标目录不存在', 404);
		}

		// 移动文件
		$error = [];
		$success = [];
		foreach ($dirs as $item) {
			$dirInfo = Db::name('folders')->where('d_id', $item)->find();
			if(!$dirInfo) {
				$error[] = [
					'id' => $item,
					'type' => 'dir',
					'status' => 'error',
					'msg' => '目录不存在'
				];
			}else if($dirInfo['position'] == $new) {
                $error[] = [
                    'id' => $item,
                    'type' => 'dir',
                    'status' => 'error',
                    'msg' => '路径未改变'
                ];
            } else {
                $isNewFolder = Db::name('folders')->where(['position' => $new . '/' . $dirInfo['title']])->find();
                if($isNewFolder) {
                    $error[] = [
                        'id' => $item,
                        'type' => 'dir',
                        'status' => 'error',
                        'msg' => '目标已存在'
                    ];
                }
                // 开始移动文件夹
                if(Db::name('folders')->where('id' , $dirInfo['id'])->update(['position'=>$new . '/' . $dirInfo['title'],'parent_folder' => $NewPath['id'],'lase_update_time'=>time()])) {
                    $success[] = [
                        'id' => $item,
                        'type' => 'dir',
                        'status' => 'success',
                        'msg' => '移动成功'
                    ];
                } else {
                    $error[] = [
                        'id' => $item,
                        'type' => 'dir',
                        'status' => 'error',
                        'msg' => '移动失败'
                    ];
                }
            }
		}

		foreach ($file as $item) {
			$id = substr($item,2);
			$fileInfo = Db::name('files')->where(['id' => $id])->find();
			if(!$fileInfo) {
				$error[] = [
					'id' => $item,
					'type' => 'file',
					'status' => 'error',
					'msg' => '文件不存在'
				];
			} else {
				$isNewFile = Db::name('files')->where(['dir' => $new . '/' . $fileInfo['title']])->find();
				if($isNewFile) {
					$error[] = [
						'id' => $item,
						'type' => 'file',
						'status' => 'error',
						'msg' => '目标已存在'
					];
				}

				// 开始移动文件
				if(Db::name('files')->where('id' , $fileInfo['id'])->update(['dir'=>$new,'parent_folder' => $NewPath['id'],'dir_id' => $NewPath['id'],'lase_update_time'=>time()])) {
					$success[] = [
						'id' => $item,
						'type' => 'file',
						'status' => 'success',
						'msg' => '移动成功'
					];
				} else {
					$error[] = [
						'id' => $item,
						'type' => 'file',
						'status' => 'error',
						'msg' => '移动失败'
					];
				}
			}
		}

		success([
            'success' => $success,
            'error' => $error
        ]);
		foreach ($file as $key => $value) {
			$fileInfo = self::getFileName($value);
			$moveName[$key] = $fileInfo[0];
			$movePath[$key] = $fileInfo[1];
		}
		$dirName=[];
		$dirPa=[];
		foreach ($dir as $key => $value) {
			$dirInfo = self::getFileName($value);
			$dirName[$key] = $dirInfo[0];
			$dirPar[$key] = $dirInfo[1];
		}
		$nameCheck = Db::name('files')->where([
			'upload_user' => $uid,
			'dir' => $new,
			'orign_name' =>["in",$moveName],
		])->find();
		$dirNameCheck = array_merge($dirName,$moveName);
		$dirCheck = Db::name('folders')->where([
			'owner' => $uid,
			'position' => $new,
			'folder_name' =>["in",$dirNameCheck],
		])->find();
		if($nameCheck || $dirCheck){
			die('{ "result": { "success": false, "error": "文件名冲突，请检查是否重名" } }');
		}
		if(!empty($dir)){
			die('{ "result": { "success": false, "error": "暂不支持移动目录" } }');
		}
		Db::name('files')->where([
			'upload_user' => $uid,
			'dir' => ["in",$movePath],
			'orign_name' =>["in",$moveName],
		])->update([
			'dir'=> $new,
			"parent_folder" => $newFolder["id"]
		]);
		echo ('{ "result": { "success": true} }');
	}

	/**
	 * ToDo 移动文件
	 *
	 * @param array $file
	 * @param string $path
	 * @return void
	 */
	static function moveFile($file,$path){

	}

	static function deleteFileRecord($id,$size,$uid){
		Db::name('files')->where([
		'id' => ["in",$id],
		])->delete();
		Db::name('shares')
		->where(['owner' => $uid])
		->where(['source_type' => "file"])
		->where(['source_name' => ["in",$id],])
		->delete();
		Db::name('users')->where([
		'id' => $uid,
		])->setDec('used_storage', $size);
	}

	static function filterFile($keyWords,$uid){
		switch ($keyWords) {
			case '{filterType:video}':
				$fileList = Db::name('files')
				->where(function ($query)use($uid) {
					$query->where('upload_user',$uid);
				})
				->where(function ($query) {
					$query->where('orign_name',"like","%.mp4")
					->whereOr('orign_name',"like","%.flv")
					->whereOr('orign_name',"like","%.avi")
					->whereOr('orign_name',"like","%.wmv")
					->whereOr('orign_name',"like","%.mkv")
					->whereOr('orign_name',"like","%.rm")
					->whereOr('orign_name',"like","%.rmvb")
					->whereOr('orign_name',"like","%.mov")
					->whereOr('orign_name',"like","%.ogv");
				})
				->select();
				break;
			case '{filterType:audio}':
				$fileList = Db::name('files')
				->where(function ($query)use($uid) {
					$query->where('upload_user',$uid);
				})
				->where(function ($query) {
					$query->where('orign_name',"like","%.mp3")
					->whereOr('orign_name',"like","%.flac")
					->whereOr('orign_name',"like","%.ape")
					->whereOr('orign_name',"like","%.wav")
					->whereOr('orign_name',"like","%.acc")
					->whereOr('orign_name',"like","%.ogg");
				})
				->select();
				break;
			case '{filterType:image}':
				$fileList = Db::name('files')
				->where(function ($query)use($uid) {
					$query->where('upload_user',$uid);
				})
				->where(function ($query) {
					$query->where('orign_name',"like","%.bmp")
					->whereOr('orign_name',"like","%.flac")
					->whereOr('orign_name',"like","%.iff")
					->whereOr('orign_name',"like","%.png")
					->whereOr('orign_name',"like","%.gif")
					->whereOr('orign_name',"like","%.jpg")
					->whereOr('orign_name',"like","%.jpge")
					->whereOr('orign_name',"like","%.psd")
					->whereOr('orign_name',"like","%.svg")
					->whereOr('orign_name',"like","%.webp");
				})
				->select();
				break;
			case '{filterType:doc}':
				$fileList = Db::name('files')
				->where(function ($query)use($uid) {
					$query->where('upload_user',$uid);
				})
				->where(function ($query) {
					$query->where('orign_name',"like","%.txt")
					->whereOr('orign_name',"like","%.md")
					->whereOr('orign_name',"like","%.pdf")
					->whereOr('orign_name',"like","%.doc")
					->whereOr('orign_name',"like","%.docx")
					->whereOr('orign_name',"like","%.ppt")
					->whereOr('orign_name',"like","%.pptx")
					->whereOr('orign_name',"like","%.xls")
					->whereOr('orign_name',"like","%.xlsx");
				})
				->select();
				break;
			default:
				$fileList = [];
				break;
		}
		return $fileList;
	}

	static function searchFile($keyWords,$uid){
		if (0 === strpos($keyWords, '{filterType:')) {
			$fileList = self::filterFile($keyWords,$uid);
		}else{
			$fileList = Db::name('files')
			->where('upload_user',$uid)
			->where('orign_name',"like","%$keyWords%")
			->select();
		}
		
		$count= 0;
		$fileListData=[
			"result"=>[],
		];
		foreach ($fileList as $key => $value) {
			$fileListData['result'][$count]['name'] = $value['orign_name'];
			$fileListData['result'][$count]['rights'] = "drwxr-xr-x";
			$fileListData['result'][$count]['size'] = $value['size'];
			$fileListData['result'][$count]['date'] = $value['upload_date'];
			$fileListData['result'][$count]['type'] = 'file';
			$fileListData['result'][$count]['name2'] = $value["dir"];
			$fileListData['result'][$count]['id'] = $value["id"];
			$fileListData['result'][$count]['pic'] = $value["pic_info"];
			$fileListData['result'][$count]['path'] = $value['dir'];
			$count++;
		}
	
		return $fileListData;
	}

	/**
	 * 列出文件
	 *
	 * @param 路径 $path
	 * @param boolean $isShare	是否为分享模式下列出文件
	 * @return void
	 */
	static function ListFile($path,$action='list',$isShare=false,$originPath=null){
		$fileList = [];
		$dirList = [];
		$fileCount = 0;
		$folderCount = 0;

		$p_folder = Db::name('folders')->where('position',$path)->find();
		if(!$p_folder || $p_folder['is_del'] == 1) {
			error('目录不存在', 404);
		}
		$pid = $p_folder['id'];

		if($action == 'dir-list') {
			$folderCount = Db::name('folders')->where('is_del', 0)->where('pid',$pid)->where('pid', '>', 0)->count();
			$dirList = Db::name('folders')->where('is_del', 0)->where('pid',$pid)->where('pid', '>', 0)->select();
		} else {
			$fileCount = Db::name('files')->where('is_del', 0)->where('dir',$path)->count();
			$folderCount = Db::name('folders')->where('is_del', 0)->where('pid',$pid)->where('pid', '>', 0)->count();

			$fileList = Db::name('files')->where('is_del', 0)->where('dir',$path)->select();
			$dirList = Db::name('folders')->where('is_del', 0)->where('pid',$pid)->where('pid', '>', 0)->select();
		}

		$count= 0;
		$folders = [];
		$lists = [];
		foreach ($dirList as $key => $value) {
			$folder = $value;
			$folder['type'] = 'dir';
			$folder['lase_update_time'] = formatTime($folder['lase_update_time']);
			$folder['id'] = 'd-'.$folder['d_id'];
			unset($folder['d_id']);
			if($action == 'dir-list') {
				$folder['dirLoc'] = $value['d_id'];
				$folder['children'] = [];
				$folder['showChildren'] = false;
				$folder['showThisToLastIcon'] = true;
			}

			if($isShare){
				if (substr($value['position'], 0, strlen($originPath)) == $originPath) {
					$value['position'] = substr($value['position'], strlen($originPath));
				}
				$folder['path'] = ($value['position']=="")?"/":$value['position'];
			}
			$lists[] = $folder;
		}

		$files = [];
		foreach ($fileList as $key => $value) {
			$ext = explode('.', $value['orign_name']);
			$ext = $ext[count($ext)-1];
			$file = $value;
			$file['type'] = 'file';

			if($isShare){
				if (substr($value['dir'], 0, strlen($originPath)) == $originPath) {
					$value['dir'] = substr($value['dir'], strlen($originPath));
				}
				$file['path'] = ($value['dir']=="")?"/":$value['dir'];
			}

			$lists[] = $file;
		}

		return [
			'total' => $fileCount + $folderCount,
			'lists' => $lists
		];
	}

	static function listPic($path,$uid,$url="/File/Preview?"){
		$firstPreview = self::getFileName($path);
		$path=$firstPreview[1];
		$fileList = Db::name('files')
		->where('upload_user',$uid)
		->where('dir',$path)
		->where('pic_info',"<>"," ")
		->where('pic_info',"<>","0,0")
		->where('pic_info',"<>","null,null")
		->select();
		$count= 0;
		$fileListData=[];
		foreach ($fileList as $key => $value) {
			if($value["orign_name"] == $firstPreview[0]){
				$previewPicInfo = explode(",",$value["pic_info"]);
				$previewSrc = $url."action=preview&path=".urlencode($path."/".$value["orign_name"]);
			}else{
				$picInfo = explode(",",$value["pic_info"]);
				$fileListData[$count]['src'] = $url."action=preview&path=".$path."/".$value["orign_name"];
				$fileListData[$count]['w'] = 0;
				$fileListData[$count]['h'] = 0;
				$fileListData[$count]['title'] = $value["orign_name"];
				$count++;
			}
		}
		array_unshift($fileListData,array(
			'src' => $previewSrc,
			'w' => 0,
			'h' => 0,
			'title' => $firstPreview[0],
			));
		return $fileListData;
	}

	/**
	 * [createFolder description]
	 * @param  [type] $dirName     [description]
	 * @param  [type] $dirPosition [description]
	 * @param  [type] $uid         [description]
	 * @return [type]              [description]
	 */
	static function createFolder($dirName,$dirPosition,$uid){
		$dirName = str_replace(" ","",$dirName);
		$dirName = str_replace("/","",$dirName);
		if(empty($dirName)){
			return ["result"=>["success"=>false,"error"=>"目录名不能为空"]];
		}
		if(Db::name('folders')->where('position_absolute',$dirPosition)->where('owner',$uid)->find() ==null || Db::name('folders')->where('owner',$uid)->where('position',$dirPosition)->where('folder_name',$dirName)->find() !=null || Db::name('files')->where('upload_date',$uid)->where('dir',$dirPosition)->where('pre_name',$dirName)->find() !=null){
			return ["result"=>["success"=>false,"error"=>"路径不存在或文件已存在"]];
		}
		$sqlData = [
			'folder_name' => $dirName,
			'parent_folder' => Db::name('folders')->where('position_absolute',$dirPosition)->value('id'),
			'position' => $dirPosition,
			'owner' => $uid,
			'date' => date("Y-m-d H:i:s"),
			'position_absolute' => ($dirPosition == "/")?($dirPosition.$dirName):($dirPosition."/".$dirName),
			];
		if(Db::name('folders')->insert($sqlData)){
			return ["result"=>["success"=>true,"error"=>null]];
		}

	}

	static function getTotalStorage($uid){
		$userData = Db::name('users')->where('id',$uid)->find();
		$basicStronge = Db::name('groups')->where('id',$userData['user_group'])->find();
		$addOnStorage = Db::name('storage_pack')
		->where('uid',$uid)
		->where('dlay_time',">",time())
		->sum('pack_size');
		return $addOnStorage+$basicStronge["max_storage"];
	}

	static function getUsedStorage($uid){
		$userData = Db::name('users')->where('id',$uid)->find();
		return $userData['used_storage'];
	}

	static function sotrageCheck($uid,$fsize){
		$totalStorage = self::getTotalStorage($uid);
		$usedStorage = self::getUsedStorage($uid);
		return ($totalStorage > ($usedStorage + $fsize)) ? True : False;
	}

	static function storageCheckOut($uid,$size){
		Db::name('users')->where('id',$uid)->setInc('used_storage',$size);
	}

	static function storageGiveBack($uid,$size){
		Db::name('users')->where('id',$uid)->setDec('used_storage',$size);
	}

	static function addFile($jsonData,$policyData,$uid,$picInfo=" "){
		$dir = "/".str_replace(",","/",$jsonData['path']);
		$fname = $jsonData['fname'];
		if(self::isExist($dir,$fname,$uid)){
			return[false,"文件已存在"];
		}
		$folderBelong = Db::name('folders')->where('owner',$uid)->where('position_absolute',$dir)->find();
		if($folderBelong ==null){
			return[false,"目录不存在"];
		}
		$sqlData = [
			'orign_name' => $jsonData['fname'],
			'pre_name' => $jsonData['objname'],
			'upload_user' => $uid,
			'size' => $jsonData['fsize'],
			'upload_date' => date("Y-m-d H:i:s"),
			'parent_folder' => $folderBelong['id'],
			'policy_id' => $policyData['id'],
			'dir' => $dir,
			'pic_info' => $picInfo,
		];
		if(Db::name('files')->insert($sqlData)){
			return [true,"上传成功"];
		}

	}

	static function isExist($dir,$fname,$uid){
		if(Db::name('files')->where('upload_user',$uid)->where('dir',$dir)->where('orign_name',$fname)->find() !=null){
			return true;
		}else{
			return false;
		}
	}

	static function deleteFile($fname,$policy){
		switch ($policy['policy_type']) {
			case 'qiniu':
				return QiniuAdapter::deleteSingle($fname,$policy);
				break;
			case 'oss':
				return OssAdapter::deleteOssFile($fname,$policy);
				break;
			case 'upyun':
				return UpyunAdapter::deleteUpyunFile($fname,$policy);
				break;
			case 's3':
				return S3Adapter::deleteS3File($fname,$policy);
				break;
			default:
				# code...
				break;
		}
	}

	static function uniqueArray($data = array()){
		$tempList = [];
		$qiniuList = [];
		$qiniuPolicyData = [];
		$localList = [];
		$localPolicyData = [];
		$ossList = [];
		$ossPolicyData = [];
		$upyunList = [];
		$upyunPolicyData = [];
		$s3List = [];
		$s3PolicyData = [];
		$remoteList = [];
		$remotePolicyData = [];
		$onedriveList = [];
		$onedrivePolicyData = [];
		foreach ($data as $key => $value) {
			if(!in_array($value['policy_id'],$tempList)){
				array_push($tempList,$value['policy_id']);
				$policyTempData = Db::name('policy')->where('id',$value['policy_id'])->find();
				switch ($policyTempData["policy_type"]) {
					case 'qiniu':
						array_push($qiniuList,$value['policy_id']);
						if(empty($qiniuPolicyData[$value['policy_id']])){
							$qiniuPolicyData[$value['policy_id']] = [];
						}
						array_push($qiniuPolicyData[$value['policy_id']],$policyTempData);
						break;
					case 'local':
						array_push($localList,$value['policy_id']);
						if(empty($localPolicyData[$value['policy_id']])){
							$localPolicyData[$value['policy_id']] = [];
						}
						array_push($localPolicyData[$value['policy_id']],$policyTempData);
						break;
					case 'oss':
						array_push($ossList,$value['policy_id']);
						if(empty($ossPolicyData[$value['policy_id']])){
							$ossPolicyData[$value['policy_id']] = [];
						}
						array_push($ossPolicyData[$value['policy_id']],$policyTempData);
						break;
					case 'upyun':
						array_push($upyunList,$value['policy_id']);
						if(empty($upyunPolicyData[$value['policy_id']])){
							$upyunPolicyData[$value['policy_id']] = [];
						}
						array_push($upyunPolicyData[$value['policy_id']],$policyTempData);
						break;
					case 's3':
						array_push($s3List,$value['policy_id']);
						if(empty($s3PolicyData[$value['policy_id']])){
							$s3PolicyData[$value['policy_id']] = [];
						}
						array_push($s3PolicyData[$value['policy_id']],$policyTempData);
						break;
					case 'remote':
						array_push($remoteList,$value['policy_id']);
						if(empty($remotePolicyData[$value['policy_id']])){
							$remotePolicyData[$value['policy_id']] = [];
						}
						array_push($remotePolicyData[$value['policy_id']],$policyTempData);
						break;
					case 'onedrive':
						array_push($onedriveList,$value['policy_id']);
						if(empty($onedrivePolicyData[$value['policy_id']])){
							$onedrivePolicyData[$value['policy_id']] = [];
						}
						array_push($onedrivePolicyData[$value['policy_id']],$policyTempData);
						break;
					default:
						# code...
						break;
				}
			}
		}
		$returenValue=array(
			'policyId' => $tempList ,
			'qiniuList' => $qiniuList,
			'qiniuPolicyData' => $qiniuPolicyData,
			'localList' => $localList,
			'localPolicyData' => $localPolicyData,
			'ossList' => $ossList,
			'ossPolicyData' => $ossPolicyData,
			'upyunList' => $upyunList,
			'upyunPolicyData' => $upyunPolicyData,
			's3List' => $s3List,
			's3PolicyData' => $s3PolicyData,
			'remoteList' => $remoteList,
			'remotePolicyData' => $remotePolicyData,
			'onedriveList' => $onedriveList,
			'onedrivePolicyData' => $onedrivePolicyData,
		);
		return $returenValue;
	}

	public function signTmpUrl(){
		return $this->adapter->signTmpUrl()[1];
	}

    /**
     * 判断文件是否已经上传
     * @param $md5 文件md5
     * @param $chunks chunk总数
     * @param int $chunk 当前chunk序号
     */
    static function checkFileIsSucc($md5, $chunks, $chunk=1, $totalSize, $currentChunkSize) {

	    // 判断秒传
        $isUploadSucc = Db::name('files')->where('file_md5', $md5)->find();
        if($isUploadSucc) {
            // 已经上传过了。

            return [
                'success' => true,
                'skipUpload' => true
            ];
        }

        // 判断断点续传
        $uploadChunksData = Db::name('chunks')->where('file_md5', $md5)->order('chunk_id desc')->select();

        $notUploadIndex = [];
        for ($i = 1; $i <= $chunks; $i++) {
            $isUploaded = false;
            foreach ($uploadChunksData as $key => $uploadChunksDatum) {
            	$_file = ROOT_PATH . 'public/uploads/chunks/'.$uploadChunksDatum['file_md5'].'/'.$uploadChunksDatum['ctx'].'.chunk';
            	$fileIsExt = is_file($_file);
                if($uploadChunksDatum['chunk_id'] == $i && $fileIsExt) {
                    $isUploaded = true;
                    unset($uploadChunksData[$key]);
                }

                if($fileIsExt == false) {
					Db::name('chunks')->where('id', $uploadChunksDatum['id'])->delete();
				}
            }

            if($isUploaded == true) {
                $notUploadIndex[] = $i;
            }
        }

        return [
            'success' => false,
            'uploaded' => $notUploadIndex
        ];
    }
}
?>
