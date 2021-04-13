<?php
	/**
	 * Create by PhpStorm
	 * title: 渠道活码红包活动
	 * User: fulu
	 * Date: 2020/09/24
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\RedPackRule;
	use app\models\WorkContactWayRedpacket;
	use app\models\WorkContactWayRedpacketDate;
	use app\models\WorkContactWayRedpacketSend;
	use app\models\WorkCorp;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkWelcome;
	use app\models\WorkDepartment;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;
	use moonland\phpexcel\Excel;

	class WorkContactWayRedpacketController extends WorkBaseController
	{
		/**
		 * @param WorkExternalContactFollowUser $a
		 * @param WorkExternalContactFollowUser $b
		 *
		 * @return int
		 */
		private function cmp ($a, $b)
		{
			return 0 - strcmp($a->createtime, $b->createtime);
		}

		/**
		 *
		 * @return mixed
		 *
		 * @throws InvalidParameterException
		 */
		private function getOtherInfo ()
		{
			$data['corp_id']     = $this->corp['id'];
			$data['uid']         = $this->user->uid;
			$data['agent_id']    = \Yii::$app->request->post('agent_id', 0);
			$data['name']        = \Yii::$app->request->post('name');
			$data['time_type']   = \Yii::$app->request->post('time_type');
			$data['start_time']  = \Yii::$app->request->post('start_time');
			$data['end_time']    = \Yii::$app->request->post('end_time');
			$data['reserve_day'] = \Yii::$app->request->post('reserve_day');
			$data['rule_id']     = \Yii::$app->request->post('rule_id') ?: 0;
			if ($data['rule_id'] == 0) {
				$data['rule_save']              = \Yii::$app->request->post('rule_save') ?: 0;//是否保存规则 1是0否
				$data['rule_name']              = \Yii::$app->request->post('rule_name');
				$data['rule_type']              = \Yii::$app->request->post('rule_type');
				$data['rule_fixed_amount']      = \Yii::$app->request->post('rule_fixed_amount');
				$data['rule_min_random_amount'] = \Yii::$app->request->post('rule_min_random_amount');
				$data['rule_max_random_amount'] = \Yii::$app->request->post('rule_max_random_amount');
				$data['rule_pic_url']           = \Yii::$app->request->post('rule_pic_url');
				$data['rule_title']             = \Yii::$app->request->post('rule_title');
				$data['rule_des']               = \Yii::$app->request->post('rule_des');
				$data['rule_thanking']          = \Yii::$app->request->post('rule_thanking');
			}
			$data['redpacket_amount'] = \Yii::$app->request->post('redpacket_amount');

			$data['add_type']     = \Yii::$app->request->post('add_type', 0);//0文本
			$data['text_content'] = \Yii::$app->request->post('text_content');
			$data['status']       = \Yii::$app->request->post('status');
			$data['tag_ids']      = \Yii::$app->request->post('tag_ids');
			$data['user_key']     = \Yii::$app->request->post('user');
			$data['title']        = \Yii::$app->request->post('title');
			$data['open_date']    = \Yii::$app->request->post('specialTime');
			$data['choose_date']  = \Yii::$app->request->post('specialDateList');
			$data['week_user']    = \Yii::$app->request->post('specialWeekList');

			$data['user_limit']        = \Yii::$app->request->post('user_limit');
			$data['is_limit']          = \Yii::$app->request->post('is_limit', 1);
			$data['verify_all_day']    = \Yii::$app->request->post('verify_all_day', 1);
			$data['verify_date']       = \Yii::$app->request->post('verify_date');
			$data['spare_employee']    = \Yii::$app->request->post('spare_employee');
			$data['is_welcome_date']   = \Yii::$app->request->post('is_welcome_date', 1);
			$data['is_welcome_week']   = \Yii::$app->request->post('is_welcome_week', 1);
			$data['welcome_date_list'] = \Yii::$app->request->post('welcome_date_list');
			$data['welcome_week_list'] = \Yii::$app->request->post('welcome_week_list');
			$data['skip_verify']       = \Yii::$app->request->post('skip_verify');
			$data['text_content']      = trim($data['text_content']);

			if (empty($data['agent_id'])) {
				throw new InvalidParameterException("请选择企业应用");
			}
			if (empty($data['reserve_day'])){
				throw new InvalidParameterException('活动结束后渠道活码保留期天数不能为空！');
			}
			if ($data['open_date'] && empty($data['choose_date'])) {
				throw new InvalidParameterException('选填时间段的成员不能为空！');
			}
			if (empty($data['week_user'])) {
				throw new InvalidParameterException('每周配置的企业成员不能为空！');
			}

			return $data;
		}

		/**
		 * @param $id
		 *
		 * @return array
		 *
		 * @throws \app\components\InvalidDataException
		 */
		private function getContactWayInfo ($id)
		{
			$verifyAllDay = \Yii::$app->request->post('verify_all_day');
			$verifyDate   = \Yii::$app->request->post('verify_date');
			$week_user    = \Yii::$app->request->post('specialWeekList');
			$choose_date  = \Yii::$app->request->post('specialDateList');
			$type         = \Yii::$app->request->post('type') ?: 1;
			$scene        = \Yii::$app->request->post('scene') ?: 2;
			$style        = \Yii::$app->request->post('style') ?: 1;
			$remark       = \Yii::$app->request->post('remark') ?: '';
			$skip_verify  = \Yii::$app->request->post('skip_verify');
			$open_date    = \Yii::$app->request->post('specialTime');
			// 前端值1需要认证0无需认证   微信接口意思是是否无需认证
			if (empty($skip_verify)) {
				$skip_verify = true; //无需认证
				if ($verifyAllDay == 2) {
					$flag = 0;
					//开启了分时段自动通过
					foreach ($verifyDate as $date) {
						$startTime = strtotime(date('Y-m-d') . ' ' . $date['start_time']);
						if ($date['end_time'] == '00:00') {
							$date['end_time'] = '23:59:59';
						}
						$endTime = strtotime(date('Y-m-d') . ' ' . $date['end_time']);
						if ($startTime <= time() && time() <= $endTime) {
							$flag = 1;//当前时间在分时段自动通过的范围内
						}
					}
					if ($flag == 0) {
						$skip_verify = false; //需认证
					}
				}
			} else {
				$skip_verify = false; //需认证
			}
			$state = WorkContactWayRedpacket::REDPACKET_WAY . '_' . $this->corp['id'] . '_' . time() . rand(111, 999);
			if (!empty($id)) {
				$contact = WorkContactWayRedpacket::findOne($id);
				if (!empty($contact->state)) {
					$state = $contact->state;
				}
			}
			WorkContactWayRedpacketDate::verifyData($week_user, $type);
			$wayDate        = WorkContactWayRedpacketDate::getNowUser($week_user, $choose_date, $this->corp->id, $open_date, $type);
			$userId         = $wayDate['userId'];
			$partyId        = $wayDate['partyId'];
			$contactWayInfo = [
				'type'        => (int) $type,
				'scene'       => (int) $scene,
				'style'       => (int) $style,
				'remark'      => $remark,
				'skip_verify' => $skip_verify,
				'state'       => $state,
				'user'        => $userId,
				'party'       => $partyId,
			];
			\Yii::error($contactWayInfo, '$contactWayRedpacketInfo');

			return $contactWayInfo;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           新增红包活动渠道活码
		 * @description     新增红包活动渠道活码
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/add-redpacket
		 *
		 * @param corp_id                必选 string 企业的唯一ID
		 * @param name                   必选 string 活动名称
		 * @param time_type              必选 int 时间设置1永久有效2时间区间
		 * @param start_time             可选 string 开始日期
		 * @param end_time               可选 string 结束日期
		 * @param reserve_day            必选 int 活动结束后渠道活码保留期（天）
		 * @param rule_id                可选 int 红包规则id
		 * @param rule_save              可选 int 是否保存规则1是0否
		 * @param rule_name              可选 string 红包规则名称
		 * @param rule_type              可选 int 单个红包金额类型：1、固定金额，2、随机金额
		 * @param rule_fixed_amount      可选 string 固定金额
		 * @param rule_min_random_amount 可选 string 最小随机金额
		 * @param rule_max_random_amount 可选 string 最大随机金额
		 * @param rule_pic_url           可选 string 红包封面路径
		 * @param rule_title             可选 string 红包标题
		 * @param rule_des               可选 string 红包描述
		 * @param rule_thanking          可选 string 感谢语
		 * @param redpacket_amount       必选 string 投放金额
		 *
		 * @param type                   必选 int 联系方式类型,1-单人, 2-多人
		 * @param scene                  必选 int 场景，1-在小程序中联系，2-通过二维码联系
		 * @param style                  可选 int 在小程序中联系时使用的控件样式，详见附表
		 * @param remark                 可选 string 联系方式的备注信息，用于助记，不超过30个字符
		 * @param skip_verify            可选 boolean 外部客户添加时是否无需验证，默认为true
		 * @param state                  可选 string 企业自定义的state参数，用于区分不同的添加渠道
		 * @param user                   可选 array 使用该联系方式的用户userID列表，在type为1时为必填，且只能有一个
		 * @param party                  可选 array 使用该联系方式的部门id列表，只在type为2时有效
		 * @param tag_ids                可选 string 标签id多个逗号隔开
		 * @param open_date              可选 bool true开启false关闭
		 * @param choose_date            可选 array 日期活码
		 * @param week_user              可选 array 每周配置的人员
		 * @param verify_all_day         可选 int 自动验证1全天开启2分时段
		 * @param spare_employee         可选 array 备用员工
		 * @param is_welcome_date        可选 int 欢迎语时段日期1关2开
		 * @param is_welcome_week        可选 int 欢迎语时段周关2开
		 * @param is_limit               可选 int 员工上限1关2开
		 * @param user_limit             可选 array 员工上限数组
		 * @param verify_date            可选 array 验证自动通过好友时间段
		 * @param welcome_week_list      可选 array   欢迎语周
		 * @param welcome_date_list      可选 array   欢迎语日期
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionAddRedpacket ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$otherInfo = $this->getOtherInfo();

			$way_redpacket = WorkContactWayRedpacket::find()->andWhere(['name' => $otherInfo['name'], 'corp_id' => $this->corp->id])->andWhere(['redpacket_status' => [1, 2, 3]])->one();
			if (!empty($way_redpacket)) {
				throw new InvalidParameterException('红包活动名称不能存在重复！');
			}

			if (empty($otherInfo['rule_id']) && $otherInfo['rule_save'] == 1) {
				//新建红包规则并保存
				$ruleData                      = [];
				$ruleData['id']                = 0;
				$ruleData['uid']               = $otherInfo['uid'];
				$ruleData['name']              = $otherInfo['rule_name'];
				$ruleData['type']              = $otherInfo['rule_type'];
				$ruleData['fixed_amount']      = $otherInfo['rule_fixed_amount'];
				$ruleData['min_random_amount'] = $otherInfo['rule_min_random_amount'];
				$ruleData['max_random_amount'] = $otherInfo['rule_max_random_amount'];
				$ruleData['pic_url']           = $otherInfo['rule_pic_url'];
				$ruleData['title']             = $otherInfo['rule_title'];
				$ruleData['des']               = $otherInfo['rule_des'];
				$ruleData['thanking']          = $otherInfo['rule_thanking'];

				$otherInfo['rule_id'] = RedPackRule::setData($ruleData);
			}

			WorkContactWayRedpacket::verify($otherInfo);

			$contactWayInfo = $this->getContactWayInfo(0);

			WorkWelcome::verify($otherInfo, 1);

			$wayId = WorkContactWayRedpacket::addWay($this->corp->id, $contactWayInfo, $otherInfo);

			return [
				'way_id' => $wayId,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           获取红包活动渠道活码列表
		 * @description     获取红包活动渠道活码列表
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/get-redpacket-list
		 *
		 * @param corp_id   必选 string 企业唯一标志
		 * @param status    可选 int 活动状态1未发布2已发布3已失效(红包活动)4已失效(渠道活码)
		 * @param name      可选 string 活动名称
		 * @param page      可选 int 页码
		 * @param page_size 可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据信息
		 * @return_param    list.id int 红包活动id
		 * @return_param    list.local_path string 红包活动活码图片路径
		 * @return_param    list.name string 红包活动名称
		 * @return_param    list.time_type int 红包活动时间设置1永久有效2时间区间
		 * @return_param    list.start_time string 开始日期
		 * @return_param    list.end_time string 结束日期
		 * @return_param    list.redpacket_amount string 活动投放金额
		 * @return_param    list.add_num int 拉新人数
		 * @return_param    list.receive_snum int 已领取金额
		 * @return_param    list.receive_num int 已领取人数
		 * @return_param    list.not_receive_snum int 待领取金额
		 * @return_param    list.not_receive_num int 待领取人数
		 * @return_param    list.expired_snum int 已过期金额
		 * @return_param    list.expired_num int 已过期人数
		 * @return_param    list.redpacket_status int 红包活动状态1未发布2已发布3已失效(红包活动)4已失效(渠道活码)
		 * @return_param    list.status_name string 红包活动状态描述
		 * @return_param    list.rule_name string 红包规则名称
		 * @return_param    list.rule_type int 红包金额类型：1、固定金额，2、随机金额
		 * @return_param    list.rule_fixed_amount string 固定金额
		 * @return_param    list.rule_min_random_amount string 最小随机金额
		 * @return_param    list.rule_max_random_amount string 最大随机金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetRedpacketList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$page      = \Yii::$app->request->post('page') ?: 1;
			$pageSize  = \Yii::$app->request->post('pageSize') ?: 15;
			$status    = \Yii::$app->request->post('status', 0);
			$name      = \Yii::$app->request->post('name', '');
			$startTime = \Yii::$app->request->post('start_date', '');
			$endTime   = \Yii::$app->request->post('end_date', '');

			//更新活动到期
			WorkContactWayRedpacket::updateAll(['redpacket_status' => WorkContactWayRedpacket::RED_PACKET_DISABLED], ['and', ['corp_id' => $this->corp->id, 'time_type' => 2, 'redpacket_status' => WorkContactWayRedpacket::RED_WAY_ISSUE], ['<', 'end_time', date('Y-m-d H:i:s')]]);

			$workContactWay = WorkContactWayRedpacket::find()->andWhere(['corp_id' => $this->corp->id]);
			if (!empty($status)) {
				if ($status == WorkContactWayRedpacket::RED_PACKET_DISABLED){
					$status = [WorkContactWayRedpacket::RED_PACKET_DISABLED, WorkContactWayRedpacket::RED_WAY_DISABLED];
				}
				$workContactWay = $workContactWay->andWhere(['redpacket_status' => $status]);
			} else {
				$workContactWay = $workContactWay->andWhere(['!=', 'redpacket_status', 5]);
			}
			if (!empty($name)) {
				$workContactWay = $workContactWay->andWhere(['or', ['like', 'name', trim($name)]]);
			}
			if (!empty($startTime) && !empty($endTime)) {
				$workContactWay = $workContactWay->andWhere("(((`start_time` BETWEEN '" . $startTime . " 00:00:00' AND '" . $endTime . " 23:59:59') OR (`end_time` BETWEEN '" . $startTime . " 00:00:00' AND '" . $endTime . " 23:59:59')) and time_type=2) or time_type=1");
			}

			$offset         = ($page - 1) * $pageSize;
			$count          = $workContactWay->count();
			$workContactWay = $workContactWay->select('*')->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();

			$contactWay = [];
			$field      = 'sum(send_money) send_sum, count(id) send_num';
			foreach ($workContactWay as $way) {
				$wayData                     = [];
				$corpInfo                    = WorkCorp::findOne($way->corp_id);
				$wayData['id']               = $way->id;
				$wayData['corp_id']          = $corpInfo->corpid;
				$wayData['local_path']       = $way->local_path;
				$wayData['name']             = $way->name;
				$wayData['time_type']        = $way->time_type;
				$wayData['start_time']       = $way->start_time;
				$wayData['end_time']         = $way->end_time;
				$wayData['redpacket_amount'] = $way->redpacket_amount;
				$wayData['add_num']          = WorkExternalContactFollowUser::find()->where(['way_redpack_id' => $way->id, 'del_type' => [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]])->count();

				$wayData['receive_snum']     = 0;//已领取金额
				$wayData['receive_num']      = 0;//已领取人数
				$wayData['not_receive_snum'] = 0;//未领取金额
				$wayData['not_receive_num']  = 0;//未领取人数
				$receiveAll                  = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $corpInfo->id, 'way_id' => $way->id, 'status' => 1]);
				$receiveAll                  = $receiveAll->select($field)->groupBy('way_id')->asArray()->all();
				$wayData['receive_snum']     = !empty($receiveAll[0]['send_sum']) ? $receiveAll[0]['send_sum'] : '0.00';
				$wayData['receive_num']      = !empty($receiveAll[0]['send_num']) ? $receiveAll[0]['send_num'] : 0;
				//全部未领取
				$notReceiveAll               = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $corpInfo->id, 'way_id' => $way->id, 'is_send' => 1, 'status' => [0, 2]]);
				$notReceiveAll               = $notReceiveAll->select($field)->groupBy('way_id')->asArray()->all();
				$wayData['not_receive_snum'] = !empty($notReceiveAll[0]['send_sum']) ? $notReceiveAll[0]['send_sum'] : '0.00';
				$wayData['not_receive_num']  = !empty($notReceiveAll[0]['send_num']) ? $notReceiveAll[0]['send_num'] : 0;

				$wayData['redpacket_status'] = $way->redpacket_status;
				$status_name                 = '--';
				switch ($way->redpacket_status) {
					case WorkContactWayRedpacket::RED_WAY_NOT_ISSUE:
						$status_name = '未发布';
						break;
					case WorkContactWayRedpacket::RED_WAY_ISSUE:
						$status_name = '已发布';
						break;
					case WorkContactWayRedpacket::RED_PACKET_DISABLED:
						$status_name = '已失效(红包活动)';
						break;
					case WorkContactWayRedpacket::RED_WAY_DISABLED:
						$status_name = '已失效(渠道活码)';
						break;
				}
				$wayData['status_name'] = $status_name;

				$wayData['rule_id'] = $way->rule_id;
				if ($way->rule_id) {
					$ruleInfo             = RedPackRule::findOne($way->rule_id);
					$wayData['rule_name'] = $ruleInfo->name;
					if ($ruleInfo->status == 0) {
						$wayData['rule_name'] .= '（已删除）';
					}
					$wayData['rule_type']              = $ruleInfo->type;
					$wayData['rule_fixed_amount']      = $ruleInfo->fixed_amount;
					$wayData['rule_min_random_amount'] = $ruleInfo->min_random_amount;
					$wayData['rule_max_random_amount'] = $ruleInfo->max_random_amount;
				} else {
					$ruleInfo                          = Json::decode($way->rule_text, true);
					$wayData['rule_name']              = $ruleInfo['name'];
					$wayData['rule_type']              = $ruleInfo['type'];
					$wayData['rule_fixed_amount']      = $ruleInfo['fixed_amount'];
					$wayData['rule_min_random_amount'] = $ruleInfo['min_random_amount'];
					$wayData['rule_max_random_amount'] = $ruleInfo['max_random_amount'];
				}

				array_push($contactWay, $wayData);
			}

			return [
				'count' => $count,
				'list'  => $contactWay,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           红包活动活码员工预览
		 * @description     红包活动活码员工预览
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/get-redpacket-user
		 *
		 * @param corp_id   必选 string 企业唯一标志
		 * @param id        必选 int 红包活动id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    contact_way array 数据信息
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetRedpacketUser ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$id = \Yii::$app->request->post('id') ?: '';

			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$workContactWay = WorkContactWayRedpacket::findOne($id);

			if (empty($workContactWay) || $workContactWay->corp_id != $this->corp->id) {
				throw new InvalidParameterException('参数不正确！');
			}
			$contact_way = $workContactWay->dumpData(true, true);

			$welcome_content['add_type']      = 0;
			$welcome_content['status']        = $contact_way['status'];
			$welcome_content['material_sync'] = $contact_way['material_sync'];
			$welcome_content['groupId']       = $contact_way['groupId'];
			$welcome_content['attachment_id'] = $contact_way['attachment_id'];
			$welcome_content['text_content']  = '';
			$content                          = [];
			if (!empty($contact_way['content'])) {
				$content = json_decode($contact_way['content'], true);
			}
			$contentData                    = WorkWelcome::getContentData($content);
			$welcome_content                = WorkWelcome::getWelcomeData($welcome_content, $content, $contentData);
			$contact_way['welcome_content'] = $welcome_content;

			$user_key = !empty($contact_way['user_key']) ? json_decode($contact_way['user_key'], true) : [];
			if (!empty($contact_way['user'])) {
				foreach ($contact_way['user'] as $key => $user) {
					if ($contact_way['type'] == 2) {
						foreach ($user_key as $val) {
							if ($val['id'] == $user['id']) {
								$contact_way['user'][$key]['user_key'] = $val['user_key'];
							}
						}
					}
				}
			}
			$contact_way['tag_ids']         = !empty($contact_way['tag_ids']) ? explode(',', $contact_way['tag_ids']) : [];
			$contact_way['specialDateList'] = WorkContactWayRedpacketDate::getChooseDate($contact_way['id'], $contact_way['corp_id']);
			$contact_way['specialWeekList'] = WorkContactWayRedpacketDate::getWeekUser($contact_way['id'], $contact_way['corp_id']);

			return [
				'contact_way' => $contact_way,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           修改红包活动渠道活码状态
		 * @description     修改红包活动渠道活码状态
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/change-redpacket-status
		 *
		 * @param id     必选 int 红包活动活码id
		 * @param status 必选 int 状态2发布3使失效5使删除
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/25
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionChangeRedpacketStatus ()
		{
			$id     = \Yii::$app->request->post('id');
			$status = \Yii::$app->request->post('status');
			if (empty($status)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (!in_array($status, [WorkContactWayRedpacket::RED_WAY_ISSUE, WorkContactWayRedpacket::RED_PACKET_DISABLED, WorkContactWayRedpacket::RED_WAY_DEL])) {
				throw new InvalidParameterException('状态参数不正确！');
			}

			$redpacket = WorkContactWayRedpacket::findOne($id);
			if (empty($redpacket)) {
				throw new InvalidParameterException('红包活动参数不正确！');
			}

			if ($status == WorkContactWayRedpacket::RED_WAY_ISSUE) {
				//发布
				if ($redpacket->redpacket_status != WorkContactWayRedpacket::RED_WAY_NOT_ISSUE) {
					throw new InvalidParameterException('活动非未发布状态，无法发布！');
				}
				if ($redpacket->time_type == 2) {
					if (strtotime($redpacket->start_time) > time()) {
						throw new InvalidParameterException('尚未到活动开始时间，暂且无法发布！');
					}
					if (strtotime($redpacket->end_time) < time()) {
						throw new InvalidParameterException('活动已结束，无法发布！');
					}
				}

				$redpacket->redpacket_status = WorkContactWayRedpacket::RED_WAY_ISSUE;
				if (!$redpacket->validate() || !$redpacket->save()) {
					throw new InvalidDataException(SUtils::modelError($redpacket));
				}
			} elseif ($status == WorkContactWayRedpacket::RED_PACKET_DISABLED) {
				//活动失效
				if ($redpacket->redpacket_status != WorkContactWayRedpacket::RED_WAY_ISSUE) {
					throw new InvalidParameterException('活动非已发布状态，无法失效！');
				}
				$redpacket->redpacket_status = WorkContactWayRedpacket::RED_PACKET_DISABLED;
				if ($redpacket->time_type == 1 || ($redpacket->time_type == 2 && strtotime($redpacket->end_time) > time())) {
					$redpacket->disabled_time = time();
				}
				if (!$redpacket->validate() || !$redpacket->save()) {
					throw new InvalidDataException(SUtils::modelError($redpacket));
				}
			} elseif ($status == WorkContactWayRedpacket::RED_WAY_DEL) {
				//删除
				WorkContactWayRedpacket::delWay($id);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           获取红包活动渠道活码详情
		 * @description     获取红包活动渠道活码详情
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/get-redpacket-info
		 *
		 * @param id        必选 int 红包活动活码id
		 * @param corp_id   必选 int 企业微信id
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 活动id
		 * @return_param    name string 活动名称
		 * @return_param    time_type int 活动时间类型1永久有效2时间区间
		 * @return_param    start_time string 开始日期
		 * @return_param    end_time string 结束日期
		 * @return_param    end_time string 结束日期
		 * @return_param    reserve_day int 活动结束后渠道活码保留期（天）
		 * @return_param    rule_id int 红包规则id
		 * @return_param    rule_name string 规则名称
		 * @return_param    rule_type int 单个红包金额类型：1、固定金额，2、随机金额
		 * @return_param    rule_fixed_amount string 固定金额
		 * @return_param    rule_min_random_amount string 最小随机金额
		 * @return_param    rule_max_random_amount string 最大随机金额
		 * @return_param    rule_title string 红包标题
		 * @return_param    rule_pic_url string 红包封面路径
		 * @return_param    rule_des string 红包描述
		 * @return_param    rule_thanking string 感谢语
		 * @return_param    redpacket_amount string 活动投放金额
		 * @return_param    redpacket_status int 红包活动状态1未发布2已发布3已失效(红包活动)4已失效(渠道活码)5已删除
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/25
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGetRedpacketInfo ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$id = \Yii::$app->request->post('id') ?: '';

			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$workContactWay = WorkContactWayRedpacket::findOne($id);

			if (empty($workContactWay) || $workContactWay->corp_id != $this->corp->id) {
				throw new InvalidParameterException('参数不正确！');
			}
			$contact_way = $workContactWay->dumpData(true, true);

			$welcome_content['add_type']      = 0;
			$welcome_content['status']        = $contact_way['status'];
			$welcome_content['material_sync'] = $contact_way['material_sync'];
			$welcome_content['groupId']       = $contact_way['groupId'];
			$welcome_content['attachment_id'] = $contact_way['attachment_id'];
			$welcome_content['text_content']  = '';
			$content                          = [];
			if (!empty($contact_way['content'])) {
				$content = json_decode($contact_way['content'], true);
			}
			$contentData                    = WorkWelcome::getContentData($content);
			$welcome_content                = WorkWelcome::getWelcomeData($welcome_content, $content, $contentData);
			$contact_way['welcome_content'] = $welcome_content;

			$user_key = !empty($contact_way['user_key']) ? json_decode($contact_way['user_key'], true) : [];
			if (!empty($contact_way['user'])) {
				foreach ($contact_way['user'] as $key => $user) {
					if ($contact_way['type'] == 2) {
						foreach ($user_key as $val) {
							if ($val['id'] == $user['id']) {
								$contact_way['user'][$key]['user_key'] = $val['user_key'];
							}
						}
					}
				}
			}
			$contact_way['tag_ids']         = !empty($contact_way['tag_ids']) ? explode(',', $contact_way['tag_ids']) : [];
			$contact_way['specialDateList'] = WorkContactWayRedpacketDate::getChooseDate($contact_way['id'], $contact_way['corp_id']);
			$contact_way['specialWeekList'] = WorkContactWayRedpacketDate::getWeekUser($contact_way['id'], $contact_way['corp_id']);

			return ['contact_way' => $contact_way];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           修改红包活动渠道活码
		 * @description     修改红包活动渠道活码
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/update-redpacket
		 *
		 * @param corp_id                必选 string 企业的唯一ID
		 * @param name                   必选 string 活动名称
		 * @param time_type              必选 int 时间设置1永久有效2时间区间
		 * @param start_time             可选 string 开始日期
		 * @param end_time               可选 string 结束日期
		 * @param reserve_day            必选 int 活动结束后渠道活码保留期（天）
		 * @param rule_id                可选 int 红包规则id
		 * @param rule_save              可选 int 是否保存规则1是0否
		 * @param rule_name              可选 string 红包规则名称
		 * @param rule_type              可选 int 单个红包金额类型：1、固定金额，2、随机金额
		 * @param rule_fixed_amount      可选 string 固定金额
		 * @param rule_min_random_amount 可选 string 最小随机金额
		 * @param rule_max_random_amount 可选 string 最大随机金额
		 * @param rule_pic_url           可选 string 红包封面路径
		 * @param rule_title             可选 string 红包标题
		 * @param rule_des               可选 string 红包描述
		 * @param rule_thanking          可选 string 感谢语
		 * @param redpacket_amount       必选 string 投放金额
		 *
		 * @param config_id              必选 string 联系方式的配置id
		 * @param type                   必选 int 联系方式类型,1-单人, 2-多人
		 * @param scene                  必选 int 场景，1-在小程序中联系，2-通过二维码联系
		 * @param style                  可选 int 在小程序中联系时使用的控件样式，详见附表
		 * @param remark                 可选 string 联系方式的备注信息，用于助记，不超过30个字符
		 * @param skip_verify            可选 boolean 外部客户添加时是否无需验证，默认为true
		 * @param state                  可选 string 企业自定义的state参数，用于区分不同的添加渠道
		 * @param user                   可选 array 使用该联系方式的用户userID列表，在type为1时为必填，且只能有一个
		 * @param party                  可选 array 使用该联系方式的部门id列表，只在type为2时有效
		 * @param tag_ids                可选 string 标签id多个逗号隔开
		 * @param open_date              可选 bool true开启false关闭
		 * @param choose_date            可选 array 日期活码
		 * @param week_user              可选 array 每周配置的人员
		 * @param verify_all_day         可选 int 自动验证1全天开启2分时段
		 * @param spare_employee         可选 array 备用员工
		 * @param is_welcome_date        可选 int 欢迎语时段日期1关2开
		 * @param is_welcome_week        可选 int 欢迎语时段周关2开
		 * @param is_limit               可选 int 员工上限1关2开
		 * @param user_limit             可选 array 员工上限数组
		 * @param verify_date            可选 array 验证自动通过好友时间段
		 * @param welcome_week_list      可选 array   欢迎语周
		 * @param welcome_date_list      可选 array   欢迎语日期
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/25
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionUpdateRedpacket ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$configId = \Yii::$app->request->post('config_id') ?: '';

			if (empty($configId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$otherInfo = $this->getOtherInfo();

			$contact_way = WorkContactWayRedpacket::find()->andWhere(['<>', 'id', $id])->andWhere(['name' => $otherInfo['name'], 'corp_id' => $this->corp->id])->andWhere(['is_del' => [1, 2, 3, 4]])->one();

			if (!empty($contact_way)) {
				throw new InvalidParameterException('活动名称名称不能存在重复！');
			}

			if (empty($otherInfo['rule_id']) && $otherInfo['rule_save'] == 1) {
				//新建红包规则并保存
				$ruleData                      = [];
				$ruleData['id']                = 0;
				$ruleData['uid']               = $otherInfo['uid'];
				$ruleData['name']              = $otherInfo['rule_name'];
				$ruleData['type']              = $otherInfo['rule_type'];
				$ruleData['fixed_amount']      = $otherInfo['rule_fixed_amount'];
				$ruleData['min_random_amount'] = $otherInfo['rule_min_random_amount'];
				$ruleData['max_random_amount'] = $otherInfo['rule_max_random_amount'];
				$ruleData['pic_url']           = $otherInfo['rule_pic_url'];
				$ruleData['title']             = $otherInfo['rule_title'];
				$ruleData['des']               = $otherInfo['rule_des'];
				$ruleData['thanking']          = $otherInfo['rule_thanking'];

				$otherInfo['rule_id'] = RedPackRule::setData($ruleData);
			}

			WorkContactWayRedpacket::verify($otherInfo);

			$contactWayInfo = $this->getContactWayInfo($id);

			$contactWayInfo['config_id'] = $configId;

			WorkWelcome::verify($otherInfo, 1);

			$wayId = WorkContactWayRedpacket::updateWay($this->corp->id, $contactWayInfo, $otherInfo);

			return [
				'way_id' => $wayId,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           红包发放列表
		 * @description     红包发放列表（拉新客户列表）
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/redpacket-send-list
		 *
		 * @param corp_id      必选 string 企业微信id
		 * @param id           必选 int 红包活动活码id
		 * @param name         可选 string 客户姓名
		 * @param status       可选 int 领取状态-1全部0待领取1已领取2已过期3已领完
		 * @param user_ids     可选 array 成员
		 * @param s_date       可选 string 开始日期
		 * @param e_date       可选 string 结束日期
		 * @param page         可选 int 页码
		 * @param page_size    可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    redpacketName string 活动名称
		 * @return_param    count int 数据条数
		 * @return_param    list array 数据信息
		 * @return_param    list.create_time string 添加时间
		 * @return_param    list.send_money string 发放金额
		 * @return_param    list.statusName string 领取状态
		 * @return_param    list.name_convert string 客户姓名
		 * @return_param    list.gender string 客户性别
		 * @return_param    list.avatar string 客户头像
		 * @return_param    list.userName string 员工名称
		 * @return_param    list.departName string 部门名称
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionRedpacketSendList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$id       = \Yii::$app->request->post('id', 0);
			$name     = \Yii::$app->request->post('name', '');
			$status   = \Yii::$app->request->post('status', '-1');
			$user_ids = \Yii::$app->request->post('user_ids', []);
			$s_date   = \Yii::$app->request->post('s_date', '');
			$e_date   = \Yii::$app->request->post('e_date', '');
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 15;

			if (empty($id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}

			$wayRedpacket = WorkContactWayRedpacket::findOne($id);
			if (empty($wayRedpacket)) {
				throw new InvalidParameterException('活动参数不正确！');
			}

			$sendData = WorkExternalContactFollowUser::find()->alias('fu');
			$sendData = $sendData->leftJoin('{{%work_external_contact}} we', '`fu`.`external_userid` = `we`.`id`');
			$sendData = $sendData->leftJoin('{{%work_contact_way_redpacket_send}} rs', '`rs`.`external_userid` = `we`.`id` and `is_send`=1 and rs.way_id=' . $wayRedpacket->id);
			$sendData = $sendData->where(['we.corp_id' => $this->corp->id, 'fu.way_redpack_id' => $wayRedpacket->id]);

			/*$sendData = WorkContactWayRedpacketSend::find()->alias('rs');
			$sendData = $sendData->leftJoin('{{%work_external_contact}} we', '`rs`.`external_userid` = `we`.`id`');
			$sendData = $sendData->where(['rs.corp_id' => $this->corp->id, 'rs.way_id' => $wayRedpacket->id]);*/

			if (!empty($name)) {
				$sendData = $sendData->andWhere(['like', 'we.name_convert', $name]);
			}
			if ($status != '-1') {
				if ($status == 0){
					$sendData = $sendData->andWhere(['rs.status' => [0, 2]]);
				}else{
					$sendData = $sendData->andWhere(['rs.status' => $status]);
				}
			} else {
				//$sendData = $sendData->andWhere(['rs.status' => [0, 1, 2, 3, 4]]);
			}
			if ($user_ids) {
				$sendData = $sendData->andWhere(['rs.user_id' => $user_ids]);
			}
			if (!empty($s_date) && !empty($e_date)) {
				$sendData = $sendData->andFilterWhere(['between', 'fu.createtime', strtotime($s_date), strtotime($e_date)]);
			}

			$count = $sendData->groupBy('`fu`.`id`')->count();

			$offset   = ($page - 1) * $pageSize;
			$sendData = $sendData->limit($pageSize)->offset($offset);
			$sendData = $sendData->select('fu.createtime,fu.external_userid,rs.status,rs.send_money,fu.user_id,rs.msg,we.name_convert,we.gender,we.avatar')->groupBy('`fu`.`id`')->orderBy(['fu.createtime' => SORT_DESC])->asArray()->all();

			//高级属性搜索
			$fieldInfo = CustomField::findOne(['is_define' => 0, 'key' => 'sex']);//性别属性

			foreach ($sendData as $k => $v) {
				$fieldValue = CustomFieldValue::findOne(['type' => 1, 'cid' => $v['external_userid'], 'fieldid' => $fieldInfo->id]);
				if ($fieldValue->value == '男') {
					$gender = 1;
				} elseif ($fieldValue->value == '女') {
					$gender = 2;
				} else {
					$gender = 0;
				}
				$sendData[$k]['gender']      = $gender;
				$sendData[$k]['create_time'] = date('Y-m-d H:i:s', $v['createtime']);
				$sendData[$k]['send_money']  = sprintf("%.2f", $v['send_money']);
				$statusName                  = '--';
				switch ($v['status']) {
					case 0:
						$statusName = '未领取';
						break;
					case 1:
						$statusName = '已领取';
						break;
					case 2:
						$statusName = '未领取';
						break;
					case 3:
						$statusName = '已领完';
						break;
					case 4:
						$statusName = '领取失败(' . $v['msg'] . ')';
						break;
				}
				$sendData[$k]['statusName'] = $statusName;
				$workUser                   = WorkUser::findOne($v['user_id']);
				$departName                 = WorkDepartment::getDepartNameByUserId($v['user_id']);
				$sendData[$k]['userName']   = !empty($workUser) ? $workUser->name : '--';
				$sendData[$k]['departName'] = $departName;
			}

			return [
				'redpacketName' => $wayRedpacket->name,
				'count'         => $count,
				'list'          => $sendData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           红包统计今日数据
		 * @description     红包统计今日数据
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/redpacket-send-today
		 *
		 * @param corp_id      必选 string 企业微信id
		 * @param id           必选 int 红包活动id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    redpacketName string 活动名称
		 * @return_param    newMemberToday int 今日新增拉新
		 * @return_param    newMemberAll int 全部拉新
		 * @return_param    receiveTodaySum int 今日领取金额
		 * @return_param    receiveTodayNum int 今日领取笔数
		 * @return_param    receiveAllSum int 全部领取笔数
		 * @return_param    receiveAllNum int 全部领取笔数
		 * @return_param    notReceiveTodaySum int 今日未领取金额
		 * @return_param    notReceiveTodayNum int 今日未领取笔数
		 * @return_param    notReceiveAllSum int 全部未领取笔数
		 * @return_param    notReceiveAllNum int 全部未领取笔数
		 * @return_param    expiredTodaySum int 今日过期金额
		 * @return_param    expiredTodayNum int 今日过期笔数
		 * @return_param    expiredAllSum int 全部过期笔数
		 * @return_param    expiredAllNum int 全部过期笔数
		 * @return_param    redpacketAmount int 总投放金额
		 * @return_param    leftAmount int 剩余金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionRedpacketSendToday ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$id = \Yii::$app->request->post('id', 0);

			if (empty($id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}

			$wayRedpacket = WorkContactWayRedpacket::findOne($id);
			if (empty($wayRedpacket)) {
				throw new InvalidParameterException('活动参数不正确！');
			}

			$todayTime     = strtotime(date('Y-m-d'));
			$yesterdayTime = time() - 86400;
			$field         = 'sum(send_money) send_sum, count(id) send_num';

			//今日新增拉新
			$newMemberToday = WorkExternalContactFollowUser::find()->where(['way_redpack_id' => $id])->andWhere(['>', 'createtime', $todayTime])->count();
			//全部拉新
			$newMemberAll = WorkExternalContactFollowUser::find()->where(['way_redpack_id' => $id])->count();
			//今日领取
			$receiveToday    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'status' => 1])->andWhere(['>', 'send_time', $todayTime]);
			$receiveToday    = $receiveToday->select($field)->groupBy('way_id')->asArray()->all();
			$receiveTodaySum = !empty($receiveToday[0]['send_sum']) ? $receiveToday[0]['send_sum'] : '0.00';
			$receiveTodayNum = !empty($receiveToday[0]['send_num']) ? $receiveToday[0]['send_num'] : 0;
			//全部领取
			$receiveAll    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'status' => 1]);
			$receiveAll    = $receiveAll->select($field)->groupBy('way_id')->asArray()->all();
			$receiveAllSum = !empty($receiveAll[0]['send_sum']) ? $receiveAll[0]['send_sum'] : '0.00';
			$receiveAllNum = !empty($receiveAll[0]['send_num']) ? $receiveAll[0]['send_num'] : 0;
			//今日未领取
			$notReceiveToday    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'is_send' => 1, 'status' => [0, 2]])->andWhere(['>', 'create_time', $yesterdayTime]);
			$notReceiveToday    = $notReceiveToday->select($field)->groupBy('way_id')->asArray()->all();
			$notReceiveTodaySum = !empty($notReceiveToday[0]['send_sum']) ? $notReceiveToday[0]['send_sum'] : '0.00';
			$notReceiveTodayNum = !empty($notReceiveToday[0]['send_num']) ? $notReceiveToday[0]['send_num'] : 0;
			//全部未领取
			$notReceiveAll    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'is_send' => 1, 'status' => [0, 2]]);
			$notReceiveAll    = $notReceiveAll->select($field)->groupBy('way_id')->asArray()->all();
			$notReceiveAllSum = !empty($notReceiveAll[0]['send_sum']) ? $notReceiveAll[0]['send_sum'] : '0.00';
			$notReceiveAllNum = !empty($notReceiveAll[0]['send_num']) ? $notReceiveAll[0]['send_num'] : 0;
			/*//今日已过期
			$expiredToday    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'status' => 2])->andWhere(['>', 'update_time', $todayTime]);
			$expiredToday    = $expiredToday->select($field)->groupBy('way_id')->asArray()->all();
			$expiredTodaySum = !empty($expiredToday[0]['send_sum']) ? $expiredToday[0]['send_sum'] : '0.00';
			$expiredTodayNum = !empty($expiredToday[0]['send_num']) ? $expiredToday[0]['send_num'] : 0;
			//全部已过期
			$expiredAll    = WorkContactWayRedpacketSend::find()->andWhere(['corp_id' => $this->corp->id, 'way_id' => $id, 'status' => 2]);
			$expiredAll    = $expiredAll->select($field)->groupBy('way_id')->asArray()->all();
			$expiredAllSum = !empty($expiredAll[0]['send_sum']) ? $expiredAll[0]['send_sum'] : '0.00';
			$expiredAllNum = !empty($expiredAll[0]['send_num']) ? $expiredAll[0]['send_num'] : 0;*/
			//金额
			$redpacketAmount = $wayRedpacket->redpacket_amount;
			$leftAmount      = sprintf('%.2f', $wayRedpacket->redpacket_amount - $wayRedpacket->out_amount);

			return [
				'redpacketName'      => $wayRedpacket->name,
				'newMemberToday'     => $newMemberToday,
				'newMemberAll'       => $newMemberAll,
				'receiveTodaySum'    => $receiveTodaySum,
				'receiveTodayNum'    => $receiveTodayNum,
				'receiveAllSum'      => $receiveAllSum,
				'receiveAllNum'      => $receiveAllNum,
				'notReceiveTodaySum' => $notReceiveTodaySum,
				'notReceiveTodayNum' => $notReceiveTodayNum,
				'notReceiveAllSum'   => $notReceiveAllSum,
				'notReceiveAllNum'   => $notReceiveAllNum,
				/*'expiredTodaySum'    => $expiredTodaySum,
				'expiredTodayNum'    => $expiredTodayNum,
				'expiredAllSum'      => $expiredAllSum,
				'expiredAllNum'      => $expiredAllNum,*/
				'redpacketAmount'    => $redpacketAmount,
				'leftAmount'         => $leftAmount,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-redpacket/
		 * @title           红包发放统计
		 * @description     红包发放统计
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-redpacket/redpacket-send-statistic
		 *
		 * @param corp_id        必选 string 企业唯一标志
		 * @param id             必选 int 红包活动id
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
		 * @return_param    redData array 详细数据列表
		 * @return_param    redData.time string 时间
		 * @return_param    redData.add_num int 统计数据
		 * @return_param    xData array X轴数据
		 * @return_param    seriesData array Y轴数据
		 * @return_param    url string 导出时使用
		 * @return_param    newMember int 拉新人数
		 * @return_param    receiveSum int 领取金额
		 * @return_param    receiveNum int 领取人数
		 * @return_param    notReceiveSum int 未领取金额
		 * @return_param    notReceiveNum int 未领取人数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/09/29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionRedpacketSendStatistic ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id', 0);
				$s_date    = \Yii::$app->request->post('s_date');
				$e_date    = \Yii::$app->request->post('e_date');
				$s_week    = \Yii::$app->request->post('s_week');
				$type      = \Yii::$app->request->post('type', 1); //天
				$is_export = \Yii::$app->request->post('is_export', 0);

				if (empty($this->corp) || empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$wayRedpacket = WorkContactWayRedpacket::findOne($id);
				if (empty($wayRedpacket)) {
					throw new InvalidParameterException('活动参数不正确！');
				}
				$corp_id = $this->corp['id'];
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}

				$result = WorkContactWayRedpacketSend::getRedpacketSendStatistic($corp_id, $id, $type, $s_date, $e_date, $s_week);

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
					$columns  = ['time', 'new_member', 'receive_sum', 'receive_num', 'not_receive_sum', 'not_receive_num'];
					$headers  = [
						'time'            => '时间',
						'new_member'      => '拉新人数（人）',
						'receive_sum'     => '领取金额（元）',
						'receive_num'     => '领取人数（人）',
						'not_receive_sum' => '未领取金额（元）',
						'not_receive_num' => '未领取人数（人）',
					];
					$fileName = '【' . $wayRedpacket->name . '】_' . date("YmdHis", time());
					Excel::export([
						'models'       => array_reverse($result['data']),//数库
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
					'chatData'      => $result['data'],
					'xData'         => $result['xData'],
					'seriesData'    => $result['seriesData'],
					'newMember'     => $result['newMember'],
					'receiveNum'    => $result['receiveNum'],
					'notReceiveNum' => $result['notReceiveNum'],
					'receiveSum'    => $result['receiveSum'],
					'notReceiveSum' => $result['notReceiveSum'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}


	}