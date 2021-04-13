<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\LimitWord;
	use app\models\UserCorpRelation;
	use app\models\WorkChat;
	use app\models\WorkChatRemind;
	use app\models\WorkDepartment;
	use app\models\WorkMsgAuditUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidDataException;

	class WorkChatRemindController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-remind/
		 * @title           群提醒列表
		 * @description     群提醒列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-remind/chat-remind-list
		 *
		 * @param corp_id      必选 string 企业的唯一ID
		 * @param agentid      可选 string 应用ID
		 * @param title        可选 string 规则名称
		 * @param chat_ids     可选 array 群id集合
		 * @param page         可选 int 页码
		 * @param page_size    可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":{"list":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int  数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.remind_id int 群提醒id
		 * @return_param    list.chatName array 适用群
		 * @return_param    list.title string 规则名称
		 * @return_param    list.remindName array 提醒行为
		 * @return_param    list.keyword array 关键词
		 * @return_param    list.add_time string 创建时间
		 * @return_param    list.upt_time string 更新时间
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-07-15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatRemindList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$agentid  = \Yii::$app->request->post('agentid');
			$title    = \Yii::$app->request->post('title', '');
			$chat_ids = \Yii::$app->request->post('chat_ids');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);
			$title    = trim($title);

			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			$chatRemind = WorkChatRemind::find()->andWhere(['corp_id' => $this->corp['id'], 'status' => 1]);

			if (!empty($agentid)) {
				$chatRemind = $chatRemind->andWhere(['agentid' => $agentid]);
			}

			//规则名称查询
			if (!empty($title)) {
				$chatRemind = $chatRemind->andWhere(['like', 'title', $title]);
			}
			//群
			if (!empty($chat_ids)) {
				$chatStr = '';
				foreach ($chat_ids as $chat_id) {
					if (empty($chatStr)) {
						$chatStr .= 'chat_ids like \'%"' . $chat_id . '"%\'';
					} else {
						$chatStr .= ' or chat_ids like \'%"' . $chat_id . '"%\'';
					}
				}
				$chatRemind = $chatRemind->andWhere($chatStr);
			}

			$count = $chatRemind->count();

			$offset     = ($page - 1) * $pageSize;
			$chatRemind = $chatRemind->limit($pageSize)->offset($offset);
			$chatRemind = $chatRemind->orderBy(['id' => SORT_DESC])->asArray()->all();

			$result = [];
			foreach ($chatRemind as $v) {
				$remindD              = [];
				$remindD['remind_id'] = $v['id'];
				$remindD['title']     = $v['title'];
				$row                  = WorkChat::find()
					->where(["in", "id", json_decode($v['chat_ids'], true)])
					->asArray()
					->all();
				$remindD['master']    = WorkUser::find()
					->where(["in", "id", array_column($row, "owner_id")])
					->select("name")
					->asArray()
					->all();
				$remind_user          = json_decode($v['remind_user'], true);
				if (!empty($remind_user)) {
					$masterName           = array_column($remindD['master'], "name");
					foreach ($remind_user as $key => $remindU) {
						if (!isset($remindU['title'])){
							continue;
						}
						if (in_array($remindU['title'], $masterName)) {
							unset($remind_user[$key]);
						}
					}
				}
				$remindD['send_user'] = $remind_user;
				$chatNameArr = [];
				$chat_ids    = json_decode($v['chat_ids'], true);
				foreach ($chat_ids as $chat_id) {
					$chatName = WorkChat::getChatName($chat_id);
					array_push($chatNameArr, $chatName);
				}
				$remindD['chatName'] = $chatNameArr;

				$remindName = [];
				if ($v['is_image']) {
					$remindName[] = '图片';
				}
				if ($v['is_link']) {
					$remindName[] = '链接';
				}
				if ($v['is_weapp']) {
					$remindName[] = '小程序';
				}
				if ($v['is_card']) {
					$remindName[] = '名片';
				}
				if ($v['is_voice']) {
					$remindName[] = '音频';
				}
				if ($v['is_video']) {
					$remindName[] = '视频';
				}
				if ($v['is_redpacket']) {
					$remindName[] = '红包';
				}
				$keyword = [];
				if ($v['is_text']) {
					$keywordId = json_decode($v['keyword'], true);
					$limitWord = LimitWord::getList('', $keywordId);
					foreach ($limitWord as $word) {
						array_push($keyword, $word->title);
					}
				}
				$remindD['remindName'] = $remindName;
				$remindD['keyword']    = $keyword;
				$remindD['add_time']   = $v['add_time'] ? date('Y-m-d H:i', $v['add_time']) : '--';
				$remindD['upt_time']   = $v['upt_time'] ? date('Y-m-d H:i', $v['upt_time']) : '--';

				$result[] = $remindD;
			}

			return [
				'count' => $count,
				'list'  => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-remind/
		 * @title           群提醒删除
		 * @description     群提醒删除
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-remind/chat-remind-delete
		 *
		 * @param remind_id   必选 int 群提醒ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-07-15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatRemindDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$remind_id = \Yii::$app->request->post('remind_id', 0);

			if (empty($remind_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$chatRemind = WorkChatRemind::findOne($remind_id);

			if (empty($chatRemind)) {
				throw new InvalidDataException('群提醒参数错误！');
			} else {
				$chatRemind->status   = 0;
				$chatRemind->upt_time = time();

				if (!$chatRemind->validate() || !$chatRemind->save()) {
					throw new InvalidDataException(SUtils::modelError($chatRemind));
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-remind/
		 * @title           群提醒规则详情
		 * @description     群提醒规则详情
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-remind/chat-remind-detail
		 *
		 * @param corp_id                必选 string 企业的唯一ID
		 * @param remind_id              必选 int 群提醒ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    data.remind_id int 群提醒id
		 * @return_param    data.corp_id int 企业的唯一ID
		 * @return_param    data.agentid int 应用id
		 * @return_param    data.title string 规则名称
		 * @return_param    data.chat_ids array 群id集合
		 * @return_param    data.remind_user array 接收成员
		 * @return_param    data.is_image int 是否图片提醒1是0否
		 * @return_param    data.is_link int 是否链接提醒1是0否
		 * @return_param    data.is_weapp int 是否小程序提醒1是0否
		 * @return_param    data.is_card int 是否名片提醒1是0否
		 * @return_param    data.is_voice int 是否音频提醒1是0否
		 * @return_param    data.is_video int 是否视频提醒1是0否
		 * @return_param    data.is_redpacket int 是否红包提醒1是0否
		 * @return_param    data.is_text int 是否关键词提醒1是0否
		 * @return_param    data.keyword array 关键词集合
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-07-15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatRemindDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$remind_id = \Yii::$app->request->post('remind_id', 0);

			if (empty($this->corp) || empty($remind_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$chatRemind = WorkChatRemind::find()->where(['id' => $remind_id])->asArray()->one();

			if (!empty($chatRemind)) {
				$chatRemind['remind_id']   = $chatRemind['id'];
				$chatRemind['chat_ids']    = json_decode($chatRemind['chat_ids'], true);
				$chatRemind['remind_user'] = !empty($chatRemind['remind_user']) ? json_decode($chatRemind['remind_user'], true) : [];
				foreach ($chatRemind['remind_user'] as &$value) {
					if (is_array($value)){
						if(!isset($value["scopedSlots"])){
							$value["scopedSlots"] = ['title' => 'custom'];
						}
						if (isset($value["key"])) {
							$key = explode(",", $value["key"]);
							if (!empty($key)) {
								$value["key"] = $key[0];
							}
						}
					}else{
						$tmp   = WorkDepartment::getUsers(0, $this->corp->id, [], 0, [$value]);
						$value = isset($tmp[0]) ? $tmp[0] : [];
					}
				}
				$keywordId = !empty($chatRemind['keyword']) ? json_decode($chatRemind['keyword'], true) : [];
				if (!empty($keywordId)) {
					$limitWord = LimitWord::getList('', $keywordId);
					$keywordId = [];
					foreach ($limitWord as $word) {
						array_push($keywordId, $word->id);
					}
				}
				$chatRemind['keyword'] = $keywordId;

				return $chatRemind;
			} else {
				throw new InvalidDataException('群提醒参数错误！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-remind/
		 * @title           群提醒规则提交
		 * @description     群提醒规则提交
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-remind/chat-remind-post
		 *
		 * @param corp_id             必选 string 企业的唯一ID
		 * @param agentid             必选 string 应用ID
		 * @param remind_id           可选 int 群提醒id
		 * @param title               可选 array 规则名称
		 * @param chat_ids            必选 array 群id集合
		 * @param remind_user         可选 array 接收成员
		 * @param is_image            可选 int 是否图片提醒1是0否
		 * @param is_link             可选 int 是否链接提醒1是0否
		 * @param is_weapp            可选 int 是否小程序提醒1是0否
		 * @param is_card             可选 int 是否名片提醒1是0否
		 * @param is_voice            可选 int 是否音频提醒1是0否
		 * @param is_video            可选 int 是否视频提醒1是0否
		 * @param is_redpacket        可选 int 是否红包提醒1是0否
		 * @param is_text             可选 int 是否关键词提醒1是0否
		 * @param keyword             可选 array 关键词集合
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-07-15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatRemindPost ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$data                 = [];
			$data['remind_id']    = \Yii::$app->request->post('remind_id', 0);
			$data['corp_id']      = $this->corp['id'];
			$data['agentid']      = \Yii::$app->request->post('agentid', 0);
			$data['title']        = \Yii::$app->request->post('title', '');
			$data['chat_ids']     = \Yii::$app->request->post('chat_ids', []);
			$data['remind_user']  = \Yii::$app->request->post('remind_user', []);
			$data['is_image']     = \Yii::$app->request->post('is_image', 0);
			$data['is_link']      = \Yii::$app->request->post('is_link', 0);
			$data['is_weapp']     = \Yii::$app->request->post('is_weapp', 0);
			$data['is_card']      = \Yii::$app->request->post('is_card', 0);
			$data['is_voice']     = \Yii::$app->request->post('is_voice', 0);
			$data['is_video']     = \Yii::$app->request->post('is_video', 0);
			$data['is_redpacket'] = \Yii::$app->request->post('is_redpacket', 0);
			$data['is_text']      = \Yii::$app->request->post('is_text', 0);
			$data['keyword']      = \Yii::$app->request->post('keyword', []);

			if (empty($data['chat_ids']) || empty($data['agentid'])) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if ($data['is_text'] && empty($data['keyword'])) {
				throw new InvalidDataException('关键词不能为空！');
			}
			if (empty($this->corp->workMsgAudit) || $this->corp->workMsgAudit->status != 1) {
				throw new InvalidDataException('还未开启会话存档！');
			}

			$userCorp         = UserCorpRelation::findOne(['corp_id' => $this->corp->id]);
			$data['uid']      = $userCorp->uid;
			$data['audit_id'] = $this->corp->workMsgAudit->id;
			if (!empty($data['remind_user'])) {
				$A = [];//部门
				$B = [];//成员
				if (is_array($data['remind_user'])) {
					foreach ($data['remind_user'] as $value) {
						if (is_array($value)) {
							$TempValue = $value["id"];
						} else {
							$TempValue = $value;
						}
						if (strpos($TempValue, 'd') !== false) {
							$T = explode("-", $TempValue);
							if (isset($T[1])) {
								array_push($A, $T[1]);
							}
						} else {
							array_push($B, $value);
						}
					}
				}
				$data['remind_user'] = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $A, $B, 0, false, 0, []);
			}
			WorkChatRemind::creat($data);

			return true;
		}

	}