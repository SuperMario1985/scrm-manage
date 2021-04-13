<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkUser;
	use app\models\WorkUserCommissionRemind;
	use app\models\WorkUserCommissionRemindTime;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;

	class WorkUserCommissionRemindController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-commission-remind/add-remind
		 * @title           员工代办通知添加-添加
		 * @description     员工代办通知添加-添加
		 * @method   post
		 * @url  http://{host_name}/api/work-user-commission-remind/add-remind
		 *
		 * @param agent_id 必选 int 应用id
		 * @param user_id 可选 array 选择员工
		 * @param department 可选 array 部门id
		 * @param inform_user 可选 array 可看员工删除被通知人old
		 * @param open_status 可选 1 状态默认0不开启1开启
		 * @param frequency 可选 int 状态：1每次2每天9点,3每月第一天9点
		 * @param remindId 可选 array|int 列表页传数组，编辑页传int
		 * @param is_edit 可选 string 是否编辑
		 * @param time_select 可选 array 时间段
		 *
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-17 16:40
		 * @number          0
		 *
		 */
		public function actionAddRemind ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id         = $this->corp->id;
			$agent           = \Yii::$app->request->post('agent_id');
			$user_id         = \Yii::$app->request->post('user_id');
			$department_key  = \Yii::$app->request->post('department');
			$inform_user_key = \Yii::$app->request->post('inform_user');
			$type            = \Yii::$app->request->post('type', 1);
			$open_status     = \Yii::$app->request->post('open_status', 1);
			$tmpFrequency    = \Yii::$app->request->post('frequency');
			$timeSelectData  = \Yii::$app->request->post('time_select');
			$remindId        = \Yii::$app->request->post('remindId');
			$is_edit         = \Yii::$app->request->post('is_edit');
			if (empty($user_id) && empty($is_edit)) {
				throw new InvalidDataException("未选中成员");
			}
			if ((empty($inform_user_key) && !empty($department_key)) || !empty($department_key)) {
				$type = 2;
			} elseif (!empty($inform_user_key) && empty($department_key)) {
				$type = 3;
			}
			if (!empty($remindId) && !empty($remindId) && is_array($remindId)) {
				WorkUserCommissionRemind::updateAll(["open_status" => $open_status], ["in", "id", $remindId]);

				return [];
			}
			if ($is_edit == 1 && !empty($remindId) && !is_array($remindId)) {
				$remindDetail               = WorkUserCommissionRemind::find()->where(["id" => $remindId])->asArray()->one();
				$remindDetail["department"] = empty($remindDetail["department"]) ? [] : $remindDetail["department"];
				if (!empty($remindDetail["inform_user_key"])) {
					$inform_user = json_decode($remindDetail["inform_user_key"], true);
					foreach ($inform_user as &$item) {
						if (!isset($item['scopedSlots'])) {
							$item['scopedSlots'] = ['title' => 'custom'];
						}
					}
					$remindDetail["inform_user"] = $inform_user;
				}
				if(!empty($remindDetail["department"])){
					$remindDetail["department"] = explode(",",$remindDetail["department"]);
				}
				$remindDetail["frequency"]  = explode(",", $remindDetail["frequency"]);
				$remindDetail["selectData"] = WorkUserCommissionRemindTime::find()->where(["remind_id" => $remindId])->asArray()->all();

				return $remindDetail;
			}
			$department  = implode(",", $department_key);
			$inform_user = [];
			if (!empty($inform_user_key)) {
				foreach ($inform_user_key as $key => $value) {
					if (is_array($value)) {
						$value = $value["id"];
					}
					if (strpos($value, 'd') === false) {
						array_push($inform_user, $value);
					}
				}
			}
			$timeSelect  = in_array(1, $tmpFrequency);
			$frequency   = implode(",", $tmpFrequency);
			$Transaction = \Yii::$app->db->beginTransaction();
			try {
				foreach ($user_id as $item) {
					$exists = WorkUserCommissionRemind::find()->where(["corp_id" => $corp_id, "agent" => $agent, "user_id" => $item["id"]])->exists();
					if ($exists) {
						throw new InvalidDataException("该员工" . $item["title"] . "已配置");
					}
					$remind                  = new WorkUserCommissionRemind();
					$remind->corp_id         = $corp_id;
					$remind->agent           = $agent;
					$remind->user_id         = $item["id"];
					$remind->type            = $type;
					$remind->inform_user     = implode(",", $inform_user);
					$remind->inform_user_key = empty($inform_user_key) ? '[]' : json_encode($inform_user_key, 255);
					$remind->department      = $department;
					$remind->open_status     = $open_status;
					$remind->frequency       = $frequency;
					$remind->create_time     = time();
					$remind->save();

					if ($timeSelect) {
						if (empty($timeSelectData)) {
							$Transaction->rollBack();
							throw new InvalidDataException("未选择任何时间段");
						}
						WorkUserCommissionRemindTime::setRemindTimeSelect($remind->id, $timeSelectData);
					}
				}
				$Transaction->commit();

				return ["error" => 0];
			} catch (\Exception $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-commission-remind/user-remind-edit
		 * @title           员工代办通知添加-修改
		 * @description     员工代办通知添加-修改
		 * @method   post
		 * @url  http://{host_name}/api/work-user-commission-remind/user-remind-edit
		 *
		 * @param department 可选 array 部门id
		 * @param inform_user 可选 array 可看员工删除被通知人
		 * @param open_status 可选 1 状态默认0不开启1开启
		 * @param frequency 可选 int 状态：1每次2每天9点
		 * @param remindId 可选 array 编辑id，
		 * @param time_select 可选 array 时间段
		 *
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-17 16:40
		 * @number          0
		 *
		 */
		public function actionUserRemindEdit ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id         = $this->corp->id;
			$department_key  = \Yii::$app->request->post('department');
			$inform_user_key = \Yii::$app->request->post('inform_user');
			$type            = \Yii::$app->request->post('type', 1);
			$tmpFrequency    = \Yii::$app->request->post('frequency');
			$timeSelectData  = \Yii::$app->request->post('time_select');
			$remindId        = \Yii::$app->request->post('remindId');
			if (empty($remindId)) {
				throw new InvalidDataException("未选中成员");
			}
			if ((empty($inform_user_key) && !empty($department_key)) || !empty($department_key)) {
				$type = 2;
			} elseif (!empty($inform_user_key) && empty($department_key)) {
				$type = 3;
			}
			$department = $inform_user = '';
			if (!empty($department_key)) {
				$department = implode(",", $department_key);
			}
			$inform_user = [];
			if (!empty($inform_user_key)) {
				foreach ($inform_user_key as $key => $value) {
					if (is_array($value)) {
						$value = $value["id"];
					}
					if (strpos($value, 'd') === false) {
						array_push($inform_user, $value);
					}
				}
			}
			$timeSelect = in_array(1, $tmpFrequency);
			$frequency  = implode(",", $tmpFrequency);


			$Transaction = \Yii::$app->db->beginTransaction();
			try {
				/** @var WorkUserCommissionRemind $commissionRemind * */
				$WorkUserCommissionRemind = WorkUserCommissionRemind::find()->where(['in', 'id', $remindId])->all();
				$inform_user              = empty($inform_user) ? [] : $inform_user;

				foreach ($WorkUserCommissionRemind as $commissionRemind) {
					$commissionRemind->type            = $type;
					$commissionRemind->inform_user     = implode(",", $inform_user);
					$commissionRemind->inform_user_key = empty($inform_user_key) ? '[]' : json_encode($inform_user_key, 255);
					$commissionRemind->department      = $department;
					$commissionRemind->frequency       = $frequency;
					$commissionRemind->save();
					if ($timeSelect) {
						if (empty($timeSelectData)) {
							$Transaction->rollBack();
							throw new InvalidDataException("未选择任何时间段");
						}
						WorkUserCommissionRemindTime::setRemindTimeSelect($commissionRemind->id, $timeSelectData);

					} else {
						WorkUserCommissionRemindTime::deleteAll(["remind_id" => $commissionRemind->id]);
					}
				}
				$Transaction->commit();
			} catch (\Exception $e) {
				$Transaction->rollBack();
				\Yii::error($e->getMessage(), "sym-run-time");
				throw new InvalidDataException($e->getMessage());
			}

			return ["error" => 0];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-commission-remind/user-remind-lists
		 * @title           员工代办配置成员列表
		 * @description     员工代办配置成员列表
		 * @method   post
		 * @url  http://{host_name}/api/work-user-commission-remind/user-remind-lists
		 *
		 * @param corp_id 必选 int 应用id
		 * @param agent_id 必选 int 应用id
		 * @param open_status 可选 int 状态默认0不开启1开启
		 * @param name 可选 string 名称
		 *
		 *
		 * @return_param    error int 状态码
		 * @return_param    dataIds array 所有成员id用于批量编辑
		 * @return_param    data array
		 * @return_param    inform_user_key array 删除成员被通知
		 * @return_param    name string 企业员工名称
		 * @return_param    avatar string 企业员工头像
		 * @return_param    department_name string 企业员工部门
		 * @return_param    open_status int 空全部0关闭1开启
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-17 16:40
		 * @number          0
		 *
		 */
		public function actionUserRemindLists ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$corp_id     = \Yii::$app->request->post("corp_id");
			$agent       = \Yii::$app->request->post("agent_id");
			$open_status = \Yii::$app->request->post("open_status");
			$name        = \Yii::$app->request->post("name");
			$page        = \Yii::$app->request->post("page");
			$pageSize    = \Yii::$app->request->post("pageSize");
			$page        = ($page > 0) ? $page : 1;
			$offset      = ($page - 1) * $pageSize;
			$corp        = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corp)) {
				throw new InvalidDataException("企业微信不存在");
			}
			$corp_id = $corp->id;
			$result  = WorkUserCommissionRemind::find()->alias("a")
				->leftJoin("{{%work_user}} as b", "a.user_id=b.id");
			if (!empty($corp)) {
				$result = $result->andWhere(["a.corp_id" => $corp_id]);
			}
			if (!empty($agent)) {
				$result = $result->andWhere(["a.agent" => $agent]);
			}
			if ($open_status === 0 || $open_status === 1) {
				$result = $result->andWhere(["a.open_status" => $open_status]);
			}
			if (!empty($name)) {
				$result = $result->andWhere("b.name like '%$name%'");
			}
			$count       = $result->count();
			$resultModel = clone $result;
			$res         = $result->offset($offset)->limit($pageSize)
				->select("a.*,b.name,b.avatar,b.department as departments,b.gender")->orderBy("a.create_time desc")->asArray()->all();
			foreach ($res as &$re) {
				$re["inform_user_key"] = json_decode($re["inform_user_key"], true);
				$departments           = explode(",", $re["department"]);
				$inform_user           = array_column($re["inform_user_key"], "id");
				$re['part']            = [];
				$re['user_names']      = [];
				if (!empty($departments)) {
					$part       = WorkDepartment::find()->where(["in", "department_id", $departments])->andWhere(["corp_id" => $re["corp_id"]])->select("name")->asArray()->all();
					$re['part'] = array_column($part, "name");
				}
				if (!empty($inform_user)) {
					$tagsName         = WorkUser::find()->where(["in", "id", $inform_user])->select("name")->asArray()->all();
					$re['user_names'] = array_column($tagsName, "name");
				}
				if (!empty($re["departments"])) {
					$departName = WorkDepartment::getDepartNameByUserId($re["departments"], $re["corp_id"]);
				} else {
					$departName = '';
				}
				$re['department_name'] = $departName;
			}
			$resIds = $resultModel->select("a.*,b.name,b.avatar,b.department as departments,b.gender")->asArray()->all();
			$resIds = array_column($resIds, "id");

			return ["data" => $res, "dataIds" => $resIds, "count" => $count];
		}

	}