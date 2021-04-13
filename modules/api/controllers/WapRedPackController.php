<?php
	/**
	 * 红包裂变手机端
	 * User: xingchangyu
	 * Date: 2020/05/29
	 * Time: 17:17
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\RedPack;
	use app\models\RedPackHelpDetail;
	use app\models\RedPackJoin;
    use app\models\User;
    use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\modules\api\components\BaseController;
	use app\queue\SyncRedPackJob;
	use app\util\WorkUtils;
	use linslin\yii2\curl\Curl;

	class WapRedPackController extends BaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-red-pack/
		 * @title           红包页面
		 * @description     红包页面
		 * @method   post
		 * @url  http://{host_name}/api/wap-red-pack/index
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param agent_id 必选 string 企业应用id
		 * @param code 必选 string 获取用户信息的code
		 * @param assist 必选 string 附带上级参数
		 *
		 * @return          {"error":0,"data":{"rid":1,"jid":0,"title":"xcy测试","is_use":0,"area_type":1,"red_pack_price":"1.00","is_remind":0,"remind_data":[],"help_type":1,"helpData":{"count":"1","tips":"已有1人获得裂变红包","info":[],"join":[{"key":"1","id":"1","name":"一切随缘","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5NSMiaygS8Qfd80LcgrMy0bwhnwqjMsRAnu9Nz7uiclYSA/0","amount":"1.00","complete_second":"36分44秒"}]},"status":4,"activity_rule":"xcy测试\n测试123","packData":{"redpack_price":"1.00","pack_type":-1,"friend_type":0,"is_help":0,"is_play":0,"pack_tip":"抱歉，您来晚了，红包已全部拆完啦~"},"timeData":{"day":"00","hour":"00","minutes":"00","seconds":"00"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    rid string 活动id
		 * @return_param    jid string 参与者id
		 * @return_param    external_id string 外部联系人id
		 * @return_param    title string 活动名称
		 * @return_param    is_use string code码是否使用过
		 * @return_param    status string 活动状态0删除、1未发布、2已发布、3到期结束、4裂变红包个数已用完、5、手动提前结束
		 * @return_param    area_type string 区域类型：1、不限制，2、部分地区
		 * @return_param    red_pack_price string 红包金额
		 * @return_param    is_remind string 是否需要提醒：0否、1是
		 * @return_param    remindData array 需要提醒数据
		 * @return_param    remindData.is_first string 类型：1首拆，0好友拆
		 * @return_param    remindData.first_amount string  首拆金额
		 * @return_param    remindData.rest_amount string  首拆剩余金额
		 * @return_param    remindData.rest_num string  剩余邀请人数
		 * @return_param    remindData.share_url string  分享链接
		 * @return_param    remindData.avatar string  好友头像
		 * @return_param    remindData.name string  好友名称
		 * @return_param    remindData.parent_name string  上级名称
		 * @return_param    remindData.amount string 拆领红包
		 * @return_param    remindData.play_url string 我也要参与链接
		 * @return_param    help_type string 好友帮拆或排行榜:-1、无，0、我的好友，1、排行榜
		 * @return_param    helpData array 好友帮拆或排行榜数据
		 * @return_param    activity_rule string 活动规则
		 * @return_param    packData array 红包数据
		 * @return_param    packData.redpack_price string 红包金额
		 * @return_param    packData.pack_type string 类型：-1、不可用，0、可拆，1、进行时，2、完成，3、失效，4、好友帮拆
		 * @return_param    packData.friend_type string 好友帮拆类型：0、进行中,1、好友帮拆成功，2、上级裂变成功，3、上级裂变失败
		 * @return_param    packData.is_help string 是否显示帮拆按钮
		 * @return_param    packData.is_play string 是否显示我也要参与按钮:0不显示，1、我也要参与，2、进入我的
		 * @return_param    packData.play_url string 相对应的链接
		 * @return_param    packData.pack_tip string 红包提示
		 * @return_param    packData.rest_num string 剩余邀请人数
		 * @return_param    packData.rest_amount string 剩余金额
		 * @return_param    packData.parent_name string 上级名称
		 * @return_param    packData.share_url string 分享链接
		 * @return_param    timeData array 时间数据
		 * @return_param    timeData.day string 天
		 * @return_param    timeData.hour string 小时
		 * @return_param    timeData.hour minutes 分钟
		 * @return_param    timeData.hour seconds 秒
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-04 10:57
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionIndex ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$agent_id = \Yii::$app->request->post('agent_id', '');
			$code     = \Yii::$app->request->post('code', '');
			$assist   = \Yii::$app->request->post('assist', '');

			$web_url   = \Yii::$app->params['web_url'];
			$stateArr  = explode('_', $assist);
			$rid       = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
			$parent_id = isset($stateArr[2]) ? intval($stateArr[2]) : 0;
			$redPack   = RedPack::findOne($rid);
			if (empty($redPack)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			$corpAgent   = WorkCorpAgent::findOne($redPack->agent_id);
			//如果传external_id，则不走code获取用户信息
			$external_id = \Yii::$app->request->post('external_id', '');
			try {
				$openId = '';
				if (empty($external_id)) {

					WorkUtils::getUserData($code, $corp_id, $result, [], true);

					if (!empty($result->UserId)) {
						throw new InvalidDataException('您已是企业成员或已绑定过个人微信，均无法参与活动！');
					} elseif ($result->OpenId) {
						\Yii::error($result->OpenId, 'OpenId');
						$openId          = $result->OpenId;
						$externalContact = WorkExternalContact::findOne(['corp_id' => $corp_id, 'openid' => $result->OpenId]);
						if (!empty($externalContact)) {
							$external_id = $externalContact->id;
						}
					} else {
						throw new InvalidDataException('获取用户信息失败，请重新刷新');
					}
				} else {
					$externalContact = WorkExternalContact::findOne($external_id);
					if (!empty($externalContact)) {
						$openId = $externalContact->openid;
					}
				}
			} catch (\Exception $e) {
				$message = $e->getMessage();
				if (strpos($message, '40029') !== false) {
					$message = '不合法的oauth_code';
				} elseif (strpos($message, '50001') !== false) {
					$message = 'redirect_url未登记可信域名';
				}
				throw new InvalidDataException($message);
			}

			//活动状态
			$date = date('Y-m-d H:i:s');
			if ($redPack->status == 2 && ($redPack->end_time <= $date)) {
				\Yii::$app->queue->push(new SyncRedPackJob([
					'red_pack_id' => $redPack->id,
					'red_status'  => 3
				]));
				$redPack->status = 3;
				$redPack->update();
			}

			//助力倒计时
			$timeData = [
				'day'     => '00',
				'hour'    => '00',
				'minutes' => '00',
				'seconds' => '00',
			];
			if ($redPack->status == 2) {
				$time     = time();
				$end_time = strtotime($redPack->end_time);
				if ($end_time > $time) {
					$timestamp           = $end_time - $time;
					$timeData['day']     = floor($timestamp / (3600 * 24));
					$timeData['hour']    = floor(($timestamp % (3600 * 24)) / 3600);
					$timeData['minutes'] = floor(($timestamp % 3600) / 60);
					$timeData['seconds'] = floor($timestamp % 60);
					$timeData['day']     = ($timeData['day'] >= 10) ? (string) $timeData['day'] : '0' . $timeData['day'];
					$timeData['hour']    = ($timeData['hour'] >= 10) ? (string) $timeData['hour'] : '0' . $timeData['hour'];
					$timeData['minutes'] = ($timeData['minutes'] >= 10) ? (string) $timeData['minutes'] : '0' . $timeData['minutes'];
					$timeData['seconds'] = ($timeData['seconds'] >= 10) ? (string) $timeData['seconds'] : '0' . $timeData['seconds'];
				}
			}

			//任务是否结束
			$is_end = 0;
			if (in_array($redPack->status, [0, 3, 4, 5])) {
				$is_end = 1;
			}

			//链接
			$workCorp  = WorkCorp::findOne($redPack->corp_id);
			$share_url = $web_url . RedPack::H5_URL . '?corp_id=' . $redPack->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $redPack->agent_id;
			if (!empty($corpAgent) && $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
				$share_url .= '&suite_id=' . $corpAgent->suite->suite_id;
			}
			try {
				$nick_name = $head_url = $help_tip = '';
				$joinData  = $remindData = [];
				$jid       = $is_remind = $is_own = 0;
				//有上级
				if (!empty($parent_id)) {
					$joinData = RedPackJoin::findOne(['uid' => $redPack->uid, 'rid' => $redPack->id, 'external_id' => $parent_id]);
					if (empty($joinData)) {
						$parent_id = 0;
					}
					if (!empty($externalContact) && ($parent_id == $externalContact->id)) {
						$is_own    = 1;
						$nick_name = urldecode($externalContact->name);
						$head_url  = $externalContact->avatar;
					}
				}

				if (empty($parent_id) && !empty($externalContact)) {
					$joinData = RedPackJoin::findOne(['uid' => $redPack->uid, 'rid' => $redPack->id, 'external_id' => $externalContact->id]);
					if (!empty($joinData)) {
						$is_own    = 1;
						$nick_name = urldecode($externalContact->name);
						$head_url  = $externalContact->avatar;
					}
				}

				//红包数据
				$packData = [
					'redpack_price' => $redPack->redpack_price,//红包金额
					'pack_type'     => 0,//类型：-1、不可用，0、可拆，1、进行时，2、完成，3、失效，4、好友帮拆
					'friend_type'   => 0,//好友帮拆类型：0、进行中,1、好友帮拆成功，2、上级裂变成功，3、上级裂变失败
					'first_amount'  => 0,//首拆金额
					'rest_amount'   => 0,//剩余金额
					'amount'        => 0,//好友帮拆后的红包金额
					'is_help'       => 0,//是否显示帮拆按钮 0:隐藏、1：显示、2：隐藏并且次数达到限制
					'is_play'       => 0,//是否显示我也要参与按钮:0不显示，1、我也要参与，2、进入我的
					'play_url'      => '',//相对应的链接
					'end_time'      => substr($redPack->end_time, 0, 10),//结束时间

				];

				if (!empty($joinData)) {
					$jid    = $joinData->id;
					$assist = RedPack::RED_HEAD . '_' . $redPack->id . '_' . $joinData->external_id;
					if (!empty($is_own)) {//自己
						if ($joinData->status == 2) {
							$packData['pack_type'] = 2;
						} else {
							$packData['rest_amount']  = $joinData->rest_amount;
							$packData['first_amount'] = $joinData->first_amount;
							//活动是否结束时
							if (empty($is_end)) {
								$rest_num              = $joinData->invite_amount - $joinData->help_num;
								$packData['rest_num']  = $rest_num;
								$packData['pack_type'] = 1;
								$packData['share_url'] = $share_url . '&assist=' . $assist;
								//提醒数据
								if (!empty($joinData->is_remind)) {
									$is_remind           = 1;
									$joinData->is_remind = 0;
									$joinData->update();

									$remindData = [
										'is_first'     => 1,
										'first_amount' => $joinData->first_amount,
										'rest_amount'  => $joinData->rest_amount,
										'rest_num'     => $rest_num,
										'end_time'     => substr($redPack->end_time, 0, 10),
										'share_url'    => $share_url . '&assist=' . $assist,
									];
								}
							} else {
								$packData['pack_type']    = 3;
							}
						}
					} else {//好友助力
						$parentContact           = WorkExternalContact::findOne($joinData->external_id);
						$packData['rest_amount'] = $joinData->rest_amount;
						$packData['parent_name'] = !empty($parentContact->name) ? rawurldecode($parentContact->name) : $parentContact->name_convert;
						$packData['pack_type']   = 4;

						//是否已参与过
						if (!empty($external_id)) {
							$friendJoin = RedPackJoin::findOne(['uid' => $redPack->uid, 'rid' => $redPack->id, 'external_id' => $external_id]);
							if (!empty($friendJoin)) {
								$packData['is_play']  = 2;
								$packData['play_url'] = $share_url . '&assist=' . RedPack::RED_HEAD . '_' . $redPack->id . '_' . $external_id;
							}
						}

						//活动是否结束时
						if (empty($is_end)) {
							//未完成时显示帮拆按钮
							if ($joinData->status == 2) {
								$packData['friend_type'] = 2;
							} else {
								$packData['is_help'] = 1;
							}
							if (empty($packData['is_play'])) {
								$joinCount = RedPackJoin::find()->where(['rid' => $rid])->count();
								if ($joinCount < $redPack->redpack_num) {
									$packData['is_play']  = 1;
									if (!empty($external_id)) {
										$packData['play_url'] = $share_url . '&assist=' . RedPack::RED_HEAD . '_' . $redPack->id . '_' . $external_id;
									} else {
										$packData['play_url'] = $share_url . '&assist=' . RedPack::RED_HEAD . '_' . $redPack->id . '_0';
									}
								}
							}

							if (!empty($externalContact)) {
								$helpDetail = RedPackHelpDetail::findOne(['jid' => $joinData->id, 'external_id' => $externalContact->id]);
								if (!empty($helpDetail)) {
									$packData['amount']      = $helpDetail->amount;
									$packData['friend_type'] = 1;
									$packData['is_help']     = 0;
									//提醒数据
									if (!empty($helpDetail->is_remind)) {
										$is_remind             = 1;
										$helpDetail->is_remind = 0;
										$helpDetail->update();
										$remindData = [
											'is_first'    => 0,
											'avatar'      => $externalContact->avatar,
											'name'        => !empty($externalContact->name) ? rawurldecode($externalContact->name) : $externalContact->name_convert,
											'parent_name' => !empty($parentContact->name) ? rawurldecode($parentContact->name) : $externalContact->name_convert,
											'amount'      => $helpDetail->amount,
											'end_time'    => substr($redPack->end_time, 0, 10),
											'play_url'    => $packData['play_url'],
										];
									}
								} else {
									//助力次数限制
									if (!empty($redPack->help_limit) && !empty($packData['is_help'])) {
										$helpCount = RedPackHelpDetail::find()->where(['rid' => $redPack->id, 'external_id' => $externalContact->id])->count();
										if ($helpCount >= $redPack->help_limit) {
											$packData['is_help'] = 2;
											$cacheLimitKey = 'help_limit_' . $redPack->id . '_' . $jid . '_' . $external_id;
											$cacheLimit    = \Yii::$app->cache->get($cacheLimitKey);
											if (empty($cacheLimit)) {
												$help_tip = '帮拆次数已达限制，不能再帮拆';
												\Yii::$app->cache->set($cacheLimitKey, 1, $timestamp);
											}
										}
									}
								}
							}
						} else {
							if ($joinData->status == 2) {
								$packData['friend_type'] = 2;
							} else {
								$packData['friend_type']  = 3;
								$packData['first_amount'] = $joinData->first_amount;
								$packData['rest_amount']  = $joinData->rest_amount;
							}
						}
					}
				} else {
					//活动结束时
					if (!empty($is_end)) {
						$packData['pack_type'] = -1;
						if ($redPack->status == 4) {
							$pack_tip = '抱歉，您来晚了，红包已全部拆完啦~';
						} else {
							$pack_tip = '抱歉，您来晚了，活动已结束';
						}
						$packData['pack_tip'] = $pack_tip;
					} else {
						//判断下参与者数量是否达到红包数
						$joinCount = RedPackJoin::find()->where(['rid' => $rid])->count();
						if ($joinCount >= $redPack->redpack_num) {
							$packData['pack_type'] = -1;
							$packData['pack_tip']  = '抱歉，当前裂变红包个数已抢完';
						}

					}
				}

				//加载好友帮拆或排行榜
				$help_type = -1;
				$helpData  = [];
				if (!empty($joinData) && $joinData->help_num > 0) {
					$help_type = 0;
					$helpData  = RedPackJoin::friendList($joinData);
				}
				$joinCount = RedPackJoin::find()->where(['rid' => $redPack->id, 'status' => 2])->count();
				if ($help_type == -1) {
					if (!empty($joinCount)) {
						$help_type = 1;
						$helpData  = RedPackJoin::rankList($redPack->id, $joinData, $external_id);
					}
				}
				$site_url = \Yii::$app->params['site_url'];
				if (!empty($head_url)) {
					//获取远程文件所采用的方法
					$curl = new Curl();
					$response = $curl->setOptions([
						CURLOPT_CONNECTTIMEOUT => 300,
						CURLOPT_FOLLOWLOCATION => true
					])->get($head_url);
					$base64Data = 'data:image/png;base64,' . base64_encode($response);
				} else {//默认头像
					$base64Data = $head_url = $site_url . '/static/image/default-avatar.png';
				}

				$returnData = [
					'rid'            => $redPack->id,
					'jid'            => $jid,
					'external_id'    => $external_id,
					'title'          => $redPack->title,
					'is_use'         => 0,
					'area_type'      => $redPack->area_type,//区域类型：1、不限制，2、部分地区
					'red_pack_price' => $redPack->redpack_price,
					'is_remind'      => $is_remind,//是否需要提醒：0否、1是
					'remindData'     => $remindData,//提醒数据
					'help_type'      => $help_type,//好友帮拆或排行榜:-1、无，0、我的好友，1、排行榜，2、全部
					'helpData'       => $helpData,//好友帮拆或排行榜数据
					'status'         => $redPack->status,//状态0删除、1未发布、2已发布、3到期结束、4裂变红包个数已用完、5、手动提前结束
					'openid'         => $openId,
					'activity_rule'  => $redPack->activity_rule,//活动规则
					'contact_phone'  => $redPack->contact_phone,//联系电话
					'packData'       => $packData,
					'timeData'       => $timeData,
					'share_url'      => $share_url . '&assist=' . $assist,
					'picRule'        => json_decode($redPack->pic_rule, 1),
					'nick_name'      => $nick_name,
					'head_url'       => $head_url,
					'help_tip'       => $help_tip,
					'base64Data'     => $base64Data,
				];
				\Yii::error($returnData,'returnData');
				return $returnData;
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-red-pack/
		 * @title           拆红包
		 * @description     拆红包
		 * @method   post
		 * @url  http://{host_name}/api/wap-red-pack/open-pack
		 *
		 * @param rid 必选 string 裂变任务id
		 * @param jid 可选 string 参与者id
		 * @param external_id 可选 string 外部联系人id
		 * @param lat 可选 string 精度
		 * @param lng 可选 string 纬度
		 *
		 * @return          {"error":0,"data":{"open_type":1,"amount":0.55,"rest_amount":9.45,"invite_amount":2,"assist":"red_6_2375","share_url":""}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    open_type string 数据类型：0、二维码，1、首拆红包，2、好友拆
		 * @return_param    qr_code string 二维码地址，open_type=0时
		 * @return_param    amount string 首拆获取金额
		 * @return_param    rest_amount string 首拆剩余金额
		 * @return_param    invite_amount string 需邀请人数
		 * @return_param    share_url string 生成海报链接
		 * @return_param    avatar string 好友头像
		 * @return_param    name string 好友名称
		 * @return_param    parent_name string 上级名称
		 * @return_param    picRule array 海报数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-05 14:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionOpenPack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}
			$rid         = \Yii::$app->request->post('rid', 0);//红包裂变任务id
			$jid         = \Yii::$app->request->post('jid', 0);//参与者id
			$external_id = \Yii::$app->request->post('external_id', 0);
			$lat         = \Yii::$app->request->post('lat', 0);
			$lng         = \Yii::$app->request->post('lng', 0);
			$uid         = \Yii::$app->request->post('uid', 0);

			if (empty($rid)) {
				throw new InvalidDataException('参数不正确');
			}

            if (empty($uid)) {
                throw new InvalidDataException('参数不正确,请刷新页面或清理缓存后重试！');
            }

			$redPack = RedPack::findOne($rid);
			if (empty($redPack)) {
				throw new InvalidDataException('参数不正确');
			}

			try {
				$date = date('Y-m-d H:i:s');
				if ($redPack->status == 1) {
					throw new InvalidDataException('活动未发布');
				} elseif (in_array($redPack->status, [0, 3, 4, 5])) {
					throw new InvalidDataException('活动已结束');
				} elseif ($redPack->status == 2 && ($redPack->end_time <= $date)) {
					\Yii::$app->queue->push(new SyncRedPackJob([
						'red_pack_id' => $redPack->id,
						'red_status'  => 3
					]));
					//RedPack::handleData($redPack, 3);
					throw new InvalidDataException('活动已结束');
				}

				if (!empty($jid)) {//有上级时，$jid不能为空
					$joinData = RedPackJoin::findOne($jid);
					if (empty($joinData)) {
						throw new InvalidDataException('无此上级，请检查');
					}
					//如果助力者人数够了，就不给助力了
					if ($joinData->help_num >= $joinData->invite_amount) {
						throw new InvalidDataException('帮拆人数已达到，无需再拆');
					}
				} else {
					//判断当前活动下参与者数量是否达到红包数
					$joinCount = RedPackJoin::find()->where(['rid' => $rid])->count();
					if ($joinCount >= $redPack->redpack_num) {
						throw new InvalidDataException('抱歉，当前裂变红包个数已抢完');
					}
				}

				//检查区域限制
				if ($redPack->area_type == 2) {
					$areaData = json_decode($redPack->area_data, 1);
					$address  = RedPack::getAddress($lat, $lng);
					$is_limit = RedPack::checkArea($address, $areaData);
					if (!empty($is_limit)) {
						if (empty($jid)) {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与。';
						} else {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与，无法帮好友拆红包。';
						}
						throw new InvalidDataException($message);
					}
				}

                //判断人数限制
                $red_envelopes_num = (int)User::getPackageAboutNum($uid,'red_envelopes_num');
                if ($red_envelopes_num > 0) {
                    $joinNum = RedPackJoin::find()->where(['rid'=>$rid])->count();
                    if ($joinNum >= $red_envelopes_num) {
                        RedPack::setActivityOver($rid);
                        throw new InvalidDataException('很抱歉，当前活动过于火爆暂时无法参与');
                    }
                }

				//当无客户时返回二维码添加
				if (empty($external_id)) {
                    //判断添加人数是否达到上限
                    $red_envelopes_num = (int)User::getPackageAboutNum($uid,'red_envelopes_num');
                    if ($red_envelopes_num > 0) {
                        $addFriendNum = WorkExternalContactFollowUser::find()->where(['red_pack_id'=>$rid])->count();
                        if ($addFriendNum >= $red_envelopes_num) {
                            throw new InvalidDataException('很抱歉，当前活动过于火爆暂时无法参与');
                        }
                    }
					if (empty($jid)) {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $redPack->qr_code];
					} else {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $joinData->qr_code];
					}
				} elseif ($redPack->sex_type != 1) {//检查性别
					$is_limit = RedPack::checkSex($external_id, $redPack->sex_type);
					if ($redPack->sex_type == 2) {
						$sex_name = '男性';
					} elseif ($redPack->sex_type == 3) {
						$sex_name = '女性';
					} else {
						$sex_name = '未知';
					}
					if (!empty($is_limit)) {
						if (empty($jid)) {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与。';
						} else {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与，您无法帮好友拆红包。';
						}
						throw new InvalidDataException($message);
					}
				}
				//判断当前客户是否加过任务中的成员
				$userIdList = WorkExternalContactFollowUser::find()->where(['external_userid' => $external_id, 'del_type' => 0])->select('userid')->all();
				if (!empty($userIdList)) {
					$userId    = array_column($userIdList, 'userid');//此客户添加的成员
					$userArr   = json_decode($redPack->user, 1);//任务中成员
					$intersect = array_intersect($userId, $userArr);
				}

				if (!empty($intersect)) {//已加过成员，直接领红包
					//链接
					$web_url   = \Yii::$app->params['web_url'];
					$workCorp  = WorkCorp::findOne($redPack->corp_id);
					$corpAgent = WorkCorpAgent::findOne($redPack->agent_id);
					$share_url = $web_url . RedPack::H5_URL . '?corp_id=' . $redPack->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $redPack->agent_id;
					if ($corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
						$share_url .= '&suite_id=' . $corpAgent->suite->suite_id;
					}
					if (empty($jid)) {
						$data              = RedPackJoin::setJoin($redPack, [RedPack::RED_HEAD, $redPack->id, $external_id]);
						$data['is_play']   = 0;
						$data['open_type'] = 1;
					} else {
						$data              = RedPackJoin::setHelpDetail($redPack, [RedPack::RED_HEAD, $redPack->id, $joinData->external_id, $external_id]);
						$data['is_play']   = 0;
						$data['open_type'] = 2;
						$friendJoin        = RedPackJoin::findOne(['uid' => $redPack->uid, 'rid' => $redPack->id, 'external_id' => $external_id]);
						if (!empty($friendJoin)) {
							$data['is_play'] = 2;
							$data['assist']  = RedPack::RED_HEAD . '_' . $redPack->id . '_' . $external_id;
						} else {
							$joinCount = RedPackJoin::find()->where(['rid' => $redPack->id])->count();
							if ($joinCount < $redPack->redpack_num) {
								$data['is_play'] = 1;
								$data['assist']  = RedPack::RED_HEAD . '_' . $redPack->id . '_' . $external_id;
							}
						}
					}
					if (!empty($data['assist'])) {
						$data['share_url'] = $share_url . '&assist=' . $data['assist'];
					} else {
						$data['share_url'] = '';//为空时不显示我也要参与
					}
					$data['end_time'] = substr($redPack->end_time, 0, 10);
					$data['err_msg']  = '';

					return $data;
				} else {//未加过，返回二维码链接
					if (empty($jid)) {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $redPack->qr_code];
					} else {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $joinData->qr_code];
					}
				}
			} catch (InvalidDataException $e) {
				return ['err_msg' => $e->getMessage()];
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-red-pack/
		 * @title           助力列表、排行榜
		 * @description     助力列表、排行榜
		 * @method   post
		 * @url  http://{host_name}/api/wap-red-pack/help-list
		 *
		 * @param rid 必选 string 裂变任务id
		 * @param jid 可选 string 参与者id
		 * @param help_type 必选 string 类型：0我的好友、1排行榜
		 * @param external_id 可选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"count":"2","tips":"已有2人获得裂变红包","info":{"avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5NSMiaygS8Qfd80LcgrMy0bwhnwqjMsRAnu9Nz7uiclYSA/0","name":"一切随缘","complete_second":"19分","ranking":2},"join":[{"key":"2","id":"2","name":"王盼","avatar":"http://wx.qlogo.cn/mmhead/GibvHudxmlJbHQEV84mpeundfic12MygBduhEaAGN01ibrCBiaeibBLOJAg/0","amount":"12.00","complete_second":"11秒"},{"key":"1","id":"1","name":"一切随缘","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5NSMiaygS8Qfd80LcgrMy0bwhnwqjMsRAnu9Nz7uiclYSA/0","amount":"12.00","complete_second":"19分"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    tips string 提示
		 * @return_param    info array 排行榜自己的数据
		 * @return_param    join array 列表数据
		 * @return_param    join.name string 名字
		 * @return_param    join.avatar string 头像
		 * @return_param    join.amount string 金额
		 * @return_param    join.help_time string 助力时间
		 * @return_param    join.complete_second string 完成时间
		 * @return_param    join.ranking string 自己排名
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-03 20:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionHelpList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}
			$rid         = \Yii::$app->request->post('rid', 0);
			$jid         = \Yii::$app->request->post('jid', 0);
			$help_type   = \Yii::$app->request->post('help_type', 0);
			$external_id = \Yii::$app->request->post('external_id', '');
			if (!empty($jid)) {
				$joinInfo = RedPackJoin::findOne($jid);
			} else {
				$joinInfo = [];
			}
			if ($help_type == 0) {
				$result = RedPackJoin::friendList($joinInfo);
			} else {
				$result = RedPackJoin::rankList($rid, $joinInfo, $external_id);
			}

			return $result;
		}
	}