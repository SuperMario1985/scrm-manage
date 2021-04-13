<?php
	/**
	 * Create by PhpStorm
	 * title: 客户群功能
	 * User: fulu
	 * Date: 2020/05/28
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\User;
	use app\models\WorkChat;
	use app\models\WorkChatContactWay;
	use app\models\WorkChatContactWayGroup;
	use app\models\WorkChatInfo;
	use app\models\WorkContactWay;
	use app\models\WorkContactWayGroup;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkExternalContactUserWayDetail;
	use app\models\WorkFollowUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;

	class WorkChatWayController extends WorkBaseController
	{
		/**
		 * @inheritDoc
		 *
		 * @param \yii\base\Action $action
		 *
		 * @return bool
		 *
		 * @throws \app\components\InvalidParameterException
		 * @throws \yii\web\BadRequestHttpException
		 */
		public function beforeAction ($action)
		{
			return parent::beforeAction($action);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           自动拉群创建和修改接口
		 * @description     自动拉群创建和修改接口
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/add
		 *
		 * @param uid 必选 int 用户id
		 * @param id 可选 int 编辑时传
		 * @param way_group_id 必选 int 分组id
		 * @param title 必选 string 群码名称
		 * @param text_content 可选 string 入群欢迎语
		 * @param tag_ids 可选 string 标签id
		 * @param skip_verify 可选 boolean 外部客户添加时是否无需验证，默认为true
		 * @param user 必选 array 成员数组
		 * @param chat_list 必选 array 群聊数据
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/2 13:34
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionAdd ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$uid          = \Yii::$app->request->post('uid');
			$id           = \Yii::$app->request->post('id');
			$title        = \Yii::$app->request->post('title');
			$text_content = \Yii::$app->request->post('text_content');
			$tag_ids      = \Yii::$app->request->post('tag_ids');
			$skip_verify  = \Yii::$app->request->post('skip_verify');
			$user_key     = \Yii::$app->request->post('user') ?: [];
			$chat_list    = \Yii::$app->request->post('chat_list') ?: [];
			$way_group_id = \Yii::$app->request->post('way_group_id');
			$condition    = ['title' => $title, 'corp_id' => $this->corp->id];
			if (empty($title)) {
				throw new InvalidParameterException('群码名称不能为空！');
			}
			if (empty($id)) {
				$contact_way = WorkChatContactWay::findOne($condition);
			} else {
				$contact_way = WorkChatContactWay::find()->andWhere(['<>', 'id', $id])->andWhere($condition)->one();
			}

			if (!empty($contact_way)) {
				throw new InvalidParameterException('群活码名称不能存在重复！');
			}
			$userId = [];
			$Temp = ["department"=>[],'user'=>[]];
			if (!empty($user_key)) {
				$Temp = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_key);
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 1, true,0);
				if(!empty($user_ids)){
					$workUser = WorkUser::find()->where(["in","id",$user_ids])->select("userid")->asArray()->all();
					$userId = array_column($workUser,"userid");
				}
