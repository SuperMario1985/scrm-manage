<?php
	/**
	 * Create by PhpStorm
	 * User: fulu
	 * Date: 2020/02/11
	 * Time: 13:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\WorkDepartment;
	use app\models\WorkUser;
	use app\models\WorkUserStatistic;
	use yii\web\MethodNotAllowedHttpException;
	use app\modules\api\components\WorkBaseController;
	use yii\db\Expression;
	use app\util\DateUtil;
	use moonland\phpexcel\Excel;

	class WorkUserStatisticController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-user-statistic/
		 * @title           运营中心成员昨日数据概览
		 * @description     运营中心成员昨日数据概览
		 * @method   post
		 * @url  http://{host_name}/api/work-user-statistic/work-user-data
		 *
		 * @param corp_id 必选 string 企业微信ID
		 *
		 * @return          {"error":0,"data":{"one":{"status":1,"count":"0","per":"0.0%"},"two":{"status":1,"count":"0","per":"0.0%"},"three":{"status":1,"count":402,"per":"0.0%"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据one-three代表从左到右
		 * @return_param    status int 1上升0下降
		 * @return_param    count int 数量
		 * @return_param    per string 百分比
		 *
		 * @remark          Create by PhpStorm. User: fulu.
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserData ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id = \Yii::$app->request->post('corp_id');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpid = $this->corp['id'];
				if (isset($this->subUser->sub_id)) {
					$user_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($user_ids === false) {
						return [
							"one"   => ["status" => 0, "count" => 0, "per" => "0%"],
							"three" => ["status" => 0, "count" => 0, "per" => "0%"],
							"two"   => ["status" => 0, "count" => 0, "per" => "0%"],
						];
					}
					if (is_array($user_ids)) {
						$userid = AuthoritySubUserDetail::getUserUserId($user_ids);

						return WorkUserStatistic::getWorkUserStatisticData($corpid, $userid);
					}

					return WorkUserStatistic::getWorkUserStatisticData($corpid);
				} else {
					return WorkUserStatistic::getWorkUserStatisticData($corpid);
				}
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-statistic/
		 * @title           成员TOP10
		 * @description     成员TOP10数据
		 * @method   post
		 * @url  http://{host_name}/api/work-user-statistic/work-user-top
		 *
		 * @param corp_id 必选 string 企业微信ID
		 * @param data_Type 必选 int 数据类型：1发起申请数；2新增客户数；3删除/拉黑成员的客户数
		 * @param s_date 必选 string 开始日期
		 * @param e_date 必选 string 结束日期
		 * @param s_week 选填 int 按周时传
		 * @param type 必选 int 1按小时2按天3按周4按月
		 * @param is_export 选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":{"user_data":[{"sort":"1","name":"flu","cnt_num":"1"},{"sort":"2","name":"fluu","cnt_num":"0"}],"url":"","xData":["flu","fluu"],"seriesData":["1","0"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_data array 底下的详细数据
		 * @return_param    xData array X轴数据
		 * @return_param    seriesData array Y轴数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/02/11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserTop ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id = \Yii::$app->request->post('corp_id');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corp_id   = $this->corp['id'];
				$data_Type = \Yii::$app->request->post('data_Type');
				$date1     = \Yii::$app->request->post('s_date');
				$date2     = \Yii::$app->request->post('e_date');
				$s_week    = \Yii::$app->request->post('s_week');
				$type      = \Yii::$app->request->post('type') ?: 2; //天
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 3 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}

				switch ($data_Type) {
					case 2:
						$typeName = '新增客户数';
						break;
					case 3:
						$typeName = '删除或拉黑成员的客户数';
						break;
					default:
					case 1:
						$typeName = '发起申请数';
						break;
				}
				//子账户范围限定
				$user_ids = [];
				if (isset($this->subUser->sub_id)) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail === false) {
						return [
							"seriesData" => [],
							"url"        => '',
							"user_data"  => [],
							"xData"      => [],
						];
					}
					if (is_array($sub_detail)) {
						$user_ids = $sub_detail;
					}
				}
				//根据类型获取数据
				$result = WorkUserStatistic::getUserTopByType($data_Type, $corp_id, $date1, $date2, $user_ids);
				$url    = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['sort', 'name', 'cnt_num'];
					$headers  = [
						'sort'    => '排行榜',
						'name'    => '员工姓名',
						'cnt_num' => $typeName
					];
					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['data'],//数库
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
					'user_data'  => $result['data'],
					'url'        => $url,
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user-statistic/
		 * @title           成员数据分类统计
		 * @description     成员数据分类统计
		 * @method   post
		 * @url  http://{host_name}/api/work-user-statistic/work-user-increase
		 *
		 * @param corp_id   必选 string 企业微信ID
		 * @param data_Type 必选 int 数据类型：1发起申请数；2新增客户数；3删除/拉黑成员的客户数
		 * @param s_date    必选 string 开始日期
		 * @param e_date    必选 string 结束日期
		 * @param s_week    选填 int 按周时传
		 * @param type      必选 int 1按小时2按天3按周4按月
		 * @param user_ids  可选 array 成员id
		 * @param is_export 选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":{"user_data":[{"cnt_num":"0","time":"2019-11-27"},{"cnt_num":"0","time":"2019-11-28"}],"url":"","legData":["发起申请数"],"xData":["2019-11-27","2019-11-28"],"seriesData":[{"name":"数据统计","type":"line","smooth":true,"data":[0,0]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_data array 底下的详细数据
		 * @return_param    xData array X轴数据
		 * @return_param    legData array 对应数据
		 * @return_param    seriesData array 总的数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/02/11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkUserIncrease ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corp_id    = $this->corp['id'];
				$data_Type  = \Yii::$app->request->post('data_Type');
				$user_ids   = \Yii::$app->request->post('user_ids',[]);
				$date1      = \Yii::$app->request->post('s_date');
				$date2      = \Yii::$app->request->post('e_date');
				$s_week     = \Yii::$app->request->post('s_week');
				$type       = \Yii::$app->request->post('type') ?: 2; //天
				$is_export  = \Yii::$app->request->post('is_export');
				$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}

				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 3 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}

				switch ($data_Type) {
					case 2:
						$typeName = '新增客户数';
						break;
					case 3:
						$typeName = '删除或拉黑成员的客户数';
						break;
					default:
					case 1:
						$typeName = '发起申请数';
						break;
				}

				$sub_detail = true;
				//子账户范围限定
				if (isset($this->subUser->sub_id) && empty($user_ids)) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail === false) {
						return json_decode('{"error":0,"data":{"user_data":[{"cnt_num":"0","time":"----"}],"url":"","legData":["发起申请数"],"xData":["---"],"seriesData":[{"name":"统计数值","type":"line","smooth":true,"data":[0]}]}}');
					}
					if (is_array($sub_detail)) {
						$user_ids = $sub_detail;
					}
				}
				$result = WorkUserStatistic::getUserIncreaseByType($type, $data_Type, $corp_id, $user_ids, $date1, $date2, $s_week);
				$url    = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['time', 'cnt_num'];
					$headers  = [
						'time'    => '时间',
						'cnt_num' => $typeName
					];
					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['data'],//数库
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

				$legData = [$typeName];
				$info    = [
					'user_data'  => $result['data'],
					'url'        => $url,
					'legData'    => $legData,
					'xData'      => $result['xData'],
					'seriesData' => $result['seriesData'],
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * 获取成员top数据
		 */
		private function getUserTopByType ($type, $data_type, $corp_id, $date1, $date2)
		{
			$xData   = [];//X轴
			$newData = [];//Y轴数据
			$allData = [];//详细数据

			$userInfo = WorkUser::find()->select('userid, name')->where(['corp_id' => $corp_id]);
			$sub_detail = true;
			//子账户范围限定
			if (isset($this->subUser->sub_id)) {
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if ($sub_detail === false) {
					return [];
				}
				if (is_array($sub_detail)) {
					$userInfo = $userInfo->andWhere(["in", "id", $sub_detail]);
				}
			}
			$userInfo     = $userInfo->asArray()->all();
			$userNameInfo = [];
			foreach ($userInfo as $k => $v) {
				$userNameInfo[$v['userid']] = $v['name'];
			}
			$userData = WorkUserStatistic::getWorkUserTopNum($corp_id, $data_type, $date1, $date2, [], $sub_detail);
			//top10数据
			$userData10 = array_slice($userData, 0, 10);
			foreach ($userData10 as $k => $v) {
				$userName = isset($userNameInfo[$v['userid']]) ? $userNameInfo[$v['userid']] : $v['userid'];
				array_push($xData, $userName);
				array_push($newData, $v['cnt_num']);
			}
			//列表数据
			$sort = 1;
			foreach ($userData as $k => $v) {
				if ($v['cnt_num'] > 0) {
					$allD            = [];
					$allD['sort']    = $sort;
					$allD['name']    = isset($userNameInfo[$v['userid']]) ? $userNameInfo[$v['userid']] : $v['userid'];
					$allD['cnt_num'] = $v['cnt_num'];
					$allData[]       = $allD;
					$sort++;
				}
			}

			$info               = [];
			$info['xData']      = $xData;
			$info['seriesData'] = $newData;
			$info['data']       = $allData;

			return $info;
		}

		/**
		 * 获取成员时间段数据
		 *
		 */
		private function getUserIncreaseByType ($type, $data_type, $corp_id, $user_ids, $date1, $date2, $s_week, $sub_detail)
		{

			$xData   = [];//X轴
			$newData = [];//统计数据
			$newNum  = 0; //统计数值
			switch ($type) {
				case 2:
					//按天
					$data   = DateUtil::getDateFromRange($date1, $date2);
					$result = [];
					foreach ($data as $k => $v) {
						$userData              = WorkUserStatistic::getWorkUserDataNum($corp_id, $user_ids, $data_type, $v, "", $sub_detail);
						$result[$k]['cnt_num'] = $userData['cnt_num'];
						$result[$k]['time']    = $v;
						$newNum                += $userData['cnt_num'];
						array_push($newData, intval($userData['cnt_num']));
					}
					$xData = $data;
					break;
				case 3:
					//按周
					$data    = DateUtil::getDateFromRange($date1, $date2);
					$data    = DateUtil::getWeekFromRange($data);
					$s_date1 = $data['s_date'];
					$e_date1 = $data['e_date'];
					$result  = [];
					foreach ($s_date1 as $k => $v) {
						foreach ($e_date1 as $kk => $vv) {
							if ($k == $kk) {
								if ($s_week == 53) {
									$s_week = 1;
								}
								$userData              = WorkUserStatistic::getWorkUserDataNum($corp_id, $user_ids, $data_type, $v, $vv, $sub_detail);
								$result[$k]['cnt_num'] = $userData['cnt_num'];
								$result[$k]['time']    = $v . '~' . $vv . '(' . $s_week . '周)';
								$newNum                += $userData['cnt_num'];
								array_push($newData, intval($userData['cnt_num']));
								array_push($xData, $result[$k]['time']);
								$s_week++;
							}
						}
					}
					break;
				case 4:
					//按月
					$date   = DateUtil::getLastMonth();
					$result = [];
					foreach ($date as $k => $v) {
						$userData              = WorkUserStatistic::getWorkUserDataNum($corp_id, $user_ids, $data_type, $v['firstday'], $v['lastday'], $sub_detail);
						$result[$k]['cnt_num'] = $userData['cnt_num'];
						$result[$k]['time']    = $v['time'];
						$newNum                += $userData['cnt_num'];
						array_push($newData, intval($userData['cnt_num']));
						array_push($xData, $result[$k]['time']);
					}

					break;
			}
			$info['newNum']     = $newNum;
			$info['data']       = $result;
			$info['xData']      = $xData;
			$seriesData         = [
				[
					'name'   => '统计数值',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $newData,
				],
			];
			$info['seriesData'] = $seriesData;

			return $info;
		}

		public function actionTest ()
		{
			$result = WorkUserStatistic::getWorkUserStatisticData(2);

			return $result;
		}
	}