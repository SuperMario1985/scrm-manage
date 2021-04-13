<?php
	/*
	 * 公海池手机端
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\AuthoritySubUserDetail;
	use app\models\CustomField;
	use app\models\CustomFieldOption;
	use app\models\CustomFieldValue;
	use app\models\Follow;
	use app\models\PublicSeaClaim;
	use app\models\PublicSeaClaimUser;
	use app\models\PublicSeaContactFollowRecord;
	use app\models\PublicSeaContactFollowUser;
	use app\models\PublicSeaCustomer;
	use app\models\PublicSeaPrivateTag;
	use app\models\PublicSeaProtect;
	use app\models\PublicSeaReclaimSet;
	use app\models\PublicSeaTag;
	use app\models\PublicSeaTimeLine;
	use app\models\SubUser;
	use app\models\SubUserProfile;
	use app\models\UserProfile;
	use app\models\WaitCustomerTask;
	use app\models\WaitTask;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkTag;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;

	class WapPublicSeaController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           企微客户公海池
		 * @description     企微客户公海池
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/customer
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 string 企业微信id
		 * @param user_id          必选 int 员工ID
		 * @param name             可选 string 搜索姓名
		 * @param add_way          可选 string 来源
		 * @param way_id           可选 string 渠道id
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param page_size        可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:17
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', 0);
			$name     = \Yii::$app->request->post('name', '');
			$addWay   = \Yii::$app->request->post('add_way', '-1');
			$wayId    = \Yii::$app->request->post('way_id', '');
			$sDate    = \Yii::$app->request->post('s_date', '');
			$eDate    = \Yii::$app->request->post('e_date', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);
			$name     = trim($name);
			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			//是否是主账号
			$isMaster = 0;
			if (!empty($workUser->mobile)) {
				$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
				if (!empty($subUser) && !empty($subUser->type)) {
					$isMaster = 1;
				}
			}

			//可见范围
			$userData = AuthoritySubUserDetail::getUserIds($userId, $uid, $corpId, []);
			$show     = $userData['show'];

			$userId = $workUser->id;

			$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

			$customerList = PublicSeaCustomer::find()->alias('sc');
			$customerList = $customerList->leftJoin('{{%work_external_contact_follow_user}} wf', 'sc.follow_user_id=wf.id');
			$customerList = $customerList->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
			$customerList = $customerList->where(['sc.corp_id' => $corpId, 'sc.is_claim' => 0, 'sc.type' => 1]);

			//姓名
			if ($name !== '') {
				$customerList = $customerList->andWhere(['like', 'we.name_convert', $name]);
			}

			//来源搜索
			if ($addWay != '-1') {
				if ($addWay === 'way') {
					$customerList = $customerList->andWhere(['>', 'wf.way_id', 0]);
				} elseif ($addWay === 'chatWay') {
					$customerList = $customerList->andWhere(['>', 'wf.chat_way_id', 0]);
				} elseif ($addWay === 'fission') {
					$customerList = $customerList->andWhere(['>', 'wf.fission_id', 0]);
				} elseif ($addWay === 'award') {
					$customerList = $customerList->andWhere(['>', 'wf.award_id', 0]);
				} elseif ($addWay === 'redPack') {
					$customerList = $customerList->andWhere(['>', 'wf.red_pack_id', 0]);
				} else {
					$customerList = $customerList->andWhere(['wf.add_way' => $addWay]);
				}
			}

			//活码搜索
			if (!empty($wayId)) {
				$wayArr = explode('_', $wayId);
				if ($wayArr[0] == 'way') {
					$customerList = $customerList->andWhere(['wf.way_id' => $wayArr[1]]);
				} elseif ($wayArr[0] == 'chatWay') {
					$customerList = $customerList->andWhere(['wf.chat_way_id' => $wayArr[1]]);
				} elseif ($wayArr[0] == 'fission') {
					$customerList = $customerList->andWhere(['wf.fission_id' => $wayArr[1]]);
				} elseif ($wayArr[0] == 'award') {
					$customerList = $customerList->andWhere(['wf.award_id' => $wayArr[1]]);
				} elseif ($wayArr[0] == 'redPack') {
					$customerList = $customerList->andWhere(['wf.red_pack_id' => $wayArr[1]]);
				}
			}

			//回收时间
			if (!empty($sDate) && !empty($eDate)) {
				$sTime        = strtotime($sDate);
				$eTime        = strtotime($eDate . ':59');
				$customerList = $customerList->andWhere(['between', 'sc.reclaim_time', $sTime, $eTime]);
			}
			//实际客户数
			$realCustomer = clone $customerList;
			$realCount    = $realCustomer->groupBy('sc.external_userid')->count();

			$customerList = $customerList->groupBy('sc.id');
			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->select('sc.id,sc.user_id,sc.external_userid,sc.follow_user_id,sc.reclaim_time,sc.reclaim_rule,we.external_userid as externaluserid,we.name,we.avatar,we.corp_name,wf.userid,wf.del_type,wf.add_way,wf.way_id,wf.baidu_way_id,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id');
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->asArray()->all();
			$customerData = [];
			foreach ($customerList as $customer) {
				$key                                  = $customer['id'];
				$reclaimTime                          = !empty($customer['reclaim_time']) ? date('Y-m-d H:i', $customer['reclaim_time']) : '--';
				$customerData[$key]['id']             = $key;
				$customerData[$key]['key']            = $key;
				$customerData[$key]['reclaim_time']   = $reclaimTime;
				$customerData[$key]['reclaim_rule']   = $customer['reclaim_rule'];
				$customerData[$key]['user_id']        = $customer['user_id'];
				$customerData[$key]['follow_user_id'] = $customer['follow_user_id'];
				$customerData[$key]['externaluserid'] = $customer['externaluserid'];
				$customerData[$key]['userid']         = $customer['userid'];
				//客户信息
				$customerInfo                       = [];
				$customerInfo['name']               = rawurldecode($customer['name']);
				$customerInfo['avatar']             = $customer['avatar'];
				$customerInfo['corp_name']          = $customer['corp_name'];
				$fieldValue                         = CustomFieldValue::findOne(['type' => 1, 'cid' => $customer['external_userid'], 'fieldid' => $fieldInfo->id]);
				$customerInfo['gender']             = !empty($fieldValue) ? $fieldValue->value : '';
				$customerData[$key]['customerInfo'] = $customerInfo;
				//归属成员
				$workUser                     = WorkUser::findOne($customer['user_id']);
				$departName                   = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$customerData[$key]['member'] = $workUser->name . '--' . $departName;
				//提示
				$claimTip  = '';
				$isDisplay = 1;
				//判断是否已加过
				$tempFollowUser = WorkExternalContactFollowUser::findOne(['external_userid' => $customer['external_userid'], 'user_id' => $userId, 'del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				if (!empty($tempFollowUser)) {
					$claimTip = '已加过此客户，无需再认领！';
				} elseif ($customer['del_type'] == 1) {
					$claimTip  = '客户已被员工删除，认领无效';
					$isDisplay = 0;
				} elseif ($customer['del_type'] == 2) {
					$claimTip  = '员工已被客户删除/拉黑，认领无效';
					$isDisplay = 0;
				} elseif ($customer['user_id'] == $userId) {
					$claimTip = '已加过此客户，无需再认领！';
				} else {
					$seaIdData[$key] = ['user_id' => $customer['user_id'], 'external_id' => $customer['external_userid']];
				}
				$customerData[$key]['claimTip']  = $claimTip;
				$customerData[$key]['isDisplay'] = $isDisplay;
			}
			if (!empty($seaIdData)) {
				$userIdArr   = array_column($seaIdData, 'user_id');
				$externalIds = array_column($seaIdData, 'external_id');
				//认领成员是否加过次用户
				$followUser      = WorkExternalContactFollowUser::find()->where(['user_id' => $userId, 'external_userid' => $externalIds, 'del_type' => WorkExternalContactFollowUser::WORK_CON_EX])->select('external_userid')->all();
				$claimExternalId = array_column($followUser, 'external_userid');
				//归属成员是否已删除
				$workUser  = WorkUser::find()->where(['id' => $userIdArr, 'is_del' => 1])->select('id')->all();
				$delUserId = array_column($workUser, 'id');
				foreach ($seaIdData as $sk => $sv) {
					if (in_array($sv['external_id'], $claimExternalId)) {
						$customerData[$sk]['claimTip'] = '已加过此客户，无需再认领！';
						unset($seaIdData[$sk]);
					} elseif (in_array($sv['user_id'], $delUserId)) {
						$customerData[$sk]['claimTip']  = '原归属员工已离职，认领无效！';
						$customerData[$sk]['isDisplay'] = 0;
						unset($seaIdData[$sk]);
					}
				}
				$externalIds = array_column($seaIdData, 'external_id');
				if (!empty($externalIds)) {
					//查询是否已认领但还没加,原归属成员与客户是否删除
					$claimUser = PublicSeaClaimUser::find()->alias('cu');
					$claimUser = $claimUser->leftJoin('{{%work_external_contact_follow_user}} wf', 'cu.old_follow_user_id=wf.id');
					$claimUser = $claimUser->where(['cu.corp_id' => $corpId, 'cu.external_userid' => $externalIds, 'cu.new_user_id' => $userId, 'wf.del_type' => WorkExternalContactFollowUser::WORK_CON_EX, 'cu.new_follow_user_id' => 0]);
					$claimUser = $claimUser->select('cu.external_userid')->all();
					if (!empty($claimUser)) {
						$claimExternalId = array_column($claimUser, 'external_userid');
						foreach ($seaIdData as $sk => $sv) {
							if (in_array($sv['external_id'], $claimExternalId)) {
								$customerData[$sk]['claimTip'] = '已认领过此客户，无需再认领！';
								unset($seaIdData[$sk]);
							}
						}
					}
				}
			}
			$customerData = array_values($customerData);

			return ['count' => $count, 'realCount' => $realCount, 'customerData' => $customerData, 'show' => $show, 'is_master' => $isMaster];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           企微客户认领列表
		 * @description     企微客户认领列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/claim-user
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 string 企业微信id
		 * @param user_id          必选 int 员工ID
		 * @param name             可选 string 搜索姓名
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param page_size        可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionClaimUser ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', 0);
			$name     = \Yii::$app->request->post('name', '');
			$sDate    = \Yii::$app->request->post('s_date', '');
			$eDate    = \Yii::$app->request->post('e_date', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 10);
			$name     = trim($name);
			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			//可见范围
			$userData = AuthoritySubUserDetail::getUserIds($userId, $uid, $corpId, []);
			$show     = $userData['show'];

			$customerList = PublicSeaClaimUser::find()->alias('scu');
			$customerList = $customerList->leftJoin('{{%work_external_contact}} we', 'we.id=scu.external_userid');
			$customerList = $customerList->where(['scu.corp_id' => $corpId]);

			//姓名
			if ($name !== '') {
				$customerList = $customerList->andWhere(['like', 'we.name_convert', $name]);
			}
			//回收时间
			if (!empty($sDate) && !empty($eDate)) {
				$sTime        = strtotime($sDate);
				$eTime        = strtotime($eDate . ':59');
				$customerList = $customerList->andWhere(['between', 'scu.add_time', $sTime, $eTime]);
			}

			if (!empty($userData['user_ids'])) {
				$customerList = $customerList->andWhere(['scu.new_user_id' => $userData['user_ids']]);
			}
			//实际客户数
			$realCustomer = clone $customerList;
			$realCount    = $realCustomer->groupBy('scu.external_userid')->count();

			$customerList = $customerList->groupBy('scu.id');
			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['scu.id' => SORT_DESC])->all();
			$customerData = [];
			foreach ($customerList as $customer) {
				$customerInfo       = $customer->dumpData();
				$customerInfo['id'] = (string) $customerInfo['id'];
				array_push($customerData, $customerInfo);
			}

			return ['count' => $count, 'realCount' => $realCount, 'customerData' => $customerData, 'show' => $show];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微客户公海池
		 * @description     非企微客户公海池
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/no-customer
		 *
		 * @param uid              必选 int 用户ID
		 * @param corp_id          必选 string 企业微信id
		 * @param user_id          必选 int 员工ID
		 * @param name             可选 string 搜索姓名
		 * @param option_id        可选 string 来源
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param page_size        可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:29
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', 0);
			$search   = \Yii::$app->request->post('name', '');
			$optionId = \Yii::$app->request->post('option_id', 0);
			$sDate    = \Yii::$app->request->post('s_date', '');
			$eDate    = \Yii::$app->request->post('e_date', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 10);
			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			$userId = $workUser->id;

			$customerList = PublicSeaCustomer::find()->alias('sc');
			$customerList = $customerList->where(['sc.uid' => $uid, 'sc.is_claim' => 0, 'sc.type' => 0]);

			if (!empty($search)) {
				$customerList = $customerList->andWhere(['like', 'sc.name', $search]);
			}

			if (!empty($optionId)) {
				$customerList = $customerList->andWhere(['sc.field_option_id' => $optionId]);
			}

			if (!empty($sDate) && !empty($eDate)) {
				$sTime        = strtotime($sDate);
				$eTime        = strtotime($eDate . ':59');
				$customerList = $customerList->andWhere(['between', 'sc.add_time', $sTime, $eTime]);
			}

			$otherData = ['corp_id' => $corpId];
			$reClaim   = PublicSeaReclaimSet::getClaimRule($corpId, $userId);
			if (!empty($reClaim)) {
				$otherData['user_id']     = $userId;
				$otherData['reclaim_day'] = $reClaim->reclaim_day;
			}

			$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
			if (!empty($subUser)) {
				$isMaster = !empty($subUser->type) ? 1 : 0;
				$subId    = !empty($subUser->type) ? 0 : $subUser->sub_id;
			} else {
				$isMaster = $subId = 0;
			}

			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->all();
			$customerData = [];
			foreach ($customerList as $customer) {
				$customerInfo = $customer->dumpData(0, $otherData);
				$canEdit      = 0;
				if ($isMaster == 1) {
					$canEdit = 1;
				} elseif (!empty($subId) && ($subId == $customerInfo['sub_id'])) {
					$canEdit = 1;
				}
				$customerInfo['can_edit'] = $canEdit;
				$customerInfo['id'] = (string)$customerInfo['id'];
				array_push($customerData, $customerInfo);
			}

			//来源列表
			$customField = CustomField::findOne(['uid' => 0, 'type' => 2, 'key' => 'offline_source']);
			$optionList  = CustomFieldOption::find()->where(['uid' => 0, 'fieldid' => $customField->id])->select('id,match')->all();

			return ['count' => $count, 'customerData' => $customerData, 'userId' => $userId, 'optionList' => $optionList];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微客户详情
		 * @description     非企微客户详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/no-customer-detail
		 *
		 * @param corp_id         必选 string 企业微信ID
		 * @param sea_id          必选 int 非企微客户ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:34
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoCustomerDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid   = \Yii::$app->request->post('uid', 0);
			$seaId = \Yii::$app->request->post('sea_id', 0);
			if (empty($uid) || empty($seaId) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$customer = PublicSeaCustomer::findOne($seaId);
			if (empty($customer)) {
				throw new InvalidDataException('参数不正确！');
			}

			$uid            = $customer->uid;
			$data           = [];
			$data['name']   = $customer->name;
			$data['wx_num'] = $customer->wx_num;
			$data['phone']  = $customer->phone;
			$data['qq']     = $customer->qq;
			$data['remark'] = $customer->remark;

			$optionInfo                = CustomFieldOption::findOne(['id' => $customer->field_option_id]);
			$data['field_option_id']   = $customer->field_option_id;
			$data['field_option_name'] = !empty($optionInfo) ? $optionInfo->match : '';
			//查高级属性
			$sex       = $area = $email = $company = '';
			$fieldList = CustomField::find()->where(['uid' => 0, 'key' => ['sex', 'area', 'email', 'company']])->all();
			/**@var CustomField $field * */
			foreach ($fieldList as $field) {
				$fieldId = $field->id;
				if ($field->key == 'sex') {
					$fieldValue = CustomFieldValue::findOne(['uid' => $uid, 'type' => 4, 'cid' => $seaId, 'fieldid' => $fieldId]);
					$sex        = !empty($fieldValue) ? $fieldValue->value : '';
				} elseif ($field->key == 'area') {
					$fieldValue = CustomFieldValue::findOne(['uid' => $uid, 'type' => 4, 'cid' => $seaId, 'fieldid' => $fieldId]);
					$area       = !empty($fieldValue) ? $fieldValue->value : '';
				} elseif ($field->key == 'email') {
					$fieldValue = CustomFieldValue::findOne(['uid' => $uid, 'type' => 4, 'cid' => $seaId, 'fieldid' => $fieldId]);
					$email      = !empty($fieldValue) ? $fieldValue->value : '';
				} elseif ($field->key == 'company') {
					$fieldValue = CustomFieldValue::findOne(['uid' => $uid, 'type' => 4, 'cid' => $seaId, 'fieldid' => $fieldId]);
					$company    = !empty($fieldValue) ? $fieldValue->value : '';
				}
			}
			$data['gender']  = $sex;
			$data['area']    = $area;
			$data['email']   = $email;
			$data['company'] = $company;
			//获取标签
			$tagData = [];
			if (!empty($corpId)) {
				$tagData = PublicSeaPrivateTag::getTagBySeaId($corpId, $seaId);
			}
			$data['tagData'] = $tagData;

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微客户录入
		 * @description     非企微客户录入
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/set-no-customer
		 *
		 * @param uid               必选 int 用户ID
		 * @param corp_id           必选 string 企业微信ID
		 * @param user_id           必选 int 员工ID
		 * @param name              必选 string 姓名
		 * @param wx_num            必选 string 微信号
		 * @param phone             必选 string 手机号
		 * @param qq                可选 string QQ
		 * @param remark            可选 string 备注
		 * @param field_option_id   必选 string 来源
		 * @param sex               可选 string 性别
		 * @param area              可选 string 区域
		 * @param email             可选 string 邮箱
		 * @param tag_ids           可选 array 标签id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:35
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSetNoCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', '');
			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (empty($postData['field_option_id'])) {
				throw new InvalidDataException('请选择客户来源！');
			}

			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile, 'status' => 1]);
			$subId   = 0;
			if (!empty($subUser)) {
				$subId = !empty($subUser->type) ? 0 : $subUser->sub_id;
			}
			$postData['sub_id']  = $subId;
			$postData['corp_id'] = $corpId;
			PublicSeaCustomer::setData($postData);

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微认领录入
		 * @description     非企微认领录入
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/set-follow-user
		 *
		 * @param uid               必选 int 用户ID
		 * @param corp_id           必选 string 企业微信ID
		 * @param user_id           必选 int 员工ID
		 * @param name              必选 string 姓名
		 * @param wx_num            必选 string 微信号
		 * @param phone             必选 string 手机号
		 * @param qq                可选 string QQ
		 * @param remark            可选 string 备注
		 * @param field_option_id   必选 string 来源
		 * @param sex               可选 string 性别
		 * @param area              可选 string 区域
		 * @param email             可选 string 邮箱
		 * @param tag_ids           可选 array 标签id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:38
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSetFollowUser ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', '');
			$tagIds   = \Yii::$app->request->post('tag_ids', []);
			if (!empty($tagIds) && !is_array($tagIds)) {
				$tagIds = explode(',', $tagIds);
			}
			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (empty($postData['field_option_id'])) {
				throw new InvalidDataException('请选择客户来源！');
			}

			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			$userId  = $workUser->id;
			$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile, 'status' => 1]);
			$subId   = 0;
			if (!empty($subUser)) {
				$subId = !empty($subUser->type) ? 0 : $subUser->sub_id;
			}
			$postData['sub_id']   = $subId;
			$postData['corp_id']  = $corpId;
			$postData['is_claim'] = 1;
			$time                 = time();
			// 事务处理
			$transaction = \Yii::$app->mdb->beginTransaction();
			try {
				$seaId = PublicSeaCustomer::setData($postData);
				//创建领取记录
				$claimData                  = new PublicSeaClaim();
				$claimData->uid             = $uid;
				$claimData->corp_id         = $corpId;
				$claimData->sea_id          = $seaId;
				$claimData->type            = 0;
				$claimData->claim_type      = 1;
				$claimData->user_id         = $userId;
				$claimData->external_userid = '';
				$claimData->claim_time      = $time;
				if (!$claimData->validate() || !$claimData->save()) {
					throw new InvalidDataException(SUtils::modelError($claimData));
				}
				//创建非企微关联表
				$followUser                   = new PublicSeaContactFollowUser();
				$followUser->close_rate       = 0;
				$followUser->description      = '';
				$followUser->follow_id        = Follow::getFollowIdByUid($uid);
				$followUser->last_follow_time = $time;
				$followUser->add_time         = $time;
				$followUser->corp_id          = $corpId;
				$followUser->sea_id           = $seaId;
				$followUser->user_id          = $userId;
				$followUser->is_reclaim       = 0;
				$followUser->company_name     = !empty($postData['company']) ? $postData['company'] : '';
				if (!$followUser->validate() || !$followUser->save()) {
					throw new InvalidDataException(SUtils::modelError($followUser));
				}
				$transaction->commit();

				//打标签
				if (!empty($tagIds)) {
					PublicSeaTag::addUserTag([$followUser->id], $tagIds);
				}
			} catch (InvalidDataException $e) {
				$transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微详情
		 * @description     非企微详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-detail
		 *
		 * @param uid           必选 string 用户ID
		 * @param now_userid    必选 string 当前员工ID
		 * @param cid           必选 string 非企微ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:43
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			$nowUserId    = \Yii::$app->request->post('now_userid', '');
			if (empty($uid) || empty($this->corp) || empty($followUserId) || empty($nowUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId     = $this->corp->id;
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}

			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			$userInfo     = UserProfile::findOne(['uid' => $uid]);

			$result               = [];
			$result['avatar']     = '';
			$result['name']       = $customerInfo->name;
			$result['nickname']   = $customerInfo->remark;
			$result['phone']      = $customerInfo->phone;
			$result['close_rate'] = $followUser->close_rate;
			$result['des']        = $followUser->description;

			//可见范围
			$userData       = AuthoritySubUserDetail::getUserIds($nowUserId, $uid, $corpId, []);
			$result['show'] = $userData['show'];

			//查询保护按钮是否显示
			$isShow      = $isRest = 0;
			$nowWorkUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $nowUserId]);
			if (!empty($nowWorkUser)) {
				$subUser = SubUser::findOne(['uid' => $uid, 'account' => $nowWorkUser->mobile, 'status' => 1]);
				if (!empty($subUser)) {
					if (!empty($subUser->type)) {
						$isShow = $isRest = 1;
					} else {
						$protectData = PublicSeaProtect::getProtectBySubId($corpId, $subUser->id);
						$isShow      = $protectData['is_show'];
						$isRest      = $protectData['is_rest'];
					}
				} else {
					$protectData = PublicSeaProtect::getProtectByUserId($corpId, $nowWorkUser->id);
					$isShow      = $protectData['is_show'];
					$isRest      = $protectData['is_rest'];
				}
			}
			$result['is_show']        = $isShow;
			$result['is_rest']        = $isRest;
			$result['is_protect']     = (int) $followUser->is_protect;
			$result['follow_user_id'] = $followUser->id;

			//跟进状态
			if (!empty($followUser->follow_id)) {
				$follow_id     = $followUser->follow_id;
				$followInfo    = Follow::findOne($follow_id);
				$follow_title  = $followInfo->title;
				$is_follow_del = 0;
				if ($followInfo->status == 0) {
					$follow_id     = $followInfo->id;
					$follow_title  .= '（已删除）';
					$is_follow_del = 1;
				}
				$result['follow_id']     = $follow_id;
				$result['follow_title']  = $follow_title;
				$result['is_follow_del'] = $is_follow_del;
			} else {
				$result['follow_id']     = '';
				$result['follow_title']  = '';
				$result['is_follow_del'] = 0;
			}

			//跟进信息
			$followRecord          = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1])->select('`sub_id`,`user_id`,`add_time`')->orderBy('id DESC')->asArray()->one();
			$result['follow_time'] = '';//上次跟进时间
			if (!empty($followRecord)) {
				if (!empty($followRecord['user_id'])) {
					$userInfo = WorkUser::findOne($followRecord['user_id']);
					$name     = $userInfo->name;
				} elseif (!empty($followRecord['sub_id'])) {
					$subInfo = SubUserProfile::findOne(['sub_user_id' => $followRecord['sub_id']]);
					$name    = $subInfo->name;
				} else {
					$name = $userInfo->nick_name;
				}

				$time                  = !empty($followRecord['add_time']) ? date('Y-m-d H:i:s', $followRecord['add_time']) : '';
				$result['follow_time'] = $name . ' ' . $time;
			}
			//联系次数
			$followCount          = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1])->count();
			$result['follow_num'] = $followCount;

			//认领记录
			$claimData            = PublicSeaClaim::getClaimData($followUser->sea_id, $followUser->corp_id);
			$result['memberInfo'] = $claimData;

			//标签
			$tagData = PublicSeaTag::find()->alias('st')->leftJoin('{{%work_tag}} wt', 'st.tag_id=wt.id');
			$tagData = $tagData->where(['st.corp_id' => $followUser->corp_id, 'st.follow_user_id' => $followUser->id, 'st.status' => 1, 'wt.is_del' => 0])->select('wt.id,wt.tagname')->asArray()->all();
			$tagName = [];
			foreach ($tagData as $k => $tag) {
				$workTagD            = [];
				$workTagD['id']      = (int) $tag['id'];
				$workTagD['tagname'] = $tag['tagname'];
				$tagName[]           = $workTagD;
			}
			$result['tag_name'] = $tagName;
			//自定义属性
			$fieldList = CustomField::getCustomField($uid, $followUser->sea_id, 4);
			$area      = '';
			$gender    = '';
			$company   = '';
			$hasArea   = 0;
			foreach ($fieldList as $k => $v) {
				if ($v['key'] == 'area') {
					$hasArea = 1;
					$area    = $v['value'];
				} elseif ($v['key'] == 'sex') {
					if ($v['value'] == '男') {
						$gender = '男性';
					} elseif ($v['value'] == '女') {
						$gender = '女性';
					} elseif ($v['value'] == '未知') {
						$gender = '未知';
					}
				} elseif ($v['key'] == 'company') {
					$company = $v['value'];
				}
			}
			if ($hasArea == 0) {
				$area        = '';
				$customField = CustomField::findOne(['uid' => 0, 'key' => 'area', 'is_define' => 0]);
				if (!empty($customField)) {
					$fieldValue = CustomFieldValue::findOne(['type' => 4, 'fieldid' => $customField->id, 'cid' => $followUser->sea_id]);
					$area       = !empty($fieldValue->value) ? $fieldValue->value : '';
				}
			}
			$result['area']        = $area;
			$result['gender']      = $gender;
			$result['company']     = !empty($company) ? $company : $followUser->company_name;
			$result['field_list']  = $fieldList;
			$result['external_id'] = $followUser->sea_id;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微跟进记录
		 * @description     非企微跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/follow-record
		 *
		 * @param uid           必选 int 用户ID
		 * @param corp_id       必选 string 企业微信ID
		 * @param user_id       必选 int 员工ID
		 * @param cid           必选 string 非企微ID
		 * @param page          可选 string 页码
		 * @param page_size     可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:46
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionFollowRecord ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$userId       = \Yii::$app->request->post('user_id', '');
			$followUserId = \Yii::$app->request->post('cid', 0);
			$page         = \Yii::$app->request->post('page', 1);
			$pageSize     = \Yii::$app->request->post('page_size', 15);
			if (empty($uid) || empty($userId) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}

			$userInfo = UserProfile::findOne(['uid' => $uid]);
			$offset   = ($page - 1) * $pageSize;

			$followRecord = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1]);
			$count        = $followRecord->count();
			$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`add_time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC]);
			$followRecord = $followRecord->asArray()->all();

			$followData = [];
			foreach ($followRecord as $k => $v) {
				$day      = !empty($v['add_time']) ? date('Y-m-d', $v['add_time']) : date('Y-m-d');
				$can_edit = 0;
				$name     = '';
				if (!empty($v['user_id']) && $v['is_master'] == 1) {
					$workUser = WorkUser::findOne($v['user_id']);
					if (!empty($workUser)) {
						$name     = $workUser->name;
						$can_edit = $workUser->userid == $userId ? 1 : 0;
					}
				} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
					$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
					if (!empty($subInfo)) {
						$name = $subInfo->name;
					}
				} else {
					if (!empty($userInfo)) {
						$name = $userInfo->nick_name;
					}
				}
				$info              = [];
				$info['id']        = $v['id'];
				$info['name']      = $name;
				$info['time']      = !empty($v['add_time']) ? date('H:i', $v['add_time']) : '';
				$info['file']      = !empty($v['file']) ? json_decode($v['file']) : [];
				$info['can_edit']  = $can_edit;
				$info['record']    = $v['record'];
				$info['follow_id'] = $v['follow_id'];
				$follow_status     = '';
				if (!empty($v['follow_id'])) {
					$follow        = Follow::findOne($v['follow_id']);
					$follow_status = $follow->title;
					if ($follow->status == 0) {
						$follow_status .= '（已删除）';
					}
				}
				$info['follow_status'] = $follow_status;

				if (!isset($followData[$day])) {
					$followData[$day] = ['date' => $day, 'data' => [$info]];
				} else {
					array_push($followData[$day]['data'], $info);
				}
			}
			$followData = array_values($followData);

			return [
				'count'        => $count,
				'followRecord' => $followData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           添加非企微跟进记录
		 * @description     添加非企微跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/follow-record-set
		 *
		 * @param uid           必选 int 用户ID
		 * @param corp_id       必选 string 企业微信ID
		 * @param follow_id     必选 int 跟进ID
		 * @param cid           必选 string 非企微ID
		 * @param record_id     可选 string 记录ID
		 * @param record        可选 string 记录内容
		 * @param file          可选 string 文件
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 17:52
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionFollowRecordSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$follow_id    = \Yii::$app->request->post('follow_id', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			$userId       = \Yii::$app->request->post('userid', '');
			$record_id    = \Yii::$app->request->post('record_id', 0);
			$record       = \Yii::$app->request->post('record', '');
			$file         = \Yii::$app->request->post('file', '');
			$record       = trim($record);
			if (empty($uid) || empty($userId) || empty($this->corp) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (empty($follow_id)) {
				throw new InvalidDataException('请选择跟进状态！');
			}
			$userInfo = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId]);
			if (empty($userInfo)) {
				throw new InvalidDataException('员工数据错误！');
			}
			if (empty($record) && empty($file)) {
				throw new InvalidDataException('跟进内容和附件至少要填写一个！');
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followInfo = Follow::findOne(['id' => $follow_id, 'status' => 1]);
			if (empty($followInfo)) {
				throw new InvalidDataException('跟进状态已被删除，请更换！');
			}
			$oldFollowId = $followUser->follow_id;

			$followUser->is_chat          = 1;
			$followUser->follow_id        = $follow_id;
			$followUser->last_follow_time = time();
			$followUser->save();

			//跟进记录
			$time = time();
			if (!empty($record_id)) {
				$followRecord              = PublicSeaContactFollowRecord::findOne($record_id);
				$followRecord->update_time = $time;
			} else {
				$followRecord            = new PublicSeaContactFollowRecord();
				$followRecord->uid       = $uid;
				$followRecord->sea_id    = $followUser->sea_id;
				$followRecord->user_id   = $userInfo->id;
				$followRecord->status    = 1;
				$followRecord->sub_id    = 0;
				$followRecord->add_time  = $time;
				$followRecord->is_master = 1;
			}
			$followRecord->record    = $record;
			$followRecord->file      = !empty($file) ? json_encode($file) : '';
			$followRecord->follow_id = $follow_id;
			if (!$followRecord->validate() || !$followRecord->save()) {
				throw new InvalidDataException(SUtils::modelError($followRecord));
			}

			//记录客户轨迹
			if (empty($record_id)) {
				//跟进次数
				$count                  = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1])->count();
				$followUser->follow_num = $count;
				$followUser->save();
				PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'sea_id' => $followUser->sea_id, 'user_id' => $userInfo->id, 'event' => 'follow', 'event_id' => $follow_id, 'related_id' => $followRecord->id, 'remark' => $count]);
			}

			//非企微客户修改跟进状态
			if ($oldFollowId > 0 && $oldFollowId != $follow_id) {
				WaitTask::publicTask($follow_id, 4, $corpId, $followUser->id);
				WaitCustomerTask::deleteData('', $followUser->sea_id, $oldFollowId, 1);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           修改非企微外部联系人字段
		 * @description     修改非企微外部联系人字段
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-update
		 *
		 * @param uid       必选 int 用户ID
		 * @param corp_id   必选 string 企业微信ID
		 * @param cid       必选 int 非企微外ID
		 * @param type      必选 string 修改类型：nickname昵称、des描述、close_rate预计成交率
		 * @param value     可选 string 修改值
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 20:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionCustomUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			$type         = \Yii::$app->request->post('type', '');
			$value        = \Yii::$app->request->post('value', '');
			if (empty($uid) || empty($followUserId) || empty($type)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, ['nickname', 'des', 'close_rate'])) {
				throw new InvalidDataException('参数不正确！');
			}

			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('非企微客户数据错误！');
			}

			$remark = '';
			switch ($type) {
				case 'nickname'://备注
					$customerInfo->remark = $value;
					$customerInfo->update();
					$remark = '备注名';
					break;
				case 'des':
					$followUser->description = $value;
					$followUser->update();
					$remark = '描述';
					break;
				case 'close_rate':
					if ($value < 0 || $value > 100) {
						throw new InvalidDataException('预计成交率不正确！');
					}
					$followUser->close_rate = $value;
					$followUser->update();
					$remark = '预计成交率';
					break;
			}

			//记录客户轨迹
			PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'user_id' => $followUser->user_id, 'sea_id' => $followUser->sea_id, 'event' => 'set_field', 'remark' => $remark]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           客户高级属性详情
		 * @description     客户高级属性详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-field-detail
		 *
		 * @param uid       必选 int 用户ID
		 * @param cid       必选 int 非企微外ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-28 14:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomFieldDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			if (empty($uid) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('非企微客户数据错误！');
			}
			//自定义属性
			$fieldList            = CustomField::getCustomField($uid, $followUser->sea_id, 4);
			$result               = [];
			$result['field_list'] = $fieldList;
			$result['des']        = $followUser->description;
			$result['phone']      = $customerInfo->phone;
			$result['company']    = $followUser->company_name;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           修改非企微高级属性
		 * @description     修改非企微高级属性
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-field-update
		 *
		 * @param uid       必选 int 用户ID
		 * @param cid       必选 int 非企微客户外部联系表ID
		 * @param fieldData 必选 array 高级属性数据
		 * @param des       可选 sting 描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 20:39
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws Throwable
		 * @throws \yii\db\Exception
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionCustomFieldUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			$fieldData    = \Yii::$app->request->post('fieldData', []);
			$des          = \Yii::$app->request->post('des', '');
			if (empty($uid) || empty($followUserId) || empty($fieldData)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('非企微客户数据错误！');
			}

			//检查数据
			$mustNum = 0;
			$isSet   = 0;
			foreach ($fieldData as $info) {
				$value = $info['value'];
				if ($info['key'] == 'name') {
					if (empty($value)) {
						throw new InvalidDataException('姓名不能为空！');
					} else {
						$customerInfo->name = $value;
					}
				}
				if ($info['key'] == 'company') {
					$followUser->company_name = $value;
				}
				if ($info['key'] == 'wx_num' || $info['key'] == 'phone' || $info['key'] == 'qq') {
					$isSet = 1;
					if ($info['key'] == 'wx_num') {
						$customerInfo->wx_num = $value;
					} elseif ($info['key'] == 'phone') {
						$customerInfo->phone = $value;
					} elseif ($info['key'] == 'qq') {
						$customerInfo->qq = $value;
					}
					if(!empty($value)){
						$mustNum = 1;
					}
				}
			}
			if (empty($mustNum) && !empty($isSet)) {
				throw new InvalidDataException('微信号/手机号/QQ号必须要填写一个！');
			}

			$cid         = $followUser->sea_id;
			$time        = time();
			$uptField    = '';
			$transaction = \Yii::$app->db->beginTransaction();
			try {
				foreach ($fieldData as $k => $v) {
					$fieldId = intval($v['fieldid']);
					$value   = is_array($v['value']) ? $v['value'] : trim($v['value']);
					if (empty($fieldId)) {
						throw new InvalidDataException('客户高级属性数据错误！');
					}
					$fieldValue = CustomFieldValue::findOne(['cid' => $cid, 'type' => 4, 'fieldid' => $fieldId]);
					if (empty($fieldValue)) {
						if (empty($value)) {
							continue;
						}
						$fieldValue          = new CustomFieldValue();
						$fieldValue->type    = 4;
						$fieldValue->cid     = $cid;
						$fieldValue->fieldid = $fieldId;
					} else {
						if ($value == $fieldValue->value) {
							continue;
						}
					}
					$fieldValue->uid  = $uid;
					$fieldValue->time = $time;
					if ($v['key'] == 'image') {
						$imgVal = json_decode($fieldValue->value, true);
						if ($imgVal == $value) {
							continue;
						}
						$value = json_encode($value);
					}

					if ($v['type'] == 6 && !empty($value)) {
						if (!preg_match("/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/", $value)) {
							throw new InvalidDataException('邮箱格式不正确！');
						}
					}
					$fieldValue->value = $value;
					if (!$fieldValue->save()) {
						throw new InvalidDataException(SUtils::modelError($fieldValue));
					}
					$uptField .= $fieldId . ',';
				}
				$transaction->commit();
			} catch (InvalidDataException $e) {
				$transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}
			//描述修改
			$des   = trim($des);
			$isDes = 0;
			if ($des != $followUser->description) {
				$isDes                   = 1;
				$followUser->description = $des;
			}
			$followUser->update();
			//修改非企微客户信息
			$customerInfo->update();

			//记录客户轨迹
			if (!empty($uptField) || !empty($isDes)) {
				$remark = !empty($isDes) ? '描述、' : '';
				if (!empty($uptField)) {
					$customField = CustomField::find()->where('id IN (' . trim($uptField, ',') . ')')->select('`title`')->asArray()->all();
					foreach ($customField as $v) {
						$remark .= $v['title'] . '、';
					}
				}
				PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'sea_id' => $cid, 'user_id' => $followUser->user_id, 'event' => 'set_field', 'remark' => substr($remark, 0, -1)]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           设置非企微客户标签
		 * @description     设置非企微客户标签
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-tags-set
		 *
		 * @param uid           必选 int 用户ID
		 * @param cid           必选 int 非企微客户外部联系表ID
		 * @param corp_id       必选 int 企业ID
		 * @param group_id      可选 int 分组id
		 * @param tagData       必选 array 客户标签
		 * @param tagData.tid   可选 int 标签ID
		 * @param tagData.tname 必选 string 标签名
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-28 14:43
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		public function actionCustomTagsSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$corpId       = \Yii::$app->request->post('corp_id', '');
			$followUserId = \Yii::$app->request->post('cid', 0);
			$groupId      = \Yii::$app->request->post('group_id', 0);
			$tagData      = \Yii::$app->request->post('tagData', []);
			$nowUserId    = \Yii::$app->request->post('now_userid', '');
			if (empty($uid) || empty($corpId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corpId]);
			if (empty($workCorp)) {
				throw new InvalidDataException('企业微信数据错误！');
			}
			if ($workCorp->corp_type != 'verified') {
				throw new InvalidDataException('当前企业号未认证！');
			}
			$corpId     = $workCorp->id;
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			} elseif ($followUser->is_reclaim == 1) {
				throw new InvalidDataException('该客户已被回收！');
			}

			if (count($tagData) > 9999) {
				throw new InvalidDataException('客户标签数量不能超过9999个！');
			}
			$nowWorkUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $nowUserId]);
			$relatedId = !empty($nowWorkUser) ? $nowWorkUser->id : 0;

			$newTag = [];//新创建的标签
			$tagNow = [];//现有标签
			$tagOld = [];//客户原有标签

			$contactTag = PublicSeaTag::find()->where(['follow_user_id' => $followUser->id, 'status' => 1])->all();
			foreach ($contactTag as $k => $v) {
				array_push($tagOld, $v['tag_id']);
			}

			foreach ($tagData as $k => $v) {
				if (!empty($v['id'])) {
					array_push($tagNow, $v['id']);
				} else {
//					if (empty(trim($v['tname']))) {
//						throw new InvalidDataException('标签名称不能为空');
//					}
//					$len = mb_strlen($v['tname'], "utf-8");
//					if ($len > 15) {
//						throw new InvalidDataException('标签名称不能超过15个字');
//					}
//					array_push($newTag, $v['tname']);
				}
			}

			$tagAdd = array_diff($tagNow, $tagOld);//添加的客户标签
			$tagDel = array_diff($tagOld, $tagNow);//删除的客户标签

			//创建标签
			if (!empty($newTag)) {
				if (count($newTag) != count(array_unique($newTag))) {
					throw new InvalidDataException('标签名称存在重复');
				}
				if (empty($groupId)) {
					throw new InvalidDataException('请选择创建标签的分组！');
				}
				$tagName = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpId])->one();
				if (!empty($tagName)) {
					throw new InvalidDataException('创建标签名称与现有标签重复：' . $tagName->tagname);
				}

				WorkTag::add(0, $corpId, $newTag, 0, $groupId);

				$newTagData = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpId])->asArray()->all();
				if (count($newTagData) != count($newTag)) {
					throw new InvalidDataException('新建标签数据错误！');
				}
				//添加新建标签
				foreach ($newTagData as $v) {
					array_push($tagAdd, $v['id']);
				}
			}

			$userIds[] = $followUser->id;
			//添加客户标签
			if (!empty($tagAdd)) {
				PublicSeaTag::addUserTag($userIds, array_values($tagAdd), ['user_id' => $relatedId]);
			}

			//删除客户标签
			if (!empty($tagDel)) {
				PublicSeaTag::removeUserTag($userIds, array_values($tagDel), ['user_id' => $relatedId]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微客户互动轨迹
		 * @description     非企微客户互动轨迹
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-track
		 *
		 * @param uid              必选 int 用户ID
		 * @param cid              必选 int 非企微客户外部联系表ID
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 20:41
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomTrack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$followUserId = \Yii::$app->request->post('cid', 0);
			$page         = \Yii::$app->request->post('page', 1);
			$pageSize     = \Yii::$app->request->post('page_size', 15);
			if (empty($uid) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('非企微客户数据错误！');
			}
			$count    = PublicSeaTimeLine::find()->where(['sea_id' => $followUser->sea_id])->count();
			$offset   = ($page - 1) * $pageSize;
			$timeLine = PublicSeaTimeLine::find()->where(['sea_id' => $followUser->sea_id])->limit($pageSize)->offset($offset)->orderBy(['event_time' => SORT_DESC])->asArray()->all();

			$timeLineData = [];
			$seaLine      = PublicSeaTimeLine::getExternalTimeLine($uid, $timeLine);
			foreach ($seaLine as $line) {
				$day             = substr($line['event_time'], 0, 10);
				$info            = [];
				$info['time']    = substr($line['event_time'], 11, 5);
				$info['icon']    = $line['icon'];
				$info['content'] = $line['content'];

				if (!isset($timeLineData[$day])) {
					$timeLineData[$day] = ['date' => $day, 'data' => [$info]];
				} else {
					array_push($timeLineData[$day]['data'], $info);
				}
			}
			$timeLineData = array_values($timeLineData);

			return ['count' => $count, 'info' => $timeLineData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           非企微客户管理
		 * @description     非企微客户管理
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/custom-list
		 *
		 * @param uid           必选 string 主账户id
		 * @param corp_id       必选 string 企业的唯一ID
		 * @param user_id       必选 string 当前成员id
		 * @param user_ids      可选 array 搜索成员id
		 * @param name          可选 string 搜索关键词
		 * @param sex           可选 string 性别-1全部1男2女3未知
		 * @param follow_id     可选 int 跟进状态id
		 * @param tag_ids       可选 string 标签值（多标签用,分开）
		 * @param from          可选 int 1按人2按日期
		 * @param type          可选 int 0全部1今日新增2本周新增3本月新增4自定义
		 * @param start_time    可选 string 自定义开始时间
		 * @param end_time      可选 string 自定义结束时间
		 * @param status_id     可选 string 联系状态
		 * @param page          可选 string 页码，默认为1
		 * @param page_size     可选 string 每页数量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-26 20:43
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionCustomList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$userId   = \Yii::$app->request->post('user_id', '');
			$userIds  = \Yii::$app->request->post('user_ids', []);
			$from     = \Yii::$app->request->post('from', 1);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);

			if (empty($uid) || empty($this->corp) || empty($userId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId    = $this->corp->id;
			$userData  = AuthoritySubUserDetail::getUserIds($userId, $uid, $corpId, $userIds);
			$userIds   = $userData['user_ids'];
			$userCount = $userData['userCount'];
			$show      = $userData['show'];

			$whereData             = \Yii::$app->request->post();
			$whereData['user_ids'] = $userIds;

			$followUserData = PublicSeaContactFollowUser::find()->alias('fu');
			$followUserData = $followUserData->leftJoin('{{%public_sea_customer}} sc', 'fu.sea_id=sc.id');
			$followUserData = $followUserData->leftJoin('{{%work_user}} wu', 'wu.id=fu.user_id');
			$followUserData = $followUserData->where(['fu.corp_id' => $corpId, 'fu.is_reclaim' => 0]);

			$followUserData = PublicSeaContactFollowUser::getCondition($followUserData, $whereData);

			$offset    = ($page - 1) * $pageSize;
			$fieldInfo = CustomField::findOne(['uid' => 0, 'key' => 'sex']);
			if ($from == 1) {
				$count          = $followUserData->groupBy('fu.id')->count();
				$followUserData = $followUserData->select('fu.id,fu.add_time,fu.sea_id,fu.user_id,fu.follow_id,fu.last_follow_time,fu.is_reclaim,fu.is_protect,sc.name,wu.name user_name');
				$followUserData = $followUserData->limit($pageSize)->offset($offset)->orderBy(['fu.user_id' => SORT_DESC,'fu.add_time' => SORT_DESC])->asArray()->all();
				$resultData     = [];
				foreach ($followUserData as $followUser) {
					$info                         = [];
					$info['key']                  = $followUser['id'];
					$info['add_time']             = $followUser['add_time'];
					$info['customerInfo']['name'] = $followUser['name'];

					//性别
					$fieldValue                     = CustomFieldValue::findOne(['type' => 4, 'cid' => $followUser['sea_id'], 'fieldid' => $fieldInfo->id]);
					$info['customerInfo']['gender'] = !empty($fieldValue) ? $fieldValue->value : '未知';

					//沟通
					if ($followUser['last_follow_time'] == $followUser['add_time']) {
						$chat = '一直未沟通';
					} else {
						$chat = DateUtil::getDiffText($followUser['last_follow_time']) . '沟通';
					}
					$info['chat'] = $chat;

					//所属成员
//					$member     = $followUser['user_name'];
//					$departName = WorkDepartment::getDepartNameByUserId($followUser['user_id']);
//					if (!empty($departName)) {
//						$member = $departName . '--' . $member;
//					}
//					$info['memberInfo']['member'] = $member;

					//标签
					$tagData          = PublicSeaTag::find()->alias('st')->leftJoin('{{%work_tag}} wt', 'st.tag_id=wt.id');
					$tagData          = $tagData->where(['st.corp_id' => $corpId, 'st.follow_user_id' => $followUser['id'], 'st.status' => 1, 'wt.is_del' => 0])->select('tagname')->asArray()->all();
					$tagName          = array_column($tagData, 'tagname');
					$info['tag_name'] = $tagName;

					//跟进状态
					$follow_status = '';
					if (!empty($followUser['follow_id'])) {
						$followInfo = Follow::findOne($followUser['follow_id']);
						if (!empty($followInfo)) {
							$follow_status = $followInfo->title;
							if ($followInfo->status == 0) {
								$follow_status .= '（已删除）';
							}
						}
					}
					$info['follow_status'] = $follow_status;
					$info['is_protect']    = empty($followUser['is_reclaim']) && !empty($followUser['is_protect']) ? 1 : 0;

					$userIdKey = $followUser['user_id'];
					if (!isset($resultData[$userIdKey])) {
						$resultData[$userIdKey] = ['name' => $followUser['user_name'], 'id' => $userIdKey, 'info' => [$info]];
					} else {
						array_push($resultData[$userIdKey]['info'], $info);
					}
				}
				$resultData = array_values($resultData);
			} else {
				$count          = $followUserData->groupBy('fu.id')->count();
				$followUserData = $followUserData->select('fu.id,fu.add_time,fu.sea_id,fu.user_id,fu.follow_id,fu.last_follow_time,fu.is_reclaim,fu.is_protect,sc.name,wu.name user_name');
				$followUserData = $followUserData->limit($pageSize)->offset($offset)->orderBy(['fu.add_time' => SORT_DESC])->asArray()->all();
				$toDay          = date("Y-m-d");
				$yesterday      = date("Y-m-d", strtotime("-1 day"));
				$resultData     = [];
				foreach ($followUserData as $followUser) {
					$info             = [];
					$info['key']      = $followUser['id'];
					$info['add_time'] = $followUser['add_time'];
					$day              = date("Y-m-d", $followUser['add_time']);
					if ($day == $toDay) {
						$day = '今天';
					} elseif ($day == $yesterday) {
						$day = '昨天';
					}
					$info['customerInfo']['name'] = $followUser['name'];

					//性别
					$fieldValue                     = CustomFieldValue::findOne(['type' => 4, 'cid' => $followUser['sea_id'], 'fieldid' => $fieldInfo->id]);
					$info['customerInfo']['gender'] = !empty($fieldValue) ? $fieldValue->value : '未知';

					//沟通
					if ($followUser['last_follow_time'] == $followUser['add_time']) {
						$chat = '一直未沟通';
					} else {
						$chat = DateUtil::getDiffText($followUser['last_follow_time']) . '沟通';
					}
					$info['chat'] = $chat;

					//所属成员
					$member     = $followUser['user_name'];
					$departName = WorkDepartment::getDepartNameByUserId($followUser['user_id']);
					if (!empty($departName)) {
						$member = $departName . '--' . $member;
					}
					$info['memberInfo']['member'] = $member;

					//标签
					$tagData          = PublicSeaTag::find()->alias('st')->leftJoin('{{%work_tag}} wt', 'st.tag_id=wt.id');
					$tagData          = $tagData->where(['st.corp_id' => $corpId, 'st.follow_user_id' => $followUser['id'], 'st.status' => 1, 'wt.is_del' => 0])->select('tagname')->asArray()->all();
					$tagName          = array_column($tagData, 'tagname');
					$info['tag_name'] = $tagName;

					//跟进状态
					$follow_status = '';
					if (!empty($followUser['follow_id'])) {
						$followInfo = Follow::findOne($followUser['follow_id']);
						if (!empty($followInfo)) {
							$follow_status = $followInfo->title;
							if ($followInfo->status == 0) {
								$follow_status .= '（已删除）';
							}
						}
					}
					$info['follow_status'] = $follow_status;
					$info['is_protect']    = empty($followUser['is_reclaim']) && !empty($followUser['is_protect']) ? 1 : 0;
					if (!isset($resultData[$day])) {
						$resultData[$day] = ['name' => $day, 'info' => [$info]];
					} else {
						array_push($resultData[$day]['info'], $info);
					}
				}
				$resultData = array_values($resultData);
			}
			//来源
			$optionList  = [];
			$customField = CustomField::findOne(['uid' => 0, 'type' => 2, 'key' => 'offline_source']);
			if (!empty($customField)) {
				$optionList = CustomFieldOption::find()->where(['uid' => 0, 'fieldid' => $customField->id])->select('id,match')->all();
			}

			return [
				'user_count' => $userCount,
				'show'       => $show,
				'count'      => $count,
				'info'       => $resultData,
				'optionList' => $optionList,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           公海池客户指定分配、认领
		 * @description     公海池客户指定分配、认领
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/customer-assign
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param uid 必选 string 账户id
		 * @param user_id 必选 string 认领、分配成员id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param sea_id 必选 array 公海池客户id
		 * @param is_claim 可选 string 是否是认领操作
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-22 16:27
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomerAssign ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$type    = \Yii::$app->request->post('type', 0);
			$userId  = \Yii::$app->request->post('user_id', 0);
			$seaIds  = \Yii::$app->request->post('sea_id', []);
			$isClaim = \Yii::$app->request->post('is_claim', 0);
			if (empty($uid) || empty($this->corp) || empty($userId) || empty($seaIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if ($isClaim == 1) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
				if (empty($workUser)) {
					throw new InvalidDataException('员工数据错误！');
				}
				$userId = $workUser->id;
			}

			$otherData = ['is_claim' => $isClaim];
			if ($type == 0) {
				$textHtml = PublicSeaCustomer::noSeaAssign($uid, $corpId, $userId, $seaIds, $otherData);
			} else {
				$textHtml = PublicSeaCustomer::seaAssign($uid, $corpId, $userId, $seaIds, $otherData);
			}

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           客户转交
		 * @description     客户转交
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/transfer
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param user_id 必选 string 员工ID
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_user_id 必选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-20 15:44
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionTransfer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$userId       = \Yii::$app->request->post('user_id', '');
			$type         = \Yii::$app->request->post('type', 0);
			$followUserId = \Yii::$app->request->post('follow_user_id', '');
			if (empty($uid) || empty($this->corp) || empty($userId) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if ($type == 0) {
				$textHtml = PublicSeaCustomer::noSeaTransfer($uid, $corpId, $userId, $followUserId);
			} else {
				$textHtml = PublicSeaCustomer::seaTransfer($uid, $corpId, $userId, $followUserId);
			}

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           客户丢弃到公海池
		 * @description     客户丢弃到公海池
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/give-up
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_user_id 必选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-20 15:51
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGiveUp ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$type         = \Yii::$app->request->post('type', 0);
			$followUserId = \Yii::$app->request->post('follow_user_id', '');
			if (empty($uid) || empty($this->corp) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$textHtml = PublicSeaCustomer::giveUp($uid, $corpId, $type, $followUserId);

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           设置客户保护
		 * @description     设置客户保护
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/protect
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param user_id 必选 string 员工ID
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_user_id 必选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-20 16:04
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionProtect ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$corpId       = \Yii::$app->request->post('corp_id', '');
			$userId       = \Yii::$app->request->post('user_id', '');
			$type         = \Yii::$app->request->post('type', 0);
			$followUserId = \Yii::$app->request->post('follow_user_id', '');
			if (empty($uid) || empty($this->corp) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
			if (!empty($subUser)) {
				$isMaster = !empty($subUser->type) ? 0 : 1;
				$subId    = !empty($subUser->type) ? 0 : $subUser->sub_id;
			} else {
				$isMaster = $subId = 0;
			}
			$otherData = ['is_master' => $isMaster, 'uid' => $uid, 'sub_id' => $subId, 'corp_id' => $corpId, 'user_id' => $workUser->id, 'type' => $type];
			$textHtml  = PublicSeaProtect::protect($followUserId, $otherData);

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-public-sea/
		 * @title           取消客户保护
		 * @description     取消客户保护
		 * @method   post
		 * @url  http://{host_name}/api/wap-public-sea/no-protect
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param user_id 必选 string 员工ID
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_user_id 必选 string 外部联系人id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-20 16:28
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoProtect ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid          = \Yii::$app->request->post('uid', 0);
			$userId       = \Yii::$app->request->post('user_id', '');
			$type         = \Yii::$app->request->post('type', 0);
			$followUserId = \Yii::$app->request->post('follow_user_id', '');
			if (empty($uid) || empty($this->corp) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$workUser = WorkUser::findOne(['corp_id' => $corpId, 'userid' => $userId, 'is_del' => 0]);
			if (empty($workUser)) {
				throw new InvalidDataException('员工数据错误！');
			}
			$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
			if (!empty($subUser)) {
				$isMaster = !empty($subUser->type) ? 0 : 1;
				$subId    = !empty($subUser->type) ? 0 : $subUser->sub_id;
			} else {
				$isMaster = $subId = 0;
			}

			$otherData = ['is_master' => $isMaster, 'uid' => $uid, 'sub_id' => $subId, 'corp_id' => $corpId, 'user_id' => $workUser->id, 'type' => $type];

			$textHtml = PublicSeaProtect::noProtect($followUserId, $otherData);

			return ['textHtml' => $textHtml];
		}

	}