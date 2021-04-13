<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/12/02
	 * Time: 09:59
	 */
	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\Fans;
	use app\models\FansTags;
	use app\models\FansTimeLine;
	use app\models\Scene;
	use app\models\WxAuthorize;
	use yii\web\MethodNotAllowedHttpException;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use app\util\DateUtil;
	use callmez\wechat\sdk\Wechat;

	class FansBehaviourController extends AuthBaseController
	{
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'fans-info'     => ['POST'],
						'fans-sub-info' => ['POST'],
						'fans-analysis' => ['POST'],
						'fans-remark'   => ['POST'],
						'fans-trail'    => ['POST'],
					],
				],
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-behaviour/
		 * @title           粉丝详细信息
		 * @description     粉丝详细信息
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-behaviour/fans-info
		 *
		 * @param id 必选 int 粉丝的id
		 *
		 * @return          {"error":0,"data":{"fans_id":436,"headerimg":"http://thirdwx.qlogo.cn/mmopen/uz4yibblmCLWYAIrWekJpjjI4YowZPtl4S0KPEzMO8GQzk6sl6QREkCsLGpmtdoIVpzH2loUupQQeE84ZLPCA7N9z8T3FqnWb/132","nickname":"王盼","interact_time":"6天前","openid":"oHPnN1WUPEkUzd0jUZM48gOshKMY","unionid":"oYBYj0v1Dfqzsi4MMuvqAfbmJgaQ","remark":"","sex":1,"province":"安徽","city":"合肥","subscribe_scene_str":"扫描二维码","subscribe_time":"2019-11-26 09:58:25","unsubscribe_time":"2019-11-26 09:59:38","last_time":"2019-11-26 09:59:38","tag_name":["王盼"],"is_show_sub":1,"is_show_unsub":1}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    fans_id string 粉丝id
		 * @return_param    headerimg string 头像
		 * @return_param    nickname string 昵称
		 * @return_param    interact_time string 互动时间
		 * @return_param    openid string openid
		 * @return_param    unionid string unionid
		 * @return_param    remark string 备注
		 * @return_param    sex string 1男2女0未知
		 * @return_param    province string 省
		 * @return_param    city string 市
		 * @return_param    subscribe_scene_str string 来源
		 * @return_param    subscribe_time string 关注时间
		 * @return_param    last_time string 上次活跃
		 * @return_param    follow_status int 跟进状态：0未跟进1跟进中2已拒绝3已成交
		 * @return_param    unsubscribe_time string 取关时间
		 * @return_param    tag_name array 标签
		 * @return_param    is_show_sub string 是否显示历史关注0不显示1显示
		 * @return_param    is_show_unsub string 是否显示历史取关0不显示1显示
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/2 11:49
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$id                          = \Yii::$app->request->post('id');
				if(empty($id)){
					throw new InvalidParameterException('参数不正确！');
				}
				$fans                        = Fans::findOne($id);
				$info['fans_id']             = $fans->id;
				$info['headerimg']           = $fans->headerimg;
				$info['nickname']            = $fans->nickname;
				$info['interact_time']       = $fans->last_time ? DateUtil::getDiffText($fans->last_time).'互动过' : '';
				$info['openid']              = $fans->openid;
				$info['unionid']             = $fans->unionid;
				$info['remark']              = $fans->remark;
				$info['sex']                 = $fans->sex;
				$info['province']            = $fans->province;
				$info['city']                = $fans->city;
				$info['subscribe_scene_str'] = $fans->subscribe_scene ? Fans::getSubscribeScene($fans->subscribe_scene) : "其他";
				$info['subscribe_time']      = $fans->subscribe_time ? date("Y-m-d H:i:s", $fans->subscribe_time) : '--';
				$info['unsubscribe_time']    = $fans->unsubscribe_time ? date("Y-m-d H:i:s", $fans->unsubscribe_time) : '--';
				$info['last_time']           = $fans->last_time ? date("Y-m-d H:i:s", $fans->last_time) : '--';
				$info['follow_status']       = $fans->follow_status ? $fans->follow_status : 0;
				if (!empty($fans->follow)) {
					$follow_id    = $fans->follow->id;
					$follow_title = $fans->follow->title;
					if ($fans->follow->status == 0) {
						$follow_id    = '';
						$follow_title .= '（已删除）';
					}
					$info['follow_id']    = $follow_id;
					$info['follow_title'] = $follow_title;
				} else {
					$info['follow_id']    = '';
					$info['follow_title'] = '';
				}
				$fansTags                    = FansTags::find()->alias('ft');
				$fansTags                    = $fansTags->leftJoin('{{%tags}} t', '`t`.`id` = `ft`.`tags_id`');
				$fansTags                    = $fansTags->andWhere(['ft.fans_id' => $id]);
				$fansTags                    = $fansTags->select('t.id,t.name');
				$fansTags                    = $fansTags->asArray()->all();
				$tag_name = [];
				if (!empty($fansTags)) {
					foreach ($fansTags as $key => $val) {
						$tag_name[$key]['key']  = $val['id'];
						$tag_name[$key]['name'] = $val['name'];
					}
				}
				$info['tag_name']      = $tag_name;
				$sub_count             = FansTimeLine::find()->where(['fans_id' => $id, 'event' => 'subscribe'])->count();
				$unsub_count           = FansTimeLine::find()->where(['fans_id' => $id, 'event' => 'unsubscribe'])->count();
				$info['is_show_sub']   = $sub_count ? 1 : 0;
				$info['is_show_unsub'] = $unsub_count ? 1 : 0;

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-behaviour/
		 * @title           关注和取关列表
		 * @description     关注和取关列表
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-behaviour/fans-sub-info
		 *
		 * @param id 必选 int 粉丝id
		 * @param type 必选 int 1关注2取关
		 *
		 * @return          {"error":0,"data":["2019-11-26 09:59:38","2019-11-26 09:58:14","2019-11-25 19:24:29","2019-11-25 16:52:50","2019-11-25 13:35:09","2019-11-22 16:06:26","2019-11-22 15:51:34","2019-11-22 15:06:42","2019-11-22 13:54:27"]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/2 13:31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansSubInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$id   = \Yii::$app->request->post('id');
				$type = \Yii::$app->request->post('type') ?: 1;
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($type == 1) {
					$subscribe = 'subscribe';
				} else {
					$subscribe = 'unsubscribe';
				}
				$fansTime = FansTimeLine::find()->where(['fans_id' => $id, 'event' => $subscribe])->orderBy(['event_time' => SORT_DESC])->all();
				$info     = [];
				if (!empty($fansTime)) {
					foreach ($fansTime as $key => $time) {
						array_push($info, $time->event_time);
					}
				}

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-behaviour/
		 * @title           指标分析
		 * @description     指标分析
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-behaviour/fans-analysis
		 *
		 * @param id 必选 int 粉丝id
		 * @param type 必选 int 1近7天2近15天3近30天
		 *
		 * @return          {"error":0,"data":{"total_interact":4,"total_message":0,"total_menu":0,"total_scan":4,"xData":["2019-11-18","2019-11-19","2019-11-20","2019-11-21","2019-11-22","2019-11-23","2019-11-24","2019-11-25","2019-11-26","2019-11-27","2019-11-28","2019-11-29","2019-11-30","2019-12-01","2019-12-02"],"legData":["总互动次数","总发送消息次数","总菜单点击次数","总扫码次数"],"seriesData":[{"name":"总互动次数","type":"line","smooth":true,"data":[0,0,0,1,2,0,0,0,1,0,0,0,0,0,0]},{"name":"总发送消息次数","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"总菜单点击次数","type":"line","smooth":true,"data":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]},{"name":"总扫码次数","type":"line","smooth":true,"data":[0,0,0,1,2,0,0,0,1,0,0,0,0,0,0]}],"list":{"1":{"date":"2019-11-18","message":0,"menu":0,"scan":0,"interact":0},"2":{"date":"2019-11-19","message":0,"menu":0,"scan":0,"interact":0},"3":{"date":"2019-11-20","message":0,"menu":0,"scan":0,"interact":0},"4":{"date":"2019-11-21","message":0,"menu":0,"scan":1,"interact":1},"5":{"date":"2019-11-22","message":0,"menu":0,"scan":2,"interact":2},"6":{"date":"2019-11-23","message":0,"menu":0,"scan":0,"interact":0},"7":{"date":"2019-11-24","message":0,"menu":0,"scan":0,"interact":0},"8":{"date":"2019-11-25","message":0,"menu":0,"scan":0,"interact":0},"9":{"date":"2019-11-26","message":0,"menu":0,"scan":1,"interact":1},"10":{"date":"2019-11-27","message":0,"menu":0,"scan":0,"interact":0},"11":{"date":"2019-11-28","message":0,"menu":0,"scan":0,"interact":0},"12":{"date":"2019-11-29","message":0,"menu":0,"scan":0,"interact":0},"13":{"date":"2019-11-30","message":0,"menu":0,"scan":0,"interact":0},"14":{"date":"2019-12-01","message":0,"menu":0,"scan":0,"interact":0},"15":{"date":"2019-12-02","message":0,"menu":0,"scan":0,"interact":0}}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    total_interact int 总互动数
		 * @return_param    total_message int 总消息数
		 * @return_param    total_menu int 总菜单数
		 * @return_param    total_scan int 总扫描数
		 * @return_param    xData array 日期
		 * @return_param    legData array legData
		 * @return_param    seriesData array seriesData
		 * @return_param    list array 底下列表数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/2 16:15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansAnalysis ()
		{
			if (\Yii::$app->request->isPost) {
				$id   = \Yii::$app->request->post('id');
				$type = \Yii::$app->request->post('type') ?: 1;
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($type == 1) {
					$data = DateUtil::get_weeks();
				} elseif ($type == 2) {
					$data = DateUtil::get_weeks('', 'Y-m-d', 15);
				} elseif ($type == 3) {
					$data = DateUtil::get_weeks('', 'Y-m-d', 30);
				} else {
					throw new InvalidParameterException('参数不正确！');
				}
				$fans           = Fans::findOne(['id' => $id]);
				$total_message  = $total_menu = $total_scan = 0;
				$legData        = ['总互动次数', '总发送消息次数', '总菜单点击次数', '总扫码次数'];
				$scan           = $interact = $message = $menu = [];
				$result         = [];
				$subscribe_time = $fans->subscribe_time;
				$fans_message   = $this->getInterActNum($id, $subscribe_time, 'image,text,link,voice,video,shortvideo,location');
				$fans_menu      = $this->getInterActNum($id, $subscribe_time, 'click,view');
				$fans_scan      = $this->getInterActNum($id, $subscribe_time, 'scan');
				if (!empty($fans_message)) {
					$total_message = intval($fans_message);
				}
				if (!empty($fans_menu)) {
					$total_menu = intval($fans_menu);
				}
				if (!empty($fans_scan)) {
					$total_scan = intval($fans_scan);
				}
				$total_interact = $total_message + $total_menu + $total_scan;
				$i              = 0;
				$xData = array_values($data);
				$data = array_reverse(array_values($data));
				if (!empty($data)) {
					foreach ($data as $k => $v) {
						$total        = $one = $two = $three = 0;
						$s_date       = $v;
						$e_date       = $v . ' 23:59:59';
						$fans_message = $this->getInterActNum($id, $subscribe_time, 'image,text,link,voice,video,shortvideo', 1, $s_date, $e_date);
						$fans_menu    = $this->getInterActNum($id, $subscribe_time, 'click,view', 1, $s_date, $e_date);
						$fans_scan    = $this->getInterActNum($id, $subscribe_time, 'scan', 1, $s_date, $e_date);
						if (!empty($fans_message)) {
							$one = intval($fans_message);
						}
						if (!empty($fans_menu)) {
							$two = intval($fans_menu);
						}
						if (!empty($fans_scan)) {
							$three = intval($fans_scan);
						}
						$total                  = $one + $two + $three;
						$result[$i]['date']     = $v;
						$result[$i]['message']  = $one;
						$result[$i]['menu']     = $two;
						$result[$i]['scan']     = $three;
						$result[$i]['interact'] = $total;
						$i++;
						array_push($scan, $three);
						array_push($message, $one);
						array_push($menu, $two);
						array_push($interact, $total);
					}
				}
				$seriesData             = [
					[
						'name'   => '总互动次数',
						'type'   => 'line',
						'smooth' => true,
						'data'   => array_reverse($interact),
					],
					[
						'name'   => '总发送消息次数',
						'type'   => 'line',
						'smooth' => true,
						'data'   => array_reverse($message),
					],
					[
						'name'   => '总菜单点击次数',
						'type'   => 'line',
						'smooth' => true,
						'data'   => array_reverse($menu),
					],
					[
						'name'   => '总扫码次数',
						'type'   => 'line',
						'smooth' => true,
						'data'   => array_reverse($scan),
					]
				];
				$info['total_interact'] = $total_interact;
				$info['total_message']  = $total_message;
				$info['total_menu']     = $total_menu;
				$info['total_scan']     = $total_scan;
				$info['xData']          = $xData;
				$info['legData']        = $legData;
				$info['seriesData']     = $seriesData;
				$info['list']           = $result;

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		//获取互动数据
		private static function getInterActNum ($id, $subscribe_time, $event, $type = 0, $s_date = '', $e_date = '')
		{
			$event        = explode(',', $event);
			$FansTimeLine = FansTimeLine::find()->where(['fans_id' => $id])
				->andWhere(['>=', 'event_time', date("Y-m-d H:i:s", $subscribe_time)])
				->andWhere(['source' => 0])
				->andWhere(['in', 'event', $event]);
			if ($type == 1) {
				$FansTimeLine = $FansTimeLine->andFilterWhere(['between', 'event_time', $s_date, $e_date]);
			}
			$count = $FansTimeLine->count();

			return $count;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-behaviour/
		 * @title           互动轨迹接口
		 * @description     互动轨迹接口
		 * @method   post
		 * @url  http://{host_name}/api/index/fans-behaviour/fans-trail
		 *
		 * @param fans_id 必选 int 粉丝id
		 *
		 * @return          {"error":0,"data":[{"event_time":"2019-12-04 14:53:00","icon":9,"content":"粉丝点击了菜单"},{"event_time":"2019-12-04 14:25:48","icon":5,"content":"粉丝向公众号发送了地理位置"},{"event_time":"2019-12-04 11:25:48","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-12-04 11:23:36","icon":2,"content":"粉丝取消关注了公众号"},{"event_time":"2019-12-04 11:23:25","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-12-04 11:23:11","icon":2,"content":"粉丝取消关注了公众号"},{"event_time":"2019-12-03 20:05:25","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-11-26 09:59:38","icon":2,"content":"粉丝取消关注了公众号"},{"event_time":"2019-11-26 09:58:25","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-11-26 09:58:14","icon":2,"content":"粉丝取消关注了公众号"},{"event_time":"2019-11-26 09:57:52","icon":9,"content":"粉丝扫描渠道二维码："},{"event_time":"2019-11-25 19:25:18","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-11-25 19:24:29","icon":2,"content":"粉丝取消关注了公众号"},{"event_time":"2019-11-25 16:53:04","icon":1,"content":"粉丝关注了公众号"},{"event_time":"2019-11-25 16:52:50","icon":2,"content":"粉丝取消关注了公众号"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    event_time string 时间
		 * @return_param    content string 内容
		 * @return_param    icon int 图标1关注2取消关注3打标签/移除标签4添加/修改备注5粉丝回复 文本、图片等6关注回复 文本、图片等7扫码回复 文本、图片等8收到消息回复  文本、图片等9粉丝扫码渠道二维码10粉丝点击菜单
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/4 16:04
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansTrail ()
		{
			if (\Yii::$app->request->isPost) {
				$fans_id = \Yii::$app->request->post('fans_id');
				if (empty($fans_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$fans     = Fans::findOne(['id' => $fans_id]);
				$wxAuthor = WxAuthorize::findOne(['author_id' => $fans->author_id]);
				//获取微信菜单
				$menu      = $this->getWxMenu($wxAuthor->authorizer_appid);
				$page      = \Yii::$app->request->post('page', 1);
				$pageSize  = \Yii::$app->request->post('pageSize', 15);
				$offset    = ($page - 1) * $pageSize;
				$fans_line = FansTimeLine::find()->where(['fans_id' => $fans_id])->limit($pageSize)->offset($offset)->orderBy(['event_time' => SORT_DESC]);
				$fans_line = $fans_line->all();
				$info      = [];
				if (!empty($fans_line)) {
					foreach ($fans_line as $key => $val) {
						$content                  = "";
						$icon                     = '5';
						$info[$key]['event_time'] = $val->event_time;
						if ($val->event == "subscribe") {
							$icon    = 4;
							$content = "粉丝关注公众号";
						} elseif ($val->event == "unsubscribe") {
							$icon    = 4;
							$content = "粉丝取消关注公众号";
						} elseif ($val->event == "view_miniprogram") {
							$icon    = 12;
							$content = "粉丝点击了小程序";
							$content = $content . "，跳转到【" . $val->remark . '】';
						} elseif ($val->event == "give_tag") {
							$icon = 1;
							if (!empty($val->scene_id)) {
								$scene = Scene::findOne([$val->scene_id]);;
								if (!empty($scene)) {
									$content = "粉丝扫描渠道二维码【" . $scene->title . "】后，自动给该粉丝打上标签【" . $val->remark . '】';
								}
							} else {
								$content = "系统给该粉丝添加标签【" . $val->remark . '】';
							}
						} elseif ($val->event == "remove_tag") {
							$icon = 2;
							if (!empty($val->remark)) {
								$content = "系统对该粉丝移除标签【" . $val->remark . '】';
							} else {
								$content = "系统对该粉丝移除标签";
							}

						} elseif ($val->event == "add_remark" || $val->event == "modify_remark") {
							$icon    = 10;
							$content = "将粉丝备注为【" . $val->remark . '】';
						} elseif ($val->event == "remove_remark") {
							$icon    = 10;
							$content = "将粉丝备注为空";
						} elseif ($val->event == "modify_field") {
							$icon    = 10;
							$content = '完善粉丝信息';
							if ($val->remark){
								$content .= '：' . $val->remark;
							}
						} elseif ($val->event == "text") {
							if ($val->source == 0) {
								$icon = 8;
								if (strpos($val->remark, '收到不支持的消息类型') !== false) {
									$remark = $val->remark;
								} else {
									$remark = '【' . $val->remark . '】';
								}
								$content = "粉丝向公众号发送文本" . $remark;
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复了文本消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复文本消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复文本消息";
							}

						} elseif ($val->event == "image") {
							if ($val->source == 0) {
								$icon    = 8;
								$content = "粉丝向公众号发送图片";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复了图片消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复图片消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复图片消息";
							}
						} elseif ($val->event == "link") {
							if ($val->source == 0) {
								$icon    = 8;
								$content = "粉丝向公众号发送链接";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复链接消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复链接消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复链接消息";
							}
						} elseif ($val->event == "voice") {
							if ($val->source == 0) {
								$icon    = 8;
								$content = "粉丝向公众号发送音频";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复音频消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复音频消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复音频消息";
							}
						} elseif ($val->event == "video") {
							if ($val->source == 0) {
								$icon    = 8;
								$content = "粉丝向公众号发送视频";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复视频消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复视频消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复视频消息";
							}
						} elseif ($val->event == "shortvideo") {
							if ($val->source == 0) {
								$icon    = 8;
								$content = "粉丝向公众号发送小视频";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复了小视频消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复小视频消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复小视频消息";
							}
						} elseif ($val->event == "location") {
							if ($val->source == 0) {
								$icon    = 3;
								$content = "粉丝向公众号发送地理位置";
							} elseif ($val->source == 1) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝关注公众号，系统" . $str . "回复了地理位置消息";
							} elseif ($val->source == 2) {
								$icon    = 11;
								$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复地理位置消息";
							} elseif ($val->source == 3) {
								$icon = 11;
								$str  = '';
								if (!empty($val->remark)) {
									$str = $this->getTime($val->remark);
								}
								$content = "粉丝发送消息，系统" . $str . "回复地理位置消息";
							}
						} elseif ($val->event == "scan") {
							$icon   = 6;
							$remark = $val->remark;
							if (!empty($val->scene_id)) {
								$scene  = Scene::findOne([$val->scene_id]);
								if (!empty($scene)) {
									$remark = $scene->title;
								}
							}
							if (!empty($val->remark) && empty($val->scene_id)) {
								$scene = Scene::findOne(['scene_id' => $val->remark, 'author_id' => $fans->author_id]);
								if (!empty($scene)) {
									$remark = $scene->title;
								}
							}
							$content = "粉丝扫描渠道二维码【" . $remark . '】';
						} elseif ($val->event == "click") {
							$icon    = 10;
							$content = "粉丝点击了菜单";
							if (!empty($val->remark) && !empty($menu)) {
								$name = '';
								foreach ($menu as $me) {
									if ($me['value'] == $val->remark) {
										$name = $me['name'];
									}
								}
								$content = "粉丝点击了菜单【" . $name . '】';
								$content = $content . "，系统推送了【" . $val->remark . '】';
							}
						} elseif ($val->event == "view") {
							$icon    = 12;
							$content = "粉丝点击了菜单";
							if (!empty($val->remark) && !empty($menu)) {
								$name = '';
								foreach ($menu as $me) {
									if ($me['value'] == $val->remark) {
										$name = $me['name'];
									}
								}
								$content = "粉丝点击了菜单【" . $name . '】';
								$content = $content . "，跳转到【" . $val->remark . '】';
							}
						} elseif ($val->event == "group") {
							$icon = 11;
							$str  = '';
							if ($val->remark == 'mpnews') {
								$str = '【图文】';
							} elseif ($val->remark == 'mpvideo' || $val->remark == 'video') {
								$str = '【视频】';
							} elseif ($val->remark == 'image') {
								$str = '【图片】';
							} elseif ($val->remark == 'voice') {
								$str = '【音频】';
							} elseif ($val->remark == 'text') {
								$str = '【文本】';
							}
							$content = "系统对该粉丝发送群发消息" . $str;
						} elseif ($val->event == "news" && $val->source == 1) {
							$icon = 11;
							$str  = '';
							if (!empty($val->remark)) {
								$str = $this->getTime($val->remark);
							}
							$content = "粉丝关注公众号，系统" . $str . "回复图文消息";
						} elseif ($val->event == "news" && $val->source == 2) {
							$icon    = 11;
							$content = "粉丝扫描渠道二维码【" . $val->remark . "】，系统立即回复图文消息";
						} elseif ($val->event == "news" && $val->source == 3) {
							$icon = 11;
							$str  = '';
							if (!empty($val->remark)) {
								$str = $this->getTime($val->remark);
							}
							$content = "粉丝发送消息，系统" . $str . "回复图文消息";
						} elseif ($val->event == "template") {
							$icon    = 11;
							$content = "系统对该粉丝发送模板消息【" . $val->remark . "】";
						} elseif ($val->event == "kefu") {
							$icon = 11;
							$str  = '';
							if ($val->remark == 5) {
								$str = '【图文】';
							} elseif ($val->remark == 4) {
								$str = '【视频】';
							} elseif ($val->remark == 2) {
								$str = '【图片】';
							} elseif ($val->remark == 3) {
								$str = '【音频】';
							} elseif ($val->remark == 1) {
								$str = '【文本】';
							}
							$content = "系统对该粉丝发送客服消息" . $str;
						} elseif ($val->event == 'follow') {
							$icon    = 11;
							$content = $val->remark;
						} elseif ($val->event == 'custom') {
							$icon    = 11;
							$content = $val->remark;
						}
						$info[$key]['icon']    = $icon;
						$info[$key]['content'] = $content;
					}
				}

				return $info;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		private static function getTime ($remark)
		{
			$str = '';
			if (!empty($remark)) {
				$time = json_decode($remark, true);
				if ($time[0] == 0 && $time[1] == 0) {
					$str = '立即';
				} elseif ($time[0] != 0 && $time[1] == 0) {
					$str = $time[0] . '小时后';
				} elseif ($time[0] == 0 && $time[1] != 0) {
					$str = $time[1] . '分钟后';
				} elseif ($time[0] != 0 && $time[1] != 0) {
					$str = $time[0] . '小时，' . $time[1] . '分钟后';
				}
			}

			return $str;
		}

		//获取微信自定义菜单
		private function getWxMenu ($appid)
		{
			$menu        = [];
			$wxAuthorize = WxAuthorize::getTokenInfo($appid, false, true);
			if (!empty($wxAuthorize)) {
				$wechat = \Yii::createObject([
					'class'          => Wechat::className(),
					'appId'          => $appid,
					'appSecret'      => $wxAuthorize['config']->appSecret,
					'token'          => $wxAuthorize['config']->token,
					'componentAppId' => $wxAuthorize['config']->appid,
				]);
			} else {
				return $menu;
			}
			$wxMenu = $wechat->getWxMenu();
			if (!empty($wxMenu)) {
				$i      = 0;
				$button = $wxMenu['selfmenu_info']['button'];
				if (!empty($button)) {
					foreach ($button as $val) {
						if (isset($val['sub_button']) && !empty($val['sub_button'])) {
							$sub_button = $val['sub_button'];
							foreach ($sub_button['list'] as $sub) {
								if ($sub['type'] == 'view') {
									$menu[$i]['name']  = $sub['name'];
									$menu[$i]['value'] = $sub['url'];
									$i++;
								}
								if ($sub['type'] == 'click') {
									$menu[$i]['name']  = $sub['name'];
									$menu[$i]['value'] = $sub['key'];
									$i++;
								}

							}
						} else {
							if ($val['type'] == 'view') {
								$menu[$i]['name']  = $val['name'];
								$menu[$i]['value'] = $val['url'];
								$i++;
							}
							if ($val['type'] == 'click') {
								$menu[$i]['name']  = $val['name'];
								$menu[$i]['value'] = $val['key'];
								$i++;
							}
						}
					}
				}

			}

			return $menu;
		}


	}