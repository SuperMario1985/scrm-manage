<?php

	namespace app\modules\admin\controllers;

	use app\models\AdminUser;
	use app\models\AdminUserEmployee;
	use app\models\Agent;
	use app\models\AgentBalance;
	use app\models\AgentOrder;
	use app\models\Area;
	use app\models\AttachmentGroup;
	use app\models\MessageOrder;
	use app\models\MessageSign;
	use app\models\Package;
	use app\models\User;
	use app\models\UserApplication;
	use app\models\UserAuthorRelation;
	use app\models\UserCorpRelation;
	use app\models\WorkCorp;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkMsgAudit;
	use app\models\WorkUser;
	use app\modules\admin\components\BaseController;
	use app\util\DateUtil;
	use app\util\StringUtil;
	use app\util\SUtils;
	use Yii;
	use app\util\WebhookUtil;
	use yii\data\Pagination;
	use app\components\InvalidDataException;
	use yii\db\Expression;
	use yii\helpers\Json;
	use yii\widgets\LinkPager;

	class UserManageController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;
		public $adminUser;
		public $eid;
		public $eidFunctionAuthority;//员工功能权限

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;

			if (!\Yii::$app->adminUser->isGuest){
				$account         = Yii::$app->adminUser->identity->account;
				$this->adminUser = AdminUser::findOne(['account' => $account]);

				$this->eid = isset(Yii::$app->adminUserEmployee->identity->id) ? Yii::$app->adminUserEmployee->identity->id : 0;
				//员工功能权限
				if ($this->adminUser->type == 0 && $this->eid > 0){
					$this->eidFunctionAuthority = AdminUserEmployee::getEmployeeFunctionAuthority($this->eid);
				}else{
					$this->eidFunctionAuthority = [];
				}
			}
		}

		/**
		 * 意向客户统计
		 */
		public function actionUserStatistics ()
		{
			$uid            = \Yii::$app->request->get('uid', 0);
			$eidSerach      = \Yii::$app->request->get('eidSerach', 0);
			$status         = \Yii::$app->request->get('status', 0);
			$aid            = \Yii::$app->request->get('aid', 0);
			$source         = \Yii::$app->request->get('source', 0);
			$time_type      = \Yii::$app->request->get('time_type', 1);
			$dates          = \Yii::$app->request->get('dates', '');
			$companyName    = \Yii::$app->request->get('companyName', '');
			$time           = time();
			$defaultPackage = Package::getDefaultPackage();

			//首次刷新套餐
			$userPackage = User::find()->andWhere(['package_id' => 0])->all();
			foreach ($userPackage as $user) {
				$user->package_id   = $defaultPackage->package_id;
				$user->package_time = $defaultPackage->duration;
				$user->time_type    = $defaultPackage->duration_type;
				if ($user->time_type == 2) {
					$timeType = 'month';
				} elseif ($user->time_type == 2) {
					$timeType = 'year';
				} else {
					$timeType = 'day';
				}
				$end_time = strtotime("$user->create_time +$user->package_time $timeType");
				if ($end_time % 86400 == 0){
					$user->end_time = $end_time;
				}else{
					$user->end_time = strtotime(date('Y-m-d', $end_time)) + 86399;
				}

				$user->save();
			}

			$user = User::find()->alias('u');
			$user = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
			$user = $user->andWhere(['u.is_merchant' => 0]);

			//代理商登录
			if ($this->adminUser->type != 0){
				$user = $user->andWhere(['u.agent_uid' => $this->adminUser->id]);
			}
			//员工登录
			$employeeRoleId = 0;
			if ($this->eid) {
				$employeeInfo   = AdminUserEmployee::findOne($this->eid);
				$employeeRoleId = $employeeInfo->role_id;
				if ($this->adminUser->type != 0 && $employeeRoleId == 2) {
				} else {
					$user = $user->andWhere(['u.eid' => $this->eid]);
				}
			}

			$snum = $user->count();
			//账户id
			if (!empty($uid)) {
				$user = $user->andWhere(['u.uid' => $uid]);
			}
			//员工
			if (!empty($eidSerach)) {
				$user = $user->andWhere(['u.eid' => $eidSerach]);
			}
			//状态
			if ($status) {
				switch ($status) {
					case 1://使用中
						$user = $user->andWhere(['u.status' => 1])->andWhere(['>', 'u.end_time', $time]);
						break;
					case 2://免费试用（永久）
						if ($defaultPackage->expire_type == 1) {
							$user = $user->andWhere(['u.uid' => 0]);//套餐到期禁用 则无永久使用
						} else {
							$user = $user->andWhere(['u.status' => 1])->andWhere(['<', 'u.end_time', $time]);
						}
						break;
					case 3://已禁用
						if ($defaultPackage->expire_type == 1) {
							$user = $user->andWhere(['or', ['u.status' => 0], ['<', 'u.end_time', $time]]);//包含套餐到期禁用
						} else {
							$user = $user->andWhere(['u.status' => 0]);
						}
						break;
				}
			}
			//代理商来源
			if ($aid != 0){
				if ($aid == -1){
					$user = $user->andWhere(['u.agent_uid' => 0]);
				}else{
					$user = $user->andWhere(['u.agent_uid' => $aid]);
				}
			}
			//来源
			if ($source) {
				$user = $user->andWhere(['u.source' => $source]);
			}
			//时间
			if (!empty($dates) && !empty($time_type)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';

				if ($time_type == 1) {
					$user = $user->andWhere(['between', 'u.create_time', $start_date, $end_date]);
				} elseif ($time_type == 2) {
					$user = $user->andWhere(['between', 'u.end_time', strtotime($start_date), strtotime($end_date)]);
				} elseif ($time_type == 3) {
					$user = $user->andWhere(['between', 'u.login_time', strtotime($start_date), strtotime($end_date)]);
				}
			}
			//公司名称
			if ($companyName) {
				$user = $user->andWhere(['like', 'up.company_name', $companyName]);
			}

			$count = $user->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$user  = $user->select('u.*,up.company_name companyName')->offset($pages->offset)->limit($pages->limit)->orderBy('u.uid desc')->asArray()->all();

			//所有套餐
			$packageData = Package::find()->select('id,name')->asArray()->all();
			$packageName = [];
			foreach ($packageData as $k => $v) {
				$packageName[$v['id']] = $v['name'];
			}
			//代理商
			$agentData = Agent::find()->andWhere(['is_contract' => 1])->select('uid, aname')->asArray()->all();
			$agentD    = [];
			foreach ($agentData as $v) {
				$agentD[$v['uid']] = $v['aname'];
			}

			foreach ($user as $k => $v) {
				$userCorp = UserCorpRelation::find()->andWhere(['uid' => $v['uid']])->all();
				$corpIds = [];
				foreach ($userCorp as $u){
					array_push($corpIds, $u->corp_id);
				}

				$workUserNum = 0;//员工数
				$externalNum = 0;//客户数
				if (!empty($corpIds)){
					$workUserNum = WorkUser::find()->andWhere(['in', 'corp_id', $corpIds])->andWhere(['status' => 1, 'is_del' => 0])->count();
					$externalNum = WorkExternalContactFollowUser::find()->alias('wf');
					$externalNum = $externalNum->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
					$externalNum = $externalNum->andWhere(['in', 'we.corp_id', $corpIds]);
					$externalNum = $externalNum->andWhere(['wf.del_type' => 0]);
					$externalNum = $externalNum->groupBy('we.id')->count();
				}
				$user[$k]['workUserNum'] = $workUserNum;
				$user[$k]['externalNum'] = $externalNum;
				//公司名称
				$userCompanyName = '';
				if (!empty($v['companyName'])) {
					$userCompanyName = $v['companyName'];
				} else if (!empty($userCorp)) {
					$userCorpRelation = $userCorp[0];
					$workCorp         = WorkCorp::findOne($userCorpRelation->corp_id);
					if (!empty($workCorp)) {
						$userCompanyName = $workCorp->corp_name;
					}
				}
				$user[$k]['companyName'] = $userCompanyName ? $userCompanyName : '--';
				//授权公众号数量
				$user[$k]['authorNum'] = UserAuthorRelation::find()->where(['uid' => $v['uid']])->select('id')->count();
				//授权企业微信数量
				$user[$k]['corpNum'] = count($userCorp);
				//短信签名数量
				$user[$k]['messageSignNum'] = MessageSign::find()->where(['uid' => $v['uid'], 'status' => 1])->select('id')->count();
				//短信订单
				$messageOrder = MessageOrder::find()->where(['uid' => $v['uid'], 'ispay' => 1, 'goods_type' => 'messagePay'])->select('goods_price,extrainfo')->asArray()->all();
				$messageSum   = 0;
				$priceSum     = 0;
				$buyNum       = count($messageOrder);
				foreach ($messageOrder as $kk => $vv) {
					$priceSum   += $vv['goods_price'];
					$extrainfo  = json_decode($vv['extrainfo'], 1);
					$messageSum += $extrainfo['message_num'];
				}
				$user[$k]['messageNum'] = $v['message_num'] . '条/' . $messageSum . '条';
				$user[$k]['messageBuy'] = $buyNum . '次/' . $priceSum . '元';

				$user[$k]['limit_author_num'] = $v['limit_author_num'] > 0 ? $v['limit_author_num'] : 5;
				$user[$k]['limit_corp_num']   = $v['limit_corp_num'] > 0 ? $v['limit_corp_num'] : 1;

				$user[$k]['source'] = $v['source'] == 1 ? '自助注册' : '手动录入';
				if ($v['time_type'] == 1) {
					$timeType = '日';
				} elseif ($v['time_type'] == 2) {
					$timeType = '月';
				} else {
					$timeType = '年';
				}
				$user[$k]['packageName'] = isset($packageName[$v['package_id']]) ? $packageName[$v['package_id']] . '(' . $v['package_time'] . $timeType . ')' : '--';

				$user[$k]['end_time']   = $v['end_time'] ? date('Y-m-d H:i', $v['end_time']) : '--';
				$user[$k]['login_time'] = $v['login_time'] ? date('Y-m-d H:i', $v['login_time']) : '--';
				if ($v['status'] == 1 && $v['end_time'] > $time) {
					$statusName = '使用中';
				} elseif ($v['status'] == 0) {
					$statusName = '已禁用';
				} else {
					$statusName = $defaultPackage->expire_type == 1 ? '已禁用' : '免费使用（永久）';
				}
				$user[$k]['statusName'] = $statusName;
				$user[$k]['code']       = base64_encode($v['password'] . '-' . $v['uid']);
				if ($v['agent_uid'] == 0) {
					$agentName = '总后台';
				} else {
					$agentName = isset($agentD[$v['agent_uid']]) ? $agentD[$v['agent_uid']] : '--';
				}
				$user[$k]['agentName'] = $agentName;
				$employeeName = '--';
				if ($v['eid'] && ($v['agent_uid'] == 0 || $this->adminUser->type != 0)) {
					$employee     = AdminUserEmployee::findOne($v['eid']);
					$employeeName = isset($employee->name) ? $employee->name : '--';
				}
				$user[$k]['employeeName'] = $employeeName;
			}

			//账户
			$userArr = User::find()->where(['is_merchant' => 0]);
			if ($this->adminUser->type != 0) {
				$userArr = $userArr->andWhere(['agent_uid' => $this->adminUser->id]);
			}
			if ($this->eid){
				$userArr = $userArr->andWhere(['eid' => $this->eid]);
			}
			$userArr = $userArr->select('uid,account')->all();

			//套餐
			$isAgent               = $this->adminUser->type != 0 ? 1 : 0;
			$packageList           = Package::getAllPackageInfo($isAgent);
			$packageLocalPriceJson = [];
			foreach ($packageList as $k => $package) {
				$packagePrice                          = !empty($package['priceJson']) ? json_decode($package['priceJson'], true) : [];
				$packageLocalPriceJson[$package['id']] = $packagePrice;
			}

			//员工
			$employeeList = AdminUserEmployee::find()->andWhere(['uid' => $this->adminUser->id])->asArray()->all();
			foreach ($employeeList as $k=>$e){
				$employeeList[$k]['name'] = $e['status'] == 1 ? $e['name'] : $e['name'] . '（已删除）';
			}

			$returnData = [
				'allUser'               => $userArr,
				'userArr'               => $user,
				'snum'                  => $snum,
				'pages'                 => $pages,
				'uid'                   => $uid,
				'eidSerach'             => $eidSerach,
				'status'                => $status,
				'aid'                   => $aid,
				'source'                => $source,
				'time_type'             => $time_type,
				'dates'                 => $dates,
				'companyName'           => $companyName,
				'agentData'             => $agentData,
				'packageList'           => $packageList,
				'packageListJson'       => json_encode($packageList),
				'packageLocalPriceJson' => json_encode($packageLocalPriceJson),
				'eid'                   => $this->eid,
				'employeeRoleId'        => $employeeRoleId,
				'employeeList'          => $employeeList,
				'eidFunctionAuthority'  => $this->eidFunctionAuthority,
			];

			return $this->render('userStatistics', $returnData);
		}

		/**
		 * 入驻客户统计
		 */
		public function actionUserMerchantStatistics ()
		{
			$uid         = \Yii::$app->request->get('uid', 0);
			$status      = \Yii::$app->request->get('status', 0);
			$aid         = \Yii::$app->request->get('aid', 0);
			$source      = \Yii::$app->request->get('source', 0);
			$time_type   = \Yii::$app->request->get('time_type', 1);
			$dates       = \Yii::$app->request->get('dates', '');
			$companyName = \Yii::$app->request->get('companyName', '');
			$time        = time();

			$user = User::find()->alias('u');
			$user = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
			$user = $user->andWhere(['u.is_merchant' => 1]);

			//代理商登录
			if ($this->adminUser->type != 0){
				$user = $user->andWhere(['u.agent_uid' => $this->adminUser->id]);
			}
			//员工登录
			$employeeRoleId = 0;
			if ($this->eid) {
				$employeeInfo   = AdminUserEmployee::findOne($this->eid);
				$employeeRoleId = $employeeInfo->role_id;
				if ($this->adminUser->type == 0 || $employeeRoleId == 1) {
					$user = $user->andWhere(['u.eid' => $this->eid]);
				}
			}

			$snum = $user->count();
			//账户id
			if (!empty($uid)) {
				$user = $user->andWhere(['u.uid' => $uid]);
			}
			//状态
			if ($status) {
				switch ($status) {
					case 1://使用中
						$user = $user->andWhere(['u.status' => 1])->andWhere(['>', 'u.end_time', $time]);
						break;
					case 2://已到期
						$user = $user->andWhere(['u.status' => 1])->andWhere(['<', 'u.end_time', $time]);
						break;
					case 3://已禁用
						$user = $user->andWhere(['u.status' => 0]);
						break;
				}
			}
			//代理商来源
			if ($aid != 0){
				if ($aid == -1){
					$user = $user->andWhere(['u.agent_uid' => 0]);
				}else{
					$user = $user->andWhere(['u.agent_uid' => $aid]);
				}
			}
			//类型
			if ($source) {
				$user = $user->andWhere(['u.source' => $source]);
			}
			//时间
			if (!empty($dates) && !empty($time_type)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';

				if ($time_type == 1) {
					$user = $user->andWhere(['between', 'u.create_time', $start_date, $end_date]);
				} elseif ($time_type == 2) {
					$user = $user->andWhere(['between', 'u.end_time', strtotime($start_date), strtotime($end_date)]);
				} elseif ($time_type == 3) {
					$user = $user->andWhere(['between', 'u.login_time', strtotime($start_date), strtotime($end_date)]);
				}
			}
			//公司名称
			if ($companyName) {
				$user = $user->andWhere(['like', 'up.company_name', $companyName]);
			}

			$count = $user->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$user  = $user->select('u.*,up.company_name companyName')->offset($pages->offset)->limit($pages->limit)->orderBy('u.merchant_time desc')->asArray()->all();

			//所有套餐
			$packageData = Package::find()->select('id,name')->asArray()->all();
			$packageName = [];
			foreach ($packageData as $k => $v) {
				$packageName[$v['id']] = $v['name'];
			}
			//代理商
			$agentData = Agent::find()->andWhere(['is_contract' => 1])->select('uid, aname')->asArray()->all();
			$agentD    = [];
			foreach ($agentData as $v) {
				$agentD[$v['uid']] = $v['aname'];
			}

			foreach ($user as $k => $v) {
				$userCorp = UserCorpRelation::find()->andWhere(['uid' => $v['uid']])->all();
				$corpIds = [];
				foreach ($userCorp as $u){
					array_push($corpIds, $u->corp_id);
				}

				$workUserNum = 0;//员工数
				$externalNum = 0;//客户数
				if (!empty($corpIds)){
					$workUserNum = WorkUser::find()->andWhere(['in', 'corp_id', $corpIds])->andWhere(['status' => 1, 'is_del' => 0])->groupBy('mobile')->count();
					$externalNum = WorkExternalContactFollowUser::find()->alias('wf');
					$externalNum = $externalNum->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
					$externalNum = $externalNum->andWhere(['in', 'we.corp_id', $corpIds]);
					$externalNum = $externalNum->andWhere(['wf.del_type' => 0]);
					$externalNum = $externalNum->groupBy('we.id')->count();
				}
				$user[$k]['workUserNum'] = $workUserNum;
				$user[$k]['externalNum'] = $externalNum;
				//公司名称
				$userCompanyName = '';
				if (!empty($v['companyName'])) {
					$userCompanyName = $v['companyName'];
				} else if (!empty($userCorp)) {
					$userCorpRelation = $userCorp[0];
					$workCorp         = WorkCorp::findOne($userCorpRelation->corp_id);
					if (!empty($workCorp)) {
						$userCompanyName = $workCorp->corp_name;
					}
				}
				$user[$k]['companyName'] = $userCompanyName ? $userCompanyName : '--';
				//授权公众号数量
				$user[$k]['authorNum'] = UserAuthorRelation::find()->where(['uid' => $v['uid']])->select('id')->count();
				//授权企业微信数量
				$user[$k]['corpNum'] = count($userCorp);
				//短信签名数量
				$user[$k]['messageSignNum'] = MessageSign::find()->where(['uid' => $v['uid'], 'status' => 1])->select('id')->count();
				//短信订单
				$messageOrder = MessageOrder::find()->where(['uid' => $v['uid'], 'ispay' => 1, 'goods_type' => 'messagePay'])->select('goods_price,extrainfo')->asArray()->all();
				$messageSum   = 0;
				$priceSum     = 0;
				$buyNum       = count($messageOrder);
				foreach ($messageOrder as $kk => $vv) {
					$priceSum   += $vv['goods_price'];
					$extrainfo  = json_decode($vv['extrainfo'], 1);
					$messageSum += $extrainfo['message_num'];
				}
				$user[$k]['messageNum'] = $v['message_num'] . '条/' . $messageSum . '条';
				$user[$k]['messageBuy'] = $buyNum . '次/' . $priceSum . '元';

				$user[$k]['limit_author_num'] = $v['limit_author_num'] > 0 ? $v['limit_author_num'] : 5;
				$user[$k]['limit_corp_num']   = $v['limit_corp_num'] > 0 ? $v['limit_corp_num'] : 1;

				$user[$k]['source'] = $v['source'] == 1 ? '自助注册' : '手动录入';
				if ($v['time_type'] == 1) {
					$timeType = '日';
				} elseif ($v['time_type'] == 2) {
					$timeType = '天';
				} else {
					$timeType = '年';
				}
				$user[$k]['packageN']    = isset($packageName[$v['package_id']]) ? $packageName[$v['package_id']] : '';
				$user[$k]['packageName'] = isset($packageName[$v['package_id']]) ? $packageName[$v['package_id']] . '(' . $v['package_time'] . $timeType . ')' : '--';

				$user[$k]['merchant_time'] = $v['merchant_time'] ? date('Y-m-d H:i', $v['merchant_time']) : '--';
				$user[$k]['end_time']      = $v['end_time'] ? date('Y-m-d H:i', $v['end_time']) : '--';
				$user[$k]['login_time']    = $v['login_time'] ? date('Y-m-d H:i', $v['login_time']) : '--';
				if ($v['status'] == 1 && $v['end_time'] > $time) {
					$statusName = '使用中';
				} elseif ($v['status'] == 1 && $v['end_time'] <= $time) {
					$statusName = '已到期';
				} else {
					$statusName = '已禁用';
				}
				$user[$k]['statusName'] = $statusName;
				$user[$k]['code']       = base64_encode($v['password'] . '-' . $v['uid']);
				if ($v['agent_uid'] == 0) {
					$agentName = '总后台';
				} else {
					$agentName = isset($agentD[$v['agent_uid']]) ? $agentD[$v['agent_uid']] : '--';
				}
				$user[$k]['agentName'] = $agentName;
				$employeeName = '--';
				if ($v['eid']) {
					$employee     = AdminUserEmployee::findOne($v['eid']);
					$employeeName = isset($employee->name) ? $employee->name : '--';
				}
				$user[$k]['employeeName'] = $employeeName;
			}

			//账户
			$userArr = User::find()->where(['is_merchant' => 1]);
			if ($this->adminUser->type != 0) {
				$userArr = $userArr->andWhere(['agent_uid' => $this->adminUser->id]);
			}
			if ($this->eid){
				$userArr = $userArr->andWhere(['eid' => $this->eid]);
			}
			$userArr = $userArr->select('uid,account')->all();

			//套餐
			$packageList           = Package::getAllPackageInfo(1);
			$packageLocalPriceJson = [];
			foreach ($packageList as $k => $package) {
				$packagePrice                          = !empty($package['priceJson']) ? json_decode($package['priceJson'], true) : [];
				$packageLocalPriceJson[$package['id']] = $packagePrice;
			}

			$returnData = [
				'allUser'               => $userArr,
				'userArr'               => $user,
				'snum'                  => $snum,
				'pages'                 => $pages,
				'uid'                   => $uid,
				'status'                => $status,
				'aid'                   => $aid,
				'source'                => $source,
				'time_type'             => $time_type,
				'dates'                 => $dates,
				'companyName'           => $companyName,
				'agentData'             => $agentData,
				'packageList'           => $packageList,
				'packageListJson'       => json_encode($packageList),
				'packageLocalPriceJson' => json_encode($packageLocalPriceJson),
				'eid'                   => $this->eid,
				'employeeRoleId'        => $employeeRoleId,
			];

			return $this->render('userMerchantStatistics', $returnData);
		}

		/**
		 * 账号设置
		 */
		public function actionSetUser ()
		{
			$postData = \Yii::$app->request->post();

			$uid            = $postData['uid'];
			$limitCorpNum   = $postData['limitCorpNum'];
			$limitAuthorNum = $postData['limitAuthorNum'];

			$user = User::findOne($uid);

			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '用户数据错误']);
			}
			$user->limit_corp_num   = $limitCorpNum;
			$user->limit_author_num = $limitAuthorNum;

			if (!$user->save()) {
				$this->dexit(['error' => 1, 'msg' => SUtils::modelError($user)]);
			}

			$this->dexit(['error' => 0, 'msg' => '']);
		}

		/**
		 * 录入客户
		 */
		public function actionRegisterUser ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post         = \Yii::$app->request->post();
			$account      = !empty($post['phone']) ? $post['phone'] : '';
			$password     = !empty($post['password']) ? $post['password'] : '';
			$province     = !empty($post['province']) ? $post['province'] : '';
			$city         = !empty($post['city']) ? $post['city'] : '';
			$company_name = !empty($post['name']) ? $post['name'] : '';
			$nickname     = !empty($post['nick']) ? $post['nick'] : '';
			$sex          = !empty($post['sex']) ? $post['sex'] : '1';
			$email        = !empty($post['email']) ? $post['email'] : '';
			$qq           = !empty($post['qq']) ? $post['qq'] : '';
			$weixin       = !empty($post['weixin']) ? $post['weixin'] : '';
			//帐号
			if (empty($account)) {
				$this->dexit(['error' => 1, 'msg' => '请输入手机号']);
			} elseif (!preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $account)) {
				$this->dexit(['error' => 1, 'msg' => '请输入正确的手机号']);
			}
			//密码
			if (empty($password)) {
				$this->dexit(['error' => 1, 'msg' => '请输入密码']);
			} else {
				$length = strlen($password);
				if ($length < 6 || $length > 20) {
					$this->dexit(['error' => 1, 'msg' => '请输入6-20位密码']);
				}
			}
			if (empty($nickname)) {
				$this->dexit(['error' => 1, 'msg' => '请填写昵称']);
			}
			if (empty($province) || empty($city)) {
				$this->dexit(['error' => 1, 'msg' => '请填写省市']);
			}
			//保存
			try {
				$profileData = [
					'company_name' => $company_name,
					'nick_name'    => $nickname,
					'sex'          => $sex,
					'province'     => $province,
					'city'         => $city,
					'email'        => $email,
					'qq'           => $qq,
					'weixin'       => $weixin
				];
				$agent_uid = $this->adminUser->type != 0 ? $this->adminUser->id : 0;
				$userInfo  = User::create($account, $password, 2, $profileData, $agent_uid, $this->eid);
				//设置默认分组
				AttachmentGroup::setNotGroup($userInfo->uid);

				$this->dexit(['error' => 0, 'msg' => '']);
			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 重置密码
		 */
		public function actionResetPwd ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post     = \Yii::$app->request->post();
			$uid      = !empty($post['uid']) ? $post['uid'] : '';
			$password = !empty($post['password']) ? $post['password'] : '';

			//密码
			if (empty($password)) {
				$this->dexit(['error' => 1, 'msg' => '请输入密码']);
			} else {
				$length = strlen($password);
				if ($length < 6 || $length > 20) {
					$this->dexit(['error' => 1, 'msg' => '请输入6-20位密码']);
				}
			}

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}

			$user->salt         = StringUtil::randomStr(6, true);
			$user->password     = StringUtil::encodePassword($user->salt, $password);
			$user->update_time  = DateUtil::getCurrentTime();
			$user->access_token = '';

			if ($user->save()) {
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '重置密码失败']);
			}
		}

		/**
		 * 套餐体验延期
		 */
		public function actionSetLengthenPackage ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post         = \Yii::$app->request->post();
			$uid          = !empty($post['uid']) ? $post['uid'] : '';
			$package_id   = !empty($post['package_id']) ? $post['package_id'] : '0';
			$package_time = !empty($post['package_time']) ? $post['package_time'] : '0';

			if (empty($uid) || empty($package_id) || empty($package_time)) {
				$this->dexit(['error' => 1, 'msg' => '数据错误']);
			}

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}

			$package = Package::findOne($package_id);
			if (empty($package)) {
				$this->dexit(['error' => 1, 'msg' => '套餐数据错误']);
			}

			try {
				$time               = time();
				$user->package_id   = $package->id;
				$user->package_time = $package_time;
				$user->time_type    = 1;
				//套餐结束时间
				if ($time > $user->end_time) {
					$end_time = strtotime("+$user->package_time day");
				} else {
					$now_end_time = date('Y-m-d H:i:s', $user->end_time);
					$end_time     = strtotime("$now_end_time +$user->package_time day");
				}
				if ($end_time % 86400 == 0) {
					$user->end_time = $end_time;
				} else {
					$user->end_time = strtotime(date('Y-m-d', $end_time)) + 86399;
				}

				if ($user->save()) {
					$this->dexit(['error' => 0, 'msg' => '']);
				} else {
					$this->dexit(['error' => 1, 'msg' => '延期失败']);
				}
			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 客户入驻
		 */
		public function actionSetUserMerchant ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post        = \Yii::$app->request->post();
			$uid         = !empty($post['uid']) ? $post['uid'] : '';
			$package_id  = !empty($post['package_id']) ? $post['package_id'] : '0';
			$package_key = !empty($post['package_key']) ? $post['package_key'] : '0';

			if (empty($uid) || empty($package_id)) {
				$this->dexit(['error' => 1, 'msg' => '数据错误']);
			}

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}

			$package = Package::findOne($package_id);
			if (empty($package)) {
				$this->dexit(['error' => 1, 'msg' => '套餐数据错误']);
			}
			if ($package->status != 1){
				$this->dexit(['error' => 1, 'msg' => '提交失败，套餐已删除']);
			}
			//套餐档位
			$timeData = json_decode($package->priceJson, true);
			$timeInfo = $timeData[$package_key];
			if (empty($timeInfo['timeNum'])) {
				$this->dexit(['error' => 1, 'msg' => '套餐时长数据错误']);
			}
			$timeType = '日';
			if ($timeInfo['timeType'] == 2) {
				$timeType = '月';
			} elseif ($timeInfo['timeType'] == 3) {
				$timeType = '年';
			}

			try {
				if (empty($this->adminUser)) {
					$this->dexit(['error' => 1, 'msg' => '帐号数据错误！']);
				}
				if ($this->adminUser->type == 0) {
					$messageOrder              = new MessageOrder();
					$messageOrder->uid         = $uid;
					$messageOrder->order_id    = '';
					$messageOrder->pay_way     = 'weixin';
					$messageOrder->pay_type    = 'wxsaoma2pay';
					$messageOrder->goods_type  = 'packageBuy';
					$messageOrder->goods_id    = $package->id;
					$messageOrder->goods_name  = $package->name . '（' . $timeInfo['timeNum'] . $timeType . '）';
					$messageOrder->goods_price = $timeInfo['nowPrice'];
					$messageOrder->add_time    = DateUtil::getCurrentTime();
					$messageOrder->paytime     = DateUtil::getCurrentTime();
					$messageOrder->ispay       = 1;
					$messageOrder->extrainfo   = json_encode($timeInfo, JSON_UNESCAPED_UNICODE);

					if (!$messageOrder->validate() || !$messageOrder->save()) {
						$this->dexit(['error' => 1, 'msg' => SUtils::modelError($messageOrder)]);
					}

					$time                = time();
					$user->package_id    = $package->id;
					$user->package_time  = $timeInfo['timeNum'];
					$user->time_type     = $timeInfo['timeType'];
					$user->is_merchant   = 1;
					$user->merchant_time = empty($user->merchant_time) ? $time : $user->merchant_time;
					//套餐结束时间
					if ($user->time_type == 2) {
						$time_type = 'month';
					} elseif ($user->time_type == 3) {
						$time_type = 'year';
					} else {
						$time_type = 'day';
					}
					if ($time > $user->end_time) {
						$end_time = strtotime("+$user->package_time $time_type");
					} else {
						$now_end_time = date('Y-m-d H:i:s', $user->end_time);
						$end_time     = strtotime("$now_end_time +$user->package_time $time_type");
					}
					if ($timeInfo['sendTimeNum'] > 0) {
						$sendTimeNum = $timeInfo['sendTimeNum'];
						if ($timeInfo['sendTimeType'] == 2) {
							$sendTimeType = 'month';
						} elseif ($timeInfo['sendTimeType'] == 3) {
							$sendTimeType = 'year';
						} else {
							$sendTimeType = 'day';
						}
						$now_end_time = date('Y-m-d H:i:s', $end_time);
						$end_time     = strtotime("$now_end_time +$sendTimeNum $sendTimeType");
					}
					if ($end_time % 86400 == 0){
						$user->end_time = $end_time;
					}else{
						$user->end_time = strtotime(date('Y-m-d', $end_time)) + 86399;
					}

					if ($user->save()) {
						$this->dexit(['error' => 0, 'msg' => '']);
					} else {
						$this->dexit(['error' => 1, 'msg' => '入驻失败']);
					}
				} else {
					$type = !empty($post['type']) ? $post['type'] : '1';
					//代理商提单
					$orderData                   = [];
					$orderData['agent_uid']      = $this->adminUser->id;
					$orderData['eid']            = $this->eid;
					$orderData['uid']            = $uid;
					$orderData['agent_type']     = 1;
					$orderData['type']           = $type;
					$orderData['original_price'] = $timeInfo['nowPrice'];
					$orderData['status']         = 1;
					$orderData['package_id']     = $package->id;
					$orderData['package_time']   = $timeInfo['timeNum'];
					$orderData['time_type']      = $timeInfo['timeType'];
					if ($type == 2 && isset($timeInfo['discount']) && !empty($timeInfo['discount']) && $timeInfo['discount'] > 0 && $timeInfo['discount'] <= 10) {
						$orderData['discount'] = $timeInfo['discount'] / 10;
					}
					$extrainfo                   = [];
					if ($timeInfo['sendTimeNum'] > 0) {
						$extrainfo['sendTimeNum']  = $timeInfo['sendTimeNum'];
						$extrainfo['sendTimeType'] = $timeInfo['sendTimeType'];
					}
					$orderData['extrainfo'] = $extrainfo ? json_encode($extrainfo) : '';

					if (AgentOrder::create($orderData)) {
						$this->dexit(['error' => 0, 'msg' => '']);
					}
				}

			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 设置禁用/启用
		 */
		public function actionSetUserStatus ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post   = \Yii::$app->request->post();
			$uid    = !empty($post['uid']) ? $post['uid'] : '';
			$status = !empty($post['status']) ? $post['status'] : 0;

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			if (!in_array($status, [0, 1])) {
				$this->dexit(['error' => 1, 'msg' => '客户状态数据错误']);
			}

			$user->status = $status;

			if ($user->save()) {
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '设置失败']);
			}
		}

		/**
		 * 提单管理
		 */
		public function actionAgentBill ()
		{
			$aid            = \Yii::$app->request->get('aid', 0);
			$searchProvince = \Yii::$app->request->get('searchProvince', 0);
			$searchCity     = \Yii::$app->request->get('searchCity', 0);
			$searchStatus   = \Yii::$app->request->get('searchStatus', 0);
			$searchType     = \Yii::$app->request->get('searchType', 0);
			$dates          = \Yii::$app->request->get('dates', '');
			$uname          = \Yii::$app->request->get('uname', '');
			$uname          = trim($uname);

			if (empty($this->adminUser)) {
				$this->dexit(['error' => 1, 'msg' => '帐号数据错误！']);
			}
			if (in_array($this->adminUser->type, [1, 2])) {
				//代理商登录
				$aid       = $this->adminUser->id;
				$agentData = Agent::find()->andWhere(['uid' => $aid])->select('uid, aname')->asArray()->all();
			} else {
				$agentData = Agent::find()->andWhere(['is_contract' => 1])->select('uid, aname')->asArray()->all();
			}
			$agentD = [];
			foreach ($agentData as $v) {
				$agentD[$v['uid']] = $v['aname'];
			}

			$agentOrder = AgentOrder::find();

			$employeeRoleId = 0;
			if ($aid) {
				$agentOrder = $agentOrder->andWhere(['agent_uid' => $aid]);
				//代理商员工登录
				if ($this->eid) {
					$employeeInfo   = AdminUserEmployee::findOne($this->eid);
					$employeeRoleId = $employeeInfo->role_id;
					if ($employeeRoleId == 1) {
						$agentOrder = $agentOrder->andWhere(['eid' => $this->eid]);
					}
				}
			} else {
				if (!empty($searchProvince)) {
					$agentData = Agent::find()->andWhere(['is_contract' => 1])->andWhere(['province' => $searchProvince]);
					if (!empty($searchCity)) {
						$agentData = $agentData->andWhere(['city' => $searchCity]);
					}
					$agentData = $agentData->asArray()->all();

					$agentIds = [];
					foreach ($agentData as $v) {
						array_push($agentIds, $v['uid']);
					}
					if ($agentIds) {
						$agentOrder = $agentOrder->andWhere(['agent_uid' => $agentIds]);
					} else {
						$agentOrder = $agentOrder->andWhere(['agent_uid' => 0]);//无数据
					}
				}
			}

			if ($searchStatus) {
				$agentOrder = $agentOrder->andWhere(['status' => $searchStatus]);
			}

			if ($searchType) {
				$agentOrder = $agentOrder->andWhere(['type' => $searchType]);
			}

			//时间
			if (!empty($dates)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';
				$agentOrder = $agentOrder->andWhere(['or', ['between', 'create_time', strtotime($start_date), strtotime($end_date)], ['between', 'pass_time', strtotime($start_date), strtotime($end_date)]]);
			}
			//商户账号/名称
			if ($uname) {
				$user = User::find()->alias('u');
				$user = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
				$user = $user->andWhere(['or', ['like', 'u.account', $uname], ['like', 'up.company_name', $uname]])->select('u.uid')->all();

				$userIds = [];
				foreach ($user as $v) {
					array_push($userIds, $v->uid);
				}

				if ($userIds) {
					$agentOrder = $agentOrder->andWhere(['uid' => $userIds]);
				} else {
					$agentOrder = $agentOrder->andWhere(['uid' => 0]);//无数据
				}
			}

			$count      = $agentOrder->count();
			$pages      = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$agentOrder = $agentOrder->offset($pages->offset)->limit($pages->limit)->orderBy('id desc')->asArray()->all();

			//套餐
			$packageList = Package::getAllPackageInfo();
			$packageD    = [];
			foreach ($packageList as $k => $package) {
				$packageD[$package['id']] = $package['name'];
			}

			foreach ($agentOrder as $k => $v) {
				$user                          = User::find()->alias('u');
				$user                          = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
				$user                          = $user->andWhere(['u.uid' => $v['uid']])->select('u.account,up.company_name')->one();
				$agentOrder[$k]['account']     = isset($user['account']) ? $user['account'] : '--';
				$agentOrder[$k]['companyName'] = isset($user['company_name']) ? $user['company_name'] : '--';

				$agentOrder[$k]['agentName'] = isset($agentD[$v['agent_uid']]) ? $agentD[$v['agent_uid']] : '--';

				$agentOrder[$k]['packageName'] = isset($packageD[$v['package_id']]) ? $packageD[$v['package_id']] : '--';
				$time_type                     = '日';
				if ($v['time_type'] == 2) {
					$time_type = '月';
				} elseif ($v['time_type'] == 3) {
					$time_type = '年';
				}
				$agentOrder[$k]['packageName'] .= '（' . $v['package_time'] . $time_type . '）';

				$agentOrder[$k]['discountStr'] = $v['discount'] < 10 ? $v['discount'] * 10 . '折' : '无折扣';

				$typeName = '--';
				if ($v['type'] == 1) {
					$typeName = '新开';
				} elseif ($v['type'] == 2) {
					$typeName = '延期';
				} elseif ($v['type'] == 3) {
					$typeName = '升级';
				} elseif ($v['type'] == 4) {
					$typeName = '降级';
				} elseif ($v['type'] == 5) {
					$typeName = '重新入驻';
				}
				$agentOrder[$k]['typeName'] = $typeName;
				$statusName                 = '--';
				if ($v['status'] == 1) {
					$statusName = '未审核';
				} elseif ($v['status'] == 2) {
					$statusName = '已审核';
				} elseif ($v['status'] == 3) {
					$statusName = '已撤销';
				}
				$agentOrder[$k]['statusName'] = $statusName;
			}

			$returnData = [
				'agentData'      => $agentData,
				'agentOrder'     => $agentOrder,
				'snum'           => $count,
				'pages'          => $pages,
				'aid'            => $aid,
				'searchProvince' => $searchProvince,
				'searchCity'     => $searchCity,
				'searchStatus'   => $searchStatus,
				'searchType'     => $searchType,
				'dates'          => $dates,
				'uname'          => $uname,
				'eid'            => $this->eid,
				'employeeRoleId' => $employeeRoleId,
			];

			return $this->render('agentBill', $returnData);
		}

		/**
		 * 提单审核/撤销
		 */
		public function actionSetBillStatus ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post   = \Yii::$app->request->post();
			$oid    = !empty($post['oid']) ? $post['oid'] : '';
			$status = !empty($post['status']) ? $post['status'] : 0;

			$agentOrder = AgentOrder::findOne($oid);
			if (empty($agentOrder)) {
				$this->dexit(['error' => 1, 'msg' => '提单数据错误']);
			}
			if ($agentOrder->status == 2){
				$this->dexit(['error' => 1, 'msg' => '提单已审核，请刷新页面']);
			}
			if (!in_array($status, [2, 3])) {
				$this->dexit(['error' => 1, 'msg' => '提单状态数据错误']);
			}

			$transaction = \Yii::$app->db->beginTransaction();
			try {
				if ($status == 2) {
					$agent = Agent::findOne(['uid' => $this->adminUser->id]);
					if ($agent->balance < $agentOrder->money){
						throw new InvalidDataException('代理商服务点数不足，不能提单');
					}

					//点数明细 扣点数
					$balanceData                  = [];
					$balanceData['uid']           = $this->adminUser->id;
					$balanceData['balance']       = $agentOrder->money;
					$balanceData['type']          = 0;
					$balanceData['blance_type']   = 2;
					$balanceData['order_id']      = $agentOrder->id;
					$balanceData['operator_type'] = 3;
					if ($agentOrder->type == 1) {
						$des = '新开商户扣款';
					} elseif ($agentOrder->type == 2) {
						$des = '商户延期扣款';
					} elseif ($agentOrder->type == 3) {
						$des = '商户升级扣款';
					} elseif ($agentOrder->type == 5) {
						$des = '商户入驻扣款';
					}
					$balanceData['des']           = $des;

					if (AgentBalance::create($balanceData)) {
						$agent->balance -= $agentOrder->money;
						if (!$agent->save()){
							throw new InvalidDataException('代理商扣款失败');
						}
					}

					$res = User::setUserMerchant($agentOrder->uid, $agentOrder);
					if (!empty($res) && $res['error'] == 0) {
						$agentOrder->status    = $status;
						$agentOrder->end_time  = $res['end_time'];
						$agentOrder->pass_time = time();
					} else {
						throw new InvalidDataException('客户入驻失败');
					}
				} else {
					$agentOrder->status = $status;
				}

				if ($agentOrder->save()) {
					$transaction->commit();
					$this->dexit(['error' => 0, 'msg' => '']);
				} else {
					throw new InvalidDataException('设置失败');
				}
			} catch (InvalidDataException $e) {
				$transaction->rollBack();
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 客户资料审核页面
		 */
		public function actionUserCheck ()
		{
			$uName  = \Yii::$app->request->get('uname', '');
			$status = \Yii::$app->request->get('searchStatus', 0);

			$user = User::find()->alias('u');
			$user = $user->leftJoin('{{%user_application}} ua', '`u`.`uid` = `ua`.`uid`');
			$user = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
			$user = $user->andWhere(['>', 'u.application_status', 0]);

			//代理商登录
			if ($this->adminUser->type != 0){
				$user = $user->andWhere(['u.agent_uid' => $this->adminUser->id]);
			}
			//员工登录
			$employeeRoleId = 0;
			if ($this->eid) {
				$employeeInfo   = AdminUserEmployee::findOne($this->eid);
				$employeeRoleId = $employeeInfo->role_id;
				if ($this->adminUser->type == 0 || $employeeRoleId == 1) {
					$user = $user->andWhere(['u.eid' => $this->eid]);
				}
			}

			//用户账号/名称
			if (!empty($uName)) {
				$user = $user->andWhere(['or', ['like', 'u.account', $uName], ['like', 'up.company_name', $uName]]);
			}
			//状态
			if ($status) {
				$user = $user->andWhere(['u.application_status' => $status]);
			}

			$count = $user->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$user  = $user->select('u.uid,u.account,u.agent_uid,u.application_status,ua.id appli_id,ua.addtime,up.province,up.city,up.company_name companyName')->offset($pages->offset)->limit($pages->limit)->orderBy('ua.id desc')->asArray()->all();

			//代理商
			$agentData = Agent::find()->andWhere(['is_contract' => 1])->select('uid, aname')->asArray()->all();
			$agentD    = [];
			foreach ($agentData as $v) {
				$agentD[$v['uid']] = $v['aname'];
			}
			//省市信息
			$area  = Area::find()->andWhere(['in', 'level', [1, 2]])->select('id, full_name')->asArray()->all();
			$areaD = [];
			foreach ($area as $v) {
				$areaD[$v['id']] = $v['full_name'];
			}

			foreach ($user as $k => $v) {
				$user[$k]['addtime']     = $v['addtime'] ? date('Y-m-d H:i', $v['addtime']) : '--';
				$user[$k]['companyName'] = $v['companyName'] ? $v['companyName'] : '--';

				if ($v['agent_uid'] == 0) {
					$agentName = '总后台';
				} else {
					$agentName = isset($agentD[$v['agent_uid']]) ? $agentD[$v['agent_uid']] : '--';
				}
				$user[$k]['agentName'] = $agentName;
				$statusName            = '--';
				if ($v['application_status'] == 1) {
					$statusName = '已提交未审核';
				} elseif ($v['application_status'] == 2) {
					$statusName = '通过审核';
				} elseif ($v['application_status'] == 3) {
					$statusName = '审核失败';
				}
				$user[$k]['statusName'] = $statusName;

				$user[$k]['province'] = !empty($v['province']) && isset($areaD[$v['province']]) ? $areaD[$v['province']] : '/';
				$user[$k]['city']     = !empty($v['city']) && isset($areaD[$v['city']]) ? $areaD[$v['city']] : '/';
			}

			$returnData = [
				'userArr'        => $user,
				'snum'           => $count,
				'pages'          => $pages,
				'uName'          => $uName,
				'status'         => $status,
				'eid'            => $this->eid,
				'employeeRoleId' => $employeeRoleId,
			];

			return $this->render('userCheck', $returnData);
		}

		/**
		 * 获取未提交资料客户
		 */
		public function actionGetUserNotApplication ()
		{
			$post   = \Yii::$app->request->post();
			$uName = !empty($post['search_user']) ? $post['search_user'] : 0;

			$user = User::find()->alias('u');
			$user = $user->leftJoin('{{%user_profile}} up', '`u`.`uid` = `up`.`uid`');
			$user = $user->andWhere(['u.application_status' => 0]);

			//代理商登录
			if ($this->adminUser->type != 0){
				$user = $user->andWhere(['u.agent_uid' => $this->adminUser->id]);
			}else{
				//$this->dexit(['error' => 1, 'msg' => '代理商数据错误']);
			}
			//员工登录
			if ($this->eid){
				$user = $user->andWhere(['u.eid' => $this->eid]);
			}
			//用户账号/名称
			if (!empty($uName)) {
				$user = $user->andWhere(['or', ['like', 'u.account', $uName], ['like', 'up.company_name', $uName]]);
			}

			$count = $user->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$user  = $user->select('u.uid,u.account,up.company_name companyName')->offset($pages->offset)->limit($pages->limit)->orderBy('u.uid desc')->asArray()->all();

			$html = '';
			foreach ($user as $v){
				$html .= '<tr>';
				$html .= '<td><div class=""><label><input type="radio" value="' . $v['uid'] . '" name="uid" class="uid"> <i></i></label></div></td>';
				$html .= '<td>' . ($v['companyName'] ? $v['companyName'] : "--") . '</td>';
				$html .= '<td>' . $v['account'] . '</td>';
				$html .= '</tr>';
			}

			if ($html) {
				$pages = LinkPager::widget([
					'pagination' => $pages,
				]);
				$this->dexit(['error' => 0, 'html' => $html, 'pageBar' => $pages]);
			} else {
				$this->dexit(['error' => 1, 'msg' => '暂无数据']);
			}
		}

		/**
		 * 客户资料提交
		 */
		public function actionUserApplicationPost ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post = \Yii::$app->request->post();
			$uid  = !empty($post['uid']) ? $post['uid'] : 0;
			if (empty($uid)) {
				$this->dexit(['error' => 1, 'msg' => '客户参数错误']);
			}
			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}

			if (!preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $post['id_number'])) {
				$this->dexit(['error' => 1, 'msg' => '请输入正确的手机号']);
			}

			$userApplication = UserApplication::findOne(['uid' => $uid]);

			if (empty($userApplication)) {
				$userApplication          = new UserApplication();
				$userApplication->uid     = $uid;
				$userApplication->addtime = time();
			}

			$userApplication->merchant        = !empty($post['merchant']) ? $post['merchant'] : '';
			$userApplication->license         = !empty($post['license']) ? $post['license'] : '';
			$userApplication->license_cp      = !empty($post['license_cp']) ? $post['license_cp'] : '';
			$userApplication->organization_cp = !empty($post['organization_cp']) ? $post['organization_cp'] : '';
			$userApplication->possessor_type  = !empty($post['possessor_type']) ? $post['possessor_type'] : '';
			$userApplication->possessor       = !empty($post['possessor']) ? $post['possessor'] : '';
			$userApplication->id_number       = !empty($post['id_number']) ? $post['id_number'] : '';
			$userApplication->id_cp_a         = !empty($post['id_cp_a']) ? $post['id_cp_a'] : '';
			$userApplication->id_cp_b         = !empty($post['id_cp_b']) ? $post['id_cp_b'] : '';
			$userApplication->id_cp_c         = !empty($post['id_cp_c']) ? $post['id_cp_c'] : '';
			$userApplication->status          = 1;

			if ($userApplication->save()) {
				$user->application_status = 1;
				$user->save();
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '提交失败']);
			}
		}

		/**
		 * 客户资料详情
		 */
		public function actionUserApplicationInfo ()
		{
			$isPost = 0;
			if (\Yii::$app->request->isPost) {
				$isPost = 1;
				$uid    = \Yii::$app->request->post('uid', 0);
			} elseif (\Yii::$app->request->isGet) {
				$uid = \Yii::$app->request->get('uid', 0);
			}

			if (empty($uid)) {
				$this->dexit(['error' => 1, 'msg' => '客户参数错误']);
			}
			$userApplication = UserApplication::find()->where(['uid' => $uid])->asArray()->one();
			if (empty($userApplication)) {
				$this->dexit(['error' => 1, 'msg' => '客户资料数据错误']);
			}

			if ($isPost) {
				$this->dexit(['error' => 0, 'msg' => $userApplication]);
			} else {
				return $this->render('userApplicationInfo', ['userInfo' => $userApplication]);
			}
		}

		/**
		 * 客户资料审核
		 */
		public function actionUserApplicationCheck ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post   = \Yii::$app->request->post();
			$uid    = !empty($post['uid']) ? $post['uid'] : 0;
			$status = !empty($post['customer_status']) ? $post['customer_status'] : 0;
			$remark = !empty($post['remark']) ? $post['remark'] : '';

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			if (!in_array($status, [2, 3])) {
				$this->dexit(['error' => 1, 'msg' => '状态数据错误']);
			}
			$userApplication = UserApplication::findOne(['uid' => $uid]);
			if (empty($userApplication)) {
				$this->dexit(['error' => 1, 'msg' => '客户资料数据错误']);
			}

			$userApplication->status = $status;
			$userApplication->remark = $remark;
			if ($status == 2) {
				$userApplication->pass_time = time();
			}

			if ($userApplication->save()) {
				$user->application_status = $status;
				$user->save();
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '设置失败']);
			}
		}

		/**
		 * 用户分配员工
		 */
		public function actionSetUserEmployee ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post     = \Yii::$app->request->post();
			$userIds  = !empty($post['userIds']) ? $post['userIds'] : [];
			$employid = !empty($post['employid']) ? $post['employid'] : 0;

			if (empty($userIds)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			if (empty($employid)) {
				$this->dexit(['error' => 1, 'msg' => '员工数据错误']);
			}

			$uptData['eid'] = $employid;
			if ($this->adminUser->type == 0) {
				$uptData['agent_uid'] = 0;
			}
			User::updateAll($uptData, ['uid' => $userIds]);

			$this->dexit(['error' => 0, 'msg' => '']);
		}

		/**
		 * 代理商列表
		 */
		public function actionGetAgent ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}

			$agent = Agent::find()->alias('a');
			$agent = $agent->leftJoin('{{%admin_user}} au', '`a`.`uid` = `au`.`id`');
			$agent = $agent->andWhere(['a.is_contract' => 1, 'au.status' => 1])->andWhere(['>', 'endtime', time()])->select('a.uid,a.aname')->asArray()->all();

			$this->dexit(['error' => 0, 'msg' => 'ok', 'data' => $agent]);
		}

		/**
		 * 用户分配代理商
		 */
		public function actionSetUserAgent ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post    = \Yii::$app->request->post();
			$userIds = !empty($post['userIds']) ? $post['userIds'] : [];
			$agentid = !empty($post['agentid']) ? $post['agentid'] : 0;

			if (empty($userIds)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			if (empty($agentid)) {
				$this->dexit(['error' => 1, 'msg' => '代理商数据错误']);
			}

			User::updateAll(['agent_uid' => $agentid, 'eid' => 0], ['uid' => $userIds]);

			$this->dexit(['error' => 0, 'msg' => '']);
		}

		/**
		 * 获取客户可升级套餐
		 */
		public function actionGetUpgradePackage ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post = \Yii::$app->request->post();
			$uid  = !empty($post['uid']) ? $post['uid'] : 0;

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			$userPackage = Package::findOne($user->package_id);

			//套餐
			$isAgent     = $this->adminUser->type != 0 ? 1 : 0;
			$packageList = Package::getAllPackageInfo($isAgent);
			$packageUp   = [];
			foreach ($packageList as $k => $package) {
				if ($package['sort'] > $userPackage->sort) {
					array_push($packageUp, $package);
				}
			}

			if ($packageUp){
				$this->dexit(['error' => 0, 'msg' => 'ok', 'data' => $packageUp]);
			}else{
				$this->dexit(['error' => 1, 'msg' => '没有可升级的套餐']);
			}
		}

		/**
		 * 客户套餐升级
		 */
		public function actionSetUserPackageUp ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post       = \Yii::$app->request->post();
			$uid        = !empty($post['uid']) ? $post['uid'] : '';
			$package_id = !empty($post['package_id']) ? $post['package_id'] : '0';

			if (empty($this->adminUser)) {
				$this->dexit(['error' => 1, 'msg' => '帐号数据错误']);
			}
			if (empty($uid) || empty($package_id)) {
				$this->dexit(['error' => 1, 'msg' => '缺少必要参数']);
			}

			$user = User::findOne($uid);
			if (empty($user)) {
				$this->dexit(['error' => 1, 'msg' => '客户数据错误']);
			}
			if ($user->end_time <= time()) {
				$this->dexit(['error' => 1, 'msg' => '套餐已到期，不能升级']);
			}

			$package = Package::findOne($package_id);
			if (empty($package)) {
				$this->dexit(['error' => 1, 'msg' => '套餐数据错误']);
			}
			if ($package->status != 1) {
				$this->dexit(['error' => 1, 'msg' => '提交失败，套餐已删除']);
			}
			//套餐档位
			$packageTime = json_decode($package->priceJson, true);

			//现套餐日价格及剩余时长
			$userAgentOrder = AgentOrder::find()->andWhere(['uid' => $uid, 'status' => 2])->andWhere(['>', 'end_time', time()])->orderBy(['id' => SORT_DESC])->asArray()->all();
			$money          = 0;
			$days           = 0;
			foreach ($userAgentOrder as $v) {
				$money += $v['original_price'] * $v['discount'];
				if ($v['time_type'] == 1) {
					$days += $v['package_time'];
				} elseif ($v['time_type'] == 2) {
					$days += $v['package_time'] * 30;
				} elseif ($v['time_type'] == 3) {
					$days += $v['package_time'] * 365;
				}

				if ($v['type'] == 3) {
					break;
				}
			}

			$daysLeft = floor(($user->end_time - time()) / 86400);
			$days     = $daysLeft > $days ? $daysLeft : $days;

			$moneyPer = $days > 0 ? sprintf('%.2f', $money / $days) : 0;

			//升级套餐日价格
			foreach ($packageTime as $k => $v) {
				if ($v['timeType'] == 1) {
					$packageTime[$k]['days'] = $v['timeNum'];
				} elseif ($v['timeType'] == 2) {
					$packageTime[$k]['days'] = $v['timeNum'] * 30;
				} elseif ($v['timeType'] == 3) {
					$packageTime[$k]['days'] = $v['timeNum'] * 365;
				}
			}
			$sort_names = array_column($packageTime, 'days');
			array_multisort($sort_names, SORT_DESC, $packageTime);
			$nowPrice = 0;
			$nowDays  = 0;
			foreach ($packageTime as $v) {
				if ($daysLeft >= $v['days']) {
					$nowPrice = $v['nowPrice'];
					$nowDays  = $v['days'];
					break;
				}
			}
			if ($nowDays == 0) {
				$end      = end($packageTime);
				$nowPrice = $end['nowPrice'];
				$nowDays  = $end['days'];
			}
			$nowMoneyPer = $nowDays > 0 ? sprintf('%.2f', $nowPrice / $nowDays) : 0;

			try {
				if ($this->adminUser->type == 0) {
					$messageOrder              = new MessageOrder();
					$messageOrder->uid         = $uid;
					$messageOrder->order_id    = '';
					$messageOrder->pay_way     = 'weixin';
					$messageOrder->pay_type    = 'wxsaoma2pay';
					$messageOrder->goods_type  = 'packageBuy';
					$messageOrder->goods_id    = $package->id;
					$messageOrder->goods_name  = $package->name . '（' . $daysLeft . '日）';
					$messageOrder->goods_price = ceil($nowMoneyPer * $daysLeft);
					$messageOrder->add_time    = DateUtil::getCurrentTime();
					$messageOrder->paytime     = DateUtil::getCurrentTime();
					$messageOrder->ispay       = 1;
					$messageOrder->extrainfo   = '';

					if (!$messageOrder->validate() || !$messageOrder->save()) {
						$this->dexit(['error' => 1, 'msg' => SUtils::modelError($messageOrder)]);
					}

					$user->package_id   = $package->id;
					$user->package_time = $daysLeft;
					$user->time_type    = 1;

					if ($user->save()) {
						$this->dexit(['error' => 0, 'msg' => '']);
					} else {
						$this->dexit(['error' => 1, 'msg' => '入驻失败']);
					}
				} else {
					//应补差价
					$agent     = Agent::findOne(['uid' => $this->adminUser->id]);
					$leftMoney = ($nowMoneyPer * $agent->discount - $moneyPer) * $daysLeft;
					$leftMoney = $leftMoney > 0 ? $leftMoney : 0;

					//代理商提单
					$orderData                   = [];
					$orderData['agent_uid']      = $this->adminUser->id;
					$orderData['eid']            = $this->eid;
					$orderData['uid']            = $uid;
					$orderData['agent_type']     = 1;
					$orderData['type']           = 3;
					$orderData['money']          = ceil($leftMoney);
					$orderData['original_price'] = ceil($nowMoneyPer * $daysLeft);
					$orderData['status']         = 1;
					$orderData['package_id']     = $package->id;
					$orderData['package_time']   = $daysLeft;
					$orderData['time_type']      = 1;
					$orderData['extrainfo']      = '';

					if (AgentOrder::create($orderData)) {
						$this->dexit(['error' => 0, 'msg' => '']);
					}
				}

			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}

		}

		public function actionGetMsgAudit ()
		{
			if (Yii::$app->request->isGet) {
				return Json::encode(["error" => 1, "msg" => "请求方式不正确"], JSON_UNESCAPED_UNICODE);

			}
			$id   = Yii::$app->request->post("id");
			$user = User::findOne(["uid" => $id]);
			$data = [];
			if (!empty($user)) {
				$corpRelations = $user->userCorpRelations;
				if (!empty($corpRelations)) {
					$heardStr = '';
					$bodyStr  = '';
					$one      = true;
					foreach ($corpRelations as $corpRelation) {
						$msgAudit = $corpRelation->corp->workMsgAudit;
						if (!empty($msgAudit)) {
							$corp                  = WorkCorp::findOne($msgAudit->corp_id);
							$oneclass              = $one ? 'active' : '';
							$heardStr              .= '<li role="presentation" style="border-left: 4px solid #FFFFFF; border-bottom: 1px solid #f8fafb;" class="' . $oneclass . '">
							<a href="#home_' . $corpRelation->id . '" style="width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color:black;background-color: #FFFFFF !important;" aria-controls="home_' . $corpRelation->id . '" role="tab" data-toggle="tab">' . $corp->corp_name . '</a></li>';
							$temp                  = [];
							$temp["credit_code"]   = $msgAudit->credit_code;
							$temp["contact_user"]  = $msgAudit->contact_user;
							$temp["contact_phone"] = $msgAudit->contact_phone;
							$bodyStr.='<div role="tabpanel" class="tab-pane '.$oneclass.'" id="home_' . $corpRelation->id . '" style=""><form class="form-horizontal" id="audit_id">';
							switch ($msgAudit->status) {
								case 0:
									$bodyStr.= '<div class="form-group" style="text-align: center; margin-top: 10px;">
												<h3 style="color: orange;"> 待审核</h3>
											</div>';
									break;
								case 1:
									$bodyStr.= '<div class="form-group" style="text-align: center; margin-top: 10px;">
												<h3 style="color: #44b549;"> 通过</h3>
											</div>';
									break;
								default:
									$bodyStr.= '<div class="form-group" style="text-align: center; margin-top: 10px;">
												<h3 style="color: #FF562D;"> 未通过</h3>
											</div>';
									break;
							}
							foreach ($temp as $key => $value){
								switch ($key) {
									case 'credit_code':
										$name = "统一征信代码";
										break;
									case 'contact_user':
										$name = "接口联系人";
										break;
									default:
										$name = "接口联系人方式";
										break;
								}
								$bodyStr .= '<div class="form-group">
												<label for="inputEmail3" class="col-sm-3 control-label">'.$name.'</label>
												<div class="col-sm-9">
													<input type="text" msg-name="'.$key.'" disabled style="width: 75%;display: inline-block;" class="form-control" value="'.$value.'" >
													<a href="javascript:0;" style="display: inline-block;" onclick="eidtDataMsg('.$msgAudit->id.',this)">修改</a>
												</div>
											</div>';
							}
							$bodyStr .= '<div class="form-group">
												<div class="col-sm-12" style="text-align: center;">
														<a type="button" onclick="changeMsgAudt('.$msgAudit->id.',-1)" class="btn btn-danger">拒绝</a>
														<a type="button" onclick="changeMsgAudt('.$msgAudit->id.',1)" class="btn btn-primary">通过</a>
												</div>
											</div>';
							$bodyStr.='</form></div>';
							$one = false;
						}
					}
					if(!empty($heardStr) && !empty($bodyStr)){
						$data["heard_str"] = $heardStr;
						$data["body_str"] = $bodyStr;
					}
				}
			}
			return Json::encode($data, JSON_UNESCAPED_UNICODE);

		}

		public function actionSaveMsgAudit ()
		{
			$id    = Yii::$app->request->post("id");
			$key   = Yii::$app->request->post("key");
			$value = Yii::$app->request->post("value");
			$audit = WorkMsgAudit::findOne($id);
			if((!$audit->credit_code || !$audit->contact_user || !$audit->contact_phone) && $key=="status" && $value == 1){
				return Json::encode(['error' => 1,'msg'=>"审核参数不完整无法通过！"], JSON_UNESCAPED_UNICODE);
			}
			WorkMsgAudit::updateAll([$key => $value], ["id" => $id]);
			if ($key == "status" && $value == -1) {
				WorkMsgAudit::updateAll(["credit_code" => NULL, 'contact_user' => NULL, "contact_phone" => NULL], ["id" => $id]);
			}

			return Json::encode(['error' => 0,'msg'=>"修改完成！"], JSON_UNESCAPED_UNICODE);
		}
	}