<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019-09-10
	 * Time: 13:21
	 */

	namespace app\modules\wechat\controllers;

	use app\components\InvalidDataException;
	use app\models\Fans;
	use app\models\FansMsg;
	use app\models\FansTimeLine;
	use app\models\HighLevelPushMsg;
	use app\models\KfPushPreview;
	use app\models\MiniMsg;
	use app\models\MiniUser;
	use app\models\Scene;
	use app\models\InteractReply;
	use app\models\InteractReplyDetail;
	use app\models\Tags;
	use app\models\Template;
	use app\models\TemplatePushInfo;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkPublicActivity;
	use app\models\WorkPublicActivityConfigCall;
	use app\models\WorkPublicActivityFansUser;
	use app\models\WorkPublicActivityFansUserDetail;
	use app\models\WorkPublicActivityPosterConfig;
	use app\models\WorkPublicActivityTier;
	use app\models\WorkUserAuthorRelation;
	use app\models\WxAuthorize;
	use app\models\WxAuthorizeConfig;
	use app\models\WxAuthorizeInfo;
	use app\models\WxMsg;
	use app\modules\wechat\components\BaseController;
	use app\queue\TaskTreasureJob;
	use app\util\apiOauth;
	use app\util\DateUtil;
	use app\util\MsgUtil;
	use app\util\SUtils;
	use app\util\WebsocketUtil;
	use app\util\WorkPublicPoster;
	use app\util\WorkUtils;
	use app\util\WxConstUtil;
	use callmez\wechat\sdk\components\MessageCrypt;
	use callmez\wechat\sdk\Wechat;
	use dovechen\yii2\weWork\src\dataStructure\Message;
	use dovechen\yii2\weWork\src\dataStructure\TextcardMesssageContent;
	use yii\db\Expression;

	class EventController extends BaseController
	{
		const SUBSCRIBE_SEND_TYPE = 'subscribe';

		public $authorAppId;
		public $appid;
		public $appSecret;
		public $token;
		public $encodingAESKey;

		/** @var \DOMDocument $xml */
		public $xml;
		public $encryptMsg;
		public $returnEncryptMsg = "";
		public $decryptCode = 0;
		public $postData = [];

		/**
		 * @param $action
		 *
		 * @return bool
		 * @throws \yii\web\BadRequestHttpException
		 */
		public function beforeAction ($action)
		{
			$this->enableCsrfValidation = false;

			if (\Yii::$app->request->isPost) {
				$this->encryptMsg = @file_get_contents("php://input");

				\Yii::info('encryptMsg: ' . $this->encryptMsg);

				if (!empty($this->encryptMsg)) {
					if (isset($_GET['g'])) {
						unset($_GET['g']);
						unset($_GET['m']);
						unset($_GET['a']);
					}

					$this->xml = new \DOMDocument();
					$this->xml->loadXML($this->encryptMsg);

					$toUserNameItem = $this->xml->getElementsByTagName("ToUserName");
					$toUserName     = $toUserNameItem->item(0)->nodeValue;

					$allResponseUserNameArr = ['gh_3c884a361561', 'gh_8dad206e9538'];
					if (in_array($toUserName, $allResponseUserNameArr)) {
						// 全网发布代码
						$configId        = isset($_GET['type']) ? $_GET['type'] : 0;
						$authorizeConfig = WxAuthorizeConfig::findOne(['id' => $configId]);
						if (!empty($authorizeConfig)) {
							$this->appid          = $authorizeConfig->appid;
							$this->appSecret      = $authorizeConfig->appSecret;
							$this->token          = $authorizeConfig->token;
							$this->encodingAESKey = $authorizeConfig->encode_aes_key;

							$xpath = new \DOMXPath($this->xml);
							$tmp   = $xpath->query('Encrypt')->length;

							if ($tmp != 0) {
								$signature         = isset($_GET['msg_signature']) ? $_GET['msg_signature'] : $_GET['signature'];
								$this->decryptCode = $this->encodeData($signature, $_GET['timestamp'], $_GET['nonce']);
							} else {
								$this->postData = $this->encryptMsg;
							}
						} else {
							echo '';
							exit();
						}
					} else {
						$wxAuthorizeInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);

						if (!empty($wxAuthorizeInfo)) {
							$authConfig = $wxAuthorizeInfo->author->config;

							$this->appid          = $authConfig->appid;
							$this->appSecret      = $authConfig->appSecret;
							$this->token          = $authConfig->token;
							$this->encodingAESKey = $authConfig->encode_aes_key;

							$xpath = new \DOMXPath($this->xml);
							$tmp   = $xpath->query('Encrypt')->length;

							if ($tmp != 0) {
								$signature         = isset($_GET['msg_signature']) ? $_GET['msg_signature'] : $_GET['signature'];
								$this->decryptCode = $this->encodeData($signature, $_GET['timestamp'], $_GET['nonce']);
							} else {
								$this->postData = $this->encryptMsg;
							}
						} else {
							echo '';
							exit();
						}
					}

					$this->xml = new \DOMDocument();
					$this->xml->loadXML($this->postData);

					$this->authorAppId = ltrim($_GET['appid'], '/');
				}
			}

			return parent::beforeAction($action);
		}

		/**
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionIndex ()
		{
			if (\Yii::$app->request->isGet) {
				$signature = isset($_GET['signature']) ? $_GET['signature'] : '';
				$echostr   = isset($_GET['echostr']) ? $_GET['echostr'] : '';
				$timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : '';
				$nonce     = isset($_GET['nonce']) ? $_GET['nonce'] : '';

				$list = [$this->token, $timestamp, $nonce];
				sort($list, SORT_STRING);
				$str      = implode($list);
				$hashCode = sha1($str);

				if ($hashCode == $signature) {
					echo $echostr;
				} else {
					echo '';
				}
			} elseif (\Yii::$app->request->isPost) {
				if ($this->decryptCode == 0) {
					// 判断是否为第三方平台安全TICKET
					$infoTypeArray = $this->xml->getElementsByTagName("MsgType");
					$infoType      = $infoTypeArray->item(0)->nodeValue;
					$infoType      = strtolower($infoType);

					// 全网发布代码
					$allResponseAppidArr = ['wx570bc396a51b8ff8', 'wxd101a85aa106f53e'];
					if (in_array($this->authorAppId, $allResponseAppidArr)) {
						echo $this->allSitesResponse($infoType);
					} else {
						/*if ($infoType != WxConstUtil::WX_MSG_EVENT_TYPE) {
							// 判断是否要推送到客服系统
							$url      = 'http://zhukui.bupu.net/index.php?g=Home&m=apiFuse&a=customer_service&appid=' . $this->authorAppId;
							$curl     = new Curl();
							$response = $curl->setOptions([
								CURLOPT_POST       => true,
								CURLOPT_POSTFIELDS => $this->postData,
							])->post($url);

							\Yii::info($curl->responseCode);
							\Yii::info($response);
							if ($curl->responseCode == 200) {
								$response = json_decode($response, JSON_UNESCAPED_UNICODE);
								if (!empty($response)) {
									$fromUserNameItem = $this->xml->getElementsByTagName("ToUserName");
									$fromUserName     = $fromUserNameItem->item(0)->nodeValue;

									$toUserNameItem = $this->xml->getElementsByTagName('FromUserName');
									$toUserName     = $toUserNameItem->item(0)->nodeValue;

									$createTime = time();

									$replyMsg
										= "<xml>
     <ToUserName><![CDATA[" . $toUserName . "]]></ToUserName>
     <FromUserName><![CDATA[" . $fromUserName . "]]></FromUserName>
     <CreateTime>" . $createTime . "</CreateTime>
     <MsgType><![CDATA[transfer_customer_service]]></MsgType>
 </xml>";

									// 对回复的消息进行加密处理
									$encryptCode = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
									\Yii::info($encryptCode);
									if ($encryptCode == 0) {
										\Yii::info($replyMsg);
										\Yii::info($this->returnEncryptMsg);
										echo $this->returnEncryptMsg;
									} else {
										echo $this->sendToQueue($infoType);
									}
								} else {
									echo $this->sendToQueue($infoType);
								}
							} else {
								echo $this->sendToQueue($infoType);
							}
						} else {*/
						echo $this->sendToQueue($infoType);
