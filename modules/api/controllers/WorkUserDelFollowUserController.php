<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkPerTagFollowUser;
	use app\models\WorkTagContact;
	use app\models\WorkUser;
	use app\models\WorkUserDelFollowUser;
	use app\models\WorkUserDelFollowUserDetail;
	use app\modules\api\components\WorkBaseController;
	use moonland\phpexcel\Excel;
	use yii\db\Expression;

	class WorkUserDelFollowUserController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-del-follow-user/add-user-del-data
		 * @title           员工删除通知添加-修改-编辑
		 * @description     员工删除通知添加-修改-编辑
		 * @method   post
		 * @url  http://{host_name}/api/work-user-del-follow-user/add-user-del-data
		 *
		 * @param agent_id 必选 int 应用id
		 * @param user_id 可选 array 选择员工
		 * @param department 可选 array 部门id
		 * @param inform_user 可选 array 可看员工删除被通知人old
		 * @param open_status 可选 1 状态默认0不开启1开启
		 * @param frequency 可选 int 状态：1每次2每天9点
		 * @param userDelId 可选 array|int 列表页传数组，编辑页传int
		 * @param is_edit 可选 string 是否编辑
		 *
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-17 16:40
		 * @number          0
		 *
		 */
		public function actionAddUserDelData ()
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
			$open_status     = \Yii::$app->request->post('open_status', 0);
			$frequency       = \Yii::$app->request->post('frequency');
			$userDelId       = \Yii::$app->request->post('userDelId');
			$is_edit         = \Yii::$app->request->post('is_edit');
			if ((empty($inform_user_key) && !empty($department_key)) || !empty($department_key)) {
				$type = 2;
			} elseif (!empty($inform_user_key) && empty($department_key)) {
				$type = 3;
			}
			if ($is_edit == 1 && !empty($userDelId) && is_array($userDelId) && empty($frequency)) {
				WorkUserDelFollowUser::updateAll(["open_status" => $open_status], ["in", "id", $userDelId]);

				return [];
			}

			if ($is_edit == 1 && !empty($userDelId) && !is_array($userDelId)) {
				$userDel = WorkUserDelFollowUser::findOne($userDelId);
				if (!empty($userDel["inform_user_key"])) {
					$inform_user = json_decode($userDel["inform_user_key"], true);
					foreach ($inform_user as &$item) {
						$item["id"] = isset($item["id"]) ? (string) $item["id"] : '';
						$workUser   = WorkUser::findOne(isset($item["id"]) ? $item["id"] : 0);
						if (!empty($workUser)) {
							$str           = ($workUser->is_del == 1) ? '（已删除）' : '';
							$str           = ($workUser->status == 5) ? '（已退出）' : $str;
							$item['title'] = $item['name'] = $workUser->name . $str;
						}
						if (!isset($item['scopedSlots'])) {
							$item['scopedSlots'] = ['title' => 'custom'];
						}
					}
					$userDel["inform_user"] = $inform_user;
				}
				$userDel["frequency"] = explode(",", $userDel["frequency"]);

				return $userDel;
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
			$frequency = implode(",", $frequency);

			if (!empty($user_id)) {
				$Temp    = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
			}
			foreach ($user_id as $item) {
				$userDel = WorkUserDelFollowUser::find()->where(["user_id" => $item, "corp_id" => $corp_id, "agent" => $agent])->one();
				if (!empty($userDel)) {
					throw new InvalidDataException("该成员已存在");
				}
				$userDel                  = new WorkUserDelFollowUser();
				$userDel->corp_id         = $corp_id;
				$userDel->agent           = $agent;
				$userDel->user_id         = $item;
				$userDel->create_time     = time();
				$userDel->type            = $type;
				$userDel->inform_user     = implode(",", $inform_user);
				$userDel->inform_user_key = json_encode($inform_user_key, 255);
				$userDel->department      = $department;
				$userDel->open_status     = $open_status;
				$userDel->frequency       = $frequency;
				$userDel->save();
			}

			return [];
		}

		public function actionUserDelUserEdit ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式错误");
			}
			$department_key  = \Yii::$app->request->post('department');
			$inform_user_key = \Yii::$app->request->post('inform_user');
			$type            = \Yii::$app->request->post('type', 1);
			$frequency       = \Yii::$app->request->post('frequency');
			$userDelId       = \Yii::$app->request->post('userDelId');
			if ((empty($inform_user_key) && !empty($department_key)) || !empty($department_key)) {
				$type = 2;
			} elseif (!empty($inform_user_key) && empty($department_key)) {
				$type = 3;
			}
			$department = '';
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
			$frequency = implode(",", $frequency);
			foreach ($userDelId as $item) {
				$userDel                  = WorkUserDelFollowUser::findOne($item);
				$userDel->type            = $type;
				$userDel->inform_user     = implode(",", $inform_user);
				$userDel->inform_user_key = json_encode($inform_user_key, 255);
				$userDel->department      = $department;
				$userDel->frequency       = $frequency;
				$userDel->save();
			}

			return [];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-del-follow-user/user-del-lists
		 * @title           员工配置成员列表
		 * @description     员工配置成员列表
		 * @method   post
		 * @url  http://{host_name}/api/work-user-del-follow-user/user-del-lists
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
		 * @return_param    open_status int 0关闭1开启
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-17 16:40
		 * @number          0
		 *
		 */
		public function actionUserDelLists ()
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
			$page        = !empty($page) ? $page : 1;
			$pageSize    = !empty($pageSize) ? $pageSize : 15;
			$offset      = ($page - 1) * $pageSize;
			$corp        = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corp)) {
				throw new InvalidDataException("企业微信不存在");
			}
			$corp_id = $corp->id;
			$result  = WorkUserDelFollowUser::find()->alias("a")
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
			$resultModel = clone $result;
			$count       = $result->count();
			$res         = $result->offset($offset)->limit($pageSize)
				->select("a.*,b.name,b.avatar,b.department as departments,b.gender")
				->orderBy("a.create_time desc")->asArray()->all();
			foreach ($res as &$re) {
				$re["inform_user_key"] = json_decode($re["inform_user_key"], true);
				$departments           = explode(",", $re["department"]);
				$inform_user           = explode(",", $re["inform_user"]);
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
					$departName = WorkUserDelFollowUser::getDepartNameByUserId($re["departments"], $re["corp_id"]);
				} else {
					$departName = '';
				}
				$re['department_name'] = $departName;
			}
			$resIds = $resultModel->select("a.*,b.name,b.avatar,b.department as departments,b.gender")
				->orderBy("a.create_time desc")->asArray()->all();
			$resIds = array_column($resIds, "id");

			return ["data" => $res, "dataIds" => $resIds, "count" => $count];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-del-follow-user/user-del-follow-lists-detail
		 * @title           员工删除列表
		 * @description     员工删除列表
		 * @method   post
		 * @url  http://{host_name}/api/work-user-del-follow-user/user-del-follow-lists-detail
		 *
		 * @param time_type 可选 int 1删除时间2添加时间
		 * @param sData     可选 string 开始时间
		 * @param eData     可选 string 结束时间
		 *
		 * @return_param    error int 状态码
		 * @return_param    name string 客户名称
		 * @return_param    names string 员工名称
		 * @return_param    avatar string 客户头像
		 * @return_param    status int 是当天重复删除
		 * @return_param    userName string 归属成员
		 * @return_param    perName array 私有标签
		 * @return_param    tagName array 标签
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-18 15:40
		 * @number          0
		 *
		 */
		public function actionUserDelFollowListsDetail ()
		{
			$corp_id   = \Yii::$app->request->post("corp_id");
			$agent     = \Yii::$app->request->post("agent_id");
			$user_depart_id = \Yii::$app->request->post("user_ids", []);
			$time_type = \Yii::$app->request->post('time_type', 0);
			$sData     = \Yii::$app->request->post('sData', '');
			$eData     = \Yii::$app->request->post('eData', '');
			$page      = \Yii::$app->request->post("page", 0);
			$pageSize  = \Yii::$app->request->post("pageSize", 15);
			$is_export = \Yii::$app->request->post('is_export', 0);
			$is_all    = \Yii::$app->request->post('is_all', 0);
			$corp      = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corp)) {
				throw new InvalidDataException("企业微信不存在");
			}
			$corp_id  = $corp->id;
			$page     = !empty($page) ? $page : 1;
			$pageSize = !empty($pageSize) ? $pageSize : 15;
			$offset   = ($page - 1) * $pageSize;
			$select   = new Expression("a.*,b.name,b.corp_name,b.gender,c.gender as sex,b.avatar,c.avatar as work_avatar,c.name as names,d.corp_name as u_corp_name,e.createtime addtime,FROM_UNIXTIME(a.create_time,'%Y-%m-%d') as creates");
			$res      = WorkUserDelFollowUserDetail::find()->alias("a")
				->leftJoin("{{%work_external_contact}} as b", "a.external_userid = b.id")
				->leftJoin("{{%work_user}} as c", "c.id = a.user_id")
				->leftJoin("{{%work_corp}} as d", "a.corp_id = d.id")
				->leftJoin("{{%work_external_contact_follow_user}} as e", "a.external_userid = e.external_userid and a.user_id = e.user_id");

			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;

			if ($sub_id) {
				$user_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if (is_array($user_ids)) {
					$res = $res->andWhere(["in", "a.user_id", $user_ids]);
				}
				if ($user_ids === false) {
					return ['count' => 0, 'customerData' => []];
				}
			}
			if (!empty($corp_id)) {
				$res = $res->andWhere(["a.corp_id" => $corp_id]);
			}
			if (!empty($agent)) {
				$res = $res->andWhere(["a.agent" => $agent]);
			}

			if (!empty($user_depart_id)) {
				$Temp    = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_depart_id);
				$user_depart_id = WorkDepartment::GiveDepartmentReturnUserData($corp_id, $Temp["department"], $Temp["user"], 0, true, 0, [], $sub_id);
				if (empty($user_depart_id)) {
					$user_depart_id = [0];
				}
				$res = $res->andWhere(["in", "a.user_id", $user_depart_id]);
			}

			if (!empty($sData) && !empty($eData)){
				if ($time_type == 1){
					$res = $res->andFilterWhere(['between', 'a.create_time', strtotime($sData), strtotime($eData . ' 23:59:59')]);
				} elseif ($time_type == 2) {
					$res = $res->andFilterWhere(['between', 'e.createtime', strtotime($sData), strtotime($eData . ' 23:59:59')]);
				}
			}

			$tmp   = $res;
			$count = $tmp->groupBy("a.user_id,a.external_userid")->count();
			if (empty($is_all)) {
				$res = $res->limit($pageSize)->offset($offset);
			}
			$res = $res->select($select)
				->groupBy("a.user_id,a.external_userid,creates")
				->orderBy("a.create_time desc")
				->asArray()->all();
			foreach ($res as &$re) {
				$re["status"]  = $re["repetition"];
				$followId      = WorkExternalContactFollowUser::find()->where(["external_userid" => $re["external_userid"]])->select("id")->asArray()->all();
				$followId      = array_unique(array_column($followId, "id"));
				$re["perName"] = [];
				$re["tagName"] = [];
				foreach ($followId as $v) {
					$perName       = WorkPerTagFollowUser::getTagName($v, 1, [$re["user_id"]]);
					$tagName       = WorkTagContact::getTagNameByContactId($v, 0, 1, [$re["user_id"]], $corp->id);
					$re["perName"] = array_merge($perName, $re["perName"]);
					$re["tagName"] = array_merge($tagName, $re["tagName"]);
				}
				$re["perName"]     = array_values(array_unique($re["perName"]));
				$re["tagName"]     = array_values(array_unique($re["tagName"]));
				$re["name"]        = urldecode($re['name']);
				$re["userName"]    = urldecode($re['names']) . "-" . $re['u_corp_name'];
				$re["create_time"] = date("Y-m-d H:i", $re["create_time"]);
				$re["add_time"]    = date("Y-m-d H:i", $re["addtime"]);
			}

			//导出
			if ($is_export == 1) {
				if (empty($res)) {
					throw new InvalidDataException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				foreach ($res as $k => $v) {
					if ($v['sex'] == 1) {
						$sex = '男性';
					} elseif ($v['sex'] == 2) {
						$sex = '女性';
					} else {
						$sex = '未知';
					}
					if ($v['gender'] == 1) {
						$gender = '男性';
					} elseif ($v['gender'] == 2) {
						$gender = '女性';
					} else {
						$gender = '未知';
					}
					$result[$k]['userName']    = $v['userName'];
					$result[$k]['create_time'] = $v['create_time'];
					$result[$k]['add_time']    = $v['add_time'];
					$result[$k]['sex']         = $sex;
					$result[$k]['gender']      = $gender;
					$result[$k]['name']        = !empty($v['name']) ? $v['name'] : '未知客户';
					$result[$k]['tagName']     = !empty($v['tagName']) ? implode(',', $v['tagName']) : '--';
					$result[$k]['perName']     = !empty($v['perName']) ? implode(',', $v['perName']) : '--';
				}
				$columns  = ['userName', 'sex', 'name', 'gender', 'tagName', 'perName', 'create_time', 'add_time'];
				$headers  = [
					'userName'    => '企业成员名称',
					'sex'         => '企业成员性别',
					'name'        => '删除客户昵称',
					'gender'      => '删除客户性别',
					'tagName'     => '公有标签',
					'perName'     => '私有标签',
					'create_time' => '删除时间',
					'add_time'    => '添加时间',
				];
				$fileName = '员工删人_' . date("YmdHis", time());
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

			return ["data" => $res, "count" => $count];

		}

	}