<?php
	/**
	 * 侧边栏对话
	 * User: xingchangyu
	 * Date: 2020/2/17
	 * Time: 17:17
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Attachment;
	use app\models\AttachmentGroup;
	use app\models\AttachmentStatistic;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\Fans;
	use app\models\Fission;
	use app\models\FissionHelpDetail;
	use app\models\FissionJoin;
	use app\models\Follow;
	use app\models\PublicSeaClaimUser;
	use app\models\PublicSeaContactFollowUser;
	use app\models\PublicSeaCustomer;
	use app\models\PublicSeaReclaimSet;
	use app\models\RedPack;
    use app\models\User;
    use app\models\UserCorpRelation;
	use app\models\WorkChat;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkMaterial;
	use app\models\WorkPublicActivity;
	use app\models\WorkPublicActivityConfigLevel;
	use app\models\WorkPublicActivityFansUser;
	use app\models\WorkPublicActivityFansUserDetail;
	use app\models\WorkPublicActivityPosterConfig;
	use app\models\WorkPublicActivityPrizeUser;
	use app\models\WorkPublicActivityTier;
	use app\models\WorkUser;
	use app\models\WxAuthorize;
	use app\models\WxAuthorizeInfo;
	use app\modules\api\components\BaseController;
	use app\queue\SyncFissionJob;
	use app\util\DateUtil;
	use app\util\MsgUtil;
	use app\util\SUtils;
	use app\util\WorkPublicPoster;
	use app\util\WorkUtils;
	use callmez\wechat\sdk\Wechat;
	use linslin\yii2\curl\Curl;
	use yii\db\Expression;
	use yii\helpers\Url;
	use yii\web\MethodNotAllowedHttpException;

	class ChatMessageController extends BaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           获取config
		 * @description     获取config
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/get-config
		 *
		 * @param corpid 必选string 企业ID
		 * @param agentid 必选string 应用ID
		 * @param url 必选string 地址
		 *
		 * @return          {"error":0,"data":{"corpid":"ww93caebeee67d134b","agentid":"1000013","timestamp":1582079078,"nonceStr":"iO78qTgqKv2qD9q0","signature":"efe1aa71462fc7d8be61bb23be563b501b4ccc8b","jsApiList":["sendChatMessage"],"uid":2,"expireTime":1582086278}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    corpid string 企业ID
		 * @return_param    agentid string 应用ID
		 * @return_param    timestamp string 生成签名的时间戳
		 * @return_param    nonceStr string 生成签名的随机串
		 * @return_param    signature string 签名
		 * @return_param    jsApiList array 接口列表
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-19 9:47
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetConfig ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			\Yii::error(\Yii::$app->request->post(), 'GetConfig');
			$agent_id      = \Yii::$app->request->post('agent_id', '');//地址
			$url           = \Yii::$app->request->post('url', '');//地址
			$agent_id      = intval($agent_id);
			$workCorpAgent = WorkCorpAgent::findOne($agent_id);
			if (empty($workCorpAgent) || $workCorpAgent->is_del == 1) {
				throw new InvalidDataException('应用不存在,请联系管理员添加');
			}
			if ($workCorpAgent->close == 1) {
				throw new InvalidDataException('应用已被停用,请联系管理员');
			}
			$workCorp = WorkCorp::findOne($workCorpAgent->corp_id);
			if (empty($workCorp)) {
				throw new InvalidDataException('企业微信不存在,请联系管理员添加');
			}
			$corpid  = $workCorp->corpid;
			$agentid = $workCorpAgent->agentid;

			$userCorp = UserCorpRelation::findOne(['corp_id' => $workCorp->id]);
			$uid      = $userCorp->uid;

			$urlArr     = explode('#', $url);
			$url        = $urlArr[0];
			$timestamp  = time();
			$expireTime = $timestamp + 7200;
			$nonceStr   = WorkCorp::getRandom(16);

			//缓存取企业ticket
			$cacheKey    = 'corpid_ticket_' . $corpid;
			$ticketCache = \Yii::$app->cache->get($cacheKey);
			if (empty($ticketCache)) {
				try {
					$serviceWork = WorkUtils::getWorkApi($workCorp->id);
					$result      = $serviceWork->GetJsapiTicket();
				} catch (\Exception $e) {
					throw new InvalidDataException($e->getMessage());
				}
				if (!empty($result['errcode'])) {
					throw new InvalidDataException($result['errmsg']);
				}
				$ticket = $result['ticket'];
				\Yii::$app->cache->set($cacheKey, $ticket, 7200);
			} else {
				$ticket = $ticketCache;
			}
			$str        = 'jsapi_ticket=' . $ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
			$signature  = sha1($str);
			$ticketData = [
				'corpid'     => $corpid,
				'timestamp'  => $timestamp,
				'nonceStr'   => $nonceStr,
				'signature'  => $signature,
				'jsApiList'  => ['sendChatMessage', 'agentConfig'],
				'expireTime' => $expireTime
			];

			//缓存取应用ticket
			$cacheAgentKey    = 'agent_ticket_' . $corpid . '_' . $agentid;
			$agentTicketCache = \Yii::$app->cache->get($cacheAgentKey);
			if (empty($agentTicketCache)) {
				try {
					$serviceWork = WorkUtils::getAgentApi($workCorp->id, $workCorpAgent->id);
					$result      = $serviceWork->TicketGet();
				} catch (\Exception $e) {
					throw new InvalidDataException($e->getMessage());
				}
				if (!empty($result['errcode'])) {
					throw new InvalidDataException($result['errmsg']);
				}
				$ticket = $result['ticket'];
				\Yii::$app->cache->set($cacheAgentKey, $ticket, 7200);
			} else {
				$ticket = $agentTicketCache;
			}

			$str       = 'jsapi_ticket=' . $ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
			$signature = sha1($str);
			$agentData = [
				'corpid'     => $corpid,
				'agentid'    => $agentid,
				'timestamp'  => $timestamp,
				'nonceStr'   => $nonceStr,
				'signature'  => $signature,
				'jsApiList'  => [
					'sendChatMessage',
					'getCurExternalContact',
					'getCurExternalChat',
					'getContext',
					'navigateToAddCustomer'
				],
				'expireTime' => $expireTime
			];

			return ['uid' => $uid, 'corpid' => $corpid, 'ticketData' => $ticketData, 'agentData' => $agentData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           附件列表
		 * @description     附件列表
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/list
		 *
		 * @param uid 必选 string 用户id
		 * @param group_id 可选 string 分组id
		 * @param file_type 可选 string 素材类型
		 * @param name 可选 string 素材名字
		 * @param id 可选 string 附件id
		 * @param page 可选 string 分页，默认1
		 * @param pageSize 可选 string 每页数量，默认10
		 * @param attach_id 可选 string 查询此id是否在当前列表中
		 *
		 * @return          {"error":0,"data":{"file_type":4,"count":"85","attachment":[{"id":2975,"group_id":1,"file_type":4,"file_name":"测试群发","file_width":"","file_height":"","local_path":"/upload/images/20200214/15816465935e46030142438.png","jump_url":"http://www.com","content":"放松放松"},{"id":2959,"group_id":58,"file_type":4,"file_name":"8888888888888888","file_width":"","file_height":"","local_path":"/upload/images/20200214/15816465935e46030142438.png","jump_url":"http://www.baidu.com","content":""},]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    file_type string 附件类型
		 * @return_param    is_have string 查询的id是否存在列表中
		 * @return_param    count string 数量
		 * @return_param    attachment array 数据
		 * @return_param    id string 附件id
		 * @return_param    group_id string 分组id
		 * @return_param    file_type string 附件类型
		 * @return_param    file_name string 附件名称
		 * @return_param    file_width string 附件宽度
		 * @return_param    file_height string 附件高度
		 * @return_param    local_path string 附件地址
		 * @return_param    jump_url string 跳转地址
		 * @return_param    content string 附件文本内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-19 10:26
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许1！');
			}

			$uid       = \Yii::$app->request->post('uid', '');//用户id
			$group_id  = \Yii::$app->request->post('group_id', '');//分组id
			$file_type = \Yii::$app->request->post('file_type', '');//素材类型
			$name      = \Yii::$app->request->post('name', '');//素材名字
			$id        = \Yii::$app->request->post('id', '');//附件id
			$attach_id = \Yii::$app->request->post('attach_id', '');//查询id
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确');
			}
			$is_have = 0;
			$keys    = [];
			if (!empty($id)) {
				$file_type  = 6;//默认值
				$count      = 0;
				$attachData = [];
				$select     = new Expression("id,id as `key`,group_id,file_type,file_name,file_content_type,file_width,file_height,local_path,s_local_path,jump_url,text_content as `content`");
				$attachment = Attachment::find()->where(['id' => $id])->select($select);
				if (!empty($group_id)) {
					$idList     = AttachmentGroup::getSubGroupId($group_id);
					$attachment = $attachment->andWhere(['group_id' => $idList]);
				}
				$attachment = $attachment->asArray()->one();
				if (!empty($attachment)) {
					$file_type = $attachment['file_type'];
					if ($file_type == 4 || $file_type == 5) {
						$is_have = [];
						if (!empty($attach_id) && is_array($attach_id)) {
							if (in_array($attachment['id'], $attach_id)) {
								$is_have = [1];
							}
						}
					} else {
						if (!empty($attach_id) && ($attach_id == $attachment['id'])) {
							$is_have = 1;
						}
					}
					if ($file_type == 6) {
						$attachment['content'] = rawurldecode($attachment['content']);
					}
					if ($file_type == 5) {
						$attachment['extension'] = Attachment::getExtension($attachment['file_content_type'], $attachment['file_name']);
					} else {
						$attachment['extension'] = '';
					}

					$attachment['first_group'] = [];
					if (!empty($attachment['group_id'])) {
						$attachment['first_group'] = AttachmentGroup::getFirstGroup($attachment['group_id']);
					}

					$count      = 1;
					$attachData = [$attachment];
					$keys       = [$id];
				}

				return [
					'file_type'  => $file_type,
					'count'      => $count,
					'is_have'    => $is_have,
					'keys'       => $keys,
					'attachment' => $attachData
				];
			}
			$attachment = Attachment::find()->where(['uid' => $uid, 'status' => 1, 'is_temp' => 0]);
			if (!empty($file_type)) {
				if ($file_type == 1) {
					$attachment = $attachment->andWhere(['file_type' => 1, 'file_content_type' => ['image/jpeg', 'image/png']]);
				} elseif ($file_type == 3) {
					$attachment = $attachment->andWhere(['file_type' => $file_type])->andWhere(['<=', 'file_length', 10485760]);
				} elseif ($file_type == 4) {
					$attachment = $attachment->andWhere(['file_type' => $file_type])->andWhere(['!=', 'file_name', '']);
				} elseif ($file_type == 5) {
					$attachment = $attachment->andWhere(['file_type' => $file_type])->andWhere(['<=', 'file_length', 20971520]);
				} else {
					$attachment = $attachment->andWhere(['file_type' => $file_type]);
				}
			} else {
				$attachment = $attachment->andWhere(['or', ['and', ['file_type' => 1, 'file_content_type' => ['image/jpeg', 'image/png']], ['<=', 'file_length', 2097152]], ['and', ['file_type' => 3], ['<=', 'file_length', 10485760]], ['and', ['file_type' => 4], ['!=', 'file_name', '']], ['and', ['file_type' => 5], ['<=', 'file_length', 20971520]], ['file_type' => 6]]);
			}
			if (!empty($group_id)) {
				$idList     = AttachmentGroup::getSubGroupId($group_id);
				$attachment = $attachment->andWhere(['group_id' => $idList]);
			}
			if (!empty($name)) {
				//$attachment = $attachment->andWhere(['or', ['like', 'file_name', $name], ['and',['file_type' => 6],['like', 'text_content', $name]]]);
				$attachment = $attachment->andWhere(['like', 'file_name', $name]);
			}
			$select     = new Expression("id,id as `key`,group_id,file_type,file_name,file_content_type,file_width,file_height,local_path,s_local_path,jump_url,text_content as `content`");
			$attachment = $attachment->select($select)->orderBy('id desc');

			if (empty($file_type)) {
				$file_type = 6;//默认值
				$info      = $attachment->one();
				if (!empty($info)) {
					$file_type = $info->file_type;
					if ($file_type == 1) {
						$attachment = $attachment->andWhere(['file_type' => 1, 'file_content_type' => ['image/jpeg', 'image/png']]);
					} elseif ($file_type == 4) {
						$attachment = $attachment->andWhere(['file_type' => $file_type])->andWhere(['!=', 'file_name', '']);
					} else {
						$attachment = $attachment->andWhere(['file_type' => $file_type]);
					}
				}
			}
			//分页
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			$offset   = ($page - 1) * $pageSize;
			$count    = $attachment->count();

			if ($file_type == 1 || $file_type == 3 || $file_type == 4 || $file_type == 5) {
				//文件柜获取所有的key
				$idList = $attachment->all();
				if (!empty($idList)) {
					foreach ($idList as $idInfo) {
						array_push($keys, (string) $idInfo['id']);
					}
				}
				$is_have = [];
				if (is_array($attach_id)) {
					foreach ($attach_id as $att_id) {
						$is_exist = in_array($att_id, $keys) ? 1 : 0;
						array_push($is_have, $is_exist);
					}
				}
			} else {
				//查询是否存在
				if (!empty($attach_id)) {
					$attachInfo = clone $attachment;
					$attachInfo = $attachInfo->andWhere(['id' => $attach_id])->one();
					if (!empty($attachInfo)) {
						$is_have = 1;
					}
				}
			}

			$attachData = $attachment->limit($pageSize)->offset($offset)->asArray()->all();
			foreach ($attachData as $key => $attach) {
				if ($attach['file_type'] == 6) {
					$attachData[$key]['content'] = rawurldecode($attach['content']);
				}
				if ($attach['file_type'] == 5) {
					$attachData[$key]['extension'] = Attachment::getExtension($attach['file_content_type'], $attach['file_name']);
				} else {
					$attachData[$key]['extension'] = '';
				}

				$attachData[$key]['first_group'] = [];
				if (!empty($attach['group_id'])) {
					$attachData[$key]['first_group'] = AttachmentGroup::getFirstGroup($attach['group_id']);
				}
			}

			return [
				'file_type'  => $file_type,
				'count'      => $count,
				'is_have'    => $is_have,
				'keys'       => $keys,
				'attachment' => $attachData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           模糊搜索下拉
		 * @description     模糊搜索下拉
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/search
		 *
		 * @param uid 必选 string 用户id
		 * @param name 必选 string 名称
		 *
		 * @return          {"error":0,"data":[{"id":52,"title":"企业微信截图_20191025163359.png","file_type":1,"type_name":"图片"},{"id":51,"title":"QQ截图20191025163821.png","file_type":1,"type_name":"图片"},{"id":49,"title":"企业微信截图_20191025163359.png","file_type":1,"type_name":"图片"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 附件id
		 * @return_param    title array 附件名称
		 * @return_param    file_type array 附件类型
		 * @return_param    type_name array 附件类型名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-19 11:15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSearch ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', '');//用户id
			$name    = \Yii::$app->request->post('name', '');//素材名字
			$userId  = \Yii::$app->request->post('user_id', '');//成员ID
			$agentId = \Yii::$app->request->post('agent_id', '');//应用ID
			$groupId = \Yii::$app->request->post('group_id', '');//分组id

			if (empty($uid)) {
				throw new InvalidDataException('参数不正确');
			}
			$attachment = Attachment::find()->where(['uid' => $uid, 'status' => 1, 'is_temp' => 0, 'file_type' => [1, 3, 4, 5, 6, 7]]);

			if (!empty($groupId)) {
				$idList     = AttachmentGroup::getSubGroupId($groupId);
				$attachment = $attachment->andWhere(['group_id' => $idList]);
			}

			if (!empty($name)) {
				//$attachment = $attachment->andWhere(['or', ['like', 'file_name', $name], ['and',['file_type' => 6],['like', 'text_content', $name]]]);
				$attachment = $attachment->andWhere(['like', 'file_name', $name]);
			}
			$offset     = 0;
			$pageSize   = 20;
			$attachment = $attachment->select('id,group_id,uid,file_type,file_name,text_content');
			$attachment = $attachment->orderBy('id desc')->limit($pageSize)->offset($offset)->all();
			$typeArr    = [
				'1' => '图片',
				'3' => '视频',
				'4' => '图文',
				'5' => '文件',
				'6' => '文本',
				'7' => '小程序',
			];
			$data       = [];
			if (!empty($attachment)) {
				$workUser = [];
				if (!empty($agentId) && !empty($userId)) {
					$corpAgent = WorkCorpAgent::findOne($agentId);
					if (!empty($corpAgent) && $corpAgent->corp->userCorpRelations[0]->uid == $uid) {
						$workUser = WorkUser::findOne(['corp_id' => $corpAgent->corp_id, 'userid' => $userId]);
					}
				}

				/**
				 * @var int        $key
				 * @var Attachment $attach
				 */
				foreach ($attachment as $key => $attach) {
					$title      = $attach->file_name;
					$data[$key] = ['id' => $attach->id, 'title' => $title, 'file_type' => $attach->file_type, 'type_name' => $typeArr[$attach->file_type], 'first_group' => []];

					if (!empty($attach->group_id)) {
						$data[$key]['first_group'] = AttachmentGroup::getFirstGroup($attach->group_id);
					}

					if (!empty($workUser)) {
						try {
							AttachmentStatistic::create($attach->id, $workUser->id, ['search' => $name, 'corp_id' => $workUser->corp_id]);
						} catch (\Exception $e) {
							\Yii::error($e->getMessage(), 'attachmentStatistic-create');
						}
					}
				}
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           分组列表
		 * @description     分组列表
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/group
		 *
		 * @param uid 必选 string 用户id
		 * @param ids 可选 string 附件id
		 *
		 * @return          {"error":0,"data":{"group":[{"key":"1","value":"1","parent_id":null,"title":"未分组","sort":32,"is_not_group":1,"scopedSlots":{"title":"custom"},"num":"118","children":[]},{"key":"36","value":"36","parent_id":null,"title":"355555555555555","sort":30,"is_not_group":0,"scopedSlots":{"title":"custom"},"num":"20","children":[{"key":"57","value":"57","parent_id":36,"title":"1111","sort":2,"is_not_group":0,"scopedSlots":{"title":"custom"},"num":"1","children":[]}]}]}]}]}]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    group array 结果列表
		 * @return_param    key string 分组id
		 * @return_param    value string 键值
		 * @return_param    parent_id string 父级id
		 * @return_param    title string 分组名称
		 * @return_param    is_not_group string 是否是未分组
		 * @return_param    num string 分租下附件数量
		 * @return_param    children string 分租下子分组
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-19 11:21
		 * @number          0
		 *
		 */
		public function actionGroup ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许1！');
			}
			$uid = \Yii::$app->request->post('uid', '');//用户id
			$ids = \Yii::$app->request->post('ids', '');//附件id
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确');
			}
			$notGroup = AttachmentGroup::findOne(['uid' => $uid, 'is_not_group' => 1]);
			if (empty($notGroup)) {
				$group               = new AttachmentGroup();
				$group->uid          = $uid;
				$group->title        = '未分组';
				$group->sort         = 1;
				$group->is_not_group = 1;
				$group->create_time  = DateUtil::getCurrentTime();
				if ($group->validate() && $group->save()) {
					Attachment::updateAll(['group_id' => $group->id], ['uid' => $uid, 'status' => 1, 'group_id' => NULL]);
				}
			}
			$idArr = [];
			if (!empty($ids)) {
				$idArr = explode(',', $ids);
			}
			$groupData = AttachmentGroup::getGroupData($uid, true, true, ['idArr' => $idArr]);

			return [
				'group' => $groupData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           发送数据转换
		 * @description     发送数据转换
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/send-data
		 *
		 * @param uid 必选 string 用户id
		 * @param corpid 必选 string 企业corpid
		 * @param ids 必选 string 附件id
		 * @param agent_id 必选 string 应用id
		 * @param user_id 可选 string 成员ID
		 * @param external_id 可选 string 外部联系人ID
		 *
		 * @return          {"error":0,"data":[{"msgtype":"image","image":{"mediaid":"3xt67k4ePHrGAqdQXS50IxurTOLIwvexaLCDrapa5siA"}}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    msgtype string 消息类型
		 * @return_param    content string 素材内容，当msgtype=text时
		 * @return_param    mediaid string 素材media_id，当msgtype!=text时
		 *
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-20 9:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \app\components\ForbiddenException
		 * @throws \app\components\InvalidParameterException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionSendData ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			\Yii::error(\Yii::$app->request->post(), 'SendData');
			$uid        = \Yii::$app->request->post('uid', '');//用户id
			$corpid     = \Yii::$app->request->post('corpid', 0);//企业ID
			$agent_id   = \Yii::$app->request->post('agent_id', 0);//应用ID
			$ids        = \Yii::$app->request->post('ids', '');//附件ID，数组
			$userId     = \Yii::$app->request->post('user_id', '');//成员ID
			$externalId = \Yii::$app->request->post('external_id', '');//外部联系人ID
			$chatId     = \Yii::$app->request->post('chat_id', '');//群ID

			if (empty($uid) || empty($corpid) || empty($ids)) {
				throw new InvalidDataException('参数不正确');
			}
			if (!is_array($ids)) {
				$ids = [$ids];
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corpid]);
			if (empty($workCorp)) {
				throw new InvalidDataException('参数不正确');
			}
			//地址
			$site_url = \Yii::$app->params['site_url'];
			$web_url  = \Yii::$app->params['web_url'];
			$typeArr  = [
				'1' => 'image',
				'3' => 'video',
				'4' => 'news',
				'5' => 'file',
				'6' => 'text',
				'7' => 'miniprogram',
			];
			$data     = [];
			$workUser = [];
			if (!empty($userId)) {
				$workUser = WorkUser::findOne(['corp_id' => $workCorp->id, 'userid' => $userId]);
			}
			//多选发送
			$count = count($ids);
			if ($count > 1) {
				$other = [
					'uid'          => $uid,
					'corp_id'      => $workCorp->id,
					'agent_id'     => $agent_id,
					'externalId'   => $externalId,
					'chat_id'      => $chatId,
					'work_user_id' => !empty($workUser) ? $workUser->id : '',
				];
				$data  = Attachment::chatSendData($ids, $other);
				\Yii::error($data, '$data1');

				return $data;
			}
			//单选发送
			foreach ($ids as $key => $id) {
				if (empty($id)) {
					continue;
				}
				if (!empty($chatId)) {
					$workChat = WorkChat::findOne(['corp_id' => $workCorp->id, 'chat_id' => $chatId]);
					if (empty($workChat)) {
						$workChatId = WorkChat::getChatInfo($workCorp->id, $chatId);
					} else {
						$workChatId = $workChat->id;
					}
				}
				$has        = false;
				$attachment = Attachment::findOne($id);
				if ($attachment->file_type == 4) {
					$local_path = !empty($attachment->s_local_path) ? $attachment->s_local_path : $attachment->local_path;
					$jump_url   = $attachment->jump_url;
					if (!empty($agent_id)) {
						if (strpos($jump_url, $web_url) !== false) {
							if (strpos($jump_url, '?') !== false) {
								$jump_url = $jump_url . '&agent_id=' . $agent_id;
							} else {
								$jump_url = $jump_url . '?agent_id=' . $agent_id;
							}
						}
					}
					if (!empty($workChatId)) {
						$jump_url = $jump_url . '&chat_id=' . $workChatId;
					}
					$data[$key] = [
						'msgtype' => 'news',
						'news'    => [
							'link'   => $jump_url,
							'title'  => $attachment->file_name,
							'desc'   => !empty($attachment->content) ? $attachment->content : $attachment->jump_url,
							'imgUrl' => $site_url . $local_path,
						]
					];
					$has        = true;
				} elseif ($attachment->file_type == 6) {
					$data[$key] = ['msgtype' => 'text', 'text' => ['content' => rawurldecode($attachment->text_content)]];
					$has        = true;
				} elseif ($attachment->file_type == 1) {
					$link       = '/h5/pages/material/list?uid=' . $attachment->uid . '&agent_id=' . $agent_id . '&ids=' . $attachment->id . '&file_type=1';
					$data[$key] = [
						'msgtype' => 'news',
						'news'    => [
							'link'   => $web_url . $link,
							'title'  => $attachment->file_name,
							'desc'   => '图片',
							'imgUrl' => $site_url . '/static/image/image.png'
						]
					];
					$has        = true;
				} elseif ($attachment->file_type == 3) {
					$link       = '/h5/pages/material/list?uid=' . $attachment->uid . '&agent_id=' . $agent_id . '&ids=' . $attachment->id . '&file_type=3';
					$data[$key] = [
						'msgtype' => 'news',
						'news'    => [
							'link'   => $web_url . $link,
							'title'  => $attachment->file_name,
							'desc'   => '视频',
							'imgUrl' => $site_url . '/static/image/video.png'
						]
					];
					$has        = true;
				} elseif ($attachment->file_type == 7) {
					$page = $attachment->appPath;
					if (strpos($page, '.html') === false) {
						$page .= '.html';
					}
					$local_path = !empty($attachment->s_local_path) ? $attachment->s_local_path : $attachment->local_path;
					$data[$key] = [
						'msgtype'     => 'miniprogram',
						'miniprogram' => [
							'appid'  => $attachment->appId,
							'title'  => $attachment->file_name,
							'imgUrl' => $site_url . $local_path,
							'page'   => $page,
						]
					];
					$has        = true;
				} else {
					$workMaterialInfo = WorkMaterial::findOne(['corp_id' => $workCorp->id, 'attachment_id' => $id, 'status' => 1]);
					$type_name        = $typeArr[$attachment->file_type];
					if (!empty($workMaterialInfo)) {
						MsgUtil::checkWorkNeedReload($workMaterialInfo);
						$media_id = $workMaterialInfo->media_id;
					} else {
						try {
							$result   = WorkMaterial::uploadMedia($id, $workCorp->id);
							$media_id = $result['media_id'];
						} catch (InvalidDataException $e) {
							\Yii::error($e->getMessage(), 'uploadMedia-' . $id);
							continue;
						}
					}

					$data[$key] = ['msgtype' => $type_name, $type_name => ['mediaid' => $media_id]];
					$has        = true;
				}

				if ($has && !empty($workUser)) {
					try {
						$statisticData = [];

						if (!empty($externalId)) {
							$externalInfo = WorkExternalContact::findOne(['corp_id' => $workCorp->id, 'external_userid' => $externalId]);
							if (!empty($externalInfo)) {
								$statisticData = [
									'external_id' => $externalInfo->id,
								];
							}
						}

						if (!empty($workChatId)) {
							$statisticData = [
								'chat_id' => $workChatId,
							];
						}
						$statisticData['corp_id'] = $workUser->corp_id;
						AttachmentStatistic::create($attachment->id, $workUser->id, $statisticData, AttachmentStatistic::ATTACHMENT_SEND);
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), 'attachmentStatistic-create');
					}
				}
			}
			\Yii::error($agent_id, '$agent_id');
			\Yii::error($data, '$data1');

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           裂变引流
		 * @description     裂变引流
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/help
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param agent_id 必选 string 企业应用id
		 * @param code 必选 string 获取用户信息的code
		 * @param assist 必选 string 附带上级参数
		 * @param external_id 可选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"jid":32,"title":"测试4","is_forbid":0,"is_use":0,"status":2,"join_status":0,"is_own":1,"is_remind":0,"is_help":0,"help_name":"","complete_num":1,"fission_num":1,"help_num":0,"rest_num":1,"ranking":2,"picRule":{"back_pic_url":"/upload/images/2/20200325/15851345225e7b3bbaa309a.png","is_avatar":1,"avatar":{"w":40,"x":0,"y":7},"shape":"circle","is_nickname":1,"nickName":{"w":120,"h":36,"x":0,"y":48},"qrCode":{"w":60,"x":0,"y":264},"color":"#F5A623","font_size":14,"align":"left"},"prizeName":"玩偶","nick_name":"王盼","head_url":"http://wx.qlogo.cn/mmhead/GibvHudxmlJbHQEV84mpeundfic12MygBdy3xd7icp02P6yh3via5yJXSA/0","qr_code":"https://wework.qpic.cn/wwpic/915951_GYVyM-lIRRaJf1z_1585293198/0","shareData":{"title":"哈哈哈哈哈哈哈哈哈哈或或或或或或或或呵呵呵","desc":"45543543543543tertret64563454353454","pic_url":"https://tapi.fastwhale.com.cn/upload/images/2/20200325/15851347825e7b3cbe7a3af.png","shareUrl":"http://ttest-m-scrm.wemero.cn/h5/pages/fission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=fission_21_1993"},"timeData":{"day":"0","hour":"04","minutes":"36","seconds":"09"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    jid string 参与者id
		 * @return_param    title string 活动名称
		 * @return_param    is_forbid string 是否禁止分享
		 * @return_param    is_use string code是否使用过
		 * @return_param    status string 活动状态：0删除、1未发布、2已发布、3到期结束、4奖品无库存结束、5、手动提前结束
		 * @return_param    join_status string 参与者状态：0去邀请 1 已获奖品 2 活动结束，已无奖品
		 * @return_param    is_own string 是否是自己
		 * @return_param    is_remind string 是否需要提醒
		 * @return_param    is_help string 是否助力成功
		 * @return_param    help_name string 助力者名称
		 * @return_param    help_head_url string 助力者头像
		 * @return_param    complete_num string 活动的已完成数量
		 * @return_param    fission_num string 活动的裂变数量
		 * @return_param    help_num string 助力人数
		 * @return_param    rest_num string 还差人数
		 * @return_param    ranking string 助力人数排行名次
		 * @return_param    picRule array 海报位置数据
		 * @return_param    prizeName string 奖品名称
		 * @return_param    nick_name string 昵称
		 * @return_param    head_url string 头像
		 * @return_param    qr_code string 渠道活码地址
		 * @return_param    h5Url string h5页面地址
		 * @return_param    shareData array 分享数据
		 * @return_param    timeData array 倒计时数据
		 * @return_param    my_url string 进入我的链接
		 * @return_param    help_type string 0、隐藏，1、帮他助力，2、帮他分享
		 * @return_param    join_type string 0、隐藏，1、我要参与，2、进入我的，3、生成海报
		 * @return_param    area_type string 区域类型：1、不限制，2、部分地区
		 * @return_param    external_id  string 外部联系人id
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-30 8:59
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionHelp ()
		{
			$corp_id     = \Yii::$app->request->post('corp_id', '');
			$agent_id    = \Yii::$app->request->post('agent_id', '');
			$code        = \Yii::$app->request->post('code', '');
			$assist      = \Yii::$app->request->post('assist', '');
			$web_url     = \Yii::$app->params['web_url'];
			$stateArr    = explode('_', $assist);
			$fission_id  = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
			$parent_id   = isset($stateArr[2]) ? intval($stateArr[2]) : 0;
			$fissionInfo = Fission::findOne($fission_id);
			if (empty($fissionInfo)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			$corpAgent   = WorkCorpAgent::findOne($fissionInfo->agent_id);
			$external_id = \Yii::$app->request->post('external_id', '');
			$openId      = '';
			try {
				if (empty($external_id)) {

					WorkUtils::getUserData($code, $corp_id, $result, [], true);

					if (!empty($result->UserId)) {
						throw new InvalidDataException('您已是企业成员或已绑定过个人微信，均无法参与活动！');
					} elseif ($result->OpenId) {
						$externalContact = WorkExternalContact::findOne(['corp_id' => $corp_id, 'openid' => $result->OpenId]);
						if (!empty($externalContact)) {
							$external_id = $externalContact->id;
						}
						$openId = $result->OpenId;
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
			$date      = date('Y-m-d H:i:s');
			$is_forbid = 0;
			if ($fissionInfo->status == 1 || $fissionInfo->start_time >= $date) {
				throw new InvalidDataException('此活动还未发布');
			} elseif ($fissionInfo->status == 0) {
				throw new InvalidDataException('此活动已删除');
			} elseif ($fissionInfo->status == 2 && $fissionInfo->end_time <= $date) {
				$is_forbid = 1;
				\Yii::$app->queue->push(new SyncFissionJob([
					'fission_id'     => $fissionInfo->id,
					'fission_status' => 3
				]));
				$fissionInfo->status = 3;
				$fissionInfo->update();
			}
			$picRule     = json_decode($fissionInfo->pic_rule, 1);
			$prizeRule   = json_decode($fissionInfo->prize_rule, 1);
			$workCorp    = WorkCorp::findOne($fissionInfo->corp_id);
			$fission_num = $prizeRule[0]['fission_num'];

			try {
				$help_type = 0;//0、隐藏，1、帮他助力，2、帮他分享
				$join_type = 0;//0、隐藏，1、我要参与，2、进入我的，3、生成海报
				$help_name = $help_head_url = $nick_name = $head_url = $my_url = $help_tip = '';
				$is_own    = $help_num = $rest_num = $ranking = $join_status = $jid = $is_help = $is_remind = 0;
				$shareUrl  = $web_url . Fission::H5_URL . '?corp_id=' . $fissionInfo->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $fissionInfo->agent_id;
				if (!empty($corpAgent) && $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
					$shareUrl .= '&suite_id=' . $corpAgent->suite->suite_id;
				}

				//任务是否结束
				$is_end = 0;
				if (in_array($fissionInfo->status, [0, 3, 4, 5])) {
					$is_end = 1;
				}
				//助力倒计时
				$timeData = [
					'day'     => '00',
					'hour'    => '00',
					'minutes' => '00',
					'seconds' => '00',
				];
				if ($is_end == 0) {
					$time     = time();
					$end_time = strtotime($fissionInfo->end_time);
					if ($end_time > $time) {
						$timestamp           = $end_time - $time;
						$timeData['day']     = (string) floor($timestamp / (3600 * 24));
						$timeData['hour']    = floor(($timestamp % (3600 * 24)) / 3600);
						$timeData['minutes'] = floor(($timestamp % 3600) / 60);
						$timeData['seconds'] = floor($timestamp % 60);
						$timeData['hour']    = ($timeData['hour'] >= 10) ? (string) $timeData['hour'] : '0' . $timeData['hour'];
						$timeData['minutes'] = ($timeData['minutes'] >= 10) ? (string) $timeData['minutes'] : '0' . $timeData['minutes'];
						$timeData['seconds'] = ($timeData['seconds'] >= 10) ? (string) $timeData['seconds'] : '0' . $timeData['seconds'];
					}
				}

				if (!empty($external_id)) {//判断当前客户是否加过任务中的成员
					$userIdList = WorkExternalContactFollowUser::find()->where(['external_userid' => $external_id, 'del_type' => 0])->select('userid')->all();
					if (!empty($userIdList)) {
						$userId    = array_column($userIdList, 'userid');//此客户添加的成员
						$userArr   = json_decode($fissionInfo->user, 1);//任务中成员
						$intersect = array_intersect($userId, $userArr);
						if (!empty($intersect)) {
							$is_add = 1;//第一次进来，是否可以添加为参与者或者助力者
							if ($fissionInfo->area_type == 2) {
								$is_add = 0;
							} elseif ($fissionInfo->sex_type != 1) {
								$is_limit = RedPack::checkSex($external_id, $fissionInfo->sex_type);
								$is_add   = !empty($is_limit) ? 0 : 1;
							}
							if (!empty($is_add)) {
								FissionJoin::setJoin($fissionInfo, [Fission::FISSION_HEAD, $fissionInfo->id, $external_id], $parent_id);
							}
						}
					}
				}

				//有上级
				if (!empty($parent_id)) {
					$fissionJoin = FissionJoin::findOne(['uid' => $fissionInfo->uid, 'fid' => $fissionInfo->id, 'external_id' => $parent_id]);
					if (!empty($fissionJoin)) {
						$parentExternalContact = WorkExternalContact::findOne($parent_id);
						if (!empty($parentExternalContact)) {
							$nick_name = urldecode($parentExternalContact->name);
							$head_url  = $parentExternalContact->avatar;
						}
						if (!empty($externalContact) && ($parent_id == $externalContact->id)) {
							$is_own = 1;
						}
					} else {
						$parent_id = 0;
					}
				}

				//无上级
				if (empty($parent_id) && !empty($externalContact)) {
					$fissionJoin = FissionJoin::findOne(['uid' => $fissionInfo->uid, 'fid' => $fissionInfo->id, 'external_id' => $externalContact->id]);
					if (!empty($fissionJoin)) {
						$is_own = 1;
					}
					$nick_name = urldecode($externalContact->name);
					$head_url  = $externalContact->avatar;
				}

				$qr_code = $fissionInfo->qr_code;
				if (!empty($fissionJoin)) {
					$jid     = $fissionJoin->id;
					$qr_code = $fissionJoin->qr_code;
					$assist  = Fission::FISSION_HEAD . '_' . $fissionInfo->id . '_' . $fissionJoin->external_id;
					//排名
					$help_num = !empty($fissionJoin->help_num) ? $fissionJoin->help_num : 0;
					if ($fissionJoin->help_num < $fissionJoin->fission_num) {
						$rest_num = $fissionJoin->fission_num - $fissionJoin->help_num;
					} else {
						$join_status = 1;
						if (!empty($is_own) && !empty($fissionJoin->is_remind)) {
							$is_remind              = 1;
							$fissionJoin->is_remind = 0;
							if (!$fissionJoin->validate() || !$fissionJoin->save()) {
								throw new InvalidDataException(SUtils::modelError($fissionJoin));
							}
						}
					}
					//排名
					$joinList = FissionJoin::find()->where(['fid' => $fissionInfo->id])->andWhere(['>=', 'help_num', $fissionJoin->help_num])->orderBy('help_num desc')->all();
					$ranking  = 1;
					if (!empty($joinList)) {
						foreach ($joinList as $join) {
							if ($join->id == $jid) {
								break;
							} else {
								$ranking += 1;
							}
						}
					}
					if (!empty($is_own)) {
						$join_type = 3;
						if ($fissionJoin->status == 2 || $fissionInfo->status != 2) {
							$join_type = 0;
						}
					} else {
						$help_type = 1;
						$join_type = 1;
						if (!empty($external_id)) {
							//活动结束或裂变完成不显示帮他助力、帮他分享
							if ($fissionJoin->status == 2 || $fissionInfo->status != 2) {
								$help_type = 0;
							} else {
								//查询是否助力过
								$tempHelp = FissionHelpDetail::findOne(['fid' => $fissionInfo->id, 'jid' => $jid, 'external_id' => $external_id]);
								if (!empty($tempHelp)) {
									$help_type = 2;
									//是否提醒助力
									if ($tempHelp->is_remind == 1 && $fissionInfo->status == 2) {
										$is_help       = 1;
										$help_name     = urldecode($externalContact->name);
										$help_head_url = $externalContact->avatar;
										//更新
										$tempHelp->is_remind = 0;
										$tempHelp->update();
									}
								} else {
									//助力次数限制
									if (!empty($fissionInfo->help_limit) && !empty($timestamp)) {
										$helpCount = FissionHelpDetail::find()->where(['fid' => $fissionInfo->id, 'external_id' => $external_id])->count();
										if ($helpCount >= $fissionInfo->help_limit) {
											$help_type     = 2;
											$cacheLimitKey = 'help_limit_' . $fissionInfo->id . '_' . $jid . '_' . $external_id;
											$cacheLimit    = \Yii::$app->cache->get($cacheLimitKey);
											if (empty($cacheLimit)) {
												$help_tip = '助力次数已达限制，不能再助力';
												\Yii::$app->cache->set($cacheLimitKey, 1, $timestamp);
											}
										}
									}
								}
							}

							//是否参与过
							$tempJoin = FissionJoin::findOne(['fid' => $fissionInfo->id, 'external_id' => $external_id]);
							if (!empty($tempJoin)) {
								$join_type = 2;
								$my_url    = $shareUrl . '&assist=' . Fission::FISSION_HEAD . '_' . $fissionInfo->id . '_' . $external_id;
							}
						}
					}
				} else {
					$help_type = 0;
					$join_type = 1;
				}

				//任务结束处理
				if ($is_end == 1) {
					if ($is_help != 1) {
						$is_help = 0;
					}
					if ($join_type != 2) {
						$join_type = 0;
					}
					$help_type = 0;
					$help_tip  = '';
					if ($join_status != 1) {
						$join_status = 2;
					}
				}

				$status = $fissionInfo->status;

				//分享链接
				$site_url              = \Yii::$app->params['site_url'];
				$welcomeArr            = json_decode($fissionInfo->welcome, 1);
				$shareData             = [];
				$shareData['title']    = $welcomeArr['link_start_title'];
				$shareData['desc']     = $welcomeArr['link_desc'];
				$shareData['pic_url']  = $site_url . $welcomeArr['link_pic_url'];
				$shareData['shareUrl'] = $shareUrl . '&assist=' . $assist;

				if (!empty($head_url)) {
					//获取远程文件所采用的方法
					$curl       = new Curl();
					$response   = $curl->setOptions([
						CURLOPT_CONNECTTIMEOUT => 300,
						CURLOPT_FOLLOWLOCATION => true
					])->get($head_url);
					$base64Data = 'data:image/png;base64,' . base64_encode($response);
				} else {//默认头像
					$base64Data = $head_url = $site_url . '/static/image/default-avatar.png';
				}

				$returnData = [
					'fid'           => $fissionInfo->id,
					'jid'           => $jid,
					'title'         => $fissionInfo->title,
					'is_forbid'     => $is_forbid,
					'is_use'        => 0,
					'is_parent'     => !empty($parent_id) ? 1 : 0,
					'status'        => $status,
					'openid'        => $openId,
					'join_status'   => $join_status,
					'is_own'        => $is_own,
					'is_remind'     => $is_remind,
					'is_help'       => $is_help,
					'help_name'     => $help_name,
					'help_head_url' => $help_head_url,
					'help_tip'      => $help_tip,
					'complete_num'  => $fissionInfo->complete_num,
					'fission_num'   => $fission_num,
					'help_num'      => $help_num,
					'rest_num'      => $rest_num,
					'ranking'       => $ranking,
					'picRule'       => $picRule,
					'prizeName'     => $prizeRule[0]['prize_name'],
					'nick_name'     => $nick_name,
					'head_url'      => $head_url,
					'qr_code'       => $qr_code,
					'h5Url'         => $shareUrl . '&assist=' . $assist,
					'my_url'        => $my_url,
					'external_id'   => $external_id,
					'help_type'     => $help_type,
					'join_type'     => $join_type,
					'shareData'     => $shareData,
					'timeData'      => $timeData,
					'base64Data'    => $base64Data,
					'area_type'     => $fissionInfo->area_type,//区域类型：1、不限制，2、部分地区
				];
				\Yii::error($returnData, 'returnData');

				return $returnData;
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           我要参与、帮他助力
		 * @description     我要参与、帮他助力
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/join-help
		 *
		 * @param fid 必选 string 活动id
		 * @param jid 可选 string 参与者id
		 * @param external_id 可选 string 外部联系人id
		 * @param lat 可选 string 纬度
		 * @param lng 可选 string 经度
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    err_msg string 错误信息
		 * @return_param    open_type string 0、二维码，1、参与，2、助力
		 * @return_param    qr_code string 二维码链接
		 * @return_param    is_help string 是否助力成功
		 * @return_param    help_name string 助力者名称
		 * @return_param    help_head_url string 助力者头像
		 * @return_param    nick_name string 参与者名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-30 9:09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionJoinHelp ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许');
			}
			$fid         = \Yii::$app->request->post('fid', 0);//红包裂变任务id
			$jid         = \Yii::$app->request->post('jid', 0);//参与者id
			$external_id = \Yii::$app->request->post('external_id', 0);
			$lat         = \Yii::$app->request->post('lat', 0);
			$lng         = \Yii::$app->request->post('lng', 0);
			$fissionInfo = Fission::findOne($fid);
			if (empty($fissionInfo)) {
				throw new InvalidDataException('参数不正确');
			}
			try {
				$date = date('Y-m-d H:i:s');
				if ($fissionInfo->status == 1) {
					throw new InvalidDataException('活动未发布');
				} elseif (in_array($fissionInfo->status, [0, 3, 4, 5])) {
					throw new InvalidDataException('活动已结束');
				} elseif ($fissionInfo->status == 2 && ($fissionInfo->end_time <= $date)) {
					\Yii::$app->queue->push(new SyncFissionJob([
						'fission_id'     => $fissionInfo->id,
						'fission_status' => 3
					]));
					throw new InvalidDataException('活动已结束');
				}
				if (!empty($jid)) {//有上级时，$jid不能为空
					$joinData = FissionJoin::findOne($jid);
					if (empty($joinData)) {
						throw new InvalidDataException('无此上级，请检查');
					}
					//如果助力者人数够了，就不给助力了
					if ($joinData->help_num >= $joinData->fission_num) {
						throw new InvalidDataException('助力人数已达到，无需再助力');
					}
				}
				//检查区域限制
				if ($fissionInfo->area_type == 2) {
					$areaData = json_decode($fissionInfo->area_data, 1);
					$address  = RedPack::getAddress($lat, $lng);
					$is_limit = RedPack::checkArea($address, $areaData);
					if (!empty($is_limit)) {
						if (empty($jid)) {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与。';
						} else {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与，无法帮好友助力。';
						}
						throw new InvalidDataException($message);
					}
				}
				//当无客户时返回二维码添加
				if (empty($external_id)) {
					if (empty($jid)) {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $fissionInfo->qr_code];
					} else {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $joinData->qr_code];
					}
				} elseif ($fissionInfo->sex_type != 1) {//检查性别
					$is_limit = RedPack::checkSex($external_id, $fissionInfo->sex_type);
					if ($fissionInfo->sex_type == 2) {
						$sex_name = '男性';
					} elseif ($fissionInfo->sex_type == 3) {
						$sex_name = '女性';
					} else {
						$sex_name = '未知';
					}
					if (!empty($is_limit)) {
						if (empty($jid)) {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与。';
						} else {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与，您无法帮好友助力。';
						}
						throw new InvalidDataException($message);
					}
				}

				//判断当前客户是否加过任务中的成员
				$userIdList = WorkExternalContactFollowUser::find()->where(['external_userid' => $external_id, 'del_type' => 0])->select('userid')->all();
				if (!empty($userIdList)) {
					$userId    = array_column($userIdList, 'userid');//此客户添加的成员
					$userArr   = json_decode($fissionInfo->user, 1);//任务中成员
					$intersect = array_intersect($userId, $userArr);
				}

				if (!empty($intersect)) {
					$is_help   = 0;
					$help_name = $head_url = $nick_name = $help_head_url = '';
					$parent_id = !empty($joinData) ? $joinData->external_id : 0;
					if (empty($jid)) {
						$open_type = 1;
						FissionJoin::setJoin($fissionInfo, [Fission::FISSION_HEAD, $fissionInfo->id, $external_id], $parent_id);
					} else {
						$open_type = 2;
						if ($parent_id == $external_id) {
							return ['err_msg' => '自己不能给自己助力'];
						}
						$is_help = FissionJoin::setHelpDetail($fissionInfo, [Fission::FISSION_HEAD, $fissionInfo->id, $parent_id, $external_id]);
						if ($is_help == 0) {
							return ['err_msg' => '已助力过，无需再助力'];
						} elseif ($is_help == 2) {
							return ['err_msg' => '来晚一步，好友已完成打Call任务'];
						} elseif ($is_help == 3) {
							return ['err_msg' => '助力次数已达限制，不能再助力'];
						}
						if ($is_help == 1) {
							$contactData = WorkExternalContact::findOne($parent_id);
							if (!empty($contactData)) {
								$nick_name = urldecode($contactData->name);
							}
						}
						$externalContact = WorkExternalContact::findOne($external_id);
						$help_name       = urldecode($externalContact->name);
						$help_head_url   = $externalContact->avatar;
					}

					return ['err_msg' => '', 'open_type' => $open_type, 'is_help' => $is_help, 'help_name' => $help_name, 'nick_name' => $nick_name, 'help_head_url' => $help_head_url, 'is_refresh' => 1];
				} else {//未加过，返回二维码链接
					if (empty($jid)) {
						return ['err_msg' => '', 'open_type' => 0, 'qr_code' => $fissionInfo->qr_code];
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
		 * @catalog         数据接口/api/chat-message/
		 * @title           助力
		 * @description     助力
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/help
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param agent_id 必选 string 企业应用id
		 * @param code 必选 string 获取用户信息的code
		 * @param assist 必选 string 附带上级参数
		 *
		 * @return          {"error":0,"data":{"jid":32,"title":"测试4","is_forbid":0,"is_use":0,"status":2,"join_status":0,"is_own":1,"is_remind":0,"is_help":0,"help_name":"","complete_num":1,"fission_num":1,"help_num":0,"rest_num":1,"ranking":2,"picRule":{"back_pic_url":"/upload/images/2/20200325/15851345225e7b3bbaa309a.png","is_avatar":1,"avatar":{"w":40,"x":0,"y":7},"shape":"circle","is_nickname":1,"nickName":{"w":120,"h":36,"x":0,"y":48},"qrCode":{"w":60,"x":0,"y":264},"color":"#F5A623","font_size":14,"align":"left"},"prizeName":"玩偶","nick_name":"王盼","head_url":"http://wx.qlogo.cn/mmhead/GibvHudxmlJbHQEV84mpeundfic12MygBdy3xd7icp02P6yh3via5yJXSA/0","qr_code":"https://wework.qpic.cn/wwpic/915951_GYVyM-lIRRaJf1z_1585293198/0","shareData":{"title":"哈哈哈哈哈哈哈哈哈哈或或或或或或或或呵呵呵","desc":"45543543543543tertret64563454353454","pic_url":"https://tapi.fastwhale.com.cn/upload/images/2/20200325/15851347825e7b3cbe7a3af.png","shareUrl":"http://ttest-m-scrm.wemero.cn/h5/pages/fission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=fission_21_1993"},"timeData":{"day":"0","hour":"04","minutes":"36","seconds":"09"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    jid string 参与者id
		 * @return_param    title string 活动名称
		 * @return_param    is_forbid string 是否禁止分享
		 * @return_param    is_use string code是否使用过
		 * @return_param    status string 活动状态：0删除、1未发布、2已发布、3到期结束、4奖品无库存结束、5、手动提前结束
		 * @return_param    join_status string 参与者状态：0去邀请 1 已获奖品 2 活动结束，已无奖品
		 * @return_param    is_own string 是否是自己
		 * @return_param    is_remind string 是否需要提醒
		 * @return_param    is_help string 是否助力成功
		 * @return_param    help_name string 被助力者名称
		 * @return_param    complete_num string 活动的已完成数量
		 * @return_param    fission_num string 活动的裂变数量
		 * @return_param    help_num string 助力人数
		 * @return_param    rest_num string 还差人数
		 * @return_param    ranking string 助力人数排行名次
		 * @return_param    picRule array 海报位置数据
		 * @return_param    prizeName string 奖品名称
		 * @return_param    nick_name string 昵称
		 * @return_param    head_url string 头像
		 * @return_param    qr_code string 渠道活码地址
		 * @return_param    h5Url string h5页面地址
		 * @return_param    shareData array 分享数据
		 * @return_param    timeData array 倒计时数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-25 14:57
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionHelpOld ()
		{
			$corp_id     = \Yii::$app->request->post('corp_id', '');
			$agent_id    = \Yii::$app->request->post('agent_id', '');
			$code        = \Yii::$app->request->post('code', '');
			$assist      = \Yii::$app->request->post('assist', '');
			$web_url     = \Yii::$app->params['web_url'];
			$stateArr    = explode('_', $assist);
			$fission_id  = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
			$parent_id   = isset($stateArr[2]) ? intval($stateArr[2]) : 0;
			$fissionInfo = Fission::findOne($fission_id);
			if (empty($fissionInfo)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			try {

				$corpAgent   = WorkCorpAgent::findOne($fissionInfo->agent_id);
				WorkUtils::getUserData($code, $corp_id, $result, [], true);

				if (!empty($result->UserId)) {
					throw new InvalidDataException('您已是企业成员或已绑定过个人微信，均无法参与活动！');
				} elseif ($result->OpenId) {
					\Yii::error($result->OpenId, 'OpenId');
					$externalContact = WorkExternalContact::findOne(['corp_id' => $corp_id, 'openid' => $result->OpenId]);
				} else {
					throw new InvalidDataException('获取用户信息失败，请重新刷新');
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
			$date      = date('Y-m-d H:i:s');
			$is_forbid = 0;
			if ($fissionInfo->status == 1 || $fissionInfo->start_time >= $date) {
				throw new InvalidDataException('此活动还未发布');
			} elseif ($fissionInfo->status == 0) {
				throw new InvalidDataException('此活动已删除');
			} elseif ($fissionInfo->status == 2 && $fissionInfo->end_time <= $date) {
				$is_forbid           = 1;
				$fissionInfo->status = 3;
				$fissionInfo->update();
				Fission::delConfigId($fissionInfo);
				//throw new InvalidDataException('此活动已结束');
			}
			$picRule     = json_decode($fissionInfo->pic_rule, 1);
			$prizeRule   = json_decode($fissionInfo->prize_rule, 1);
			$workCorp    = WorkCorp::findOne($fissionInfo->corp_id);
			$fission_num = $prizeRule[0]['fission_num'];

			try {
				$help_name = $nick_name = $head_url = '';
				$is_own    = $help_num = $rest_num = $ranking = $join_status = $jid = $is_help = $is_remind = 0;
				$webUrl    = $web_url . Fission::H5_URL . '?corp_id=' . $fissionInfo->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $fissionInfo->agent_id;
				$shareUrl  = $webUrl . '&assist=' . $assist;
				if (!empty($externalContact) && !empty($externalContact->id)) {//是外部联系人
					$external_id = $externalContact->id;
					\Yii::error($external_id, '$external_id');
					//判断当前客户是否加过任务中的成员
					$userIdList = WorkExternalContactFollowUser::find()->where(['external_userid' => $external_id, 'del_type' => 0])->select('userid')->all();
					if (!empty($userIdList)) {
						$userId    = array_column($userIdList, 'userid');//此客户添加的成员
						$userArr   = json_decode($fissionInfo->user, 1);//任务中成员
						$intersect = array_intersect($userId, $userArr);
					}
					//是否是参与者
					$joinData = FissionJoin::findOne(['uid' => $fissionInfo->uid, 'fid' => $fissionInfo->id, 'external_id' => $external_id]);
					//是否已添加过成员或者是参与者
					if (!empty($intersect) || !empty($joinData)) {
						//设置参与者
						FissionJoin::setJoin($fissionInfo, ['fission', $fissionInfo->id, $external_id], $parent_id);
						if (!empty($parent_id) && ($parent_id != $external_id) && ($fissionInfo->status == 2)) {
							//助力记录
							$is_help = FissionJoin::setHelpDetail($fissionInfo, ['fission', $fissionInfo->id, $parent_id, $external_id]);
							//获取被助力者名称
							if (!empty($is_help)) {
								$contactData = WorkExternalContact::findOne($stateArr[2]);
								if (!empty($contactData)) {
									$help_name = urldecode($contactData->name);
								}
							}
						}

						//页面显示的数据
						$fissionJoin = FissionJoin::setJoin($fissionInfo, ['fission', $fissionInfo->id, $external_id], $parent_id);
						if (!empty($fissionJoin)) {
							$is_own = 1;
							//排名
							$help_num = !empty($fissionJoin->help_num) ? $fissionJoin->help_num : 0;
							if ($fissionJoin->help_num < $fissionJoin->fission_num) {
								$rest_num = $fissionJoin->fission_num - $fissionJoin->help_num;
							} else {
								$join_status = 1;
								if (!empty($fissionJoin->is_remind)) {
									$is_remind              = 1;
									$fissionJoin->is_remind = 0;
									if (!$fissionJoin->validate() || !$fissionJoin->save()) {
										throw new InvalidDataException(SUtils::modelError($fissionJoin));
									}
								}
							}
							$jid     = $fissionJoin->id;
							$qr_code = $fissionJoin->qr_code;
							//排名
							$joinList = FissionJoin::find()->where(['fid' => $fissionInfo->id])->andWhere(['>=', 'help_num', $fissionJoin->help_num])->orderBy('help_num desc')->all();
							$ranking  = 1;
							if (!empty($joinList)) {
								foreach ($joinList as $join) {
									if ($join->id == $jid) {
										break;
									} else {
										$ranking += 1;
									}
								}
							}
							$contactData = WorkExternalContact::findOne($external_id);
							if (!empty($contactData)) {
								$nick_name = urldecode($contactData->name);
								$head_url  = $contactData->avatar;
							}
							$shareUrl = $webUrl . '&assist=' . Fission::FISSION_HEAD . '_' . $fissionInfo->id . '_' . $external_id;
						}
					}
				}

				//是否是自己
				if ($is_own == 0) {
					if (!empty($parent_id)) {
						$fissionJoin = FissionJoin::setJoin($fissionInfo, ['fission', $fissionInfo->id, $parent_id]);
						$qr_code     = $fissionJoin->qr_code;
						$contactData = WorkExternalContact::findOne($parent_id);
						if (!empty($contactData)) {
							$nick_name = urldecode($contactData->name);
							$head_url  = $contactData->avatar;
						}
					} else {
						$qr_code = $fissionInfo->qr_code;
					}
				}

				//任务是否结束
				$is_end = 0;
				if (in_array($fissionInfo->status, [0, 3, 4, 5])) {
					$is_end = 1;
					if ($is_help != 1) {
						$is_help = 0;
					}
					if ($join_status != 1) {
						$join_status = 2;
					}
				}

				//助力倒计时
				$timeData = [
					'day'     => '00',
					'hour'    => '00',
					'minutes' => '00',
					'seconds' => '00',
				];
				if ($is_end == 0) {
					$time     = time();
					$end_time = strtotime($fissionInfo->end_time);
					if ($end_time > $time) {
						$timestamp           = $end_time - $time;
						$timeData['day']     = (string) floor($timestamp / (3600 * 24));
						$timeData['hour']    = floor(($timestamp % (3600 * 24)) / 3600);
						$timeData['minutes'] = floor(($timestamp % 3600) / 60);
						$timeData['seconds'] = floor($timestamp % 60);
						$timeData['hour']    = ($timeData['hour'] >= 10) ? (string) $timeData['hour'] : '0' . $timeData['hour'];
						$timeData['minutes'] = ($timeData['minutes'] >= 10) ? (string) $timeData['minutes'] : '0' . $timeData['minutes'];
						$timeData['seconds'] = ($timeData['seconds'] >= 10) ? (string) $timeData['seconds'] : '0' . $timeData['seconds'];
					}
				}
				$status = $fissionInfo->status;

				//分享链接
				$site_url              = \Yii::$app->params['site_url'];
				$welcomeArr            = json_decode($fissionInfo->welcome, 1);
				$shareData             = [];
				$shareData['title']    = $welcomeArr['link_start_title'];
				$shareData['desc']     = $welcomeArr['link_desc'];
				$shareData['pic_url']  = $site_url . $welcomeArr['link_pic_url'];
				$shareData['shareUrl'] = $shareUrl;

				if (!empty($head_url)) {
					//获取远程文件所采用的方法
					$ch      = curl_init();
					$timeout = 300;
					curl_setopt($ch, CURLOPT_URL, $head_url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
					$img = curl_exec($ch);
					curl_close($ch);
					$base64Data = 'data:image/png;base64,' . base64_encode($img);
				} else {//默认头像
					$base64Data = $head_url = $site_url . '/static/image/default-avatar.png';
				}

				$returnData = [
					'fid'          => $fissionInfo->id,
					'jid'          => $jid,
					'title'        => $fissionInfo->title,
					'is_forbid'    => $is_forbid,
					'is_use'       => 0,
					'is_parent'    => !empty($parent_id) ? 1 : 0,
					'status'       => $status,
					'openid'       => !empty($result->OpenId) ? $result->OpenId : '',
					'join_status'  => $join_status,
					'is_own'       => $is_own,
					'is_remind'    => $is_remind,
					'is_help'      => $is_help,
					'help_name'    => $help_name,
					'complete_num' => $fissionInfo->complete_num,
					'fission_num'  => $fission_num,
					'help_num'     => $help_num,
					'rest_num'     => $rest_num,
					'ranking'      => $ranking,
					'picRule'      => $picRule,
					'prizeName'    => $prizeRule[0]['prize_name'],
					'nick_name'    => $nick_name,
					'head_url'     => $head_url,
					'qr_code'      => $qr_code,
					'base64Data'   => $base64Data,
					'h5Url'        => $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT ? $shareUrl . '&suite_id=' . $corpAgent->suite->suite_id : $shareUrl,
					'shareData'    => $shareData,
					'timeData'     => $timeData
				];
				\Yii::error($returnData, 'returnData');

				return $returnData;
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           获取人气列表
		 * @description     获取人气列表
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/help-list
		 *
		 * @param jid 必选 string 参与者id
		 * @param type 必选 string 类型：0我的好友、1排行榜
		 * @param page 可选 string 默认页数
		 * @param pageSize 可选 string 默认每页数量
		 *
		 * @return          {"error":0,"data":{"info":{"avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM7pibwaHtwiaHrm7jroeuz8nPguxQr0CVD21YxNYtElNSHA/0","name":"SHAKALAKA","help_num":4,"rest_num":0,"ranking":1},"count":"7","helpList":[{"key":"3","id":"3","help_num":"4","name":"SHAKALAKA","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM7pibwaHtwiaHrm7jroeuz8nPguxQr0CVD21YxNYtElNSHA/0"},{"key":"4","id":"4","help_num":"2","name":"flu","avatar":null},{"key":"5","id":"5","help_num":"0","name":"LLR","avatar":null}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    info array 参与者数据
		 * @return_param    info.avatar string 参与者头像
		 * @return_param    info.name string 参与者名称
		 * @return_param    info.help_num string 参与者助力数
		 * @return_param    info.rest_num string 参与者还差数量
		 * @return_param    count string 列表数量
		 * @return_param    helpList array 列表数量
		 * @return_param    helpList.key string key
		 * @return_param    helpList.id string id
		 * @return_param    helpList.help_num string 助力人数
		 * @return_param    helpList.name string 名字
		 * @return_param    helpList.avatar string 头像
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-25 13:37
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
			$jid      = \Yii::$app->request->post('jid', 0);
			$type     = \Yii::$app->request->post('type', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			if (empty($jid)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			$joinInfo = FissionJoin::findOne($jid);
			if (empty($joinInfo)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			$fissionInfo = Fission::findOne($joinInfo->fid);
			$rest_num    = $ranking = 0;
			if ($joinInfo->help_num < $joinInfo->fission_num) {
				$rest_num = $joinInfo->fission_num - $joinInfo->help_num;
			}
			$helpList    = [];
			$contactInfo = WorkExternalContact::findOne($joinInfo->external_id);
			if ($type == 0) {
				$helpData = FissionHelpDetail::find()->alias('fhd');
				$helpData = $helpData->leftJoin('{{%work_external_contact}} wec', '`fhd`.`external_id` = `wec`.`id`');
				$helpData = $helpData->where(['fhd.jid' => $jid, 'fhd.status' => 1]);
				$helpData = $helpData->select('wec.name,wec.avatar,fhd.*');
				$offset   = ($page - 1) * $pageSize;
				$count    = $helpData->count();
				$helpData = $helpData->limit($pageSize)->offset($offset)->asArray()->all();
				foreach ($helpData as $key => $help) {
					$helpList[$key]['key']       = $help['id'];
					$helpList[$key]['id']        = $help['id'];
					$helpList[$key]['name']      = urldecode($help['name']);
					$helpList[$key]['avatar']    = $help['avatar'];
					$helpList[$key]['help_time'] = substr($help['help_time'], 0, 16);
				}
			} else {
				$fissionJoin = FissionJoin::find()->alias('fj');
				$fissionJoin = $fissionJoin->leftJoin('{{%work_external_contact}} wec', '`fj`.`external_id` = `wec`.`id`');
				$fissionJoin = $fissionJoin->where(['fj.fid' => $joinInfo->fid, 'fj.is_black' => 0]);
				$fissionJoin = $fissionJoin->select('wec.name,wec.avatar,fj.*');
				$fissionJoin = $fissionJoin->orderBy('fj.help_num desc');
				$offset      = ($page - 1) * $pageSize;
				$count       = $fissionJoin->count();
				$helpData    = $fissionJoin->limit($pageSize)->offset($offset)->asArray()->all();
				foreach ($helpData as $key => $help) {
					$helpList[$key]['key']      = $help['id'];
					$helpList[$key]['id']       = $help['id'];
					$helpList[$key]['help_num'] = $help['help_num'];
					$helpList[$key]['name']     = urldecode($help['name']);
					$helpList[$key]['avatar']   = $help['avatar'];
				}
				//排名
				$joinList = FissionJoin::find()->where(['fid' => $joinInfo->fid])->andWhere(['>=', 'help_num', $joinInfo->help_num])->orderBy('help_num desc')->all();
				$ranking  = 1;
				if (!empty($joinList)) {
					foreach ($joinList as $join) {
						if ($join->id == $jid) {
							break;
						} else {
							$ranking += 1;
						}
					}
				}
			}
			$info = ['avatar' => $contactInfo->avatar, 'name' => $contactInfo->name, 'help_num' => $joinInfo->help_num, 'rest_num' => $rest_num, 'ranking' => $ranking];

			return ['title' => $fissionInfo->title, 'info' => $info, 'count' => $count, 'helpList' => $helpList];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           裂变活动预览接口
		 * @description     裂变活动预览接口
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/preview
		 *
		 * @param fid 必选 string 活动id
		 *
		 * @return          {"error":0,"data":{"tip":"","complete_num":2,"fission_num":2,"picRule":{"back_pic_url":"/upload/images/2/20200324/15850401845e79cb38dd8c5.png","is_avatar":1,"avatar":{"w":40,"x":24,"y":24},"shape":"circle","is_nickname":1,"nickName":{"w":120,"h":36,"x":88,"y":28},"qrCode":{"w":60,"x":161,"y":225},"color":"#000000","font_size":14,"align":"left"},"prizeName":"测试奖品"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    tip string 提示语
		 * @return_param    complete_num string 已领取人数
		 * @return_param    fission_num string 裂变人数
		 * @return_param    picRule string 海报规则
		 * @return_param    prizeName string 奖品名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-03-27 17:55
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionPreview ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$fid         = \Yii::$app->request->post('fid', 0);
			$fissionInfo = Fission::findOne($fid);
			if (empty($fissionInfo)) {
				throw new InvalidDataException('链接地址不正确，请检查链接地址');
			}
			$picRule   = json_decode($fissionInfo->pic_rule, 1);
			$prizeRule = json_decode($fissionInfo->prize_rule, 1);
			$tip       = '仅供预览';
			if ($fissionInfo->status == 0) {
				$tip = '活动已被删除，仅供预览';
			} elseif ($fissionInfo->status == 1) {
				$tip = '活动尚未开始，仅供预览';
			} elseif (in_array($fissionInfo->status, [3, 4, 5])) {
				$tip = '活动已结束，仅供预览';
			}
			$web_url = \Yii::$app->params['web_url'];

			return [
				'tip'          => $tip,
				'title'        => $fissionInfo->title,
				'qr_code'      => $web_url . '/h5/pages/fission/preview?fid=' . $fissionInfo->id,
				'complete_num' => $fissionInfo->complete_num,
				'fission_num'  => $prizeRule[0]['fission_num'],
				'picRule'      => $picRule,
				'prizeName'    => $prizeRule[0]['prize_name'],
                'uid'          => $fissionInfo->uid,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           多选发送页面展示
		 * @description     多选发送页面展示
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/send-display
		 *
		 * @param uid 必选 string 用户id
		 * @param ids 必选 array 附件id数组
		 * @param agent_id 必选 string 应用id
		 * @param group_id 可选 string 分组id
		 * @param name 可选 string 附件名称
		 * @param page 可选 string 页码，默认1
		 * @param pageSize 可选 string 每页数量，默认20
		 *
		 * @return          {"error":0,"data":{"count":"2","attachment":[{"id":"633","uid":"2","group_id":"15","file_type":"4","file_name":"123","file_content_type":null,"local_path":"/upload/images/20200115/15790507925e1e67283a96b.jpg","jump_url":"https://baidu.com","content":"234"},{"id":"1048","uid":"2","group_id":"15","file_type":"4","file_name":"gtddfgg","file_content_type":null,"local_path":"/upload/images/20191023/15718249545db0253a27347.jpg","jump_url":"https://baidu.com","content":"dddddd"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数据数量
		 * @return_param    attachment array 数据列表
		 * @return_param    id string 附件id
		 * @return_param    uid string 用户id
		 * @return_param    group_id string 分组id
		 * @return_param    file_type string 附件类型
		 * @return_param    file_name string 附件名称
		 * @return_param    file_content_type string 附件类型
		 * @return_param    local_path string 地址
		 * @return_param    jump_url string 跳转地址
		 * @return_param    content string 内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-04-24 14:44
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSendDisplay ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$ids      = \Yii::$app->request->post('ids', '');
			$group_id = \Yii::$app->request->post('group_id', '');
			$agent_id = \Yii::$app->request->post('agent_id', '');
			$chat_id  = \Yii::$app->request->post('chat_id', '');
			$name     = \Yii::$app->request->post('name', '');
			if (empty($uid) || empty($ids)) {
				throw new InvalidDataException('参数不正确');
			}
			$idArr      = explode(',', $ids);
			$attachment = Attachment::find()->where(['uid' => $uid, 'id' => $idArr]);
			if (!empty($name)) {
				$attachment = $attachment->andWhere(['like', 'file_name', $name]);
			}
			if (!empty($group_id)) {
				if (is_array($group_id)) {
					$attachment = $attachment->andWhere(['group_id' => $group_id]);
				} else {
					$idList     = AttachmentGroup::getSubGroupId($group_id);
					$attachment = $attachment->andWhere(['group_id' => $idList]);
				}
			}
			//分页
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('pageSize', 20);
			$offset     = ($page - 1) * $pageSize;
			$count      = $attachment->count();
			$select     = 'id,uid,group_id,file_type,file_name,file_content_type,local_path,jump_url,content';
			$attachment = $attachment->select($select);
			$attachData = $attachment->limit($pageSize)->offset($offset)->asArray()->all();
			$site_url   = \Yii::$app->params['site_url'];
			$web_url    = \Yii::$app->params['web_url'];
			$file_type  = '4';
			foreach ($attachData as $key => $attach) {
				$file_type = $attach['file_type'];
				if (!empty($attach['local_path'])) {
					$attachData[$key]['local_path'] = $site_url . $attach['local_path'];
				}
				if ($file_type == 5) {
					$attachData[$key]['extension'] = Attachment::getExtension($attach['file_content_type'], $attach['file_name']);
				} elseif ($file_type == 4) {
					$jump_url = $attach['jump_url'];
					if (!empty($agent_id)) {
						if (strpos($jump_url, $web_url) !== false) {
							if (strpos($jump_url, '?') !== false) {
								$jump_url = $jump_url . '&agent_id=' . $agent_id;
							} else {
								$jump_url = $jump_url . '?agent_id=' . $agent_id;
							}
							if (!empty($chat_id)) {
								$jump_url = $jump_url . '&chat_id=' . $chat_id;
							}
						}
					}
					$attachData[$key]['jump_url'] = $jump_url;
				} else {
					$attachData[$key]['extension'] = '';
				}
			}

			return [
				'count'      => $count,
				'file_type'  => $file_type,
				'attachment' => $attachData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-get-config
		 * @title           活动获取appid
		 * @description     活动获取appid
		 * @url  http://{host_name}/modules/api/chat-message/activity-get-config
		 *
		 * @param activity_id 必选 int 活动id
		 *
		 * @return  array
		 *
		 * @return_param    data array 微信配置信息
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-8-27
		 */
		public function actionActivityGetConfig ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidParameterException("请求方式不正确");
			}
			$activityId = \Yii::$app->request->post('activity_id');
			$activity   = WorkPublicActivity::findOne($activityId);
			$url        = \Yii::$app->request->post('url', '');//地址
			if (empty($activity)) {
				throw new InvalidDataException("活动不存在");
			}
			$ticket = \Yii::$app->cache->get($activity->public_id . "-sym");
			$wxInfo = WxAuthorizeInfo::findOne($activity->public_id);
			if (empty($ticket)) {
				$wechat = WorkPublicPoster::getWxObject($activity->public_id);
				$jsapi  = $wechat->getJsApiTicket();
				\Yii::$app->cache->set($activity->public_id . "-sym", $jsapi, 7200);
			}
			$timestamp  = time();
			$urlArr     = explode('#', $url);
			$url        = $urlArr[0];
			$nonceStr   = WorkCorp::getRandom(16);
			$str        = 'jsapi_ticket=' . $ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
			$signature  = sha1($str);
			$ticketData = [
				'debug'     => true,
				'appId'     => $wxInfo->authorizer_appid,
				'timestamp' => $timestamp,
				'nonceStr'  => $nonceStr,
				'signature' => $signature,
				'jsApiList' => [
					'hideOptionMenu',
				],
                'uid'       => $activity->uid,
			];

			return $ticketData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-status-go
		 * @title           活动是否继续--占时废弃
		 * @description     活动是否继续--占时废弃
		 * @url  http://{host_name}/modules/api/chat-message/activity-status-go
		 *
		 * @param activity_id 必选 int 活动id
		 * @param fans_id 必选 int 参加粉丝id
		 *
		 * @return_param    prize_name string 奖品名称
		 * @return_param    num int 库存
		 */
		public function actionActivityStatusGo ()
		{
			if (\Yii::$app->request->isGet) {
				$activityId = \Yii::$app->request->get('activity_id');
				$code       = \Yii::$app->request->get('code');
				$show       = \Yii::$app->request->get('show', 0);
				$openid     = \Yii::$app->request->get('openid');

			} else {
				$activityId = \Yii::$app->request->post('activity_id');
				$code       = \Yii::$app->request->post('code');
				$openid     = \Yii::$app->request->post('openid');
				$show       = \Yii::$app->request->post('show', 0);
			}
			if ($show == 1) {
				//上线要改
				$redirectUrl = "http://wangbowen.ttest-m-scrm.wemero.cn" . WorkPublicActivity::TICKET_H5_URL . '?activity=' . $activityId . '&code=' . $code;
				$this->redirect($redirectUrl);

				return;
			}
			if (empty($code)) {
				throw new InvalidDataException("参数不正确");
			}
			$activity = WorkPublicActivity::findOne($activityId);
			if (empty($activity)) {
				throw new InvalidDataException("活动不存在");
			}
			if (!$openid) {
				$wxAuthInfo  = WxAuthorize::findOne(['author_id' => $activity->public_id]);
				$wxAuthorize = WxAuthorize::getTokenInfo($wxAuthInfo->authorizer_appid, false, true);
				if (empty($wxAuthorize)) {
					throw new InvalidDataException("未获取授权信息");
				}
				$wechat   = \Yii::createObject([
					'class'                 => Wechat::className(),
					'appId'                 => $wxAuthInfo->authorizer_appid,
					'appSecret'             => $wxAuthorize['config']->appSecret,
					'token'                 => $wxAuthorize['config']->token,
					'componentAppId'        => $wxAuthorize['config']->appid,
					'_componentAccessToken' => $wxAuthorize['config']->component_access_token,
				]);
				$userInfo = $wechat->getOauth2AccessToken($code);
				$openid   = $userInfo["openid"];
				$fansInfo = Fans::findOne(['openid' => $openid]);
			} else {
				$fansInfo = Fans::findOne(['openid' => $openid]);
			}
			if ($activity->is_over != 1 || $activity->end_time < time()) {
				throw new InvalidDataException("活动已结束");
			}
			if (empty($fansInfo)) {
				throw new InvalidDataException("未关注公众号");
			}
			$fans = WorkPublicActivityFansUser::findOne(['fans_id' => $fansInfo->id, "activity_id" => $activityId]);
			if (empty($fans)) {
				throw new InvalidDataException("活动人员不存在");
			}

			$level        = WorkPublicActivityConfigLevel::find()
				->where(["activity_id" => $activityId, "is_open" => 1, "level" => 1])
				->andWhere("$fans->activity_num >= number")->orderBy("level desc")->asArray()->one();
			$levels       = WorkPublicActivityConfigLevel::find()
				->where(["activity_id" => $activityId, "is_open" => 1, "level" => 1])
				->asArray()->all();
			$prize_status = 0;
			if ($level) {
				$prize_status = 3;
			}
			$msg = '';
			$sum = array_column($levels, "num");
			if (!empty($level)) {
				if (!empty($fans->prize)) {
					$prize = WorkPublicActivityPrizeUser::findOne($fans->prize);
					if (!empty($prize)) {
						if ($prize->status == 0) {
							$prize_status = 1;
						} elseif ($prize->status == 1) {
							$prize_status = 2;
						}
					}
				}

				return ["level" => $level["level"], "levels" => $levels, "prize_status" => $prize_status, "openid" => $openid];
			}

			return ['error' => 1];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-config
		 * @title           任务宝公众号配置
		 * @description     任务宝公众号配置
		 * @url  http://{host_name}/modules/api/chat-message/activity-config
		 *
		 * @param activity_id 必选 int 活动id
		 *
		 * @return_param    config string 公众号配置
		 * @return_param    tickets_start int 活动开始时间
		 * @return_param    tickets_end int 活动结束时间
		 */
		public function actionActivityConfig ()
		{
			$activityId = \Yii::$app->request->post('activity_id');
			$code       = \Yii::$app->request->post('code');
			if (empty($code)) {
				throw new InvalidDataException("参数不正确");
			}
			$activity = WorkPublicActivity::findOne($activityId);
			if (empty($activity)) {
				throw new InvalidDataException("活动不存在");
			}
			$wxAuthInfo  = WxAuthorize::findOne(['author_id' => $activity->public_id]);
			$wxAuthorize = WxAuthorize::getTokenInfo($wxAuthInfo->authorizer_appid, false, true);
			if (empty($wxAuthorize)) {
				throw new InvalidDataException("未获取授权信息");
			}
			$wechat   = \Yii::createObject([
				'class'                 => Wechat::className(),
				'appId'                 => $wxAuthInfo->authorizer_appid,
				'appSecret'             => $wxAuthorize['config']->appSecret,
				'token'                 => $wxAuthorize['config']->token,
				'componentAppId'        => $wxAuthorize['config']->appid,
				'_componentAccessToken' => $wxAuthorize['config']->component_access_token,
			]);
			$userInfo = $wechat->getOauth2AccessToken($code);
			if (empty($userInfo)) {
				$redirectUrl = \Yii::$app->params["site_url"] . '/api/chat-message/activity-status-success?activity_id=' . $activityId . '&show=1';
				$url         = $wechat->getOauth2AuthorizeUrl($redirectUrl);

				return ["error" => 0, "url" => $url];
			}

			return [
				"openid" => $userInfo["openid"],
				"config" => json_decode($activity->hfive_config, 1),
//				"tickets_start" => date("Y-m-d H:i", $activity->tickets_start),
//				"tickets_end"   => date("Y-m-d H:i", $activity->tickets_end),
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-status-success
		 * @title           任务宝兑奖
		 * @description     任务宝兑奖
		 * @url  http://{host_name}/modules/api/chat-message/activity-status-success
		 *
		 * @param activity_id 必选 int 活动id
		 * @param level_id 必选 int 等级id
		 * @param openid 必选 int 用户openid
		 * @param name 必选 int 收货人姓名
		 * @param mobile 必选 int 收货人手机号
		 * @param region 必选 int 收货人所在省
		 * @param city 必选 int 收货人所在市
		 * @param county 必选 int 收货人所在县区
		 * @param detail 必选 int 详细地址
		 * @param remark 必选 int 备注
		 *
		 * @return_param    error int 0
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-11 15:57
		 * @number          0
		 */
		public function actionActivityStatusSuccess ()
		{
			if (\Yii::$app->request->isGet) {
				$activityId = \Yii::$app->request->get('activity_id');
				$code       = \Yii::$app->request->get('code');
				$show       = \Yii::$app->request->get('show', 0);
				if ($show == 1 && empty($fansId)) {
					$redirectUrl = \Yii::$app->params["web_url"] . WorkPublicActivity::REDEEM . '?activity=' . $activityId . '&code=' . $code;

					$this->redirect($redirectUrl);

					return;
				}
			}
			$activityId     = \Yii::$app->request->post('activity_id');
			$openid         = \Yii::$app->request->post('openid');
			$level_id       = \Yii::$app->request->post('level_id');
			$data['name']   = \Yii::$app->request->post('name');
			$data['mobile'] = \Yii::$app->request->post('mobile');
			$data['region'] = \Yii::$app->request->post('region');
			$data['city']   = \Yii::$app->request->post('city');
			$data['county'] = \Yii::$app->request->post('county');
			$data['detail'] = \Yii::$app->request->post('detail');
			$data['remark'] = \Yii::$app->request->post('remark');
			if (!preg_match("/[\d]{11}|[+86][\d]{13}/", $data["mobile"])) {
				throw new InvalidDataException("联系方式不正确");
			}

			return WorkPublicActivityConfigLevel::activitySuccess($activityId, $level_id, $openid, $data);
		}

		public function actionActivityPreview ()
		{
			$assist       = \Yii::$app->request->post('assist', '');
			$stateArr     = explode('_', $assist);
			$activity     = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
			$activityInfo = WorkPublicActivity::findOne($activity);
			$level        = WorkPublicActivityConfigLevel::find()->where(["activity_id" => $activityInfo->id, "is_open" => 1])->asArray()->all();
			$poster       = WorkPublicActivityPosterConfig::find()->where(["activity_id" => $activityInfo->id])->asArray()->one();
			//活动未开始
			if ($activityInfo->start_time > time() && $activityInfo->is_over == 1) {
				return ["run" => 0,"uid" => $activityInfo->uid, "title" => $activityInfo->activity_name, "is_over" => $activityInfo->is_over, "tip" => "活动未开始", "complete_num" => 0, "fission_num" => 0, "level" => $level, "poster" => $poster];
			} else {
				return ["run" => 1,"uid" => $activityInfo->uid];
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-config
		 * @title           任务宝企业微信活动
		 * @description     任务宝企业微信活动
		 * @url  http://{host_name}/modules/api/chat-message/activity-config
		 *
		 * @param corp_id 必选 int 活动id
		 * @param agent_id 必选 int 等级id
		 * @param code 必选 int 用户openid
		 *
		 * @return          {"error":0,"data":{"parent_id":0,"tier":191,"external_id":4104,"is_self":0,"fans_id":454,"help_type":0,"is_help":0,"location":0,"lists":[],"self_success":0,"join_type":3,"open_type":0,"qc_url":"","openid":"oojmqwAmcdC5Jx3-mn4b_vOR8NCk","level":[{"id":"184","activity_id":"117","is_open":"1","type":"1","level":"1","prize_name":"一阶任务","money_amount":null,"money_count":"0","number":"1","num":"1","create_time":"1599716207","update_time":null},{"id":"185","activity_id":"117","is_open":"1","type":"1","level":"2","prize_name":"二阶任务","money_amount":null,"money_count":"0","number":"2","num":"2","create_time":"1599716207","update_time":null},{"id":"186","activity_id":"117","is_open":"1","type":"1","level":"3","prize_name":"三阶任务","money_amount":null,"money_count":"0","number":"3","num":"3","create_time":"1599716207","update_time":null}],"timeData":{"seconds":56,"minutes":9,"hour":9,"day":"19"},"is_over":5,"nick_name":"清","head_url":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM6icHkICmQYRfTp9iaCUaUFk0NVNKzkSicTsjdnU1WUpVpiaQ/0","base64Data":"data:image/png;base64,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","title":"企业微信","shareData":{"desc":"好友，你可以给我打个Call吗？","pic_url":"https://tapi.fastwhale.com.cn/upload/fission/share.png","shareUrl":"http://ttest-m-scrm.wemero.cn/h5/pages/marketFission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=activity_117_0_191","title":"呼朋唤友，免费拿奖品"},"h5Url":"http://ttest-m-scrm.wemero.cn/h5/pages/marketFission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=activity_117_0_191","my_url":"http://ttest-m-scrm.wemero.cn/h5/pages/marketFission/index?corp_id=1&corpid=ww93caebeee67d134b&agent_id=5&assist=activity_117_454_191","area_type":1,"activity_id":117,"picRule":{"avatar":{"x":"59","w":"40","h":"40","y":"182"},"qrCode":{"x":"88","w":"178","h":"178","y":"294"},"nickName":{"x":"177","h":28,"w":84,"y":"129"},"fontSize":"14","color":"255,255,255","font_size":"14","is_avatar":"1","is_nickname":"1","back_pic_url":"https://tapi.fastwhale.com.cn/upload/images/2/20200910/15997161875f59bb5b11f6a.jpg"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    activity_id int 活动id
		 * @return_param    area_type int 区域限制
		 * @return_param    base64Data string 头像base64
		 * @return_param    external_id int 外部联系人id
		 * @return_param    fans_id int 当前参与者id
		 * @return_param    h5Url string h5地址
		 * @return_param    head_url string 头像
		 * @return_param    help_type int 帮助
		 * @return_param    is_help int 是否助力成功
		 * @return_param    is_over int 活动状态
		 * @return_param    is_self int 是否自己
		 * @return_param    join_type int
		 * @return_param    level array 奖品等级
		 * @return_param    my_url string 自己url地址
		 * @return_param    nick_name string 名称
		 * @return_param    open_type int
		 * @return_param    openid string 当前用户openid
		 * @return_param    parent_id int 上级id
		 * @return_param    picRule array 海报参数
		 * @return_param    self_success int 自己是否完成
		 * @return_param    shareData array 分享话术
		 * @return_param    tier int 层级id
		 * @return_param    timeData array 倒计时数据
		 * @return_param    title string 活动名称
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-11 15:57
		 * @number          0
		 */
		public function actionActivityHelp ()
		{
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$agent_id = \Yii::$app->request->post('agent_id', '');
			$code     = \Yii::$app->request->post('code', '');
			$assist   = \Yii::$app->request->post('assist', '');
			if (empty($corp_id) || empty($agent_id) || empty($assist)) {
				throw new InvalidDataException('参数与丢失');
			}
			$site_url     = \Yii::$app->params['site_url'];
			$web_url      = \Yii::$app->params['web_url'];
			$stateArr     = explode('_', $assist);
			$activity     = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
			$parent_id    = isset($stateArr[2]) ? intval($stateArr[2]) : 0;
			$tierId       = isset($stateArr[3]) ? intval($stateArr[3]) : 0;

            //判断人数限制
            if (WorkPublicActivity::checkJoinNumIsMax($activity)) {
                WorkPublicActivity::setActivityOver($activity);
                //throw new InvalidDataException("很抱歉，当前活动过于火爆暂时无法参与");
            }

			$activityInfo = WorkPublicActivity::findOne($activity);
			if (empty($activityInfo)) {
				throw new InvalidDataException('活动不存在');
			}
			$corpAgent      = WorkCorpAgent::findOne($activityInfo->corp_agent);
			$external_id    = \Yii::$app->request->post('external_id');
			\Yii::error($external_id,'$external_id');
			$data           = [
				'activity_id'   => $activity,
				'complete_num'  => 0,
				'nick_name'     => '',
				'head_url'      => '',
				'activity_num'  => 0,
				'rest_num'      => 0,
				'is_self'       => 0,
				'ranking'       => 0,
				'success_level' => 0,
				'lists'         => 0,
				'open_type'     => 0,
				'join_type'     => 0,
				'self_success'  => 0,
				'is_help'       => 0,
				'is_remind'     => 0,
				'help_type'     => 0,
				'fans_id'       => 0,
				'parent_id'     => $parent_id,
				'tier'          => $tierId,
				'external_id'   => $external_id,
			];
			$data["openid"] = $data["qc_url"] = $data["success_url"] = '';
			$poster         = WorkPublicActivityPosterConfig::find()->where(["activity_id" => $activityInfo->id])->asArray()->one();
			$data["level"]  = WorkPublicActivityConfigLevel::find()->where(["activity_id" => $activityInfo->id, "is_open" => 1])->asArray()->all();
			try {
				if (empty($external_id)) {

					WorkUtils::getUserData($code, $corp_id, $result, [], true);

					if (!empty($result->UserId)) {
						throw new InvalidDataException('您已是企业成员或已绑定过个人微信，均无法参与活动！');
					} elseif ($result->OpenId) {
						$externalContact = WorkExternalContact::findOne(['corp_id' => $corp_id, 'openid' => $result->OpenId]);
						if (!empty($externalContact)) {
							$data['external_id'] = $external_id = $externalContact->id;
							$data["open_type"]   = 1;
						} else {
							$data["join_type"] = 1;
							$data["new_self"]  = 1;
						}
						$data["openid"] = $result->OpenId;
					} else {
						throw new InvalidDataException('获取用户信息失败，请重新刷新');
					}
				} else {
					$externalContact = WorkExternalContact::findOne($external_id);
					if (!empty($externalContact)) {
						$data["openid"] = $externalContact->openid;
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
			if (!empty($externalContact)) {
				$data['nick_name'] = urldecode($externalContact->name);
				$data['head_url']  = $externalContact->avatar;
			}
			//活动时间
			$data['timeData']['day'] = $data['timeData']['hour'] = $data['timeData']['minutes'] = $data['timeData']['seconds'] = 0;
			$data["is_over"]         = $activityInfo->is_over;
			if ($activityInfo->start_time < time() && $activityInfo->end_time > time() && $activityInfo->is_over == 1) {
				$timestamp                   = $activityInfo->end_time - time();
				$data['timeData']['day']     = (string) floor($timestamp / (3600 * 24));
				$data['timeData']['hour']    = floor(($timestamp % (3600 * 24)) / 3600);
				$data['timeData']['minutes'] = floor(($timestamp % 3600) / 60);
				$data['timeData']['seconds'] = floor($timestamp % 60);
				$data["is_over"]             = 5;
			}

			if (!empty($external_id)) {//判断当前客户是否加过任务中的成员
				$userArr    = explode(",", $activityInfo->channel_user_id);//任务中成员
				$userIdList = WorkExternalContactFollowUser::find()
					->where(['external_userid' => $external_id])
					->andWhere(["in", "user_id", $userArr])
					->count();
				if ($userIdList > 0) {
					$f = WorkPublicActivityFansUser::findOne(["external_userid" => $external_id, "activity_id" => $activityInfo->id]);
					if (!$f) {
						$is_add = 1;//第一次进来，是否可以添加为参与者或者助力者
						if ($activityInfo->region_type != 1) {
							$is_add            = 0;
							$data["join_type"] = 1;
						} elseif ($activityInfo->sex_type != 4) {
							if ($activityInfo->sex_type == 1) {
								$activityInfo->sex_type = 2;
							} else if ($activityInfo->sex_type == 2) {
								$activityInfo->sex_type = 3;
							} else if ($activityInfo->sex_type == 3) {
								$activityInfo->sex_type = 4;
							}
							$is_limit          = RedPack::checkSex($external_id, $activityInfo->sex_type);
							$is_add            = !empty($is_limit) ? 0 : 1;
							$data["join_type"] = 1;
						}
						if ($is_add) {
							$fansData = [
								"corp_id"         => $activityInfo->corp_id,
								"public_id"       => empty($activityInfo->public_id) ? 0 : $activityInfo->public_id,
								"external_userid" => $external_id,
								"activity_id"     => $activityInfo->id,
								"create_time"     => time(),
							];
							$f        = WorkPublicActivityFansUser::setData($fansData);
							//层级
							if (empty($parent_id)) {
								$tier              = new WorkPublicActivityTier();
								$tier->fans_id     = $f->id;
								$tier->activity_id = $activityInfo->id;
								$tier->level       = '1';
								$tier->create_time = time();
								$tier->save();
								$data["tier"] = $tier->id;
							}
							$follow = WorkExternalContactFollowUser::find()->where(["external_userid"=>$external_id])->andWhere(["in","user_id",explode(",",$activityInfo->channel_user_id)])->one();
							WorkPublicActivityFansUser::activityTags(empty($follow)?0:$follow->id, $activityInfo, $f);
							$data["fans_id"]   = $f->id;
							$data["join_type"] = 3;
						}
					}
				} else {
					$data["join_type"] = 1;
				}
			}

			$userFans = WorkPublicActivityFansUser::findOne(['external_userid' => $external_id, 'activity_id' => $activityInfo->id]);
			if (!empty($userFans)) {
				$WorkPublicActivityTier = WorkPublicActivityTier::findOne(["fans_id" => $userFans->id, "activity_id" => $activity]);
				if (!empty($WorkPublicActivityTier)) {
					$data['tier'] = $WorkPublicActivityTier->id;
				}
				$data['fans_id'] = $userFans->id;
				if (empty($parent_id)) {
					$data['activity_num'] = $userFans->activity_num;
				}
				$data["join_type"] = 3;
			}
			$data['complete_num'] = WorkPublicActivityFansUser::find()->where(["activity_id" => $activity])->andWhere("prize is not null")->count();
			//有上级
			if (!empty($parent_id)) {
				$userFans = WorkPublicActivityFansUser::findOne($parent_id);
				if (!empty($userFans)) {
					$detail = WorkPublicActivityFansUserDetail::findOne(["public_user_id" => $data['fans_id'], "public_parent_id" => $parent_id, "external_userid" => empty($externalContact) ? 0 : $externalContact->id, "activity_id" => $activityInfo->id]);
					if (!empty($detail)) {
						$data['help_type'] = 2;
					} else {
						$data['help_type'] = 1;
					}
					if (!is_null($userFans->prize)) {
						$data['is_help'] = 2;
					}
					$parentExternalContact = WorkExternalContact::findOne($userFans->external_userid);
					if (!empty($parentExternalContact)) {
						$data['nick_name'] = urldecode($parentExternalContact->name);
						$data['head_url']  = $parentExternalContact->avatar;
					}
					$data["join_type"] = 2;
					$f                 = WorkPublicActivityFansUser::findOne(["external_userid" => $external_id, "activity_id" => $activityInfo->id]);
					//自己扫码自己
					if (!empty($f) && $parent_id == $f->id) {
						$data['is_help']        = 0;
						$data["fans_id"]        = $f->id;
						$WorkPublicActivityTier = WorkPublicActivityTier::findOne(["fans_id" => $userFans->id]);
						if (!empty($WorkPublicActivityTier)) {
							$data['tier'] = $WorkPublicActivityTier->id;
						}
						$self_detail = WorkPublicActivityFansUserDetail::find()
							->where(["public_parent_id" => $f->id, "activity_id" => $activityInfo->id])
							->andWhere("type is null")
							->count();
						if ($self_detail >= 1) {
							$data["lists"] = 1;
						}
						$data["join_type"] = 3;
						$data['is_self']   = 1;
						$userLevels        = WorkPublicActivityConfigLevel::findAll(["activity_id" => $activity, "is_open" => 1]);
						foreach ($userLevels as $userLevel) {
							if (!is_null($userFans->prize)) {
								$data['rest_num'] = 0;
							} else {
								$tmp = $userLevel->number - $f->activity_num;
								if ($f->activity_num < $userLevel->number && $userLevel->level == 1) {
									$data['rest_num'] = ($tmp < 0) ? 0 : $tmp;
								}
							}

						}
						if (!is_null($userFans->prize) && empty($f->level)) {
							$data["is_remind"] = 1;
							$f->level          = 1;
							$f->save();
						}
						//已经领取
						if (!is_null($userFans->prize) && $userFans->is_form == 1) {
							if ($userFans->is_form == 1) {
								$prize = WorkPublicActivityPrizeUser::findOne($userFans->prize);
								if ($prize->status == 0) {
									$data["self_success"] = 2;
								} else {
									$data["self_success"] = 1;
								}
							}
						} elseif (!is_null($userFans->prize) && $userFans->is_form == 0) {
							$data["self_success"]  = 2;
							$data["success_level"] = 1;
						}
						$lists                = WorkPublicActivityFansUser::find()->alias("a")
							->leftJoin("{{%work_external_contact}} as b", "a.external_userid = b.id")
							->where(["a.activity_id" => $activity])
							->andWhere("a.activity_num !=0")
							->select("a.activity_num,a.id,b.name as name_convert,b.avatar,a.create_time")
							->limit(100)
							->orderBy("activity_num desc")
							->asArray()->all();
						$arr                  = array_column($lists, "id");
						$data['ranking']      = array_search($f->id, $arr);
						$data['invitate_num'] = $f->activity_num;
						$data['ranking']      += 1;
						//达成任务
						$success_level = WorkPublicActivityFansUser::find()->alias("a")
							->leftJoin("{{%work_public_activity_config_level}} as b", "a.activity_id = b.activity_id")
							->where(["a.external_userid" => $external_id, "a.activity_id" => $activity])
							->andWhere("a.activity_num >= b.number")
							->one();
						if (!empty($success_level) && $activityInfo->action_type == 2) {
							$data["success_url"]   = $activityInfo->user_url;
							$data["success_level"] = 2;
						} else if (!empty($success_level) && $activityInfo->action_type == 1) {
							$data["success_level"] = 1;
						}
					}
					$data["qc_url"] = $userFans->poster_path;
				}
			}
			$is_remind = WorkPublicActivityFansUserDetail::findOne(['external_userid' => $external_id, 'activity_id' => $activityInfo->id, "public_parent_id" => $parent_id, "is_remind" => 0]);
			if (!empty($is_remind)) {
				$data["help_name"]     = empty($externalContact) ? '' : urldecode($externalContact->name);
				$data["help_head_url"] = empty($externalContact) ? '' : $externalContact->avatar;
				if (empty($data["help_head_url"])) {
					$data["help_head_url"] = $head_url = $site_url . '/static/image/default-avatar.png';
				}
				$data["is_help"]      = 1;
				$data["is_remind"]    = 2;
				$is_remind->is_remind = 1;
				$is_remind->save();
			}
			if (empty($data['head_url'])) {
				$data['head_url'] = $head_url = $site_url . '/static/image/default-avatar.png';
			}
			if (!empty($data['head_url'])) {
				//获取远程文件所采用的方法
				$ch      = curl_init();
				$timeout = 300;
				curl_setopt($ch, CURLOPT_URL, $data['head_url']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
				$img = curl_exec($ch);
				curl_close($ch);
				$data['base64Data'] = 'data:image/png;base64,' . base64_encode($img);
			}
			$data["title"]                = $activityInfo->activity_name;
			$data["shareData"]['desc']    = $activityInfo->welcome_describe;
			$data["shareData"]['pic_url'] = $site_url . $activityInfo->welcome_url;
			$workCorp                     = WorkCorp::findOne($activityInfo->corp_id);
			$state                        = WorkPublicActivity::STATE_NAME . '_' . $activityInfo->id . '_';
			if (!empty($userFans)) {
				$state1 = $state . $userFans->id . "_" . $data["tier"];
			} else {
				$state1 = $state . $parent_id . "_" . $data["tier"];
			}
			$WorkPublicActivityTier = WorkPublicActivityTier::findOne(["fans_id" => $data["fans_id"], "parent" => $parent_id, "activity_id" => $activity]);
			if (!empty($WorkPublicActivityTier)) {
				//携带对应的层级id
				$state2 = $state . $data["fans_id"] . "_" . $WorkPublicActivityTier->id;
			} else {
				//携带默认的层级id
				$state2 = $state . $data["fans_id"] . "_" . $data["tier"];
			}
			if ($corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
				$data['h5Url']  = $data["shareData"]['shareUrl'] = $web_url . WorkPublicActivity::H5_URL . '?suite_id=' . $corpAgent->suite->suite_id . '&corp_id=' . $activityInfo->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $activityInfo->corp_agent . '&assist=' . $state1;
				$data['my_url'] = $web_url . WorkPublicActivity::H5_URL . '?suite_id=' . $corpAgent->suite->suite_id . '&corp_id=' . $activityInfo->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $activityInfo->corp_agent . '&assist=' . $state2;
			} else {
				$data['h5Url']  = $data["shareData"]['shareUrl'] = $web_url . WorkPublicActivity::H5_URL . '?corp_id=' . $corpAgent->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $activityInfo->corp_agent . '&assist=' . $state1;
				$data['my_url'] = $web_url . WorkPublicActivity::H5_URL . '?corp_id=' . $corpAgent->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $activityInfo->corp_agent . '&assist=' . $state2;
			}
			$data["shareData"]['title']       = $activityInfo->welcome_title;
			$data["area_type"]                = $activityInfo->region_type;
			$data["activity_id"]              = $activity;
			$data["picRule"]["shape"]         = ($poster["heard_type"] == 1) ? "square" : "circle";
			$data["picRule"]['avatar']["x"]   = $poster["heard_left"];
			$data["picRule"]['avatar']["w"]   = $poster["heard_width"];
			$data["picRule"]['avatar']["h"]   = $poster["heard_height"];
			$data["picRule"]['qrCode']["x"]   = $poster["code_left"];
			$data["picRule"]['qrCode']["w"]   = $poster["code_width"];
			$data["picRule"]['qrCode']["h"]   = $poster["code_height"];
			$data["picRule"]['nickName']["x"] = $poster["font_left"];
			$data["picRule"]["fontSize"]      = $poster["font_size"];
			$data["picRule"]['nickName']["h"] = $poster["font_size"] * 2;
			$data["picRule"]['nickName']["w"] = $poster["font_size"] * 6;
			$data["picRule"]['avatar']["y"]   = $poster["heard_top"];
			$data["picRule"]['qrCode']["y"]   = $poster["code_top"];
			$data["picRule"]['nickName']["y"] = $poster["font_top"];
			$data["picRule"]['color']         = $poster["font_color"];
			$data["picRule"]['font_size']     = $poster["font_size"];
			$data["picRule"]['is_avatar']     = $poster["is_heard"];
			$data["picRule"]['is_nickname']   = $poster["is_font"];
			$data["picRule"]['back_pic_url']  = $site_url . $poster["background_url"];

			return $data;


		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-lists
		 * @title           任务宝排行榜
		 * @description     任务宝排行榜
		 * @url  http://{host_name}/modules/api/chat-message/activity-lists
		 *
		 * @param fans_id 必选 int 当前粉丝id
		 * @param activity_id 必选 int 活动id
		 * @param type 必选 int 1我的好友，2排行榜
		 * @param page 必选 int
		 * @param pageSize 必选 int
		 *
		 * @return_param    config string 公众号配置
		 * @return_param    tickets_start int 活动开始时间
		 * @return_param    tickets_end int 活动结束时间
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-11 15:57
		 * @number          0
		 */
		public function actionActivityLists ()
		{
			$fans_id             = \Yii::$app->request->post("fans_id");
			$activity_id         = \Yii::$app->request->post("activity_id");
			$type                = \Yii::$app->request->post("type");
			$page                = \Yii::$app->request->post("page", 0);
			$pageSize            = \Yii::$app->request->post("pageSize", 15);
			$data["location"]    = 0;
			$data["lists"]       = [];
			$data["level_msg"]   = '';
			$data["detailLists"] = [];
			$data["now"]         = 0;
			$data["self"]        = [];
			$data["count"]       = 0;
			$activity            = WorkPublicActivity::findOne($activity_id);
			if (empty($activity)) {
				throw new InvalidDataException("活动不存在");
			}
			//奖品阶级
			$levels = WorkPublicActivityConfigLevel::find()->where(["activity_id" => $activity_id, "is_open" => 1])->asArray()->all();
			$self   = WorkPublicActivityFansUser::findOne($fans_id);
			if (!empty($self)) {
				$data["now"] = $self->activity_num;
				if ($activity->type != 2) {
					$ext            = Fans::findOne($self->fans_id);
					$data["avatar"] = $ext->headerimg;
				} else {
					$ext            = WorkExternalContact::findOne($self->external_userid);
					$data["avatar"] = $ext->avatar;
				}
				foreach ($levels as $level) {
					$tmp = $level["number"] - $self->activity_num;
					if (!is_null($self->prize)) {
						$data["level_msg"] = "还差0人";
					} else {
						$data["level_msg"] = "还差" . (($tmp <= 0) ? 0 : $tmp) . "人";
					}
				}
			} else {
				$data["avatar"] = '';
				$data["now"]    = 0;
				foreach ($levels as $level) {
					$data["level_msg"] = '还差' . $level["number"] . '人';
				}

			}
			if ($type == 1) {
				$lists = WorkPublicActivityFansUser::find()->alias("a");
				//列表
				if ($activity->type != 2) {
					$lists = $lists->leftJoin("{{%fans}} as b", "a.fans_id = b.id")
						->select("a.activity_num,a.id,b.nickname as name_convert,b.headerimg as avatar,a.create_time");
				} else {
					$lists = $lists->leftJoin("{{%work_external_contact}} as b", "a.external_userid = b.id")
						->select("a.activity_num,a.id,b.name as name_convert,b.avatar,a.create_time");
				}
				$lists         = $lists->where(["a.activity_id" => $activity_id])
					->andWhere("a.activity_num !=0")
					->limit(100)
					->orderBy("activity_num desc")
					->asArray()->all();
				$data["count"] = count($lists);
				if (!empty($lists)) {
					foreach ($lists as &$list) {
						$list["name_convert"] = urldecode($list["name_convert"]);
						$list["create_time"]  = date("Y-m-d H:i", $list["create_time"]);
					}
					$data["lists"] = $lists;
					$arr           = array_column($lists, "id");
					$key           = array_search($fans_id, $arr);
					$data["self"]  = $lists[$key];
					//自己的定位
					if ($key !== false) {
						$data["location"] = $key + 1;
					}
					if (count($data["lists"]) > $pageSize) {
						$data["lists"] = array_chunk($data["lists"], $pageSize);
						if (count($data["lists"]) >= $page) {
							$data["lists"] = $data["lists"][$page - 1];
						}
					}
				}
			} else if (!empty($fans_id)) {
				if ($activity->type != 2) {
					$data["detailLists"] = WorkPublicActivityFansUserDetail::find()->alias("a")
						->leftJoin("{{%work_public_activity_fans_user}} as b", "b.id = a.public_user_id")
						->leftJoin("{{%fans}} as c", "c.id = b.fans_id")
						->where(["a.activity_id" => $activity_id, "a.public_parent_id" => $fans_id])
						->select("a.create_time,c.nickname as name_convert,c.headerimg as avatar")
						->andWhere("a.type is null ")
						->asArray()->all();
				} else {
					$data["detailLists"] = WorkPublicActivityFansUserDetail::find()->alias("a")
						->leftJoin("{{%work_external_contact}} as b", "a.external_userid = b.id")
						->where(["a.activity_id" => $activity_id, "a.public_parent_id" => $fans_id])
						->select("a.create_time,b.name as name_convert,b.avatar")
						->andWhere("a.type is null ")
						->asArray()->all();
				}

				$data["count"] = count($data["detailLists"]);

				if (!empty($data["detailLists"])) {
					foreach ($data["detailLists"] as &$datum) {
						$datum["create_time"]  = date("Y-m-d H:i", $datum["create_time"]);
						$datum["name_convert"] = urldecode($datum["name_convert"]);
					}
					if (count($data["detailLists"]) > $pageSize) {
						$data["detailLists"] = array_chunk($data["detailLists"], $pageSize);
						if (count($data["detailLists"]) >= $page) {
							$data["detailLists"] = $data["detailLists"][$page - 1];
						}
					}
				}
			}
			$data["title"] = $activity->activity_name;

			return $data;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/activity-lists
		 * @title           任务宝排行榜
		 * @description     任务宝排行榜
		 * @url  http://{host_name}/modules/api/chat-message/activity-lists
		 *
		 * @param fans_id 必选 int 当前粉丝id
		 * @param activity_id 必选 int 活动id
		 * @param parent_id 必选 int 上级id
		 * @param tier 必选 int 层级id
		 * @param openid 必选 int 助力人openid
		 * @param type 必选 int 0我要参与，1助力
		 * @param page 必选 int
		 * @param pageSize 必选 int
		 *
		 * @return_param    config string 公众号配置
		 * @return_param    tickets_start int 活动开始时间
		 * @return_param    tickets_end int 活动结束时间
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020-09-11 15:57
		 * @number          0
		 */
		public function actionActivityCall ()
		{
			$ip = \Yii::$app->request->getRemoteIP();
			if (\Yii::$app->cache->exists($ip)) {
				throw new InvalidDataException("打call次数过快");
			}
			\Yii::$app->cache->set($ip, 1, 3);
			$activityId  = \Yii::$app->request->post("activity_id");
			$external_id = \Yii::$app->request->post("external_id");
			$parent_id   = \Yii::$app->request->post("parent_id");
			$fans_id     = \Yii::$app->request->post("fans_id");
			$tierIds     = \Yii::$app->request->post("tier", 0);
			$openid      = \Yii::$app->request->post("openid");
			$type        = \Yii::$app->request->post("type", 0);
			$lat         = \Yii::$app->request->post('lat', 0);
			$lng         = \Yii::$app->request->post('lng', 0);
			$uid         = \Yii::$app->request->post('uid', 0);
			try {

				\Yii::error($external_id,'$external_id');
				if (empty($activityId)) {
					throw new InvalidDataException("参数不完整");
				}
				$activity = WorkPublicActivity::findOne($activityId);
				if (empty($activity)) {
					throw new InvalidDataException("活动不存在");
				}
				$is_add = false;
				//检查区域限制
				if ($activity->region_type != 1) {
					$areaData = json_decode($activity->region, 1);
					$address  = RedPack::getAddress($lat, $lng);
					$is_limit = RedPack::checkArea($address, $areaData);
					if (!empty($is_limit)) {
						if (empty($fans_id)) {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与。';
						} else {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与，无法帮好友助力。';
						}
						throw new InvalidDataException($message);
					} else {
						$is_add = true;
					}
				}
				$ext = WorkExternalContact::findOne($external_id);
				if ($activity->sex_type != 4 && !empty($ext)) {//检查性别
					$is_add = false;
					if ($activity->sex_type == 1) {
						$sex_name = '男性';
					} elseif ($activity->sex_type == 2) {
						$sex_name = '女性';
					} else {
						$sex_name = '未知';
					}
					if ($activity->sex_type == 1) {
						$activity->sex_type = 2;
					} else if ($activity->sex_type == 2) {
						$activity->sex_type = 3;
					} else if ($activity->sex_type == 3) {
						$activity->sex_type = 4;
					}
					$is_limit = RedPack::checkSex($ext->id, $activity->sex_type);
					if (!empty($is_limit)) {
						if (empty($fans_id)) {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与。';
						} else {
							$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与，您无法帮好友助力。';
						}
						throw new InvalidDataException($message);
					} else {
						$is_add = true;
					}
				}

                //判断人数限制
                if (WorkPublicActivity::checkJoinNumIsMax($activityId)) {
                    WorkPublicActivity::setActivityOver($activityId);
                    throw new InvalidDataException("很抱歉，当前活动过于火爆暂时无法参与");
                }

				//父级用户
				$parentFans = WorkPublicActivityFansUser::findOne($parent_id);
				$userIdList = WorkExternalContactFollowUser::find()
					->where(['external_userid' => $external_id])
					->andWhere(["in", "user_id", explode(",", $activity->channel_user_id)])
					->count();
				$f          = WorkPublicActivityFansUser::findOne(["external_userid" => $external_id, "activity_id" => $activity->id]);
				if ($is_add && $activity->region_type != 4 && $userIdList != 0 && empty($f) && !empty($ext)) {
					$fansData = [
						"corp_id"         => $activity->corp_id,
						"public_id"       => empty($activity->public_id) ? 0 : $activity->public_id,
						"external_userid" => $external_id,
						"activity_id"     => $activity->id,
						"create_time"     => time(),
					];
					$f        = WorkPublicActivityFansUser::setData($fansData);
					//层级
					$tier = WorkPublicActivityTier::findOne(["fans_id" => $f->id, "activity_id" => $activity->id]);
					if (empty($tier)) {
						$tier              = new WorkPublicActivityTier();
						$tier->fans_id     = $f->id;
						$tier->activity_id = $activity->id;
						$tier->level       = '1';
						$tier->create_time = time();
						$tier->save();
						$tier->id;
					}
					if ($type == 0) {
						return ['err_msg' => '', 'open_type' => 1, 'is_refresh' => 1];
					} else {
						$fans_id = $f->id;
					}

					$follow = WorkExternalContactFollowUser::find()->where(["external_userid"=>$ext->id])->andWhere(["in","user_id",explode(",",$activity->channel_user_id)])->one();
					WorkPublicActivityFansUser::activityTags(empty($follow)?0:$follow->id, $activity, $f);
				}

				if (empty($parent_id) && $userIdList == 0) {
					return ['err_msg' => '', 'open_type' => 0, "qr_code" => $activity->qc_url];
				} elseif (!empty($parentFans) && $userIdList == 0) {
					//获取上级的渠道二维码
					$workApi      = WorkUtils::getWorkApi($activity->corp_id, WorkUtils::EXTERNAL_API);
					$channel_user = explode(",", $activity->channel_user_id);
					$wayInfo      = WorkPublicActivity::CheckCorpUser($workApi, $activity->id, $channel_user, $parentFans->id, $tierIds,false,2);

                    //判断人数限制
                    if (WorkPublicActivity::checkJoinNumIsMax($activityId)) {
                        WorkPublicActivity::setActivityOver($activityId);
                        throw new InvalidDataException("很抱歉，当前活动过于火爆暂时无法参与");
                    }
					return ['err_msg' => '', 'open_type' => 0, "qr_code" => $wayInfo["qr_code"]];
				}
				//当前用户
				$publicFans = WorkPublicActivityFansUser::findOne($fans_id);
				if (empty($publicFans) && $type == 0) {
					return ['err_msg' => '', 'open_type' => 1];
				}

				$pext = WorkExternalContact::findOne($parentFans->external_userid);
				if (empty($publicFans) || empty($parentFans)) {
					throw new InvalidDataException("参加活动人员不存在");
				}
				//不允许互助
				if ($activity->mutual != 1) {
					$mutual = WorkPublicActivityFansUserDetail::find()
						->where(["public_user_id" => $parentFans->id, "public_parent_id" => $publicFans->id, "external_userid" => $pext->id, "activity_id" => $activityId])
						->andWhere("type is null")
						->exists();
					if ($mutual) {
						throw new InvalidDataException("不允许互助");
					}
				}
				$selParent = WorkPublicActivityFansUserDetail::find()
					->where(["public_user_id" => $publicFans->id, "public_parent_id" => $parent_id, "external_userid" => $ext->id, "activity_id" => $activityId])
					->andWhere("type is null")->exists();
				if ($selParent) {
					throw new InvalidDataException("已完成助力");
				}
				$countHelp = WorkPublicActivityFansUserDetail::find()
					->where(["public_user_id" => $publicFans->id, "external_userid" => $ext->id, "activity_id" => $activityId])
					->andWhere("type is null")
					->count();
				if ($countHelp >= $activity->number && $activity->number != 0) {
					throw new InvalidDataException("助力次数不足");
				}
				if (!is_null($parentFans->prize)) {
					return ['err_msg' => '', 'is_help' => 2, 'help_name' => urldecode($pext->name), 'nick_name' => urldecode($ext->name), 'help_head_url' => $pext->avatar, 'is_refresh' => 1];
				}
				$Transaction = \Yii::$app->db->beginTransaction();
				try {
					WorkPublicActivityFansUser::setRecord([
						"public_parent_id" => $parent_id,
						"public_user_id"   => $publicFans->id,
						"activity_id"      => $activityId,
						"external_userid"  => $ext->id,
						"is_remind"        => 1,
					]);
					if (empty($publicFans->parent_id)) {
						$publicFans->parent_id = $parent_id;
					} else {
						$publicFans->parent_id = $publicFans->parent_id . "," . $parent_id;
					}
					$parentFans->activity_num = $parentFans->activity_num + WorkPublicActivityFansUser::ACTIVITY_NUM;
					$publicFans->save();
					$parentFans->save();
					WorkPublicActivityFansUser::setParentLeveL([0, $activity->id, $parentFans->id, $tierIds], $publicFans);
					/** @var WorkPublicActivityConfigLevel $level * */
					$level      = WorkPublicActivityConfigLevel::find()->where(["activity_id" => $activityId, "is_open" => 1])->andWhere("$parentFans->activity_num >= number ")->orderBy("level desc")->one();
					$is_refresh = 1;
					if (!empty($level)) {
						if ($level->level == 1) {
							//记录完成时间
							$parentFans->success_time = time();
							$parentFans->save();
							if ($level->type == 2 && empty($parentFans->prize)) {
								WorkPublicActivityFansUser::SendRedBook('', $level["id"], $activity, $parentFans);
							} elseif ($level->type == 1 && empty($parentFans->prize)) {
								WorkPublicActivityFansUser::getPrize($parentFans, $level, $activity);
							}
						}
						if ($parentFans->activity_num == $level->number) {
							$is_refresh = 0;
						}
					}
					$Transaction->commit();

					return ['err_msg' => '', 'open_type' => 2, 'is_help' => 1, 'help_name' => urldecode($ext->name), 'nick_name' => urldecode($pext->name), 'help_head_url' => empty($ext->avatar) ? \Yii::$app->params["site_url"] . '/static/image/default-avatar.png' : $ext->avatar, 'is_refresh' => 1];

				} catch (\Exception $e) {
					$Transaction->rollBack();

					return ['err_msg' => $e->getLine()];
				}

			} catch (\Exception $e) {
				return ['err_msg' => $e->getMessage()];
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           企微公海池通知列表
		 * @description     企微公海池通知列表
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/sea-customer
		 *
		 * @param user_id 必选 int 成员id
		 * @param remind_type 必选 int 通知类型：0待回收通知客户，1、公海池客户，2、已认领客户
		 * @param follow_id 可选 string 跟进状态id
		 * @param name 可选 string 搜索词
		 * @param page 可选 string 页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-11-18 20:02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSeaCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$userId     = \Yii::$app->request->post('user_id', 0);
			$remindType = \Yii::$app->request->post('remind_type', 0);
			$name       = \Yii::$app->request->post('name', '');
			$folId      = \Yii::$app->request->post('follow_id', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('pageSize', 10);
			if (empty($userId)) {
				throw new InvalidDataException('缺少必要参数');
			}
			$workUser = WorkUser::findOne($userId);
			if (empty($workUser)) {
				throw new InvalidDataException('参数不正确');
			}
			$corpId = $workUser->corp_id;
			if ($remindType == 0) {
				$reclaim = PublicSeaReclaimSet::getClaimRule($workUser->corp_id, $userId);
				if (empty($reclaim)) {
					return ['count' => 0, 'customerData' => [], 'followData' => []];
				}
				$ruleArr  = json_decode($reclaim->reclaim_rule, 1);
				$delayDay = !empty($reclaim->is_delay) ? intval($reclaim->delay_day) : 0;
				$nowDate  = date('Y-m-d');
				//查询当前的跟进状态是否在规则里面
				if (!empty($folId)) {
					$followIdData = array_column($ruleArr, 'follow_id');
					if (!in_array($folId, $followIdData)) {
						$folId = 0;
					}
				}

				$followIdArr    = [];
				$followIdDayArr = [];
				$where          = ['or'];
				foreach ($ruleArr as $rule) {
					$followId = $rule['follow_id'];
					if (!empty($rule['reclaim_day'])) {
						array_push($followIdArr, $followId);
						if ((empty($folId) || ($folId == $followId))) {
							$reclaimDay                = $rule['reclaim_day'];
							$day                       = $rule['day'] + $delayDay;
							$startTime                 = strtotime($nowDate) - ($day - $reclaimDay + 1) * 86400;//提醒开始时间
							$endTime                   = $startTime + 86400;//提醒结束时间
							$tempWhere                 = ['and', ['wf.follow_id' => $followId], ['between', 'wf.update_time', $startTime, $endTime]];
							$followIdDayArr[$followId] = $reclaimDay;
							array_push($where, $tempWhere);
						}
					}
				}
				//获取跟进列表
				$followData    = Follow::find()->where(['id' => $followIdArr])->select('id,title')->all();
				$followIdTitle = array_column($followData, 'title', 'id');

				$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

				//查询数据
				$followUser   = WorkExternalContactFollowUser::find()->alias('wf');
				$followUser   = $followUser->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$followUser   = $followUser->leftJoin('{{%wait_customer_task}} wt', 'wt.external_userid = wf.external_userid');
				$followUser   = $followUser->leftJoin('{{%wait_task}} t', 'wt.task_id=t.id');

				$followUser   = $followUser->where(['wf.user_id' => $userId, 'wf.is_reclaim' => 0, 'wf.is_protect' => 0, 'wf.del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				$followUser   = $followUser->andwhere($where);
				$followUser   = $followUser->andwhere(['or', ['wt.id' => NULL], ['wt.is_del' => 1], ['t.is_del' => 1]]);
				$followUser   = $followUser->groupBy('wf.id');
				$count        = $followUser->count();
				$offset       = ($page - 1) * $pageSize;
				$followUser   = $followUser->select('wf.id,wf.user_id,wf.follow_id,wf.external_userid,we.name,we.avatar,we.gender,we.corp_name,we.external_userid as externaluserid');

				$followUser   = $followUser->limit($pageSize)->offset($offset)->asArray()->all();
				$customerData = [];
				foreach ($followUser as $val) {
					$customerInfo                   = [];
					$customerInfo['id']             = $val['id'];
					$customerInfo['name']           = !empty($val['name']) ? rawurldecode($val['name']) : '';
					$customerInfo['avatar']         = $val['avatar'];
					$customerInfo['corp_name']      = $val['corp_name'];
					$customerInfo['userid']         = $workUser->userid;
					$customerInfo['externaluserid'] = $val['externaluserid'];
					//几天即将回收
					$customerInfo['reclaim_day'] = !empty($followIdDayArr[$val['follow_id']]) ? $followIdDayArr[$val['follow_id']] : '';
					$gender                      = '';
					if ($val['gender'] == 0) {
						$gender = '未知';
					} elseif ($val['gender'] == 1) {
						$gender = '男';
					} elseif ($val['gender'] == 2) {
						$gender = '女';
					}
					$fieldValue                   = CustomFieldValue::findOne(['type' => 1, 'cid' => $val['external_userid'], 'fieldid' => $fieldInfo->id]);
					$customerInfo['gender']       = !empty($fieldValue) ? $fieldValue->value : $gender;
					$customerInfo['follow_title'] = !empty($followIdTitle[$val['follow_id']]) ? $followIdTitle[$val['follow_id']] : '';
					array_push($customerData, $customerInfo);
				}
				//所属成员
				$workUser   = WorkUser::findOne($userId);
				$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$member     = $workUser->name . '--' . $departName;

				$reclaimDay = !empty($folId) ? $day : '';

				return ['count' => $count, 'reclaim_day' => $reclaimDay, 'member' => $member, 'customerData' => $customerData, 'followData' => $followData];

			} elseif ($remindType == 1) {//公海池客户
				$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

				$customerList = PublicSeaCustomer::find()->alias('sc');
				$customerList = $customerList->leftJoin('{{%work_external_contact_follow_user}} wf', 'sc.follow_user_id=wf.id');
				$customerList = $customerList->leftJoin('{{%work_external_contact}} we', 'we.id=sc.external_userid');
				$customerList = $customerList->where(['sc.corp_id' => $corpId, 'sc.user_id' => $userId, 'sc.type' => 1, 'sc.is_claim' => 0, 'wf.del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				if ($name !== '') {
					$customerList = $customerList->andWhere(['like', 'we.name_convert', $name]);
				}
				$customerList = $customerList->groupBy('sc.id');
				$count        = $customerList->count();
				$offset       = ($page - 1) * $pageSize;
				$customerList = $customerList->select('sc.id,sc.user_id,sc.follow_user_id,sc.reclaim_time,sc.reclaim_rule,we.name,we.avatar,we.corp_name,we.external_userid,wf.del_type,wf.add_way,wf.way_id,wf.baidu_way_id,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id');

				$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->asArray()->all();
				$customerData = [];
				foreach ($customerList as $key => $customer) {
					$customerData[$key]['id']             = $customer['id'];
					$customerData[$key]['user_id']        = $customer['user_id'];
					$customerData[$key]['externaluserid'] = $customer['external_userid'];
					$customerData[$key]['userid']         = $workUser->userid;
					$customerData[$key]['follow_user_id'] = $customer['follow_user_id'];
					//客户信息
					$customerData[$key]['name']      = rawurldecode($customer['name']);
					$customerData[$key]['avatar']    = $customer['avatar'];
					$customerData[$key]['corp_name'] = $customer['corp_name'];
					$fieldValue                      = CustomFieldValue::findOne(['type' => 1, 'cid' => $customer['external_userid'], 'fieldid' => $fieldInfo->id]);
					$customerData[$key]['gender']    = !empty($fieldValue) ? $fieldValue->value : '';
				}
				//所属成员
				$workUser   = WorkUser::findOne($userId);
				$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$member     = $workUser->name . '--' . $departName;

				return ['count' => $count, 'member' => $member, 'customerData' => $customerData];
			} elseif ($remindType == 2) {//已认领客户
				$customerList = PublicSeaClaimUser::find()->alias('scu');
				$customerList = $customerList->leftJoin('{{%work_external_contact_follow_user}} wf', 'scu.old_follow_user_id=wf.id');
				$customerList = $customerList->leftJoin('{{%work_external_contact}} we', 'we.id=scu.external_userid');
				$customerList = $customerList->where(['scu.corp_id' => $corpId, 'scu.old_user_id' => $userId, 'scu.new_follow_user_id' => 0, 'wf.del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				if ($name !== '') {
					$customerList = $customerList->andWhere(['like', 'we.name_convert', $name]);
				}
				$customerList = $customerList->groupBy('scu.id');
				$count        = $customerList->count();
				$offset       = ($page - 1) * $pageSize;
				$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['scu.id' => SORT_DESC])->all();
				$customerData = [];
				foreach ($customerList as $customer) {
					$customerInfo = $customer->dumpData();
					array_push($customerData, $customerInfo);
				}
				//所属成员
				$workUser   = WorkUser::findOne($userId);
				$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$member     = $workUser->name . '--' . $departName;

				return ['count' => $count, 'member' => $member, 'customerData' => $customerData];
			}

			return ['count' => 0, 'customerData' => [], 'followData' => []];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/chat-message/
		 * @title           非企微公海池
		 * @description     非企微公海池
		 * @method   post
		 * @url  http://{host_name}/api/chat-message/sea-no-customer
		 *
		 * @param user_id 必选 int 成员id
		 * @param remind_type 必选 int 通知类型：0待回收通知客户，1、公海池客户
		 * @param follow_id 可选 string 跟进状态id
		 * @param name 可选 string 搜索词
		 * @param page 可选 string 页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-11-18 20:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSeaNoCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$userId     = \Yii::$app->request->post('user_id', 0);
			$remindType = \Yii::$app->request->post('remind_type', 0);
			$name       = \Yii::$app->request->post('name', '');
			$folId      = \Yii::$app->request->post('follow_id', '');
			$page       = \Yii::$app->request->post('page', 1);
			$pageSize   = \Yii::$app->request->post('pageSize', 10);
			if (empty($userId)) {
				throw new InvalidDataException('缺少必要参数');
			}
			$workUser = WorkUser::findOne($userId);
			if (empty($workUser)) {
				throw new InvalidDataException('参数不正确');
			}
			$corpId = $workUser->corp_id;
			if ($remindType == 0) {
				$reclaim = PublicSeaReclaimSet::getClaimRule($workUser->corp_id, $userId);
				if (empty($reclaim)) {
					return ['count' => 0, 'customerData' => [], 'followData' => []];
				}
				$ruleArr  = json_decode($reclaim->reclaim_rule, 1);
				$delayDay = !empty($reclaim->is_delay) ? intval($reclaim->delay_day) : 0;
				$nowDate  = date('Y-m-d');
				//查询当前的跟进状态是否在规则里面
				if (!empty($folId)) {
					$followIdData = array_column($ruleArr, 'follow_id');
					if (!in_array($folId, $followIdData)) {
						$folId = 0;
					}
				}

				$followIdArr    = [];
				$followIdDayArr = [];
				$where          = ['or'];
				foreach ($ruleArr as $rule) {
					$followId = $rule['follow_id'];
					if (!empty($rule['reclaim_day'])) {
						array_push($followIdArr, $followId);
						if ((empty($folId) || ($folId == $followId))) {
							$reclaimDay                = $rule['reclaim_day'];
							$day                       = $rule['day'] + $delayDay;
							$startTime                 = strtotime($nowDate) - ($day - $reclaimDay + 1) * 86400;//提醒开始时间
							$endTime                   = $startTime + 86400;//提醒结束时间
							$tempWhere                 = ['and', ['fu.follow_id' => $followId], ['between', 'fu.last_follow_time', $startTime, $endTime]];
							$followIdDayArr[$followId] = $reclaimDay;
							array_push($where, $tempWhere);
						}
					}
				}
				//获取跟进列表
				$followData    = Follow::find()->where(['id' => $followIdArr])->select('id,title')->all();
				$followIdTitle = array_column($followData, 'title', 'id');

				$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

				$followUser = PublicSeaContactFollowUser::find()->alias('fu');
				$followUser = $followUser->leftJoin('{{%public_sea_customer}} sc', 'fu.sea_id=sc.id');
				$followUser = $followUser->leftJoin('{{%wait_customer_task}} wt', 'wt.sea_id = fu.sea_id');
				$followUser = $followUser->leftJoin('{{%wait_task}} t', 'wt.task_id=t.id');
				$followUser = $followUser->where(['fu.corp_id' => $corpId, 'fu.is_reclaim' => 0, 'fu.is_protect' => 0, 'fu.user_id' => $userId]);
				$followUser = $followUser->andwhere($where);
				$followUser = $followUser->andwhere(['or', ['wt.id' => NULL], ['wt.is_del' => 1], ['t.is_del' => 1]]);
				if ($name !== '') {
					$followUser = $followUser->andWhere(['like', 'sc.name', $name]);
				}
				$followUser   = $followUser->groupBy('fu.id');
				$count        = $followUser->count();
				$offset       = ($page - 1) * $pageSize;
				$followUser   = $followUser->select('fu.id,fu.sea_id,fu.follow_id,sc.name');
				$followUser   = $followUser->limit($pageSize)->offset($offset)->orderBy(['fu.add_time' => SORT_DESC])->asArray()->all();
				$customerData = [];
				foreach ($followUser as $val) {
					$customerInfo           = [];
					$customerInfo['id']     = $val['id'];
					$customerInfo['sea_id'] = $val['sea_id'];
					$customerInfo['name']   = $val['name'];
					//几天即将回收
					$customerInfo['reclaim_day'] = !empty($followIdDayArr[$val['follow_id']]) ? $followIdDayArr[$val['follow_id']] : '';
					//性别
					$fieldValue                   = CustomFieldValue::findOne(['type' => 4, 'cid' => $val['sea_id'], 'fieldid' => $fieldInfo->id]);
					$customerInfo['gender']       = !empty($fieldValue) ? $fieldValue->value : '未知';
					$customerInfo['follow_title'] = !empty($followIdTitle[$val['follow_id']]) ? $followIdTitle[$val['follow_id']] : '';
					array_push($customerData, $customerInfo);
				}
				//所属成员
				$workUser   = WorkUser::findOne($userId);
				$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$member     = $workUser->name . '--' . $departName;
				$reclaimDay = !empty($folId) ? $day : '';

				return ['count' => $count, 'reclaim_day' => $reclaimDay, 'member' => $member, 'customerData' => $customerData, 'followData' => $followData];

			} elseif ($remindType == 1) {
				$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

				$customerList = PublicSeaCustomer::find()->alias('sc');
				$customerList = $customerList->where(['sc.user_id' => $userId, 'sc.is_claim' => 0, 'sc.type' => 0]);
				if ($name !== '') {
					$customerList = $customerList->andWhere(['like', 'sc.name', $name]);
				}
				$customerList = $customerList->groupBy('sc.id');
				$count        = $customerList->count();
				$offset       = ($page - 1) * $pageSize;
				$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->all();
				$customerData = [];
				foreach ($customerList as $key => $customer) {
					$customerData[$key]['id']     = $customer['id'];
					$customerData[$key]['name']   = $customer['name'];
					$fieldValue                   = CustomFieldValue::findOne(['type' => 4, 'cid' => $customer['id'], 'fieldid' => $fieldInfo->id]);
					$customerData[$key]['gender'] = !empty($fieldValue) ? $fieldValue->value : '未知';
				}

				//所属成员
				$workUser   = WorkUser::findOne($userId);
				$departName = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$member     = $workUser->name . '--' . $departName;

				return ['count' => $count, 'member' => $member, 'customerData' => $customerData];
			}
		}
	}