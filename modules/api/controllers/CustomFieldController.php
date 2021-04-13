<?php

	namespace app\modules\api\controllers;

	use app\models\CustomField;
	use app\models\CustomFieldChat;
	use app\models\CustomFieldUser;
	use app\models\CustomFieldOption;
	use app\models\Follow;
	use app\models\WaitTask;
	use app\models\WorkExternalContactFollowUser;
	use app\modules\api\components\AuthBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidDataException;

	class CustomFieldController extends AuthBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           客户属性列表
		 * @description     客户高级属性字段列表
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/field-list
		 *
		 * @param uid 必选 string 用户ID
		 * @param status 可选 int 状态2全部1开启0关闭
		 * @param chat_status 可选 int 状态2全部1开启0关闭
		 *
		 * @return          {"error":0,"data":{"field":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    field array 属性列表
		 * @return_param    field.id int 属性id
		 * @return_param    field.key string 属性唯一标识
		 * @return_param    field.title string 属性名称
		 * @return_param    field.type int 格式:1文本类型、2单选类型、3多选类型、4日期类型、5手机号类型、6邮箱类型、7区域类型
		 * @return_param    field.is_define int 是否商家自定义：1是0否
		 * @return_param    field.status string 是否开启：1是0否
		 * @return_param    field.optionVal string 属性值（英文逗号隔开）
		 * @return_param    field.sort int 排序值
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-04-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFieldList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid         = \Yii::$app->request->post('uid', 0);
			$status      = \Yii::$app->request->post('status', 2);
			$chat_status = \Yii::$app->request->post('chat_status', 2);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			//状态筛选
			$statusWhere = '';
			if ($status == 2){
				$statusWhere = ' AND status IN (0,1)';
			} elseif ($status == 1){
				$statusWhere = ' AND status=1';
			} elseif ($status == 0){
				$statusWhere = ' AND status=0';
			}
			if ($chat_status == 1){
				$statusWhere = ' AND chat_status=1';
			} elseif ($chat_status == 0){
				$statusWhere = ' AND chat_status=0';
			}
			$fieldList1 = CustomField::find()->where('uid=' . $uid . $statusWhere)->select('`id`,`key`,`title`,`type`,`is_define`,`status`,`chat_status`,`sort`')->orderBy('id desc')->asArray()->all();//自定义属性
			$fieldList2 = CustomField::find()->where('is_define=0 AND status=1')->select('`id`,`key`,`title`,`type`,`is_define`,`status`,`sort`')->orderBy('id asc')->asArray()->all();//默认属性

			//商户没有设置过客户默认属性，则默认全部开启
			$userField  = CustomFieldUser::find()->where(['uid' => $uid])->select('`fieldid`,`status`,`sort`')->asArray()->all();
			if (empty($userField)) {
				foreach ($fieldList2 as $k => $v) {
					$fieldUser          = new CustomFieldUser();
					$fieldUser->uid     = $uid;
					$fieldUser->fieldid = $v['id'];
					$fieldUser->status  = 1;
					$fieldUser->sort    = 0;
					$fieldUser->time    = time();
					if (!$fieldUser->save()) {
						throw new InvalidDataException(SUtils::modelError($fieldUser));
					}
				}
				$userField  = CustomFieldUser::find()->where(['uid' => $uid])->select('`fieldid`,`status`,`sort`')->asArray()->all();
			}
			//默认客户属性状态
			$userFieldD = [];
			foreach ($userField as $k => $v) {
				$userFieldD[$v['fieldid']] = $v;
			}
			//商户没有设置过客户群默认属性，则默认部分开启
			$chatField = CustomFieldChat::find()->where(['uid' => $uid])->select('`fieldid`,`status`')->asArray()->all();
			if (empty($chatField)) {
				foreach ($fieldList2 as $k => $v) {
					if (!in_array($v['key'], ['sex', 'birthday', 'age', 'education', 'income', 'idCard'])){
						$fieldChat          = new CustomFieldChat();
						$fieldChat->uid     = $uid;
						$fieldChat->fieldid = $v['id'];
						$fieldChat->status  = 1;
						$fieldChat->time    = time();
						if (!$fieldChat->save()) {
							throw new InvalidDataException(SUtils::modelError($fieldChat));
						}
					}
				}
				$chatField = CustomFieldChat::find()->where(['uid' => $uid])->select('`fieldid`,`status`')->asArray()->all();
			}
			//默认客户群属性状态
			$chatFieldD = [];
			foreach ($chatField as $k => $v) {
				$chatFieldD[$v['fieldid']] = $v;
			}

			foreach ($fieldList2 as $k=>$v){
				$fieldList2[$k]['status'] = isset($userFieldD[$v['id']]['status']) ? $userFieldD[$v['id']]['status'] : 0;
				$fieldList2[$k]['chat_status'] = isset($chatFieldD[$v['id']]['status']) ? $chatFieldD[$v['id']]['status'] : 0;
				$fieldList2[$k]['sort']   = isset($userFieldD[$v['id']]['sort']) ? $userFieldD[$v['id']]['sort'] : 0;
				if ($status == 1 && $fieldList2[$k]['status'] == 0){
					unset($fieldList2[$k]);
				} elseif ($status == 0 && $fieldList2[$k]['status'] == 1){
					unset($fieldList2[$k]);
				}
				if ($chat_status == 1 && $fieldList2[$k]['chat_status'] == 0){
					unset($fieldList2[$k]);
				} elseif ($chat_status == 0 && $fieldList2[$k]['chat_status'] == 1){
					unset($fieldList2[$k]);
				}
			}
			$fieldList  = array_merge($fieldList1, $fieldList2);

			foreach ($fieldList as $k=>$v){
				//属性值
				$optionVal = '';
				if (in_array($v['type'], [2, 3])){
					if ($v['is_define'] == 0){
						$fieldOption = CustomFieldOption::find()->where(['fieldid' => $v['id']])->asArray()->all();
					}else{
						$fieldOption = CustomFieldOption::find()->where(['fieldid' => $v['id'], 'is_del' => 0])->asArray()->all();
					}

					foreach ($fieldOption as $vv){
						$optionVal .= $vv['match'] . ',';
					}
					$optionVal = trim($optionVal, ',');
				}
				$fieldList[$k]['optionVal'] = $optionVal;
			}

			//排序
			/*$sort_names = array_column($fieldList, 'sort');
			array_multisort($sort_names, SORT_DESC, $fieldList);*/

			return [
				'field' => $fieldList
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           设置客户属性
		 * @description     设置客户属性
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/set-field
		 *
		 * @param uid          必选 int 用户ID
		 * @param id           可选 int 属性ID
		 * @param title        必选 string 属性名称
		 * @param type         必选 string 字段类型
		 * @param optionVal    可选 string 属性值
		 * @param status       必选 int 开启关闭状态
		 * @param chat_status  必选 int 客户群开启关闭状态
		 * @param sort         可选 int 排序值
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-04-09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetField ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();

			try {
				$is_define = 1;
				if ($postData['id']){
					$customField = CustomField::findOne($postData['id']);
					$is_define = $customField->is_define;
				}
				if ($is_define == 0){
					//默认属性
					$uid    = isset($postData['uid']) ? intval($postData['uid']) : 0;
					$id     = isset($postData['id']) ? intval($postData['id']) : 0;
					$status = isset($postData['status']) ? $postData['status'] : 0;
					$chat_status = isset($postData['chat_status']) ? $postData['chat_status'] : 0;
					$sort   = isset($postData['sort']) ? $postData['sort'] : 0;
					if (empty($id)) {
						throw new InvalidDataException('id参数不能为空！');
					}
					if ($status == 2) {
						throw new InvalidDataException('状态参数错误！');
					}
					//客户
					$userField = CustomFieldUser:: findOne(['fieldid' => $id, 'uid' => $uid]);
					if (empty($userField)) {
						$userField          = new CustomFieldUser();
						$userField->uid     = $uid;
						$userField->fieldid = $id;
					}
					$userField->status = $status;
					$userField->sort   = $sort;
					$userField->time   = time();
					if (!$userField->save()) {
						throw new InvalidDataException(SUtils::modelError($userField));
					}
					//客户群
					$chatField = CustomFieldChat:: findOne(['fieldid' => $id, 'uid' => $uid]);
					if (empty($chatField)) {
						$chatField          = new CustomFieldChat();
						$chatField->uid     = $uid;
						$chatField->fieldid = $id;
					}
					$chatField->status = $chat_status;
					$chatField->time   = time();
					if (!$chatField->save()) {
						throw new InvalidDataException(SUtils::modelError($chatField));
					}
				}else{
					//自定义属性
					//判断是否有字段名重复
					if (empty($postData['id'])){
						$hasField = CustomField::find()->where('(`uid`=0 AND `title`=\'' . $postData['title'] . '\' AND `status`=1) OR (`uid`=' . $postData['uid'] . ' AND binary `title`=\'' . $postData['title'] . '\' AND `status`!=2)')->one();
					}else{
						$hasField = CustomField::find()->where('(`uid`=0 AND `title`=\'' . $postData['title'] . '\' AND `status`=1) OR (`uid`=' . $postData['uid'] . ' AND `id`!=' . $postData['id'] . ' AND binary `title`=\'' . $postData['title'] . '\' AND `status`!=2)')->one();
					}
					if (!empty($hasField)) {
						throw new InvalidDataException('此字段名:' . $postData['title'] . '已存在,请更换！');
					}

					CustomField::UserSetField($postData);
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           设置客户属性开启关闭删除
		 * @description     设置客户属性开启关闭删除状态
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/set-field-status
		 *
		 * @param uid         必选 int 用户ID
		 * @param id          必选 int 属性ID
		 * @param is_define   必选 int 是否自定义属性
		 * @param status      必选 int 状态:0关闭、1开启、2删除
		 * @param type        必选 int 状态类型:0客户、1客户群
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-04-10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetFieldStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$id        = \Yii::$app->request->post('id', 0);
			$is_define = \Yii::$app->request->post('is_define', 0);
			$status    = \Yii::$app->request->post('status', 0);
			$type      = \Yii::$app->request->post('type', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (!in_array($status, [0, 1, 2])) {
				throw new InvalidDataException('状态参数错误！');
			}
			if ($type == 1 && !in_array($status, [0, 1])){
				throw new InvalidDataException('群状态参数错误！');
			}

			if ($is_define == 1) {
				$field = CustomField:: findOne(['id' => $id, 'uid' => $uid]);
				if (empty($field)) {
					throw new InvalidDataException('id参数错误！');
				}
				if ($type == 1) {
					$field->chat_status = $status;
				} else {
					$field->status = $status;
					if ($status == 2){
						$field->chat_status = $status;
					}
				}
				$field->updatetime = time();
				if (!$field->save()) {
					throw new InvalidDataException(SUtils::modelError($field));
				}
			} else {
				if ($status == 2) {
					throw new InvalidDataException('状态参数错误！');
				}
				if ($type == 1) {
					$chatField = CustomFieldChat:: findOne(['fieldid' => $id, 'uid' => $uid]);
					if (empty($chatField)) {
						$chatField          = new CustomFieldChat();
						$chatField->uid     = $uid;
						$chatField->fieldid = $id;
					}
					$chatField->status = $status;
					$chatField->time   = time();
					if (!$chatField->save()) {
						throw new InvalidDataException(SUtils::modelError($chatField));
					}
				} else {
					$userField = CustomFieldUser:: findOne(['fieldid' => $id, 'uid' => $uid]);
					if (empty($userField)) {
						$userField          = new CustomFieldUser();
						$userField->uid     = $uid;
						$userField->fieldid = $id;
					}
					$userField->status = $status;
					$userField->time   = time();
					if (!$userField->save()) {
						throw new InvalidDataException(SUtils::modelError($userField));
					}
				}
			}

			return true;
		}
		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           批量设置客户属性
		 * @description     批量设置客户属性
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/set-field-batch
		 *
		 * @param uid                必选 int 用户ID
		 * @param msgData            必选 array 数据
		 * @param msgData.id         可选 int 属性ID
		 * @param msgData.title      必选 string 属性名称
		 * @param msgData.type       必选 string 字段类型
		 * @param msgData.optionVal  可选 string 属性值
		 * @param msgData.status     必选 int 状态:0关闭、1开启、2删除
		 * @param msgData.chat_status  必选 int 客户群状态:0关闭、1开启
		 * @param msgData.is_define  必选 int 是否自定义属性
		 * @param msgData.sort       可选 int 排序值
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-04-10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetFieldBatch ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$msgData = \Yii::$app->request->post('msgData');

			if (empty($uid) || empty($msgData)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			try {
				$nowDefineField = [];

				//判断是否有字段名重复
				$msgD = $msgData;
				foreach ($msgData as $k=>$v){
					if ($v['is_define'] == 1){
						$title = trim($v['title']);
						if (empty($title)){
							throw new InvalidDataException('请填写字段名称！');
						}else{
							unset($msgD[$k]);
							foreach ($msgD as $kk=>$vv){
								$compareTitle = trim($vv['title']);
								if ($title == $compareTitle){
									throw new InvalidDataException('字段名:' . $title . '重复,请更换！');
								}
							}
						}
					}
				}
				/*foreach ($msgData as $k=>$v){
					if ($v['is_define'] == 1){
						if (empty($v['id'])){
							$hasField = CustomField::find()->where('`uid`=' . $uid . ' AND binary `title`=\'' . $v['title'] . '\' AND `status`!=2')->one();
						}else{
							$hasField = CustomField::find()->where('`uid`=' . $uid . ' AND `id`!=' . $v['id'] . ' AND binary `title`=\'' . $v['title'] . '\' AND `status`!=2')->one();
						}
						if (!empty($hasField)) {
							throw new InvalidDataException('此字段名:' . $v['title'] . '已存在,请更换！');
						}
					}
				}*/
				foreach ($msgData as $k=>$v){
					$id        = isset($v['id']) ? intval($v['id']) : 0;
					$status    = isset($v['status']) ? $v['status'] : 0;
					$chat_status = isset($v['chat_status']) ? $v['chat_status'] : 0;
					$is_define = isset($v['is_define']) ? $v['is_define'] : 0;
					$sort      = isset($v['sort']) ? $v['sort'] : 0;

					if ($is_define == 0){
						//默认属性
						if (empty($id)) {
							throw new InvalidDataException('id参数不能为空！');
						}
						if ($status == 2) {
							throw new InvalidDataException('状态参数错误！');
						}
						//客户
						$userField = CustomFieldUser:: findOne(['fieldid' => $id, 'uid' => $uid]);
						if (empty($userField)) {
							$userField          = new CustomFieldUser();
							$userField->uid     = $uid;
							$userField->fieldid = $id;
						}
						$userField->status = $status;
						$userField->sort   = $sort;
						$userField->time   = time();
						if (!$userField->save()) {
							throw new InvalidDataException(SUtils::modelError($userField));
						}
						//客户群
						$chatField = CustomFieldChat:: findOne(['fieldid' => $id, 'uid' => $uid]);
						if (empty($chatField)) {
							$chatField          = new CustomFieldChat();
							$chatField->uid     = $uid;
							$chatField->fieldid = $id;
						}
						$chatField->status = $chat_status;
						$chatField->time   = time();
						if (!$chatField->save()) {
							throw new InvalidDataException(SUtils::modelError($chatField));
						}
					}else{
						//自定义属性
						$v['uid'] = $uid;
						$res      = CustomField::UserSetField($v);

						$nowDefineField[$res['fieldid']] = $res['fieldid'];
					}
				}

				//删除的属性
				$fieldUser = CustomField::find()->where('uid=' . $uid . ' AND status IN (0,1)')->select('`id`')->asArray()->all();
				$delFieldD = [];
				foreach ($fieldUser as $k=>$v){
					if (!isset($nowDefineField[$v['id']])){
						array_push($delFieldD, $v['id']);
					}
				}
				if (!empty($delFieldD)){
					CustomField::updateAll(['status' => 2, 'chat_status' => 2], ['id' => $delFieldD]);
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           获取属性选项信息
		 * @description     获取属性选项信息
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/field-info
		 *
		 * @param uid 必选 string 用户ID
		 * @param type 必选 int 类型0客户1客户群
		 *
		 * @return          {"error":0,"data":{"work":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    work array 行业属性数据
		 * @return_param    defineField array 自定义高级属性搜索项
		 * @return_param    defineField.fieldid int 高级属性id
		 * @return_param    defineField.title string 高级属性名称
		 * @return_param    defineField.type int 属性类型：2单选、3多选
		 * @return_param    defineField.optionVal array 属性选项（只可选一项）
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-04-24
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFieldInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid  = \Yii::$app->request->post('uid', 0);
			$type = \Yii::$app->request->post('type', 0);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$fieldList = CustomField::find()->where('is_define=0')->select('`id`,`key`,`title`')->orderBy('id asc')->asArray()->all();//默认属性

			$work = [];
			foreach ($fieldList as $k => $v) {
				//属性值
				if ($v['key'] == 'work') {
					$fieldOption = CustomFieldOption::find()->where(['fieldid' => $v['id']])->asArray()->all();
					foreach ($fieldOption as $vv) {
						array_push($work, $vv['match']);
					}
				}
			}

			//高级属性搜索项
			$defineField = CustomField::find()->where(['uid' => $uid, 'is_define' => 1]);
			if ($type == 1){
				$defineField = $defineField->andWhere(['chat_status' => 1]);
			}else{
				$defineField = $defineField->andWhere(['status' => 1]);
			}
			$defineField = $defineField->andWhere(['in', 'type', [2, 3]])->select('`id` fieldid,`title`,`type`')->orderBy(['sort' => SORT_DESC])->asArray()->all();
			foreach ($defineField as $k => $v) {
				//属性值
				$optionVal   = [];
				$fieldOption = CustomFieldOption::find()->where(['fieldid' => $v['fieldid'], 'is_del' => 0])->asArray()->all();

				foreach ($fieldOption as $vv) {
					array_push($optionVal, $vv['match']);
				}
				$defineField[$k]['optionVal'] = $optionVal;
			}

			return [
				'work'        => $work,
				'defineField' => $defineField
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           跟进状态列表
		 * @description     跟进状态列表
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/follow
		 *
		 * @param uid 必选 string 用户ID
		 * @param status 可选 string 状态
		 * @param is_del_str 可选 string 1在标题带上已删除
		 *
		 * @return          {"error":0,"data":{"follow":[{"id":1,"uid":2,"title":"未跟进","status":1},{"id":2,"uid":2,"title":"跟进中","status":1},{"id":3,"uid":2,"title":"已拒绝","status":1},{"id":4,"uid":2,"title":"已成交","status":1}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    follow array 跟进列表
		 * @return_param    id string 跟进id
		 * @return_param    uid string 用户id
		 * @return_param    title string 跟进名称
		 * @return_param    status string 跟进状态：0、删除，1、可用
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-06 9:11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollow ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$id     = \Yii::$app->request->post('id', 0);
			$uid    = \Yii::$app->request->post('uid', 0);
			$status = \Yii::$app->request->post('status', 0);
			$isDelStr = \Yii::$app->request->post('is_del_str', 0);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$follow = Follow::find()->where(['uid' => $uid]);
			$followClone = clone $follow;
			if (!empty($status)) {
				$follow = $follow->andWhere(['status' => $status]);
			}
			$follow = $follow->select('id,uid,title,describe,status')->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();
			if(!empty($isDelStr)){
				foreach($follow as $fol){
					if($fol['status']== 0){
						$fol['title'] = $fol['title'].'（已删除）';
					}
				}
			}
			if(!empty($follow)){
				foreach ($follow as $k=>$v){
					$follow[$k]['id'] = intval($v['id']);
					$follow[$k]['key'] = $k;
				}
			}

			if(!empty($id)){
				$followClone = $followClone->andWhere(["id"=>$id]);
				$followClone = $followClone->select('id,uid,title,describe,status')->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();
				if(!empty($followClone)){
					foreach ($followClone as $k=>$v){
						$followClone[$k]['id'] = intval($v['id']);
						$followClone[$k]['key'] = $k;
						$followClone[$k]['title'] = $v["title"]."(已删除)";
					}
				}
				array_push($followClone,...$follow);
				$follow = $followClone;
			}

			return [
				'follow' => $follow
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           跟进状态排序
		 * @description     跟进状态排序
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/follow-sort
		 *
		 * @param uid 必选 string 账户id
		 * @param ids 必选 array 排序好的跟进状态id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-27 14:24
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionFollowSort ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$ids = \Yii::$app->request->post('ids');
			if (empty($uid) || empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			foreach ($ids as $key => $id) {
				$sort   = $key + 1;
				$follow = Follow::findOne(['uid' => $uid, 'id' => $id]);
				if (!empty($follow)) {
					$follow->sort = $sort;
					$follow->update();
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           添加修改跟进状态
		 * @description     添加修改跟进状态
		 * @method   请求方式
		 * @url  http://{host_name}/api/custom-field/add-follow
		 *
		 * @param uid 必选 string 账户id
		 * @param id 可选 string 修改时必填
		 * @param title 可选 string 跟进名称
		 * @param describe 可选 string 跟进描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-27 17:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAddFollow ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$id       = \Yii::$app->request->post('id', 0);
			$title    = \Yii::$app->request->post('title', '');
			$describe = \Yii::$app->request->post('describe', '');
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($title)) {
				throw new InvalidDataException('请输入状态名称！');
			}
			$title = trim($title);
			if (mb_strlen($title, 'utf-8') > 8) {
				throw new InvalidDataException('状态名称最多8个字！');
			}
			if (!empty($describe) && mb_strlen($describe, 'utf-8') > 50) {
				throw new InvalidDataException('状态描述最多50个字！');
			}
			$followInfo = Follow::find()->where(['uid' => $uid, 'title' => $title, 'status' => 1]);
			if (!empty($id)) {
				$followInfo = $followInfo->andWhere(['<>', 'id', $id]);
				$follow     = Follow::findOne($id);
			} else {
				$follow              = new Follow();
				$follow->uid         = $uid;
				$follow->create_time = DateUtil::getCurrentTime();

				//获取排序sort
				$followSort = Follow::find()->where(['uid' => $uid, 'status' => 1])->orderBy(['sort' => SORT_DESC])->one();
				$sort       = 0;
				if (!empty($followSort)) {
					$sort = $followSort->sort + 1;
				}
				$follow->sort = $sort;
			}

			//查询标识是否重复
			$followInfo = $followInfo->one();
			if (!empty($followInfo)) {
				throw new InvalidDataException('状态名称已经存在，请更换');
			}
			$follow->title    = $title;
			$follow->describe = $describe;
			if (!$follow->validate() || !$follow->save()) {
				throw new InvalidDataException(SUtils::modelError($follow));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           跟进状态删除
		 * @description     跟进状态删除
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/del-follow
		 *
		 * @param uid 必选 string 账户id
		 * @param id 必选 string 跟进状态id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-27 19:33
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionDelFollow ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$id  = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$follow = Follow::findOne(['uid' => $uid, 'id' => $id]);
			if (empty($follow)) {
				throw new InvalidDataException('参数不正确！');
			}

			$followUserCount = WorkExternalContactFollowUser::find()->where(['del_type' => 0, 'follow_id' => $follow->id])->groupBy('external_userid')->count();
			if ($followUserCount > 0) {
				throw new InvalidDataException('当前【' . $follow->title . '】存在【' . $followUserCount . '】个客户，暂不可删除，请将这些客户移到其他状态后，才能删除。');
			}
			//更改待办事项的任务变为已删除
			$count = WaitTask::find()->where(['is_del' => 0, 'follow_id' => $follow->id])->groupBy('project_id')->count();
			if ($count > 0) {
				throw new InvalidDataException('当前【' . $follow->title . '】已关联【' . $count . '】个服务待办项目，无法删除！');
			}
			WaitTask::updateAll(['is_del' => WaitTask::IS_DEL], ['follow_id' => $id]);
			$follow->status = 0;
			$follow->update();

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/custom-field/
		 * @title           跟进状态添加
		 * @description     跟进状态添加
		 * @method   post
		 * @url  http://{host_name}/api/custom-field/follow-add
		 *
		 * @param uid 必选 string 用户id
		 * @param msgData 必选 array 状态数据
		 * @param delIdArr 可选 array 删除id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-06 9:12
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFollowAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$msgData  = \Yii::$app->request->post('msgData', []);
			$delIdArr = \Yii::$app->request->post('delIdArr', []);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($msgData)) {
				throw new InvalidDataException('请输入状态名称！');
			}
			//检查数据
			$titleArr = [];
			foreach ($msgData as $msg) {
				$title = trim($msg['title']);
				if (empty($title)) {
					throw new InvalidDataException('请输入状态名称！');
				}
				if (mb_strlen($title, 'utf-8') > 8) {
					throw new InvalidDataException('状态名称最多8个字！');
				}
				if (in_array($title, $titleArr)) {
					throw new InvalidDataException('状态名称不能重复！');
				}
				array_push($titleArr, $title);
				if (!empty($msg['id'])) {
					$follow = Follow::findOne(['id' => $msg['id'], 'uid' => $uid]);
					if (empty($follow)) {
						throw new InvalidDataException('参数不正确！');
					}
				}
			}
			//检查删除数据
			if (!empty($delIdArr)) {
				foreach ($delIdArr as $delId) {
					$follow = Follow::findOne(['id' => $delId, 'uid' => $uid]);
					if (empty($follow)) {
						throw new InvalidDataException('参数不正确！');
					}
				}
			}

			$transaction = \Yii::$app->db->beginTransaction();
			try {
				//添加数据
				$create_time = DateUtil::getCurrentTime();
				foreach ($msgData as $msg) {
					if (!empty($msg['id'])) {
						$follow = Follow::findOne($msg['id']);
					} else {
						$follow              = new Follow();
						$follow->uid         = $uid;
						$follow->create_time = $create_time;
					}
					$follow->title = trim($msg['title']);
					if (!$follow->validate() || !$follow->save()) {
						throw new InvalidDataException(SUtils::modelError($follow));
					}
				}
				$transaction->commit();

				//删除数据
				if (!empty($delIdArr)) {
					Follow::updateAll(['status' => 0], ['id' => $delIdArr]);
				}
			} catch (InvalidDataException $e) {
				$transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		//批量更新相关数据
		public function actionUpdateBatch ()
		{
			$uid         = \Yii::$app->request->post('uid', 0);
			$type        = \Yii::$app->request->post('type', 0);
			$update_type = \Yii::$app->request->post('update_type', 0);
			if (empty($uid)) {
				echo '缺少必要参数';

				return true;
			}
			try {
				if ($type == 'followBatch') {
					Follow::updateFollow($update_type);
				} elseif ($type == 'updateFollowContact') {
					Follow::updateFollowContact($update_type);
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
			echo "更新完成";

			return true;
		}
	}