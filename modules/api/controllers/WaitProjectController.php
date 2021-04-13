<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/9/16
	 * Time: 17:26
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\AuthoritySubUserDetail;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\Follow;
	use app\models\PublicSeaContactFollowUser;
	use app\models\PublicSeaCustomer;
	use app\models\WaitAgent;
	use app\models\WaitCustomerTask;
	use app\models\WaitLevel;
	use app\models\WaitProject;
	use app\models\WaitProjectFollow;
	use app\models\WaitStatus;
	use app\models\WaitTask;
	use app\models\WaitUserRemind;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use yii\data\Sort;
	use yii\db\Expression;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class WaitProjectController extends WorkBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'add'    => ['POST'],
						'detail' => ['POST'],
					],
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           初始化接口
		 * @description     初始化接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/add
		 *
		 * @param uid 必选 int 账户id
		 * @param corp_id 必选 int 企业微信ID
		 * @param agent_id 必选 int 应用ID
		 * @param project 必选 array 项目
		 * @param task 必选 array 任务
		 * @param project.id 必选 int 执行状态名称初始化传0后面从接口取
		 * @param project.title 必选 string 项目名称
		 * @param project.level 必选 string 优先级名称
		 * @param project.desc 可选 string 优先级描述
		 * @param project.user_id 必选 int 项目负责人
		 * @param project.finish_time 必选 int 多少天内完成
		 * @param project.sort 必选 int 排序
		 * @param project.remind 可选 array 项目提醒
		 * @param project.remind.type 必选 int 1、预计结束时间前2、项目超时
		 * @param project.remind.days 可选 int 天数
		 * @param task.follow_id 必选 int 跟进状态id
		 * @param task.type 必选 int 1所有项目完成2非所有
		 * @param task.way 可选 array 选2时必填方式
		 * @param task.num 可选 int 项数
		 * @param task.is_change 可选 int 是否完成任务改变跟进状态0否1是
		 * @param task.content 必选 array 具体任务
		 * @param task.content.task_id 必选 int 任务ID默认传0
		 * @param task.content.project_two 必选 array 所选的多选必须全部包含
		 * @param task.content.project_one 必选 array 含有至少完某几项的多选
		 * @param task.content.title 必选 string 项目名称
		 * @param task.content.type 必选 int 1手动开启2自动开启3N天后开启
		 * @param task.content.days 可选 int 天数
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/17 14:54
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 * @throws \Throwable
		 * @throws \app\components\InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$uid           = \Yii::$app->request->post('uid');
				$projectStatus = \Yii::$app->request->post('project_status');
				$projectLevel  = \Yii::$app->request->post('project_level');
				$project       = \Yii::$app->request->post('project');
				$task          = \Yii::$app->request->post('task');
				$agentId       = \Yii::$app->request->post('agent_id');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($agentId)) {
					throw new InvalidParameterException('请选择应用');
				}

				$status = WaitStatus::findOne(['uid' => $uid, 'is_del' => 0]);
				if (empty($status)) {
					//默认初始化参数
					$projectStatus = [
						[
							'key'   => 1,
							'title' => '待处理',
							'color' => '#FF0000',
							'desc'  => '',
							'sort'  => 1,
						],
						[
							'key'   => 2,
							'title' => '处理中',
							'color' => '#00FF00',
							'desc'  => '',
							'sort'  => 2,
						],
						[
							'key'   => 3,
							'title' => '已完成',
							'color' => '#5599FF',
							'desc'  => '',
							'sort'  => 3,
						],
					];
					$projectLevel  = [
						[
							'key'   => 1,
							'title' => '非常重要',
							'color' => '#f85e5e',
							'desc'  => '',
							'sort'  => 1,
						],
						[
							'key'   => 2,
							'title' => '重要',
							'color' => '#93c36b',
							'desc'  => '',
							'sort'  => 2,
						],
						[
							'key'   => 3,
							'title' => '一般',
							'color' => '#97afd0',
							'desc'  => '',
							'sort'  => 3,
						],
					];
				}

				$data['corp_id']        = $this->corp->id;
				$data['uid']            = $uid;
				$data['project_status'] = $projectStatus;
				$data['project_level']  = $projectLevel;
				$data['project']        = $project;
				$data['task']           = $task;
				$data['agent_id']       = $agentId;
				WaitProject::verify($data);
				WaitProject::add($data);

				return true;
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           详情接口
		 * @description     详情接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/detail
		 *
		 * @param uid 必选 int 账户id
		 * @param corp_id 必选 int 企业微信ID
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/17 16:15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$uid = \Yii::$app->request->post('uid');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$status = WaitProject::findOne(['is_del' => 0, 'corp_id' => $this->corp->id]);
				$init   = 0;
				if (!empty($status)) {
					$init = 1;
				}
				//项目执行状态
				$waitStatus = WaitStatus::getData($uid);
				//项目优先级
				$waitLevel = WaitLevel::getData($uid);
				//获取项目
				$project = WaitProject::getData($this->corp->id);
				//获取任务
				$waitTask = WaitTask::getData($uid);
				$agentId  = WaitAgent::getData($this->corp->id);

				return [
					'init'           => $init,
					'project_status' => $waitStatus,
					'project_level'  => $waitLevel,
					'project'        => $project['data'],
					'task'           => $waitTask,
					'agent_id'       => $agentId,
					'count'          => count($project['data'])
				];
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           公共部分设置
		 * @description     公共部分设置
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/set
		 *
		 * @param uid 必选 int 账户id
		 * @param project_status 必选 array 项目状态
		 * @param project_level 必选 array 项目优先级
		 * @param project_status.id 必选 int 初始化传0后面从接口取
		 * @param project_status.title 必选 string 执行状态名称
		 * @param project_status.color 必选 string 执行状态颜色
		 * @param project_status.desc 可选 string 执行状态描述
		 * @param project_status.sort 必选 int 执行状态排序
		 * @param project_level.id 必选 int 执行状态名称初始化传0后面从接口取
		 * @param project_level.title 必选 string 优先级名称
		 * @param project_level.color 必选 string 优先级名称颜色
		 * @param project_level.desc 可选 string 优先级描述
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/27 14:49
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 * @throws \Throwable
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionSet ()
		{
			if (\Yii::$app->request->isPost) {
				$uid                    = \Yii::$app->request->post('uid');
				$projectStatus          = \Yii::$app->request->post('project_status');
				$projectLevel           = \Yii::$app->request->post('project_level');
				$data['uid']            = $uid;
				$data['project_status'] = $projectStatus;
				$data['project_level']  = $projectLevel;
				WaitProject::verifyCommon($data);
				//添加项目执行状态
				WaitStatus::add($projectStatus, $uid);
				//添加项目优先级
				WaitLevel::add($projectLevel, $uid);

				return true;
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办公共详情
		 * @description     待办公共详情
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/common-detail
		 *
		 * @param uid 必选 int 账户ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    init int 1已初始化0没有初始化
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/27 14:47
		 * @number          0
		 *
		 * @throws NotAllowException
		 */
		public function actionCommonDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$uid    = \Yii::$app->request->post('uid');
				$status = WaitStatus::findOne(['uid' => $uid,'is_del'=>0]);
				$init   = 0;
				if (!empty($status)) {
					$init = 1;
				}
				//项目执行状态
				$waitStatus = WaitStatus::getData($uid);
				//项目优先级
				$waitLevel = WaitLevel::getData($uid);

				//$data = WaitTask::getAllData([177],time(),1);
				return [
					//'data'           => $data,
					'init'           => $init,
					'project_status' => $waitStatus,
					'project_level'  => $waitLevel,
				];
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           是否可以编辑客户跟进状态
		 * @description     是否可以编辑客户跟进状态
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/can-edit-follow
		 *
		 * @param cid 必选 int 客户和公海客户详情id
		 * @param type 必选 int 1企微客户2公海客户
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    can_edit_follow int 1可以修改0不可以
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/20 14:46
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCanEditFollow ()
		{
			$cid  = \Yii::$app->request->post('cid', 0);
			$type = \Yii::$app->request->post('type', 1);
			if (empty($cid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$canEditFollow = 1;
			if ($type == 1) {
				$follow = WorkExternalContactFollowUser::findOne($cid);
				if (!empty($follow)) {
					$canEditFollow = WaitCustomerTask::isDone($follow->follow_id, 0, $follow->external_userid);
				}
			} else {
				//公海客户
				$sea = PublicSeaContactFollowUser::findOne($cid);
				if (!empty($sea)) {
					$canEditFollow = WaitCustomerTask::isDone($sea->follow_id, 1, NULL, $sea->sea_id);
				}
			}
			$info['can_edit_follow'] = $canEditFollow; //1可以修改跟进状态0不可以

			return $info;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           企微H5待办事项
		 * @description     企微H5待办事项
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/custom-wait-project
		 *
		 * @param uid 必选 int 账户id
		 * @param userid 必选 string 员工id
		 * @param external_userid 必选 string 客户id
		 * @param corp_id 必选 string 企业微信id
		 * @param page 可选 string 当前页
		 * @param page_size 可选 string 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id用于添加项目跟进
		 * @return_param    show_project int 是否显示项目跟进按钮0不显示1显示
		 * @return_param    show_start int 是否显示开始按钮0不显示1显示
		 * @return_param    title string 项目名称
		 * @return_param    status string 执行状态
		 * @return_param    status_color string 状态颜色
		 * @return_param    level string 等级
		 * @return_param    level_color string 等级颜色
		 * @return_param    desc string 项目描述
		 * @return_param    per string 项目百分比
		 * @return_param    leader string 项目负责人
		 * @return_param    start_time string 项目开始时间
		 * @return_param    end_time string 项目结束时间
		 * @return_param    finish_time string 项目实际完成时间
		 * @return_param    days string 超时天数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/20 15:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomWaitProject ()
		{
			$uid            = \Yii::$app->request->post('uid', 0);
			$userId         = \Yii::$app->request->post('userid', '');
			$user_id         = \Yii::$app->request->post('user_id', '');
			$corpId         = \Yii::$app->request->post('corp_id', '');
			$externalUserId = \Yii::$app->request->post('external_userid', '');
			$page           = \Yii::$app->request->post('page', 1);
			$pageSize       = \Yii::$app->request->post('page_size', 10);
			if (empty($uid) || empty($userId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corpId]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userId]);
			if (empty($workUser)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			$externalUserData = WorkExternalContact::findOne(['external_userid' => $externalUserId]);
			if (empty($externalUserData)) {
				throw new InvalidParameterException('客户数据错误！');
			}
			$followUser = WorkExternalContactFollowUser::findOne(['userid'=>$user_id,'external_userid'=>$externalUserData->id]);
			if (empty($followUser)) {
				throw new InvalidParameterException('客户数据错误！');
			}
			$offset       = ($page - 1) * $pageSize;
			$customerTask = WaitCustomerTask::find()->alias('c')->leftJoin('{{%wait_task}} t', 'c.task_id=t.id');
			$customerTask = $customerTask->leftJoin('{{%wait_project}} p', 't.project_id=p.id');
			$customerTask = $customerTask->where(['c.external_userid' => $externalUserData->id, 'p.corp_id' => $corpInfo->id, 't.follow_id' => $followUser->follow_id, 'c.type' => 0, 'p.is_del' => 0, 't.is_del' => 0])->groupBy(['p.id']);
			$count        = $customerTask->count();
			$customerTask = $customerTask->limit($pageSize)->offset($offset)->select('p.title,p.level_id,p.desc,p.user_id,t.id as task_id,c.external_userid,c.sea_id,t.follow_id,c.start_time,c.end_time,c.finish_time,c.status,c.per,c.id');
			$customerTask = $customerTask->asArray()->all();
			$info         = [];
			if (!empty($customerTask)) {
				$waitLevel     = WaitLevel::find()->where(['uid' => $uid])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatus    = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatusOne = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_DESC])->one();
				foreach ($customerTask as $key => $task) {
					$showStart    = 0; //是否显示开始按钮 0不显示 1显示
					$showProject  = 0; //是否显示项目跟进按钮 0不显示 1显示
					$followNum = WaitProjectFollow::find()->where(['customer_task_id' => $task['id']])->count();
					if ($task['status'] == $waitStatus[0]['id']  && $workUser->id == $task['user_id'] ) {
						$showStart = 1;
					}
					if ($task['status'] != $waitStatusOne->id && $workUser->id == $task['user_id']) {
						$showProject = 1;
					}
					$leader   = '';
					$taskUser = WorkUser::findOne($task['user_id']);
					if (!empty($taskUser)) {
						$leader = $taskUser->name;
					}
					$level      = '';
					$levelColor = '';
					foreach ($waitLevel as $wl) {
						if ($wl['id'] == $task['level_id']) {
							$level      = $wl['title'];
							$levelColor = $wl['color'];
						}
					}
					$statusTitle = '';
					$statusColor = '';
					foreach ($waitStatus as $ws) {
						if ($ws['id'] == $task['status']) {
							$statusTitle = $ws['title'];
							$statusColor = $ws['color'];
						}
					}
					$info[$key]['id']              = $task['id'];
					$info[$key]['show_project']    = $showProject;
					$info[$key]['show_start']      = $showStart;
					$info[$key]['title']           = $task['title'];
					$info[$key]['status_id']       = $task['status'];
					$info[$key]['status_title']    = $statusTitle;
					$info[$key]['status_color']    = $statusColor;
					$info[$key]['level']           = $level;
					$info[$key]['follow_id']       = $task['follow_id'];
					$info[$key]['level_color']     = $levelColor;
					$info[$key]['desc']            = $task['desc'];
					$info[$key]['follow_num']      = $followNum;
					$info[$key]['per']             = !empty($task['per']) ? intval($task['per']) : 0;
					$info[$key]['leader']          = $leader;
					$info[$key]['start_time']      = !empty($task['start_time']) ? date('Y-m-d', $task['start_time']) : '';
					$info[$key]['end_time']        = !empty($task['end_time']) ? date('Y-m-d', $task['end_time']) : '';
					$info[$key]['finish_time']     = !empty($task['finish_time']) ? date('Y-m-d', $task['finish_time']) : '';
					$info[$key]['external_userid'] = !empty($task['external_userid']) ? $task['external_userid'] : '';
					$info[$key]['sea_id']          = !empty($task['sea_id']) ? $task['sea_id'] : '';
					$info[$key]['task_id']         = $task['task_id'];
					$status                     = 2;//处理中
					if ($task['status'] == $waitStatus[0]['id']) {
						$status = 1;//待处理
					}
					if ($task['status'] == $waitStatusOne->id) {
						$status = 3;//已完成
					}
					$info[$key]['status'] = $status;
					$isFinish             = 0; //未完成
					$delayDays            = 0;
					if (!empty($task['finish_time']) && $task['finish_time'] > $task['end_time']) {
						//超时
						$delayDays = ceil(($task['finish_time'] - $task['end_time']) / (24 * 3600));
						$isFinish  = 2;//超时完成
					}

					$preDays = 0;
					if (!empty($task['finish_time']) && $task['finish_time'] <= $task['end_time']) {
						$date1 = date('Y-m-d', $task['finish_time']);
						$date2 = date('Y-m-d', $task['end_time']);
						if ($date1 == $date2) {
							$isFinish = 1; //按时完成
						} else {
							//提前
							$preDays  = floor(($task['end_time'] - $task['finish_time']) / (24 * 3600));
							$isFinish = 3; //提前
						}

					}

					$lastDays = 0;
					if (!empty($task['end_time']) && $task['end_time'] > time()) {
						$lastDays = floor(($task['end_time'] + 1 - time()) / (24 * 3600));
					}
					$info[$key]['last_days']  = $lastDays;
					$info[$key]['is_finish']  = $isFinish;
					$info[$key]['pre_days']   = $preDays;
					$info[$key]['delay_days'] = $delayDays;
				}

			}
			$data = [];
			if (!empty($info)) {
				$followId = array_unique(array_column($info, 'follow_id'));
				if (!empty($followId)) {
					$followStatus = Follow::find()->where(['id' => $followId, 'status' => 1])->all();
					if (!empty($followStatus)) {
						/** @var Follow $stu */
						foreach ($followStatus as $key => $stu) {
							$data[$key]['title'] = $stu->title;
							$data[$key]['id']    = $stu->id;
							$infoData            = [];
							foreach ($info as $val) {
								if ($val['follow_id'] == $stu->id) {
									array_push($infoData, $val);
								}
							}
							$data[$key]['data'] = $infoData;
						}
					}
				}
			}
			return [
				'count' => $count,
				'info'  => $data
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           企微H5待办事项-项目跟进
		 * @description     企微H5待办事项-项目跟进
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/add-project-status
		 *
		 * @param uid 必选 int 账户id
		 * @param id 必选 int 客户待办ID
		 * @param status 必选 int 状态id
		 * @param per 必选 string 百分比
		 * @param per_desc 可选 string 进度说明
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/20 16:27
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionAddProjectStatus ()
		{
			$uid             = \Yii::$app->request->post('uid');
			$id              = \Yii::$app->request->post('id');
			$status          = \Yii::$app->request->post('status');
			$taskId          = \Yii::$app->request->post('task_id');
			$external_userid = \Yii::$app->request->post('external_userid');
			$seaId           = \Yii::$app->request->post('sea_id');
			$per             = \Yii::$app->request->post('per');
			$desc            = \Yii::$app->request->post('per_desc');
			if (empty($id) || empty($taskId) || empty($uid) || empty($status) || (empty($external_userid) && empty($seaId))) {
				throw new InvalidParameterException('参数错误！');
			}
			if (intval($per) < 0 || intval($per) > 100) {
				throw new InvalidParameterException('百分比必须介于1之100之间！');
			}
			if (empty(trim($desc))) {
				throw new InvalidParameterException('进度说明不能为空！');
			}
			if (!empty($desc) && mb_strlen($desc, 'utf-8') > 200) {
				throw new InvalidParameterException('进度说明不能多于200个字！');
			}
			$data['uid']             = $uid;
			$data['id']              = $id;
			$data['status']          = $status;
			$data['per']             = $per;
			$data['per_desc']        = $desc;
			$data['task_id']         = $taskId;
			$data['external_userid'] = $external_userid;
			$data['sea_id']          = $seaId;
			WaitProjectFollow::add($data);

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           所有待办项目
		 * @description     所有待办项目
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/project
		 *
		 * @param corp_id 必选 string 企业唯一身份
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/15 14:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionProject ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$project = WaitProject::find()->where(['corp_id' => $this->corp->id, 'is_del' => 0])->select('id,title')->orderBy(['sort' => SORT_DESC])->asArray()->all();

			return $project;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办添加、修改执行状态接口
		 * @description     待办添加、修改执行状态接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/add-wait-status
		 *
		 * @param uid 必选 int 账户id
		 * @param id 必选 int 状态ID
		 * @param title 必选 string 名称
		 * @param color 必选 string 颜色
		 * @param desc 可选 string 描述
		 * @param sort 必选 string 排序
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/6 10:29
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionAddWaitStatus ()
		{
			$id    = \Yii::$app->request->post('id');
			$uid   = \Yii::$app->request->post('uid');
			$title = \Yii::$app->request->post('title');
			$color = \Yii::$app->request->post('color');
			$desc  = \Yii::$app->request->post('desc');
			$sort  = \Yii::$app->request->post('sort');
			if (empty($title)) {
				throw new InvalidParameterException('状态名称不能为空');
			} elseif (mb_strlen($title, 'utf-8') > 20) {
				throw new InvalidDataException('状态名称不能超过20个字');
			}
			if (empty($color)) {
				throw new InvalidParameterException('颜色不能为空');
			}
			if (!empty($desc) && mb_strlen($desc, 'utf-8') > 200) {
				throw new InvalidDataException('阶段描述不能超过200个字');
			}
			if (empty(intval($sort))) {
				throw new InvalidParameterException('排序不能为空');
			}
			WaitStatus:: addData($uid, $title, $id, $color, $desc, $sort, rand(5, 100), 1);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办添加、修改优先级接口
		 * @description     待办添加、修改优先级接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/add-wait-level
		 *
		 * @param uid 必选 int 账户id
		 * @param id 必选 int 优先级ID
		 * @param title 必选 string 名称
		 * @param color 必选 string 颜色
		 * @param desc 可选 string 描述
		 * @param sort 必选 string 排序
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/6 10:43
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionAddWaitLevel ()
		{
			$id    = \Yii::$app->request->post('id',0);
			$uid   = \Yii::$app->request->post('uid');
			$title = \Yii::$app->request->post('title');
			$color = \Yii::$app->request->post('color');
			$desc  = \Yii::$app->request->post('desc');
			$sort  = \Yii::$app->request->post('sort');
			if (empty($title)) {
				throw new InvalidParameterException('优先级名称不能为空');
			} elseif (mb_strlen($title, 'utf-8') > 20) {
				throw new InvalidDataException('优先级名称不能超过20个字');
			}
			if (empty($color)) {
				throw new InvalidParameterException('颜色不能为空');
			}
			if (!empty($desc) && mb_strlen($desc, 'utf-8') > 200) {
				throw new InvalidDataException('优先级描述不能超过200个字');
			}
			if (empty(intval($sort))) {
				throw new InvalidParameterException('排序不能为空');
			}
			WaitLevel:: addData($id, $uid, $title, $color, $desc, $sort, rand(6, 100));

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           删除状态接口
		 * @description     删除状态接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/del-wait-status
		 *
		 * @param id 必选 int 状态id
		 * @param type 必选 int 0验证1删除
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/6 11:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionDelWaitStatus ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确');
			}
			$waitStatus = WaitStatus::findOne($id);
			$uid        = $waitStatus->uid;
			if (empty($waitStatus)) {
				throw new InvalidParameterException('参数不正确');
			}
			$count = WaitStatus::find()->where(['uid' => $waitStatus->uid, 'is_del' => 0])->count();
			if (empty($count)) {
				throw new InvalidDataException('项目阶段至少要保留一个！');
			}
			WaitStatus::updateAll(['is_del' => 1], ['id' => $id]);
			$task = WaitCustomerTask::find()->where(['status' => $id])->all();
			if (!empty($task)) {
				$status = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->one();
				/** @var WaitCustomerTask $ta */
				foreach ($task as $ta) {
					try {
						$ta->status     = $status->id;
						$ta->end_time   = 0;
						$ta->start_time = 0;
						$ta->queue_id   = 0;
						$ta->per        = '0';
						$ta->per_desc   = '';
						if (!empty($ta->queue_id)) {
							\Yii::$app->queue->remove($ta->queue_id);
						}
						$ta->queue_id = 0;
						if (!$ta->validate() || !$ta->save()) {
							\Yii::error(SUtils::modelError($ta), 'error');
						}
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), 'message');
					}

				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           删除优先级接口
		 * @description     删除优先级接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/del-wait-level
		 *
		 * @param id 必选 int 优先级id
		 * @param type 必选 int 0验证1删除
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/6 11:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionDelWaitLevel ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确');
			}
			$waitLevel = WaitLevel::findOne($id);
			$uid       = $waitLevel->uid;
			if (empty($waitLevel)) {
				throw new InvalidParameterException('参数不正确');
			}
//			$pro = WaitProject::findOne(['level_id' => $id, 'is_del' => 0]);
//			if (!empty($pro)) {
//				throw new InvalidDataException('当前优先级存在项目无法删除！');
//			}
			$count = WaitLevel::find()->where(['uid' => $waitLevel->uid])->count();
			if ($count<=1) {
				throw new InvalidDataException('优先级至少要保留一个！');
			}
			$project = WaitProject::find()->where(['level_id' => $id])->all();
			if (!empty($project)) {
				$level = WaitLevel::find()->where(['uid' => $uid])->orderBy(['sort' => SORT_ASC])->one();
				/** @var WaitProject $pro */
				foreach ($project as $pro) {
					$pro->level_id = $level->id;
					$pro->save();
				}
			}
			WaitLevel::deleteAll(['id' => $id]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办详情
		 * @description     待办详情
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/wait-info
		 *
		 * @param id 必选 int 客户待办ID
		 * @param page 可选 string 当前页
		 * @param page_size 可选 string 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    days int 还剩天数
		 * @return_param    end_time string 结束时间
		 * @return_param    title string 项目名称
		 * @return_param    info array 数据信息
		 * @return_param    info.date string 日期
		 * @return_param    info.data array 具体待办事项
		 * @return_param    info.data.time string 时间
		 * @return_param    info.data.per string 百分比
		 * @return_param    info.data.per_desc string 待办说明
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/20 17:26
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWaitInfo ()
		{
			$id              = \Yii::$app->request->post('id');
			$taskId          = \Yii::$app->request->post('task_id');
			$external_userid = \Yii::$app->request->post('external_userid');
			$sea_id          = \Yii::$app->request->post('sea_id');
			if (empty($id) || empty($taskId) || (empty($external_userid) && empty($sea_id))) {
				throw new InvalidParameterException('参数错误！');
			}
			$finishTime    = '';
			$page          = \Yii::$app->request->post('page', 1);
			$pageSize      = \Yii::$app->request->post('page_size', 10);
			$offset        = ($page - 1) * $pageSize;
			$unixType      = '%Y-%m-%d';
			$select        = new Expression('FROM_UNIXTIME(create_time, \'' . $unixType . '\') time');
			$projectFollow = WaitProjectFollow::find()->where(['task_id' => $taskId]);
			if (!empty($external_userid)) {
				$projectFollow = $projectFollow->andWhere(['external_userid' => $external_userid]);
			}
			if (!empty($sea_id)) {
				$projectFollow = $projectFollow->andWhere(['sea_id' => $sea_id]);
			}
			$projectFollow = $projectFollow->select($select)->groupBy('time');
			$count         = count(SUtils::array_unset_tt($projectFollow->asArray()->all(), 'time'));
			$projectFollow = $projectFollow->limit($pageSize)->offset($offset);
			$projectFollow = $projectFollow->orderBy(['create_time' => SORT_DESC])->asArray()->all();
			$info          = [];
			if (!empty($projectFollow)) {
				foreach ($projectFollow as $key => $follow) {
					$pro      = [];
					$sTime    = strtotime($follow['time']);
					$eTime    = strtotime($follow['time'] . ' 23:59:59');
					$waitData = WaitProjectFollow::find()->where(['customer_task_id' => $id]);
					if (!empty($external_userid)) {
						$waitData = $waitData->andWhere(['external_userid' => $external_userid]);
					}
					if (!empty($sea_id)) {
						$waitData = $waitData->andWhere(['sea_id' => $sea_id]);
					}
					$waitData = $waitData->andFilterWhere(['between', 'create_time', $sTime, $eTime])->orderBy(['create_time' => SORT_DESC])->asArray()->all();
					if (!empty($waitData)) {
						foreach ($waitData as $k => $val) {
							$pro[$k]['time']     = date('H:i', $val['create_time']);
							$pro[$k]['per']      = intval($val['per']);
							$pro[$k]['per_desc'] = $val['per_desc'];
							$statusTitle         = '';
							$statusColor         = '';
							if (!empty($val['status'])) {
								$status = WaitStatus::findOne(['id' => $val['status']]);
								if (!empty($status)) {
									$statusTitle = $status->title;
									if ($status->is_del == 1) {
										$statusTitle = $status->title . '（已删除）';
									}
									$statusColor = $status->color;
								}
							}
							$pro[$k]['per_desc']     = $val['per_desc'];
							$pro[$k]['status_title'] = $statusTitle;
							$pro[$k]['status_color'] = $statusColor;
						}
					}
					$info[$key]['date'] = $follow['time'];
					$info[$key]['data'] = $pro;
				}
			}
			$info       = SUtils::array_unset_tt($info, 'date');
			$info       = array_values($info);
			$endTime    = '';
			$title      = '';
			$customTask = WaitCustomerTask::findOne($id);

			$isFinish  = 0; //未完成
			$delayDays = 0;
			$preDays   = 0;

			if (!empty($customTask)) {
				$title      = $customTask->task->project->title;
				$endTime    = !empty($customTask->end_time) ? date('Y-m-d', $customTask->end_time) : '';
				$finishTime = !empty($customTask->finish_time) ? date('Y-m-d', $customTask->finish_time) : '';

				if (!empty($customTask->finish_time) && $customTask->finish_time > $customTask->end_time) {
					//超时
					$delayDays = ceil(($customTask->finish_time - $customTask->end_time) / (24 * 3600));
					$isFinish  = 2;//超时完成
				}

				if (!empty($customTask->finish_time) && $customTask->finish_time <= $customTask->end_time) {
					$date1 = date('Y-m-d', $customTask->finish_time);
					$date2 = date('Y-m-d', $customTask->end_time);
					if ($date1 == $date2) {
						$isFinish = 1; //按时完成
					} else {
						//提前
						$preDays  = floor(($customTask->end_time - $customTask->finish_time) / (24 * 3600));
						$isFinish = 3; //提前
					}
				}
			}

			$time = [
				'pre_days'    => $preDays,
				'delay_days'  => $delayDays,
				'is_finish'   => $isFinish,
				'finish_time' => $finishTime,
			];

			$date = strtotime(date('Y-m-d'));

			return [
				'date'     => $date,
				'days'     => 0,
				'title'    => $title,
				'end_time' => $endTime,
				'time'     => $time,
				'info'     => $info,
				'count'    => $count,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           点开始接口
		 * @description     点开始接口
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/start
		 *
		 * @param id 必选 int 客户待办ID
		 * @param uid 必选 int 用户ID
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/21 11:27
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionStart ()
		{
			$id  = \Yii::$app->request->post('id');
			$uid = \Yii::$app->request->post('uid');
			if (empty($id) || empty($uid)) {
				throw new InvalidParameterException('参数错误！');
			}
			$waitStatus = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->asArray()->all();
			if (!empty($waitStatus)) {
				$status     = $waitStatus[1]['id'];
				$customTask = WaitCustomerTask::findOne($id);
				if (!empty($customTask) && empty($customTask->start_time)) {
					$waitTask               = WaitTask::findOne($customTask->task_id);
					$customTask->status     = $status;
					$customTask->start_time = time();
					$endTime                = ($waitTask->project->finish_time - 1) * 24 * 3600 + time();
					$endTime                = strtotime(date('Y-m-d', $endTime) . ' 23:59:59');
					$customTask->end_time   = $endTime;
					$customTask->save();
					if (!empty($endTime)) {
						WaitUserRemind::addMind([$id], $waitTask->project_id, $waitTask->id, $waitTask->project->user_id, $endTime);
					}
				} else {
					throw new InvalidParameterException('参数错误！');
				}
			}

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办看板
		 * @description     待办看板
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/wait-project-board
		 *
		 * @param id 必选 int 待办ID
		 * @param corp_id 必选 int 企业微信ID
		 * @param page 可选 string 当前页
		 * @param pageSize 可选 string 页数
		 * @param isMasterAccount 可选 string 子账户2
		 * @param uid 可选 string 用户ID
		 * @param name 可选 string 名称
		 * @param phone 可选 string 电话
		 * @param user_ids 可选 array 部门ID
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param project_id 可选 string 项目ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    info array 跟进状态
		 * @return_param    info.id int 跟进状态ID
		 * @return_param    info.title string 跟进名称
		 * @return_param    info.status int 1正常0已删除
		 * @return_param    info.count int 待办数量
		 * @return_param    info.members array 待办数据
		 * @return_param    info.members.id int 待办ID
		 * @return_param    info.members.id int 待办ID
		 * @return_param    info.members.s_type int 0企微客户1公海客户
		 * @return_param    info.members.start_time string 开始时间
		 * @return_param    info.members.end_time string 结束时间
		 * @return_param    info.members.finish_time string 完成时间
		 * @return_param    info.members.avatar string 头像
		 * @return_param    info.members.name string 姓名
		 * @return_param    info.members.corp_name string 公司名称
		 * @return_param    info.members.per string 百分比
		 * @return_param    info.members.title string 项目名称
		 * @return_param    info.members.days string 项目需要在多少天完成
		 * @return_param    info.members.user_name string 项目负责人名称
		 * @return_param    info.members.level string 级别
		 * @return_param    info.members.follow_num string 跟进次数
		 * @return_param    info.members.status string 1待处理2处理中3已完成
		 * @return_param    info.members.is_finish string 0未完成1按时完成2超时完成3提前完成
		 * @return_param    info.members.pre_days string 提前天数
		 * @return_param    info.members.delay_days string 推迟天数
		 * @return_param    info.members.type string 1手动开启2自动开启3N天后开启
		 * @return_param    info.members.start_days string 多少天后启动
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/24 17:38
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWaitProjectBoard ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$uid             = \Yii::$app->request->post('uid', 0);
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('pageSize', 15);
				$name            = \Yii::$app->request->post('name');
				$phone           = \Yii::$app->request->post('phone');
				$user_ids        = \Yii::$app->request->post('user_ids');
				$start_time      = \Yii::$app->request->post('start_time');
				$end_time        = \Yii::$app->request->post('end_time');
				$project_id      = \Yii::$app->request->post('project_id');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数错误！');
				}
				$name         = trim($name);
				$phone        = trim($phone);
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					if(empty($user_ids)){
						return ['info'    => []];
					}
				}
				//$follow        = Follow::find()->where(['uid' => $uid])->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->all();
				$followStatus  = WaitStatus::find()->where(['uid' => $uid,'is_del'=>0])->orderBy(['sort' => SORT_ASC])->all();
				$offset        = ($page - 1) * $pageSize;
				$info          = [];
				$seaPhone      = [];
				$externalPhone = [];
				$seaName       = [];
				$externalName  = [];
				$userId = 0;
				if ($isMasterAccount == 1) {
					$account = $this->user->account;
				} else {
					$account = $this->subUser->account;
				}
				$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'mobile' => $account, 'is_del' => 0]);
				if (!empty($workUser)) {
					$userId = $workUser->id;
				}

				if (!empty($phone)) {
					$seaCustom = PublicSeaCustomer::find()->where(['uid' => $uid, 'type' => 0])->andWhere(' phone like \'%' . $phone . '%\'')->select('id');
					$seaCustom = $seaCustom->asArray()->all();
					if (!empty($seaCustom)) {
						foreach ($seaCustom as $sea) {
							array_push($seaPhone, $sea['id']);
						}
					}
					$externalCustom = WorkExternalContactFollowUser::find()->alias('f')->leftJoin('{{%work_external_contact}} c', 'c.id=f.external_userid')->where(['c.corp_id' => $this->corp->id])->andWhere(' f.remark_mobiles like  \'%' . $phone . '%\' ')->select('c.id')->groupBy('c.id')->asArray()->all();
					if (!empty($externalCustom)) {
						foreach ($externalCustom as $ext){
							array_push($externalPhone,$ext['id']);
						}
					}
				}
				$customField = CustomField::findOne(['key' => 'name']);
				if (!empty($name)) {
					$seaCustom = PublicSeaCustomer::find()->alias('sc')->leftJoin('{{%public_sea_contact_follow_user}} wf', 'wf.sea_id=sc.id')->where(['sc.uid' => $uid, 'sc.type' => 0])->andWhere(['or', ['like', 'sc.name', $name], ['like', 'sc.remark', $name], ['like', 'wf.company_name', $name]])->select('sc.id')->groupBy('sc.id');
					$seaCustom = $seaCustom->asArray()->all();
					if (!empty($seaCustom)) {
						foreach ($seaCustom as $sea) {
							array_push($seaName, $sea['id']);
						}
					}

					$followName = WorkExternalContact::find()->alias('wc')->leftJoin('{{%work_external_contact_follow_user}} wf', 'wf.external_userid=wc.id')->leftJoin('{{%custom_field_value}} cf', 'cf.cid=wc.id');
					$followName = $followName->where(['corp_id' => $this->corp->id, 'wf.del_type' => [0, 3]])->andWhere(' wc.name_convert like \'%' . $name . '%\' or wf.remark_corp_name like \'%' . $name . '%\'  or wf.remark like \'%' . $name . '%\' or wf.nickname like \'%' . $name . '%\' or (cf.fieldid =' . $customField->id . ' and cf.value like \'%' . $name . '%\')');
					$followName = $followName->select('wc.id')->groupBy('wc.id');
					$followName = $followName->asArray()->all();
					if (!empty($followName)) {
						foreach ($followName as $ext) {
							array_push($externalName, $ext['id']);
						}
					}
				}
				$waitStatus    = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatusOne = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_DESC])->one();
				if (!empty($followStatus)) {
					/** @var WaitStatus $foll */
					foreach ($followStatus as $key => $foll) {
						$customTask = WaitCustomerTask::find()->alias('c')->leftJoin('{{%wait_task}} t', 'c.task_id=t.id');
						$customTask = $customTask->leftJoin('{{%wait_project}} p', 'p.id=t.project_id');
						$customTask = $customTask->where(['t.is_del' => 0, 'p.is_del' => 0, 'status' => $foll->id, 'p.corp_id' => $this->corp->id]);
						if (!empty($user_ids)) {
							\Yii::error($user_ids,'$user_ids');
							$customTask = $customTask->andWhere(['in', 'p.user_id', $user_ids]);
						}
						if ($isMasterAccount == 2) {
							$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
							if (is_array($sub_detail)) {
								$customTask = $customTask->andWhere(["in", 'p.user_id', $sub_detail]);
							} else if ($sub_detail === false) {
								return ["info" => []];
							}
						}
						if (!empty($start_time) && !empty($end_time)) {
							$customTask = $customTask->andFilterWhere(['between', 'c.finish_time', strtotime($start_time), strtotime($end_time)]);
						}
						if (!empty($project_id)) {
							$customTask = $customTask->andWhere(['p.id' => $project_id]);
						}
						if (!empty($phone)) {
							$customTask = $customTask->andWhere(['or', ['c.external_userid' => $externalPhone], ['c.sea_id' => $seaPhone]]);
						}
						if (!empty($name)) {
							$customTask = $customTask->andWhere(['or', ['c.external_userid' => $externalName], ['c.sea_id' => $seaName]]);
						}
						if ($isMasterAccount == 2) {
							$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
							if (is_array($sub_detail)) {
								$customTask = $customTask->andWhere(['in', 'p.user_id', $sub_detail]);
							} else if ($sub_detail === false) {
								return [];
							}
						}
						$count = $customTask->groupBy('c.id')->count();
						if (!empty($pages)) {
							foreach ($pages as $k => $pp) {
								if ($k == $key) {
									$limit      = $pp * $pageSize;
									$customTask = $customTask->limit($limit)->groupBy('c.id');
								}
							}
						} else {
							$customTask = $customTask->limit($pageSize)->offset($offset)->groupBy('c.id');
						}
						$customTask          = $customTask->select('c.id,c.type as s_type,c.is_finish,c.status,c.start_time,c.end_time,c.finish_time,c.sea_id,p.user_id,p.level_id,c.external_userid,c.per,c.per_desc,p.title,p.finish_time as days,t.days as start_days,t.type,t.id as task_id');
						$customTask          = $customTask->asArray()->all();
						$info[$key]['id']    = $foll->id;
						$info[$key]['title'] = $foll->title;
						$info[$key]['count'] = $count;
						$members             = [];
						if (!empty($customTask)) {
							foreach ($customTask as $k => $task) {
								$members[$k]['id']              = $task['id'];
								$members[$k]['s_type']          = $task['s_type'];
								$members[$k]['external_userid'] = !empty($task['external_userid']) ? $task['external_userid'] : '';
								$members[$k]['sea_id']          = !empty($task['sea_id']) ? $task['sea_id'] : '';
								$members[$k]['task_id']         = $task['task_id'];
								$avatar                         = '';
								$corpName                       = NULL;
								$nameVal                        = '';
								$nickName                       = '';
								if ($task['s_type'] == 0) {
									$contact = WorkExternalContact::findOne($task['external_userid']);
									if (!empty($contact)) {
										$avatar   = $contact->avatar;
										$corpName = $contact->corp_name;
										$nickName = rawurldecode($contact->name);
									}
									$fieldVal = CustomFieldValue::findOne(['fieldid' => $customField->id, 'type' => 1, 'cid' => $task['external_userid']]);
									if (!empty($fieldVal)) {
										$nameVal = $fieldVal->value;
									}
								} else {
									$fieldVal = CustomFieldValue::findOne(['fieldid' => $customField->id, 'type' => 4, 'cid' => $task['sea_id']]);
									if (!empty($fieldVal)) {
										$nameVal = $fieldVal->value;
									}
									$sea      = PublicSeaCustomer::findOne($task['sea_id']);
									$nickName = $sea->name;
								}
								$userName = '';
								if (isset($this->subUser) && !empty($this->subUser->account)) {
									$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'is_del' => 0, 'mobile' => $this->subUser->account]);
									if ($workUser && $workUser->id == $task['user_id']) {
										$userName = '自己';
									}else{
										$workUser = WorkUser::findOne($task['user_id']);
										if (!empty($workUser)) {
											$userName = $workUser->name;
										}
									}
								} else {
									$workUser = WorkUser::findOne($task['user_id']);
									if (!empty($workUser)) {
										$userName = $workUser->name;
									}
								}
								$canEdit = 0;
								if ($task['user_id'] == $userId && $task['is_finish'] != 1) {
									$canEdit = 1;
								}
								$level     = '';
								$levelColor = '';
								$waitLevel = WaitLevel::findOne($task['level_id']);
								if (!empty($waitLevel)) {
									$level = $waitLevel->title;
									$levelColor = $waitLevel->color;
								}
								$status = 2;//处理中
								if ($task['status'] == $waitStatus[0]['id']) {
									$status = 1;//待处理
								}
								if ($task['status'] == $waitStatusOne->id) {
									$status = 3;//已完成
								}
								$followNum = WaitProjectFollow::find()->where(['customer_task_id' => $task['id']])->count();

								$isFinish  = 0; //未完成
								$delayDays = 0;
								if (!empty($task['finish_time']) && $task['finish_time'] > $task['end_time']) {
									//超时
									$delayDays = ceil(($task['finish_time'] - $task['end_time']) / (24 * 3600));
									$isFinish  = 2;//超时完成
								}

								$preDays = 0;
								if (!empty($task['finish_time']) && $task['finish_time'] <= $task['end_time']) {
									$date1 = date('Y-m-d', $task['finish_time']);
									$date2 = date('Y-m-d', $task['end_time']);
									if ($date1 == $date2) {
										$isFinish = 1; //按时完成
									} else {
										//提前
										$preDays  = floor(($task['end_time'] - $task['finish_time']) / (24 * 3600));
										$isFinish = 3; //提前
									}

								}

								$members[$k]['start_time']  = !empty($task['start_time']) ? date('Y-m-d', $task['start_time']) : '';
								$members[$k]['end_time']    = !empty($task['end_time']) ? date('Y-m-d', $task['end_time']) : '';
								$members[$k]['finish_time'] = !empty($task['finish_time']) ? date('Y-m-d', $task['finish_time']) : '';
								$members[$k]['avatar']      = $avatar;
								$members[$k]['name']        = $nameVal;
								$members[$k]['corp_name']   = $corpName;
								$members[$k]['per']         = $task['per'];
								$members[$k]['per_desc']    = $task['per_desc'];
								$members[$k]['title']       = $task['title'];
								$members[$k]['days']        = $task['days'];
								$members[$k]['level_color'] = $levelColor;
								$members[$k]['can_edit']    = $canEdit;
								$members[$k]['user_name']   = $userName;
								$members[$k]['level']       = $level;
								$members[$k]['follow_num']  = $followNum;
								$members[$k]['status']      = $status;
								$members[$k]['is_finish']   = $isFinish;
								$members[$k]['pre_days']    = $preDays;
								$members[$k]['delay_days']  = $delayDays;
								$members[$k]['type']        = $task['type'];
								$members[$k]['start_days']  = $task['start_days'];
								$members[$k]['nickname']    = $nickName;
							}
						}
						$info[$key]['members'] = $members;

					}
				}

				return [
					'info'    => $info,
				];
			} else {
				throw new InvalidParameterException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           H5待办详情
		 * @description     H5待办详情
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/wait-detail
		 *
		 * @param id 必选 int 详情ID
		 * @param uid 必选 int 账户ID
		 * @param userid 必选 int 员工ID
		 * @param corp_id 必选 string 企业ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @return_param    title string 项目名称
		 * @return_param    status string 执行状态
		 * @return_param    status_color string 状态颜色
		 * @return_param    level string 等级
		 * @return_param    level_color string 等级颜色
		 * @return_param    desc string 项目描述
		 * @return_param    leader string 项目负责人
		 * @return_param    start_time string 项目开始时间
		 * @return_param    end_time string 项目结束时间
		 * @return_param    finish_time string 项目实际完成时间
		 * @return_param    last_days string 剩余天数
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/19 9:45
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWaitDetail ()
		{
			$id         = \Yii::$app->request->post('id');
			$uid        = \Yii::$app->request->post('uid');
			$workUserId = \Yii::$app->request->post('userid', '');
			$corp_id    = \Yii::$app->request->post('corp_id', '');
			if (empty($id)) {
				throw new InvalidParameterException('参数错误！');
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $workUserId]);
			if (empty($workUser)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			$customTask = WaitCustomerTask::find()->alias('c')->leftJoin('{{%wait_task}} t', 'c.task_id=t.id');
			$customTask = $customTask->leftJoin('{{%wait_project}} p', 'p.id=t.project_id');
			$customTask = $customTask->where(['t.is_del' => 0, 'p.is_del' => 0, 'c.id' => $id]);
			$customTask = $customTask->select('p.desc,c.id,c.type as s_type,c.status,c.start_time,c.end_time,c.per,t.id as task_id,c.per_desc,c.finish_time,c.sea_id,p.user_id,p.level_id,c.external_userid,c.per,p.title,p.finish_time as days,t.days as start_days,t.type');
			$task       = $customTask->asArray()->one();
			$info       = [];
			if (!empty($task)) {
				$waitLevel  = WaitLevel::find()->where(['uid' => $uid])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatus = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->asArray()->all();

				$leader   = '';
				$taskUser = WorkUser::findOne($task['user_id']);
				if (!empty($taskUser)) {
					$leader = $taskUser->name;
				}
				$canEdit = 0;
				if ($workUser->id == $task['user_id']) {
					$canEdit = 1;
				}
				$level      = '';
				$levelColor = '';
				foreach ($waitLevel as $wl) {
					if ($wl['id'] == $task['level_id']) {
						$level      = $wl['title'];
						$levelColor = $wl['color'];
					}
				}
				$statusTitle = '';
				$statusColor = '';
				$statusId    = 0;
				foreach ($waitStatus as $ws) {
					if ($ws['id'] == $task['status']) {
						$statusTitle = $ws['title'];
						$statusColor = $ws['color'];
						$statusId    = $ws['id'];
					}
				}
				$info['can_edit']        = $canEdit;
				$info['sea_id']          = !empty($task['sea_id']) ? $task['sea_id'] : '';
				$info['external_userid'] = !empty($task['external_userid']) ? $task['external_userid'] : '';
				$info['task_id']         = !empty($task['task_id']) ? $task['task_id'] : 0;
				$info['title']           = $task['title'];
				$info['per']             = $task['per'];
				$info['per_desc']        = $task['per_desc'];
				$info['status_title']    = $statusTitle;
				$info['status_color']    = $statusColor;
				$info['status_id']       = $statusId;
				$info['level']           = $level;
				$info['level_color']     = $levelColor;
				$info['desc']            = $task['desc'];
				$info['leader']          = $leader;
				$info['start_time']      = !empty($task['start_time']) ? date('Y-m-d', $task['start_time']) : '';
				$info['end_time']        = !empty($task['end_time']) ? date('Y-m-d', $task['end_time']) : '';
				$info['finish_time']     = !empty($task['finish_time']) ? date('Y-m-d', $task['finish_time']) : '';
				$isFinish             = 0; //未完成
				$delayDays            = 0;
				if (!empty($task['finish_time']) && $task['finish_time'] > $task['end_time']) {
					//超时
					$delayDays = ceil(($task['finish_time'] - $task['end_time']) / (24 * 3600));
					$isFinish  = 2;//超时完成
				}

				$preDays = 0;
				if (!empty($task['finish_time']) && $task['finish_time'] <= $task['end_time']) {
					$date1 = date('Y-m-d', $task['finish_time']);
					$date2 = date('Y-m-d', $task['end_time']);
					if ($date1 == $date2) {
						$isFinish = 1; //按时完成
					} else {
						//提前
						$preDays  = floor(($task['end_time'] - $task['finish_time']) / (24 * 3600));
						$isFinish = 3; //提前
					}

				}
				$lastDays = 0;
				if (!empty($task['end_time']) && $task['end_time'] > time()) {
					$lastDays = floor(($task['end_time'] + 1 - time()) / (24 * 3600));
				}
				$info['last_days']  = $lastDays;
				$info['is_finish']  = $isFinish;
				$info['pre_days']   = $preDays;
				$info['delay_days'] = $delayDays;

			}

			return $info;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           获取所有项目
		 * @description     获取所有项目
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/get-project
		 *
		 * @param corp_id 必选 string 企业ID
		 *
		 * @return          {"error":0,"data":[{"id":"102","title":"项目3"},{"id":"103","title":"项目2"},{"id":"104","title":"项目1"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/23 9:39
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetProject ()
		{
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$result = WaitProject::find()->where(['corp_id' => $corpInfo->id, 'is_del' => 0])->select('id,title')->orderBy(['sort' => SORT_ASC])->asArray()->all();

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           待办工作台
		 * @description     待办工作台
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/desk-wait
		 *
		 * @param uid 必选 int 账户id
		 * @param userid 必选 string 员工id
		 * @param status_id 必选 string 状态ID
		 * @param project_id 必选 string 项目ID
		 * @param corp_id 必选 string 企业微信id
		 * @param page 可选 string 当前页
		 * @param page_size 可选 string 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id用于添加项目跟进
		 * @return_param    show_project int 是否显示项目跟进按钮0不显示1显示
		 * @return_param    show_start int 是否显示开始按钮0不显示1显示
		 * @return_param    title string 项目名称
		 * @return_param    status string 执行状态
		 * @return_param    status_color string 状态颜色
		 * @return_param    level string 等级
		 * @return_param    level_color string 等级颜色
		 * @return_param    desc string 项目描述
		 * @return_param    per string 项目百分比
		 * @return_param    leader string 项目负责人
		 * @return_param    start_time string 项目开始时间
		 * @return_param    end_time string 项目结束时间
		 * @return_param    finish_time string 项目实际完成时间
		 * @return_param    days string 超时天数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/23 13:30
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionDeskWait ()
		{
			$uid          = \Yii::$app->request->post('uid', 0);
			$userId       = \Yii::$app->request->post('userid', '');
			$statusId     = \Yii::$app->request->post('status_id', 0);
			$projectId    = \Yii::$app->request->post('project_id', 0);
			$corpId       = \Yii::$app->request->post('corp_id', '');
			$page         = \Yii::$app->request->post('page', 1);
			$pageSize     = \Yii::$app->request->post('page_size', 10);
			$user_ids     = \Yii::$app->request->post('user_ids', []);
			$customId     = \Yii::$app->request->post('custom_id', []);
			$url_user_ids = \Yii::$app->request->post('url_user_ids', []);
			$from         = \Yii::$app->request->post('from', 0);
			if (empty($uid) || empty($userId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corpId]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userId]);
			if (empty($workUser)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			$offset       = ($page - 1) * $pageSize;
			$customerTask = WaitCustomerTask::find()->alias('c')->leftJoin('{{%wait_task}} t', 'c.task_id=t.id');
			$customerTask = $customerTask->leftJoin('{{%wait_project}} p', 't.project_id=p.id');
			if (!empty($statusId)) {
				$customerTask = $customerTask->andWhere(['c.status' => $statusId]);
			}
			if (!empty($projectId)) {
				$customerTask = $customerTask->andWhere(['p.id' => $projectId]);
			}
			if (!empty($customId) && $customId[0] != -1) {
				$customerTask = $customerTask->andWhere(['c.id' => $customId]);
			}

			if (empty($from)) {
				$userIds   = $user_ids;
				$userData  = AuthoritySubUserDetail::getUserIds($userId, $this->user->uid, $this->corp->id, $user_ids);
				$user_ids  = $userData['user_ids'];
				$show      = $userData['show'];
				$userCount = $userData['userCount'];
			} else {
				$userCount = 0;
				if (empty($user_ids)) {
					$user_ids = $url_user_ids;
				} else {
					$uIds      = WorkUser::getDepartUser($corpInfo->id, $user_ids);
					if (!empty($uIds)) {
						$userCount = count($uIds);
						$user_ids  = $uIds;
					}
				}
				if (count($url_user_ids) <= 1) {
					$show = 1;
				} else {
					$show = 0;
				}
			}




//			if (empty($from)) {
//				$userIds   = $user_ids;
//				$userData  = AuthoritySubUserDetail::getUserIds($userId, $this->user->uid, $this->corp->id, $user_ids);
//				$user_ids  = $userData['user_ids'];
//				$show      = $userData['show'];
//				$userCount = 0;
//				if (!empty($user_ids)) {
//					$userCount = count($user_ids);
//				}
//				if (empty($userIds)) {
//					$userCount = 0;
//				}
//			} else {
//				$show      = 1;
////				$userCount = count($user_ids);
////				if ($userCount > 1) {
////					$show = 0;
////				}
//			}
//
//			if (!empty($url_user_ids)) {
//				$userCount = 0;
//				$user_ids  = $url_user_ids;
//				if (count($url_user_ids) > 1) {
//					$show = 0;
//				} else {
//					$show = 1;
//				}
//			}

			$customerTask = $customerTask->andWhere(['p.is_del' => 0, 't.is_del' => 0, 'p.corp_id' => $corpInfo->id]);
			if (!empty($user_ids)) {
				$customerTask = $customerTask->andWhere(['p.user_id' => $user_ids]);
			}
			$count        = $customerTask->count();
			$customerTask = $customerTask->limit($pageSize)->offset($offset)->select('p.title,p.level_id,p.desc,p.user_id,t.follow_id,c.start_time,c.end_time,c.finish_time,c.status,c.per,c.id,t.id as task_id,c.external_userid,c.sea_id');
			$customerTask = $customerTask->asArray()->all();
			$info         = [];
			if (!empty($customerTask)) {
				$waitLevel     = WaitLevel::find()->where(['uid' => $uid])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatus    = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_ASC])->asArray()->all();
				$waitStatusOne = WaitStatus::find()->where(['uid' => $uid, 'is_del' => 0])->orderBy(['sort' => SORT_DESC])->one();
				foreach ($customerTask as $key => $task) {
					$showStart    = 0; //是否显示开始按钮 0不显示 1显示
					$showProject  = 0; //是否显示项目跟进按钮 0不显示 1显示
					$followNum = WaitProjectFollow::find()->where(['customer_task_id' => $task['id']])->count();
					if ($task['status'] == $waitStatus[0]['id'] && $workUser->id == $task['user_id']) {
						$showStart = 1;
					}
					if ($task['status'] != $waitStatusOne->id && $workUser->id == $task['user_id']) {
						$showProject = 1;
					}
					$leader   = '';
					$taskUser = WorkUser::findOne($task['user_id']);
					if (!empty($taskUser)) {
						$leader = $taskUser->name;
					}
					$level      = '';
					$levelColor = '';
					foreach ($waitLevel as $wl) {
						if ($wl['id'] == $task['level_id']) {
							$level      = $wl['title'];
							$levelColor = $wl['color'];
						}
					}
					$statusTitle = '';
					$statusColor = '';
					foreach ($waitStatus as $ws) {
						if ($ws['id'] == $task['status']) {
							$statusTitle = $ws['title'];
							$statusColor = $ws['color'];
						}
					}
					$info[$key]['id']              = $task['id'];
					$info[$key]['task_id']         = $task['task_id'];
					$info[$key]['external_userid'] = !empty($task['external_userid']) ? $task['external_userid'] : '';
					$info[$key]['sea_id']          = !empty($task['sea_id']) ? $task['sea_id'] : '';
					$info[$key]['show_project']    = $showProject;
					$info[$key]['show_start']      = $showStart;
					$info[$key]['title']           = $task['title'];
					$info[$key]['status_id']       = $task['status'];
					$info[$key]['status_title']    = $statusTitle;
					$info[$key]['status_color']    = $statusColor;
					$info[$key]['level']           = $level;
					$info[$key]['follow_id']       = $task['follow_id'];
					$info[$key]['level_color']     = $levelColor;
					$info[$key]['desc']            = $task['desc'];
					$info[$key]['follow_num']      = $followNum;
					$info[$key]['per']             = !empty($task['per']) ? intval($task['per']) : 0;
					$info[$key]['leader']          = $leader;
					$info[$key]['start_time']      = !empty($task['start_time']) ? date('Y-m-d', $task['start_time']) : '';
					$info[$key]['end_time']        = !empty($task['end_time']) ? date('Y-m-d', $task['end_time']) : '';
					$info[$key]['finish_time']     = !empty($task['finish_time']) ? date('Y-m-d', $task['finish_time']) : '';
					$status                        = 2;//处理中
					if ($task['status'] == $waitStatus[0]['id']) {
						$status = 1;//待处理
					}
					if ($task['status'] == $waitStatusOne->id) {
						$status = 3;//已完成
					}
					$info[$key]['status'] = $status;
					$isFinish             = 0; //未完成
					$delayDays            = 0;
					if (!empty($task['finish_time']) && $task['finish_time'] > $task['end_time']) {
						//超时
						$delayDays = ceil(($task['finish_time'] - $task['end_time']) / (24 * 3600));
						$isFinish  = 2;//超时完成
					}

					$preDays = 0;
					if (!empty($task['finish_time']) && $task['finish_time'] <= $task['end_time']) {
						$date1 = date('Y-m-d', $task['finish_time']);
						$date2 = date('Y-m-d', $task['end_time']);
						if ($date1 == $date2) {
							$isFinish = 1; //按时完成
						} else {
							//提前
							$preDays  = floor(($task['end_time'] - $task['finish_time']) / (24 * 3600));
							$isFinish = 3; //提前
						}

					}
					$lastDays = 0;
					if (!empty($task['end_time']) && $task['end_time'] > time()) {
						$lastDays = floor(($task['end_time'] + 1 - time()) / (24 * 3600));
					}
					$info[$key]['last_days']  = $lastDays;
					$info[$key]['is_finish']  = $isFinish;
					$info[$key]['pre_days']   = $preDays;
					$info[$key]['delay_days'] = $delayDays;

					$name                     = '';
					$avatar                   = '';
					$corpName                 = NULL;
					if (!empty($task['external_userid'])) {
						$contact = WorkExternalContact::findOne($task['external_userid']);
						if (!empty($contact)) {
							$name     = $contact->name;
							$avatar   = $contact->avatar;
							$corpName = $contact->corp_name;
						}
					}
					if (!empty($task['sea_id'])) {
						$sea = PublicSeaCustomer::findOne($task['sea_id']);
						if (!empty($sea)) {
							$name = $sea->name;
						}
					}
					$info[$key]['name']      = $name;
					$info[$key]['avatar']    = $avatar;
					$info[$key]['corp_name'] = $corpName;
				}

			}

			return [
				'user_count' => $userCount,
				'count'      => $count,
				'info'       => $info,
				'show'       => $show
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           自定义项目阶段排序
		 * @description     自定义项目阶段排序
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/wait-status-sort
		 *
		 * @param uid 必选 int 用户ID
		 * @param ids 必选 array 阶段ID
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/9 9:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionWaitStatusSort ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$ids = \Yii::$app->request->post('ids');
			if (empty($uid) || empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			foreach ($ids as $key => $id) {
				$sort       = $key + 1;
				$waitStatus = WaitStatus::findOne(['id' => $id, 'is_del' => 0]);
				if (!empty($waitStatus)) {
					$waitStatus->sort = $sort;
					$waitStatus->update();
				}

			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           优先级排序
		 * @description     优先级排序
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/wait-level-sort
		 *
		 * @param uid 必选 int 用户ID
		 * @param ids 必选 array 阶段ID
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/9 9:38
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionWaitLevelSort ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$ids = \Yii::$app->request->post('ids');
			if (empty($uid) || empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			foreach ($ids as $key => $id) {
				$sort      = $key + 1;
				$waitLevel = WaitLevel::findOne($id);
				if (!empty($waitLevel)) {
					$waitLevel->sort = $sort;
					$waitLevel->update();
				}

			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wait-project/
		 * @title           优先级排序
		 * @description     优先级排序
		 * @method   post
		 * @url  http://{host_name}/api/wait-project/del-project-task
		 *
		 * @param id 必选 int 项目/任务ID
		 * @param type 必选 int 0项目1任务
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/11/9 14:22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDelProjectTask ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$id        = \Yii::$app->request->post('id', 0);
			$type      = \Yii::$app->request->post('type', 0);
			$projectId = \Yii::$app->request->post('project_id', 0);
			if (empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($type)) {
				$project = WaitProject::findOne($id);
				if (empty($project)) {
					throw new InvalidDataException('当前项目不存在！');
				}
			} else {
				$waitTask = WaitTask::find()->where(['follow_id' => $id])->select('id')->asArray()->all();
				if (empty($waitTask)) {
					throw new InvalidDataException('当前任务不存在！');
				} else {
					$id = array_column($waitTask, 'id');
				}
			}

			$isDel        = 0;
			$customerTask = WaitCustomerTask::find()->alias('c')->leftJoin('{{%wait_task}} t', 'c.task_id=t.id');
			$customerTask = $customerTask->leftJoin('{{%wait_project}} p', 't.project_id=p.id');
			if (empty($type)) {
				$customerTask = $customerTask->where(['p.id' => $id, 'p.is_del' => 0, 't.is_del' => 0]);
				$customerTask = $customerTask->one();
			} else {
				$customerTask = $customerTask->where(['t.id' => $id, 'p.is_del' => 0, 't.is_del' => 0]);
				if (!empty($projectId)) {
					$customerTask = $customerTask->andWhere(['p.id' => $projectId]);
				}
				$customerTask = $customerTask->one();
			}
			if (!empty($customerTask)) {
				$isDel = 1;
			}

			return [
				'is_del' => $isDel
			];
		}




	}