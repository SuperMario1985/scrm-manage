<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 15:32
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\Fans;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkCorp;
	use app\models\WorkCorpAuth;
	use app\models\WorkDepartment;
	use app\models\WorkDismissUserDetail;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkTag;
	use app\models\WorkTagContact;
	use app\models\WorkTagFollowUser;
	use app\models\WorkTagUser;
	use app\models\WorkUser;
	use app\models\WorkUserStatistic;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncTransferResultJob;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\src\dataStructure\EContactGetTransferResult;
	use yii\web\MethodNotAllowedHttpException;
	use yii\db\Expression;

	class WorkUserController extends WorkBaseController
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

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           获取成员列表
		 * @description     获取成员列表
		 * @method   POST
		 * @url  http://{host_name}/api/work-user/get-user-list
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param party_id 可选 int 部门id。获取指定部门及其下的子部门。如果不填，默认获取全量组织架构
		 * @param sort 可选 bool 排序true升序false降序
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param id 可选 int 成员id
		 * @param department_id 可选 array 部门id
		 * @param type 可选 int 1发起申请数2新增客户数3聊天数4发送消息数
		 * @param status 可选 int 状态：0、全部1、已激活2、已禁用4、未激活5、退出企业
		 * @param is_all  可选 int 0、不传1、传所有企业成员名称
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":{"count":"5","users":[{"id":1,"corp_id":1,"userid":"dove_chen","name":"dove_chen","department":"1","order":"","position":null,"mobile":null,"gender":"1","email":null,"is_leader_in_dept":null,"avatar":"https://rescdn.qqmail.com/node/wwmng/wwmng/style/images/independent/DefaultAvatar$73ba92b5.png","thumb_avatar":"https://rescdn.qqmail.com/node/wwmng/wwmng/style/images/independent/DefaultAvatar$73ba92b5.png","telephone":null,"enable":null,"alias":"","address":null,"extattr":null,"status":1,"qr_code":null,"is_del":0,"department_info":[{"id":1,"corp_id":1,"department_id":1,"name":"1","name_en":null,"parentid":null,"order":100000000,"is_del":0}]},{"loop":"……"}],"keys":[1,"loop",5]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 满足条件的成员数
		 * @return_param    users array 成员数据
		 * @return_param    keys array 所有的成员ID
		 * @return_param    id int ID
		 * @return_param    corp_id int 授权的企业ID
		 * @return_param    userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    gender int 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    enable string 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    address string 地址
		 * @return_param    extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    is_del int 0：未删除；1：已删除
		 * @return_param    department_info array 部门信息
		 * @return_param    id int ID
		 * @return_param    corp_id int 授权的企业ID
		 * @return_param    department_id int 创建的部门id
		 * @return_param    name string 部门名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示部门名称
		 * @return_param    name_en string 英文名称
		 * @return_param    parentid int 父亲部门id。根部门为1
		 * @return_param    order int 在父部门中的次序值。order值大的排序靠前。值范围是[0, 2^32)
		 * @return_param    is_del int 0：未删除；1：已删除
		 * @return_param    apply_num int 发起申请数
		 * @return_param    new_customer int 新增客户数
		 * @return_param    message_num int 发送消息数
		 * @return_param    chat_num int 聊天数
		 * @return_param    replyed_per string 已回复聊天占比
		 * @return_param    first_reply_time string 平均首次回复时长
		 * @return_param    delete_customer_num int 拉黑客户数
		 * @return_param    today_apply_num int 今日发起申请数
		 * @return_param    seven_apply_num int 7日累计发起申请数
		 * @return_param    today_new_customer_num int 今日新增客户数
		 * @return_param    seven_new_customer_num int 7日累计新增客户数
		 * @return_param    today_chat_num int 今日聊天数
		 * @return_param    seven_chat_num int 7日累计聊天数
		 * @return_param    today_message_num int 今日发送消息数
		 * @return_param    seven_message_num int 7日累计发送消息数
		 * @return_param    last_syn_time string 上次同步时间
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/1/7 19:19
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetUserList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$page          = \Yii::$app->request->post('page') ?: 1;
				$sort          = \Yii::$app->request->post('sort') ?: false;
				$pageSize      = \Yii::$app->request->post('page_size') ?: 15;
				$id            = \Yii::$app->request->post('id');
				$department_id = \Yii::$app->request->post('department_id');
				$type          = \Yii::$app->request->post('type') ?: 0;
				$is_external   = \Yii::$app->request->post('is_external') ?: 0;
				$status        = \Yii::$app->request->post('status') ?: 0;
				$is_all        = \Yii::$app->request->post('is_all') ?: 0;
				if (!empty($id)) {
					$Temp = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($id);
					$id   = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
				}
				if ($sort) {
					$sort_new = SORT_ASC;
				}else{
					$sort_new = SORT_DESC;
				}

				$offset  = ($page - 1) * $pageSize;
				$groupBy = '';

				$userData = WorkUser::find()->alias('wu');
				if (!empty($department_id)) {
					foreach ($department_id as $key => $depart_id) {
						if (!empty($depart_id)) {
							$userData = $userData->orWhere("find_in_set ($depart_id,wu.department)");
						}
					}
				}
				if ($is_external != -1) {
					$userData = $userData->andWhere(['wu.is_external' => $is_external]);
				}
				if (!empty($status)) {
					if ($status == 3) {
						$userData = $userData->andWhere(['wu.is_del' => 1]);
					} else {
						$userData = $userData->andWhere(['wu.status' => $status, 'wu.is_del' => 0]);
					}
				}
				$userData = $userData->andWhere(['wu.corp_id' => $this->corp->id]);
				if (!empty($id)) {
					$userData = $userData->andWhere(['wu.id' => $id]);
				}
				if (!empty($groupBy)) {
					$userData->groupBy($groupBy);
				}
				$count = $userData->count();
				if ($type == 0) {
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.id' => SORT_DESC,'wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.id' => SORT_DESC,'wu.status'=>SORT_ASC])->all();
				} elseif ($type == 1) {
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.new_apply_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.new_apply_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
				} elseif ($type == 2) {
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.new_contact_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.new_contact_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
				} elseif ($type == 3) {
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.chat_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.chat_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
				} elseif ($type == 4) {
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.message_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.message_cnt' => $sort_new,'wu.status'=>SORT_ASC])->all();
				}elseif ($type==-1){
					$userIdInfo = $userData->select('wu.id,wu.name')->orderBy(['wu.status'=>SORT_ASC])->all();
					$userData   = $userData->select('wu.*')->limit($pageSize)->offset($offset)->orderBy(['wu.status'=>SORT_ASC])->all();
				}

				$userInfo = [];
				if (!empty($userData)) {
					foreach ($userData as $user) {
						$userInfoData = $user->dumpData(true);
						array_push($userInfo, $userInfoData);

					}
				}

				//最后一次同步时间
				$sync_user_time = $this->corp->sync_user_time;
				if (!empty($sync_user_time)) {
					$sync_user_time = date('Y-m-d H:i:s', $sync_user_time);
				}

				$userIds        = [];
				$userIndexArray = [];
				$userTagCount   = [];
				if (!empty($userIdInfo)) {
					foreach ($userIdInfo as $key => $user) {
						array_push($userIds, $user->id);
						$userIndexArray[$user->id] = $key;
						array_push($userTagCount, 0);
					}
					$tag_count = WorkTagUser::find()->select('`user_id`,count(`user_id`) as cnt')->where(['user_id' => $userIds])->groupBy('user_id')->asArray()->all();
					$tagCount  = array_column($tag_count, 'cnt', 'user_id');
					if (!empty($tagCount)) {
						foreach ($tagCount as $userId => $cnt) {
							$userTagCount[$userIndexArray[$userId]] = $cnt;
						}
					}
				}

				$userName = [];
				if ($is_all == 1) {
					$workUser = WorkUser::find()->where(['corp_id' => $this->corp->id])->select('id,name')->all();
					if (!empty($workUser)) {
						/**
						 * @var k        $k
						 * @var WorkUser $v
						 */
						foreach ($workUser as $k => $v) {
							$userName[$k]['id']   = $v->id;
							$userName[$k]['name'] = $v->name;
						}
					}
				}

				$top = [
					'today_apply_num'        => 0,
					'seven_apply_num'        => 0,
					'today_new_customer_num' => 0,
					'seven_new_customer_num' => 0,
					'today_chat_num'         => 0,
					'seven_chat_num'         => 0,
					'today_message_num'      => 0,
					'seven_message_num'      => 0,
					'last_syn_time'          => $sync_user_time,
				];

				return [
					'count'     => $count,
					'users'     => $userInfo,
					'keys'      => $userIds,
					'top'       => $top,
					'tag_count' => $userTagCount,
					'user_name' => $userName,
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           给通讯录或客户添加和移除标签
		 * @description     给通讯录或客户添加和移除标签
		 * @method   post
		 * @url  http://{host_name}/api/work-user/give-user-tags
		 *
		 * @param tag_ids 必选 array 标签id
		 * @param user_ids 必选 array 员工列表id
		 * @param type 必选 int 0打标签1移除标签
		 * @param s_type 必选 int 1通讯录2客户管理
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: s. Date: 2020/1/9 13:44
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGiveUserTags ()
		{
			if (\Yii::$app->request->isPost) {
				$tag_ids  = \Yii::$app->request->post('tag_ids');
				$user_ids = \Yii::$app->request->post('user_ids');
				$type     = \Yii::$app->request->post('type') ?: 0;//0 打标签 1 移除标签
				$s_type   = \Yii::$app->request->post('s_type');//1 通讯录 2 客户管理
				$bitchAll = \Yii::$app->request->post('bitch_all', 0);//来源

				if ((empty($tag_ids) && empty($bitchAll)) || empty($user_ids) || empty($s_type)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if(!is_array($user_ids)){
					$user_ids = [$user_ids];
				}
				if (!is_array($tag_ids) || !is_array($user_ids)) {
					throw new InvalidParameterException('参数格式不正确！');
				}
				try {
//					if ($s_type == 2) {
//						$followUser = WorkExternalContactFollowUser::find()->where(['id' => $user_ids])->select('external_userid')->asArray()->all();
//						$user_ids   = array_column($followUser, 'external_userid');
//					}
					//总共操作人数
					$total = count($user_ids);
					//成功人数
					$success = 0;
					//失败人数
					$fail = 0;
					if($bitchAll){
						$notTags = WorkTagFollowUser::find()->where(["and", ["status" => 1], ["in", "follow_user_id", $user_ids]])->asArray()->all();
						if (!empty($notTags) && empty($tag_ids)) {
							$notTagsAll = array_column($notTags, "tag_id");
							WorkTag::removeUserTag($s_type, $user_ids, $notTagsAll);
						}
						if (!empty($notTags) && !empty($tag_ids)) {
							$notTagsAll       = array_column($notTags, "tag_id");
							$notTagsAllRemove = array_diff($notTagsAll, $tag_ids);
							if(!empty($notTagsAllRemove)){
								$notTagsAllRemove = array_values($notTagsAllRemove);
								WorkTag::removeUserTag($s_type, $user_ids, $notTagsAllRemove);
							}
							WorkTag::addUserTag($s_type, $user_ids, $tag_ids);
						}
						if(empty($notTags) && !empty($tag_ids)){
							WorkTag::addUserTag($s_type, $user_ids, $tag_ids);
						}
						return [
							'error'     => 0,
							'error_msg' => "提交成功",
						];
					}else{
						if ($type == 0) {
							$active   = '打';
							$fail_num = WorkTag::addUserTag($s_type, $user_ids, $tag_ids);
						} else {
							$active   = '移除';
							$fail_num = WorkTag::removeUserTag($s_type, $user_ids, $tag_ids);
						}
					}

					$fail    = $fail_num;
					$success = $total - $fail_num;


				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => "本次共给" . $total . "人" . $active . "标签，成功" . $success . "人，失败" . $fail . "人。",
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           获取所选对象下的标签
		 * @description     获取所选对象下的标签
		 * @method   post
		 * @url  http://{host_name}/api/work-user/get-user-tags
		 *
		 * @param user_ids 必选 array 员工id
		 * @param type 必选 int 1通讯录2客户
		 * @param give 必选 int 0打标签1移除标签
		 *
		 * @return   {"error":0,"data":[{"id":"1","tagname":"aa"},{"id":"2","tagname":"cc"}]}
		 *
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: s. Date: 2020/1/9 16:57
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetUserTags ()
		{
			if (\Yii::$app->request->isPost) {
				$user_ids = \Yii::$app->request->post('user_ids');
				$type     = \Yii::$app->request->post('type') ?: 1;//1通讯录 2客户
				$give     = \Yii::$app->request->post('give') ?: 0;
				if (empty($user_ids) || empty($type)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$count    = count($user_ids);
				$user_ids = implode(',', $user_ids);
				$result   = [];
				if ($type == 1) {
					$tag_user = WorkTagUser::find()->alias('wt');
					$tag_user = $tag_user->leftJoin('{{%work_tag}} t', '`t`.`id` = `wt`.`tag_id`');
					$tag_user = $tag_user->select('count(*) as num,t.id,t.tagname')->where("wt.user_id in (" . $user_ids . ")")->andWhere(['t.is_del'=>0])->groupBy('t.id')->asArray()->all();
					if(!empty($give)){
						$result = $tag_user;
					}else{
						if (!empty($tag_user)) {
							foreach ($tag_user as $key => $v) {
								if ($v['num'] == $count) {
									$result[$key]['id']      = $v['id'];
									$result[$key]['tagname'] = $v['tagname'];
								}
							}
						}
					}
				} elseif ($type == 2) {
//					$user_ids    = explode(',', $user_ids);
//					$followUser  = WorkExternalContactFollowUser::find()->where(['id' => $user_ids])->select('external_userid')->asArray()->all();
//					$user_ids    = array_column($followUser, 'external_userid');
					//$user_ids    = implode(',', $user_ids);
					$tag_contact = WorkTagFollowUser::find()->alias('wt');
					$tag_contact = $tag_contact->leftJoin('{{%work_tag}} t', '`t`.`id` = `wt`.`tag_id`');
					$tag_contact = $tag_contact->select('count(*) as num,t.id,t.tagname')->where("wt.follow_user_id in (" . $user_ids . ")")->andWhere(['t.is_del' => 0, 'wt.status' => 1])->groupBy('t.id');
					$tag_contact = $tag_contact->asArray()->all();
					if (!empty($give)) {
						$result = $tag_contact;
					} else {
						if (!empty($tag_contact)) {
							foreach ($tag_contact as $key => $v) {
								if ($v['num'] == $count) {
									$result[$key]['id']      = $v['id'];
									$result[$key]['tagname'] = $v['tagname'];
								}
							}
						}
					}
				} else {
					throw new InvalidParameterException('参数不合法！');
				}
				$result = array_values($result);

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           根据企业成员统计表数据批量更新成员数据
		 * @description     根据企业成员统计表数据批量更新成员数据
		 * @method   post
		 * @url  http://{host_name}/api/work-user/update-work-user-data
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/13 17:54
		 * @number          0
		 *
		 */
		public function actionUpdateWorkUserData ()
		{
			$workCorp = WorkCorp::find()->select('id,corpid')->where('corpid != \'\' AND corp_type != \'\'')->all();
			$select   = new Expression('userid,sum(new_apply_cnt) apply_cnt,sum(new_contact_cnt) contact_cnt,sum(negative_feedback_cnt) feedback_cnt,sum(chat_cnt) chat_cnt,sum(message_cnt) message_cnt,sum(avg_reply_time) reply_time,sum(reply_percentage) reply_percentage');
			foreach ($workCorp as $corp) {
				$workUser = WorkUser::find()->andWhere(['corp_id' => $corp->id])->all();
				foreach ($workUser as $user) {
					$count1        = WorkUserStatistic::find()->andWhere(['userid' => $user->userid, 'corp_id' => $corp->id])->andWhere(['<>', 'reply_percentage', ''])->count();
					$count2        = WorkUserStatistic::find()->andWhere(['userid' => $user->userid, 'corp_id' => $corp->id])->andWhere(['<>', 'avg_reply_time', ''])->count();
					$workStatistic = WorkUserStatistic::find()->andWhere(['userid' => $user->userid, 'corp_id' => $corp->id])->select($select)->groupBy('userid')->asArray()->one();
					if (!empty($workStatistic)) {
						$reply_percentage_per = '';
						if (!empty($workStatistic['reply_percentage'])) {
							$reply_percentage_per = round($workStatistic['reply_percentage'] / ($count1 * 100), 2);
						}
						$reply_time_per = '';
						if (!empty($workStatistic['reply_time'])) {
							$reply_time_per = round($workStatistic['reply_time'] / $count2, 2);
						}
						$user->new_apply_cnt         = $workStatistic['apply_cnt'];
						$user->new_contact_cnt       = $workStatistic['contact_cnt'];
						$user->negative_feedback_cnt = $workStatistic['feedback_cnt'];
						$user->chat_cnt              = $workStatistic['chat_cnt'];
						$user->message_cnt           = $workStatistic['message_cnt'];
						$user->reply_percentage      = $reply_percentage_per;
						$user->avg_reply_time        = $reply_time_per;
						$user->save();
					}

				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           离职成员列表
		 * @description     离职成员列表
		 * @method   post
		 * @url  http://{host_name}/api/work-user/work-dismiss-users
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param user_id 可选 array 成员ID
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 *
		 * @return          {"error":0,"data":{"count":"9","info":[{"key":2,"id":2,"name":"汪博文-销售","user_count":"0","chat_count":"0","time":"--"},{"key":95,"id":95,"name":"林凤-技术","user_count":"0","chat_count":"0","time":"--"},{"key":119,"id":119,"name":"王美丁-小猪科技公司","user_count":"0","chat_count":"0","time":"--"},{"key":120,"id":120,"name":"钱玉洁-小猪科技公司","user_count":"0","chat_count":"0","time":"--"},{"key":128,"id":128,"name":"徐溧成-小猪科技公司","user_count":"0","chat_count":"0","time":"--"},{"key":174,"id":174,"name":"卢敏-小猪科技公司","user_count":"0","chat_count":"0","time":"--"},{"key":177,"id":177,"name":"王盼-小猪科技公司/销售","user_count":"0","chat_count":"0","time":"--"},{"key":179,"id":179,"name":"汪博文-小猪科技公司","user_count":"0","chat_count":"0","time":"--"},{"key":181,"id":181,"name":"汪博文-小猪科技公司","user_count":"0","chat_count":"0","time":"--"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名字
		 * @return_param    user_count string 客户数
		 * @return_param    chat_count string 群聊数
		 * @return_param    time string 离职时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/19 15:32
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWorkDismissUsers ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$page       = \Yii::$app->request->post('page') ?: 1;
			$pageSize   = \Yii::$app->request->post('page_size') ?: 15;
			$user_id    = \Yii::$app->request->post('user_id');
			$start_time = \Yii::$app->request->post('start_time');
			$end_time   = \Yii::$app->request->post('end_time');
			$workUser   = WorkUser::find()->where(['corp_id' => $this->corp->id, 'is_del' => WorkUser::USER_IS_DEL]);

			if(!empty($user_id)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,1);
			}

			if (!empty($start_time) && !empty($end_time)) {
				$workUser = $workUser->andFilterWhere(['between', 'dimission_time', strtotime($start_time), strtotime($end_time)]);
			}
			if (!empty($user_id)) {
				$workUser = $workUser->andWhere(["in",'id', $user_id]);
			}
			$count    = $workUser->count();
			$offset   = ($page - 1) * $pageSize;
			$workUser = $workUser->limit($pageSize)->offset($offset)->orderBy(['dimission_time' => SORT_DESC])->all();
			$info     = [];
			if (!empty($workUser)) {
				/**
				 * @var key      $key
				 * @var WorkUser $user
				 */
				foreach ($workUser as $key => $user) {
					$info[$key]['key']             = $user->id;
					$info[$key]['avatar']          = $user->avatar;
					$info[$key]['gender']          = $user->gender;
					$info[$key]['id']              = $user->id;
					$departName                    = WorkDepartment::getDepartNameByUserId($user->department, $user->corp_id);
					$info[$key]['name']            = $user->name . '-' . $departName;
					$userCount                     = WorkDismissUserDetail::find()->where(['user_id' => $user->id])->andWhere(['!=', 'external_userid', ''])->count();
					$willUserCount                 = WorkDismissUserDetail::find()->where(['user_id' => $user->id, 'status' => 0])->andWhere(['!=', 'external_userid', ''])->count();
					$chatCount                     = WorkDismissUserDetail::find()->where(['user_id' => $user->id])->andWhere(['!=', 'chat_id', ''])->count();
					$willChatCount                 = WorkDismissUserDetail::find()->where(['user_id' => $user->id, 'status' => 0])->andWhere(['!=', 'chat_id', ''])->count();
					$info[$key]['will_user_count'] = $willUserCount;
					$info[$key]['will_chat_count'] = $willChatCount;
					$info[$key]['user_count']      = $userCount;
					$info[$key]['chat_count']      = $chatCount;
					$info[$key]['corp_id']         = $this->corp->corpid;
					$info[$key]['time']            = !empty($user->dimission_time) ? date('Y-m-d H:i', $user->dimission_time) : '--';
				}
			}

			$cacheKey     = 'sync_transfer_result' . $this->corp->id;
			$currentYmd   = DateUtil::getCurrentYMD();
			//\Yii::$app->cache->delete($cacheKey);
			$refreshCache = \Yii::$app->cache->get($cacheKey);
			if (empty($refreshCache) || empty($refreshCache[$currentYmd])) {
				$refreshCache = [
					$currentYmd => [
						'last_refresh_time' => 0,
					]
				];
			}
			\Yii::error($refreshCache,'$refreshCache');
			\Yii::error(time(),'time');
			if (($refreshCache[$currentYmd]['last_refresh_time'] + 60*60) > time()) {
				\Yii::error('不足1小时','$refreshCache');
			}else{
				$refreshCache[$currentYmd]['last_refresh_time'] = time();
				\Yii::$app->cache->set($cacheKey, $refreshCache);
			}

			\Yii::$app->queue->push(new SyncTransferResultJob([
				'corpId'=>$this->corp->id
			]));

			return [
				'count' => $count,
				'info'  => $info,
			];
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           客户明细
		 * @description     客户明细
		 * @method   post
		 * @url  http://{host_name}/api/work-user/work-dismiss-user-detail
		 *
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param id 可选 int 离职列表人员id
		 * @param name 可选 string 客户昵称
		 * @param status 可选 int 分配状态-1全部0未分配1已分配
		 * @param user_id 可选 array 分配人员id
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    corp_name string 公司名称
		 * @return_param    gender string 性别
		 * @return_param    wx_name string 公众号
		 * @return_param    chat_name array 群名
		 * @return_param    status string 状态
		 * @return_param    user_name string 员工名称
		 * @return_param    time string 分配时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/23 10:12
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionWorkDismissUserDetail ()
		{
			if (\Yii::$app->request->isPost) {
//				$workApi                 = WorkUtils::getWorkApi(1, WorkUtils::EXTERNAL_API);
				//$data['external_userid'] = 'wmiWVTDwAAaUyro_YczH-YL_xMMO_X7Q';
//				$data['external_userid'] = 'wmiWVTDwAAaUyro_YczH-YL_xMMO_X7Q';
//				$data['handover_userid'] = 'relieved';
//				$data['takeover_userid'] = 'jiangyuexia';
////
////
//				$res = EContactGetTransferResult::parseFromArray($data);
//				$result = $workApi->EContactGetTransferResult($res);
//				\Yii::error($result,'$result');
//				$assignList          = $workApi->ECGetUnAssignedList();
//				\Yii::error($assignList,'$assignList');

				$page       = \Yii::$app->request->post('page') ?: 1;
				$pageSize   = \Yii::$app->request->post('page_size') ?: 15;
				$id         = \Yii::$app->request->post('id');
				$name       = \Yii::$app->request->post('name');
				$status     = \Yii::$app->request->post('status', -1);
				$user_id    = \Yii::$app->request->post('user_id');
				$start_time = \Yii::$app->request->post('start_time');
				$end_time   = \Yii::$app->request->post('end_time');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$dismissDetail = WorkDismissUserDetail::find()->alias('d')->leftJoin('{{%work_external_contact}} c', '`c`.`id` = `d`.`external_userid`')->where(['d.user_id' => $id])->andWhere(['!=','`d`.`external_userid`','']);
				if (!empty($name) || $name==0) {
					$dismissDetail = $dismissDetail->andWhere('c.name_convert like \'%' . $name . '%\'');
				}
				if ($status != -1) {
					$dismissDetail = $dismissDetail->andWhere(['d.status' => $status]);
				}
				if (!empty($user_id)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
					$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_id = empty($user_id) ? [0] : $user_id;
					$dismissDetail = $dismissDetail->andWhere(['d.allocate_user_id' => $user_id]);
				}
				if (!empty($start_time) && !empty($end_time)) {
					$dismissDetail = $dismissDetail->andFilterWhere(['between', 'd.allocate_time', strtotime($start_time), strtotime($end_time)]);
				}
				$count         = $dismissDetail->count();
				$offset        = ($page - 1) * $pageSize;
				$info          = [];
				$dismissDetail = $dismissDetail->select('c.id cid,c.name,c.corp_name,c.avatar,c.gender,d.id did,d.status,d.allocate_user_id,d.allocate_time')->limit($pageSize)->offset($offset)->orderBy(['d.create_time' => SORT_DESC])->asArray()->all();
				if (!empty($dismissDetail)) {
					foreach ($dismissDetail as $key => $val) {
						if ($val['gender'] == 0) {
							$gender = '未知';
						} elseif ($val['gender'] == 1) {
							$gender = '男性';
						} elseif ($val['gender'] == 2) {
							$gender = '女性';
						}
						$fans   = Fans::findOne(['external_userid' => $val['cid'], 'subscribe' => Fans::USER_SUBSCRIBE]);
						$wxName = '';
						if (!empty($fans)) {
							$wxName = $fans->author->wxAuthorizeInfo->nick_name;
						}
						$status = '未分配';
						if ($val['status'] == 1) {
							$status = '已分配';
						} elseif ($val['status'] == 2) {
							$status = '客户拒绝';
						} elseif ($val['status'] == 3) {
							$status = '接替成员客户达到上限';
						} elseif ($val['status'] == 4) {
							$status = '分配中';
						} elseif ($val['status'] == 5) {
							$status = '未知';
						}
						$name = '';
						if (!empty($val['allocate_user_id'])) {
							$workUser = WorkUser::findOne($val['allocate_user_id']);
							if (!empty($workUser)) {
								$name = $workUser->name;
							}
						}
						$time = '--';
						if (!empty($val['allocate_time'])) {
							$time = date('Y-m-d H:i', $val['allocate_time']);
						}
						$chatName                = WorkChatInfo::getChatList(2, $val['cid']);
						$info[$key]['key']       = $val['did'];
						$info[$key]['name']      = !empty($val['name']) ? rawurldecode($val['name']) : '';
						$info[$key]['avatar']    = $val['avatar'];
						$info[$key]['corp_name'] = $val['corp_name'];
						$info[$key]['gender']    = $gender;
						$info[$key]['wx_name']   = $wxName;
						$info[$key]['chat_name'] = $chatName;
						$info[$key]['status']    = $status;
						$info[$key]['user_name'] = $name;
						$info[$key]['time']      = $time;


					}
				}

				return [
					'count' => $count,
					'info'  => $info,
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           客户群明细
		 * @description     客户群明细
		 * @method   post
		 * @url  http://{host_name}/api/work-user/work-dismiss-chat-detail
		 *
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param id 可选 int 离职列表人员id
		 * @param name 可选 string 群名称
		 * @param status 可选 int 分配状态-1全部0未分配1已分配
		 * @param user_id 可选 array 分配人员id
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 * @return_param    corp_name string 公司名称
		 * @return_param    gender string 性别
		 * @return_param    wx_name string 公众号
		 * @return_param    chat_name array 群名
		 * @return_param    status string 状态
		 * @return_param    user_name string 员工名称
		 * @return_param    time string 分配时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/19 17:11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionWorkDismissChatDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$page       = \Yii::$app->request->post('page') ?: 1;
				$pageSize   = \Yii::$app->request->post('page_size') ?: 15;
				$id         = \Yii::$app->request->post('id');
				$name       = \Yii::$app->request->post('name');
				$status     = \Yii::$app->request->post('status',-1);
				$user_id    = \Yii::$app->request->post('user_id');
				$start_time = \Yii::$app->request->post('start_time');
				$end_time   = \Yii::$app->request->post('end_time');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$dismissDetail = WorkDismissUserDetail::find()->alias('d')->leftJoin('{{%work_chat}} c', '`c`.`id` = `d`.`chat_id`')->where(['d.user_id' => $id])->andWhere(['!=','`d`.`chat_id`','']);
				if (!empty($name) || $name=='0') {
					$dismissDetail = $dismissDetail->andWhere('c.name like \'%' . $name . '%\'');
				}
				if ($status != -1) {
					$dismissDetail = $dismissDetail->andWhere(['d.status' => $status]);
				}
				if (!empty($user_id)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
					$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_id = empty($user_id) ? [0] : $user_id;
					$dismissDetail = $dismissDetail->andWhere(['d.allocate_user_id' => $user_id]);
				}
				if (!empty($start_time) && !empty($end_time)) {
					$dismissDetail = $dismissDetail->andFilterWhere(['between', 'd.allocate_time', strtotime($start_time), strtotime($end_time)]);
				}
				$count         = $dismissDetail->count();
				$offset        = ($page - 1) * $pageSize;
				$info          = [];
				$dismissDetail = $dismissDetail->select('c.id cid,c.name,d.id did,d.chat_id,d.status,d.allocate_user_id,d.allocate_time')->limit($pageSize)->offset($offset)->orderBy(['d.create_time' => SORT_DESC]);
				$dismissDetail = $dismissDetail->asArray()->all();
				if (!empty($dismissDetail)) {
					foreach ($dismissDetail as $key => $val) {
						$chatName = WorkChat::getChatName($val['cid']);
						$chatCount    = WorkChatInfo::find()->andWhere(['chat_id' => $val['chat_id'], 'status' => 1])->count();
						$status   = '未分配';
						if (!empty($val['status'])) {
							$status = '已分配';
						}
						$name = '';
						if (!empty($val['allocate_user_id'])) {
							$workUser = WorkUser::findOne($val['allocate_user_id']);
							if (!empty($workUser)) {
								$name = $workUser->name;
							}
						}
						$time = '--';
						if (!empty($val['allocate_time'])) {
							$time = date('Y-m-d H:i', $val['allocate_time']);
						}
						$info[$key]['key']       = $val['did'];
						$info[$key]['chat_name'] = $chatName;
						$info[$key]['count']     = $chatCount;
						$info[$key]['status']    = $status;
						$info[$key]['user_name'] = $name;
						$info[$key]['time']      = $time;
						$info[$key]['avatarData'] = WorkChat::getChatAvatar($val['chat_id']);
					}
				}

				return [
					'count' => $count,
					'info'  => $info,
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/work-user/
		 * @title           获取所有成员
		 * @description     获取所有成员
		 * @method   post
		 * @url  http://{host_name}/api/work-user/get-all-user
		 *
		 * @param name 可选 string 名字
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页码
		 * @param corp_id 可选 string 企业微信ID
		 * @param isMasterAccount 可选 string 账户类型：1账户2子账户
		 * @param sub_id 可选 string 子账户id
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/7 11:22
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAllUser ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;
				$subId           = \Yii::$app->request->post('sub_id');
				$page            = \Yii::$app->request->post('page') ?: 1;
				$pageSize        = \Yii::$app->request->post('pageSize') ?: 15;
				$name            = \Yii::$app->request->post('name');
				$is_all          = \Yii::$app->request->post('is_all') ?: 0;
				$user_id         = \Yii::$app->request->post('user_id');

				$result   = [];
				$offset   = ($page - 1) * $pageSize;
				$workUser = WorkUser::find()->where(['corp_id' => $this->corp->id, 'is_del' => WorkUser::USER_NO_DEL, 'is_external' => 1]);
				if (!empty($user_id)) {
					$workUserCurrent = WorkUser::find()->where(['id' => $user_id]);
					$workUser = $workUser->andWhere(['!=', 'id', $user_id]);
				}
				if (!empty($name) || $name == '0') {
					$workUser = $workUser->andWhere(['like', 'name', $name]);
					if (!empty($workUserCurrent)) {
						$workUserCurrent = $workUserCurrent->andWhere(['like', 'name', $name]);
					}
				}
				//根据帐号的数据可见范围
				if ($isMasterAccount != 1 && !empty($subId)) {
					$userIds = AuthoritySubUserDetail::getDepartmentUserLists($subId, $this->corp->id);
					if ($userIds === false) {
						return [
							'count' => 0,
							'info'  => []
						];
					}
					if (is_array($userIds)) {
						$workUser = $workUser->andWhere(['id' => $userIds]);
					}
				}

				$count = $workUser->count();
				if (empty($is_all)) {
					$workUser = $workUser->limit($pageSize)->offset($offset);
				}
				$workUser = $workUser->all();
				if (!empty($workUser)) {
					/** @var WorkUser $user */
					foreach ($workUser as $user) {
						array_push($result, $user->dumpData(false, true, false));
					}
				}
				if(!empty($workUserCurrent) && $page==1){
					$workUserCurrent = $workUserCurrent->one();
					if(!empty($workUserCurrent)){
						$data = $workUserCurrent->dumpData(false,true);
						if($data["is_external"] == "无"){
							$data["name"] .= "(无权限)";
						}
						if($data["is_del"] != 0){
							$data["name"] .= "(已删除)";
						}
						$result = array_merge([$data],$result);
					}
				}

//				$res    = [];
//				$offset1 = ($page - 1) * $pageSize;//0 15 30 45
//				$c = $page * $pageSize;
//				$i = 0;
//				while ($offset1<$c){
//					$res[$i]['id']   = $offset1;
//					$res[$i]['name'] = $offset1 . '李云莉';
//					$i++;
//					$offset1++;
//				}

				return [
					'count' => $count,
					'info'  => $result
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		public function actionUpdateUserStatistic ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			$workUser = WorkUser::find()->where(['<>', 'corp_id', 1])->andWhere(['<>', 'corp_id', 2])->select('id,userid,corp_id')->all();
			if (!empty($workUser)) {
				foreach ($workUser as $user) {
					$where      = [
						'userid'  => $user->userid,
						'corp_id' => $user->corp_id,
					];
					$replyCount = WorkUserStatistic::find()->andWhere($where)->andWhere(['<>', 'reply_percentage', ''])->count();
					if (!empty($replyCount)) {
						$expression             = new Expression('sum(reply_percentage) sum');
						$work_static            = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
						$sum                    = $work_static['sum'];
						$count                  = round($sum / ($replyCount * 100), 2);
						$user->reply_percentage = strval($count);
					}
					$avgCount = WorkUserStatistic::find()->andWhere($where)->andWhere(['<>', 'avg_reply_time', ''])->count();
					if (!empty($avgCount)) {
						$expression           = new Expression('sum(avg_reply_time) sum');
						$work_static          = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
						$sum                  = $work_static['sum'];
						$count                = round($sum / $avgCount, 2);
						$user->avg_reply_time = strval($count);
					}
					$expression    = new Expression('sum(new_apply_cnt) sum');
					$new_apply_cnt = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
					if (!empty($new_apply_cnt) && isset($new_apply_cnt['sum'])) {
						$user->new_apply_cnt = $new_apply_cnt['sum'];
					}
					$expression      = new Expression('sum(new_contact_cnt) sum');
					$new_contact_cnt = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
					if (!empty($new_contact_cnt) && isset($new_contact_cnt['sum'])) {
						$user->new_contact_cnt = $new_contact_cnt['sum'];
					}
					$expression            = new Expression('sum(negative_feedback_cnt) sum');
					$negative_feedback_cnt = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
					if (!empty($negative_feedback_cnt) && isset($negative_feedback_cnt['sum'])) {
						$user->negative_feedback_cnt = $negative_feedback_cnt['sum'];
					}
					$expression = new Expression('sum(chat_cnt) sum');
					$chat_cnt   = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
					if (!empty($chat_cnt) && isset($chat_cnt['sum'])) {
						$user->chat_cnt = $chat_cnt['sum'];
					}
					$expression  = new Expression('sum(message_cnt) sum');
					$message_cnt = WorkUserStatistic::find()->andWhere($where)->select($expression)->groupBy('userid')->asArray()->one();
					if (!empty($message_cnt) && isset($message_cnt['sum'])) {
						$user->message_cnt = $message_cnt['sum'];
					}
					if (!$user->save()) {
						\Yii::error(SUtils::modelError($user), '$user');
					}
				}
			}
		}

		public function actionUpdateData ()
		{
			WorkUser::getOldData();
		}

	}