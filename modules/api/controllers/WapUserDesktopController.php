<?php
	/**
	 * Create by PhpStorm
	 * title: 成员工作台数据
	 * Date: 2020/08/19
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\ApplicationSign;
	use app\models\Attachment;
	use app\models\AttachmentGroup;
	use app\models\AttachmentStatistic;
	use app\models\AuthoritySubUserDetail;
	use app\models\CustomFieldValue;
	use app\models\CustomField;
	use app\models\Fission;
	use app\models\Follow;
	use app\models\RedPack;
	use app\models\SubUser;
	use app\models\User;
	use app\models\UserCorpRelation;
	use app\models\WorkChat;
	use app\models\WorkChatContactWay;
	use app\models\WorkChatGroup;
	use app\models\WorkChatInfo;
	use app\models\WorkChatRemind;
	use app\models\WorkChatStatistic;
	use app\models\WorkChatWelcome;
	use app\models\WorkContactWay;
	use app\models\WorkCorp;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowRecord;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkExternalContactMember;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkNotFollowDay;
	use app\models\WorkPerTagFollowUser;
	use app\models\WorkTag;
	use app\models\WorkTagChat;
	use app\models\WorkTagContact;
	use app\models\WorkTagFollowUser;
	use app\models\WorkUser;
	use app\models\WorkDepartment;
	use app\models\WorkUserStatistic;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkChatJob;
	use app\util\DateUtil;
	use app\util\SUtils;
	use dovechen\yii2\weWork\Work;
	use yii\db\Expression;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;
	use moonland\phpexcel\Excel;

	class WapUserDesktopController extends WorkBaseController
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
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户数据
		 * @description     成员客户数据
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-custom-statistic
		 *
		 * @param corp_id        必选 string 企业微信ID
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    allExternalNum int 客户数
		 * @return_param    allChatNum int 客户群数
		 * @return_param    todayExternalNum int 今日新增客户
		 * @return_param    todayExternalDelNum int 今日流失客户
		 * @return_param    todayExternalFollowNum int 今日跟进客户
		 * @return_param    todayChatNum int 今日新增入群数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/08/19
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserCustomStatistic ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			$user_id         = \Yii::$app->request->post('user_id');
			$user_depart_ids = \Yii::$app->request->post('user_ids', []);
			if (empty($this->corp) || empty($user_id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$userInfo = WorkUser::findOne(['corp_id' => $this->corp->id, 'userid' => $user_id]);
			if ($userInfo) {
				$departName = WorkDepartment::getDepartNameByUserId($userInfo->id);
			} else {
				$departName = '--';
			}
			//分配员工数据
			$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
			$user_ids   = $userData['user_ids'];
			$user_count = $userData['userCount'];

			if ( in_array($userInfo->id,$user_ids)) {
				$is_self = 1;
			} else {
				$is_self = 0;
			}

			//客户数
			$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
			$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
			$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id])->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);
			if (!empty($user_ids)) {
				$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
			}
			$allExternalNum = $workExternalUserData->count();
			//客户群数
			/*if (!empty($user_ids)) {
				$allChatNum = WorkChatInfo::find()->andWhere(['status' => 1])->andWhere(['in', 'user_id', $user_ids])->count();
			} else {
				$allChatNum = WorkChat::find()->andWhere(['corp_id' => $this->corp->id])->andWhere(['!=', 'status', 4])->count();
			}*/
			$userChat = WorkChat::find()->andWhere(['corp_id' => $this->corp->id])->andWhere(['!=', 'status', 4]);
			if (!empty($user_ids)) {
				$userChat = $userChat->andWhere(['in', 'owner_id', $user_ids]);
			}
			$allChatNum = $userChat->count();
			//今日新增客户
			$todayTime            = strtotime(date('Y-m-d'));
			$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
			$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
			$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id])->andWhere(['wf.del_type' => 0])->andWhere(['>', 'wf.createtime', $todayTime]);
			if (!empty($user_ids)) {
				$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
			}
			$todayExternalNum = $workExternalUserData->count();
			//今日流失客户
			$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
			$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
			$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id])->andWhere(['wf.del_type' => 2])->andWhere(['>', 'wf.del_time', $todayTime]);
			if (!empty($user_ids)) {
				$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
			}
			$todayExternalDelNum = $workExternalUserData->count();
			//今日跟进客户
			$workExternalUserData = WorkExternalContactFollowRecord::find()->alias('wf');
			$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_id');
			$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id])->andWhere(['wf.type' => 1, 'wf.status' => 1])->andWhere(['>', 'wf.time', $todayTime]);
			if (!empty($user_ids)) {
				$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
			}
			$todayExternalFollowNum = $workExternalUserData->count();
			//今日新增入群数
			$workChatInfoData = WorkChatInfo::find()->alias('wci');
			$workChatInfoData = $workChatInfoData->leftJoin('{{%work_chat}} wc', '`wci`.`chat_id` = `wc`.`id`');
			$workChatInfoData = $workChatInfoData->andWhere(['wc.corp_id' => $this->corp->id,'wci.status' => 1, 'wci.type' => 2])->andWhere(['>', 'wci.join_time', $todayTime]);
			if (!empty($user_ids)) {
				$workChatInfoData = $workChatInfoData->andWhere(['in', 'wc.owner_id', $user_ids]);
			}
			$todayChatNum = $workChatInfoData->count();

			$info = [
				'name'                   => isset($userInfo->name) ? $userInfo->name : '--',
				'avatar'                 => isset($userInfo->avatar) ? $userInfo->avatar : '',
				'departName'             => $departName,
				'allExternalNum'         => $allExternalNum,
				'allChatNum'             => $allChatNum,
				'todayExternalNum'       => $todayExternalNum,
				'todayExternalDelNum'    => $todayExternalDelNum,
				'todayExternalFollowNum' => $todayExternalFollowNum,
				'todayChatNum'           => $todayChatNum,
				'show'                   => $userData['show'],
				'is_self'                => $is_self,
				'user_count'             => $user_count,
			];

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户跟进统计
		 * @description     成员客户跟进统计
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-custom-follow
		 *
		 * @param corp_id   必选 string 企业微信ID
		 * @param user_id   必选 string 企业成员userid
		 * @param user_ids  可选 array 员工id集合
		 * @param follow_id 必选 int 跟进状态ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    externalFollowNum int 当前跟进状态客户数
		 * @return_param    notFollowDayData array 未联系客户数据
		 * @return_param    day int 未联系日
		 * @return_param    num int 未联系数量
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/08/19
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserCustomFollow ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			$user_id         = \Yii::$app->request->post('user_id');
			$user_depart_ids = \Yii::$app->request->post('user_ids', []);
			$follow_id       = \Yii::$app->request->post('follow_id');
			if (empty($this->corp) || empty($user_id) || empty($follow_id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			//分配员工数据
			$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
			$user_ids   = $userData['user_ids'];
			$user_count = $userData['userCount'];

			//当前跟进状态客户数
			$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
			$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
			$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id, 'wf.follow_id' => $follow_id])->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);
			if (!empty($user_ids)) {
				$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
			}
			$externalFollowNum = $workExternalUserData->count();

			//自定义未跟进天数
			$notFollowDay     = WorkNotFollowDay::find()->where(['uid' => $this->user->uid, 'is_del' => 0])->orderBy(['day' => SORT_ASC])->asArray()->all();
			$notFollowDayD    = [];
			$notFollowDayData = [];
			array_push($notFollowDayD, 0);
			array_push($notFollowDayD, 1);
			array_push($notFollowDayD, 3);
			foreach ($notFollowDay as $v) {
				array_push($notFollowDayD, $v['day']);
			}
			foreach ($notFollowDayD as $day) {
				$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp->id])->andWhere(['wf.del_type' => [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN], 'wf.follow_id' => $follow_id]);
				if ($day == 0) {
					$workExternalUserData = $workExternalUserData->andWhere('wf.update_time = wf.createtime');
				} else {
					$time                 = strtotime(date('Y-m-d'));
					$workExternalUserData = $workExternalUserData->andWhere(['<', 'wf.update_time', $time - $day * 86400]);
				}
				if (!empty($user_ids)) {
					$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
				}
				$notFollowDayNum = $workExternalUserData->count();

				$notFollowD         = [];
				$notFollowD['day']  = $day;
				$notFollowD['num']  = $notFollowDayNum;
				$notFollowDayData[] = $notFollowD;
			}

			$info = [
				'externalFollowNum' => $externalFollowNum,
				'notFollowDayData'  => $notFollowDayData,
				'show'              => $userData['show'],
				'user_count'        => $user_count,
			];

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户群列表
		 * @description     成员客户群列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-chat-list
		 *
		 * @param corp_id        必选 string 企业微信ID
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 * @param name           可选 string 群名称
		 * @param owner_id       可选 int 群主成员ID
		 * @param status         可选 int 群状态：-1表示全部0正常1跟进人离职2离职继承中3离职继承完成4群已解散
		 * @param follow_id      可选 int 跟进状态id
		 * @param stime          可选 string 起始时间
		 * @param etime          可选 string 结束时间
		 * @param time_type      可选 int 时间类型：0全部1今日新增2本周新增3本月新增
		 * @param page           可选 int 页数
		 * @param page_size      可选 int 每页数量
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.chat_id int 群id
		 * @return_param    list.name string 群名称
		 * @return_param    list.tag_name array 群标签
		 * @return_param    list.status string 群状态
		 * @return_param    list.owner_name string 群主
		 * @return_param    list.member_num int 群人数
		 * @return_param    list.create_time string 群创建时间
		 * @return_param    list.follow_des string 跟进时间描述
		 * @return_param    list.follow_name string 跟进状态
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/08/19
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserChatList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$user_id         = \Yii::$app->request->post('user_id', '');
			$user_depart_ids = \Yii::$app->request->post('user_ids', []);
			$name            = \Yii::$app->request->post('name');
			$owner_id        = \Yii::$app->request->post('owner_id', '');
			$status          = \Yii::$app->request->post('status', 0);
			$follow_id       = \Yii::$app->request->post('follow_id', 0);
			$stime           = \Yii::$app->request->post('stime', '');
			$etime           = \Yii::$app->request->post('etime', '');
			$time_type       = \Yii::$app->request->post('time_type', '');
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('page_size', 15);
			$name            = trim($name);

			if (empty($this->corp) || empty($user_id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			//更新未分组
			if ($page == 1) {
				$chatGroupData = WorkChatGroup::findOne(['corp_id' => $this->corp['id'], 'status' => 1, 'group_name' => '未分组']);
				if (empty($chatGroupData)) {
					$defaultGroup = WorkChatGroup::add(0, $this->corp['id'], '未分组');
				} else {
					$defaultGroup = $chatGroupData->id;
				}
				WorkChat::updateAll(['group_id' => $defaultGroup], ['corp_id' => $this->corp['id'], 'group_id' => 0]);
			}

			//分配员工数据
			$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
			$user_ids   = $userData['user_ids'];
			$user_count = $userData['userCount'];

			//员工所在客户群
			/*if (!empty($user_ids)) {
				$userChat = WorkChatInfo::find()->andWhere(['status' => 1])->andWhere(['in', 'user_id', $user_ids])->select('chat_id')->asArray()->all();
			} else {
				$userChat = WorkChat::find()->andWhere(['corp_id' => $this->corp->id])->select('`id` chat_id')->asArray()->all();
			}*/
//			$userChat = WorkChat::find()->andWhere(['corp_id' => $this->corp->id]);
//			if (!empty($user_ids)) {
//				$userChat = $userChat->andWhere(['in', 'owner_id', $user_ids]);
//			}
//			$userChat = $userChat->select('`id` chat_id')->asArray()->all();
//
//			$userChatId = [];
//			foreach ($userChat as $v) {
//				if (!in_array($v['chat_id'], $userChatId)) {
//					array_push($userChatId, $v['chat_id']);
//				}
//			}

			$all_chat = [];
			$result   = [];
			$chatData = WorkChat::find()->andWhere(['corp_id' => $this->corp['id']]);

			if (!empty($name) || $name === '0') {
				$chatData = $chatData->andWhere(['like', 'name', $name]);
			}
			if (!empty($owner_id)) {
				$chatData = $chatData->andWhere(['owner_id' => $owner_id]);
			} elseif (!empty($user_ids)) {
				$chatData = $chatData->andWhere(['in', 'owner_id', $user_ids]);
			}
			if ($status != '-1') {
				$chatData = $chatData->andWhere(['status' => $status]);
			}
			if ($follow_id != '-1') {
				$chatData = $chatData->andWhere(['follow_id' => $follow_id]);
			}
			if (!empty($time_type) && !empty($stime) && !empty($etime)) {
				$chatData = $chatData->andFilterWhere(['between', 'create_time', strtotime($stime), strtotime($etime . ':59')]);
			}
			if ($time_type) {
				switch ($time_type) {
					case 1:
						$time     = strtotime(date('Y-m-d'));
						$chatData = $chatData->andWhere(['>', 'create_time', $time]);
						break;
					case 2:
						$week     = date('w') - 1;
						$time     = strtotime(date('Y-m-d', strtotime('-' . $week . ' days')));
						$chatData = $chatData->andWhere(['>', 'create_time', $time]);
						break;
					case 3:
						$time     = strtotime(date('Y-m-01'));
						$chatData = $chatData->andWhere(['>', 'create_time', $time]);
						break;
				}
			}

			$count     = $chatData->count();

			$offset   = ($page - 1) * $pageSize;
			$chatData = $chatData->limit($pageSize)->offset($offset);

			$chatData = $chatData->select('`id` chat_id,`chat_id` chat_id_str,`name`,`owner_id`,`owner`,`create_time`,`notice`,`group_id`,`status`,`update_time`,`follow_id`')->orderBy(['create_time' => SORT_DESC])->asArray()->all();

			foreach ($chatData as $key => $val) {
				$chatD                = [];
				$chatD['chat_id']     = $val['chat_id'];
				$chatD['chat_id_str'] = $val['chat_id_str'];
				$chatD['owner']       = $val['owner'];
				$chatD['name']        = WorkChat::getChatName($val['chat_id']);

				//群标签
				$workTagContact = WorkTagChat::find()->alias('w');
				$workTagContact = $workTagContact->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`')->andWhere(['t.is_del' => 0, 'w.status' => 1, 'w.chat_id' => $val['chat_id']]);
				$workTagContact = $workTagContact->select('t.*');
				$contactTag     = $workTagContact->asArray()->all();
				$tagName        = [];
				foreach ($contactTag as $k => $v) {
					$tagName[] = ['tid' => (int) $v['id'], 'tname' => $v['tagname']];
				}
				$chatD['tag_name'] = $tagName;

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
						$status = '已解散';
						break;
					default:
					case 0:
						$status = '正常';
						break;
				}
				if (!empty($val['owner_id'])) {
					//$departName = WorkDepartment::getDepartNameByUserId($val['owner_id']);
					$work_user = WorkUser::findOne($val['owner_id']);
					//$owner_name = $work_user->name . '--' . $departName;
					$owner_name = $work_user->name;
				} else {
					$ownerId = 0;
					try {
						$ownerId = WorkExternalContact::getExternalId($this->corp->id, $val['owner']);
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

				$chatD['status']      = $status;
				$chatD['owner_name']  = $owner_name;
				$chatD['member_num']  = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 1])->count();
				$chatD['create_time'] = date('Y-m-d H:i', $val['create_time']);

				if ($val['update_time']) {
					$followTime = time() - $val['update_time'];
					$followDay  = floor($followTime / 86400);
					$followStr  = $followDay > 0 ? $followDay . '天前沟通' : '今天有沟通';
				} else {
					$followStr = '一直未沟通';
				}
				$chatD['follow_des'] = $followStr;
				if ($val['follow_id']) {
					$followData           = Follow::findOne($val['follow_id']);
					$chatD['follow_name'] = $followData->title;
					if ($followData->status == 0){
						$chatD['follow_name'] .= '（已删除）';
					}
				} else {
					$chatD['follow_name'] = '未跟进';
				}
				$chatD['avatarData'] = WorkChat::getChatAvatar($val['chat_id'], $val['status']);

				$result[] = $chatD;
			}

			return [
				'count'      => $count,
				'user_count' => $user_count,
				'all_chat'   => $all_chat,
				'list'       => $result,
				'show'       => $userData['show'],
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户数量Top统计
		 * @description     成员客户数量Top统计
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-custom-num-top
		 *
		 * @param corp_id        必选 string 企业微信ID
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 * @param data_Type      必选 int 数据类型：1发起申请数；2新增客户数；3流失客户数；4总客户数
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param s_week         选填 int 按周时传
		 * @param type           必选 int 1按小时2按天3按周4按月
		 *
		 * @return          {"error":0,"data":{"user_data":[{"sort":"1","name":"flu","cnt_num":"1"},{"sort":"2","name":"fluu","cnt_num":"0"}],"url":"","xData":["flu","fluu"],"seriesData":["1","0"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_data array 底下的详细数据
		 * @return_param    xData array X轴数据
		 * @return_param    seriesData array Y轴数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/08/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserCustomNumTop ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$data_Type       = \Yii::$app->request->post('data_Type');
			$user_id         = \Yii::$app->request->post('user_id');
			$user_depart_ids = \Yii::$app->request->post('user_ids', []);
			$date1           = \Yii::$app->request->post('s_date');
			$date2           = \Yii::$app->request->post('e_date');
			$s_week          = \Yii::$app->request->post('s_week');
			$type            = \Yii::$app->request->post('type') ?: 2; //天
			if (empty($this->corp) || empty($user_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($date1) || empty($date2)) {
				throw new InvalidParameterException('请传入日期！');
			}
			if ($type == 3 && empty($s_week)) {
				throw new InvalidParameterException('请传入起始周！');
			}
			$corp_id = $this->corp['id'];

			//分配员工数据
			$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
			$user_ids   = $userData['user_ids'];
			$user_count = $userData['userCount'];
			//获取Top数据
			$result = WorkUserStatistic::getUserTopByType($data_Type, $corp_id, $date1, $date2, $user_ids);

			$info = [
				'user_data'  => $result['data'],
				'xData'      => $result['xData'],
				'seriesData' => $result['seriesData'],
				'show'       => $userData['show'],
				'user_count' => $user_count,
			];

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户数量趋势图统计
		 * @description     成员客户数量趋势图统计
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-custom-num-increase
		 *
		 * @param corp_id        必选 string 企业微信ID
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 * @param data_Type      必选 int 数据类型：1发起申请数；2新增客户数；3流失客户数；4总客户数
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param s_week         选填 int 按周时传
		 * @param type           必选 int 1按小时2按天3按周4按月
		 *
		 * @return          {"error":0,"data":{"user_data":[{"sort":"1","name":"flu","cnt_num":"1"},{"sort":"2","name":"fluu","cnt_num":"0"}],"url":"","xData":["flu","fluu"],"seriesData":["1","0"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_data array 底下的详细数据
		 * @return_param    xData array X轴数据
		 * @return_param    seriesData array Y轴数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/08/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserCustomNumIncrease ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			$data_Type       = \Yii::$app->request->post('data_Type');
			$user_id         = \Yii::$app->request->post('user_id');
			$user_depart_ids = \Yii::$app->request->post('user_ids', []);
			$date1           = \Yii::$app->request->post('s_date');
			$date2           = \Yii::$app->request->post('e_date');
			$s_week          = \Yii::$app->request->post('s_week');
			$type            = \Yii::$app->request->post('type') ?: 2; //天
			if (empty($this->corp) || empty($user_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($date1) || empty($date2)) {
				throw new InvalidParameterException('请传入日期！');
			}
			if ($type == 3 && empty($s_week)) {
				throw new InvalidParameterException('请传入起始周！');
			}
			$corp_id = $this->corp['id'];

			//分配员工数据
			$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
			$user_ids   = $userData['user_ids'];
			$user_count = $userData['userCount'];
			$result = WorkUserStatistic::getUserIncreaseByType($type, $data_Type, $corp_id, $user_ids, $date1, $date2, $s_week);

			$info = [
				'user_data'  => $result['data'],
				'xData'      => $result['xData'],
				'seriesData' => $result['seriesData'],
				'show'       => $userData['show'],
				'user_count' => $user_count,
			];

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户群统计
		 * @description     成员客户群统计
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-chat-statistic
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param user_id        必选 string 企业成员userid
		 * @param data_type      必选 int 数据类型：1群聊总数2新增群聊数3有过消息的群聊数4群成员总数5新增群成员数6发过消息的群成员数7群聊消息总数8退群人数
		 * @param user_ids       可选 array 员工id集合
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param s_week         选填 int 按周时传
		 * @param type           必选 int 1按天2按周3按月
		 * @param is_pc          选填 int 是否PC端请求1是0否
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
		 * @return_param    seriesData array Y轴数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/8/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserChatStatistic ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id         = $this->corp['id'];
				$user_id         = \Yii::$app->request->post('user_id');
				$data_Type       = \Yii::$app->request->post('data_type', 1);
				$user_depart_ids = \Yii::$app->request->post('user_ids', []);
				$s_date          = \Yii::$app->request->post('s_date');
				$e_date          = \Yii::$app->request->post('e_date');
				$s_week          = \Yii::$app->request->post('s_week');
				$type            = \Yii::$app->request->post('type', 1); //天
				$is_pc           = \Yii::$app->request->post('is_pc', 0); //是否PC端请求
				$is_export       = \Yii::$app->request->post('is_export', 0);

				if (empty($this->corp) || (empty($user_id) && $is_pc == 0)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}


				//分配员工数据
				if (!empty($user_id)) {
					//H5
					$userData = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
					$user_ids = $userData['user_ids'];
					$user_count = $userData['userCount'];
				} else {
					$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
					if(!empty($user_depart_ids)){
						$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_depart_ids);
						$user_depart_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
						if(empty($user_depart_ids)){
							$user_depart_ids = [0];
						}
					}
					//PC
					if (isset($this->subUser->sub_id) && $is_pc == 1 && empty($user_depart_ids)) {
						$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$this->subUser->sub_id,0,true);
						$user_count = !empty($user_ids) && !empty($user_depart_ids) ? count($user_ids) : 0;
						if(empty($user_ids)){
							$user_ids = [0];
						}
					} else {
						$user_ids = $user_depart_ids;
						$user_count = !empty($user_ids) && !empty($user_depart_ids) ? count($user_ids) : 0;
					}
				}
				//按群主
				$result = WorkChat::getUserChatStatistic($corp_id, $data_Type, $type, $s_date, $e_date, $s_week, $user_ids);

				//导出
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['time', 'add_num'];
					$typeName = $result['seriesData'][0]['name'];
					$headers  = [
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

				//总数
				$snum1 = $snum2 = $snum3 = $snum4 = 0;
				if (in_array($data_Type, [1, 2, 3])) {
					$snum1 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 1, $s_date, $e_date);
					$snum2 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 2, $s_date, $e_date);
					$snum3 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 3, $s_date, $e_date);
				} elseif (in_array($data_Type, [4, 5, 6, 8])) {
					$snum1 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 4, $s_date, $e_date);
					$snum2 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 5, $s_date, $e_date);
					$snum3 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 6, $s_date, $e_date);
					$snum4 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 8, $s_date, $e_date);
				} elseif (in_array($data_Type, [7])) {
					$snum1 = WorkChatStatistic::getChatStatisticsByDataType($corp_id, $user_ids, 7, $s_date, $e_date);
				}

				$info = [
					'chatData'   => $result['data'],
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
					'snum1'      => $snum1,
					'snum2'      => $snum2,
					'snum3'      => $snum3,
					'snum4'      => $snum4,
					'show'       => isset($userData['show']) ? $userData['show'] : 0,
					'user_count' => $user_count,
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户统计列表
		 * @description     成员客户统计列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-custom-num-list
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param page           可选 int 页数
		 * @param page_size      可选 int 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.name string 成员名称
		 * @return_param    list.avatar string 成员头像
		 * @return_param    list.gender int 成员性别1男2女0未知
		 * @return_param    list.departName string 成员部门
		 * @return_param    list.new_apply_cnt_snum int 发起申请数
		 * @return_param    list.new_contact_cnt_snum int 新增客户数
		 * @return_param    list.negative_feedback_cnt_snum int 流失客户数
		 * @return_param    list.custom_snum int 总客户数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/8/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserCustomNumList ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id         = $this->corp['id'];
				$user_id         = \Yii::$app->request->post('user_id');
				$user_depart_ids = \Yii::$app->request->post('user_ids', []);
				$s_date          = \Yii::$app->request->post('s_date');
				$e_date          = \Yii::$app->request->post('e_date');
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($this->corp) || empty($user_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}

				//分配员工数据
				$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
				$user_ids   = $userData['user_ids'];
				$user_count = $userData['userCount'];

				$workUserData = WorkUser::find()->select('id, userid, name, avatar, gender')->where(['corp_id' => $corp_id, 'is_del' => 0]);
				if (!empty($user_ids)) {
					$workUserData = $workUserData->andWhere(['id' => $user_ids]);
				}

				$count = $workUserData->count();

				$offset       = ($page - 1) * $pageSize;
				$workUserData = $workUserData->limit($pageSize)->offset($offset);
				$workUserData = $workUserData->orderBy(['id' => SORT_DESC])->asArray()->all();

				$workUserD = [];
				foreach ($workUserData as $v) {
					array_push($workUserD, $v['userid']);
				}

				$field             = 'userid, SUM(`new_apply_cnt`) new_apply_cnt_snum, SUM(`new_contact_cnt`) new_contact_cnt_snum, SUM(`negative_feedback_cnt`) negative_feedback_cnt_snum';
				$stime             = strtotime($s_date);
				$etime             = strtotime($e_date);
				$userStatisticData = WorkUserStatistic::find()->andWhere(['corp_id' => $corp_id])->andFilterWhere(['between', 'time', $stime, $etime]);
				if (!empty($workUserD)) {
					$userStatisticData = $userStatisticData->andWhere(['in', 'userid', $workUserD]);
				}
				$userStatisticData = $userStatisticData->select($field)->groupBy('userid')->asArray()->all();
				$userSta           = [];
				foreach ($userStatisticData as $v) {
					$userSta[$v['userid']] = $v;
				}

				//总客户数
				$field      = 'wf.userid,count(wf.id) cnt_num';
				$userStatic = WorkExternalContactFollowUser::find()->alias('wf');
				$userStatic = $userStatic->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$userStatic = $userStatic->andWhere(['we.corp_id' => $this->corp->id]);
				$userStatic = $userStatic->andWhere(['<', 'wf.createtime', strtotime($e_date . ' 23:59:59')]);
				$userStatic = $userStatic->andWhere(['or', ['wf.del_type' => 0], ['and', ['in', 'wf.del_type', [1, 2]], ['>=', 'wf.del_time', strtotime($e_date . ' 23:59:59')]]]);
				if (!empty($user_ids)) {
					$userStatic = $userStatic->andWhere(['in', 'wf.user_id', $user_ids]);
				}
				$userStatic = $userStatic->select($field)->groupBy('wf.user_id')->asArray()->all();

				$userNum = [];
				foreach ($userStatic as $v) {
					$userNum[$v['userid']] = $v['cnt_num'];
				}

				$workUserD = [];
				foreach ($workUserData as $k => $v) {
					$statisticD                               = [];
					$statisticD['name']                       = $v['name'] ? $v['name'] : '--';
					$statisticD['avatar']                     = $v['avatar'];
					$statisticD['gender']                     = $v['gender'];
					$departName                               = WorkDepartment::getDepartNameByUserId($v['id']);
					$statisticD['departName']                 = $departName;
					$statisticD['custom_snum']                = isset($userNum[$v['userid']]) ? $userNum[$v['userid']] : 0;
					$statisticD['new_apply_cnt_snum']         = isset($userSta[$v['userid']]['new_apply_cnt_snum']) ? $userSta[$v['userid']]['new_apply_cnt_snum'] : 0;
					$statisticD['new_contact_cnt_snum']       = isset($userSta[$v['userid']]['new_contact_cnt_snum']) ? $userSta[$v['userid']]['new_contact_cnt_snum'] : 0;
					$statisticD['negative_feedback_cnt_snum'] = isset($userSta[$v['userid']]['negative_feedback_cnt_snum']) ? $userSta[$v['userid']]['negative_feedback_cnt_snum'] : 0;

					$workUserD[] = $statisticD;
				}

				return [
					'count'      => $count,
					'list'       => $workUserD,
					'show'       => $userData['show'],
					'user_count' => $user_count,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           成员客户群统计列表
		 * @description     成员客户群统计列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/work-user-chat-statistic-list
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param user_id        必选 string 企业成员userid
		 * @param user_ids       可选 array 员工id集合
		 * @param s_date         必选 string 开始日期
		 * @param e_date         必选 string 结束日期
		 * @param page           可选 int 页数
		 * @param page_size      可选 int 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.name string 成员名称
		 * @return_param    list.departName string 成员部门
		 * @return_param    list.new_chat_cnt_snum int 新增群聊数
		 * @return_param    list.chat_total_snum int 群总数
		 * @return_param    list.chat_has_msg_snum int 有过消息的群聊数
		 * @return_param    list.new_member_cnt_snum int 新增群成员数
		 * @return_param    list.member_total_snum int 群成员总数
		 * @return_param    list.member_has_msg_snum int 发过消息的群成员数
		 * @return_param    list.msg_total_snum int 群聊消息总数
		 * @return_param    list.leave_num int 退群人数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/8/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserChatStatisticList ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id         = $this->corp['id'];
				$user_id         = \Yii::$app->request->post('user_id');
				$user_depart_ids = \Yii::$app->request->post('user_ids', []);
				$s_date          = \Yii::$app->request->post('s_date');
				$e_date          = \Yii::$app->request->post('e_date');
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($this->corp) || empty($user_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}

				//分配员工数据
				$userData   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_depart_ids);
				$user_ids   = $userData['user_ids'];
				$user_count = $userData['userCount'];
				//退群人数
				$chatData = WorkChat::find()->alias('a');
				$chatData = $chatData->andWhere(['a.corp_id' => $corp_id]);
				if (is_array($user_ids) && !empty($user_ids)) {
					$chatData = $chatData->andWhere(['in', 'a.owner_id', $user_ids]);
				}
				$chatData = $chatData->leftJoin('{{%work_chat_info}} b', '`b`.`chat_id` = `a`.`id` and b.`status`=0 and b.`leave_time`>=' . strtotime($s_date) . ' and b.`leave_time`<=' . strtotime($e_date . ' 23:59:59'));

				$countData = $chatData->select('a.owner_id')->groupBy('a.owner_id')->all();
				$count     = count($countData);

				$offset   = ($page - 1) * $pageSize;
				$chatData = $chatData->limit($pageSize)->offset($offset);
				$chatData = $chatData->select('a.owner_id, COUNT(b.id) leave_num')->groupBy('a.owner_id')->asArray()->all();

				$field = 'owner_id, SUM(`new_chat_cnt`) new_chat_cnt_snum, SUM(`chat_has_msg`) chat_has_msg_snum, SUM(`new_member_cnt`) new_member_cnt_snum, SUM(`member_has_msg`) member_has_msg_snum, SUM(`msg_total`) msg_total_snum';
				$stime = strtotime($s_date);
				$etime = strtotime($e_date);
				foreach ($chatData as $k => $v) {
					$workUser                   = WorkUser::findOne($v['owner_id']);
					$departName                 = WorkDepartment::getDepartNameByUserId($v['owner_id']);
					$chatData[$k]['name']       = $workUser->name;
					$chatData[$k]['avatar']     = $workUser->avatar;
					$chatData[$k]['gender']     = $workUser->gender;
					$chatData[$k]['departName'] = $departName;

					$chatStatisticData = WorkChatStatistic::find()->where(['corp_id' => $corp_id])->andFilterWhere(['between', '`time`', $stime, $etime]);
					$chatStatisticData = $chatStatisticData->andWhere(['owner_id' => $v['owner_id']]);
					$chatStatisticData = $chatStatisticData->select($field)->groupBy('owner_id')->asArray()->all();

					$chatStatisticData1 = WorkChatStatistic::find()->where(['corp_id' => $corp_id])->andWhere(['time' => $etime]);
					$chatStatisticData1 = $chatStatisticData1->andWhere(['owner_id' => $v['owner_id']]);
					$chatStatisticData1 = $chatStatisticData1->select('`chat_total` chat_total_snum, `member_total` member_total_snum')->asArray()->all();

					$chatData[$k]['leave_num']           = !empty($v['leave_num']) ? $v['leave_num'] : 0;
					$chatData[$k]['new_chat_cnt_snum']   = isset($chatStatisticData[0]['new_chat_cnt_snum']) ? $chatStatisticData[0]['new_chat_cnt_snum'] : 0;
					$chatData[$k]['chat_total_snum']     = isset($chatStatisticData1[0]['chat_total_snum']) ? $chatStatisticData1[0]['chat_total_snum'] : 0;
					$chatData[$k]['chat_has_msg_snum']   = isset($chatStatisticData[0]['chat_has_msg_snum']) ? $chatStatisticData[0]['chat_has_msg_snum'] : 0;
					$chatData[$k]['new_member_cnt_snum'] = isset($chatStatisticData[0]['new_member_cnt_snum']) ? $chatStatisticData[0]['new_member_cnt_snum'] : 0;
					$chatData[$k]['member_total_snum']   = isset($chatStatisticData1[0]['member_total_snum']) ? $chatStatisticData1[0]['member_total_snum'] : 0;
					$chatData[$k]['member_has_msg_snum'] = isset($chatStatisticData[0]['member_has_msg_snum']) ? $chatStatisticData[0]['member_has_msg_snum'] : 0;
					$chatData[$k]['msg_total_snum']      = isset($chatStatisticData[0]['msg_total_snum']) ? $chatStatisticData[0]['msg_total_snum'] : 0;
				}

				return [
					'count'      => $count,
					'list'       => $chatData,
					'show'       => $userData['show'],
					'user_count' => $user_count,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           群成员会话信息
		 * @description     群成员会话信息
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/chat-info-audit
		 *
		 * @param corp_id        必选 string 企业微信ID
		 * @param chat_id        必选 string 群id
		 * @param stime          可选 string 起始时间
		 * @param etime          可选 string 结束时间
		 * @param sort           可选 int 排序1降序2升序
		 * @param name           可选 string 群成员昵称
		 * @param now_userid     选填 string H5当前员工的userid
		 * @param page           可选 int 页数
		 * @param page_size      可选 int 每页数量
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据列表
		 * @return_param    list.from_type int 群成员类型1员工2客户3群主
		 * @return_param    list.num int 发言数量
		 * @return_param    list.name string 成员名称
		 * @return_param    list.avatar string 成员头像
		 * @return_param    list.gender int 成员性别
		 * @return_param    list.top string 排行
		 * @return_param    list.msgtime string 上次活跃时间
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/11/10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatInfoAudit ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$chat_id    = \Yii::$app->request->post('chat_id', '');
			$stime      = \Yii::$app->request->post('stime', '');
			$etime      = \Yii::$app->request->post('etime', '');
			$sort       = \Yii::$app->request->post('sort', 1);
			$name       = \Yii::$app->request->post('name', '');
			$now_userid = \Yii::$app->request->post('now_userid', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$name       = trim($name);

			if (empty($this->corp) || empty($chat_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($stime) || empty($etime)) {
				throw new InvalidParameterException('起止时间不能为空！');
			}
			$workChat = WorkChat::findOne(['corp_id' => $this->corp->id, 'chat_id' => $chat_id]);
			if (empty($workChat)) {
				throw new InvalidParameterException('客户群数据不正确！');
			}

			$smicrotime = strtotime($stime) * 1000;
			$emicrotime = strtotime($etime . ':59') * 1000;
			$field      = new Expression('from_type,CASE WHEN from_type = 1 THEN user_id ELSE external_id END as member_id,count(id) num');
			$chatAudit  = WorkMsgAuditInfo::find()->where(['chat_id' => $workChat->id])->andFilterWhere(['between', 'msgtime', $smicrotime, $emicrotime])->andWhere(['from_type' => [1, 2]]);
			$chatAudit  = $chatAudit->select($field)->groupBy('member_id,from_type');

			$chatAllAudit = $chatAudit->orderBy(['num' => SORT_DESC])->asArray()->all();

			//TOP值
			$auditTop = [];
			foreach ($chatAllAudit as $k => $v) {
				$key            = $v['member_id'] . '_' . $v['from_type'];
				$auditTop[$key] = $k + 1;
			}

			if (!empty($name)) {
				//外部联系人
				$workExternalData = WorkExternalContact::find()->alias('we');
				$workExternalData = $workExternalData->andWhere(['we.corp_id' => $this->corp['id']]);
				$workExternalData = $workExternalData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
				$workExternalData = $workExternalData->andWhere(' we.name_convert like \'%' . $name . '%\' or (cf.fieldid=2 and cf.value like \'%' . $name . '%\')');
				$workExternalData = $workExternalData->select('we.id as wid')->groupBy('we.id')->asArray()->all();
				$external_ids     = array_column($workExternalData, 'wid');
				//成员
				$workUserData = WorkUser::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => 0])->andWhere(['like', 'name', $name]);
				$workUserData = $workUserData->select('id')->asArray()->all();
				$user_ids     = array_column($workUserData, 'id');

				$chatAudit = WorkMsgAuditInfo::find()->where(['chat_id' => $workChat->id])->andFilterWhere(['between', 'msgtime', $smicrotime, $emicrotime])->andWhere(['from_type' => [1, 2]]);
				if (!empty($user_ids) && !empty($external_ids)) {
					$chatAudit = $chatAudit->andWhere(['or', ['from_type' => 1, 'user_id' => $user_ids], ['from_type' => 2, 'external_id' => $external_ids]]);
				} elseif (!empty($user_ids)) {
					$chatAudit = $chatAudit->andWhere(['from_type' => 1, 'user_id' => $user_ids]);
				} elseif ($external_ids) {
					$chatAudit = $chatAudit->andWhere(['from_type' => 2, 'external_id' => $external_ids]);
				} else {
					$chatAudit = $chatAudit->andWhere(['from_type' => 0]);//无
				}
				$chatAudit = $chatAudit->select($field)->groupBy('member_id,from_type');

				$count = $chatAudit->count();
			} else {
				$count = count($chatAllAudit);
			}

			$offset = ($page - 1) * $pageSize;
			if ($sort == 1) {
				$chatAudit = $chatAudit->orderBy(['num' => SORT_DESC]);
			} else {
				$chatAudit = $chatAudit->orderBy(['num' => SORT_ASC]);
			}
			$chatData = $chatAudit->limit($pageSize)->offset($offset)->asArray()->all();

			foreach ($chatData as $k => $v) {
				$auditInfo       = WorkMsgAuditInfo::find()->where(['chat_id' => $workChat->id]);
				$external_userid = '';
				if ($v['from_type'] == 1) {
					$chatData[$k]['from_type'] = $v['member_id'] == $workChat->owner_id ? 3 : $v['from_type'];
					$workUser                  = WorkUser::findOne($v['member_id']);
					$name                      = !empty($workUser) ? $workUser->name : '--';
					$avatar                    = !empty($workUser) ? $workUser->avatar : '--';
					$gender                    = !empty($workUser) ? $workUser->gender : '--';
					$auditInfo                 = $auditInfo->andWhere(['from_type' => 1, 'user_id' => $v['member_id']]);
				} else {
					$externalContact = WorkExternalContact::findOne($v['member_id']);
					$name            = !empty($externalContact->name) ? rawurldecode($externalContact->name) : '未知';
					$avatar          = !empty($externalContact) ? $externalContact->avatar : "";
					$gender          = !empty($externalContact) ? $externalContact->gender : 0;
					$auditInfo       = $auditInfo->andWhere(['from_type' => 2, 'external_id' => $v['member_id']]);

					if (!empty($now_userid) && !empty($externalContact)) {
						$followUser      = WorkExternalContactFollowUser::findOne(['userid' => $now_userid, 'external_userid' => $externalContact->id, 'del_type' => 0]);
						$external_userid = !empty($followUser) ? $externalContact->external_userid : '';
					}
				}
				$chatData[$k]['name']            = $name;
				$chatData[$k]['avatar']          = $avatar;
				$chatData[$k]['gender']          = $gender;
				$chatData[$k]['external_userid'] = $external_userid;
				$key                             = $v['member_id'] . '_' . $v['from_type'];
				$chatData[$k]['top']             = isset($auditTop[$key]) ? $auditTop[$key] : '--';
				$auditInfo                       = $auditInfo->select('msgtime')->orderBy(['id' => SORT_DESC])->one();
				$chatData[$k]['msgtime']         = isset($auditInfo->msgtime) && !empty($auditInfo->msgtime) ? date('Y-m-d H:i', floor($auditInfo->msgtime / 1000)) : '--';
			}

			return [
				'count' => $count,
				'list'  => $chatData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           获取各类型内容引擎的次数
		 * @description     获取各类型内容引擎的次数
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/attachment-sum
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 企业id
		 * @param user_id 必选 string 企业成员userid
		 * @param type 必选 string 类型：1、内容TOP10，5、员工TOP10
		 * @param date_type 必选 string 日期类型：1、日，2、周，3、月
		 * @param file_type 可选 string 附件类型，1：图片、2：音频、3：视频、4：图文、5：文件、6：文本、7：小程序
		 * @param s_date 可选 string 开始日期
		 * @param e_date 可选 string 结束日期
		 * @param group_id 可选 string 分组
		 * @param user_ids 可选 array 成员id
		 *
		 * @return          {"error":0,"data":{"createSum":"2391","searchNum":"2391","sendNum":"3173","openSum":"113"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    createSum string 员工贡献内容个数，type=5
		 * @return_param    searchNum string 搜索次数，type=1
		 * @return_param    sendNum string 发送次数，type=1，5
		 * @return_param    openSum string 打开次数，type=1
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-21 17:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAttachmentSum ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$corp_id   = \Yii::$app->request->post('corp_id', 0);
			$user_id   = \Yii::$app->request->post('user_id', 0);
			$type      = \Yii::$app->request->post('type', 5);
			$date_type = \Yii::$app->request->post('date_type', 1);
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$file_type = \Yii::$app->request->post('file_type', 0);
			$s_date    = \Yii::$app->request->post('s_date', '');
			$e_date    = \Yii::$app->request->post('e_date', '');
			$userIds   = \Yii::$app->request->post('user_ids', []);
			if (empty($corp_id) || empty($uid) || empty($user_id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$corp_id  = $workCorp->id;
			$userData = AuthoritySubUserDetail::getUserIds($user_id, $uid, $corp_id, $userIds);
			$userCount = $userData['userCount'];
			$userIds = $userData['user_ids'];
			$show    = $userData['show'];
			if ($type == 1) {
				$attachmentData = AttachmentStatistic::find()->alias('s')->where(['s.corp_id' => $corp_id, 'a.uid' => $uid]);
				$attachmentData = $attachmentData->leftJoin('{{%attachment}} a', '`s`.`attachment_id` = `a`.`id`');
				//分组
				if (!empty($group_id)) {
					$idList         = AttachmentGroup::getSubGroupId($group_id);
					$attachmentData = $attachmentData->andWhere(['a.group_id' => $idList]);
				}
				//内容类型
				if (!empty($file_type)) {
					$attachmentData = $attachmentData->andWhere(['a.file_type' => $file_type]);
				}

				//部门成员
				if (!empty($userIds)) {
					$followUser = WorkExternalContactFollowUser::find()->where(["in", "user_id", $userIds])->select("external_userid")->asArray()->all();
					if (!empty($followUser)) {
						$externalId     = array_column($followUser, 'external_userid');
						$attachmentData = $attachmentData->andWhere(['or', ['s.user_id' => $userIds], ['s.type' => 3, 's.external_id' => $externalId]]);
					} else {
						$attachmentData = $attachmentData->andWhere(['s.user_id' => $userIds]);
					}
				}

				//日期选择
				if ($date_type == 3) {
					$beforeMonth    = date('Y-m', strtotime('-11 month'));
					$attachmentData = $attachmentData->andWhere(['>', 's.create_time', $beforeMonth]);
				} elseif (!empty($e_date)) {
					$e_date         = $e_date . ' 23:59:59';
					$attachmentData = $attachmentData->andWhere(['between', 's.create_time', $s_date, $e_date]);
				}
				$attachmentData = $attachmentData->select('count(s.id) sum,s.type');
				$attachmentData = $attachmentData->groupBy('s.type')->asArray()->all();

				$searchNum = $sendNum = $openSum = 0;
				if (!empty($attachmentData)) {
					foreach ($attachmentData as $statistic) {
						if ($statistic['type'] == 1) {
							$searchNum = $statistic['sum'];
						} elseif ($statistic['type'] == 2) {
							$sendNum = $statistic['sum'];
						} elseif ($statistic['type'] == 3) {
							$openSum = $statistic['sum'];
						}
					}
				}

				return ['searchNum' => $searchNum, 'sendNum' => $sendNum, 'openSum' => $openSum, 'show' => $show, 'user_count' => $userCount];
			} elseif ($type == 5) {
				//员工贡献内容
				$attachmentData = Attachment::find()->alias('a');
				$attachmentData = $attachmentData->where(['a.uid' => $uid, 'a.isMasterAccount' => 2])->andWhere(['>', 'a.sub_id', 0]);

				//员工发送
				$attachmentSendData = AttachmentStatistic::find()->alias('s')->where(['s.corp_id' => $corp_id]);
				$attachmentSendData = $attachmentSendData->leftJoin('{{%attachment}} a', '`s`.`attachment_id` = `a`.`id`');
				$attachmentSendData = $attachmentSendData->where(['s.corp_id' => $corp_id, 's.type' => 2, 'a.uid' => $uid])->andWhere(['>', 's.user_id', 0]);

				if (!empty($group_id)) {
					$idList             = AttachmentGroup::getSubGroupId($group_id);
					$attachmentData     = $attachmentData->andWhere(['a.group_id' => $idList]);
					$attachmentSendData = $attachmentSendData->andWhere(['a.group_id' => $idList]);
				}
				if (!empty($user_id)) {
					$workUser = WorkUser::findOne(['corp_id' => $corp_id, 'userid' => $user_id]);
					if (!empty($workUser)) {
						$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
						if (!empty($subUser) && $subUser->type == 0) {
							$attachmentData = $attachmentData->andWhere(['a.sub_id' => $subUser->sub_id]);
						}
					}
				}
				if (!empty($userIds)) {
					$attachmentSendData = $attachmentSendData->andWhere(['s.user_id' => $userIds]);
				}
				if (!empty($file_type)) {
					$attachmentData     = $attachmentData->andWhere(['a.file_type' => $file_type]);
					$attachmentSendData = $attachmentSendData->andWhere(['a.file_type' => $file_type]);
				}
				if ($date_type == 3) {
					$beforeMonth        = date('Y-m', strtotime('-11 month'));
					$attachmentData     = $attachmentData->andWhere(['>', 'a.create_time', $beforeMonth]);
					$attachmentSendData = $attachmentSendData->andWhere(['>', 's.create_time', $beforeMonth]);
				} elseif (!empty($e_date)) {
					$e_date             = $e_date . ' 23:59:59';
					$attachmentData     = $attachmentData->andWhere(['between', 'a.create_time', $s_date, $e_date]);
					$attachmentSendData = $attachmentSendData->andWhere(['between', 's.create_time', $s_date, $e_date]);
				}
				$attachmentData = $attachmentData->select('id');
				$createSum      = $attachmentData->count();

				$attachmentSendData = $attachmentSendData->select('s.id');
				$sendSum            = $attachmentSendData->count();

				return ['createNum' => $createSum, 'sendNum' => $sendSum, 'show' => $show];
			}

			return [];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           获取内容统计
		 * @description     获取内容统计
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/get-attachment-statistic
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 企业id
		 * @param user_id 必选 string 企业成员userid
		 * @param file_type 可选 string 类型：1：图片、2：音频、3：视频、4：图文、5：文件、6：文本、7：小程序
		 * @param file_name 可选 string 附件名称
		 * @param s_date 可选 string 开始日期
		 * @param e_date 可选 string 结束日期
		 * @param order_type 可选 string 排序字段：search_num：搜索次数，send_num:发送次数，open_num：打开次数
		 * @param order_sort 可选 string 排序：3、倒序，4、正序
		 * @param user_ids 可选 array 成员id
		 * @param page 可选 string 页数，默认为1
		 * @param page_size 可选 string 每页数量，默认15
		 *
		 * @return          {"error":0,"data":{"count":"573","fileData":[{"file_name":"ceshi56899","type_name":"图片","search_num":"2","send_num":"0","open_num":"0"},{"file_name":"微信图片_20190909094603.jpg","type_name":"图片","search_num":"7","send_num":"0","open_num":"0"},{"file_name":"s_15839212165e68b8408e999.jpg","type_name":"图片","search_num":"4","send_num":"0","open_num":"0"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    fileData array 附件列表
		 * @return_param    fileData.file_name string 附件名称
		 * @return_param    fileData.type_name string 附件类型名称
		 * @return_param    fileData.search_num string 附件搜索次数
		 * @return_param    fileData.send_num string 附件发送次数
		 * @return_param    fileData.open_num string 附件打开次数
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-24 20:06
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionGetAttachmentStatistic ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			$fileData = AttachmentStatistic::getStatistic($postData);

			return $fileData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-user-desktop/
		 * @title           客户CRM
		 * @description     客户CRM
		 * @method   post
		 * @url  http://{host_name}/api/wap-user-desktop/custom-list
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param sub_id           必选 int 子账户ID
		 * @param suite_id      可选 int 应用ID（授权的必填）
		 * @param corp_id       必选 string 企业的唯一ID
		 * @param user_ids      可选 array 成员id
		 * @param name          可选 string 客户姓名、公司名称
		 * @param phone         可选 string 手机号、QQ号
		 * @param qq            可选 string QQ号
		 * @param company       可选 string 公司名称
		 * @param sex           可选 string 性别-1全部1男2女3未知
		 * @param work          可选 string 行业
		 * @param province      可选 string 区域-省
		 * @param city          可选 string 区域-市
		 * @param follow_status 可选 int 跟进状态-1全部0未跟进1跟进中2已拒绝3已成交
		 * @param fieldData     可选 array 自定义高级属性搜索
		 * @param fieldData     .field 可选 int 属性id
		 * @param fieldData     .type 可选 int 属性类型2单选3多选
		 * @param fieldData     .match 可选 string 属性值
		 * @param follow_id     可选 int 跟进状态id
		 * @param tag_ids       可选 string 标签值（多标签用,分开）
		 * @param group_id      可选 array 标签分组
		 * @param tag_type      可选 int 标签1或2且
		 * @param no_tag        可选 int 无标签1选中，0未选中
		 * @param add_way       可选 string 来源
		 * @param way_id        可选 string 渠道活码id、群活码id
		 * @param chat_id       可选 string 客户群id
		 * @param chat_type     可选 string 群类型：0全部，1无，2一个，3多个
		 * @param page          可选 int 页码
		 * @param page_size     可选 int 每页数据量，默认15
		 * @param correctness   可选 int 1全部2按条件筛选
		 * @param update_time   可选 array 最后一次跟进时间
		 * @param follow_num1    可选 int 开始跟进次数
		 * @param follow_num2    可选 int 结束跟进次数
		 * @param from_unique   可选 int 0不去重1去重复
		 * @param chat_time   可选 array 上次单聊时间
		 * @param sign_id   可选 int 绑定店铺
		 * @param type   可选 int 0全部1今日新增2本周新增3本月新增
		 * @param from   可选 int 1按人2按日期
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    follow_status string 跟进状态
		 * @return_param    tag_name array 标签
		 * @return_param    key int 客户id
		 * @return_param    member string 归属成员
		 * @return_param    source string 来源
		 * @return_param    remark string 备注
		 * @return_param    create_time string 添加时间
		 * @return_param    nickname string 姓名
		 * @return_param    phone string 手机号
		 * @return_param    area string 区域
		 * @return_param    chat_time string 上次单聊时间
		 * @return_param    add_way_info string 来源
		 * @return_param    show int 0显示筛选1不显示
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/24 9:48
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\db\Exception
		 */
		public function actionCustomList ()
		{
			if (\Yii::$app->request->isPost) {
				$user_ids = $TempUserIds = \Yii::$app->request->post('user_ids');
				$name     = \Yii::$app->request->post('name', '');
				$phone    = \Yii::$app->request->post('phone');
				$name     = trim($name);
				$phone    = trim($phone);

				$sex       = \Yii::$app->request->post('sex', -1);
				$work      = \Yii::$app->request->post('work', '');
				$province  = \Yii::$app->request->post('province', '');
				$city      = \Yii::$app->request->post('city', '');
				$fieldData = \Yii::$app->request->post('fieldData', []);
				$follow_id = \Yii::$app->request->post('follow_id', '-1');

				$tag_ids     = \Yii::$app->request->post('tag_ids', '');
				$group_id    = \Yii::$app->request->post('group_id', '');
				$tag_type    = \Yii::$app->request->post('tag_type', 1);
				$no_tag      = \Yii::$app->request->post('no_tag', 0);
				$add_way     = \Yii::$app->request->post('add_way', '-1');
				$way_id      = \Yii::$app->request->post('way_id', '');
				$chat_id     = \Yii::$app->request->post('chat_id', '');
				$chat_type   = \Yii::$app->request->post('chat_type', 0);
				$start_time  = \Yii::$app->request->post('start_time');
				$end_time    = \Yii::$app->request->post('end_time');
				$is_all      = \Yii::$app->request->post('is_all') ?: 0;
				$page        = \Yii::$app->request->post('page') ?: 1;
				$pageSize    = \Yii::$app->request->post('page_size', 10);
				$update_time = \Yii::$app->request->post('update_time');
				$follow_num1 = \Yii::$app->request->post('follow_num1');
				$follow_num2 = \Yii::$app->request->post('follow_num2');
				$from_unique = \Yii::$app->request->post('from_unique') ?: 0;
				$chat_time   = \Yii::$app->request->post('chat_time');
				$sign_id     = \Yii::$app->request->post('sign_id');
				$uid         = \Yii::$app->request->post('uid');
				$type        = \Yii::$app->request->post('type');
				$is_fans     = \Yii::$app->request->post('is_fans');
				$from        = \Yii::$app->request->post('from', 1);
				$user_id     = \Yii::$app->request->post('user_id');
				$day         = \Yii::$app->request->post('day');
				$status_id   = \Yii::$app->request->post('status_id');
				$last_id     = \Yii::$app->request->post('last_id', 0);
				$last_user   = \Yii::$app->request->post('last_user');
				$isProtect   = \Yii::$app->request->post('is_protect', '-1');
				if (empty($this->corp) || empty($uid) || empty($user_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$userIds = $user_ids;

//				//判断当前员工是否是主账户 1是0否
//				$isMaster = AuthoritySubUserDetail::isMaster($user_id, $this->user->uid, $this->corp->id);
//				//分配员工数据
//				$userIds = AuthoritySubUserDetail::getUserSubUser($user_id, $this->user->uid, $this->corp->id);
//				if($isMaster==1){
//					$userIds = [];
//				}
//
//				//显示成员筛选
//				if ($isMaster == 1) {
//					$show = 0;
//				} else {
//					$show = AuthoritySubUserDetail::showMembers($user_id, $this->user->uid, $this->corp->id);
//				}

				$userData  = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
				$user_ids  = $userData['user_ids'];
				$show      = $userData['show'];
				$userCount = $userData['userCount'];

				//$data['userIds']     = $userIds;
				$data['update_time'] = $update_time;
				$data['follow_num1'] = $follow_num1;
				$data['follow_num2'] = $follow_num2;
				$data['chat_time']   = $chat_time;
				$data['sign_id']     = $sign_id;
				$data['user_ids']    = $user_ids;
				$data['start_time']  = $start_time;
				$data['end_time']    = $end_time;
				$data['follow_id']   = $follow_id;
				$data['add_way']     = $add_way;
				$data['way_id']      = $way_id;
				$data['chat_id']     = $chat_id;
				$data['chat_type']   = $chat_type;
				$data['tag_type']    = $tag_type;
				$data['tag_ids']     = $tag_ids;
				$data['group_id']    = $group_id;
				$data['no_tag']      = $no_tag;
				$data['name']        = $name;
				$data['phone']       = $phone;
				$data['work']        = $work;
				$data['province']    = $province;
				$data['city']        = $city;
				$data['sex']         = $sex;
				$data['fieldData']   = $fieldData;
				$data['uid']         = $uid;
				$data['type']        = $type;
				$data['from']        = $from;
				$data['is_fans']     = $is_fans;
				$data['day']         = $day;
				$data['status_id']   = $status_id;
				$data['is_protect']  = $isProtect;

				//高级属性搜索
				$fieldList    = CustomField::find()->where('is_define=0')->select('`id`,`key`')->asArray()->all();//默认属性
				$fieldD       = [];
				$contactField = [];//列表展示字段
				foreach ($fieldList as $k => $v) {
					$fieldD[$v['key']] = $v['id'];
					if (in_array($v['key'], ['name', 'sex', 'phone', 'area'])) {
						array_push($contactField, $v['id']);
					}
				}
				$data['fieldD'] = $fieldD;
				$offset         = ($page - 1) * $pageSize;

				$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_user}} wu', 'wu.id=wf.user_id');
				$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp['id']])->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);

				$workExternalUserData = WorkExternalContactFollowUser::getCondition($workExternalUserData, $this->corp['id'], $data);

				if (empty($from_unique)) {
					$group       = 'wf.id';
					$customCount = $workExternalUserData->groupBy($group)->count();
				} else {
					$group = 'we.id';
					//所有客户id
					$allExternalUser = $workExternalUserData->select('wf.id,count(DISTINCT(wf.user_id)) as count')->groupBy($group)->having(['>', 'count', 1])->orderBy(['wf.createtime' => SORT_DESC]);
					$allExternalUser = $allExternalUser->asArray()->all();
					$customCount     = count($allExternalUser);
				}
				$workExternalUserData1 = $workExternalUserData;
				$uniqueCount           = $workExternalUserData1->groupBy('we.id')->count();

				$is_hide_phone = $this->user->is_hide_phone;

				if ($from == 1) {

					$count = $workExternalUserData->groupBy(['wf.user_id'])->count();
					if (empty($is_all)) {
						$workExternalUserData = $workExternalUserData->limit($pageSize)->offset($offset);
					}
					$workExternalUserData = $workExternalUserData->select('wf.user_id,wu.name')->groupBy(['wf.user_id']);
					$workExternalUserData = $workExternalUserData->asArray()->all();

					$result = [];
					if (!empty($workExternalUserData)) {
						$circulation  = true;
						$TempCount    = 0;
						$Ps           = 15;
						$TempLastUser = true;
						foreach ($workExternalUserData as $kv => $user) {
							if (!empty($last_user) && $user['user_id'] != $last_user && $TempLastUser) {
								continue;
							}
							if (!$circulation) {
								break;
							}
							$result[$kv]['name']     = $user['name'];
							$result[$kv]['user_id']  = $user['user_id'];
							$workExternalUserDataNew = [];
							$workExternalUserDataNew = WorkExternalContactFollowUser::find()->alias('wf');
							$workExternalUserDataNew = $workExternalUserDataNew->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
							$workExternalUserDataNew = $workExternalUserDataNew->andWhere(['we.corp_id' => $this->corp['id'], 'wf.user_id' => $user['user_id']])->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);

							$workExternalUserDataNew = WorkExternalContactFollowUser::getCondition($workExternalUserDataNew, $this->corp['id'], $data);

							if (empty($from_unique)) {
								$workExternalUserDataNew = $workExternalUserDataNew->select('we.id as wid,we.external_userid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.update_time,wf.id fid,wf.way_id,wf.add_way,wf.follow_num,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.update_time,wf.remark_mobiles,wf.is_reclaim,wf.is_protect')->groupBy($group)->orderBy(['wf.createtime' => SORT_DESC]);
								$workExternalUserDataNew = $workExternalUserDataNew->asArray()->all();
							} else {
								$workExternalUserDataNew = $workExternalUserDataNew->select('we.id as wid,we.external_userid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.update_time,wf.id fid,wf.way_id,wf.follow_num,wf.add_way,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.update_time,wf.remark_mobiles,count(DISTINCT(wf.user_id)) as count,wf.is_reclaim,wf.is_protect')->groupBy($group)->having(['>', 'count', 1])->orderBy(['wf.createtime' => SORT_DESC]);
								$workExternalUserDataNew = $workExternalUserDataNew->asArray()->all();
							}
							$info = [];
							if (!empty($workExternalUserDataNew)) {
								// TODO Shi Yimin $workExternalUserDataNew 的 count 返回给前端，同时根据 lastId 和 pSize 做 $workExternalUserDataNew 数组的分页截取，同时和前端对一下排序的事情，还有非企微客户！！！
								$result[$kv]['ContactCount'] = count($workExternalUserDataNew);
								$ContactIdAndKey             = array_column($workExternalUserDataNew, "id");
								if ($TempCount != 0 && $TempCount < $Ps) {
									$length = $Ps - $TempCount;
								} else {
									$length = $Ps;
								}
								if (!empty($last_id)) {
									$localKey = array_search($last_id, $ContactIdAndKey);
									if ($localKey === false) {
										$workExternalUserDataNew = array_slice($workExternalUserDataNew, 0, $length);
									} else {
										$workExternalUserDataNew = array_slice($workExternalUserDataNew, $localKey + 1, $length);
									}
								} else {
									$workExternalUserDataNew = array_slice($workExternalUserDataNew, 0, $length);
								}
								$TempCount += count($workExternalUserDataNew);
								if ($TempCount < $Ps) {
									$circulation  = true;
									$TempLastUser = false;
								} else {
									$circulation = false;
								}

								foreach ($workExternalUserDataNew as $key => $val) {
									if ($val['gender'] == 0) {
										$gender = '未知';
									} elseif ($val['gender'] == 1) {
										$gender = '男性';
									} elseif ($val['gender'] == 2) {
										$gender = '女性';
									}
									if (empty($from_unique)) {
										$workExternal = WorkExternalContactFollowUser::find()->andWhere(['id' => $val['fid']])->all();
									} else {
										$workExternal = WorkExternalContactFollowUser::find()->alias('f')->leftJoin('{{%work_external_contact}} c', '`c`.`id` = `f`.`external_userid`')->where(['c.corp_id' => $this->corp->id, 'f.external_userid' => $val['wid']])->select('f.user_id,f.createtime,f.del_type,f.nickname,f.remark');
										$workExternal = $workExternal->all();
									}
									$memberInfo = [];
									$userId     = [];
									if (!empty($workExternal)) {
										/**
										 * @var int                           $k
										 * @var WorkExternalContactFollowUser $user
										 */
										foreach ($workExternal as $k => $user) {
											$departName = WorkDepartment::getDepartNameByUserId($user->user_id);
											$work_user  = WorkUser::findOne($user->user_id);
											$member     = $departName . '--' . $work_user->name;
											$remark     = !empty($user->nickname) ? "（备注：" . $user->nickname . "）" : ((!empty($user->remark) && $user->remark != $val['name_convert']) ? "（备注：" . $user->remark . "）" : "");

											array_push($userId, $work_user->id);
											$memberInfo[$k]['remark']      = $remark;
											$memberInfo[$k]['member']      = $member;
											$memberInfo[$k]['del_type']    = $user->del_type;
											$memberInfo[$k]['create_time'] = !empty($user->createtime) ? date("Y-m-d H:i:s", $user->createtime) : '';
										}
									}
									$perName = WorkPerTagFollowUser::getTagName($val['fid'], $from_unique, $userId);
									$tagName = WorkTagContact::getTagNameByContactId($val['fid'], 0, $from_unique, $userId,$this->corp->id);

									$info[$key]['customerInfo']['avatar']    = $val['avatar'];
									$info[$key]['customerInfo']['name']      = !empty($val['name']) ? rawurldecode($val['name']) : '';
									$info[$key]['customerInfo']['corp_name'] = $val['wcorp_name'];

									//高级属性
									$fieldValue  = CustomFieldValue::find()->where(['type' => 1, 'cid' => $val['wid']])->andWhere(['in', 'fieldid', $contactField])->asArray()->all();
									$fieldValueD = [];
									foreach ($fieldValue as $field) {
										$fieldValueD[$field['fieldid']] = $field['value'];
									}
									$info[$key]['customerInfo']['nickname'] = isset($fieldValueD[$fieldD['name']]) ? $fieldValueD[$fieldD['name']] : '';
									$info[$key]['customerInfo']['phone']    = isset($val['remark_mobiles']) && !empty($val['remark_mobiles']) ? $val['remark_mobiles'] : '';
									$info[$key]['customerInfo']['area']     = isset($fieldValueD[$fieldD['area']]) ? $fieldValueD[$fieldD['area']] : '';
									if ($is_hide_phone){
										$info[$key]['customerInfo']['phone'] = '';
									}
									if (isset($fieldValueD[$fieldD['sex']])) {
										if ($fieldValueD[$fieldD['sex']] == '男') {
											$gender = '男性';
										} elseif ($fieldValueD[$fieldD['sex']] == '女') {
											$gender = '女性';
										} else {
											$gender = '未知';
										}
									}
									$follow = Follow::findOne($val['follow_id']);
									$title  = '';
									if (!empty($follow)) {
										$title = $follow->title;
										if (empty($follow->status)) {
											$title .= '（已删除）';
										}
									}
									if ($val['update_time'] == $val['createtime']) {
										$chat = '一直未沟通';
									} else {
										$time = $val['update_time'];
										$chat = DateUtil::getDiffText($time) . '沟通';
									}
									$info[$key]['customerInfo']['gender'] = $gender;
									$info[$key]['follow_status']          = $title;
									$info[$key]['tag_name']               = $tagName;
									$info[$key]['per_name']               = $perName;
									$info[$key]['key']                    = $val['fid'];
									$info[$key]['memberInfo']             = $memberInfo;
									$info[$key]['chat']                   = $chat;
									$info[$key]['external_userid']        = $val['external_userid'];
									$info[$key]['userid']                 = $val['userid'];
									$info[$key]['is_protect']             = empty($val['is_reclaim']) && !empty($val['is_protect']) ? 1 : 0;
								}
							}
							$result[$kv]['info'] = array_values($info);
							$result              = array_values($result);
						}
					}

				} else {

					$count = $workExternalUserData->groupBy(['wf.id'])->count();
					if (empty($is_all)) {
						$workExternalUserData = $workExternalUserData->limit($pageSize)->offset($offset);
					}

					if (empty($from_unique)) {
						$workExternalUserData = $workExternalUserData->select('we.id as wid,we.external_userid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.update_time,wf.id fid,wf.way_id,wf.add_way,wf.follow_num,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.update_time,wf.remark_mobiles,wf.is_reclaim,wf.is_protect')->groupBy($group)->orderBy(['wf.createtime' => SORT_DESC]);
						$workExternalUserData = $workExternalUserData->asArray()->all();
					} else {
						$workExternalUserData = $workExternalUserData->select('we.id as wid,we.external_userid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.update_time,wf.id fid,wf.way_id,wf.follow_num,wf.add_way,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.update_time,wf.remark_mobiles,count(DISTINCT(wf.user_id)) as count,wf.is_reclaim,wf.is_protect')->groupBy($group)->having(['>', 'count', 1])->orderBy(['wf.createtime' => SORT_DESC]);
						$workExternalUserData = $workExternalUserData->asArray()->all();
					}

					$result = [];
					if (!empty($workExternalUserData)) {
						foreach ($workExternalUserData as $key => $val) {
							if ($val['gender'] == 0) {
								$gender = '未知';
							} elseif ($val['gender'] == 1) {
								$gender = '男性';
							} elseif ($val['gender'] == 2) {
								$gender = '女性';
							}
							if (empty($from_unique)) {
								$workExternal = WorkExternalContactFollowUser::find()->andWhere(['id' => $val['fid']])->all();
							} else {
								$workExternal = WorkExternalContactFollowUser::find()->alias('f')->leftJoin('{{%work_external_contact}} c', '`c`.`id` = `f`.`external_userid`')->where(['c.corp_id' => $this->corp->id, 'f.external_userid' => $val['wid']])->select('f.user_id,f.createtime,f.del_type,f.nickname,f.remark');
								$workExternal = $workExternal->all();
							}
							$memberInfo = [];
							$userId     = [];
							if (!empty($workExternal)) {
								/**
								 * @var int                           $k
								 * @var WorkExternalContactFollowUser $user
								 */
								foreach ($workExternal as $k => $user) {
									$departName = WorkDepartment::getDepartNameByUserId($user->user_id);
									$work_user  = WorkUser::findOne($user->user_id);
									$member     = $work_user->name . '--' . $departName;
									$remark     = !empty($user->nickname) ? "（备注：" . $user->nickname . "）" : ((!empty($user->remark) && $user->remark != $val['name_convert']) ? "（备注：" . $user->remark . "）" : "");

									array_push($userId, $work_user->id);
									$memberInfo[$k]['remark']      = $remark;
									$memberInfo[$k]['member']      = $member;
									$memberInfo[$k]['del_type']    = $user->del_type;
									$memberInfo[$k]['create_time'] = !empty($user->createtime) ? date("Y-m-d H:i:s", $user->createtime) : '';
								}
							}
							$perName = WorkPerTagFollowUser::getTagName($val['fid'], $from_unique, $userId);
							$tagName = WorkTagContact::getTagNameByContactId($val['fid'], 0, $from_unique, $userId, $this->corp->id);

							$result[$key]['customerInfo']['avatar']    = $val['avatar'];
							$result[$key]['customerInfo']['name']      = !empty($val['name']) ? rawurldecode($val['name']) : '';
							$result[$key]['customerInfo']['corp_name'] = $val['wcorp_name'];

							//高级属性
							$fieldValue  = CustomFieldValue::find()->where(['type' => 1, 'cid' => $val['wid']])->andWhere(['in', 'fieldid', $contactField])->asArray()->all();
							$fieldValueD = [];
							foreach ($fieldValue as $field) {
								$fieldValueD[$field['fieldid']] = $field['value'];
							}
							$result[$key]['customerInfo']['nickname'] = isset($fieldValueD[$fieldD['name']]) ? $fieldValueD[$fieldD['name']] : '';
							$result[$key]['customerInfo']['phone']    = isset($val['remark_mobiles']) && !empty($val['remark_mobiles']) ? $val['remark_mobiles'] : '';
							$result[$key]['customerInfo']['area']     = isset($fieldValueD[$fieldD['area']]) ? $fieldValueD[$fieldD['area']] : '';
							if ($is_hide_phone){
								$result[$key]['customerInfo']['phone'] = '';
							}
							if (isset($fieldValueD[$fieldD['sex']])) {
								if ($fieldValueD[$fieldD['sex']] == '男') {
									$gender = '男性';
								} elseif ($fieldValueD[$fieldD['sex']] == '女') {
									$gender = '女性';
								} else {
									$gender = '未知';
								}
							}
							$follow = Follow::findOne($val['follow_id']);
							$title  = '';
							if (!empty($follow)) {
								$title = $follow->title;
								if (empty($follow->status)) {
									$title .= '（已删除）';
								}
							}
							if ($val['update_time'] == $val['createtime']) {
								$chat = '一直未沟通';
							} else {
								$time = $val['update_time'];
								$chat = DateUtil::getDiffText($time) . '沟通';
							}
							$day       = date("Y-m-d", $val['createtime']);
							$toDay     = date("Y-m-d");
							$yesterday = date("Y-m-d", strtotime("-1 day"));
							$time      = $day;
							if ($day == $toDay) {
								$time = '今天';
							}
							if ($day == $yesterday) {
								$time = '昨天';
							}
							$result[$key]['customerInfo']['gender'] = $gender;
							$result[$key]['follow_status']          = $title;
							$result[$key]['tag_name']               = $tagName;
							$result[$key]['per_name']               = $perName;
							$result[$key]['key']                    = $val['fid'];
							$result[$key]['createtime']             = $time;
							$result[$key]['memberInfo']             = $memberInfo;
							$result[$key]['chat']                   = $chat;
							$result[$key]['external_userid']        = $val['external_userid'];
							$result[$key]['userid']                 = $val['userid'];
							$result[$key]['is_protect']             = empty($val['is_reclaim']) && !empty($val['is_protect']) ? 1 : 0;
						}
					}
					$resultData = [];
					if (!empty($result)) {
						$result = SUtils::arrayGroupBy($result, 'createtime');
						$i      = 0;
						foreach ($result as $k => $val) {
							$createTime             = $val[0]['createtime'];
							$resultData[$i]['name'] = $createTime;
							$resultData[$i]['info'] = $val;
							$i++;
						}
					}
					$result = $resultData;

				}

				return [
					'is_hide_phone' => $is_hide_phone,
					'user_count'    => $userCount,
					'show'          => $show,
					'count'         => $count,
					'customCount'   => $customCount,
					'uniqueCount'   => $uniqueCount,
					'info'          => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

	}