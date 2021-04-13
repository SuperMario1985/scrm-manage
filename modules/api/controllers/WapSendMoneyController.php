<?php
	/**
	 * H5发送红包
	 * User: fulu
	 * Date: 2020/05/13
	 * Time: 09:40
	 */

	namespace app\modules\api\controllers;

	use app\models\MoneySet;
	use app\models\RedPackChatSendRule;
	use app\models\RedPackRule;
	use app\models\User;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkContactWayRedpacket;
	use app\models\WorkContactWayRedpacketSend;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkGroupSending;
	use app\models\WorkGroupSendingRedpacketSend;
	use app\models\WorkUser;
	use app\models\MoneyOrder;
	use app\models\ExternalTimeLine;
	use app\components\InvalidParameterException;
	use app\models\WorkExternalContact;
	use app\modules\api\components\WorkBaseController;
	use app\util\SUtils;
	use app\util\WxPay\RedPacketPay;

	class WapSendMoneyController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           客户红包记录
		 * @description     客户红包记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/custom-money-order
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 int 企业ID
		 * @param userid           必选 int 员工的userid
		 * @param external_userid  必选 string 客户的userid
		 * @param chat_id          必选 string 群id
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    access_token string access_token
		 * @return_param    can_send int 是否可发送红包1是0否
		 * @return_param    send_msg string 发送状态描述
		 * @return_param    user_hmoney string 员工可发送金额
		 * @return_param    user_hnum int 员工可发送次数
		 * @return_param    external_hmoney string 客户可接受金额
		 * @return_param    external_hnum int 客户可接受次数
		 * @return_param    count int 数据条数
		 * @return_param    moneyList array 红包记录
		 * @return_param    moneyList.money string 金额
		 * @return_param    moneyList.order_id string 订单号
		 * @return_param    moneyList.transaction_id string 微信订单号
		 * @return_param    moneyList.send_time string 发送时间
		 * @return_param    moneyList.user_name string 发送人
		 * @return_param    moneyList.remark string 员工备注
		 * @return_param    moneyList.message string 给客户留言
		 * @return_param    moneyList.external_name string 客户姓名
		 * @return_param    moneyList.type string 红包类型1手动发红包4红包拉新5群发红包
		 * @return_param    moneyList.type_name string 红包类型名称
		 * @return_param    moneyList.act_name string 活动名称
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomMoneyOrder ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$chat_id         = \Yii::$app->request->post('chat_id', '');
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($uid) || empty($corp_id) || empty($userid)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				if (empty($external_userid) && empty($chat_id)){
					throw new InvalidParameterException('缺少必要参数！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('员工数据错误！');
				}

				$user         = User::findOne($uid);
				$user_type    = User::USER_TYPE;
				$access_token = base64_encode($user_type . '-' . $user->access_token);

				$result                 = [];
				$result['access_token'] = $access_token;

				$externalUserData = [];
				if (!empty($external_userid)){
					$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id , 'external_userid' => $external_userid]);
					if (empty($externalUserData)) {
						throw new InvalidParameterException('客户数据错误！');
					}
				}
				$workChat = [];
				if ($chat_id){
					$workChat = WorkChat::findOne(['corp_id' => $corpInfo->id, 'chat_id' => $chat_id]);
					if (empty($workChat)){
						throw new InvalidParameterException("抱歉，发红包页面无法打开，请检查下：\n1、群主为非企业成员\n2、该客户群目前没有同步到后台，请管理员同步下。");
					}
				}

				if ($page == 1) {
					$sendInfo = MoneyOrder::sendMoneyInfo($corpInfo, $userInfo, $externalUserData);

					$result['can_send']        = $sendInfo['can_send'];
					$result['send_msg']        = $sendInfo['send_msg'];
					$result['user_hmoney']     = $sendInfo['user_hmoney'];
					$result['user_hnum']       = $sendInfo['user_hnum'];
					$result['external_hmoney'] = $sendInfo['external_hmoney'];
					$result['external_hnum']   = $sendInfo['external_hnum'];

					if (empty($externalUserData) && !empty($workChat)){
						//只群主可发红包
						if ($workChat->owner_id != $userInfo->id){
							$result['is_owner'] = 0;
						}else{
							$result['is_owner'] = 1;
						}
					}
				}

				$offset    = ($page - 1) * $pageSize;
				if (!empty($external_userid)){
					/*$moneyList = MoneyOrder::find()->andWhere(['uid' => $uid, 'corp_id' => $corpInfo->id, 'ispay' => 1])->andWhere(['goods_type' => ['sendMoney', 'redPacket']]);
					$moneyList = $moneyList->andWhere(['external_id' => $externalUserData->id]);
					$count     = $moneyList->count();
					$moneyList = $moneyList->limit($pageSize)->offset($offset)->select('`id`, `order_id`,`user_id`, `chat_send_id`,`money`,`send_time`,`remark`,`message`,`transaction_id`')->orderBy(['id' => SORT_DESC])->asArray()->all();
					foreach ($moneyList as $k => $v) {
						$moneyList[$k]['send_time'] = date('Y-m-d H:i:s', $v['send_time']);
						$moneyUser                  = WorkUser::findOne($v['user_id']);
						$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
						if ($v['chat_send_id'] > 0) {
							$sendRule = RedPackChatSendRule::findOne($v['chat_send_id']);
							$chatName = WorkChat::getChatName($sendRule->chat_id);
							if (!empty($chatName)) {
								$moneyList[$k]['user_name'] .= '（' . $chatName . '）';
							}
						}
					}*/

					//手动发红包、红包拉新、群发红包记录
					$sql1 = 'select `id`, "" send_id,`order_id`,`transaction_id`,`user_id`, `external_id`, `chat_send_id`,`money`,`pay_time` send_time,`remark`,`message`,1 as type from {{%money_order}} where corp_id = ' . $corpInfo->id . ' and external_id= ' . $externalUserData->id . ' and ispay=1';
					$sql2 = 'select `id`, `jid` send_id, `order_id`, `transaction_id`,"" user_id, `external_id`,"" chat_send_id,`amount` money,`send_time`,`remark`,"" message,type from {{%red_pack_order}} where corp_id = ' . $corpInfo->id . ' and external_id=' . $externalUserData->id . ' and type in (4,5)';
					//总数
					$sqlCount = 'select count(id) count from ((' . $sql1 . ') UNION ALL (' . $sql2 . ' )) con ';
					$redCount = MoneyOrder::findBySql($sqlCount)->asArray()->all();
					$count    = !empty($redCount) ? $redCount[0]['count'] : 0;
					//列表
					$sql       = 'select * from ((' . $sql1 . ') UNION ALL (' . $sql2 . ') ) con order by send_time desc limit ' . $offset . ',' . $pageSize;
					$moneyList = MoneyOrder::findBySql($sql)->asArray()->all();

					foreach ($moneyList as $k => $v) {
						$moneyList[$k]['send_time']     = date('Y-m-d H:i', $v['send_time']);
						$externalUserData               = WorkExternalContact::findOne($v['external_id']);
						$moneyList[$k]['external_name'] = !empty($externalUserData) ? $externalUserData->name_convert : '--';
						$moneyList[$k]['type']          = $v['type'];

						$type_name = '';//红包类型
						$act_name  = '';//活动名称
						if ($v['type'] == 1) {
							$moneyUser                  = WorkUser::findOne($v['user_id']);
							$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
							$type_name                  = '员工手动向客户发红包';
							if ($v['chat_send_id'] > 0) {
								$sendRule = RedPackChatSendRule::findOne($v['chat_send_id']);
								$chatName = WorkChat::getChatName($sendRule->chat_id);
								if (!empty($chatName)) {
									$moneyList[$k]['user_name'] .= '（' . $chatName . '）';
								}
								$type_name = '员工手动向客户群发红包';
							}
						} elseif ($v['type'] == 4) {
							$redpacketSend              = WorkContactWayRedpacketSend::findOne($v['send_id']);
							$moneyUser                  = WorkUser::findOne($redpacketSend->user_id);
							$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
							$redpackData                = WorkContactWayRedpacket::findOne($redpacketSend->way_id);
							$act_name                   = $redpackData->name;
							$type_name                  = '红包拉新';
						} elseif ($v['type'] == 5) {
							$redpacketSend              = WorkGroupSendingRedpacketSend::findOne($v['send_id']);
							$moneyUser                  = WorkUser::findOne($redpacketSend->user_id);
							$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
							$redpackData                = WorkGroupSending::findOne($redpacketSend->send_id);
							$act_name                   = $redpackData->title;

							$hasChatSend = WorkGroupSendingRedpacketSend::findOne(['group_send_id' => $redpacketSend->group_send_id, 'is_chat' => 1]);
							if (!empty($hasChatSend)) {
								$chatName = WorkChat::getChatName($redpacketSend->external_userid);
								if (!empty($chatName)) {
									$moneyList[$k]['user_name'] .= '（' . $chatName . '）';
								}
								$type_name = '向客户群群发红包';
							} else {
								$type_name = '向客户群发红包';
							}
						}

						$moneyList[$k]['type_name'] = $type_name;
						$moneyList[$k]['act_name']  = $act_name;
					}
				}else{
					/*$moneyList = RedPackChatSendRule::find()->andWhere(['corp_id' => $corpInfo->id, 'chat_id' => $workChat->id]);
					$count     = $moneyList->count();
					$moneyList = $moneyList->limit($pageSize)->offset($offset)->select('`id`, `user_id`,`redpacket_amount` money,`create_time` send_time,`remark`,`des` message')->orderBy(['id' => SORT_DESC])->asArray()->all();
					foreach ($moneyList as $k => $v) {
						$moneyUser                  = WorkUser::findOne($v['user_id']);
						$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
					}*/

					//手动群发红包、群发客户群红包记录
					$sql1 = 'select `id`, `user_id`, "" send_id,`redpacket_amount` money, UNIX_TIMESTAMP(`create_time`) send_time,`remark`,`des` message,1 as type from {{%red_pack_chat_send_rule}} where corp_id = ' . $corpInfo->id . ' and chat_id= ' . $workChat->id;
					$sql2 = 'select `id`, `user_id`, `send_id`, `send_money` money,`create_time` send_time,"" remark,"" message,5 as type from {{%work_group_sending_redpacket_send}} where corp_id = ' . $corpInfo->id . ' and external_userid=' . $workChat->id . ' and is_send=1 and is_chat=1';
					//总数
					$sqlCount = 'select count(id) count from ((' . $sql1 . ') UNION ALL (' . $sql2 . ' )) con ';
					$redCount = RedPackChatSendRule::findBySql($sqlCount)->asArray()->all();
					$count    = !empty($redCount) ? $redCount[0]['count'] : 0;
					//列表
					$sql       = 'select * from ((' . $sql1 . ') UNION ALL (' . $sql2 . ') ) con order by send_time desc limit ' . $offset . ',' . $pageSize;
					$moneyList = MoneyOrder::findBySql($sql)->asArray()->all();

					foreach ($moneyList as $k => $v) {
						$moneyList[$k]['send_time'] = date('Y-m-d H:i', $v['send_time']);
						$moneyUser                  = WorkUser::findOne($v['user_id']);
						$moneyList[$k]['user_name'] = !empty($moneyUser) ? $moneyUser->name : '--';
						$moneyList[$k]['type']      = $v['type'];

						$type_name = '';//红包类型
						$act_name  = '';//活动名称
						if ($v['type'] == 1) {
							$type_name                = '员工手动向客户群发红包';
							$moneyList[$k]['message'] = $v['message'] ? $v['message'] : MoneyOrder::REDPACKET_THANKING;
						} elseif ($v['type'] == 5) {
							$redpackData = WorkGroupSending::findOne($v['send_id']);
							$act_name    = $redpackData->title;
							$type_name   = '向客户群群发红包';

							if ($redpackData->rule_id > 0) {
								$redRule = RedPackRule::find()->andWhere(['id' => $redpackData->rule_id])->asArray()->one();
							} else {
								$redRule = json_decode($redpackData->rule_text, true);
							}
							$moneyList[$k]['remark']  = $redRule['des'];
							$moneyList[$k]['message'] = $redRule['thanking'] ? $redRule['thanking'] : MoneyOrder::REDPACKET_THANKING;
						}

						$moneyList[$k]['type_name'] = $type_name;
						$moneyList[$k]['act_name']  = $act_name;
					}
				}

				$result['count']     = $count;
				$result['moneyList'] = $moneyList;

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           群红包明细
		 * @description     群红包明细
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/chat-money-order
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 int 企业ID
		 * @param chat_send_id     必选 int 群发放id
		 * @param type             必选 int 群发类型1员工主动发客户群5红包群发客户群
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    moneyList array 红包记录
		 * @return_param    moneyList.money string 金额
		 * @return_param    moneyList.order_id string 订单号
		 * @return_param    moneyList.transaction_id string 微信订单号
		 * @return_param    moneyList.send_time string 发送时间
		 * @return_param    moneyList.user_name string 发送人
		 * @return_param    moneyList.remark string 员工备注
		 * @return_param    moneyList.message string 给客户留言
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/27
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionChatMoneyOrder ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id      = \Yii::$app->request->post('corp_id', '');
				$chat_send_id = \Yii::$app->request->post('chat_send_id', '');
				$type         = \Yii::$app->request->post('type', '');
				$page         = \Yii::$app->request->post('page', 1);
				$pageSize     = \Yii::$app->request->post('page_size', 15);

				if (empty($corp_id) || empty($chat_send_id)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				if (!in_array($type, [1, 5])) {
					throw new InvalidParameterException('群发类型数据错误！');
				}

				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}

				if ($type == 1) {
					$moneyList = MoneyOrder::find()->andWhere(['corp_id' => $corpInfo->id, 'ispay' => 1, 'chat_send_id' => $chat_send_id, 'goods_type' => 'redPacket']);
					$count     = $moneyList->count();
					$offset    = ($page - 1) * $pageSize;
					$moneyList = $moneyList->limit($pageSize)->offset($offset)->select('`id`, `order_id`,`user_id`,`external_id`,`money`,`send_time`,`remark`,`message`,`transaction_id`')->orderBy(['id' => SORT_DESC])->asArray()->all();
					foreach ($moneyList as $k => $v) {
						$moneyList[$k]['send_time']     = date('Y-m-d H:i:s', $v['send_time']);
						$moneyUser                      = WorkUser::findOne($v['user_id']);
						$moneyList[$k]['user_name']     = !empty($moneyUser) ? $moneyUser->name : '--';
						$externalUserData               = WorkExternalContact::findOne($v['external_id']);
						$moneyList[$k]['external_name'] = !empty($externalUserData) ? $externalUserData->name_convert : '--';
					}
				} else {
					$chatSend = WorkGroupSendingRedpacketSend::findOne($chat_send_id);
					$sendData = WorkGroupSending::findOne($chatSend->send_id);
					if ($sendData->rule_id > 0) {
						$redRule = RedPackRule::find()->andWhere(['id' => $sendData->rule_id])->asArray()->one();
					} else {
						$redRule = json_decode($sendData->rule_text, true);
					}
					$remark  = $redRule['des'];
					$message = $redRule['thanking'] ? $redRule['thanking'] : MoneyOrder::REDPACKET_THANKING;

					$moneyList = WorkGroupSendingRedpacketSend::find()->alias('rs');
					$moneyList = $moneyList->leftJoin('{{%red_pack_order}} ro', 'rs.id=ro.jid and ro.type=5');
					$moneyList = $moneyList->andWhere(['rs.corp_id' => $corpInfo->id, 'rs.group_send_id' => $chatSend->group_send_id, 'rs.is_chat' => 0, 'rs.status' => 1]);
					$count     = $moneyList->count();
					$offset    = ($page - 1) * $pageSize;
					$moneyList = $moneyList->limit($pageSize)->offset($offset)->select('rs.`id`,rs.`user_id`,rs.`external_userid` external_id,rs.`send_money` money,rs.`send_time`, ro.`order_id`,ro.`transaction_id`')->orderBy(['rs.`id`' => SORT_DESC])->asArray()->all();
					foreach ($moneyList as $k => $v) {
						$moneyList[$k]['send_time']     = date('Y-m-d H:i:s', $v['send_time']);
						$moneyUser                      = WorkUser::findOne($v['user_id']);
						$moneyList[$k]['user_name']     = !empty($moneyUser) ? $moneyUser->name : '--';
						$externalUserData               = WorkExternalContact::findOne($v['external_id']);
						$moneyList[$k]['external_name'] = !empty($externalUserData) ? $externalUserData->name_convert : '--';
						$moneyList[$k]['remark']        = $remark;
						$moneyList[$k]['message']       = $message;
					}

				}

				$result['count']     = $count;
				$result['moneyList'] = $moneyList;

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           发送红包页面
		 * @description     发送红包页面
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/send-money-info
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 int 企业ID
		 * @param userid           必选 int 员工的userid
		 * @param external_userid  必选 string 客户的userid
		 * @param chat_id          可选 string 群id
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    can_send int 是否可发送红包1是0否
		 * @return_param    send_msg string 发送状态描述
		 * @return_param    user_hmoney string 员工可发送金额
		 * @return_param    user_hnum int 员工可发送次数
		 * @return_param    external_hmoney string 客户可接受金额
		 * @return_param    external_hnum int 客户可接受次数
		 * @return_param    chatInfoNum int 群人数
		 * @return_param    moneyList array 红包记录
		 * @return_param    moneyList.money_id string 档位id
		 * @return_param    moneyList.money string 档位金额
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendMoneyInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$chat_id         = \Yii::$app->request->post('chat_id', '');

				if (empty($uid) || empty($corp_id) || empty($userid)) {
					throw new InvalidParameterException('缺少必要参数！');
				}

				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('员工数据错误！');
				}

				$externalUserData = [];
				if ($external_userid){
					$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
					if (empty($externalUserData)) {
						throw new InvalidParameterException('客户数据错误！');
					}
				}
				$chatInfoNum = 0;
				if ($chat_id){
					$workChat = WorkChat::findOne(['corp_id' => $corpInfo->id, 'chat_id' => $chat_id]);
					if (empty($workChat)){
						throw new InvalidParameterException('客户群数据错误！');
					}
					$chatInfoNum = WorkChatInfo::find()->andWhere(['chat_id' => $workChat->id, 'status' => 1])->count();
				}

				$result = MoneyOrder::sendMoneyInfo($corpInfo, $userInfo, $externalUserData);

				$moneyList = MoneySet::find()->andWhere(['uid' => $uid, 'corp_id' => $corpInfo->id, 'status' => 1]);
				$moneyList = $moneyList->select('`id` money_id,`money`')->orderBy(['money' => SORT_DESC])->asArray()->all();

				$result['moneyList']   = $moneyList;
				$result['chatInfoNum'] = $chatInfoNum;

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           发送红包
		 * @description     发送红包提交
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/send-money
		 *
		 * @param uid                必选 int 用户ID
		 * @param corp_id            必选 int 企业ID
		 * @param userid             必选 int 员工的userid
		 * @param external_userid    必选 int 客户的userid
		 * @param money              必选 string 红包金额
		 * @param remark             必选 string 红包备注
		 * @param message            可选 string 留言
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendMoney ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$money           = \Yii::$app->request->post('money', 0);
				$remark          = \Yii::$app->request->post('remark', '');
				$message         = \Yii::$app->request->post('message', '');

				if (empty($uid) || empty($corp_id) || empty($userid) || empty($external_userid) || empty($money)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('员工数据错误！');
				}

				try {
					$sendInfo = MoneyOrder::sendMoneyInfo($corpInfo, $userInfo, $externalUserData, $money);
					if ($sendInfo['can_send'] == 0) {
						throw new InvalidParameterException($sendInfo['send_msg']);
					}

					$moneySet = MoneySet::findOne(['corp_id' => $corpInfo->id, 'status' => 1, 'money' => $money]);
					$goods_id = !empty($moneySet) ? $moneySet->id : 0;

					$moneyOrder              = new MoneyOrder();
					$moneyOrder->uid         = $uid;
					$moneyOrder->order_id    = '33' . date('YmdHis') . $userInfo->id . mt_rand(1111, 9999);
					$moneyOrder->corp_id     = $corpInfo->id;
					$moneyOrder->user_id     = $userInfo->id;
					$moneyOrder->external_id = $externalUserData->id;
					$moneyOrder->goods_type  = 'sendMoney';
					$moneyOrder->goods_id    = $goods_id;
					$moneyOrder->money       = $money;
					$moneyOrder->send_time   = time();
					$moneyOrder->remark      = $remark;
					$moneyOrder->message     = $message;
					$moneyOrder->ispay       = 0;
					if (!$moneyOrder->validate() || !$moneyOrder->save()) {
						throw new InvalidParameterException(SUtils::modelError($moneyOrder));
					}

					$redPacketPay = new RedPacketPay();

					$orderData                     = [];
					$orderData['partner_trade_no'] = $moneyOrder->order_id;
					$orderData['openid']           = $externalUserData->openid;
					$orderData['amount']           = $money * 100;
					$orderData['desc']             = $remark;
					$resData                       = $redPacketPay->RedPacketSend($corpInfo->id, $orderData);
					\Yii::error($resData, 'resData');
					if ($resData['return_code'] == 'SUCCESS' && $resData['result_code'] == 'SUCCESS') {
						$uptOrder = MoneyOrder::findOne(['order_id' => $resData['partner_trade_no']]);
						if ($uptOrder) {
							$uptOrder->ispay          = 1;
							$uptOrder->pay_time       = strtotime($resData['payment_time']);
							$uptOrder->transaction_id = $resData['payment_no'];

							if (!$uptOrder->validate() || !$uptOrder->save()) {
								throw new InvalidParameterException(SUtils::modelError($uptOrder));
							}
						} else {
							throw new InvalidParameterException('订单数据错误！');
						}

						if (!empty($moneySet)){
							$moneySet->send_num += 1;
							$moneySet->save();
						}
						//记录客户轨迹
						ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => $uptOrder->external_id, 'user_id' => $uptOrder->user_id, 'event' => 'send_money', 'related_id' => $uptOrder->id, 'remark' => $uptOrder->money]);
					} else {
						//$msg = isset($resData['err_code_des']) && !empty($resData['err_code_des']) ? '错误码：' . $resData['err_code'] . "错误描述：" . $resData['err_code_des'] : '';
						$msg = isset($resData['err_code_des']) && !empty($resData['err_code_des']) ? $resData['err_code_des'] : '';
						$msg = empty($msg) && isset($resData['return_msg']) ? $resData['return_msg'] : $msg;

						$moneyOrder->extrainfo = $msg;
						$moneyOrder->save();

						throw new InvalidParameterException($msg);
					}

				} catch (InvalidParameterException $e) {
					throw new InvalidParameterException($e->getMessage());
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           发放个人红包（手动领取）
		 * @description     发放个人红包（手动领取）
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/send-manual-money
		 *
		 * @param uid                必选 string 用户id
		 * @param corpid             必选 string 企业corpid
		 * @param userid             必选 int 员工的userid
		 * @param external_userid    必选 string 客户的userid
		 * @param money              必选 string 红包金额
		 * @param remark             必选 string 员工备注
		 * @param message            可选 string 祝福语
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    msgtype string 消息类型
		 * @return_param    news array 消息内容
		 * @return_param    news.link string 跳转链接
		 * @return_param    news.title string 员工备注
		 * @return_param    news.desc string 祝福语
		 * @return_param    news.imgUrl string 红包图片
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-10-02
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendManualMoney ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许！');
			}
			$uid             = \Yii::$app->request->post('uid', 0);
			$corp_id         = \Yii::$app->request->post('corp_id', '');
			$userid          = \Yii::$app->request->post('userid', '');
			$external_userid = \Yii::$app->request->post('external_userid', '');
			$money           = \Yii::$app->request->post('money', 0);
			$remark          = \Yii::$app->request->post('remark', '');
			$message         = \Yii::$app->request->post('message', '');
			$message         = !empty($message) ? $message : MoneyOrder::REDPACKET_THANKING;

			if (empty($uid) || empty($corp_id) || empty($userid) || empty($money) || empty($external_userid)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $workCorp->id, 'userid' => $userid]);
			if (empty($workUser)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			if (empty($remark)) {
				throw new InvalidParameterException('员工备注不能为空！');
			}
			$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
			if (empty($externalUserData)) {
				throw new InvalidParameterException('客户数据错误！');
			}

			$sendInfo = MoneyOrder::sendMoneyInfo($workCorp, $workUser, $externalUserData, $money);
			if ($sendInfo['can_send'] == 0) {
				throw new InvalidParameterException($sendInfo['send_msg']);
			}

			//领取记录
			$moneySet = MoneySet::findOne(['corp_id' => $workCorp->id, 'status' => 1, 'money' => $money]);
			$goods_id = !empty($moneySet) ? $moneySet->id : 0;

			$moneyOrder              = new MoneyOrder();
			$moneyOrder->uid         = $uid;
			$moneyOrder->order_id    = '33' . date('YmdHis') . $workUser->id . mt_rand(1111, 9999);
			$moneyOrder->corp_id     = $workCorp->id;
			$moneyOrder->user_id     = $workUser->id;
			$moneyOrder->external_id = $externalUserData->id;
			$moneyOrder->goods_type  = 'redPacket';
			$moneyOrder->goods_id    = $goods_id;
			$moneyOrder->money       = $money;
			$moneyOrder->send_time   = time();
			$moneyOrder->remark      = $remark;
			$moneyOrder->message     = $message;
			$moneyOrder->ispay       = 0;
			if (!$moneyOrder->validate() || !$moneyOrder->save()) {
				throw new InvalidParameterException(SUtils::modelError($moneyOrder));
			}

			if (!empty($moneySet)){
				$moneySet->send_num += 1;
				$moneySet->save();
			}

			//点击链接是进入领取红包页面
			$workAgent = WorkCorpAgent::findOne(['corp_id' => $workCorp->id, 'is_del' => WorkCorpAgent::AGENT_NO_DEL, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'agent_type' => WorkCorpAgent::CUSTOM_AGENT]);
			if (empty($workAgent)){
				throw new InvalidParameterException('没有可用的应用！');
			}
			$assist   = MoneyOrder::REDPACKET_SEND . '_' . $moneyOrder->id;
			$web_url  = \Yii::$app->params['web_url'];
			$site_url = \Yii::$app->params['site_url'];
			$url      = $web_url . MoneyOrder::H5_URL . '?corpid=' . $workCorp->corpid . '&assist=' . $assist . '&agent_id=' . $workAgent->id;

			$data = [
				'assist'  => $assist,
				'msgtype' => 'news',
				'news'    => [
					'link'   => $url,
					'title'  => $remark,
					'desc'   => $message,
					'imgUrl' => $site_url . '/static/image/default-redpacket.png',
				]
			];

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           发放群红包（手动领取）
		 * @description     发放群红包（手动领取）
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/send-chat-manual-money
		 *
		 * @param uid                必选 string 用户id
		 * @param corpid             必选 string 企业corpid
		 * @param userid             必选 int 员工的userid
		 * @param chat_id            必选 string 群id
		 * @param type               必选 string 红包类型1固定金额2随机金额
		 * @param money              必选 string 红包金额
		 * @param num                必选 string 红包数量
		 * @param remark             必选 string 员工备注
		 * @param message            可选 string 祝福语
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    msgtype string 消息类型
		 * @return_param    news array 消息内容
		 * @return_param    news.link string 跳转链接
		 * @return_param    news.title string 员工备注
		 * @return_param    news.desc string 祝福语
		 * @return_param    news.imgUrl string 红包图片
		 *
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-10-02
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendChatManualMoney ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$corp_id = \Yii::$app->request->post('corp_id', '');
			$userid  = \Yii::$app->request->post('userid', '');
			$chatId  = \Yii::$app->request->post('chat_id', '');//群ID
			$type    = \Yii::$app->request->post('type', 0);
			$money   = \Yii::$app->request->post('money', 0);
			$num     = \Yii::$app->request->post('num', 0);
			$remark  = \Yii::$app->request->post('remark', '');
			$message = \Yii::$app->request->post('message', '');
			$message = !empty($message) ? $message : MoneyOrder::REDPACKET_THANKING;

			if (empty($uid) || empty($corp_id) || empty($userid) || empty($money) || empty($num) || empty($chatId)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $workCorp->id, 'userid' => $userid]);
			if (empty($workUser)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			if (!in_array($type, [1, 2])) {
				throw new InvalidParameterException('红包类型数据错误！');
			}
			if (empty($remark)) {
				throw new InvalidParameterException('员工备注不能为空！');
			}
			$externalUserData = [];
			$workChatId       = 0;
			if (!empty($chatId)) {
				$workChat = WorkChat::findOne(['corp_id' => $workCorp->id, 'chat_id' => $chatId]);
				if (empty($workChat)) {
					$workChatId = WorkChat::getChatInfo($workCorp->id, $chatId);
				} else {
					$workChatId = $workChat->id;
				}
			}

			$sendInfo = MoneyOrder::sendMoneyInfo($workCorp, $workUser, $externalUserData, $money);
			if ($sendInfo['can_send'] == 0) {
				throw new InvalidParameterException($sendInfo['send_msg']);
			}

			$ruleData                     = [];
			$ruleData['uid']              = $uid;
			$ruleData['corp_id']          = $workCorp->id;
			$ruleData['user_id']          = $workUser->id;
			$ruleData['chat_id']          = $workChatId;
			$ruleData['type']             = $type;
			$ruleData['redpacket_amount'] = $money;
			$ruleData['redpacket_num']    = $num;
			$ruleData['remark']           = $remark;
			$ruleData['des']              = $message;
			$rule_id                      = RedPackChatSendRule::setData($ruleData);

			//领取记录
			if ($type == 1) {
				$money = $money / $num;
			}
			$moneySet = MoneySet::findOne(['corp_id' => $workCorp->id, 'status' => 1, 'money' => $money]);
			if (!empty($moneySet)) {
				$moneyNum           = $type == 1 ? $num : 1;
				$moneySet->send_num += $moneyNum;
				$moneySet->save();
			}

			//点击链接是进入领取红包页面
			$workAgent = WorkCorpAgent::findOne(['corp_id' => $workCorp->id, 'is_del' => WorkCorpAgent::AGENT_NO_DEL, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'agent_type' => WorkCorpAgent::CUSTOM_AGENT]);
			if (empty($workAgent)){
				throw new InvalidParameterException('没有可用的应用！');
			}
			$assist   = MoneyOrder::REDPACKET_CHAT_SEND . '_' . $rule_id;
			$web_url  = \Yii::$app->params['web_url'];
			$site_url = \Yii::$app->params['site_url'];
			$url      = $web_url . MoneyOrder::H5_URL . '?corpid=' . $workCorp->corpid . '&assist=' . $assist . '&agent_id=' . $workAgent->id;

			$data = [
				'assist'  => $assist,
				'msgtype' => 'news',
				'news'    => [
					'link'   => $url,
					'title'  => $remark,
					'desc'   => $message,
					'imgUrl' => $site_url . '/static/image/default-redpacket.png',
				]
			];

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-send-money/
		 * @title           取消红包发放
		 * @description     取消红包发放
		 * @method   post
		 * @url  http://{host_name}/api/wap-send-money/send-money-cancel
		 *
		 * @param assist             必选 string 红包标识
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-10-29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSendMoneyCancel ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException('请求方式不允许！');
			}
			$assist = \Yii::$app->request->post('assist', '');

			if (empty($assist)) {
				throw new InvalidParameterException('缺少必要参数！');
			}

			$stateArr = explode('_', $assist);

			if (!in_array($stateArr['0'], [MoneyOrder::REDPACKET_SEND, MoneyOrder::REDPACKET_CHAT_SEND])) {
				throw new InvalidParameterException('附带参数不正确！');
			}

			$send_id = $stateArr['1'];

			if ($stateArr['0'] == MoneyOrder::REDPACKET_SEND) {
				MoneyOrder::deleteAll(['id' => $send_id]);
			} else {
				RedPackChatSendRule::deleteAll(['id' => $send_id]);
			}

			return true;
		}

	}