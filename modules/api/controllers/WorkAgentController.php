<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/2/21
	 * Time: 13:07
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\WorkCorpAgent;
	use app\models\WorkSuiteConfig;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class WorkAgentController extends WorkBaseController
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
						'list' => ['POST'],
						'add'  => ['POST'],
						'get'  => ['POST'],
						'set'  => ['POST'],
						'info' => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           应用管理
		 * @description     应用管理
		 * @method   POST
		 * @url  http://{host_name}/modules/controller/list
		 *
		 * @param corp_id 选填 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{"auth":{"1":{"id":1,"suite_id":"wx7e8bf2bec44b2db9","name":"云美来智能营销","logo_url":"https://wework.qpic.cn/bizmail/lqbia1VyVHu824rkRUh3gXBIJlODCJnjqphY2YsGMzNkZe82QqD9liaw/0","description":"云美来智能营销","redirect_domain":"test-scrm.wemero.cn","home_url":"http://test-scrm.wemero.cn","has_auth":true}},"normal":{"normal":[],"custom":[{"id":6,"agentid":1000019,"agent_type":2,"agent_use_type":0,"name":"云美来SCRM","round_logo_url":null,"square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf41eHtvxM0oSeRDiaiaICrlp8ib7iaKQKFYHVADvuw/0","description":"云美来SCRM","level":null,"allow_party":"1","allow_user":"","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-m-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":null}]}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    auth array 授权应用集
		 * @return_param    id string 授权ID
		 * @return_param    suite_id string 授权方ID
		 * @return_param    name string 企业应用名称
		 * @return_param    logo_url string 授权应用圆形头像
		 * @return_param    description string 企业应用详情
		 * @return_param    redirect_domain string 企业应用可信域名
		 * @return_param    home_url string 应用主页url
		 * @return_param    has_auth boolean 是否已经授权
		 * @return_param    forbidden_auth boolean 是否允许授权
		 * @return_param    normal array 非授权应用集
		 * @return_param    normal array 基础应用集
		 * @return_param    custom array 自建应用集
		 * @return_param    id int 应用id
		 * @return_param    agentid string 企业应用id
		 * @return_param    agent_type int 应用类型
		 * @return_param    agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    name string 企业应用名称
		 * @return_param    round_logo_url string 授权方应用圆形头像
		 * @return_param    square_logo_url string 企业应用方形头像
		 * @return_param    description string 企业应用详情
		 * @return_param    level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    allow_party string 应用可见范围（部门）
		 * @return_param    allow_user string 应用可见范围（成员）
		 * @return_param    allow_tag string 应用可见范围（标签）
		 * @return_param    extra_party string 额外通讯录（部门）
		 * @return_param    extra_user string 额外通讯录（成员）
		 * @return_param    extra_tag string 额外通讯录（标签）
		 * @return_param    close int 企业应用是否被停用
		 * @return_param    redirect_domain string 企业应用可信域名
		 * @return_param    report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    home_url string 应用主页url
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/2/22 14:45
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				$result = [
					'auth'   => [],
					'normal' => [
						'normal'           => [],
						'custom'           => [],
						'mini'             => [],
						'token'            => '',
						'encoding_AES_key' => '',
						'has_moneyPay'     => '0'
					],
				];

				$suiteConfigs = WorkSuiteConfig::find()->all();
				if (!empty($suiteConfigs)) {
					/** @var WorkSuiteConfig $suiteConfig */
					foreach ($suiteConfigs as $suiteConfig) {
						if ($suiteConfig->status == WorkSuiteConfig::SUITE_NORMAL) {
							$suiteConfigInfo                   = $suiteConfig->dumpData();
							$suiteConfigInfo['has_auth']       = false;
							$suiteConfigInfo['forbidden_auth'] = $suiteConfig->id == 2 && !empty($this->corp->workCorpBind->book_secret) ? true : false;
							$result['auth'][$suiteConfig->id]  = $suiteConfigInfo;
						}
					}
				}

				if (!empty($this->corp) && !empty($this->corp->workCorpAgents)) {
					foreach ($this->corp->workCorpAgents as $workCorpAgent) {
						if ($workCorpAgent->close == WorkCorpAgent::AGENT_NOT_CLOSE && $workCorpAgent->is_del == WorkCorpAgent::AGENT_NO_DEL) {
							switch ($workCorpAgent->agent_type) {
								case WorkCorpAgent::NORMAL_AGENT:
									array_push($result['normal']['normal'], $workCorpAgent->dumpData());

									if ($workCorpAgent->basic_agent_type == 'moneyPay') {
										$result['normal']['has_moneyPay'] = 1;//是否有企业支付应用
									}
									break;
								case WorkCorpAgent::CUSTOM_AGENT:
									array_push($result['normal']['custom'], $workCorpAgent->dumpData());

									break;
								case WorkCorpAgent::AUTH_AGENT:
									$result['auth'][$workCorpAgent->suite_id]['has_auth'] = true;

									break;
								case WorkCorpAgent::MINIAPP_AGENT:
									array_push($result['normal']['mini'], $workCorpAgent->dumpData());

									break;
							}
						}
					}
				}

				if (!empty($this->corp->workCorpBind)) {
					$result['normal']['token']            = $this->corp->workCorpBind->token;
					$result['normal']['encoding_AES_key'] = $this->corp->workCorpBind->encode_aes_key;
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           添加应用
		 * @description     添加应用
		 * @method   POST
		 * @url  http://{host_name}/api/work-agent/add
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param agent_id 必选 int 企业微信应用ID
		 * @param agent_secret 必选 int 应用secret
		 * @param agent_type 可选 int 应用类型：1：基础；2：自建（默认）
		 * @param basic_agent_type 可选 int 基础应用类型：moneyPay企业支付
		 * @param agent_use_type 可选 int 应用类型：0：基础（默认）；1：侧边栏
		 * @param agent_is_money 可选 int 是否红包应用1是0否
		 *
		 * @return          {"error":0,"data":{"agent_id":7}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    agent_id int 应用ID
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/2/22 13:34
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId        = \Yii::$app->request->post('agent_id');
				$agentSecret    = \Yii::$app->request->post('agent_secret');
				$agentType      = \Yii::$app->request->post('agent_type');
				$basicAgentType = \Yii::$app->request->post('basic_agent_type', '');
				$agentUseType   = \Yii::$app->request->post('agent_use_type');
				$agentIsMoney   = \Yii::$app->request->post('agent_is_money', 0);

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentSecret)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$agentInfo = [
					'agentid'          => trim($agentId),
					'secret'           => trim($agentSecret),
					'agent_type'       => !empty($agentType) ? $agentType : WorkCorpAgent::CUSTOM_AGENT,
					'agent_use_type'   => !empty($agentUseType) ? $agentUseType : WorkCorpAgent::PUB_AGENT,
					'basic_agent_type' => $basicAgentType,
					'agent_is_money'   => $agentIsMoney,
					'close'            => WorkCorpAgent::AGENT_NOT_CLOSE,
				];

				try {
					$corpAgentId = WorkCorpAgent::setCorpAgent($this->corp->id, $agentInfo);
				} catch (InvalidDataException $e) {
					throw new InvalidParameterException($e->getMessage());
				}

				return [
					'agent_id' => $corpAgentId
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           获取应用详细
		 * @description     获取应用详细
		 * @method   POST
		 * @url  http://{host_name}/api/work-agent/get
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param agent_id 必选 int 应用ID
		 * @param is_tip 可选 int 停用是否提示
		 *
		 * @return          {"error":0,"data":{"id":6,"agentid":1000019,"agent_type":2,"agent_use_type":0,"name":"云美来SCRM","round_logo_url":null,"square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf41eHtvxM0oSeRDiaiaICrlp8ib7iaKQKFYHVADvuw/0","description":"云美来SCRM","level":null,"allow_party":"1","allow_user":"","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-m-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":null}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 应用id
		 * @return_param    agentid string 企业应用id
		 * @return_param    agent_type int 应用类型
		 * @return_param    agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    name string 企业应用名称
		 * @return_param    round_logo_url string 授权方应用圆形头像
		 * @return_param    square_logo_url string 企业应用方形头像
		 * @return_param    description string 企业应用详情
		 * @return_param    level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    allow_party string 应用可见范围（部门）
		 * @return_param    allow_user string 应用可见范围（成员）
		 * @return_param    allow_tag string 应用可见范围（标签）
		 * @return_param    extra_party string 额外通讯录（部门）
		 * @return_param    extra_user string 额外通讯录（成员）
		 * @return_param    extra_tag string 额外通讯录（标签）
		 * @return_param    close int 企业应用是否被停用
		 * @return_param    redirect_domain string 企业应用可信域名
		 * @return_param    report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    home_url string 应用主页url
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/2/22 12:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGet ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId = \Yii::$app->request->post('agent_id');
				$ignore  = \Yii::$app->request->post('ignore', false);
				$isTip   = \Yii::$app->request->post('is_tip', 0);

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				return WorkCorpAgent::getAgent($this->corp->id, $agentId, $ignore, $isTip);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           同步全部应用
		 * @description     同步全部应用
		 * @method   post
		 * @url  http://{host_name}/api/work-agent/sync-agent
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/10/13 13:26
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSyncAgent ()
		{
			if (\Yii::$app->request->isPost) {

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::find()->where(['corp_id' => $this->corp->id, 'is_del' => 0, 'agent_type' => 2])->all();
				if (!empty($corpAgent)) {
					/** @var WorkCorpAgent $agent */
					foreach ($corpAgent as $agent) {
						WorkCorpAgent::getAgent($this->corp->id, $agent->id, false);
					}
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           修改应用
		 * @description     修改应用
		 * @method   POST
		 * @url  http://{host_name}/api/work-agent/set
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param agent_id 必选 int 应用ID
		 * @param name 可选 string 企业应用名称
		 * @param logo_mediaid 可选 string 企业应用头像的mediaid，通过素材管理接口上传图片获得mediaid，上传后会自动裁剪成方形和圆形两个头像
		 * @param description 可选 string 企业应用详情
		 * @param redirect_domain 可选 string 企业应用可信域名
		 * @param report_location_flag 可选 int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @param isreportenter 可选 int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @param home_url 可选 string 应用主页url
		 * @param agent_use_type 可选 int 应用类型：0：基础（默认）；1：侧边栏
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/2/24 16:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionSet ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId = \Yii::$app->request->post('agent_id');

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::findOne($agentId);
				if (empty($corpAgent) || $corpAgent->corp_id != $this->corp->id || $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT) {
					throw new InvalidParameterException('参数不正确！');
				}

				$agentData = [];

				if (!empty(\Yii::$app->request->post('name'))) {
					if (!empty(\Yii::$app->params['work_agent']) && !empty(\Yii::$app->params['work_agent']['must_begin_with']) && strpos(\Yii::$app->request->post('name'), \Yii::$app->params['work_agent']['must_begin_with']) !== 0) {
						throw new InvalidDataException("应用名称必须以 " . \Yii::$app->params['work_agent']['must_begin_with'] . " 开头");
					}

					$agentData['name'] = \Yii::$app->request->post('name');
				}

				if (!empty(\Yii::$app->request->post('logo_mediaid'))) {
					$agentData['logo_mediaid'] = \Yii::$app->request->post('logo_mediaid');
				}

				if (!empty(\Yii::$app->request->post('description'))) {
					$agentData['description'] = \Yii::$app->request->post('description');
				}

				if (!empty(\Yii::$app->request->post('redirect_domain'))) {
					$agentData['redirect_domain'] = \Yii::$app->request->post('redirect_domain');
				}

				if (!is_null(\Yii::$app->request->post('report_location_flag'))) {
					$agentData['report_location_flag'] = \Yii::$app->request->post('report_location_flag');
				}

				if (!is_null(\Yii::$app->request->post('isreportenter'))) {
					$agentData['isreportenter'] = \Yii::$app->request->post('isreportenter');
				}

				if (!empty(\Yii::$app->request->post('home_url'))) {
					$agentData['home_url'] = \Yii::$app->request->post('home_url');
				}

				$changeUserType = false;
				if (!is_null(\Yii::$app->request->post('agent_use_type'))) {
					$corpAgent->agent_use_type = \Yii::$app->request->post('agent_use_type');
					$corpAgent->update();
					$changeUserType = true;
				}

				if (!empty($agentData)) {
					$agentData['agentid'] = $corpAgent->agentid;

					try {
						WorkCorpAgent::setAgent($this->corp->id, $agentId, $agentData);

						return true;
					} catch (InvalidDataException $e) {
						throw new InvalidParameterException($e->getMessage());
					}
				} else {
					if ($changeUserType) {
						return true;
					} else {
						throw new InvalidParameterException('修改数据有误！');
					}
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           获取应用详情
		 * @description     获取应用详情
		 * @method   请求方式
		 * @url  http://{host_name}/api/work-agent/info
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param agent_id 必选 int 应用ID
		 *
		 * @return          {"error":0,"data":{"id":6,"agentid":1000016,"agent_type":3,"agent_use_type":0,"name":"云美来智能营销","round_logo_url":null,"square_logo_url":"https://wework.qpic.cn/bizmail/lqbia1VyVHu824rkRUh3gXBIJlODCJnjqphY2YsGMzNkZe82QqD9liaw/0","description":"云美来智能营销","level":null,"allow_party":"6,2,3","allow_user":"dove_chen","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":"http://test-scrm.wemero.cn"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 应用id
		 * @return_param    agentid string 企业应用id
		 * @return_param    agent_type int 应用类型
		 * @return_param    agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    name string 企业应用名称
		 * @return_param    round_logo_url string 授权方应用圆形头像
		 * @return_param    square_logo_url string 企业应用方形头像
		 * @return_param    description string 企业应用详情
		 * @return_param    level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    allow_party string 应用可见范围（部门）
		 * @return_param    allow_user string 应用可见范围（成员）
		 * @return_param    allow_tag string 应用可见范围（标签）
		 * @return_param    extra_party string 额外通讯录（部门）
		 * @return_param    extra_user string 额外通讯录（成员）
		 * @return_param    extra_tag string 额外通讯录（标签）
		 * @return_param    close int 企业应用是否被停用
		 * @return_param    redirect_domain string 企业应用可信域名
		 * @return_param    report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    home_url string 应用主页url
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/2/24 17:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId = \Yii::$app->request->post('agent_id');

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::findOne($agentId);
				if (empty($corpAgent) || $corpAgent->corp_id != $this->corp->id) {
					throw new InvalidParameterException('参数不正确！');
				}

				return $corpAgent->dumpData();
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           侧边栏应用
		 * @description     获取侧边栏应用
		 * @method   POST
		 * @url  http://{host_name}/api/work-agent/slider
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return          {"error":0,"data":{"id":5,"agentid":1000019,"agent_type":2,"agent_use_type":1,"name":"云美来SCRM","round_logo_url":null,"square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf41eHtvxM0oSeRDiaiaICrlp8ib7iaKQKFYHVADvuw/0","description":"云美来SCRM","level":null,"allow_party":"1","allow_user":"","allow_tag":"","extra_party":"","extra_user":"","extra_tag":"","close":0,"redirect_domain":"test-m-scrm.wemero.cn","report_location_flag":0,"isreportenter":0,"home_url":null}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 应用id
		 * @return_param    agentid string 企业应用id
		 * @return_param    agent_type int 应用类型
		 * @return_param    agent_use_type int 应用使用范围0：通用；1：侧边栏
		 * @return_param    name string 企业应用名称
		 * @return_param    round_logo_url string 授权方应用圆形头像
		 * @return_param    square_logo_url string 企业应用方形头像
		 * @return_param    description string 企业应用详情
		 * @return_param    level int 权限等级。1:通讯录基本信息只读；2:通讯录全部信息只读（已废弃）；3:通讯录全部信息读写；4:单个基本信息只读；5:通讯录全部信息只写（已废弃）
		 * @return_param    allow_party string 应用可见范围（部门）
		 * @return_param    allow_user string 应用可见范围（成员）
		 * @return_param    allow_tag string 应用可见范围（标签）
		 * @return_param    extra_party string 额外通讯录（部门）
		 * @return_param    extra_user string 额外通讯录（成员）
		 * @return_param    extra_tag string 额外通讯录（标签）
		 * @return_param    close int 企业应用是否被停用
		 * @return_param    redirect_domain string 企业应用可信域名
		 * @return_param    report_location_flag int 企业应用是否打开地理位置上报 0：不上报；1：进入会话上报；
		 * @return_param    isreportenter int 是否上报用户进入应用事件。0：不接收；1：接收
		 * @return_param    home_url string 应用主页url
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/3/11 09:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSlider ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$result = [
					'is_have' => 0,
					'custom'  => []
				];

				$workCorpAgent = WorkCorpAgent::findOne(['corp_id' => $this->corp->id, 'agent_use_type' => WorkCorpAgent::SLIDER_AGENT, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'is_del' => WorkCorpAgent::AGENT_NO_DEL]);

				if (!empty($workCorpAgent)) {
					$result            = $workCorpAgent->dumpData();
					$result['is_have'] = 1;
				}
				//获取自建应用
				$workAgent = WorkCorpAgent::find()->where(['corp_id' => $this->corp->id, 'is_del' => WorkCorpAgent::AGENT_NO_DEL]);
				$workAgent = $workAgent->andWhere(['agent_type' => WorkCorpAgent::CUSTOM_AGENT, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE]);
				$workAgent = $workAgent->select('id,agentid,name,description,square_logo_url,redirect_domain,home_url,report_location_flag,isreportenter')->asArray()->all();
				if (!empty($workAgent)) {
					$result['custom'] = $workAgent;
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           更改应用用途
		 * @description     更改应用用途
		 * @method   post
		 * @url  http://{host_name}/api/work-agent/update-agent-use-type
		 *
		 * @param agent_id 必选 string 应用id
		 * @param use_type 可选 string 应用用途：0通用、1侧边栏
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: win7. Date: 2020-04-24 18:11
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionUpdateAgentUseType ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId  = \Yii::$app->request->post('agent_id');
				$use_type = \Yii::$app->request->post('use_type', 1);

				if (empty($this->corp) || empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::findOne($agentId);
				if (empty($corpAgent) || $corpAgent->corp_id != $this->corp->id) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (!empty($use_type)) {
					WorkCorpAgent::updateAll(['agent_use_type' => 0], ['and', ['corp_id' => $this->corp->id, 'agent_use_type' => 1], ['!=', 'id', $corpAgent->id]]);
				}
				$corpAgent->agent_use_type = $use_type;
				$corpAgent->update();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           自建应用关联用途
		 * @description     自建应用关联用途
		 * @method   post
		 * @url  http://{host_name}/api/work-agent/update-agent-use
		 *
		 * @param corp_id  必选 string 企业的唯一ID
		 * @param agent_id 必选 string 应用id
		 * @param agent_is_money 可选 string 1红包应用
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-05-14
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionUpdateAgentUse ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId        = \Yii::$app->request->post('agent_id');
				$agent_is_money = \Yii::$app->request->post('agent_is_money', 0);

				if (empty($this->corp) || empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::findOne($agentId);
				if (empty($corpAgent) || $corpAgent->corp_id != $this->corp->id) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($corpAgent->agent_type != 2) {
					throw new InvalidParameterException('不能关联非自建应用！');
				}

				if ($agent_is_money == 1){
					WorkCorpAgent::updateAll(['agent_is_money' => 0], ['corp_id' => $this->corp->id, 'agent_is_money' => 1]);
				}

				$corpAgent->agent_is_money = $agent_is_money;
				$corpAgent->update();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-agent/
		 * @title           获取应用的允许成员
		 * @description     获取应用的允许成员
		 * @method   POST
		 * @url  http://{host_name}/api/work-agent/get-user
		 *
		 * @param corp_id  必选 string 企业的唯一ID
		 * @param agent_id 必选 string 应用id
		 *
		 * @return          {"error":0,"data":{"allow_party":"2","allow_user":"Koai,doen,109","user_info":{"user_1":{"id":1,"key":1,"corp_id":1,"userid":"doen","name":"尧","department":"1,2","order":"0,0","position":"","mobile":"1530648","gender":"1","email":"","is_leader_in_dept":"0,1","avatar":"http://wework.qpic.cn/bizmail/upwJgIAYaE3SEnJBzsibQ/0","thumb_avatar":"http://wework.qpic.cn/bizmail/upwsibQ/100","telephone":"","enable":1,"alias":"","address":"","extattr":"[]","status":1,"qr_code":"https://open.work.weixin.qq.com/wwopen/userQRCode?vcode=vce892d","is_del":0,"openid":"oojmZmHKT_R0vdo","is_external":"有","apply_num":2,"new_customer":31,"chat_num":58,"message_num":195,"replyed_per":"--","first_reply_time":"--","delete_customer_num":40,"department_name":"科技公司/销售","tag_name":[]},"loop":"……"}}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    allow_party string 应用可见范围（部门）
		 * @return_param    allow_user string 应用可见范围（成员）
		 * @return_param    user_info data 应用成员信息
		 * @return_param    user_info.id int 成员ID
		 * @return_param    user_info.key int 关键件
		 * @return_param    user_info.corp_id int 授权的企业ID
		 * @return_param    user_info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    user_info.name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    user_info.department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    user_info.order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    user_info.position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    user_info.mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    user_info.gender int 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    user_info.email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    user_info.is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    user_info.avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    user_info.thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    user_info.telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    user_info.enable string 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    user_info.alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    user_info.address string 地址
		 * @return_param    user_info.extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    user_info.status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    user_info.qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    user_info.is_del int 0：未删除；1：已删除
		 * @return_param    user_info.openid string 成员openid
		 * @return_param    user_info.is_external string 是否有外部联系人权限
		 * @return_param    user_info.apply_num int 发起申请数
		 * @return_param    user_info.new_customer int 新增客户数
		 * @return_param    user_info.chat_num int 聊天数
		 * @return_param    user_info.message_num int 发送消息数
		 * @return_param    user_info.replyed_per string 已回复聊天占比
		 * @return_param    user_info.first_reply_time string 平均首次回复时长
		 * @return_param    user_info.delete_customer_num int 拉黑客户数
		 * @return_param    user_info.department_name string 部门名称
		 * @return_param    user_info.tag_name array 标签组
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/6/22 12:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetUser ()
		{
			if (\Yii::$app->request->isPost) {
				$agentId = \Yii::$app->request->post('agent_id');

				if (empty($this->corp) || empty($agentId)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpAgent = WorkCorpAgent::findOne($agentId);
				if (empty($corpAgent) || $corpAgent->corp_id != $this->corp->id) {
					throw new InvalidParameterException('参数不正确！');
				}

				$result = [
					'allow_party' => $corpAgent->allow_party,
					'allow_user'  => $corpAgent->allow_user,
					'user_info'   => [],
				];

				if (!empty($corpAgent->allow_party)) {
					$allowParty = explode(',', $corpAgent->allow_party);
					foreach ($allowParty as $partyId) {
						$workUsers = WorkUser::find()->where("find_in_set ($partyId,department)")->andWhere(['corp_id' => $this->corp->id, 'is_del' => 0])->all();
						if (!empty($workUsers)) {
							/** @var WorkUser $workUser */
							foreach ($workUsers as $workUser) {
								if (empty($result['user_info']['user_' . $workUser->id])) {
									$result['user_info']['user_' . $workUser->id] = $workUser->dumpData();
								}
							}
						}
					}
				}

				if (!empty($corpAgent->allow_user)) {
					$allowUser = explode(',', $corpAgent->allow_user);
					foreach ($allowUser as $userId) {
						$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'userid' => $userId, 'is_del' => 0]);
						if (!empty($workUser) && empty($result['user_info']['user_' . $workUser->id])) {
							$result['user_info']['user_' . $workUser->id] = $workUser->dumpData();
						}
					}
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}