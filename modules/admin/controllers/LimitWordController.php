<?php

	namespace app\modules\admin\controllers;

	use app\components\InvalidDataException;
	use app\models\LimitWord;
	use app\models\LimitWordGroup;
	use app\modules\admin\components\BaseController;
	use yii\data\Pagination;

	class LimitWordController extends BaseController
	{
		public $enableCsrfValidation = false;

		//敏感词列表
		public function actionList ()
		{
			LimitWordGroup::defaultGroup();
			$title    = \Yii::$app->request->get('name') ?: '';
			$group_id = \Yii::$app->request->get('sort_id') ?: '';
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;

			$limitWord = LimitWord::find()->where(['uid' => NULL, 'status' => 1]);
			if (!empty($title)) {
				$limitWord = $limitWord->andWhere(['like', 'title', $title]);
			}
			if (!empty($group_id)) {
				$limitWord = $limitWord->andWhere(['group_id' => $group_id]);
			}
			$count     = $limitWord->count();
			$pages     = new Pagination(['totalCount' => $count, 'pageSize' => $pageSize]);
			$limitWord = $limitWord->offset($pages->offset)->limit($pages->limit)->orderBy('id desc')->all();

			$groupList = LimitWordGroup::find()->where(['status' => 1])->all();
			$groupArr  = array_column($groupList, 'title', 'id');

			return $this->render('list', ['title' => $title, 'group_id' => $group_id, 'limitWord' => $limitWord, 'groupArr' => $groupArr, 'pages' => $pages]);
		}

		//敏感词设置
		public function actionSet ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
			$postData = \Yii::$app->request->post();
			try {
				$limit_id = LimitWord::setName($postData);
				$result   = ['error' => 0, 'msg' => '', 'id' => $limit_id];
			} catch (InvalidDataException $e) {
				$result = ['error' => 1, 'msg' => $e->getMessage()];
			}
			$this->dexit($result);
		}

		//敏感词删除
		public function actionDelete ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
			$id     = \Yii::$app->request->post('id', 0);
			$status = \Yii::$app->request->post('status', 0);
			if (!in_array($status, [0, 1])) {
				$this->dexit(['error' => 1, 'msg' => '状态值不正确']);
			}
			$limitWord = LimitWord::findOne($id);
			if (empty($limitWord)) {
				$this->dexit(['error' => 1, 'msg' => '参数不正确']);
			}
			$limitWord->status = $status;
			$limitWord->update();

			return true;
		}

		//分组
		public function actionGroup ()
		{
			LimitWordGroup::defaultGroup();
			$title    = \Yii::$app->request->get('name') ?: '';
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;

			$limitWord = LimitWordGroup::find()->where(['uid' => NULL,'status' => 1]);
			if (!empty($title)) {
				$limitWord = $limitWord->andWhere(['like', 'title', $title]);
			}
			$count     = $limitWord->count();
			$pages     = new Pagination(['totalCount' => $count, 'pageSize' => $pageSize]);
			$limitWord = $limitWord->offset($pages->offset)->limit($pages->limit)->orderBy('id desc')->all();

			return $this->render('group', ['title' => $title, 'groupList' => $limitWord, 'pages' => $pages]);
		}

		//分组添加
		public function actionSetGroup ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
			$postData = \Yii::$app->request->post();
			try {
				$group_id = LimitWordGroup::setGroup($postData);
				$result   = ['error' => 0, 'msg' => '', 'id' => $group_id];
			} catch (InvalidDataException $e) {
				$result = ['error' => 1, 'msg' => $e->getMessage()];
			}
			$this->dexit($result);

		}

		//分组删除
		public function actionDelGroup ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
			$id     = \Yii::$app->request->post('id', 0);
			$status = \Yii::$app->request->post('status', 0);
			if (!in_array($status, [0, 1])) {
				$this->dexit(['error' => 1, 'msg' => '状态值不正确']);
			}
			$group = LimitWordGroup::findOne($id);
			if (empty($group)) {
				$this->dexit(['error' => 1, 'msg' => '参数不正确']);
			}
			$group->status = $status;
			$group->update();

			$defaultGroupId = LimitWordGroup::defaultGroup();
			//更改敏感词分组
			LimitWord::updateAll(['group_id' => $defaultGroupId], ['status' => 1, 'group_id' => $group->id, 'uid' => NULL]);

			return true;
		}
	}