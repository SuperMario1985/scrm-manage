<?php
	/**
	 * 企业微信素材库
	 * User: xingchanngyu
	 * Date: 2020/01/08
	 * Time: 09:24
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\Attachment;
	use app\models\WorkArticle;
	use app\models\WorkMaterial;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\UploadFileUtil;
	use app\util\WorkUtils;
	use yii\web\MethodNotAllowedHttpException;
	use dovechen\yii2\weWork\ServiceWork;

	class WorkMaterialController extends WorkBaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-material/
		 * @title           素材列表
		 * @description     素材列表
		 * @method   post
		 * @url  http://{host_name}/api/work-material/list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param material_type 必选 int 素材类型：1、图文（articles）；2、图片（image）；3、语音（voice）；4、视频（video）；5、文件（file)、6：文本（text）、7：小程序（miniprogram）；
		 * @param page 可选 int 页数，默认为1
		 * @param pageSize 可选 int 每页个数，默认10
		 *
		 * @return          {"error":0,"data":{"count":"1","material":[{"id":"1","key":"1","material_type":"6","file_name":"","local_path":null,"create_time":"2020-01-10 14:02:40","media_width":"","media_height":"","media_duration":"","content":"12月27日，国内可看到不同程度的日偏食。日食，通常可分为日全食、日环食和日偏食三种。日偏食，可分为三个阶段：初亏、食甚和复圆。今天，广州初亏12时15分；食甚13时51分，最大食分0.440；复圆15时18分。观测日食必须采用滤光片，要十分注意眼睛的安全。","appId":"","appPath":"","jump_url":null}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数据数量
		 * @return_param    material array 数据列表
		 * @return_param    id string 素材id
		 * @return_param    key string 键值
		 * @return_param    material_type string 素材类型：1、图文（articles）；2、图片（image）；3、语音（voice）；4、视频（video）；5、文件（file)、6：文本（text）、7：小程序（miniprogram）；
		 * @return_param    file_name string 素材名称或标题
		 * @return_param    local_path string 素材本地地址
		 * @return_param    create_time string 创建时间
		 * @return_param    media_width string 素材宽度
		 * @return_param    media_height string 素材高度
		 * @return_param    media_duration string 素材时长秒
		 * @return_param    content string 对于文本类型，content是文本内容，对于图文类型，content是图文描述，，对于小程序类型，content是图片的pic_media_id
		 * @return_param    appId string 小程序appid
		 * @return_param    appPath string 小程序page路径
		 * @return_param    jump_url string 图文的跳转地址
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-01-10 9:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$material_type = \Yii::$app->request->post('material_type', 0);
			if (empty($this->corp) || empty($material_type)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (!in_array($material_type, [1, 2, 3, 4, 5, 6, 7])) {
				throw new InvalidDataException('素材类型不正确！');
			}

			$workMaterialData = WorkMaterial::find()->where(['corp_id' => $this->corp->id, 'material_type' => $material_type, 'status' => 1]);

			//分页
			$page             = \Yii::$app->request->post('page', 1);
			$pageSize         = \Yii::$app->request->post('pageSize', 10);
			$offset           = ($page - 1) * $pageSize;
			$count            = $workMaterialData->count();
			$select           = 'id,id as `key`,material_type,file_name,local_path,create_time,media_width,media_height,media_duration,content,appId,appPath,jump_url';
			$workMaterialData = $workMaterialData->select($select)->orderBy('id desc');
			$workMaterialData = $workMaterialData->limit($pageSize)->offset($offset)->asArray()->all();

			return [
				'count'    => $count,
				'material' => $workMaterialData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-material/
		 * @title           上传素材
		 * @description     上传素材
		 * @method   post
		 * @url  http://{host_name}/api/work-material/upload-media
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param attachment_id 必选 string 附件id
		 *
		 * @return          {"error":0,"data":["id":"610","file_name":"123456","local_path":"/upload/images/20200107/15783645185e13ee66a50e2.jpg"]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 本地素材id
		 * @return_param    file_name string 素材名称
		 * @return_param    local_path string 素材本地地址
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-01-09 16:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUploadMedia ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$attachment_id = \Yii::$app->request->post('attachment_id', 0);
			if (empty($attachment_id)) {
				throw new InvalidDataException('请传附件id！');
			}
			$attachment = Attachment::findOne($attachment_id);
			if (empty($attachment)) {
				throw new InvalidDataException('无此附件，请检查！');
			}
			//类型如果是图文则返回对应数据，无需上传
			if ($attachment->file_type == 4) {
				$artList[] = [
					'title'              => $attachment->file_name,
					'digest'             => $attachment->content,
					'local_path'         => $attachment->local_path,
					'content_source_url' => $attachment->jump_url,
					'qy_local_path'      => !empty($attachment->qy_local_path) ? $attachment->qy_local_path : $attachment->local_path
				];

				return ['id' => $attachment->id, 'artList' => $artList];
			}
			//其他类型，如果企业素材库有附件id，则直接返回
			$workMaterialInfo = WorkMaterial::findOne(['corp_id' => $this->corp->id, 'attachment_id' => $attachment_id, 'status' => 1]);
			if (!empty($workMaterialInfo)) {
				return ['id' => $attachment->id, 'file_name' => $attachment->file_name, 'local_path' => $attachment->local_path];
			}

			if (!in_array($attachment->file_type, [1, 2, 3, 5])) {
				throw new InvalidDataException('此类型不支持上传！');
			}

			$appPath           = \Yii::getAlias('@app');
			$filePath          = $appPath . $attachment->local_path;
			$file_content_type = $attachment->file_content_type;
			$local_path        = $attachment->local_path;
			if ($attachment->file_type == 1) {//图片
				$material_type = 2;
				$fileType      = 'image';
			} elseif ($attachment->file_type == 2) {//音频
				if ($attachment->file_duration > '00:01:00') {
					throw new InvalidDataException('音频的播放长度不能超过60s');
				}
				$material_type = 3;
				$fileType      = 'voice';
//				$amrPath       = explode('.', $attachment->local_path)[0] . '.amr';
//				$filePath      = $appPath . $amrPath;
//				if (file_exists($filePath)) {
//					unlink($filePath);
//				}
//				shell_exec('ffmpeg -i ' . $appPath . $attachment->local_path . '  -ar 8000 ' . $filePath);
//				$file_content_type = 'audio/amr';
//				$local_path        = $amrPath;
			} elseif ($attachment->file_type == 3) {//视频
				$material_type = 4;
				$fileType      = 'video';
			} elseif ($attachment->file_type == 5) {//文件
				$material_type = 5;
				$fileType      = 'file';
			}

			$transaction = \Yii::$app->db->beginTransaction();
			try {
				//上传临时素材
				$serviceWork = WorkUtils::getWorkApi($this->corp->id);
//				$serviceWork = \Yii::createObject([
//					'class'          => ServiceWork::className(),
//					'suite_id'       => $this->corp->suite->suite_id,
//					'suite_secret'   => $this->corp->suite->suite_secret,
//					'suite_ticket'   => $this->corp->suite->suite_ticket,
//					'auth_corpid'    => $this->corp->corpid,
//					'permanent_code' => $this->corp->permanent_code,
//				]);
				//上传企业微信素材
				$media_id = $serviceWork->MediaUpload($filePath, $fileType);
				$time     = time();
				//添加
				$workMaterial                 = new WorkMaterial();
				$workMaterial->corp_id        = $this->corp->id;
				$workMaterial->media_id       = $media_id;
				$expire                       = $time + 259200;//三天后时间戳
				$workMaterial->expire         = (string) $expire;
				$workMaterial->type           = 0;
				$workMaterial->material_type  = $material_type;
				$workMaterial->file_name      = $attachment->file_name;
				$workMaterial->file_length    = $attachment->file_length;
				$workMaterial->content_type   = $file_content_type;
				$workMaterial->local_path     = $local_path;
				$workMaterial->media_width    = $attachment->file_width;
				$workMaterial->media_height   = $attachment->file_height;
				$workMaterial->media_duration = $attachment->file_duration;
				$workMaterial->created_at     = $time;
				$workMaterial->create_time    = DateUtil::getCurrentTime();
				$workMaterial->attachment_id  = $attachment_id;
				if (!$workMaterial->validate() || !$workMaterial->save()) {
					throw new InvalidDataException(SUtils::modelError($workMaterial));
				}

				$transaction->commit();
			} catch (\Exception $e) {
				$transaction->rollBack();
				\Yii::error($e->getMessage(),'work-material');
				throw new InvalidDataException("当前素材不合法，请重新选择或上传");
			}

			return ['id' => $attachment->id, 'file_name' => $attachment->file_name, 'local_path' => $attachment->local_path];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-material/
		 * @title           添加本地素材
		 * @description     添加本地素材
		 * @method   post
		 * @url  http://{host_name}/api/work-material/add-material
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param material_type 必选 string 素材类型：1：图文（articles）、6：文本（text）、7：小程序（miniprogram）
		 * @param material_id 可选 string 素材id,修改时必选
		 * @param msgData 可选 array 素材类型=1时必选
		 * @param msgData.title 可选 string 图文标题
		 * @param msgData.content 可选 string 图文描述
		 * @param msgData.pic_url 可选 string 图片封面
		 * @param msgData.jump_url 可选 string 跳转链接
		 * @param content 可选 string 文本内容，素材类型=6时必选
		 * @param appData 可选 array 小程序数据，素材类型=7时必选
		 * @param appData.appId 可选 string 小程序appid
		 * @param appData.appPath 可选 string 小程序page路径
		 * @param appData.title 可选 string 卡面标题
		 * @param appData.pic_url 可选 string 卡面图片
		 * @param appData.media_id 可选 string 图片media_id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-01-10 10:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\db\Exception
		 */
		public function actionAddMaterial ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			//素材类型
			$material_type = \Yii::$app->request->post('material_type', 0);
			if (!in_array($material_type, [1, 6, 7])) {
				throw new InvalidDataException('参数不正确！');
			}
			$material_id = \Yii::$app->request->post('material_id', 0);
			$site_url    = \Yii::$app->params['site_url'];
			try {
				if (!empty($material_id)) {
					$workMaterial = WorkMaterial::findOne($material_id);
				} else {
					$workMaterial                = new WorkMaterial();
					$workMaterial->corp_id       = $this->corp->id;
					$workMaterial->material_type = $material_type;
					$workMaterial->type          = 1;
					$workMaterial->create_time   = DateUtil::getCurrentTime();
				}

				if ($material_type == 1) {
					$msgData = \Yii::$app->request->post('msgData', []);
					if (empty($msgData)) {
						throw new InvalidDataException('请填写图文信息！');
					}
					$title    = trim($msgData['title']);
					$content  = trim($msgData['content']);
					$pic_url  = trim($msgData['pic_url']);
					$jump_url = trim($msgData['jump_url']);
					if (empty($title)) {
						throw new InvalidDataException('请填写标题！');
					} elseif (mb_strlen($title, 'utf-8') > 64) {
						throw new InvalidDataException('标题不能超过64个字符！');
					}
					if (empty($pic_url)) {
						throw new InvalidDataException('请选择图片封面！');
					}
					if (!empty($content) && mb_strlen($content, 'utf-8') > 255) {
						throw new InvalidDataException('描述不能超过255个字符！');
					}
					if (empty($jump_url)) {
						throw new InvalidDataException('请填写跳转链接！');
					} else {
						$content_url = strtolower($jump_url);
						$pattern     = '/(http|https)(.)*([a-z0-9\-\.\_])+/i';
						if (!preg_match($pattern, $content_url)) {
							throw new InvalidDataException('跳转链接格式不正确！');
						}
					}
					$workMaterial->file_name  = $title;
					$workMaterial->content    = $content;
					$workMaterial->local_path = str_replace($site_url, '', $pic_url);
					$workMaterial->jump_url   = $jump_url;
				} elseif ($material_type == 6) {
					$content = \Yii::$app->request->post('content', '');
					$content = trim($content);
					if (empty($content)) {
						throw new InvalidDataException('请填写文本内容！');
					} elseif (mb_strlen($content, 'utf-8') > 200) {
						throw new InvalidDataException('文本内容不能超过200个字符！');
					}
					$workMaterial->content = $content;
				} elseif ($material_type == 7) {
					$appData  = \Yii::$app->request->post('appData', []);
					$appId    = trim($appData['appId']);
					$appPath  = trim($appData['appPath']);
					$title    = trim($appData['title']);
					$media_id = trim($appData['media_id']);
					$pic_url  = trim($appData['pic_url']);
					if (empty($appId)) {
						throw new InvalidDataException('请填写小程序appid！');
					} elseif (mb_strlen($appId, 'utf-8') > 32) {
						throw new InvalidDataException('小程序appid不能超过32个字符！');
					}
					if (empty($appPath)) {
						throw new InvalidDataException('请填写小程序路径！');
					} elseif (mb_strlen($appPath, 'utf-8') > 64) {
						throw new InvalidDataException('小程序路径不能超过64个字符！');
					}
					if (empty($title)) {
						throw new InvalidDataException('请填写卡面标题！');
					} elseif (mb_strlen($title, 'utf-8') > 64) {
						throw new InvalidDataException('卡面标题不能超过64个字符！');
					}
					if (empty($pic_url)) {
						throw new InvalidDataException('请选择卡面图片！');
					}
					$workMaterial->file_name  = $title;
					$workMaterial->local_path = str_replace($site_url, '', $pic_url);
					$workMaterial->appId      = $appId;
					$workMaterial->appPath    = $appPath;
					$workMaterial->content    = $media_id;
				}
				if (!$workMaterial->validate() || !$workMaterial->save()) {
					throw new InvalidDataException(SUtils::modelError($workMaterial));
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-material/
		 * @title           素材删除
		 * @description     素材删除
		 * @method   post
		 * @url  http://{host_name}/modules/controller/actionDelete
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param material_id 必选 string 素材id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-01-09 20:26
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$material_id  = \Yii::$app->request->post('material_id', 0);
			$workMaterial = WorkMaterial::findOne($material_id);
			if (empty($workMaterial)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$workMaterial->status = 0;
			if (!$workMaterial->save()) {
				throw new InvalidDataException(SUtils::modelError($workMaterial));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-material/
		 * @title           上传群聊图片
		 * @description     上传群聊图片
		 * @method   post
		 * @url  http://{host_name}/api/work-material/upload-img
		 *
		 * @param uid 必选 int uid
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/4 9:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUploadImg ()
		{
			$flag = false;
			try {
				$uid      = \Yii::$app->request->post('uid');
				$from_tag = \Yii::$app->request->post('from_tag',0);

				if (empty($uid)) {
					throw new InvalidDataException("缺少必要参数");
				}
				if (empty($this->corp)) {
					throw new InvalidDataException('缺少必要参数！');
				}
				$saveDir                 = 'images';
				$maxSize                 = 2 * 1024 * 1024;
				$allowExts               = ['jpg', 'png', 'jpeg'];
				$allowTypes              = ['image/jpeg', 'image/png', 'application/octet-stream'];
				$uploadFileUtil          = new UploadFileUtil();
				$uploadFileUtil->saveDir = $saveDir . '/' . $uid;//上传文件保存路径
				$uploadFileUtil->maxSize = $maxSize;//大小限制
				if (isset($allowExts)) {
					$uploadFileUtil->allowExts = $allowExts;
				}
				if (isset($allowTypes)) {
					$uploadFileUtil->allowTypes = $allowTypes;
				}
				$result = $uploadFileUtil->upload();
				if (empty($result)) {
					$flag = true;
					throw new InvalidDataException($uploadFileUtil->getErrorMsg());
				}
				$uploadFileList = $uploadFileUtil->getUploadFileList();
				$uploadInfo     = $uploadFileList[0];
				$local_path     = $uploadInfo['local_path'];

				$length = mb_strlen($uploadInfo['name'], 'utf-8');
				if ($length > 128) {
					$file_name = mb_substr($uploadInfo['name'], 0, 128, 'utf-8');
				} else {
					$file_name = $uploadInfo['name'];
				}

				$serviceWork = WorkUtils::getWorkApi($this->corp->id);
				//上传企业微信素材
				$appPath  = \Yii::getAlias('@app');
				$filePath = $appPath . $local_path;

//				if ($from_tag == 1) {
//					$newImg = str_replace("png", "jpg", $filePath);
//					$resImg = imagecreatefrompng($filePath);
//					$resImg = imagejpeg($resImg, $newImg);
//					if ($resImg) {
//						$filePath = $newImg;
//					}
//				}

				$media_id = $serviceWork->MediaUpload($filePath, 'image');
				$time     = time();
				//添加
				$workMaterial                = new WorkMaterial();
				$workMaterial->corp_id       = $this->corp->id;
				$workMaterial->media_id      = $media_id;
				$expire                      = $time + 259200;//三天后时间戳
				$workMaterial->expire        = (string) $expire;
				$workMaterial->type          = 0;
				$workMaterial->material_type = 2;
				$workMaterial->file_name     = $file_name;
				$workMaterial->file_length   = $uploadInfo['size'];
				$workMaterial->content_type  = $uploadInfo['type'];
				$workMaterial->local_path    = $local_path;
				if (isset($uploadInfo['width'])) {
					$workMaterial->media_width = $uploadInfo['width'];
				}
				if (isset($uploadInfo['height'])) {
					$workMaterial->media_height = $uploadInfo['height'];
				}
				$workMaterial->created_at  = $time;
				$workMaterial->create_time = DateUtil::getCurrentTime();
				if (!$workMaterial->validate() || !$workMaterial->save()) {
					throw new InvalidDataException(SUtils::modelError($workMaterial));
				}

				return ['media_id' => $workMaterial->id, 'local_path' => $local_path, 'file_name' => $file_name];
			} catch (\Exception $e) {
				if (!$flag) {
					\Yii::error($e->getMessage(), 'ChatUploadImage');
					throw new InvalidDataException("上传失败");
				} else {
					throw new InvalidDataException($e->getMessage());
				}
			}
		}



	}