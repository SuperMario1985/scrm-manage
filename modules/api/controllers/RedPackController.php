<?php
	/**
	 * 红包裂变
	 * User: xingchanngyu
	 * Date: 2020/05/27
	 * Time: 15:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\RedPack;
	use app\models\RedPackHelpDetail;
	use app\models\RedPackJoin;
	use app\models\RedPackOrder;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkExternalContact;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncRedPackJob;
	use app\util\SUtils;
	use app\util\WorkUtils;

	class RedPackController extends WorkBaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           裂变列表
		 * @description     裂变列表
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/list
		 *
		 * @param uid 必选 string 用户id
		 * @param title 可选 string 标题
		 * @param status 可选 string 状态：0全部、1未开始、2进行中、3已结束
		 * @param start_date 可选 string 开始日期
		 * @param end_date 可选 string 结束日期
		 * @param page 可选 string 页码，默认为1
		 * @param pageSize 可选 string 每页数量，默认为15
		 *
		 * @return          {"error":0,"data":{"count":"1","redPack":[{"id":1,"uid":2,"corp_id":1,"agent_id":5,"title":"测试","start_time":"2020-05-27 14:02:01","end_time":"2020-05-29 14:02:05","activity_rule":"123456","contact_phone":"18505607672","redpack_price":"100.00","redpack_num":10,"first_detach_type":1,"min_random_amount":"0.30","max_random_amount":"3.00","fixed_amount":"0.00","min_random_amount_per":0,"max_random_amount_per":0,"invite_amount":2,"friend_detach_type":2,"min_friend_random_amount":"0.00","max_friend_random_amount":"0.00","fixed_friend_amount":"2.00","total_amount":"10.00","send_type":1,"sex_type":1,"area_type":1,"area_data":"","tag_ids":[],"qr_code":"","status":1,"create_time":"2020-05-27 14:03:15","update_time":"0000-00-00 00:00:00","first_str":"首拆领0.30元~3.00元","limit_str":"需邀请2人拆领，每人领取2.00元","member_str":[],"status_str":"未开始","h5Url":"http://ttest-m-scrm.wemero.cn/h5/pages/fission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=red_1_0"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    redPack string 列表数据
		 * @return_param    id string 任务id
		 * @return_param    title string 活动标题
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    redpack_str string 裂变红包金额
		 * @return_param    first_str string 首次拆领
		 * @return_param    limit_str string 活动限制
		 * @return_param    member_str string 成员
		 * @return_param    status_str string 状态
		 * @return_param    total_amount string 预发放金额
		 * @return_param    h5Url string 二维码地址
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 14:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id    = $this->corp->id;
			$corpId     = $this->corp->corpid;
			$uid        = \Yii::$app->request->post('uid', 0);
			$status     = \Yii::$app->request->post('status', 0);
			$title      = \Yii::$app->request->post('title', '');
			$start_date = \Yii::$app->request->post('start_date', '');
			$end_date   = \Yii::$app->request->post('end_date', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('pageSize', 10);
			if (empty($uid) || empty($corp_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			//更改过期状态
			$date_now    = date('Y-m-d H:i:s');
			$redPackList = RedPack::find()->where(['uid' => $uid, 'corp_id' => $corp_id, 'status' => [1, 2]])->andWhere(['<=', 'end_time', $date_now])->all();
			if (!empty($redPackList)) {
				foreach ($redPackList as $pack) {
					/**@var RedPack $pack * */
					$pack->status = 3;
					$pack->update();
					\Yii::$app->queue->push(new SyncRedPackJob([
						'red_pack_id' => $pack->id,
						'red_status'  => 3
					]));
				}
			}

			$redPack = RedPack::find()->where(['uid' => $uid, 'corp_id' => $corp_id]);
			//状态
			if (!empty($status)) {
				if ($status == 3) {
					$redPack = $redPack->andWhere(['status' => [3, 4, 5]]);
				} else {
					$redPack = $redPack->andWhere(['status' => $status]);
				}
			} else {
				$redPack = $redPack->andWhere(['status' => [1, 2, 3, 4, 5]]);
			}
			//标题
			if (!empty($title)) {
				$redPack = $redPack->andWhere(['like', 'title', $title]);
			}
			//日期
			if (!empty($start_date) && !empty($end_date)) {
				$start_date = $start_date . ' 00:00:00';
				$end_date   = $end_date . ' 23:59:59';
				$redPack    = $redPack->andWhere(['and', ['or', ['and', ['<=', 'start_time', $start_date], ['>=', 'end_time', $start_date]], ['and', ['>=', 'start_time', $start_date], ['<=', 'end_time', $end_date]], ['and', ['<=', 'start_time', $end_date], ['>=', 'end_time', $end_date]]]]);
			}
			$redPack     = $redPack->orderBy('id desc');
			$offset      = ($page - 1) * $pageSize;
			$count       = $redPack->count();
			$redPack     = $redPack->limit($pageSize)->offset($offset)->all();
			$web_url     = \Yii::$app->params['web_url'];
			$redPackData = [];
			foreach ($redPack as $red) {
				/** @var RedPack $red * */
				$redInfo   = $red->dumpData(true);
				$state     = RedPack::RED_HEAD . '_' . $red->id . '_0';
				$corpAgent = WorkCorpAgent::findOne($red->agent_id);
				$h5Url     = $web_url . RedPack::H5_URL . '?corp_id=' . $red->corp_id . '&corpid=' . $corpId . '&agent_id=' . $red->agent_id . '&assist=' . $state;
				if ($corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
					$h5Url .= '&suite_id=' . $corpAgent->suite->suite_id;
				}
				$redInfo['h5Url'] = $h5Url;
				array_push($redPackData, $redInfo);
			}

			return [
				'count'   => $count,
				'redPack' => $redPackData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           裂变添加修改
		 * @description     裂变添加修改
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/add
		 *
		 * @param uid 必选 string 用户id
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param agent_id 必选 string 应用ID
		 * @param id 可选 string 任务id，修改时必填
		 * @param title 必选 string 活动标题
		 * @param start_time 必选 string 开始时间
		 * @param end_time 必选 string 结束时间
		 * @param activity_rule 可选 string 活动规则
		 * @param contact_phone 必选 string 活动电话
		 * @param redpack_price 必选 string 裂变红包金额
		 * @param redpack_num 必选 string 裂变红包个数
		 * @param first_detach_type 必选 string 用户首次拆领类型：1、随机金额，2、固定金额，3、百分比金额
		 * @param min_random_amount 可选 string 最小随机金额
		 * @param max_random_amount 可选 string 最大随机金额
		 * @param fixed_amount 可选 string 固定金额
		 * @param min_random_amount_per 可选 string 最小随机金额百分比
		 * @param max_random_amount_per 可选 string 最大随机金额百分比
		 * @param invite_amount 必选 string 裂变人数数量
		 * @param friend_detach_type 必选 string 好友拆领类型：1、随机金额，2、固定金额
		 * @param min_friend_random_amount 可选 string 最小随机金额
		 * @param max_friend_random_amount 可选 string 最大随机金额
		 * @param fixed_friend_amount 可选 string 固定金额
		 * @param total_amount 必选 string 活动总金额
		 * @param send_type 必选 string 发放红包类型：1、手动发送，2、自动发放
		 * @param sex_type 必选 string 性别类型：1、不限制，2、男性，3、女性，4、未知
		 * @param area_type 必选 string 区域类型：1、不限制，2、部分地区
		 * @param area_data 必选 string 区域数据
		 * @param tag_ids 必选 string 给客户打的标签
		 * @param back_pic_url 必选 string 海报图片地址
		 * @param is_avatar 必选 string 头像选择按钮，0否、1是
		 * @param avatar 必选 string 头像位置
		 * @param shape 可选 string 圆形方形，is_avatar=1必填
		 * @param is_nickname 必选 string 昵称选择按钮，0否、1是
		 * @param nickName 必选 string 昵称位置
		 * @param qrCode 必选 string 二维码位置
		 * @param color 可选 string 昵称颜色，is_nickname=1必填
		 * @param font_size 可选 string 昵称大小，is_nickname=1必填
		 * @param align 可选 string 昵称对齐方式，is_nickname=1必填
		 * @param user 必选 string 成员数据
		 * @param text_content 可选 string 招呼语话术
		 * @param link_title 必选 string 开始活动标题
		 * @param link_desc 可选 string 描述
		 * @param link_pic_url 可选 string 封面图片
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 14:11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			//检查数据
			try {
				$postData            = \Yii::$app->request->post();
				$postData['corp_id'] = $this->corp->id;
				\Yii::error($postData, 'postData');
				RedPack::setData($postData);
			} catch (InvalidDataException $e) {
				$message = $e->getMessage();
				if (strpos($message, '84074') !== false) {
					$message = '没有外部联系人权限';
				} elseif (strpos($message, '40096') !== false) {
					$message = '不合法的外部联系人userid';
				} elseif (strpos($message, '40098') !== false) {
					$message = '接替成员尚未实名认证';
				} elseif (strpos($message, '40100') !== false) {
					$message = '用户的外部联系人已经在转移流程中';
				} elseif (strpos($message, '41054') !== false) {
					$message = '引流成员必须是已激活的成员（已登录过APP的才算作完全激活）';
				}
				throw new InvalidDataException($message);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           裂变任务修改详情
		 * @description     裂变任务修改详情
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/edit-info
		 *
		 * @param uid 必选 string 用户id
		 * @param id 必选 string 任务id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 修改id
		 * @return_param    uid string 用户id
		 * @return_param    corp_id string 企业corp_id
		 * @return_param    corp_name string 企业名称
		 * @return_param    title string 活动标题
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    contact_phone string 活动电话
		 * @return_param    redpack_price string 裂变红包金额
		 * @return_param    redpack_num string 裂变红包个数
		 * @return_param    first_detach_type string 用户首次拆领类型：1、随机金额，2、固定金额，3、百分比金额
		 * @return_param    min_random_amount string 最小随机金额
		 * @return_param    max_random_amount string 最大随机金额
		 * @return_param    fixed_amount string 固定金额
		 * @return_param    min_random_amount_per string 最小随机金额百分比
		 * @return_param    max_random_amount_per string 最大随机金额百分比
		 * @return_param    invite_amount string 裂变人数数量
		 * @return_param    friend_detach_type string 好友拆领类型：1、随机金额，2、固定金额
		 * @return_param    min_friend_random_amount string 最小随机金额
		 * @return_param    max_friend_random_amount string 最大随机金额
		 * @return_param    fixed_friend_amount string 固定金额
		 * @return_param    total_amount string 活动总金额
		 * @return_param    send_type string 发放红包类型：1、手动发送，2、自动发放
		 * @return_param    sex_type string 性别类型：1、不限制，2、男性，3、女性，4、未知
		 * @return_param    area_type string 区域类型：1、不限制，2、部分地区
		 * @return_param    area_data string 区域数据
		 * @return_param    tag_ids array 给客户打的标签
		 * @return_param    back_pic_url string 海报图片地址
		 * @return_param    is_avatar string 头像选择按钮，0否、1是
		 * @return_param    avatar string 头像位置
		 * @return_param    shape string 圆形方形，is_avatar=1必填
		 * @return_param    is_nickname string 昵称选择按钮，0否、1是
		 * @return_param    nickName string 昵称位置
		 * @return_param    qrCode string 二维码位置
		 * @return_param    color string 昵称颜色，is_nickname=1必填
		 * @return_param    font_size string 昵称大小，is_nickname=1必填
		 * @return_param    align string 昵称对齐方式，is_nickname=1必填
		 * @return_param    user array 成员数据
		 * @return_param    text_content string 招呼语话术
		 * @return_param    link_title string 开始活动标题
		 * @return_param    link_desc string 描述
		 * @return_param    link_pic_url string 封面图片
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 14:26
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionEditInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$id  = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$redPack = RedPack::findOne($id);
			if (empty($redPack)) {
				throw new InvalidDataException('参数不正确！');
			}
			$redPackData              = $redPack->dumpData(false, true);
			$workCorp                 = WorkCorp::findOne($redPackData['corp_id']);
			$redPackData['corp_id']   = $workCorp->corpid;
			$redPackData['corp_name'] = $workCorp->corp_name;

			return $redPackData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           裂变修改状态
		 * @description     裂变修改状态
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/change-status
		 *
		 * @param uid 必选 string 用户id
		 * @param id 必选 string 任务id
		 * @param status 必选 string 状态:0删除、2发布、5手动提前结束
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 14:34
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid', 0);
			$status = \Yii::$app->request->post('status', 5);
			$id     = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$redData = RedPack::findOne($id);
			if (empty($redData)) {
				throw new InvalidDataException('此活动不存在！');
			}
			if ($status == 2) {
				$date = date('Y-m-d H:i:s');
				if ($redData->start_time > $date) {
					throw new InvalidDataException('尚未到开始时间，不能发布！');
				}
			}
			$oldStatus = $redData->status;
			if ($oldStatus == 2 && $status == 0) {
				throw new InvalidDataException('进行中的活动不能删除！');
			}
			$redData->status = $status;
			if (!$redData->validate() || !$redData->save()) {
				throw new InvalidDataException('修改失败.' . SUtils::modelError($redData));
			}
			//删除企业微信config_id
			if ($status == 5) {
				\Yii::$app->queue->push(new SyncRedPackJob([
					'red_pack_id' => $redData->id,
					'red_status'  => 5
				]));
			} elseif ($oldStatus == 1 && $status == 0) {
				if (!empty($redData->config_id)) {
					try {
						$workApi = WorkUtils::getWorkApi($redData->corp_id, WorkUtils::EXTERNAL_API);
						$workApi->ECDelContactWay($redData->config_id);
					} catch (\Exception $e) {

					}
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           参与者列表
		 * @description     参与者列表
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/join-list
		 *
		 * @param uid 必选 string 用户id
		 * @param rid 必选 string 任务id
		 * @param name 可选 string 搜索名称
		 * @param send_status 可选 string 发放状态：0未发放、1已发放
		 * @param page 可选 string 页码，默认1
		 * @param pageSize 可选 string 每页数量，默认10
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    keys array 键值列表
		 * @return_param    is_end string 活动是否结束：0否，1是
		 * @return_param    fission array 列表数据
		 * @return_param    id string 参与者id
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    help_num string 有效助力数
		 * @return_param    invite_amount string 裂变人数
		 * @return_param    first_amount string 首拆金额
		 * @return_param    friend_amount string 好友拆金额
		 * @return_param    status string 状态：0未完成、1进行中、2已完成
		 * @return_param    first_send_status string 首拆发放状态：0未发放、1已发放
		 * @return_param    send_status string 剩余发放状态：0未发放、1已发放
		 * @return_param    join_time string 参与时间
		 * @return_param    complete_time string 完成时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 15:49
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionJoinList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid         = \Yii::$app->request->post('uid', 0);
			$rid         = \Yii::$app->request->post('rid', 0);
			$page        = \Yii::$app->request->post('page', 1);
			$pageSize    = \Yii::$app->request->post('pageSize', 10);
			$name        = \Yii::$app->request->post('name', '');
			$send_status = \Yii::$app->request->post('send_status', '-1');
			if (empty($uid) || empty($rid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$redPack = RedPack::findOne($rid);
			if (empty($redPack)) {
				throw new InvalidDataException('参数不正确！');
			}
			$is_end = 0;
			if (in_array($redPack->status, [3, 4, 5])) {
				$is_end = 1;
			}
			$redPackJoin = RedPackJoin::find()->alias('rj');
			$redPackJoin = $redPackJoin->leftJoin('{{%work_external_contact}} wec', '`rj`.`external_id` = `wec`.`id`');
			$redPackJoin = $redPackJoin->where(['rj.uid' => $uid, 'rj.rid' => $rid]);
			if (!empty($name)) {
				$redPackJoin = $redPackJoin->andWhere(['like', 'wec.name_convert', $name]);
			}
			if ($send_status != -1) {
				$redPackJoin = $redPackJoin->andWhere(['rj.send_status' => $send_status]);
			}
			//获取符合条件的keys
			$keys   = [];
			$joinId = clone $redPackJoin;
			$idList = $joinId->andWhere(['or', ['rj.first_send_status' => 0], ['rj.status' => 2, 'rj.send_status' => 0]])->select('rj.id,rj.first_send_status,rj.status,rj.send_status')->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					/**@var RedPackJoin $idInfo * */
					if ($idInfo->first_send_status == 0) {
						array_push($keys, (string) $idInfo['id']);
					} elseif ($idInfo->status == 2 && $idInfo->send_status == 0) {
						if (!($redPack->send_type == 2 && $redPack->status == 2)) {
							array_push($keys, (string) $idInfo['id']);
						}
					}
				}
			}

			$redPackJoin = $redPackJoin->select('wec.name,wec.avatar,rj.*');
			$offset      = ($page - 1) * $pageSize;
			$count       = $redPackJoin->count();
			$redPackJoin = $redPackJoin->limit($pageSize)->offset($offset)->asArray()->all();
			$joinData    = [];
			foreach ($redPackJoin as $key => $join) {
				$joinData[$key]['key']               = $join['id'];
				$joinData[$key]['id']                = $join['id'];
				$joinData[$key]['name']              = urldecode($join['name']);
				$joinData[$key]['avatar']            = $join['avatar'];
				$joinData[$key]['help_num']          = $join['help_num'];
				$joinData[$key]['invite_amount']     = $join['invite_amount'];
				$joinData[$key]['first_amount']      = $join['first_amount'];
				$joinData[$key]['friend_amount']     = $join['friend_amount'];
				$joinData[$key]['status']            = $join['status'];
				$joinData[$key]['first_send_status'] = $join['first_send_status'];
				$joinData[$key]['send_status']       = $join['send_status'];
				$joinData[$key]['join_time']         = $join['join_time'];
				$joinData[$key]['complete_time']     = $join['complete_time'];
			}

			return [
				'count'     => $count,
				'keys'      => $keys,
				'is_end'    => $is_end,
				'send_type' => $redPack->send_type,
				'join'      => $joinData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           参与者手动发放
		 * @description     参与者手动发放
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/join-hand-send
		 *
		 * @param uid 必选 string 账户id
		 * @param rid 必选 string 任务id
		 * @param jid 必选 string 参与者id
		 *
		 * @return          {"error":0,"data":{"textHtml":"发放1人，未获取到微信支付配置信息，导致1人发放失败"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-29 11:45
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionJoinHandSend ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$rid = \Yii::$app->request->post('rid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			if (empty($uid) || empty($rid)) {
				throw new InvalidDataException('请求参数不正确！');
			}
			if (empty($jid)) {
				throw new InvalidDataException('请选择未发放的参与者！');
			}
			$redPack = RedPack::findOne($rid);
			if (empty($redPack)) {
				throw new InvalidDataException('请求参数不正确！');
			}
			$joinList = RedPackJoin::find()->where(['uid' => $uid, 'id' => $jid])->andWhere(['or', ['first_send_status' => 0], ['status' => 2, 'send_status' => 0]]);
			$count    = $joinList->count();
			$joinList = $joinList->all();
			if (empty($joinList)) {
				throw new InvalidDataException('请选择未发放的参与者！');
			}
			$success = 0;
			$err_msg = $no_msg = '';
			foreach ($joinList as $join) {
				/** @var RedPackJoin $join * */
				try {
					$remark    = '';
					$send_type = $amount = 0;
					if ($join->first_send_status == 0) {
						$remark                  = '还有' . $join->rest_amount . '元正在路上，快召唤' . $join->invite_amount . '位好友一起拆红包，TA有，你也有~~';
						$amount                  = $join->first_amount;
						$send_type               = 1;
						$join->first_send_status = 1;
						$join->first_send_type   = 1;
						if ($join->status == 2 && $join->send_status == 0) {
							if (!($redPack->send_type == 2 && $redPack->status == 2)) {
								$remark            = $join->invite_amount . '位好友已全部拆完，' . $join->redpack_price . '元红包拿走，不谢~~~';
								$amount            = $join->redpack_price;
								$send_type         = 4;
								$join->send_status = 1;
								$join->send_type   = 1;
							}
						}
					} elseif ($join->status == 2 && $join->send_status == 0) {
						if ($redPack->send_type == 2 && $redPack->status == 2) {
							continue;
						}
						$remark            = $join->invite_amount . '位好友已全部拆完，剩下的' . $join->rest_amount . '元红包拿走，不谢~~~';
						$amount            = $join->rest_amount;
						$send_type         = 2;
						$join->send_status = 1;
						$join->send_type   = 1;
					}
					if (!empty($send_type)) {
						$orderData = [
							'uid'         => $redPack->uid,
							'corp_id'     => $redPack->corp_id,
							'rid'         => $redPack->id,
							'jid'         => $join->id,
							'external_id' => $join->external_id,
							'openid'      => $join->openid,
							'amount'      => $amount,
							'remark'      => $remark,
							'send_type'   => $send_type,
						];
						$res       = RedPackOrder::sendRedPack($orderData);
						if (!empty($res)) {
							$join->update();
							$success++;
							$is_send = 1;
						}
					} else {
						$no_msg = '不符合发放规则';
					}
				} catch (InvalidDataException $e) {
					$err_msg = $e->getMessage();
					break;
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
					$err_msg = trim($err_msg, '！');
					if (!empty($no_msg)) {
						$err_msg = $err_msg . '或' . $no_msg;
					}
					$textHtml .= '，' . $err_msg . '，导致' . $diff . '人发放失败';
				}
			}
			if (empty($err_msg) && !empty($is_send)) {
				\Yii::$app->queue->delay(10)->push(new SyncRedPackJob([
					'red_pack_id' => $redPack->id,
					'sendData'    => ['is_all' => 1, 'uid' => $redPack->uid]
				]));
			}

			return ['textHtml' => $textHtml, 'success' => $success];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           参与者标记发放
		 * @description     参与者标记发放
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/join-status
		 *
		 * @param uid 必选 string 用户id
		 * @param jid 必选 string 参与者id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 16:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionJoinStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$join = RedPackJoin::findOne($jid);
			if ($join->first_send_status == 0) {
				$join->first_send_status = 1;
				$join->first_send_type   = 2;
				if ($join->status == 2 && $join->send_status == 0) {
					if (!($join->r->send_type == 2 && $join->r->status == 2)) {
						$join->send_status = 1;
						$join->send_type   = 2;
					}
				}
			} elseif ($join->status == 2 && $join->send_status == 0) {
				if ($join->r->send_type == 2 && $join->r->status == 2) {
					throw new InvalidDataException('活动未结束，不能进行标记发放！');
				}
				$join->send_status = 1;
				$join->send_type   = 2;
			}
			$join->update();

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           助力列表
		 * @description     助力列表
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/help-list
		 *
		 * @param uid 必选 string 用户id
		 * @param jid 必选 string 参与表id
		 * @param page 可选 string 页码，默认1
		 * @param pageSize 可选 string 每页数量，默认10
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    keys array 键值列表
		 * @return_param    is_end string 活动是否结束：0否，1是
		 * @return_param    helpList array 列表数据
		 * @return_param    id string 列表id
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    help_time string 助力时间
		 * @return_param    amount string 金额
		 * @return_param    status string 有效状态：0无效、1有效
		 * @return_param    send_status string 发放状态：0未发放、1已发放
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 15:52
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionHelpList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$redPackJoin = RedPackJoin::findOne($jid);
			if (empty($redPackJoin)) {
				throw new InvalidDataException('参数不正确！');
			}

			$is_end = 0;
			if (in_array($redPackJoin->r->status, [3, 4, 5])) {
				$is_end = 1;
			}

			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);

			$helpData = RedPackHelpDetail::find()->alias('rhd');
			$helpData = $helpData->leftJoin('{{%work_external_contact}} wec', '`rhd`.`external_id` = `wec`.`id`');
			$helpData = $helpData->where(['rhd.jid' => $jid]);

			//获取符合条件的key
			$keys   = [];
			$helpId = clone $helpData;
			$idList = $helpId->andWhere(['rhd.status' => 1, 'rhd.send_status' => 0])->select('rhd.id')->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					array_push($keys, (string) $idInfo['id']);
				}
			}

			$helpData = $helpData->select('wec.name,wec.avatar,rhd.*');
			$offset   = ($page - 1) * $pageSize;
			$count    = $helpData->count();
			$helpData = $helpData->limit($pageSize)->offset($offset)->asArray()->all();
			$helpList = [];
			foreach ($helpData as $key => $help) {
				$helpList[$key]['key']         = $help['id'];
				$helpList[$key]['id']          = $help['id'];
				$helpList[$key]['name']        = urldecode($help['name']);
				$helpList[$key]['avatar']      = $help['avatar'];
				$helpList[$key]['help_time']   = $help['help_time'];
				$helpList[$key]['amount']      = $help['amount'];
				$helpList[$key]['status']      = $help['status'];
				$helpList[$key]['send_status'] = $help['send_status'];
			}

			return [
				'count'    => $count,
				'keys'     => $keys,
				'is_end'   => $is_end,
				'helpList' => $helpList,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           助力者手动发放
		 * @description     助力者手动发放
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/help-hand-send
		 *
		 * @param uid 必选 string 账户id
		 * @param jid 必选 string 参与者id
		 * @param hid 必选 string 助力者id
		 *
		 * @return          {"error":0,"data":{"textHtml":"发放1人，未获取到微信支付配置信息，导致1人发放失败"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-29 11:45
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionHelpHandSend ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			$hid = \Yii::$app->request->post('hid', 0);
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('请求参数不正确！');
			}
			if (empty($hid)) {
				throw new InvalidDataException('请选择未发放的助力者！');
			}
			$redPackJoin = RedPackJoin::findOne($jid);
			if (empty($redPackJoin)) {
				throw new InvalidDataException('请求参数不正确！');
			}

			$corp_id  = $redPackJoin->r->corp_id;
			$helpList = RedPackHelpDetail::find()->where(['jid' => $jid, 'id' => $hid, 'status' => 1, 'send_status' => 0]);
			$count    = $helpList->count();
			$helpList = $helpList->all();
			if (empty($helpList)) {
				throw new InvalidDataException('请选择未发放的参与者！');
			}
			$contact = WorkExternalContact::findOne($redPackJoin->external_id);
			if (empty($contact)) {
				throw new InvalidDataException('未找到该参与者！');
			}
			$name    = !empty($contact->name) ? rawurldecode($contact->name) : $contact->name_convert;
			$success = 0;
			$err_msg = '';
			foreach ($helpList as $help) {
				/** @var RedPackHelpDetail $help * */
				try {
					$remark    = '恭喜您，你帮“' . $name . '”拆红包，获得' . $help->amount . '元红包';
					$orderData = [
						'uid'         => $redPackJoin->r->uid,
						'corp_id'     => $corp_id,
						'rid'         => $redPackJoin->r->id,
						'jid'         => $jid,
						'hid'         => $help->id,
						'external_id' => $help->external_id,
						'openid'      => $help->openid,
						'amount'      => $help->amount,
						'remark'      => $remark,
						'send_type'   => 3,
					];
					$res       = RedPackOrder::sendRedPack($orderData);
					if (!empty($res)) {
						$help->send_status = 1;
						$help->send_type   = 1;
						$help->update();
						$success++;
					}
				} catch (InvalidDataException $e) {
					$err_msg = $e->getMessage();
					break;
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

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack/
		 * @title           助力者标记发放
		 * @description     助力者标记发放
		 * @method   post
		 * @url  http://{host_name}/api/red-pack/help-status
		 *
		 * @param uid 必选 string 用户id
		 * @param hid 必选 string 助力者id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 16:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionHelpStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$hid = \Yii::$app->request->post('hid', 0);
			if (empty($uid) || empty($hid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$helpData = RedPackHelpDetail::findOne($hid);
			if (empty($helpData)) {
				throw new InvalidDataException('参数不正确！');
			}
			if ($helpData->status == 0) {
				throw new InvalidDataException('该助力记录为无效状态，不能标记发放！');
			}
			$helpData->send_status = 1;
			$helpData->send_type   = 2;
			$helpData->update();

			return true;
		}

	}