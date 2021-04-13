<?php

	namespace app\modules\admin\controllers;

	use app\models\MessageOrder;
	use app\models\MessagePack;
	use app\models\Package;
	use app\models\User;
	use app\modules\admin\components\BaseController;
	use app\util\SUtils;
	use yii\data\Pagination;
	use app\components\InvalidDataException;
	use yii\db\Expression;

	class OrderManageController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;
		}

		/**
		 * 短信订单
		 */
		public function actionUserOrder ()
		{
			$uid     = \Yii::$app->request->get('uid', 0);
			$orderId = \Yii::$app->request->get('orderId', '');
			$packId  = \Yii::$app->request->get('packId', 0);
			$dates   = \Yii::$app->request->get('dates', '');

			$isJump  = \Yii::$app->request->get('isJump', 0);
			$account = \Yii::$app->request->get('account', '');

			$order = MessageOrder::find()->alias('o');
			$order = $order->leftJoin('{{%user}} u', 'o.uid = u.uid');
			$order = $order->where(['o.ispay' => '1']);
			$order = $order->andWhere(['o.goods_type' => 'messagePay']);//购买短信

			//账户id
			if (!empty($uid)) {
				$order = $order->andWhere(['u.uid' => $uid]);
			}
			//订单号
			if (!empty($orderId)) {
				$order = $order->andWhere(' (o.order_id=\'' . $orderId . '\' OR o.transaction_id=\'' . $orderId . '\') ');
			}
			//短信包
			if (!empty($packId)) {
				$order = $order->andWhere(['o.goods_id' => $packId]);
			}
			//购买时间
			if (!empty($dates)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';
				$order      = $order->andWhere(['between', 'o.paytime', $start_date, $end_date]);
			}

			$order = $order->select('u.uid,u.account,o.id,o.order_id,o.goods_type,o.goods_id,o.goods_name,o.goods_price,o.paytime,o.ispay');
			$count = $order->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$order = $order->offset($pages->offset)->limit($pages->limit)->orderBy('o.id desc')->asArray()->all();

			foreach ($order as $k => $v) {
				$order[$k]['goods_type'] = '短信包';
				if ($v['ispay'] == 1){
					$order[$k]['status'] = '已支付';
				}else{
					$order[$k]['status'] = '未支付';
				}
			}

			//账户
			$userArr = User::find()->select('uid,account')->all();
			//短信包
			$messagePack = MessagePack::find()->where(['status' => 1])->select('id,num')->all();

			return $this->render('userOrder', ['allUser' => $userArr, 'messagePack' => $messagePack, 'orderList' => $order, 'pages' => $pages, 'uid' => $uid, 'orderId' => $orderId, 'packId' => $packId, 'dates' => $dates, 'isJump' => $isJump, 'account' => $account]);
		}

		/**
		 * 套餐订单
		 */
		public function actionPackageOrder ()
		{
			$uid     = \Yii::$app->request->get('uid', 0);
			$packId  = \Yii::$app->request->get('packId', 0);
			$dates   = \Yii::$app->request->get('dates', '');

			$isJump  = \Yii::$app->request->get('isJump', 0);
			$account = \Yii::$app->request->get('account', '');

			$order = MessageOrder::find()->alias('o');
			$order = $order->leftJoin('{{%user}} u', 'o.uid = u.uid');
			$order = $order->where(['o.ispay' => '1']);
			$order = $order->andWhere(['o.goods_type' => 'packageBuy']);

			//账户id
			if (!empty($uid)) {
				$order = $order->andWhere(['u.uid' => $uid]);
			}
			//套餐
			if (!empty($packId)) {
				$order = $order->andWhere(['o.goods_id' => $packId]);
			}
			//购买时间
			if (!empty($dates)) {
				$dateArr    = explode(' - ', $dates);
				$start_date = $dateArr[0];
				$end_date   = $dateArr[1] . ' 23:59:59';
				$order      = $order->andWhere(['between', 'o.paytime', $start_date, $end_date]);
			}

			$order = $order->select('u.uid,u.account,o.id,o.order_id,o.goods_type,o.goods_id,o.goods_name,o.goods_price,o.paytime,o.ispay');
			$count = $order->count();
			$pages = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$order = $order->offset($pages->offset)->limit($pages->limit)->orderBy('o.id desc')->asArray()->all();

			foreach ($order as $k => $v) {
				$order[$k]['goods_type'] = '套餐';
				if ($v['ispay'] == 1){
					$order[$k]['status'] = '已支付';
				}else{
					$order[$k]['status'] = '未支付';
				}
			}

			//账户
			$userArr = User::find()->select('uid,account')->all();
			//套餐
			$packageData = Package::find()->where(['status' => 1])->select('id,name')->all();

			return $this->render('packageOrder', ['allUser' => $userArr, 'packageData' => $packageData, 'orderList' => $order, 'pages' => $pages, 'uid' => $uid, 'packId' => $packId, 'dates' => $dates, 'isJump' => $isJump, 'account' => $account]);
		}


	}