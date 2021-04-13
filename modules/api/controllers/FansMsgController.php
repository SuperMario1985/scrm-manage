<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/10/11
	 * Time: 20:24
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\Attachment;
	use app\models\FansMsg;
	use app\models\Material;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class FansMsgController extends AuthBaseController
	{
		/**
		 * {@inheritDoc}
		 */
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'get-msg-list' => ['POST'],
						'send'         => ['POST'],
						'msg-read'     => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-msg/
		 * @title           获取消息列表
		 * @description     获取消息列表
		 * @method   post
		 * @url  http://{host_name}/api/fans-msg/get-msg-list
		 *
		 * @param wx_id 必选 string 公众号ID
		 * @param fans_id 必选 int 粉丝ID
		 * @param last_id 可选 int 最后消息的msgId
		 * @param msg_size 可选 int 每次获取的消息数，默认15
		 *
		 * @return          {"error":0,"data":{"fans_id":186,"wx_id":"gh_d039d3877b93","total":"80","count":15,"msg_list":[{"id":124,"is_read":1,"from":{"type":2,"data":{"alias":"","user_name":"gh_d039d3877b93","nick_name":"彼岸智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/vSgib1JZE1myUFgFic1R8x0hxt3OLFyNRKw09O6r4skpw3MkszKrNe0olL1Feqic2ZtQaasXGXw8aNf7ygJESlHn1nyib29OOn3A/0"}},"to":{"type":1,"data":{"fans_id":89,"openid":"ogw9MwziPRomyJkQ7bmMq5_mP_Vw","nickname":"Dove_Chen","remark":"","headerimg":"http://thirdwx.qlogo.cn/mmopen/SqI4bDPk5Q9eoJtoia1lx0yvohSpnqlU4IDd7KDjODIbfc6pBuMGu3qicQ6lib07VzyVkBa3mib5zmSD2qMPvyO0RnpqBzTzUu9p/132"}},"content":"特温特我","type":1,"create_time":"2019-10-12 19:10:43"},{"loop":"……"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    fans_id int  粉丝ID
		 * @return_param    wx_id string  公众号ID
		 * @return_param    total string  消息总数
		 * @return_param    count int 当前消息数
		 * @return_param    msg_list array 消息列表
		 * @return_param    id string 消息ID
		 * @return_param    is_read int 是否已读
		 * @return_param    from array 发送方信息
		 * @return_param    type int 发送方类型，1：粉丝、2：用户、3：客服
		 * @return_param    data array 发送方详细信息
		 * @return_param    alias string 微信号
		 * @return_param    user_name string 公众号ID
		 * @return_param    nick_name string 昵称
		 * @return_param    head_img string 头像
		 * @return_param    to array 接收方信息
		 * @return_param    type int 接收方类型，1：粉丝、2：用户、3：客服
		 * @return_param    data array 接收方详细信息
		 * @return_param    fans_id int 粉丝ID
		 * @return_param    openid string 粉丝openid
		 * @return_param    nickname string 昵称
		 * @return_param    remark string 备注
		 * @return_param    headerimg string 头像
		 * @return_param    content string 消息内容
		 * @return_param    type int 消息类型，1：文本、2：图片、3：音频、4：视频、5、图文、6：音乐
		 * @return_param    create_time string 消息时间
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/12 19:53
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetMsgList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$fansId = \Yii::$app->request->post('fans_id');
				if (empty($fansId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$lastId  = \Yii::$app->request->post('last_id') ?: 0;
				$msgSize = \Yii::$app->request->post('msg_size') ?: 15;

				$msgList          = FansMsg::getMsgList($fansId, $lastId, $msgSize);
				$msgList['wx_id'] = $this->wxAuthorInfo->user_name;

				return $msgList;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-msg/
		 * @title           粉丝对话发送
		 * @description     粉丝对话发送
		 * @method   post
		 * @url  http://{host_name}/api/fans-msg/send
		 *
		 * @param wx_id 必选 string 公众号ID
		 * @param fans_id 必选 int 粉丝ID
		 * @param from 必选 int 发送方，2：用户、3：客服
		 * @param msg_type 必选 int 消息类型，1：文本、2：图片、3：音频、4：视频、5、图文、6：音乐、7小程序
		 * @param msg_content 必选 array 回复消息体
		 * @param text 可选 string 文本信息的内容
		 * @param media_id 可选 int 非文本信息的素材ID
		 * @param thumb_media_id 可选 int 视频消息的缩略图素材ID
		 * @param title 可选 string 视频和音乐消息的标题
		 * @param description 可选 int 视频和音乐消息的描述
		 * @param music_url 可选 string 音乐消息的音乐链接
		 * @param hq_music_url 可选 string 音乐消息的高质量音乐链接，WIFI环境优先使用该链接播放音乐
		 *
		 * @return          {"error":0,"data":{"status":true}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    status boolean 发送结果
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/12 20:37
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \app\components\ForbiddenException
		 * @throws \app\components\InvalidDataException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionSend ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				if (empty($postData)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$msgType = $postData['msg_type'];
				if (empty($msgType)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$fansId     = $postData['fans_id'];
				$from       = $postData['from'] ?: FansMsg::FROM_USER;
				$msgContent = $postData['msg_content'];
				//改版，现在传过来的media_id是附件的id，需要转换
				if ($msgType != 1) {
					$attachment                  = Attachment::findOne($msgContent['media_id']);
					$msgContent['attachment_id'] = $msgContent['media_id'];
					$author_id                   = $this->wxAuthorInfo->author_id;
					if (!empty($attachment->material_id) && $attachment->material->author_id == $author_id) {
						$msgContent['media_id'] = $attachment->material_id;
					} else {
						//$material = Material::findOne(['author_id' => $author_id, 'attachment_id' => $attachment->id]);
						$material = Material::getMaterial(['author_id' => $author_id, 'attachment_id' => $attachment->id, 'file_type' => $attachment->file_type]);
						if ($msgType == 2 || $msgType == 3) {
							//图片 音频
							$msgContent['media_id'] = $material->id;
						} elseif ($msgType == 4) {
							//视频
							$msgContent['media_id'] = $material->id;
							$msgContent['title']    = $attachment->file_name;
						} elseif ($msgType == 5) {
							$msgContent['media_id']    = '';
							$msgContent['title']       = $attachment->file_name;
							$msgContent['description'] = $attachment->content;
							$msgContent['url']         = $attachment->jump_url;
							$site_url                  = \Yii::$app->params['site_url'];
							$msgContent['pic_url']     = $site_url . $attachment->local_path;
						} elseif ($msgType == 10) {
							//小程序
							$msgContent['media_id'] = $material->id;
							$msgContent['title']    = $msgContent['title'];
							$msgContent['appid']    = $msgContent['appid'];
							$msgContent['pagepath'] = $msgContent['pagepath'];
						}
					}
				}
				\Yii::error($msgContent,'$msgContent');
				$sendResult = FansMsg::send($this->wxAuthorInfo->authorizer_appid, $fansId, $msgType, $msgContent, $from);

				if (is_array($sendResult)) {
					$result = [
						'status'     => false,
						'error_info' => $sendResult,
					];
				} else {
					$result = [
						'status' => $sendResult,
					];
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans-msg/
		 * @title           修改粉丝消息的阅读状态
		 * @description     根据粉丝ID，将该粉丝的未读消息变更为已读
		 * @method   post
		 * @url  http://{host_name}/api/fans-msg/msg-read
		 *
		 * @param wx_id 必选 string 公众号ID
		 * @param fans_id 必选 int 粉丝ID
		 *
		 * @return          {"error":0,"data":{"status":true}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    status boolean 执行结果
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/31 16:47
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMsgRead ()
		{
			if (\Yii::$app->request->isPost) {
				$fansId = \Yii::$app->request->post('fans_id') ?: 0;

				if (empty($fansId)) {
					throw new InvalidParameterException('请求参数不正确！');
				}

				$result = [
					'status' => FansMsg::readMsgByFansId($fansId),
				];

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}