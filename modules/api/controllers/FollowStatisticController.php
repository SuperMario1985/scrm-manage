<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/06/26
	 * Time: 09:13
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\Follow;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContactFollowRecord;
	use app\models\WorkExternalContactFollowStatistic;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkNotFollowDay;
	use app\models\WorkUser;
	use app\models\WorkUserStatistic;
	use yii\debug\panels\EventPanel;
	use yii\web\MethodNotAllowedHttpException;
	use app\modules\api\components\WorkBaseController;
	use yii\db\Expression;
	use app\util\DateUtil;
	use moonland\phpexcel\Excel;

	class FollowStatisticController extends WorkBaseController
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
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           跟进简报
		 * @description     跟进简报
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/report
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 *
		 * @return          {"error":0,"data":{"userCount":"21","recordCount":"236","followState":[{"title":"未跟进","num":"48","next_num":"12"},{"title":"已拒绝已拒绝","num":"8","next_num":"7"},{"title":"已成交","num":"5","next_num":"3"},{"title":"测试测试测试状态","num":"3","next_num":"0"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    userCount string 客户跟进人数
		 * @return_param    recordCount string 客户跟进记录
		 * @return_param    todayUserCount string 今日客户跟进人数
		 * @return_param    todayRecordCount string 今日客户跟进记录
		 * @return_param    followState array 跟进状态
		 * @return_param    followState.num string 当前状态客户数
		 * @return_param    followState.next_num string 下一阶段客户数
		 * @return_param    followState.title string 状态名称
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/26 15:55
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionReport ()
		{
			if (\Yii::$app->request->isPost) {
				$uid      = \Yii::$app->request->post('uid');
				$user_ids = \Yii::$app->request->post('user_ids');
				$s_date   = \Yii::$app->request->post('s_date');
				$e_date   = \Yii::$app->request->post('e_date');
				if (empty($this->corp) || empty($uid)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpId = $this->corp->id;

				if (!empty($user_ids)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0, 0,isset($this->subUser->sub_id)?$this->subUser->sub_id:0);
					if(empty($user_ids)){
						return [
							"followState"      => [
								["next_num" => 0, "title" => "跟进中", "num" => 0],
								["next_num" => 0, "title" => "已成交", "num" => 0],
							],
							"recordCount"      => 0,
							"todayRecordCount" => 0,
							"todayUserCount"   => 0,
							"userCount"        => 0,
						];
					}
				}
				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$subDetail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);

					if (is_array($subDetail)) {
						$user_ids = $subDetail;
					}
					if ($subDetail === false) {
						return [
							"followState"      => [
								["next_num" => 0, "title" => "跟进中", "num" => 0],
								["next_num" => 0, "title" => "已成交", "num" => 0],
							],
							"recordCount"      => 0,
							"todayRecordCount" => 0,
							"todayUserCount"   => 0,
							"userCount"        => 0,
						];
					}
				}
				$data        = WorkExternalContactFollowRecord::getReport($corpId, $user_ids, $s_date, $e_date, 0);
				$data1       = WorkExternalContactFollowRecord::getReport($corpId, $user_ids, $s_date, $e_date, 1);
				$followState = [];
				$follow      = Follow::find()->where(['uid' => $uid, 'status' => 1])->orderBy(["sort"=>SORT_ASC])->asArray()->all();
				$followId    = array_column($follow, 'id');
				$lastFollow  = Follow::find()->where(['uid' => $uid, 'status' => 1])->orderBy(['id' => SORT_DESC])->one();
				$followNew   = Follow::findOne(['uid' => $uid, 'status' => 1]);
				if (!empty($follow)) {
					foreach ($follow as $key => $value) {
						foreach ($followId as $k => $id) {
							if ($id <= $value['id']) {
								unset($followId[$k]);
							}
						}
						$user = WorkExternalContactFollowRecord::getDateUser($followNew->id, $uid, $corpId, $value['id'], $user_ids, $s_date, $e_date, 1);
						if ($lastFollow->id == $value['id']) {
							$user['nextNum'] = '--';
						}
						$followState[$key]['title']    = $value['title'];
						$followState[$key]['num']      = $user['num'];
						$followState[$key]['next_num'] = $user['nextNum'];
					}
				}
				$res = [
					'userCount'        => $data['userCount'],
					'recordCount'      => $data['recordCount'],
					'todayUserCount'   => $data1['userCount'],
					'todayRecordCount' => $data1['recordCount'],
					'followState'      => $followState,
					'user_ids'         => [],
					'type'             => 1,
				];
				$res['user_ids'] = ["user"=>[],"department"=>[]];
				if (!empty($user_ids) && isset($Temp) && !isset($this->subUser->sub_id)) {
					$departmentName                = WorkDepartment::find()->where(["in", "department_id", $Temp["department"]])->andWhere(["corp_id"=>$this->corp->id])->select("name")->asArray()->all();
					$departmentName                = array_column($departmentName, "name");
					$res['user_ids']["department"] = $departmentName;
					$userName                      = WorkUser::find()->where(["in", "id", $Temp["user"]])->select("name")->asArray()->all();
					$userName                      = array_column($userName, "name");
					$res['user_ids']["user"]       = $userName;
					$res['type'] = 2;
				}

				if (isset($this->subUser->sub_id)) {
					$sub = AuthoritySubUserDetail::findOne(["corp_id" => $this->corp->id, "sub_id" => $this->subUser->sub_id]);
					if (!empty($sub) && $sub->type_all != 1) {
						if (!empty($sub->user_key)) {
							$userKey = json_decode($sub->user_key, true);
							$subUser = WorkUser::findOne(["mobile" => $this->subUser->account, "corp_id" => $this->corp->id]);
							if (!empty($subUser)) {
								array_push($userKey, $subUser->id);
							}
							$userKey                 = array_unique($userKey);
							$userName                = WorkUser::find()->where(["in", "id", $userKey])->select("name")->asArray()->all();
							$userName                = array_column($userName, "name");
							$res['user_ids']["user"] = $userName;
						}
						if (!empty($sub->department)) {
							$department                    = json_decode($sub->department, true);
							$departmentName                = WorkDepartment::find()->where(["in", "department_id", $department])->andWhere(["corp_id"=>$this->corp->id])->select("name")->asArray()->all();
							$departmentName                = array_column($departmentName, "name");
							$res['user_ids']["department"] = $departmentName;
						}
						if(!empty($user_ids)){
							$res['type']     = 2;
						}else{
							$res['type']     = 3;
						}
					}
				}

				return $res;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           成员top排序
		 * @description     成员top排序
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/top
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 * @param page  可选 int 当前页
		 * @param pageSize  可选 int 页数
		 * @param is_export  可选 int 1导出
		 * @param follow_id  可选 int 跟进状态id默认0
		 * @param type  必选 int 1、未跟进客户数2、跟进客户数3、跟进次数
		 * @param status_id  必选 int 未跟进天数传id给我
		 *
		 * @return          {"error":0,"data":{"count":9,"allData":[{"sort":1,"name":"李云莉","status":"全部阶段","days":"5天","count":"5"},{"sort":2,"name":"陈志尧","status":"全部阶段","days":"5天","count":"4"},{"sort":3,"name":"汪博文","status":"全部阶段","days":"5天","count":"2"},{"sort":4,"name":"张婷","status":"全部阶段","days":"5天","count":"2"},{"sort":5,"name":"邢长宇","status":"全部阶段","days":"5天","count":"2"},{"sort":6,"name":"林凤","status":"全部阶段","days":"5天","count":"2"},{"sort":7,"name":"李蓉蓉","status":"全部阶段","days":"5天","count":"2"},{"sort":8,"name":"王美丁","status":"全部阶段","days":"5天","count":"1"},{"sort":9,"name":"钱玉洁","status":"全部阶段","days":"5天","count":"1"}],"seriesData":["5","4","2","2","2","2","2","1","1"],"xData":["李云莉","陈志尧","汪博文","张婷","邢长宇","林凤","李蓉蓉","王美丁","钱玉洁"],"days":[{"name":"全部","id":"-1"},{"name":"1天未跟进","id":"-2"},{"name":"3天未跟进","id":"-3"},{"id":"1","name":"5天未跟进"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    userName string 成员名称
		 * @return_param    days array 未跟进天数的配置
		 * @return_param    allData array 详细数据
		 * @return_param    allData.sort string 排序
		 * @return_param    allData.name string 员工名称
		 * @return_param    allData.status string 跟进阶段
		 * @return_param    allData.days string 未跟进天数
		 * @return_param    allData.count string 未跟进客户数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/28 16:22
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionTop ()
		{
			if (\Yii::$app->request->isPost) {
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('pageSize') ?: 10;
				$s_date    = \Yii::$app->request->post('s_date');
				$e_date    = \Yii::$app->request->post('e_date');
				$is_export = \Yii::$app->request->post('is_export');
				$follow_id = \Yii::$app->request->post('follow_id') ?: 0;
				$type      = \Yii::$app->request->post('type') ?: 1;
				$uid       = \Yii::$app->request->post('uid') ?: 0;
				$status_id = \Yii::$app->request->post('status_id') ?: WorkExternalContactFollowRecord::ALL_DAY;
				$user_ids  = \Yii::$app->request->post('user_ids');
				$user_id   = \Yii::$app->request->post('user_id');
				if (empty($this->corp) || empty($uid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('日期不能为空！');
				}
				if (!empty($user_ids)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0, 0,isset($this->subUser->sub_id)?$this->subUser->sub_id:0);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if (is_array($user)) {
						$user_ids = $user;
					}
					if ($user === false) {
						return [
							"allData"    => [],
							"seriesData" => [],
							"xData"      => [],
							"count"      => 0,
						];
					}
				}
				$show = 0;
				if (!empty($user_id)) {
					$detail   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
					$user_ids = $detail['user_ids'];
					$show     = $detail['show'];
				}
				$corpId = $this->corp->id;
				$result = WorkExternalContactFollowRecord::getData($user_ids, $uid, $type, $corpId, $page, $pageSize, $s_date, $e_date, $follow_id, $is_export, $status_id);
				if ($is_export == 1) {
					if (empty($result['allData'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					if ($type == 2 || $type == 3) {
						if ($type == 2) {
							$name = '跟进客户数';
						} else {
							$name = '跟进次数';
						}
						$columns  = ['sort', 'name', 'status', 'count'];
						$headers  = [
							'sort'   => '排行',
							'name'   => '员工名称',
							'status' => '跟进阶段',
							'count'  => $name
						];
						$typeName = $name;
					} else {
						$typeName = '未跟进客户数';
						$columns  = ['sort', 'name', 'status', 'days', 'count'];
						$headers  = [
							'sort'   => '排行',
							'name'   => '员工名称',
							'status' => '跟进阶段',
							'days'   => '未跟进天数',
							'count'  => '未跟进客户数'
						];
					}
					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['allData'],//数库
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
				$userName = '所有成员';
				if (!empty($user_ids)) {
					$count = count($user_ids);
					if ($count > 3) {
						$user_ids = array_splice($user_ids, 0, 3);
					}
					$workUser = WorkUser::find()->where(['id' => $user_ids])->select('name')->asArray()->all();
					$name     = array_column($workUser, 'name');
					$userName = implode(',', $name);
					if ($count > 3) {
						$userName .= '等' . $count . '人';
					}
				}

				return [
					'show'       => $show,
					'count'      => $result['count'],
					'allData'    => $result['allData'],
					'seriesData' => $result['seriesData'],
					'xData'      => $result['xData'],
					'userName'   => $userName,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           获取未跟进天数
		 * @description     获取未跟进天数
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/days
		 *
		 * @param uid  必选 int 用户ID
		 *
		 * @return          {"error":0,"data":[{"name":"1天未跟进","id":-3,"day":1,"num":"1_day"},{"name":"3天未跟进","id":-2,"day":3,"num":"3_day"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/30 16:13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionDays ()
		{
			$uid = \Yii::$app->request->post('uid') ?: 0;
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$days = WorkExternalContactFollowRecord::getDays($uid);

			return $days;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           跟进分析之未跟进相关数据
		 * @description     跟进分析之未跟进相关数据
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/analysis-two
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 * @param page  可选 int 当前页
		 * @param pageSize  可选 int 页数
		 * @param is_export  可选 int 1导出
		 * @param follow_id  可选 int 跟进状态id默认0
		 * @param type  必选 int 1按员工2按日期
		 *
		 * @return          {"error":0,"data":{"userData":[{"name":"陈志尧","1_day":"26","3_day":"26","100_day":"20"},{"name":"汪博文","1_day":"6","3_day":"6","100_day":"1"},{"name":"张婷","1_day":"14","3_day":"9","100_day":"1"},{"name":"陈允","1_day":"2","3_day":"2","100_day":"1"},{"name":"邢长宇","1_day":"38","3_day":"38","100_day":"19"},{"name":"林凤","1_day":"9","3_day":"8","100_day":"2"},{"name":"李云莉","1_day":"14","3_day":"13"},{"name":"何思彬","1_day":"11","3_day":"11"},{"name":"付路","1_day":"2","3_day":"2"},{"name":"王美丁","1_day":"6","3_day":"5"}],"dateData":[],"columns":[{"title":"员工姓名","dataIndex":"name","key":"name"},{"title":"1天未跟进","dataIndex":"1_day","key":"1_day"},{"title":"3天未跟进","dataIndex":"3_day","key":"3_day"},{"title":"100天未跟进","dataIndex":"100_day","key":"100_day"}],"count":"16"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    userData array 按员工
		 * @return_param    dateData array 按日期
		 * @return_param    days array 未跟进天数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/29 15:36
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAnalysisTwo ()
		{
			if (\Yii::$app->request->isPost) {
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('pageSize') ?: 10;
				$s_date    = \Yii::$app->request->post('s_date');
				$e_date    = \Yii::$app->request->post('e_date');
				$is_export = \Yii::$app->request->post('is_export') ?: 0;
				$follow_id = \Yii::$app->request->post('follow_id') ?: 0;
				$uid       = \Yii::$app->request->post('uid');
				$type      = \Yii::$app->request->post('type') ?: 1;
				$user_ids  = \Yii::$app->request->post('user_ids');
				$user_id   = \Yii::$app->request->post('user_id');
				if (empty($this->corp) || empty($uid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('日期不能为空！');
				}
				$userIds   = $user_ids;

				$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if (is_array($user)) {
						$user_ids = $user;
					}
					if ($user === false) {
						return [
							"columns"  => [],
							"dateData" => [],
							"userData" => [],
							"count"    => 0,
						];
					}
				}
				$corpId    = $this->corp->id;
				$show      = 0;
				$userCount = 0;
				if (!empty($user_id)) {
					$detail    = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
					$user_ids  = $detail['user_ids'];
					$show      = $detail['show'];
					$userCount = $detail['userCount'];
				}
				if (empty($userIds)) {
					$userCount = 0;
				}
				$days = WorkExternalContactFollowRecord::getDays($uid);

				$followStatus = WorkExternalContactFollowRecord::getFollowStatus($type, $corpId, $follow_id, $s_date, $e_date, $user_ids, $days, $page, $pageSize, $is_export);
				if ($is_export == 1) {
					if ($type == 1) {
						$data            = $followStatus['userData']['userData'];
						$typeName        = $s_date . '-' . $e_date . '范围内员工未跟进情况';
						$headers['name'] = '员工姓名';
					} else {
						$data            = $followStatus['dateData']['dateData'];
						$typeName        = $s_date . '-' . $e_date . '范围内每天员工未跟进情况';
						$headers['name'] = '时间';
					}
					if (empty($data)) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns = [];
					foreach ($days as $key => $day) {
						$headers[$day['num']] = $day['name'];
						array_push($columns, $day['num']);
					}
					array_unshift($columns, 'name');
					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $data,//数库
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
				if ($type == 1) {
					$title = '员工姓名';
					$count = $followStatus['userData']['count'];
				} else {
					$title = '时间';
					$count = $followStatus['dateData']['count'];
				}
				$column = [];
				foreach ($days as $k => $v) {
					$column[$k]['title']     = $v['name'];
					$column[$k]['dataIndex'] = $v['num'];
					$column[$k]['key']       = $v['num'];
				}
				$arr                 = [];
				$arr[0]['title']     = $title;
				$arr[0]['dataIndex'] = 'name';
				$arr[0]['key']       = 'name';
				array_walk($arr, function ($item) use (&$column) {
					array_unshift($column, $item);
				});

				return [
					'userData'   => isset($followStatus['userData']['userData']) ? $followStatus['userData']['userData'] : [],
					'dateData'   => isset($followStatus['dateData']['dateData']) ? $followStatus['dateData']['dateData'] : [],
					'columns'    => $column,
					'count'      => intval($count),
					'show'       => $show,
					'user_count' => $userCount,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           跟进分析之饼状图相关数据
		 * @description     跟进分析之饼状图相关数据
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/analysis-one
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 * @param status_id  必选 int 未跟进天数传id给我
		 *
		 * @return          {"error":0,"data":{"legData":["未跟进","已拒绝已拒绝","已成交","测试测试测试状态","666"],"pieData":[{"name":"未跟进","count":"49"},{"name":"已拒绝已拒绝","count":"3"},{"name":"已成交","count":"1"},{"name":"测试测试测试状态","count":"2"},{"name":"666","count":0}],"seriesData":[{"name":"未跟进","type":"line","smooth":true,"data":["49","49","49"]},{"name":"已拒绝已拒绝","type":"line","smooth":true,"data":["3","3","3"]},{"name":"已成交","type":"line","smooth":true,"data":["1","1","1"]},{"name":"测试测试测试状态","type":"line","smooth":true,"data":["2","2","2"]},{"name":"666","type":"line","smooth":true,"data":["0","0","0"]}],"xData":["2020-06-25","2020-06-26","2020-06-27"],"days":[{"name":"1天未跟进","id":-3,"day":1,"num":"1_day"},{"name":"3天未跟进","id":-2,"day":3,"num":"3_day"},{"id":"1","day":"5","name":"5天未跟进","num":"5_day"}],"userName":"所有成员"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    days array 未跟进数据头部
		 * @return_param    userName array 所选成员数据
		 * @return_param    pieData array 饼状图
		 * @return_param    xData array X轴
		 * @return_param    legData array legData
		 * @return_param    seriesData array seriesData
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/29 15:48
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionAnalysisOne ()
		{
			$s_date    = \Yii::$app->request->post('s_date');
			$e_date    = \Yii::$app->request->post('e_date');
			$uid       = \Yii::$app->request->post('uid');
			$status_id = \Yii::$app->request->post('status_id') ?: WorkExternalContactFollowRecord::ONE_DAY;
			$user_ids  = \Yii::$app->request->post('user_ids');
			$user_id   = \Yii::$app->request->post('user_id');
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($s_date) || empty($e_date)) {
				throw new InvalidParameterException('日期不能为空！');
			}
			$userIds   = $user_ids;
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($user_ids)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
				$user_ids = empty($user_ids) ? [0] : $user_ids;
			}
			if (empty($user_ids) && isset($this->subUser->sub_id)) {
				$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if (is_array($user)) {
					$user_ids = $user;
				}
				if ($user === false) {
					return [
						"days"       => [],
						"legData"    => [],
						"pieData"    => [],
						"seriesData" => [],
						"xData"      => [],
						"userName"   => '---',
					];
				}
			}
			$show      = 0;
			$userCount = 0;
			if (!empty($user_id)) {
				$detail    = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
				$user_ids  = $detail['user_ids'];
				$show      = $detail['show'];
				$userCount = $detail['userCount'];
			}
			if (empty($userIds)) {
				$userCount = 0;
			}
			$corpId       = $this->corp->id;
			$days         = WorkExternalContactFollowRecord::getDays($uid);
			$followStatus = WorkExternalContactFollowRecord::getPiedata($corpId, 0, $s_date, $e_date, $status_id, $user_ids, $uid);
			$userName     = '所有成员';
			if (!empty($user_ids)) {
				$count = count($user_ids);
				if ($count > 3) {
					$user_ids = array_splice($user_ids, 0, 3);
				}
				$workUser = WorkUser::find()->where(['id' => $user_ids])->select('name')->asArray()->all();
				$name     = array_column($workUser, 'name');
				$userName = implode(',', $name);
				if ($count > 3) {
					$userName .= '等' . $count . '人';
				}
			}

			return [
				'show'       => $show,
				'legData'    => $followStatus['legData'],
				'pieData'    => $followStatus['pieData'],
				'seriesData' => $followStatus['seriesData'],
				'xData'      => $followStatus['xData'],
				'days'       => $days,
				'userName'   => $userName,
				'user_count' => $userCount,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           跟进分析之已跟进情况
		 * @description     跟进分析之已跟进情况
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/analysis-three
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 * @param is_export  可选 int 1导出默认0
		 * @param page  可选 int 当前页
		 * @param pageSize  可选 int 页数
		 * @param type  必选 int 1按日期2按员工
		 *
		 * @return          {"error":0,"data":{"legData":["跟进客户数","跟进次数"],"seriesData":[{"name":"跟进客户数","type":"line","smooth":["0","1","3","0","13","0","0","0"]},{"name":"跟进次数","type":"line","smooth":["0","6","7","0","46","0","0","0"]}],"xData":["2020-06-20","2020-06-21","2020-06-22","2020-06-23","2020-06-24","2020-06-25","2020-06-26","2020-06-27"],"users":[{"name":"李云莉","userNum":"6","recordNum":"14"},{"name":"总经理","userNum":"5","recordNum":"11"},{"name":"林凤","userNum":"5","recordNum":"13"},{"name":"陈志尧","userNum":"4","recordNum":"6"},{"name":"张婷","userNum":"2","recordNum":"2"},{"name":"李蓉蓉","userNum":"2","recordNum":"6"},{"name":"汪博文","userNum":"2","recordNum":"2"},{"name":"邢长宇","userNum":"2","recordNum":"2"},{"name":"王美丁","userNum":"1","recordNum":"1"},{"name":"钱玉洁","userNum":"1","recordNum":"2"}],"allData":[{"name":"李云莉","userNum":"6","recordNum":"14"},{"name":"总经理","userNum":"5","recordNum":"11"},{"name":"林凤","userNum":"5","recordNum":"13"},{"name":"陈志尧","userNum":"4","recordNum":"6"},{"name":"张婷","userNum":"2","recordNum":"2"},{"name":"李蓉蓉","userNum":"2","recordNum":"6"},{"name":"汪博文","userNum":"2","recordNum":"2"},{"name":"邢长宇","userNum":"2","recordNum":"2"},{"name":"王美丁","userNum":"1","recordNum":"1"},{"name":"钱玉洁","userNum":"1","recordNum":"2"}],"count1":8,"count2":10}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    legData array legData
		 * @return_param    seriesData array seriesData
		 * @return_param    xData array X轴
		 * @return_param    users array 按员工表格数据
		 * @return_param    allData array 按日期表格数据
		 * @return_param    count int 表格数量
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/30 10:50
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionAnalysisThree ()
		{
			$s_date    = \Yii::$app->request->post('s_date');
			$e_date    = \Yii::$app->request->post('e_date');
			$uid       = \Yii::$app->request->post('uid');
			$user_ids  = \Yii::$app->request->post('user_ids');
			$user_id   = \Yii::$app->request->post('user_id');
			$is_export = \Yii::$app->request->post('is_export') ?: 0;
			$type      = \Yii::$app->request->post('type') ?: 1;
			$page      = \Yii::$app->request->post('page') ?: 1;
			$pageSize  = \Yii::$app->request->post('pageSize') ?: 10;
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($s_date) || empty($e_date)) {
				throw new InvalidParameterException('日期不能为空！');
			}
			$userIds   = $user_ids;
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($user_ids)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
				$user_ids = empty($user_ids) ? [0] : $user_ids;
			}
			if (empty($user_ids) && isset($this->subUser->sub_id)) {
				$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if (is_array($user)) {
					$user_ids = $user;
				}
				if ($user === false) {
					return [
						"allData"    => [],
						"legData"    => [],
						"seriesData" => [],
						"users"      => [],
						"xData"      => [],
						"count"      => 0,
					];
				}
			}
			$userCount = 0;
			$show      = 0;
			if (!empty($user_id)) {
				$detail    = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
				$user_ids  = $detail['user_ids'];
				$show      = $detail['show'];
				$userCount = $detail['userCount'];
			}
			if (empty($userIds)) {
				$userCount = 0;
			}
			$corpId = $this->corp->id;
			$result = WorkExternalContactFollowRecord::getThreeData($corpId, $s_date, $e_date, $user_ids, $page, $pageSize, $is_export, $uid, $type);
			if ($is_export == 1) {
				if ($type == 2) {
					$data     = $result['users'];
					$typeName = $s_date . '-' . $e_date . '范围内员工跟进情况';
					$headers  = [
						'name'      => '员工名称',
						'userNum'   => '跟进客户',
						'recordNum' => '跟进次数'
					];
				} else {
					$data     = $result['allData'];
					$typeName = $s_date . '-' . $e_date . '范围内每天员工跟进情况';
					$headers  = [
						'name'      => '时间',
						'userNum'   => '跟进客户',
						'recordNum' => '跟进次数'
					];
				}
				if (empty($data)) {
					throw new InvalidParameterException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				$columns  = ['name', 'userNum', 'recordNum'];
				$fileName = $typeName . '_' . date("YmdHis", time());
				Excel::export([
					'models'       => $data,//数库
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

			return [
				'legData'    => $result['legData'],
				'seriesData' => $result['seriesData'],
				'xData'      => $result['xData'],
				'users'      => $result['users'],
				'allData'    => $result['allData'],
				'count'      => $result['count'],
				'show'       => $show,
				'user_count' => $userCount,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/follow-statistic/
		 * @title           跟进漏斗
		 * @description     跟进漏斗
		 * @method   post
		 * @url  http://{host_name}/api/follow-statistic/hopper
		 *
		 * @param corp_id  必选 string 企业微信id
		 * @param uid  必选 int 用户ID
		 * @param user_ids  可选 array 成员
		 * @param s_date  必选 string 开始时间
		 * @param e_date  必选 string 结束时间
		 * @param is_export  可选 int 1导出默认0
		 * @param show  可选 int 0显示成员1不显示
		 * @param user_id  可选 string H5时传
		 *
		 * @return          {"error":0,"data":{"info":[{"name":"未跟进","num":"12","per":"71%","day":144,"everyDay":"86.26","rate":"0"},{"name":"已拒绝已拒绝","num":"3","per":"18%","day":142,"everyDay":"31.24","rate":"0"},{"name":"已成交","num":"2","per":"12%","day":137,"everyDay":"27.83","rate":"0"},{"name":"测试测试状态","num":"2","per":"12%","day":138,"everyDay":"61.80","rate":"0"},{"name":"88888","num":"0","per":"0%","day":18443,"everyDay":0,"rate":0}],"legend":["未跟进","已拒绝已拒绝","已成交","测试测试状态","88888"],"rate":[{"name":"未跟进","value":"0"},{"name":"已拒绝已拒绝","value":"0"},{"name":"已成交","value":"0"},{"name":"测试测试状态","value":"0"},{"name":"88888","value":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    info array 表格数据
		 * @return_param    legend array 漏斗数据
		 * @return_param    rate array 漏斗数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/1 14:08
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionHopper ()
		{
			$s_date    = \Yii::$app->request->post('s_date');
			$e_date    = \Yii::$app->request->post('e_date');
			$uid       = \Yii::$app->request->post('uid');
			$user_ids  = \Yii::$app->request->post('user_ids');
			$user_id   = \Yii::$app->request->post('user_id');
			$is_export = \Yii::$app->request->post('is_export') ?: 0;
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($s_date) || empty($e_date)) {
				throw new InvalidParameterException('日期不能为空！');
			}
			$userCount = 0;
			if (!empty($user_ids)) {
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0, 0,isset($this->subUser->sub_id)?$this->subUser->sub_id:0);
				if(empty($user_ids)){
					return ['info' => [], 'legend' => [], 'rate' => [],"user_count"=>0,"series"=>[]];
				}
				$userCount = count($user_ids);
			}
			if (empty($user_ids) && isset($this->subUser->sub_id)) {
				$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if (is_array($user)) {
					$user_ids = $user;
				}
				if ($user === false) {
					return ['info' => [], 'legend' => [], 'rate' => [],"user_count"=>0,"series"=>[]];
				}
			}
			$show      = 0;
			if (!empty($user_id)) {
				$detail    = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
				$user_ids  = $detail['user_ids'];
				$show      = $detail['show'];
			}
			$corpId     = $this->corp->id;
			$followNew  = Follow::findOne(['uid' => $uid, 'status' => 1]);
			$resultData = WorkExternalContactFollowRecord::getHopper($followNew->id, $uid, $corpId, $user_ids, $s_date, $e_date);
			$result     = $resultData['data'];
			$total      = $resultData['num'];
			$rate       = $legend = $series = [];
			foreach ($result as $key => $res) {
				array_push($legend, $res['name']);
				$per = '0%';
				if ($res['num'] > 0) {
					$per = $res['num'] / $total;
				}
				if ($per > 0) {
					$per = number_format($per, 4);
					$per = $per * 100;
					$per = number_format($per, 2) . '%';
				}
				$result[$key]['per']  = $per;
				$rate[$key]['num']    = $res['num'];
				$rate[$key]['name']   = $res['name'] . '（' . $res['num'] . '）';
				$rate[$key]['value']  = rtrim($per, '%');
				$series[$key]['name'] = $res['name'] . '（' . $res['num'] . '）';
				$series[$key]['data'] = rtrim($per, '%');
			}
			if ($is_export == 1) {
				$data     = $result;
				$typeName = '跟进转化漏斗';
				$headers  = [
					'name'     => '跟进阶段',
					'num'      => '客户数',
					'per'      => '所占比例',
					'rate'     => '阶段转化率',
					'day'      => '最长停留天数',
					'everyDay' => '平均停留天数',
				];
				if (empty($data)) {
					throw new InvalidParameterException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				$columns  = ['name', 'num', 'per', 'rate', 'day', 'everyDay'];
				$fileName = $typeName . '_' . date("YmdHis", time());
				Excel::export([
					'models'       => $data,//数库
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
				'info'       => $result,
				'legend'     => $legend,
				'rate'       => $rate,
				'series'     => $series,
				'show'       => $show,
				'user_count' => $userCount,
			];

			return $info;

		}

	}