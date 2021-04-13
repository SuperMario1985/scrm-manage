<?php

	namespace app\modules\admin\controllers;

	use app\models\CustomField;
	use app\models\CustomFieldOption;
	use app\modules\admin\components\BaseController;
	use app\util\SUtils;
	use yii\data\Pagination;
	use app\components\InvalidDataException;
	use yii\db\Expression;

	class CustomManageController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;
		}

		/**
		 * 客户高级属性
		 */
		public function actionCustomField ()
		{
			$key = \Yii::$app->request->get('key', '');

			$field = CustomField::find();
			$field = $field->where(['is_define' => 0]);
			$field = $field->andWhere(['!=', 'status', 2]);
			if ($key) {
				$field = $field->andWhere(['like', 'key', $key]);
			}
			$field = $field->select('`id`,`key`,`title`,`type`,`status`')->asArray()->all();

			//默认值
			$fieldId = [];
			foreach ($field as $kv=>$lv) {
				array_push($fieldId, $lv['id']);
				$typeName = '';
				if ($lv['type'] == 1){
					$typeName = '文本类型';
				} elseif ($lv['type'] == 2){
					$typeName = '单选类型';
				} elseif ($lv['type'] == 3){
					$typeName = '多选类型';
				} elseif ($lv['type'] == 4){
					$typeName = '日期类型';
				} elseif ($lv['type'] == 5){
					$typeName = '手机号类型';
				} elseif ($lv['type'] == 6){
					$typeName = '邮箱类型';
				} elseif ($lv['type'] == 7){
					$typeName = '区域类型';
				} elseif ($lv['type'] == 8){
					$typeName = '图片类型';
				}
				$field[$kv]['typeName'] = $typeName;
			}
			$optionVal = [];
			if (!empty($fieldId)) {
				$fieldStr    = implode(',', $fieldId);
				$fieldOption = CustomFieldOption::find()->where('fieldid IN (' . $fieldStr . ')')->asArray()->all();

				foreach ($fieldOption as $ov) {
					$optionVal[$ov['fieldid']][] = $ov['match'];
				}
			}

			return $this->render('customField', ['fieldList' => $field, 'optionVal' => $optionVal, 'key' => $key]);
		}
		/**
		 * 添加/修改客户高级属性
		 */
		public function actionAddField ()
		{
			$postData = \Yii::$app->request->post();
			try {
				$result = CustomField::setField($postData);
			} catch (InvalidDataException $e) {
				$result = ['error' => 1, 'msg' => $e->getMessage()];
			}
			$this->dexit($result);
		}
		/**
		 * 获取客户高级属性
		 */
		public function actionGetField ()
		{
			$postData = \Yii::$app->request->post();

			$id     = $postData['id'];
			$result = CustomField::find()->where(['id' => $id])->asArray()->one();

			if (!empty($result)) {
				if (in_array($result['type'], [2, 3])) {
					$option = CustomFieldOption::find()->where(['fieldid' => $result['id']])->asArray()->all();

					$optionArr = [];
					if (!empty($option)) {
						foreach ($option as $o) {
							array_push($optionArr, $o['match']);
						}
					}
					if (!empty($optionArr)) {
						$result['match'] = implode("\r\n", $optionArr);
					}
				}
			}

			$this->dexit(['error' => 0, 'msg' => $result]);
		}
		/**
		 * 修改客户高级属性状态
		 */
		public function actionUpdateField ()
		{
			$postData = \Yii::$app->request->post();

			$id     = $postData['id'];
			$status = $postData['status'];
			$field  = CustomField::findOne($id);

			if (!empty($field)) {
				$field->status = $status;
				if (!$field->save()) {
					throw new InvalidDataException(SUtils::modelError($field));
				}

				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '修改失败']);
			}
		}

	}