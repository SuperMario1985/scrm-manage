<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/10/27
	 * Time: 16:41
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\ExternalTimeLine;
	use app\models\UserCorpRelation;
	use app\models\WorkExternalContact;
	use app\models\WorkGroupClockActivity;
	use app\models\WorkGroupClockDetail;
	use app\models\WorkGroupClockJoin;
	use app\models\WorkGroupClockPrize;
	use app\models\WorkGroupClockTask;
	use app\models\WorkUser;
	use app\models\WorkCorp;
	use app\modules\api\components\BaseController;
	use app\queue\SyncClockJob;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;

	class WapClockActivityController extends BaseController
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
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           群打卡H5接口
		 * @description     群打卡H5接口
		 * @method   post
		 * @url http://{host_name}/api/wap-clock-activity/wap-detail
		 *
		 * @param corp_id 必选 string 企业微信ID
		 * @param assist 必选 string 参数
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 * @param userid 必选 string 员工ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    nick_name string 昵称
		 * @return_param    avatar string 头像
		 * @return_param    days string 签到天数
		 * @return_param    rule string 活动说明
		 * @return_param    type string 类型：1永久有效、2固定区间
		 * @return_param    choose_type string 打卡类型：1连续打卡、2累计打卡
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    send string 0未签到1已签到
		 * @return_param    task array 任务阶段
		 * @return_param    task.days string 签到天数
		 * @return_param    task.type string 奖品类型：1实物、2红包
		 * @return_param    task.reward_name string 奖品名称
		 * @return_param    task.money_amount string 红包金额
		 * @return_param    task.last_days string 还差多少天
		 * @return_param    is_address string 是否显示地址按钮
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/29 13:25
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWapDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			\Yii::error(\Yii::$app->request->post(), 'postData0');
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$activityId      = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$userid          = \Yii::$app->request->post('userid', '');
			if ($openid == 'null') {//兼容前端的传值
				$openid = '';
			}
			if ($external_userid == 'null') {//兼容前端的传值
				$external_userid = '';
			}
			if ($userid == 'null') {//兼容前端的传值
				$userid = '';
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($activityId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($external_userid) && empty($openid) && empty($userid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$clockAct = WorkGroupClockActivity::findOne($activityId);
			if (empty($clockAct)) {
				throw new InvalidParameterException('当前活动不存在！');
			}

			$time        = time();
			$nickName    = ''; //昵称
			$avatar      = ''; //头像
			$title       = $clockAct->title;
			$rule        = $clockAct->rule;
			$type        = $clockAct->type;
			$start_time  = date('Y-m-d', $clockAct->start_time);
			$end_time    = date('Y-m-d', $clockAct->end_time);
			$choose_type = $clockAct->choose_type;
			$joinId      = 0;
			$send        = 0;//未签到
			$task        = []; //任务阶段
			$sTime       = strtotime(date("Y-m-d"));
			$eTime       = $sTime + 86400;
			if (!empty($external_userid)) {
				$externalContact = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (!empty($externalContact)) {
					$nickName        = $externalContact->name;
					$avatar          = $externalContact->avatar;
					$external_userid = $externalContact->id;
				} else {
					throw new InvalidParameterException('客户数据错误！');
				}
			}
			if (!empty($userid)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				if (!empty($workUser)) {
					$nickName = $workUser->name;
					$avatar   = $workUser->avatar;
				}
			}
			$date   = [];
			$status = 0;//未发布

			//活动状态
			if ($clockAct->type == 1) {
				$status = $clockAct->status;
			} else {
				if ($time < $clockAct->start_time) {
					$status = 2;//未开始
				}
				if (in_array($clockAct->status, [0, 1]) && $time >= $clockAct->start_time && $time <= $clockAct->end_time) {
					$status = 1;//进行中
					if ($clockAct->status == 0) {
						$clockAct->status = 1;
						$clockAct->save();
					}
				}
				if ($time >= $clockAct->end_time) {
					$status           = 3;  //已结束
					$clockAct->status = 2;
					$clockAct->save();
					\Yii::$app->queue->push(new SyncClockJob([
						'activityId' => $clockAct->id,
						'corpId'     => $clockAct->corp_id,
					]));
				}
			}

			if ($clockAct->status == 2 || $clockAct->status == 3) {
				$status = 3;//已结束
			}
			if ($clockAct->is_del == 1) {
				$status = 4;//已删除
			}

			if (!empty($external_userid) && empty($userid)) {
				$join = WorkGroupClockJoin::findOne(['activity_id' => $activityId, 'external_id' => $external_userid]);
				//当是外部联系人时并且有openid，补充数据
				if (empty($join) && !empty($externalContact) && !empty($openid)) {
					if ($externalContact->openid == $openid) {
						$join = WorkGroupClockJoin::findOne(['activity_id' => $activityId, 'openid' => $openid]);
						if (!empty($join) && empty($join->external_id)) {
							$join->external_id = $externalContact->id;
							$join->update();
							\Yii::$app->queue->push(new SyncClockJob([
								'externalId' => $externalContact->id,
								'joinId'     => $join->id,
							]));
						}
					}
				}
				if (!empty($join)) {
					$joinId = $join->id;
					//当前客户今天是否已签到
					$send = WorkGroupClockDetail::isSend($join->id, $sTime, $eTime);
					\Yii::error($joinId, '$joinId');
					$date = WorkGroupClockDetail::getData($joinId);
				}
			}

			if (empty($external_userid) && !empty($openid)) {
				$join = WorkGroupClockJoin::findOne(['activity_id' => $activityId, 'openid' => $openid]);
				if (!empty($join)) {
					$joinId = $join->id;
					//当前客户今天是否已签到
					$send = WorkGroupClockDetail::isSend($join->id, $sTime, $eTime);
					$date = WorkGroupClockDetail::getData($joinId);
				}
			}

			$continueDays = !empty($join) ? $join->continue_days : 0;
			$totalDays    = !empty($join) ? $join->total_days : 0;
			if ($choose_type == 1) {
				$days = $continueDays;
			} else {
				$days = $totalDays;
			}
			$isAddress = 0;//是否显示地址按钮
			if (!empty($join)) {
				//获取当前奖品最高签到记录
				$prizeDay  = 0;
				$prizeInfo = WorkGroupClockPrize::find()->where(['join_id' => $join->id])->orderBy(['days' => SORT_DESC])->one();
				if (!empty($prizeInfo)) {
					$prizeDay = $prizeInfo->days;
				}

				$clockTask = WorkGroupClockTask::find()->alias('ct');
				$clockTask = $clockTask->leftJoin('{{%work_group_clock_prize}} cp', 'ct.id = cp.task_id and cp.join_id=' . $join->id);
				$clockTask = $clockTask->where(['ct.activity_id' => $activityId]);
				$clockTask = $clockTask->andWhere(['or', ['ct.is_open' => 1], ['!=', 'cp.id', '']]);
				$clockTask = $clockTask->select('ct.id,ct.days,ct.type,ct.is_open,ct.reward_type,ct.reward_name,ct.money_amount,cp.id prize_id,cp.send,cp.days prize_day,cp.reward_name prize_name,cp.money_amount prize_money');
				$clockTask = $clockTask->asArray()->all();
				if (!empty($clockTask)) {
					foreach ($clockTask as $key => $val) {
						if (empty($val['prize_id']) && ($val['days'] <= $prizeDay)) {
							continue;
						}
						if ($choose_type == 1) {
							$lastDays = ($val['days'] - $join->continue_days) > 0 ? $val['days'] - $join->continue_days : 0;
						} else {
							$lastDays = ($val['days'] - $join->total_days) > 0 ? $val['days'] - $join->total_days : 0;
						}
						if (empty($val['prize_id']) && empty($lastDays)) {
							continue;
						}
						if (empty($val['prize_id'])) {
							$taskDays    = $val['days'];
							$rewardName  = $val['reward_name'];
							$moneyAmount = $val['money_amount'];
						} else {
							$taskDays    = $val['prize_day'];
							$rewardName  = $val['prize_name'];
							$moneyAmount = $val['prize_money'];
						}
						$task[$key]['days']         = $taskDays;
						$task[$key]['type']         = $val['type'];
						$task[$key]['reward_name']  = $rewardName;
						$task[$key]['money_amount'] = $moneyAmount;
						$task[$key]['last_days']    = $lastDays; //还差多少天
						$prizeStatus                = 0; //未获得奖品
						if (!empty($val['prize_id'])) {
							$prizeStatus = 1;
							if (!empty($val['send'])) {
								$prizeStatus = 2;
							}
							if ($val['reward_type'] == 2) {
								$isAddress = 1;
							}
						}
						$task[$key]['status'] = $prizeStatus;
					}
					$task = array_values($task);
				}
			} else {
				$clockTask = WorkGroupClockTask::find()->where(['activity_id' => $activityId, 'is_open' => 1])->all();
				if (!empty($clockTask)) {
					/** @var WorkGroupClockTask $val */
					foreach ($clockTask as $key => $val) {
						$task[$key]['days']         = $val->days;
						$task[$key]['type']         = $val->type;
						$task[$key]['reward_name']  = $val->reward_name;
						$task[$key]['money_amount'] = $val->money_amount;
						if ($choose_type == 1) {
							$lastDays = ($val->days - $continueDays) > 0 ? $val->days - $continueDays : 0;
						} else {
							$lastDays = ($val->days - $totalDays) > 0 ? $val->days - $totalDays : 0;
						}
						$task[$key]['last_days'] = $lastDays; //还差多少天
						$task[$key]['status']    = 0;
					}
				}
			}

			$info['status']      = $status;
			$info['nick_name']   = $nickName;
			$info['avatar']      = $avatar;
			$info['days']        = $days;//连续签到天数
			$info['date']        = $date;
			$info['rule']        = $rule;
			$info['type']        = $type;
			$info['choose_type'] = $choose_type;
			$info['start_time']  = $start_time;
			$info['end_time']    = $end_time;
			$info['task']        = $task;
			$info['send']        = $send;
			$info['join_id']     = $joinId;
			$info['title']       = $title;
			$info['is_address']  = $isAddress;

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           点击签到接口
		 * @description     点击签到接口
		 * @method   post
		 * @url http://{host_name}/api/wap-clock-activity/punch-card
		 *
		 * @param assist 必选 string 参数
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/29 14:21
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionPunchCard ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			\Yii::error(\Yii::$app->request->post(), 'postData');
			$activityId      = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$userid          = \Yii::$app->request->post('userid', '');
			if ($openid == 'null') {//兼容前端的传值
				$openid = '';
			}
			if ($external_userid == 'null') {//兼容前端的传值
				$external_userid = '';
			}
			if ($userid == 'null') {//兼容前端的传值
				$userid = '';
			}
			if (empty($external_userid) && empty($openid) && empty($userid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($activityId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (!empty($userid)) {
				throw new InvalidParameterException('员工不允许签到');
			}
			$clockAct = WorkGroupClockActivity::findOne($activityId);
			if (empty($clockAct)) {
				throw new InvalidParameterException('当前活动不存在！');
			}

			if ($clockAct->is_del == 1) {
				throw new InvalidParameterException('当前活动已删除！');
			}
			if ($clockAct->status == 0) {
				throw new InvalidParameterException('当前活动未开始！');
			}
			if ($clockAct->status == 2 || $clockAct->status == 3) {
				throw new InvalidParameterException('当前活动已结束！');
			}
			$time = time();
			if ($clockAct->type == 2) {
				if ($time >= $clockAct->end_time) {
					$clockAct->status = 2;
					$clockAct->save();
					\Yii::$app->queue->push(new SyncClockJob([
						'activityId' => $clockAct->id,
						'corpId'     => $clockAct->corp_id,
					]));
					throw new InvalidParameterException('当前活动已结束！');
				}
			}

			$dayStart = date('Y-m-d');
			if (!empty($external_userid)) {
				$externalContact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $external_userid]);
				if (empty($externalContact)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$external_userid = $externalContact->id;
				$join            = WorkGroupClockJoin::findOne(['activity_id' => $activityId, 'external_id' => $external_userid]);
				if (empty($join)) {
					$joinData['external_id'] = $external_userid;
					$joinData['activity_id'] = $activityId;
					$joinData['openid']      = '';
					$joinId                  = WorkGroupClockJoin::addData($joinData);
				} else {
					$joinId = $join->id;
					$flag   = WorkGroupClockDetail::findOne(['join_id' => $joinId, 'punch_time' => $dayStart]);
					if (!empty($flag)) {
						throw new InvalidParameterException('今日已签到！');
					}
				}
				$cacheKey = 'punch_card_' . $external_userid . '_' . $joinId;
				$hasGot   = !empty(\Yii::$app->cache->get($cacheKey)) ? \Yii::$app->cache->get($cacheKey) : false;
				if (!$hasGot) {
					\Yii::$app->cache->set($cacheKey, true, 5);
					WorkGroupClockDetail::addData($joinId, $time);
				}
			}

			if (empty($external_userid) && !empty($openid)) {
				$join = WorkGroupClockJoin::findOne(['activity_id' => $activityId, 'openid' => $openid]);
				if (empty($join)) {
					$joinData['external_id'] = '';
					$joinData['activity_id'] = $activityId;
					$joinData['openid']      = $openid;
					$joinId                  = WorkGroupClockJoin::addData($joinData);
				} else {
					$joinId = $join->id;
					$flag   = WorkGroupClockDetail::findOne(['join_id' => $joinId, 'punch_time' => $dayStart]);
					if (!empty($flag)) {
						throw new InvalidParameterException('当前已签到！');
					}
				}
				$cacheKey = 'punch_open_card_' . $openid . '_' . $joinId;
				$hasGot   = !empty(\Yii::$app->cache->get($cacheKey)) ? \Yii::$app->cache->get($cacheKey) : false;
				if (!$hasGot) {
					\Yii::$app->cache->set($cacheKey, true, 5);
					WorkGroupClockDetail::addData($joinId, $time);
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           签到后是否获得奖励接口
		 * @description     签到后是否获得奖励接口
		 * @method   post
		 * @url http://{host_name}/api/wap-clock-activity/success
		 *
		 * @param assist 必选 string 参数
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    success string 1获的奖励0没有
		 * @return_param    choose_type string 打卡类型：1连续打卡2累计打卡
		 * @return_param    days string 任务天数
		 * @return_param    date string 完成打卡时间
		 * @return_param    type string 默认0、1是奖品、2是金额
		 * @return_param    reward_type string 默认0、奖品方式：1联系客服 2兑换链接
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/19 19:34
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionSuccess ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			$activityId      = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$userid          = \Yii::$app->request->post('userid', '');
			if ($openid == 'null') {//兼容前端的传值
				$openid = '';
			}
			if ($external_userid == 'null') {//兼容前端的传值
				$external_userid = '';
			}
			if ($userid == 'null') {//兼容前端的传值
				$userid = '';
			}
			if (empty($external_userid) && empty($openid) && empty($userid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($activityId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (!empty($userid)) {
				throw new InvalidParameterException('员工不允许签到');
			}
			$clockAct = WorkGroupClockActivity::findOne($activityId);
			if (empty($clockAct)) {
				throw new InvalidParameterException('当前活动不存在！');
			}
			if ($clockAct->status == 0) {
				throw new InvalidParameterException('当前活动未开始！');
			}
			if ($clockAct->status == 2 || $clockAct->status == 3) {
				throw new InvalidParameterException('当前活动已结束！');
			}
			$success     = 0;
			$days        = 0;
			$date        = '';
			$taskId      = 0;
			$qrCode      = '';
			$choose_type = $clockAct->choose_type;
			$day         = 0;
			$joinId      = 0;
			$type        = 0;
			$rewardType  = 0;
			$rewardName  = '';
			$moneyAmount = '';
			if ($choose_type == 1) {
				//连续签到
				if (!empty($external_userid)) {
					$workContact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $external_userid]);
					if (empty($workContact)) {
						throw new InvalidParameterException('客户数据错误！');
					}
					$clockDay  = WorkGroupClockJoin::getDays($activityId, $workContact->id, '', 0);
					$day       = $clockDay['choose_days'];
					$joinId    = $clockDay['join_id'];
					$clockJoin = $clockDay['clock_join'];
				} else {
					//未知客户
					$clockDay  = WorkGroupClockJoin::getDays($activityId, '', $openid, 0);
					$day       = $clockDay['choose_days'];
					$joinId    = $clockDay['join_id'];
					$clockJoin = $clockDay['clock_join'];
				}
			} else {
				//累计签到
				if (!empty($external_userid)) {
					$workContact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $external_userid]);
					$clockDay    = WorkGroupClockJoin::getDays($activityId, $workContact->id, '', 1);
					$day         = $clockDay['choose_days'];
					$joinId      = $clockDay['join_id'];
					$clockJoin   = $clockDay['clock_join'];
				} else {
					//未知客户
					$clockDay  = WorkGroupClockJoin::getDays($activityId, '', $openid, 1);
					$day       = $clockDay['choose_days'];
					$joinId    = $clockDay['join_id'];
					$clockJoin = $clockDay['clock_join'];
				}
			}

			//获取今日之前奖品最高签到记录
			$prizeDay  = 0;
			$yesTime   = strtotime(date('Y-m-d'));
			$prizeInfo = WorkGroupClockPrize::find()->where(['join_id' => $joinId])->andWhere(['<', 'create_time', $yesTime])->orderBy(['days' => SORT_DESC])->one();
			if (!empty($prizeInfo)) {
				$prizeDay = $prizeInfo->days;
			}

			$clockTask = WorkGroupClockTask::find()->where(['activity_id' => $clockAct->id, 'is_open' => 1])->all();
			if (!empty($clockTask)) {
				/** @var WorkGroupClockTask $task */
				foreach ($clockTask as $task) {
					if ($task->days == $day && ($task->days > $prizeDay)) {
						$days   = $task->days;
						$taskId = $task->id;
						$date   = date('Y-m-d H:i');
						$prize  = WorkGroupClockPrize::findOne(['join_id' => $joinId, 'task_id' => $task->id]);
						if (empty($prize)) {
							$success              = 1;
							$data['join_id']      = $joinId;
							$data['task_id']      = $task->id;
							$data['days']         = $task->days;
							$data['type']         = $task->type;
							$data['money_amount'] = $task->money_amount;
							$data['reward_name']  = $task->reward_name;
							$prizeInfo            = WorkGroupClockPrize::addData($data, $external_userid, $openid);
						}
						$type        = $task->type;
						$rewardType  = $task->reward_type;
						$qrCode      = $task->qr_code;
						$rewardName  = $task->reward_name;
						$moneyAmount = $task->money_amount;
					}
				}
			}
			//打卡轨迹
			$startTime = strtotime(date('Y-m-d'));
			$emdTime   = $startTime + 86400;
			$timeLine  = ExternalTimeLine::find()->where(['event' => 'punch_card', 'event_id' => $joinId])->andWhere(['between', 'event_time', $startTime, $emdTime])->one();
			if (empty($timeLine) && !empty($clockJoin)) {
				$clockName = $clockAct->title;
				/**@var WorkGroupClockJoin $clockJoin * */
				if (!empty($clockJoin->external_id)) {
					$contact  = WorkExternalContact::findOne($clockJoin->external_id);
					$nickname = !empty($contact) ? $contact->name : '未知客户';
				} else {
					$nickname = '未知客户';
				}
				$remark = '【' . $nickname . '】参与【' . $clockName . '】打卡';
				/**@var WorkGroupClockPrize $prizeInfo * */
				if (!empty($prizeInfo)) {
					$prizeId = $prizeInfo->id;
					if ($choose_type == 1) {
						$remark .= '，连续打卡' . $clockJoin->continue_days . '天';
					} else {
						$remark .= '，累计总打卡' . $clockJoin->total_days . '天';
					}
					if ($prizeInfo->type == 1) {
						$remark .= '，获得【' . $prizeInfo->reward_name . '】';
					} else {
						$remark .= '，获得【' . $prizeInfo->money_amount . '元红包】';
					}
				} else {
					$prizeId = 0;
					$remark  .= '，已连续打卡' . $clockJoin->continue_days . '天，累计总打卡' . $clockJoin->total_days . '天';
				}

				ExternalTimeLine::addExternalTimeLine(['external_id' => $clockJoin->external_id, 'openid' => $clockJoin->openid, 'event' => 'punch_card', 'event_id' => $clockJoin->id, 'related_id' => $prizeId, 'remark' => $remark]);
			}

			return [
				'success'     => $success,
				'choose_type' => $choose_type,
				'days'        => $days,
				'date'        => $date,
				'task_id'     => $taskId,
				'type'        => $type,
				'reward_type' => $rewardType,
				'reward_name' => $rewardName,
				'money_amount'=> $moneyAmount,
				'qr_code'     => $qrCode,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           获取我的地址
		 * @description     获取我的地址
		 * @method   post
		 * @url  http://{host_name}/api/wap-clock-activity/get-address
		 *
		 * @param assist 必选 string 活动id
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: win7. Date: 2020-11-27 10:24
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAddress ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$activityId     = \Yii::$app->request->post('assist', '');
			$externalUserId = \Yii::$app->request->post('external_userid', '');
			$openId         = \Yii::$app->request->post('openid', '');
			if (empty($activityId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$clockAct = WorkGroupClockActivity::findOne($activityId);
			if (empty($clockAct)) {
				throw new InvalidDataException('暂无此活动');
			}
			if (empty($externalUserId) && empty($openId)) {
				throw new InvalidParameterException('参数不正确');
			}
			if (!empty($externalUserId)) {
				$workContact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $externalUserId]);
				if (!empty($workContact)) {
					$clockJoin = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'external_id' => $workContact->id]);
				}
			} else {
				$clockJoin = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'openid' => $openId]);
			}
			$name = $mobile = $region = $city = $county = $detail = $remark = '';
			if (!empty($clockJoin)) {
				$name   = $clockJoin->name;
				$mobile = $clockJoin->mobile;
				$region = $clockJoin->region;
				$city   = $clockJoin->city;
				$county = $clockJoin->county;
				$detail = $clockJoin->detail;
				$remark = $clockJoin->remark;
			}
			$data = [
				'name'   => $name,
				'mobile' => $mobile,
				'region' => $region,
				'city'   => $city,
				'county' => $county,
				'detail' => $detail,
				'remark' => $remark,
			];

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           填写地址接口
		 * @description     填写地址接口
		 * @method   post
		 * @url  http://{host_name}/api/wap-clock-activity/sub-address
		 *
		 * @param assist 必选 string 参数
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 * @param name 必选 string 名称
		 * @param mobile 必选 string 手机号
		 * @param region 必选 string 省
		 * @param city 必选 string 市
		 * @param county 必选 string 区/县
		 * @param detail 必选 string 详情
		 * @param remark 可选 string 备注
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/30 15:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSubAddress ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			$activityId      = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$name            = \Yii::$app->request->post('name', '');
			$mobile          = \Yii::$app->request->post('mobile', '');
			$region          = \Yii::$app->request->post('region', '');
			$city            = \Yii::$app->request->post('city', '');
			$county          = \Yii::$app->request->post('county', '');
			$detail          = \Yii::$app->request->post('detail', '');
			$remark          = \Yii::$app->request->post('remark', '');
			if (empty($activityId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($external_userid) && empty($openid)) {
				throw new InvalidParameterException('参数不正确');
			}
			if (empty($name)) {
				throw new InvalidParameterException('姓名不能为空');
			} elseif (mb_strlen($name, 'utf-8') > 32) {
				throw new InvalidParameterException('姓名不能超过32个字！');
			}
			if (empty($mobile)) {
				throw new InvalidParameterException('手机号不能为空');
			} elseif (!preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $mobile)) {
				throw new InvalidParameterException('请输入正确的手机号');
			}
			if (empty($region)) {
				throw new InvalidParameterException('省不能为空');
			}
			if (empty($city)) {
				throw new InvalidParameterException('市不能为空');
			}
			if (empty($county)) {
				throw new InvalidParameterException('区/县不能为空');
			}
			if (empty($detail)) {
				throw new InvalidParameterException('详细地址不能为空');
			}
			if (!empty($detail) && mb_strlen($detail, 'utf-8') > 100) {
				throw new InvalidParameterException('详细地址不能超过100个字！');
			}
			$clockAct = WorkGroupClockActivity::findOne($activityId);
			if (!empty($clockAct)) {
				if (!empty($external_userid)) {
					$workContact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $external_userid]);
					if (!empty($workContact)) {
						$join = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'external_id' => $workContact->id]);
					}
				} else {
					$join = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'openid' => $openid]);
				}
				if (!empty($join)) {
					$join->name   = $name;
					$join->mobile = $mobile;
					$join->region = $region;
					$join->city   = $city;
					$join->county = $county;
					$join->detail = $detail;
					$join->remark = $remark;
					if (!$join->save()) {
						throw new InvalidDataException(SUtils::modelError($join));
					}
				} else {
					throw new InvalidDataException('暂无此参与人，无法修改地址');
				}
			} else {
				throw new InvalidParameterException('活动不存在！');
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-clock-activity/
		 * @title           签到排行榜
		 * @description     签到排行榜
		 * @method   post
		 * @url  http://{host_name}/api/wap-clock-activity/ranking
		 *
		 * @param assist 必选 string 参数
		 * @param external_userid 必选 string 外部联系人ID
		 * @param openid 必选 string 未知客户
		 * @param page 可选 int 当前页
		 * @param page_size 可选 int 页数默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 昵称
		 * @return_param    avatar string 头像
		 * @return_param    days string 天数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/30 17:32
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionRanking ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			$activityId      = \Yii::$app->request->post('assist', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$openid          = \Yii::$app->request->post('openid', '');
			$page            = \Yii::$app->request->post('page') ?: 1;
			$pageSize        = \Yii::$app->request->post('page_size') ?: 15;
			$clockAct        = WorkGroupClockActivity::findOne($activityId);
			if (empty($external_userid) && empty($openid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (!empty($clockAct)) {
				$join   = WorkGroupClockJoin::find()->where(['activity_id' => $clockAct->id]);
				$offset = ($page - 1) * $pageSize;
				$count  = $join->count();
				if ($clockAct->choose_type == 1) {
					$order = ['continue_days' => SORT_DESC, 'last_time' => SORT_DESC, 'id' => SORT_DESC];
				} else {
					$order = ['total_days' => SORT_DESC, 'last_time' => SORT_DESC, 'id' => SORT_DESC];
				}
				$join = $join->orderBy($order);
				$info = [];
				if (!empty($external_userid)) {
					$contact = WorkExternalContact::findOne(['corp_id' => $clockAct->corp_id, 'external_userid' => $external_userid]);
					if (!empty($contact)) {
						$joinNew = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'external_id' => $contact->id]);
					}
				} else {
					$joinNew = WorkGroupClockJoin::findOne(['activity_id' => $clockAct->id, 'openid' => $openid]);
				}
				$myInfo = [];
				if (!empty($joinNew)) {
					$rank = 0;
					$rankJoin = clone $join;
					$rankList = $rankJoin->select('id')->all();
					if(!empty($rankList)){
						foreach($rankList as $key=>$rankInfo){
							if($rankInfo->id == $joinNew->id){
								$rank = $key + 1;
								break;
							}
						}
					}
					$avatar = '';
					$name   = '未知客户';
					if (!empty($joinNew->external_id)) {
						$externalContact = WorkExternalContact::findOne($joinNew->external_id);
						if (!empty($externalContact)) {
							$avatar = $externalContact->avatar;
							$name   = $externalContact->name;
						}
					}
					if ($clockAct->choose_type == 1) {
						$days = $joinNew->continue_days;
					} else {
						$days = $joinNew->total_days;
					}
					$newInfo['id']   = $joinNew->id;
					$newInfo['name']   = $name;
					$newInfo['avatar'] = $avatar;
					$newInfo['days']   = $days;
					$newInfo['rank']   = $rank;
					array_push($myInfo, $newInfo);
				}
				$i = $offset;
				$join = $join->limit($pageSize)->offset($offset)->all();
				if (!empty($join)) {
					/** @var WorkGroupClockJoin $value */
					foreach ($join as $key => $value) {
						$i++;
						$avatar = '';
						$name   = '未知客户';
						if (!empty($value->external_id)) {
							$externalContact = WorkExternalContact::findOne($value->external_id);
							if (!empty($externalContact)) {
								$avatar = $externalContact->avatar;
								$name   = $externalContact->name;
							}
						}
						if ($clockAct->choose_type == 1) {
							$days = $value->continue_days;
						} else {
							$days = $value->total_days;
						}
						$info[$key]['name']   = $name;
						$info[$key]['avatar'] = $avatar;
						$info[$key]['days']   = $days;
						$info[$key]['rank']   = $i;
					}
				}

				return [
					'info'    => $info,
					'count'   => $count,
					'my_info' => $myInfo
				];

			} else {
				throw new InvalidParameterException('活动不存在！');
			}

		}

	}