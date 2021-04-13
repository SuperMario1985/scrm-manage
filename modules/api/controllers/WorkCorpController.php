<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/1/8
	 * Time: 10:01
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Package;
	use app\models\User;
	use app\models\WorkCorpAgent;
	use app\models\WorkCorpAuth;
	use app\models\WorkCorpBind;
	use app\models\WorkTagGroup;
	use app\models\SubUserAuthority;
	use app\models\UserCorpRelation;
	use app\models\WorkCorp;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkExternalContactJob;
	use yii\web\MethodNotAllowedHttpException;

	class WorkCorpController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-corp/
		 * @title           企业微信列表
		 * @description     企业微信列表
		 * @method   post
		 * @url  http://{host_name}/api/work-corp/list
		 *
		 * @param uid 必选 int 登录账号
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 页数
		 * @param sub_id 可选 int 当前员工登录的id
		 * @param corp_name 可选 string 企业微信名称
		 * @param corp_type 可选 int 1认证号2注册号
		 *
		 * @return          {"error":0,"data":{"count":"5","info":[{"book_secret":"3GyCiwlIC3-qO5Lp842k7-Ewndep_VNHnIQqorUfBm4","book_status":1,"book_url":"https://test-hd-scrm.wemero.cn/work/event/index/1","external_secret":"gpPLfmT5QMJfHZrEjzCDVPWNKdWK8_yINQR_t6GLQWs","external_status":1,"external_url":"https://test-hd-scrm.wemero.cn/work/event/index/1","bind_time":"2020-02-29 17:24:51","suite_secret":"jaWrq_5kPAFqvO-UVsQUmaN08_oki-dhp4vBAObDlLk|aPCaOItGDiFOpK9naT7samO6vaithfjX8e7SmIbX3lw","auth_type":"change_auth|cancel_auth","key":1,"id":1,"corpid":"ww93caebeee67d134b","corp_type":"verified","logo":"https://p.qlogo.cn/bizmail/wdDeUj8flAcyZoKcj49hQHnibWQ3AL9he0nAnV4EibJL1bxLibGBXfxlQ/0","corp_name":"小猪科技公司","welcome":1,"verified_end_time":"2020-07-27 10:20:52","industry":"IT服务|互联网和相关服务"},{"loop":"……"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    canAdd int 是否可添加企业微信1是否
		 * @return_param    key int key
		 * @return_param    id int 唯一id
		 * @return_param    corpid string 企业唯一标志
		 * @return_param    corp_type string 认证号：verified注册号：unverified
		 * @return_param    logo string 企业logo
		 * @return_param    corp_name string 企业简称
		 * @return_param    corp_full_name string 企业全称（认证过才有）
		 * @return_param    verified_end_time string 授权时间
		 * @return_param    industry string 行业
		 * @return_param    auth_type string 授权状态：cancel_auth是取消授权，change_auth是更新授权，create_auth是授权成功通知
		 * @return_param    isAudit int 是否开通会话存档1是0否
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 11:39
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty(\Yii::$app->request->post('uid'))) {
					throw new InvalidParameterException('参数不正确！');
				}
				$sub_id          = \Yii::$app->request->post('sub_id');
				$uid             = \Yii::$app->request->post('uid'); //登录账号id
				$page            = \Yii::$app->request->post('page') ?: 1;
				$pageSize        = \Yii::$app->request->post('page_size') ?: 15;
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;//1主账户 2子账户
				$corp_name       = \Yii::$app->request->post('corp_name'); //企业名称
				$corp_type       = \Yii::$app->request->post('corp_type'); //1认证 2注册
				$offset          = ($page - 1) * $pageSize;
				if ($isMasterAccount == 1) {
					$sub_id = 0;
				}
				$workCorpData = WorkCorp::find()->alias('wc');
				$workCorpData = $workCorpData->leftJoin('{{%user_corp_relation}} uc', '`wc`.`id` = `uc`.`corp_id`');
				$workCorpData = $workCorpData->andWhere(['uc.uid' => $uid]);
				if (!empty($sub_id)) {
					$sub_auth = SubUserAuthority::find()->andWhere(['sub_user_id' => $sub_id, 'type' => 2])->andWhere(['<>', 'authority_ids', ''])->asArray()->all();
					if (empty($sub_auth)) {
						return [
							'count'       => 0,
							'info'        => [],
							'notBindList' => [],
						];
					} else {
						$wx_account_id = array_column($sub_auth, 'wx_id');
						$workCorpData  = $workCorpData->andWhere(['in', 'wc.id', $wx_account_id]);
					}
				}
				if (!empty($corp_name)) {
					$workCorpData = $workCorpData->andWhere(['or', ['like', 'corp_full_name', $corp_name], ['like', 'corp_name', $corp_name]]);
				}
				if (!empty($corp_type)) {
					if ($corp_type == 1) {
						$workCorpData = $workCorpData->andWhere(['wc.corp_type' => 'verified']);
					} elseif ($corp_type == 2) {
						$workCorpData = $workCorpData->andWhere(['wc.corp_type' => 'unverified']);
					}
				}
				$count        = $workCorpData->count();
				$workCorpInfo = $workCorpData->limit($pageSize)->offset($offset)->orderBy(['wc.create_time' => SORT_ASC])->all();
				$notBindList  = [];//未绑定的企业微信
				$result       = [];
				if (!empty($workCorpInfo)) {
					/**
					 * @var int      $key
					 * @var WorkCorp $corp
					 */
					foreach ($workCorpInfo as $key => $corp) {
						if (empty($corp->workCorpBind)) {
							$notBindList[]                   = [
								'corp_id'               => $corp->id,
								'corpid'                => $corp->corpid,
								'corp_name'             => $corp->corp_name,
								'corp_token'            => '',
								'corp_encoding_AES_key' => ''
							];
							$result[$key]['book_secret']     = '';
							$result[$key]['book_status']     = 0;
							$result[$key]['book_url']        = '';
							$result[$key]['external_secret'] = '';
							$result[$key]['external_status'] = 0;
							$result[$key]['external_url']    = '';
							$result[$key]['bind_time']       = '';
							$result[$key]['is_supply']       = 1;
						} else {
							$result[$key]['corp_token']            = $corp->workCorpBind->token;
							$result[$key]['corp_encoding_AES_key'] = $corp->workCorpBind->encode_aes_key;
							$result[$key]['book_secret']           = $corp->workCorpBind->book_secret;
							$result[$key]['book_status']           = $corp->workCorpBind->book_status;
							$result[$key]['book_url']              = \Yii::$app->params['site_url'] . '/work/event/index/' . $corp->id;
							$result[$key]['external_secret']       = $corp->workCorpBind->external_secret;
							$result[$key]['external_status']       = $corp->workCorpBind->external_status;
							$result[$key]['external_url']          = \Yii::$app->params['site_url'] . '/work/event/index/' . $corp->id;
							$result[$key]['bind_time']             = $corp->workCorpBind->create_time;
							$result[$key]['is_supply']             = empty($corp->workCorpBind->external_secret) ? 1 : 0;
						}
						if (!empty($corp->workCorpAuths)) {
							$suiteSecret = [];
							$authType    = [];
							foreach ($corp->workCorpAuths as $workCorpAuth) {
								array_push($suiteSecret, $workCorpAuth->suite->suite_secret);
								array_push($authType, $workCorpAuth->auth_type);
							}

							$result[$key]['suite_secret'] = implode('|', $suiteSecret);
							$result[$key]['auth_type']    = implode('|', $authType);
						} else {
							$result[$key]['suite_secret'] = '';
							$result[$key]['auth_type']    = '';
						}
						$result[$key]['key']               = $corp->id;
						$result[$key]['id']                = $corp->id;
						$result[$key]['corpid']            = $corp->corpid;
						$result[$key]['corp_type']         = $corp->corp_type;
						$result[$key]['logo']              = $corp->corp_square_logo_url;
						$result[$key]['corp_name']         = $corp->corp_name;
						$result[$key]['welcome']           = WorkWelcome::getCorpWelcome($corp->id);
						$result[$key]['verified_end_time'] = !empty($corp->verified_end_time) ? date('Y-m-d H:i:s', $corp->verified_end_time) : '';
						$industry                          = '';
						if (!empty($corp->corp_industry)) {
							$industry .= $corp->corp_industry . '|';
						}
						if (!empty($corp->corp_sub_industry)) {
							$industry .= $corp->corp_sub_industry . '|';
						}
						$industry                 = rtrim($industry, '|');
						$result[$key]['industry'] = $industry;

						//是否开通会话存档
						$result[$key]['isAudit'] = !empty($corp->workMsgAudit) && ($corp->workMsgAudit->status == 1) ? 1 : 0;

						$tagName = WorkTagGroup::findOne(['type' => 0, 'corp_id' => $corp->id, 'group_name' => "未分组"]);
						if (empty($tagName)) {
							WorkTagGroup::add(0, $corp->id, '未分组', 0, ['未分组'], true);
						}

						$tagName = WorkTagGroup::findOne(['type' => 1, 'corp_id' => $corp->id, 'group_name' => "未分组"]);
						if (empty($tagName)) {
							WorkTagGroup::add(0, $corp->id, '未分组', 1, ['未分组'], true);
						}

						$agentCount = WorkCorpAgent::find()
							->where(['corp_id' => $corp->id, 'agent_type' => WorkCorpAgent::CUSTOM_AGENT, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'is_del' => WorkCorpAgent::AGENT_NO_DEL])
							->count();

						$result[$key]['agent_count'] = $agentCount;
					}
				}

				//添加企业微信数量限制
				$canAdd    = 1;//是否可添加企业微信
				$user      = User::findOne($uid);
				$corpCount = UserCorpRelation::find()->where(['uid' => $uid])->count();
				if ($user->limit_corp_num > 0) {
					if ($corpCount >= $user->limit_corp_num) {
						$canAdd = 0;
					}
				} else {
					if ($corpCount >= \Yii::$app->params['default_corp_num']) {
						$canAdd = 0;//默认1个
					}
				}

				return [
					'count'       => $count,
					'canAdd'      => $canAdd,
					'info'        => $result,
					'notBindList' => $notBindList,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-corp/
		 * @title           企业微信绑定
		 * @description     企业微信绑定
		 * @method   post
		 * @url  http://{host_name}/api/work-corp/bind
		 *
		 * @param uid 必选 int 登录账号
		 * @param is_edit 必选 int 是否是编辑
		 * @param is_select 必选 int 是否是选择已经企业微信
		 * @param corp_name 必选 string 企业微信名称
		 * @param corpid 必选 string 企业微信ID
		 * @param book_secret 必选 string 通讯录管理secret
		 * @param external_secret 必选 string 外部联系人管理secret
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-02-04 11:30
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionBind ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$bindInfo        = \Yii::$app->request->post();
			$uid             = \Yii::$app->request->post('uid', 0);
			$is_edit         = \Yii::$app->request->post('is_edit', 0);
			$is_select       = \Yii::$app->request->post('is_select', 0);
			$corp_name       = \Yii::$app->request->post('corp_name', '');
			$corpid          = \Yii::$app->request->post('corpid', '');
			$book_secret     = \Yii::$app->request->post('book_secret', '');
			$external_secret = \Yii::$app->request->post('external_secret', '');
			$corp_name       = trim($corp_name);
			$corpid          = trim($corpid);
			$book_secret     = trim($book_secret);
			$external_secret = trim($external_secret);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			if (empty($corp_name)) {
				throw new InvalidDataException('请填写企业名称！');
			}
			if (empty($corpid)) {
				throw new InvalidDataException('请填写企业ID！');
			}
			if (empty($book_secret)) {
				throw new InvalidDataException('请填写通讯录管理secret！');
			}
			if (empty($external_secret)) {
				throw new InvalidDataException('请填写外部联系人管理secret！');
			}
			if (empty($is_edit) && empty($is_select)) {
				$workCorp = WorkCorp::findOne(['corpid' => $corpid]);
				if (!empty($workCorp)) {
					throw new InvalidDataException('该企业ID已存在，请重新填写！');
				}
				//最多只能绑定5个
				$count = UserCorpRelation::find()->where(['uid' => $uid])->count();
				if ($count >= 5) {
					throw new InvalidDataException('最多只能申请5个！');
				}
				//套餐限定
				$packageLimit = Package::packageLimitNum($uid, 'wechat_num');
				if ($packageLimit && $count >= $packageLimit) {
					throw new InvalidDataException('企业微信已达到套餐限制数量！');
				}
			}

			try {
				$corpId = WorkCorp::bindCorp($bindInfo, $uid);
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-corp/
		 * @title           更新授权
		 * @description     更新授权
		 * @method   post
		 * @url  http://{host_name}/api/work-corp/refresh-corp
		 *
		 * @param id 必选 string 企业微信ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-12-15 9:30
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionRefreshCorp ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$id = \Yii::$app->request->post('id', '');
			if (empty($id)) {
				throw new InvalidDataException('参数不正确！');
			}
			$workCorp = WorkCorp::findOne($id);
			if (empty($workCorp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpAuthInfo = WorkCorpAuth::findOne(['corp_id' => $workCorp->id, 'auth_type' => ['change_auth', 'create_auth']]);
			if (empty($corpAuthInfo)) {
				throw new InvalidDataException('无可用授权关系！');
			}
			$corpAuthInfo->refreshCorp();

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-corp/
		 * @title           补充外部联系人管理secret
		 * @description     补充外部联系人管理secret
		 * @method   post
		 * @url  http://{host_name}/api/work-corp/supply-data
		 *
		 * @param corp_id          必选 string 企业微信id
		 * @param external_secret  必选 string 外部联系人管理secret
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-11-09 19:14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSupplyData ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$externalSecret = \Yii::$app->request->post('external_secret', '');
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$externalSecret = trim($externalSecret);
			if (empty($externalSecret)) {
				throw new InvalidDataException('请填写外部联系人管理secret！');
			}
			$bookSecret = '';
			$corpBind   = WorkCorpBind::findOne(['corp_id' => $this->corp->id]);
			if (!empty($corpBind)) {
				$bookSecret = $corpBind->book_secret;
			}

			$bindInfo                    = [];
			$bindInfo['corpid']          = $this->corp->corpid;
			$bindInfo['corp_name']       = $this->corp->corp_name;
			$bindInfo['book_secret']     = $bookSecret;
			$bindInfo['external_secret'] = $externalSecret;
			$bindInfo['external_status'] = 1;
			try {
				$corpId = WorkCorp::bindCorp($bindInfo);
				if (!empty($this->corp->workExternalContacts)) {
					\Yii::$app->work->push(new SyncWorkExternalContactJob([
						'corp' => $this->corp,
					]));
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}
	}