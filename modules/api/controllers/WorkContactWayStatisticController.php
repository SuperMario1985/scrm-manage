<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 20:22
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\WorkContactWayLine;
	use app\models\WorkContactWayStatistic;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use moonland\phpexcel\Excel;
	use yii\web\MethodNotAllowedHttpException;

	class WorkContactWayStatisticController extends WorkBaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-statistic/
		 * @title           新增渠道活码
		 * @description     新增渠道活码
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-statistic/yesterday-statistic
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":[{"title":"昨日新增客户数","desc":"新增客户数，成员新添加的客户数量。","status":0,"count":"0","per":"0.0%"},{"title":"昨日被客户删除/拉黑人数","desc":"删除/拉黑成员的客户数，即将成员删除或加入黑名单的客户数。","status":0,"count":"0","per":"0.0%"},{"title":"昨日删除人数","desc":"员工删除的客户数。","status":0,"count":"0","per":"0.0%"},{"title":"昨日净增人数","desc":"昨日新增客户数减去昨日被客户删除/拉黑人数。","status":0,"count":"0","per":"0.0%"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 标题
		 * @return_param    desc string 描述
		 * @return_param    status string 1上升2下降0持平
		 * @return_param    count string 当前数量
		 * @return_param    per string 百分比
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/9 20:05
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionYesterdayStatistic ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (isset($this->subUser->sub_id)) {
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if ($sub_detail === false) {
					return [
						['title'=>"新增客户数，成员新添加的客户数量。", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
						['title'=>"昨日被客户删除/拉黑人数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
						['title'=>"员工删除的客户数", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
						['title'=>"新增客户数减去昨日被客户删除/拉黑人数。", "dec"=> "", "status"=>0, "count"=> "0", "per"=> "0"],
					];
				}
				if (is_array($sub_detail)) {
					return WorkContactWayStatistic::getLastData($this->corp->id,$sub_detail);
				}
			}
			$result = WorkContactWayStatistic::getLastData($this->corp->id);

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-statistic/
		 * @title           活码top10
		 * @description     活码top10
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-statistic/top
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param s_date 必选 string 开始时间
		 * @param e_date 必选 string 结束时间
		 * @param s_week 可选 string 起始周
		 * @param type 必选 string 1天2周3月
		 * @param is_export 可选 string 1导出
		 * @param search_type 必选 string 1所有活码2分组
		 * @param group_id 可选 string 分组id
		 * @param data_type 必选 string 1新增客户2被客户删除3删除人数4净增
		 *
		 * @return          {"error":0,"data":{"wayData":[{"sort":1,"name":"这里活码测试","all_num":"1","group_name":"智慧店铺"}],"url":"","xData":["这里活码测试"],"seriesData":["1"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/10 16:16
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionTop ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page        = \Yii::$app->request->post('page')?:1;
				$pageSize    = \Yii::$app->request->post('pageSize')?:10;
				$s_date      = \Yii::$app->request->post('s_date');
				$e_date      = \Yii::$app->request->post('e_date');
				$s_week      = \Yii::$app->request->post('s_week');
				$type        = \Yii::$app->request->post('type', 1); //天
				$is_export   = \Yii::$app->request->post('is_export');
				$search_type = \Yii::$app->request->post('search_type', 1);//1所有活码、2分组
				$group_id    = \Yii::$app->request->post('group_id', 0);//分组id、0全部
				$data_type   = \Yii::$app->request->post('data_type', 1);//1新增客户2被客户删除3删除人数4净增
				if($type == 3){
					$month = DateUtil::getLastMonth();
					$s_date = $month[0]['firstday'];
					$e_date = $month[11]['firstday'];
				}
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}
				$corp_id = $this->corp->id;
				$show    = 1;
				$sub_detail = true;
				//子账户范围限定
				if (empty($user_id) && isset($this->subUser->sub_id)) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				}
				//所有活码和所有分组
				if (($search_type == 1) || ($search_type == 2 && !empty($group_id))) {
					if ($search_type == 1) {
						$group_id = 0;
					}
					$result = WorkContactWayStatistic::getTopData($corp_id, $s_date, $e_date, $data_type, $show, $group_id, $page, $pageSize, $is_export,$sub_detail);
				} elseif ($search_type == 2 && empty($group_id)) {
					//显示所有分组
					$show   = 2;
					$result = WorkContactWayStatistic::getTopData($corp_id, $s_date, $e_date, $data_type, $show, $group_id, $page, $pageSize, $is_export,$sub_detail);
				}
				switch ($data_type) {
					case 1:
						$typeName = '新增客户数';
						break;
					case 2:
						$typeName = '被客户删除拉黑人数';
						break;
					case 3:
						$typeName = '删除人数';
						break;
					case 4:
						$typeName = '净增人数';
						break;
				}
				$url = '';
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}

					if ($show == 1) {
						//按活码
						$columns = ['sort', 'name', 'group_name', 'all_num'];
						$headers = [
							'sort'       => '排行',
							'name'       => '活码名称',
							'group_name' => '分组名称',
							'all_num'    => $typeName
						];
					} elseif ($show == 2) {
						//按分组
						$columns = ['sort', 'group_name', 'all_num'];
						$headers = [
							'sort'    => '排行榜',
							'name'    => '群主名称',
							'all_num' => $typeName
						];
					}

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
					'count'      => $result['count'],
					'wayData'    => $result['data'],
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
		 * @catalog         数据接口/api/work-contact-way-statistic/
		 * @title           客户增长
		 * @description     客户增长
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-statistic/increase
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param s_date 必选 string 开始时间
		 * @param e_date 必选 string 结束时间
		 * @param s_week 可选 string 起始周
		 * @param type 必选 string 1天2周3月
		 * @param is_export 可选 string 1导出
		 * @param search_type 必选 string 1所有活码2分组
		 * @param group_id 可选 string 分组id
		 *
		 * @return          {"error":0,"data":{"total":{"new_contact_cnt":1,"negative_feedback_cnt":0,"delete_cnt":0,"increase_cnt":1,"per":"0.0%"},"wayData":[{"new_contact_cnt":0,"negative_feedback_cnt":0,"delete_cnt":0,"increase_cnt":0,"per":"0.0%","time":"2020-06-08"},{"new_contact_cnt":"1","negative_feedback_cnt":0,"delete_cnt":0,"increase_cnt":"1","per":"0.0%","time":"2020-06-09"}],"legData":["新增客户数","被客户删除/拉黑人数","员工删除客户人数","净增长"],"url":"","xData":["2020-06-08","2020-06-09"],"seriesData":[{"name":"新增客户数","type":"line","smooth":true,"data":[0,1]},{"name":"被客户删除/拉黑人数","type":"line","smooth":true,"data":[0,0]},{"name":"员工删除客户人数","type":"line","smooth":true,"data":[0,0]},{"name":"净增长","type":"line","smooth":true,"data":[0,1]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/10 19:45
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionIncrease ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$s_date      = \Yii::$app->request->post('s_date');
				$e_date      = \Yii::$app->request->post('e_date');
				$s_week      = \Yii::$app->request->post('s_week');
				$type        = \Yii::$app->request->post('type', 1); //天
				$is_export   = \Yii::$app->request->post('is_export');
				$search_type = \Yii::$app->request->post('search_type', 1);//1所有活码、2分组
				$group_id    = \Yii::$app->request->post('group_id', 0);//分组id、0全部
				if (empty($s_date) || empty($e_date)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 2 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}
				$corp_id = $this->corp->id;

				$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$sub_ids = true;
				if(isset($this->subUser->sub_id)){
					$sub_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id,$this->corp->id);
					if($sub_ids === false){
						return json_decode('{"error":0,"data":{"total":{"new_contact_cnt":0,"negative_feedback_cnt":0,"delete_cnt":0,"increase_cnt":0,"per":"0.0%"},"wayData":[{"new_contact_cnt":"0","negative_feedback_cnt":0,"delete_cnt":0,"increase_cnt":"0","per":"0.0%","time":"---"}],"legData":["新增客户数","被客户删除/拉黑人数","员工删除客户人数","净增长"],"url":"","xData":["---"],"seriesData":[{"name":"新增客户数","type":"line","smooth":true,"data":[0]},{"name":"被客户删除/拉黑人数","type":"line","smooth":true,"data":[0]},{"name":"员工删除客户人数","type":"line","smooth":true,"data":[0]},{"name":"净增长","type":"line","smooth":true,"data":[0]}]}}');
					}
					if(is_array($sub_ids)){
						$workExternalUserData = $workExternalUserData->andWhere(["in","user_id",$sub_ids]);
					}
				}
				$count                = $workExternalUserData->andWhere(['we.corp_id' => $this->corp['id'], 'wf.del_type' => 0])->groupBy('we.id')->count();
				if ($search_type == 1) {
					$group_id = 0;
				}
				$result  = WorkContactWayStatistic::getIncreaseData($corp_id, $s_date, $e_date, $s_week, $type, $group_id,$count,$sub_ids);
				if ($is_export == 1) {
					if (empty($result['data'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$typeName = '客户增长';
					//按活码
					$columns = ['time', 'new_contact_cnt', 'negative_feedback_cnt', 'delete_cnt', 'per', 'increase_cnt'];
					$headers = [
						'time'                  => '时间',
						'new_contact_cnt'       => '新增客户数',
						'negative_feedback_cnt' => '删除/拉黑成员的客户数',
						'delete_cnt'            => '员工删除客户数',
						'per'                   => '流失率',
						'increase_cnt'          => '净增长',
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
				$url     = '';
				$legData = ['新增客户数', '被客户删除/拉黑人数', '员工删除客户人数', '净增长'];
				$per     = '0.0%';
				if (!empty($count)) {
					$num = round(($result['delete_cnt'] + $result['negative_feedback_cnt']) / $count, 3);
					$num = sprintf("%.1f", $num * 100);
					$per = $num . '%';
				}
				$total = [
					'new_contact_cnt'       => $result['new_contact_cnt'],
					'negative_feedback_cnt' => $result['negative_feedback_cnt'],
					'delete_cnt'            => $result['delete_cnt'],
					'increase_cnt'          => $result['increase_cnt'],
					'per'                   => $per,
				];
				$info  = [
					'total'      => $total,
					'wayData'    => $result['data'],
					'legData'    => $legData,
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
		 * @catalog         数据接口/api/work-contact-way-statistic/
		 * @title           客户属性
		 * @description     客户属性
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-statistic/attribute
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param s_date 必选 string 开始时间
		 * @param e_date 必选 string 结束时间
		 * @param is_export 可选 string 1导出
		 * @param group_id 可选 string 分组id
		 *
		 * @return          {"error":0,"data":{"increaseCount":1,"per":"2.1%","seriesData1":[{"name":"男","type":"line","smooth":true,"data":[0,0,0,0,0]},{"name":"女","type":"line","smooth":true,"data":[0,"1",0,0,0]},{"name":"未知","type":"line","smooth":true,"data":[0,0,0,0,0]}],"seriesData2":[{"name":"这里活码测试","type":"line","smooth":true,"data":[0,"1",0,0,0]}],"legData1":["男","女","未知"],"legData2":["这里活码测试"],"pieData1":[{"value":0,"name":"男"},{"value":1,"name":"女"},{"value":0,"name":"未知"}],"pieData2":[{"value":"1","name":"这里活码测试"}],"xData":["2020-06-08","2020-06-09","2020-06-10","2020-06-11","2020-06-12"],"sourceDetail":[{"name":"这里活码测试","count":"1","per":"2.1%"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/12 15:11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAttribute ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$s_date                = \Yii::$app->request->post('s_date');
				$e_date                = \Yii::$app->request->post('e_date');
				$is_export             = \Yii::$app->request->post('is_export');
				$group_id              = \Yii::$app->request->post('group_id', 0);//分组id、0全部
				$corp_id               = $this->corp->id;
				$user_ids = true;
				if(isset($this->subUser->sub_id)){
					$user_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id,$this->corp->id);
					if($user_ids === false){
						return [
							"increaseCount"=>0,
							"per"=>'0%',
							"seriesData1"=>[],
							"seriesData2"=>[],
							"legData1"=>[],
							"legData2"=>[],
							"pieData1"=>[],
							"pieData2"=>[],
							"xData"=>[],
							"sourceDetail"=>[],
						];
					}
				}
				$result                = WorkContactWayStatistic::getAttributeData($corp_id, $s_date, $e_date, $group_id,$user_ids);
				$info['increaseCount'] = $result['increaseCount'];
				$info['per']           = $result['per'];
				$info['seriesData1']   = $result['seriesData1'];
				$info['seriesData2']   = $result['seriesData2'];
				$info['legData1']      = $result['legData1'];
				$info['legData2']      = $result['legData2'];
				$info['pieData1']      = $result['pieData1'];
				$info['pieData2']      = $result['pieData2'];
				$info['xData']         = $result['xData'];
				$info['sourceDetail']  = $result['sourceDetail'];
				if ($is_export == 1) {
					if (empty($result['sourceDetail'])) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$typeName = '客户属性';
					//按活码
					$columns = ['name', 'count', 'per'];
					$headers = [
						'name'  => '渠道',
						'count' => '新增客户数',
						'per'   => '客户占比',
					];

					$fileName = $typeName . '_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result['sourceDetail'],//数库
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
				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-statistic/
		 * @title           批量更新活码统计数据
		 * @description     批量更新活码统计数据
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-statistic/update-statistic
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/10 15:51
		 * @number          0
		 *
		 */
		public function actionUpdateStatistic ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			$statistic = WorkContactWayStatistic::find()->alias('w');
			$statistic = $statistic->leftJoin('{{%work_contact_way}} c', 'c.id=w.way_id');
			$statistic = $statistic->select('c.way_group_id,w.id');
			$statistic = $statistic->asArray()->all();
			$groupId   = [];
			foreach ($statistic as $sta) {
				$groupId[$sta['id']] = $sta['way_group_id'];
			}
			$wayStatistic = WorkContactWayStatistic::find()->where(['group_id' => NULL])->all();
			foreach ($wayStatistic as $st) {
				if (isset($groupId[$st->id])) {
					$st->group_id = $groupId[$st->id];
					$st->save();
				}
			}
			echo '更新完成';
		}

		public function actionUpdateLine ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			$contact = WorkExternalContact::find()->alias('a');
			$contact = $contact->leftJoin('{{%custom_field_value}} c', 'c.cid=a.id');
			$contact = $contact->select('c.value,c.cid')->where(['c.type' => 1, 'fieldid' => 3])->asArray()->all();
			$gender  = [];
			if (!empty($contact)) {
				foreach ($contact as $cnt) {
					$genderVal = 0;
					if ($cnt['value'] == '男') {
						$genderVal = 1;
					} elseif ($cnt['value'] == '女') {
						$genderVal = 2;
					}
					$gender[$cnt['cid']] = $genderVal;
				}
			}
			$contactLine = WorkContactWayLine::find()->where(['gender' => NULL])->all();
			foreach ($contactLine as $line) {
				if (isset($gender[$line->external_userid])) {
					$line->gender = $gender[$line->external_userid];
					$line->save();
				}
			}
			echo '更新完成';
		}

	}