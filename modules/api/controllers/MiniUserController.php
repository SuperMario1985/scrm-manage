<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/9/16
	 * Time: 11:38
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\Authority;
	use app\models\MiniUser;
	use app\models\WxAuthorize;
	use app\models\SubUserAuthority;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class MiniUserController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'msg-list'   => ['POST'],
						'set-remark' => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/mini-user/
		 * @title           消息列表接口
		 * @description     消息列表接口
		 * @method   post
		 * @url  http://{host_name}/api/fans/msg-list
		 *
		 * @param           * * * *
		 *
		 * @return          {"error":0,"data":[{"id":"gh_5829cbdda7fb","avatar":"https://wx.qlogo.cn/mmopen/1THKz7DTmC1XKaIb/0","name":"小猪娱乐慧店铺","mini_list":[{"mini_id":1,"openid":"ovLgU0bHl5XarjVw","remark":null,"unionid":null,"last_time":null,"last_content":[{"id":7,"is_read":0,"from":{"type":1,"data":{"mini_id":1,"openid":"ovLgU0bHl5XarjVw","remark":null}},"to":{"type":2,"data":{"alias":"","user_name":"gh_582da7fb","nick_name":"小猪娱乐慧店铺","head_img":"https://wx.qlogo.cn/mmopen/1THKz7DTmC1XKaIb/0"}},"content":"统计局","type":1,"create_time":"2020-04-19 14:49:05"}]},"loop"]},"loop"]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 公众号唯一ID
		 * @return_param    avatar string 公众号头像
		 * @return_param    name string 公众号名称
		 * @return_param    mini_list array 活跃用户列表
		 * @return_param    mini_id int 用户ID
		 * @return_param    openid string 用户唯一ID
		 * @return_param    remark string 备注
		 * @return_param    unionid string 用户统一ID
		 * @return_param    last_time string 最后一条的消息时间
		 * @return_param    last_content data 最后一条的消息信息
		 * @return_param    id string 消息ID
		 * @return_param    is_read int 是否已读
		 * @return_param    from array 发送方信息
		 * @return_param    type int 发送方类型，1：小程序、2：用户、3：客服
		 * @return_param    data array 发送方详细信息
		 * @return_param    mini int 小程序用户ID
		 * @return_param    openid string 粉丝openid
		 * @return_param    remark string 备注
		 * @return_param    to array 接收方信息
		 * @return_param    type int 接收方类型，1：小程序、2：用户、3：客服
		 * @return_param    data array 接收方详细信息
		 * @return_param    alias string 微信号
		 * @return_param    user_name string 小程序ID
		 * @return_param    nick_name string 昵称
		 * @return_param    head_img string 头像
		 * @return_param    content string 消息内容
		 * @return_param    type int 消息类型，1：文本、2：图片、3：小程序
		 * @return_param    create_time string 消息时间
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/12 14:30
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMsgList ()
		{
			if (\Yii::$app->request->isPost) {
				$result       = [];
				$miniAuthorId = [];//当前公众号的author_id
				$subId        = \Yii::$app->request->post('sub_id');
				if (!empty($subId)) {
					$subAuth = SubUserAuthority::find()->andWhere(['sub_user_id' => $subId, 'type' => 1])->andWhere(['<>', 'authority_ids', ''])->asArray()->all();
					if (!empty($subAuth)) {
						foreach ($subAuth as $auth) {
							$authorityIds = explode(',', $auth['authority_ids']);
							$routes       = Authority::find()->where(['in', 'id', $authorityIds])->asArray()->all();
							$routes       = array_column($routes, 'route');
							if (in_array('miniMsg', $routes)) {
								array_push($miniAuthorId, $auth['wx_id']);
							}
						}
					}
				}
				$userAuthorRelations = $this->user->userAuthorRelations;
				if (!empty($userAuthorRelations)) {
					foreach ($userAuthorRelations as $relation) {
						if ($relation->author->authorizer_type != WxAuthorize::AUTH_TYPE_UNAUTH && $relation->author->auth_type == WxAuthorize::AUTH_TYPE_MINI_APP) {
							$author = $relation->author->wxAuthorizeInfo;
							if (!empty($miniAuthorId) && !in_array($relation->author_id, $miniAuthorId)) {
								continue;
							}
							$data              = [];
							$data['id']        = $author->user_name;
							$data['avatar']    = $author->head_img;
							$data['name']      = $author->nick_name;
							$data['mini_list'] = MiniUser::getActiveUsers($relation->author->author_id);

							array_push($result, $data);
						}
					}
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/mini-user/
		 * @title           修改小程序用户备注
		 * @description     修改小程序用户的备注信息
		 * @method   POST
		 * @url  http://{host_name}/api/mini-user/set-remark
		 *
		 * @param wx_id 必选 string 小程序唯一ID
		 * @param mini_id 必选 int 小程序用户ID
		 * @param remark 可选 string 备注信息
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/12/6 15:08
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetRemark ()
		{
			if (\Yii::$app->request->isPost) {
				$miniId = \Yii::$app->request->post('mini_id');
				$remark = \Yii::$app->request->post('remark');
				if (empty($miniId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				try {
					MiniUser::modifyFansRemark($miniId, $remark);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return true;

			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}

		}

	}