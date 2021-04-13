<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/11/14
	 * Time: 09:35
	 */
	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\Fans;
	use app\models\FansStatistic;
	use app\models\FansTimeLine;
	use app\models\WxAuthorizeInfo;
	use yii\web\MethodNotAllowedHttpException;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\db\Expression;
	use app\util\DateUtil;
	use moonland\phpexcel\Excel;

	class IndexController extends AuthBaseController
	{
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'fans-top'       => ['POST'],
						'fans-increase'  => ['POST'],
						'fans-attribute' => ['POST'],
						'fans-active'    => ['POST'],
						'fans-message'   => ['POST'],
						'fans-loyalty'   => ['POST'],
					],
				],
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           每个公众号指标数据
		 * @description     每个公众号指标数据
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-top
		 *
		 * @param wx_id 选填 int wx_id
		 *
		 * @return          {"error":0,"data":{"one":{"status":1,"count":"0","per":"0.0%"},"two":{"status":1,"count":"0","per":"0.0%"},"three":{"status":1,"count":402,"per":"0.0%"},"four":{"status":1,"count":"0","per":"0.0%"},"five":{"status":1,"count":"402","per":"0.0%"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据one-five代表从左到右
		 * @return_param    status int 1上升0下降
		 * @return_param    count int 数量
		 * @return_param    per string 百分比
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/22 14:51
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansTop ()
		{
			if (\Yii::$app->request->isPost) {
				$wx_id = \Yii::$app->request->post('wx_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$result = Fans::getIndexFansData($this->wxAuthorInfo->author_id);

				return $result;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           粉丝增长
		 * @description     粉丝增长
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-increase
		 *
		 * @param wx_id 必选 string wx_id
		 * @param s_date 必选 string 开始日期
		 * @param e_date 必选 string 结束日期
		 * @param s_week 选填 int 按周时传
		 * @param type 必选 int 1按小时2按天3按周4按月
		 * @param is_export 选填 int 点导出时传1
		 *
		 * @return          {"error":0,"data":{"fans_stat":{"fans_new":"0","fans_cancel":"0","fans_increase":0,"cancel_per":"0.0%","perDay":0},"fans_data":[{"new":"0","cancel":"0","new_incre":0,"per":"0.0%","hour":"2019-11-27"},{"new":"0","cancel":"0","new_incre":0,"per":"0.0%","hour":"2019-11-28"}],"url":"","legData":["净增长","取消关注","新增关注"],"xData":["2019-11-27","2019-11-28"],"seriesData":[{"name":"净增长","type":"line","smooth":true,"data":[0,0]},{"name":"取消关注","type":"line","smooth":true,"data":[0,0]},{"name":"新增关注","type":"line","smooth":true,"data":[0,0]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    fans_stat array 这段时间里的数据
		 * @return_param    fans_new string 新关注
		 * @return_param    fans_cancel string 取消关注
		 * @return_param    fans_increase string 净增长
		 * @return_param    cancel_per string 取关率
		 * @return_param    perDay string 平均每天增长
		 * @return_param    fans_data array 底下的详细数据
		 * @return_param    xData array X轴数据
		 * @return_param    legData array 对应数据
		 * @return_param    seriesData array 总的数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/27 14:18
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansIncrease ()
		{
			if (\Yii::$app->request->isPost) {
				$wx_id = \Yii::$app->request->post('wx_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$date1     = \Yii::$app->request->post('s_date');
				$date2     = \Yii::$app->request->post('e_date');
				$s_week    = \Yii::$app->request->post('s_week');
				$type      = \Yii::$app->request->post('type') ?: 1; //1小时
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('请传入日期！');
				}
				if ($type == 3 && empty($s_week)) {
					throw new InvalidParameterException('请传入起始周！');
				}
				$date       = date('Y-m-d', time());
				$author_id  = $this->wxAuthorInfo->author_id;
				$wxInfo = WxAuthorizeInfo::findOne(['author_id'=>$author_id]);
				$total_fans = 0;
				//总粉丝数
				if ($date == $date2) {
					$total_fans = Fans::find()->andWhere(['subscribe' => 1, 'author_id' => $author_id])->count();
				} else {
					$fans_static = FansStatistic::find()->andWhere(['author_id' => $author_id, 'type' => 1, 'data_time' => $date2])->one();
					if (!empty($fans_static)) {
						$total_fans = $fans_static->total;
					}
				}
				//根据类型获取数据
				$result = $this->getFansIncreaseByType($type, $total_fans, $author_id, $date1, $date2, $s_week);
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
					$columns  = ['hour', 'new', 'cancel', 'new_incre', 'per'];
					$headers  = [
						'hour'      => '时间',
						'new'       => '新关注粉丝',
						'cancel'    => '取关粉丝',
						'new_incre' => '净增长',
						'per'       => '取关率',
					];
					$fileName = '【'.$wxInfo->nick_name.'】粉丝增长_' . date("YmdHis", time());
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
				//新关注
				$fans_new = Fans::getFansCount(1, $author_id, $date1, $date2 . " 23:59:59");
				//取消关注
				$fans_cancel = Fans::getFansCount(2, $author_id, $date1, $date2 . " 23:59:59");
				//净增
				$fans_increase = $fans_new - $fans_cancel;
				if ($type != 1) {
					$fans_new      = $result['newFans'];
					$fans_cancel   = $result['cancelFans'];
					$fans_increase = $result['newIncre'];
				}
				//取关率  取关粉丝数/（取关粉丝数+总粉丝数）
				$per = '0.0%';
				if ($total_fans > 0) {
					$per = $this->getPer($fans_cancel, $fans_cancel + $total_fans);
				}
				//平均每天增长
				if ($fans_increase <= 0) {
					$perDay = 0;
				} else {
					$stimestamp = strtotime($date1);
					$etimestamp = strtotime($date2);
					$days       = ($etimestamp - $stimestamp) / 86400 + 1;
					$perDay     = floor($fans_increase / $days);
				}
				$fans_stat = [
					'fans_new'      => $fans_new,
					'fans_cancel'   => $fans_cancel,
					'fans_increase' => $fans_increase,
					'cancel_per'    => $per,
					'perDay'        => $perDay,
				];
				$legData   = ['净增长', '取消关注', '新增关注'];
				$info      = [
					'fans_stat'  => $fans_stat,
					'fans_data'  => $result['data'],
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
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           粉丝属性
		 * @description     粉丝属性
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-attribute
		 *
		 * @param wx_id 必选 string wx_id
		 * @param s_date 必选 string 开始日期
		 * @param e_date 必选 string 结束日期
		 * @param type 可选 int 1新增2全部
		 * @param is_export 选填 int 点导出时传1默认0
		 *
		 * @return          {"error":0,"data":{"legData1":["男","女","未知"],"legData2":["公众号搜索","公众号迁移","名片分享","扫描二维码","图文页内名称点击","图文页右上角菜单","支付后关注","其他"],"xData":["2019-11-27","2019-11-28"],"sexData":{"fans_count":0,"fans_male":0,"fans_male_per":"0.0%","fans_female":0,"fans_female_per":"0.0%","fans_unknown":0,"fans_unknown_per":"0.0%"},"pieData1":[{"value":0,"name":"男"},{"value":0,"name":"女"},{"value":0,"name":"未知"}],"pieData2":[{"value":0,"name":"公众号搜索"},{"value":0,"name":"公众号迁移"},{"value":0,"name":"名片分享"},{"value":0,"name":"扫描二维码"},{"value":0,"name":"图文页内名称点击"},{"value":0,"name":"图文页右上角菜单"},{"value":0,"name":"支付后关注"},{"value":0,"name":"其他"}],"seriesData1":[{"name":"男","type":"line","smooth":true,"data":[0,0]},{"name":"女","type":"line","smooth":true,"data":[0,0]},{"name":"未知","type":"line","smooth":true,"data":[0,0]}],"seriesData2":[{"name":"公众号搜索","type":"line","smooth":true,"data":[0,0]},{"name":"公众号迁移","type":"line","smooth":true,"data":[0,0]},{"name":"名片分享","type":"line","smooth":true,"data":[0,0]},{"name":"扫描二维码","type":"line","smooth":true,"data":[0,0]},{"name":"图文页内名称点击","type":"line","smooth":true,"data":[0,0]},{"name":"图文页右上角菜单","type":"line","smooth":true,"data":[0,0]},{"name":"支付后关注","type":"line","smooth":true,"data":[0,0]},{"name":"其他","type":"line","smooth":true,"data":[0,0]}],"sourceDetail":[{"name":"公众号搜索","count":0,"per":"0.00%"},{"name":"公众号迁移","count":0,"per":"0.00%"},{"name":"名片分享","count":0,"per":"0.00%"},{"name":"扫描二维码","count":0,"per":"0.00%"},{"name":"图文页内名称点击","count":0,"per":"0.00%"},{"name":"图文页右上角菜单","count":0,"per":"0.00%"},{"name":"支付后关注","count":0,"per":"0.00%"},{"name":"其他","count":0,"per":"0.00%"}],"province":[],"provinceData":[],"countryData":[],"max":0}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    legData1 array 性别
		 * @return_param    legData2 array 来源
		 * @return_param    xData array X轴
		 * @return_param    sexData array 性别头部数据
		 * @return_param    seriesData1 array 性别
		 * @return_param    seriesData2 array 来源
		 * @return_param    sourceDetail array 来源列表
		 * @return_param    pieData1 array 性别饼状
		 * @return_param    pieData2 array 来源饼状
		 * @return_param    province array 柱状图
		 * @return_param    provinceData array 柱状图
		 * @return_param    countryData array 地图
		 * @return_param    max int 地图最大值
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/28 15:54
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansAttribute ()
		{
			if (\Yii::$app->request->isPost) {
				$wx_id = \Yii::$app->request->post('wx_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$date1     = \Yii::$app->request->post('s_date');
				$date2     = \Yii::$app->request->post('e_date');
				$type      = \Yii::$app->request->post('type') ?: 1; //1新增粉丝2全部
				$is_export = \Yii::$app->request->post('is_export') ?: 0; //1导出
				if ((empty($date1) || empty($date2)) && $type == 1) {
					throw new InvalidParameterException('日期不能为空！');
				}
				$authorInfo   = $this->wxAuthorInfo->author;
				$author_id    = $authorInfo->author_id;
				$wxInfo = WxAuthorizeInfo::findOne(['author_id'=>$author_id]);
				$fansData     = Fans::find()->andWhere(['author_id' => $author_id, 'subscribe' => 1]);
				$xData        = $legData1 = $legData2 = $sexData1 = $seriesData1 = $seriesData2 = $pieData1 = $pieData2 = $sourceDetail = [];
				$defaultData  = $this->getFansIncreaseDefaultData();
				$pieData1     = $defaultData['pieData1'];
				$pieData2     = $defaultData['pieData2'];
				$sourceDetail = $defaultData['sourceDetail'];
				if ($type == 1) {
					$legData1 = ['男', '女', '未知'];
					$legData2 = ['公众号搜索', '公众号迁移', '名片分享', '扫描二维码', '图文页内名称点击', '图文页右上角菜单', '支付后关注', '其他'];
					//新增粉丝
					$s_date = strtotime($date1);
					$e_date = strtotime($date2 . ' 23:59:59');
					$data   = DateUtil::getDateFromRange($date1, $date2);
					$xData  = $data;
					//获取折线图和饼状图数据
					$info1        = $this->getFansCountByGroup($data, $author_id);
					$sexData1     = $info1['sex'];
					$seriesData1  = $info1['seriesData1'];
					$seriesData2  = $info1['seriesData2'];
					$sourceDetail = $info1['sourceDetail'];
					$pieData1     = $info1['pieData1'];
					$pieData2     = $info1['pieData2'];
				} else {
					//全部粉丝
					$select  = new Expression('count(id) as cc,sex');
					$sexData = $fansData->select($select)->groupBy('sex')->asArray()->all();
					$total   = 0;
					if (!empty($sexData)) {
						foreach ($sexData as $sex) {
							if (!empty($sex['cc'])) {
								$total += intval($sex['cc']);
							}
							foreach ($pieData1 as $k => $v) {
								if ($v['name'] == '男' && $sex['sex'] == 1) {
									$pieData1[$k]['value'] = $sex['cc'];
								} elseif ($v['name'] == '女' && $sex['sex'] == 2) {
									$pieData1[$k]['value'] = $sex['cc'];
								} elseif ($v['name'] == '未知' && $sex['sex'] == 0) {
									$pieData1[$k]['value'] = $sex['cc'];
								}
							}
						}
					}
					$select     = new Expression('count(id) as cc,subscribe_scene');
					$sourceData = $fansData->select($select)->groupBy('subscribe_scene')->asArray()->all();
					if (!empty($sourceData)) {
						$fanIncre     = $this->getFansIncreaseTotal($sourceData, $sourceDetail, $total, $pieData2);
						$sourceDetail = $fanIncre['sourceDetail'];
						$pieData2     = $fanIncre['pieData2'];
					}
				}

				if ($is_export == 1) {
					if (empty($sourceDetail)) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['name', 'count', 'per'];
					$headers  = [
						'name'  => '渠道',
						'count' => '关注量',
						'per'   => '关注量占比',
					];
					$fileName = '【'.$wxInfo->nick_name.'】粉丝属性_' . date("YmdHis",time());
					Excel::export([
						'models'       => $sourceDetail,//数库
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

				//获取地图数据
				$select = new Expression('count(id) as cc,province');
				if ($type == 1) {
					$fansData = $fansData->andWhere(['between', 'subscribe_time', $s_date, $e_date]);
				}
				$fansData     = $fansData->select($select)->groupBy('province')->orderBy(['cc'=>SORT_DESC])->limit(10);
				$fansData     = $fansData->asArray()->all();
				$province     = [];
				$provinceData = [];
				$countryData  = [];
				$i = 0;
				if (!empty($fansData)) {
					foreach ($fansData as $key => $prov) {
						if (!empty($prov['province'])) {
							array_push($province, $prov['province']);
							array_push($provinceData, intval($prov['cc']));
							$countryData[$i]['name']  = $prov['province'];
							$countryData[$i]['value'] = intval($prov['cc']);
							$i++;
						}
					}
				}
				$max = 0;
				if (!empty($provinceData)) {
					$max = max($provinceData);
				}
				$result['legData1']     = $legData1;
				$result['legData2']     = $legData2;
				$result['xData']        = $xData;
				$result['sexData']      = $sexData1;
				$result['pieData1']     = $pieData1;
				$result['pieData2']     = $pieData2;
				$result['seriesData1']  = $seriesData1;
				$result['seriesData2']  = $seriesData2;
				$result['sourceDetail'] = $sourceDetail;
				$result['province']     = $province;
				$result['provinceData'] = $provinceData;
				$result['countryData']  = $countryData;
				$result['max']          = $max;

				return $result;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 *  获取全部粉丝的饼状图
		 *
		 * @param $sourceData
		 * @param $sourceDetail
		 * @param $total
		 * @param $pieData2
		 *
		 * @return array
		 */
		private function getFansIncreaseTotal ($sourceData, $sourceDetail, $total, $pieData2)
		{
			foreach ($sourceData as $source) {
				foreach ($sourceDetail as $k => $detail) {
					if ($detail['name'] == '公众号搜索' && $source['subscribe_scene'] == 'ADD_SCENE_SEARCH') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '公众号迁移' && $source['subscribe_scene'] == 'ADD_SCENE_ACCOUNT_MIGRATION') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '名片分享' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_CARD') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '扫描二维码' && $source['subscribe_scene'] == 'ADD_SCENE_QR_CODE') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '图文页内名称点击' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_LINK') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '图文页右上角菜单' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_ITEM') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '支付后关注' && $source['subscribe_scene'] == 'ADD_SCENE_PAID') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					} elseif ($detail['name'] == '其他' && $source['subscribe_scene'] == 'ADD_SCENE_OTHERS') {
						$sourceDetail[$k]['count'] = intval($source['cc']);
						$sourceDetail[$k]['per']   = $this->getPer(intval($source['cc']), $total, 1);
					}
				}
				foreach ($pieData2 as $kk => $vv) {
					if ($vv['name'] == '公众号搜索' && $source['subscribe_scene'] == 'ADD_SCENE_SEARCH') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '公众号迁移' && $source['subscribe_scene'] == 'ADD_SCENE_ACCOUNT_MIGRATION') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '名片分享' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_CARD') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '扫描二维码' && $source['subscribe_scene'] == 'ADD_SCENE_QR_CODE') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '图文页内名称点击' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_LINK') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '图文页右上角菜单' && $source['subscribe_scene'] == 'ADD_SCENE_PROFILE_ITEM') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '支付后关注' && $source['subscribe_scene'] == 'ADD_SCENE_PAID') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					} elseif ($vv['name'] == '其他' && $source['subscribe_scene'] == 'ADD_SCENE_OTHERS') {
						$pieData2[$kk]['value'] = intval($source['cc']);
					}
				}

			}
			$info['sourceDetail'] = $sourceDetail;
			$info['pieData2']     = $pieData2;

			return $info;
		}

		/**
		 *  获取新增粉丝的饼状折线图
		 *
		 * @param $data  天的时间跨度
		 * @param $author_id
		 *
		 * @return array
		 */
		private function getFansCountByGroup ($data, $author_id)
		{
			//获取默认数据
			$defaultData  = $this->getFansIncreaseDefaultData();
			$sex          = $defaultData['sex'];
			$sourceDetail = $defaultData['sourceDetail'];

			$pieData1 = $seriesData1 = $seriesData2 = $male = $female = $unknown = $one = $two = $three = $four = $five = $six = $seven = $eight = [];
			$count1   = $count2 = $count3 = $count4 = $count5 = $count6 = $count7 = $count8 = 0;
			foreach ($data as $k => $v) {
				$sexData    = [
					'male'    => 0,
					'female'  => 0,
					'unknown' => 0,
				];
				$sourceData = [
					'one'   => 0,
					'two'   => 0,
					'three' => 0,
					'four'  => 0,
					'five'  => 0,
					'six'   => 0,
					'seven' => 0,
					'eight' => 0,
				];
				$fansData   = FansStatistic::find()->andWhere(['author_id' => $author_id, 'type' => 1, 'data_time' => $v])->one();
				if (!empty($fansData)) {
					$sexData['unknown']  = !empty($fansData->unknown) ? $fansData->unknown : 0;
					$sexData['female']   = !empty($fansData->female) ? $fansData->female : 0;
					$sexData['male']     = !empty($fansData->male) ? $fansData->male : 0;
					$sourceData['one']   = !empty($fansData->add_scene_search) ? $fansData->add_scene_search : 0;
					$sourceData['two']   = !empty($fansData->add_scene_account_migration) ? $fansData->add_scene_account_migration : 0;
					$sourceData['three'] = !empty($fansData->add_scene_profile_card) ? $fansData->add_scene_profile_card : 0;
					$sourceData['four']  = !empty($fansData->add_scene_qr_code) ? $fansData->add_scene_qr_code : 0;
					$sourceData['five']  = !empty($fansData->add_scene_profile_link) ? $fansData->add_scene_profile_link : 0;
					$sourceData['six']   = !empty($fansData->add_scene_profile_item) ? $fansData->add_scene_profile_item : 0;
					$sourceData['seven'] = !empty($fansData->add_scene_paid) ? $fansData->add_scene_paid : 0;
					$sourceData['eight'] = !empty($fansData->add_scene_others) ? $fansData->add_scene_others : 0;
				}
				$count1 += $sourceData['one'];
				$count2 += $sourceData['two'];
				$count3 += $sourceData['three'];
				$count4 += $sourceData['four'];
				$count5 += $sourceData['five'];
				$count6 += $sourceData['six'];
				$count7 += $sourceData['seven'];
				$count8 += $sourceData['eight'];
				$sex['fans_unknown'] += $sexData['unknown'];
				array_push($unknown, $sexData['unknown']);
				$sex['fans_male'] += $sexData['male'];
				array_push($male, $sexData['male']);
				$sex['fans_female'] += $sexData['female'];
				array_push($female, $sexData['female']);
				//来源
				array_push($one, $sourceData['one']);
				array_push($two, $sourceData['two']);
				array_push($three, $sourceData['three']);
				array_push($four, $sourceData['four']);
				array_push($five, $sourceData['five']);
				array_push($six, $sourceData['six']);
				array_push($seven, $sourceData['seven']);
				array_push($eight, $sourceData['eight']);
			}
			$sex['fans_count']       = $sex['fans_unknown'] + $sex['fans_male'] + $sex['fans_female'];
			$sex['fans_male_per']    = $this->getPer($sex['fans_male'], $sex['fans_count']);
			$sex['fans_female_per']  = $this->getPer($sex['fans_female'], $sex['fans_count']);
			$sex['fans_unknown_per'] = $this->getPer($sex['fans_unknown'], $sex['fans_count']);
			$pieData1                = [
				[
					'value' => $sex['fans_male'],
					'name'  => '男',
				],
				[
					'value' => $sex['fans_female'],
					'name'  => '女',
				],
				[
					'value' => $sex['fans_unknown'],
					'name'  => '未知',
				]
			];
			$seriesData1             = [
				[
					'name'   => '男',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $male,
				],
				[
					'name'   => '女',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $female,
				],
				[
					'name'   => '未知',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $unknown,
				],
			];

			//来源
			$seriesData2 = [
				[
					'name'   => '公众号搜索',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $one,
				],
				[
					'name'   => '公众号迁移',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $two,
				],
				[
					'name'   => '名片分享',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $three,
				],
				[
					'name'   => '扫描二维码',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $four,
				],
				[
					'name'   => '图文页内名称点击',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $five,
				],
				[
					'name'   => '图文页右上角菜单',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $six,
				],
				[
					'name'   => '支付后关注',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $seven,
				],
				[
					'name'   => '其他',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $eight,
				]
			];
			$total       = $count1 + $count2 + $count3 + $count4 + $count5 + $count6 + $count7 + $count8;
			foreach ($sourceDetail as $k => $v) {
				switch ($v['name']) {
					case "公众号搜索":
						$sourceDetail[$k]['count'] = $count1;
						$sourceDetail[$k]['per']   = $this->getPer($count1, $total, 1);
						break;
					case "公众号迁移":
						$sourceDetail[$k]['count'] = $count2;
						$sourceDetail[$k]['per']   = $this->getPer($count2, $total, 1);
						break;
					case "名片分享":
						$sourceDetail[$k]['count'] = $count3;
						$sourceDetail[$k]['per']   = $this->getPer($count3, $total, 1);
						break;
					case "扫描二维码":
						$sourceDetail[$k]['count'] = $count4;
						$sourceDetail[$k]['per']   = $this->getPer($count4, $total, 1);
						break;
					case "图文页内名称点击":
						$sourceDetail[$k]['count'] = $count5;
						$sourceDetail[$k]['per']   = $this->getPer($count5, $total, 1);
						break;
					case "图文页右上角菜单":
						$sourceDetail[$k]['count'] = $count6;
						$sourceDetail[$k]['per']   = $this->getPer($count6, $total, 1);
						break;
					case "支付后关注":
						$sourceDetail[$k]['count'] = $count7;
						$sourceDetail[$k]['per']   = $this->getPer($count7, $total, 1);
						break;
					case "其他":
						$sourceDetail[$k]['count'] = $count8;
						$sourceDetail[$k]['per']   = $this->getPer($count8, $total, 1);
						break;
				}
			}
			$pieData2             = [
				[
					'value' => $count1,
					'name'  => '公众号搜索',
				],
				[
					'value' => $count2,
					'name'  => '公众号迁移',
				],
				[
					'value' => $count3,
					'name'  => '名片分享',
				],
				[
					'value' => $count4,
					'name'  => '扫描二维码',
				],
				[
					'value' => $count5,
					'name'  => '图文页内名称点击',
				],
				[
					'value' => $count6,
					'name'  => '图文页右上角菜单',
				],
				[
					'value' => $count7,
					'name'  => '支付后关注',
				],
				[
					'value' => $count8,
					'name'  => '其他',
				],
			];
			$info['sex']          = $sex;
			$info['seriesData1']  = $seriesData1;
			$info['seriesData2']  = $seriesData2;
			$info['pieData1']     = $pieData1;
			$info['pieData2']     = $pieData2;
			$info['sourceDetail'] = $sourceDetail;

			return $info;

		}

		/**
		 * 获取粉丝增长的默认数据
		 *
		 * @return string
		 */
		private function getFansIncreaseDefaultData ()
		{
			$sex                    = [
				'fans_count'       => 0,
				'fans_male'        => 0,
				'fans_male_per'    => '0.0%',
				'fans_female'      => 0,
				'fans_female_per'  => '0.0%',
				'fans_unknown'     => 0,
				'fans_unknown_per' => '0.0%',
			];
			$sourceDetail           = [
				[
					'name'  => '公众号搜索',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '公众号迁移',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '名片分享',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '扫描二维码',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '图文页内名称点击',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '图文页右上角菜单',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '支付后关注',
					'count' => 0,
					'per'   => 0,
				],
				[
					'name'  => '其他',
					'count' => 0,
					'per'   => 0,
				],
			];
			$pieData1 = array(
				array(
					'value' => 0,
					'name' => '男',
				),
				array(
					'value' => 0,
					'name' => '女',
				),
				array(
					'value' => 0,
					'name' => '未知',
				),
			);
			$pieData2             = [
				[
					'value' => 0,
					'name'  => '公众号搜索',
				],
				[
					'value' => 0,
					'name'  => '公众号迁移',
				],
				[
					'value' => 0,
					'name'  => '名片分享',
				],
				[
					'value' => 0,
					'name'  => '扫描二维码',
				],
				[
					'value' => 0,
					'name'  => '图文页内名称点击',
				],
				[
					'value' => 0,
					'name'  => '图文页右上角菜单',
				],
				[
					'value' => 0,
					'name'  => '支付后关注',
				],
				[
					'value' => 0,
					'name'  => '其他',
				],
			];
			$result['pieData1']          = $pieData1;
			$result['pieData2']          = $pieData2;
			$result['sex']          = $sex;
			$result['sourceDetail'] = $sourceDetail;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           粉丝活跃度
		 * @description     粉丝活跃度
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-active
		 *
		 * @param wx_id 必选 int wx_id
		 * @param s_date 必选 string 开始日期
		 * @param e_date 必选 string 结束日期
		 * @param is_export 可选 int 导出传1默认0
		 *
		 * @return          {"error":0,"data":{"detail":[{"date":"2019-11-27","active_day":0,"active_48h":0,"active_7d":0,"active_15d":0,"total":"402","active_per":"0.0%"},{"date":"2019-11-28","active_day":0,"active_48h":0,"active_7d":0,"active_15d":0,"total":"402","active_per":"0.0%"},{"date":"平均","active_day":0,"active_48h":0,"active_7d":0,"active_15d":0,"total":"402.0","active_per":"0.0%"}],"every_day_count":0,"every_day_per":"0.0%","xData":["2019-11-27","2019-11-28"],"legData":["48小时内互动","7天内互动","15天内互动"],"seriesData":[{"name":"48小时内互动","type":"line","smooth":true,"data":[0,0]},{"name":"7天内互动","type":"line","smooth":true,"data":[0,0]},{"name":"15天内互动","type":"line","smooth":true,"data":[0,0]}],"header":["日期","当日活跃粉丝数","48小时内互动粉丝数","7天内互动粉丝数","15天内互动粉丝数","总粉丝数","活跃比例"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    detail array 列表数据
		 * @return_param    every_day_count int 平均每天活跃粉丝数
		 * @return_param    every_day_per string 平均活跃比例
		 * @return_param    xData array xData
		 * @return_param    legData array legData
		 * @return_param    seriesData array seriesData
		 * @return_param    headers array 列表头部
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/29 17:41
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansActive ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$date1     = \Yii::$app->request->post('s_date');
				$date2     = \Yii::$app->request->post('e_date');
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('日期不能为空！');
				}
				$activeCount = 0; //总共的活跃粉丝数
				$forthData   = $sevenData = $fifteenData = $perData = $total = 0;
				$xData       = $one = $two = $three = [];
				$legData     = ['48小时内互动', '7天内互动', '15天内互动'];
				$authorInfo  = $this->wxAuthorInfo->author;
				$author_id   = $authorInfo->author_id;
				$wxInfo = WxAuthorizeInfo::findOne(['author_id'=>$author_id]);
				$data        = DateUtil::getDateFromRange($date1, $date2);
				$xData       = $data;
				$detail      = []; //详细数据

				foreach ($data as $k => $v) {
					$first    = $second = $third = $fourth = $fansTotal = 0;
					$fansData = FansStatistic::find()->andWhere(['author_id' => $author_id, 'type' => 1, 'data_time' => $v])->one();
					if (!empty($fansData)) {
						$first     = $fansData->active;
						$second    = $fansData->active_48h;
						$third     = $fansData->active_7d;
						$fourth    = $fansData->active_15d;
						$fansTotal = $fansData->total;
					}
					array_push($one, $second);
					array_push($two, $third);
					array_push($three, $fourth);
					$activeCount              += $first;
					$forthData                += $second;
					$sevenData                += $third;
					$fifteenData              += $fourth;
					$total                    += $fansTotal;
					$detail[$k]['date']       = $v;
					$detail[$k]['active_day'] = $first;
					$detail[$k]['active_48h'] = $second;
					$detail[$k]['active_7d']  = $third;
					$detail[$k]['active_15d'] = $fourth;
					$detail[$k]['total']      = $fansTotal;
					$detail[$k]['active_per'] = $this->getPer($first, $fansTotal);
					if ($fansTotal > 0) {
						$perData += round($first / $fansTotal, 3);
					}
				}
				$detail[count($data)] = [
					'date'       => '平均',
					'active_day' => round($activeCount / count($data), 1),
					'active_48h' => round($forthData / count($data), 1),
					'active_7d'  => round($sevenData / count($data), 1),
					'active_15d' => round($fifteenData / count($data), 1),
					'total'      => round($total / count($data), 1),
					'active_per' => $this->getPer($perData, count($data)),
				];

				if ($is_export == 1) {
					if (empty($detail)) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['date', 'active_day', 'active_48h', 'active_7d', 'active_15d', 'total', 'active_per'];
					$headers  = [
						'date'       => '日期',
						'active_day' => '当日活跃粉丝数',
						'active_48h' => '48小时内互动粉丝数',
						'active_7d'  => '7天内互动粉丝数',
						'active_15d' => '15天内互动粉丝数',
						'total'      => '总粉丝数',
						'active_per' => '活跃比例',
					];
					$fileName = '【'.$wxInfo->nick_name.'】粉丝活跃度_' . date("YmdHis", time());
					Excel::export([
						'models'       => $detail,//数库
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

				$seriesData = [
					[
						"name"   => "48小时内互动",
						"type"   => "line",
						"smooth" => true,
						"data"   => $one
					],
					[
						"name"   => "7天内互动",
						"type"   => "line",
						"smooth" => true,
						"data"   => $two
					],
					[
						"name"   => "15天内互动",
						"type"   => "line",
						"smooth" => true,
						"data"   => $three
					],
				];
				$every_day_count         = round($activeCount / count($data), 1);
				$every_day_per           = $this->getPer($perData, count($data));
				$header                  = ['日期', '当日活跃粉丝数', '48小时内互动粉丝数', '7天内互动粉丝数', '15天内互动粉丝数', '总粉丝数', '活跃比例'];
				$info['detail']          = $detail;
				$info['every_day_count'] = $every_day_count;
				$info['every_day_per']   = $every_day_per;
				$info['xData']           = $xData;
				$info['legData']         = $legData;
				$info['seriesData']      = $seriesData;
				$info['header']          = $header;

				return $info;

			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           互动消息
		 * @description     互动消息
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-message
		 *
		 * @param wx_id 必选 int wx_id
		 * @param s_date 必选 string 开始日期
		 * @param e_date 必选 string 结束日期
		 * @param type 必选 int 1互动时段2互动类型
		 * @param is_export 可选 int 导出传1默认0
		 *
		 * @return          {"error":0,"data":{"pieData":[],"xData":["00:00-01:00","01:00-02:00","02:00-03:00","03:00-04:00","04:00-05:00","05:00-06:00","06:00-07:00","07:00-08:00","08:00-09:00","09:00-10:00","10:00-11:00","11:00-12:00","12:00-13:00","13:00-14:00","14:00-15:00","15:00-16:00","16:00-17:00","17:00-18:00","18:00-19:00","19:00-20:00","20:00-21:00","21:00-22:00","22:00-23:00","23:00-24:00"],"legData":["菜单点击","扫描二维码","关注","取关","粉丝消息"],"seriesData":[{"name":"菜单点击","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"扫描二维码","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"关注","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"取关","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"粉丝消息","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,5,8,0,1,3,6,3,0,0,0,0]}],"detail":[{"hour":"00:00-01:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"01:00-02:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"02:00-03:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"03:00-04:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"04:00-05:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"05:00-06:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"06:00-07:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"07:00-08:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"08:00-09:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"09:00-10:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"10:00-11:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"11:00-12:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"12:00-13:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"13:00-14:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":5,"total":5},{"hour":"14:00-15:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":8,"total":8},{"hour":"15:00-16:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"16:00-17:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":1,"total":1},{"hour":"17:00-18:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":3,"total":3},{"hour":"18:00-19:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":6,"total":6},{"hour":"19:00-20:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":3,"total":3},{"hour":"20:00-21:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"21:00-22:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"22:00-23:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0},{"hour":"23:00-24:00","click":0,"scan":0,"subscribe":0,"unsubscribe":0,"message":0,"total":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    xData array xData
		 * @return_param    legData array legData
		 * @return_param    seriesData array seriesData
		 * @return_param    detail array 列表数据
		 * @return_param    pieData array 互动类型的饼状数据
		 * @return_param    url string 导出时使用
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/1 10:09
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansMessage ()
		{
			if (\Yii::$app->request->isPost) {
				$wx_id = \Yii::$app->request->post('wx_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$date1 = \Yii::$app->request->post('s_date');
				$date2 = \Yii::$app->request->post('e_date');
				$type  = \Yii::$app->request->post('type') ?: 1;; //1互动时段
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($date1) || empty($date2)) {
					throw new InvalidParameterException('日期不能为空！');
				}
				$seriesData = $xData = $legData = $pieData = $result = [];
				$authorInfo = $this->wxAuthorInfo->author;
				$author_id  = $authorInfo->author_id;
				$wxInfo = WxAuthorizeInfo::findOne(['author_id'=>$author_id]);
				$fansLine   = FansTimeLine::find()->alias('ftl');
				$fansLine   = $fansLine->leftJoin('{{%fans}} f', '`f`.`id` = `ftl`.`fans_id`');
				$fansLine   = $fansLine->andWhere(['f.author_id' => $author_id]);
				$fansLine   = $fansLine->andWhere(['ftl.source' => 0]);
				$fansLine   = $fansLine->andFilterWhere(['between', 'ftl.event_time', $date1, $date2.' 23:59:59']);
				if ($type == 1) {
					$xData   = ['00:00-01:00', '01:00-02:00', '02:00-03:00', '03:00-04:00', '04:00-05:00', '05:00-06:00', '06:00-07:00', '07:00-08:00', '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00', '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00', '17:00-18:00', '18:00-19:00', '19:00-20:00', '20:00-21:00', '21:00-22:00', '22:00-23:00', '23:00-24:00'];
					$legData = ['菜单点击', '扫描二维码', '关注', '取关', '粉丝消息'];

					$select   = new Expression('count(ftl.id) as cc,event,DATE_FORMAT(event_time,"%H") hour');
					$fansLine = $fansLine->select($select)->groupBy('hour,event');
					//echo $fansLine->createCommand()->getRawSql();die();
					$fansLine = $fansLine->asArray()->all();
					$click    = $scan = $subscribe = $unsubscribe = $message = [];
					for ($i = 0; $i < 24; $i++) {
						$j = $i;
						if ($j < 10) {
							$j = '0' . $i;
						}
						$k = $j + 1;
						if ($k < 10) {
							$k = '0' . $k;
						}
						$result[$i] = [
							'hour'        => $j . ':00' . '-' . $k . ':00',
							'click'       => 0,
							'scan'        => 0,
							'subscribe'   => 0,
							'unsubscribe' => 0,
							'message'     => 0,
						];
					}
					foreach ($result as $k => $v) {
						$hour = explode('-', $v['hour']);
						$h    = explode(':', $hour[1]);
						$one  = $two = $three = $four = $five = 0;
						if (!empty($fansLine)) {
							foreach ($fansLine as $line) {
								if ($line['hour'] == '00') {
									$hour = '01';
								} else {
									$hour = $line['hour'];
								}
								if ($hour == $h[0]) {
									if ($line['event'] == 'click' || $line['event'] == 'view') {
										$one += intval($line['cc']);
									}
									if ($line['event'] == 'scan') {
										$two += intval($line['cc']);
									}
									if ($line['event'] == 'subscribe') {
										$three += intval($line['cc']);
									}
									if ($line['event'] == 'unsubscribe') {
										$four += intval($line['cc']);
									}
									if ($line['event'] == 'image' || $line['event'] == 'text' || $line['event'] == 'link' || $line['event'] == 'voice' || $line['event'] == 'video' || $line['event'] == 'shortvideo' || $line['event'] == 'location') {
										$five += intval($line['cc']);
									}
								}
							}
						}

						$result[$k]['click']       = $one;
						$result[$k]['scan']        = $two;
						$result[$k]['subscribe']   = $three;
						$result[$k]['unsubscribe'] = $four;
						$result[$k]['message']     = $five;
						array_push($click, $one);
						array_push($scan, $two);
						array_push($subscribe, $three);
						array_push($unsubscribe, $four);
						array_push($message, $five);

					}

					$seriesData = [
						[
							'name'   => '菜单点击',
							'type'   => 'line',
							'smooth' => true,
							'data'   => $click,
						],
						[
							'name'   => '扫描二维码',
							'type'   => 'line',
							'smooth' => true,
							'data'   => $scan,
						],
						[
							'name'   => '关注',
							'type'   => 'line',
							'smooth' => true,
							'data'   => $subscribe,
						],
						[
							'name'   => '取关',
							'type'   => 'line',
							'smooth' => true,
							'data'   => $unsubscribe,
						],
						[
							'name'   => '粉丝消息',
							'type'   => 'line',
							'smooth' => true,
							'data'   => $message,
						],
					];

					if (!empty($result)) {
						foreach ($result as $k => $v) {
							$total               = $v['click'] + $v['scan'] + $v['subscribe'] + $v['unsubscribe'] + $v['message'];
							$result[$k]['total'] = $total;
						}
					}

					if ($is_export == 1) {
						if (empty($result)) {
							throw new InvalidParameterException('暂无数据，无法导出！');
						}
						$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
						//创建保存目录
						if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
							return ['error' => 1, 'msg' => '无法创建目录'];
						}
						$columns  = ['hour', 'message', 'subscribe', 'unsubscribe', 'scan', 'click', 'total'];
						$headers  = [
							'hour'        => '时间段',
							'message'     => '粉丝消息',
							'subscribe'   => '关注',
							'unsubscribe' => '取关',
							'scan'        => '扫描二维码',
							'click'       => '菜单点击',
							'total'       => '总计',
						];
						$fileName = '【'.$wxInfo->nick_name.'】粉丝互动消息_' . date("YmdHis",time());
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


				} else {
					$pieData  = [
						[
							'value' => 0,
							'name'  => '粉丝消息',
						],
						[
							'value' => 0,
							'name'  => '关注消息',
						],
						[
							'value' => 0,
							'name'  => '取关消息',
						],
						[
							'value' => 0,
							'name'  => '扫描二维码',
						],
						[
							'value' => 0,
							'name'  => '点击菜单',
						],
					];
					$select   = new Expression('count(ftl.id) as cc,event');
					$fansLine = $fansLine->select($select)->groupBy('event');
					//echo $fansLine->createCommand()->getRawSql();die();
					$fansLine = $fansLine->asArray()->all();
					if (!empty($fansLine)) {
						foreach ($fansLine as $v) {
							foreach ($pieData as $k => $pie) {
								if (($v['event'] == 'click' || $v['event'] == 'view') && $pie['name'] == "点击菜单") {
									$pieData[$k]['value'] = $v['cc'];
								}
								if ($v['event'] == 'subscribe' && $pie['name'] == "关注消息") {
									$pieData[$k]['value'] = $v['cc'];
								}
								if ($v['event'] == 'unsubscribe' && $pie['name'] == "取关消息") {
									$pieData[$k]['value'] = $v['cc'];
								}
								if ($v['event'] == 'scan' && $pie['name'] == "扫描二维码") {
									$pieData[$k]['value'] = $v['cc'];
								}
								if (($v['event'] == 'image' || $v['event'] == 'text' || $v['event'] == 'link' || $v['event'] == 'voice' || $v['event'] == 'video' || $v['event'] == 'shortvideo' || $v['event'] == 'location') && $pie['name'] == "粉丝消息") {
									$pieData[$k]['value'] = $v['cc'];
								}
							}
						}
					}
				}
				$info['pieData']    = $pieData;
				$info['xData']      = $xData;
				$info['legData']    = $legData;
				$info['seriesData'] = $seriesData;
				$info['detail']     = $result;

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/index/
		 * @title           粉丝忠诚度
		 * @description     粉丝忠诚度
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-loyalty
		 *
		 * @param wx_id 必选 int wx_id
		 * @param date 必选 string 日期
		 * @param type 必选 int 1百分比2关注数
		 * @param is_export 可选 int 导出传1默认0
		 *
		 * @return          {"error":0,"data":[{"date":"2019-11-21","current_day_subscribe_fans":2,"current_day_stay_fans":2,"two_stay_fans":2,"three_stay_fans":2,"four_stay_fans":2,"five_stay_fans":1,"six_stay_fans":1,"seven_stay_fans":1},{"date":"2019-11-22","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":0,"seven_stay_fans":0},{"date":"2019-11-23","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":0,"seven_stay_fans":0},{"date":"2019-11-24","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":0,"seven_stay_fans":0},{"date":"2019-11-25","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":0,"seven_stay_fans":0},{"date":"2019-11-26","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":0,"seven_stay_fans":"--"},{"date":"2019-11-27","current_day_subscribe_fans":0,"current_day_stay_fans":0,"two_stay_fans":0,"three_stay_fans":0,"four_stay_fans":0,"five_stay_fans":0,"six_stay_fans":"--","seven_stay_fans":"--"},{"date":"平均留存率","current_day_subscribe_fans":"--","current_day_stay_fans":"28.6%","two_stay_fans":"28.6%","three_stay_fans":"28.6%","four_stay_fans":"28.6%","five_stay_fans":"14.3%","six_stay_fans":"14.3%","seven_stay_fans":"14.3%"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/1 16:29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansLoyalty ()
		{
			if (\Yii::$app->request->isPost) {
				$wx_id = \Yii::$app->request->post('wx_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$date      = \Yii::$app->request->post('date');
				$type      = \Yii::$app->request->post('type') ?: 1; //1互动时段
				$is_export = \Yii::$app->request->post('is_export');
				if (empty($date)) {
					throw new InvalidParameterException('日期不能为空！');
				}
				$result     = [];
				$authorInfo = $this->wxAuthorInfo->author;
				$author_id  = $authorInfo->author_id;
				$wxInfo = WxAuthorizeInfo::findOne(['author_id'=>$author_id]);
//				$fansLine   = FansTimeLine::find()->alias('ftl');
//				$fansLine   = $fansLine->leftJoin('{{%fans}} f', '`f`.`id` = `ftl`.`fans_id`');
//				$fansLine   = $fansLine->andWhere(['f.author_id' => $author_id]);
//				$fansLine   = $fansLine->andWhere(['ftl.event' => 'subscribe']);

				$weeks = DateUtil::get_weeks(strtotime($date));
				if ($type == 1) {
					$result = $this->getStayFansDay($weeks, $author_id);
					foreach ($result as $key => $day) {
						if($key==7){
							break;
						}
						if ($day['current_day_subscribe_fans'] > 0) {
							$result[$key]['current_day_stay_fans'] = $this->getPer($day['current_day_stay_fans'], $day['current_day_subscribe_fans']);
							if ($day['two_stay_fans'] != '--' || $day['two_stay_fans']===0) {
								$result[$key]['two_stay_fans'] = $this->getPer($day['two_stay_fans'], $day['current_day_subscribe_fans']);
							}
							if ($day['three_stay_fans'] != '--' || $day['three_stay_fans']===0) {
								$result[$key]['three_stay_fans'] = $this->getPer($day['three_stay_fans'], $day['current_day_subscribe_fans']);
							}
							if ($day['four_stay_fans'] != '--' || $day['four_stay_fans']===0) {
								$result[$key]['four_stay_fans'] = $this->getPer($day['four_stay_fans'], $day['current_day_subscribe_fans']);
							}
							if ($day['five_stay_fans'] != '--' || $day['five_stay_fans']===0) {
								$result[$key]['five_stay_fans'] = $this->getPer($day['five_stay_fans'], $day['current_day_subscribe_fans']);
							}
							if ($day['six_stay_fans'] != '--' || $day['six_stay_fans']===0) {
								$result[$key]['six_stay_fans'] = $this->getPer($day['six_stay_fans'], $day['current_day_subscribe_fans']);
							}
							if ($day['seven_stay_fans'] != '--' || $day['seven_stay_fans']===0) {
								$result[$key]['seven_stay_fans'] = $this->getPer($day['seven_stay_fans'], $day['current_day_subscribe_fans']);
							}
						} else {
							$result[$key]['current_day_stay_fans'] = '0.0%';
							if ($day['two_stay_fans'] === 0) {
								$result[$key]['two_stay_fans'] = '0.0%';
							}
							if ($day['three_stay_fans'] === 0) {
								$result[$key]['three_stay_fans'] = '0.0%';
							}
							if ($day['four_stay_fans'] === 0) {
								$result[$key]['four_stay_fans'] = '0.0%';
							}
							if ($day['five_stay_fans'] === 0) {
								$result[$key]['five_stay_fans'] = '0.0%';
							}
							if ($day['six_stay_fans'] === 0) {
								$result[$key]['six_stay_fans'] = '0.0%';
							}
							if ($day['seven_stay_fans'] === 0) {
								$result[$key]['seven_stay_fans'] = '0.0%';
							}
						}
					}
				} else {
					$result = $this->getStayFansDay($weeks, $author_id);
				}
				if ($is_export == 1) {
					if (empty($result)) {
						throw new InvalidParameterException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['date', 'current_day_subscribe_fans', 'current_day_stay_fans', 'two_stay_fans', 'three_stay_fans', 'four_stay_fans', 'five_stay_fans', 'six_stay_fans', 'seven_stay_fans'];
					$headers  = [
						'date'                       => '日期',
						'current_day_subscribe_fans' => '当天关注粉丝	',
						'current_day_stay_fans'      => '当天留存',
						'two_stay_fans'              => '2天留存',
						'three_stay_fans'            => '3天留存',
						'four_stay_fans'             => '4天留存',
						'five_stay_fans'             => '5天留存',
						'six_stay_fans'              => '6天留存',
						'seven_stay_fans'            => '7天留存',
					];
					$fileName = '【'.$wxInfo->nick_name.'】粉丝忠诚度_' . date("YmdHis",time());
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

				return $result;

			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * 获取每天留存粉丝数
		 *
		 * @return int
		 */
		private function getStayFansDay ($weeks, $author_id)
		{
			$result    = [];
			$total_current = $total_three_current = $total_four_current = $total_five_current = $total_six_current = $total_seven_current = $total_day = $total_two = $total_three = $total_four = $total_five = $total_six = $total_seven = 0;
			foreach ($weeks as $key => $week) {
				$key = $key-1;
				$current_day_fans        = 0;//当天关注粉丝数
				$current_stay_fans       = 0;//当天留存粉丝数
				$current_two_stay_fans   = 0;//2天留存粉丝数
				$current_three_stay_fans = 0;//3天留存粉丝数
				$current_four_stay_fans  = 0;//4天留存粉丝数
				$current_five_stay_fans  = 0;//5天留存粉丝数
				$current_six_stay_fans   = 0;//6天留存粉丝数
				$current_seven_stay_fans = 0;//7天留存粉丝数
				$date1                   = $week;
				$date2                   = $week . ' 23:59:59';

//				$fansLine   = FansTimeLine::find()->alias('ftl');
//				$fansLine   = $fansLine->leftJoin('{{%fans}} f', '`f`.`id` = `ftl`.`fans_id`');
//				$fansLine   = $fansLine->andWhere(['f.author_id' => $author_id]);
//				$fansLine   = $fansLine->andWhere(['ftl.event' => 'subscribe']);
//				$sub_fans                = $fansLine->andFilterWhere(['between', 'ftl.event_time', $date1, $date2]);
//				$sub_fans                = $sub_fans->select('ftl.fans_id,ftl.event_time');
//				$sub_fans                = $sub_fans->groupBy('ftl.fans_id');
//				$sub_fans = $sub_fans->asArray()->all();
				//$fansLine = NEW FansTimeLine();
				$sql      = 'SELECT * FROM (SELECT `ftl`.fans_id,`ftl`.event_time FROM {{%fans_time_line}} ftl LEFT JOIN  {{%fans}} f ON f.id=ftl.fans_id WHERE f.author_id=' . $author_id . ' AND ftl.event="subscribe" AND ftl.event_time BETWEEN "' . $date1 . '" AND "' . $date2 . '" ORDER BY ftl.event_time DESC ) b GROUP BY b.fans_id';
				$sub_fans = \Yii::$app->getDb()->createCommand($sql)->queryAll();
				if (!empty($sub_fans)) {
					$current_day_fans = count($sub_fans);
					$total_current += $current_day_fans;
				}
				$date_one   = date("Y-m-d", strtotime($week));
				$date_two   = date("Y-m-d", strtotime("+1 day", strtotime($week)));
				$date_three = date("Y-m-d", strtotime("+2 day", strtotime($week)));
				$date_four  = date("Y-m-d", strtotime("+3 day", strtotime($week)));
				$date_five  = date("Y-m-d", strtotime("+4 day", strtotime($week)));
				$date_six   = date("Y-m-d", strtotime("+5 day", strtotime($week)));
				$date_seven = date("Y-m-d", strtotime("+6 day", strtotime($week)));
				//当天留存
				if (strtotime($date_one) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_one, $date_one);
						$total_day             += $current_stay_fans;
					}
				} else {
					$current_stay_fans = '--';
				}

				//2日
				if (strtotime($date_two) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_two_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_two, $date_two);
						$total_two             += $current_two_stay_fans;
					}
				} else {
					$current_two_stay_fans = '--';
				}
				//3日
				if (strtotime($date_three) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_three_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_three, $date_three);
						$total_three             += $current_three_stay_fans;
					}
					if (!empty($sub_fans)) {
						$current_day_fans    = count($sub_fans);
						$total_three_current += $current_day_fans;
					}
				} else {
					$current_three_stay_fans = '--';
				}
				//4日
				if (strtotime($date_four) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_four_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_four, $date_four);
						$total_four             += $current_four_stay_fans;
					}
					if (!empty($sub_fans)) {
						$current_day_fans    = count($sub_fans);
						$total_four_current += $current_day_fans;
					}
				} else {
					$current_four_stay_fans = '--';
				}
				//5日
				if (strtotime($date_five) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_five_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_five, $date_five);
						$total_five             += $current_five_stay_fans;
					}
					if (!empty($sub_fans)) {
						$current_day_fans    = count($sub_fans);
						$total_five_current += $current_day_fans;
					}
				} else {
					$current_five_stay_fans = '--';
				}
				//6日
				if (strtotime($date_six) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_six_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_six, $date_six);
						$total_six             += $current_six_stay_fans;
					}
					if (!empty($sub_fans)) {
						$current_day_fans    = count($sub_fans);
						$total_six_current += $current_day_fans;
					}
				} else {
					$current_six_stay_fans = '--';
				}
				//7日
				if (strtotime($date_seven) <= strtotime(date("Y-m-d"))) {
					if ($current_day_fans > 0) {
						$current_seven_stay_fans = $this->getStayFans($sub_fans, $current_day_fans, $date_seven, $date_seven);
						$total_seven             += $current_seven_stay_fans;
					}
					if (!empty($sub_fans)) {
						$current_day_fans    = count($sub_fans);
						$total_seven_current += $current_day_fans;
					}
				} else {
					$current_seven_stay_fans = '--';
				}
				$result[$key]['date']                       = $week;
				$result[$key]['current_day_subscribe_fans'] = $current_day_fans;
				$result[$key]['current_day_stay_fans']      = $current_stay_fans;
				$result[$key]['two_stay_fans']              = $current_two_stay_fans;
				$result[$key]['three_stay_fans']            = $current_three_stay_fans;
				$result[$key]['four_stay_fans']             = $current_four_stay_fans;
				$result[$key]['five_stay_fans']             = $current_five_stay_fans;
				$result[$key]['six_stay_fans']              = $current_six_stay_fans;
				$result[$key]['seven_stay_fans']            = $current_seven_stay_fans;
			}
			$result[7] = [
				'date'                       => '平均留存率',
				'current_day_subscribe_fans' => '--',
				'current_day_stay_fans'      => $this->getPer($total_day, $total_current),
				'two_stay_fans'              => $this->getPer($total_two, $total_current),
				'three_stay_fans'            => $this->getPer($total_three, $total_three_current),
				'four_stay_fans'             => $this->getPer($total_four, $total_four_current),
				'five_stay_fans'             => $this->getPer($total_five, $total_five_current),
				'six_stay_fans'              => $this->getPer($total_six, $total_six_current),
				'seven_stay_fans'            => $this->getPer($total_seven, $total_seven_current),
			];

			return $result;
		}

		/**
		 * 获取留存粉丝数
		 *
		 * @return int
		 */
		private function getStayFans ($fansLine, $stay_fans, $date1, $date2)
		{
			foreach ($fansLine as $curr) {
				$cancel_fans = FansTimeLine::find()->where(['fans_id' => $curr['fans_id'], 'event' => 'unsubscribe'])->andFilterWhere(['between', 'event_time', $date1, $date2 . ' 23:59:59'])->orderBy(['event_time' => SORT_DESC])->limit(1);
				$cancel_fans = $cancel_fans->one();
				if (!empty($cancel_fans)) {
					$stime1 = strtotime($cancel_fans->event_time);
					$stime2 = strtotime($curr['event_time']);
					if($stime1>=$stime2){
						$stay_fans--;
					}
				}
			}

			return $stay_fans;
		}

		/**
		 * 获取百分比
		 *
		 * @return string
		 */
		private function getPer ($count1, $count2, $type = 0)
		{
			$num = '0.0%';
			if ($type == 1) {
				$num = '0.00%';
			}
			if ($count2 > 0) {
				$num = round($count1 / $count2, 3);
				if ($type == 1) {
					$num = sprintf("%.2f", $num * 100);
				}else{
					$num = sprintf("%.1f", $num * 100);
				}
				$num = $num . '%';
			}
			return $num;
		}

		/**
		 * @title         获取粉丝增长数据
		 * @param $type 1、按小时 2、按天 3、按周 4、按月
		 * @param $total_fans
		 * @param $author_id
		 * @param $s_date
		 * @param $e_date
		 * @param $s_week
		 *
		 */
		private function getFansIncreaseByType ($type, $total_fans, $author_id, $date1, $date2, $s_week)
		{
			$xData         = [];//X轴
			$newData       = [];//新增
			$cancelData    = [];//取消
			$new_increData = [];//净增
			$newFans       = 0; //新增粉丝
			$cancelFans    = 0; //取消粉丝
			$newIncre      = 0; //净增粉丝
			switch ($type) {
				case 1:
					//按小时
					$s_date  = $date1;
					$e_date  = $date2 . ' 23:59:59';
					$select1 = new Expression('DATE_FORMAT(ft.event_time, "%H") hour,count(ft.id) sum');
					$fans    = Fans::find()->alias('f');
					$fans    = $fans->leftJoin('{{%fans_time_line}} ft', '`f`.`id` = `ft`.`fans_id`');
					$fans    = $fans->andWhere(['f.author_id' => $author_id, 'ft.event' => 'subscribe']);
					$fans    = $fans->select($select1)->andFilterWhere(['between', 'ft.event_time', $s_date, $e_date])->groupBy('hour,ft.fans_id');
					$fans    = $fans->asArray()->all();

					$cancelFans = Fans::find()->alias('f');
					$cancelFans = $cancelFans->leftJoin('{{%fans_time_line}} ft', '`f`.`id` = `ft`.`fans_id`');
					$cancelFans = $cancelFans->andWhere(['f.author_id' => $author_id, 'ft.event' => 'unsubscribe']);
					$cancelFans = $cancelFans->select($select1)->andFilterWhere(['between', 'ft.event_time', $s_date, $e_date])->groupBy('hour,ft.fans_id');
					$cancelFans = $cancelFans->asArray()->all();

					$result = [];
					for ($i = 0; $i < 24; $i++) {
						$j = $i;
						if ($j < 10) {
							$j = '0' . $i;
						}
						$k = $j + 1;
						if ($k < 10) {
							$k = '0' . $k;
						}
						$result[$i] = [
							'hour'      => $j . ':00' . '-' . $k . ':00',
							'new'       => 0,
							'cancel'    => 0,
							'new_incre' => 0,
						];
					}
					foreach ($result as $k => $v) {
						$hour   = explode('-', $v['hour']);
						$h      = explode(':', $hour[0]);
						$new    = 0;
						$cancel = 0;
						if (!empty($fans)) {
							foreach ($fans as $vv) {
								if ($vv['hour'] == '00') {
									$hour = '01';
								} else {
									$hour = $vv['hour'];
								}
								if ($h[0] == $hour) {
									$new++;
								}

							}
						}
						if (!empty($cancelFans)) {
							foreach ($cancelFans as $vv) {
								if ($vv['hour'] == '00') {
									$vv['hour'] = 24;
								}
								if ($h[0] == $vv['hour']) {
									$cancel++;
								}
							}
						}
						$per = '0.0%';
						if ($total_fans > 0) {
							$per = $this->getPer($cancel, $cancel + $total_fans);
						}
						$result[$k]['new']       = $new;
						$result[$k]['cancel']    = $cancel;
						$result[$k]['new_incre'] = $new - $cancel;
						$result[$k]['per']       = $per;
						array_push($newData, intval($new));
						array_push($cancelData, intval($cancel));
						array_push($new_increData, intval($new - $cancel));
					}
					$xData = ['00:00-01:00', '01:00-02:00', '02:00-03:00', '03:00-04:00', '04:00-05:00', '05:00-06:00', '06:00-07:00', '07:00-08:00', '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00', '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00', '17:00-18:00', '18:00-19:00', '19:00-20:00', '20:00-21:00', '21:00-22:00', '22:00-23:00', '23:00-24:00'];
					break;
				case 2:
					//按天
					$data   = DateUtil::getDateFromRange($date1, $date2);
					$result = [];
					foreach ($data as $k => $v) {
						$fansData                = FansStatistic::getFansNum($author_id, 1, $v);
						$result[$k]['new']       = $fansData['new'];
						$result[$k]['cancel']    = $fansData['cancel'];
						$result[$k]['new_incre'] = $fansData['new_incre'];
						$result[$k]['per']       = $fansData['per'];
						$result[$k]['hour']      = $v;
						$newFans                 += $fansData['new'];
						$cancelFans              += $fansData['cancel'];
						$newIncre                += $fansData['new_incre'];
						array_push($newData, intval($fansData['new']));
						array_push($cancelData, intval($fansData['cancel']));
						array_push($new_increData, intval($fansData['new_incre']));
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
								$fansData                = FansStatistic::getFansNum($author_id, 2, $v);
								$result[$k]['new']       = $fansData['new'];
								$result[$k]['cancel']    = $fansData['cancel'];
								$result[$k]['new_incre'] = $fansData['new_incre'];
								$result[$k]['per']       = $fansData['per'];
								$result[$k]['hour']      = $v . '~' . $vv . '(' . $s_week . '周)';
								array_push($xData, $result[$k]['hour']);
								array_push($newData, intval($fansData['new']));
								array_push($cancelData, intval($fansData['cancel']));
								array_push($new_increData, intval($fansData['new_incre']));
								$newFans    += $fansData['new'];
								$cancelFans += $fansData['cancel'];
								$newIncre   += $fansData['new_incre'];
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
						$date_time               = explode('/', $v['time']);
						$date_time               = $date_time[0] . '-' . $date_time[1] . '-' . '01';
						$fansData                = FansStatistic::getFansNum($author_id, 3, $date_time);
						$result[$k]['new']       = $fansData['new'];
						$result[$k]['cancel']    = $fansData['cancel'];
						$result[$k]['new_incre'] = $fansData['new_incre'];
						$result[$k]['per']       = $fansData['per'];
						$result[$k]['hour']      = $v['time'];
						$newFans                 += $fansData['new'];
						$cancelFans              += $fansData['cancel'];
						$newIncre                += $fansData['new_incre'];
						array_push($newData, intval($fansData['new']));
						array_push($cancelData, intval($fansData['cancel']));
						array_push($new_increData, intval($fansData['new_incre']));
						array_push($xData, $v['time']);
					}

					break;

			}
			$info['newFans']    = $newFans;
			$info['cancelFans'] = $cancelFans;
			$info['newIncre']   = $newIncre;
			$info['data']       = $result;
			$info['xData']      = $xData;
			$seriesData         = [
				[
					'name'   => '净增长',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $new_increData,
				],
				[
					'name'   => '取消关注',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $cancelData,
				],
				[
					'name'   => '新增关注',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $newData,
				],
			];
			$info['seriesData'] = $seriesData;

			return $info;
		}

		/**
		 * 获取最近的12个月和每月的第一天和最后一天
		 *
		 * @return array
		 */
		private function getFansCount ($author_id, $s_date, $e_date, $total_fans)
		{
			$select1 = new Expression('count(ft.id) cc,ft.fans_id');
			$fans    = Fans::find()->alias('f');
			$fans    = $fans->leftJoin('{{%fans_time_line}} ft', '`f`.`id` = `ft`.`fans_id`');
			$fans    = $fans->andWhere(['f.author_id' => $author_id, 'ft.event' => 'subscribe']);
			$fans    = $fans->select($select1)->andFilterWhere(['between', 'ft.event_time', $s_date, $e_date])->groupBy('ft.fans_id');
			\Yii::error($fans->createCommand()->getRawSql(),'$fans');
			$fans    = $fans->count();

			$cancelFans = Fans::find()->alias('f');
			$cancelFans = $cancelFans->leftJoin('{{%fans_time_line}} ft', '`f`.`id` = `ft`.`fans_id`');
			$cancelFans = $cancelFans->andWhere(['f.author_id' => $author_id, 'ft.event' => 'unsubscribe']);
			$cancelFans = $cancelFans->select($select1)->andFilterWhere(['between', 'ft.event_time', $s_date, $e_date])->groupBy('ft.fans_id');
			\Yii::error($cancelFans->createCommand()->getRawSql(),'$fans');
			$cancelFans = $cancelFans->count();

			$per = '0.0%';
			if ($total_fans > 0) {
				$per = $this->getPer($cancelFans, $cancelFans + $total_fans);
			}
			$result['fans']       = $fans;
			$result['cancelFans'] = $cancelFans;
			$result['per']        = $per;

			return $result;
		}

		public function actionTest ()
		{
			$result = Fans::getIndexFansData('gh_a5a2b5c4f175');

			return $result;
		}
	}