<?php
	/**
	 * 裂变引流
	 * User: xingchanngyu
	 * Date: 2020/03/16
	 * Time: 15:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\Fission;
	use app\models\FissionHelpDetail;
	use app\models\FissionJoin;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkExternalContact;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use app\queue\SyncFissionJob;
	use app\models\RedPackOrder;
	use yii\web\MethodNotAllowedHttpException;

	class FissionController extends WorkBaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           裂变列表
		 * @description     裂变列表
		 * @method   post
		 * @url  http://{host_name}/api/fission/list
		 *
		 * @param uid 必选 string 用户id
		 * @param title 可选 string 标题
		 * @param status 可选 string 状态：0全部、1未开始、2进行中、3已结束
		 * @param date 可选 string 日期
		 * @param page 可选 string 页码，默认为1
		 * @param pageSize 可选 string 每页数量，默认为10
		 *
		 * @return          {"error":0,"data":{"count":"2","fission":[{"id":2,"uid":2,"corp_id":1,"title":"321","start_time":"2020-03-19 00:00:00","end_time":"2020-03-20 11:59:59","status":1,"create_time":"0000-00-00 00:00:00","update_time":"2020-03-19 09:13:07","prize_name":"321321","prize_num":12,"limit_str":"需邀请231人助力（必须为新好友）","member_str":"陈志尧","status_str":"未开始","key":2},{"id":1,"uid":2,"corp_id":1,"title":"ewq","start_time":"2020-03-19 00:00:00","end_time":"2020-03-21 11:59:59","status":1,"create_time":"0000-00-00 00:00:00","update_time":"2020-03-18 17:50:06","prize_name":"3213","prize_num":321,"limit_str":"需邀请321人助力（必须为新好友）","member_str":"李云莉,林凤","status_str":"未开始","key":1}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    fission string 列表数据
		 * @return_param    id string 任务id
		 * @return_param    title string 活动标题
		 * @return_param    start_time string 开始时间
		 * @return_param    end_time string 结束时间
		 * @return_param    prize_name string 奖品名称
		 * @return_param    prize_num string 奖品数量
		 * @return_param    limit_str string 活动限制
		 * @return_param    member_str string 成员
		 * @return_param    status_str string 状态
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-17 13:28
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid        = \Yii::$app->request->post('uid', 0);
			$status     = \Yii::$app->request->post('status', 0);
			$title      = \Yii::$app->request->post('title', '');
			$start_date = \Yii::$app->request->post('start_date', '');
			$end_date   = \Yii::$app->request->post('end_date', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('pageSize', 10);
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			if (empty($uid) || empty($corp_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			//更改过期状态
			$date_now    = date('Y-m-d H:i:s');
			$fissionList = Fission::find()->where(['uid' => $uid, 'corp_id' => $corp_id, 'status' => [1, 2]])->andWhere(['<=', 'end_time', $date_now])->select('id,uid,status,corp_id,agent_id,config_id')->all();
			if (!empty($fissionList)) {
				foreach ($fissionList as $fiss) {
					/**@var Fission $fiss **/
					$fiss->status = 3;
					$fiss->update();
					\Yii::$app->queue->push(new SyncFissionJob([
						'fission_id'     => $fiss->id,
						'fission_status' => 3
					]));
				}
			}

			$fission = Fission::find()->where(['uid' => $uid, 'corp_id' => $corp_id]);
			//状态
			if (!empty($status)) {
				if ($status == 3) {
					$fission = $fission->andWhere(['status' => [3, 4, 5]]);
				} else {
					$fission = $fission->andWhere(['status' => $status]);
				}
			} else {
				$fission = $fission->andWhere(['status' => [1, 2, 3, 4, 5]]);
			}
			//标题
			if (!empty($title)) {
				$fission = $fission->andWhere(['like', 'title', $title]);
			}
			//日期
			if (!empty($start_date) && !empty($end_date)) {
				$start_date = $start_date . ' 00:00:00';
				$end_date   = $end_date . ' 23:59:59';
				$fission    = $fission->andWhere(['and', ['or', ['and', ['<=', 'start_time', $start_date], ['>=', 'end_time', $start_date]], ['and', ['>=', 'start_time', $start_date], ['<=', 'end_time', $end_date]], ['and', ['<=', 'start_time', $end_date], ['>=', 'end_time', $end_date]]]]);
			}
			$fission     = $fission->orderBy('id desc');
			$offset      = ($page - 1) * $pageSize;
			$count       = $fission->count();
			$fission     = $fission->limit($pageSize)->offset($offset)->all();
			$fissionData = [];
			$web_url     = \Yii::$app->params['web_url'];

			/**
			 * @var int     $sk
			 * @var Fission $sv
			 */
			foreach ($fission as $sk => $sv) {
				$fissionInfo        = $sv->dumpData(true);
				$fissionInfo['key'] = $fissionInfo['id'];
				if (in_array($sv->status, [1, 3, 4, 5])) {
					$h5Url = $web_url . '/h5/pages/fission/preview?fid=' . $sv->id;
				} else {
					$workCorp  = WorkCorp::findOne($sv->corp_id);
					$state     = Fission::FISSION_HEAD . '_' . $sv->id . '_0';
					$corpAgent = WorkCorpAgent::findOne($sv->agent_id);
					if (!empty($corpAgent) && $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
						$h5Url = $web_url . Fission::H5_URL . '?suite_id=' . $corpAgent->suite->suite_id . '&corp_id=' . $sv->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $sv->agent_id . '&assist=' . $state;
					} else {
						$h5Url = $web_url . Fission::H5_URL . '?corp_id=' . $sv->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $sv->agent_id . '&assist=' . $state;
					}
				}
				$fissionInfo['h5Url'] = $h5Url;
				//给前端的改成剩余数量
				if ($fissionInfo['prize_num'] >= $fissionInfo['complete_num']) {
					$restNum = $fissionInfo['prize_num'] - $fissionInfo['complete_num'];
				} else {
					$restNum = 0;
				}
				$fissionInfo['complete_num'] = $restNum;
				array_push($fissionData, $fissionInfo);
			}

			return [
				'count'   => $count,
				'fission' => $fissionData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           裂变添加修改
		 * @description     裂变添加修改
		 * @method   post
		 * @url  http://{host_name}/api/fission/add
		 *
		 * @param uid 必选 string 用户id
		 * @param id 可选 string 任务id，修改时必填
		 * @param title 必选 string 活动标题
		 * @param start_time 必选 string 开始时间
		 * @param end_time 必选 string 结束时间
		 * @param is_end 必选 string 在有效期内，奖品已无库存情况下，活动自动结束 0未勾 1已勾
		 * @param is_friend 必选 string 裂变要求:0新好友助力、1全部好友
		 * @param is_invalid 必选 string 删企微好友/被拉黑助力失效是否失效:0否、1是
		 * @param is_brush 必选 string 防刷检测:0否、1是
		 * @param brush_time 可选 string 防刷秒数，is_brush=1必填
		 * @param brush_num 可选 string 防刷人数，is_brush=1必填
		 * @param fission_num 必选 string 裂变人数
		 * @param prize_name 必选 string 奖品名称
		 * @param prize_num 必选 string 奖品库存
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
		 * @param text_content 可选 string 招呼语话术
		 * @param link_start_title 必选 string 开始活动标题
		 * @param link_end_title 必选 string 结束活动标题
		 * @param link_desc 可选 string 描述
		 * @param link_pic_url 可选 string 封面图片
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-17 13:34
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			//检查数据
			try {
				$postData            = \Yii::$app->request->post();
				$postData['corp_id'] = $this->corp->id;
				\Yii::error($postData, 'postData');
				Fission::setData($postData);
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
		 * @catalog         数据接口/api/fission/
		 * @title           裂变修改状态
		 * @description     裂变修改状态
		 * @method   post
		 * @url  http://{host_name}/api/fission/change-status
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
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-17 13:49
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid', 0);
			$status = \Yii::$app->request->post('status', 5);
			$id     = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$fission = Fission::findOne(['uid' => $uid, 'id' => $id]);
			if (empty($fission)) {
				throw new InvalidDataException('此任务不存在！');
			}
			if ($status == 2) {
				$date = date('Y-m-d H:i:s');
				if ($fission->start_time > $date) {
					throw new InvalidDataException('尚未到开始时间，不能发布！');
				}
			}
			$oldStatus       = $fission->status;
			$fission->status = $status;
			if (!$fission->validate() || !$fission->save()) {
				throw new InvalidDataException('修改失败.' . SUtils::modelError($fission));
			}

			//删除企业微信config_id
			if ($status == 5) {
				\Yii::$app->queue->push(new SyncFissionJob([
					'fission_id'     => $fission->id,
					'fission_status' => 5
				]));
			} elseif ($oldStatus == 1 && $status == 0) {
				if (!empty($fission->config_id)) {
					try {
						$workApi = WorkUtils::getWorkApi($fission->corp_id, WorkUtils::EXTERNAL_API);
						$workApi->ECDelContactWay($fission->config_id);
					} catch (\Exception $e) {

					}
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           裂变任务修改详情
		 * @description     裂变任务修改详情
		 * @method   post
		 * @url  http://{host_name}/api/fission/edit-info
		 *
		 * @param uid 必选 string 用户id
		 * @param id 必选 string 任务id
		 *
		 * @return          {"error":0,"data":{"id":1,"uid":2,"corp_id":"ww93caebeee67d134b","title":"ewq","start_time":"2020-03-19 00:00:00","end_time":"2020-03-21 11:59:59","is_end":true,"is_friend":0,"is_invalid":true,"is_brush":true,"is_option":false,"user":[{"id":39,"user_key":"39-4"},{"id":95,"user_key":"95-4","name":"林凤"}],"create_time":"0000-00-00 00:00:00","update_time":"2020-03-18 17:50:06","brush_time":321,"brush_num":231,"fission_num":321,"prize_name":"3213","prize_num":321,"back_pic_url":"/upload/images/2/20200318/15845243405e71ec3412a32.jpg","is_avatar":true,"avatar":{"w":40,"x":24,"y":24},"shape":"circle","is_nickname":true,"nickName":{"w":120,"h":36,"x":24,"y":70},"qrCode":{"w":60,"x":160,"y":355},"color":"#000000","font_size":14,"align":"left","text_content":"3213","link_start_title":"321","link_end_title":"","link_desc":"321","link_pic_url":"/upload/images/2/20200313/15840640545e6ae636842a0.jpg","corp_name":"小猪科技公司"}}
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
		 * @return_param    is_end bool 在有效期内，奖品已无库存情况下，活动自动结束
		 * @return_param    is_friend string 裂变要求:0新好友助力、1全部好友
		 * @return_param    is_invalid bool 删企微好友/被拉黑助力失效是否失效:0否、1是
		 * @return_param    is_brush bool 防刷检测:0否、1是
		 * @return_param    brush_time string 防刷秒数
		 * @return_param    brush_num string 防刷人数
		 * @return_param    is_option bool 引流成员选项:0选择引流成员、1渠道活码获取引流成员
		 * @return_param    user array 成员信息
		 * @return_param    fission_num string 裂变人数
		 * @return_param    prize_name string 奖品名称
		 * @return_param    prize_num string 奖品数量
		 * @return_param    back_pic_url string 海报地址
		 * @return_param    is_avatar bool 头像是否显示
		 * @return_param    avatar string 头像位置
		 * @return_param    shape string 头像形状
		 * @return_param    is_nickname bool 昵称是否显示
		 * @return_param    nickName bool 昵称位置
		 * @return_param    qrCode bool 二维码位置
		 * @return_param    color string 昵称颜色
		 * @return_param    font_size string 昵称大小
		 * @return_param    align string 昵称对齐方式
		 * @return_param    text_content string 欢迎语文本
		 * @return_param    link_start_title string 欢迎语标题
		 * @return_param    link_desc string 欢迎语描述
		 * @return_param    link_pic_url string 欢迎语图片地址
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-19 9:04
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionEditInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$id  = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$fission = Fission::findOne(['uid' => $uid, 'id' => $id]);
			if (empty($fission)) {
				throw new InvalidDataException('参数不正确！');
			}
			$fissionData              = $fission->dumpData(false, true);
			$workCorp                 = WorkCorp::findOne($fissionData['corp_id']);
			$fissionData['corp_id']   = $workCorp->corpid;
			$fissionData['corp_name'] = $workCorp->corp_name;
			unset($fissionData['brush_rule'], $fissionData['prize_rule'], $fissionData['pic_rule']);
			unset($fissionData['welcome']);

			return $fissionData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           参与名单
		 * @description     参与名单
		 * @method   post
		 * @url  http://{host_name}/modules/controller/join-list
		 *
		 * @param uid 必选 string 用户id
		 * @param fid 必选 string 任务id
		 * @param is_black 可选 string 是否是黑名单列表：0不是、1是
		 * @param name 可选 string 搜索名称
		 * @param page 可选 string 页码，默认1
		 * @param pageSize 可选 string 每页数量，默认10
		 *
		 * @return          {"error":0,"data":{"count":"1","keys":["1"],"fission":[{"key":"1","id":"1","name":"宅厨网络","avatar":"http://wx.qlogo.cn/mmhead/1ZqjMhQoRAYonQAYkwJHRO7bRgr9qibYIsPicjSnvjrj4/0","help_num":"0","fission_num":"4","status":"0","prize_status":"0","join_time":"2020-03-18 20:33:01","complete_time":"2020-03-18 20:33:04","black_time":"0000-00-00 00:00:00"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    keys array 键值列表
		 * @return_param    fission array 列表数据
		 * @return_param    id string 列表id
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    help_num string 有效助力数
		 * @return_param    fission_num string 裂变人数
		 * @return_param    status string 任务状态0未完成、1进行中、2已完成
		 * @return_param    prize_status string 奖品状态0未处理、1已处理
		 * @return_param    join_time string 参与时间
		 * @return_param    complete_time string 完成时间
		 * @return_param    black_time string 拉入黑名单时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-19 9:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionJoinList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$fid      = \Yii::$app->request->post('fid', 0);
			$is_black = \Yii::$app->request->post('is_black', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			$name     = \Yii::$app->request->post('name', '');
			$status   = \Yii::$app->request->post('status ', '');
			if (empty($uid) || empty($fid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			//活动是否结束
			$fission = Fission::findOne($fid);
			$is_end  = 0;
			if (in_array($fission->status, [3, 4, 5])) {
				$is_end = 1;
			}

			//参与记录
			$fissionJoin = FissionJoin::find()->alias('fj');
			$fissionJoin = $fissionJoin->leftJoin('{{%work_external_contact}} wec', '`fj`.`external_id` = `wec`.`id`');
			$fissionJoin = $fissionJoin->where(['fj.uid' => $uid, 'fj.fid' => $fid, 'fj.is_black' => $is_black]);
			if (!empty($name)) {
				$fissionJoin = $fissionJoin->andWhere(['like', 'name_convert', $name]);
			}
			if (!empty($status)) {
				$fissionJoin = $fissionJoin->andWhere(['status' => $status]);
			}
			$prize_send_type = $fission->prize_send_type;
			//获取符合条件的keys
			$keys   = [];
			if (!($prize_send_type == 2 && $is_end == 0)) {
				$joinId = clone $fissionJoin;
				$idList = $joinId->andWhere(['fj.status' => 2, 'fj.prize_status' => 0])->select('fj.id')->all();
				if (!empty($idList)) {
					foreach ($idList as $idInfo) {
						array_push($keys, (string) $idInfo['id']);
					}
				}
			}

			$fissionJoin = $fissionJoin->select('wec.name,wec.avatar,fj.*');
			$offset      = ($page - 1) * $pageSize;
			$count       = $fissionJoin->count();
			$fissionJoin = $fissionJoin->limit($pageSize)->offset($offset)->asArray()->all();
			$fissionData = [];
			foreach ($fissionJoin as $key => $fission) {
				$fissionData[$key]['key']           = $fission['id'];
				$fissionData[$key]['id']            = $fission['id'];
				$fissionData[$key]['name']          = urldecode($fission['name']);
				$fissionData[$key]['avatar']        = $fission['avatar'];
				$fissionData[$key]['help_num']      = $fission['help_num'];
				$fissionData[$key]['fission_num']   = $fission['fission_num'];
				$fissionData[$key]['status']        = $fission['status'];
				$fissionData[$key]['prize_status']  = $fission['prize_status'];
				$fissionData[$key]['join_time']     = $fission['join_time'];
				$fissionData[$key]['complete_time'] = $fission['complete_time'];
				$fissionData[$key]['black_time']    = $fission['black_time'];
			}

			return [
				'count'           => $count,
				'keys'            => $keys,
				'is_end'          => $is_end,
				'prize_send_type' => $prize_send_type,
				'fission'         => $fissionData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           加入、移出黑名单
		 * @description     加入、移出黑名单
		 * @method   post
		 * @url  http://{host_name}/api/fission/change-black
		 *
		 * @param uid 必选 string 用户id
		 * @param jid 必选 string 参与表id
		 * @param is_black 必选 string 移入黑名单列表：0是、1否
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-19 9:33
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChangeBlack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$jid      = \Yii::$app->request->post('jid', 0);
			$is_black = \Yii::$app->request->post('is_black', 0);
			$is_black = !empty($is_black) ? 0 : 1;
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			if ($is_black == 1) {
				$black_time = DateUtil::getCurrentTime();
				FissionJoin::updateAll(['is_black' => $is_black, 'black_time' => $black_time], ['uid' => $uid, 'id' => $jid, 'prize_status' => [0, 2]]);
			} else {
				foreach ($jid as $id) {
					$joinInfo = FissionJoin::findOne($id);
					if (!empty($joinInfo)) {
						$joinInfo->is_black   = 0;
						$joinInfo->black_time = '';
						$fissionInfo          = $joinInfo->f;
						//活动还在进行时处理
						if (!empty($fissionInfo) && ($fissionInfo->status == 2)) {
							//如果助力数大于等于要求数，则增加活动完成数，若库存不足，则设置奖品无法处理
							if ($joinInfo->status == 1 && $joinInfo->help_num >= $joinInfo->fission_num) {
								$joinInfo->status = 2;
								$prizeRule        = json_decode($fissionInfo->prize_rule, 1);
								$prize_num        = $prizeRule[0]['prize_num'];
								$is_del           = 0;
								if ($fissionInfo->complete_num < $prize_num) {
									$fissionInfo->complete_num += 1;
									$fissionInfo->update();
								} else {
									$joinInfo->prize_status = 2;
								}
								if (($fissionInfo->complete_num >= $prize_num) && !empty($fissionInfo->is_end)) {
									$fissionInfo->status = 4;
									$fissionInfo->update();
									$is_del = 1;
								}
								if (!empty($is_del)) {
									Fission::delConfigId($fissionInfo);
								}
							}
						}
						$joinInfo->update();
					}
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           助力列表
		 * @description     助力列表
		 * @method   post
		 * @url  http://{host_name}/api/fission/help-list
		 *
		 * @param uid 必选 string 用户id
		 * @param jid 必选 string 参与表id
		 * @param page 可选 string 页码，默认1
		 * @param pageSize 可选 string 每页数量，默认10
		 *
		 * @return          {"error":0,"data":{"count":"1","helpList":[{"key":"1","id":"1","name":"宅厨网络","avatar":"http://wx.qlogo.cn/mmhead/1ZqjMhQoRAYonQAYkwJHRO7bRgr9qibYIsPicjSnvjrj4/0","help_time":"2020-03-18 20:33:04"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    helpList array 列表数据
		 * @return_param    id string 列表id
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    help_time string 助力时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-19 9:48
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionHelpList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			$helpData = FissionHelpDetail::find()->alias('fhd');
			$helpData = $helpData->leftJoin('{{%work_external_contact}} wec', '`fhd`.`external_id` = `wec`.`id`');
			$helpData = $helpData->where(['fhd.jid' => $jid, 'fhd.status' => 1]);
			$helpData = $helpData->select('wec.name,wec.avatar,fhd.*');
			$offset   = ($page - 1) * $pageSize;
			$count    = $helpData->count();
			$helpData = $helpData->limit($pageSize)->offset($offset)->asArray()->all();
			$helpList = [];
			foreach ($helpData as $key => $help) {
				$helpList[$key]['key']       = $help['id'];
				$helpList[$key]['id']        = $help['id'];
				$helpList[$key]['name']      = urldecode($help['name']);
				$helpList[$key]['avatar']    = $help['avatar'];
				$helpList[$key]['help_time'] = $help['help_time'];
			}

			return [
				'count'    => $count,
				'helpList' => $helpList,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fission/
		 * @title           奖品处理
		 * @description     奖品处理
		 * @method   post
		 * @url  http://{host_name}/api/fission/prize-status
		 *
		 * @param uid 必选 string 用户id
		 * @param jid 必选 string 参与表id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-19 10:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionPrizeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$jid = \Yii::$app->request->post('jid', 0);
			if (empty($uid) || empty($jid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$fissionJoin = FissionJoin::findOne(['uid' => $uid, 'id' => $jid]);
			if (empty($fissionJoin)) {
				throw new InvalidDataException('此记录不存在！');
			}
			if ($fissionJoin->f->prize_send_type == 2 && !in_array($fissionJoin->f->status, [3, 4, 5])) {
				throw new InvalidDataException('奖品要在活动结束后才能发放！');
			}
			if (empty($fissionJoin->f->prize_type)) {
				$fissionJoin->prize_status = 1;
				if (!$fissionJoin->update()) {
					throw new InvalidDataException(SUtils::modelError($fissionJoin));
				}
			} elseif ($fissionJoin->status == 2 && in_array($fissionJoin->prize_status, [0, 2]) && $fissionJoin->is_black == 0) {//发红包
				try {
					$remark      = '裂变成功，' . $fissionJoin->amount . '元红包拿走，不谢~~~';
					$contactInfo = WorkExternalContact::findOne($fissionJoin->external_id);
					$helpData    = [
						'uid'         => $fissionJoin->f->uid,
						'corp_id'     => $fissionJoin->f->corp_id,
						'rid'         => $fissionJoin->f->id,
						'jid'         => $fissionJoin->id,
						'external_id' => $fissionJoin->external_id,
						'openid'      => $contactInfo->openid,
						'amount'      => $fissionJoin->amount,
						'remark'      => $remark,
					];

					$res = RedPackOrder::sendRedPack($helpData, 2);
					if (!empty($res)) {
						$fissionJoin->prize_status = 1;
						$fissionJoin->update();
						$is_send = 1;
					}
				} catch (InvalidDataException $e) {
					\Yii::error($e->getMessage(), 'handSendHelp');
					throw new InvalidDataException($e->getMessage());
				}

				//补发剩余的
				if (!empty($is_send)) {
					\Yii::$app->queue->delay(10)->push(new SyncFissionJob([
						'fission_id' => $fissionJoin->fid,
						'sendData'   => ['is_all' => 1, 'uid' => $fissionJoin->uid]
					]));
				}
			}

			return true;
		}
	}