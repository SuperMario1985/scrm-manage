<?php

	namespace app\modules\msg\controllers;

	use app\components\InvalidParameterException;
	use app\models\WorkCorp;
	use app\models\WorkMsgAudit;
	use app\modules\msg\controllers\components\BaseController;
	use app\queue\MsgAuditSetJob;
	use app\util\DateUtil;

	/**
	 * Default controller for the `msg` module
	 */
	class IndexController extends BaseController
	{
		public function actionGetList ()
		{
			$result  = [];
			$auditId = \Yii::$app->request->get('id', '');

			if ($auditId == 'all') {
				$msgAudits = WorkMsgAudit::findAll(['status' => WorkMsgAudit::MSG_AUDIT_OPEN]);

				if (!empty($msgAudits)) {
					foreach ($msgAudits as $msgAudit) {
						array_push($result, $msgAudit->dumpData(true, true));
					}
				}
			} elseif ((int) $auditId != 0) {
				$msgAudit = WorkMsgAudit::findOne($auditId);
				if (!empty($msgAudit)) {
					$result = $msgAudit->dumpData(true, true);
				}
			}

			return $result;
		}

		public function actionSet ()
		{
			$corpId  = \Yii::$app->request->post('corpid', '');
			$seq     = \Yii::$app->request->post('seq', 0);
			$msgData = \Yii::$app->request->post('msg_data', '');

			if (empty($corpId) || empty($msgData)) {
				throw new InvalidParameterException('参数不正确');
			}

			$workCorp = WorkCorp::findOne(['corpid' => $corpId]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('参数不正确');
			}

			$msgDate = DateUtil::getFormattedYMD(explode('_', $msgData['msgid'])[1]);
			if (empty($msgDate)) {
				$msgDate = DateUtil::getCurrentYMD();
			}
			$uid = $workCorp->userCorpRelations[0]->uid;

			$saveDir  = "/msg-audit/${uid}/${msgDate}/";
			$savePath = \Yii::getAlias("@upload") . $saveDir;

			if (!is_dir($savePath) && !mkdir($savePath, 0755, true)) {
				\Yii::error([
					'uid'     => $uid,
					'msgId'   => $msgData['msgid'],
					'msgDate' => $msgDate,
					'saveDir' => $saveDir,
					'error'   => 1,
					'msg'     => '无法创建目录'
				]);
			}

			$jobId = \Yii::$app->msg->push(new MsgAuditSetJob([
				'corp_id'  => $workCorp->id,
				'seq'      => $seq,
				'msg_data' => $msgData,
			]));

			return ['job_id' => $jobId];
		}

		public function actionError ()
		{
			$corpId    = \Yii::$app->request->post('corpid', '');
			$errorCode = \Yii::$app->request->post('error_code', '');

			if (empty($corpId) || empty($errorCode)) {
				throw new InvalidParameterException('参数不正确');
			}

			$workCorp = WorkCorp::findOne(['corpid' => $corpId]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('参数不正确');
			}

			if (empty($workCorp->workMsgAudit)) {
				throw new InvalidParameterException('参数不正确');
			}

			switch ($errorCode) {
				case 301042:
				case 301052:
				case 41001:
					$workCorp->workMsgAudit->status = 0;
					$workCorp->workMsgAudit->save();
					break;
				default:
					break;
			}

			\Yii::error($corpId, '$corpId');
			\Yii::error($errorCode, '$errorCode');

			return true;
		}
	}
