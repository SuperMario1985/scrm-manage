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
	use app\models\Attachment;
	use app\models\AuthoritySubUserDetail;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\ExternalTimeLine;
	use app\models\Follow;
	use app\models\SubUser;
	use app\models\SubUserProfile;
	use app\models\UserProfile;
	use app\models\WorkChat;
	use app\models\WorkChatGroup;
	use app\models\WorkChatInfo;
	use app\models\WorkChatRemind;
	use app\models\WorkChatStatistic;
	use app\models\WorkChatWelcome;
	use app\models\WorkCorp;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowRecord;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkGroupSending;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkTag;
	use app\models\WorkTagChat;
	use app\models\WorkUser;
	use app\models\WorkDepartment;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkChatJob;
	use app\util\DateUtil;
	use app\util\SUtils;
	use yii\db\Expression;
	use yii\web\MethodNotAllowedHttpException;
	use moonland\phpexcel\Excel;

	class WorkChatController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-chat/
		 * @title           群列表
		 * @description     群列表数据
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-list
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 * @param group_id   可选 int 分组：0表示全部
		 * @param status     可选 int 群状态：-1表示全部0正常1跟进人离职2离职继承中3离职继承完成
		 * @param name       可选 string 群名称
		 * @param user_id    可选 int 员工id
		 * @param stime      可选 string 起始时间
		 * @param etime      可选 string 结束时间
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 * @param is_remind_all     选填 int 是否显示设置过群违规提醒
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.chat_id int 群id
		 * @return_param    list.name string 群名称
		 * @return_param    list.group_id int 所属分组id
		 * @return_param    list.group_name string 所属分组
		 * @return_param    list.status string 群状态
		 * @return_param    list.owner_name string 群主
		 * @return_param    list.member_num int 群人数
		 * @return_param    list.external_num int 外部联系人数
		 * @return_param    list.new_member_num int 今日入群人数
		 * @return_param    list.leave_member_num int 今日退群人数
		 * @return_param    list.notice string 群公告
		 * @return_param    list.create_time string 群创建时间
		 * @return_param    list.isRemind int 是否设置群提醒
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/05/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$status    = \Yii::$app->request->post('status', 0);
			$name      = \Yii::$app->request->post('name');
			$user_id   = \Yii::$app->request->post('user_id', '');
			$stime     = \Yii::$app->request->post('stime', '');
			$etime     = \Yii::$app->request->post('etime', '');
			$page      = \Yii::$app->request->post('page', 1);
			$pageSize  = \Yii::$app->request->post('page_size', 15);
			$is_export = \Yii::$app->request->post('is_export', 0);
			$is_all    = \Yii::$app->request->post('is_all', 0);
			$is_list    = \Yii::$app->request->post('is_list', 0);
			$is_remind_all = \Yii::$app->request->post('is_remind_all', 0);
			$chat_id = \Yii::$app->request->post('chat_id', 0);
			$name      = trim($name);

			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($user_id)){
				$Temp    = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true, 0, [], $sub_id);
				$user_id = WorkGroupSending::sendChat($this->corp->id, $user_id);
				if(empty($user_id)){
					return [
						"all_chat"=>[],
						"count"=>0,
						"list"=>[],
					];
				}
			}
			//更新未分组
			if ($page == 1){
				$chatGroupData = WorkChatGroup::findOne(['corp_id' => $this->corp['id'], 'status' => 1, 'group_name' => '未分组']);
				if (empty($chatGroupData)){
					$defaultGroup = WorkChatGroup::add(0, $this->corp['id'], '未分组');
				}else{
					$defaultGroup = $chatGroupData->id;
				}
				WorkChat::updateAll(['group_id' => $defaultGroup], ['corp_id' => $this->corp['id'], 'group_id' => 0]);
			}

			$offset = ($page - 1) * $pageSize;

			$chatData = WorkChat::find()->alias("a")
				->leftJoin("{{%work_user}} as b", "a.owner_id = b.id")
				->andWhere(['a.corp_id' => $this->corp['id']]);
			if(isset($this->subUser->sub_id) && empty($user_id)){
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$sub_id,0,true);
				if(empty($user_ids)){
					return [
						"all_chat"=>[],
						"count"=>0,
						"list"=>[],
					];
				}
				if(is_array($user_ids)){
					if($is_list == 1){
						$chat_lists = AuthoritySubUserDetail::getUserChatLists($user_ids,$this->corp->id);
						$chatData = $chatData->andWhere(["in","a.id",$chat_lists]);
					}
					if($is_list == 2){
						$chat_lists = AuthoritySubUserDetail::getUserInChatLists($user_ids,$this->corp->id);
						$chatData = $chatData->andWhere(["in","a.id",$chat_lists]);
					}
				}
			}
			if (!empty($group_id)) {
				$chatData = $chatData->andWhere(['a.group_id' => $group_id]);
			}
			if ($status != '-1') {
				$chatData = $chatData->andWhere(['a.status' => $status]);
			}
			if (!empty($name) || $name === '0') {
				$chatData = $chatData->andWhere("(a.name like '%$name%' or b.name like '%$name%')");
			}
			if (!empty($user_id)) {
				$chatData = $chatData->andWhere(['a.owner_id' => $user_id]);
			}
			if (!empty($stime) && !empty($etime)) {
				$chatData = $chatData->andFilterWhere(['between', 'a.create_time', strtotime($stime), strtotime($etime . ':59')]);
			}

			//群提醒设置
			$chatRemind = WorkChatRemind::find()->andWhere(['corp_id' => $this->corp['id']]);
			if(empty($is_remind_all)){
				$chatRemind = $chatRemind->andWhere(['status' => 1]);
			}

			$chatRemind = $chatRemind->all();
			$chatIds    = [];
			if (!empty($chatRemind)) {
				foreach ($chatRemind as $v) {
					$chat_ids = !empty($v->chat_ids) ? json_decode($v->chat_ids, true) : [];
					$chatIds  = array_merge($chatIds, $chat_ids);
				}
			}

			if (!empty($is_remind_all)) {
				$chatIds  = array_unique($chatIds);
				$chatData = $chatData->andWhere(['a.id' => $chatIds]);
			}

			$countData = $chatData->select('a.`id`')->all();
			$count     = count($countData);

			$all_chat = [];
			foreach ($countData as $k => $v) {
				array_push($all_chat, $v->id);
			}

			if (empty($is_all)) {
				$chatData = $chatData->limit($pageSize)->offset($offset);
			}

			$chatData = $chatData->select('`b`.`name` as user_name,`a`.`id` as chat_id, `a`.`chat_id` as chatid, `a`.`name`,`a`.`owner_id`,`a`.`owner`,`a`.`create_time`,`a`.`notice`,`a`.`group_id`,`a`.`status`')->orderBy(['a.create_time' => SORT_DESC])->asArray()->all();

			if ($chat_id && $page == 1){
				//群搜索框 选中群放到第一条
				$selectChat = WorkChat::find()->where(['id' => $chat_id])->select('`owner_id` user_name, `id` chat_id, `chat_id` chatid,`name`,`owner_id`,`owner`,`create_time`,`notice`,`group_id`,`status`')->asArray()->one();
				if (!empty($selectChat)){
					array_unshift($chatData, $selectChat);
				}
			}

			$time   = strtotime(date('Y-m-d'));
			$result = [];
			foreach ($chatData as $key => $val) {
				$chatD             = [];
				$chatD['chat_id']  = $val['chat_id'];
				$chatD['chatid']   = $val['chatid'];
				$chatD['name']     = WorkChat::getChatName($val['chat_id']);
				$chatD['group_id'] = $val['group_id'];
				$chatD["user_key"] = ["id" => $val["owner_id"], "name" => $val["user_name"]];
				$group_name        = '';
				if (!empty($val['group_id'])) {
					$chatGroup  = WorkChatGroup::findOne($val['group_id']);
					$group_name = $chatGroup->group_name;
				}
				switch ($val['status']) {
					case 1:
						$status = '跟进人离职';
						break;
					case 2:
						$status = '离职继承中';
						break;
					case 3:
						$status = '离职继承完成';
						break;
					case 4:
						$status = '群已解散';
						break;
					default:
					case 0:
						$status = '正常';
						break;
				}
				if (!empty($val['owner_id'])) {
					$work_user  = WorkUser::findOne($val['owner_id']);
					$departName = WorkDepartment::getDepartNameByUserId($work_user->department, $work_user->corp_id);
					$owner_name = $work_user->name . '--' . $departName;
				} else {
					$ownerId = 0;
					try {
						$owenrId = WorkExternalContact::getExternalId($this->corp->id, $val['owner']);
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), __CLASS__ . '-' . __FUNCTION__ . ':getExternalId');
					}

					if ($ownerId == 0) {
						$owner_name = '外部非联系人：' . $val['owner'];
					} else {
						$externalContact = WorkExternalContact::findOne($ownerId);
						$owner_name      = '外部联系人：' . $externalContact->name;
					}
				}

				$chatD['group_name']       = $group_name;
				$chatD['status']           = $status;
				$chatD['owner_name']       = $owner_name;
				$chatD['member_num']       = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 1])->count();
				$chatD['external_num']     = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 1, 'type' => 2])->count();
				$chatD['new_member_num']   = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 1])->andWhere(['>', 'join_time', $time])->count();
				$chatD['leave_member_num'] = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 0])->andWhere(['>', 'leave_time', $time])->count();
				$chatD['notice']           = $val['notice'];
				$chatD['create_time']      = date('Y-m-d H:i', $val['create_time']);
				$chatD['isRemind']         = in_array($val['chat_id'], $chatIds) ? 1 : 0;
				$chatD['avatarData']       = WorkChat::getChatAvatar($val['chat_id'], $val['status']);
				//是否有会话存档
				$auditInfo        = WorkChat::chatAuditInfo($this->corp['id'], $val['chat_id'], 0);
				$chatD['isAudit'] = $auditInfo['isAudit'];

				$result[] = $chatD;
			}

			//导出
			if ($is_export == 1) {
				if (empty($result)) {
					throw new InvalidParameterException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}

				$columns  = ['name', 'group_name', 'status', 'owner_name', 'member_num', 'external_num', 'new_member_num', 'leave_member_num', 'notice', 'create_time'];
				$headers  = [
					'name'             => '群名称',
					'group_name'       => '所属分组',
					'status'           => '群状态',
					'owner_name'       => '群主',
					'member_num'       => '群人数',
					'external_num'     => '外部联系人数',
					'new_member_num'   => '今日入群数',
					'leave_member_num' => '今日退群数',
					'notice'           => '群公告',
					'create_time'      => '创建时间',
				];
				$fileName = '群列表_' . date("YmdHis", time());
				Excel::export([
					'models'       => $result,//数库
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

			return [
				'count'    => $count,
				'all_chat' => $all_chat,
				'list'     => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           搜索群成员
		 * @description     搜索群成员
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-member-name
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 * @param chat_id    必选 int 群id
		 * @param name       可选 string 群成员昵称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    list array 数据列表
		 * @return_param    list.nickname string 成员昵称
		 * @return_param    list.name string 成员姓名
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/05/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatMemberName ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$chat_id = \Yii::$app->request->post('chat_id');
			$name    = \Yii::$app->request->post('name', '');
			$name    = trim($name);

			if (empty($this->corp) || empty($chat_id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$chatInfo = WorkChat::findOne($chat_id);
			if (empty($chatInfo)) {
				throw new InvalidParameterException('客户群数据不正确！');
			}

			$result = [];
			$chatInfoData = WorkChatInfo::find()->andWhere(['chat_id' => $chat_id]);

			if (!empty($name)) {
				//外部联系人
				$workExternalData = WorkExternalContact::find()->alias('we');
				$workExternalData = $workExternalData->andWhere(['we.corp_id' => $this->corp['id']]);
				$workExternalData = $workExternalData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
				$workExternalData = $workExternalData->andWhere(' we.name_convert like \'%' . $name . '%\' or (cf.fieldid=2 and cf.value like \'%' . $name . '%\')');
				$workExternalData = $workExternalData->select('we.id as wid')->groupBy('we.id')->asArray()->all();
				$external_id      = array_column($workExternalData, 'wid');
				//成员
				$workUserData = WorkUser::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => 0])->andWhere(['like', 'name', $name]);
				$workUserData = $workUserData->select('id')->asArray()->all();
				$user_id      = array_column($workUserData, 'id');

				if (!empty($external_id) && !empty($user_id)) {
					$chatInfoData = $chatInfoData->andWhere(['or', ['in', 'external_id', $external_id], ['in', 'user_id', $user_id]]);
				} elseif (!empty($external_id)) {
					$chatInfoData = $chatInfoData->andWhere(['in', 'external_id', $external_id]);
				} elseif (!empty($user_id)) {
					$chatInfoData = $chatInfoData->andWhere(['in', 'user_id', $user_id]);
				} else {
					$chatInfoData = $chatInfoData->andWhere(['id' => 0]);
				}

				$chatInfoData = $chatInfoData->select('`user_id`,`external_id`,`type`')->orderBy(['type' => SORT_ASC, 'join_time' => SORT_DESC])->asArray()->all();

				foreach ($chatInfoData as $key => $val) {
					$chatD = [];
					$name  = '';
					if ($val['type'] == 1) {
						$work_user = WorkUser::findOne($val['user_id']);
						$nickname  = $work_user->name;
					} else {
						$external_contact = WorkExternalContact::findOne($val['external_id']);
						$nickname         = !empty($external_contact->name_convert) ? $external_contact->name_convert : '';
						$fieldInfo        = CustomFieldValue::findOne(['cid' => $val['external_id'], 'type' => 1, 'fieldid' => 2]);
						if ($fieldInfo && $fieldInfo->value) {
							$name = $fieldInfo->value;
						}
					}
					$chatD['nickname'] = $nickname;
					$chatD['name']     = $name;

					$result[] = $chatD;
				}
			}

			return [
				'list' => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群成员列表
		 * @description     群成员列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-info-list
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 * @param chat_id    必选 int 群id
		 * @param chatid     可选 string 群chatid
		 * @param status     可选 int 群状态：-1表示全部、1正常、0已离开
		 * @param name       可选 string 群成员昵称
		 * @param stime      可选 string 起始时间
		 * @param etime      可选 string 结束时间
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 * @param now_userid 选填 string H5当前员工的userid
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chatName string 群名称（page=1时返回）
		 * @return_param    owner_name string 群主名称（page=1时返回）
		 * @return_param    join_num int 在群成员数（page=1时返回）
		 * @return_param    leave_num int 离群成员数（page=1时返回）
		 * @return_param    isAudit int 是否有会话存档1是0否
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.avatar string 成员头像
		 * @return_param    list.name string 成员名称
		 * @return_param    list.type_name string 成员身份
		 * @return_param    list.join_time string 入群时间
		 * @return_param    list.leave_time string 离群时间
		 * @return_param    list.join_scene string 入群方式
		 * @return_param    list.audit_num string 消息数量
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/05/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatInfoList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$chatId     = \Yii::$app->request->post('chat_id');
			$status     = \Yii::$app->request->post('status');
			$name       = \Yii::$app->request->post('name', '');
			$stime      = \Yii::$app->request->post('stime', '');
			$etime      = \Yii::$app->request->post('etime', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$isExport   = \Yii::$app->request->post('is_export', 0);
			$isAll      = \Yii::$app->request->post('is_all', 0);
			$now_userid = \Yii::$app->request->post('now_userid', '');
			$name       = trim($name);

			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if (empty($chatId)) {
				$chatid = \Yii::$app->request->post('chatid');
				if (empty($chatid)) {
					throw new InvalidParameterException('客户群数据不正确！');
				}
				$chatInfo = WorkChat::findOne(['corp_id' => $this->corp->id, 'chat_id' => $chatid]);
				if (!empty($chatInfo)) {
					$chatId = $chatInfo->id;
				}
			} else {
				$chatInfo = WorkChat::findOne($chatId);
			}

			if (empty($chatInfo)) {
				throw new InvalidParameterException('客户群数据不正确！');
			}

			//是否有会话存档
			$auditInfo = WorkChat::chatAuditInfo($chatInfo->corp_id, $chatInfo->id, 0);
			$isAudit   = $auditInfo['isAudit'];

			$chatName  = '';
			$ownerName = '';
			$joinNum   = 0;
			$leaveNum  = 0;
			if ($page == 1) {
				$chatName = $chatInfo->name;

				if (!empty($chatInfo->owner_id)) {
					$workUser   = WorkUser::findOne($chatInfo->owner_id);
					$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
					$ownerName  = $departName . '--' . $workUser->name;
				} else {
					$ownerId = 0;
					try {
						$ownerId = WorkExternalContact::getExternalId($this->corp->id, $chatInfo->owner);
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), __CLASS__ . '-' . __FUNCTION__ . ':getExternalId');
					}

					if ($ownerId == 0) {
						$ownerName = '外部非联系人：' . $chatInfo->owner;
					} else {
						$externalContact = WorkExternalContact::findOne($ownerId);
						$ownerName       = '外部联系人：' . $externalContact->name;
					}
				}

				$joinNum  = WorkChatInfo::find()->andWhere(['chat_id' => $chatId, 'status' => 1])->count();
				$leaveNum = WorkChatInfo::find()->andWhere(['chat_id' => $chatId, 'status' => 0])->count();
			}

			$offset       = ($page - 1) * $pageSize;
			$chatInfoData = WorkChatInfo::find()->andWhere(['chat_id' => $chatId]);

			if ($status != '-1') {
				$chatInfoData = $chatInfoData->andWhere(['status' => $status]);
			}
			if (!empty($stime) && !empty($etime)) {
				if ($status == 0) {
					$chatInfoData = $chatInfoData->andFilterWhere(['between', 'leave_time', strtotime($stime), strtotime($etime . ':59')]);
				} else {
					$chatInfoData = $chatInfoData->andFilterWhere(['between', 'join_time', strtotime($stime), strtotime($etime . ':59')]);
				}
			}
			if (!empty($name)) {
				//外部联系人
				$workExternalData = WorkExternalContact::find()->alias('we');
				$workExternalData = $workExternalData->andWhere(['we.corp_id' => $this->corp['id']]);
				$workExternalData = $workExternalData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
				$workExternalData = $workExternalData->andWhere(' we.name_convert like \'%' . $name . '%\' or (cf.fieldid=2 and cf.value like \'%' . $name . '%\')');
				$workExternalData = $workExternalData->select('we.id as wid')->groupBy('we.id')->asArray()->all();
				$external_id      = array_column($workExternalData, 'wid');
				//成员
				$workUserData = WorkUser::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => 0])->andWhere(['like', 'name', $name]);
				$workUserData = $workUserData->select('id')->asArray()->all();
				$user_id      = array_column($workUserData, 'id');

				if (!empty($external_id) && !empty($user_id)) {
					$chatInfoData = $chatInfoData->andWhere(['or', ['in', 'external_id', $external_id], ['in', 'user_id', $user_id]]);
				} elseif (!empty($external_id)) {
					$chatInfoData = $chatInfoData->andWhere(['in', 'external_id', $external_id]);
				} elseif (!empty($user_id)) {
					$chatInfoData = $chatInfoData->andWhere(['in', 'user_id', $user_id]);
				} else {
					$chatInfoData = $chatInfoData->andWhere(['id' => 0]);
				}
			}

			$count = $chatInfoData->count();

			if (empty($isAll)) {
				$chatInfoData = $chatInfoData->limit($pageSize)->offset($offset);
			}

			$chatField    = new Expression('`type`,`userid`,`user_id`,`external_id`,`join_time`,`leave_time`,`join_scene`,`status`');
			$chatInfoData = $chatInfoData->select($chatField)->orderBy(['type' => SORT_ASC, 'join_time' => SORT_ASC])->asArray()->all();

			$result = [];
			foreach ($chatInfoData as $key => $val) {
				$chatD           = [];
				$isOwner         = $val['userid'] == $chatInfo->owner;
				$external_userid = '';
				if ($val['type'] == 2) {
					$externalContact = WorkExternalContact::findOne(['corp_id' => $this->corp['id'], 'external_userid' => $val['userid']]);

					$avatar          = !empty($externalContact) ? $externalContact->avatar : "";
					$name            = !empty($externalContact->name) ? rawurldecode($externalContact->name) : '未知';
					$gender          = !empty($externalContact) ? $externalContact->gender : 0;
					$corpName        = !empty($externalContact) ? $externalContact->corp_name : '未知';
					/*$fieldInfo       = CustomFieldValue::findOne(['cid' => $val['external_id'], 'type' => 1, 'fieldid' => 2]);
					if ($fieldInfo && $fieldInfo->value) {
						$name .= '（' . $fieldInfo->value . '）';
					}*/
					$fieldSex = CustomFieldValue::findOne(['cid' => $val['external_id'], 'type' => 1, 'fieldid' => 3]);
					if ($fieldSex) {
						if ($fieldSex->value == '男') {
							$gender = 1;
						} elseif ($fieldSex->value == '女') {
							$gender = 2;
						} else {
							$gender = 0;
						}
					}
					$typeName = ($isOwner ? '群主-' : "") . (!empty($externalContact) ? '外部联系人' : '外部非联系人');
					$chatName = WorkChatInfo::getChatList(2, $val['external_id'], $chatId);
					$userType = !empty($externalContact) ? 2 : 3;
					if (!empty($now_userid) && !empty($externalContact)) {
						$followUser      = WorkExternalContactFollowUser::find()->where(['userid' => $now_userid, 'external_userid' => $externalContact->id])->andWhere(['del_type' => [0, 2]])->one();
						$external_userid = !empty($followUser) ? $val['userid'] : '';
					}
				} else {
					$workUser = WorkUser::findOne($val['user_id']);
					$avatar   = !empty($workUser) ? $workUser->avatar : '';
					$name     = !empty($workUser) ? $workUser->name : '未同步的员工';
					$gender   = !empty($workUser) ? $workUser->gender : 0;

					if (!empty($workUser)) {
						if ($workUser->is_del == 1) {
							$name .= '【已删除】';
						} elseif ($workUser->status == 2) {
							$name .= '【已禁用】';
						}
					}
					$corpName = "";
					$typeName = $isOwner ? '群主' : '企业成员';
					$chatName = WorkChatInfo::getChatList(1, $val['user_id'], $chatId);
					$userType = 1;
				}

				if ($val['join_scene'] == 1) {
					$joinScene = '由成员邀请入群（直接邀请入群）';
				} elseif ($val['join_scene'] == 2) {
					$joinScene = '由成员邀请入群（通过邀请链接入群）';
				} elseif ($val['join_scene'] == 3) {
					$joinScene = '通过扫描群二维码入群';
				} else {
					$joinScene = '';
				}
				$chatD['user_type']  = $isOwner ? 0 : $userType;
				$chatD['avatar']     = $avatar;
				$chatD['name']       = $name;
				$chatD['gender']     = $gender;
				$chatD['corp_name']  = $corpName;
				$chatD['type_name']  = $typeName;
				$chatD['join_time']  = date('Y-m-d H:i', $val['join_time']);
				$chatD['leave_time'] = $val['status'] == 0 && !empty($val['leave_time']) ? date('Y-m-d H:i', $val['leave_time']) : '--';
				$chatD['join_scene'] = $joinScene;
				$chatD['chat_name']  = $chatName;
				$chatD['external_userid']  = $external_userid;

				if ($isAudit == 1) {
					$chatAudit = WorkMsgAuditInfo::find()->where(['chat_id' => $chatInfo->id]);
					if ($val['type'] == 1) {
						$chatAudit = $chatAudit->andWhere(['from_type' => 1, 'user_id' => $val['user_id']]);
					} else {
						$chatAudit = $chatAudit->andWhere(['from_type' => 2, 'external_id' => $val['external_id']]);
					}
					$auditNum           = $chatAudit->count();
					$chatD['audit_num'] = $auditNum;
				}else{
					$chatD['audit_num'] = '--';
				}

				if (!$isOwner) {
					array_push($result, $chatD);
				} else {
					array_unshift($result, $chatD);
				}
			}

			//导出
			if ($isExport == 1) {
				if (empty($result)) {
					throw new InvalidParameterException('暂无数据，无法导出！');
				}
				$saveDir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($saveDir) && !mkdir($saveDir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}

				$columns  = ['name', 'type_name', 'join_time', 'leave_time', 'join_scene'];
				$headers  = [
					'name'       => '群成员',
					'type_name'  => '成员身份',
					'join_time'  => '入群时间',
					'leave_time' => '退群时间',
					'join_scene' => '入群方式',
				];
				$fileName = '群成员列表_' . date("YmdHis", time());
				Excel::export([
					'models'       => $result,//数库
					'fileName'     => $fileName,//文件名
					'savePath'     => $saveDir,//下载保存的路径
					'asAttachment' => true,//是否下载
					'columns'      => $columns,//要导出的字段
					'headers'      => $headers
				]);
				$url = \Yii::$app->params['site_url'] . str_replace(\Yii::getAlias('@upload'), '/upload', $saveDir) . $fileName . '.xlsx';

				return [
					'url' => $url,
				];
			}

			return [
				'chatName'   => $chatName,
				'owner_name' => $ownerName,
				'join_num'   => $joinNum,
				'leave_num'  => $leaveNum,
				'isAudit'    => $isAudit,
				'count'      => $count,
				'list'       => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群成员统计
		 * @description     群成员统计
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-info-statistic
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 * @param chat_id    必选 int 群id
		 * @param s_date     必选 string 开始日期
		 * @param e_date     必选 string 结束日期
		 * @param s_week     选填 int 按周时传
		 * @param type       必选 int 1按天2按周3按月
		 * @param is_export  选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chat_stat array 总数据
		 * @return_param    chat_stat.member_sum int 总成员
		 * @return_param    chat_stat.leave_member_sum int 总退群成员
		 * @return_param    chat_stat.today_member_num int 今日新增成员
		 * @return_param    chat_stat.today_leave_member_num int 今日退群成员
		 * @return_param    chat_stat.time_member_num int 搜索时间内新增成员
		 * @return_param    chat_stat.time_leave_member_num int 搜索时间内退群成员
		 * @return_param    chat_data array 详细数据
		 * @return_param    chat_data.hour string 时间
		 * @return_param    chat_data.add_num int 新增成员数
		 * @return_param    chat_data.leave_num int 离开成员数
		 * @return_param    chat_data.member_snum int 总成员数
		 * @return_param    chat_data.leave_snum int 总退群数
		 * @return_param    xData array X轴数据
		 * @return_param    legData array 对应数据
		 * @return_param    seriesData array 总的数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/3
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatInfoStatistic ()
		{
			if (\Yii::$app->request->isPost) {
				$chat_id   = \Yii::$app->request->post('chat_id');
				$date1     = \Yii::$app->request->post('s_date');
				$date2     = \Yii::$app->request->post('e_date');
				$s_week    = \Yii::$app->request->post('s_week');
				$type      = \Yii::$app->request->post('type') ?: 1; //1按天
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($this->corp) || empty($chat_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}

				//根据类型获取数据
				$result = $this->getChatMemberByTime($type, $chat_id, $date1, $date2, $s_week);
				$url    = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['hour', 'add_num', 'leave_num', 'member_snum', 'leave_snum'];
					$headers = [
						'hour'        => '时间',
						'add_num'     => '新增成员数',
						'leave_num'   => '退群成员数',
						'member_snum' => '当前成员数',
						'leave_snum'  => '总退群数',
					];
					$fileName = '群成员统计_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['data'],//数库
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

				$time                   = strtotime(date('Y-m-d'));
				$member_sum             = WorkChatInfo::find()->andWhere(['chat_id' => $chat_id, 'status' => 1])->count();
				$leave_member_sum       = WorkChatInfo::find()->andWhere(['chat_id' => $chat_id, 'status' => 0])->count();
				$today_member_num       = WorkChatInfo::find()->andWhere(['chat_id' => $chat_id, 'status' => 1])->andWhere(['>', 'join_time', $time])->count();
				$today_leave_member_num = WorkChatInfo::find()->andWhere(['chat_id' => $chat_id, 'status' => 0])->andWhere(['>', 'leave_time', $time])->count();

				$chat_stat = [
					'member_sum'             => $member_sum,
					'leave_member_sum'       => $leave_member_sum,
					'today_member_num'       => $today_member_num,
					'today_leave_member_num' => $today_leave_member_num,
					'time_member_num'        => $result['addNum'],
					'time_leave_member_num'  => $result['leaveNum'],
				];

				$legData = ['新增成员数', '退群成员数'];
				$info    = [
					'chat_stat'  => $chat_stat,
					'chat_data'  => $result['data'],
					'url'        => $url,
					'legData'    => $legData,
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * 获取群成员数据
		 * $type 1按天 2按周 3按月
		 * $corp_id
		 * $chat_id
		 * $date1
		 * $date2
		 * $s_week
		 */
		private function getChatMemberByTime ($type, $chat_id, $date1, $date2, $s_week)
		{
			$xData      = [];//X轴
			$newData    = [];//新增
			$cancelData = [];//离开
			$addNum     = 0; //新增成员
			$leaveNum   = 0; //离开成员
			$result     = [];
			switch ($type) {
				case 1:
					//按天
					$data = DateUtil::getDateFromRange($date1, $date2);
					foreach ($data as $k => $v) {
						$chatData                  = WorkChatInfo::getChatMemberByTime($chat_id, $v, $v);
						$result[$k]['add_num']     = $chatData['add_num'];
						$result[$k]['leave_num']   = $chatData['leave_num'];
						$result[$k]['member_snum'] = $chatData['member_snum'];
						$result[$k]['leave_snum']  = $chatData['leave_snum'];
						$result[$k]['hour']        = $v;
						array_push($newData, intval($chatData['add_num']));
						array_push($cancelData, intval($chatData['leave_num']));
						$addNum   += $chatData['add_num'];
						$leaveNum += $chatData['leave_num'];
					}
					$xData = $data;
					break;
				case 2:
					//按周
					$data    = DateUtil::getDateFromRange($date1, $date2);
					$data    = DateUtil::getWeekFromRange($data);
					$s_date1 = $data['s_date'];
					$e_date1 = $data['e_date'];
					foreach ($s_date1 as $k => $v) {
						foreach ($e_date1 as $kk => $vv) {
							if ($k == $kk) {
								if ($s_week == 53) {
									$s_week = 1;
								}
								$chatData                  = WorkChatInfo::getChatMemberByTime($chat_id, $v, $vv);
								$result[$k]['add_num']     = $chatData['add_num'];
								$result[$k]['leave_num']   = $chatData['leave_num'];
								$result[$k]['member_snum'] = $chatData['member_snum'];
								$result[$k]['leave_snum']  = $chatData['leave_snum'];
								$result[$k]['hour']        = $v . '~' . $vv . '(' . $s_week . '周)';
								array_push($xData, $result[$k]['hour']);
								array_push($newData, intval($chatData['add_num']));
								array_push($cancelData, intval($chatData['leave_num']));
								$addNum   += $chatData['add_num'];
								$leaveNum += $chatData['leave_num'];
								$s_week++;
							}
						}
					}
					break;
				case 3:
					//按月
					$date = DateUtil::getLastMonth();
					foreach ($date as $k => $v) {
						$chatData                  = WorkChatInfo::getChatMemberByTime($chat_id, $v['firstday'], $v['lastday']);
						$result[$k]['add_num']     = $chatData['add_num'];
						$result[$k]['leave_num']   = $chatData['leave_num'];
						$result[$k]['member_snum'] = $chatData['member_snum'];
						$result[$k]['leave_snum']  = $chatData['leave_snum'];
						$result[$k]['hour']        = $v['time'];
						array_push($xData, $v['time']);
						array_push($newData, intval($chatData['add_num']));
						array_push($cancelData, intval($chatData['leave_num']));
						$addNum   += $chatData['add_num'];
						$leaveNum += $chatData['leave_num'];
					}

					break;

			}
			$info['addNum']     = $addNum;
			$info['leaveNum']   = $leaveNum;
			$info['data']       = $result;
			$info['xData']      = $xData;
			$seriesData         = [
				[
					'name'   => '新增成员',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $newData,
				],
				[
					'name'   => '离开成员',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $cancelData,
				],
			];
			$info['seriesData'] = $seriesData;

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           更改客户群所属分组
		 * @description     更改客户群所属分组
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-change-group
		 *
		 * @param chat_id    必选 array 群id
		 * @param group_id   必选 int 群分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatChangeGroup ()
		{
			if (\Yii::$app->request->isPost) {
				$chat_ids = \Yii::$app->request->post('chat_id');
				$group_id = \Yii::$app->request->post('group_id', 0);
				if (empty($chat_ids) || empty($group_id)) {
					throw new InvalidParameterException('参数不正确');
				}

				$chatGroup = WorkChatGroup::findOne(['id' => $group_id, 'status' => 1]);
				if (empty($chatGroup)) {
					throw new InvalidParameterException('群分组数据错误');
				}

				WorkChat::updateAll(['group_id' => $group_id], ['id' => $chat_ids]);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           更新群公告
		 * @description     更新群公告
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-update-notice
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatUpdateNotice ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				WorkChat::updateChatNotice($this->corp['id']);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           同步客户群
		 * @description     同步客户群
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/refresh-work-chat
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionRefreshWorkChat ()
		{
			if (\Yii::$app->request->isPost) {
				ignore_user_abort();
				set_time_limit(0);

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$jobId = \Yii::$app->work->push(new SyncWorkChatJob([
					'corp' => $this->corp,
				]));

				return ['error' => 0];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群分组列表
		 * @description     群分组列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-group-list
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    key int key
		 * @return_param    group_name string 分组名称
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatGroupList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$chatGroupData = WorkChatGroup::find()->andWhere(['corp_id' => $this->corp['id'], 'status' => 1])->orderBy(['sort' => SORT_ASC])->all();
				if (empty($chatGroupData)){
					WorkChatGroup::add(0, $this->corp['id'], '未分组');
				}
				$result = [];
				foreach ($chatGroupData as $group) {
					$data = $group->dumpData();
					array_push($result, $data);
				}

				return [
					'info' => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           添加群分组
		 * @description     添加群分组
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-group-add
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param name    必选 string 分组名称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatGroupAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$name = \Yii::$app->request->post('name');
				$len  = mb_strlen($name, "utf-8");
				if ($len > 15) {
					throw new InvalidParameterException('名称不能超过15个字');
				}
				$groupName = WorkChatGroup::find()->andWhere(['group_name' => $name, 'corp_id' => $this->corp['id'], 'status' => 1])->one();
				if (!empty($groupName)) {
					throw new InvalidParameterException('分组名称不能重复');
				}
				WorkChatGroup::add(0, $this->corp['id'], $name);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           修改群分组
		 * @description     修改群分组
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-group-update
		 *
		 * @param id 必选 int 分组id
		 * @param name 必选 string 分组名称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatGroupUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$id   = \Yii::$app->request->post('id');
				$name = \Yii::$app->request->post('name');
				if (empty($id) || empty($name)) {
					throw new InvalidParameterException('参数不正确');
				}
				$len = mb_strlen($name, "utf-8");
				if ($len > 15) {
					throw new InvalidParameterException('名称不能超过15个字');
				}
				$chatGroup = WorkChatGroup::findOne($id);
				if($name == $chatGroup->group_name){
					return true;
				}
				$groupName  = WorkChatGroup::find()->andWhere(['!=', 'id', $id])->andWhere(['group_name' => $name, 'corp_id' => $chatGroup->corp_id, 'status' => 1])->one();
				if (!empty($groupName)) {
					throw new InvalidParameterException('分组名称不能重复');
				}
				WorkChatGroup::add($id, $this->corp['id'], $name);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           删除群分组
		 * @description     删除群分组
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-group-delete
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param id      必选 int 分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatGroupDelete ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确');
				}
				$chatGroup = WorkChatGroup::findOne($id);

				if (!empty($chatGroup)) {
					if ($chatGroup->group_name == '未分组'){
						throw new InvalidParameterException('未分组不可删除');
					}

					$chatGroup->status = 0;
					if (!$chatGroup->validate() || !$chatGroup->save()) {
						throw new InvalidParameterException(SUtils::modelError($chatGroup));
					}

					$chatGroupData = WorkChatGroup::findOne(['corp_id' => $this->corp['id'], 'group_name' => '未分组']);
					WorkChat::updateAll(['group_id' => $chatGroupData->id], ['corp_id' => $this->corp['id'], 'group_id' => $chatGroup->id]);
				} else {
					throw new InvalidParameterException('群分组数据错误');
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群分组排序
		 * @description     群分组排序
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-group-sort
		 *
		 * @param ids 必选 array  分组id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/5/31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatGroupSort ()
		{
			if (\Yii::$app->request->isPost) {
				$ids = \Yii::$app->request->post('ids');
				if (empty($ids) || !is_array($ids)) {
					throw new InvalidParameterException('参数不正确！');
				}
				foreach ($ids as $k => $v) {
					$chatGroup       = WorkChatGroup::findOne($v);
					$chatGroup->sort = $k;
					$chatGroup->save();
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群欢迎语列表
		 * @description     群欢迎语列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-welcome-list
		 *
		 * @param corp_id  必选 string 企业的唯一ID
		 * @param page     可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    wel_type string 欢迎语类型
		 * @return_param    time string 创建时间
		 * @return_param    add_type int 1图片2网页3小程序0都没有
		 * @return_param    text_content string 文本内容
		 * @return_param    image_url string 图片的url地址
		 * @return_param    link_title string 网页标题
		 * @return_param    link_pic_url string 图文封面的url地址
		 * @return_param    link_desc string 图文消息描述
		 * @return_param    link_url string 图文消息链接
		 * @return_param    mini_title string 小程序消息标题
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/2
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatWelcomeList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page     = \Yii::$app->request->post('page', 1);
				$pageSize = \Yii::$app->request->post('pageSize', 15);
				$offset   = ($page - 1) * $pageSize;
				$welcome  = WorkChatWelcome::find()->andWhere(['corp_id' => $this->corp['id'], 'status' => 1]);
				$count    = $welcome->count();
				$welcome  = $welcome->limit($pageSize)->offset($offset)->orderBy(['create_time' => SORT_DESC])->all();
				$result   = [];
				if (!empty($welcome)) {
					foreach ($welcome as $key => $wel) {
						$result[$key]['id']       = $wel['id'];
						$result[$key]['wel_type'] = WorkChatWelcome::getData($wel);
						$result[$key]['time']     = date('Y-m-d H:i:s', $wel->create_time);
						$result[$key]['add_type'] = 0;
						$content                  = [];
						if (!empty($wel->context)) {
							$content = json_decode($wel->context, true);
						}
						$contentData = WorkChatWelcome::getContentData($content);

						if (!empty($content['text'])) {
							$result[$key]['text_content'] = $contentData['text_content'];
						}
						if (!empty($content['image'])) {
							$result[$key]['image_url'] = $contentData['image_url'];
							$result[$key]['add_type']  = $contentData['add_type'];
						}
						if (!empty($content['link'])) {
							if (!empty($wel->attachment_id)) {
								$attachment   = Attachment::findOne($wel->attachment_id);
								$link_pic_url = $attachment->local_path;
							} else {
								$link_pic_url = $contentData['link_pic_url'];
							}
							$result[$key]['link_title']   = $contentData['link_title'];
							$result[$key]['link_pic_url'] = $link_pic_url;
							$result[$key]['link_desc']    = $contentData['link_desc'];
							$result[$key]['link_url']     = $contentData['link_url'];
							$result[$key]['add_type']     = $contentData['add_type'];
						}
						if (!empty($content['miniprogram'])) {
							$result[$key]['mini_title'] = $contentData['mini_title'];
							$result[$key]['add_type']   = $contentData['add_type'];
						}
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           添加/修改群欢迎语
		 * @description     添加/修改群欢迎语
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-welcome-add
		 *
		 * @param corp_id            必选 string 企业的唯一ID
		 * @param id                 可选 int 群欢迎语id（修改时传）
		 * @param add_type           可选 int 1图片2网页3小程序
		 * @param text_content       可选 string 文本内容
		 * @param media_id           可选 int 图片企业微信素材表id
		 * @param link_title         可选 string 网页标题
		 * @param link_attachment_id 可选 int 网页封面id来源素材表
		 * @param link_desc          可选 string    网页描述
		 * @param link_url           可选 string    网页链接
		 * @param mini_title         可选 string    小程序标题
		 * @param mini_pic_media_id  可选 int  小程序封面企业微信素材表id
		 * @param mini_appid         可选 string    小程序appid
		 * @param mini_page          可选 string    小程序page路径
		 * @param attachment_id      可选 int  内容引擎id
		 * @param materialSync       可选 int  0不同步到内容库1同步
		 * @param groupId            可选 int  素材分组id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/2
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionChatWelcomeAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$data['id']                 = \Yii::$app->request->post('id'); //编辑时传
				$data['add_type']           = \Yii::$app->request->post('add_type');//1图片2网页3小程序
				$data['text_content']       = \Yii::$app->request->post('text_content');
				$data['media_id']           = \Yii::$app->request->post('media_id');
				$data['link_title']         = \Yii::$app->request->post('link_title');
				$data['link_attachment_id'] = \Yii::$app->request->post('link_attachment_id');
				$data['link_desc']          = \Yii::$app->request->post('link_desc');
				$data['link_url']           = \Yii::$app->request->post('link_url');
				$data['mini_title']         = \Yii::$app->request->post('mini_title');
				$data['mini_pic_media_id']  = \Yii::$app->request->post('mini_pic_media_id');
				$data['mini_appid']         = \Yii::$app->request->post('mini_appid');
				$data['mini_page']          = \Yii::$app->request->post('mini_page');
				$data['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
				$data['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
				$data['group_id']           = \Yii::$app->request->post('groupId') ?: 0;
				$data['mini_title']         = trim($data['mini_title']);
				$data['text_content']       = trim($data['text_content']);
				$data['corp_id']            = $this->corp['id'];
				$data['uid']                = $this->user->uid;

				//添加/修改
				WorkChatWelcome::add($data);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群欢迎语详情
		 * @description     群欢迎语详情
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-welcome-detail
		 *
		 * @param id        必选 int 欢迎语id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    corp_id string 当前corp_id
		 * @return_param    corp_name string 当前企业微信
		 * @return_param    add_type int 1图片2网页3小程序0都没有
		 * @return_param    text_content string 文本内容
		 * @return_param    image_attachment_id string 图片的id
		 * @return_param    image_url string 图片的url地址
		 * @return_param    link_title string 网页标题
		 * @return_param    link_attachment_id int 网页封面的id
		 * @return_param    link_pic_url string 图文封面的url地址
		 * @return_param    link_desc string 图文消息描述
		 * @return_param    link_url string 图文消息链接
		 * @return_param    mini_title string 小程序消息标题
		 * @return_param    mini_attachment_id string 小程序消息封面的id
		 * @return_param    mini_pic_url string 小程序消息封面url
		 * @return_param    mini_appid string 小程序appid
		 * @return_param    mini_page string 小程序路径
		 * @return_param    attachment_id  int  内容引擎id
		 * @return_param    material_sync  int  0不同步到内容库1同步
		 * @return_param    groupId        int  素材分组id
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/2
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatWelcomeDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$work_welcome = WorkChatWelcome::findOne(['id' => $id, 'status' => 1]);
				if (empty($work_welcome)) {
					throw new InvalidParameterException('欢迎语数据不存在！');
				}
				$corp = WorkCorp::findOne($work_welcome->corp_id);

				$data                  = [];
				$data['corp_id']       = $corp->corpid;
				$data['corp_name']     = $corp->corp_name;
				$data['add_type']      = 0;
				$data['material_sync'] = $work_welcome->material_sync;
				$data['attachment_id'] = $work_welcome->attachment_id;
				$data['groupId']       = $work_welcome->group_id;
				$data['text_content']  = '';
				$content               = [];
				if (!empty($work_welcome->context)) {
					$content = json_decode($work_welcome->context, true);
				}
				$contentData = WorkChatWelcome::getContentData($content);

				$data = WorkChatWelcome::getWelcomeData($data, $content, $contentData);

				return $data;

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群欢迎语删除
		 * @description     群欢迎语删除
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-welcome-delete
		 *
		 * @param id 必选 int 欢迎语id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/2
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatWelcomeDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				WorkChatWelcome::updateStatus($id);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		//客户群首次拉取脚本
		public function actionGetChatFirst ()
		{
			WorkChat::getChatFirst();
		}

		//客户群首次统计脚本
		public function actionGetChatDayFirst ()
		{
			WorkChat::getChatDayStatistic(1);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           客户群昨日统计概况
		 * @description     客户群昨日统计概况
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-statistic-yesterday
		 *
		 * @param corp_id    必选 string 企业唯一标志
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 标题描述
		 * @return_param    status int 状态1上升2下降0持平
		 * @return_param    count int 昨日数量
		 * @return_param    per string 昨日较前日百分比
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/4
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatStatisticYesterday ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if(isset($this->subUser->sub_id)){
					$user_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id,$this->corp->id);
					if(is_array($user_ids)){
						return WorkChatStatistic::getWorkChatStatisticData($this->corp['id'],$user_ids);
					}
					if($user_ids === false){
						return [
							['title'=>"昨日新增群成员数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"昨日有发过消息的群成员数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"昨日退群人群", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"昨日客户群消息总数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"昨日新增客户群数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"昨日有发过消息的客户群数量", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
							['title'=>"群总数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
						];
					}
				}
				$result = WorkChatStatistic::getWorkChatStatisticData($this->corp['id']);

				return $result;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           客户群TOP10统计
		 * @description     客户群TOP10统计
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-statistic-top
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param search_type    必选 int 查询类型：1按群2按组3按群主
		 * @param group_id       可选 int 分组：0表示全部
		 * @param user_ids       可选 array 员工id集合
		 * @param data_type      必选 int 数据类型：1新增群成员数2退群人数3群聊消息总数(search_type=3时才有)
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param s_week         选填 int 按周时传
		 * @param type           必选 int 1按天2按周3按月
		 * @param is_export      选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chatData array 详细数据列表
		 * @return_param    chatData.sort int 排行
		 * @return_param    chatData.name string 群名称/群主名称
		 * @return_param    chatData.group_name string 分组名称
		 * @return_param    chatData.all_num int 统计数据
		 * @return_param    xData array X轴数据
		 * @return_param    seriesData array Y轴数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/4
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatStatisticTop ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corp_id     = $this->corp['id'];
				$search_type = \Yii::$app->request->post('search_type', 1);
				$group_id    = \Yii::$app->request->post('group_id', 0);
				$user_ids    = \Yii::$app->request->post('user_ids');
				$data_Type   = \Yii::$app->request->post('data_type', 1);
				$s_date      = \Yii::$app->request->post('s_date');
				$e_date      = \Yii::$app->request->post('e_date');
				$s_week      = \Yii::$app->request->post('s_week');
				$type        = \Yii::$app->request->post('type', 1); //天
				$is_export   = \Yii::$app->request->post('is_export');
				$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
					if (empty($user_ids)) {
						return [
							"chatData"=>[],
							"seriesData"=>[],
							"url"=>'',
							"xData"=>[],
						];
					}
				}
				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$sub_id,0,true);
					if (empty($user_ids)) {
						return [
							"chatData"=>[],
							"seriesData"=>[],
							"url"=>'',
							"xData"=>[],
						];
					}
				}else{
					$user_ids    = empty($user_ids) ? 1 : $user_ids;
				}

				/*if ($search_type == 3 && empty($user_ids)) {
					throw new InvalidParameterException('请选择群主！');
				}*/
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}
				if (in_array($search_type, [1, 2]) && $data_Type == 3) {
					throw new InvalidParameterException('数据类型错误！');
				}

				//根据查询类型获取数据
				if ($search_type == 1) {
					//按群
					$result = WorkChat::getChatTopByChat($corp_id, $data_Type, $s_date, $e_date,'',$user_ids);
				} elseif ($search_type == 2) {
					//按分组
					if (!empty($group_id)) {
						$result = WorkChat::getChatTopByChat($corp_id, $data_Type, $s_date, $e_date, $group_id,$user_ids);//某个分组下的群
					} else {
						$result = WorkChat::getChatTopByGroup($corp_id, $data_Type, $s_date, $e_date);//按分组查询
					}
				} elseif ($search_type == 3) {
					//按群主
					$result = WorkChat::getChatTopByOwner($corp_id, $data_Type, $s_date, $e_date, $user_ids);//按群主查询
				}

				switch ($data_Type) {
					case 2:
						$typeName = '退群人数';
						break;
					case 3:
						$typeName = '群聊消息总数';
						break;
					default:
					case 1:
						$typeName = '新增群成员数';
						break;
				}

				$url = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}

					if ($search_type == 1) {
						//按群
						$columns = ['sort', 'name', 'group_name', 'all_num'];
						$headers = [
							'sort'       => '排行榜',
							'name'       => '群名称',
							'group_name' => '所属分组',
							'all_num'    => $typeName
						];
					} elseif ($search_type == 2) {
						//按分组
						if (!empty($group_id)) {
							$columns = ['sort', 'name', 'group_name', 'all_num'];
							$headers = [
								'sort'       => '排行榜',
								'name'       => '群名称',
								'group_name' => '所属分组',
								'all_num'    => $typeName
							];
						} else {
							$columns = ['sort', 'group_name', 'all_num'];
							$headers = [
								'sort'       => '排行榜',
								'group_name' => '群分组',
								'all_num'    => $typeName
							];
						}
					} elseif ($search_type == 3) {
						//按群主
						$columns = ['sort', 'name', 'all_num'];
						$headers = [
							'sort'    => '排行榜',
							'name'    => '群主名称',
							'all_num' => $typeName
						];
					}

					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['data'],//数库
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
				$info = [
					'chatData'   => $result['data'],
					'url'        => $url,
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           客户群分类统计
		 * @description     客户群分类统计
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-statistic-classify
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param data_type      必选 int 数据类型：1新增群成员数2退群人数3群聊消息总数4新增群5群成员总数
		 * @param search_type    可选 int 查询类型：0无1按群2按组3按群主
		 * @param group_id       可选 int 分组id
		 * @param user_ids       可选 array 员工id集合
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param s_week         选填 int 按周时传
		 * @param type           必选 int 1按天2按周3按月
		 * @param is_export      选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chatData array 详细数据列表
		 * @return_param    chatData.time string 时间
		 * @return_param    chatData.add_num int 统计数据
		 * @return_param    xData array X轴数据
		 * @return_param    legData array 对应数据
		 * @return_param    seriesData array Y轴数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/6/4
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatStatisticClassify ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corp_id     = $this->corp['id'];
				$data_Type   = \Yii::$app->request->post('data_type', 1);
				$search_type = \Yii::$app->request->post('search_type', 0);
				$group_id    = \Yii::$app->request->post('group_id', 0);
				$user_ids    = \Yii::$app->request->post('user_ids');
				$s_date      = \Yii::$app->request->post('s_date');
				$e_date      = \Yii::$app->request->post('e_date');
				$s_week      = \Yii::$app->request->post('s_week');
				$type        = \Yii::$app->request->post('type', 1); //天
				$is_export   = \Yii::$app->request->post('is_export');
				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail === false) {
						return ['chatData'=>[],'legData'=>[],'seriesData'=>[],'xData'=>[]];
					}
					if (is_array($sub_detail)) {
						$user_ids = $sub_detail;
					}
					if ($sub_detail === true) {
						$user_ids = 0;
					}
				}else{
					$user_ids    = empty($user_ids) ? 1 : $user_ids;
				}
				$user_ids    = empty($user_ids) ? 1 : $user_ids;

				if ($search_type == 2 && empty($group_id)) {
					throw new InvalidParameterException('请选择分组！');
				}
				/*if ($search_type == 3 && empty($user_ids)) {
					throw new InvalidParameterException('请选择群主！');
				}*/
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}
				if (in_array($search_type, [1, 2]) && $data_Type == 4) {
					throw new InvalidParameterException('数据类型错误！');
				}
				if ($search_type && $data_Type == 4) {
					throw new InvalidParameterException('新增群搜索类型错误！');
				}

				//根据查询类型获取数据
				if ($search_type == 1) {
					//按群
					$result = WorkChat::getChatIncrease($corp_id, $data_Type, $type, $s_date, $e_date, $s_week,0,$user_ids);
				} elseif ($search_type == 2) {
					//按分组
					$result = WorkChat::getChatIncrease($corp_id, $data_Type, $type, $s_date, $e_date, $s_week, $group_id,$user_ids);
				} elseif ($search_type == 3) {
					//按群主
					$result = WorkChat::getChatIncrease($corp_id, $data_Type, $type, $s_date, $e_date, $s_week, 0, $user_ids);
				}else{
					$result = WorkChat::getChatIncrease($corp_id, $data_Type, $type, $s_date, $e_date, $s_week,0,$user_ids);
				}

				$typeName = $result['seriesData'][0]['name'];
				$url      = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns = ['time', 'add_num'];
					$headers = [
						'time'    => '时间',
						'add_num' => $typeName
					];
					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['data'],//数库
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

				$legData = [$typeName];
				$info    = [
					'chatData'   => $result['data'],
					'url'        => $url,
					'legData'    => $legData,
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群详情
		 * @description     群详情
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-detail
		 *
		 * @param uid 必选 string 账户id
		 * @param chat_id 必选 string 群id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-19 16:01
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid');
			$chatId = \Yii::$app->request->post('chat_id');
			if (empty($uid) || empty($chatId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$workChat = WorkChat::findOne($chatId);
			if (empty($workChat)) {
				throw new InvalidDataException('参数不正确！');
			}

			$result = WorkChat::getChatDetail($uid, $workChat);
			$result['is_hide_phone'] = $this->user->is_hide_phone;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群跟进记录
		 * @description     群跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/follow-record
		 *
		 * @param isMasterAccount  必选 string 1主账户2子账户
		 * @param uid              必选 string 用户ID
		 * @param sub_id           必选 string 子账户ID
		 * @param chat_id          必选 string 群ID
		 * @param page             可选 string 页码
		 * @param page_size        可选 string 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-19 16:04
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowRecord ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$chatId          = \Yii::$app->request->post('chat_id', 0);
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('page_size', 15);

			if (empty($uid) || empty($chatId) || empty($sub_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$workChat = WorkChat::findOne($chatId);
			if (empty($workChat)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$userId = 0;
			if ($isMasterAccount == 2) {
				$subUser = SubUser::findOne($sub_id);
				if (!empty($subUser)) {
					$workUser = WorkUser::findOne(['corp_id' => $workChat->corp_id, 'mobile' => $subUser->account]);
					if (!empty($workUser)) {
						$userId = $workUser->id;
					}
				}
			} else {
				$sub_id = 0;
			}
			$offset       = ($page - 1) * $pageSize;
			$userInfo     = UserProfile::findOne(['uid' => $uid]);
			$followRecord = WorkExternalContactFollowRecord::find()->where(['chat_id' => $chatId, 'type' => 3, 'status' => 1]);
			$count        = $followRecord->count();

			$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC])->asArray()->all();
			foreach ($followRecord as $k => $v) {
				$can_edit = 0;
				if (!empty($v['user_id']) && $v['is_master'] == 1) {
					$workUser = WorkUser::findOne($v['user_id']);
					$name     = $workUser->name;
				} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
					$subInfo  = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
					$name     = $subInfo->name;
					$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
				} else {
					$name     = $userInfo->nick_name;
					$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
				}
				if ($isMasterAccount == 2 && ($sub_id == $v['sub_id'] || $userId == $v['user_id']) && $v['is_master'] == 1) {
					$can_edit = 1;
				}
				$followRecord[$k]['name']     = $name;
				$followRecord[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
				$followRecord[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
				$followRecord[$k]['can_edit'] = $can_edit;
				$follow_status                = '';
				if (!empty($v['follow_id'])) {
					$follow        = Follow::findOne($v['follow_id']);
					$follow_status = $follow->title;
					if ($follow->status == 0) {
						$follow_status .= '（已删除）';
					}
				}
				$followRecord[$k]['follow_status'] = $follow_status;
			}

			return [
				'count'        => $count,
				'followRecord' => $followRecord,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           群跟进记录设置
		 * @description     群跟进记录设置
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/follow-record-set
		 *
		 * @param isMasterAccount  必选 string 1主账户2子账户
		 * @param uid              必选 string 用户ID
		 * @param sub_id           必选 string 子账户ID
		 * @param chat_id          必选 string 群ID
		 * @param follow_id 必选 string 跟进状态id
		 * @param record_id 可选 string 跟进记录id
		 * @param record 必选 string 跟进内容
		 * @param file 可选 string 跟进图片
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-19 16:08
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowRecordSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$follow_id       = \Yii::$app->request->post('follow_id', 0);
			$chatId          = \Yii::$app->request->post('chat_id', 0);
			$record_id       = \Yii::$app->request->post('record_id', 0);
			$record          = \Yii::$app->request->post('record', '');
			$file            = \Yii::$app->request->post('file', '');
			$record          = trim($record);
			if (empty($uid) || empty($chatId) || empty($sub_id) || empty($follow_id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if (empty($record) && empty($file)) {
				throw new InvalidParameterException('跟进内容和附件至少要填写一个！');
			}

			$workChat = WorkChat::findOne($chatId);
			if (empty($workChat)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if ($workChat->status == 4) {
				throw new InvalidParameterException('群已解散，不能再进行操作！');
			}

			$followInfo = Follow::findOne(['id' => $follow_id, 'status' => 1]);
			if (empty($followInfo)) {
				throw new InvalidParameterException('跟进状态已被删除，请更换！');
			}

			$userId = 0;
			//子账户
			if ($isMasterAccount == 2) {
				$subUser = SubUser::findOne($sub_id);
				if (!empty($subUser)) {
					$workUser = WorkUser::findOne(['corp_id' => $workChat->corp_id, 'mobile' => $subUser->account]);
					if (!empty($workUser)) {
						$userId = $workUser->id;
					}
				}
			}
			//更新跟进状态
			$workChat->follow_id = $follow_id;
			$workChat->update_time = time();
			if (!$workChat->save()) {
				throw new InvalidParameterException(SUtils::modelError($workChat));
			}

			if (empty($record_id)) {
				$followRecord            = new WorkExternalContactFollowRecord();
				$followRecord->uid       = $uid;
				$followRecord->type      = 3;
				$followRecord->chat_id   = $workChat->id;
				$followRecord->user_id   = $userId;
				$followRecord->status    = 1;
				$followRecord->sub_id    = $isMasterAccount == 1 ? 0 : $sub_id;
				$followRecord->time      = time();
				$followRecord->is_master = $isMasterAccount == 1 ? 0 : 1;
			} else {
				$followRecord           = WorkExternalContactFollowRecord::findOne($record_id);
				$followRecord->upt_time = time();
			}

			$followRecord->record    = $record;
			$followRecord->file      = !empty($file) ? json_encode($file) : '';
			$followRecord->follow_id = $follow_id;

			if (!$followRecord->save()) {
				throw new InvalidParameterException(SUtils::modelError($followRecord));
			}

			//记录客户群轨迹
			if (empty($record_id)) {
				$subId = $isMasterAccount == 1 ? 0 : $sub_id;
				if (!empty($userId)) {
					$workUser = WorkUser::findOne($userId);
					$name     = !empty($workUser) ? $workUser->name : '';
				} elseif (!empty($subId)) {
					$subInfo = SubUserProfile::findOne(['sub_user_id' => $subId]);
					$name    = !empty($subInfo) ? $subInfo->name : '';
				} else {
					$userInfo = UserProfile::findOne(['uid' => $uid]);
					$name     = !empty($userInfo) ? $userInfo->nick_name : '';
				}
				if (!empty($name)) {
					$name = '【' . $name . '】';
				}
				$remark = $name . '跟进状态为【' . $followInfo->title . '】';
				ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'sub_id' => $subId, 'user_id' => $userId, 'event' => 'chat_track', 'event_id' => 13, 'related_id' => $workChat->id, 'remark' => $remark]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           设置群标签
		 * @description     设置群标签
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/tags-set
		 *
		 * @param isMasterAccount  必选 string 1主账户2子账户
		 * @param uid              必选 string 用户ID
		 * @param sub_id           必选 string 子账户ID
		 * @param chat_id          必选 string 群ID
		 * @param tag_ids          可选 array 标签id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-19 16:13
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionTagsSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$chatId          = \Yii::$app->request->post('chat_id', '');
			$tagIds          = \Yii::$app->request->post('tag_ids', []);
			if (empty($uid) || empty($chatId) || empty($sub_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$workChat = WorkChat::findOne($chatId);
			if (empty($workChat)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if ($workChat->status == 4) {
				throw new InvalidParameterException('群已解散，不能再进行操作！');
			}

			if (count($tagIds) > 9999) {
				throw new InvalidParameterException('选择的标签数量不能超过9999个！');
			}

			$tagNow = $tagIds;//现有标签
			$tagOld = [];//客户原有标签

			$workTagContact = WorkTagChat::find()->alias('w');
			$workTagContact = $workTagContact->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`')->andWhere(['t.is_del' => 0, 'w.status' => 1, 'w.chat_id' => $workChat->id]);
			$workTagContact = $workTagContact->select('w.*');
			$contactTag     = $workTagContact->all();
			foreach ($contactTag as $k => $v) {
				array_push($tagOld, $v->tag_id);
			}

			$tagAdd = array_diff($tagNow, $tagOld);//添加的客户标签
			$tagDel = array_diff($tagOld, $tagNow);//删除的客户标签

			if($isMasterAccount == 1){
				$sub_id = 0;
			}
			//添加群标签
			if (!empty($tagAdd)) {
				$addData   = ['uid' => $uid, 'sub_id' => $sub_id, 'event' => 'chat_track', 'event_id' => 4, 'related_id' => $workChat->id];
				$addRemark = '系统打标签';
				$workTag   = WorkTag::find()->where(['id' => $tagAdd, 'is_del' => 0])->all();
				$tagName   = '';
				$tagIdArr  = [];
				if (!empty($workTag)) {
					foreach ($workTag as $tag) {
						$tagName .= '【' . $tag->tagname . '】、';
						array_push($tagIdArr, $tag->id);
					}
				}
				//给群打标签
				if (!empty($tagIdArr)) {
					WorkTagChat::addChatTag($workChat->corp_id, $workChat->id, $tagIdArr);
				}

				if (!empty($tagName)) {
					$tagName           = rtrim($tagName, '、');
					$addData['remark'] = $addRemark . $tagName;
					\Yii::error($addData, 'add_tag');
					ExternalTimeLine::addExternalTimeLine($addData);
				}
			}

			//删除群标签
			if (!empty($tagDel)) {
				WorkTagChat::removeChatTag($workChat->id, $tagDel);
				$addData      = ['uid' => $uid, 'sub_id' => $sub_id, 'event' => 'chat_track', 'event_id' => 5, 'related_id' => $workChat->id];
				$removeRemark = '系统移除标签';
				$workTag      = WorkTag::find()->where(['id' => $tagDel])->all();
				$tagName      = '';
				if (!empty($workTag)) {
					foreach ($workTag as $tag) {
						$tagName .= '【' . $tag->tagname . '】、';
					}
				}
				if (!empty($tagName)) {
					$tagName           = rtrim($tagName, '、');
					$addData['remark'] = $removeRemark . $tagName;
					\Yii::error($addData, 'remove_tag');
					ExternalTimeLine::addExternalTimeLine($addData);
				}
			}
			return [
				'error'     => 0,
				'error_msg' => "操作成功",
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           客户群行为轨迹
		 * @description     客户群行为轨迹
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-track
		 *
		 * @param uid              必选 string 用户ID
		 * @param chat_id          必选 string 群ID
		 * @param page             可选 string 页码
		 * @param page_size        可选 string 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-19 16:18
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatTrack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$chatId   = \Yii::$app->request->post('chat_id', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);
			if (empty($uid) || empty($chatId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$workChat = WorkChat::findOne($chatId);
			if (empty($workChat)) {
				throw new InvalidDataException('参数不正确！');
			}
			$result = WorkChat::getChatTrack($workChat->id, $page, $pageSize);

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat/
		 * @title           修改群高级属性
		 * @description     修改群高级属性
		 * @method   post
		 * @url  http://{host_name}/api/work-chat/chat-field-update
		 *
		 * @param corp_id     必选 string 企业唯一标志
		 * @param chat_id     可选 int 群ID
		 * @param chatid      可选 string 群chatid
		 * @param user_id     可选 int 员工ID
		 * @param fieldData   必选 array 群属性
		 * @param fieldData.fieldid  必选 int 属性ID
		 * @param fieldData.value    可选 int 属性值
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/11/04
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionChatFieldUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$uid       = $this->user->uid;
				$chat_id   = \Yii::$app->request->post('chat_id', 0);
				$chatid    = \Yii::$app->request->post('chatid', '');
				$user_id   = \Yii::$app->request->post('user_id', 0);
				$fieldData = \Yii::$app->request->post('fieldData', []);
				if (empty($uid) || empty($fieldData)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($chat_id) && empty($chatid)){
					throw new InvalidParameterException('客户群参数错误！');
				}
				if (!empty($chat_id)){
					$workChat = WorkChat::findOne($chat_id);
				}else{
					$workChat = WorkChat::findOne(['corp_id' => $this->corp->id, 'chat_id' => $chatid]);
				}
				if (empty($workChat)) {
					throw new InvalidParameterException('客户群数据错误！');
				}
				$workUser = [];
				if ($user_id) {
					$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'userid' => $user_id]);
				}

				$time     = time();
				$uptField = '';
				foreach ($fieldData as $k => $v) {
					$fieldid = intval($v['fieldid']);
					$value   = is_array($v['value']) ? $v['value'] : trim($v['value']);
					if (empty($fieldid)) {
						throw new InvalidParameterException('客户高级属性数据错误！');
					}
					$fieldValue = CustomFieldValue::findOne(['cid' => $workChat->id, 'type' => 3, 'fieldid' => $fieldid]);
					if (empty($fieldValue)) {
						if (empty($value)) {
							continue;
						}
						$fieldValue          = new CustomFieldValue();
						$fieldValue->type    = 3;
						$fieldValue->cid     = $workChat->id;
						$fieldValue->fieldid = $fieldid;
					} else {
						if ($value == $fieldValue->value) {
							continue;
						}
					}
					if ($v['key'] == 'image') {
						$imgVal = json_decode($fieldValue->value, true);
						if ($imgVal == $value) {
							continue;
						}
						$value = json_encode($value);
					}

					$fieldValue->uid  = $uid;
					$fieldValue->time = $time;

					if ($v['type'] == 5 && !empty($value)) {
						$phones = explode(',', $value);
						foreach ($phones as $phone) {
							if (strlen($phone) == 11 && !preg_match("/^1[3456789]{1}\d{9}$/", $phone)) {
								throw new InvalidParameterException('手机号格式不正确！');
							}
						}
					} elseif ($v['type'] == 6 && !empty($value)) {
						if (!preg_match("/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/", $value)) {
							throw new InvalidParameterException('邮箱格式不正确！');
						}
					}

					$fieldValue->value = $value;
					if (!$fieldValue->save()) {
						throw new InvalidParameterException(SUtils::modelError($fieldValue));
					}
					$uptField .= $fieldid . ',';
				}
				//记录群轨迹
				if (!empty($uptField)) {
					$customField = CustomField::find()->where('id IN (' . trim($uptField, ',') . ')')->select('`title`')->asArray()->all();
					$remark      = '';
					if (!empty($workUser)) {
						$remark .= '【' . $workUser->name . '】';
					}
					$remark .= '完善群信息：';
					foreach ($customField as $v) {
						$remark .= $v['title'] . '、';
					}
					$userId = !empty($workUser) ? $workUser->id : 0;

					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => 0, 'user_id' => $userId, 'event' => 'chat_track', 'event_id' => 12, 'related_id' => $workChat->id, 'remark' => substr($remark, 0, -1)]);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}
	}