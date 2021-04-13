<?php

	namespace app\modules\api\controllers;

	use app\models\AuthoritySubUserDetail;
	use app\models\MoneyOrder;
	use app\models\MoneyPayconfig;
	use app\models\MoneySet;
	use app\models\RedPackChatSendRule;
	use app\models\User;
	use app\models\WorkChat;
	use app\models\WorkFollowMsg;
	use app\models\WorkUser;
	use app\models\UserProfile;
	use app\models\SubUserProfile;
	use app\models\CustomFieldValue;
	use app\models\WorkCorpAgent;
	use app\models\WorkDepartment;
	use app\models\WorkUserCommissionRemind;
	use app\models\WorkUserDelFollowUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use app\util\UploadFileUtil;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidDataException;
	use moonland\phpexcel\Excel;
	use yii\db\Expression;

	class SendMoneyManageController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包记录
		 * @description     红包记录列表
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/send-money-list
		 *
		 * @param uid        必选 string 用户ID
		 * @param corp_id    必选 string 企业的唯一ID
		 * @param phone      可选 int 手机号
		 * @param user_id    可选 int 员工id
		 * @param send_stime 可选 string 起始发送时间
		 * @param send_etime 可选 string 结束发送时间
		 * @param order_id   可选 string 订单号
		 * @param shop       可选 string 店铺
		 * @param account    可选 string 购物账号
		 * @param name       可选 string 客户姓名、昵称
		 * @param remark     可选 string 员工备注、留言
		 * @param status     可选 int 领取状态-1全部0待领取1已领取2已过期4已过期
		 * @param send_type  可选 int 发送对象-1全部1客户群2客户
		 * @param chat_id    可选 int 群id
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int  数据条数
		 * @return_param    money array 数据列表
		 * @return_param    money.user_name string 员工姓名
		 * @return_param    money.send_time string 发送时间
		 * @return_param    money.name string 客户姓名
		 * @return_param    money.nickname string 客户昵称
		 * @return_param    money.phone string 客户手机号
		 * @return_param    money.money string 金额
		 * @return_param    money.order_id string 商户订单号
		 * @return_param    money.transaction_id string 微信订单号
		 * @return_param    money.shop string 店铺
		 * @return_param    money.account string 购物账号
		 * @return_param    money.remark string 员工备注
		 * @return_param    money.message string 留言
		 * @return_param    money.statusName string 领取状态
		 * @return_param    money.typeName string 类型
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-08
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSendMoneyList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid        = \Yii::$app->request->post('uid', 0);
			$phone      = \Yii::$app->request->post('phone');
			$user_id    = \Yii::$app->request->post('user_id', '');
			$send_stime = \Yii::$app->request->post('send_stime', '');
			$send_etime = \Yii::$app->request->post('send_etime', '');
			$order_id   = \Yii::$app->request->post('order_id', '');
			$shop       = \Yii::$app->request->post('shop', '');
			$account    = \Yii::$app->request->post('account', '');
			$name       = \Yii::$app->request->post('name', '');
			$remark     = \Yii::$app->request->post('remark', '');
			$status     = \Yii::$app->request->post('status', '-1');
			$send_type  = \Yii::$app->request->post('send_type', '-1');
			$chat_id    = \Yii::$app->request->post('chat_id', 0);
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$is_export  = \Yii::$app->request->post('is_export', 0);
			$is_all     = \Yii::$app->request->post('is_all', 0);
			$name       = trim($name);
			$phone      = trim($phone);
			$remark     = trim($remark);
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($user_id)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				$user_id  = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
				if(empty($user_id)){
					return [
						"count"=>0,
						"money"=>[],
					];
				}
			}

			//子账户范围限定
			if (empty($user_id) && isset($this->subUser->sub_id)) {
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if($sub_detail === false){
					return [
						"count"=>0,
						"money"=>[],
					];
				}
				if($sub_detail === true){
					$user_id = '';
				}
				if(is_array($sub_detail)){
					$user_id = $sub_detail;
				}
			}

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			$offset    = ($page - 1) * $pageSize;
			$moneyData = MoneyOrder::find()->alias('m');
			$moneyData = $moneyData->leftJoin('{{%work_external_contact}} we', '`we`.`id`=`m`.`external_id`');
			$moneyData = $moneyData->andWhere(['m.uid' => $uid, 'm.corp_id' => $this->corp['id']])->andWhere(['m.goods_type' => ['sendMoney', 'redPacket']]);
			if (!empty($user_id)) {
				$moneyData = $moneyData->andWhere(["in",'m.user_id',$user_id]);
			}
			if (!empty($send_stime) || !empty($send_etime)) {
				if (!empty($send_stime) && !empty($send_etime)){
					$moneyData = $moneyData->andFilterWhere(['between', 'm.send_time', strtotime($send_stime), strtotime($send_etime)]);
				} elseif (!empty($send_stime)){
					$moneyData = $moneyData->andWhere(['>', 'm.send_time', strtotime($send_stime)]);
				} else{
					$moneyData = $moneyData->andWhere(['<', 'm.send_time', strtotime($send_etime)]);
				}
			}
			if (!empty($order_id)) {
				$moneyData = $moneyData->andWhere(['or', ['m.order_id' => $order_id], ['m.transaction_id' => $order_id], ['m.third_id' => $order_id]]);
			}
			if (!empty($shop)) {
				$moneyData = $moneyData->andWhere(['m.shop' => $shop]);
			}
			if (!empty($account)) {
				$moneyData = $moneyData->andWhere(['m.account' => $account]);
			}
			if (!empty($remark)) {
				$moneyData = $moneyData->andWhere(['or', ['like', 'm.remark', $remark], ['like', 'm.message', $remark]]);
			}
			if ($status != '-1'){
				$moneyData = $moneyData->andWhere(['m.status' => $status]);
			}
			if ($send_type == 1){
				if ($chat_id > 0){
					$chatSendRule = RedPackChatSendRule::find()->andWhere(['corp_id' => $this->corp['id'], 'chat_id' => $chat_id])->all();
					$ruleId = [];
					foreach ($chatSendRule as $rule){
						array_push($ruleId, $rule->id);
					}
					if (!empty($ruleId)){
						$moneyData = $moneyData->andWhere(['m.chat_send_id' => $ruleId]);
					}else{
						$moneyData = $moneyData->andWhere(['m.chat_send_id' => '-1']);
					}
				}else{
					$moneyData = $moneyData->andWhere(['>', 'm.chat_send_id', 0]);
				}
			} elseif ($send_type == 2) {
				$moneyData = $moneyData->andWhere(['m.chat_send_id' => 0]);
			}
			if (!empty($name) || $phone !== ''){
				$moneyData = $moneyData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `m`.`external_id` AND `cf`.`type`=1');
				if (!empty($name)){
					$moneyData = $moneyData->andWhere(' we.name_convert like \'%' . $name . '%\' or (cf.fieldid =2 and cf.value like \'%' . $name . '%\')');
				}
				if ($phone !== ''){
					$moneyData = $moneyData->andWhere(['and', ['cf.fieldid' => 1], ['like', 'cf.value', $phone]]);
				}
			}

			$count = $moneyData->groupBy('m.id')->count();

			if (empty($is_all)) {
				$moneyData = $moneyData->limit($pageSize)->offset($offset);
			}
			$moneyData = $moneyData->select('m.*,we.name')->orderBy(['m.id' => SORT_DESC])->asArray()->all();

			$is_hide_phone = $this->user->is_hide_phone;
			$result        = [];
			foreach ($moneyData as $key => $val) {
				$moneyD              = [];
				$workUser            = WorkUser::findOne($val['user_id']);
				$moneyD['user_name'] = isset($workUser->name) ? $workUser->name : '--';
				$moneyD['send_time'] = date('Y-m-d H:i', $val['send_time']);
				$moneyD['nickname']  = !empty($val['name']) ? rawurldecode($val['name']) : '';

				$fieldValue      = CustomFieldValue::find()->where(['type' => 1, 'cid' => $val['external_id']])->andWhere(['in', 'fieldid', [1, 2]])->asArray()->all();
				$moneyD['name']  = '';
				$moneyD['phone'] = '';
				foreach ($fieldValue as $field) {
					if ($field['fieldid'] == 1) {
						$moneyD['phone'] = $field['value'];
					} else {
						$moneyD['name'] = $field['value'];
					}
				}
				if ($is_hide_phone && empty($is_export)){
					$moneyD['phone'] = '';
				}

				$moneyD['money']    = $val['money'];
				$moneyD['order_id'] = $val['order_id'];
				$moneyD['transaction_id'] = $val['transaction_id'];
				$moneyD['shop']     = $val['shop'];
				$moneyD['account']  = $val['account'];
				$moneyD['remark']   = $val['remark'];
				$moneyD['message']  = $val['message'] ? $val['message'] : MoneyOrder::REDPACKET_THANKING;
				$statusName = '--';
				switch ($val['status']){
					case 0:
						$statusName = '待领取';
						break;
					case 1:
						$statusName = '已领取';
						$statusName .= '（' . date('Y-m-d H:i', $val['pay_time']) . '）';
						break;
					case 2:
						$statusName = '已过期';
						break;
					case 4:
						$statusName = '领取失败';
						if (!empty($val['extrainfo'])){
							$statusName .= '（' . $val['extrainfo'] . '）';
						}
						break;
				}
				$moneyD['statusName'] = $statusName;
				$moneyD['typeName']   = $val['chat_send_id'] > 0 ? '客户群' : '客户';

				if ($val['chat_send_id'] > 0) {
					$sendRule           = RedPackChatSendRule::findOne($val['chat_send_id']);
					$chatName           = WorkChat::getChatName($sendRule->chat_id);
					$moneyD['typeName'] .= '（' . $chatName . '）';
				}

				$result[] = $moneyD;
			}

			//导出
			if ($is_export == 1) {
				if (empty($result)) {
					throw new InvalidDataException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				foreach ($result as $k => $v) {
					$result[$k]['nickname'] = !empty($v['nickname']) ? $v['nickname'] : '未知客户';
					$result[$k]['name']     = !empty($v['name']) ? $v['name'] : '--';
					$result[$k]['phone']    = !empty($v['phone']) ? $v['phone'] : '--';
				}
				$columns = ['user_name', 'typeName', 'send_time', 'nickname', 'name', 'phone', 'statusName', 'money', 'order_id', 'transaction_id', 'remark', 'message'];
				$headers = [
					'user_name'      => '员工操作',
					'typeName'       => '类型',
					'send_time'      => '发送时间',
					'nickname'       => '客户昵称',
					'name'           => '客户姓名',
					'phone'          => '客户手机号',
					'statusName'     => '领取状态',
					'money'          => '零钱金额（元）',
					'order_id'       => '商户订单号',
					'transaction_id' => '微信付款单号',
					//'shop'           => '店铺',
					//'account'        => '购物账号',
					'remark'         => '员工备注',
					'message'        => '给客户留言',
				];
				$fileName = '红包记录_' . date("YmdHis", time());
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

			return [
				'is_hide_phone' => $is_hide_phone,
				'count'         => $count,
				'money'         => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包统计
		 * @description     红包统计
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/send-money-statistics
		 *
		 * @param uid        必选 string 用户ID
		 * @param corp_id    必选 string 企业的唯一ID
		 * @param type       必选 int 1日统计2月统计
		 * @param send_stime 可选 string 起始发送时间
		 * @param send_etime 可选 string 结束发送时间
		 * @param send_type  可选 int 发送对象-1全部1客户群2客户
		 * @param chat_id    可选 int 群id
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    search_time string 查询时间
		 * @return_param    search_money string 查询金额
		 * @return_param    search_num int 查询笔数
		 * @return_param    all_money string 总金额
		 * @return_param    all_num int 总笔数
		 * @return_param    count int 数据条数
		 * @return_param    money array 数据列表
		 * @return_param    money.perdate string 日期
		 * @return_param    money.smoney string 金额
		 * @return_param    money.snum int 条数
		 * @return_param    money.stime string 开始时间
		 * @return_param    money.etime string 结束时间
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-08
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSendMoneyStatistics ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid        = \Yii::$app->request->post('uid', 0);
			$send_stime = \Yii::$app->request->post('send_stime', '');
			$send_etime = \Yii::$app->request->post('send_etime', '');
			$type       = \Yii::$app->request->post('type', 1);
			$send_type  = \Yii::$app->request->post('send_type', '-1');
			$chat_id    = \Yii::$app->request->post('chat_id', 0);
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$is_export  = \Yii::$app->request->post('is_export', 0);
			$is_all     = \Yii::$app->request->post('is_all', 0);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if ((empty($send_stime) && !empty($send_etime)) || (!empty($send_stime) && empty($send_etime))) {
				throw new InvalidDataException('起止时间缺失！');
			}

			if ($type == 2) {
				$unixType = '%Y-%m';
			} else {
				$unixType = '%Y-%m-%d';
			}
			$unixDate  = new Expression('FROM_UNIXTIME(send_time, \'' . $unixType . '\') perdate');
			$unixField = new Expression('FROM_UNIXTIME(send_time, \'' . $unixType . '\') perdate, SUM(money) smoney, COUNT(id) snum');

			$stime = '';
			$etime = '';
			if (!empty($send_stime) && !empty($send_etime)) {

				if ($type == 2) {
					$stime = strtotime($send_stime . '-01');
					$etime = strtotime($send_etime . '-01 +1 month') - 1;
				} else {
					$stime = strtotime($send_stime);
					$etime = strtotime($send_etime . ' 23:59:59');
				}
				$search_time = $send_stime . '~' . $send_etime;
			} else {
				$search_time = '全部';
			}

			$search_money = 0;
			$search_num   = 0;
			$all_money    = 0;
			$all_num      = 0;

			$moneyData = MoneyOrder::find()->where(['uid' => $uid, 'corp_id' => $this->corp['id'], 'ispay' => 1])->andWhere(['goods_type' => ['sendMoney', 'redPacket']]);
			if (isset($this->subUser->sub_id)) {
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if($sub_detail === false){
					return [
						"all_money"=>0,
						"all_num"=>0,
						"count"=>0,
						"money"=>[],
						"search_money"=>0,
						"search_num"=>0,
						"search_time"=>$search_time,
					];
				}
				if(is_array($sub_detail)){
					$moneyData = $moneyData->andWhere(['in',"user_id",$sub_detail]);
				}
			}
			if ($page == 1) {
				$allData   = $moneyData->select('SUM(money) all_money, COUNT(id) all_num')->asArray()->all();
				$all_money = $allData[0]['all_money'] ? $allData[0]['all_money'] : 0;
				$all_num   = $allData[0]['all_num'] ? $allData[0]['all_num'] : 0;
			}
			if ($stime && $etime){
				$moneyData = $moneyData->andFilterWhere(['between', 'send_time', $stime, $etime]);
			}

			if ($send_type == 1){
				if ($chat_id > 0){
					$chatSendRule = RedPackChatSendRule::find()->andWhere(['corp_id' => $this->corp['id'], 'chat_id' => $chat_id])->all();
					$ruleId = [];
					foreach ($chatSendRule as $rule){
						array_push($ruleId, $rule->id);
					}
					if (!empty($ruleId)){
						$moneyData = $moneyData->andWhere(['chat_send_id' => $ruleId]);
					}else{
						$moneyData = $moneyData->andWhere(['chat_send_id' => '-1']);
					}
				}else{
					$moneyData = $moneyData->andWhere(['>', 'chat_send_id', 0]);
				}
			} elseif ($send_type == 2) {
				$moneyData = $moneyData->andWhere(['chat_send_id' => 0]);
			}

			if ($page == 1) {
				$allData      = $moneyData->select('SUM(money) search_money, COUNT(id) search_num')->asArray()->all();
				$search_money = $allData[0]['search_money'] ? $allData[0]['search_money'] : 0;
				$search_num   = $allData[0]['search_num'] ? $allData[0]['search_num'] : 0 ;
			}

			$count = $moneyData->select($unixDate)->groupBy('perdate')->all();
			$count = count($count);

			if (empty($is_all)) {
				$offset    = ($page - 1) * $pageSize;
				$moneyData = $moneyData->limit($pageSize)->offset($offset);
			}
			$moneyData = $moneyData->select($unixField)->groupBy('perdate')->orderBy(['perdate' => SORT_DESC])->asArray()->all();

			//导出
			if ($is_export == 1) {
				if (empty($moneyData)) {
					throw new InvalidDataException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				$columns  = ['perdate', 'smoney', 'snum'];
				$headers = [
					'perdate' => '日期',
					'smoney'  => '金额（元）',
					'snum'    => '笔数',
				];
				$fileName = '红包统计_' . date("YmdHis", time());
				Excel::export([
					'models'       => $moneyData,//数库
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

			foreach ($moneyData as $key => $val) {
				if ($type == 2) {
					$moneyData[$key]['stime'] = $val['perdate'] . '-01';
					$moneyData[$key]['etime'] = date('Y-m-d', strtotime($moneyData[$key]['stime'] . ' +1 month') - 1);
				} else {
					$moneyData[$key]['stime'] = $moneyData[$key]['etime'] = $val['perdate'];
				}
			}

			return [
				'search_time'  => $search_time,
				'search_money' => $search_money,
				'search_num'   => $search_num,
				'all_money'    => $all_money,
				'all_num'      => $all_num,
				'count'        => $count,
				'money'        => $moneyData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包模版列表
		 * @description     红包模版列表
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/money-set-list
		 *
		 * @param uid       必选 string 用户ID
		 * @param corp_id   必选 string 企业的唯一ID
		 * @param status    可选 int 使用状态0全部1启用2禁用
		 * @param page      可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    have_agent int 是否有红包应用1是0否
		 * @return_param    canChangeAgent int 是否可更换红包应用1是0否
		 * @return_param    count int 条数
		 * @return_param    money array 数据列表
		 * @return_param    money.money_id string 模版id
		 * @return_param    money.money string 金额
		 * @return_param    money.send_num int 发送次数
		 * @return_param    money.user_name string 创建人
		 * @return_param    money.status string 使用状态
		 * @return_param    money.status_des string 状态描述
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMoneySetList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$status   = \Yii::$app->request->post('status', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			$agentInfo  = WorkCorpAgent::findOne(['corp_id' => $this->corp['id'], 'agent_is_money' => 1, 'close' => 0, 'is_del' => 0]);
			$have_agent = !empty($agentInfo) ? 1 : 0;
			$agentId    = !empty($agentInfo) ? $agentInfo->id : 0;

			$canChangeAgent = 0;
			if ($have_agent == 1) {
				$workAgentNum   = WorkCorpAgent::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => WorkCorpAgent::AGENT_NO_DEL, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'agent_type' => WorkCorpAgent::CUSTOM_AGENT])->count();
				$canChangeAgent = $workAgentNum > 1 ? 1 : $canChangeAgent;
			}

			$moneyData = MoneySet::find()->andWhere(['uid' => $uid, 'corp_id' => $this->corp['id']]);
			$moneyData = $moneyData->andWhere(['!=', 'status', 3]);
			if ($status) {
				$moneyData = $moneyData->andWhere(['status' => $status]);
			}

			$count = $moneyData->count();

			$offset    = ($page - 1) * $pageSize;
			$moneyData = $moneyData->limit($pageSize)->offset($offset);
			$moneyData = $moneyData->select('`id` money_id,`sub_id`,`money`,`send_num`,`status`')->orderBy(['id' => SORT_DESC])->asArray()->all();

			$userInfo = UserProfile::findOne(['uid' => $uid]);
			foreach ($moneyData as $key => $val) {
				if ($val['sub_id']) {
					$subInfo = SubUserProfile::findOne(['sub_user_id' => $val['sub_id']]);
					$name    = !empty($subInfo) ? $subInfo->name : '';
				} else {
					$name = $userInfo->nick_name;
				}
				$moneyData[$key]['user_name']  = $name;
				$moneyData[$key]['status_des'] = $val['status'] == 1 ? '启用' : '禁用';
			}

			return [
				'have_agent'     => $have_agent,
				'agentId'        => $agentId,
				'canChangeAgent' => $canChangeAgent,
				'count'          => $count,
				'money'          => $moneyData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包模版提交
		 * @description     红包模版提交
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/money-set-post
		 *
		 * @param uid              必选 int 用户ID
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param sub_id           必选 int 子账户ID
		 * @param corp_id          必选 string 企业的唯一ID
		 * @param money            必选 string 金额
		 * @param status           必选 int 使用状态1启用2禁用
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMoneySetPost ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid             = \Yii::$app->request->post('uid', 0);
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$money           = \Yii::$app->request->post('money', 0);
			$status          = \Yii::$app->request->post('status', 0);

			if (empty($uid) || empty($sub_id) || empty($money)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($status, [1, 2])) {
				throw new InvalidDataException('使用状态不正确！');
			}

			$moneySet = MoneySet::find()->where(['corp_id' => $this->corp['id'], 'money' => $money])->andWhere(['!=', 'status', 3])->one();

			if (!empty($moneySet)) {
				throw new InvalidDataException('该红包金额已存在！');
			}

			$moneySet          = new MoneySet();
			$moneySet->uid     = $uid;
			$moneySet->corp_id = $this->corp['id'];
			$moneySet->sub_id  = $isMasterAccount == 1 ? 0 : $sub_id;
			$moneySet->money   = $money;
			$moneySet->status  = $status;
			$moneySet->time    = time();

			if (!$moneySet->validate() || !$moneySet->save()) {
				throw new InvalidDataException(SUtils::modelError($moneySet));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包模版状态
		 * @description     红包模版状态修改
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/money-set-status
		 *
		 * @param money_id   必选 int 红包模版ID
		 * @param status     必选 int 状态1启用2禁用3删除
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMoneySetStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$money_id = \Yii::$app->request->post('money_id', 0);
			$status   = \Yii::$app->request->post('status', 0);

			if (empty($money_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (!in_array($status, [1, 2, 3])) {
				throw new InvalidDataException('状态不正确！');
			}

			$moneySet = MoneySet::findOne($money_id);

			if (empty($moneySet)) {
				throw new InvalidDataException('红包参数错误！');
			} else {
				$moneySet->status = $status;
				$moneySet->time   = time();

				if (!$moneySet->validate() || !$moneySet->save()) {
					throw new InvalidDataException(SUtils::modelError($moneySet));
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           客户限额设置
		 * @description     客户限额设置
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/money-send-limit
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMoneySendLimit ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			$result                       = [];
			$result['day_sum_money']      = $this->corp['day_sum_money'] > 0 ? $this->corp['day_sum_money'] : MoneyOrder::DAY_SUM_MONEY;
			$result['day_external_num']   = $this->corp['day_external_num'] ? $this->corp['day_external_num'] : MoneyOrder::DAY_EXTERNAL_NUM;
			$result['day_external_money'] = $this->corp['day_external_money'] > 0 ? $this->corp['day_external_money'] : MoneyOrder::DAY_EXTERNAL_MONEY;

			return [
				'money' => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           客户限额设置提交
		 * @description     客户限额设置提交
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/money-send-limit-set
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 * @param day_sum_money      必选 string 单日红包额度
		 * @param day_external_num   必选 int 客户单日红包次数
		 * @param day_external_money 必选 string 客户单日红包额度
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMoneySendLimitSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid                = \Yii::$app->request->post('uid', 0);
			$day_sum_money      = \Yii::$app->request->post('day_sum_money', 0);
			$day_external_num   = \Yii::$app->request->post('day_external_num', 0);
			$day_external_money = \Yii::$app->request->post('day_external_money', 0);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			if ($day_sum_money > 100000 || $day_sum_money < 0.3){
				throw new InvalidDataException('单日付款总额度设置不正确！');
			}
			if ($day_external_num > 10 || $day_external_num < 1){
				throw new InvalidDataException('客户单日红包次数设置不正确！');
			}
			if ($day_external_money > 5000 || $day_external_money < 0.3){
				throw new InvalidDataException('客户单日红包额度设置不正确！');
			}

			$this->corp['day_sum_money']      = $day_sum_money;
			$this->corp['day_external_num']   = $day_external_num;
			$this->corp['day_external_money'] = $day_external_money;

			if (!$this->corp->validate() || !$this->corp->save()) {
				throw new InvalidDataException(SUtils::modelError($this->corp));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           红包员工管理
		 * @description     红包员工管理
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/user-send-money
		 *
		 * @param uid        必选 string 用户ID
		 * @param corp_id    必选 string 企业的唯一ID
		 * @param user_ids   可选 array 员工id
		 * @param send_stime 可选 string 起始发送时间
		 * @param send_etime 可选 string 结束发送时间
		 * @param page       可选 int 页码
		 * @param page_size  可选 int 每页数据量，默认15
		 * @param is_export  选填 int 导出时传1
		 * @param is_all     选填 int 0当前页导出1全部导出
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    have_agent int 是否有红包应用1是0否
		 * @return_param    agentId int 红包应用id
		 * @return_param    canChangeAgent int 是否可更换红包应用1是0否
		 * @return_param    count int  数据条数
		 * @return_param    all_user array 全部员工id
		 * @return_param    money array 数据列表
		 * @return_param    money.user_id int 员工id
		 * @return_param    money.key int 员工id
		 * @return_param    money.user_name string 员工姓名
		 * @return_param    money.sex int 员工性别1男2女0未知
		 * @return_param    money.user_status int 客户状态1可用2取消权限3已关闭
		 * @return_param    money.day_smoney string 单日总金额
		 * @return_param    money.day_money string 今日已发送金额
		 * @return_param    money.day_hmoney string 今日剩余金额
		 * @return_param    money.day_snum int 单日总次数
		 * @return_param    money.day_num int 今日已发送次数
		 * @return_param    money.day_hnum int 今日剩余次数
		 * @return_param    money.smoney string 累计发送金额
		 * @return_param    money.snum int 累计发送次数
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-12
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUserSendMoney ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid        = \Yii::$app->request->post('uid', 0);
			$user_ids   = \Yii::$app->request->post('user_ids', []);
			$send_stime = \Yii::$app->request->post('send_stime', '');
			$send_etime = \Yii::$app->request->post('send_etime', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('page_size', 15);
			$is_export  = \Yii::$app->request->post('is_export', 0);
			$is_all     = \Yii::$app->request->post('is_all', 0);

			if(!empty($user_ids)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
				$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
				$user_ids = empty($user_ids) ? [0] : $user_ids;
			}

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if ((empty($send_stime) && !empty($send_etime)) || (!empty($send_stime) && empty($send_etime))) {
				throw new InvalidDataException('起止时间缺失！');
			}

			$agentInfo  = WorkCorpAgent::findOne(['corp_id' => $this->corp['id'], 'agent_is_money' => 1, 'close' => 0, 'is_del' => 0]);
			$have_agent = !empty($agentInfo) ? 1 : 0;
			$agentId    = !empty($agentInfo) ? $agentInfo->id : 0;

			$canChangeAgent = 0;
			if ($have_agent == 1) {
				$workAgentNum   = WorkCorpAgent::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => WorkCorpAgent::AGENT_NO_DEL, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'agent_type' => WorkCorpAgent::CUSTOM_AGENT])->count();
				$canChangeAgent = $workAgentNum > 1 ? 1 : $canChangeAgent;
			}

			//可见员工
			$agentUser = [];
			$agentInfo = WorkCorpAgent::findOne(['corp_id' => $this->corp['id'], 'agent_is_money' => 1, 'close' => 0, 'is_del' => 0]);
			if (!empty($agentInfo->allow_party) || !empty($agentInfo->allow_user)) {
				$department_ids = !empty($agentInfo->allow_party) ? explode(',', $agentInfo->allow_party) : [];
				$user_arr       = !empty($agentInfo->allow_user) ? explode(',', $agentInfo->allow_user) : [];

				$agentUser = WorkDepartment::getDepartmentUser($this->corp['id'], $department_ids, $user_arr);
			}
			//可发红包的员工
			$canSendUser = [];
			if (!empty($agentUser)) {
				$workUserSend = WorkUser::find()->where(['can_send_money' => 1])->andWhere(['id' => $agentUser])->select('id')->all();
				if (!empty($workUserSend)) {
					foreach ($workUserSend as $user) {
						array_push($canSendUser, $user->id);
					}
				}
			}
			//发送过红包的不可见员工
			$sendMoneyData = MoneyOrder::find()->andWhere(['uid' => $uid, 'corp_id' => $this->corp['id'], 'goods_type' => ['sendMoney', 'redPacket'], 'ispay' => 1]);
			if ($canSendUser){
				$sendMoneyData = $sendMoneyData->andWhere(['not in', 'user_id', $canSendUser]);
			}
			$sendMoneyData = $sendMoneyData->select('`user_id`')->groupBy('user_id')->asArray()->all();
			//全部员工
			$sendMoneyUser = array_column($sendMoneyData, 'user_id');
			$allUserIds    = array_merge($canSendUser, $sendMoneyUser);

			$moneyData = WorkUser::find()->alias('wu');
			$moneyData = $moneyData->leftJoin('{{%money_order}} m', '`m`.`user_id`=`wu`.`id` and m.goods_type in (\'sendMoney\',\'redPacket\') and m.ispay=1');
			$moneyData = $moneyData->andWhere(['wu.corp_id' => $this->corp['id']]);
			//子账户范围限定
			if (isset($this->subUser->sub_id)) {
				$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if ($sub_detail === false) {
					return [
						"all_user"=>[],
						"count"=>0,
						"money"=>0,
					];
				}
				if (is_array($sub_detail)) {
					$moneyData = $moneyData->andWhere(['in', '`wu`.`id`', $sub_detail]);
				}
				if ($sub_detail === true) {
					$moneyData = $moneyData->andWhere(['in', '`wu`.`id`', $allUserIds]);
				}
			}else{
				$moneyData = $moneyData->andWhere(['in', '`wu`.`id`', $allUserIds]);
			}
			if (!empty($user_ids)) {
				$moneyData = $moneyData->andWhere(['in', '`wu`.`id`', $user_ids]);
			}
			if (!empty($send_stime) && !empty($send_etime)) {
				$moneyData = $moneyData->andFilterWhere(['between', 'm.send_time', strtotime($send_stime), strtotime($send_etime . ' 23:59:59')]);
			}

			$countData = $moneyData->select('`wu`.`id`')->groupBy('`wu`.`id`')->asArray()->all();
			$count     = count($countData);

			$all_user = [];
			foreach ($countData as $k => $v) {
				if (in_array($v['id'], $canSendUser)){
					array_push($all_user, intval($v['id']));
				}
			}

			if (empty($is_all)) {
				$offset    = ($page - 1) * $pageSize;
				$moneyData = $moneyData->limit($pageSize)->offset($offset);
			}
			$field = '`wu`.`id` user_id, SUM(m.money) smoney, COUNT(m.id) snum';
			$moneyData = $moneyData->select($field)->groupBy('`wu`.`id`')->orderBy(['smoney' => SORT_DESC, 'user_id' => SORT_ASC])->asArray()->all();

			/*$moneyData = MoneyOrder::find()->andWhere(['uid' => $uid, 'corp_id' => $this->corp['id'], 'goods_type' => 'sendMoney', 'ispay' => 1]);
			if (!empty($user_ids)) {
				$moneyData = $moneyData->andWhere(['in', 'user_id', $user_ids]);
			}
			$countData = $moneyData->select('`user_id`')->groupBy('user_id')->orderBy(['user_id' => SORT_ASC])->all();
			$count     = count($countData);

			if (empty($is_all)) {
				$offset    = ($page - 1) * $pageSize;
				$moneyData = $moneyData->limit($pageSize)->offset($offset);
			}

			if (!empty($send_stime) && !empty($send_etime)){
				//全部员工
				$stime = strtotime($send_stime);
				$etime = strtotime($send_etime . ' 23:59:59');
				$field = new Expression('user_id, SUM(CASE WHEN send_time>=' . $stime . ' AND send_time<' . $etime . ' THEN money ELSE 0 END) smoney, SUM(CASE WHEN send_time>=' . $stime . ' AND send_time<' . $etime . ' THEN 1 ELSE 0 END) snum');
			}else{
				$field = 'user_id, SUM(money) smoney, COUNT(id) snum';
			}
			$moneyData = $moneyData->select($field)->groupBy('user_id')->orderBy(['smoney' => SORT_DESC, 'user_id' => SORT_ASC])->asArray()->all();*/

			$result = [];
			if (!empty($moneyData)) {
				$selectUser = [];
				foreach ($moneyData as $key => $val) {
					array_push($selectUser, $val['user_id']);
				}
				//员工今日发放红包数据
				$selectUserMoney  = MoneyOrder::find()->andWhere(['corp_id' => $this->corp['id'], 'goods_type' => 'redPacket', 'chat_send_id' => 0]);
				$selectUserMoney  = $selectUserMoney->andWhere(['in', 'user_id', $selectUser]);
				$selectUserMoney  = $selectUserMoney->andFilterWhere(['between', 'send_time', strtotime(date('Y-m-d')), time()]);
				$selectUserMoney  = $selectUserMoney->select('user_id, SUM(money) day_money, COUNT(id) day_num')->groupBy('user_id')->asArray()->all();
				$selectUserMoneyD = [];
				foreach ($selectUserMoney as $k => $v) {
					$selectUserMoneyD[$v['user_id']] = $v;
				}
				//员工今日发放客户群数据
				$selectUserMoney  = RedPackChatSendRule::find()->andWhere(['corp_id' => $this->corp['id']]);
				$selectUserMoney  = $selectUserMoney->andWhere(['in', 'user_id', $selectUser]);
				$selectUserMoney  = $selectUserMoney->andFilterWhere(['between', 'create_time', date('Y-m-d') . ' 00:00:00', date('Y-m-d H:i:s')]);
				$selectUserMoney  = $selectUserMoney->select('user_id, SUM(redpacket_amount) day_money, COUNT(id) day_num')->groupBy('user_id')->asArray()->all();
				$selectUserChatMoneyD = [];
				foreach ($selectUserMoney as $k => $v) {
					$selectUserChatMoneyD[$v['user_id']] = $v;
				}
				//员工信息
				$selectUserInfo  = WorkUser::find()->andWhere(['in', 'id', $selectUser])->select('`id` user_id, name user_name, gender sex, day_user_money, day_user_num')->asArray()->all();
				$selectUserInfoD = [];
				foreach ($selectUserInfo as $k => $v) {
					$selectUserInfoD[$v['user_id']] = $v;
				}

				//整合数据
				foreach ($moneyData as $k => $v) {
					$moneyD                = [];
					$moneyD['user_id']     = intval($v['user_id']);
					$moneyD['key']         = intval($v['user_id']);
					$user_status           = in_array($v['user_id'], $agentUser) ? 1 : 2;
					if ($user_status == 1) {
						$user_status = in_array($v['user_id'], $canSendUser) ? 1 : 3;
					}
					$moneyD['user_status'] = strval($user_status);
					$moneyD['user_name']   = isset($selectUserInfoD[$v['user_id']]) ? $selectUserInfoD[$v['user_id']]['user_name'] : '--';
					$moneyD['sex']         = isset($selectUserInfoD[$v['user_id']]) ? $selectUserInfoD[$v['user_id']]['sex'] : '--';
					$moneyD['day_smoney']  = isset($selectUserInfoD[$v['user_id']]) && $selectUserInfoD[$v['user_id']]['day_user_money'] > 0 ? $selectUserInfoD[$v['user_id']]['day_user_money'] : MoneyOrder::DAY_USER_MONEY;
					$day_money1            = isset($selectUserMoneyD[$v['user_id']]) ? $selectUserMoneyD[$v['user_id']]['day_money'] : '0.00';
					$day_money2            = isset($selectUserChatMoneyD[$v['user_id']]) ? $selectUserChatMoneyD[$v['user_id']]['day_money'] : '0.00';
					$moneyD['day_money']   = $day_money1 + $day_money2;
					$moneyD['day_hmoney']  = $moneyD['day_smoney'] > $moneyD['day_money'] ? sprintf('%.2f', $moneyD['day_smoney'] - $moneyD['day_money']) : '0.00';
					$moneyD['day_snum']    = isset($selectUserInfoD[$v['user_id']]) && $selectUserInfoD[$v['user_id']]['day_user_num'] > 0 ? $selectUserInfoD[$v['user_id']]['day_user_num'] : MoneyOrder::DAY_USER_NUM;
					$day_num1              = isset($selectUserMoneyD[$v['user_id']]) ? $selectUserMoneyD[$v['user_id']]['day_num'] : 0;
					$day_num2              = isset($selectUserChatMoneyD[$v['user_id']]) ? $selectUserChatMoneyD[$v['user_id']]['day_num'] : 0;
					$moneyD['day_num']     = $day_num1 + $day_num2;
					$moneyD['day_hnum']    = $moneyD['day_snum'] > $moneyD['day_num'] ? $moneyD['day_snum'] - $moneyD['day_num'] : 0;
					$moneyD['smoney']      = $v['smoney'] ? $v['smoney'] : '0.00';
					$moneyD['snum']        = $v['snum'] ? $v['snum'] : 0;

					$result[] = $moneyD;
				}
			}

			//导出
			if ($is_export == 1) {
				if (empty($result)) {
					throw new InvalidDataException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				foreach ($result as $k => $v) {
					$name = $v['user_name'];
					if ($v['sex'] == 1) {
						$name .= '（男）';
					} elseif ($v['sex'] == 2) {
						$name .= '（女）';
					}
					if ($v['user_status'] == 2) {
						$name .= '【已取消权限】';
					}
					$result[$k]['user_name'] = $name;
				}
				$columns  = ['user_name', 'day_snum', 'day_smoney', 'day_money', 'day_hmoney', 'day_num', 'day_hnum', 'smoney', 'snum'];
				$headers = [
					'user_name'  => '员工',
					'day_snum'   => '单日付款总次数（次）',
					'day_smoney' => '单日付款总金额（元）',
					'day_money'  => '今日已发放金额（元）',
					'day_hmoney' => '今日剩余金额（元）',
					'day_num'    => '今日已发放次数（次）',
					'day_hnum'   => '今日剩余次数（次）',
					'smoney'     => '累计已发放金额（元）',
					'snum'       => '累计已发放笔数（次）',
				];
				$fileName = '员工统计_' . date("YmdHis", time());
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

			return [
				'have_agent'     => $have_agent,
				'agentId'        => $agentId,
				'canChangeAgent' => $canChangeAgent,
				'count'          => $count,
				'all_user'       => $all_user,
				'money'          => $result
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           获取可用的部门
		 * @description     获取可用的部门
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/get-select-department
		 *
		 * @param corp_id   必选 string 企业的唯一ID
		 * @param agentid   可选 string 应用ID
		 * @param followMsg 可选 int 是否跟进提醒1是0否
		 *
		 * @return          {"error":0,"data":[{"key":1,"title":"1","department_id":1,"children":[{"key":5,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":4,"title":"23","department_id":23,"children":[{"key":6,"title":"39","department_id":39,"children":[],"user_list":[]},{"key":7,"title":"40","department_id":40,"children":[],"user_list":[]}],"user_list":[]},{"key":2,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":3,"title":"3","department_id":3,"children":[],"user_list":[]}],"user_list":[]}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    key int key
		 * @return_param    department_id int 部门id
		 * @return_param    title string 部门名称
		 * @return_param    children array 子集
		 * @return_param    user_list array 部门下的成员
		 * @return_param    id int id
		 * @return_param    userid string 成员UserID
		 * @return_param    name string 成员名称
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetSelectDepartment ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$agentid       = \Yii::$app->request->post('agentid', 0);
			$followMsg     = \Yii::$app->request->post('followMsg', 0);
			$remind        = \Yii::$app->request->post('remind', 0);
			$userDel       = \Yii::$app->request->post('userDel', 0);
			$userSendMoney = \Yii::$app->request->post('userSendMoney', 0);//是否可发红包

			if (!empty($agentid)) {
				$agentInfo = WorkCorpAgent::findOne($agentid);
			} else {
				$agentInfo = WorkCorpAgent::findOne(['corp_id' => $this->corp['id'], 'agent_is_money' => 1, 'close' => 0, 'is_del' => 0]);
			}

			//应用可见部门及员工
			$departments = [];
			if (!empty($agentInfo->allow_party) || !empty($agentInfo->allow_user)) {
				$department_ids = !empty($agentInfo->allow_party) ? explode(',', $agentInfo->allow_party) : [];
				$user_ids       = !empty($agentInfo->allow_user) ? explode(',', $agentInfo->allow_user) : [];

				$departments = WorkDepartment::getSelectDepartment($this->corp['id'], $department_ids, $user_ids, 1);
//				if (isset($this->subUser->sub_id)) {
//					$detail = AuthoritySubUserDetail::checkSubUser($this->subUser->sub_id,$this->corp['id']);
//					if($detail["type_all"] == AuthoritySubUserDetail::TYPE_ALL){
//						return $departments;
//					}
//					$departments =  WorkDepartment::getUserListsSubMember($detail,$departments,$this->subUser->sub_id,$this->corp->id);
//					$departments = WorkDepartment::getSelectDepartment($this->corp['id'], $department_ids, $user_ids, 1,0,0,$departments[0],$departments[1],$departments[2],$departments[3],$departments[4],true);
////					foreach ($departments  as $k=>$department){
////						if(isset($department["children"]) && empty($department["children"])){
////							unset($departments[$k]);
////						}
////					}
////					$departments = array_values($departments);
//				}else{
					//$departments = WorkDepartment::getSelectDepartment($this->corp['id'], $department_ids, $user_ids, 1);
//				}
			}
			//员工删除已设置员工禁选
			if ($userDel) {
				$userDelData = WorkUserDelFollowUser::find()->andWhere(['corp_id' => $this->corp->id, 'agent' => $agentid])->all();
				$userDelU    = array_column($userDelData, "user_id");
				WorkUserDelFollowUser::repeatConstructData($departments,$userDelU);
				return $departments;
			}
			//跟进提醒已设置员工禁选
			/*if ($followMsg) {
				$followMsgData = WorkFollowMsg::find()->andWhere(['corp_id' => $this->corp['id'], 'agentid' => $agentid])->all();
				$followMsgU    = [];
				foreach ($followMsgData as $v) {
					array_push($followMsgU, $v->user_id);
				}
				foreach ($departments as $k => $v) {
					$departments[$k]['disabled'] = in_array($v['id'], $followMsgU) ? true : false;
				}
			}*/


			//员工待办已设置员工禁选
			if ($remind) {
				$userDelData = WorkUserCommissionRemind::find()->andWhere(['corp_id' => $this->corp->id, 'agent' => $agentid])->all();
				$userDelU    = array_column($userDelData, "user_id");
				WorkUserCommissionRemind::repeatConstructData($departments,$userDelU);
				return $departments;
			}
			//已设置可发红包禁选
			if ($userSendMoney) {
				$userSendMoneyData = WorkUser::find()->andWhere(['corp_id' => $this->corp->id, 'is_del' => 0, 'can_send_money' => 1])->select('id')->all();
				$userDelU          = array_column($userSendMoneyData, "id");
				WorkUserCommissionRemind::repeatConstructData($departments, $userDelU);

				return $departments;
			}

			return $departments;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           员工限额设置
		 * @description     员工限额设置
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/user-send-money-limit
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 * @param user_ids           必选 array 员工id
		 * @param day_user_num       必选 int 员工单日红包次数
		 * @param day_user_money     必选 string 员工单日红包额度
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUserSendMoneyLimit ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid            = \Yii::$app->request->post('uid', 0);
			$user_ids       = \Yii::$app->request->post('user_ids', []);
			$day_user_num   = \Yii::$app->request->post('day_user_num');
			$day_user_money = \Yii::$app->request->post('day_user_money');

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($user_ids)) {
				throw new InvalidDataException('请选择员工！');
			}
			if ($day_user_num > 999999 || $day_user_num < 1) {
				throw new InvalidDataException('员工单日红包次数限制设置不正确！');
			}
			if ($day_user_money > 5000 || $day_user_money < 0.3) {
				throw new InvalidDataException('员工单日红包额度限制设置不正确！');
			}

			WorkUser::updateAll(['day_user_num' => $day_user_num, 'day_user_money' => $day_user_money, 'can_send_money' => 1], ['id' => $user_ids]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           员工发放红包状态设置
		 * @description     员工发放红包状态设置
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/user-send-money-status
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 * @param user_ids           必选 array 员工id
		 * @param status             必选 int 发放红包状态1开启0关闭
		 *
		 * @return          {"error":0,"data":{"money":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-11-25
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUserSendMoneyStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$user_ids = \Yii::$app->request->post('user_ids', []);
			$status   = \Yii::$app->request->post('status');

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($user_ids)) {
				throw new InvalidDataException('请选择员工！');
			}
			if (!in_array($status, [0, 1])) {
				throw new InvalidDataException('员工单日红包次数限制设置不正确！');
			}

			WorkUser::updateAll(['can_send_money' => $status], ['id' => $user_ids]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           上传支付密钥
		 * @description     上传支付密钥
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/upload-pem
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    local_path string 文件路径
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUploadPem ()
		{
			$flag = false;
			try {
				$uid = \Yii::$app->request->post('uid', 0);
				if (empty($uid)) {
					throw new InvalidDataException("参数不正确");
				}
				if (empty($this->corp)) {
					throw new InvalidDataException('参数不正确！');
				}
				$saveDir   = 'pem';
				$maxSize   = 2 * 1024 * 1024;
				$allowExts = ['pem'];
				//$allowTypes              = ['application/octet-stream'];
				$uploadFileUtil          = new UploadFileUtil();
				$uploadFileUtil->saveDir = $saveDir . '/' . $this->corp['id'];//上传文件保存路径
				$uploadFileUtil->maxSize = $maxSize;//大小限制
				if (isset($allowExts)) {
					$uploadFileUtil->allowExts = $allowExts;
				}
				if (isset($allowTypes)) {
					$uploadFileUtil->allowTypes = $allowTypes;
				}
				$result = $uploadFileUtil->upload();
				if (empty($result)) {
					$flag = true;
					throw new InvalidDataException($uploadFileUtil->getErrorMsg());
				}
				$uploadFileList     = $uploadFileUtil->getUploadFileList();
				$uploadInfo         = $uploadFileList[0];
				$local_path         = $uploadInfo['local_path'];
				$data['local_path'] = $local_path;

				return $data;
			} catch (\Exception $e) {
				if (!$flag) {
					\Yii::error($e->getMessage(), 'actionUploadPem');
					throw new InvalidDataException("上传失败");
				} else {
					throw new InvalidDataException($e->getMessage());
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           支付配置页面
		 * @description     支付配置页面
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/pay-config
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    payConfig array 结果数据
		 * @return_param    payConfig.id int 配置id
		 * @return_param    payConfig.mchid string 商户号
		 * @return_param    payConfig.key string 商户密钥
		 * @return_param    payConfig.apiclient_cert string 证书apiclient_cert.pem文件路径
		 * @return_param    payConfig.apiclient_key string 证书密钥apiclient_key.pem文件路径
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPayConfig ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			$payConfig = MoneyPayconfig::findOne(['corp_id' => $this->corp['id'], 'status' => 1]);

			return [
				'payConfig' => $payConfig
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/send-money-manage/
		 * @title           支付配置设置提交
		 * @description     支付配置设置提交
		 * @method   post
		 * @url  http://{host_name}/api/send-money-manage/pay-config-set
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 string 企业的唯一ID
		 * @param id                 可选 int 配置id
		 * @param mchid              可选 string 商户号
		 * @param key                可选 string 商户密钥
		 * @param apiclient_cert     可选 string 证书apiclient_cert
		 * @param apiclient_key      可选 string 证书密钥apiclient_key
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPayConfigSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid            = \Yii::$app->request->post('uid', 0);
			$id             = \Yii::$app->request->post('id', 0);
			$mchid          = \Yii::$app->request->post('mchid', '');
			$key            = \Yii::$app->request->post('key', '');
			$apiclient_cert = \Yii::$app->request->post('apiclient_cert', '');
			$apiclient_key  = \Yii::$app->request->post('apiclient_key', '');

			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}

			if ($id) {
				$payConfig = MoneyPayconfig::findOne($id);
				if (empty($payConfig)) {
					throw new InvalidDataException('配置参数不正确！');
				}
				$payConfig->upt_time = time();
			} else {
				$payConfig           = new MoneyPayconfig();
				$payConfig->uid      = $uid;
				$payConfig->corp_id  = $this->corp['id'];
				$payConfig->appid    = $this->corp['corpid'];
				$payConfig->status   = 1;
				$payConfig->add_time = time();
			}

			$payConfig->mchid          = $mchid;
			$payConfig->key            = $key;
			$payConfig->apiclient_cert = $apiclient_cert;
			$payConfig->apiclient_key  = $apiclient_key;

			if (!$payConfig->validate() || !$payConfig->save()) {
				throw new InvalidDataException(SUtils::modelError($payConfig));
			}

			return true;
		}

		//员工老数据可发放红包置1
		public function actionUpdateUserSendMoney (){
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

			WorkUser::updateAll(['can_send_money' => 1], ['is_del' => 0]);

			return true;
		}

	}