//						}
					}
				} else {
					echo $this->decryptCode;
				}
			}
		}

		/**
		 * 发送消息进队列
		 *
		 * @param $infoType
		 *
		 * @return string
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 * @throws \yii\db\StaleObjectException
		 */
		private function sendToQueue ($infoType)
		{
			\Yii::info($infoType);
			switch ($infoType) {
				case WxConstUtil::WX_MSG_EVENT_TYPE:
					return $this->event();

					break;
				case WxConstUtil::WX_MSG_TEXT_TYPE:
					return $this->text();

					break;
				case WxConstUtil::WX_MSG_IMAGE_TYPE:
					return $this->image();

					break;
				case WxConstUtil::WX_MSG_VOICE_TYPE:
					return $this->voice();

					break;
				case WxConstUtil::WX_MSG_VIDEO_TYPE:
					return $this->video();

					break;
				case WxConstUtil::WX_MSG_SHORT_VIDEO_TYPE:
					return $this->shortVideo();

					break;
				case WxConstUtil::WX_MSG_LOCATION_TYPE:
					return $this->location();

					break;
				case WxConstUtil::WX_MSG_LINK_TYPE:
					return $this->link();

					break;
				default:
					return "";

					break;
			}
		}

		/**
		 * @param $msgSignTrue
		 * @param $timestamp
		 * @param $nonce
		 *
		 * @return int
		 */
		private function encodeData ($msgSignTrue, $timestamp, $nonce)
		{
			// 对返回的第三方平台安全TICKET进行解密
			$messageCrypt = new MessageCrypt($this->token, $this->encodingAESKey, $this->appid);
			$decryptCode  = $messageCrypt->decryptMsg($msgSignTrue, $timestamp, $nonce, $this->encryptMsg, $this->postData);

			return $decryptCode;
		}

		/**
		 * @param $replyMsg
		 * @param $timeStamp
		 * @param $nonce
		 *
		 * @return int
		 */
		private function decodeData ($replyMsg, $timeStamp, $nonce)
		{
			// 对返回的第三方平台安全TICKET进行解密
			$messageCrypt = new MessageCrypt($this->token, $this->encodingAESKey, $this->appid);
			$encryptCode  = $messageCrypt->encryptMsg($replyMsg, $timeStamp, $nonce, $this->returnEncryptMsg);

			return $encryptCode;
		}

		/**
		 * 获取来源、公众号的唯一ID和事件时间
		 * @return array
		 */
		private function getFromToTime ()
		{
			// 获取来源
			$fromUserNameItem = $this->xml->getElementsByTagName('FromUserName');
			$fromUserName     = $fromUserNameItem->item(0)->nodeValue;

			// 获取公众号的唯一ID
			$toUserNameItem = $this->xml->getElementsByTagName("ToUserName");
			$toUserName     = $toUserNameItem->item(0)->nodeValue;

			// 获取事件时间
			$createTimeItem = $this->xml->getElementsByTagName('CreateTime');
			$createTime     = $createTimeItem->item(0)->nodeValue;

			return [$fromUserName, $toUserName, $createTime];
		}

		/**
		 * 创建粉丝
		 *
		 * @param WxAuthorizeInfo $authorInfo
		 * @param                 $openid
		 *
		 * @return Fans|array|null
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function fansCreate ($authorInfo, $openid)
		{
			$fansInfo     = [];
			$fansInfoData = Fans::getFansInfo($authorInfo->authorizer_appid, $openid);
			if (!empty($fansInfoData)) {
				foreach ($fansInfoData as $fansTmpInfo) {
					$fansId = Fans::create($authorInfo->author_id, $fansTmpInfo['openid'], $fansTmpInfo);
					if ($fansId) {
						$fansInfo = Fans::findOne(['id' => $fansId]);
					}
				}
			}

			return $fansInfo;
		}

		/**
		 * 创建小程序用户
		 *
		 * @param WxAuthorizeInfo $authorInfo
		 * @param                 $openid
		 * @param string          $unionid
		 *
		 * @return MiniUser|array|null
		 *
		 * @throws InvalidDataException
		 */
		private function miniCreate ($authorInfo, $openid, $unionid = '')
		{
			$miniInfo = [];
			$miniId   = MiniUser::create($authorInfo->author_id, $openid, $unionid);
			if (!empty($miniId)) {
				$miniInfo = MiniUser::findOne($miniId);
			}

			return $miniInfo;
		}

		/**
		 * 事件处理逻辑
		 *
		 * @return string
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 * @throws \yii\db\StaleObjectException
		 */
		private function event ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取event名称
			$eventItem = $this->xml->getElementsByTagName("Event");
			$event     = $eventItem->item(0)->nodeValue;
			$event     = strtolower($event);
			// 获取EventKey名称
			$eventItem = $this->xml->getElementsByTagName("EventKey");
			if (isset($eventItem->item(0)->nodeValue)) {
				$eventKey = $eventItem->item(0)->nodeValue;
				//仅供粉丝轨迹使用
				$fansKey  = $eventKey;
				$eventKey = strtolower($eventKey);
			} else {
				$eventKey = '';
			}
			\Yii::error($eventKey,'$eventKey');

			// 生成去重字符串
			$msgType = $fromUserName . "_" . $createTime . "_" . $event;
			if ($event == WxConstUtil::WX_USER_GET_CARD_EVENT) {
				// 获取用户卡号
				$userCardCodeItem = $this->xml->getElementsByTagName('UserCardCode');
				$userCardCode     = $userCardCodeItem->item(0)->nodeValue;

				$msgType .= "_" . $userCardCode;
			}

			$wxMsg = WxMsg::findOne(['key' => $msgType]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgType;
				$wxMsg->msg_type       = WxMsg::EVENT_MSG;
				$wxMsg->msg_type_value = $event;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						switch ($event) {
							case WxConstUtil::WX_SUBSCRIBE_EVENT:
								$activity = explode("_", $eventKey);
								\Yii::error($activity, '$eventKey');
								if (count($activity) > 1 && $activity[1] == WorkPublicActivity::STATE_NAME) {
									array_shift($activity);
								}

								// 根据粉丝的 openid 刷新粉丝信息
								$fansInfoData = Fans::getFansInfo($wxAuthorInfo->authorizer_appid, $fromUserName);
								if (!empty($fansInfoData)) {
									foreach ($fansInfoData as $fansInfo) {
										try {
											$fansId = Fans::create($wxAuthorInfo->author->author_id, $fansInfo['openid'], $fansInfo,$activity);
											WxMsg::updateAll(['status' => 1, 'update_time' => DateUtil::getCurrentTime()], ['key' => $msgType]);
											//带参数的关注记录行为
											if (!empty($eventKey)) {
												$scanKey = substr($eventKey, 8);
												\Yii::error($scanKey,'$scanKey');
												if (!empty($scanKey)) {
													if(isset($activity[0]) && $activity[0] == WorkPublicActivity::STATE_NAME){
														$pubActivity = WorkPublicActivity::findOne($activity[1]);
														$title = "裂变引流-".$pubActivity->activity_name;
														FansTimeLine::create($fansId, FansTimeLine::SCAN_EVENT, $createTime, 0, 0, $title);
													}else{
														FansTimeLine::create($fansId, FansTimeLine::SCAN_EVENT, $createTime, 0, 0, $scanKey);
													}
												}
											}
											if (!empty($activity) && $activity[0] == WorkPublicActivity::STATE_NAME) {
												\Yii::$app->queue->push(new TaskTreasureJob([
													'activity'     => $activity,
													'fromUserName' => $fromUserName,
													'subscribe'    => 0,
												]));
											}
											$newFansInfo = Fans::findOne($fansId);
											$content     = '我是 ' . $newFansInfo->nickname . ' 刚刚关注了贵公众号';
											$this->saveMsg(FansMsg::TEXT_MSG, $content, $msgType, $wxAuthorInfo, $newFansInfo, FansTimeLine::SEND_TEXT, $createTime, static::SUBSCRIBE_SEND_TYPE);
										} catch (InvalidDataException $e) {
											\Yii::error('event: ' . $event . ', msgId: ' . $wxMsg->id . 'error_msg: ' . $e->getMessage());

											return '';
										}
									}

								}
								//关注回复
								$sendData = ['author_id' => $wxAuthorInfo->author_id, 'openid' => $fromUserName, 'user_name' => $toUserName, 'event' => $event, 'eventKey' => $eventKey, 'time' => $createTime, 'type' => 1];
								try {
									$result = InteractReply::sendMessage($sendData);
									if (!empty($result['replyMsg'])) {
										$replyMsg    = $result['replyMsg'];
										$encryptCode = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
										if ($encryptCode == 0) {
											$fans = Fans::findOne(['openid' => $fromUserName, 'author_id' => $wxAuthorInfo->author_id]);
											FansTimeLine::create($fans->id, 'news', time(), 0, 1, $result['time_json']);
											InteractReplyDetail::create($result['detail'], 1);
											echo $this->returnEncryptMsg;
										} else {
											InteractReplyDetail::create($result['detail'], 2);
										}
										//插入统计数据
										InteractReply::insertStatisc($result['inter_id'], $result['author_id']);
									}
								} catch (InvalidDataException $e) {
									\Yii::error('event: ' . $event . ', eventKey: ' . $eventKey . 'error_msg: ' . $e->getMessage());

									return '';
								}

								break;
							case WxConstUtil::WX_CLICK_EVENT:
								$fansInfo = Fans::findOne(['openid' => $fromUserName]);
								if (!empty($fansInfo)) {
									// 更新粉丝时间线
									FansTimeLine::create($fansInfo->id, FansTimeLine::CLICK_EVENT, $createTime, 0, 0, $fansKey);
								}
								break;
							case WxConstUtil::WX_VIEW_EVENT:
								$fansInfo = Fans::findOne(['openid' => $fromUserName]);
								if (!empty($fansInfo)) {
									// 更新粉丝时间线
									FansTimeLine::create($fansInfo->id, FansTimeLine::VIEW_EVENT, $createTime, 0, 0, $fansKey);
								}
								break;
							case WxConstUtil::WX_VIEW_MINIPROGRAM:
								$fansInfo = Fans::findOne(['openid' => $fromUserName]);
								if (!empty($fansInfo)) {
									//更新粉丝时间戳
									FansTimeLine::create($fansInfo->id, FansTimeLine::VIEW_MINIPROGRAM, $createTime, 0, 0, $fansKey);
								}
								break;
							case WxConstUtil::WX_UN_SUBSCRIBE_EVENT:
								Fans::unSubscribe($wxAuthorInfo->author->author_id, $fromUserName, $createTime);

								break;
							case WxConstUtil::WX_SCAN_EVENT:
								$activity = explode("_", $eventKey);
								\Yii::error($activity, '$activity');
								if (!empty($activity) && $activity[0] == WorkPublicActivity::STATE_NAME) {
									if (count($activity) > 6) {
										array_shift($activity);
									}
									\Yii::$app->queue->push(new TaskTreasureJob([
										'activity'     => $activity,
										'fromUserName' => $fromUserName,
										'subscribe'    => 1,
									]));
								}
								Fans::scanQrCode($wxAuthorInfo->author->author_id, $fromUserName, $eventKey);
								break;
							case WxConstUtil::WX_MASSSENDJOBFINISH:
								$eventItem = $this->xml->getElementsByTagName("MsgID");
								if (isset($eventItem->item(0)->nodeValue)) {
									$msgId = $eventItem->item(0)->nodeValue;
									\Yii::error($msgId, '$sql123');
									$sentItem = $this->xml->getElementsByTagName("SentCount");
									if (isset($sentItem->item(0)->nodeValue)) {
										$sentCount = $sentItem->item(0)->nodeValue;
									} else {
										$sentCount = 0;
									}
									\Yii::error($sentCount, '$sql123');
									if (!empty($msgId)) {
										$where     = new Expression('FIND_IN_SET(:msg_id, msg_id)', [':msg_id' => $msgId]);
										$highLevel = HighLevelPushMsg::find()->where(['author_id' => $wxAuthorInfo->author_id])->andWhere($where)->one();
										if (!empty($highLevel) && !empty($sentCount)) {
											$highLevel->updateCounters(['fans_num' => $sentCount]);
										}
										//状态
										$eventItem = $this->xml->getElementsByTagName("Status");
										if (isset($eventItem->item(0)->nodeValue)) {
											$status = $eventItem->item(0)->nodeValue;
											\Yii::error($status, 'Status123');
											if (strpos($status, 'err') !== false) {
												$highLevel->status     = 2;
												$highLevel->error_code = substr($status, 4, -1);;
												$highLevel->update();
											}
										}
									}
								}
								break;
							case WxConstUtil::WX_TEMPLATESENDJOBFINISH:
								try {
									// 获取用户卡号
									$msgIdItem = $this->xml->getElementsByTagName('MsgID');
									$msgId     = $msgIdItem->item(0)->nodeValue;

									$fans = Fans::findOne(['openid' => $fromUserName, 'author_id' => $wxAuthorInfo->author_id]);
									if (!empty($fans)) {
										$templatePushInfo = TemplatePushInfo::findOne(['fans_id' => $fans->id, 'message_id' => $msgId]);
										if (!empty($templatePushInfo)) {
											$templatePushInfo->status       = TemplatePushInfo::SEND_SUCCESS;
											$templatePushInfo->success_time = DateUtil::getCurrentTime();

											if (!$templatePushInfo->validate() || !$templatePushInfo->save()) {
												\Yii::error(SUtils::modelError($templatePushInfo), __CLASS__ . '-' . __FUNCTION__ . 'model');
											}
										}
									}
								} catch (\Exception $e) {
									\Yii::error('event: ' . $event . ', eventKey: ' . $eventKey . 'error_msg: ' . $e->getMessage());
								}

								break;
							default:
								\Yii::error('un know event: ' . $event . ', msgId: ' . $wxMsg->id);
								break;
						}
						//参数二维码发送
						if (!empty($eventKey) && in_array($event, [WxConstUtil::WX_SUBSCRIBE_EVENT, WxConstUtil::WX_SCAN_EVENT])) {
							try {
								$result = Scene::sceneSend(['author_id' => $wxAuthorInfo->author_id, 'openid' => $fromUserName, 'user_name' => $toUserName, 'eventKey' => $eventKey, 'event' => $event, 'time' => $createTime]);
								if (isset($result['push_type']) && $result['push_type'] == 1 && !empty($result['replyMsg'])) {
									$replyMsg    = $result['replyMsg'];
									$encryptCode = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
									if ($encryptCode == 0) {
										$fans = Fans::findOne(['openid' => $fromUserName, 'author_id' => $wxAuthorInfo->author_id]);
										FansTimeLine::create($fans->id, $result['type'], time(), 0, 2, $result['title']);
										echo $this->returnEncryptMsg;
									}
								}
							} catch (InvalidDataException $e) {
								\Yii::error('event: ' . $event . ', eventKey: ' . $eventKey . 'error_msg: ' . $e->getMessage());

								return '';
							}
						}
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}
			return '';
		}

		/**
		 * @param        $msgType
		 * @param string $from
		 *
		 * @return mixed
		 */
		private function getNoticeText ($msgType, $from = 'wx')
		{
			$wxData = [
				FansMsg::IMG_MSG         => '图片',
				FansMsg::VOICE_MSG       => '音频',
				FansMsg::VIDEO_MSG       => '视频',
				FansMsg::NEWS_MSG        => '图文',
				FansMsg::MUSIC_MSG       => '音乐',
				FansMsg::SHORT_VIDEO_MSG => '小视频',
				FansMsg::LOCATION_MSG    => '位置',
				FansMsg::LINK_MSG        => '链接',
			];

			$miniData = [
				MiniMsg::IMG_MSG  => '图片',
				MiniMsg::MINI_MSG => '小程序卡片',
			];

			return $from == 'wx' ? $wxData[$msgType] : $miniData[$msgType];
		}

		/**
		 * 消息保存成功后处理
		 *
		 * @param                 $msgType
		 * @param                 $content
		 * @param                 $msgId
		 * @param WxAuthorizeInfo $wxAuthorInfo
		 * @param Fans            $fansInfo
		 * @param                 $fansTimeLineEvent
		 * @param                 $createTime
		 * @param string          $sendType
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 */
		private function saveMsg ($msgType, $content, $msgId, $wxAuthorInfo, $fansInfo, $fansTimeLineEvent, $createTime, $sendType = '')
		{
			$fansMsgId = FansMsg::create($fansInfo->id, $msgType, $content);

			WxMsg::updateAll(['status' => 1, 'update_time' => DateUtil::getCurrentTime()], ['key' => $msgId]);

			// 更新粉丝时间线
			FansTimeLine::create($fansInfo->id, $fansTimeLineEvent, $createTime, 0, 0, $content);

			$userRelations = $wxAuthorInfo->author->userAuthorRelations;

			if (!empty($userRelations)) {
				foreach ($userRelations as $userRelation) {
					$uid = $userRelation->u->uid;

					\Yii::$app->websocket->send([
						'channel' => 'push-message',
						'to'      => $uid,
						'type'    => WebsocketUtil::WX_TYPE,
						'wx_id'   => $wxAuthorInfo->author_id,
						'work_id' => 0,
						'info'    => [
							'type'      => 'chat',
							'from'      => $fansInfo->id,
							'wx_id'     => $wxAuthorInfo->user_name,
							'fans_info' => $fansInfo->dumpData(),
							'msg_list'  => FansMsg::findOne(['id' => $fansMsgId])->dumpData(),
							'msg_type'  => 'wx'
						]
					]);
				}
			}

			$workUserAuthorRelations = WorkUserAuthorRelation::findAll(['author_id' => $wxAuthorInfo->author_id, 'status' => WorkUserAuthorRelation::SEND_OPEN]);

			if (!empty($workUserAuthorRelations)) {
				foreach ($workUserAuthorRelations as $workUserAuthorRelation) {
					$workApi = WorkUtils::getAgentApi($workUserAuthorRelation->corp_id, $workUserAuthorRelation->agent_id);

					switch ($msgType) {
						case FansMsg::TEXT_MSG:
							if ($sendType == static::SUBSCRIBE_SEND_TYPE) {
								$messageContent = [
									'title'       => '新的关注粉丝',
									'description' => "<div class=\"gray\">" . DateUtil::getCurrentYMD() . "</div><div class=\"normal\">粉丝 " . $fansInfo->nickname . " 刚刚关注了公众号 " . $wxAuthorInfo->nick_name . " </div><div class=\"highlight\">请及时处理</div>",
									'url'         => \Yii::$app->params['web_url'] . '/h5/pages/message/msg?id=' . $fansInfo->id . '&chatFromId=' . $wxAuthorInfo->user_name . '&nickname=' . ((!empty($fansInfo->remark) ? $fansInfo->remark : $fansInfo->nickname) . "（公众号：" . $wxAuthorInfo->nick_name . "）") . '&chatType=0&agentId=' . $workUserAuthorRelation->agent->id,
								];
							} else {
								$messageContent = [
									'title'       => '新的公众号粉丝消息',
									'description' => "<div class=\"gray\">" . DateUtil::getCurrentYMD() . "</div><div class=\"normal\">粉丝 " . $fansInfo->nickname . " 向公众号 " . $wxAuthorInfo->nick_name . " 发送了新的消息</div><div class=\"normal\">" . $content . "</div><div class=\"highlight\">请及时处理</div>",
									'url'         => \Yii::$app->params['web_url'] . '/h5/pages/message/msg?id=' . $fansInfo->id . '&chatFromId=' . $wxAuthorInfo->user_name . '&nickname=' . ((!empty($fansInfo->remark) ? $fansInfo->remark : $fansInfo->nickname) . "（公众号：" . $wxAuthorInfo->nick_name . "）") . '&chatType=0&agentId=' . $workUserAuthorRelation->agent->id,
								];
							}

							$messageContent = TextcardMesssageContent::parseFromArray($messageContent);
							$message        = [
								'touser'                   => [$workUserAuthorRelation->user->userid],
								'agentid'                  => $workUserAuthorRelation->agent->agentid,
								'messageContent'           => $messageContent,
								'duplicate_check_interval' => 10,
							];

							$message = Message::pareFromArray($message);
							try {
								$workApi->MessageSend($message, $invalidUserIdList, $invalidPartyIdList, $invalidTagIdList);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'messageSend');
							}

							break;
						case FansMsg::IMG_MSG:
						case FansMsg::VOICE_MSG:
						case FansMsg::VIDEO_MSG:
						case FansMsg::NEWS_MSG:
						case FansMsg::MUSIC_MSG:
						case FansMsg::SHORT_VIDEO_MSG:
						case FansMsg::LOCATION_MSG:
						case FansMsg::LINK_MSG:
							$messageContent = [
								'title'       => '新的公众号粉丝消息',
								'description' => "<div class=\"gray\">" . DateUtil::getCurrentYMD() . "</div><div class=\"normal\">粉丝 " . $fansInfo->nickname . " 向公众号 " . $wxAuthorInfo->nick_name . " 发送了新的 " . $this->getNoticeText($msgType) . " 消息</div><div class=\"highlight\">请及时处理</div>",
								'url'         => \Yii::$app->params['web_url'] . '/h5/pages/message/msg?id=' . $fansInfo->id . '&chatFromId=' . $wxAuthorInfo->user_name . '&nickname=' . ((!empty($fansInfo->remark) ? $fansInfo->remark : $fansInfo->nickname) . "（公众号：" . $wxAuthorInfo->nick_name . "）") . '&chatType=0&agentId=' . $workUserAuthorRelation->agent->id,
							];

							$messageContent = TextcardMesssageContent::parseFromArray($messageContent);
							$message        = [
								'touser'                   => [$workUserAuthorRelation->user->userid],
								'agentid'                  => $workUserAuthorRelation->agent->agentid,
								'messageContent'           => $messageContent,
								'duplicate_check_interval' => 10,
							];

							$message = Message::pareFromArray($message);
							try {
								$workApi->MessageSend($message, $invalidUserIdList, $invalidPartyIdList, $invalidTagIdList);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'messageSend');
							}

							break;
						default:
							break;
					}
				}
			}

		}

		/**
		 * @param                 $msgType
		 * @param                 $content
		 * @param                 $msgId
		 * @param WxAuthorizeInfo $miniAuthorInfo
		 * @param MiniUser        $miniInfo
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\Exception
		 * @throws \yii\base\InvalidConfigException
		 */
		private function saveMiniMsg ($msgType, $content, $msgId, $miniAuthorInfo, $miniInfo)
		{
			$miniMsgId = MiniMsg::create($miniInfo->id, $msgType, $content);

			WxMsg::updateAll(['status' => 1, 'update_time' => DateUtil::getCurrentTime()], ['key' => $msgId]);

			$userRelations = $miniAuthorInfo->author->userAuthorRelations;

			if (!empty($userRelations)) {
				foreach ($userRelations as $userRelation) {
					$uid = $userRelation->u->uid;

					\Yii::$app->websocket->send([
						'channel' => 'push-message',
						'to'      => $uid,
						'type'    => WebsocketUtil::WX_TYPE,
						'wx_id'   => $miniAuthorInfo->author_id,
						'work_id' => 0,
						'info'    => [
							'type'      => 'chat',
							'from'      => $miniInfo->id,
							'wx_id'     => $miniAuthorInfo->user_name,
							'mini_info' => $miniInfo->dumpData(),
							'msg_list'  => MiniMsg::findOne(['id' => $miniMsgId])->dumpData(),
							'msg_type'  => 'mini'
						]
					]);
				}
			}

			$workUserAuthorRelations = WorkUserAuthorRelation::findAll(['author_id' => $miniAuthorInfo->author_id, 'status' => WorkUserAuthorRelation::SEND_OPEN]);

			if (!empty($workUserAuthorRelations)) {
				foreach ($workUserAuthorRelations as $workUserAuthorRelation) {
					$workApi = WorkUtils::getAgentApi($workUserAuthorRelation->corp_id, $workUserAuthorRelation->agent_id);

					switch ($msgType) {
						case MiniMsg::TEXT_MSG:
							$nickName       = !empty($miniInfo->fans) ? $miniInfo->fans->nickname : (!empty($miniInfo->remark) ? $miniInfo->remark : $miniInfo->openid);
							$nickName       .= "（小程序：" . $miniAuthorInfo->nick_name . "）";
							$messageContent = [
								'title'       => '新的小程序用户消息',
								'description' => "<div class=\"gray\">" . DateUtil::getCurrentYMD() . "</div><div class=\"normal\">小程序用户 " . $nickName . " 向小程序 " . $miniAuthorInfo->nick_name . " 发送了新的消息</div><div class=\"normal\">" . $content . "</div><div class=\"highlight\">请及时处理</div>",
								'url'         => \Yii::$app->params['web_url'] . '/h5/pages/message/msg?id=' . $miniInfo->id . '&chatFromId=' . $miniAuthorInfo->user_name . '&nickname=' . $nickName . '&chatType=1&agentId=' . $workUserAuthorRelation->agent->id,
							];

							$messageContent = TextcardMesssageContent::parseFromArray($messageContent);
							$message        = [
								'touser'                   => [$workUserAuthorRelation->user->userid],
								'agentid'                  => $workUserAuthorRelation->agent->agentid,
								'messageContent'           => $messageContent,
								'duplicate_check_interval' => 10,
							];

							$message = Message::pareFromArray($message);
							try {
								$workApi->MessageSend($message, $invalidUserIdList, $invalidPartyIdList, $invalidTagIdList);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'messageSend');
							}

							break;
						case MiniMsg::IMG_MSG:
						case MiniMsg::MINI_MSG:
							$nickName       = !empty($miniInfo->fans) ? $miniInfo->fans->nickname : (!empty($miniInfo->remark) ? $miniInfo->remark : $miniInfo->openid);
							$nickName       .= "（小程序：" . $miniAuthorInfo->nick_name . "）";
							$messageContent = [
								'title'       => '新的小程序用户消息',
								'description' => "<div class=\"gray\">" . DateUtil::getCurrentYMD() . "</div><div class=\"normal\">小程序用户 " . $nickName . " 向小程序 " . $miniAuthorInfo->nick_name . " 发送了新的 " . $this->getNoticeText($msgType, 'mini') . " 消息</div><div class=\"highlight\">请及时处理</div>",
								'url'         => \Yii::$app->params['web_url'] . '/h5/pages/message/msg?id=' . $miniInfo->id . '&chatFromId=' . $miniAuthorInfo->user_name . '&nickname=' . $nickName . '&chatType=1&agentId=' . $workUserAuthorRelation->agent->id,
							];

							$messageContent = TextcardMesssageContent::parseFromArray($messageContent);
							$message        = [
								'touser'                   => [$workUserAuthorRelation->user->userid],
								'agentid'                  => $workUserAuthorRelation->agent->agentid,
								'messageContent'           => $messageContent,
								'duplicate_check_interval' => 10,
							];

							$message = Message::pareFromArray($message);
							try {
								$workApi->MessageSend($message, $invalidUserIdList, $invalidPartyIdList, $invalidTagIdList);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'messageSend');
							}

							break;
						default:
							break;
					}
				}
			}
		}

		/**
		 * 文本消息处理
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\Exception
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function text ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取文本信息
			$contentItem = $this->xml->getElementsByTagName('Content');
			$content     = $contentItem->item(0)->nodeValue;

			$activity = WorkPublicActivity::find()->alias("a")
				->leftJoin("{{%wx_authorize_info}} as b","a.public_id = b.author_id")
				->where(["a.keyword" => $content,"a.is_over"=>1,"b.user_name"=>$toUserName])
				->andWhere("a.start_time < UNIX_TIMESTAMP() and a.end_time > UNIX_TIMESTAMP() and a.type in (1,3) ")
				->select("a.id")
				->asArray()->one();
			if (!empty($activity)) {
				\Yii::$app->queue->push(new TaskTreasureJob([
					'activity'     => $activity,
					'fromUserName' => $fromUserName,
					'type'         => false,
				]));
			}

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::TEXT_MSG;
				$wxMsg->msg_type_value = $content;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						if ($wxAuthorInfo->auth_type == WxAuthorizeInfo::AUTH_TYPE_APP) {
							try {
								$fansInfo = Fans::findOne(['openid' => $fromUserName]);
								if (empty($fansInfo)) {
									$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
								}

								if (!empty($fansInfo)) {
									$this->saveMsg(FansMsg::TEXT_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_TEXT, $createTime);

									//当输入文本为“客服消息预览”时处理
									if ($content == "客服消息预览") {
										$replyContent = KfPushPreview::getRandom($fansInfo->id);
										$replyMsg     = "<xml>
												  <ToUserName><![CDATA[" . $fromUserName . "]]></ToUserName>
												  <FromUserName><![CDATA[" . $toUserName . "]]></FromUserName>
												  <CreateTime>" . $createTime . "</CreateTime>
												  <MsgType><![CDATA[text]]></MsgType>
												  <Content><![CDATA[" . $replyContent . "]]></Content>
												</xml>";
										$encryptCode  = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
										if ($encryptCode == 0) {
											return $this->returnEncryptMsg;
										}
									}
								}
							} catch (InvalidDataException $e) {
								\Yii::error($e->getMessage());
							}

							//添加回复内容
							return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'TEXT');
						} elseif ($wxAuthorInfo->auth_type == WxAuthorizeInfo::AUTH_TYPE_MINI_APP) {
							try {
								$miniInfo = MiniUser::findOne(['openid' => $fromUserName]);
								if (empty($miniInfo)) {
									$miniInfo = $this->miniCreate($wxAuthorInfo, $fromUserName);
								}

								if (!empty($miniInfo)) {
									$this->saveMiniMsg(FansMsg::TEXT_MSG, $content, $msgId, $wxAuthorInfo, $miniInfo);
								}
							} catch (InvalidDataException $e) {
								\Yii::error($e->getMessage());
							}
						}
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 图片消息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\Exception
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function image ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取图片MediaId
			$mediaIdItem = $this->xml->getElementsByTagName('MediaId');
			$mediaId     = $mediaIdItem->item(0)->nodeValue;

			// 获取图片PicUrl
			$picUrlItem = $this->xml->getElementsByTagName('PicUrl');
			$picUrl     = $picUrlItem->item(0)->nodeValue;

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::IMAGE_MSG;
				$wxMsg->msg_type_value = $mediaId;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						if ($wxAuthorInfo->auth_type == WxAuthorizeInfo::AUTH_TYPE_APP) {
							try {
								$fansInfo = Fans::findOne(['openid' => $fromUserName]);
								if (empty($fansInfo)) {
									$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
								}

								if (!empty($fansInfo)) {
									$content = [
										'media_id' => $mediaId,
										'wx_url'   => $picUrl,
									];

									$this->saveMsg(FansMsg::IMG_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_IMAGE, $createTime);
								}
							} catch (InvalidDataException $e) {
								\Yii::error($e->getMessage());
							}

							//添加回复内容
							return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'IMAGE');
						} elseif ($wxAuthorInfo->auth_type == WxAuthorizeInfo::AUTH_TYPE_MINI_APP) {
							try {
								$miniInfo = MiniUser::findOne(['openid' => $fromUserName]);
								if (empty($miniInfo)) {
									$miniInfo = $this->miniCreate($wxAuthorInfo, $fromUserName);
								}

								if (!empty($miniInfo)) {
									$content = [
										'media_id' => $mediaId,
										'wx_url'   => $picUrl,
									];

									$this->saveMiniMsg(FansMsg::IMG_MSG, $content, $msgId, $wxAuthorInfo, $miniInfo);
								}
							} catch (InvalidDataException $e) {
								\Yii::error($e->getMessage());
							}
						}
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 语音消息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function voice ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息 ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取语音的 MediaId
			$mediaIdItem = $this->xml->getElementsByTagName('MediaId');
			$mediaId     = $mediaIdItem->item(0)->nodeValue;

			// 获取语音格式
			$formatItem = $this->xml->getElementsByTagName('Format');
			$format     = $formatItem->item(0)->nodeValue;

			// 获取语音识别结果
			$recognition     = '';
			$recognitionItem = $this->xml->getElementsByTagName('Recognition');
			if (!empty($recognitionItem)) {
				$recognition = $recognitionItem->item(0)->nodeValue;
			}

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::VOICE_MSG;
				$wxMsg->msg_type_value = $mediaId;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						try {
							$fansInfo = Fans::findOne(['openid' => $fromUserName]);
							if (empty($fansInfo)) {
								$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
							}

							if (!empty($fansInfo)) {
								$content = [
									'media_id'    => $mediaId,
									'format'      => $format,
									'recognition' => $recognition,
								];

								$this->saveMsg(FansMsg::VOICE_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_VOICE, $createTime);
							}
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage());
						}

						//添加回复内容
						return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'VOICE');
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 视频消息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function video ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取视频 MediaId
			$mediaIdItem = $this->xml->getElementsByTagName('MediaId');
			$mediaId     = $mediaIdItem->item(0)->nodeValue;

			// 获取视频缩略图 MediaId
			$thumbMediaIdItem = $this->xml->getElementsByTagName('ThumbMediaId');
			$thumbMediaId     = $thumbMediaIdItem->item(0)->nodeValue;

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::VIDEO_MSG;
				$wxMsg->msg_type_value = $mediaId;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						try {
							$fansInfo = Fans::findOne(['openid' => $fromUserName]);
							if (empty($fansInfo)) {
								$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
							}

							if (!empty($fansInfo)) {
								$content = [
									'media_id'       => $mediaId,
									'thumb_media_id' => $thumbMediaId,
								];

								$this->saveMsg(FansMsg::VIDEO_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_VIDEO, $createTime);
							}
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage());
						}

						//添加回复内容
						return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'VIDEO');
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 小视频消息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function shortVideo ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取小视频 MediaId
			$mediaIdItem = $this->xml->getElementsByTagName('MediaId');
			$mediaId     = $mediaIdItem->item(0)->nodeValue;

			// 获取小视频缩略图 MediaId
			$thumbMediaIdItem = $this->xml->getElementsByTagName('ThumbMediaId');
			$thumbMediaId     = $thumbMediaIdItem->item(0)->nodeValue;

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::SHORTVIDEO_MSG;
				$wxMsg->msg_type_value = $mediaId;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						try {
							$fansInfo = Fans::findOne(['openid' => $fromUserName]);
							if (empty($fansInfo)) {
								$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
							}

							if (!empty($fansInfo)) {
								$content = [
									'media_id'       => $mediaId,
									'thumb_media_id' => $thumbMediaId,
								];

								$this->saveMsg(FansMsg::SHORT_VIDEO_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_SHORTVIDEO, $createTime);
							}
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage());
						}

						//添加回复内容
						return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'SHORTVIDEO');
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';

		}

		/**
		 * 地理位置信息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function location ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取地理位置维度
			$locationXItem = $this->xml->getElementsByTagName('Location_X');
			$locationX     = $locationXItem->item(0)->nodeValue;

			// 获取地理位置经度
			$locationYItem = $this->xml->getElementsByTagName('Location_Y');
			$locationY     = $locationYItem->item(0)->nodeValue;

			// 获取地图缩放大小
			$scaleItem = $this->xml->getElementsByTagName('Scale');
			$scale     = $scaleItem->item(0)->nodeValue;

			// 获取地理位置信息
			$labelItem = $this->xml->getElementsByTagName('Label');
			$label     = $labelItem->item(0)->nodeValue;

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::LOCATION_MSG;
				$wxMsg->msg_type_value = $label;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						try {
							$fansInfo = Fans::findOne(['openid' => $fromUserName]);
							if (empty($fansInfo)) {
								$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
							}

							if (!empty($fansInfo)) {
								$content = [
									'location_x' => $locationX,
									'location_y' => $locationY,
									'scale'      => $scale,
									'label'      => $label,
								];

								$this->saveMsg(FansMsg::LOCATION_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_LOCATION, $createTime);
							}
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage());
						}

						//添加回复内容
						return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'LOCATION');
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 链接消息处理逻辑
		 *
		 * @return string
		 *
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		private function link ()
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			// 获取消息ID
			$msgIdItem = $this->xml->getElementsByTagName('MsgId');
			$msgId     = $msgIdItem->item(0)->nodeValue;

			// 获取消息标题
			$titleItem = $this->xml->getElementsByTagName('Title');
			$title     = $titleItem->item(0)->nodeValue;

			// 获取消息描述
			$descriptionItem = $this->xml->getElementsByTagName('Description');
			$description     = $descriptionItem->item(0)->nodeValue;

			// 获取消息链接
			$urlItem = $this->xml->getElementsByTagName('Url');
			$url     = $urlItem->item(0)->nodeValue;

			$wxMsg = WxMsg::findOne(['key' => $msgId]);

			// 判断是否已经接收过
			if (empty($wxMsg)) {
				$wxMsg                 = new WxMsg();
				$wxMsg->key            = $msgId;
				$wxMsg->msg_type       = WxMsg::LINK_MSG;
				$wxMsg->msg_type_value = $title;
				$wxMsg->data           = $this->postData;
				$wxMsg->status         = 0;
				$wxMsg->create_time    = DateUtil::getCurrentTime();
				if ($wxMsg->validate() && $wxMsg->save()) {
					$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $toUserName]);
					if (!empty($wxAuthorInfo)) {
						try {
							$fansInfo = Fans::findOne(['openid' => $fromUserName]);
							if (empty($fansInfo)) {
								$fansInfo = $this->fansCreate($wxAuthorInfo, $fromUserName);
							}

							if (!empty($fansInfo)) {
								$content = [
									'title'       => $title,
									'description' => $description,
									'url'         => $url,
								];

								$this->saveMsg(FansMsg::LINK_MSG, $content, $msgId, $wxAuthorInfo, $fansInfo, FansTimeLine::SEND_LINK, $createTime);
							}
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage());
						}

						//添加回复内容
						return $this->replyData($wxAuthorInfo, $fromUserName, $toUserName, $createTime, 'LINK');
					}
				} else {
					\Yii::error(SUtils::modelError($wxMsg));
				}
			}

			return '';
		}

		/**
		 * 根据消息新增回复内容
		 *
		 * @param $wxAuthorInfo
		 * @param $fromUserName
		 * @param $toUserName
		 * @param $createTime
		 * @param $type
		 *
		 * @return string
		 */
		private function replyData ($wxAuthorInfo, $fromUserName, $toUserName, $createTime, $type)
		{
			$sendData = ['author_id' => $wxAuthorInfo->author_id, 'openid' => $fromUserName, 'user_name' => $toUserName, 'time' => $createTime, 'type' => 2];
			try {
				$result = InteractReply::sendMessage($sendData);
				if (!empty($result['replyMsg'])) {
					$replyMsg                 = $result['replyMsg'];
					$encryptCode              = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
					$result['detail']['time'] = $createTime;
					if ($encryptCode == 0) {
						InteractReplyDetail::create($result['detail'], 1);
						$fans = Fans::findOne(['openid' => $fromUserName, 'author_id' => $wxAuthorInfo->author_id]);
						FansTimeLine::create($fans->id, strtolower($type), time(), 0, 3, '');

						return $this->returnEncryptMsg;
					} else {
						InteractReplyDetail::create($result['detail'], 2);
					}
					//插入统计数据
					InteractReply::insertStatisc($result['inter_id'], $result['author_id']);
				}
			} catch (InvalidDataException $e) {
				\Yii::error($type . '_MSG_' . $e->getMessage());
			}

			return '';
		}

		/**
		 * 全网发布的通用代码
		 *
		 * @param $infoType
		 *
		 * @return string
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 */
		private function allSitesResponse ($infoType)
		{
			[$fromUserName, $toUserName, $createTime] = $this->getFromToTime();

			$createTime = time();

			switch ($infoType) {
				case 'text':
					$contentNameItem = $this->xml->getElementsByTagName('Content');
					$contentName     = $contentNameItem->item(0)->nodeValue;

					if ($contentName == 'TESTCOMPONENT_MSG_TYPE_TEXT') {
						$replyMsg
							= "<xml>
     <ToUserName><![CDATA[" . $fromUserName . "]]></ToUserName>
     <FromUserName><![CDATA[" . $toUserName . "]]></FromUserName>
     <CreateTime>" . $createTime . "</CreateTime>
     <MsgType><![CDATA[text]]></MsgType>
     <Content><![CDATA[TESTCOMPONENT_MSG_TYPE_TEXT_callback]]></Content>
     <FuncFlag>0</FuncFlag>
 </xml>";

						// 对回复的消息进行加密处理
						$encryptCode = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
						\Yii::info($encryptCode);
						if ($encryptCode == 0) {
							\Yii::info($replyMsg);
							\Yii::info($this->returnEncryptMsg);

							return $this->returnEncryptMsg;
						}
					} else if (strstr($contentName, 'QUERY_AUTH_CODE')) {
						$configId  = isset($_GET['type']) ? $_GET['type'] : 0;
						$tokenData = [
							1 => 'normal20170101',
							2 => 'oemone20170101',
							3 => 'oemtwo20170101',
							4 => 'oemthree20170101',
							5 => 'oemfour20170101',
							6 => 'oemfive20170101',
						];

						$apiOauth = new apiOauth($tokenData[$configId], $configId);

						$auth_code          = str_replace('QUERY_AUTH_CODE:', '', $contentName);
						$authorization_info = $apiOauth->getAuthorizationInfo($auth_code, true);
						$access_token       = $authorization_info['authorizer_access_token'];
						$url                = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
						$content            = $auth_code . '_from_api';
						$call
						                    = '{
									"touser":"' . $fromUserName . '",
									"msgtype":"text",
									"text":
									{
										 "content":"' . $content . '"
									}
								}';

						$result = $apiOauth->https_request($url, $call);
						\Yii::info($result);

						return '';
					}
					break;
				case 'event':
					$eventNameItem = $this->xml->getElementsByTagName('Event');
					$eventName     = $eventNameItem->item(0)->nodeValue;
					$content       = $eventName . 'from_callback';
					$replyMsg
					               = "<xml>
     <ToUserName><![CDATA[" . $fromUserName . "]]></ToUserName>
     <FromUserName><![CDATA[" . $toUserName . "]]></FromUserName>
     <CreateTime>" . $createTime . "</CreateTime>
     <MsgType><![CDATA[text]]></MsgType>
     <Content><![CDATA[" . $content . "]]></Content>
     <FuncFlag>0</FuncFlag>
 </xml>";

					// 对回复的消息进行加密处理
					$encryptCode = $this->decodeData($replyMsg, $createTime, $_GET['nonce']);
					\Yii::info($encryptCode);
					if ($encryptCode == 0) {
						\Yii::info($replyMsg);
						\Yii::info($this->returnEncryptMsg);

						return $this->returnEncryptMsg;
					}
					break;
				default:
					break;
			}

			return '';
		}
	}