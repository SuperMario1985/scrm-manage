<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/5/27
	 * Time: 10:57
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\AuthoritySubUserDetail;
	use app\models\WorkChat;
	use app\models\WorkExternalContact;
	use app\models\WorkMsgAudit;
	use app\models\WorkMsgAuditAgree;
	use app\models\WorkMsgAuditCategory;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkMsgAuditKey;
	use app\models\WorkMsgAuditNoticeRule;
	use app\models\WorkMsgAuditNoticeRuleInfo;
	use app\models\WorkMsgAuditUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class WorkMsgAuditController extends WorkBaseController
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
						'get'                  => ['POST'],
						'set'                  => ['POST'],
						'get-key'              => ['POST'],
						'set-key-version'      => ['POST'],
						'get-permit-user-list' => ['POST'],
						'get-single-agree'     => ['POST'],
						'get-room-agree'       => ['POST'],
						'get-categories'       => ['POST'],
						'get-rule-list'        => ['POST'],
						'get-rule-info'        => ['POST'],
						'set-rule'             => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取会话存档配置
		 * @description     获取会话存档配置
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{"credit_code":"913461560","contact_user":"阿萨","contact_phone":"13855554444","secret":null,"status":-1,"create_time":"2020-05-28 18:28:22","key_list":[{"key_id":1,"key_version":1,"private_key":"-----BEGIN PRIVATE KEY-----……-----END PRIVATE KEY-----\n","private_key_path":"/pem/2/20200528/private_20200528182822.key","public_key":"-----BEGIN PUBLIC KEY-----……-----END PUBLIC KEY-----\n","public_key_path":"/pem/2/20200528/public_20200528182822.key"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    credit_code string 统一社会信用代码
		 * @return_param    contact_user string 接口联系人
		 * @return_param    contact_phone string 接口信息人联系电话
		 * @return_param    secret string 会话存档的secret
		 * @return_param    status int 是否开启：1、开启；0、关闭；-1、未开启
		 * @return_param    create_time string 创建时间
		 * @return_param    key_list array 密钥对列表
		 * @return_param    key_id int 密钥对ID
		 * @return_param    key_version string 密钥对版本
		 * @return_param    private_key string 私钥内容
		 * @return_param    private_key_path string 私钥文件地址
		 * @return_param    public_key string 公钥内容
		 * @return_param    public_key_path string 公钥文件地址
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/5/28 18:41
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGet ()
		{

			if (\Yii::$app->request->isPost) {
				$workMsgAudit = [];
				if (!empty($this->corp->workMsgAudit)) {
					$workMsgAudit = $this->corp->workMsgAudit->dumpData(true);
				}

				return $workMsgAudit;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           设置会话存档
		 * @description     设置会话存档
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/set
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param credit_code 可选 string 社会统一信用代码
		 * @param contact_user 可选 string 接口联系人
		 * @param contact_phone 可选 string 接口联系人手机号
		 * @param secret 可选 string 会话存档的secret
		 *
		 * @return          {"error":0,"data":{"credit_code":"913461560","contact_user":"阿萨","contact_phone":"13855554444","secret":null,"status":-1,"create_time":"2020-05-28 18:28:22","key_list":[{"key_id":1,"key_version":1,"private_key":"-----BEGIN PRIVATE KEY-----……-----END PRIVATE KEY-----\n","private_key_path":"/pem/2/20200528/private_20200528182822.key","public_key":"-----BEGIN PUBLIC KEY-----……-----END PUBLIC KEY-----\n","public_key_path":"/pem/2/20200528/public_20200528182822.key"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    credit_code string 统一社会信用代码
		 * @return_param    contact_user string 接口联系人
		 * @return_param    contact_phone string 接口信息人联系电话
		 * @return_param    secret string 会话存档的secret
		 * @return_param    status int 是否开启：1、开启；0、关闭；-1、未开启
		 * @return_param    create_time string 创建时间
		 * @return_param    key_list array 密钥对列表
		 * @return_param    key_id int 密钥对ID
		 * @return_param    key_version string 密钥对版本
		 * @return_param    private_key string 私钥内容
		 * @return_param    private_key_path string 私钥文件地址
		 * @return_param    public_key string 公钥内容
		 * @return_param    public_key_path string 公钥文件地址
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/5/28 18:04
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionSet ()
		{
			if (\Yii::$app->request->isPost) {
				$creditCode   = \Yii::$app->request->post('credit_code', '');
				$contactUser  = \Yii::$app->request->post('contact_user', '');
				$contactPhone = \Yii::$app->request->post('contact_phone', '');
				$secret       = \Yii::$app->request->post('secret', '');

				$auditData = [
					'credit_code'   => $creditCode,
					'contact_user'  => $contactUser,
					'contact_phone' => $contactPhone,
					'secret'        => $secret,
				];

				$auditId = WorkMsgAudit::create($this->corp->id, $auditData);

				return WorkMsgAudit::findOne($auditId)->dumpData(true);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           修改会话存档的状态
		 * @description     修改会话存档的状态
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/change-status
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param status 可选 int 会话存档的状态1、开启；0、关闭；-1、未开启
		 *
		 * @return          {"error":0,"data":{"credit_code":"913461560","contact_user":"阿萨","contact_phone":"13855554444","secret":null,"status":-1,"create_time":"2020-05-28 18:28:22","key_list":[{"key_id":1,"key_version":1,"private_key":"-----BEGIN PRIVATE KEY-----……-----END PRIVATE KEY-----\n","private_key_path":"/pem/2/20200528/private_20200528182822.key","public_key":"-----BEGIN PUBLIC KEY-----……-----END PUBLIC KEY-----\n","public_key_path":"/pem/2/20200528/public_20200528182822.key"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    credit_code string 统一社会信用代码
		 * @return_param    contact_user string 接口联系人
		 * @return_param    contact_phone string 接口信息人联系电话
		 * @return_param    secret string 会话存档的secret
		 * @return_param    status int 是否开启：1、开启；0、关闭；-1、未开启
		 * @return_param    create_time string 创建时间
		 * @return_param    key_list array 密钥对列表
		 * @return_param    key_id int 密钥对ID
		 * @return_param    key_version string 密钥对版本
		 * @return_param    private_key string 私钥内容
		 * @return_param    private_key_path string 私钥文件地址
		 * @return_param    public_key string 公钥内容
		 * @return_param    public_key_path string 公钥文件地址
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/5/28 18:58
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionChangeStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$status = \Yii::$app->request->post('status', '');

				if (!empty($this->corp->workMsgAudit)) {
					$status = (string)$status;
					if ($status != '') {
						switch ($status) {
							case WorkMsgAudit::MSG_AUDIT_FORBIDDEN:
								$this->corp->workMsgAudit->status = WorkMsgAudit::MSG_AUDIT_FORBIDDEN;
								$this->corp->workMsgAudit->update();

								break;
							case WorkMsgAudit::MSG_AUDIT_CLOSE:
								$this->corp->workMsgAudit->status = WorkMsgAudit::MSG_AUDIT_CLOSE;
								$this->corp->workMsgAudit->update();

								break;
							case WorkMsgAudit::MSG_AUDIT_OPEN:
								$this->corp->workMsgAudit->status = WorkMsgAudit::MSG_AUDIT_OPEN;
								$this->corp->workMsgAudit->update();

								break;
							default:
								break;
						}
					}

					return $this->corp->workMsgAudit->dumpData(true);
				} else {
					return [];
				}
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取证书文件
		 * @description     自动获取证书文件
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-key
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{"key_id":1,"key_version":null,"private_key":"-----BEGIN PRIVATE KEY-----……---END PRIVATE KEY-----\n","private_key_path":"/pem/2/20200528/private_20200528182822.key","public_key":"-----BEGIN PUBLIC KEY-----……-----END PUBLIC KEY-----\n","public_key_path":"/pem/2/20200528/public_20200528182822.key"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    key_id int 密钥对ID
		 * @return_param    key_version string 密钥对版本
		 * @return_param    private_key string 私钥内容
		 * @return_param    private_key_path string 私钥文件地址
		 * @return_param    public_key string 公钥内容
		 * @return_param    public_key_path string 公钥文件地址
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/5/28 18:23
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionGetKey ()
		{
			if (\Yii::$app->request->isPost) {
				return WorkMsgAuditKey::create($this->corp->id);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           设置密钥对的版本号
		 * @description     设置密钥对的版本号
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/set-key-version
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param key_id 必选 string 密钥对ID
		 * @param key_version 必选 string 密钥对版本号
		 *
		 * @return          {"error":0,"data":{"key_id":1,"key_version":"1","private_key":"-----BEGIN PRIVATE KEY-----……-----END PRIVATE KEY-----\n","private_key_path":"/pem/2/20200528/private_20200528182822.key","public_key":"-----BEGIN PUBLIC KEY-----……-----END PUBLIC KEY-----\n","public_key_path":"/pem/2/20200528/public_20200528182822.key"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    key_id int 密钥对ID
		 * @return_param    key_version string 密钥对版本
		 * @return_param    private_key string 私钥内容
		 * @return_param    private_key_path string 私钥文件地址
		 * @return_param    public_key string 公钥内容
		 * @return_param    public_key_path string 公钥文件地址
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/5/28 18:36
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionSetKeyVersion ()
		{
			if (\Yii::$app->request->isPost) {
				$keyId   = \Yii::$app->request->post('key_id', '');
				$version = \Yii::$app->request->post('key_version', '');
				if (empty($keyId) || empty($version)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				return WorkMsgAuditKey::changeVersion($keyId, $version);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取会话内容存档开启成员列表
		 * @description     获取会话内容存档开启成员列表
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-permit-user-list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param refresh 可选 int 是否刷新成员：0、不刷新；1、刷新
		 *
		 * @return          {"error":0,"data":[{"id":24,"key":24,"corp_id":2,"userid":"Qiaie","name":"钱","department":"39","order":"0","position":"","mobile":"1801249","gender":"0","email":"","is_leader_in_dept":"0","avatar":"https://wework.qpic.cn/bizmail/KpGsSiclmssFKeg/0","thumb_avatar":"https://wework.qpic.cn/bizmail/KpGsSicTnucvtkrK9XtlmssFKeg/100","telephone":"","enable":1,"alias":"","address":null,"extattr":"[]","status":1,"qr_code":"https://open.work.weixin.qq.com/wwopen/userQRCode?vcode=vc9799a0","is_del":0,"openid":"oru9-jv9Ycs8T0k","is_external":"有","apply_num":25,"new_customer":61,"chat_num":797,"message_num":8411,"replyed_per":"97%","first_reply_time":"10.46分钟","delete_customer_num":3,"department_name":"A组","tag_name":[]},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int ID
		 * @return_param    key int 关键件
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
		 * @return_param    openid string 成员openid
		 * @return_param    is_external string 是否有外部联系人权限
		 * @return_param    apply_num int 发起申请数
		 * @return_param    new_customer int 新增客户数
		 * @return_param    chat_num int 聊天数
		 * @return_param    message_num int 发送消息数
		 * @return_param    replyed_per string 已回复聊天占比
		 * @return_param    first_reply_time string 平均首次回复时长
		 * @return_param    delete_customer_num int 拉黑客户数
		 * @return_param    department_name string 部门名称
		 * @return_param    tag_name array 标签组
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/8 15:28
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGetPermitUserList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}
				$refresh = \Yii::$app->request->post('refresh', 0);
				$result  = [];
				if ($refresh == 1 || empty($this->corp->workMsgAudit->workMsgAuditUsers)) {
					if ($this->corp->workMsgAudit->status != 1) {
						throw new InvalidParameterException('未开启会话存档功能！');
					}
					try {
						$msgAuditInfo = WorkMsgAuditUser::getPermitUserList($this->corp->id);
						$result       = $msgAuditInfo['info'];
					} catch (\Exception $e) {
						$message = $e->getMessage();
						if (strpos($message, '40001') !== false) {
							$message = '不合法的secret参数,请检查会话存档配置';
						}
						throw new InvalidParameterException($message);
					}
				} else {
					foreach ($this->corp->workMsgAudit->workMsgAuditUsers as $workMsgAuditUser) {
						if (empty($workMsgAuditUser->user)) {
							try {
								$workUserId = WorkUser::getUserSuite($this->corp->id, $workMsgAuditUser->userid);
								if (!empty($workUserId)) {
									$workMsgAuditUser->user_id = $workUserId;
									$workMsgAuditUser->update();
								}
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), __CLASS__ . '-' . __FUNCTION__ . '_getWorkUser');
							}
						}

						if (!empty($workMsgAuditUser->user)) {
							$msgAuditUser = $workMsgAuditUser->user->dumpData();
							array_push($result, $msgAuditUser);
						}
					}
				}
				if (isset($this->subUser->sub_id)) {
					$user_ids = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if (is_array($user_ids)) {
						foreach ($result as $key => $item) {
							if (!in_array($item['id'], $user_ids)) {
								unset($result[$key]);
							}
						};
						$result = array_values($result);

						return $result;
					}
					if ($user_ids === false) {
						return [

						];
					}

					return $result;
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取单聊会话同意情况
		 * @description     获取单聊会话同意情况
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-single-agree
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param user_id 必选 int 成员内部ID
		 * @param external_id 必选 int 外部联系人的内部ID
		 *
		 * @return          {"error":0,"data":{"count":1,"success":1,"failed":0,"info":[{"id":1,"userid":"Qie","exteranalopenid":"wm_4OwBwOGqpkNCfzhSw","roomid":null,"agree_status":"Default_Agree","status_change_time":"1590367716","user":{"id":24,"key":24,"corp_id":2,"userid":"Qie","name":"钱洁","department":"39","order":"0","position":"","mobile":"180****1249","gender":"0","email":"","is_leader_in_dept":"0","avatar":"http://wework.qpic.cn/bizmail/KpGsSicZdkoOtkrK9XtlmssFKeg/0","thumb_avatar":"http://wework.qpic.krK9XtlmssFKeg/100","telephone":"","enable":1,"alias":"","address":null,"extattr":"[]","status":1,"qr_code":"https://open.work.weixin.qq.com/wwopen/userQRCode?vcode=vc97a6199a0","is_del":0,"openid":"oru9-jv9bM5Zd1Ycs8T0k","is_external":"有","apply_num":12,"new_customer":39,"chat_num":396,"message_num":4155,"replyed_per":"96%","first_reply_time":"11.23分钟","delete_customer_num":2,"department_name":"A组","tag_name":[]},"external":{"external_userid":"wm_4OwBwOGqpkNCfzhSw","name":"","name_convert":"ly","position":null,"avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5icwgbQqvlHBXyVzKuCBKu02FcCOKeAG9yxQ/0","corp_name":null,"corp_full_name":null,"type":1,"gender":2,"unionid":null,"openid":"oru9-jrSPR38YTuL_b-E","nickname":"","des":"","close_rate":null,"follow_status":0,"follow_id":31},"room":[]}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 总人数
		 * @return_param    success int 成功人数
		 * @return_param    failed int 失败人数
		 * @return_param    info array 同一情况
		 * @return_param    info.id int ID
		 * @return_param    info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    info.exteranalopenid string 外部成员的externalopenid
		 * @return_param    info.roomid string 群组ID
		 * @return_param    info.agree_status string 同意:”Agree”，不同意:”Disagree”，默认同意:”Default_Agree”
		 * @return_param    info.status_change_time string 同意状态改变的具体时间
		 * @return_param    info.user.key int 关键件
		 * @return_param    info.user.corp_id int 授权的企业ID
		 * @return_param    info.user.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    info.user.name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    info.user.department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    info.user.order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    info.user.position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    info.user.mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    info.user.gender int 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    info.user.email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    info.user.is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    info.user.avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    info.user.thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    info.user.telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    info.user.enable string 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    info.user.alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    info.user.address string 地址
		 * @return_param    info.user.extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    info.user.status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    info.user.qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    info.user.is_del int 0：未删除；1：已删除
		 * @return_param    info.user.openid string 成员openid
		 * @return_param    info.user.is_external string 是否有外部联系人权限
		 * @return_param    info.user.apply_num int 发起申请数
		 * @return_param    info.user.new_customer int 新增客户数
		 * @return_param    info.user.chat_num int 聊天数
		 * @return_param    info.user.message_num int 发送消息数
		 * @return_param    info.user.replyed_per string 已回复聊天占比
		 * @return_param    info.user.first_reply_time string 平均首次回复时长
		 * @return_param    info.user.delete_customer_num int 拉黑客户数
		 * @return_param    info.user.department_name string 部门名称
		 * @return_param    info.user.tag_name array 标签组
		 * @return_param    info.external.external_userid string 外部联系人的userid
		 * @return_param    info.external.name string 外部联系人的姓名或别名
		 * @return_param    info.external.name_convert string 外部联系人的姓名或别名（解码后）
		 * @return_param    info.external.position string 外部联系人的职位，如果外部企业或用户选择隐藏职位，则不返回，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.avatar string 外部联系人头像，第三方不可获取
		 * @return_param    info.external.corp_name string 外部联系人所在企业的简称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.corp_full_name string 外部联系人所在企业的主体名称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.type int 外部联系人的类型，1表示该外部联系人是微信用户，2表示该外部联系人是企业微信用户
		 * @return_param    info.external.gender int 外部联系人性别 0-未知 1-男性 2-女性
		 * @return_param    info.external.unionid string 外部联系人在微信开放平台的唯一身份标识（微信unionid），通过此字段企业可将外部联系人与公众号/小程序用户关联起来。仅当联系人类型是微信用户，且企业绑定了微信开发者ID有此字段。查看绑定方法
		 * @return_param    info.external.openid string 外部联系人openid
		 * @return_param    info.external.nickname string 设置的用户昵称
		 * @return_param    info.external.des string 设置的用户描述
		 * @return_param    info.external.close_rate string 预计成交率
		 * @return_param    info.external.follow_status int 跟进状态
		 * @return_param    info.external.follow_id int 跟进id
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/8 16:34
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \Throwable
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGetSingleAgree ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$userId     = \Yii::$app->request->post('user_id', '');
				$externalId = \Yii::$app->request->post('external_id', '');

				if (empty($userId) || empty($externalId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$workUser = WorkUser::findOne($userId);
				if (empty($workUser)) {
					throw new InvalidParameterException('参数不正确');
				}

				$info = [];

				if (!empty($externalId)) {
					$externalData = WorkExternalContact::findOne($externalId);

					if (empty($externalData)) {
						throw new InvalidParameterException('参数不正确');
					}

					array_push($info, ['userid' => $workUser->userid, 'exteranalopenid' => $externalData->external_userid]);
				} else {
					if (!empty($workUser->workExternalContactFollowUsers)) {
						foreach ($workUser->workExternalContactFollowUsers as $workExternalContactFollowUser) {
							if (!empty($workExternalContactFollowUser->externalUser)) {
								array_push($info, ['userid' => $workUser->userid, 'exteranalopenid' => $workExternalContactFollowUser->externalUser->external_userid]);
							}
						}
					}
				}

				return WorkMsgAuditAgree::checkSingle($this->corp->id, $info);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取群聊会话同意情况
		 * @description     获取群聊会话同意情况
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-room-agree
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param room_id 必选 int 群聊内部ID
		 *
		 * @return          {"error":0,"data":{"count":1,"success":1,"failed":0,"info":[{"id":2,"userid":null,"exteranalopenid":"wm_4OwBwAVAJ0mg","roomid":"wr_4Owr9A","agree_status":"Default_Agree","status_change_time":1589948279,"user":[],"external":{"external_userid":"wm_4OwBwAVAJ0mg","name":"张继光·品牌部落","name_convert":"张继光·品牌部落","position":null,"avatar":"http://wx.qlogo.cn/mmhead/JgtHcaQ9BYMB7Kbe74KSV1ciaibnxE/0","corp_name":null,"corp_full_name":null,"type":1,"gender":1,"unionid":null,"openid":"oru9-jhRXRG3Ylw","nickname":"","des":"","close_rate":null,"follow_status":0,"follow_id":31},"room":{"id":3,"corp_id":2,"chat_id":"wr_4Owr9A","name":"品牌部落轻晓云","owner_id":29,"owner":"Lung","create_time":"1589538444","notice":null}}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 总人数
		 * @return_param    success int 成功人数
		 * @return_param    failed int 失败人数
		 * @return_param    info array 同一情况
		 * @return_param    info.id int ID
		 * @return_param    info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    info.exteranalopenid string 外部成员的externalopenid
		 * @return_param    info.roomid string 群组ID
		 * @return_param    info.agree_status string 同意:”Agree”，不同意:”Disagree”，默认同意:”Default_Agree”
		 * @return_param    info.status_change_time string 同意状态改变的具体时间
		 * @return_param    info.external.external_userid string 外部联系人的userid
		 * @return_param    info.external.name string 外部联系人的姓名或别名
		 * @return_param    info.external.name_convert string 外部联系人的姓名或别名（解码后）
		 * @return_param    info.external.position string 外部联系人的职位，如果外部企业或用户选择隐藏职位，则不返回，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.avatar string 外部联系人头像，第三方不可获取
		 * @return_param    info.external.corp_name string 外部联系人所在企业的简称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.corp_full_name string 外部联系人所在企业的主体名称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    info.external.type int 外部联系人的类型，1表示该外部联系人是微信用户，2表示该外部联系人是企业微信用户
		 * @return_param    info.external.gender int 外部联系人性别 0-未知 1-男性 2-女性
		 * @return_param    info.external.unionid string 外部联系人在微信开放平台的唯一身份标识（微信unionid），通过此字段企业可将外部联系人与公众号/小程序用户关联起来。仅当联系人类型是微信用户，且企业绑定了微信开发者ID有此字段。查看绑定方法
		 * @return_param    info.external.openid string 外部联系人openid
		 * @return_param    info.external.nickname string 设置的用户昵称
		 * @return_param    info.external.des string 设置的用户描述
		 * @return_param    info.external.close_rate string 预计成交率
		 * @return_param    info.external.follow_status int 跟进状态
		 * @return_param    info.external.follow_id int 跟进id
		 * @return_param    info.room.id int 群组内部ID
		 * @return_param    info.room.corp_id int 企业ID
		 * @return_param    info.room.chat_id string 客户群ID
		 * @return_param    info.room.name string 群名
		 * @return_param    info.room.owner_id int 群主用户ID
		 * @return_param    info.room.owner string 群主ID
		 * @return_param    info.room.create_time string 群的创建时间
		 * @return_param    info.room.notice string 群公告
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/8 16:54
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \Throwable
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGetRoomAgree ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$roomId = \Yii::$app->request->post('room_id', '');

				if (empty($roomId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$room = WorkChat::findOne($roomId);
				if (empty($room)) {
					throw new InvalidParameterException('参数不正确');
				}

				return WorkMsgAuditAgree::checkRoom($this->corp->id, $room->chat_id);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/get-categories
		 * @title           获取会话存档类别
		 * @description     获取会话存档类别
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-categories
		 *
		 * @param           * * * *
		 *
		 * @return          {"error":0,"data":{"1":"文本","2":"图片","3":"撤回","4":"同意","5":"不同意","6":"语音","7":"视频","8":"名片","9":"位置","10":"Emotion 表情","11":"文件","12":"链接","13":"小程序","14":"聊天记录","15":"待办","16":"投票","17":"填表","18":"红包","19":"会议","20":"在线文档","21":"Markdown","22":"图文","23":"日程","24":"混合"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/20 16:27
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetCategories ()
		{
			if (\Yii::$app->request->isPost) {
				return WorkMsgAuditCategory::getCategory();
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/get-rule-list
		 * @title           获取规则列表
		 * @description     获取规则列表
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-rule-list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":[{"id":1,"audit_id":2,"notice_name":"测试规则","status":1,"create_time":"2020-06-20 16:22:32","info":{"8":{"agent_id":8,"agent_info":{"id":8,"agentid":10002,"agent_type":2,"basic_agent_type":"","agent_use_type":0,"agent_is_money":0,"name":"客服消息","round_logo_url":null,"square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf4177JGMA/0","description":"客服消息","level":null,"allow_party":"1","allow_user":"","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-m-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":null},"notice_info":{"1":{"category_id":1,"category_name":"文本","user_info":{"1":{"user_id":1,"user_info":{"id":1,"key":1,"corp_id":1,"userid":"den","name":"尧","department":"1,2","order":"0,0","position":"","mobile":"1530648","gender":"1","email":"","is_leader_in_dept":"0,1","avatar":"http://wework.qpic.cn/bizmail/upwJgIAYaE3SzsibQ/0","thumb_avatar":"http://wework.qpic.cn/bizmail/upwJlWrJ7SGEnJBzsibQ/100","telephone":"","enable":1,"alias":"","address":"","extattr":"[]","status":1,"qr_code":"https://open.work.weixin.qq.com/wwopen/userQRCode?vcode=v1d","is_del":0,"openid":"oojmqwvdo","is_external":"有","apply_num":2,"new_customer":31,"chat_num":58,"message_num":195,"replyed_per":"--","first_reply_time":"--","delete_customer_num":40,"department_name":"科技公司/销售","tag_name":[]}},"loop":"……"}},"loop":"……"}}}}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 规则ID
		 * @return_param    audit_id int 会话存档ID
		 * @return_param    notice_name string 规则名称
		 * @return_param    status int 规则状态：0、关闭；1、开启
		 * @return_param    create_time string 创建时间
		 * @return_param    info array 规则详细内容
		 * @return_param    info.agent_id int 应用ID
		 * @return_param    info.agent_info data 应用信息
		 * @return_param    info.agent_info.id int 应用id
		 * @return_param    info.agent_info.agentid string 企业应用id
		 * @return_param    info.agent_info.agent_type int 应用类型
		 * @return_param    info.agent_info.agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    info.agent_info.name string 企业应用名称
		 * @return_param    info.agent_info.round_logo_url string 授权方应用圆形头像
		 * @return_param    info.agent_info.square_logo_url string 企业应用方形头像
		 * @return_param    info.agent_info.description string 企业应用详情
		 * @return_param    info.agent_info.level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    info.agent_info.allow_party string 应用可见范围（部门）
		 * @return_param    info.agent_info.allow_user string 应用可见范围（成员）
		 * @return_param    info.agent_info.allow_tag string 应用可见范围（标签）
		 * @return_param    info.agent_info.extra_party string 额外通讯录（部门）
		 * @return_param    info.agent_info.extra_user string 额外通讯录（成员）
		 * @return_param    info.agent_info.extra_tag string 额外通讯录（标签）
		 * @return_param    info.agent_info.close int 企业应用是否被停用
		 * @return_param    info.agent_info.redirect_domain string 企业应用可信域名
		 * @return_param    info.agent_info.report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    info.agent_info.isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    info.agent_info.home_url string 应用主页url
		 * @return_param    info.notice_info data 规则信息
		 * @return_param    info.notice_info.category_id int 类别ID
		 * @return_param    info.notice_info.category_name string 类别名称
		 * @return_param    info.notice_info.user_info data 成员数据
		 * @return_param    info.notice_info.user_info.user_id int 成员ID
		 * @return_param    info.notice_info.user_info.user_info data 成员信息
		 * @return_param    info.notice_info.user_info.user_info.id int 成员ID
		 * @return_param    info.notice_info.user_info.user_info.key int 关键件
		 * @return_param    info.notice_info.user_info.user_info.corp_id int 授权的企业ID
		 * @return_param    info.notice_info.user_info.user_info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    info.notice_info.user_info.user_info.name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    info.notice_info.user_info.user_info.department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    info.notice_info.user_info.user_info.order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    info.notice_info.user_info.user_info.position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.gender int 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    info.notice_info.user_info.user_info.email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.enable string 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    info.notice_info.user_info.user_info.alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.address string 地址
		 * @return_param    info.notice_info.user_info.user_info.extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    info.notice_info.user_info.user_info.qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.is_del int 0：未删除；1：已删除
		 * @return_param    info.notice_info.user_info.user_info.openid string 成员openid
		 * @return_param    info.notice_info.user_info.user_info.is_external string 是否有外部联系人权限
		 * @return_param    info.notice_info.user_info.user_info.apply_num int 发起申请数
		 * @return_param    info.notice_info.user_info.user_info.new_customer int 新增客户数
		 * @return_param    info.notice_info.user_info.user_info.chat_num int 聊天数
		 * @return_param    info.notice_info.user_info.user_info.message_num int 发送消息数
		 * @return_param    info.notice_info.user_info.user_info.replyed_per string 已回复聊天占比
		 * @return_param    info.notice_info.user_info.user_info.first_reply_time string 平均首次回复时长
		 * @return_param    info.notice_info.user_info.user_info.delete_customer_num int 拉黑客户数
		 * @return_param    info.notice_info.user_info.user_info.department_name string 部门名称
		 * @return_param    info.notice_info.user_info.user_info.tag_name array 标签组
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/20 16:29
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetRuleList ()
		{

			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$result = [];

				if (!empty($this->corp->workMsgAudit->workMsgAuditNoticeRules)) {
					foreach ($this->corp->workMsgAudit->workMsgAuditNoticeRules as $workMsgAuditNoticeRule) {
						array_push($result, $workMsgAuditNoticeRule->dumpData(true, true, true));
					}
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/get-rule-info
		 * @title           获取规则详情
		 * @description     获取规则详情
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-rule-info
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param rule_id 必选 int 规则ID
		 *
		 * @return          {"error":0,"data":{"id":1,"audit_id":2,"notice_name":"测试规则","status":1,"create_time":"2020-06-20 16:22:32","info":{"8":{"agent_id":8,"agent_info":{"id":8,"agentid":10002,"agent_type":2,"basic_agent_type":"","agent_use_type":0,"agent_is_money":0,"name":"客服消息","round_logo_url":null,"square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf4177JGMA/0","description":"客服消息","level":null,"allow_party":"1","allow_user":"","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-m-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":null},"notice_info":{"1":{"category_id":1,"category_name":"文本","user_info":{"1":{"user_id":1,"user_info":{"id":1,"key":1,"corp_id":1,"userid":"den","name":"尧","department":"1,2","order":"0,0","position":"","mobile":"1530648","gender":"1","email":"","is_leader_in_dept":"0,1","avatar":"http://wework.qpic.cn/bizmail/upwJgIAYaE3SzsibQ/0","thumb_avatar":"http://wework.qpic.cn/bizmail/upwJlWrJ7SGEnJBzsibQ/100","telephone":"","enable":1,"alias":"","address":"","extattr":"[]","status":1,"qr_code":"https://open.work.weixin.qq.com/wwopen/userQRCode?vcode=v1d","is_del":0,"openid":"oojmqwvdo","is_external":"有","apply_num":2,"new_customer":31,"chat_num":58,"message_num":195,"replyed_per":"--","first_reply_time":"--","delete_customer_num":40,"department_name":"科技公司/销售","tag_name":[]}},"loop":"……"}},"loop":"……"}}}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 规则ID
		 * @return_param    audit_id int 会话存档ID
		 * @return_param    notice_name string 规则名称
		 * @return_param    status int 规则状态：0、关闭；1、开启
		 * @return_param    create_time string 创建时间
		 * @return_param    info array 规则详细内容
		 * @return_param    info.agent_id int 应用ID
		 * @return_param    info.agent_info data 应用信息
		 * @return_param    info.agent_info.id int 应用id
		 * @return_param    info.agent_info.agentid string 企业应用id
		 * @return_param    info.agent_info.agent_type int 应用类型
		 * @return_param    info.agent_info.agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    info.agent_info.name string 企业应用名称
		 * @return_param    info.agent_info.round_logo_url string 授权方应用圆形头像
		 * @return_param    info.agent_info.square_logo_url string 企业应用方形头像
		 * @return_param    info.agent_info.description string 企业应用详情
		 * @return_param    info.agent_info.level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    info.agent_info.allow_party string 应用可见范围（部门）
		 * @return_param    info.agent_info.allow_user string 应用可见范围（成员）
		 * @return_param    info.agent_info.allow_tag string 应用可见范围（标签）
		 * @return_param    info.agent_info.extra_party string 额外通讯录（部门）
		 * @return_param    info.agent_info.extra_user string 额外通讯录（成员）
		 * @return_param    info.agent_info.extra_tag string 额外通讯录（标签）
		 * @return_param    info.agent_info.close int 企业应用是否被停用
		 * @return_param    info.agent_info.redirect_domain string 企业应用可信域名
		 * @return_param    info.agent_info.report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    info.agent_info.isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    info.agent_info.home_url string 应用主页url
		 * @return_param    info.notice_info data 规则信息
		 * @return_param    info.notice_info.category_id int 类别ID
		 * @return_param    info.notice_info.category_name string 类别名称
		 * @return_param    info.notice_info.user_info data 成员数据
		 * @return_param    info.notice_info.user_info.user_id int 成员ID
		 * @return_param    info.notice_info.user_info.user_info data 成员信息
		 * @return_param    info.notice_info.user_info.user_info.id int 成员ID
		 * @return_param    info.notice_info.user_info.user_info.key int 关键件
		 * @return_param    info.notice_info.user_info.user_info.corp_id int 授权的企业ID
		 * @return_param    info.notice_info.user_info.user_info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    info.notice_info.user_info.user_info.name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    info.notice_info.user_info.user_info.department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    info.notice_info.user_info.user_info.order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    info.notice_info.user_info.user_info.position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.gender int 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    info.notice_info.user_info.user_info.email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.enable string 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    info.notice_info.user_info.user_info.alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.address string 地址
		 * @return_param    info.notice_info.user_info.user_info.extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    info.notice_info.user_info.user_info.qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    info.notice_info.user_info.user_info.is_del int 0：未删除；1：已删除
		 * @return_param    info.notice_info.user_info.user_info.openid string 成员openid
		 * @return_param    info.notice_info.user_info.user_info.is_external string 是否有外部联系人权限
		 * @return_param    info.notice_info.user_info.user_info.apply_num int 发起申请数
		 * @return_param    info.notice_info.user_info.user_info.new_customer int 新增客户数
		 * @return_param    info.notice_info.user_info.user_info.chat_num int 聊天数
		 * @return_param    info.notice_info.user_info.user_info.message_num int 发送消息数
		 * @return_param    info.notice_info.user_info.user_info.replyed_per string 已回复聊天占比
		 * @return_param    info.notice_info.user_info.user_info.first_reply_time string 平均首次回复时长
		 * @return_param    info.notice_info.user_info.user_info.delete_customer_num int 拉黑客户数
		 * @return_param    info.notice_info.user_info.user_info.department_name string 部门名称
		 * @return_param    info.notice_info.user_info.user_info.tag_name array 标签组
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/20 16:51
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetRuleInfo ()
		{

			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$ruleId = \Yii::$app->request->post('rule_id', '');

				if (empty($ruleId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$ruleInfo = WorkMsgAuditNoticeRule::findOne(['id' => $ruleId, 'audit_id' => $this->corp->workMsgAudit->id]);
				if (empty($ruleInfo)) {
					throw new InvalidParameterException('参数不正确');
				}

				return $ruleInfo->dumpData(true, true, true);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           添加或者修改规则
		 * @description     添加或者修改规则
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/set-rule
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param title 必选 string 规则名称
		 * @param agent_id 必选 int 发送的应用ID
		 * @param category_ids 必选 string 通知类别ID（用,分割）
		 * @param users 必选 string 通知成员ID（用,分割）
		 * @param rule_id 可选 int 规则ID（修改规则时必填）
		 *
		 * @return          {"error":0,"data":{"rule_id":1}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    rule_id int 规则ID
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/20 16:22
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionSetRule ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$noticeName = \Yii::$app->request->post('title', '');
				$agentId    = \Yii::$app->request->post('agent_id', '');
				$categories = \Yii::$app->request->post('category_ids', '');
				$users      = \Yii::$app->request->post('users', '');

				if (empty($noticeName) || empty($agentId) || empty($categories) || empty($users)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$ruleId = \Yii::$app->request->post('rule_id', '');

				$noticeData = [
					'notice_id'    => $ruleId,
					'notice_name'  => $noticeName,
					'agent_id'     => $agentId,
					'category_ids' => $categories,
					'users'        => $users,
				];

				$noticeId = WorkMsgAuditNoticeRule::create($this->corp->workMsgAudit->id, $noticeData);

				return [
					'rule_id' => $noticeId,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           修改规则状态
		 * @description     修改规则状态
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/change-rule
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param status 必选 int 状态：0：关闭、1：开启
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/22 14:40
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChangeRule ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$ruleId = \Yii::$app->request->post('rule_id', '');
				$status = \Yii::$app->request->post('status', NULL);
				if (empty($ruleId) || is_null($status)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$upStatus = WorkMsgAuditNoticeRule::updateAll(['status' => $status], ['id' => $ruleId, 'audit_id' => $this->corp->workMsgAudit->id]);

				if (!$upStatus) {
					return false;
				} else {
					return true;
				}
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           删除规则
		 * @description     删除规则
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/del-rule
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/22 14:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 */
		public function actionDelRule ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$ruleId = \Yii::$app->request->post('rule_id', '');
				if (empty($ruleId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$rowInfoNum = WorkMsgAuditNoticeRuleInfo::deleteAll(['rule_id' => $ruleId]);
				$rowNum     = WorkMsgAuditNoticeRule::deleteAll(['id' => $ruleId, 'audit_id' => $this->corp->workMsgAudit->id]);

				if ($rowNum == 0) {
					throw new NotAllowException('非法操作！');
				} else {
					return true;
				}
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit
		 * @title           获取会话消息列表
		 * @description     获取会话消息列表
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-msg-list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param user_id 必选 int 员工ID
		 * @param type 可选 int 会话类型0：内部；1：外部；2：群聊。默认0
		 *
		 * @return          {"error":0,"data":[{"id":"147521","msgtime":"1593682776981","content":"[OK]","chatUserId":"53","user":{"id":"53","name":"尧","avatar":"https://wework.qpic.cn/wwhead/duc2TvpEjtp8lM/0","thumb_avatar":"https://wework.qpic.cn/wwhead/duc2T8lM/100"}},{"id":"151895","msgtime":"1593754681091","content":"嗯","chatUserId":"2647","user":{"id":"2647","name":"我是个正面人物","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM4gia1Cw/0","corp_name":null}},{"id":"152060","msgtime":"1593755434878","content":"app在重新打包最新版本的","chat_id":"62","roomid":"wr_4OwBwdvHkQ","chat":"中研com"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 最后一条回话的ID
		 * @return_param    msgtime string 最后一条消息的时间
		 * @return_param    content string 最后一条消息的内容
		 * @return_param    chatUserId string 会话人员ID
		 * @return_param    user data 会员人员信息
		 * @return_param    user.id string 会话人员ID
		 * @return_param    user.name string 会话人员名称
		 * @return_param    user.avatar string 会话人员头像
		 * @return_param    user.thumb_avatar string 会话人员头像缩略图
		 * @return_param    user.corp_name string 会话人员企业名称
		 * @return_param    chat_id string 会话群组ID
		 * @return_param    roomid string 会话群组标识
		 * @return_param    chat string 会话群名称
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/7/23 21:06
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetMsgList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$userId = \Yii::$app->request->post('user_id', '');
				if (empty($userId)) {
					throw new InvalidParameterException('缺少必要参数');
				}
				$workUser = WorkUser::findOne($userId);
				if (empty($workUser)) {
					throw new InvalidParameterException('参数不正确');
				}

				// 0：内部，1：外部；2：外部群聊；3：内部群聊
				$type     = \Yii::$app->request->post('type', 0);
				$listInfo = [];
				switch ($type) {
					case 0:
						$sql      = 'SELECT
	`info`.`id`,
	`info`.`msgtime`,
	`info`.`content`,
	( CASE `info`.`user_id` WHEN ' . $userId . ' THEN `info`.`to_user_id` ELSE `info`.`user_id` END ) AS chatUserId
FROM
	{{%work_msg_audit_info}} AS `info`
	RIGHT JOIN (
	SELECT
		`audit_id`,
		max( `msgtime` ) AS msgTime,
		( CASE `user_id` WHEN ' . $userId . ' THEN `to_user_id` ELSE `user_id` END ) AS chatUserId 
	FROM
		{{%work_msg_audit_info}}
	WHERE
		( ( `from_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `to_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) ) 
		AND ( ( `user_id` = ' . $userId . ' ) OR ( `to_user_id` = ' . $userId . ' ) ) 
	GROUP BY
		`chatUserId` 
	) AS `sd` ON `info`.`msgtime` = `sd`.`msgTime` 
	AND `info`.`audit_id` = `sd`.`audit_id`  
	WHERE
		( ( `info`.`from_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `info`.`to_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) ) 
		AND ( ( `info`.`user_id` = ' . $userId . ' ) OR ( `info`.`to_user_id` = ' . $userId . ' ) ) 
ORDER BY
	`info`.`msgtime` DESC';
						$listInfo = WorkMsgAuditInfo::findBySql($sql)->asArray()->all();
						if (!empty($listInfo)) {
							$userIdData = array_column($listInfo, 'chatUserId');
							$workUsers  = WorkUser::find()
								->select('`id`, `name`, `avatar`, `thumb_avatar`')
								->where(['id' => $userIdData])
								->asArray()
								->all();
							if (!empty($workUsers)) {
								$workUsers = array_column($workUsers, NULL, 'id');

								foreach ($listInfo as $key => $info) {
									$info['user'] = !empty($workUsers[$info['chatUserId']]) ? $workUsers[$info['chatUserId']] : [];
									if (!empty($info['content'])) {
										$info['content'] = rawurldecode($info['content']);
									}
									$listInfo[$key] = $info;
								}
							}
						}

						break;
					case 1:
						$sql      = 'SELECT
	`info`.`id`,
	`info`.`msgtime`,
	`info`.`content`,
	( CASE `info`.`user_id` WHEN ' . $userId . ' THEN `info`.`to_external_id` ELSE `info`.`external_id` END ) AS chatUserId
FROM
	{{%work_msg_audit_info}} AS `info`
	RIGHT JOIN (
	SELECT
		`audit_id`,
		max( `msgtime` ) AS msgTime,
		( CASE `user_id` WHEN ' . $userId . ' THEN `to_external_id` ELSE `external_id` END ) AS chatUserId 
	FROM
		{{%work_msg_audit_info}} 
	WHERE
		( ( `from_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `to_type` = ' . WorkMsgAuditInfo::IS_EXTERNAL_USER . ' ) AND ( `user_id` = ' . $userId . ' ) ) 
		OR ( ( `from_type` = ' . WorkMsgAuditInfo::IS_EXTERNAL_USER . ' ) AND ( `to_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `to_user_id` = ' . $userId . ' ) ) 
	GROUP BY
		`chatUserId` 
	) AS `sd` ON `info`.`msgtime` = `sd`.`msgTime` 
	AND `info`.`audit_id` = `sd`.`audit_id`
WHERE
		( ( `info`.`from_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `info`.`to_type` = ' . WorkMsgAuditInfo::IS_EXTERNAL_USER . ' ) AND ( `info`.`user_id` = ' . $userId . ' ) ) 
		OR ( ( `info`.`from_type` = ' . WorkMsgAuditInfo::IS_EXTERNAL_USER . ' ) AND ( `info`.`to_type` = ' . WorkMsgAuditInfo::IS_WORK_USER . ' ) AND ( `info`.`to_user_id` = ' . $userId . ' ) ) 
ORDER BY
	`info`.`msgtime` DESC';
						$listInfo = WorkMsgAuditInfo::findBySql($sql)->asArray()->all();
						if (!empty($listInfo)) {
							$userIdData          = array_column($listInfo, 'chatUserId');
							$workExternalContact = WorkExternalContact::find()
								->select('`id`, `name`, `avatar`, `corp_name`')
								->where(['id' => $userIdData])
								->asArray()
								->all();
							if (!empty($workExternalContact)) {
								$workExternalContact = array_column($workExternalContact, NULL, 'id');

								foreach ($listInfo as $key => $info) {
									if (!empty($workExternalContact[$info['chatUserId']]) && !empty($workExternalContact[$info['chatUserId']]['name'])) {
										$workExternalContact[$info['chatUserId']]['name'] = urldecode($workExternalContact[$info['chatUserId']]['name']);
									}

									$info['user'] = !empty($workExternalContact[$info['chatUserId']]) ? $workExternalContact[$info['chatUserId']] : [];
									if (!empty($info['content'])) {
										$info['content'] = rawurldecode($info['content']);
									}
									$listInfo[$key] = $info;
								}
							}

							$unsetKey = [];
							foreach ($listInfo as $key => $info) {
								if (empty($info['chatUserId']) || empty($info['user'])) {
									array_push($unsetKey, $key);
								}
							}

							if (!empty($unsetKey)) {
								foreach ($unsetKey as $key) {
									unset($listInfo[$key]);
								}

								$listInfo = array_values($listInfo);
							}
						}

						break;
					case 2:
						$sql      = <<<SQL
SELECT
	`info`.`id`,
	`info`.`msgtime`,
	`info`.`content`,
	`info`.`chat_id`,
	`info`.`roomid` 
FROM
	{{%work_msg_audit_info}} AS `info`
	RIGHT JOIN (
	SELECT
		max( `wmai`.`msgtime` ) AS msgTime,
		`wmai`.`chat_id`
	FROM
		{{%work_msg_audit_info}} AS `wmai`
		INNER JOIN {{%work_chat_info}} AS `wci` ON `wci`.`chat_id` = `wmai`.`chat_id` 
	WHERE
		`wci`.`user_id` = $userId 
	GROUP BY
		`wmai`.`chat_id` 
	) AS `sd` ON `sd`.`chat_id` = `info`.`chat_id`
	AND `sd`.`msgTime` = `info`.`msgtime` 
ORDER BY
	`info`.`msgtime` DESC
SQL;
						$listInfo = WorkMsgAuditInfo::findBySql($sql)->asArray()->all();
						if (!empty($listInfo)) {
							$chatIdData = array_column($listInfo, 'chat_id');
							$chatData   = WorkChat::find()
								->select('`id`, `name`')
								->where(['id' => $chatIdData])
								->asArray()
								->all();
							if (!empty($chatData)) {
								$chatData = array_column($chatData, 'name', 'id');

								foreach ($listInfo as $key => $info) {

									$info['chat'] = !empty($chatData[$info['chat_id']]) ? $chatData[$info['chat_id']] : '';
									if (!empty($info['content'])) {
										$info['content'] = rawurldecode($info['content']);
									}
									$info['avatarData'] = WorkChat::getChatAvatar($info['chat_id']);
									$listInfo[$key] = $info;
								}
							}

						}

						break;
					default:
						break;
				}

				return $listInfo;
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-msg-audit/
		 * @title           获取聊天记录
		 * @description     获取聊天记录
		 * @method   POST
		 * @url  http://{host_name}/api/work-msg-audit/get-msg-content
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param from_id 必选 int 发送者ID
		 * @param to_id 可选 int 接收者ID（type不为2是必填）
		 * @param last_time 可选 string 最后一条消息的时间
		 * @param msg_size 可选 int 获取的消息数（默认15条）
		 * @param type 可选 int 会话类型0：内部；1：外部；2：群聊。默认0
		 *
		 * @return          {"error":0,"data":[{"msgid":"1136723759088_1593777","action":"send","from_type":1,"to_type":1,"user_id":13,"external_id":null,"to_user_id":53,"to_external_id":null,"chat_id":null,"roomid":null,"content":"[OK]","msgtype":"text","msgtime":"1593682776981","from_info":{"name":"丁","mobile":"1373305","gender":"2","email":"","avatar":"https://wework.qpic.cn/bizmail/vjbCaYw/0","thumb_avatar":"https://wework.qpic.cn/bizmail/vjbCMKWriaYw/100"},"to_info":{"name":"尧","mobile":"1530648","gender":"0","email":"","avatar":"https://wework.qpic.cn/wwhead/duc2Tvtp8lM/0","thumb_avatar":"https://wework.qpic.cn/wwhead/duc2tp8lM/100"},"info":{"content":"[OK]"}},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/7/25 15:03
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetMsgContent ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException("未配置会话存档功能");
				}

				$type   = \Yii::$app->request->post('type', 0);// 0：内部，1：外部；2：外部群聊；3：内部群聊
				$chatId = \Yii::$app->request->post('to_id', 0);
				if ($type != 2 && empty($chatId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				$userId = \Yii::$app->request->post('from_id', '');
				if (empty($userId)) {
					throw new InvalidParameterException('缺少必要参数');
				}

				if ($type != 2) {
					$workUser = WorkUser::findOne($userId);
					if (empty($workUser)) {
						throw new InvalidParameterException('参数不正确');
					}
				} else {
					$chatRoom = WorkChat::findOne($userId);
					if (empty($chatRoom)) {
						throw new InvalidParameterException('参数不正确');
					}
				}

				$lastTime   = \Yii::$app->request->post('last_time') ?: 0;
				$msgSize    = \Yii::$app->request->post('msg_size') ?: 15;
				$msgType    = \Yii::$app->request->post('msg_type') ?: '';
				$searchName = \Yii::$app->request->post('search_name') ?: '';
				$startDate  = \Yii::$app->request->post('start_date') ?: '';
				$endDate    = \Yii::$app->request->post('end_date') ?: '';

				$otherData                = [];
				$otherData['msg_type']    = $msgType;
				$otherData['search_name'] = $searchName;
				$otherData['start_date']  = $startDate;
				$otherData['end_date']    = $endDate;

				return WorkMsgAuditInfo::getMsgContent($userId, $chatId, $lastTime, $type, $msgSize, $otherData);
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}
	}