<?php

	namespace app\modules\admin\controllers;

	use app\models\MessagePack;
	use app\models\MessageSign;
	use app\models\MessageTemplate;
	use app\models\MessageType;
	use app\models\User;
	use app\modules\admin\components\BaseController;
	use app\util\SUtils;
	use yii\data\Pagination;
	use app\components\InvalidDataException;
	use yii\db\Expression;

	class ShortMessageController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;
		}

		//系统模版
		public function actionTemplate ()
		{
			if (\Yii::$app->request->isGet) {
				$type_id  = \Yii::$app->request->get('type_id', '');
				$template = MessageTemplate::find()->where(['status' => 1, 'uid' => NULL]);
				if (!empty($type_id)) {
					$template = $template->andWhere(['type_id' => $type_id]);
				}
				$count    = $template->count();
				$pages    = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
				$template = $template->offset($pages->offset)->limit($pages->limit)->orderBy('id desc')->all();

				$typeAll = MessageType::find()->all();
				$idTitle = [];
				if (!empty($typeAll)) {
					$idTitle = array_column($typeAll, 'title', 'id');
				}
				//短信类型
				$typeArr = MessageType::find()->where(['status' => 1])->all();

				return $this->render('template', ['template' => $template, 'pages' => $pages, 'typeArr' => $typeArr, 'idTitle' => $idTitle, 'type_id' => $type_id]);
			} else {
				$postData = \Yii::$app->request->post();
				try {
					$result = MessageTemplate::setTemplate($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			}
		}

		//用户模版审核
		public function actionUserTemplate ()
		{
			$uid      = \Yii::$app->request->get('uid', 0);
			$sid      = \Yii::$app->request->get('sid', 0);
			$tid      = \Yii::$app->request->get('tid', 0);
			$status   = \Yii::$app->request->get('status', '-1');
			$dates    = \Yii::$app->request->get('dates', '');
			$template = MessageTemplate::find()->alias('t');
			$template = $template->leftJoin('{{%user}} u', 't.uid = u.uid');
			$template = $template->where(['t.status' => [0, 1, 2]]);
			$template = $template->where(['>', 't.uid', '0']);
			//账户
			if (!empty($uid)) {
				$template = $template->andWhere(['u.uid' => $uid]);
			}
			//签名
			if (!empty($sid)) {
				$template = $template->andWhere(['t.sign_id' => $sid]);
			}
			//短信类型
			if (!empty($tid)) {
				$template = $template->andWhere(['t.type_id' => $tid]);
			}
			//状态
			if ($status != '-1') {
				$template = $template->andWhere(['t.status' => $status]);
			}
			//申请时间
			if (!empty($dates)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';
				$template   = $template->andWhere(['between', 'apply_time', $start_date, $end_date]);
			}
			$template = $template->select('t.*,u.account');
			$count    = $template->count();
			$pages    = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$template = $template->offset($pages->offset)->limit($pages->limit)->orderBy('t.id desc')->asArray()->all();
			//账户
			$userArr = User::find()->select('uid,account')->all();
			//短信签名
			$signData = MessageSign::find()->where(['status' => 1])->select('id,title')->all();
			$signArr  = [];
			if (!empty($signData)) {
				$signArr = array_column($signData, 'title', 'id');
			}
			//短信类型
			$typeData = MessageType::find()->select('id,title')->all();
			$typeArr  = [];
			if (!empty($typeData)) {
				$typeArr = array_column($typeData, 'title', 'id');
			}

			return $this->render('userTemplate', ['userArr' => $userArr, 'signArr' => $signArr, 'typeArr' => $typeArr, 'templateArr' => $template, 'pages' => $pages, 'uid' => $uid, 'sid' => $sid, 'tid' => $tid, 'status' => $status, 'dates' => $dates]);
		}

		//模版状态
		public function actionTemplateStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id');
				$status    = \Yii::$app->request->post('status');
				$error_msg = \Yii::$app->request->post('error_msg', '');
				if (empty($id)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$error_msg = trim($error_msg);
				if ($status == 2 && empty($error_msg)) {
					$this->dexit(['error' => 1, 'msg' => '请填写失败原因']);
				}
				if($status == 1){
					$error_msg = '';
				}
				$template            = MessageTemplate::findOne($id);
				$template->status    = $status;
				$template->error_msg = $error_msg;
				if (!$template->save()) {
					$this->dexit(['error' => 1, 'msg' => SUtils::modelError($template)]);
				}
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//签名审核管理
		public function actionSign ()
		{
			$uid      = \Yii::$app->request->get('uid', 0);
			$status   = \Yii::$app->request->get('status', '-1');
			$sid      = \Yii::$app->request->get('sid', 0);
			$dates    = \Yii::$app->request->get('dates', '');
			$signData = MessageSign::find()->alias('s');
			$signData = $signData->leftJoin('{{%user}} u', 's.uid = u.uid');
			$signData = $signData->where(['s.status' => [0, 1, 2]]);
			if (!empty($uid)) {
				$signData = $signData->andWhere(['u.uid' => $uid]);
			}
			if ($status != '-1') {
				$signData = $signData->andWhere(['s.status' => $status]);
			}
			if (!empty($sid)) {
				$signData = $signData->andWhere(['s.id' => $sid]);
			}
			if (!empty($dates)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';
				$signData   = $signData->andWhere(['between', 'apply_time', $start_date, $end_date]);
			}
			$signData = $signData->select('s.*,u.account');
			$count    = $signData->count();
			$pages    = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$signData = $signData->offset($pages->offset)->limit($pages->limit)->orderBy('s.id desc')->asArray()->all();
			//账户
			$userArr = User::find()->select('uid,account')->all();
			//短信签名
			$titleArr = MessageSign::find()->where(['status' => [0, 1, 2]])->select('id,title')->all();

			return $this->render('sign', ['userArr' => $userArr, 'titleArr' => $titleArr, 'signArr' => $signData, 'pages' => $pages, 'uid' => $uid, 'status' => $status, 'sid' => $sid, 'dates' => $dates]);
		}

		//签名状态改变
		public function actionSignStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id');
				$status    = \Yii::$app->request->post('status');
				$error_msg = \Yii::$app->request->post('error_msg', '');
				if (empty($id)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$error_msg = trim($error_msg);
				if ($status == 2 && empty($error_msg)) {
					$this->dexit(['error' => 1, 'msg' => '请填写失败原因']);
				}
				$sign            = MessageSign::findOne($id);
				$sign->status    = $status;
				$sign->error_msg = $error_msg;
				if (!$sign->save()) {
					$this->dexit(['error' => 1, 'msg' => SUtils::modelError($sign)]);
				}
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//短信包管理
		public function actionPack ()
		{
			if (\Yii::$app->request->isGet) {
				$dates   = \Yii::$app->request->get('dates', '');
				$onWhere = 'p.id = o.goods_id and o.ispay=1 and o.goods_type=\'messagePay\'';
				if (!empty($dates)) {
					$dateArr    = explode(' - ', $dates);
					$start_time = strtotime($dateArr[0]);
					$end_time   = strtotime($dateArr[1] . ' 23:59:59');
					$onWhere    .= ' and o.paytime between ' . $start_time . ' and ' . $end_time;
				}

				$packData = MessagePack::find()->alias('p');
				$packData = $packData->leftJoin('{{%message_order}} o', $onWhere);
				$packData = $packData->where(['p.status' => 1]);
				$select   = new Expression('p.id,p.num,p.price,count(o.id) times,sum(o.goods_price) prices');
				$packData = $packData->select($select)->groupBy('p.id')->asArray()->all();

				return $this->render('pack', ['packArr' => $packData, 'dates' => $dates]);
			} else {
				$postData = \Yii::$app->request->post();
				try {
					$result = MessagePack::setPack($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			}
		}

		//短信类型
		public function actionType ()
		{
			if (\Yii::$app->request->isGet) {
				$typeArr = MessageType::find()->all();

				return $this->render('type', ['typeArr' => $typeArr]);
			} else {
				$postData = \Yii::$app->request->post();
				$title    = \Yii::$app->request->post('title', '');
				if (empty($title)) {
					$this->dexit(['error' => 1, 'msg' => '类型名称不能为空']);
				} elseif (mb_strlen($title, 'utf-8') > 20) {
					$this->dexit(['error' => 1, 'msg' => '类型名称不能超过20个字符']);
				}
				try {
					$result = MessageType::setType($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			}
		}
	}