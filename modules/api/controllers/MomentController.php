<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/9/5
	 * Time: 16:37
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Attachment;
	use app\models\TempMedia;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkDepartment;
	use app\models\WorkMoments;
	use app\models\WorkMomentsAudit;
	use app\models\WorkMomentsBase;
	use app\models\WorkMomentSetting;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\ImageTextUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\src\dataStructure\User;
	use moonland\phpexcel\Excel;
	use yii\db\Exception;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;

	class MomentController extends WorkBaseController
	{
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$userId = \Yii::$app->request->post('user_id');
				$type   = \Yii::$app->request->post('type');
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moments-context-add
		 * @title           朋友圈创建
		 * @description     朋友圈创建
		 * @method   post
		 * @url  http://{host_name}/api/moment/moments-context-add
		 *
		 * @param is_master 必须 string 标题
		 * @param title_name 必须 string 标题
		 * @param corp_id   必须 string 企业微信id
		 * @param ownership 必须 array []代表全部归属成员
		 * @param condition 必须 array []空代表全部，不为空传条件数组
		 * @param condition.sex 必须 int 不为空定义数组中变量0-未知,1-男性,2-女性,3-不限
		 * @param condition.create 必须 array 不为空定义数组中变量添加外部联系人时间
		 * @param condition.create.start_time 必须 string 不为空定义数组中变量添加外部联系人開始时间
		 * @param condition.create.end_time 必须 string 不为空定义数组中变量添加外部联系人結束时间
		 * @param condition.simple 必须 array 不为空定义数组中变量添加外部联系人結束时间
		 * @param condition.simple.start_time 必须 string 不为空定义数组中变量上一次单聊开始时间
		 * @param condition.simple.end_time 必须 string 不为空定义数组中变量上一次单聊結束时间
		 * @param condition.follow 必须 array 不为空定义数组中最后跟进外部联系人时间
		 * @param condition.follow.start_time 必须 string 不为空定义数组中最后跟进外部联系人開始时间
		 * @param condition.follow.end_time 必须 string 不为空定义数组中最后跟进外部联系人結束时间
		 * @param condition.follow_sum  必须 array 不为空定义数组中示例{'min':1,'max':5}
		 * @param condition.binding     必须 int 不为空定义数组中绑定店铺id
		 * @param condition.area        必须 array 不为空定义数组中区域
		 * @param condition.status      必须 int 不为空定义数组中状态
		 * @param condition.fans        必须 int 不为空定义数组中是否粉丝
		 * @param condition.tags        必须 array 不为空定义数组中是否标签id
		 * @param advanced_setting      必须 int 提醒同步
		 * @param send_time             必须 string 群发时间null立即发送，不为null指定时间
		 * @param type                  必须 int 1、仅文本；2、图片；3、视频；4、链接
		 * @param context               必须 string 内容
		 * @param title                 必须 string 分享的标题
		 * @param description           必须 string 分享的描述
		 * @param url                   必须 string 分享的地址
		 * @param image_setting         必须 int 图文是否开启
		 * @param pic_url               必须 string 分享的图片地址
		 * @param image                 必须 array 分享的图片地址
		 * @param image.local_path      必须 string 图文|视频图片地址
		 * @param image.sort            必须 string 图文图片排序
		 * @param momentId              必须 string id
		 * @param is_edit               必须 string 0查看1编辑
		 * @param audit                 必须 int 来自审核
		 * @param reply                 必须 int 回复内容拒绝必须填
		 * @param status                必须 int 状态1通过3审核失败
		 *
		 * @return_param    error int 状态码
		 * @return_param    msg string  信息
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/5 13:56
		 */
		public function actionMomentsContextAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$data['corp_id']          = \Yii::$app->request->post("corp_id");
			$data['title']            = \Yii::$app->request->post("title");
			$data['ownership']        = \Yii::$app->request->post("ownership", []);
			$data['condition']        = \Yii::$app->request->post("condition", []);
			$data['advanced_setting'] = \Yii::$app->request->post("advanced_setting");
			$data['send_time']        = \Yii::$app->request->post("send_time");
			$data['type']             = \Yii::$app->request->post("type");
//			$data['open_range']       = \Yii::$app->request->post("open_range", []);
			$data["context"] = \Yii::$app->request->post("context");
			if (mb_strlen($data["context"]) > 500) {
				throw new InvalidDataException("内容超出限制");
			}
			$data['create_time'] = time();
			$info                = \Yii::$app->request->post("info");
			$momentId            = \Yii::$app->request->post("momentId");
			$is_edit             = \Yii::$app->request->post("is_edit");
			$data['status']      = $status = \Yii::$app->request->post("status", 1);
			$audit               = \Yii::$app->request->post("audit", 0);
			$reply               = \Yii::$app->request->post("reply");
			$data['sub_id']      = NULL;
			$data['is_mobile']   = 1;
			$corp                = WorkCorp::findOne(["corpid" => $data['corp_id']]);
			if ($status == 3 && $audit == 1 && empty($reply)) {
				throw new InvalidParameterException("审核失败时，回复内容不能为空");
			}
			if (empty($corp)) {
				throw new InvalidDataException("企业微信不存在");
			}
			$setting = WorkMomentSetting::findOne(["corp_id" => $corp->id]);
			if (empty($setting)) {
				throw new InvalidDataException("请先完善,通用设置");
			}
			$data['agent_id'] = $setting->agent_id;
			if (!empty($momentId) && empty($is_edit)) {
				$base = WorkMomentsBase::find()->where(["id" => $momentId, "corp_id" => $corp->id])->one()->toArray();
				if (!empty($base)) {
					$corp              = WorkCorp::findOne($base["corp_id"]);
					$base["corp_id"]   = $corp->corpid;
					$info              = Json::decode($base["info"]);
					$base["condition"] = Json::decode($base["condition"]);
					$base["ownership"] = Json::decode($base["ownership"]);
					foreach ($base["ownership"] as &$value) {
						if (!isset($value["title"])) {
							$value["title"] = $value["name"];
						}
						if (!isset($value["key"])) {
							$value["key"] = isset($value["user_key"]) ? $value["user_key"] : '';
						}
						if (!isset($value["scopedSlots"])) {
							$value["scopedSlots"] = ["title" => "custom"];
						}
					}
					WorkMomentsBase::getDataInfo($info, $base["type"]);
					$base["info"] = $info;

					return $base;
				}
				throw new InvalidDataException("该条内容不存在");
			}
			$data['corp_id'] = $corp->id;
			if (empty($data['title']) && empty($momentId) && empty($audit)) {
				throw new InvalidDataException("标题不能为空");
			}
			if (!empty($data['ownership'])) {
				$data['user_ids'] = array_column($data['ownership'], "id");
				$data['user_ids'] = implode(",", $data['user_ids']);
			}
			//暂时不需要条件
			$data['ownership'] = Json::encode($data['ownership'], JSON_UNESCAPED_UNICODE);
			$data['condition'] = Json::encode($data['condition'], JSON_UNESCAPED_UNICODE);
			if (isset($this->subUser->sub_id)) {
				$data['sub_id'] = $this->subUser->sub_id;
			}

			$pattern = "/^(http|https|Https):\/\/.*$/i";
			if ($data['type'] == 4) {
				//对于图文验证
				foreach ($info as &$value) {
					if (!preg_match($pattern, $value['url'])) {
						throw new InvalidDataException("链接不正确");
					}
					if (empty($value['url'])) {
						throw new InvalidDataException("分享链接不能为空");
					}
					if (empty($value['title'])) {
						throw new InvalidDataException("分享标题不能为空");
					}
					//防止科学计数
					$value['title'] .= ' ';
				}
			}
			$Transaction = \Yii::$app->db->beginTransaction();
			try {
				$data['info'] = Json::encode($info, JSON_UNESCAPED_UNICODE);
				if (!empty($momentId)) {
					$base = WorkMomentsBase::findOne(["id" => $momentId, "corp_id" => $corp->id]);
					if (empty($base)) {
						throw new InvalidDataException("该条内容不存在");
					}
					if ($base->send_success == 1 && empty($audit)) {
						throw new InvalidDataException("该条内容已发布");
					}
					if (\Yii::$app->cache->exists($base->id . "moment-add")) {
						$job_id = \Yii::$app->cache->get($base->id . "moment-add");
						\Yii::$app->work->remove($job_id);
						\Yii::$app->cache->delete($base->id . "moment-add");
					}
					unset($data['is_mobile']);
				} else {
					$base = new WorkMomentsBase();
				}
				$base->setAttributes($data);
				if (!$base->validate() || !$base->save()) {
					throw new InvalidDataException(SUtils::modelError($base));
				}
				//审核人信息
				if ($status == 3 && $audit == 1) {
					$auditData["base_id"] = $base->id;
					$auditData["reply"]   = $reply;
					if (isset($this->subUser->sub_id)) {
						$auditData['audit_people'] = $this->subUser->sub_id;
						$auditData["type"]         = 2;
					} else {
						$auditData['audit_people'] = $this->user->uid;
						$auditData["type"]         = 1;
					}
					$audit          = WorkMomentsAudit::setData($auditData);
					$base->audit_id = $audit->id;
					$base->context  = $data["context"];
					$base->save();
				}
				$res = WorkMomentsBase::setMomentContext($base, $setting->agent_id, $info);
				$Transaction->commit();
				if ($res == 2) {
					return ["error" => 0, "msg" => "内容将于 " . $base->send_time . " 发布"];
				}

			} catch (\Exception $e) {
				$Transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}

			return ["error" => 0, "msg" => "已发布"];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moments-lists
		 * @title           朋友圈列表
		 * @description     朋友圈创建
		 * @method   post
		 * @url  http://{host_name}/api/moment/moments-lists
		 *
		 * @param title         选填 string 标题
		 * @param status        选填 string 0全部，1已发送，2未发送
		 * @param page          必填
		 * @param page_size     必填
		 *
		 * @return_param    error int 状态码
		 * @return_param    title string 标题
		 * @return_param    ownership string 归属成员
		 * @return_param    context string 朋友圈内容
		 * @return_param    send_success int 0失败,1成功,2待发送
		 * @return_param    crate_time  string 发布时间
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/6 11:02
		 */
		public function actionMomentsLists ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id    = \Yii::$app->request->post("corp_id");
			$title      = \Yii::$app->request->post("title");
			$status     = \Yii::$app->request->post("status");
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$page       = ($page > 0) ? $page : 1;
			$offset     = ($page - 1) * $pageSize;
			$is_setting = 0;
			$corp       = WorkCorp::findOne(["corpid" => $corp_id]);
			if (empty($corp)) {
				throw new InvalidDataException("所选企业不存在");
			}
			$setting = WorkMomentSetting::findOne(["corp_id" => $corp->id]);
			if (!empty($setting)) {
				$is_setting = 1;
			}
			$lists = WorkMomentsBase::find()->alias("a")
				->leftJoin("{{%work_user}} as b", "a.user_id = b.id")
				->where(["a.is_del" => 0, "a.corp_id" => $corp->id, "a.status" => 1]);
			if (!empty($title) || $title == 0) {
				$lists->andWhere("a.title like '%$title%'");
			}
			if (!empty($status)) {
				$lists->andWhere(["a.send_success" => $status]);
			}
			$count = $lists->count();
			$lists = $lists->select("a.*,b.name")->groupBy("a.id")->orderBy("a.create_time desc")->offset($offset)->limit($pageSize)->asArray()->all();
			foreach ($lists as &$list) {
				$list["create_time"] = date("Y-m-d H:i", $list["create_time"]);
				$list["info"]        = Json::decode($list["info"]);
				WorkMomentsBase::getDataInfo($list["info"], $list["type"]);
				$list["context"]   = urldecode($list["context"]);
				$list["ownership"] = Json::decode($list["ownership"]);
				foreach ($list["ownership"] as &$value) {
					if (!isset($value["title"])) {
						$value["title"] = $value["name"];
					}
					if (!isset($value["key"])) {
						$value["key"] = isset($value["user_key"]) ? $value["user_key"] : '';
					}
					if (!isset($value["scopedSlots"])) {
						$value["scopedSlots"] = ["title" => "custom"];
					}
				}
				if ($list["is_mobile"] == 2) {
					array_push($list["ownership"], ["id" => $list["user_id"], "scopedSlots" => ["title" => "custom"], "title" => $list["name"]]);
				}
			}

			return ["data" => $lists, "count" => $count, "is_setting" => $is_setting];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/open-moments
		 * @title           批量开启朋友圈 废弃
		 * @description     批量开启朋友圈 废弃
		 * @method   post
		 * @url  http://{host_name}/api/moment/open-moments
		 *
		 * @param title 必须 string 标题
		 * @param web_title 必须 string 网页标题
		 * @param user_id 必须 array []开启成员
		 * @param see_url 必须 int 单个开启，弹窗显示成员url,直接开启0
		 * @param close   选填 int 是否关闭1关闭2不关闭
		 *
		 * @return_param    error int 状态码
		 * @return_param    msg string  信息
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/6 9:16
		 *
		 */
		public function actionOpenMoments ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id    = $this->corp->id;
			$userIds    = \Yii::$app->request->post("user_id");
			$title      = \Yii::$app->request->post("title");
			$webTitle   = \Yii::$app->request->post("web_title");
			$see_url    = \Yii::$app->request->post("see_url");
			$close      = \Yii::$app->request->post("close");
			$workApi    = WorkUtils::getWorkApi($corp_id);
			$errorNum   = 0;
			$successNum = 0;
			$webUrl     = \Yii::$app->params["web_url"];
			$agent      = WorkCorpAgent::findOne(["corp_id" => $corp_id]);
			if (count($userIds) == 1 && $see_url && empty($close)) {
				$user = WorkUser::findOne($userIds[0]);
				$url  = $webUrl . WorkMomentsBase::BASE_URL . '?user_id=' . $user->userid . "&corpid=" . $this->corp->corpid . "&agentId=" . $agent->id;

				return ["error" => 0, "url" => $url];
			}
			foreach ($userIds as $userId) {
				$workUser           = WorkUser::findOne($userId);
				$userData["userid"] = $workUser->userid;
				if ($close == 2) {
					$url = $webUrl . WorkMomentsBase::BASE_URL . "?user_id=" . $workUser->userid . "&corpid=" . $this->corp->corpid . "&agentId=" . $agent->id;
				} else {
					$url      = '';
					$title    = '';
					$webTitle = '';
				}
				$userData["external_profile"] = [
					"external_attr" => [
						[
							"type" => 1,
							"name" => $title,
							"web"  => [
								"title" => $webTitle,
								"url"   => $url
							],
						]
					]
				];
				$user                         = User::parseFromArray($userData);
				$res                          = $workApi->userUpdata($user);
				if ($res['errcode'] != 0) {
					++$errorNum;
					continue;
				}
				++$successNum;
			}
			if ($close == 2) {
				$str = "开启";
			} else {
				$str = "关闭";
			}

			return ["error" => 0, "msg" => "已" . $str . "成员" . $successNum . "个,其中" . $errorNum . "个" . $str . "失败"];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moments-setting
		 * @title           朋友圈通用设置
		 * @description     朋友圈通用设置
		 * @method   post
		 * @url  http://{host_name}/api/moment/moments-setting
		 *
		 * @param is_context 必须 int 员工是否允许发表内容0不允许1允许
		 * @param is_audit 必须 int 员工发表内容是否审核0不允许1允许
		 * @param agent_id 必须 int 应用id
		 * @param description 必须 string 个性签名
		 * @param is_description 必须 string 是否允许自定义个性签名
		 * @param banner_info 必须 string 默认背景
		 * @param banner_type 必须 int 0不允许1允许
		 * @param heard_img 必须 string 默认头像
		 * @param is_heard 必须 int 0不允许1允许
		 * @param is_check 必须 int 编辑传1获取设置
		 * @param setting_id 必须 int 编辑是传返回的ID
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/6 16:08
		 *
		 */
		public function actionMomentsSetting ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id             = $this->corp->id;
			$agent_id            = \Yii::$app->request->post("agent_id");
			$description         = \Yii::$app->request->post("description");
			$is_description      = \Yii::$app->request->post("is_description");
			$re["banner_info"]   = \Yii::$app->request->post("banner_info");
			$re["s_banner_info"] = \Yii::$app->request->post("s_banner_info");
			$is_context          = \Yii::$app->request->post("is_context", 0);
			$is_audit            = \Yii::$app->request->post("is_audit", 0);
			$banner_type         = \Yii::$app->request->post("banner_type");
			$heard_img           = \Yii::$app->request->post("heard_img");
			$is_heard            = \Yii::$app->request->post("is_heard");
			$is_check            = \Yii::$app->request->post("is_check");
			$settingId           = \Yii::$app->request->post("setting_id");
			if (!empty($is_check)) {
				$setting = WorkMomentSetting::find()->where(["corp_id" => $corp_id])->asArray()->one();
				if (!empty($setting)) {
					$banner_info = json_decode($setting["banner_info"], true);
					if (is_array($banner_info)) {
						$setting["banner_info"]   = is_null($banner_info["banner_info"]) ? '' : $banner_info["banner_info"];
						$setting["s_banner_info"] = is_null($banner_info["s_banner_info"]) ? '' : $banner_info["s_banner_info"];
					} else {
						$setting["s_banner_info"] = $setting["banner_info"] = empty($setting["banner_info"]) ? "" : $setting["banner_info"];
					}
				}

				return ["error" => 0, "data" => $setting];
			} elseif (!empty($settingId)) {
				$setting = WorkMomentSetting::findOne($settingId);
			} else {
				$setting = new WorkMomentSetting();
			}
			if (empty($agent_id)) {
				throw new InvalidDataException("应用未选择");
			}
			if ($is_heard == 0 && empty($heard_img)) {
				throw new InvalidDataException("头像未上传");
			}
			$setting->is_context     = $is_context;
			$setting->is_audit       = $is_audit;
			$setting->agent_id       = $agent_id;
			$setting->corp_id        = $corp_id;
			$setting->description    = $description;
			$setting->is_description = $is_description;
			$setting->banner_info    = Json::encode($re, JSON_UNESCAPED_UNICODE);
			$setting->banner_type    = $banner_type;
			$setting->heard_img      = $heard_img;;
			$setting->is_heard       = $is_heard;
			$setting->external_title = "朋友圈";
			if (!$setting->validate() || !$setting->save()) {
				throw new InvalidDataException(SUtils::modelError($setting));
			}

			return ["error" => 0, "success" => 1];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moments-images-text
		 * @title           获取分享内容
		 * @description     获取分享内容
		 * @method   post
		 * @url  http://{host_name}/api/moment/moments-images-text
		 *
		 * @param url 必须 string 地址
		 *
		 * @return_param    error int 状态码
		 * @return_param    error msg 信息
		 * @return_param    error title  网页标题
		 * @return_param    error description  网页描述
		 * @return_param    error url  网页图片
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/11 14:36
		 *
		 */
		public function actionMomentsImagesText ()
		{
			$url     = \Yii::$app->request->post("url");
			$pattern = "/^(http|https):\/\/.*$/i";
			try {
				if (preg_match($pattern, $url)) {
					return ImageTextUtil::getUrlAll($url);
				} else {
					throw new InvalidDataException("网址格式不正确");
				}
			} catch (\Exception $e) {
				throw new InvalidDataException($e->getMessage());
//				throw new InvalidDataException("网页抓取失败,请手动填写");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moments-upload
		 * @title           上传文件
		 * @description     上传文件
		 * @method   post
		 * @url  http://{host_name}/api/moment/moments-upload
		 *
		 * @param url 必须 string type 文件类型支持png,jpeg,mp4
		 * @param md5 必须 string 文件md5值
		 *
		 * @return_param    error int 状态码
		 * @return_param    local_path string 图片地址
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/11 14:36
		 */
		public function actionMomentsUpload ()
		{
			$type     = \Yii::$app->request->post("type");
			$md5      = \Yii::$app->request->post("md5");
			$is_heard = \Yii::$app->request->post("is_heard", 0);

			return TempMedia::UploadTempFile($type, $md5, false);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/check-moment
		 * @title           朋友圈审核
		 * @description     朋友圈审核
		 * @method   post
		 * @url  http://{host_name}/api/moment/check-moment
		 *
		 * @param base_id 必须 int 朋友圈id
		 * @param corpid 必须 string corpid
		 * @param status 必须 int   状态1通过3审核失败
		 * @param reply 必须 int   3审核失败必须填原因
		 *
		 * @return_param    error int 状态码
		 * @return_param    msg string 信息
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/11/6 13:36
		 */
		public function actionCheckMoment ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$base_id = \Yii::$app->request->post("base_id");
			$corpid  = \Yii::$app->request->post("corpid");
			$status  = \Yii::$app->request->post("status");
			$reply   = \Yii::$app->request->post("reply");
			if ($status == 3 && empty($reply)) {
				throw new InvalidParameterException("审核失败时，回复内容不能为空");
			}
			$WorkCorp = WorkCorp::findOne(["corpid" => $corpid]);
			if (empty($WorkCorp)) {
				throw new InvalidParameterException("企业不存在");
			}
			$base    = WorkMomentsBase::findOne(["id" => $base_id, "corp_id" => $WorkCorp->id]);
			$setting = WorkMomentSetting::findOne(["corp_id" => $WorkCorp->id]);
			if (empty($setting)) {
				throw new InvalidParameterException("通用设置不完整");
			}
			if (empty($base)) {
				throw new InvalidParameterException("该条内容不存在");
			}
			if ($base->status == 1) {
				throw new InvalidParameterException("该条内容已审核");
			}
			$Transaction = \Yii::$app->db->beginTransaction();
			try {
				$info = Json::decode($base->info);
				WorkMomentsBase::setMomentContext($base, $setting->agent_id, $info);
				//审核人信息
				if ($status == 3) {
					$data["base_id"] = $base->id;
					$data["reply"]   = $reply;
					if (isset($this->subUser->sub_id)) {
						$data['audit_people'] = $this->subUser->sub_id;
						$data["type"]         = 2;
					} else {
						$data['audit_people'] = $this->user->uid;
						$data["type"]         = 1;
					}
					$audit          = WorkMomentsAudit::setData($data);
					$base->audit_id = $audit->id;
				}
				$base->status = $status;
				$base->save();
				$Transaction->commit();
			} catch (Exception $e) {
				$Transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}

			return ["error" => 0, "msg" => "已审核，正在发布"];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/get-work-user
		 * @title           获取具有外部联系人的成员
		 * @description     获取具有外部联系人的成员
		 * @method   post
		 * @url  http://{host_name}/api/moment/get-work-user
		 *
		 * @param corpid 必须 int 企业微信id
		 * @param name   选填 string 员工名称
		 * @param page      必须 int
		 * @param page_size 必须 int
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 *
		 * @return_param    error int 状态码
		 * @return_param    count int 总条数
		 * @return_param    key   array 所有人id
		 * @return_param    id string 企业成员id
		 * @return_param    userid string 企业成员userid
		 * @return_param    avatar string 企业成员头像
		 * @return_param    gender string 企业成员性别
		 * @return_param    name string 企业成员名称
		 * @return_param    moments_url string 朋友圈地址
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/20 16:28
		 */
		public function actionGetWorkUser ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corp_id   = $this->corp->id;
				$page      = \Yii::$app->request->post('page') ?: 1;
				$name      = \Yii::$app->request->post('name');
				$pageSize  = \Yii::$app->request->post('page_size') ?: 15;
				$is_export = \Yii::$app->request->post('is_export');
				$is_all    = \Yii::$app->request->post('is_all', 0);
				$offset    = ($page - 1) * $pageSize;
				$setting   = WorkMomentSetting::findOne(["corp_id" => $corp_id]);
				if (empty($setting)) {
					throw new InvalidDataException("请先完善,通用设置");
				}
				$user = WorkUser::find()->where(["corp_id" => $corp_id, "is_external" => 1, "is_del" => 0, "status" => 1]);
				if (!empty($name)) {
					$user = $user->andWhere("name like '%$name%'");
				}
				$count = $user->count();
				$key   = $user->select("id")->asArray()->all();
				if (!empty($is_all)) {
					$user = $user->select("id,userid,avatar,gender,department,name")->asArray()->all();
				} else {
					$user = $user->limit($pageSize)->offset($offset)->select("id,userid,avatar,gender,department,name")->asArray()->all();
				}
				$webUrl = \Yii::$app->params["web_url"];
				foreach ($user as &$value) {
					$value["moments_url"] = $webUrl . WorkMomentsBase::BASE_URL . '?user_id=' . $value["userid"] . "&corpid=" . $this->corp->corpid . "&agentId=" . $setting->agent_id;
					$department           = WorkDepartment::find()->where(["in", "department_id", explode(",", $value["department"])])
						->andWhere(["corp_id" => $this->corp->id])->select("name")->asArray()->all();
					$tmpName              = implode("/", array_column($department, "name"));
					$value["name"]        .= "-" . $tmpName;
				}
				$key = array_column($key, "id");
				//导出
				if ($is_export == 1) {
					if (empty($user)) {
						throw new InvalidDataException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$headers  = [
						'name'        => '员工姓名',
						'moments_url' => '朋友圈地址',
					];
					$columns  = array_keys($headers);
					$fileName = '朋友圈_' . date("YmdHis", time());
					Excel::export([
						'models'       => $user,//数库
						'fileName'     => $fileName,//文件名
						'savePath'     => $save_dir,//下载保存的路径
						'asAttachment' => true,//是否下载
						'columns'      => $columns,//要导出的字段
						'headers'      => $headers
					]);
					$url = \Yii::$app->params['site_url'] . str_replace(\Yii::getAlias('@upload'), '/upload', $save_dir) . $fileName . '.xlsx';

					return [
						'url' => $url,
					];
				}

				return ["count" => $count, "data" => $user, "key" => $key];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moment-del
		 * @title           删除朋友圈
		 * @description     删除朋友圈
		 * @method   post
		 * @url  http://{host_name}/api/moment/moment-del
		 *
		 * @param momentId 必须 int id
		 * @param is_detail 必须 int 是否来自详情，当详情最后一个也为1
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/20 16:28
		 */
		public function actionMomentDel ()
		{
			$momentId  = \Yii::$app->request->post('momentId');
			$is_detail = \Yii::$app->request->post('is_detail', 0);
			if (empty($is_detail)) {
				$jobId = \Yii::$app->cache->get($momentId . "moment-add");
				if (!empty($jobId)) {
					\Yii::$app->work->remove($jobId);
					\Yii::$app->cache->delete($momentId . "moment-add");
				}
				WorkMomentsBase::updateAll(["is_del" => 1], ["id" => $momentId]);
			} else {
				WorkMoments::updateAll(["status" => 0], ["id" => $momentId]);
			}

			return;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moment-audit-lists
		 * @title           朋友圈待审核
		 * @description     朋友圈待审核
		 * @method   post
		 * @url  http://{host_name}/api/moment/moment-audit-lists
		 *
		 * @param corpid 必须 string 企业微信id
		 * @param status 选填 状态 2待审核，3审核失败，0全部
		 * @param page 必须
		 * @param pageSize 必须
		 *
		 * @return_param    error int 状态码
		 * @return_param    count string 总数
		 * @return_param    user_name string 审核人
		 * @return_param    name string 创建人
		 * @return_param    audit_time string 最后一次审核时间
		 * @return_param    create_time string 发布时间
		 * @return_param    info array 提交内容
		 * @return_param    context string 文本内容
		 * @return_param    type array 类型：1、仅文本；2、图片；3、视频；4、链接
		 * @return_param    status string 1已审核,2未审核,3审核失败
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/11/8 14:36
		 */
		public function actionMomentAuditLists ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp     = \Yii::$app->request->post("corpid");
			$page     = \Yii::$app->request->post("page");
			$status   = \Yii::$app->request->post("status");
			$pageSize = \Yii::$app->request->post("page_size");
			$page     = ($page > 0) ? $page : 1;
			$offset   = ($page - 1) * $pageSize;
			$corp     = WorkCorp::findOne(["corpid" => $corp]);
			if (empty($corp)) {
				throw new InvalidParameterException("企业不存在");
			}
			$setting = WorkMomentSetting::findOne(["corp_id" => $corp->id]);
			$lists   = WorkMomentsBase::find()->alias("a")
				->leftJoin("{{%work_user}} as b", "a.user_id = b.id")
				->leftJoin("{{%work_moments_audit}} as c", "a.audit_id = c.id")
				->where(["a.corp_id" => $corp->id, "a.is_del" => 0])
				->andWhere("a.status != 1");
			if (!empty($status)) {
				$lists = $lists->andWhere(["a.status" => $status]);
			}
			$count = $lists->count();
			$lists = $lists->select("c.create_time as audit_time,a.sub_id,a.id,a.is_mobile,a.title,a.ownership,a.context,a.create_time,a.status,a.corp_id,a.user_id,a.info,a.agent_id,b.name,c.audit_people,c.type as types,c.reply")
				->limit($pageSize)->offset($offset)
				->orderBy("a.create_time desc")->asArray()->all();
			foreach ($lists as &$list) {
				$list["ownership"]   = Json::decode($list["ownership"]);
				$list["create_time"] = date("Y-m-d H:i:s", $list["create_time"]);
				$list["info"]        = Json::decode($list["info"]);
				$list["context"]     = urldecode($list["context"]);
				if ($list["is_mobile"] == 2) {
					$list["title"]     = "手机端发布";
					$list["ownership"] = [["id" => $list["user_id"], "name" => $list["name"]]];
				}
				if ($list["is_mobile"] == 1 && !empty($list["sub_id"])) {
					$list["name"] = Attachment::getUserName(["sub_id" => $list["sub_id"]]);
				}
				if (!empty($list["types"])) {
					switch ((int) $list["types"]) {
						case 1:
							$list["user_name"] = Attachment::getUserName(["uid" => $list["audit_people"]]);
							break;
						case 2:
							$list["user_name"] = Attachment::getUserName(["sub_id" => $list["audit_people"]]);
							break;
					}
				}
			}

			return ["count" => $count, "lists" => $lists, "is_setting" => empty($setting) ? 0 : 1];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/moment-detail
		 * @title           获取具体内容
		 * @description     获取具体内容
		 * @method   post
		 * @url  http://{host_name}/api/moment/moment-detail
		 *
		 * @param corpid 必须 int corpid
		 * @param base_id 必须 int 内容id
		 * @param user_id 必须 int 员工id
		 * @param check 必须 int 1来自审核2来自查看
		 *
		 * @return_param    error int 状态码
		 * @return_param    heard_img   string 员工头像
		 * @return_param    name   string 员工头像
		 * @return_param    info   array 附加内容图片活图文链接
		 * @return_param    create_time   string 创建时间
		 * @return_param    type   string 1、仅文本；2、图片；3、视频；4、图文
		 * @return_param    context   string 文本内容
		 * @return_param    line   array 游览记录
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/11/6 14:30
		 */
		public function actionMomentDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException("请求方式出错");
			}
			$corpid  = \Yii::$app->request->post("corpid");
			$base_id = \Yii::$app->request->post("base_id");
			$user_id = \Yii::$app->request->post("user_id");
			$check   = \Yii::$app->request->post("check");
			$corp    = WorkCorp::findOne(["corpid" => $corpid]);
			if (empty($corp)) {
				throw new InvalidDataException("未查询到授权企业微信");
			}
			$moment = WorkMomentsBase::find()->alias("a")
				->leftJoin("{{%work_moments}} as b", "b.base_id = a.id")
				->leftJoin("{{%work_user}} as c", "a.user_id = c.id")
				->where(["a.id" => $base_id, "a.corp_id" => $corp->id]);
			if (!empty($user_id)) {
				$moment = $moment->andWhere(["b.user_id" => $user_id]);
			}
			$moment = $moment->select("a.*,c.name,c.department,c.avatar as heard_img,b.id as ids,b.status as bstatus")->asArray()->one();
			if ($moment["bstatus"] == 0) {
				throw new InvalidDataException("当前用户内容已被删除");
			}
			$moment["count"] = WorkMoments::find()->where(["base_id" => $moment["id"], "status" => 1])->count();
			if ($check != 1) {
				$workUser            = WorkUser::findOne($user_id);
				$moment["name"]      = empty($workUser) ? "" : $workUser->name;
				$moment["heard_img"] = empty($workUser) ? '' : $workUser->avatar;
			}
			$setting = WorkMomentSetting::findOne(["corp_id" => $corp->id]);
			if (empty($moment) || empty($setting)) {
				return ["error" => 0, "msg" => "暂无数据"];
			}
			$moment["ownership"] = Json::decode($moment["ownership"]);
			$moment["info"]      = Json::decode($moment["info"]);
			WorkMomentsBase::getDataInfo($moment["info"], $moment["type"]);
			$moment["context"]     = urldecode($moment["context"]);
			$moment["create_time"] = date("Y-m-d H:i:s", $moment["create_time"]);
			$moment["heard_img"]   = empty($setting->heard_img) ? $moment["heard_img"] : $setting->heard_img;
			if ($setting->is_heard == 1) {
				$moment["heard_img"] = empty($workUser) ? '' : $workUser->avatar;
			}
			$moment["line"] = [];
			if ($check == 2) {
				WorkMoments::getExternalTimeLine($moment["ids"], empty($workUser) ? 0 : $workUser->id, $moment["line"]);
			}

			return $moment;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/moment/get-moment-user
		 * @title           根据内容id获取员工名称id
		 * @description     根据内容id获取员工名称id
		 * @method   post
		 * @url  http://{host_name}/api/moment/get-moment-user
		 *
		 * @param base_id 必须 int 内容id
		 * @param name  非必须 string 搜索名称
		 * @param page 必须
		 * @param pageSize 必须
		 *
		 * @return_param    error int 状态码
		 * @return_param    name   string 员工名称
		 * @return_param    id   int 员工id
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/11/27 10:30
		 */
		public function actionGetMomentUser ()
		{
			$baseId   = \Yii::$app->request->post("base_id");
			$name     = \Yii::$app->request->post("name");
			$page     = \Yii::$app->request->post("page");
			$pageSize = \Yii::$app->request->post("pageSize", 15);
			$page     = ($page > 0) ? $page : 1;
			$offset   = ($page - 1) * $pageSize;
			$base     = WorkMomentsBase::findOne(["id" => $baseId, "corp_id" => $this->corp->id]);
			if (empty($base)) {
				throw new InvalidDataException("内容不存在");
			}
			if ($base->is_del == 1) {
				throw new InvalidDataException("内容不存在");
			}

			$user    = WorkMoments::find()->alias("a")
				->leftJoin("{{%work_user}} as b", "a.user_id = b.id")
				->where(["a.base_id" => $baseId, "a.status" => 1]);
			$is_name = 0;
			if ((!empty($name) || $name == 0) && $name!='') {
				$user    = $user->andWhere("b.name like '%$name%'");
				$is_name = 1;
			}
			$count = $user->count();
			$user  = $user->select("b.id,b.name")
				->limit($pageSize)
				->offset($offset)
				->asArray()->all();

			return ["error" => 0, "data" => $user, "count" => $count, "is_name" => $is_name];
		}

	}