<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 15:32
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkCorpAuth;
	use app\models\WorkDepartment;
	use app\models\WorkTag;
	use app\models\WorkTagContact;
	use app\models\WorkTagUser;
	use app\models\WorkUser;
	use app\models\WorkUserAuthorRelation;
	use app\models\WorkUserStatistic;
	use app\models\WxAuthorize;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;
	use yii\db\Expression;

	class WorkAuthorController extends WorkBaseController
	{
		/**
		 * @inheritDoc
		 *
		 * @param \yii\base\Action $action
		 *
		 * @return bool
		 *
		 * @throws \app\components\InvalidParameterException
		 * @throws \yii\web\BadRequestHttpException
		 */
		public function beforeAction ($action)
		{
			return parent::beforeAction($action);
		}

		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$result = [];

				if (!empty($this->corp->workUserAuthorRelations)) {
					foreach ($this->corp->workUserAuthorRelations as $workUserAuthorRelation) {
						$relation = [
							'key'         => $workUserAuthorRelation->id,
							'user'        => $workUserAuthorRelation->user->dumpData(),
							'author'      => $workUserAuthorRelation->author->wxAuthorizeInfo->dumpData(),
							'agent'       => $workUserAuthorRelation->agent->dumpData(),
							'status'      => $workUserAuthorRelation->status,
							'create_time' => $workUserAuthorRelation->create_time,
						];

						array_push($result, $relation);
					}
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		public function actionChange ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$relationId = \Yii::$app->request->post('relation_id', 0);
				if (empty($relationId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$status = \Yii::$app->request->post('status', NULL);
				if (is_null($status)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$upStatus = WorkUserAuthorRelation::updateAll(['status' => $status], ['id' => $relationId, 'corp_id' => $this->corp->id]);

				if (!$upStatus) {
					return false;
				} else {
					return true;
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		public function actionDel ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$relationId = \Yii::$app->request->post('relation_id', 0);
				if (empty($relationId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$rowNum = WorkUserAuthorRelation::deleteAll(['id' => $relationId, 'corp_id' => $this->corp->id]);

				if ($rowNum == 0) {
					throw new NotAllowException('非法操作！');
				} else {
					return true;
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		public function actionSet ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$userId   = \Yii::$app->request->post('user_id', 0);
				$authorId = \Yii::$app->request->post('author_id', 0);
				$agentId  = \Yii::$app->request->post('agent_id', 0);
				if (empty($userId) || empty($authorId) || empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty(WorkUser::findOne(['id' => $userId, 'corp_id' => $this->corp->id]))) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty(WorkCorpAgent::findOne(['id' => $agentId, 'corp_id' => $this->corp->id]))) {
					throw new InvalidParameterException('参数不正确！');
				}

				$wxAuthor = WxAuthorize::findOne(['author_id' => $authorId]);
				if (empty($wxAuthor) || $wxAuthor->userAuthorRelations[0]->uid != $this->user->uid) {
					throw new InvalidParameterException('参数不正确！');
				}

				$workUserAuthorRelation = WorkUserAuthorRelation::findOne(['corp_id' => $this->corp->id, 'user_id' => $userId, 'author_id' => $authorId, 'agent_id' => $agentId]);
				if (!empty($workUserAuthorRelation)) {
					throw new NotAllowException('该配置已经存在！');
				}

				$workUserAuthorRelation              = new WorkUserAuthorRelation();
				$workUserAuthorRelation->corp_id     = $this->corp->id;
				$workUserAuthorRelation->user_id     = $userId;
				$workUserAuthorRelation->author_id   = $authorId;
				$workUserAuthorRelation->agent_id    = $agentId;
				$workUserAuthorRelation->status      = WorkUserAuthorRelation::SEND_OPEN;
				$workUserAuthorRelation->create_time = DateUtil::getCurrentTime();

				if (!$workUserAuthorRelation->validate() || !$workUserAuthorRelation->save()) {
					throw new InvalidDataException(SUtils::modelError($workUserAuthorRelation));
				}

				return [
					'key'         => $workUserAuthorRelation->id,
					'user'        => $workUserAuthorRelation->user->dumpData(),
					'author'      => $workUserAuthorRelation->author->wxAuthorizeInfo->dumpData(),
					'agent'       => $workUserAuthorRelation->agent->dumpData(),
					'status'      => $workUserAuthorRelation->status,
					'create_time' => $workUserAuthorRelation->create_time,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}