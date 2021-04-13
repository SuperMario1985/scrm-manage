<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 15:36
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\AuthoritySubUserDetail;
	use app\models\LimitWord;
	use app\models\LimitWordRemind;
	use app\models\SubUser;
	use app\models\PublicSeaReclaimSet;
	use app\models\WorkCorp;
	use app\models\WorkCorpAgent;
	use app\models\WorkCorpAuth;
	use app\models\WorkDepartment;
	use app\models\WorkFollowMsg;
	use app\models\WorkFollowUser;
	use app\models\WorkGroupSending;
	use app\models\WorkMsgAuditUser;
	use app\models\WorkPublicActivity;
	use app\models\WorkUser;
	use app\models\WorkUserCommissionRemind;
	use app\models\WorkUserDelFollowUser;
	use app\models\WorkUserTagRule;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkDepartmentListJob;
	use app\util\DateUtil;
	use app\util\WebsocketUtil;
	use dovechen\yii2\weWork\Work;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;

	class WorkPartyController extends WorkBaseController
	{
		/**
		 * @inheritDoc
		 *
		 * @return array
		 */
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'refresh-party-list' => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-party/
		 * @title           刷新部门列表
		 * @description     刷新部门列表
		 * @method   POST
		 * @url  http://{host_name}/api/work-party/refresh-party-list
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param party_id 可选 int 部门id。获取指定部门及其下的子部门。如果不填，默认获取全量组织架构
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/1/7 16:01
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 */
		public function actionRefreshPartyList ()
		{
			if (\Yii::$app->request->isPost) {
				ignore_user_abort();
				set_time_limit(0);

				$departId = \Yii::$app->request->post('party_id');

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($this->corp->corp_type != 'verified') {
					throw new InvalidParameterException('当前企业号未认证！');
				}
				$cacheKey     = 'refresh_work_department_' . $this->corp->id;
				$currentYmd   = DateUtil::getCurrentYMD();
				$refreshCache = \Yii::$app->cache->get($cacheKey);
				if (empty($refreshCache) || empty($refreshCache[$currentYmd])) {
					$refreshCache = [
						$currentYmd => [
							'refresh'           => 0,
							'last_refresh_time' => 0,
						]
					];
				}

				//  每日请求次数验证 最多三次
				if ($refreshCache[$currentYmd]['refresh'] > 2) {
					//throw new NotAllowException('今日请求已达上限！');
				}

				//  两次请求时间间隔验证 间隔两小时
				if (($refreshCache[$currentYmd]['last_refresh_time'] + 2 * 60 * 60) > time()) {
					//throw new NotAllowException('距离上次请求时间不足两小时！');
				}

				++$refreshCache[$currentYmd]['refresh'];
				$refreshCache[$currentYmd]['last_refresh_time'] = time();
				\Yii::$app->cache->set($cacheKey, $refreshCache);

				$jobId = \Yii::$app->work->push(new SyncWorkDepartmentListJob([
					'corp'     => $this->corp,
					'departId' => $departId,
				]));

				//保存最后一次同步时间
				$this->corp->sync_user_time = time();
				$this->corp->save();

				return ['error' => 0];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-party/
		 * @title           获取所有的部门
		 * @description     获取所有的部门
		 * @method   post
		 * @url  http://{host_name}/api/work-party/get-all-department
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param parentId 可选 int 父级部门id
		 * @param get_users 必选 int 1获取成员0不获取
		 * @param disabled 必选 int 0不要1要
		 * @param from_channel 必选 int 0不是1是
		 * @param welcome_id 可选 int 欢迎语id
		 * @param from_chat 可选 int 1群主
		 * @param is_audit 可选 int 来源：1、监控提醒调用，2、聊天打标签
		 * @param is_audit_edit 可选 int 是否是监控提醒修改
		 * @param is_audit_user_all 可选 int 是否是全部成员调用
		 * @param is_del 可选 int 默认0，1调已删除的员工
		 * @param is_from    可选 int 来源：1、客户来源回收
		 * @param is_from_edit 可选 int 是否修改：0否、1是
		 * @param from_id 可选 int 来源修改id
		 *
		 * @return          {"error":0,"data":[{"key":1,"title":"1","department_id":1,"children":[{"key":5,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":4,"title":"23","department_id":23,"children":[{"key":6,"title":"39","department_id":39,"children":[],"user_list":[]},{"key":7,"title":"40","department_id":40,"children":[],"user_list":[]}],"user_list":[]},{"key":2,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":3,"title":"3","department_id":3,"children":[],"user_list":[]}],"user_list":[]}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    key int key
		 * @return_param    department_id int 部门id
		 * @return_param    title string 部门名称
		 * @return_param    children array 子集
		 * @return_param    user_list array 部门下的成员
		 * @return_param    id int id
		 * @return_param    userid string 成员UserID
		 * @return_param    name string 成员名称
		 * @return_param    is_checked int 成员是否可选0可选1不可选
		 * @return_param    is_external int 是否查看具有外部联系人权限0不查看1查看
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/10 11:35
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAllDepartment ()
		{
			if (\Yii::$app->request->isPost) {
				$startTime = microtime(true);
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$parentId          = \Yii::$app->request->post('parentId') ?: 0;
				$disabled          = \Yii::$app->request->post('disabled') ?: 0;
				$welcome_id        = \Yii::$app->request->post('welcome_id') ?: 0;
				$from_channel      = \Yii::$app->request->post('from_channel') ?: 0;
				$get_users         = \Yii::$app->request->post('get_users');
				$is_del            = \Yii::$app->request->post('is_del', 0);
				$from_chat         = \Yii::$app->request->post('from_chat');
				$is_special        = \Yii::$app->request->post('is_special');
				$is_audit          = \Yii::$app->request->post('is_audit', 0);
				$is_audit_edit     = \Yii::$app->request->post('is_audit_edit', 0);
				$is_audit_user_all = \Yii::$app->request->post('is_audit_user_all', 0);
				$is_user_tag       = \Yii::$app->request->post('is_user_tag', 0);
				$isFrom            = \Yii::$app->request->post('is_from', 0);
				$isFromEdit        = \Yii::$app->request->post('is_from_edit', 0);
				$fromId            = \Yii::$app->request->post('from_id', 0);
				$is_external       = \Yii::$app->request->post('is_external', 0);
				$users             = WorkUser::findOne(['corp_id' => $this->corp['id']]);

				if (empty($users)) {
					throw new InvalidParameterException('请先同步企业微信通讯录！');
				}
				$welcome_user_ids      = [];
				$welcome_user_ids_edit = [];
				$audit_user_ids        = $audit_depart_ids = $audit_edit_ids = [];
				if ($disabled == 1) {
					//对欢迎语单独判断
					$welcome = WorkWelcome::find()->andWhere(['corp_id' => $this->corp['id']])->select('user_ids')->all();
					if (!empty($welcome)) {
						foreach ($welcome as $wel) {
							if (!empty($wel->user_ids)) {
								$wel_user_ids = json_decode($wel->user_ids, true);
								$user_ids     = array_column($wel_user_ids, 'id');
								foreach ($user_ids as $id) {
									array_push($welcome_user_ids, $id);
								}
							}
						}
					}
				}
				if (!empty($welcome_id)) {
					$work_wel = WorkWelcome::findOne($welcome_id);
					if (!empty($work_wel->user_ids)) {
						$wel_user_ids = json_decode($work_wel->user_ids, true);
						$user_ids     = array_column($wel_user_ids, 'id');
						foreach ($user_ids as $id) {
							array_push($welcome_user_ids_edit, $id);
						}
					}
				}
				if (!empty($is_audit)) {
					if (empty($this->corp->workMsgAudit)) {
						throw new InvalidParameterException('未配置会话存档功能！');
					}
					$userDepartData = WorkMsgAuditUser::getUserIdDepartId($this->corp->workMsgAudit->id, $is_audit_user_all);
					if (empty($userDepartData)) {
						return [];
					}
					$audit_user_ids   = $userDepartData['userIdData'];
					$audit_depart_ids = $userDepartData['departIdData'];
					if (!empty($is_audit_edit)) {
						if ($is_audit == 1) {
							$limitWordRemind = LimitWordRemind::find()->where(['corp_id' => $this->corp->id])->select('limit_user_id')->all();
							if (!empty($limitWordRemind)) {
								$audit_edit_ids = array_column($limitWordRemind, 'limit_user_id');
							}
						} elseif ($is_audit == 2) {
							$userTagRule = WorkUserTagRule::find()->where(['corp_id' => $this->corp->id, 'status' => [1, 2]])->select('user_id')->all();
							if (!empty($userTagRule)) {
								$audit_edit_ids = array_column($userTagRule, 'user_id');
							}
						}
					}
				}
				if (!empty($is_user_tag)) {
					$is_audit_edit = 1;
					$userTagRule   = WorkUserTagRule::find()->where(['corp_id' => $this->corp->id, 'status' => [1, 2]])->select('user_id')->all();
					if (!empty($userTagRule)) {
						$audit_edit_ids = array_column($userTagRule, 'user_id');
					}
				}
				if (!empty($isFrom)) {
					$is_audit_edit = $isFromEdit;
					if ($isFrom == 1) {
						$claimList = PublicSeaReclaimSet::find()->where(['corp_id' => $this->corp->id, 'status' => 1]);
						if (!empty($fromId)) {
							$claimList = $claimList->andWhere(['!=', 'id', $fromId]);
						}
						$claimList = $claimList->select('user')->all();
						$noUser    = [];
						foreach ($claimList as $claim) {
							if (!empty($claim->user)) {
								$UserData = explode(',', $claim->user);
								foreach ($UserData as $party) {
									array_push($noUser, intval($party));
								}
							}
						}
						$audit_edit_ids = $noUser;
					}
				}
				$user_param = [
					'disabled'              => $disabled,
					'from_channel'          => $from_channel,
					'welcome_user_ids'      => array_unique($welcome_user_ids),
					'welcome_user_ids_edit' => array_unique($welcome_user_ids_edit),
					'audit_user_ids'        => $audit_user_ids,
					'audit_depart_ids'      => $audit_depart_ids,
					'is_audit_edit'         => $is_audit_edit,
					'audit_edit_ids'        => $audit_edit_ids,
					'is_del'                => $is_del,
					'is_external'           => $is_external,
				];

				if (isset($this->subUser->sub_id) && $is_special == 1) {
					$detail = AuthoritySubUserDetail::checkSubUser($this->subUser->sub_id, $this->corp->id);
					if (empty($detail)) {
						return [];
					}
					if ($detail["type_all"] == AuthoritySubUserDetail::TYPE_ALL) {
						return WorkDepartment::getDepartment($parentId, $get_users, $this->corp->id, $user_param, $from_chat);
					}
					$departments = WorkDepartment::getUserListsSubMember($detail, [], $this->subUser->sub_id, $this->corp->id);

					return WorkDepartment::getDepartment($parentId, $get_users, $this->corp['id'], $user_param, $from_chat, $departments[0], $departments[1], $departments[2], $departments[3], $departments[4], true);
				} else {
					$endTime = microtime(true);
					\Yii::error($endTime-$startTime,"run-time-old");
					return WorkDepartment::getDepartment($parentId, $get_users, $this->corp->id, $user_param, $from_chat);
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-party/
		 * @title           获取所有的部门-部分
		 * @description     获取所有的部门-部分
		 * @method   post
		 * @url  http://{host_name}/api/work-party/get-all-department-user
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param parentId 可选 int 父级部门id
		 * @param get_users 必选 int 1获取成员0不获取
		 * @param disabled 必选 int 0不要1要
		 * @param from_channel 必选 int 0不是1是
		 * @param welcome_id 可选 int 欢迎语id
		 * @param from_chat 可选 int 1群主
		 * @param is_audit 可选 int 来源：1、监控提醒调用，2、聊天打标签
		 * @param is_audit_edit 可选 int 是否是监控提醒修改
		 * @param is_audit_user_all 可选 int 是否是全部成员调用
		 * @param is_del 可选 int 默认0，1调已删除的员工
		 * @param is_from    可选 int 来源：1、客户来源回收
		 * @param is_from_edit 可选 int 是否修改：0否、1是
		 * @param from_id 可选 int 来源修改id
		 *
		 * @return          {"error":0,"data":[{"key":1,"title":"1","department_id":1,"children":[{"key":5,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":4,"title":"23","department_id":23,"children":[{"key":6,"title":"39","department_id":39,"children":[],"user_list":[]},{"key":7,"title":"40","department_id":40,"children":[],"user_list":[]}],"user_list":[]},{"key":2,"title":"2","department_id":2,"children":[{"key":8,"title":"42","department_id":42,"children":[],"user_list":[]},{"key":9,"title":"43","department_id":43,"children":[],"user_list":[]},{"key":10,"title":"44","department_id":44,"children":[],"user_list":[]}],"user_list":[]},{"key":3,"title":"3","department_id":3,"children":[],"user_list":[]}],"user_list":[]}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    key int key
		 * @return_param    department_id int 部门id
		 * @return_param    title string 部门名称
		 * @return_param    children array 子集
		 * @return_param    user_list array 部门下的成员
		 * @return_param    id int id
		 * @return_param    userid string 成员UserID
		 * @return_param    name string 成员名称
		 * @return_param    is_checked int 成员是否可选0可选1不可选
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/12/1 15:09 重构
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAllDepartmentUser ()
		{
			$startTime = microtime(true);

			$data[]      = \Yii::$app->request->post("parentId");//父级id
			$data[]      = \Yii::$app->request->post("uid");
			$data[]      = \Yii::$app->request->post("is_external", 0);//具有外部联系人成员
			$data[]      = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			$data[]      = \Yii::$app->request->post('from_channel', 0);
			$data[]      = $startTime;
			$user_param  = $this->getPostParams();
			$from_chat   = $user_param["form"];

			return WorkDepartment::FormattingData($this->corp->id, $data, $user_param, $from_chat);
		}

		public function getPostParams ()
		{
			$disabled           = \Yii::$app->request->post('disabled', 0);
			$welcome_id         = \Yii::$app->request->post('welcome_id', 0);
			$is_del             = \Yii::$app->request->post('is_del', 0);
			$is_audit           = \Yii::$app->request->post('is_audit', 0);
			$is_audit_edit      = \Yii::$app->request->post('is_audit_edit', 0);
			$is_audit_user_all  = \Yii::$app->request->post('is_audit_user_all', 0);
			$is_user_tag        = \Yii::$app->request->post('is_user_tag', 0);
			/**
			 * 0无来源，1客户回收设置2员工删人3员工代办4员工跟进5群打卡6渠道活码7组织架构
			 */
			$isFrom             = \Yii::$app->request->post('is_from', 0);
			$departmentDisabled = \Yii::$app->request->post('departmentDisabled', 0);
			$userDisabled       = \Yii::$app->request->post('userDisabled', 0);
			$isFromEdit         = \Yii::$app->request->post('is_from_edit', 0);
			$fromId             = \Yii::$app->request->post('from_id', 0);
			$is_external        = \Yii::$app->request->post('is_external', 0);//外部联系人
			$agentid            = \Yii::$app->request->post('agentid', 0);//应用可见范围
			$subScope           = \Yii::$app->request->post('is_special', 1);//数据可见範圍

			$users             = WorkUser::findOne(['corp_id' => $this->corp['id']]);

			if (empty($users)) {
				throw new InvalidParameterException('请先同步企业微信通讯录！');
			}
			$welcome_user_ids      = [];
			$welcome_user_ids_edit = [];
			$audit_user_ids        = $audit_depart_ids = $audit_edit_ids = [];
			if ($disabled == 1) {
				//对欢迎语单独判断
				$welcome = WorkWelcome::find()->andWhere(['corp_id' => $this->corp['id']])->select('user_ids')->all();
				if (!empty($welcome)) {
					/**@var WorkWelcome $wel**/
					foreach ($welcome as $wel) {
						if (!empty($wel->user_ids)) {
							$wel_user_ids = json_decode($wel->user_ids, true);
							$user_ids     = array_column($wel_user_ids, 'id');
							foreach ($user_ids as $id) {
								array_push($welcome_user_ids, $id);
							}
						}
					}
				}
			}
			/**欢迎语**/
			if (!empty($welcome_id)) {
				$work_wel = WorkWelcome::findOne($welcome_id);
				if (!empty($work_wel->user_ids)) {
					$wel_user_ids = json_decode($work_wel->user_ids, true);
					$user_ids     = array_column($wel_user_ids, 'id');
					foreach ($user_ids as $id) {
						array_push($welcome_user_ids_edit, $id);
					}
				}
			}
			/**会话存档**/
			if (!empty($is_audit)) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException('未配置会话存档功能！');
				}
				$userDepartData = WorkMsgAuditUser::getUserIdDepartId($this->corp->workMsgAudit->id, $is_audit_user_all);
				if (empty($userDepartData)) {
					throw new InvalidParameterException('未配置会话成员和部门不存在');
				}
				$audit_user_ids   = $userDepartData['userIdData'];
				$audit_depart_ids = $userDepartData['departIdData'];
				if (!empty($is_audit_edit)) {
					if ($is_audit == 1) {
						$limitWordRemind = LimitWordRemind::find()->where(['corp_id' => $this->corp->id])->select('limit_user_id')->all();
						if (!empty($limitWordRemind)) {
							$audit_edit_ids = array_column($limitWordRemind, 'limit_user_id');
						}
					} elseif ($is_audit == 2) {
						$userTagRule = WorkUserTagRule::find()->where(['corp_id' => $this->corp->id, 'status' => [1, 2]])->select('user_id')->all();
						if (!empty($userTagRule)) {
							$audit_edit_ids = array_column($userTagRule, 'user_id');
						}
					}
				}
			}
			if (!empty($is_user_tag)) {
				$is_audit_edit = 1;
				$userTagRule   = WorkUserTagRule::find()->where(['corp_id' => $this->corp->id, 'status' => [1, 2]])->select('user_id')->all();
				if (!empty($userTagRule)) {
					$audit_edit_ids = array_column($userTagRule, 'user_id');
				}
			}
			$part = [];
			/**跟进提醒*/
			if (!empty($isFrom)) {
				$is_audit_edit = $isFromEdit;
				if ($isFrom == 1) {
					$claimList = PublicSeaReclaimSet::find()->where(['corp_id' => $this->corp->id, 'status' => 1]);
					$claimList = $claimList->select('user,party,id')->all();
					$noUser    = [];
					/** @var PublicSeaReclaimSet $claim**/
					foreach ($claimList as $claim) {
						if(!empty($claim->party)){
							if($claim->id != $fromId){
								$TempPart = explode(",",$claim->party);
								array_push($part,...$TempPart);
							}
						}
						if (!empty($claim->user) && $claim->id != $fromId) {
							$UserData = explode(',', $claim->user);
							foreach ($UserData as $party) {
								array_push($noUser, intval($party));
							}
						}
					}
//					if(!empty($part)){
//						$part = WorkDepartment::GiveDepartmentReturnChildren($part,$this->corp->id);
//					}
					$audit_edit_ids = $noUser;
				}
			}
			return [
				'disabledPart'          => $part,
				'disabled'              => $disabled,
				'agentid'               => $agentid,
				'form'                  => $isFrom,
				'departmentDisabled'    => $departmentDisabled,
				'userDisabled'          => $userDisabled,
				'welcome_user_ids'      => array_unique($welcome_user_ids),
				'welcome_user_ids_edit' => array_unique($welcome_user_ids_edit),
				'audit_user_ids'        => $audit_user_ids,
				'audit_depart_ids'      => $audit_depart_ids,
				'is_audit_edit'         => $is_audit_edit,
				'audit_edit_ids'        => $audit_edit_ids,
				'is_del'                => $is_del,
				'subScope'              => $subScope,
				'is_external'           => $is_external,
			];
		}
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-party/
		 * @title           获取所有的部门
		 * @description     获取所有的部门
		 * @method   post
		 * @url  http://{host_name}/api/work-party/search-department-or-user
		 *
		 * @param name 可选 int 搜索名称
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param from_chat 可选 int 1群主
		 *
		 * @return      {"error":0,"data":{"department":[{"id":"d-1","title":"小猪科技公司","key":"1","department_id":"1","scopedSlots":{"title":"title"}}],"workUser":[{"id":"197","title":"小姐姐","key":"197","departmentName":"小猪科技公司"},{"id":"239","title":"莫楠小鱼儿","key":"239","departmentName":"小猪科技公司"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    department array 部门
		 * @return_param    id string id
		 * @return_param    title string 部门名称
		 * @return_param    department_id string 部门id
		 * @return_param    workUser array 成员
		 * @return_param    id int id
		 * @return_param    title int 成员名称
		 * @return_param    key int id
		 * @return_param    departmentName string 所在部门
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/12/1 15:34
		 * @number          0
		 *
		 */
		public function actionSearchDepartmentOrUser ()
		{
			$name            = \Yii::$app->request->post("name");
			$orderBy         = \Yii::$app->request->post("orderBy","asc");
			if(empty($name)){
				return [];
			}
			$sub_id          = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
			$user_param      = $this->getPostParams();
			$subScope        = isset($user_param["subScope"]) ? $user_param["subScope"] : 0;
			$is_del          = isset($user_param["is_del"]) ? $user_param["is_del"] : 0;
			$agentid         = isset($user_param["agentid"]) ? $user_param["agentid"] : 0;
			$is_external     = isset($user_param["is_external"]) ? $user_param["is_external"] : 0;
			$disabledPart    = isset($user_param["disabledPart"]) ? $user_param["disabledPart"] : [];
			$from_chat       = $user_param["form"];
			$AgentDepartmentOld = $AgentDepartment = $AgentUserIds = $subUser = $subDepartment = [];
			$all             = true;
			$corp_id         = $this->corp->id;
			if(empty($corp_id)){
				throw new InvalidDataException("企业微信不存在");
			}
			if (!empty($agentid)) {
				[$AgentDepartment, $AgentUserIds,$AgentDepartmentOld] = WorkDepartment::GiveAgentIdReturnDepartmentOrUser($corp_id, $agentid, $is_del, $is_external);
				if($subScope == 1 && empty($sub_id)){
					$subScope = 0;
				}
			}
			if ($subScope == 1) {
				[$subUser, $subDepartment, $all] = WorkDepartment::GiveSubIdReturnDepartmentOrUser($corp_id, $sub_id, $user_param["is_del"], $user_param['is_external']);
				/** 范围限定包含应用范围限定**/
				if (!empty($agentid) && $all) {
					$subDepartment = array_intersect($subDepartment, $AgentDepartmentOld);
					$subUser       = array_intersect($subUser, $AgentUserIds);
				}
			} else {
				/** 应用范围限定**/
				if (!empty($agentid)) {
					$subDepartment = $AgentDepartmentOld;
					$subUser       = $AgentUserIds;
				}
			}
			$departmentLists = WorkDepartment::find()->where(["corp_id" => $corp_id, "is_del" => 0]);
			if($all && !empty($subDepartment)){
				$departmentLists = $departmentLists->andWhere(["in", "department_id", $subDepartment]);
			}
			$departmentLists = $departmentLists->andWhere("name like '%$name%'")->select("id,name,department_id")->asArray()->all();
			$WorkUser        = WorkDepartment::getUsers(0, $corp_id, $user_param, $from_chat, $subUser, $name,false,true);
			if (!empty($departmentLists)) {
				$departmentChildrenIds = array_column($departmentLists, "department_id");
				[$departmentChildrenDepartmentCount, $departmentChildrenDepartmentAll] = WorkDepartment::GiveDepartmentReturnChildResult($corp_id, $departmentChildrenIds, $subDepartment);
				$TempU = [];
				$departmentChildrenCount = WorkDepartment::GiveDepartmentReturnUserArray($departmentChildrenIds, $corp_id, $TempU, true, $is_del, $is_external, $user_param["audit_user_ids"], false, true, $subUser);
				foreach ($departmentLists as $key=>&$record) {
					$record['disabled']      = false;
					$record["ids"]           = $record["id"];
					$record["id"]            = "d-" . $record["department_id"];
					$record["titleAll"] = $record["title"]         = $record["name"];
					$record["key"]           = $record["id"];
					$record["scopedSlots"]   = ["title" => "title"];
					if ($user_param["departmentDisabled"] == 1) {
						$record['disabled'] = true;
					}
					$workUser       = 0;
					$departmentNext = 0;
					if (isset($departmentChildrenCount[$record["department_id"]])) {
						$workUser = $departmentChildrenCount[$record["department_id"]];
					}
					if (isset($departmentChildrenDepartmentAll[$record["department_id"]])) {
						$departmentNext = $departmentChildrenDepartmentAll[$record["department_id"]];
					}
					if ($workUser == 0 && count($departmentNext) <= 1) {
						$record["isLeaf"]   = true;
						$record["disabled"] = true;
					}
					if ($user_param["departmentDisabled"] == 1) {
						$record["disabled"] = true;
					}
					if (in_array($record["department_id"], $disabledPart) && !empty($disabledPart)) {
						$record["disabled"] = true;
					}
					$departmentId = $record["department_id"];
					$parentId     = \Yii::$app->db->createCommand("SELECT getParentList(" . $departmentId . "," . $corp_id . ") as department;")->queryOne();
					if (!empty($parentId)) {
						$parentId           = explode(",", $parentId["department"]);
						$departmentName     = WorkDepartment::find()->where(["in", "department_id", $parentId])->andWhere(["corp_id" => $corp_id])->orderBy("parentid $orderBy")->asArray()->all();
						$departmentName     = array_column($departmentName, "name");
						if(!empty($departmentName)){
							$record["titleAll"] = implode("/", $departmentName) . "/" . $record["titleAll"];
						}else{
							$record["titleAll"] = $record["title"];
						}
					}
				}
			}

			return [
				"department" => $departmentLists,
				"workUser"   => $WorkUser,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-party/
		 * @title           获取H5通讯录成员
		 * @description     获取H5通讯录成员
		 * @method   post
		 * @url  http://{host_name}/api/work-party/get-depart-user
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param id 必选 string 部门id
		 *
		 * @return          {"error":0,"data":{"department":[{"id":2,"name":"销售"},{"id":3,"name":"技术"},{"id":14,"name":"销售测试"},{"id":15,"name":"22"}],"users":[{"letter":"A","data":[{"id":"173","name":"A小猪cms&云美来～陈利18009696793"}]},{"letter":"C","data":[{"id":"1","name":"陈志尧"},{"id":"4","name":"陈允"}]},{"letter":"F","data":[{"id":"183","name":"flu"}]},{"letter":"J","data":[{"id":"125","name":"江月霞"},{"id":"168","name":"简迷离"}]},{"letter":"L","data":[{"id":"121","name":"李蓉蓉"},{"id":"124","name":"旅划算小助手 鹿鹿"},{"id":"126","name":"李灵烨"},{"id":"127","name":"卢亮"},{"id":"175","name":"lyc"},{"id":"184","name":"林"}]},{"letter":"N","data":[{"id":"172","name":"倪瑞"}]},{"letter":"S","data":[{"id":"122","name":"少荃"},{"id":"123","name":"孙涛"},{"id":"169","name":"施益民"}]},{"letter":"W","data":[{"id":"178","name":"王盼"},{"id":"182","name":"汪博文"}]},{"letter":"X","data":[{"id":"98","name":"徐孺牛"}]},{"letter":"Y","data":[{"id":"170","name":"叶雨琴"}]},{"letter":"Z","data":[{"id":"3","name":"张婷"},{"id":"171","name":"赞赞de＇小兔子"}]},{"letter":"#","data":[{"id":"180","name":"????"}]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/26 16:39
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetDepartUser ()
		{
			$id       = \Yii::$app->request->post('id', 0);
			$name     = \Yii::$app->request->post('name');
			$user_id  = \Yii::$app->request->post('user_id');
			$user_ids = \Yii::$app->request->post('user_ids');
			$uid      = \Yii::$app->request->post('uid');
			$isAll    = \Yii::$app->request->post('is_all', 0);//是否显示全部
			if (empty($this->corp) || empty($uid) || empty($user_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (!empty($user_ids)) {
				$data = WorkUser::getAllDepartmentUser(0, $this->corp->id, $name, [], $user_ids);

				return $data;
			}
			$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'userid' => $user_id]);
			$data     = [
				'users' => [],
			];

			if (!empty($workUser)) {
				$subUser = SubUser::findOne(['uid' => $uid, 'account' => $workUser->mobile]);
				if (empty($isAll) && !empty($subUser) && $subUser->type == 0) {
					$detail = AuthoritySubUserDetail::checkSubUser($subUser->sub_id, $this->corp->id);
					if (empty($detail)) {
						return $data;
					}
					$departments = WorkDepartment::getUserListsSubMember($detail, [], $subUser->sub_id, $this->corp->id);
					\Yii::error($departments, '$departments');
					if ($detail["type_all"] == AuthoritySubUserDetail::TYPE_ALL) {
						$data = WorkUser::getAllDepartmentUser($id, $this->corp->id, $name);

						return $data;
					}
					//3 选择的成员 1选择的部门
					$dt         = [];
					$userDepart = [];
					if (!empty($departments[2])) {
						if (count($departments[2]) > 1) {
							$allUsers = $departments[2];
							foreach ($allUsers as $uu) {
								$wUser = WorkUser::findOne($uu);
								if (!empty($wUser) && $wUser->is_del == 0) {
									$userDepart = $wUser;
								}
							}
							unset($allUsers);
						} else {
							$userDepart = WorkUser::findOne($departments[2]);
						}
						if (!empty($userDepart)) {
							$departs = explode(',', $userDepart->department);
							//自己当前部门和所选员工部门 并且没有选部门
							$departsOne = array_unique(array_merge($departs, $departments[4]));
							if (count($departsOne) == 1 && empty($departments[1])) {
								$workDepart = WorkDepartment::findOne(['department_id' => $departsOne[0]]);
								if (empty($workDepart->parentid)) {
									//当前所选员工和自己都在最上级 公司 不在子部门 无需返回子部门
									$data = WorkUser::getAllDepartmentUser($id, $this->corp->id, $name, [], $departments[0], 1);
									\Yii::error($data, '$data11');

									return $data;
								}
							}
							$departTwo = WorkDepartment::findOne(['department_id' => $departs[0]]);
							if (!empty($departTwo->parentid)) {
								$department = WorkDepartment::find()->where(['department_id' => $departs, 'is_del' => 0, 'corp_id' => $this->corp->id])->asArray()->all();
								if (!empty($department)) {
									foreach ($department as $depart) {
										$result       = WorkDepartment::getSubDepart($depart['department_id'], $this->corp->id, $dt);
										$resultDepart = WorkDepartment::getParentDepart($depart['department_id'], $this->corp->id, $dt);
										$dt           = array_unique(array_merge($result, $resultDepart));
										unset($result);
										unset($resultDepart);
									}
								}
							}

						}
					}
					$dep = [];
					if (!empty($departments[1])) {
						foreach ($departments[1] as $depart) {
							$result       = WorkDepartment::getSubDepart($depart, $this->corp->id, $dep);
							$resultDepart = WorkDepartment::getParentDepart($depart, $this->corp->id, $dep);
							$dep          = array_unique(array_merge($result, $resultDepart));
							unset($result);
							unset($resultDepart);
						}
					}
					$department = array_merge($dt, $dep, $departments[4]);
					unset($dt);
					unset($dep);
					$data = WorkUser::getAllDepartmentUser($id, $this->corp->id, $name, array_unique($department), $departments[0]);
				} else {
					$otherData = [];
					if (!empty($isAll)) {
						$otherData['is_external'] = 1;
					}
					$data = WorkUser::getAllDepartmentUser($id, $this->corp->id, $name, [], [], 0, $otherData);
				}
			}

			return $data;
		}

	}