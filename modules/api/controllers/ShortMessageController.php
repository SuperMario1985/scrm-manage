<?php
	/**
	 * 短信营销
	 * User: xcy
	 * Date: 2019-12-05
	 * Time: 14:21
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\MessageCustomer;
	use app\models\MessageOrder;
	use app\models\MessagePack;
	use app\models\MessagePush;
	use app\models\MessagePushDetail;
	use app\models\MessageSign;
	use app\models\MessageTemplate;
	use app\models\MessageType;
	use app\models\User;
	use app\modules\api\components\AuthBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\queue\MessageJob;
	use moonland\phpexcel\Excel;
	use yii\web\MethodNotAllowedHttpException;

	use app\util\WxPay\NativePay;

	class ShortMessageController extends AuthBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           短信群发
		 * @description     短信群发
		 * @method   post
		 * @url  http://{host_name}/api/short-message/message
		 *
		 * @param uid 必选 int 登录账号id
		 * @param status 可选 string 状态：-1全部、0未发送、1已发送、2发送失败、3发送中
		 * @param page 可选 string 页数
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"5","message":[{"id":"5","title":"测试3","sign_id":"1","type_id":"1","content":"测试123456","send_type":"1","target_num":"2","arrival_num":"0","push_time":"2019-12-18 11:05:04","status":"0","is_del":"0","key":"5","send_name":"选择已有","status_name":"未发送","type_name":"会员营销"},{"id":"4","title":"测试3","sign_id":"1","type_id":"2","content":"酷虎关怀321","send_type":"2","target_num":"36","arrival_num":"0","push_time":"2019-12-18 10:44:51","status":"0","is_del":"0","key":"4","send_name":"Excel导入","status_name":"未发送","type_name":"客户关怀"}],"restNum":2924,"consume":"0"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 结果数量
		 * @return_param    message array 群发数据
		 * @return_param    id string 群发id
		 * @return_param    title string 消息名称
		 * @return_param    sign_id string 签名id
		 * @return_param    type_id string 类型id
		 * @return_param    content string 发送内容
		 * @return_param    send_type string 发送对象类型
		 * @return_param    target_num string 预计发送个数
		 * @return_param    arrival_num string 实际发送个数
		 * @return_param    push_time string 发送时间
		 * @return_param    status string 状态：0未发送、1已发送、2发送失败、3发送中
		 * @return_param    is_del string 状态：0未删除、1删除
		 * @return_param    key string 键
		 * @return_param    send_name string 发送对象名称
		 * @return_param    status_name string 状态名称
		 * @return_param    type_name string 类型名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 11:07
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMessage ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid', 0);
			$status = \Yii::$app->request->post('status', '');
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$user = User::findOne($uid);
			if (empty($user)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$messagePush = MessagePush::find()->where(['uid' => $uid, 'is_del' => 0]);
			if ($status != '-1') {
				$messagePush = $messagePush->andWhere(['status' => $status]);
			}
			$messagePush = $messagePush->select('id,uid,title,sign_id,type_id,content,send_type,target_num,arrival_num,push_time,status,is_del,error_msg,id key');
			//分页
			$page        = \Yii::$app->request->post('page') ?: 1;
			$pageSize    = \Yii::$app->request->post('pageSize') ?: 10;
			$offset      = ($page - 1) * $pageSize;
			$count       = $messagePush->count();
			$messagePush = $messagePush->orderBy('id desc')->limit($pageSize)->offset($offset)->asArray()->all();
			$messageType = MessageType::find()->asArray()->all();
			$typeArr     = [];
			if (!empty($messageType)) {
				$typeArr = array_column($messageType, 'title', 'id');
			}

			foreach ($messagePush as $k => $mv) {
				if ($mv['send_type'] == 1) {
					$send_name = '选择已有';
				} elseif ($mv['send_type'] == 2) {
					$send_name = 'Excel导入';
				} elseif ($mv['send_type'] == 3) {
					$send_name = '输入手机号';
				}
				if ($mv['status'] == 0) {
					$status = '未发送';
				} elseif ($mv['status'] == 1) {
					$status = '已发送';
				} elseif ($mv['status'] == 2) {
					$status = '发送失败';
				} elseif ($mv['status'] == 3) {
					$status = '发送中';
				}
				$messagePush[$k]['send_name']   = $send_name;
				$messagePush[$k]['status_name'] = $status;
				$messagePush[$k]['type_name']   = !empty($typeArr[$mv['type_id']]) ? $typeArr[$mv['type_id']] : '';
			}

			//消耗短信数量
			$consume = MessagePushDetail::find()->where(['uid' => $uid, 'status' => [0, 1, 3, 4]])->sum('num');

			//超过72小时的还在发送的短信置为未知状态
			$time = strtotime('-72 hours');
			$date = date('Y-m-d H:i:s', $time);
			MessagePushDetail::updateAll(['status' => 4], ['and', ['uid' => $uid, 'status' => 3], ['<', 'push_time', $date]]);

			return [
				'count'   => $count,
				'message' => $messagePush,
				'restNum' => $user->message_num,
				'consume' => !empty($consume) ? $consume : 0
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           创建短信群发时数据
		 * @description     创建短信群发时数据
		 * @method   post
		 * @url  http://{host_name}/api/short-message/push-data
		 *
		 * @param uid 必选 int 登录账号id
		 *
		 * @return          {"error":0,"data":{"signArr":[{"id":1,"title":"小猪科技"},{"id":4,"title":"测试"}],"typeArr":[{"id":1,"title":"会员营销","status":1},{"id":2,"title":"客户关怀","status":1}],"templateArr":[{"id":1,"content":"测试123456"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    signArr array 签名数据
		 * @return_param    id string 签名id
		 * @return_param    title string 签名名称
		 * @return_param    typeArr array 类型数据
		 * @return_param    id string 类型id
		 * @return_param    title string 类型名称
		 * @return_param    templateArr array 模版数据
		 * @return_param    id string 模版id
		 * @return_param    content string 模版内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 11:30
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPushData ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			//短信签名
			$signArr = MessageSign::find()->where(['or', ['uid' => $uid], ['uid' => NULL]])->andWhere(['status' => 1])->select('id,title')->all();
			//发送场景
			$typeArr = MessageType::find()->where(['status' => 1])->all();
			//短信内容列表
			$templateArr = MessageTemplate::find()->where(['or', ['uid' => $uid], ['uid' => NULL]])->andWhere(['status' => 1]);
			if (!empty($signArr)) {
				$templateArr = $templateArr->andWhere(['or', ['sign_id' => $signArr[0]['id']], ['sign_id' => NULL]]);
			}
			if (!empty($typeArr)) {
				$templateArr = $templateArr->andWhere(['type_id' => $typeArr[0]['id']]);
			}
			$templateArr = $templateArr->select('id,content')->all();

			return [
				'signArr'     => $signArr,
				'typeArr'     => $typeArr,
				'templateArr' => $templateArr,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           根据类型获取模版内容
		 * @description     根据类型获取模版内容
		 * @method   post
		 * @url  http://{host_name}/api/short-message/get-template
		 *
		 * @param uid 必选 int 登录账号id
		 * @param sign_id 必选 int 短信签名id
		 * @param type_id 必选 int 短信类型id
		 *
		 * @return          {"error":0,"data":{"templateArr":[{"id":1,"content":"测试123456"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    templateArr array 模版数据
		 * @return_param    id string 模版id
		 * @return_param    content string 模版内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 11:37
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetTemplate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$sign_id = \Yii::$app->request->post('sign_id', 0);
			$type_id = \Yii::$app->request->post('type_id', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$templateArr = MessageTemplate::find()->where(['or', ['uid' => $uid], ['uid' => NULL]])->andWhere(['status' => 1]);
			if (!empty($sign_id)) {
				$templateArr = $templateArr->andWhere(['or', ['sign_id' => $sign_id], ['sign_id' => NULL]]);
			}
			if (!empty($type_id)) {
				$templateArr = $templateArr->andWhere(['type_id' => $type_id]);
			}
			$templateArr = $templateArr->select('id,content')->all();

			return ['templateArr' => $templateArr];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           添加短信群发
		 * @description     添加短信群发
		 * @method   post
		 * @url  http://{host_name}/api/short-message/add-push
		 *
		 * @param uid 必选 string 登录账号id
		 * @param title 必选 string 消息名称
		 * @param sign_id 必选 string 签名id
		 * @param type_id 必选 string 类型id
		 * @param content 必选 string 短信内容
		 * @param send_type 必选 string 发送对象类型：1、选择已有，2、excel导入，3、手动填写
		 * @param customerIds 可选 array 选择已有：send_type=1时有数据
		 * @param exportPhone 可选 array excel导入：send_type=2时有数据
		 * @param phoneTxt 可选 string 手动填写：send_type=3时有数据
		 * @param push_type 必选 string 群发时间设置：1立即发送、2指定时间
		 * @param push_time 必选 string 群发时间
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 11:41
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAddPush ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			$uid      = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$time      = time();
			$push_type = \Yii::$app->request->post('push_type', '');
			$push_time = \Yii::$app->request->post('push_time', '');
			try {
				$kf_id = MessagePush::setData($postData);
				if (!empty($kf_id)) {
					if ($push_type == 1) {
						$jobId = \Yii::$app->queue->push(new MessageJob([
							'message_push_id' => $kf_id
						]));
					} else {
						//指定时间发送
						$second = $push_time - $time;
						$jobId  = \Yii::$app->queue->delay($second)->push(new MessageJob([
							'message_push_id' => $kf_id
						]));
					}
					$messagePush           = MessagePush::findOne($kf_id);
					$messagePush->queue_id = $jobId;
					$messagePush->save();

					return true;
				} else {
					throw new InvalidDataException('创建失败');
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           短信群发明细
		 * @description     短信群发明细
		 * @method   post
		 * @url  http://{host_name}/api/short-message/push-detail
		 *
		 * @param uid 必选 int 登录账号id
		 * @param push_id 可选 string 群发id
		 * @param push_time 可选 string 发送时间
		 * @param title 可选 string 消息名称
		 * @param status 可选 string 状态：-1全部、0未发送、1已发送、2发送失败、3发送中
		 * @param page 可选 string 页数
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"1","detail":[{"id":"1","uid":"2","message_id":"1","title":"123","phone":"18505607671","sign_name":"111","type_name":"111","content":"111","status":"1","push_time":"2019-12-18 15:18:39","error_code":"0","error_msg":"","key":"1"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    detail array 群发明细
		 * @return_param    id string 群发明细id
		 * @return_param    id string 群发明细id
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 14:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPushDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$detail  = MessagePushDetail::find()->where(['uid' => $uid]);
			$push_id = \Yii::$app->request->post('push_id', '');
			if (!empty($push_id)) {
				$detail = $detail->andWhere(['message_id' => $push_id]);
			}
			$title = \Yii::$app->request->post('title', '');
			if (!empty($title)) {
				$detail = $detail->andWhere(['like', 'title', $title]);
			}
			$phone = \Yii::$app->request->post('phone', '');
			if (!empty($phone)) {
				$detail = $detail->andWhere(['like', 'phone', $phone]);
			}
			$push_time = \Yii::$app->request->post('push_time', '');
			if (!empty($push_time)) {
				$start_date = $push_time;
				$end_date   = $push_time . ' 23:59:59';
				$detail     = $detail->andWhere(['between', 'push_time', $start_date, $end_date]);
			}
			$status = \Yii::$app->request->post('status', '');
			if ($status != '-1') {
				$detail = $detail->andWhere(['status' => $status]);
			}
			$detail = $detail->select('id,title,push_time,phone,title,type_name,content,status,id key');
			//分页
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;
			$offset   = ($page - 1) * $pageSize;
			$count    = $detail->count();
			$detail   = $detail->orderBy('id desc')->limit($pageSize)->offset($offset)->asArray()->all();

			return [
				'count'  => $count,
				'detail' => $detail,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           删除群发记录
		 * @description     删除群发记录
		 * @method   post
		 * @url  http://{host_name}/api/short-message/push-delete
		 *
		 * @param uid 必选 int 登录账号id
		 * @param push_id 必选 string 群发id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 14:39
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPushDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$push_id = \Yii::$app->request->post('push_id', 0);
			if (empty($push_id) || empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$push = MessagePush::findOne($push_id);
			if (empty($push)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$push->is_del = 1;
			if (!$push->validate() || !$push->save()) {
				throw new InvalidDataException(SUtils::modelError($push));
			}
			//如果状态时未发送，删除返还短信数
			if ($push->status == 0) {
				$signInfo    = MessageSign::findOne($push->sign_id);
				$contentStr  = $push->content . '回T退订【' . $signInfo->title . '】';
				$length      = mb_strlen($contentStr, 'utf-8');
				$user        = User::findOne($uid);
				$num         = ceil($length / 66);//营销短信按照66字/每条
				$message_num = $num * $push->target_num;
				$user->updateCounters(['message_num' => +$message_num]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           短信订单
		 * @description     短信订单
		 * @method   post
		 * @url  http://{host_name}/api/short-message/push-order
		 *
		 * @param uid 必选 int 登录账号id
		 * @param search 可选 string 搜索值
		 * @param page 可选 string 页数
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"3","detail":[{"id":"22","order_id":"2220191217170554700236","goods_id":"1","goods_name":"短信包1000条","goods_price":"0.01","paytime":"2019-12-17 17:06:39","key":"22"},{"id":"7","order_id":"2220191213170557694430","goods_id":"1","goods_name":"短信包1000条","goods_price":"0.01","paytime":"2019-12-13 17:06:25","key":"7"},{"id":"6","order_id":"2220191213170248957816","goods_id":"1","goods_name":"短信包1000条","goods_price":"0.01","paytime":"2019-12-13 17:03:23","key":"6"}],"packArr":[{"key":1,"price":"0.01","num":1000,"txt":"低至1.0E-5元/条"},{"key":2,"price":"10.00","num":2000,"txt":"低至0.005元/条"}],"pack_id":1,"nowPrice":"0.01","payee":"久绿园艺"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数
		 * @return_param    detail array 订单数据
		 * @return_param    id string 订单id
		 * @return_param    key string 键
		 * @return_param    order_id string 订单order_id
		 * @return_param    goods_name string 产品名称
		 * @return_param    goods_price string 产品价格
		 * @return_param    paytime string 支付时间
		 * @return_param    packArr array 短信包
		 * @return_param    key string 短信包id
		 * @return_param    price string 短信包价格
		 * @return_param    num string 短信包数量
		 * @return_param    txt string 短信包显示文本
		 * @return_param    pack_id string 默认显示短信包id
		 * @return_param    nowPrice string 默认显示短信包价格
		 * @return_param    payee string 收款方
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 14:04
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPushOrder ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$search       = \Yii::$app->request->post('search', 0);
			$messageOrder = MessageOrder::find()->where(['uid' => $uid, 'ispay' => 1]);
			if (!empty($search)) {
				$messageOrder = $messageOrder->andWhere(['or', ['like', 'goods_name', $search], ['like', 'order_id', $search]]);
			}
			$messageOrder = $messageOrder->select('id,order_id,goods_id,goods_name,goods_price,paytime,id key');
			//分页
			$page        = \Yii::$app->request->post('page') ?: 1;
			$pageSize    = \Yii::$app->request->post('pageSize') ?: 10;
			$offset      = ($page - 1) * $pageSize;
			$count       = $messageOrder->count();
			$detail      = $messageOrder->orderBy('id desc')->limit($pageSize)->offset($offset)->asArray()->all();
			$messagePack = MessagePack::find()->where(['status' => 1])->all();
			$packArr     = [];
			$pack_id     = 0;
			$nowPrice    = "";
			foreach ($messagePack as $k => $pack) {
				if ($k == 0) {
					$pack_id  = $pack['id'];
					$nowPrice = $pack['price'];
				}
				$price = $pack['price'] / $pack['num'];
				if ($price < 0.0001) {
					$price = number_format($price, strlen($price) - 1);
				}
				$txt         = '低至' . $price . '元/条';
				$packArr[$k] = ['key' => $pack['id'], 'price' => $pack['price'], 'num' => $pack['num'], 'txt' => $txt];
			}
			$payee = \Yii::$app->params['weixin']['payee'];

			return [
				'count'    => $count,
				'detail'   => $detail,
				'packArr'  => $packArr,
				'pack_id'  => $pack_id,
				'nowPrice' => $nowPrice,
				'payee'    => $payee,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           生成订单
		 * @description     生成订单
		 * @method   post
		 * @url  http://{host_name}/api/short-message/post-order
		 *
		 * @param uid 必选 string 登录账号id
		 * @param pack_id 必选 string 短信包id
		 *
		 * @return          {"error":0,"data":{"ewmUrl":"weixin://wxpay/bizpayurl?pr=agdCFHj","order_id":30}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    ewmUrl string 二维码地址
		 * @return_param    order_id string 订单id
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 13:47
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPostOrder ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$pack_id = \Yii::$app->request->post('pack_id', 0);
			if (empty($pack_id)) {
				throw new InvalidDataException('请选择短信包！');
			}
			$messagePack = MessagePack::findOne(['id' => $pack_id, 'status' => 1]);
			if (empty($messagePack)) {
				throw new InvalidDataException('此短信包不存在！');
			}
			try {
				$order_id                  = '22' . date('YmdHis') . mt_rand(111111, 999999);
				$messageOrder              = new MessageOrder();
				$messageOrder->uid         = $uid;
				$messageOrder->order_id    = $order_id;
				$messageOrder->pay_way     = 'weixin';
				$messageOrder->pay_type    = 'wxsaoma2pay';
				$messageOrder->goods_type  = 'messagePay';
				$messageOrder->goods_id    = $messagePack->id;
				$messageOrder->goods_name  = '短信包' . $messagePack->num . '条';
				$messageOrder->goods_price = $messagePack->price;
				$messageOrder->add_time    = DateUtil::getCurrentTime();
				$messageOrder->ispay       = 0;
				$extraInfo                 = ['message_num' => $messagePack->num];
				$messageOrder->extrainfo   = json_encode($extraInfo, JSON_UNESCAPED_UNICODE);
				if (!$messageOrder->validate() || !$messageOrder->save()) {
					throw new InvalidDataException(SUtils::modelError($messageOrder));
				}
				$orderData               = $messageOrder->attributes;
				$site_url                = \Yii::$app->params['site_url'];
				$orderData['notify_url'] = $site_url . '/pay-return/index';
				$nativePay               = new NativePay($orderData);
				$ewmUrl2Arr              = $nativePay->GetPayUrl($orderData);
				if (!empty($ewmUrl2Arr) && !empty($ewmUrl2Arr['code_url'])) {
					return ['ewmUrl' => $ewmUrl2Arr['code_url'], 'order_id' => $messageOrder->id];
				} else {
					$msg = '二维码生成失败';
					if (!empty($ewmUrl2Arr['return_msg'])) {
						$msg = $ewmUrl2Arr['return_msg'];
					}
					if (($ewmUrl2Arr['result_code'] != 'SUCCESS') && !empty($ewmUrl2Arr['err_code_des'])) {
						$msg = $ewmUrl2Arr['err_code_des'];
					}
					throw new InvalidDataException($msg);
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           查询订单状态
		 * @description     查询订单状态
		 * @method   post
		 * @url  http://{host_name}/api/short-message/get-order-status
		 *
		 * @param uid 必选 int 登录账号id
		 * @param order_id 必选 int 订单id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 13:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetOrderStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$order_id = \Yii::$app->request->post('order_id', 0);
			if (empty($uid) || empty($order_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$order = MessageOrder::findOne($order_id);
			if ($order->ispay == 1) {
				return true;
			}

			return false;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           获取客户数据
		 * @description     获取客户数据
		 * @method   post
		 * @url  http://{host_name}/api/short-message/get-phone
		 *
		 * @param uid 必选 int 登录账号id
		 * @param page 可选 string 页数
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"38","customerArr":[{"id":"1","phone":"18505607671","name":"","nickname":"","sex":"0","remark":"","key":"1"},{"id":"2","phone":"18324586924","name":"xcy","nickname":"ceshi","sex":"2","remark":"","key":"2"},{"id":"3","phone":"18505607672","name":"","nickname":"","sex":"0","remark":"","key":"3"},{"id":"4","phone":"18504607671","name":"测试4","nickname":"鹅鹅鹅饿","sex":"1","remark":"","key":"4"}],"keysArr":["1","2","3","4","39","40","41","42","43","44","45","46","47","48","49","50","51","52","53","54","55","56","57","58","59","60","61","62","63","64","65","66","67","68","69","70","71","72"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    customerArr array 客户数据
		 * @return_param    id string 客户id
		 * @return_param    phone string 手机号
		 * @return_param    name string 姓名
		 * @return_param    nickname string 微信昵称
		 * @return_param    sex string 性别，0：未知、1：男、2：女
		 * @return_param    remark string 备注
		 * @return_param    key string 键
		 * @return_param    keysArr array 客户id数组键值
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-18 11:52
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetPhone ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$customer = MessageCustomer::find()->where(['uid' => $uid])->select('id,phone,name,nickname,sex,remark,id key');
			//分页
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;
			$offset   = ($page - 1) * $pageSize;
			$count    = $customer->count();
			$customer = $customer->limit($pageSize)->offset($offset)->asArray()->all();

			$keyList = MessageCustomer::find()->where(['uid' => $uid])->select('id')->asArray()->all();
			$keysArr = [];
			if (!empty($keyList)) {
				$keysArr = array_column($keyList, 'id');
			}

			return [
				'count'       => $count,
				'customerArr' => $customer,
				'keysArr'     => $keysArr
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           客户管理
		 * @description     客户管理
		 * @method   post
		 * @url  http://{host_name}/api/short-message/customer
		 *
		 * @param uid 必选 int 登录账号id
		 * @param phone 可选 string 手机号
		 *
		 * @return          {"error":0,"data":{"count":"2","customer":[{"id":"3","phone":"18505607672","name":"","nickname":"","sex":"0","remark":""},{"id":"2","phone":"18505607671","name":"","nickname":"","sex":"0","remark":""}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数
		 * @return_param    customer array 客户数据
		 * @return_param    id string 客户id
		 * @return_param    phone string 手机号
		 * @return_param    name string 姓名
		 * @return_param    nickname string 微信昵称
		 * @return_param    sex string 性别：0、未知，1、男，2、女
		 * @return_param    remark string 备注
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-06 13:16
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			$uid   = \Yii::$app->request->post('uid', 0);
			$phone = \Yii::$app->request->post('phone', '');
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$customer = MessageCustomer::find()->where(['uid' => $uid])->select('id,uid,phone,name,nickname,sex,remark');
			if (!empty($phone)) {
				$customer = $customer->andWhere(['like', 'phone', $phone]);
			}
			$customer = $customer->select('*,id key');
			//分页
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;
			$offset   = ($page - 1) * $pageSize;
			$count    = $customer->count();
			$customer = $customer->orderBy('id desc')->limit($pageSize)->offset($offset)->asArray()->all();

			return [
				'count'    => $count,
				'customer' => $customer,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           添加客户
		 * @description     添加客户
		 * @method   post
		 * @url  http://{host_name}/api/short-message/add-customer
		 *
		 * @param uid 必选 int 登录账号id
		 * @param import 必选 int 导入类型：0、文本导入，1、excel导入
		 *
		 * @return          {"error":0,"data":{"insertNum":1,"skipNum":1}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    insertNum int 成功个数
		 * @return_param    skipNum int 失败个数
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-06 13:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionAddCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			$uid      = \Yii::$app->request->post('uid', 0);
			$import   = \Yii::$app->request->post('is_import', 0);
			$comefrom = \Yii::$app->request->post('comefrom', 0);//来源：0，客户管理、1，短信群发导入
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$insertNum = $skipNum = $skipPhoneNum = 0;
			if ($import == 0) {
				$phoneTxt = \Yii::$app->request->post('phoneTxt', '');
				if (empty($phoneTxt)) {
					throw new InvalidDataException('请填写手机号');
				}
				$phoneTxt = str_replace('，', ',', $phoneTxt);
				$phoneTxt = trim($phoneTxt,',');
				$phoneArr = explode(',', $phoneTxt);
				foreach ($phoneArr as $phone) {
					$type = MessageCustomer::setCustomer(['uid' => $uid, 'phone' => $phone]);
					switch ($type) {
						case 'insert':
							$insertNum++;
							break;
						case 'skip':
							$skipNum++;
							break;
						case 'skipPhone':
							$skipPhoneNum++;
							break;
					}
				}
			} else {
				if (!empty($_FILES['importFile']['name'])) {
					$fileTypes = explode(".", $_FILES['importFile']['name']);
					$fileType  = $fileTypes [count($fileTypes) - 1];
					/*判别是不是.xls .xlsx文件，判别是不是excel文件*/
					if (strtolower($fileType) != "xls" && strtolower($fileType) != "xlsx") {
						throw new InvalidDataException('文件类型不对！');
					}
					$fileTmpPath = $_FILES['importFile']['tmp_name'];
					$excelData   = Excel::import($fileTmpPath, [
						'setFirstRecordAsKeys' => false
					]);

					$importData = $excelData[0];

					if (!empty($importData[1])) {
						$header = $importData[1];
						if ($header['A'] != '手机号' || $header['B'] != '姓名' || $header['C'] != '微信昵称' || $header['D'] != '性别' || $header['E'] != '备注') {
							throw new InvalidDataException('数据格式不对，请保留标题！');
						}
					} else {
						throw new InvalidDataException('数据格式不对，请保留标题！');
					}
					$count = count($importData);
					if ($count < 2) {
						throw new InvalidDataException('请在文件内添加要导入的数据！');
					} else if ($count > 1001) {
						throw new InvalidDataException('文件内行数不能超过1000行！');
					}
					if (!empty($comefrom)) {//从短信群发导入的直接返回数据
						$phoneArr = [];
						foreach ($importData as $k => $data) {
							if ($k == 1) {
								continue;
							}
							$phone = trim($data['A']);
							if (!empty($phone)) {
								if (preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $phone)) {
									array_push($phoneArr, $phone);
								}
							}
						}
						if (count($phoneArr) < 2) {
							throw new InvalidDataException('导入的手机号最少要2个！');
						}

						return $phoneArr;
					}
					foreach ($importData as $k => $data) {
						if ($k == 1) {
							continue;
						}
						$type = MessageCustomer::setCustomer(['uid' => $uid, 'phone' => $data['A'], 'name' => $data['B'], 'nickname' => $data['C'], 'sex' => $data['D'], 'remark' => $data['E']]);
						switch ($type) {
							case 'insert':
								$insertNum++;
								break;
							case 'skip':
								$skipNum++;
								break;
							case 'skipPhone':
								$skipPhoneNum++;
								break;
						}
					}
				} else {
					throw new InvalidDataException('请上传文件！');
				}
			}
			$textHtml = '本次';
			if (!empty($insertNum)) {
				$textHtml .= '导入成功' . $insertNum . '条，';
			}
			if (!empty($skipNum)) {
				$textHtml .= '忽略' . $skipNum . '条（已有的），';
			}
			if (!empty($skipPhoneNum)) {
				$textHtml .= $skipPhoneNum . '条手机号格式不正确，';
			}
			$textHtml = trim($textHtml, '，');

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           签名管理
		 * @description     签名管理
		 * @method   post
		 * @url  http://{host_name}/api/short-message/sign
		 *
		 * @param uid 必选 int 登录账号id
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":"2","sign":[{"id":"2","uid":"6","title":"测试","status":"2","error_msg":"1111111","apply_time":"2019-12-05 15:07:05"},{"id":"1","uid":null,"title":"小猪科技","status":"1","error_msg":"","apply_time":"2019-12-03 15:14:46"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    sign array 签名数据
		 * @return_param    id string 签名id
		 * @return_param    uid string 用户id
		 * @return_param    title string 签名名称
		 * @return_param    error_msg string 失败原因
		 * @return_param    apply_time string 申请时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-05 15:26
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSign ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$messageSign = MessageSign::find()->where(['or', ['uid' => $uid], ['uid' => NULL]]);
			$messageSign = $messageSign->andWhere(['>=', 'status', '0']);
			$messageSign = $messageSign->select('*,id key');
			$messageSign = $messageSign->orderBy('id desc');
			//分页
			$page        = \Yii::$app->request->post('page') ?: 1;
			$pageSize    = \Yii::$app->request->post('pageSize') ?: 10;
			$offset      = ($page - 1) * $pageSize;
			$count       = $messageSign->count();
			$messageSign = $messageSign->limit($pageSize)->offset($offset)->asArray()->all();

			return [
				'count' => $count,
				'sign'  => $messageSign,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           签名申请
		 * @description     签名申请
		 * @method   post
		 * @url  http://{host_name}/api/short-message/sign-add
		 *
		 * @param uid 必选 int 登录账号id
		 * @param title 必选 string 签名名称
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-05 16:18
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSignAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid   = \Yii::$app->request->post('uid', 0);
			$title = \Yii::$app->request->post('title', '');
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($title)) {
				throw new InvalidParameterException('请填写要申请的签名！');
			} else {
				$mb_strlen = mb_strlen($title, 'utf-8');
				if ($mb_strlen < 2 || $mb_strlen > 8) {
					throw new InvalidDataException('申请的签名只能是2-8个字符！');
				}
				$strlen = strlen($title);
				if ($mb_strlen == $strlen) {
					throw new InvalidDataException('申请的签名不能为纯英文或纯数字！');
				}
			}
			$title = trim($title);
			$sign  = MessageSign::find()->where(['or', ['uid' => $uid], ['uid' => NULL]])->andWhere(['title' => $title, 'status' => [0, 1, 2]])->one();
			if (!empty($sign)) {
				throw new InvalidDataException('签名已存在，请更换');
			}
			$sign             = new MessageSign();
			$sign->uid        = $uid;
			$sign->title      = $title;
			$sign->apply_time = DateUtil::getCurrentTime();;
			$sign->status = 0;
			if (!$sign->validate() || !$sign->save()) {
				throw new InvalidDataException(SUtils::modelError($sign));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           签名删除
		 * @description     签名删除
		 * @method   post
		 * @url  http://{host_name}/api/short-message/sign-delete
		 *
		 * @param sign_id 必选 int 签名id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-05 16:20
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSignDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$sign_id = \Yii::$app->request->post('sign_id', 0);
			if (empty($sign_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$sign = MessageSign::findOne($sign_id);
			if (empty($sign)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$sign->status = -1;
			if (!$sign->validate() || !$sign->save()) {
				throw new InvalidDataException(SUtils::modelError($sign));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           模版管理
		 * @description     模版管理
		 * @method   post
		 * @url  http://{host_name}/api/short-message/user-template
		 *
		 * @param uid 必选 string 登录账号id
		 * @param start_date 可选 string 开始日期
		 * @param end_date 可选 string 结束日期
		 * @param status 可选 string 状态，-1：全部、0：待审核、1：已审核、2：审核失败
		 *
		 * @return          {"error":0,"data":{"count":"3","templateArr":[{"status_name":"待审核","id":"7","key":"7","status":"0","content":"12月26日，国内可看要十分注意眼睛的安全。","error_msg":"","apply_time":"2019-12-26 14:30:03","sign_name":"小猪科技","type_name":"会员营销"},{"status_name":"已通过","id":"6","key":"6","status":"1","content":"由于寒冬天气，使车况恢复一下再出发。","error_msg":"","apply_time":"2019-12-25 17:04:20","sign_name":"小猪科技","type_name":"客户关怀"},{"status_name":"待审核","id":"5","key":"5","status":"0","content":"圣诞节了，今晚不加班1","error_msg":"内容不符合规则","apply_time":"2019-12-25 15:47:32","sign_name":"小猪科技","type_name":"会员营销"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    templateArr array 模版数据
		 * @return_param    status_name string 状态名称
		 * @return_param    id string 模版id
		 * @return_param    key string 模版key
		 * @return_param    status string 状态值
		 * @return_param    content string 模版内容
		 * @return_param    error_msg string 错误信息
		 * @return_param    apply_time string 申请时间
		 * @return_param    sign_name string 签名名称
		 * @return_param    type_name string 类型名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-26 19:31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUserTemplate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$template = MessageTemplate::find()->where(['uid' => $uid, 'status' => [0, 1, 2]]);
			//申请时间
			$start_date = \Yii::$app->request->post('start_date', '');
			$end_date   = \Yii::$app->request->post('end_date', '');
			if (!empty($start_date) && !empty($end_date)) {
				$start_date = $start_date . ' 00:00:00';
				$end_date   = $end_date . ' 23:59:59';
				$template   = $template->andWhere(['between', 'apply_time', $start_date, $end_date]);
			}
			//状态
			$status = \Yii::$app->request->post('status', '-1');
			if ($status != '-1') {
				$template = $template->andWhere(['status' => $status]);
			}

			$template = $template->orderBy('id desc');
			//分页
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;
			$offset   = ($page - 1) * $pageSize;
			$count    = $template->count();
			$template = $template->limit($pageSize)->offset($offset)->asArray()->all();
			//短信签名
			$messageSign = MessageSign::find()->where(['or', ['uid' => $uid], ['uid' => NULL]])->andWhere(['status' => 1])->select('id,title')->all();
			$signArr     = [];
			if (!empty($messageSign)) {
				$signArr = array_column($messageSign, 'title', 'id');
			}
			//短信类型
			$messageType = MessageType::find()->asArray()->all();
			$typeArr     = [];
			if (!empty($messageType)) {
				$typeArr = array_column($messageType, 'title', 'id');
			}
			$templateArr = [];
			foreach ($template as $tk => $tv) {
				$status_name = '';
				if ($tv['status'] == 0) {
					$status_name = '待审核';
				} elseif ($tv['status'] == 1) {
					$status_name = '已通过';
				} elseif ($tv['status'] == 2) {
					$status_name = '未通过';
				}
				$templateArr[$tk]['status_name'] = $status_name;
				$templateArr[$tk]['id']          = $tv['id'];
				$templateArr[$tk]['key']         = $tv['id'];
				$templateArr[$tk]['status']      = $tv['status'];
				$templateArr[$tk]['content']     = $tv['content'];
				$templateArr[$tk]['error_msg']   = $tv['error_msg'];
				$templateArr[$tk]['apply_time']  = $tv['apply_time'];
				$templateArr[$tk]['sign_name']   = !empty($signArr[$tv['sign_id']]) ? $signArr[$tv['sign_id']] : '--';
				$templateArr[$tk]['type_name']   = !empty($typeArr[$tv['type_id']]) ? $typeArr[$tv['type_id']] : '--';
			}

			return [
				'count'       => $count,
				'templateArr' => $templateArr,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           申请短信模版
		 * @description     申请短信模版
		 * @method   请求方式
		 * @url  http://{host_name}/api/short-message/add-template
		 *
		 * @param uid 必选 string 登录账号id
		 * @param sign_id 必选 string 签名id
		 * @param type_id 必选 string 短信类型id
		 * @param content 必选 string 短信内容
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-26 20:01
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAddTemplate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$sign_id = \Yii::$app->request->post('sign_id', 0);
			if (empty($sign_id)) {
				throw new InvalidParameterException('请选择短信签名！');
			}
			$type_id = \Yii::$app->request->post('type_id', 0);
			if (empty($type_id)) {
				throw new InvalidParameterException('请选择发送场景！');
			}
			$content = \Yii::$app->request->post('content', '');
			$content = trim($content);
			if (empty($content)) {
				throw new InvalidParameterException('请填写短信内容！');
			}
			$template              = new MessageTemplate();
			$template->uid         = $uid;
			$template->sign_id     = $sign_id;
			$template->type_id     = $type_id;
			$template->content     = $content;
			$template->status      = 0;
			$template->create_time = DateUtil::getCurrentTime();
			$template->apply_time  = DateUtil::getCurrentTime();
			if (!$template->validate() || !$template->save()) {
				throw new InvalidDataException(SUtils::modelError($template));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           删除模版
		 * @description     删除模版
		 * @method   post
		 * @url  http://{host_name}/api/short-message/template-delete
		 *
		 * @param uid 必选 string 登录账号id
		 * @param template_id 必选 string 短信模版id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-26 20:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionTemplateDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$template_id = \Yii::$app->request->post('template_id', 0);
			if (empty($template_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$template = MessageTemplate::findOne($template_id);
			if (empty($template)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$template->status = -1;
			if (!$template->validate() || !$template->save()) {
				throw new InvalidDataException(SUtils::modelError($template));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/short-message/
		 * @title           修改短信模版
		 * @description     修改短信模版
		 * @method   post
		 * @url  http://{host_name}/modules/controller/actionEditTemplate
		 *
		 * @param uid 必选 string 登录账号id
		 * @param template_id 必选 string 短信模版id
		 * @param content 必选 string 短信内容
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-26 20:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionEditTemplate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$template_id = \Yii::$app->request->post('template_id', 0);
			if (empty($template_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$content = \Yii::$app->request->post('content', '');
			$content = trim($content);
			if (empty($content)) {
				throw new InvalidDataException('短信内容不能为空！');
			}
			$template = MessageTemplate::findOne($template_id);
			if (empty($template)) {
				throw new InvalidParameterException('参数不正确！');
			} else {
				if ($template->status == 1) {
					throw new InvalidDataException('审核通过的模版不能进行修改！');
				}
			}
			$template->content   = $content;
			$template->status    = 0;
			$template->error_msg = '';
			if (!$template->validate() || !$template->save()) {
				throw new InvalidDataException(SUtils::modelError($template));
			}

			return true;
		}
	}