//				foreach ($user_key as $user) {
//					$followUser = WorkFollowUser::findOne(['corp_id' => $this->corp['id'], 'user_id' => $user['id'], 'status' => 1]);
//					if (!empty($followUser)) {
//						$workUser = WorkUser::findOne($user['id']);
//						if (!empty($workUser) && $workUser->corp_id == $this->corp['id']) {
//							array_push($userId, $workUser->userid);
//						}
//					}
//				}
			}
			if (empty($Temp["department"]) && empty($userId)) {
				throw new InvalidDataException('使用成员不能为空！');
			}
			$contactWayInfo = $this->getContactWayInfo($skip_verify, $title, $userId,$user_key);
			if (!empty($contactWayInfo['state'])) {
				$whereState = ['state' => $contactWayInfo['state'], 'corp_id' => $this->corp->id, 'is_del' => 0];
				if (empty($id)) {
					$state = WorkChatContactWay::findOne($whereState);
				} else {
					$state = WorkChatContactWay::find()->andWhere(['<>', 'id', $id])->andWhere($whereState)->one();
				}
				if (!empty($state)) {
					throw new InvalidParameterException('渠道活码名称的自定义参数不能存在重复！');
				}
			}

			if (empty($chat_list)) {
				throw new InvalidDataException('群聊内容不能为空');
			}
			foreach ($chat_list as $v) {
				if ($v['limit'] > 200) {
					throw new InvalidDataException('群人数上限不能超过200人');
				}
				if (empty($v['chat_id'])) {
					throw new InvalidDataException('参数不正确');
				}
				if($v['limit']<=0){
					throw new InvalidDataException('群人数上限必须大于0');
				}
			}

			$data['id']           = $id;
			$data['title']        = $title;
			$data['text_content'] = $text_content;
			$data['tag_ids']      = $tag_ids;
			$data['user_key']     = $user_key;
			$data['way_group_id'] = $way_group_id;
			$data['chat_list']    = $chat_list;
			$data['uid']          = $uid;
			$data['user']         = $userId;
			$data['corp_id']      = $this->corp['id'];

			WorkChatContactWay::add($data, $contactWayInfo);

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           群活码列表
		 * @description     群活码列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param title 可选 string 活码名称
		 * @param way_group_id 可选 int 分组id
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/3 13:14
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$title        = \Yii::$app->request->post('title');
			$way_group_id = \Yii::$app->request->post('way_group_id'); //分页
			$page         = \Yii::$app->request->post('page'); //分页
			$pageSize     = \Yii::$app->request->post('page_size'); //页数
			$way          = WorkChatContactWay::find()->where(['corp_id' => $this->corp->id]);
			if (!empty($title) || $title === '0') {
				$way = $way->andWhere(['like', 'title', trim($title)]);
			}
			if (!empty($way_group_id)) {
				if (is_array($way_group_id)) {
					$way = $way->andWhere(['way_group_id' => $way_group_id]);
				} else {
					$idList = WorkContactWayGroup::getSubGroupId($way_group_id, $this->corp->id);
					$way    = $way->andWhere(['way_group_id' => $idList]);
				}
			}

			//获取所有的key
			$keys = [];
			if (empty($comefrom)) {
				$idList = $way->select('id')->all();
				if (!empty($idList)) {
					foreach ($idList as $idInfo) {
						array_push($keys, $idInfo['id']);
					}
				}
			}

			$offset       = ($page - 1) * $pageSize;
			$count        = $way->count();
			$info         = [];
			$way          = $way->limit($pageSize)->offset($offset)->select('*')->orderBy(['id' => SORT_DESC]);
			$way          = $way->all();
			$tmp          = \Yii::$app->db->createCommand("SELECT table_name,create_time FROM information_schema.TABLES where table_name ='{{%work_external_contact_user_way_detail}}'")->queryOne();
			$detailStatus = 0;
			if (!empty($way)) {
				/** @var WorkChatContactWay $w */
				foreach ($way as $w) {
					$result                  = $w->dumpData(1);
					$result['detail_status'] = $detailStatus;
					if (!empty($tmp)) {
						$result['detail_status'] = (strtotime($w->create_time) > strtotime($tmp['create_time'])) ? 1 : 0;
					}
					array_push($info, $result);
				}
			}

			return [
				'count' => $count,
				'info'  => $info,
				'keys'  => $keys,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           群活码详情
		 * @description     群活码详情
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/detail
		 *
		 * @param id 必选 int 群活码id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/3 13:44
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionDetail ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$detail = WorkChatContactWay::findOne($id);
			$info   = [];
			if (!empty($detail)) {
				$info = $detail->dumpData();
			}

			return $info;

		}

		/**
		 * 配置活码信息
		 *
		 * @param $skip_verify
		 * @param $title
		 * @param $userId
		 * @param $user_key
		 *
		 * @return array
		 * @throws InvalidDataException
		 */
		private function getContactWayInfo ($skip_verify, $title, $userId,$user_key)
		{
			$party = WorkDepartment::GiveUserDataReturnPart($user_key);
			$contactWayInfo = [
				'type'        => 2,
				'scene'       => 2,
				'style'       => 1,
				'remark'      => '',
				'skip_verify' => !(boolean) $skip_verify,
				'state'       => WorkChatContactWay::CHAT_HEAD .'_'. $title,
				'user'        => $userId,
				'party'       => $party,
			];

			return $contactWayInfo;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           分组列表
		 * @description     分组列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/group
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/2 15:54
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionGroup ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corp_id   = $this->corp->id;
			$groupData = WorkChatContactWayGroup::getGroupData($uid, $corp_id);
			$count     = WorkChatContactWay::find()->where(['corp_id' => $corp_id])->count();

			return [
				'group' => $groupData,
				'count' => $count
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           分组添加修改
		 * @description     分组添加修改
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/group-add
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param title 必选 string 分组名
		 * @param parent_id 可选 父级ID
		 * @param group_id 可选 string 分组id，修改时必选
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/2 15:58
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupAdd ()
		{
			$uid       = \Yii::$app->request->post('uid', 0);
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$parent_id = \Yii::$app->request->post('parent_id', NULL);
			if (empty($parent_id)) {
				$parent_id = NULL;
			}
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			$title   = \Yii::$app->request->post('title', '');
			$title   = trim($title);
			if (empty($title)) {
				throw new InvalidDataException('请填写分组名称！');
			} elseif (mb_strlen($title, 'utf-8') > 15) {
				throw new InvalidDataException('分组名称不能超过15个字符！');
			} elseif ($title == '未分组' || $title == '所有') {
				throw new InvalidDataException('分组名称不能为“' . $title . '”，请更换！');
			}
			//父级为未分组的不让添加子分组
			if (!empty($parent_id)) {
				$group = WorkChatContactWayGroup::findOne($parent_id);
				if ($group->is_not_group == 1) {
					throw new InvalidDataException('此分组不允许添加子分组！');
				}
			}
			if (!empty($group_id)) {
				$group = WorkChatContactWayGroup::findOne($group_id);
				if ($group->is_not_group == 1) {
					throw new InvalidDataException('此分组不允许修改或添加子分组或删除！');
				}
				$group->update_time = DateUtil::getCurrentTime();
			} else {
				$group              = new WorkChatContactWayGroup();
				$group->uid         = $uid;
				$group->corp_id     = $corp_id;
				$group->parent_id   = $parent_id;
				$group->create_time = DateUtil::getCurrentTime();
				$info               = WorkChatContactWayGroup::find()->where(['uid' => $uid, 'corp_id' => $corp_id, 'parent_id' => $parent_id, 'status' => 1])->orderBy('sort desc')->one();
				if (!empty($info)) {
					$group->sort = $info->sort + 1;
				} else {
					$group->sort = 1;
				}
			}
			$group->title = $title;
			if (!$group->validate() || !$group->save()) {
				throw new InvalidDataException(SUtils::modelError($group));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           分组排序
		 * @description     分组排序
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/group-sort
		 *
		 * @param uid 必选 string 用户ID
		 * @param parent_id 必选 string 父级ID
		 * @param group_id 必选 string 当前移动的id
		 * @param sort 必选 array 移动后分组id排序
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/2 15:59
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupSort ()
		{
			$uid       = \Yii::$app->request->post('uid', 0);
			$parent_id = \Yii::$app->request->post('parent_id', 0);
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$sortData  = \Yii::$app->request->post('sort');
			if (empty($uid) || empty($group_id) || empty($sortData)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$groupInfo = WorkChatContactWayGroup::findOne($group_id);
			if (!empty($groupInfo)) {
				//修改父级
				$groupInfo->parent_id   = !empty($parent_id) ? $parent_id : NULL;
				$groupInfo->update_time = DateUtil::getCurrentTime();
				$groupInfo->save();

				//排序
				$idData = array_reverse($sortData);
				foreach ($idData as $k => $id) {
					$group       = WorkChatContactWayGroup::findOne($id);
					$group->sort = $k + 1;
					$group->save();
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           分组删除
		 * @description     分组删除
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/group-del
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param group_id 必选 string 分组id
		 * @param status 必选 string 状态0：删除
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/2 16:53
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupDel ()
		{
			$uid      = \Yii::$app->request->post('uid', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			if (empty($uid) || empty($group_id) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			$status  = \Yii::$app->request->post('status', 0);
			if (!in_array($status, [0, 1])) {
				throw new InvalidDataException('状态值不存在！');
			}
			$group = WorkChatContactWayGroup::findOne($group_id);
			if (empty($group)) {
				throw new InvalidDataException('此分组已不存在！');
			}
			//未分组不允许删除
			if ($group->is_not_group == 1) {
				throw new InvalidDataException('此分组不允许删除！');
			}
			//分组下面如果有子分组不允许删除
			$parentGroup = WorkChatContactWayGroup::findOne(['parent_id' => $group_id, 'status' => 1]);
			if (!empty($parentGroup)) {
				throw new InvalidDataException('此分组下面还有子分组，不允许删除，请先删除子分组！');
			}

			$group->status      = 0;
			$group->update_time = DateUtil::getCurrentTime();
			if (!$group->validate() || !$group->save()) {
				throw new InvalidDataException(SUtils::modelError($group));
			}
			//更新附件分组
			$notGroup    = WorkChatContactWayGroup::setNoGroup($uid, $corp_id);
			$no_group_id = $notGroup->id;

			if (!empty($no_group_id)) {
				WorkChatContactWay::updateAll(['way_group_id' => $no_group_id], ['corp_id' => $corp_id, 'way_group_id' => $group_id]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           群活码移动
		 * @description     群活码移动
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way/group-change
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param group_id 必选 string 新分组id
		 * @param way_id 必选 string 当前选择的群码id批量时传数组
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/10 9:40
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupChange ()
		{
			$uid      = \Yii::$app->request->post('uid', 0);
			$way_id   = \Yii::$app->request->post('way_id', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			if (empty($uid) || empty($way_id) || empty($group_id) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			WorkChatContactWay::updateAll(['way_group_id' => $group_id], ['corp_id' => $corp_id, 'id' => $way_id]);

			return true;
		}

		/**
		 * showdocw
		 * @catalog         数据接口/api/work-chat-way/
		 * @title           自动拉群明细
		 * @description     自动拉群明细
		 * @method   post
		 *
		 * @url  http://{host_name}/api/work-chat-way/detail-group-num
		 *
		 * @param chat_ids 必选 array 群id
		 * @param contact_way_id 必选 int 活码id
		 * @param user_name 可选 string 搜索名称
		 * @param start_time 可选 string 添加客服开始时间
		 * @param end_time 可选 string 添加客服结束时间
		 * @param type 可选 int 是否入群 默认 0、全部，1未入群，2入群，3离群
		 *
		 * @return   {"error":0,"data":{"total":"4","success_total":"1","out_total":"1","del_total":"4","lists":[{"id":"4107","name_convert":"益达","avatar":"http://wx.qlogo.cn/mmhead/j8JSzC6ialBaUMe8gaaWyrsAKWCT3aHYXlm8FUzK9pOoDKpAicyibEyWA/0","gender":"0","del_type":false,"del_time":null,"name":"张婷","createtime":"2020-08-05 11:23:15","group_status":2,"leave_time":null,"join_time":"2020-08-05 11:22:46","chat_name":"测试照顾"}]}}
		 *
		 * @return_param       error int 状态码
		 * @return_param       data array 结果数据
		 * @return_param       total int 客服添加总数
		 * @return_param       success_total int 成功入群人数
		 * @return_param       out_total int 离群人数
		 * @return_param       del_total int 删除客服人数
		 * @return_param       lists array 人数列表
		 * @return_param       lists.name_convert char 名称
		 * @return_param       lists.avatar varchar 头像
		 * @return_param       lists.del_type bool 是否删除
		 * @return_param       lists.del_time char 删除时间
		 * @return_param       lists.name char 所属人
		 * @return_param       lists.createtime varchar 添加客服时间
		 * @return_param       lists.group_status int 是否入群 1:未入群、2:入群、3:离群
		 * @return_param       lists.leave_time varchar 离群时间
		 * @return_param       lists.join_time varchar 入群时间
		 * @return_param       lists.chat_name char 所属群
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/8/5 12.02
		 * @number          0
		 */
		public function actionDetailGroupNum ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$chat_ids       = \Yii::$app->request->post('chat_ids');
			$contact_way_id = \Yii::$app->request->post('contact_way_id');
			//搜索条件
			$user_name  = \Yii::$app->request->post('user_name');
			$type       = \Yii::$app->request->post('type', 0);
			$time_type  = (int) \Yii::$app->request->post('time_type', 1);
			$start_time = \Yii::$app->request->post('start_time', false);
			$end_time   = \Yii::$app->request->post('end_time', false);
			//分页
			$page      = \Yii::$app->request->post('page', 1);
			$page_size = \Yii::$app->request->post('page_size', 15);
			$user_ids  = \Yii::$app->request->post('user_ids');

			$row = WorkChatContactWay::find()->where(["id" => $contact_way_id])->asArray()->one();

			if (empty($row)) {
				throw new InvalidDataException('活码数据未查找到！');
			}
			$row1 = WorkExternalContact::find()
				->Where("find_in_set ($contact_way_id,chat_way_id)")
				->andWhere(['corp_id'=>$this->corp->id])
				->asArray()
				->all();
			//提取被邀请人id
			$external_ids = array_column($row1, "id");
			//提取客服id
			$Airlines_ids = array_column(json_decode($row['user_key']), "id");

			//总邀请人
			$result['total'] = WorkExternalContact::find()
				->Where("find_in_set ($contact_way_id,chat_way_id)")
				->asArray()
				->count();
			$detail = WorkExternalContactUserWayDetail::find()->where(["way_id"=>$contact_way_id])->asArray()->all();
			$result['success_total'] = 0;
			$result['out_total'] = 0;
			if(!empty($detail)){
				$chat_id = array_column($detail,"chat_id");
				//以同意入群的
				$result['success_total'] = WorkChatInfo::find()->alias("a")
					->leftJoin("{{%work_chat}} as b","a.chat_id = b.id")
					->where(["in", 'a.chat_id', $chat_id])
					->andWhere(["in", 'a.external_id', $external_ids])
					->andWhere(['b.corp_id'=>$this->corp->id])
					->andWhere(['a.status' => 1, "a.join_scene" => 3, "a.type" => 2])
					->count();
				//退出群的
				$result['out_total'] = WorkChatInfo::find()->alias("a")
					->leftJoin("{{%work_chat}} as b","a.chat_id = b.id")
					->where(["in", 'a.chat_id', $chat_id])
					->andWhere(["in", 'a.external_id', $external_ids])
					->andWhere(['b.corp_id'=>$this->corp->id])
					->andWhere(['a.status' => 0, "a.join_scene" => 3, "a.type" => 2])
					->count();
			}
			//删除客服的
			$result['del_total'] = WorkExternalContactFollowUser::find()->alias("a")
				->leftJoin("{{%work_external_contact}} as b","a.external_userid = b.id")
				->where(["a.chat_way_id" => $contact_way_id])
				->andWhere(['b.corp_id'=>$this->corp->id])
				->andWhere(['in', "a.external_userid", $external_ids])
				->andWhere(["a.del_type" => 2])
				->count();
			//列表数据集
			$user_lists      = WorkExternalContactFollowUser::find()
				->alias("wecf")
				->leftJoin("{{%work_external_contact}} as wec", "wec.id = wecf.external_userid")
				->leftJoin("{{%work_user}} as wu", "wecf.user_id = wu.id")
				->where("find_in_set ($contact_way_id,wec.chat_way_id)")
				->andWhere(['wecf.chat_way_id' => $contact_way_id,'wec.corp_id' => $this->corp->id]);
			if (!empty($user_name) || $user_name == '0') {
				$user_lists = $user_lists->andWhere(["like", 'wec.name_convert', "$user_name"]);
			}
			if(!empty($user_ids)){
				$user_lists = $user_lists->andWhere(["in", 'wecf.user_id',$user_ids]);
			}
			if ($start_time && $end_time) {
				$start_time = strtotime($start_time);
				$end_time   = strtotime($end_time);
				if ($time_type == 1) {
					$user_lists = $user_lists->andWhere("wecf.createtime between $start_time and $end_time");
				}
				if ($time_type == 2) {
					$user_lists = $user_lists->andWhere("wecf.del_time between $start_time and $end_time")->andWhere("del_type = 2");
				}
			}
			$user_lists = $user_lists->select("wec.id as ids,wec.corp_name,wec.name_convert as customer_name,wec.avatar,wec.gender,wecf.del_type,del_time,wu.name,wecf.createtime");
			$offset     = ($page - 1) * $page_size;
			$user_lists = $user_lists->orderBy("wecf.id desc")->limit($page_size)->offset($offset)->asArray()->all();
			//重组数组
			foreach ($user_lists as $key => &$user_list) {
				$user_list['createtime'] = date("Y-m-d H:i", $user_list['createtime']);
				if ($user_list['del_type'] == 2) {
					$user_list['del_type'] = true;
					$user_list['del_time'] = date("Y-m-d H:i", $user_list['del_time']);
				} else {
					$user_list['del_type'] = false;
					$user_list['del_time'] = '---';
				}
				if ($user_list['gender'] == 1) {
					$user_list['gender'] = "男性";
				} elseif ($user_list['gender'] == 2) {
					$user_list['gender'] = "女性";
				} else {
					$user_list['gender'] = "未知";
				}

				$user_list['group_status'] = 1; //未入群
				$user_list['leave_time']   = '---';
				$user_list['join_time']    = '---';
				$detail                    = WorkExternalContactUserWayDetail::find()
					->alias("wcd")
					->leftJoin("{{%work_chat_info}} as wci","wci.chat_id = wcd.chat_id")
					->where(["wcd.external_id" => $user_list["ids"]])
					->andWhere(["wcd.way_id" => $contact_way_id]);
				if(!empty($chat_ids)){
					$detail = $detail->andWhere(["in","wci.chat_id",$chat_ids]);
				}
				$detail = $detail->asArray()->one();
				if (!empty($detail)) {
					$user_list['chat_name'] = WorkChat::getChatName($detail['chat_id']);
					//查看是否进群
					$FollowUsers = WorkChatInfo::find()
						->alias("wcf")
						->leftJoin("{{%work_chat}} as wc", "wcf.chat_id = wc.id")
						->andWhere(["wcf.external_id" => $user_list['ids'],"wcf.chat_id"=>$detail["chat_id"], "wcf.join_scene" => 3, "wcf.type" => 2]);
					if ($time_type == 3 && $start_time && $end_time) {
						$FollowUsers = $FollowUsers->andWhere("wcf.join_time between $start_time and $end_time");
					}
					if ($time_type == 4 && $start_time && $end_time) {
						$FollowUsers = $FollowUsers->andWhere("wcf.leave_time between $start_time and $end_time");
					}
					$FollowUsers = $FollowUsers->select("wc.name,wcf.leave_time,wcf.join_time,wc.id")->asArray()->one();
					if ($time_type == 3 && empty($FollowUsers) && $start_time && $end_time) {
						unset($user_lists[$key]);
						continue;
					}
					if ($time_type == 4 && empty($FollowUsers) && $start_time && $end_time) {
						unset($user_lists[$key]);
						continue;
					}
					//加入和离群时间
					if (!empty($FollowUsers)) {
						$user_list['join_time'] = date("Y-m-d H:i", $FollowUsers['join_time']);
						$user_list['chat_name'] = WorkChat::getChatName($FollowUsers['id']);
						if (empty($FollowUsers['leave_time'])) {
							$user_list['leave_time']   = '---';
							$user_list['group_status'] = 2; //未离开
						} else {
							$user_list['group_status'] = 3; //离开
							$user_list['leave_time']   = date("Y-m-d H:i", $FollowUsers['leave_time']);
						}
					}
				} else {
					unset($user_lists[$key]);
					continue;
				}

				//产看全部筛选
				if ($type && $type != $user_list['group_status']) {
					unset($user_lists[$key]);
				}

			}

			$data            = array_values($user_lists);
			$result['count'] = count($data);
			$result['lists'] = $data;

			return $result;
		}



	}