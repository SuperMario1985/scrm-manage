<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/10/27
	 * Time: 16:41
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\UserCorpRelation;
	use app\models\WorkExternalContact;
	use app\models\WorkGroupClockActivity;
	use app\models\WorkGroupClockDetail;
	use app\models\WorkGroupClockJoin;
	use app\models\WorkGroupClockPrize;
	use app\models\WorkGroupClockTask;
	use app\models\WorkUser;
	use app\models\WorkCorp;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncClockJob;
	use yii\web\MethodNotAllowedHttpException;

	class WorkGroupClockActivityController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           群打卡添加
		 * @description     群打卡添加
		 * @method   post
		 * @url  http://{host_name}/api/work-group-clock-activity/add
		 *
		 * @param id 必选 int 活动ID默认0
		 * @param corp_id 必选 string 企业微信ID
		 * @param agent_id 必选 int 应用ID
		 * @param title 必选 string 活动名称
		 * @param type 必选 int 活动类型：1永久有效、2固定区间
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param rule 可选 string 活动规则
		 * @param choose_type 可选 int 打卡类型：1连续打卡、2累计打卡
		 * @param task 必选 array 打卡任务
		 * @param task.id 必选 int 任务ID
		 * @param task.days 必选 int 打卡天数
		 * @param task.type 必选 int 奖品类型：1实物、2红包
		 * @param task.reward_name 必选 string 奖品名称
		 * @param task.money_amount 必选 string 红包金额
		 * @param task.reward_type 必选 string 奖品方式：1、联系客服2兑换链接
		 * @param task.user_key 必选 array 客服人员
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/28 11:50
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$data['id']          = \Yii::$app->request->post('id') ?: 0;
				$data['agent_id']    = \Yii::$app->request->post('agent_id');
				$data['title']       = \Yii::$app->request->post('title');
				$data['type']        = \Yii::$app->request->post('type');
				$data['start_time']  = \Yii::$app->request->post('start_time', '');
				$data['end_time']    = \Yii::$app->request->post('end_time', '');
				$data['rule']        = \Yii::$app->request->post('rule');
				$data['choose_type'] = \Yii::$app->request->post('choose_type');
				$data['task']        = \Yii::$app->request->post('task');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$data['corp_id'] = $this->corp->id;
				WorkGroupClockActivity::add($data);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           打卡详情
		 * @description     打卡详情
		 * @method   post
		 * @url  http://{host_name}/api/work-group-clock-activity/detail
		 *
		 * @param id 必选 int 活动ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/28 13:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$clockAct = WorkGroupClockActivity::findOne($id);
				$info     = $clockAct->dumpData();

				return $info;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           打卡活动列表
		 * @description     打卡活动列表
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/list
		 *
		 * @param corp_id 必选 string 企业微信ID
		 * @param id 可选 string 活动ID
		 * @param status 可选 string 状态：-1全部0未开始1进行中2已结束
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param page 可选 string 当前页
		 * @param page_size 可选 string 页码
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 活动名称
		 * @return_param    type string 活动类型：1永久有效、2固定区间
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    rule string 规则
		 * @return_param    num string 打卡人数
		 * @return_param    url string 链接地址
		 * @return_param    choose_type string 打卡类型：1连续打卡、2累计打卡
		 * @return_param    status string 活动状态：0未开始1进行中2已结束
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/28 14:00
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('page_size') ?: 15;
				$id       = \Yii::$app->request->post('id');
				$status   = \Yii::$app->request->post('status');
				$sTime    = \Yii::$app->request->post('start_time');
				$eTime    = \Yii::$app->request->post('end_time');
				$title    = \Yii::$app->request->post('title');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corpId = $this->corp->id;
				$time   = time();
				//自动发布
				WorkGroupClockActivity::updateAll(['status' => 1], ['and', ['corp_id' => $corpId, 'is_del' => 0, 'type' => 2, 'status' => 0], ['<=', 'start_time', $time], ['>=', 'end_time', $time]]);
				//自动结束
				$clockList = WorkGroupClockActivity::find()->where(['corp_id' => $corpId, 'is_del' => 0, 'type' => 2, 'status' => [0, 1]])->andWhere(['<=', 'end_time', $time])->all();
				if (!empty($clockList)) {
					foreach ($clockList as $clock) {
						$clock->status = 2;
						$clock->update();
						\Yii::$app->queue->push(new SyncClockJob([
							'activityId' => $clock->id,
							'corpId'     => $clock->corp_id,
						]));
					}
				}

				$clockAct = WorkGroupClockActivity::find()->where(['corp_id' => $corpId, 'is_del' => 0]);
				if (!empty($id)) {
					$clockAct = $clockAct->andWhere(['id' => $id]);
				}
				if (!empty($title) || $title == '0') {
					$clockAct = $clockAct->andWhere('title like \'%' . $title . '%\'');
				}
				if ($status != []) {
					if ($status == 2) {
						$status = [2, 3];
					}
					$clockAct = $clockAct->andWhere(['status' => $status]);
				}
				if (!empty($sTime) && !empty($eTime)) {
					$sTime    = strtotime($sTime);
					$eTime    = strtotime($eTime . ' 23:59:59');
					$clockAct = $clockAct->andWhere(['and', ['or', ['type' => 1], ['and', ['<=', 'start_time', $sTime], ['>=', 'end_time', $sTime]], ['and', ['>=', 'start_time', $sTime], ['<=', 'end_time', $eTime]], ['and', ['<=', 'start_time', $eTime], ['>=', 'end_time', $eTime]]]]);
				}

				$offset   = ($page - 1) * $pageSize;
				$count    = $clockAct->count();
				$clockAct = $clockAct->limit($pageSize)->offset($offset)->orderBy(['create_time' => SORT_DESC])->all();
				$info     = [];
				if (!empty($clockAct)) {
					/** @var WorkGroupClockActivity $act */
					foreach ($clockAct as $act) {
						array_push($info, $act->dumpData(1, ['is_open' => 1]));
					}
				}

				return [
					'count' => $count,
					'info'  => $info,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           参与人打卡列表
		 * @description     参与人打卡列表
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/join-list
		 *
		 * @param page 可选 string 当前页
		 * @param page_size 可选 string 页码
		 * @param id 必选 string 活动ID
		 * @param name 可选 string 客户昵称
		 * @param write_status 可选 string 是否填写：0全部，1未填写，2已填写
		 * @param send_status 可选 string 是否发放：0全部，1未发放，2已发放
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数量
		 * @return_param    info array 列表数据
		 * @return_param    info.avatar string 头像
		 * @return_param    info.name string 昵称
		 * @return_param    info.s_time string 首次打卡时间
		 * @return_param    info.e_time string 最近打卡时间
		 * @return_param    info.total_days string 总打卡天数
		 * @return_param    info.continue_days string 连续打卡天数
		 * @return_param    task array 奖励
		 * @return_param    task.days string 签到天数
		 * @return_param    task.type string 奖品类型：1实物、2红包
		 * @return_param    task.reward_name string 奖品名称
		 * @return_param    task.money_amount string 红包金额
		 * @return_param    task.last_days string 还差多少天
		 * @return_param    task.is_give string 0带发放1已发放
		 * @return_param    task.get_award string 0未获得奖品1已获得奖品
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/5 10:43
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionJoinList ()
		{
			if (\Yii::$app->request->isPost) {
				$page        = \Yii::$app->request->post('page') ?: 1;
				$pageSize    = \Yii::$app->request->post('page_size') ?: 15;
				$id          = \Yii::$app->request->post('id');
				$name        = \Yii::$app->request->post('name', '');
				$writeStatus = \Yii::$app->request->post('write_status', '');//0全部，1未填写，2已填写
				$sendStatus  = \Yii::$app->request->post('send_status', '');//0全部，1未发放，2已发放
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$activity = WorkGroupClockActivity::findOne($id);
				if (empty($activity)) {
					throw new InvalidParameterException('活动不存在！');
				}

				$clockJoin = WorkGroupClockJoin::find()->alias('cj');
				$clockJoin = $clockJoin->leftJoin('{{%work_group_clock_prize}} cp', 'cj.id = cp.join_id');
				$clockJoin = $clockJoin->leftJoin('{{%work_group_clock_task}} ct', 'ct.id = cp.task_id');
				$clockJoin  = $clockJoin->where(['cj.activity_id' => $id]);
				$externalId = 0;
				if ($name !== '') {
					$contact = WorkExternalContact::find()->where(['like', 'name_convert', $name])->select('id')->asArray()->all();
					if (!empty($contact)) {
						$externalId = array_column($contact, 'id');
					} else {
						return [
							'all_ids'  => [],
							'info'     => [],
							'count'    => 0,
							'taskData' => [],
						];
					}
				}
				if (!empty($externalId)) {
					$clockJoin = $clockJoin->andWhere(['in', 'cj.external_id', $externalId]);
				}
				//是否填写资料
				if (!empty($writeStatus)) {
					if ($writeStatus == 1) {
						$clockJoin = $clockJoin->andWhere(['ct.reward_type' => 2, 'cj.mobile' => '']);
					} else {
						$clockJoin = $clockJoin->andWhere(['and', ['!=', 'cj.mobile', ''], ['ct.reward_type' => 2]]);
					}
				}
				//是否发放
				if (!empty($sendStatus)) {
					if ($sendStatus == 1) {
						$clockJoin = $clockJoin->andWhere(['cp.send' => 0]);
					} else {
						$clockJoin = $clockJoin->andWhere(['cp.send' => 1]);
					}
				}

				$clockJoin = $clockJoin->select('cj.*');
				$clockJoin = $clockJoin->groupBy('cj.id');

				$clockJoinNew = $clockJoin;
				$all_ids      = [];
				$clockJoinNew = $clockJoinNew->all();
				if (!empty($clockJoinNew)) {
					/** @var WorkGroupClockJoin $new */
					foreach ($clockJoinNew as $new) {
						array_push($all_ids, $new->id);
					}
				}
				$count     = $clockJoin->count();
				$offset    = ($page - 1) * $pageSize;
				$clockJoin = $clockJoin->limit($pageSize)->offset($offset)->orderBy(['create_time' => SORT_DESC])->all();
				$info      = [];
				if (!empty($clockJoin)) {
					/**
					 * @var key                $key
					 * @var WorkGroupClockJoin $join
					 */
					foreach ($clockJoin as $key => $join) {
						$avatar    = '';
						$name      = '';
						$corp_name = NULL;
						$gender    = 0;
						$getPrize  = 0;
						if (!empty($join->external_id)) {
							$externalContact = WorkExternalContact::findOne($join->external_id);
							if (!empty($externalContact)) {
								$avatar    = $externalContact->avatar;
								$name      = $externalContact->name;
								$corp_name = $externalContact->corp_name;
								$gender    = $externalContact->gender;
							}
						} else {
							$name = '未知客户';
						}
						$info[$key]['key']           = $join->id;
						$info[$key]['avatar']        = $avatar;
						$info[$key]['name']          = $name;
						$info[$key]['s_time']        = date('Y-m-d H:i', $join->create_time);
						$info[$key]['e_time']        = date('Y-m-d H:i', $join->last_time);
						$info[$key]['corp_name']     = $corp_name;
						$info[$key]['gender']        = $gender;
						$info[$key]['total_days']    = !empty($join->total_days) ? $join->total_days : 0;
						$info[$key]['continue_days'] = !empty($join->history_continue_days) ? $join->history_continue_days : 0;

						//获取当前奖品最高签到记录
						$prizeDay  = 0;
						$prizeInfo = WorkGroupClockPrize::find()->where(['join_id' => $join->id])->orderBy(['days' => SORT_DESC])->one();
						if (!empty($prizeInfo)) {
							$prizeDay = $prizeInfo->days;
						}

						$task      = [];
						$clockTask = WorkGroupClockTask::find()->alias('ct');
						$clockTask = $clockTask->leftJoin('{{%work_group_clock_prize}} cp', 'ct.id = cp.task_id and cp.join_id=' . $join->id);
						$clockTask = $clockTask->where(['ct.activity_id' => $id]);
						$clockTask = $clockTask->andWhere(['or', ['ct.is_open' => 1], ['!=', 'cp.id', '']]);
						$clockTask = $clockTask->select('ct.id,ct.days,ct.type,ct.reward_type,ct.is_open,ct.reward_name,ct.money_amount,cp.id prize_id,cp.send,cp.days prize_day,cp.reward_name prize_name,cp.money_amount prize_money');
						$clockTask = $clockTask->asArray()->all();
						if (!empty($clockTask)) {
							foreach ($clockTask as $k => $val) {
								if (empty($val['prize_id']) && ($val['days'] <= $prizeDay)) {
									continue;
								}
								if ($activity->choose_type == 1) {
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
								$task[$k]['task_id']      = $val['id'];
								$task[$k]['days']         = $taskDays;
								$task[$k]['type']         = $val['type'];
								$task[$k]['reward_name']  = $rewardName;
								$task[$k]['money_amount'] = $moneyAmount;
								$task[$k]['choose_type']  = $activity->choose_type;
								$task[$k]['last_days']    = $lastDays; //还差多少天
								$status                   = 0; //未获得奖品
								$isShow                   = 0; //是否展示
								$isAdd                    = 0; //是否已添加地址
								if (!empty($val['prize_id'])) {
									$status = 1;
									if (!empty($val['send'])) {
										$status = 2;
									} else {
										$getPrize = 1;
									}
									if ($val['type'] == 1 && $val['reward_type'] == 2) {
										$isShow = 1;
										if (!empty($join->name) && !empty($join->mobile) && !empty($join->detail)) {
											$isAdd = 1;
										}
									}
								}
								$task[$k]['status']  = $status;
								$task[$k]['is_show'] = $isShow;
								$task[$k]['is_add']  = $isAdd;
							}
						}
						$info[$key]['task']      = array_values($task);
						$info[$key]['get_prize'] = $getPrize;
					}
				}

				//任务列表
				$taskData = [];
				$taskList = WorkGroupClockTask::find()->where(['activity_id' => $id])->all();
				foreach ($taskList as $key => $taskInfo) {
					$taskData[] = ['task_id' => $taskInfo->id, 'name' => ($key + 1) . '阶奖品'];
				}

				return [
					'all_ids'  => $all_ids,
					'info'     => $info,
					'count'    => $count,
					'taskData' => $taskData,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           地址详情
		 * @description     地址详情
		 * @method   post
		 * @url  http://{host_name}/api/work-group-clock-activity/get-address
		 *
		 * @param join_id 必选 string 参与者id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-11-26 19:45
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAddress ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$joinId = \Yii::$app->request->post('join_id');
			if (empty($joinId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$clockJoin = WorkGroupClockJoin::findOne($joinId);
			if (empty($clockJoin)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$title = '未知客户';
			if (!empty($clockJoin->external_id)) {
				$workContact = WorkExternalContact::findOne($clockJoin->external_id);
				if (!empty($workContact)) {
					$title = rawurldecode($workContact->name);
				}
			}
			$data = [
				'title'  => $title,
				'name'   => $clockJoin->name,
				'mobile' => $clockJoin->mobile,
				'region' => $clockJoin->region,
				'city'   => $clockJoin->city,
				'county' => $clockJoin->county,
				'detail' => $clockJoin->detail,
				'remark' => $clockJoin->remark,
			];

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           单个奖品发放
		 * @description     单个奖品发放
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/give-product
		 *
		 * @param join_id 必选 int 参与者ID
		 * @param task_id 必选 int 任务ID
		 *
		 * @return          bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/4 11:40
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGiveProduct ()
		{
			if (\Yii::$app->request->isPost) {
				$joinId = \Yii::$app->request->post('join_id');
				$taskId = \Yii::$app->request->post('task_id');
				if (empty($joinId) || empty($taskId)) {
					throw new InvalidParameterException('参数错误！');
				}
				$clockJoin = WorkGroupClockJoin::findOne($joinId);
				if (empty($clockJoin)) {
					throw new InvalidParameterException('参数错误！');
				}
				$activity = WorkGroupClockActivity::findOne($clockJoin->activity_id);
				if (empty($activity)) {
					throw new InvalidParameterException('无此活动！');
				}
				$clockTask = WorkGroupClockTask::findOne($taskId);
				if (empty($activity)) {
					throw new InvalidParameterException('无此任务！');
				}
				$clockPrize = WorkGroupClockPrize::findOne(['join_id' => $joinId, 'task_id' => $taskId, 'send' => 0]);
				if (empty($clockPrize)) {
					throw new InvalidParameterException('无此奖励！');
				}
				if (($clockPrize->type == 1)) {
					if (!empty($clockPrize->reward_name)) {
						if ($clockTask->reward_type == 2) {
							if (empty($clockJoin->name) || empty($clockJoin->mobile) || empty($clockJoin->detail)) {
								throw new InvalidParameterException('地址未填写完整，无法发放！');
							}
						}
						$clockPrize->send      = 1;
						$clockPrize->send_time = time();
						$clockPrize->save();
					}
				} elseif ($clockPrize->type == 2) {
					//发放零钱
					if (!empty($clockPrize->money_amount)) {
						//发放零钱到客户
						$userCorp             = UserCorpRelation::findOne(['corp_id' => $activity->corp_id]);
						$orderData['corp_id'] = $activity->corp_id;
						$orderData['remark']  = '打卡成功，已成功发放到零钱';
						$orderData['amount']  = $clockPrize->money_amount;
						$orderData['uid']     = $userCorp->uid;
						$orderData['rid']     = $activity->id;
						$orderData['jid']     = $clockPrize->join_id;
						$orderData['task_id'] = $clockPrize->task_id;
						if (!empty($clockJoin->external_id)) {
							$contact    = WorkExternalContact::findOne($clockJoin->external_id);
							$openid     = !empty($contact) ? $contact->openid : '';
							$externalId = !empty($contact) ? $contact->id : 0;
						} else {
							$openid     = $clockJoin->openid;
							$externalId = '';
						}
						$orderData['openid']      = $openid;
						$orderData['external_id'] = $externalId;
						$result                   = WorkGroupClockPrize::sendChange($orderData);
						if (!empty($result['error'])) {
							throw new InvalidParameterException($result['msg']);
						} else {
							\Yii::$app->queue->delay(5)->push(new SyncClockJob([
								'corpId' => $activity->corp_id,
							]));
						}
					}
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           批量发放
		 * @description     批量发放
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/give-more
		 *
		 * @param activity_id 必选 int 活动ID
		 * @param join_ids 可选 array 人员ID
		 * @param task_id 必选 array 任务ID
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/4 15:30
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGiveMore ()
		{
			if (\Yii::$app->request->isPost) {
				ini_set('memory_limit', '1024M');
				set_time_limit(0);

				$activityId = \Yii::$app->request->post('activity_id');
				$joinIds    = \Yii::$app->request->post('join_ids');
				$taskId     = \Yii::$app->request->post('task_id');
				if (empty($activityId) || empty($taskId)) {
					throw new InvalidParameterException('参数错误！');
				}
				if (empty($joinIds) || !is_array($joinIds)) {
					throw new InvalidParameterException('参数错误！');
				}
				$activity = WorkGroupClockActivity::findOne($activityId);
				if (empty($activity)) {
					throw new InvalidParameterException('无此活动！');
				}
				$clockTask = WorkGroupClockTask::findOne(['activity_id' => $activityId, 'id' => $taskId]);
				if (empty($clockTask)) {
					throw new InvalidParameterException('无此任务！');
				}
				$clockJoin = WorkGroupClockJoin::find()->where(['id' => $joinIds])->all();
				$userCorp  = UserCorpRelation::findOne(['corp_id' => $activity->corp_id]);

				$time      = time();
				$sum       = $success = 0;
				$errorData = [];
				if (!empty($clockJoin)) {
					/** @var WorkGroupClockJoin $join */
					foreach ($clockJoin as $join) {
						$joinId     = $join->id;
						$clockPrize = WorkGroupClockPrize::findOne(['join_id' => $joinId, 'task_id' => $taskId, 'send' => 0]);
						if (!empty($clockPrize)) {
							$sum++;
							if (($clockPrize->type == 1)) {
								if (!empty($clockPrize->reward_name)) {
									if ($clockTask->reward_type == 2) {
										if (empty($join->name) || empty($join->mobile) || empty($join->detail)) {
											array_push($errorData, '地址未填写完整');
											continue;
										}
									}
									$clockPrize->send      = 1;
									$clockPrize->send_time = $time;
									$clockPrize->save();
									$success++;
								}
							} elseif ($clockPrize->type == 2) {
								//发放零钱
								if (!empty($clockPrize->money_amount)) {
									//发放零钱到客户
									$orderData            = [];
									$orderData['corp_id'] = $activity->corp_id;
									$orderData['remark']  = '打卡成功，已成功发放到零钱';
									$orderData['amount']  = $clockPrize->money_amount;
									$orderData['uid']     = $userCorp->uid;
									$orderData['rid']     = $activity->id;
									$orderData['jid']     = $clockPrize->join_id;
									$orderData['task_id'] = $clockPrize->task_id;
									if (!empty($join->external_id)) {
										$contact    = WorkExternalContact::findOne($join->external_id);
										$openid     = !empty($contact) ? $contact->openid : '';
										$externalId = !empty($contact) ? $contact->id : 0;
									} else {
										$openid     = $join->openid;
										$externalId = '';
									}
									$orderData['openid']      = $openid;
									$orderData['external_id'] = $externalId;
									$result                   = WorkGroupClockPrize::sendChange($orderData);
									if (!empty($result['error'])) {
										array_push($errorData, $result['msg']);
									} else {
										$success++;
									}
								}
							}
						}
					}
				}
				if (empty($sum)) {
					throw new InvalidParameterException('暂无可发放的奖励！');
				}

				//补发之前未发放的
				if (isset($result) && empty($errorData)) {
					\Yii::$app->queue->delay(5)->push(new SyncClockJob([
						'corpId' => $activity->corp_id,
					]));
				}

				$textHtml = '发放' . $sum . '条奖励，';
				if (!empty($success)) {
					$textHtml .= '成功' . $success . '条，';
				}
				if (!empty($errorData)) {
					$errorData = array_unique($errorData);
					$errorStr  = implode('、', $errorData);
					$restNum   = $sum - $success;
					if ($restNum > 0) {
						$textHtml .= '失败' . $restNum . '条，原因如下：' . $errorStr . '。';
					}
				}
				$textHtml = trim($textHtml, '，');
				if (!empty($restNum) && $sum == $restNum) {
					throw new InvalidParameterException($textHtml);
				}

				return ['textHtml' => $textHtml];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           使失效接口
		 * @description     使失效接口
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/invalid
		 *
		 * @param id 必选 string 活动ID
		 * @param type 必选 string 0使失效1发布2删除
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/28 14:15
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionInvalid ()
		{
			if (\Yii::$app->request->isPost) {
				$id   = \Yii::$app->request->post('id');
				$type = \Yii::$app->request->post('type', 0);
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$activity = WorkGroupClockActivity::findOne($id);
				if (empty($activity)) {
					throw new InvalidParameterException('无此活动！');
				}

				if (empty($type)) {
					$activity->status = 3;
					$activity->update();
					\Yii::$app->queue->push(new SyncClockJob([
						'activityId' => $activity->id,
						'corpId'     => $activity->corp_id,
					]));
				} elseif ($type == 1) {
					if ($activity->type == 2) {
						$time = time();
						if ($activity->start_time > $time) {
							throw new InvalidParameterException('未到发布时间！');
						}
					}
					$activity->status = 1;
					$activity->update();
				} else {
					$activity->is_del = 1;
					$activity->update();
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-clock-activity/
		 * @title           PC查看打卡明细
		 * @description     PC查看打卡明细
		 * @method   post
		 * @url http://{host_name}/api/work-group-clock-activity/card-detail
		 *
		 * @param join_id 必选 int 参与者ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    data array 具体打卡信息
		 * @return_param    task array 连续打卡任务
		 * @return_param    total_days int 总打卡天数
		 * @return_param    first_time string 首次打卡时间
		 * @return_param    new_time string 最近打卡时间
		 * @return_param    type string 类型 1永久有效 2 固定区间
		 * @return_param    start_time string 活动开始时间
		 * @return_param    end_time string 活动结束时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/3 10:06
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCardDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许');
			}
			$joinId = \Yii::$app->request->post('join_id');
			if (empty($joinId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$clockJoin = WorkGroupClockJoin::findOne($joinId);
			if (empty($clockJoin)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$info        = [];
			$firstTime   = '';
			$newTime     = '';
			$count       = WorkGroupClockDetail::find()->where(['join_id' => $joinId])->count();
			$clockDetail = WorkGroupClockDetail::find()->where(['join_id' => $joinId])->orderBy(['punch_time' => SORT_ASC])->all();
			if (!empty($clockDetail)) {
				/**
				 * @var key                  $key
				 * @var WorkGroupClockDetail $detail
				 */
				foreach ($clockDetail as $key => $detail) {
					if ($key == 0) {
						$firstTime = date('Y-m-d H:i', $detail->create_time);
					}
					if ($key == $count - 1) {
						$newTime = date('Y-m-d H:i', $detail->create_time);
					}
					$info[$key]['date'] = $detail->punch_time;
				}
			}
			$activity     = $clockJoin->activity;
			$choose_type  = $activity->choose_type;
			$activityId   = $activity->id;
			$continueDays = !empty($clockJoin->continue_days) ? $clockJoin->continue_days : 0;
			$totalDays    = !empty($clockJoin->total_days) ? $clockJoin->total_days : 0;

			//获取当前奖品最高签到记录
			$prizeDay  = 0;
			$prizeInfo = WorkGroupClockPrize::find()->where(['join_id' => $clockJoin->id])->orderBy(['days' => SORT_DESC])->one();
			if (!empty($prizeInfo)) {
				$prizeDay = $prizeInfo->days;
			}

			$task      = [];
			$clockTask = WorkGroupClockTask::find()->alias('ct');
			$clockTask = $clockTask->leftJoin('{{%work_group_clock_prize}} cp', 'ct.id = cp.task_id and cp.join_id=' . $clockJoin->id);
			$clockTask = $clockTask->where(['ct.activity_id' => $activityId]);
			$clockTask = $clockTask->andWhere(['or', ['ct.is_open' => 1], ['!=', 'cp.id', '']]);
			$clockTask = $clockTask->select('ct.id,ct.days,ct.type,ct.reward_type,ct.is_open,ct.reward_name,ct.money_amount,cp.id prize_id,cp.send,cp.days prize_day,cp.reward_name prize_name,cp.money_amount prize_money');
			$clockTask = $clockTask->asArray()->all();
			if (!empty($clockTask)) {
				foreach ($clockTask as $k => $val) {
					if (empty($val['prize_id']) && ($val['days'] <= $prizeDay)) {
						continue;
					}
					if ($choose_type == 1) {
						$lastDays = ($val['days'] - $continueDays) > 0 ? $val['days'] - $continueDays : 0;
					} else {
						$lastDays = ($val['days'] - $totalDays) > 0 ? $val['days'] - $totalDays : 0;
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
					$task[$k]['task_id']      = $val['id'];
					$task[$k]['days']         = $taskDays;
					$task[$k]['type']         = $val['type'];
					$task[$k]['reward_name']  = $rewardName;
					$task[$k]['money_amount'] = $moneyAmount;
					$task[$k]['choose_type']  = $activity->choose_type;
					$task[$k]['last_days']    = $lastDays; //还差多少天
					$status                   = 0; //未获得奖品
					$isShow                   = 0; //
					$isAdd                    = 0; //
					if (!empty($val['prize_id'])) {
						$status = 1;
						if (!empty($val['send'])) {
							$status = 2;
						}
						if ($val['type'] == 1 && $val['reward_type'] == 2) {
							$isShow = 1;
							if (!empty($clockJoin->name) && !empty($clockJoin->mobile) && !empty($clockJoin->detail)) {
								$isAdd = 1;
							}
						}
					}
					$task[$k]['status']  = $status;
					$task[$k]['is_show'] = $isShow;
					$task[$k]['is_add']  = $isAdd;
				}
			}
			$task = array_values($task);

			$startTime = $endTime = '';
			if ($activity->type == 2) {
				$startTime = date('Y-m-d H:i', $activity->start_time);
				$endTime   = date('Y-m-d H:i', $activity->end_time);
			}

			return [
				'data'       => $info,
				'total_days' => $totalDays,
				'first_time' => $firstTime,
				'new_time'   => $newTime,
				'task'       => $task,
				'type'       => $activity->type,
				'start_time' => $startTime,
				'end_time'   => $endTime,
			];
		}
	}