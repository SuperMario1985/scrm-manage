<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/3/16
	 * Time: 13:15
	 */

	namespace app\modules\api\controllers;

	use app\models\AwardsJoin;
	use app\models\AwardsJoinDetail;
	use app\models\RedPackOrder;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncAwardJob;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidParameterException;
	use app\components\InvalidDataException;
	use app\models\AwardsActivity;
	use app\models\AwardsList;
	use app\models\WorkFollowUser;
	use app\models\WorkUser;
	use app\models\AwardsRecords;
	use yii\helpers\ArrayHelper;
	use yii\filters\VerbFilter;
	use app\util\SUtils;

	class AwardsActivityController extends WorkBaseController
	{

		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'list' => ['POST'],
					],
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           抽奖活动列表
		 * @description     抽奖活动列表
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/list
		 *
		 * @param uid 必选 int 当前登录账号id
		 * @param page 可选 int 页码
		 * @param pageSize 可选 int 页数
		 * @param status 必选 int 1、未开启2、进行中3、已结束
		 * @param title 可选 string 活动标题
		 * @param corp_id 必选 string 企业授权id
		 * @param date 可选 array 日期
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 活动id
		 * @return_param    title string 活动名称
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    part_num int 参与人数
		 * @return_param    visitor_num int 参与人数
		 * @return_param    status int 状态：0 未开启 1 进行中 2 已结束
		 * @return_param    create_time string 创建时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/25 11:54
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				$uid      = \Yii::$app->request->post('uid');
				$status   = \Yii::$app->request->post('status');
				$title    = \Yii::$app->request->post('title');
				$date     = \Yii::$app->request->post('date');
				if (empty($uid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
//				//更改过期
//				$date_now    = date('Y-m-d H:i:s');
//				$allActivity = AwardsActivity::find()->where(['uid' => $uid, 'corp_id' => $this->corp->id, 'status' => [0, 1]])->andWhere(['<=', 'end_time', $date_now])->all();
//				if (!empty($allActivity)) {
//					foreach ($allActivity as $act) {
//						$act->status = 2;
//						$act->save();
//						try {
//							AwardsActivity::deleteConfigId($act->id);
//						} catch (\Exception $e) {
//							\Yii::error($e->getMessage(), 'awardListMsg');
//						}
//					}
//				}

				$activity = AwardsActivity::find()->andWhere(['uid' => $uid, 'is_del' => 0, 'corp_id' => $this->corp['id']]);
				if (!empty($status)) {
					if ($status == 3) {
						$activity = $activity->andWhere(['in', 'status', [2, 3, 4]]);
					} elseif ($status == 2) {
						$activity = $activity->andWhere(['status' => 1]);
					} else {
						$activity = $activity->andWhere(['status' => 0]);
					}
				}
				if (!empty($title)) {
					$activity = $activity->andWhere(['or', ['like', 'title', $title]]);
				}
				if (!empty($date)) {
					$start_date = $date[0] . ' 00:00:00';
					$end_date   = $date[1] . ' 23:59:59';
					$activity   = $activity->andWhere(['and', ['or', ['and', ['<=', 'start_time', $start_date], ['>=', 'end_time', $start_date]], ['and', ['>=', 'start_time', $start_date], ['<=', 'end_time', $end_date]], ['and', ['<=', 'start_time', $end_date], ['>=', 'end_time', $end_date]]]]);
				}
				$offset   = ($page - 1) * $pageSize;
				$count    = $activity->count();
				$info     = [];
				$activity = $activity->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
				if (!empty($activity)) {
					foreach ($activity as $activityData) {
						$time1 = strtotime($activityData->end_time);
						if (time() > $time1 && $activityData->status!=2) {
							$activityData->status = 2;
							$activityData->save();
							\Yii::$app->queue->push(new SyncAwardJob([
								'award_id'     => $activityData->id,
								'award_status' => 2
							]));
						}
						$data = $activityData->dumpData();
						array_push($info, $data);
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
		 * @catalog         数据接口/api/awards-activity/
		 * @title           活动删除
		 * @description     活动删除
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/delete
		 *
		 * @param id 必选 int 活动id
		 *
		 * @return bool
		 *
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$activity = AwardsActivity::findOne($id);
				if (!empty($activity)) {
					$activity->is_del = 1;
					$activity->save();
					try {
						AwardsActivity::deleteConfigId($id);
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), 'awardListMsg');
					}
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           活动详情
		 * @description     活动详情
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/detail
		 *
		 * @param id 必选 int 活动id
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/20 9:11
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
				$activity = AwardsActivity::findOne($id);
				$result   = [];
				if (!empty($activity)) {
					$activityData = $activity->dumpData();
					$result = $activityData;
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * @catalog         数据接口/api/awards-activity/
		 * @title           抽奖活动添加
		 * @description     抽奖活动添加
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/add
		 *
		 * @param corp_id 必选 int 当前账户id
		 * @param uid 必选 int 当前账户id
		 * @param id 可选 int 修改时传
		 * @param agent_id 必选 int 应用id
		 * @param title 必选 string 活动名称
		 * @param start_time 必选 string 开始时间
		 * @param end_time 必选 string 结束时间
		 * @param description 可选 string 活动说明
		 * @param style 必选 int 样式：1、梦幻紫2、喜庆红
		 * @param poster_path 必选 string 海报地址
		 * @param share_title 必选 string 分享标题
		 * @param apply_setting 必选 array 参与设置
		 * @param award_setting 必选 array 中奖设置
		 * @param share_setting 必选 array 分享设置
		 * @param content 必选 array 奖项设置
		 * @param user 必选 array 成员
		 * @param text_content 可选 string 文本
		 * @param link_start_title 可选 string 标题
		 * @param link_desc 可选 string 描述
		 * @param link_pic_url 可选 string 图片
		 * @param link_pic_url 可选 string 图片
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/24 20:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\db\Exception
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$uid              = \Yii::$app->request->post('uid');
				$id               = \Yii::$app->request->post('id') ?: 0;
				$title            = \Yii::$app->request->post('title');
				$agent_id         = \Yii::$app->request->post('agent_id');
				$start_time       = \Yii::$app->request->post('start_time');
				$end_time         = \Yii::$app->request->post('end_time');
				$description      = \Yii::$app->request->post('description');
				$style            = \Yii::$app->request->post('style') ?: 1;
				$poster_path      = \Yii::$app->request->post('poster_path');
				$share_title      = \Yii::$app->request->post('share_title');
				$apply_setting    = \Yii::$app->request->post('apply_setting');
				$award_setting    = \Yii::$app->request->post('award_setting');
				$share_setting    = \Yii::$app->request->post('share_setting');
				$content          = \Yii::$app->request->post('content');
				$user_key         = \Yii::$app->request->post('user') ?: '';
				$text_content     = \Yii::$app->request->post('text_content');
				$link_start_title = \Yii::$app->request->post('link_start_title');
				$link_end_title   = \Yii::$app->request->post('link_end_title');
				$link_desc        = \Yii::$app->request->post('link_desc');
				$link_pic_url     = \Yii::$app->request->post('link_pic_url');
				$init_num         = \Yii::$app->request->post('init_num') ?: 1;
				$prize_send_type  = \Yii::$app->request->post('prize_send_type') ?: 1;
				$sex_type         = \Yii::$app->request->post('sex_type') ?: 1;
				$area_type        = \Yii::$app->request->post('area_type') ?: 1;
				$area_data        = \Yii::$app->request->post('area_data') ?: [];
				$tag_ids        = \Yii::$app->request->post('tag_ids') ?: '';

				//基础设置
				$back_pic_url = \Yii::$app->request->post('back_pic_url');
				$is_avatar    = \Yii::$app->request->post('is_avatar');
				$avatar       = \Yii::$app->request->post('avatar');
				$shape        = \Yii::$app->request->post('shape');
				$is_nickname  = \Yii::$app->request->post('is_nickname');
				$nickName     = \Yii::$app->request->post('nickName');
				$qrCode       = \Yii::$app->request->post('qrCode');
				$color        = \Yii::$app->request->post('color');
				$font_size    = \Yii::$app->request->post('font_size');
				$align        = \Yii::$app->request->post('align');
				$isShareOpen  = \Yii::$app->request->post('is_share_open');


				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($uid)) {
					throw new InvalidParameterException('参数不正确');
				}
				if (empty($title)) {
					throw new InvalidParameterException('活动名称不能为空');
				}
				if (!empty($title) && mb_strlen($title, 'utf-8') > 20) {
					throw new InvalidDataException("活动名称不能超过20个字");
				}
				if (empty($start_time) || empty($end_time)) {
					throw new InvalidParameterException('活动时间不能为空');
				}
				if (strtotime($start_time) > strtotime($end_time)) {
					throw new InvalidDataException("开始时间不能大于结束时间");
				}
//				if (empty($poster_path)) {
//					throw new InvalidDataException("活动海报不能为空");
//				}
//				if (empty($share_title)) {
//					throw new InvalidDataException("分享标题不能为空");
//				}
				if (!empty($description) && mb_strlen($description, 'utf-8') > 100) {
					throw new InvalidDataException("活动说明不能超过100个字");
				}
				if (!empty($share_title) && mb_strlen($share_title, 'utf-8') > 30) {
					throw new InvalidDataException("分享标题不能超过30个字");
				}
				if (empty($apply_setting)) {
					throw new InvalidDataException("参与设置不能为空");
				}
				if (empty($award_setting)) {
					throw new InvalidDataException("中奖设置不能为空");
				}
				if (!empty($isShareOpen) && empty($share_setting)) {
					throw new InvalidDataException("分享设置不能为空");
				}
				if (empty($content)) {
					throw new InvalidDataException("奖项不能为空");
				}else{
					//判断
					foreach ($content as $con) {
						if (!empty($con['prize_type'])) {
							if ($con['amount'] < 0.3 || $con['amount'] > 5000) {
								throw new InvalidDataException("红包金额只能在0.3到5000之间");
							}
						}
					}
				}
				if (!empty($id)) {
					$awardTitle = AwardsActivity::find()->andWhere(['title' => $title, 'uid' => $uid, 'is_del' => 0])->andWhere(['!=', 'id', $id])->one();
					if (!empty($awardTitle)) {
						throw new InvalidDataException("活动名称已存在");
					}
				} else {
					$awardTitle = AwardsActivity::findOne(['title' => $title, 'uid' => $uid, 'is_del' => 0]);
					if (!empty($awardTitle)) {
						throw new InvalidDataException("活动名称已存在");
					}
				}

				if (empty($back_pic_url)) {
					//throw new InvalidDataException('请选择海报图片');
					$back_pic_url = '/static/image/raffle/raffle.png';
				}
				if (!empty($is_nickname)) {
					if (empty($shape)) {
						throw new InvalidDataException('请选择头像类型');
					}
				}
				if (!empty($is_avatar)) {
					if (empty($color)) {
						throw new InvalidDataException('请选择昵称颜色');
					}
					if (empty($font_size)) {
						throw new InvalidDataException('请选择昵称大小');
					}
				}

				if (empty($user_key)) {
					throw new InvalidDataException('请选择引流成员');
				}

				if (empty($link_start_title)) {
					throw new InvalidDataException('请填写欢迎语标题');
				}

				if (empty($link_pic_url)) {
					throw new InvalidDataException('请选择欢迎语图片');
				}

				$userId = [];
				if (!empty($user_key)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_key);
					$userIds = WorkDepartment::GiveDepartmentReturnUserData($this->corp['id'], $Temp["department"], $Temp["user"], 1, true,0);
					if(!empty($userIds)){
						foreach ($userIds as $usId) {
							$workUser = WorkUser::findOne($usId);
							if (!empty($workUser) && $workUser->corp_id == $this->corp['id']) {
								array_push($userId, $workUser->userid);
							}
						}
					}
				}
				if (empty($userId)) {
					throw new InvalidDataException('请选择引流成员');
				} elseif (count($userId) > 100) {
					throw new InvalidDataException('引流成员最多只能选择100个');
				}
				if (intval($init_num) > 99999999) {
					throw new InvalidDataException('初始次数不能超过99999999次');
				}
				if (!empty($apply_setting[0]['limit'])) {
					if (intval($apply_setting[0]['total_num']) > 99999999) {
						throw new InvalidDataException('总参与次数不能超过99999999次');
					}
				}
				if (intval($apply_setting[1]['day_num']) > 99999999) {
					throw new InvalidDataException('日参与次数不能超过99999999次');
				}
				if (!empty($award_setting[0]['limit'])) {
					if (intval($award_setting[0]['total_num']) > 99999999) {
						throw new InvalidDataException('总中奖次数不能超过99999999次');
					}
				}
				if (!empty($award_setting[1]['limit'])) {
					if (intval($award_setting[1]['day_num']) > 99999999) {
						throw new InvalidDataException('日中奖次数不能超过99999999次');
					}
				}
				if (!empty($isShareOpen) && intval($share_setting[0]['total_num']) > 99999999) {
					throw new InvalidDataException('分享1次增加次数不能超过99999999次');
				}
				if (!empty($isShareOpen) && !empty($share_setting[1]['limit'])) {
					if (intval($share_setting[1]['day_num']) > 99999999) {
						throw new InvalidDataException('日分享获得最大次数不能超过99999999次');
					}
				}

				$data['id']               = $id;
				$data['uid']              = $uid;
				$data['corp_id']          = $this->corp['id'];
				$data['agent_id']         = $agent_id;
				$data['title']            = $title;
				$data['start_time']       = $start_time;
				$data['end_time']         = $end_time;
				$data['description']      = $description;
				$data['style']            = $style;
				$data['poster_path']      = $poster_path;
				$data['share_title']      = $share_title;
				$data['apply_setting']    = $apply_setting;
				$data['award_setting']    = $award_setting;
				$data['share_setting']    = $share_setting;
				$data['is_share_open']    = !empty($isShareOpen);
				$data['content']          = $content;
				$data['text_content']     = !empty($text_content) ? trim($text_content) : '';
				$data['link_start_title'] = !empty($link_start_title) ? trim($link_start_title) : '';
				$data['link_end_title']   = !empty($link_end_title) ? trim($link_end_title) : '';
				$data['link_desc']        = !empty($link_desc) ? trim($link_desc) : '';
				$data['link_pic_url']     = $link_pic_url;
				$data['userId']           = $userId;
				$data['user_key']         = $user_key;
				$data['init_num']         = $init_num;
				$data['back_pic_url']     = !empty($back_pic_url) ? trim($back_pic_url) : '';
				$data['is_avatar']        = !empty($is_avatar) ? intval($is_avatar) : '';
				$data['avatar']           = !empty($avatar) ? $avatar : [];
				$data['nickName']         = !empty($nickName) ? $nickName : [];
				$data['qrCode']           = !empty($qrCode) ? $qrCode : [];
				$data['shape']            = !empty($shape) ? $shape : '';
				$data['color']            = !empty($color) ? $color : '';
				$data['align']            = !empty($align) ? $align : '';
				$data['font_size']        = !empty($font_size) ? $font_size : '';
				$data['is_nickname']      = !empty($is_nickname) ? intval($is_nickname) : '';
				$data['prize_send_type']  = $prize_send_type;
				$data['sex_type']         = $sex_type;
				$data['area_type']        = $area_type;
				$data['area_data']        = ($area_type == 2) ? $area_data : [];
				$data['tag_ids']          = $tag_ids;
				AwardsActivity::add($data);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           获奖名单列表
		 * @description     获奖名单列表
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/records
		 *
		 * @param award_id  必选 int 活动id
		 * @param uid 必选 int 当前登录账号id
		 * @param join_id 可选 int 参与者id
		 * @param page 可选 int 页码
		 * @param nick_name  可选 string 昵称
		 * @param pageSize 可选 int 页数
		 * @param is_record 必选 int 0、未中奖1、已中奖
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    nick_name string 昵称
		 * @return_param    phone string 手机号
		 * @return_param    avatar string 头像
		 * @return_param    award_name string 奖品名称
		 * @return_param    status int 0、未核销1、已核销
		 * @return_param    create_time string 参与时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/18 11:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionRecords ()
		{
			if (\Yii::$app->request->isPost) {
				$award_id = \Yii::$app->request->post('award_id') ?: 0;
				$join_id  = \Yii::$app->request->post('join_id') ?: 0;
				$uid      = \Yii::$app->request->post('uid') ?: 0;
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				$nickName = \Yii::$app->request->post('nick_name');
				if (empty($uid) || empty($award_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$records = AwardsRecords::find()->alias('ads');
				$records = $records->leftJoin('{{%awards_activity}} ac', '`ac`.`id` = `ads`.`award_id`');
				$records = $records->andWhere(['ac.uid' => $uid, 'ads.award_id' => $award_id]);
				if (!empty($nickName)) {
					$records = $records->andWhere(['or', ['like', 'ads.nick_name', $nickName], ['like', 'ads.nick_name', urlencode($nickName)]]);
				}
				if (!empty($join_id)) {
					$records = $records->andWhere(['ads.join_id' => $join_id]);
				} else {
					$records = $records->andWhere(['ads.is_record' => 1]);
				}
				$count   = $records->count();
				$offset  = ($page - 1) * $pageSize;
				$info    = [];
				$idList  = $records->all();
				$records = $records->limit($pageSize)->offset($offset)->orderBy(['ads.id' => SORT_DESC])->all();
				if (!empty($records)) {
					foreach ($records as $recordData) {
						$data = $recordData->dumpData();
						array_push($info, $data);
					}
				}
				//获取所有的key
				$keys = [];
				if (!empty($idList)) {
					foreach ($idList as $idInfo) {
						if($idInfo['status'] == 0){
							array_push($keys, intval($idInfo['id']));
						}
					}
				}

				return [
					'count' => $count,
					'info'  => $info,
					'keys'  => $keys,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           更新核销状态
		 * @description     更新核销状态
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/update-status
		 *
		 * @param award_id 必选 int 活动id
		 * @param id 必选 int id可以传整形或数组
		 *
		 * @return bool
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUpdateStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$award_id       = \Yii::$app->request->post('award_id', 0);
				$id             = \Yii::$app->request->post('id');
				$awardsActivity = AwardsActivity::findOne($award_id);
				if (empty($id) || empty($awardsActivity)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($awardsActivity->prize_send_type == 2 && $awardsActivity->status == 1) {
					throw new InvalidDataException('活动结束后才能核销！');
				}

				$recordList = AwardsRecords::find()->where(['id' => $id])->all();
				if (empty($recordList)) {
					throw new InvalidDataException('请选择获奖人员！');
				}
				$count   = $success = 0;
				$err_msg = '';
				foreach ($recordList as $record) {
					$count++;
					/**@var AwardsRecords $record * */
					$awardsList = AwardsList::findOne($record->aid);
					if (empty($awardsList->prize_type)) {
						$record->status = 1;
						$record->save();
						$success++;
					} else {
						$awardJoin = AwardsJoin::findOne($record->join_id);
						try {
							$amount   = $awardsList->amount;
							$remark   = '恭喜中奖，' . $amount . '元红包拿走，不谢~~~';
							$sendData = [
								'uid'         => $awardsActivity->uid,
								'corp_id'     => $awardsActivity->corp_id,
								'rid'         => $awardsActivity->id,
								'jid'         => $record->join_id,
								'hid'         => $record->id,
								'external_id' => $awardJoin->external_id,
								'openid'      => $awardJoin->openid,
								'amount'      => $amount,
								'remark'      => $remark,
							];
							$res      = RedPackOrder::sendRedPack($sendData, 3);
							if (!empty($res)) {
								$record->status = 1;
								$record->update();
								$success++;
								$is_send = 1;
							}
						} catch (InvalidDataException $e) {
							$err_msg = $e->getMessage();
						}
					}
				}

				$textHtml = '发放' . $count . '人';
				if ($count == $success) {
					$textHtml .= '，已全部发放';
				} else {
					if (!empty($success)) {
						$textHtml .= '，已成功发放' . $success . '人';
					}
					$diff = $count - $success;
					if (!empty($err_msg)) {
						$err_msg  = trim($err_msg, '！');
						$textHtml .= '，' . $err_msg . '，导致' . $diff . '人发放失败';
					}
				}
				if (empty($err_msg) && !empty($is_send)) {
					\Yii::$app->queue->delay(10)->push(new SyncAwardJob([
						'fission_id' => $awardsActivity->id,
						'sendData'   => ['is_all' => 1, 'uid' => $awardsActivity->uid]
					]));
				}

				return ['textHtml' => $textHtml, 'success' => $success];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           发布/使失效接口
		 * @description     发布/使失效接口
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/update-award-status
		 *
		 * @param id 必选 int id
		 * @param status 必选 int 1发布2使失效
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/25 11:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionUpdateAwardStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$id     = \Yii::$app->request->post('id');
				$status = \Yii::$app->request->post('status');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($status != 1 && $status != 2) {
					throw new InvalidParameterException('参数不正确！');
				}
				$award = AwardsActivity::findOne($id);
				if ($status == 1) {
					$time1 = strtotime($award->start_time);
					if (time() < $time1) {
						throw new InvalidParameterException('活动尚未开始，不可发布！');
					}
				}
				if ($status == 2) {
					$status = 4;
				}
				$award->status = $status;
				$award->save();
				if ($status == 4) {
					\Yii::$app->queue->push(new SyncAwardJob([
						'award_id'     => $award->id,
						'award_status' => 4
					]));
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/awards-activity/
		 * @title           玩家列表
		 * @description     玩家列表
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/awards-join
		 *
		 * @param id 必选 int 活动id
		 * @param page 可选 int 页码
		 * @param pageSize 可选 int 页数
		 * @param nick_name  可选 string 昵称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    nick_name string 昵称
		 * @return_param    avatar string 头像
		 * @return_param    has_num string 中奖次数
		 * @return_param    total_num string 抽奖次数
		 * @return_param    last_time string 最后一次抽奖时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/27 20:09
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAwardsJoin ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				$nickName = \Yii::$app->request->post('nick_name');
				$join     = AwardsJoin::find()->where(['award_id' => $id]);
				if (!empty($nickName)) {
					$join = $join->andWhere(['or', ['like', 'nick_name', $nickName], ['like', 'nick_name', urlencode($nickName)]]);
				}
				$count  = $join->count();
				$offset = ($page - 1) * $pageSize;
				$info   = [];
				$join   = $join->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
				if (!empty($join)) {
					foreach ($join as $key => $value) {
						$awardCount              = AwardsRecords::find()->where(['award_id' => $id, 'join_id' => $value->id])->count();
						$recordCount             = AwardsRecords::find()->where(['award_id' => $id, 'join_id' => $value->id, 'is_record' => 1])->count();
						$info[$key]['nick_name'] = urldecode($value->nick_name);
						$info[$key]['avatar']    = urldecode($value->avatar);
						$info[$key]['award_id']  = $value->award_id;
						$info[$key]['key']       = $value->id;
						$info[$key]['has_num']   = $recordCount;
						$info[$key]['total_num'] = $awardCount;
						$info[$key]['last_time'] = !empty($value->last_time) && $value->last_time != '0000-00-00 00:00:00' ? $value->last_time : '--';
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
		 * @catalog         数据接口/api/awards-activity/
		 * @title           助力接口
		 * @description     助力接口
		 * @method   post
		 * @url  http://{host_name}/api/awards-activity/help
		 *
		 * @param id 必选 int 参与人id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/27 20:53
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionHelp ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				$join     = AwardsJoinDetail::find()->where(['awards_join_id' => $id]);
				$count    = $join->count();
				$offset   = ($page - 1) * $pageSize;
				$info     = [];
				$join     = $join->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
				if (!empty($join)) {
					foreach ($join as $key => $value) {
						$workExternal              = WorkExternalContact::findOne($value->external_id);
						$info[$key]['nick_name']   = urldecode($workExternal->name);
						$info[$key]['create_time'] = $value->create_time;
						$info[$key]['key']         = $value->id;
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

	}