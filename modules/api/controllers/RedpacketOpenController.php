<?php
	/**
	 * 客户领取红包
	 * User: fulu
	 * Date: 2020/10/17
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\MoneyOrder;
	use app\models\RedPackChatSendRule;
	use app\models\RedPackRule;
	use app\models\WorkChatInfo;
	use app\models\WorkContactWayRedpacket;
	use app\models\WorkContactWayRedpacketSend;
	use app\models\WorkGroupSending;
	use app\models\WorkGroupSendingRedpacketSend;
	use app\models\WorkChat;
	use app\models\WorkExternalContact;
	use app\models\WorkUser;
	use app\modules\api\components\BaseController;
	use app\models\WorkCorp;
	use yii\helpers\Json;

	class RedpacketOpenController extends BaseController
	{
		/************************************** 红包拉新 ****************************************/
		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           红包拉新领取红包页面
		 * @description     红包拉新领取红包页面
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/redpacket-index
		 *
		 * @param corp_id   必选 string 企业微信id
		 * @param assist    必选 string 附带参数
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    send_id int 发放id
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 红包感谢语
		 * @return_param    canOpen int 红包是否可开启1是0否
		 * @return_param    isOpen int 红包是否已领取1是0否
		 * @return_param    msg string 红包开启提示
		 * @return_param    timeType int 活动时间类型1永久有效2时间区间
		 * @return_param    timeData array 活动倒计时
		 * @return_param    timeData.day string 活动倒计时-天
		 * @return_param    timeData.hour string 活动倒计时-时
		 * @return_param    timeData.minutes string 活动倒计时-分
		 * @return_param    timeData.seconds string 活动倒计时-秒
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionRedpacketIndex ()
		{
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist') ?: '';
			$external_userid = \Yii::$app->request->post('external_id') ?: '';

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($external_userid)) {
				throw new InvalidParameterException('员工自己无法领取红包！');
			}

			$stateArr = explode('_', $assist);

			if ($stateArr['0'] != WorkContactWayRedpacket::REDPACKET_WAY) {
				throw new InvalidParameterException('附带参数不正确！');
			}

			$way_id      = $stateArr['1'];
			$send_id     = $stateArr['2'];
			$external_id = $stateArr['3'];

			$sendVerify = WorkContactWayRedpacketSend::verifyRedpacketSend($way_id, $send_id, $external_id);

			$resData              = [];
			$resData['send_id']   = $send_id;
			$resData['corp_logo'] = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user            = WorkUser::findOne($sendVerify['redpacketSend']->user_id);
			$resData['avatar']    = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']      = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']  = !empty($sendVerify['redRule']['thanking']) ? $sendVerify['redRule']['thanking'] : '';
			$resData['canOpen']   = $sendVerify['canOpen'] ? $sendVerify['canOpen'] : 0;
			$resData['isOpen']    = $sendVerify['isOpen'] ? $sendVerify['isOpen'] : 0;
			$resData['msg']       = $sendVerify['msg'] ? $sendVerify['msg'] : '';

			$wayRedpacket        = WorkContactWayRedpacket::findOne($way_id);
			$resData['act_name'] = $wayRedpacket->name;

			//活动倒计时
			$timeData = [
				'day'     => '00',
				'hour'    => '00',
				'minutes' => '00',
				'seconds' => '00',
			];
			/*if ($wayRedpacket->time_type == 2) {
				$time     = time();
				$end_time = strtotime($wayRedpacket->end_time);
				if ($end_time > $time) {
					$timestamp           = $end_time - $time;
					$timeData['day']     = floor($timestamp / (3600 * 24));
					$timeData['hour']    = floor(($timestamp % (3600 * 24)) / 3600);
					$timeData['minutes'] = floor(($timestamp % 3600) / 60);
					$timeData['seconds'] = floor($timestamp % 60);
					$timeData['day']     = ($timeData['day'] >= 10) ? (string) $timeData['day'] : '0' . $timeData['day'];
					$timeData['hour']    = ($timeData['hour'] >= 10) ? (string) $timeData['hour'] : '0' . $timeData['hour'];
					$timeData['minutes'] = ($timeData['minutes'] >= 10) ? (string) $timeData['minutes'] : '0' . $timeData['minutes'];
					$timeData['seconds'] = ($timeData['seconds'] >= 10) ? (string) $timeData['seconds'] : '0' . $timeData['seconds'];
				}
			}*/
			$resData['timeType'] = $wayRedpacket->time_type;
			$resData['timeData'] = $timeData;

			return ['resData' => $resData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           红包拉新打开红包
		 * @description     红包拉新打开红包
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/redpacket-open
		 *
		 * @param send_id       必选 int 发放id
		 * @param external_id   必选 string 客户userid
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionRedpacketOpen ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}
			$send_id         = \Yii::$app->request->post('send_id', 0);//发放id
			$external_userid = \Yii::$app->request->post('external_id', '');

			if (empty($send_id) || empty($external_userid)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$redpacketSend = WorkContactWayRedpacketSend::findOne($send_id);

			$externalInfo = WorkExternalContact::findOne(['corp_id' => $redpacketSend->corp_id, 'external_userid' => $external_userid]);

			if ($externalInfo->id != $redpacketSend->external_userid) {
				throw new InvalidParameterException('非客户本人，不能领取红包！');
			}

			$sendVerify = WorkContactWayRedpacketSend::verifyRedpacketSend($redpacketSend->way_id, $send_id, $redpacketSend->external_userid);

			if (empty($sendVerify)) {
				throw new InvalidParameterException('发放失败！');
			}
			if ($sendVerify['canOpen'] == 0){
				throw new InvalidParameterException($sendVerify['msg']);
			}

			//发红包
			$thanking = !empty($sendVerify['redRule']['thanking']) ? $sendVerify['redRule']['thanking'] : '';
			WorkContactWayRedpacketSend::redpacketSend($send_id, $thanking);

			return ['send_id' => $send_id];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           红包拉新获取红包领取列表
		 * @description     红包拉新获取红包领取列表
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/redpacket-receive
		 *
		 * @param corp_id      必选 string 企业微信id
		 * @param send_id      必选 int 发放id
		 * @param page         可选 int 页码
		 * @param page_size    可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    send_id int 发放id
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 感谢语
		 * @return_param    send_money string 领取金额
		 * @return_param    redpacket_amount_sum string 总发放金额
		 * @return_param    send_amount_sum string 总领取金额
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据信息
		 * @return_param    list.name_convert string 客户姓名
		 * @return_param    list.avatar string 客户头像
		 * @return_param    list.gender string 客户性别
		 * @return_param    list.send_time string 领取时间
		 * @return_param    list.send_money string 领取金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionRedpacketReceive ()
		{
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$send_id  = \Yii::$app->request->post('send_id', 0);//发放id
			$assist   = \Yii::$app->request->post('assist', '');
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 15;

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$isUser = 0;
			if (empty($send_id) && !empty($assist)) {
				$isUser   = 1;
				$stateArr = explode('_', $assist);
				$send_id  = $stateArr['2'];
			}

			if (empty($send_id)){
				throw new InvalidParameterException('红包参数不正确！');
			}

			$redpacketSend = WorkContactWayRedpacketSend::findOne($send_id);
			$wayRedpacket  = WorkContactWayRedpacket::findOne($redpacketSend->way_id);
			if ($wayRedpacket->rule_id > 0) {
				$redRule = RedPackRule::find()->andWhere(['id' => $wayRedpacket->rule_id])->asArray()->one();
			} else {
				$redRule = Json::decode($wayRedpacket->rule_text);
			}

			$resData                         = [];
			$resData['send_id']              = $send_id;
			$resData['corp_logo']            = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user                       = WorkUser::findOne($redpacketSend->user_id);
			$resData['avatar']               = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']                 = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']             = !empty($redRule['thanking']) ? $redRule['thanking'] : '恭喜发财，大吉大利';
			$resData['send_money']           = $redpacketSend->status == 1 || $isUser == 1 ? $redpacketSend->send_money : '0.00';
			$resData['redpacket_amount_sum'] = $wayRedpacket->redpacket_amount;
			$resData['send_amount_sum']      = $wayRedpacket->send_amount;
			$resData['rule_type']            = $redRule['type'];
			$resData['rule_type']            = $redpacketSend->rule_type;
			$resData['is_expired']           = time() - $redpacketSend->create_time > 86400 ? 1 : 0;
			$resData['act_name']             = $wayRedpacket->name;

			//列表数据
			$sendList = WorkContactWayRedpacketSend::find()->alias('rs');
			$sendList = $sendList->leftJoin('{{%work_external_contact}} we', 'we.id=rs.external_userid');
			$sendList = $sendList->andWhere(['rs.id' => $send_id, 'rs.status' => 1]);
			$count    = $sendList->count();
			$offset   = ($page - 1) * $pageSize;
			$sendList = $sendList->limit($pageSize)->offset($offset);
			$sendList = $sendList->select('rs.send_time,rs.send_money,we.name_convert,we.gender,we.avatar')->orderBy(['rs.send_time' => SORT_DESC])->asArray()->all();
			foreach ($sendList as $k => $v) {
				$sendList[$k]['send_time']  = date('Y-m-d H:i', $v['send_time']);
				$sendList[$k]['send_money'] = sprintf("%.2f", $v['send_money']);
			}
			$resData['count']    = $count;
			$resData['sendList'] = $sendList;

			return $resData;
		}

		/************************************** 员工发红包 ****************************************/
		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           领取红包页面
		 * @description     领取红包页面
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/send-redpacket-index
		 *
		 * @param corp_id            必选 string 企业微信id
		 * @param assist             必选 string 附带参数
		 * @param external_userid    必选 string 客户的userid
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    assist string 附带参数
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 红包感谢语
		 * @return_param    canOpen int 红包是否可开启1是0否
		 * @return_param    isOpen int 红包是否已领取1是0否
		 * @return_param    msg string 红包开启提示
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendRedpacketIndex ()
		{
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if (empty($external_userid) && empty($openid)){
				throw new InvalidParameterException('员工自己无法领取红包！');
			}
			$externalUserData = [];
			if ($external_userid){
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
			}

			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [MoneyOrder::REDPACKET_SEND, MoneyOrder::REDPACKET_CHAT_SEND])){
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];

			if (!empty($externalUserData)){
				$sendVerify = MoneyOrder::verifyRedpacketOpen($corpInfo, $stateArr['0'], $send_id, $externalUserData->id);
			}else{
				$sendVerify = MoneyOrder::verifyRedpacketOpen($corpInfo, $stateArr['0'], $send_id, 0, $openid);
			}

			$resData              = [];
			$resData['assist']    = $assist;
			$resData['corp_logo'] = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user            = WorkUser::findOne($sendVerify['user_id']);
			$resData['avatar']    = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']      = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']  = !empty($sendVerify['des']) ? $sendVerify['des'] : MoneyOrder::REDPACKET_THANKING;
			$resData['canOpen']   = $sendVerify['canOpen'] ? $sendVerify['canOpen'] : 0;
			$resData['isOpen']    = $sendVerify['isOpen'] ? $sendVerify['isOpen'] : 0;
			$resData['msg']       = $sendVerify['msg'] ? $sendVerify['msg'] : '';
			$resData['act_name']  = '员工发红包';

			return ['resData' => $resData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           打开红包
		 * @description     打开红包
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/send-redpacket-open
		 *
		 * @param corp_id            必选 string 企业微信id
		 * @param assist             必选 string 附带参数
		 * @param external_userid    必选 string 客户的userid
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionSendRedpacketOpen ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}

			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if (empty($external_userid) && empty($openid)){
				throw new InvalidParameterException('员工自己无法领取红包！');
			}
			$externalUserData = [];
			if ($external_userid){
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
			}

			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [MoneyOrder::REDPACKET_SEND, MoneyOrder::REDPACKET_CHAT_SEND])){
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];

			if (!empty($externalUserData)){
				$sendVerify = MoneyOrder::verifyRedpacketOpen($corpInfo, $stateArr['0'], $send_id, $externalUserData->id);
			}else{
				$sendVerify = MoneyOrder::verifyRedpacketOpen($corpInfo, $stateArr['0'], $send_id, 0, $openid);
			}

			if (empty($sendVerify)) {
				throw new InvalidParameterException('领取失败！');
			}
			if ($sendVerify['canOpen'] == 0){
				throw new InvalidParameterException($sendVerify['msg']);
			}

			//领取红包
			if (!empty($externalUserData)){
				MoneyOrder::redpacketOpen($stateArr['0'], $send_id, $externalUserData->id, $sendVerify['des']);
			}else{
				MoneyOrder::redpacketOpen($stateArr['0'], $send_id, 0, $sendVerify['des'], $openid);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           获取红包领取列表
		 * @description     获取红包领取列表
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/send-redpacket-receive
		 *
		 * @param corp_id              必选 string 企业微信id
		 * @param assist               必选 string 附带参数
		 * @param external_userid      必选 string 客户id
		 * @param page                 可选 int 页码
		 * @param page_size            可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 感谢语
		 * @return_param    redpacket_amount string 红包金额
		 * @return_param    redpacket_num int 红包个数
		 * @return_param    get_amount int 领取金额
		 * @return_param    get_num int 领取个数
		 * @return_param    rule_type int 红包金额类型：1、固定金额，2、随机金额
		 * @return_param    send_money int 领取金额
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据信息
		 * @return_param    list.name_convert string 客户姓名
		 * @return_param    list.avatar string 客户头像
		 * @return_param    list.gender string 客户性别
		 * @return_param    list.pay_time string 领取时间
		 * @return_param    list.money string 领取金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendRedpacketReceive ()
		{
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$user_id         = \Yii::$app->request->post('user_id', '');
			$page            = \Yii::$app->request->post('page') ?: 1;
			$pageSize        = \Yii::$app->request->post('pageSize') ?: 15;

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$externalUserData = [];
			if ($external_userid) {
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
			}

			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$stateArr = explode('_', $assist);
			if (!in_array($stateArr['0'], [MoneyOrder::REDPACKET_SEND, MoneyOrder::REDPACKET_CHAT_SEND])){
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id    = $stateArr['1'];
			$send_money = 0;

			if ($stateArr['0'] == MoneyOrder::REDPACKET_SEND) {
				$sendExternal = MoneyOrder::findOne(['id' => $send_id]);
				if (empty($sendExternal)) {
					throw new InvalidParameterException('发放参数不正确！');
				}
				$user_id          = $sendExternal->user_id;
				$thanking         = !empty($sendExternal->message) ? $sendExternal->message : MoneyOrder::REDPACKET_THANKING;
				$redpacket_amount = $sendExternal->money;
				$redpacket_num    = 1;
				$get_amount       = $sendExternal->status == 1 ? $sendExternal->money : 0;
				$get_num          = $sendExternal->status == 1 ? 1 : 0;
				$rule_type        = 1;
				$send_money       = $sendExternal->status == 1 ? $sendExternal->money : 0;
				$is_expired       = time() - $sendExternal->send_time > 86400 ? 1 : 0;
			} else {
				$sendRule = RedPackChatSendRule::findOne($send_id);
				if (empty($sendRule)) {
					throw new InvalidParameterException('发放参数不正确！');
				}
				$user_id          = $sendRule->user_id;
				$thanking         = !empty($sendRule->des) ? $sendRule->des : MoneyOrder::REDPACKET_THANKING;
				$redpacket_amount = $sendRule->redpacket_amount;
				$redpacket_num    = $sendRule->redpacket_num;
				$get_amount       = $sendRule->get_amount;
				$get_num          = $sendRule->get_num;
				$rule_type        = $sendRule->type;
				if ($externalUserData) {
					$sendExternal = MoneyOrder::findOne(['corp_id' => $corpInfo->id, 'chat_send_id' => $sendRule->id, 'external_id' => $externalUserData->id]);
					$send_money   = !empty($sendExternal) && $sendExternal->status == 1 ? $sendExternal->money : 0;
				} elseif ($openid){
					$sendExternal = MoneyOrder::findOne(['corp_id' => $corpInfo->id, 'chat_send_id' => $sendRule->id, 'openid' => $openid]);
					$send_money   = !empty($sendExternal) && $sendExternal->status == 1 ? $sendExternal->money : 0;
				}
				$is_expired       = time() - strtotime($sendRule->create_time) > 86400 ? 1 : 0;
			}

			$resData                     = [];
			$resData['send_id']          = $send_id;
			$resData['corp_logo']        = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user                   = WorkUser::findOne($user_id);
			$resData['avatar']           = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']             = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']         = $thanking;
			$resData['redpacket_amount'] = $redpacket_amount;
			$resData['redpacket_num']    = $redpacket_num;
			$resData['get_amount']       = $get_amount;
			$resData['get_num']          = $get_num;
			$resData['rule_type']        = $rule_type;
			$resData['send_money']       = empty($user_id) ? $send_money : 0;
			$resData['is_expired']       = $is_expired;
			$resData['act_name']         = '员工发红包';

			//列表数据
			$sendList = MoneyOrder::find()->alias('mo');
			$sendList = $sendList->leftJoin('{{%work_external_contact}} we', 'we.id=mo.external_id');
			if ($stateArr['0'] == MoneyOrder::REDPACKET_SEND) {
				$sendList = $sendList->andWhere(['mo.id' => $send_id, 'mo.status' => 1]);
			} else {
				$sendList = $sendList->andWhere(['mo.corp_id' => $corpInfo->id, 'mo.chat_send_id' => $send_id, 'mo.status' => 1]);
			}
			$count    = $sendList->count();
			$offset   = ($page - 1) * $pageSize;
			$sendList = $sendList->limit($pageSize)->offset($offset);
			$sendList = $sendList->select('mo.pay_time,mo.money,mo.external_id,mo.openid,we.name_convert,we.gender,we.avatar')->orderBy(['mo.pay_time' => SORT_DESC])->asArray()->all();
			foreach ($sendList as $k => $v) {
				$sendList[$k]['pay_time']     = date('Y-m-d H:i', $v['pay_time']);
				$sendList[$k]['money']        = sprintf("%.2f", $v['money']);
				$sendList[$k]['name_convert'] = $v['name_convert'] == NULL ? '' : $v['name_convert'];
				$sendList[$k]['gender']       = $v['gender'] == NULL ? '' : $v['gender'];
				$sendList[$k]['avatar']       = $v['avatar'] == NULL ? '' : $v['avatar'];
				if ($v['external_id'] == 0 && !empty($v['openid']) && $v['openid'] == $openid) {
					$sendList[$k]['name_convert'] = '未知客户（我自己）';
				}
			}
			$resData['count']    = $count;
			$resData['sendList'] = $sendList;

			return $resData;
		}

		/************************************** 红包群发 ****************************************/
		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           红包群发领取红包页面
		 * @description     红包群发领取红包页面
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/group-send-redpacket-index
		 *
		 * @param corp_id            必选 string 企业微信id
		 * @param assist             必选 string 附带参数
		 * @param external_userid    必选 string 客户的userid
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    assist string 附带参数
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 红包感谢语
		 * @return_param    canOpen int 红包是否可开启1是0否
		 * @return_param    isOpen int 红包是否已领取1是0否
		 * @return_param    msg string 红包开启提示
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGroupSendRedpacketIndex ()
		{
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [WorkGroupSending::GROUP_REDPACKET])){
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];
			$user_id = $stateArr['2'];
			$toChat  = $stateArr['3'];

			if (empty($external_userid) && empty($openid)){
				throw new InvalidParameterException('员工自己无法领取红包！');
			}
			$externalUserData = [];
			if ($external_userid){
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
			}

			$chatId = 0;
			if ($toChat == 1 && !empty($externalUserData)) {
				//获取群红包发放状态
				WorkGroupSendingRedpacketSend::refreshChatSendResult($send_id, $user_id);

				//获取客户所在的群
				$redpacketChatSend = WorkGroupSendingRedpacketSend::find()->andWhere(['send_id' => $send_id, 'user_id' => $user_id,'is_chat' => 1, 'is_send' => 1])->asArray()->all();//群红包记录
				if (!empty($redpacketChatSend)){
					foreach ($redpacketChatSend as $k=>$v){
						$sendExternal = WorkGroupSendingRedpacketSend::findOne(['group_send_id' => $v['group_send_id'], 'user_id' => $user_id, 'external_userid' => $externalUserData->id, 'is_chat' => 0]);//群个人红包记录
						if ($sendExternal){
							$chatId = $v['external_userid'];
							break;
						}
						$hasChat = WorkChatInfo::findOne(['chat_id' => $v['external_userid'], 'type' => 2, 'external_id' => $externalUserData->id, 'status' => 1]);
						if ($hasChat){
							if ($v['send_money'] - $v['get_money'] >= 0.3){
								$chatId = $v['external_userid'];
								break;
							}
							$hasChatId = $v['external_userid'];
						}
					}
					if (empty($chatId)){
						$chatId = isset($hasChatId) ? $hasChatId : $redpacketChatSend[0]['external_userid'];
					}
				}else{
					throw new InvalidParameterException('没有找到该员工所发放的红包！');
				}
			}

			$externalId = !empty($externalUserData) ? $externalUserData->id : 0;
			$sendVerify = WorkGroupSendingRedpacketSend::verifyRedpacketOpen($corpInfo, $send_id, $externalId, $user_id, $toChat, $chatId);

			$resData              = [];
			$resData['assist']    = $assist;
			$resData['corp_logo'] = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user            = WorkUser::findOne($user_id);
			$resData['avatar']    = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']      = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']  = $sendVerify['des'];
			$resData['canOpen']   = $sendVerify['canOpen'] ? $sendVerify['canOpen'] : 0;
			$resData['isOpen']    = $sendVerify['isOpen'] ? $sendVerify['isOpen'] : 0;
			$resData['msg']       = $sendVerify['msg'] ? $sendVerify['msg'] : '';

			$sendData            = WorkGroupSending::findOne($send_id);
			$resData['act_name'] = $sendData->title;

			return ['resData' => $resData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           红包群发打开红包
		 * @description     红包群发打开红包
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/group-send-redpacket-open
		 *
		 * @param corp_id            必选 string 企业微信id
		 * @param assist             必选 string 附带参数
		 * @param external_userid    必选 string 客户的userid
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGroupSendRedpacketOpen ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}

			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
			if (empty($externalUserData)) {
				throw new InvalidParameterException('客户数据错误！');
			}

			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [WorkGroupSending::GROUP_REDPACKET])){
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];
			$user_id = $stateArr['2'];
			$toChat  = $stateArr['3'];

			$chatId = 0;
			if ($toChat == 1) {
				//获取客户所在的群
				$redpacketChatSend = WorkGroupSendingRedpacketSend::find()->andWhere(['send_id' => $send_id, 'user_id' => $user_id, 'is_chat' => 1, 'is_send' => 1])->asArray()->all();//群红包记录
				if (!empty($redpacketChatSend)){
					foreach ($redpacketChatSend as $k=>$v){
						$sendExternal = WorkGroupSendingRedpacketSend::findOne(['group_send_id' => $v['group_send_id'], 'user_id' => $user_id, 'external_userid' => $externalUserData->id, 'is_chat' => 0]);//群个人红包记录
						if ($sendExternal){
							$chatId = $v['external_userid'];
							break;
						}
						$hasChat = WorkChatInfo::findOne(['chat_id' => $v['external_userid'], 'type' => 2, 'external_id' => $externalUserData->id, 'status' => 1]);
						if ($hasChat){
							if ($v['send_money'] - $v['get_money'] >= 0.3){
								$chatId = $v['external_userid'];
								break;
							}
							$hasChatId = $v['external_userid'];
						}
					}
					if (empty($chatId)){
						$chatId = isset($hasChatId) ? $hasChatId : $redpacketChatSend[0]['external_userid'];
					}
				}else{
					throw new InvalidParameterException('没有找到该员工所发放的红包！');
				}
			}

			$sendVerify = WorkGroupSendingRedpacketSend::verifyRedpacketOpen($corpInfo, $send_id, $externalUserData->id, $user_id, $toChat, $chatId);

			if (empty($sendVerify)) {
				throw new InvalidParameterException('领取失败！');
			}
			if ($sendVerify['canOpen'] == 0){
				throw new InvalidParameterException($sendVerify['msg']);
			}

			//领取红包
			WorkGroupSendingRedpacketSend::redpacketOpen($send_id, $externalUserData->id, $user_id, $toChat, $chatId, $sendVerify['des']);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/redpacket-open/
		 * @title           获取群红包领取列表
		 * @description     获取群红包领取列表
		 * @method   post
		 * @url  http://{host_name}/api/redpacket-open/group-send-redpacket-receive
		 *
		 * @param corp_id              必选 string 企业微信id
		 * @param assist               必选 string 附带参数
		 * @param external_userid      必选 string 客户id
		 * @param page                 可选 int 页码
		 * @param page_size            可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    send_id int 发放id
		 * @return_param    corp_logo string 企业微信logo
		 * @return_param    avatar string 员工头像
		 * @return_param    name string 员工姓名
		 * @return_param    thanking string 感谢语
		 * @return_param    redpacket_amount string 红包金额
		 * @return_param    get_amount int 领取金额
		 * @return_param    get_num int 领取个数
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据信息
		 * @return_param    list.name_convert string 客户姓名
		 * @return_param    list.avatar string 客户头像
		 * @return_param    list.gender string 客户性别
		 * @return_param    list.send_time string 领取时间
		 * @return_param    list.send_money string 领取金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGroupSendRedpacketReceive ()
		{
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$assist          = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$page            = \Yii::$app->request->post('page') ?: 1;
			$pageSize        = \Yii::$app->request->post('pageSize') ?: 15;

			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}

			if (empty($assist)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [WorkGroupSending::GROUP_REDPACKET])) {
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];
			$user_id = $stateArr['2'];
			$toChat  = $stateArr['3'];

			$groupSend = WorkGroupSending::findOne($send_id);
			if (empty($groupSend)) {
				throw new InvalidParameterException('活动参数不正确！');
			}
			if ($groupSend->rule_id > 0) {
				$redRule = RedPackRule::find()->andWhere(['id' => $groupSend->rule_id])->asArray()->one();
			} else {
				$redRule = json_decode($groupSend->rule_text, true);
			}

			$resData                     = [];
			$resData['send_id']          = $send_id;
			$resData['corp_logo']        = !empty($corpInfo->corp_square_logo_url) ? $corpInfo->corp_square_logo_url : '';
			$work_user                   = WorkUser::findOne($user_id);
			$resData['avatar']           = !empty($work_user) ? $work_user->avatar : '';
			$resData['name']             = !empty($work_user) ? $work_user->name : '';
			$resData['thanking']         = $redRule['thanking'] ? $redRule['thanking'] : MoneyOrder::REDPACKET_THANKING;
			$resData['redpacket_amount'] = $groupSend->redpacket_amount;
			$resData['send_money']       = 0;

			if ($external_userid) {
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}

				$redpacketSend = WorkGroupSendingRedpacketSend::findOne(['send_id' => $send_id, 'user_id' => $user_id, 'external_userid' => $externalUserData->id, 'is_chat' => 0]);//红包记录
				if (empty($redpacketSend)) {
					throw new InvalidParameterException('发放参数不正确！');
				}

				$resData['send_money'] = !empty($redpacketSend) && $redpacketSend->status == 1 ? $redpacketSend->send_money : 0;
				$resData['rule_type']  = $redpacketSend->rule_type;
				$resData['is_expired'] = time() - $redpacketSend->create_time > 86400 ? 1 : 0;

				if ($toChat == 1) {
					$chatSend    = WorkGroupSendingRedpacketSend::findOne(['group_send_id' => $redpacketSend->group_send_id, 'user_id' => $user_id, 'is_chat' => 1]);//群红包记录
					$send_amount = $chatSend->get_money;
					$send_num    = $chatSend->get_num;
				} else {
					$send_amount                 = $resData['send_money'];
					$send_num                    = 1;
					$resData['redpacket_amount'] = $redpacketSend->send_money;
				}

				//客户列表数据
				$sendList = WorkGroupSendingRedpacketSend::find()->alias('rs');
				$sendList = $sendList->leftJoin('{{%work_external_contact}} we', 'we.id=rs.external_userid');
				$sendList = $sendList->andWhere(['rs.corp_id' => $corpInfo->id, 'rs.group_send_id' => $redpacketSend->group_send_id, 'rs.status' => 1]);
				$count    = $sendList->groupBy('rs.external_userid')->count();
				$offset   = ($page - 1) * $pageSize;
				$sendList = $sendList->limit($pageSize)->offset($offset);
				$sendList = $sendList->select('rs.send_time,rs.send_money,we.name_convert,we.gender,we.avatar')->groupBy('rs.external_userid')->orderBy(['rs.send_time' => SORT_DESC])->asArray()->all();
				foreach ($sendList as $k => $v) {
					$sendList[$k]['send_time']  = date('Y-m-d H:i', $v['send_time']);
					$sendList[$k]['send_money'] = sprintf("%.2f", $v['send_money']);
				}

			} else {
				$send_amount = $groupSend->send_amount;
				$send_num    = $groupSend->send_num;
				//员工列表数据
				$sendList = WorkGroupSendingRedpacketSend::find()->alias('rs');
				$sendList = $sendList->leftJoin('{{%work_external_contact}} we', 'we.id=rs.external_userid');
				$sendList = $sendList->andWhere(['rs.corp_id' => $corpInfo->id, 'rs.send_id' => $send_id, 'rs.user_id' => $user_id, 'rs.is_chat' => 0, 'rs.status' => 1]);
				$count    = $sendList->groupBy('rs.external_userid')->count();
				$offset   = ($page - 1) * $pageSize;
				$sendList = $sendList->limit($pageSize)->offset($offset);
				$sendList = $sendList->select('rs.send_time,rs.send_money,rs.rule_type,we.name_convert,we.gender,we.avatar')->groupBy('rs.external_userid')->orderBy(['rs.send_time' => SORT_DESC])->asArray()->all();
				foreach ($sendList as $k => $v) {
					$sendList[$k]['send_time']  = date('Y-m-d H:i', $v['send_time']);
					$sendList[$k]['send_money'] = sprintf("%.2f", $v['send_money']);
				}
				$resData['rule_type'] = !empty($sendList) ? $sendList[0]['rule_type'] : 1;
			}
			$sendData              = WorkGroupSending::findOne($send_id);
			$resData['act_name']   = $sendData->title;
			$resData['get_amount'] = $send_amount;
			$resData['get_num']    = $send_num;
			$resData['count']      = $count;
			$resData['sendList']   = $sendList;

			return $resData;
		}
	}