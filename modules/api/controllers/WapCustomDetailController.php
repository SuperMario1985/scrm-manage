<?php
	/**
	 * H5客户详情接口
	 * User: fulu
	 * Date: 2020/04/20
	 * Time: 14:40
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\ApplicationSign;
	use app\models\AuthoritySubUserDetail;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\ExternalTimeLine;
	use app\models\Follow;
	use app\models\Menu;
	use app\models\Package;
	use app\models\PackageMenu;
	use app\models\PublicSeaProtect;
	use app\models\SubUser;
	use app\models\SubUserProfile;
	use app\models\TaobaoOrder;
	use app\models\User;
	use app\models\UserProfile;
	use app\models\WaitCustomerTask;
	use app\models\WaitTask;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkCorp;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowRecord;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkExternalContactMember;
	use app\models\WorkPerTagFollowUser;
	use app\models\WorkTag;
	use app\models\WorkTagChat;
	use app\models\WorkTagFollowUser;
	use app\models\WorkUser;
	use app\models\YouzanOrder;
	use app\modules\api\components\WorkBaseController;
	use app\queue\WaitUserTaskJob;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\src\dataStructure\ExternalContactRemark;
	use dovechen\yii2\weWork\Work;
	use yii\db\Expression;
	use yii\web\MethodNotAllowedHttpException;

	class WapCustomDetailController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户详情
		 * @description     客户详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-detail
		 *
		 * @param uid              必选 int 用户ID
		 * @param external_userid  必选 int 客户的userid
		 * @param now_userid       必选 sting 当前成员的userid
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    access_token string access_token
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    nickname string 设置的昵称
		 * @return_param    avatar string 头像
		 * @return_param    des string 描述
		 * @return_param    close_rate int 预计成交率
		 * @return_param    follow_time string 上次跟进时间
		 * @return_param    follow_num int 跟进次数
		 * @return_param    follow_status string 跟进状态：0未跟进1跟进中2已拒绝3已成交
		 * @return_param    phone string 手机号
		 * @return_param    area string 区域
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
		 * @return_param    packageHas int 是否有客户套餐权限1是0否
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$uid        = \Yii::$app->request->post('uid', 0);
				$workUserId = \Yii::$app->request->post('userid', '');
				$corp_id    = \Yii::$app->request->post('corp_id', '');
				$userid     = \Yii::$app->request->post('external_userid', '');
				$nowUserId  = \Yii::$app->request->post('now_userid', '');
				if (empty($uid) || empty($userid)) {
					throw new InvalidParameterException($userid . '参数不正确！');
				}

				$user         = User::findOne($uid);
				$user_type    = User::USER_TYPE;
				$access_token = base64_encode($user_type . '-' . $user->access_token);
				$corpInfo     = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$userInfo = UserProfile::findOne(['uid' => $uid]);
				$workUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $workUserId]);
				if (empty($workUser)) {
					throw new InvalidParameterException('员工数据错误！');
				}
				$result                 = [];
				$result['access_token'] = $access_token;
				$externalUserData       = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $userid]);
				$follow                 = WorkExternalContactFollowUser::findOne(['user_id' => $workUser->id, 'external_userid' => $externalUserData->id]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				if (empty($follow)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$result["del_type"] = $follow->del_type;
				if ($externalUserData->gender == 0) {
					$gender = '未知';
				} elseif ($externalUserData->gender == 1) {
					$gender = '男性';
				} elseif ($externalUserData->gender == 2) {
					$gender = '女性';
				}
				//查询保护按钮是否显示
				$isShow      = $isRest = 0;
				$nowWorkUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $nowUserId]);
				if (!empty($nowWorkUser)) {
					$subUser = SubUser::findOne(['uid' => $uid, 'account' => $nowWorkUser->mobile, 'status' => 1]);
					if (!empty($subUser)) {
						if (!empty($subUser->type)) {
							$isShow = $isRest = 1;
						} else {
							$protectData = PublicSeaProtect::getProtectBySubId($corpInfo->id, $subUser->id);
							$isShow      = $protectData['is_show'];
							$isRest      = $protectData['is_rest'];
						}
					} else {
						$protectData = PublicSeaProtect::getProtectByUserId($corpInfo->id, $nowWorkUser->id);
						$isShow      = $protectData['is_show'];
						$isRest      = $protectData['is_rest'];
					}
				}
				$result['is_show']        = $isShow;
				$result['is_rest']        = $isRest;
				$result['is_protect']     = (int) $follow->is_protect;
				$result['is_reclaim']     = !empty($follow->is_reclaim) ? 0 : 1;//客户转交按钮是否显示;
				$result['follow_user_id'] = $follow->id;

				//第三方店铺会员信息
				$bindCount  = 0;
				$memberInfo = [];
				$member     = WorkExternalContactMember::find()->alias('m');
				$member     = $member->leftJoin('{{%application_sign}} s', '`s`.`id` = `m`.`sign_id`');
				$member     = $member->where(['m.is_bind' => 1, 's.uid' => $uid, 'm.external_userid' => $externalUserData->id, 's.is_bind' => 1]);
				$member     = $member->select('m.id,m.sign_id,m.external_userid,s.sign')->groupBy('m.sign_id')->asArray()->all();
				if (!empty($member)) {
					foreach ($member as $memId) {
						$member = WorkExternalContactMember::find()->where(['is_bind' => 1, 'sign_id' => $memId['sign_id'], 'external_userid' => $memId['external_userid']])->asArray()->all();
						if (!empty($member)) {
							foreach ($member as $key => $mem) {
								$sign = ApplicationSign::findOne($mem['sign_id']);
								if ($sign->come_from == 1) {
									$url        = \Yii::$app->params['cashier_url'] . ApplicationSign::MEMBER_INFO_URL;
									$resultInfo = SUtils::postUrl($url, ['token' => $sign->sign, 'uc_id' => $mem['uc_id']]);
									if ($resultInfo['err_code'] == 0) {
										$isPay  = $resultInfo['data']['is_pay'];
										$status = '已激活未消费';
										if ($isPay == 1) {
											$status = '已激活已消费';
										}
										$memberInfo[$key]['memberId']  = $resultInfo['data']['pigcms_id'];
										$memberInfo[$key]['cardNo']    = $resultInfo['data']['card_no'];
										$memberInfo[$key]['points']    = $resultInfo['data']['points'];
										$memberInfo[$key]['exp']       = $resultInfo['data']['exp'];
										$memberInfo[$key]['money']     = $resultInfo['data']['money'];
										$memberInfo[$key]['gradeName'] = $resultInfo['data']['grade_name'];
										$memberInfo[$key]['status']    = $status;
									}
									$url         = \Yii::$app->params['cashier_url'] . ApplicationSign::ORDER_URL;
									$orderResult = SUtils::postUrl($url, ['token' => $sign->sign, 'memberId' => $mem['member_id']]);
									if (isset($orderResult['count'])) {
										$bindCount += $orderResult['count'];
									}
								}
								if ($sign->come_from == 2) {
//									$phoneMore = [];
//									if (!empty($customId)) {
//										$conMember = WorkExternalContactMember::find()->where(['external_userid' => $externalUserData->id, 'is_bind' => 1, 'sign_id' => $sign->id])->asArray()->all();
//										if (!empty($conMember)) {
//											foreach ($conMember as $m) {
//												array_push($phoneMore, $m['uc_id']);
//											}
//										}
//									}
									$phoneMore   = [$mem['uc_id']];
									$youzanOrder = YouzanOrder::getYouZanOrders($sign, NULL, NULL, '', '', NULL, '', NULL, '', NULL, 1, 15, $phoneMore);
									if (!empty($youzanOrder)) {
										$bindCount += $youzanOrder['count'];
									}
								}
								if ($sign->come_from == 3 || $sign->come_from == 4) {
//									$phoneMore = [];
//									if (!empty($externalUserData->id)) {
//										$member = WorkExternalContactMember::find()->where(['external_userid' => $externalUserData->id, 'is_bind' => 1, 'sign_id' => $sign->id])->asArray()->all();
//										if (!empty($member)) {
//											foreach ($member as $mem) {
//												array_push($phoneMore, $mem['uc_id']);
//											}
//										}
//									}
									$whereData['account']         = [$mem['uc_id']];
									$whereData['page']            = 1;
									$whereData['pageSize']        = 15;
									$whereData['order_id']        = '';
									$whereData['phone']           = '';
									$whereData['order_status_id'] = '';
									$whereData['payTime']         = '';
									$taobao                       = TaobaoOrder::getList($sign->id, $whereData, $sign->come_from);
									if (!empty($taobao)) {
										$bindCount += $taobao['count'];
									}
								}
							}
						}
					}
				}
				$desc = '';
				if (!empty($follow->des)) {
					$desc = $follow->des;
				}
				$result['memberInfo']  = $memberInfo;
				$result['external_id'] = $follow->id;
				//绑定店铺数量
				$bindMerchants            = ApplicationSign::find()->where(['uid' => $uid, 'is_bind' => 1])->andWhere(['<>', 'come_from', 0])->count();
				$result['merchantsCount'] = $bindMerchants;
				$result['bindCount']      = $bindCount;
				$result['customerId']     = $externalUserData->id;
				$result['avatar']         = $externalUserData->avatar;
				$nickname                 = !empty($follow->nickname) ? $follow->nickname : $follow->remark;
				$result['nickname']       = $nickname;
				$result['des']            = $desc;
				$result['close_rate']     = !empty($follow->close_rate) ? $follow->close_rate : NULL;
				$result['name']           = !empty($externalUserData->name) ? rawurldecode($externalUserData->name) : '';
				$result['follow_status']  = $externalUserData->follow_status ? $externalUserData->follow_status : 0;//跟进状态
				$is_follow_del            = 0;
//				if (!empty($externalUserData->follow)) {
//					$follow_id    = $externalUserData->follow->id;
//					$follow_title = $externalUserData->follow->title;
//					if ($externalUserData->follow->status == 0) {
//						$is_follow_del = 1;
//						$follow_id     = '';
//					}
//					$result['follow_id']    = $follow_id;
//					$result['follow_title'] = $follow_title;
//				} else {
//					$result['follow_id']    = '';
//					$result['follow_title'] = '';
//				}
				$follow_title        = '';
				$result['follow_id'] = $follow->follow_id;
				$newFollow           = Follow::findOne($follow->follow_id);
				if (!empty($newFollow)) {
					$is_follow_del = empty($newFollow->status) ? 1 : 0;
					$follow_title  = $newFollow->title;
				}
				$result['is_follow_del'] = $is_follow_del;
				$result['follow_title'] = $follow_title;

				$nowUserId = !empty($nowUserId) ? $nowUserId : $workUserId;
				$userData = AuthoritySubUserDetail::getUserIds($nowUserId, $uid, $corpInfo->id, []);
				$user_ids = $userData['user_ids'];
				if (!empty($user_ids)){
					array_push($user_ids, 0);
				}
				//跟进信息
				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $externalUserData->id, 'type' => 1, 'status' => 1]);
				if ($corpInfo->unshare_follow == 1 && !empty($user_ids)) {
					$followRecord = $followRecord->andWhere(['user_id' => $user_ids]);
				}
				$followNum    = $followRecord->count();
				$followRecord = $followRecord->select('`sub_id`,`user_id`,`time`')->orderBy('id DESC')->asArray()->one();

				$result['follow_num']  = $followNum;//跟进次数
				$result['follow_time'] = '';//上次跟进时间
				if ($result['follow_num'] > 0) {
					$name = '';
					if (!empty($followRecord['user_id'])) {
						$workUserFollow = WorkUser::findOne($followRecord['user_id']);
						if (!empty($workUserFollow)) {
							$name = $workUserFollow->name;
						}
					} elseif (!empty($followRecord['sub_id'])) {
						$subInfo = SubUserProfile::findOne(['sub_user_id' => $followRecord['sub_id']]);
						if (!empty($subInfo)) {
							$name = $subInfo->name;
						}
					} else {
						$name = $userInfo->nick_name;
					}

					$time                  = !empty($followRecord['time']) ? date('Y-m-d H:i:s', $followRecord['time']) : '';
					$result['follow_time'] = $name . ' ' . $time;
				}

				//归属企业成员
				/*$workExternal = WorkExternalContactFollowUser::find()->andWhere(['external_userid' => $cid])->all();
				$memberInfo   = [];
				foreach ($workExternal as $k => $user) {
					$departName                    = WorkDepartment::getDepartNameByUserId($user->user_id);
					$work_user                     = WorkUser::findOne($user->user_id);
					$member                        = $departName . '--' . $work_user->name;
					$memberInfo[$k]['member']      = $member;
					$memberInfo[$k]['del_type']    = $user->del_type;
					$memberInfo[$k]['source']      = $user->state;
					$memberInfo[$k]['create_time'] = !empty($user->createtime) ? date("Y-m-d H:i:s", $user->createtime) : '';
				}
				$result['memberInfo'] = $memberInfo;*/
				//用户标签
				$workTagContact = WorkTagFollowUser::find()->alias('w');
				$workTagContact = $workTagContact->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`')->andWhere(['t.is_del' => 0, 'w.status' => 1, 'w.follow_user_id' => $follow->id]);
				$contactTag     = $workTagContact->select('t.id,t.tagname')->asArray()->all();
				$tagName        = [];
				foreach ($contactTag as $k => $v) {
					//$workTag           = WorkTag::findOne($v['tag_id']);
					$workTagD            = [];
					$workTagD['id']      = $v['id'];
					$workTagD['tagname'] = $v['tagname'];
					$tagName[]           = $workTagD;
				}
				$result['tag_name'] = $tagName;
				$perName            = WorkPerTagFollowUser::getTagName($follow->id);
				$result['per_name'] = $perName;
				//获取所在群名称
				if ($this->corp->unshare_chat == 1 && !empty($user_ids)){
					$result['chat_name'] = WorkChatInfo::getChatList(2, $externalUserData->id, '', $this->corp->unshare_chat, $user_ids);
				}else{
					$result['chat_name'] = WorkChatInfo::getChatList(2, $externalUserData->id);
				}

				//自定义属性
				$fieldList = CustomField::getCustomField($uid, $externalUserData->id, 1, $this->corp->unshare_field, $workUser->id);
				$phone     = '';
				$area      = '';
				$hasPhone  = 0;
				$hasArea   = 0;
				foreach ($fieldList as $k => $v) {
					if ($v['key'] == 'phone') {
						$hasPhone = 1;
						$phone    = $v['value'];
					} elseif ($v['key'] == 'area') {
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
					}
				}
				if ($hasPhone == 0) {
					$fieldValue = CustomFieldValue::findOne(['type' => 1, 'fieldid' => 1, 'cid' => $externalUserData->id]);
					$phone      = !empty($fieldValue->value) ? $fieldValue->value : '';
				}
				if ($hasArea == 0) {
					$customField = CustomField::findOne(['key' => 'area', 'is_define' => 0]);
					if ($this->corp->unshare_field == 0) {
						$fieldValue = CustomFieldValue::findOne(['type' => 1, 'fieldid' => $customField->id, 'cid' => $externalUserData->id]);
					} else {
						$fieldValue = CustomFieldValue::find()->where(['type' => 1, 'fieldid' => $customField->id, 'cid' => $externalUserData->id])->andWhere(['user_id' => [0, $workUser->id]])->orderBy(['user_id' => SORT_DESC])->one();
					}
					$area = !empty($fieldValue->value) ? $fieldValue->value : '';
				}
				$result['phone']         = !empty($follow->remark_mobiles) ? $follow->remark_mobiles : '';
				$is_hide_phone           = $user->is_hide_phone;
				$result['is_hide_phone'] = $is_hide_phone;
				if ($is_hide_phone) {
					$result['phone'] = '';
				}
				$result['company'] = !empty($follow->remark_corp_name) ? $follow->remark_corp_name : '';
				$result['area']    = $area;
				$result['gender']  = $gender;

				$result['field_list'] = $fieldList;

				//套餐权限 是否有功能权限字段添加
				$packageHas = 0;
				$packageId  = $this->user->package_id;
				$endTime    = $this->user->end_time;
				if ($endTime < time()) {
					//到期使用套餐
					$defaultPackage = Package::getDefaultPackage();
					if ($defaultPackage->expire_type == 1) {
						$packageId = 0;
					} else {
						$packageId = $defaultPackage->expire_package_id;
					}
				}
				$menu = Menu::findOne(['key' => 'manage', 'level' => 1, 'status' => 1]);
				if ($menu) {
					$packageMenu = PackageMenu::findOne(['package_id' => $packageId, 'status' => 1, 'menu_id' => $menu->id]);
					if ($packageMenu) {
						$packageHas = 1;
					}
				}
				$result['packageHas'] = $packageHas;

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户高级属性详情
		 * @description     客户高级属性详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-field-detail
		 *
		 * @param uid              必选 int 用户ID
		 * @param external_userid  必选 int 客户的userid
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    field_list array 客户属性
		 * @return_param    field_list.fieldid int 属性ID
		 * @return_param    field_list.key string 属性key
		 * @return_param    field_list.title string 属性名称
		 * @return_param    field_list.type int 属性类型
		 * @return_param    field_list.optionVal string 属性选项
		 * @return_param    field_list.value string 已设置属性值
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFieldDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$uid            = \Yii::$app->request->post('uid', 0);
				$externalUserId = \Yii::$app->request->post('external_userid', '');
				$userId         = \Yii::$app->request->post('userid', '');
				if (empty($uid) || empty($externalUserId) || empty($userId)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$result           = [];
				$externalUserData = WorkExternalContact::findOne(['external_userid' => $externalUserId]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $externalUserData->id, 'userid' => $userId]);
				if (empty($followUser)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$workCorp = WorkCorp::findOne($externalUserData->corp_id);

				//自定义属性
				$fieldList = CustomField::getCustomField($uid, $externalUserData->id, 1, $workCorp->unshare_field, $followUser->user_id);

				$result['field_list'] = $fieldList;

				$desc = '';
				if (!empty($followUser->des)) {
					$desc = $followUser->des;
				}
				$result['des']           = $desc;//新增描述
				$result['phone']         = !empty($followUser->remark_mobiles) ? $followUser->remark_mobiles : '';
				$is_hide_phone           = $this->user->is_hide_phone;
				$result['is_hide_phone'] = $is_hide_phone;
				if ($is_hide_phone) {
					$result['phone'] = '';
				}
				$result['company'] = !empty($followUser->remark_corp_name) ? $followUser->remark_corp_name : '';

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           修改客户字段
		 * @description     修改客户字段
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-update
		 *
		 * @param uid    必选 int 用户ID
		 * @param external_userid  必选 int 客户的userid
		 * @param type   必选 string 修改类型：nickname昵称、des描述、close_rate预计成交率
		 * @param value  可选 string 修改值
		 * @param userid      必选 int 员工的userid
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$type            = \Yii::$app->request->post('type', '');
				$value           = \Yii::$app->request->post('value', '');
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				if (empty($userid) || empty($corp_id) || empty($type) || empty($external_userid)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $corpInfo->id, 'external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('员工数据错误！');
				}
				$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $externalUserData->id, 'user_id' => $userInfo->id]);
				if (!empty($followUser)) {
					$remark = '';
					switch ($type) {
						case 'nickname':
							if (empty($value)) {
								$value = $externalUserData->name_convert;
							}
							$followUser->nickname = $value;
							$remark               = '备注名';

							$workApi = '';
							try {
								$workApi = WorkUtils::getWorkApi($externalUserData->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}

							if ($workApi instanceof Work) {
								$sendData['userid']          = $followUser->userid;
								$sendData['external_userid'] = $externalUserData->external_userid;
								$sendData['remark']          = $value;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'WapCustomUpdate1');
							}

							break;
						case 'des':
							$followUser->des = $value;
							$remark          = '描述';
							break;
						case 'close_rate':
							if ($value < 0 || $value > 100) {
								throw new InvalidParameterException('预计成交率不正确！');
							}
							$followUser->close_rate = $value;
							$remark                 = '预计成交率';
							break;
					}

					if (!$followUser->save()) {
						throw new InvalidParameterException(SUtils::modelError($followUser));
					}

					//记录客户轨迹$eventData['user_id']
					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'user_id' => $userInfo->id, 'external_id' => $externalUserData->id, 'event' => 'set_field', 'remark' => $remark]);

					return true;
				}

				throw new InvalidParameterException('客户数据错误！');

//				$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
//				if (!empty($externalUserData)) {
//					$remark = '';
//					switch ($type) {
//						case 'nickname':
//							$externalUserData->nickname = $value;
//							$remark                     = '用户昵称';
//							break;
//						case 'des':
//							$externalUserData->des = $value;
//							$remark                = '描述';
//							break;
//						case 'close_rate':
//							if ($value < 0 || $value > 100) {
//								throw new InvalidParameterException('预计成交率不正确！');
//							}
//							$externalUserData->close_rate = $value;
//							$remark                       = '预计成交率';
//							break;
//					}
//
//					if (!$externalUserData->save()) {
//						throw new InvalidParameterException(SUtils::modelError($externalUserData));
//					}
//
//					//记录客户轨迹
//					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => $externalUserData->id, 'event' => 'set_field', 'remark' => $remark]);
//				} else {
//					throw new InvalidParameterException('客户数据错误！');
//				}
//
//				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           修改客户高级属性
		 * @description     修改客户高级属性
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-field-update
		 *
		 * @param uid         必选 int 用户ID
		 * @param external_userid  必选 int 客户的userid
		 * @param fieldData   必选 array 客户属性
		 * @param fieldData.fieldid  必选 int 属性ID
		 * @param fieldData.value    可选 int 属性值
		 * @param fieldData.key      可选 int 属性key
		 * @param des      可选 string 描述
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFieldUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$uid       = \Yii::$app->request->post('uid', 0);
				$corp_id   = \Yii::$app->request->post('corp_id', '');
				$externalUserId    = \Yii::$app->request->post('external_userid', '');
				$userId    = \Yii::$app->request->post('userid', '');
				$fieldData = \Yii::$app->request->post('fieldData', []);
				$des       = \Yii::$app->request->post('des', '');
				if (empty($uid) || empty($corp_id) || empty($externalUserId) || empty($fieldData) || empty($userId)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($workCorp)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$externalUserData = WorkExternalContact::findOne(['corp_id' => $workCorp->id, 'external_userid' => $externalUserId]);
				if(empty($externalUserData)){
					throw new InvalidParameterException('客户数据错误！');
				}
				$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $externalUserData->id, 'userid' => $userId]);
				if (empty($followUser)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				if (mb_strlen($des, 'utf-8') > 255) {
					throw new InvalidParameterException('描述不能超过255个字符！');
				}

				$time     = time();
				$uptField = '';
				foreach ($fieldData as $k => $v) {
					$fieldid = intval($v['fieldid']);
					if (!is_array($v['value'])) {
						$value = !empty($v['value']) ? trim($v['value'], ',') : '';
					} else {
						$value = $v['value'];
					}
					if (empty($fieldid)) {
						throw new InvalidParameterException('客户高级属性数据错误！');
					}
					if ($workCorp->unshare_field == 0){
						$fieldValue = CustomFieldValue::findOne(['cid' => $externalUserData->id, 'type' => 1, 'fieldid' => $fieldid]);
					}else{
						$fieldValue = CustomFieldValue::findOne(['cid' => $externalUserData->id, 'type' => 1, 'fieldid' => $fieldid, 'user_id' => $followUser->user_id]);
					}
					if (empty($fieldValue)) {
						if (empty($value)) {
							continue;
						}
						$fieldValue          = new CustomFieldValue();
						$fieldValue->type    = 1;
						$fieldValue->uid     = $uid;
						$fieldValue->cid     = $externalUserData->id;
						$fieldValue->fieldid = $fieldid;
						$fieldValue->value   = '';
						if ($workCorp->unshare_field == 1) {
							$fieldValue->user_id = $followUser->user_id;
						}
					} else {
						if (($value == $fieldValue->value) && !in_array($v['key'], ['company', 'phone'])) {
							continue;
						}
					}
					if ($v['type'] == 8) {
						$imgVal = json_decode($fieldValue->value, true);
						if ($imgVal == $value) {
							continue;
						}
						$value = json_encode($value);
					}

					if ($v['key'] == 'company') {
						if (($followUser->remark_corp_name == $value) && ($fieldValue->value == $value)) {
							continue;
						}
						//公司
						if ($followUser->remark_corp_name != $value) {
							$workApi = '';
							try {
								$workApi = WorkUtils::getWorkApi($externalUserData->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}
							if ($workApi instanceof Work) {
								$sendData['userid']          = $followUser->userid;
								$sendData['external_userid'] = $externalUserData->external_userid;
								$sendData['remark_company']  = $value;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'WapCustomUpdate3');
							}
							$followUser->remark_corp_name = $value;
						}
					}

					$fieldValue->uid   = $uid;
					$fieldValue->value = $value;
					$fieldValue->time  = $time;

					if ($v['key'] == 'phone') {
						if (!empty($value)) {
							$phones = explode(',', $value);
						} else {
							$phones = '';
						}
						if (($followUser->remark_mobiles == $value) && ($fieldValue->value == $value)) {
							continue;
						}
//						foreach ($phones as $phone) {
//							if (strlen($phone) == 11 && !preg_match("/^1[34578]{1}\d{9}$/", $phone)) {
//								throw new InvalidParameterException('手机号格式不正确！');
//							}
//						}

						if ($followUser->remark_mobiles != $value) {
							$workApi = '';
							try {
								$workApi = WorkUtils::getWorkApi($externalUserData->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}
							if ($workApi instanceof Work) {
								$sendData['userid']          = $followUser->userid;
								$sendData['external_userid'] = $externalUserData->external_userid;
								$sendData['remark_mobiles']  = $phones;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'WapCustomUpdate2');
							}

							$followUser->remark_mobiles = $value;
						}
					} elseif ($v['key'] == 'email' && !empty($value)) {
						if (!preg_match("/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/", $value)) {
							throw new InvalidParameterException('邮箱格式不正确！');
						}
					}

					if (!$fieldValue->save()) {
						throw new InvalidParameterException(SUtils::modelError($fieldValue));
					}
					$uptField .= $fieldid . ',';
				}

				//描述修改
				$des    = trim($des);
				$is_des = 0;
//				if ($externalUserData->des != $des) {
//					$externalUserData->des = trim($des);
//					$externalUserData->update();
//					$is_des = 1;
//				}
				if ($followUser->des != $des) {
					$followUser->des = trim($des);
					$followUser->update();
					$is_des = 1;

					$workApi = '';
					try {
						$workApi = WorkUtils::getWorkApi($externalUserData->corp_id, WorkUtils::EXTERNAL_API);
					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), 'getMessage');
					}

					if ($workApi instanceof Work) {
						$sendData['userid']          = $followUser->userid;
						$sendData['external_userid'] = $externalUserData->external_userid;
						$sendData['description']     = $des;
						$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
						$data                        = $workApi->ECRemark($contactRemark);
						$result                      = SUtils::Object2Array($data);
						\Yii::error($result, 'WapCustomUpdate');
					}
				}

				$followUser->save();
				//记录客户轨迹
				if (!empty($uptField) || !empty($is_des)) {
					$remark = !empty($is_des) ? '描述、' : '';
					if (!empty($uptField)) {
						$customField = CustomField::find()->where('id IN (' . trim($uptField, ',') . ')')->select('`title`')->asArray()->all();
						foreach ($customField as $v) {
							$remark .= $v['title'] . '、';
						}
					}

					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'user_id' => $followUser->user_id, 'external_id' => $externalUserData->id, 'event' => 'set_field', 'remark' => trim($remark, '、')]);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户跟进记录
		 * @description     客户跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-follow-record
		 *
		 * @param corp_id     必选 int 企业ID
		 * @param uid         必选 int 用户ID
		 * @param userid      必选 int 员工的userid
		 * @param now_userid  可选 int 当前员工的userid
		 * @param external_userid  必选 int 客户的userid
		 * @param page        可选 int 页码
		 * @param page_size   可选 int 每页数据量，默认15
		 *
		 *
		 * @return          {"error":0,"data":{}}
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
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFollowRecord ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$userid          = \Yii::$app->request->post('userid', '');
				$now_userid      = \Yii::$app->request->post('now_userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				if (empty($uid) || empty($userid) || empty($external_userid) || empty($corp_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}

				$offset = ($page - 1) * $pageSize;

				$userInfo = UserProfile::findOne(['uid' => $uid]);
//				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $externalUserData->id, 'type' => 1, 'status' => 1]);
//				$count        = $followRecord->count();
//
//				$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC])->asArray()->all();
//
//				foreach ($followRecord as $k => $v) {
//					$can_edit = 0;
//					if (!empty($v['user_id']) && $v['is_master']==1) {
//						$workUser = WorkUser::findOne($v['user_id']);
//						$name = '';
//						$can_edit = 0;
//						if(!empty($workUser)){
//							$name     = $workUser->name;
//							$can_edit = $workUser->userid == $userid ? 1 : 0;
//						}
//
//					} elseif (!empty($v['sub_id']) && $v['is_master']==1) {
//						$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
//						$name    = $subInfo->name;
//					} else {
//						$name = $userInfo->nick_name;
//					}
//					$followRecord[$k]['name']     = $name;
//					$followRecord[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
//					$followRecord[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
//					$followRecord[$k]['can_edit'] = $can_edit;
//					$follow_status                = '';
//					if ($v['follow_id']) {
//						$follow        = Follow::findOne($v['follow_id']);
//						$follow_status = $follow->title;
//						if ($follow->status == 0) {
//							$follow_status .= '（已删除）';
//						}
//					}
//					$followRecord[$k]['follow_status'] = $follow_status;
//				}

				$unixType     = '%Y-%m-%d';
				$select       = new Expression('FROM_UNIXTIME(time, \'' . $unixType . '\') time');
				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $externalUserData->id, 'type' => 1, 'status' => 1]);
				$extFollow = WorkExternalContactFollowUser::findOne(["external_userid"=>$externalUserData->id,"userid"=>$userid]);
				if ($corpInfo->unshare_follow == 1) {
					$now_userid = !empty($now_userid) ? $now_userid : $userid;
					$userData   = AuthoritySubUserDetail::getUserIds($now_userid, $this->user->uid, $this->corp->id, []);
					$user_ids   = $userData['user_ids'];

					if (!empty($user_ids)) {
						array_push($user_ids, 0);
					}
				}
				if (!empty($user_ids)){
					$followRecord = $followRecord->andWhere(['user_id' => $user_ids]);
				}
				$followRecord = $followRecord->select($select)->groupBy('time');

				$count        = count(SUtils::array_unset_tt($followRecord->asArray()->all(), 'time'));
				$followRecord = $followRecord->limit($pageSize)->offset($offset);
				$followRecord = $followRecord->orderBy(['time' => SORT_DESC]);
				$followRecord = $followRecord->asArray()->all();
				$info         = [];
				if (!empty($followRecord)) {
					foreach ($followRecord as $key => $line) {
						$pro      = [];
						$sTime    = strtotime($line['time']);
						$eTime    = strtotime($line['time'] . ' 23:59:59');
						$lineData = WorkExternalContactFollowRecord::find()->where(['external_id' => $externalUserData->id, 'type' => 1, 'status' => 1])->andFilterWhere(['between', 'time', $sTime, $eTime]);
						if ($corpInfo->unshare_follow == 1 && $user_ids){
							$lineData = $lineData->andWhere(['user_id' => $user_ids]);
						}
						$lineData = $lineData->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC])->asArray()->all();
						if (!empty($lineData)) {
							foreach ($lineData as $k => $v) {
								$pro[$k]['time'] = date('H:i', $v['time']);
								$can_edit        = 0;
								$name     = '';
								if(!empty($extFollow) && $extFollow->del_type != 2){
									if (!empty($v['user_id']) && $v['is_master'] == 1) {
										$workUser = WorkUser::findOne($v['user_id']);
										$can_edit = 0;
										if (!empty($workUser)) {
											$name     = $workUser->name;
											$can_edit = $workUser->userid == $userid ? 1 : 0;
										}

									} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
										$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
										$name    = $subInfo->name;
									} else {
										$name = $userInfo->nick_name;
									}
								}
								$pro[$k]['record']    = $v['record'];
								$pro[$k]['follow_id'] = $v['follow_id'];
								$pro[$k]['name']      = $name;
//								$pro[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
								$pro[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
								$pro[$k]['can_edit'] = $can_edit;
								$follow_status       = '';
								if ($v['follow_id']) {
									$follow        = Follow::findOne($v['follow_id']);
									$follow_status = $follow->title;
									if ($follow->status == 0) {
										$follow_status .= '（已删除）';
									}
								}
								$pro[$k]['follow_status'] = $follow_status;
							}

						}
						$info[$key]['date'] = $line['time'];
						$info[$key]['data'] = $pro;
					}
				}
				$info = SUtils::array_unset_tt($info, 'date');
				$info = array_values($info);

				return [
					'count'        => $count,
					'followRecord' => $info,
				];
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           添加客户跟进记录
		 * @description     添加客户跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-follow-record-set
		 *
		 * @param uid         必选 int 用户ID
		 * @param corp_id     必选 int 企业ID
		 * @param userid      必选 int 员工的userid
		 * @param external_userid  必选 int 客户的userid
		 * @param follow_id   必选 int 跟进状态id
		 * @param record_id   可选 int 记录ID
		 * @param record      可选 string 记录内容
		 * @param file        可选 array 图片附件链接
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFollowRecordSet ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$nowUserId       = \Yii::$app->request->post('now_userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$follow_id       = \Yii::$app->request->post('follow_id', 0);
				$record_id       = \Yii::$app->request->post('record_id', 0);
				$record          = \Yii::$app->request->post('record', '');
				$file            = \Yii::$app->request->post('file', '');
				$record          = trim($record);
				\Yii::error($external_userid, '$external_userid0');
				if (empty($uid) || empty($userid) || empty($external_userid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($follow_id)) {
					throw new InvalidParameterException('请选择跟进状态！');
				}
				if (empty($record) && empty($file)) {
					throw new InvalidParameterException('跟进内容和附件至少要填写一个！');
				}
				$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
				$nowWorkUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $nowUserId]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('员工数据错误！');
				}
				$followInfo = Follow::findOne(['id' => $follow_id, 'status' => 1]);
				if (empty($followInfo)) {
					throw new InvalidParameterException('跟进状态已被删除，请更换！');
				}
				//更新跟进状态
				$externalUserData->follow_id = $follow_id;
				if (!$externalUserData->save()) {
					throw new InvalidParameterException(SUtils::modelError($externalUserData));
				}
				$oldFollowId = 0;
				$followUser  = WorkExternalContactFollowUser::findOne(['external_userid' => $externalUserData->id, 'user_id' => $userInfo->id]);
				if (!empty($followUser)) {
					$oldFollowId             = $followUser->follow_id;
					$followUser->is_chat     = WorkExternalContactFollowUser::HAS_CHAT;
					$followUser->follow_id   = $follow_id;
					$followUser->update_time = time();
					$follow_num              = $followUser->follow_num;
					$followUser->follow_num  = $follow_num + 1;
					$followUser->save();
				}

				if (empty($record_id)) {
					$followRecord              = new WorkExternalContactFollowRecord();
					$followRecord->uid         = $uid;
					$followRecord->type        = 1;
					$followRecord->external_id = $externalUserData->id;
					$followRecord->user_id     = $nowWorkUser->id;
					$followRecord->status      = 1;
					$followRecord->sub_id      = 0;
					$followRecord->is_master   = 1;
					$followRecord->time        = time();
				} else {
					$followRecord           = WorkExternalContactFollowRecord::findOne($record_id);
					$followRecord->upt_time = time();
				}
				$followRecord->record    = $record;
				$followRecord->file      = !empty($file) ? json_encode($file) : '';
				$followRecord->follow_id = $follow_id;

				if (!$followRecord->save()) {
					throw new InvalidParameterException(SUtils::modelError($followRecord));
				}

				//记录客户轨迹
				if (empty($record_id)) {
					$count = WorkExternalContactFollowRecord::find()->where(['external_id' => $externalUserData->id, 'type' => 1, 'status' => 1])->count();//跟进次数
					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => $externalUserData->id, 'user_id' => $nowWorkUser->id, 'event' => 'follow', 'event_id' => $follow_id, 'related_id' => $followRecord->id, 'remark' => $count]);
				}

				if ($oldFollowId > 0 && $oldFollowId != $follow_id) {
					$waitTask = WaitTask::find()->alias('w')->leftJoin('{{%wait_project}} p', 'w.project_id=p.id')->where(['w.follow_id' => $follow_id, 'p.is_del' => 0, 'w.is_del' => 0])->all();
					if (!empty($waitTask)) {
						$jobId = \Yii::$app->queue->push(new WaitUserTaskJob([
							'followUserId' => $followUser->id,
							'followId'     => $follow_id,
							'type'         => 3,
							'corpId'       => $this->corp->id,
							'daysNew'      => 0,
						]));
					}
					WaitCustomerTask::deleteData($externalUserData->id, '', $oldFollowId);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户互动轨迹
		 * @description     客户互动轨迹
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-track
		 *
		 * @param uid         必选 int 用户ID
		 * @param corp_id     必选 int 企业微信id
		 * @param userid      必选 int 员工userid
		 * @param external_userid  必选 int 客户的userid
		 * @param page        可选 int 页码
		 * @param page_size   可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    event_time string 时间
		 * @return_param    content string 内容
		 * @return_param    icon int 图标
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionCustomTrack ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$corp_id         = \Yii::$app->request->post('corp_id', '');
				$userid          = \Yii::$app->request->post('userid', '');
				$now_userid      = \Yii::$app->request->post('now_userid', '');
				$external_userid = \Yii::$app->request->post('external_userid', '');
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($uid) || empty($corp_id) || empty($userid) || empty($external_userid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				$offset = ($page - 1) * $pageSize;

				$externalUserData = WorkExternalContact::findOne(['external_userid' => $external_userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}

//				$count         = ExternalTimeLine::find()->where(['external_id' => $externalUserData->id])->count();
//				$external_line = ExternalTimeLine::find()->where(['external_id' => $externalUserData->id])->limit($pageSize)->offset($offset)->orderBy(['event_time' => SORT_DESC])->asArray()->all();
//				$list          = ExternalTimeLine::getExternalTimeLine($uid, $external_line);

				if ($corpInfo->unshare_line == 1) {
					$now_userid   = !empty($now_userid) ? $now_userid : $userid;
					$userData     = AuthoritySubUserDetail::getUserIds($now_userid, $this->user->uid, $this->corp->id, []);
					$user_ids     = $userData['user_ids'];
					if (!empty($user_ids)){
						array_push($user_ids, 0);
					}
				}

				$unixType     = '%Y-%m-%d';
				$select       = new Expression('FROM_UNIXTIME(event_time, \'' . $unixType . '\') time');
				$externalLine = ExternalTimeLine::find()->where(['external_id' => $externalUserData->id]);
				if ($corpInfo->unshare_line == 1 && !empty($user_ids)) {
					$externalLine = $externalLine->andWhere(['user_id' => $user_ids]);
				}
				$externalLine = $externalLine->andWhere(['!=', 'event', 'chat_track_money']);
				$externalLine = $externalLine->select($select)->groupBy('time');

				$count        = count(SUtils::array_unset_tt($externalLine->asArray()->all(), 'time'));
				$externalLine = $externalLine->limit($pageSize)->offset($offset);
				$externalLine = $externalLine->orderBy(['event_time' => SORT_DESC]);
				$externalLine = $externalLine->asArray()->all();
				$info         = [];
				if (!empty($externalLine)) {
					foreach ($externalLine as $key => $line) {
						$pro      = [];
						$sTime    = strtotime($line['time']);
						$eTime    = strtotime($line['time'] . ' 23:59:59');
						$lineData = ExternalTimeLine::find()->where(['external_id' => $externalUserData->id]);
						if ($corpInfo->unshare_line == 1 && !empty($user_ids)) {
							$lineData = $lineData->andWhere(['user_id' => $user_ids]);
						}
						$lineData = $lineData->andWhere(['!=', 'event', 'chat_track_money'])->andFilterWhere(['between', 'event_time', $sTime, $eTime]);
						$lineData = $lineData->orderBy(['event_time' => SORT_DESC])->asArray()->all();
						if (!empty($lineData)) {
							foreach ($lineData as $k => $val) {
								$pro[$k]['time']    = date('H:i', $val['event_time']);
								$content            = ExternalTimeLine::getExternalTimeLine($uid, [$val]);
								$pro[$k]['content'] = $content[0]['content'];
							}
						}
						$info[$key]['date'] = $line['time'];
						$info[$key]['data'] = $pro;
					}
				}
				$info = SUtils::array_unset_tt($info, 'date');
				$info = array_values($info);

				return ['count' => $count, 'info' => $info];
			} else {
				throw new InvalidParameterException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           设置客户标签
		 * @description     设置客户标签
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/custom-tags-set
		 *
		 * @param uid         必选 int 用户ID
		 * @param corp_id     必选 int 企业ID
		 * @param external_userid  必选 int 客户的userid
		 * @param group_id  可选 int 分组id
		 * @param tagData   必选 array 客户标签
		 * @param tagData.tid  可选 int 标签ID
		 * @param tagData.tname  必选 string 标签名
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomTagsSet ()
		{
			if (\Yii::$app->request->isPost) {
				$uid      = \Yii::$app->request->post('uid', 0);
				$corp_id  = \Yii::$app->request->post('corp_id', '');
				$userid   = \Yii::$app->request->post('external_userid', '');
				$userId   = \Yii::$app->request->post('userid', '');
				$group_id = \Yii::$app->request->post('group_id', 0);
				$tagData  = \Yii::$app->request->post('tagData', []);
				$nowUserId  = \Yii::$app->request->post('now_userid', '');
				if (empty($uid) || empty($corp_id) || empty($userid) || empty($userId)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
				if (empty($corpInfo)) {
					throw new InvalidParameterException('企业微信数据错误！');
				}
				if ($corpInfo->corp_type != 'verified') {
					throw new InvalidParameterException('当前企业号未认证！');
				}
				$externalUserData = WorkExternalContact::findOne(['external_userid' => $userid]);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $externalUserData->id, 'userid' => $userId]);
				if (empty($followUser)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				if (count($tagData) > 9999) {
					throw new InvalidParameterException('客户标签数量不能超过9999个！');
				}
				$nowWorkUser = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $nowUserId]);
				$relatedId = !empty($nowWorkUser) ? $nowWorkUser->id : 0;

				$newTag = [];//新创建的标签
				$tagNow = [];//现有标签
				$tagOld = [];//客户原有标签
				//$contactTag = WorkTagContact::find()->where(['contact_id' => $externalUserData->id])->all();
				$contactTag = WorkTagFollowUser::find()->where(['follow_user_id' => $followUser->id, 'status' => 1])->all();
				foreach ($contactTag as $k => $v) {
					array_push($tagOld, $v['tag_id']);
				}

				foreach ($tagData as $k => $v) {
					if (!empty($v['id'])) {
						array_push($tagNow, $v['id']);
					} else {
//						if (empty(trim($v['tname']))) {
//							throw new InvalidParameterException('标签名称不能为空');
//						}
//						$len = mb_strlen($v['tname'], "utf-8");
//						if ($len > 15) {
//							throw new InvalidParameterException('标签名称不能超过15个字');
//						}
//
//						array_push($newTag, $v['tname']);
					}
				}

				$tagAdd = array_diff($tagNow, $tagOld);//添加的客户标签
				$tagDel = array_diff($tagOld, $tagNow);//删除的客户标签

				//创建标签
				if (!empty($newTag)) {
					if (count($newTag) != count(array_unique($newTag))) {
						throw new InvalidParameterException('标签名称存在重复');
					}
					if (empty($group_id)) {
						throw new InvalidParameterException('请选择创建标签的分组！');
					}
					$tagName = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpInfo->id])->one();
					if (!empty($tagName)) {
						throw new InvalidParameterException('创建标签名称与现有标签重复：' . $tagName->tagname);
					}

					WorkTag::add(0, $corpInfo->id, $newTag, 0, $group_id);

					$newTagData = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpInfo->id])->asArray()->all();
					if (count($newTagData) != count($newTag)) {
						throw new InvalidParameterException('新建标签数据错误！');
					}
					//添加新建标签
					foreach ($newTagData as $v) {
						array_push($tagAdd, $v['id']);
					}
				}

				//$user_ids[] = $externalUserData->id;
				$user_ids[] = $followUser->id;
				//添加客户标签
				if (!empty($tagAdd)) {
					WorkTag::addUserTag(2, $user_ids, array_values($tagAdd), ['user_id' => $relatedId]);
				}
				//删除客户标签
				if (!empty($tagDel)) {
					WorkTag::removeUserTag(2, $user_ids, array_values($tagDel), ['user_id' => $relatedId]);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           跟进状态列表
		 * @description     跟进状态列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/follow
		 *
		 * @param uid 必选 string 用户ID
		 * @param status 可选 string 状态
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
			$uid    = \Yii::$app->request->post('uid', 0);
			$status = \Yii::$app->request->post('status', 0);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$follow = Follow::find()->where(['uid' => $uid]);
			if (!empty($status)) {
				$follow = $follow->andWhere(['status' => $status]);
			}
			$follow = $follow->select('id,uid,title,status')->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])->all();

			return [
				'follow' => $follow
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户群详情
		 * @description     客户群详情
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/chat-detail
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param chat_id 必选 string 客户群ID
		 *
		 * @return          {"error":0,"data":{"access_token":"TWFpblVzZXItYjRjMGE5YjU0NWNmMTczNTYwY2Y1MzI0YWQ5ODBlMGE=","name":"123","notice":"阿鲁","follow_status":1,"follow_id":1,"follow_title":"未跟进","is_follow_del":0,"tag_name":[{"tid":915,"tname":"20"},{"tid":911,"tname":"测试同步"},{"tid":912,"tname":"重要重要"}],"all_sum":"3","user_sum":"2","external_sum":"1","no_external_sum":"0"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    access_token string token数据
		 * @return_param    name string 群名称
		 * @return_param    notice string 群公告
		 * @return_param    follow_status string 跟进状态
		 * @return_param    follow_id string 跟进状态id
		 * @return_param    follow_title string 跟进状态名称
		 * @return_param    is_follow_del string 跟进状态是否删除
		 * @return_param    tag_name array 标签数据
		 * @return_param    all_sum string 群成员总数
		 * @return_param    user_sum string 群企业成员总数
		 * @return_param    external_sum string 外部联系人总数
		 * @return_param    no_external_sum string 非外部联系人总数
		 * @return_param    create_time string 群创建时间
		 * @return_param    today_join_sum string 今日入群数
		 * @return_param    today_leave_sum string 今日离群数
		 * @return_param    isAudit int 是否显示会话存档
		 * @return_param    todayAuditNum int 今日活跃数
		 * @return_param    fieldList array 群画像数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-18 16:59
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid     = \Yii::$app->request->post('uid', 0);
			$corp_id = \Yii::$app->request->post('corp_id', '');
			$chatId  = \Yii::$app->request->post('chat_id', 0);

			if (empty($uid) || empty($chatId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$user         = User::findOne($uid);
			$user_type    = User::USER_TYPE;
			$access_token = base64_encode($user_type . '-' . $user->access_token);
			$workCorp     = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workChat = WorkChat::findOne(['corp_id' => $workCorp->id, 'chat_id' => $chatId]);
			if (empty($workChat)) {
				throw new InvalidParameterException("抱歉，群详情页无法打开，请检查下：\n1、群主为非企业成员\n2、该客户群目前没有同步到后台，请管理员同步下。");
			}

			$result                  = WorkChat::getChatDetail($uid, $workChat);
			$result['access_token']  = $access_token;
			$is_hide_phone           = $user->is_hide_phone;
			$result['is_hide_phone'] = $is_hide_phone;

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户群跟进记录
		 * @description     客户群跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/chat-follow-record
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param chat_id 必选 string 客户群ID
		 * @param userid 必选 string 当前成员
		 * @param page 可选 string 页码
		 * @param page_size 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-18 19:08
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatFollowRecord ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$chatId   = \Yii::$app->request->post('chat_id', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);
			$userid   = \Yii::$app->request->post('userid', '');
			if (empty($uid) || empty($chatId) || empty($corp_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			$workChat = WorkChat::findOne(['corp_id' => $workCorp->id, 'chat_id' => $chatId]);
			if (empty($workChat)) {
				throw new InvalidParameterException('群数据错误！');
			}

			$offset = ($page - 1) * $pageSize;

			$unixType = '%Y-%m-%d';
			$select   = new Expression('FROM_UNIXTIME(time, \'' . $unixType . '\') time');

			$userInfo     = UserProfile::findOne(['uid' => $uid]);
			$followRecord = WorkExternalContactFollowRecord::find()->where(['chat_id' => $workChat->id, 'type' => 3, 'status' => 1])->select($select)->groupBy('time');
			$count        = count(SUtils::array_unset_tt($followRecord->asArray()->all(), 'time'));
			$followRecord = $followRecord->limit($pageSize)->offset($offset)->select($select)->orderBy(['time' => SORT_DESC])->asArray()->all();
			$info         = [];
			if(!empty($followRecord)){
				foreach ($followRecord as $key => $line) {
					$pro      = [];
					$sTime    = strtotime($line['time']);
					$eTime    = strtotime($line['time'] . ' 23:59:59');
					$lineData = WorkExternalContactFollowRecord::find()->where(['chat_id' => $workChat->id, 'type' => 3, 'status' => 1])->andFilterWhere(['between', 'time', $sTime, $eTime])->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`,`follow_id`,`is_master`')->orderBy(['time' => SORT_DESC])->asArray()->all();
					if (!empty($lineData)) {
						foreach ($lineData as $k => $v) {
							$pro[$k]['time']    = date('H:i', $v['time']);
							$can_edit = 0;
							if (!empty($v['user_id']) && $v['is_master']==1) {
								$workUser = WorkUser::findOne($v['user_id']);
								$name = '';
								$can_edit = 0;
								if(!empty($workUser)){
									$name     = $workUser->name;
									$can_edit = $workUser->userid == $userid ? 1 : 0;
								}

							} elseif (!empty($v['sub_id']) && $v['is_master']==1) {
								$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
								$name    = $subInfo->name;
							} else {
								$name = $userInfo->nick_name;
							}
							$pro[$k]['record']    = $v['record'];
							$pro[$k]['follow_id'] = $v['follow_id'];
							$pro[$k]['name']      = $name;
//								$pro[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
							$pro[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
							$pro[$k]['can_edit'] = $can_edit;
							$follow_status       = '';
							if ($v['follow_id']) {
								$follow        = Follow::findOne($v['follow_id']);
								$follow_status = $follow->title;
								if ($follow->status == 0) {
									$follow_status .= '（已删除）';
								}
							}
							$pro[$k]['follow_status'] = $follow_status;
						}

					}
					$info[$key]['date'] = $line['time'];
					$info[$key]['data'] = $pro;
				}
			}


