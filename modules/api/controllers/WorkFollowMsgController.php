<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\Follow;
	use app\models\WorkFollowMsg;
	use app\models\WorkNotFollowDay;
	use app\models\WorkUser;
	use app\models\WorkCorpAgent;
	use app\models\WorkDepartment;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidDataException;

	class WorkFollowMsgController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           跟进提醒列表
		 * @description     跟进提醒列表
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/follow-user-list
		 *
		 * @param corp_id    必选 string 企业的唯一ID
		 * @param agentid    必选 string 应用ID
		 * @param name       可选 string 员工姓名
		 * @param status     可选 string 状态：-1全部、0已关闭、1已开启
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int  数据条数
		 * @return_param    keys array 所有成员id
		 * @return_param    list array 数据列表
		 * @return_param    list.user_id int 员工id
		 * @return_param    list.name string 员工姓名
		 * @return_param    list.avatar string 员工头像
		 * @return_param    list.sex int 性别
		 * @return_param    list.department_name string 所属部门
		 * @return_param    list.follow_name string 查看员工
		 * @return_param    list.send_time array 推送时间
		 * @return_param    list.send_content int 推送内容
		 * @return_param    list.status int 是否开启1是0否
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowUserList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$agentid  = \Yii::$app->request->post('agentid');
			$name     = \Yii::$app->request->post('name', '');
			$status   = \Yii::$app->request->post('status', '-1');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);

			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			//获取应用可见成员
			$agentUser = [];
			$agentInfo = WorkCorpAgent::findOne($agentid);
			if (!empty($agentInfo->allow_party) || !empty($agentInfo->allow_user)) {
				$department_ids = !empty($agentInfo->allow_party) ? explode(',', $agentInfo->allow_party) : [];
				$user_arr       = !empty($agentInfo->allow_user) ? explode(',', $agentInfo->allow_user) : [];
				$agentUser      = WorkDepartment::getDepartmentUser($this->corp['id'], $department_ids, $user_arr);
			}
			//员工姓名查询
			if (!empty($name)) {
				$nameUser   = WorkUser::find()->andWhere(['corp_id' => $this->corp['id']])->andWhere(['like', 'name', $name])->all();
				$nameUserId = [];
				foreach ($nameUser as $user) {
					array_push($nameUserId, $user->id);
				}
				$agentUser = array_intersect($agentUser, $nameUserId);
			}
			//现在只展示已设置过提醒规则的员工 20200713
			$setFollowUser = WorkFollowMsg::find()->alias("a")
				->leftJoin("{{%work_user}} as b","a.user_id = b.id")
				->andWhere(['a.corp_id' => $this->corp['id'], 'a.agentid' => $agentid]);
			if(isset($this->subUser->sub_id)){
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if(is_array($sub_detail)){
					foreach ($agentUser as $key=>$item){
						if(!in_array($item,$sub_detail)){
							unset($agentUser[$key]);
						}
					}
				}
				if($sub_detail === true){
					$setFollowUser = $setFollowUser->andWhere(['in', 'a.user_id', $agentUser]);
				}
				if($sub_detail === false){
					return ['count' => 0,'keys'  => [],'list'  => []];
				}
			}
			$setFollowUser = $setFollowUser->andWhere(['in', 'a.user_id', $agentUser]);
			if ($status != '-1') {
				$setFollowUser = $setFollowUser->andWhere(['a.status' => $status]);
			}
			$offset             = ($page - 1) * $pageSize;
			$setFollowUserClone = $setFollowUser;
			$count              = $setFollowUser->count();
			$key                = $setFollowUserClone->select("a.user_id")->asArray()->all();
			$keyUser            = array_column($key, "user_id");
			$setFollowUser      = $setFollowUser->select('a.*,b.name,b.avatar,b.gender,b.department,b.corp_id')
				->offset($offset)
				->limit($pageSize)
				->orderBy(['create_time' => SORT_DESC])->asArray()->all();
			$list          = [];
			$followUserId  = $setFollowUser;
			if (!empty($followUserId)) {
				//跟进状态
				$followData = $followDataTemp = [];
				$follow     = Follow::find()->where(['uid' => $this->user->uid, 'status' => 1])->select('id,title')->all();
				foreach ($follow as $f) {
					$followData[$f['id']] = $f['title'];
					$followDataTemp[$f['id']] = $f;
				}
				//自定义未跟进天数
				$notFollowDay = WorkNotFollowDay::find()->where(['uid' => $this->user->uid])->orderBy(['day' => SORT_ASC])->asArray()->all();
				//员工信息
				$workUser  = WorkUser::find()->andWhere(['in', 'id', $followUserId])->select('id,userid,name,avatar,gender')->asArray()->all();
				$workUserD = [];
				foreach ($workUser as $k => $v) {
					$workUserD[$v['id']] = $v;
				}

				foreach ($followUserId as $value) {
					$data                    = [];
					$data['user_id']         = $value["user_id"];
					$data['name']            = $value["name"];;
					$data['avatar']          = $value['avatar'];
					$data['sex']             = $value['gender'];
					if (!empty($value["department"])) {
						$departName = WorkDepartment::getDepartNameByUserId($value["department"], $value["corp_id"]);
					} else {
						$departName = '';
					}
					$data['department_name'] = $departName;
					$data['follow_name']  = '';
					$data['send_time']    = [];
					$data['send_content'] = '';
					$data['status']       = 0;
					$data['follow_id']    = 0;
					if ($value["is_all"] == 1) {
						$follow_name = '全员';
					} elseif (!empty($value["follow_party"]) || !empty($value["follow_user"])) {
						$follow_name = '';
						$departmentIds = !empty($value["follow_party"]) ? json_decode($value["follow_party"], true) : [];
						if (!empty($departmentIds)) {
							$department = WorkDepartment::find()->andWhere(['in', 'department_id', $departmentIds])->andWhere(['corp_id' => $this->corp['id']])->asArray()->all();
							$departmentName = array_column($department,"name");
							if(!empty($departmentName)){
								$follow_name.= implode("/",$departmentName)."/";
							}
						}
						$userIds = [];
						if (!empty($value["follow_user"])) {
							$userKeyArr   = json_decode($value["follow_user"], true);
							$ids          = array_column($userKeyArr, "id");
							$ids          = array_diff($ids, [$value["user_id"]]);
							$workUser     = WorkUser::find()->where(["and", ["corp_id" => $value["corp_id"]], ["in", "id", $ids]])->select("name,id")->asArray()->all();
							$workUserName = array_column($workUser, "name", "id");
							$userIds      = array_keys($workUser);
							if (!empty($workUserName)) {
								$follow_name .= implode("/", $workUserName)."/";
							}
						}
						if (!in_array($value["user_id"], $userIds)) {
							$follow_name .= '自己/';
						}
						$follow_name = rtrim($follow_name, '/');
					} else {
						$follow_name = '自己';
					}
					$data['follow_name'] = $follow_name;
					$data['send_time']   = !empty($value["send_time"]) ? json_decode($value["send_time"], true) : [];
					$data['status']      = $value["status"];
					$data['follow_id']   = $value["id"];

					$send_content = !empty($value["send_content"]) ? json_decode($value["send_content"], true) : [];
					$send_key     = array_column($send_content,"id");
					foreach ($send_key as $vb){
						if(!isset($followData[$vb]) && !empty($followData[$vb])){
							$sendData                = [];
							$sendData['id']          = $followData[$vb]['id'];
							$sendData['textContent'] = "{follow_id}阶段，有{notChangeNum}人停留，请尽快沟通落实。\n";
							array_push($send_content, $sendData);
						}
					}
					$send_content_str = '';
					foreach ($send_content as $content) {
						if (!empty($content['textContent'])) {
							if ($content['id'] > 0 && !isset($followData[$content['id']])) {
								//跟进状态已删除
								continue;
							}
							$str = $content['textContent'];
							if (strpos($str, '{username}') !== false) {
								$str = str_replace('{username}', $data['name'], $str);
							}
							if (strpos($str, '{sendTime}') !== false) {
								$str = str_replace('{sendTime}', $data['send_time'][0], $str);
							}
							if (strpos($str, '{followUser}') !== false) {
								$str = str_replace('{followUser}', $follow_name, $str);
							}
							if (strpos($str, '{newMemberNum}') !== false) {
								$str = str_replace('{newMemberNum}', 1, $str);
							}
							if (strpos($str, '{newMemberNum}') !== false) {
								$str = str_replace('{newMemberNum}', 1, $str);
							}
							if (strpos($str, '{follow_id}') !== false) {
								$followTitle = isset($followData[$content['id']]) ? $followData[$content['id']] : '';
								$str         = str_replace('{follow_id}', $followTitle, $str);
							}
							if (strpos($str, '{followMemberNum}') !== false) {
								$str = str_replace('{followMemberNum}', 1, $str);
							}
							if (strpos($str, '{followNum}') !== false) {
								$str = str_replace('{followNum}', 1, $str);
							}
							if (strpos($str, '{changeFollowNum}') !== false) {
								$str = str_replace('{changeFollowNum}', 1, $str);
							}
							if (strpos($str, '{notChangeNum}') !== false) {
								$str = str_replace('{notChangeNum}', 1, $str);
							}
							if (strpos($str, '{notFollowDay_1}') !== false) {
								$str = str_replace('{notFollowDay_1}', 1, $str);
							}
							if (strpos($str, '{notFollowDay_3}') !== false) {
								$str = str_replace('{notFollowDay_3}', 1, $str);
							}
							foreach ($notFollowDay as $day) {
								$dayStr = '{notFollowDay_' . $day['day'] . '}';
								if (strpos($str, $dayStr) !== false) {
									$str = str_replace($dayStr, 1, $str);
								}
							}
							$send_content_str .= $str . "\n";
						}
					}
					$data['send_content'] = $send_content_str;

					$list[] = $data;
				}
			}

			return [
				'count' => $count,
				'keys'  => $keyUser,
				'list'  => $list
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           未跟进天数
		 * @description     未跟进天数数据
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/not-follow-day-list
		 *
		 * @param uid                必选 int 用户ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    data.id int 数据id
		 * @return_param    data.day int 天数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionNotFollowDayList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$notFollowDay = WorkNotFollowDay::find()->where(['uid' => $uid, 'is_del' => 0])->select('id, day')->orderBy(['day' => SORT_ASC])->asArray()->all();
			$days         = [];
			foreach ($notFollowDay as $day) {
				array_push($days, $day['day']);
			}

			return $days;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           未跟进天数设置
		 * @description     未跟进天数设置
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/not-follow-day-post
		 *
		 * @param uid              必选 int 用户ID
		 * @param dayArr           可选 array 天数集合
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionNotFollowDayPost ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid', 0);
			$dayArr = \Yii::$app->request->post('dayArr', []);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			WorkNotFollowDay::updateAll(['is_del' => 1], ['uid' => $uid, 'is_del' => 0]);

			foreach ($dayArr as $day) {
				$notFollowDay = WorkNotFollowDay::findOne(['uid' => $uid, 'day' => $day]);

				if (empty($notFollowDay)) {
					$notFollowDay       = new WorkNotFollowDay();
					$notFollowDay->uid  = $uid;
					$notFollowDay->day  = $day;
					$notFollowDay->time = time();
				}
				$notFollowDay->is_del = 0;

				if (!$notFollowDay->validate() || !$notFollowDay->save()) {
					throw new InvalidDataException(SUtils::modelError($notFollowDay));
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           跟进提醒状态设置
		 * @description     跟进提醒状态设置
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/follow-user-set-status
		 *
		 * @param agentid     必选 string 应用ID
		 * @param follow_id   必选 array 跟进提醒ID集合
		 * @param status      必选 int 状态0关闭1启用
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowUserSetStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$agentid   = \Yii::$app->request->post('agentid');
			$follow_id = \Yii::$app->request->post('follow_id', 0);
			$status    = \Yii::$app->request->post('status', 0);

			if (empty($follow_id) || empty($agentid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (!in_array($status, [0, 1])) {
				throw new InvalidDataException('状态不正确！');
			}

			/*$follow = WorkFollowMsg::findOne($follow_id);

			if (empty($follow)) {
				throw new InvalidDataException('跟进提醒参数错误！');
			} else {
				$follow->status   = $status;
				$follow->upt_time = time();

				if (!$follow->validate() || !$follow->save()) {
					throw new InvalidDataException(SUtils::modelError($follow));
				}
			}*/

			WorkFollowMsg::updateAll(['status' => $status, 'upt_time' => time()], ['agentid' => $agentid, 'user_id' => $follow_id]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           成员跟进提醒规则
		 * @description     成员跟进提醒规则
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/follow-user-detail
		 *
		 * @param corp_id                必选 string 企业的唯一ID
		 * @param agentid                必选 string 应用ID
		 * @param user_id                必选 int 成员ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    data.follow_id int 跟进提醒id
		 * @return_param    data.is_all int 是否全员1是0否
		 * @return_param    data.follow_party string 部门
		 * @return_param    data.follow_user string 成员
		 * @return_param    data.send_time array 推送时间
		 * @return_param    data.send_content array 推送内容
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowUserDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$agentid = \Yii::$app->request->post('agentid', '');
			$user_id = \Yii::$app->request->post('user_id', 0);

			if (empty($this->corp) || empty($agentid) || empty($user_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			//跟进状态
			$followData = [];
			$sendContent = [];
			$follow     = Follow::find()->andWhere(['uid' => $this->user->uid, 'status' => 1])->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->select('id,title')->asArray()->all();
			foreach ($follow as $f) {
				$followData[$f['id']] = $f['title'];
				$sendContent[$f['id']] = [];
			}
			\Yii::error($followData,'$followData');
			$followUser = WorkFollowMsg::findOne(['corp_id' => $this->corp['id'], 'agentid' => $agentid, 'user_id' => $user_id]);

			$result = [];
			if (!empty($followUser)) {
				$result['follow_id']    = $followUser->id;
				$result['is_all']       = $followUser->is_all;
				$result['follow_party'] = [];
				$result['follow_user']  = [];
				if (empty($result['is_all'])) {
					if (!empty($followUser->follow_party)) {
						$result['follow_party'] = json_decode($followUser->follow_party, true);
					}
					if (!empty($followUser->follow_user)) {
						$userKeyArr = json_decode($followUser->follow_user, true);
//						foreach ($userKeyArr as $key => $val) {
//							$workUser = WorkUser::findOne($val['id']);
//							if (!empty($workUser)) {
//								$userKeyArr[$key]['name'] = $workUser->name;
//							}
//						}
						WorkDepartment::ActivityDataFormat($userKeyArr,$this->corp->id,[]);
						$result['follow_user'] = $userKeyArr;
					}
				}
				$result['send_time'] = !empty($followUser->send_time) ? json_decode($followUser->send_time, true) : [];
				$result['status']    = $followUser->status;
				$send_content_old    = !empty($followUser->send_content) ? json_decode($followUser->send_content, true) : [];
				$send_key            = [];
				$firstArr            = [];
				//$send_content        = [];
				foreach ($send_content_old as $k => $s) {
					if ($s['id'] > 0 && !isset($followData[$s['id']])) {
						//跟进状态已删除
						continue;
					}
					array_push($send_key, $s['id']);
					if (!empty($s['id'])) {
						$sendContent[$s['id']] = $s;
					} else {
						$firstArr = $s;
					}
					//array_push($send_content, $s);
				}
				foreach ($follow as $f) {
					if (!in_array($f['id'], $send_key)) {
						$sendData                = [];
						$sendData['id']          = $f['id'];
						$sendData['textContent'] = "{follow_id}阶段，有{notChangeNum}人停留，请尽快沟通落实。\n";
						$sendContent[$f['id']] = $sendData;
						//array_push($send_content, $sendData);
					}
				}
				$sendContent = array_values($sendContent);
				if (!empty($firstArr)) {
					array_unshift($sendContent, $firstArr);
				}
				$result['send_content'] = array_values($sendContent);
			}

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           成员跟进提醒规则提交
		 * @description     成员跟进提醒规则提交
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/follow-user-post
		 *
		 * @param corp_id         必选 string 企业的唯一ID
		 * @param agentid         必选 string 应用ID
		 * @param follow_id       可选 int 跟进提醒id
		 * @param user_ids        可选 array 成员id集合
		 * @param is_all          可选 int 是否全员1是否
		 * @param follow_party    可选 array 部门
		 * @param follow_user     可选 array 成员
		 * @param send_time       必选 array 推送时间
		 * @param send_content    必选 array 推送内容
		 * @param status          必选 int 状态
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-06-22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowUserPost ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$data                 = [];
			$data['agentid']      = \Yii::$app->request->post('agentid', 0);
			$data['follow_id']    = \Yii::$app->request->post('follow_id', 0);
			$data['user_ids']     = \Yii::$app->request->post('user_ids', []);
			$data['is_all']       = \Yii::$app->request->post('is_all', 0);
			$data['follow_party'] = \Yii::$app->request->post('follow_party', []);
			$data['follow_user']  = \Yii::$app->request->post('follow_user', []);
			$data['send_time']    = \Yii::$app->request->post('send_time', []);
			$data['send_content'] = \Yii::$app->request->post('send_content', []);
			$data['status']       = \Yii::$app->request->post('status', 0);
			$data['uid']          = $this->user->uid;
			$data['corp_id']      = $this->corp['id'];

			if (empty($data['uid']) || empty($data['agentid'])) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($data['follow_id']) && empty($data['user_ids'])) {
				throw new InvalidDataException('成员数据错误！');
			}
			if (empty($data['send_time'])) {
				throw new InvalidDataException('推送时间不能为空！');
			}
			if (empty($data['send_content'])) {
				throw new InvalidDataException('推送内容不能为空！');
			}

			WorkFollowMsg::setFollowUser($data);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-msg/
		 * @title           获取部门列表
		 * @description     获取部门列表
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-msg/get-party-list
		 *
		 * @param corp_id         必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-07-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetPartyList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$partyList = [];
			$workDepartments = WorkDepartment::find()->where(["corp_id"=>$this->corp->id,"is_del"=>0])->orderBy("parentid asc")->all();
			if (!empty($workDepartments)) {
				foreach ($workDepartments as $workDepartment) {
					array_push($partyList,$workDepartment->toArray());
				}
				$partyList = WorkDepartment::getDepartmentData($partyList);
			}

			return [
				'party_list' => $partyList,
			];
		}

	}