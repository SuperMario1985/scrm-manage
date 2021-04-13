<?php
	/**
	 * 公海池
	 * User: xcy
	 * Date: 2020-09-01
	 * Time: 14:00
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
	use app\models\PublicSeaTransferDetail;
	use app\models\SubUser;
	use app\models\SubUserProfile;
	use app\models\User;
	use app\models\UserCorpRelation;
	use app\models\UserProfile;
	use app\models\WaitCustomerTask;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use app\queue\WaitUserTaskJob;
	use app\queue\SyncWorkImportPsCustomerJob;
	use app\queue\SyncWorkImportPsCustomerCreateJob;
	use app\models\WaitTask;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use moonland\phpexcel\Excel;
	use dovechen\yii2\weWork\src\dataStructure\EContactGetTransferResult;

	class PublicSeaCustomerController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           企微客户公海池
		 * @description     企微客户公海池
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/customer
		 *
		 * @param corp_id          必选 string 企业微信id
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param search           可选 string 搜索姓名
		 * @param add_way          可选 string 来源
		 * @param way_id           可选 string 渠道id
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param pageSize         可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 11:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$name            = \Yii::$app->request->post('name', '');
			$user_ids        = \Yii::$app->request->post('user_ids');
			$add_way         = \Yii::$app->request->post('add_way', '-1');
			$way_id          = \Yii::$app->request->post('way_id', '');
			$sDate           = \Yii::$app->request->post('s_date', '');
			$eDate           = \Yii::$app->request->post('e_date', '');
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('pageSize', 15);
			$name            = trim($name);
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId = $this->corp->id;
			//获取当前帐号对应的员工
			if ($isMasterAccount == 1) {
				$userInfo = User::findOne($uid);
				$mobile   = !empty($userInfo) ? $userInfo->account : '';
			} else {
				$subUser = SubUser::findOne($sub_id);
				$mobile  = !empty($subUser) ? $subUser->account : '';
			}
			if (!empty($mobile)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'mobile' => $mobile, 'is_external' => 1, 'is_del' => 0]);
			}
			$otherData = [];
			$userId    = 0;
			if (!empty($workUser)) {
				$userId    = $workUser->id;
				$otherData = ['user_id' => $userId];
//				$reClaim = PublicSeaReclaimSet::getClaimRule($corpId, $userId);
//				if (!empty($reClaim)) {
//					$otherData = ['user_id' => $userId, 'reclaim_day' => $reClaim->reclaim_day];
//				}
			}
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
			if ($add_way != '-1') {
				if ($add_way === 'way') {
					$customerList = $customerList->andWhere(['>', 'wf.way_id', 0]);
				} elseif ($add_way === 'chatWay') {
					$customerList = $customerList->andWhere(['>', 'wf.chat_way_id', 0]);
				} elseif ($add_way === 'fission') {
					$customerList = $customerList->andWhere(['>', 'wf.fission_id', 0]);
				} elseif ($add_way === 'award') {
					$customerList = $customerList->andWhere(['>', 'wf.award_id', 0]);
				} elseif ($add_way === 'redPack') {
					$customerList = $customerList->andWhere(['>', 'wf.red_pack_id', 0]);
				} else {
					$customerList = $customerList->andWhere(['wf.add_way' => $add_way]);
				}
			}

			//活码搜索
			if (!empty($way_id)) {
				$wayArr = explode('_', $way_id);
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

			//上次认领成员
			if (!empty($user_ids)) {
				$customerList = $customerList->andWhere(['sc.user_id' => $user_ids]);
			}

			//回收时间
			if (!empty($sDate) && !empty($eDate)) {
				$sTime        = strtotime($sDate);
				$eTime        = strtotime($eDate . ':59');
				$customerList = $customerList->andWhere(['between', 'sc.reclaim_time', $sTime, $eTime]);
			}
			$customerList = $customerList->groupBy('sc.id');
			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->select('sc.id,sc.user_id,sc.external_userid,sc.follow_user_id,sc.reclaim_time,sc.reclaim_rule,we.name,we.avatar,we.corp_name,wf.del_type,wf.add_way,wf.way_id,wf.baidu_way_id,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id');
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->asArray()->all();
			//$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->all();
			$customerData = [];
			$seaIdData    = [];
			foreach ($customerList as $customer) {
				$key                                  = $customer['id'];
				$reclaimTime                          = !empty($customer['reclaim_time']) ? date('Y-m-d H:i', $customer['reclaim_time']) : '--';
				$customerData[$key]['id']             = (int) $key;
				$customerData[$key]['key']            = (int) $key;
				$customerData[$key]['reclaim_time']   = $reclaimTime;
				$customerData[$key]['reclaim_rule']   = $customer['reclaim_rule'];
				$customerData[$key]['user_id']        = $customer['user_id'];
				$customerData[$key]['follow_user_id'] = $customer['follow_user_id'];
				//客户信息
				$customerInfo                       = [];
				$customerInfo['name']               = rawurldecode($customer['name']);
				$customerInfo['avatar']             = $customer['avatar'];
				$customerInfo['corp_name']          = $customer['corp_name'];
				$fieldValue                         = CustomFieldValue::findOne(['type' => 1, 'cid' => $customer['external_userid'], 'fieldid' => $fieldInfo->id]);
				$customerInfo['gender']             = !empty($fieldValue) ? $fieldValue->value : '';
				$customerData[$key]['customerInfo'] = $customerInfo;
				//来源
				$otherData                            = [
					'add_way'      => $customer['add_way'],
					'way_id'       => $customer['way_id'],
					'baidu_way_id' => $customer['baidu_way_id'],
					'chat_way_id'  => $customer['chat_way_id'],
					'fission_id'   => $customer['fission_id'],
					'award_id'     => $customer['award_id'],
					'red_pack_id'  => $customer['red_pack_id'],
				];
				$addWay                               = PublicSeaCustomer::getAddWayById($otherData);
				$customerData[$key]['add_other_info'] = !empty($addWay) ? $addWay['add_other_info'] : '';
				$customerData[$key]['add_way_info']   = !empty($addWay) ? $addWay['add_way_info'] : '';
				$customerData[$key]['add_way_title']  = !empty($addWay) ? $addWay['add_way_title'] : '';
				//归属成员
				$workUser                     = WorkUser::findOne($customer['user_id']);
				$departName                   = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
				$customerData[$key]['member'] = $workUser->name . '--' . $departName;
				//提示
				$claimTip = '';
				$isDisplay = 1;
				//判断是否已加过
				if (!empty($userId)) {
					$tempFollowUser = WorkExternalContactFollowUser::findOne(['external_userid' => $customer['external_userid'], 'user_id' => $userId, 'del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				} else {
					$tempFollowUser = '';
				}
				if (!empty($tempFollowUser)) {
					$claimTip = '已加过此客户，无需再认领！';
				} elseif ($customer['del_type'] == 1) {
					$claimTip = '客户已被员工删除，无法认领';
					$isDisplay = 0;
				} elseif ($customer['del_type'] == 2) {
					$claimTip = '员工已被客户删除/拉黑，无法认领';
					$isDisplay = 0;
				} elseif ($customer['user_id'] == $userId) {
					$claimTip = '已加过此客户，无需再认领！';
				} else {
					$seaIdData[$key] = ['user_id' => $customer['user_id'], 'external_id' => $customer['external_userid']];
				}
				$customerData[$key]['claimTip'] = $claimTip;
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
						$customerData[$sk]['claimTip'] = '原归属员工已离职，但尚无新的接替成员时，无法认领！';
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

			return ['count' => $count, 'seaIdData' => $seaIdData, 'customerData' => $customerData, 'userId' => $userId];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           企微客户认领列表
		 * @description     企微客户认领列表
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/claim-user
		 *
		 * @param corp_id          必选 string 企业微信id
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param name             可选 string 搜索姓名
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param pageSize         可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 13:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionClaimUser ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid   = \Yii::$app->request->post('uid', 0);
			$name  = \Yii::$app->request->post('name', '');
			$sDate = \Yii::$app->request->post('s_date', '');
			$eDate = \Yii::$app->request->post('e_date', '');
			$name  = trim($name);
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId   = $this->corp->id;
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);

			if (isset($this->subUser->sub_id)) {
				$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
				if (is_array($user)) {
					$userIds = $user;
				}
				if ($user === false) {
					return ['count' => 0, 'customerData' => []];
				}
			}

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
			//成员
			if (!empty($userIds)) {
				$customerList = $customerList->andWhere(['scu.new_user_id' => $userIds]);
			}

			$customerList = $customerList->groupBy('scu.id');
			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['scu.id' => SORT_DESC])->all();
			$customerData = [];
			foreach ($customerList as $customer) {
				$customerInfo = $customer->dumpData();
				array_push($customerData, $customerInfo);
			}

			return ['count' => $count, 'customerData' => $customerData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           认领再次提醒
		 * @description     认领再次提醒
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/claim-remind
		 *
		 * @param corp_id         必选 string 企业微信id
		 * @param claim_user_id   必选 string 认领id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 13:39
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionClaimRemind ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$claimUserId = \Yii::$app->request->post('claim_user_id', 0);
			if (empty($this->corp) || empty($claimUserId)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$claimUser = PublicSeaClaimUser::findOne($claimUserId);
			if (empty($claimUser)) {
				throw new InvalidDataException('参数不正确！');
			}

			PublicSeaCustomer::claimSend($this->corp, $claimUser->old_follow_user_id, $claimUser->new_user_id);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户公海池
		 * @description     非企微客户公海池
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/no-customer
		 *
		 * @param corp_id          必选 string 企业微信id
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param search           可选 string 搜索姓名
		 * @param option_id        可选 string 来源
		 * @param s_date           可选 string 开始时间
		 * @param e_date           可选 string 结束时间
		 * @param page             可选 string 页码
		 * @param pageSize         可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 13:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoCustomer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$search          = \Yii::$app->request->post('name', '');
			$optionId        = \Yii::$app->request->post('option_id', 0);
			$sDate           = \Yii::$app->request->post('s_date', '');
			$eDate           = \Yii::$app->request->post('e_date', '');
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('pageSize', 10);
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId = $this->corp->id;

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
			//获取当前帐号对应的员工
			if ($isMasterAccount == 1) {
				$userInfo = User::findOne($uid);
				$mobile   = !empty($userInfo) ? $userInfo->account : '';
			} else {
				$subUser = SubUser::findOne($subId);
				$mobile  = !empty($subUser) ? $subUser->account : '';
			}
			if (!empty($mobile)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'mobile' => $mobile, 'is_external' => 1, 'is_del' => 0]);
			}
			$otherData = ['corp_id' => $corpId];
			$userId    = 0;
			if (!empty($workUser)) {
				$userId  = $workUser->id;
				$reClaim = PublicSeaReclaimSet::getClaimRule($corpId, $userId);
				if (!empty($reClaim)) {
					$otherData['user_id']     = $userId;
					$otherData['reclaim_day'] = $reClaim->reclaim_day;
				}
			}

			$count        = $customerList->count();
			$offset       = ($page - 1) * $pageSize;
			$customerList = $customerList->limit($pageSize)->offset($offset)->orderBy(['sc.id' => SORT_DESC])->all();
			$customerData = [];
			foreach ($customerList as $customer) {
				$customerInfo = $customer->dumpData(0, $otherData);
				$canEdit      = 0;
				if ($isMasterAccount == 1) {
					$canEdit = 1;
				} elseif (!empty($subId) && ($subId == $customerInfo['sub_id'])) {
					$canEdit = 1;
				}
				$customerInfo['can_edit'] = $canEdit;
				array_push($customerData, $customerInfo);
			}

			//来源列表
			$customField = CustomField::findOne(['uid' => 0, 'type' => 2, 'key' => 'offline_source']);
			$optionList  = CustomFieldOption::find()->where(['uid' => 0, 'fieldid' => $customField->id])->select('id,match')->all();

			return ['count' => $count, 'customerData' => $customerData, 'userId' => $userId, 'optionList' => $optionList];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户详情
		 * @description     非企微客户详情
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/no-customer-detail
		 *
		 * @param sea_id 必选 string 公海客户id
		 *
		 * @return          {"error":0,"data":{"name":"懒洋洋6381","wx_num":"","phone":"15811118602","qq":"","remark":"","field_option_name":"其他","sex":"女","area":"","email":""}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 姓名
		 * @return_param    wx_num string 微信号
		 * @return_param    phone string 手机号
		 * @return_param    qq string QQ
		 * @return_param    remark string 备注
		 * @return_param    field_option_name string 来源
		 * @return_param    sex string 性别
		 * @return_param    area string 区域
		 * @return_param    email string 邮箱
		 * @return_param    tagData array 标签数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 13:55
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoCustomerDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$seaId    = \Yii::$app->request->post('sea_id', 0);
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
			if (!empty($this->corp)) {
				$tagData = PublicSeaPrivateTag::getTagBySeaId($this->corp->id, $seaId);
			}
			$data['tagData'] = $tagData;

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户录入
		 * @description     非企微客户录入
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/set-no-customer
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
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
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 13:58
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
			if (empty($postData['field_option_id'])) {
				throw new InvalidDataException('请选择客户来源！');
			}
			$isMasterAccount     = \Yii::$app->request->post('isMasterAccount', 1);
			$subId               = \Yii::$app->request->post('sub_id', 0);
			$subId               = $isMasterAccount == 1 ? 0 : $subId;
			$postData['sub_id']  = $subId;
			$postData['corp_id'] = $this->corp->id;
			PublicSeaCustomer::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户导入
		 * @description     非企微客户导入
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/import-no-customer
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param importFile 必选 string 导入文件
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 导入结果
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionImportNoCustomer ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$tagIds          = \Yii::$app->request->post('tag_ids', []);
			$subId           = $isMasterAccount == 1 ? 0 : $subId;
			if (!empty($_FILES['importFile']['name'])) {
				$fileTypes = explode(".", $_FILES['importFile']['name']);
				$fileType  = $fileTypes[count($fileTypes) - 1];
				/*判别是不是.xls .xlsx文件，判别是不是excel文件*/
				if (strtolower($fileType) != "xls" && strtolower($fileType) != "xlsx") {
					throw new InvalidDataException('文件类型不对！');
				}
				$fileTmpPath = $_FILES['importFile']['tmp_name'];
				$excelData   = Excel::import($fileTmpPath, [
					'setFirstRecordAsKeys' => false
				]);

				$importData = $excelData[0];

				if (!empty($importData[1])) {
					$header  = $importData[1];
					$headerA = !empty($header['A']) ? $header['A'] : '';
					$headerB = !empty($header['B']) ? $header['B'] : '';
					$headerC = !empty($header['C']) ? $header['C'] : '';
					$headerD = !empty($header['D']) ? $header['D'] : '';
					$headerE = !empty($header['E']) ? $header['E'] : '';
					$headerF = !empty($header['F']) ? $header['F'] : '';
					$headerG = !empty($header['G']) ? $header['G'] : '';
					$headerH = !empty($header['H']) ? $header['H'] : '';
					$headerI = !empty($header['I']) ? $header['I'] : '';
					$headerJ = !empty($header['J']) ? $header['J'] : '';
					if ($headerA != '姓名' || $headerB != '微信号' || $headerC != '手机号' || $headerD != 'QQ' || $headerE != '性别' || $headerF != '地区' || $headerG != '邮箱' || $headerH != '备注' || $headerI != '线下客户来源' || $headerJ != '公司名称') {
						throw new InvalidDataException('导入数据格式不对，请检查标题是否与模版一致！');
					}
				} else {
					throw new InvalidDataException('导入数据格式不对，请检查标题是否与模版一致！');
				}
				$count = count($importData);
				if ($count < 2) {
					throw new InvalidDataException('请在文件内添加要导入的数据！');
				} else if ($count > 5001) {
					throw new InvalidDataException('单次导入客户最多5000条，请重新上传！');
				}

				try {
					$import               = [];
					$import['uid']        = $uid;
					$import['sub_id']     = $subId;
					$import['corp_id']    = $this->corp->id;
					$import['tag_ids']    = $tagIds;
					$import['importData'] = $importData;

					$jobId = \Yii::$app->work->push(new SyncWorkImportPsCustomerCreateJob([
						'import' => $import,
					]));

					/*$res                  = PublicSeaCustomer::create($import);
					$textHtml             = '本次';
					if (isset($res['insertNum'])) {
						$textHtml .= '导入成功' . $res['insertNum'] . '条，';
					}
					if (!empty($res['skipNum'])) {
						$textHtml .= '忽略' . $res['skipNum'] . '条（已有的），';
					}
					if (!empty($res['skipPhoneNum'])) {
						$textHtml .= $res['skipPhoneNum'] . '条格式不正确，';
					}
					$textHtml = trim($textHtml, '，');

					return ['textHtml' => $textHtml];*/

					return true;
				} catch (InvalidDataException $e) {
					throw new InvalidDataException($e->getMessage());
				}
			} else {
				throw new InvalidDataException('请上传文件！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微认领录入
		 * @description     非企微认领录入
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/set-follow-user
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param name              必选 string 姓名
		 * @param wx_num            必选 string 微信号
		 * @param phone             必选 string 手机号
		 * @param qq                可选 string QQ
		 * @param remark            可选 string 备注
		 * @param field_option_id   必选 string 来源
		 * @param sex               可选 string 性别
		 * @param area              可选 string 区域
		 * @param email             可选 string 邮箱
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:02
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
			$userId   = \Yii::$app->request->post('user_id', 0);
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;

			if (empty($postData['field_option_id'])) {
				throw new InvalidDataException('请选择客户来源！');
			}

			//是否有员工
			if (empty($userId)) {
				throw new InvalidDataException('当前帐号无关联的成员！');
			}
			$time                 = time();
			$tagIds               = \Yii::$app->request->post('tag_ids', []);
			$isMasterAccount      = \Yii::$app->request->post('isMasterAccount', 1);
			$subId                = \Yii::$app->request->post('sub_id', 0);
			$subId                = $isMasterAccount == 1 ? 0 : $subId;
			$postData['sub_id']   = $subId;
			$postData['corp_id']  = $corpId;
			$postData['is_claim'] = 1;

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
				//创建待办事项
				WaitTask::publicTask($followUser->follow_id, 2, $this->corp->id);

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
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微认领导入
		 * @description     非企微认领导入
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/import-follow-user
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param importFile 必选 string 导入文件
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 导入结果
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionImportFollowUser ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$subId           = $isMasterAccount == 1 ? 0 : $subId;
			$userId          = \Yii::$app->request->post('user_id', 0);
			$tagIds          = \Yii::$app->request->post('tag_ids', []);
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			//是否有员工
			if (empty($userId)) {
				throw new InvalidDataException('当前帐号无关联的成员！');
			}
			if (empty($_FILES['importFile']['name'])) {
				throw new InvalidDataException('请上传文件！');
			}
			$fileTypes = explode(".", $_FILES['importFile']['name']);
			$fileType  = $fileTypes[count($fileTypes) - 1];
			/*判别是不是.xls .xlsx文件，判别是不是excel文件*/
			if (strtolower($fileType) != "xls" && strtolower($fileType) != "xlsx") {
				throw new InvalidDataException('文件类型不对！');
			}
			$fileTmpPath = $_FILES['importFile']['tmp_name'];
			$excelData   = Excel::import($fileTmpPath, [
				'setFirstRecordAsKeys' => false
			]);

			$importData = $excelData[0];

			if (!empty($importData[1])) {
				$header  = $importData[1];
				$headerA = !empty($header['A']) ? $header['A'] : '';
				$headerB = !empty($header['B']) ? $header['B'] : '';
				$headerC = !empty($header['C']) ? $header['C'] : '';
				$headerD = !empty($header['D']) ? $header['D'] : '';
				$headerE = !empty($header['E']) ? $header['E'] : '';
				$headerF = !empty($header['F']) ? $header['F'] : '';
				$headerG = !empty($header['G']) ? $header['G'] : '';
				$headerH = !empty($header['H']) ? $header['H'] : '';
				$headerI = !empty($header['I']) ? $header['I'] : '';
				$headerJ = !empty($header['J']) ? $header['J'] : '';
				if ($headerA != '姓名' || $headerB != '微信号' || $headerC != '手机号' || $headerD != 'QQ' || $headerE != '性别' || $headerF != '地区' || $headerG != '邮箱' || $headerH != '备注' || $headerI != '线下客户来源' || $headerJ != '公司名称') {
					throw new InvalidDataException('导入数据格式不对，请检查标题是否与模版一致！');
				}
			} else {
				throw new InvalidDataException('导入数据格式不对，请检查标题是否与模版一致！');
			}
			$count = count($importData);
			if ($count < 2) {
				throw new InvalidDataException('请在文件内添加要导入的数据！');
			} else if ($count > 5001) {
				throw new InvalidDataException('单次导入客户最多5000条，请重新上传！');
			}
			try {
				$import               = [];
				$import['uid']        = $uid;
				$import['sub_id']     = $subId;
				$import['corp_id']    = $corpId;
				$import['user_id']    = $userId;
				$import['tag_ids']    = $tagIds;
				$import['importData'] = $importData;

				$jobId = \Yii::$app->work->push(new SyncWorkImportPsCustomerJob([
					'import' => $import,
				]));

				/*$res                  = PublicSeaCustomer::createFollowUser($import);
				$textHtml             = '本次';
				if (isset($res['insertNum'])) {
					$textHtml .= '导入成功' . $res['insertNum'] . '条，';
				}
				if (!empty($res['skipNum'])) {
					$textHtml .= '忽略' . $res['skipNum'] . '条（已有的），';
				}
				if (!empty($res['skipPhoneNum'])) {
					$textHtml .= $res['skipPhoneNum'] . '条格式不正确，';
				}
				$textHtml = trim($textHtml, '，');

				return ['textHtml' => $textHtml];*/

				return ['error' => 0];
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           我要认领
		 * @description     我要认领
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/claim
		 *
		 * @param corp_id    必选 string 企业微信id
		 * @param uid        必选 string 帐号ID
		 * @param user_id    必选 string 成员ID
		 * @param type       必选 string 类型：0非企微，1企微
		 * @param sea_id     必选 string 公海客户id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:04
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionClaim ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid    = \Yii::$app->request->post('uid', 0);
			$userId = \Yii::$app->request->post('user_id', 0);
			$type   = \Yii::$app->request->post('type', 0);
			$seaId  = \Yii::$app->request->post('sea_id', 0);
			if (empty($uid) || empty($this->corp) || empty($seaId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}

			//是否有员工
			if (empty($userId)) {
				throw new InvalidDataException('当前帐号无关联的成员！');
			}

			//查询是否已达到分配客户上限
			if ($type == 0) {
				$reClaim = PublicSeaReclaimSet::getClaimRule($corpId, $userId);
				if (!empty($reClaim)) {
					$count = PublicSeaClaim::find()->where(['corp_id' => $corpId, 'user_id' => $userId, 'claim_type' => 1, 'reclaim_time' => 0, 'type' => 0])->count();
					if ($reClaim->private_num <= $count) {
						throw new InvalidDataException('此成员已达认领上限，不能再认领！');
					}
				}
			}

			$customerInfo = PublicSeaCustomer::findOne($seaId);
			if (empty($customerInfo)) {
				throw new InvalidDataException('参数不正确！');
			}

			$time = time();
			if ($type == 1) {//企微客户
				//判断是否加过此客户
				$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $customerInfo->external_userid, 'user_id' => $userId, 'del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
				if (!empty($followUser)) {
					throw new InvalidDataException('已加过此客户，无需再认领！');
				} else {
					//判断是否存在回收记录
//					$claimInfo = PublicSeaClaim::find()->where(['type' => 1, 'claim_type' => 0, 'sea_id' => $seaId, 'user_id' => $userId])->orderBy(['id' => SORT_DESC])->one();
//					if (!empty($claimInfo)) {//再判断是否符合再次认领的条件
//
//					}
				}
			} else {//非企微客户
				$customerInfo->user_id = $userId;
				//判断是否加过此客户
//				$followUser = PublicSeaContactFollowUser::findOne(['sea_id' => $customerInfo->id, 'user_id' => $userId]);
//				if (!empty($followUser)) {
//					//判断是否存在回收记录
//					$claimInfo = PublicSeaClaim::find()->where(['type' => 0, 'claim_type' => 0, 'sea_id' => $seaId, 'user_id' => $userId])->orderBy(['id' => SORT_DESC])->one();
//					if (!empty($claimInfo)) {//再判断是否符合再次认领的条件
//
//					}
//				}
			}
			$customerInfo->is_claim    = 1;
			$customerInfo->update_time = $time;
			$customerInfo->update();
			//创建领取记录
			$claimData                  = new PublicSeaClaim();
			$claimData->uid             = $uid;
			$claimData->corp_id         = $corpId;
			$claimData->sea_id          = $seaId;
			$claimData->type            = $type;
			$claimData->claim_type      = 1;
			$claimData->user_id         = $userId;
			$claimData->external_userid = $customerInfo->external_userid;
			$claimData->claim_time      = $time;
			if (!$claimData->validate() || !$claimData->save()) {
				throw new InvalidDataException(SUtils::modelError($claimData));
			}

			//创建非企微客户关系
			if ($type == 0) {
				$followUser = PublicSeaContactFollowUser::findOne(['sea_id' => $customerInfo->id, 'user_id' => $userId]);
				$flag       = false;
				if (empty($followUser)) {
					$followUser              = new PublicSeaContactFollowUser();
					$followUser->close_rate  = 0;
					$followUser->description = '';
					$followUser->follow_id   = Follow::getFollowIdByUid($uid);
					$flag                    = true;
				} else {
					$followUser->update_time = $time;
				}
				$followUser->last_follow_time = $time;
				$followUser->add_time         = $time;
				$followUser->corp_id          = $corpId;
				$followUser->sea_id           = $customerInfo->id;
				$followUser->user_id          = $userId;
				$followUser->is_reclaim       = 0;
				//获取公司名称
				$companyInfo = CustomField::findOne(['uid' => 0, 'key' => 'company']);
				if (!empty($companyInfo)) {
					$fieldValue = CustomFieldValue::findOne(['type' => 4, 'fieldid' => $companyInfo->id, 'cid' => $customerInfo->id]);
				}
				$followUser->company_name = !empty($fieldValue) ? $fieldValue->value : '';
				if (!$followUser->validate() || !$followUser->save()) {
					throw new InvalidDataException(SUtils::modelError($followUser));
				}

				//打标签
				$tagData = PublicSeaPrivateTag::getTagBySeaId($corpId, $customerInfo->id);
				if (!empty($tagData)) {
					$tagIds = array_column($tagData, 'tid');
					PublicSeaTag::addUserTag([$followUser->id], $tagIds);
				}

				if ($flag) {
					//创建待办事项
					WaitTask::publicTask($followUser->follow_id, 2, $this->corp->id);
				}
			} else {
				$claimUser = PublicSeaClaimUser::findOne(['uid' => $uid, 'sea_id' => $customerInfo->id, 'old_follow_user_id' => $customerInfo->follow_user_id, 'new_user_id' => $userId]);
				if (empty($claimUser)) {
					$claimUser                     = new PublicSeaClaimUser();
					$claimUser->uid                = $uid;
					$claimUser->sea_id             = $customerInfo->id;
					$claimUser->corp_id            = $corpId;
					$claimUser->external_userid    = $customerInfo->external_userid;
					$claimUser->old_user_id        = $customerInfo->user_id;
					$claimUser->old_follow_user_id = $customerInfo->follow_user_id;
					$claimUser->new_user_id        = $userId;
					$claimUser->reclaim_rule       = $customerInfo->reclaim_rule;
					$claimUser->reclaim_time       = $customerInfo->reclaim_time;
					$claimUser->add_time           = $time;
					if (!$claimUser->validate() || !$claimUser->save()) {
						throw new InvalidDataException(SUtils::modelError($claimUser));
					}
				}
			}

			//企微客户
			if ($type == 1) {
				$isSend = 1;
				try {
					$workApi     = WorkUtils::getWorkApi($corpId, WorkUtils::EXTERNAL_API);
					$contactInfo = WorkExternalContact::findOne($customerInfo->external_userid);
					$oldWorkUser = WorkUser::findOne($customerInfo->user_id);
					$workUser    = WorkUser::findOne($userId);
					if (!empty($contactInfo) && !empty($oldWorkUser) && !empty($workUser)) {
						$externalUserId = $contactInfo->external_userid;
						$handoverUserId = $oldWorkUser->userid;
						$takeoverUserId = $workUser->userid;
						$result         = $workApi->ECTransfer($externalUserId, $handoverUserId, $takeoverUserId);
						if ($result['errcode'] == 0) {
							//分配成员24小时后查询,若还没添加则把状态置为拒绝
							PublicSeaClaimUser::updateStatusJob($claimUser->id);

							//添加分配记录
							$transfer                  = new PublicSeaTransferDetail();
							$transfer->uid             = $uid;
							$transfer->sea_id          = $customerInfo->id;
							$transfer->corp_id         = $corpId;
							$transfer->external_userid = $customerInfo->external_userid;
							$transfer->handover_userid = $customerInfo->user_id;
							$transfer->takeover_userid = $userId;
							$transfer->add_time        = $time;
							if (!$transfer->validate() || !$transfer->save()) {
								throw new InvalidDataException(SUtils::modelError($transfer));
							}
							$isSend = 0;
						}
					}
				} catch (\Exception $e) {
					\Yii::error($e->getMessage(), 'ECTransfer' . $customerInfo->id);
				}
				if (!empty($isSend)) {
					//发送消息
					if (!empty($customerInfo->follow_user_id)) {
						PublicSeaCustomer::claimSend($this->corp, $customerInfo->follow_user_id, $userId);
					}
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微详情
		 * @description     非企微详情
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/custom-detail
		 *
		 * @param uid  必选 int 用户ID
		 * @param cid  必选 int 客户ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    avatar string 头像
		 * @return_param    des string 描述
		 * @return_param    close_rate int 预计成交率
		 * @return_param    follow_time string 上次跟进时间
		 * @return_param    follow_num int 跟进次数
		 * @return_param    follow_status string 跟进状态：0未跟进1跟进中2已拒绝3已成交
		 * @return_param    phone string 手机号
		 * @return_param    area string 区域
		 * @return_param    memberInfo array 归属企业成员
		 * @return_param    memberInfo.member string 企业成员姓名
		 * @return_param    memberInfo.create_time string 归属时间
		 * @return_param    memberInfo.del_type int 删除类型
		 * @return_param    memberInfo.source string 渠道
		 * @return_param    tag_name array 标签
		 * @return_param    tag_name.tid int 标签id
		 * @return_param    tag_name.tname int 标签名称
		 * @return_param    field_list array 客户属性
		 * @return_param    field_list.fieldid int 属性ID
		 * @return_param    field_list.key string 属性key
		 * @return_param    field_list.title string 属性名称
		 * @return_param    field_list.type int 属性类型
		 * @return_param    field_list.optionVal string 属性选项
		 * @return_param    field_list.value string 已设置属性值
		 * @return_param    project array 待办项目
		 * @return_param    project.start_time string 项目开始时间
		 * @return_param    project.end_time string 项目结束时间
		 * @return_param    project.finish_time string 项目完成时间
		 * @return_param    project.name string 项目处理人
		 * @return_param    project.days string 项目完成天数
		 * @return_param    project.project_name string 项目名称
		 * @return_param    project.delay_days string 超时天数
		 * @return_param    project.pre_days string 提前天数
		 * @return_param    project.is_finish string 完成状态、0未完成1按时完成2超时完成3提前完成
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:09
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
			if (empty($uid) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}
			$userInfo = UserProfile::findOne(['uid' => $uid]);

			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('参数不正确！');
			}
			$userCorpRelation = UserCorpRelation::findOne(['uid' => $uid, 'corp_id' => $followUser->corp_id]);
			if (empty($userCorpRelation)) {
				throw new InvalidDataException('参数不正确！');
			}

			$result               = [];
			$result['avatar']     = '';
			$result['name']       = $customerInfo->name;
			$result['nickname']   = $customerInfo->remark;
			$result['phone']      = $customerInfo->phone;
			$result['close_rate'] = $followUser->close_rate;
			$result['des']        = $followUser->description;
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
				$workTagD          = [];
				$workTagD['tid']   = $tag['id'];
				$workTagD['tname'] = $tag['tagname'];
				$tagName[]         = $workTagD;
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
			$result['area']       = $area;
			$result['gender']     = $gender;
			$result['company']    = !empty($company) ? $company : $followUser->company_name;
			$result['field_list'] = $fieldList;

			$project           = WaitCustomerTask::getDetail(1, $followUser->sea_id, $uid, $followUser->follow_id);
			$result['project'] = $project;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微跟进记录
		 * @description     非企微跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/follow-record
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 客户ID
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    followRecord array 跟进记录
		 * @return_param    followRecord.id int 记录id
		 * @return_param    followRecord.record string 记录内容
		 * @return_param    followRecord.name string 记录人名称
		 * @return_param    followRecord.time string 记录时间
		 * @return_param    followRecord.can_edit int 是否可编辑1是0否
		 * @return_param    followRecord.file array 附件图片
		 * @return_param    followRecord.follow_status 跟进状态
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-14 14:11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionFollowRecord ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$followUserId    = \Yii::$app->request->post('cid', 0);
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('page_size', 15);
			if (empty($uid) || empty($followUserId) || empty($sub_id)) {
				throw new InvalidDataException('参数不正确！');
			}
			$sub_id     = $isMasterAccount == 1 ? 0 : $sub_id;
			$userInfo   = UserProfile::findOne(['uid' => $uid]);
			$offset     = ($page - 1) * $pageSize;
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			}
			$userId       = $followUser->user_id;
			$followRecord = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1]);
			$count        = $followRecord->count();
			$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`add_time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC]);
			$followRecord = $followRecord->asArray()->all();
			foreach ($followRecord as $k => $v) {
				$can_edit = 0;
				$name     = '';
				if (!empty($v['user_id']) && $v['is_master'] == 1) {
					$workUser = WorkUser::findOne($v['user_id']);
					if (!empty($workUser)) {
						$name = $workUser->name;
					}
				} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
					$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
					if (!empty($subInfo)) {
						$name = $subInfo->name;
					}
					$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
				} else {
					if (!empty($userInfo)) {
						$name = $userInfo->nick_name;
					}
					$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
				}
				if ($isMasterAccount == 2 && ($sub_id == $v['sub_id'] || $userId == $v['user_id']) && $v['is_master'] == 1) {
					$can_edit = 1;
				}
				$followRecord[$k]['name']     = $name;
				$followRecord[$k]['time']     = !empty($v['add_time']) ? date('Y-m-d H:i:s', $v['add_time']) : '';
				$followRecord[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
				$followRecord[$k]['can_edit'] = $can_edit;
				$follow_status                = '';
				if (!empty($v['follow_id'])) {
					$follow        = Follow::findOne($v['follow_id']);
					$follow_status = $follow->title;
					if ($follow->status == 0) {
						$follow_status .= '（已删除）';
					}
				}
				$followRecord[$k]['follow_status'] = $follow_status;
			}

			return [
				'count'        => $count,
				'followRecord' => $followRecord,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           添加非企微跟进记录
		 * @description     添加非企微跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/follow-record-set
		 *
		 * @param corp_id  必选 int 企业微信id
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 客户ID
		 * @param follow_id        必选 int 跟进状态id
		 * @param record_id        可选 int 记录ID
		 * @param record           可选 string 记录内容
		 * @param file             可选 array 图片附件链接
		 * @param tag_ids           可选 array 标签
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 18:02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionFollowRecordSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$follow_id       = \Yii::$app->request->post('follow_id', 0);
			$followUserId    = \Yii::$app->request->post('cid', 0);
			$record_id       = \Yii::$app->request->post('record_id', 0);
			$record          = \Yii::$app->request->post('record', '');
			$file            = \Yii::$app->request->post('file', '');
			$closeRate       = \Yii::$app->request->post('close_rate', '-1');
			$tagIds          = \Yii::$app->request->post('tag_ids', '');
			$record          = trim($record);
			if (empty($uid) || empty($followUserId) || empty($subId) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($follow_id)) {
				throw new InvalidDataException('请选择跟进状态！');
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
			if ($closeRate != '-1') {
				$followUser->close_rate = $closeRate;
			}
			$followUser->save();

			$userId = 0;
			//子账户
			if ($isMasterAccount == 2) {
				$subUser = SubUser::findOne($subId);
				if (!empty($subUser)) {
					$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'mobile' => $subUser->account, 'is_del' => 0]);
					if (!empty($workUser)) {
						$userId = $workUser->id;
					}
				}
			} else {
				$userId = $followUser->user_id;
			}
			//跟进记录
			$time = time();
			if (!empty($record_id)) {
				$followRecord              = PublicSeaContactFollowRecord::findOne($record_id);
				$followRecord->update_time = $time;
			} else {
				$followRecord            = new PublicSeaContactFollowRecord();
				$followRecord->uid       = $uid;
				$followRecord->sea_id    = $followUser->sea_id;
				$followRecord->user_id   = $userId;
				$followRecord->status    = 1;
				$followRecord->sub_id    = $isMasterAccount == 1 ? 0 : $subId;
				$followRecord->add_time  = $time;
				$followRecord->is_master = $isMasterAccount == 1 ? 0 : 1;
			}
			$followRecord->record    = $record;
			$followRecord->file      = !empty($file) ? json_encode($file) : '';
			$followRecord->follow_id = $follow_id;
			if (!$followRecord->validate() || !$followRecord->save()) {
				throw new InvalidDataException(SUtils::modelError($followRecord));
			}

			//标签
			if (!empty($tagIds)) {
				PublicSeaTag::addUserTag([$followUser->id], $tagIds);
			}

			//记录客户轨迹
			if (empty($record_id)) {
				//跟进次数
				$count                  = PublicSeaContactFollowRecord::find()->where(['sea_id' => $followUser->sea_id, 'status' => 1])->count();
				$followUser->follow_num = $count;
				$followUser->save();
				PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'sea_id' => $followUser->sea_id, 'sub_id' => $followRecord->sub_id, 'event' => 'follow', 'event_id' => $follow_id, 'related_id' => $followRecord->id, 'remark' => $count]);
			}
			//非企微客户修改跟进状态
			if ($oldFollowId > 0 && $oldFollowId != $follow_id) {
				WaitTask::publicTask($follow_id, 4, $this->corp->id, $followUser->id);
				WaitCustomerTask::deleteData('', $followUser->sea_id, $oldFollowId, 1);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           修改非企微外部联系人字段
		 * @description     修改非企微外部联系人字段
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/custom-update
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid    必选 int 客户ID
		 * @param type   必选 string 修改类型：nickname昵称、des描述、close_rate预计成交率
		 * @param value  可选 string 修改值
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 18:00
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionCustomUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid             = \Yii::$app->request->post('uid', 0);
			$followUserId    = \Yii::$app->request->post('cid', 0);
			$type            = \Yii::$app->request->post('type', '');
			$value           = \Yii::$app->request->post('value', '');
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			if (empty($followUserId) || empty($type)) {
				throw new InvalidDataException('参数不正确！');
			}
			if ($isMasterAccount == 1) {
				$subId = 0;
			}
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
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
			PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'sub_id' => $subId, 'sea_id' => $followUser->sea_id, 'event' => 'set_field', 'remark' => $remark]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           修改非企微高级属性
		 * @description     修改非企微高级属性
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/customF-field-update
		 *
		 * @param uid       必选 int 用户ID
		 * @param cid       必选 int 非企微客户外部联系表ID
		 * @param fieldData 必选 array 高级属性数据
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 17:56
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
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
			if (empty($uid) || empty($followUserId) || empty($fieldData)) {
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

			$followUser->update();
			//修改非企微客户信息
			$customerInfo->update();

			//记录客户轨迹
			if (!empty($uptField)) {
				$customField = CustomField::find()->where('id IN (' . trim($uptField, ',') . ')')->select('`title`')->asArray()->all();
				$remark      = '';
				foreach ($customField as $v) {
					$remark .= $v['title'] . '、';
				}
				PublicSeaTimeLine::addExternalTimeLine(['uid' => $uid, 'sea_id' => $cid, 'user_id' => $followUser->user_id, 'event' => 'set_field', 'remark' => substr($remark, 0, -1)]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户互动轨迹
		 * @description     非企微客户互动轨迹
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/custom-track
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 非企微客户外部联系表ID
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[{"event_time":"2020-09-11 17:52:09","icon":11,"content":"【总经理】 第0次跟进，为【跟进中】状态"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    event_time string 时间
		 * @return_param    icon string 图标
		 * @return_param    content string 内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 17:48
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomTrack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$sub_id          = \Yii::$app->request->post('sub_id', 0);
			$followUserId    = \Yii::$app->request->post('cid', 0);
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('page_size', 15);

			if (empty($uid) || empty($followUserId)) {
				throw new InvalidDataException('参数不正确！');
			}

			$offset     = ($page - 1) * $pageSize;
			$followUser = PublicSeaContactFollowUser::findOne($followUserId);
			if (empty($followUser)) {
				throw new InvalidDataException('参数不正确！');
			}
			$customerInfo = PublicSeaCustomer::findOne($followUser->sea_id);
			if (empty($customerInfo)) {
				throw new InvalidDataException('非企微客户数据错误！');
			}

			$timeLine = PublicSeaTimeLine::find()->where(['sea_id' => $followUser->sea_id])->limit($pageSize)->offset($offset)->orderBy(['event_time' => SORT_DESC])->asArray()->all();

			return PublicSeaTimeLine::getExternalTimeLine($uid, $timeLine);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户管理
		 * @description     非企微客户管理
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/custom-list
		 *
		 * @param uid 必选 string 主账户id
		 * @param sub_id 可选 string 子账户id
		 * @param page 可选 string 页码，默认为1
		 * @param page_size 可选 string 每页数量，默认15
		 * @param search 可选 string 搜索关键词
		 * @param field_option_id 可选 string 来源
		 * @param user_ids 可选 array 成员
		 * @param is_protect 可选 string 是否已保护：-1全部、0否、1是
		 * @param tag_ids  可选 array 标签数组
		 * @param tag_type 可选 string 1默认或，2且
		 *
		 * @return          {"error":0,"data":{"count":"33","followData":[{"key":"4","name":"美洋洋6814","phone":"13811119035","source":"网上搜索","add_time":"2020-09-10 13:35","area":"天津-河东","user_name":"李云莉","is_bind":0,"tag_name":["8"],"follow_status":"跟进中","follow_num":"0","gender":"女","claimTip":"请尽快在后日添加跟进记录 ，逾期后将自动变更为公海客户"},{"key":"5","name":"美洋洋6811","phone":"13811119032","source":"网上搜索","add_time":"2020-09-10 13:42","area":"","user_name":"李云莉","is_bind":0,"tag_name":[],"follow_status":"跟进中","follow_num":"0","gender":"男","claimTip":"请尽快在后日添加跟进记录 ，逾期后将自动变更为公海客户"}],"optionList":[{"id":243,"match":"客户介绍"},{"id":244,"match":"广告宣传"},{"id":245,"match":"网上搜索"},{"id":246,"match":"陌拜"},{"id":247,"match":"其他"}],"keys":["4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36"],"tag_count":["1",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,"4","2"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    keys array 数据键列表
		 * @return_param    optionList array 来源列表
		 * @return_param    tag_count array 标签个数列表
		 * @return_param    followData array 数据列表
		 * @return_param    followData.add_time string 添加时间
		 * @return_param    followData.area string 区域
		 * @return_param    followData.claimTip string 提醒
		 * @return_param    followData.follow_num string 跟进次数
		 * @return_param    followData.follow_status string 跟进状态名称
		 * @return_param    followData.gender string 性别
		 * @return_param    followData.key string 键
		 * @return_param    followData.name string 姓名
		 * @return_param    followData.phone string 手机号
		 * @return_param    followData.source string 来源
		 * @return_param    followData.tag_name array 标签列表
		 * @return_param    followData.user_name string 认领成员
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 17:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCustomList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$search          = \Yii::$app->request->post('name', '');
			$fieldOptionId   = \Yii::$app->request->post('field_option_id', '');
			$userIds         = \Yii::$app->request->post('user_ids', []);
			$sDate           = \Yii::$app->request->post('s_date', '');
			$eDate           = \Yii::$app->request->post('e_date', '');
			$page            = \Yii::$app->request->post('page') ?: 1;
			$pageSize        = \Yii::$app->request->post('page_size') ?: 15;
			$isProtect       = \Yii::$app->request->post('is_protect', '-1');
			$tagIds          = \Yii::$app->request->post('tag_ids', []);
			$tagType         = \Yii::$app->request->post('tag_type', 1);
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId     = $this->corp->id;
			$offset     = ($page - 1) * $pageSize;
			$followUser = PublicSeaContactFollowUser::find()->alias('fu');
			$followUser = $followUser->leftJoin('{{%public_sea_customer}} sc', 'fu.sea_id=sc.id');
			$followUser = $followUser->where(['fu.corp_id' => $this->corp->id, 'fu.is_reclaim' => 0]);
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($userIds)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($userIds);
				$userIds = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
				$userIds = empty($userIds) ? [0] : $userIds;
			}
			if(!empty($sub_id) && empty($userIds)){
				$userIds = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$sub_id,0,true);
				$userIds = empty($userIds) ? [0] : $userIds;
			}

			if (!empty($tagIds)) {
				$followUser = $followUser->leftJoin('{{%public_sea_tag}} st', 'st.follow_user_id=fu.id');
				if ($tagType == 1) {
					$followUser = $followUser->andWhere(['st.tag_id' => $tagIds, 'st.status' => 1]);
				} else {
					$contactArr = [];//符合标签并集客户
					$haveTag    = 1;//有符合标签的客户
					foreach ($tagIds as $tagId) {
						if($haveTag){
							$contactTag = PublicSeaTag::find()->where(['tag_id' => $tagId, 'status' => 1, 'corp_id' => $corpId])->select('`follow_user_id`')->asArray()->all();
							$contactId  = array_column($contactTag, 'follow_user_id');
							if (!empty($contactArr)) {
								$contactArr = array_intersect($contactArr, $contactId);
							} else {
								$contactArr = $contactId;
							}
							$haveTag = empty($contactArr) ? 0 : $haveTag;
						}
					}
					if (!empty($contactArr)) {
						$followUser = $followUser->andWhere(['in', 'fu.id', $contactArr]);
					} else {
						$followUser = $followUser->andWhere(['fu.id' => 0]);
					}
				}
			}

			if (!empty($search)) {
				$followUser = $followUser->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `sc`.`id` AND `cf`.`type`=4');
				$emailInfo  = CustomField::findOne(['uid' => 0, 'key' => 'email']);
				$followUser = $followUser->andWhere(' sc.name like \'%' . $search . '%\' or sc.wx_num like \'%' . $search . '%\'  or sc.phone like \'%' . $search . '%\' or sc.qq like \'%' . $search . '%\' or (cf.fieldid =' . $emailInfo->id . ' and cf.value like \'%' . $search . '%\')');
			}
			if (!empty($fieldOptionId)) {
				$followUser = $followUser->andWhere(['sc.field_option_id' => $fieldOptionId]);
			}
			if (!empty($sDate) && !empty($eDate)) {
				$sTime      = strtotime($sDate);
				$eTime      = strtotime($eDate . ':59');
				$followUser = $followUser->andWhere(['between', 'fu.add_time', $sTime, $eTime]);
			}
			if ($isProtect != '-1') {
				$followUser = $followUser->andWhere(['fu.is_protect' => $isProtect]);
			}
			if (!empty($userIds)) {
				$followUser = $followUser->andWhere(['fu.user_id' => $userIds]);
			}

			$followUser = $followUser->groupBy('fu.id');
			$count      = $followUser->count();
			$followUser = $followUser->select('fu.id,fu.sea_id,fu.user_id,fu.follow_id,fu.last_follow_time,fu.follow_num,fu.add_time,fu.is_protect,sc.name,sc.phone,sc.wx_num,sc.qq,sc.field_option_id,sc.external_userid');

			$followUser = $followUser->limit($pageSize)->offset($offset)->orderBy(['fu.add_time' => SORT_DESC])->asArray()->all();
			//获取所有的key
			$keys           = [];
			$userTagCount   = [];
			$userIndexArray = [];
			if (!empty($followUser)) {
				foreach ($followUser as $key => $idInfo) {
					array_push($keys, (string) $idInfo['id']);
					array_push($userTagCount, 0);
					$userIndexArray[$idInfo['id']] = $key;
				}
			}
			//标签个数
			$tagList   = PublicSeaTag::find()->alias('st');
			$tagList   = $tagList->leftJoin('{{%work_tag}} t', '`t`.`id` = `st`.`tag_id`');
			$tag_count = $tagList->select('st.`follow_user_id`, count(st.`follow_user_id`) as cnt')->where(['st.follow_user_id' => $keys, 'st.status' => 1, 't.is_del' => 0, 'st.corp_id' => $corpId])->groupBy('st.follow_user_id')->asArray()->all();
			$tagCount  = array_column($tag_count, 'cnt', 'follow_user_id');
			if (!empty($tagCount)) {
				foreach ($tagCount as $cId => $cnt) {
					$userTagCount[$userIndexArray[$cId]] = $cnt;
				}
			}

			//地区
			$areaField  = CustomField::findOne(['uid' => 0, 'type' => 7, 'key' => 'area']);
			$emailField = CustomField::findOne(['uid' => 0, 'type' => 6, 'key' => 'email']);
			//来源
			$customField = CustomField::findOne(['uid' => 0, 'type' => 2, 'key' => 'offline_source', 'status' => [0, 1]]);
			$optionList  = CustomFieldOption::find()->where(['uid' => 0, 'fieldid' => $customField->id])->select('id,match')->all();
			$optionData  = array_column($optionList, 'match', 'id');
			$fieldInfo   = CustomField::findOne(['uid' => 0, 'key' => 'sex']);

			//查询客户保护
			if ($isMasterAccount == 1) {
				$isShow = $isRest = 1;
			} else {
				$protectFollowData = PublicSeaProtect::getDataByFollowUserId($corpId, $keys);
				$protectData       = PublicSeaProtect::getProtectBySubId($corpId, $subId);
				$isShow            = $protectData['is_show'];
				$isRest            = $protectData['is_rest'];
				$subUserId         = $protectData['sub_user_id'];
			}
			$isCancel = 0;//取消按钮是否可用

			$followData = [];
			foreach ($followUser as $follow) {
				$result             = [];
				$result['key']      = $follow['id'];
				$result['name']     = $follow['name'];
				$result['phone']    = $follow['phone'];
				$result['wx_num']   = $follow['wx_num'];
				$result['qq']       = $follow['qq'];
				$result['source']   = !empty($optionData[$follow['field_option_id']]) ? $optionData[$follow['field_option_id']] : '';
				$result['add_time'] = date('Y-m-d H:i', $follow['add_time']);
				//邮箱
				$emailValue      = CustomFieldValue::findOne(['type' => 4, 'fieldid' => $emailField->id, 'cid' => $follow['sea_id']]);
				$result['email'] = !empty($emailValue) ? $emailValue->value : '';

				//区域
				$fieldValue     = CustomFieldValue::findOne(['type' => 4, 'fieldid' => $areaField->id, 'cid' => $follow['sea_id']]);
				$result['area'] = !empty($fieldValue) ? $fieldValue->value : '';
				//归属成员
				$workUser            = WorkUser::findOne($follow['user_id']);
				$result['user_name'] = !empty($workUser) ? $workUser->name : '';
				$result['is_bind']   = !empty($follow['external_userid']) ? 1 : 0;
				$tagData             = PublicSeaTag::find()->alias('st')->leftJoin('{{%work_tag}} wt', 'st.tag_id=wt.id');
				$tagData             = $tagData->where(['st.corp_id' => $corpId, 'st.follow_user_id' => $follow['id'], 'st.status' => 1, 'wt.is_del' => 0])->select('tagname')->asArray()->all();
				$tagName             = array_column($tagData, 'tagname');

				$result['tag_name'] = $tagName;

				//跟进状态
				$follow_status = '';
				if (!empty($follow['follow_id'])) {
					$followInfo = Follow::findOne($follow['follow_id']);
					if (!empty($followInfo)) {
						$follow_status = $followInfo->title;
						if ($followInfo->status == 0) {
							$follow_status .= '（已删除）';
						}
					}
				}
				$result['follow_status'] = $follow_status;
				$result['follow_num']    = $follow['follow_num'];

				//性别
				$fieldValue       = CustomFieldValue::findOne(['type' => 4, 'cid' => $follow['sea_id'], 'fieldid' => $fieldInfo->id]);
				$result['gender'] = !empty($fieldValue) ? $fieldValue->value : '未知';

				//回收提醒
				$claimTip = '';
				if ($follow['is_protect'] == 0) {
					$isTask = WaitTask::getTaskById(0, $follow['sea_id']);
					if (empty($isTask)) {
						$claimTip = PublicSeaReclaimSet::getSeaRule($corpId, $follow['user_id'], ['follow_id' => $follow['follow_id'], 'last_follow_time' => $follow['last_follow_time']]);
					}
				}else{
					$isCancel = 1;
				}
				$result['claimTip'] = $claimTip;

				$result['is_protect']  = (int) $follow['is_protect'];
				$result['is_show']     = $isShow;
				$result['is_rest']     = $isRest;
				$result['protect_str'] = '';
				if ($isMasterAccount != 1) {
					if (!empty($protectFollowData[$follow['id']])) {
						$returnData = $protectFollowData[$follow['id']];
						if ($subId != $returnData['sub_id'] && $follow['user_id'] != $subUserId) {
							$result['protect_str'] = '【' . $follow['name'] . '】已被【' . $returnData['name'] . '】保护，您无法取消保护';
						}
					}
				}

				array_push($followData, $result);
			}

			//获取当前帐号对应的员工
			if ($isMasterAccount == 1) {
				$userInfo = User::findOne($uid);
				$mobile   = !empty($userInfo) ? $userInfo->account : '';
			} else {
				$subUser = SubUser::findOne($subId);
				$mobile  = !empty($subUser) ? $subUser->account : '';
			}
			if (!empty($mobile)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'mobile' => $mobile, 'is_external' => 1, 'is_del' => 0]);
			}
			$userId = 0;
			if (!empty($workUser)) {
				$userId = $workUser->id;
			}

			return [
				'count'      => $count,
				'followData' => $followData,
				'optionList' => $optionList,
				'keys'       => $keys,
				'tag_count'  => $userTagCount,
				'user_id'    => $userId,
				'is_show'    => $isShow,
				'is_rest'    => $isRest,
				'is_cancel'  => $isCancel,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           获取所选对象下的标签
		 * @description     获取所选对象下的标签
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/get-user-tags
		 *
		 * @param user_ids 必选 array 非企微客户外部联系人id数组
		 * @param give 必选 int 0打标签1移除标签
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 17:00
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGetUserTags ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$userIds = \Yii::$app->request->post('user_ids');
			$give    = \Yii::$app->request->post('give') ?: 0;
			if (empty($userIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			$count     = count($userIds);
			$userIdStr = implode(',', $userIds);
			$result    = [];
			$tagList   = PublicSeaTag::find()->alias('st');
			$tagList   = $tagList->leftJoin('{{%work_tag}} t', '`t`.`id` = `st`.`tag_id`');
			$tagList   = $tagList->select('count(*) as num,t.id,t.tagname')->where("st.follow_user_id in (" . $userIdStr . ")")->andWhere(['t.is_del' => 0, 'st.status' => 1])->groupBy('t.id');
			$tagList   = $tagList->asArray()->all();
			if (!empty($give)) {
				$result = $tagList;
			} else {
				if (!empty($tagList)) {
					foreach ($tagList as $key => $v) {
						if ($v['num'] == $count) {
							$result[$key]['id']      = $v['id'];
							$result[$key]['tagname'] = $v['tagname'];
						}
					}
				}
			}
			$result = array_values($result);

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           给客户添加和移除标签
		 * @description     给客户添加和移除标签
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/give-user-tags
		 *
		 * @param tag_ids 必选 array 标签id数组
		 * @param user_ids 必选 array 非企微客户外部联系人id数组
		 * @param type 必选 string 0、打标签，1、移除标签
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    error_msg string 提示语
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 16:25
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGiveUserTags ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$tagIds   = \Yii::$app->request->post('tag_ids');
			$userIds  = \Yii::$app->request->post('user_ids');
			$type     = \Yii::$app->request->post('type') ?: 0;//0 打标签 1 移除标签
			$bitchAll = \Yii::$app->request->post('bitch_all', 0);//0 打标签 1 移除标签
			if ((empty($tag_ids) && empty($bitchAll)) || empty($userIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			if(!is_array($userIds)){
				$userIds = [$userIds];
			}
			if (!is_array($tagIds) || !is_array($userIds)) {
				throw new InvalidDataException('参数格式不正确！');
			}
			try {
				//总共操作人数
				$total = count($userIds);
				//成功人数
				$success = 0;
				//失败人数
				$fail = 0;
				if($bitchAll){
					$notTags = PublicSeaTag::find()->where(["and", ["status" => 1], ["in", "follow_user_id", $userIds]])->asArray()->all();
					if (!empty($notTags) && empty($tagIds)) {
						$notTagsAll = array_column($notTags, "tag_id");
						PublicSeaTag::removeUserTag($userIds, $notTagsAll);
					}
					if (!empty($notTags) && !empty($tagIds)) {
						$notTagsAll       = array_column($notTags, "tag_id");
						$notTagsAllRemove = array_diff($notTagsAll, $tagIds);
						if (!empty($notTagsAllRemove)) {
							$notTagsAllRemove = array_values($notTagsAllRemove);
							PublicSeaTag::removeUserTag($userIds, $notTagsAllRemove);
						}
						PublicSeaTag::addUserTag($userIds, $tagIds);
					}
					if(empty($notTags) && !empty($tagIds)){
						PublicSeaTag::addUserTag($userIds, $tagIds);
					}
					return [
						'error'     => 0,
						'error_msg' => "提交成功",
					];
				}else{
					if ($type == 0) {
						$active   = '打';
						$fail_num = PublicSeaTag::addUserTag($userIds, $tagIds);
					} else {
						$active   = '移除';
						$fail_num = PublicSeaTag::removeUserTag($userIds, $tagIds);
					}
				}
				$fail    = $fail_num;
				$success = $total - $fail_num;
			} catch (InvalidDataException $e) {
				return [
					'error'     => $e->getCode(),
					'error_msg' => $e->getMessage(),
				];
			}

			return [
				'error'     => 0,
				'error_msg' => "本次共给" . $total . "人" . $active . "标签，成功" . $success . "人，失败" . $fail . "人。",
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           非企微客户看版
		 * @description     非企微客户看版
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/custom-board
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param isMasterAccount 必选 string 1、主账户，2子账户
		 * @param uid 必选 string 主账户id
		 * @param sub_id 可选 string 子账户id
		 * @param page 可选 string 页码，默认为1
		 * @param pageSize 可选 string 每页数量，默认15
		 * @param name 可选 string 搜索关键词
		 * @param phone 可选 string 搜索手机号
		 * @param user_ids 可选 array 搜索成员
		 * @param sort 可选 string 排序方式
		 * @param day 可选 string 联系时间
		 * @param id 可选 string 跟进状态id
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param pages 可选 array 所有列的当前页数
		 * @param is_protect 可选 string 是否已保护：-1全部、0否、1是
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 跟进状态id
		 * @return_param    title string 状态名称
		 * @return_param    status string 0代表已删除要标记下
		 * @return_param    count string 客户数
		 * @return_param    members array 客户信息
		 * @return_param    members.employee string 员工姓名
		 * @return_param    members.cid string 客户id
		 * @return_param    members.chat string 沟通时间
		 * @return_param    members.remark string 备注
		 * @return_param    members.close_rate string 成交率
		 * @return_param    members.company_name string 公司名称
		 * @return_param    members.name string 姓名
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 15:57
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionCustomBoard ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$cid             = \Yii::$app->request->post('cid', 0);
			$uid             = \Yii::$app->request->post('uid', 0);
			$page            = \Yii::$app->request->post('page', 1);
			$pageSize        = \Yii::$app->request->post('pageSize', 15);
			$name            = \Yii::$app->request->post('name');
			$phone           = \Yii::$app->request->post('phone');
			$userIds         = \Yii::$app->request->post('user_ids');
			$sort            = \Yii::$app->request->post('sort', 0);
			$day             = \Yii::$app->request->post('day', 0);
			$id              = \Yii::$app->request->post('id');
			$startTime       = \Yii::$app->request->post('start_time');
			$endTime         = \Yii::$app->request->post('end_time');
			$pages           = \Yii::$app->request->post('pages');
			$isProtect       = \Yii::$app->request->post('is_protect', '-1');
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId = $this->corp->id;

			//查询客户保护
			if ($isMasterAccount == 1) {
				$isShow = $isRest = 1;
			} else {
				$protectData = PublicSeaProtect::getProtectBySubId($corpId, $subId);
				$isShow      = $protectData['is_show'];
				$isRest      = $protectData['is_rest'];
			}

			$followNew = Follow::findOne(['uid' => $uid, 'status' => 1]);
			$follow    = Follow::find()->where(['uid' => $uid]);
			if (!empty($id)) {
				$follow = $follow->andWhere(["id" => $id]);
			}
			$follow    = $follow->select('id,uid,title,status')->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();
			$offset    = ($page - 1) * $pageSize;
			$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			if(!empty($userIds)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($userIds);
				$userIds = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
				$userIds = empty($userIds) ? [0] : $userIds;
			}
			if(!empty($sub_id) && empty($userIds)){
				$userIds = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$sub_id,0,true);
				$userIds = empty($userIds) ? [0] : $userIds;
			}
			$info = [];
			foreach ($follow as $key => $value) {
				$followUser = PublicSeaContactFollowUser::find()->alias('wf');
				$followUser = $followUser->leftJoin('{{%public_sea_customer}} sc', 'sc.id=wf.sea_id');
				$followUser = $followUser->leftJoin('{{%work_user}} a', 'wf.user_id=a.id');
				$followUser = $followUser->where(['wf.corp_id' => $corpId, 'wf.follow_id' => $value['id'], 'wf.is_reclaim' => 0]);
				if (!empty($phone)) {
					$followUser = $followUser->andWhere(['like', 'sc.phone', $phone]);
				}
				if ($name !== '') {
					$followUser = $followUser->andWhere(['or', ['like', 'sc.name', $name], ['like', 'sc.remark', $name], ['like', 'wf.company_name', $name]]);
				}
				if ($isProtect != '-1') {
					$followUser = $followUser->andWhere(['wf.is_protect' => $isProtect]);
				}
				if (!empty($userIds)) {
					$followUser = $followUser->andWhere(['in', 'wf.user_id', $userIds]);
				}
				if (!empty($startTime) && !empty($endTime)) {
					$followUser = $followUser->andFilterWhere(['between', 'wf.add_time', strtotime($startTime), strtotime($endTime)]);
				}
				if (!empty($day)) {
					switch ($day) {
						case 1:
							$followUser = $followUser->andWhere('wf.last_follow_time = wf.add_time');
							break;
						case 2:
							$time       = Follow::getTime(1);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 3:
							$time       = Follow::getTime(2);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 4:
							$time       = Follow::getTime(3);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 5:
							$time       = Follow::getTime(4);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 6:
							$time       = Follow::getTime(5);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 7:
							$time       = Follow::getTime(6);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 8:
							$time       = Follow::getTime(7);
							$followUser = $followUser->andWhere(['OR', ['<', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
						case 9:
							$time       = Follow::getTime(8);
							$followUser = $followUser->andWhere(['OR', ['>', 'wf.last_follow_time', $time], 'wf.last_follow_time=wf.add_time']);
							break;
					}
				}
				if ($sort == 0) {
					$followUser = $followUser->orderBy(['wf.add_time' => SORT_DESC]);
				} elseif ($sort == 1) {
					$followUser = $followUser->orderBy(['wf.add_time' => SORT_ASC]);
				} elseif ($sort == 2) {
					if ($followNew->id == $value['id']) {
						$followUser = $followUser->orderBy(['wf.is_chat' => SORT_DESC, 'wf.last_follow_time' => SORT_DESC]);
					} else {
						$followUser = $followUser->orderBy(['wf.last_follow_time' => SORT_DESC]);
					}
				} elseif ($sort == 3) {
					if ($followNew->id == $value['id']) {
						$followUser = $followUser->orderBy(['wf.is_chat' => SORT_ASC, 'wf.last_follow_time' => SORT_ASC]);
					} else {
						$followUser = $followUser->orderBy(['wf.last_follow_time' => SORT_ASC]);
					}
				}
				if (!empty($cid)) {
					$followUser = $followUser->andWhere(['wf.id'=>$cid]);
				}
				$followUser = $followUser->select('a.name as employee,sc.name,wf.sea_id,wf.user_id,wf.last_follow_time,wf.id as fid,sc.remark,wf.close_rate,wf.company_name,wf.add_time,wf.is_protect');
				$count      = $followUser->groupBy('wf.id')->count();
				if ($count == 0 && $value["status"] == 0) {
					continue;
				}
				if (empty($cid)) {
					$followUser = $followUser->limit($pageSize)->offset($offset)->groupBy('wf.id');
				}
				$followUser = $followUser->asArray()->all();
				if ($isMasterAccount != 1) {
					$followUserIds     = array_column($followUser, 'fid');
					$protectFollowData = PublicSeaProtect::getDataByFollowUserId($corpId, $followUserIds);
				}
				$info[$key]['id']      = $value['id'];
				$info[$key]['title']   = $value['title'];
				$info[$key]['status']  = $value['status'];
				$info[$key]['count']   = $count;
				$info[$key]['members'] = $members = [];
				if (!empty($followUser)) {
					$fid = array_column($followUser, "fid");
					//标签
					$tagData = PublicSeaTag::find()->alias('st')->leftJoin('{{%work_tag}} wt', 'st.tag_id=wt.id');
					$tagData = $tagData->where(['st.corp_id' => $corpId, 'st.status' => 1, 'wt.is_del' => 0])
						->andWhere(["in",'st.follow_user_id', $fid])->select('follow_user_id,tagname')->asArray()->all();
					$newData = [];
					foreach ( $tagData as $vv){
						$newData[$vv["follow_user_id"]][] = $vv["tagname"];
					}
					foreach ($followUser as $k => $v) {
						$members[$k]['name']         = $v['name'];
						$members[$k]['company_name'] = !empty($v['company_name']) ? $v['company_name'] : '暂无公司';
						$employee                    = $v["employee"];
						$members[$k]['employee'] = $employee;
						$members[$k]['cid']      = $v['fid'];
						if ($v['last_follow_time'] == 0 || $v['last_follow_time'] == $v['add_time']) {
							$chat = '一直未沟通';
						} else {
							$chat = DateUtil::getDiffText($v['last_follow_time']);
						}
						$members[$k]['chat']       = $chat;
						$members[$k]['remark']     = !empty($v['remark']) ? $v['remark'] : '暂无备注';
						$members[$k]['close_rate'] = !empty($v['close_rate']) ? $v['close_rate'] : 0;
						$tagName = [];
						if(isset($newData[$v["fid"]])){
							$tagName = $newData[$v["fid"]];
						}
						$members[$k]['tag_name']    = $tagName;
						$members[$k]['is_show']     = $isShow;
						$members[$k]['is_rest']     = $isRest;
						$members[$k]['is_protect']  = (int) $v['is_protect'];
						$members[$k]['protect_str'] = '';
						if ($isMasterAccount != 1) {
							if (!empty($protectFollowData[$v['fid']])) {
								$followData = $protectFollowData[$v['fid']];
								if ($subId != $followData['sub_id']) {
									$tempName = rawurldecode($v['name']);;
									$members[$k]['protect_str'] = '【' . $tempName . '】已被【' . $followData['name'] . '】保护，您无法取消保护';
								}
							}
						}
					}
				}
				if(empty($cid)){
					$info[$key]['members'] = $members;
				}else{
					$info = $members;
				}
			}

			return array_values($info);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户回收设置列表
		 * @description     客户回收设置列表
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/reclaim
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param page 可选 string 页码，默认为1
		 * @param page_size 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"2","claimData":[{"id":10,"corp_id":1,"agent_id":5,"valid_type":1,"user_key":[],"party":[],"reclaim_rule":[{"follow_id":3,"day":3,"reclaim_day":1,"repeat":false}],"private_num":300,"is_delay":0,"delay_day":0,"reclaim_day":7,"nameArr":["所有成员"],"ruleData":["进入【跟进中】阶段，3天未添加跟进记录"],"userLimit":["每个员工分配客户上限300个","不允许员工延期","7天后员工可捡回"]},{"id":8,"corp_id":1,"agent_id":5,"valid_type":2,"user_key":[{"id":5,"user_key":"5-2","name":"邢长宇"},{"id":96,"user_key":"96-3","name":"李云莉"}],"party":[],"reclaim_rule":[{"follow_id":399,"day":1,"reclaim_day":1,"repeat":false},{"follow_id":3,"day":3,"reclaim_day":1,"repeat":false}],"private_num":100,"is_delay":0,"delay_day":0,"reclaim_day":1,"nameArr":["邢长宇","李云莉"],"ruleData":["进入【技术测试】阶段，1天未添加跟进记录","进入【跟进中】阶段，3天未添加跟进记录"],"userLimit":["每个员工分配客户上限100个","不允许员工延期","1天后员工可捡回"]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    claimData array 列表数据
		 * @return_param    id string 回收id
		 * @return_param    nameArr array 生效成员
		 * @return_param    ruleData array 回收规则
		 * @return_param    userLimit array 成员限制
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 15:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionReclaim ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}

			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId   = $this->corp->id;
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('page_size') ?: 15;
			$offset   = ($page - 1) * $pageSize;

			$reclaimList = PublicSeaReclaimSet::find()->where(['corp_id' => $corpId, 'status' => 1]);
			$count       = $reclaimList->count();
			$reclaimList = $reclaimList->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$reclaimData = [];
			foreach ($reclaimList as $reclaim) {
				$reclaimInfo = $reclaim->dumpData(1);
				array_push($reclaimData, $reclaimInfo);
			}

			return ['count' => $count, 'claimData' => $reclaimData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户回收删除
		 * @description     客户回收删除
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/reclaim-del
		 *
		 * @param claim_id 必选 string 回收规则id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 15:10
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionReclaimDel ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$claimId = \Yii::$app->request->post('claim_id', 0);
			$reclaim = PublicSeaReclaimSet::findOne($claimId);
			if (empty($reclaim)) {
				throw new InvalidDataException('参数不正确！');
			}
			$reclaim->status      = 0;
			$reclaim->update_time = time();
			$reclaim->update();

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户回收修改详情
		 * @description     客户回收修改详情
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/reclaim-info
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param claim_id 必选 string 回收规则id
		 *
		 * @return          {"error":0,"data":{"id":8,"corp_id":1,"agent_id":5,"valid_type":2,"user_key":[{"id":5,"user_key":"5-2","name":"邢长宇"},{"id":96,"user_key":"96-3","name":"李云莉"}],"party":[],"reclaim_rule":[{"follow_id":399,"day":1,"reclaim_day":1,"repeat":false},{"follow_id":3,"day":3,"reclaim_day":1,"repeat":false}],"private_num":100,"is_delay":0,"delay_day":0,"reclaim_day":1,"notParty":[8,7,6,5,4,3,2,1],"isAll":1}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 回收规则id
		 * @return_param    corp_id string 企业微信id
		 * @return_param    agent_id string 应用id
		 * @return_param    valid_type string 生效成员状态：1通用、2仅企业成员适用
		 * @return_param    user_key array 生效成员
		 * @return_param    party array 生效部门
		 * @return_param    reclaim_rule array 生效规则
		 * @return_param    private_num string 私有池数量
		 * @return_param    is_delay string 是否延期：0否、1是
		 * @return_param    delay_day string 延期天数
		 * @return_param    reclaim_day string 可捡回天数
		 * @return_param    notParty string 不可选bumen
		 * @return_param    isAll string 通用是否已被选择
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 14:33
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionReclaimInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$claimId = \Yii::$app->request->post('claim_id', 0);
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			//不可选部门
			$notParty  = [];
			$claimList = PublicSeaReclaimSet::find()->where(['status' => 1]);
			if (!empty($claimId)) {
				$reclaim = PublicSeaReclaimSet::findOne($claimId);
				if (empty($reclaim)) {
					throw new InvalidDataException('参数不正确！');
				}
				$result    = $reclaim->dumpData();
				$claimList = $claimList->andWhere(['corp_id' => $reclaim->corp_id])->andWhere(['!=', 'id', $claimId]);
			} else {
				$result    = [];
				$claimList = $claimList->andWhere(['corp_id' => $this->corp->id]);
			}
			$claimInfo = clone $claimList;
			$claimInfo = $claimInfo->andWhere(['valid_type' => 1])->one();

			$claimList = $claimList->select('party')->all();
			if (!empty($claimList)) {
				foreach ($claimList as $claim) {
					if (!empty($claim->party)) {
						$partyData = explode(',', $claim->party);
						foreach ($partyData as $party) {
							array_push($notParty, "d-".intval($party));
						}
					}
				}
			}
			if(!empty($result['user_key'])){
				WorkDepartment::ActivityDataFormat($result['user_key'],$this->corp->id,[]);
			}
			$result['notParty'] = $notParty;
			$result['isAll']    = !empty($claimInfo) ? 1 : 0;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户回收设置
		 * @description     客户回收设置
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/reclaim-set
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 企业微信id
		 * @param agent_id 必选 string 应用id
		 * @param id 可选 string 回收设置id
		 * @param valid_type 必选 string 生效成员：1通用、2选择员工部门
		 * @param user_key 可选 array 生效成员数据
		 * @param party 可选 array 生效部门
		 * @param ruleData 必选 array 规则数据
		 * @param private_num 必选 string 私有池数量
		 * @param is_delay 必选 string 是否延期：0否、1是
		 * @param delay_day 可选 string 延期天数
		 * @param reclaim_day 可选 string 可捡回天数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-09-11 13:22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionReclaimSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corpId              = $this->corp->id;
			$postData            = \Yii::$app->request->post();
			$postData['corp_id'] = $corpId;
			PublicSeaReclaimSet::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           设置客户保护
		 * @description     设置客户保护
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/protect
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param isMasterAccount 必选 string 是否是主账户:1主账户、2子账户
		 * @param uid 必选 string 账户id
		 * @param sub_id 必选 string 子账户id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_id 必选 array 外部联系人关系id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 10:37
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionProtect ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$uid             = \Yii::$app->request->post('uid', 0);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$type            = \Yii::$app->request->post('type', 0);
			$followIds       = \Yii::$app->request->post('follow_id', []);
			if (empty($uid) || empty($this->corp) || empty($followIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$userId = 0;
			if ($isMasterAccount == 1) {
				$isMaster = 0;
				$userInfo = User::findOne($uid);
				$mobile   = !empty($userInfo) ? $userInfo->account : '';
			} else {
				$isMaster = 1;
				$subUser  = SubUser::findOne($subId);
				$mobile   = !empty($subUser) ? $subUser->account : '';
			}
			if (!empty($mobile)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'mobile' => $mobile, 'is_external' => 1, 'is_del' => 0]);
				if (!empty($workUser)) {
					$userId = $workUser->id;
				}
			}

			$otherData = ['is_master' => $isMaster, 'uid' => $uid, 'sub_id' => $subId, 'corp_id' => $corpId, 'user_id' => $userId, 'type' => $type];
			$textHtml  = PublicSeaProtect::protect($followIds, $otherData);

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           取消客户保护
		 * @description     取消客户保护
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/no-protect
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param isMasterAccount 必选 string 是否是主账户:1主账户、2子账户
		 * @param uid 必选 string 账户id
		 * @param sub_id 必选 string 子账户id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_id 必选 array 外部联系人关系id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 11:13
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionNoProtect ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
			$subId           = \Yii::$app->request->post('sub_id', 0);
			$uid             = \Yii::$app->request->post('uid', 0);
			$type            = \Yii::$app->request->post('type', 0);
			$followIds       = \Yii::$app->request->post('follow_id', []);
			if (empty($uid) || empty($this->corp) || empty($followIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$userId = 0;
			if ($isMasterAccount == 1) {
				$isMaster = 0;
				$userInfo = User::findOne($uid);
				$mobile   = !empty($userInfo) ? $userInfo->account : '';
			} else {
				$isMaster = 1;
				$subUser  = SubUser::findOne($subId);
				$mobile   = !empty($subUser) ? $subUser->account : '';
			}
			if (!empty($mobile)) {
				$workUser = WorkUser::findOne(['corp_id' => $corpId, 'mobile' => $mobile, 'is_external' => 1, 'is_del' => 0]);
				if (!empty($workUser)) {
					$userId = $workUser->id;
				}
			}
			$otherData = ['is_master' => $isMaster, 'uid' => $uid, 'sub_id' => $subId, 'corp_id' => $corpId, 'user_id' => $userId, 'type' => $type];

			$textHtml = PublicSeaProtect::noProtect($followIds, $otherData);

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           公海池客户查重
		 * @description     公海池客户查重
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/check-repeat
		 *
		 * @param uid 必选 string 账户id
		 * @param name 必选 string 关键词
		 *
		 * @return          {"error":0,"data":[{"name":"123","phone":"","company_name":"","user_name":"李云莉"},{"name":"1234","phone":"","company_name":"","user_name":"李云莉"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 姓名
		 * @return_param    phone string 手机号
		 * @return_param    company_name string 公司名称
		 * @return_param    user_name string 成员名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 11:34
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionCheckRepeat ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid  = \Yii::$app->request->post('uid', 0);
			$name = \Yii::$app->request->post('name', 0);
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确！');
			}
			$name = trim($name);
			if ($name == '') {
				return [];
			}

			$followUser = PublicSeaContactFollowUser::find()->alias('wf');
			$followUser = $followUser->leftJoin('{{%public_sea_customer}} sc', 'sc.id=wf.sea_id');
			$followUser = $followUser->where(['sc.uid' => $uid, 'wf.is_reclaim' => 0]);

			$followUser = $followUser->andWhere(['or', ['like', 'sc.name', $name], ['like', 'sc.phone', $name], ['like', 'wf.company_name', $name]]);
			$followUser = $followUser->select('sc.name,sc.phone,wf.id,wf.company_name,wf.user_id');
			$followUser = $followUser->asArray()->all();
			$followData = [];
			foreach ($followUser as $key => $followInfo) {
				$followData[$key]['id']           = $followInfo['id'];
				$followData[$key]['name']         = !empty($followInfo['name']) ? $followInfo['name'] : '--';
				$followData[$key]['phone']        = !empty($followInfo['phone']) ? $followInfo['phone'] : '--';
				$followData[$key]['company_name'] = !empty($followInfo['company_name']) ? $followInfo['company_name'] : '--';
				$userName                         = '';
				$workUser                         = WorkUser::findOne($followInfo['user_id']);
				if (!empty($workUser)) {
					$userName = $workUser->name;
				}
				$followData[$key]['user_name'] = $userName;
			}

			return $followData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           公海池客户指定分配
		 * @description     公海池客户指定分配
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/customer-assign
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param uid 必选 string 账户id
		 * @param user_id 必选 string 分配成员id
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
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 11:45
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
			if ($isClaim == 1 && empty($userId)) {
				throw new InvalidDataException('当前帐号无关联的成员！');
			}
			if (empty($uid) || empty($this->corp) || empty($userId) || empty($seaIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId    = $this->corp->id;
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
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户丢弃到公海池
		 * @description     客户丢弃到公海池
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/give-up
		 *
		 * @param uid 必选 string 账户id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_id 必选 array 外部联系人关系表id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 14:37
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGiveUp ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$type      = \Yii::$app->request->post('type', 0);
			$followIds = \Yii::$app->request->post('follow_id', []);
			if (empty($uid) || empty($this->corp) || empty($followIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$textHtml = PublicSeaCustomer::giveUp($uid, $corpId, $type, $followIds);

			return ['textHtml' => $textHtml];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/public-sea-customer/
		 * @title           客户转交
		 * @description     客户转交
		 * @method   post
		 * @url  http://{host_name}/api/public-sea-customer/transfer
		 *
		 * @param corp_id 必选 string 企业微信id
		 * @param uid 必选 string 账户id
		 * @param user_id 必选 string 分配成员id
		 * @param type 必选 string 类型：0非企微客户、1企微客户
		 * @param follow_id 必选 array 外部联系人关系表id
		 *
		 * @return          {"error":0,"data":{"textHtml":"操作成功"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    textHtml string 提示信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-10-09 16:37
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionTransfer ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$type      = \Yii::$app->request->post('type', 0);
			$userId    = \Yii::$app->request->post('user_id', 0);
			$followIds = \Yii::$app->request->post('follow_id', []);
			if (empty($uid) || empty($this->corp) || empty($followIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($type, [0, 1])) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId = $this->corp->id;

			if ($type == 0) {
				$textHtml = PublicSeaCustomer::noSeaTransfer($uid, $corpId, $userId, $followIds);
			} else {
				$textHtml = PublicSeaCustomer::seaTransfer($uid, $corpId, $userId, $followIds);
			}

			return ['textHtml' => $textHtml];
		}

	}