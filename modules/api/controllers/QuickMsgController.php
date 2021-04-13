<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/12/03
	 * Time: 15:33
	 */

	namespace app\modules\api\controllers;

	use app\components\NotAllowException;
	use app\models\QuickMsg;
	use app\models\WxAuthorizeInfo;
	use app\modules\api\components\AuthBaseController;
	use yii\base\InvalidParamException;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class QuickMsgController extends AuthBaseController
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
						'get'    => ['POST'],
						'create' => ['POST'],
						'delete' => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/quick-msg/
		 * @title           获取快捷回复
		 * @description     获取商家的所有快捷回复的接口
		 * @method   POST
		 * @url  http://{host_name}/api/quick-msg/get
		 *
		 * @param           * * * *
		 *
		 * @return          {"error":0,"data":[{"id":1,"uid":2,"wx_id":"","content":"STORY😊"},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 快捷回复编号
		 * @return_param    uid int 商家ID
		 * @return_param    wx_id string 公众号唯一标识
		 * @return_param    content int 快捷回复内容
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/12/3 19:56
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionGet ()
		{
			if (\Yii::$app->request->isPost) {
				return QuickMsg::get($this->user->uid);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/quick-msg/
		 * @title           保存快捷回复
		 * @description     创建或者修改快捷回复
		 * @method   POST
		 * @url  http://{host_name}/api/quick-msg/save
		 *
		 * @param content 必选 string 快捷回复内容
		 * @param q_id 可选 int 快捷回复的ID（修改时必选）
		 * @param q_wx_id 可选 string 快捷回复的公众号原始ID
		 *
		 * @return          {"error":0,"data":{"id":1,"uid":2,"wx_id":null,"content":"STORY😊"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 快捷回复编号
		 * @return_param    uid int 商家ID
		 * @return_param    wx_id string 公众号唯一标识
		 * @return_param    content int 快捷回复内容
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/12/3 20:09
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionSave ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				if (empty($postData)) {
					throw new InvalidParamException('参数不正确！');
				}

				$authorId = 0;
				$wxId     = !empty($postData['q_wx_id']) ? $postData['q_wx_id'] : '';
				$qid      = !empty($postData['q_id']) ? $postData['q_id'] : 0;
				$content  = !empty($postData['content']) ? $postData['content'] : '';

				if (empty($content)) {
					throw new InvalidParamException('参数不正确！');
				}

				if (!empty($qid)) {
					$quickInfo = QuickMsg::findOne(['id' => $qid, 'uid' => $this->user->uid]);

					if (empty($quickInfo)) {
						throw new NotAllowException('非法操作！');
					}
				}

				if (!empty($wxId)) {
					$authorInfo = WxAuthorizeInfo::findOne(['user_name' => $wxId]);

					if (empty($authorInfo)) {
						throw new InvalidParamException('参数不正确！');
					}

					$authorId = $authorInfo->author->author_id;
				}

				$quickData = [
					'uid'     => $this->user->uid,
					'content' => rtrim($content),//去除尾部空格换行
				];

				if ($authorId != 0) {
					$quickData['author_id'] = $authorId;
				}

				return QuickMsg::create($quickData, $qid);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/quick-msg/
		 * @title           删除快捷回复
		 * @description     删除快捷回复
		 * @method   POST
		 * @url  http://{host_name}/api/quick-msg/delete
		 *
		 * @param q_id 必选 int 快捷回复ID
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/12/4 20:22
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				if (empty($postData)) {
					throw new InvalidParamException('参数不正确！');
				}

				$qid = !empty($postData['q_id']) ? $postData['q_id'] : 0;
				if (empty($qid)) {
					throw new InvalidParamException('参数不正确！');
				}

				$rowNum = QuickMsg::deleteAll(['id' => $qid, 'uid' => $this->user->uid]);

				if ($rowNum == 0) {
					throw new NotAllowException('非法操作！');
				} else {
					return true;
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}