//			foreach ($followRecord as $k => $v) {
//				$can_edit = 0;
//				if (!empty($v['user_id']) && $v['is_master'] == 1) {
//					$workUser = WorkUser::findOne($v['user_id']);
//					$name     = $workUser->name;
//					$can_edit = $workUser->userid == $userid ? 1 : 0;
//				} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
//					$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
//					$name    = $subInfo->name;
//				} else {
//					$name = $userInfo->nick_name;
//				}
//				$followRecord[$k]['name']     = $name;
//				$followRecord[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
//				$followRecord[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
//				$followRecord[$k]['can_edit'] = $can_edit;
//				$follow_status                = '';
//				if (!empty($v['follow_id'])) {
//					$follow        = Follow::findOne($v['follow_id']);
//					$follow_status = $follow->title;
//					if ($follow->status == 0) {
//						$follow_status .= '（已删除）';
//					}
//				}
//				$followRecord[$k]['follow_status'] = $follow_status;
//			}

			$info = SUtils::array_unset_tt($info, 'date');
			$info = array_values($info);

			return [
				'count'        => $count,
				'followRecord' => $info,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           添加客户群跟进记录
		 * @description     添加客户群跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/chat-follow-record-set
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param chat_id 必选 string 客户群ID
		 * @param userid 必选 string 当前成员
		 * @param follow_id 必选 string 跟进状态id
		 * @param record_id 可选 string 跟进记录id
		 * @param record 必选 string 跟进内容
		 * @param file 可选 string 跟进图片
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-18 19:11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatFollowRecordSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$corp_id   = \Yii::$app->request->post('corp_id', '');
			$chatId    = \Yii::$app->request->post('chat_id', '');
			$userid    = \Yii::$app->request->post('userid', '');
			$follow_id = \Yii::$app->request->post('follow_id', 0);
			$record_id = \Yii::$app->request->post('record_id', 0);
			$record    = \Yii::$app->request->post('record', '');
			$file      = \Yii::$app->request->post('file', '');
			$record    = trim($record);
			if (empty($uid) || empty($userid) || empty($chatId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($follow_id)) {
				throw new InvalidParameterException('请选择跟进状态！');
			}
			if (empty($record) && empty($file)) {
				throw new InvalidParameterException('跟进内容和附件至少要填写一个！');
			}
			$workCorp = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($workCorp)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}

			$userInfo = WorkUser::findOne(['corp_id' => $workCorp->id, 'userid' => $userid]);
			if (empty($userInfo)) {
				throw new InvalidParameterException('员工数据错误！');
			}

			$workChat = WorkChat::findOne(['corp_id' => $workCorp->id, 'chat_id' => $chatId]);
			if (empty($workChat)) {
				throw new InvalidParameterException('群数据错误！');
			}
			if ($workChat->status == 4) {
				throw new InvalidParameterException('群已解散，不能再进行操作！');
			}

			$followInfo = Follow::findOne(['id' => $follow_id, 'status' => 1]);
			if (empty($followInfo)) {
				throw new InvalidParameterException('跟进状态已被删除，请更换！');
			}
			//更新跟进状态
			$workChat->follow_id   = $follow_id;
			$workChat->update_time = time();
			if (!$workChat->save()) {
				throw new InvalidParameterException(SUtils::modelError($workChat));
			}

			if (empty($record_id)) {
				$followRecord            = new WorkExternalContactFollowRecord();
				$followRecord->uid       = $uid;
				$followRecord->type      = 3;
				$followRecord->chat_id   = $workChat->id;
				$followRecord->user_id   = $userInfo->id;
				$followRecord->status    = 1;
				$followRecord->sub_id    = 0;
				$followRecord->is_master = 1;
				$followRecord->time      = time();
			} else {
				$followRecord           = WorkExternalContactFollowRecord::findOne($record_id);
				$followRecord->upt_time = time();
			}
			$followRecord->record    = $record;
			$followRecord->file      = !empty($file) ? json_encode($file) : '';
			$followRecord->follow_id = $follow_id;

			if (!$followRecord->save()) {
				throw new InvalidParameterException(SUtils::modelError($followRecord));
			}

			//记录客户群轨迹
			if (empty($record_id)) {
				$remark = '【' . $userInfo->name . '】' . '跟进状态为【' . $followInfo->title . '】';
				ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => 0, 'user_id' => $userInfo->id, 'event' => 'chat_track', 'event_id' => 13, 'related_id' => $workChat->id, 'remark' => $remark]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           设置群标签
		 * @description     设置群标签
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/chat-tags-set
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param chat_id 必选 string 客户群ID
		 * @param userid 必选 string 当前成员
		 * @param group_id 必选 string 分组id
		 * @param tagData 必选 array 标签
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-18 19:14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		public function actionChatTagsSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$chatId   = \Yii::$app->request->post('chat_id', '');
			$userid   = \Yii::$app->request->post('userid', '');
			$group_id = \Yii::$app->request->post('group_id', 0);
			$tagData  = \Yii::$app->request->post('tagData', []);
			if (empty($uid) || empty($corp_id) || empty($userid) || empty($chatId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}
			if ($corpInfo->corp_type != 'verified') {
				throw new InvalidParameterException('当前企业号未认证！');
			}

			$workChat = WorkChat::findOne(['corp_id' => $corpInfo->id, 'chat_id' => $chatId]);
			if (empty($workChat)) {
				throw new InvalidParameterException('群数据错误！');
			}
			if ($workChat->status == 4) {
				throw new InvalidParameterException('群已解散，不能再进行操作！');
			}

			$userInfo = WorkUser::findOne(['corp_id' => $corpInfo->id, 'userid' => $userid]);
			if (empty($userInfo)) {
				throw new InvalidParameterException('员工数据错误！');
			}
			if (count($tagData) > 9999) {
				throw new InvalidParameterException('选择的标签数量不能超过9999个！');
			}

			$newTag = [];//新创建的标签
			$tagNow = [];//现有标签
			$tagOld = [];//客户原有标签

			$workTagContact = WorkTagChat::find()->alias('w');
			$workTagContact = $workTagContact->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`')->andWhere(['t.is_del' => 0, 'w.status' => 1, 'w.chat_id' => $workChat->id]);
			$workTagContact = $workTagContact->select('w.*');
			$contactTag     = $workTagContact->all();
			foreach ($contactTag as $k => $v) {
				array_push($tagOld, $v->tag_id);
			}

			foreach ($tagData as $k => $v) {
				if (!empty($v['id'])) {
					array_push($tagNow, $v['id']);
				} else {
					if (empty(trim($v['tagname']))) {
						throw new InvalidParameterException('标签名称不能为空');
					}
					$len = mb_strlen($v['tagname'], "utf-8");
					if ($len > 15) {
						throw new InvalidParameterException('标签名称不能超过15个字');
					}

					array_push($newTag, $v['tagname']);
				}
			}

			$tagAdd = array_diff($tagNow, $tagOld);//添加的客户标签
			$tagDel = array_diff($tagOld, $tagNow);//删除的客户标签

			//创建标签
			if (!empty($newTag)) {
				if (count($newTag) != count(array_unique($newTag))) {
					throw new InvalidParameterException('标签名称存在重复');
				}
				if (empty($group_id)) {
					throw new InvalidParameterException('请选择创建标签的分组！');
				}
				$tagName = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpInfo->id])->one();
				if (!empty($tagName)) {
					throw new InvalidParameterException('创建标签名称与现有标签重复：' . $tagName->tagname);
				}

				WorkTag::add(0, $corpInfo->id, $newTag, 0, $group_id);

				$newTagData = WorkTag::find()->andWhere(['tagname' => $newTag, 'is_del' => 0, 'type' => 0, 'corp_id' => $corpInfo->id])->asArray()->all();
				if (count($newTagData) != count($newTag)) {
					throw new InvalidParameterException('新建标签数据错误！');
				}
				//添加新建标签
				foreach ($newTagData as $v) {
					array_push($tagAdd, $v['id']);
				}
			}
			$remark = '';
			if ($workChat->owner_id == $userInfo->id) {
				$remark .= '群主【' . $userInfo->name . '】';
			} else {
				$remark .= '群企业成员【' . $userInfo->name . '】';
			}
			$chatName = WorkChat::getChatName($workChat->id);
			$remark   .= '给【' . $chatName . '】';
			//添加群标签
			if (!empty($tagAdd)) {
				$tagAdd = array_values($tagAdd);
				//添加轨迹 群主/群企业成员【XXX】给【群名称】打标签【标签A】、【标签B】
				$addData   = ['uid' => 0, 'event' => 'chat_track', 'event_id' => 4, 'related_id' => $workChat->id, 'user_id' => $userInfo->id];
				$addRemark = $remark . '打标签';
				$workTag   = WorkTag::find()->where(['id' => $tagAdd, 'is_del' => 0])->all();
				$tagName   = '';
				$tagIdArr  = [];
				if (!empty($workTag)) {
					foreach ($workTag as $tag) {
						$tagName .= '【' . $tag->tagname . '】、';
						array_push($tagIdArr, $tag->id);
					}
				}
				//给群打标签
				if (!empty($tagIdArr)) {
					WorkTagChat::addChatTag($corpInfo->id, $workChat->id, $tagIdArr);
				}

				if (!empty($tagName)) {
					$tagName           = rtrim($tagName, '、');
					$addData['remark'] = $addRemark . $tagName;
					\Yii::error($addData, 'add_tag');
					ExternalTimeLine::addExternalTimeLine($addData);
				}
			}
			//删除群标签
			\Yii::error($tagDel, '$tagDel');
			if (!empty($tagDel)) {
				$tagDel = array_values($tagDel);
				\Yii::error($tagDel, '$tagDel1');
				WorkTagChat::removeChatTag($workChat->id, $tagDel);
				//删除轨迹  群主/群企业成员【XXX】给【群名称】移除标签【标签A】、【标签B】
				$addData      = ['uid' => 0, 'event' => 'chat_track', 'event_id' => 5, 'related_id' => $workChat->id, 'user_id' => $userInfo->id];
				$removeRemark = $remark . '移除标签';
				$workTag      = WorkTag::find()->where(['id' => $tagDel])->all();
				$tagName      = '';
				if (!empty($workTag)) {
					foreach ($workTag as $tag) {
						$tagName .= '【' . $tag->tagname . '】、';
					}
				}
				if (!empty($tagName)) {
					$tagName           = rtrim($tagName, '、');
					$addData['remark'] = $removeRemark . $tagName;
					\Yii::error($addData, 'remove_tag');
					ExternalTimeLine::addExternalTimeLine($addData);
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-custom-detail/
		 * @title           客户群行为轨迹
		 * @description     客户群行为轨迹
		 * @method   post
		 * @url  http://{host_name}/api/wap-custom-detail/chat-track
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 必选 string 授权方企业微信id
		 * @param chat_id 必选 string 客户群ID
		 * @param page 可选 string 页码
		 * @param page_size 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-18 19:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatTrack ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$corp_id  = \Yii::$app->request->post('corp_id', '');
			$chatId   = \Yii::$app->request->post('chat_id', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('page_size', 15);
			if (empty($uid) || empty($corp_id) || empty($chatId)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$corpInfo = WorkCorp::findOne(['corpid' => $corp_id]);
			if (empty($corpInfo)) {
				throw new InvalidParameterException('企业微信数据错误！');
			}

			$workChat = WorkChat::findOne(['corp_id' => $corpInfo->id, 'chat_id' => $chatId]);
			if (empty($workChat)) {
				throw new InvalidParameterException('群数据错误！');
			}
			$chat_id = $workChat->id;

			$unixType = '%Y-%m-%d';
			$select   = new Expression('FROM_UNIXTIME(event_time, \'' . $unixType . '\') time');
			$sql1     = 'select id,"" as attachment_id,"" as search,"" as open_time,"" as leave_time,"" as openid,user_id,external_id,"11" as type,event_time,event_id,remark from {{%external_time_line}} where related_id = ' . $chat_id . ' and event="chat_track"';
			$sql2     = 'select id,attachment_id,search,open_time,leave_time,openid,user_id,external_id,type,UNIX_TIMESTAMP(create_time) AS event_time,"" as event_id,"" as remark from {{%attachment_statistic}} where chat_id = ' . $chat_id;
			//总数
			$sqlCount  = 'select ' . $select . ' from ((' . $sql1 . ') UNION ALL (' . $sql2 . ' )) con group by time ';
			$LineCount = ExternalTimeLine::findBySql($sqlCount)->asArray()->all();
			$count     = count(SUtils::array_unset_tt($LineCount, 'time'));

			$offset = ($page - 1) * $pageSize;
			$sql    = 'select ' . $select . ' from ((' . $sql1 . ') UNION ALL (' . $sql2 . ' )) con group by time order by time desc limit ' . $offset . ',' . $pageSize;;
			$LineList = ExternalTimeLine::findBySql($sql)->asArray()->all();
			$info     = [];
			if (!empty($LineList)) {
				foreach ($LineList as $key => $line) {
					$pro      = [];
					$sTime    = strtotime($line['time']);
					$eTime    = strtotime($line['time'] . ' 23:59:59');
					$sql      = 'select * from ((' . $sql1 . ') UNION ALL (' . $sql2 . ') ) con where event_time>=' . $sTime . ' and event_time<=' . $eTime . ' order by event_time desc,id desc ';
					$LineListData = ExternalTimeLine::findBySql($sql)->asArray()->all();
					foreach ($LineListData as $k => $v) {
						$pro[$k]['time']    = date('H:i', $v['event_time']);
						$returnData         = WorkChat::returnData([$v], $workChat);
						$pro[$k]['content'] = $returnData[0]['content'];
					}
					$info[$key]['date'] = $line['time'];
					$info[$key]['data'] = $pro;
				}
			}
			$info = SUtils::array_unset_tt($info, 'date');
			$info = array_values($info);

			return ['count' => $count, 'list' => $info];
		}
	}