<?php

	namespace app\modules\admin\controllers;

	use app\components\InvalidDataException;
	use app\models\WorkProviderConfig;
	use app\models\WorkSuiteConfig;
	use app\models\WxAuthorizeConfig;
	use app\modules\admin\components\BaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use yii\helpers\Json;

	class ServiceProviderController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;
		}

		/**
		 * 打开时候回去当前的服务商配置
		 * @return string
		 */
		public function actionGetConfig ()
		{
			$WorkProviderConfig = WorkProviderConfig::findOne(\Yii::$app->params['default_pro']);
//			$WorkProviderConfig = WorkProviderConfig::findOne(12);
			$WxAuthorizeConfig  = WxAuthorizeConfig::findOne(\Yii::$app->params['default_auth']);
//			$WxAuthorizeConfig  = WxAuthorizeConfig::findOne(8);
			$WorkSuiteConfig    = empty($WorkProviderConfig) ? [] : $WorkProviderConfig->workSuiteConfigs[0];
			if (!empty($WorkSuiteConfig)) {
				$WorkSuiteConfig                      = $WorkSuiteConfig->toArray();
				$pattern                              = "/(http:\/\/|https:\/\/)/";
				$WorkSuiteConfig["redirect_1"]        = preg_replace($pattern, '', \Yii::$app->params["web_url"]);
				$WorkSuiteConfig["redirect_2"]        = preg_replace($pattern, '', \Yii::$app->params["site_url"]);
				$WorkSuiteConfig["redirect_3"]        = preg_replace($pattern, '', \Yii::$app->params["scrm_url"]);
				$WorkSuiteConfig["home_url"]          = preg_replace($pattern, '', \Yii::$app->params["scrm_url"]);
				$WorkSuiteConfig["redirect_url"]      = preg_replace($pattern, '', \Yii::$app->params["web_url"]);
				$WorkSuiteConfig["data_redirect"]     = \Yii::$app->params["site_url"] . "/work/server-event/index/" . $WorkSuiteConfig["id"];
				$WorkSuiteConfig["instruct_redirect"] = \Yii::$app->params["site_url"] . "/work-receive/index/" . $WorkSuiteConfig["id"];
				$WorkSuiteConfig["business_url"]      = \Yii::$app->params["scrm_url"] . "/login";
				$WorkSuiteConfig["choose"]            = 1;
				if (!preg_match($pattern, $WorkSuiteConfig["logo_url"])) {
					$WorkSuiteConfig["choose"] = 2;
				}
			}
			if (!empty($WxAuthorizeConfig)) {
				$WxAuthorizeConfig         = $WxAuthorizeConfig->toArray();
				$WxAuthorizeConfig["url1"] = \Yii::$app->params["site_url"] . "/open-auth/index";
				$WxAuthorizeConfig["url2"] = \Yii::$app->params["site_url"] . "/wechat/event/index?type=1&appid=" . $WxAuthorizeConfig["appid"];
			}

			return $this->render('index', [
				"setting"      => empty($WorkSuiteConfig) ? 0 : 1,
				"w_setting"    => empty($WxAuthorizeConfig) ? 0 : 1,
				"wConfig"      => $WxAuthorizeConfig,
				"serverConfig" => $WorkProviderConfig,
				"appConfig"    => empty($WorkSuiteConfig) ? [] : $WorkSuiteConfig]);
		}

		/**
		 * 添加企业微信服务商配置
		 */
		public function actionAddConfig ()
		{
			if (\Yii::$app->request->isPost) {
				$providerConfig['provider_corpid'] = \Yii::$app->request->post('provider_corpid');
				$providerConfig['provider_secret'] = \Yii::$app->request->post('provider_secret');
				$providerConfig['token']           = \Yii::$app->request->post('token');
				$providerConfig['encode_aes_key']  = \Yii::$app->request->post("encode_aes_key");
				$providerConfig['status']          = 1;
				$chooseAction                      = \Yii::$app->request->post("chooseAction");
				$id                                = \Yii::$app->request->post("id", 0);
				$suiteConfig['name']               = \Yii::$app->request->post("name");
				$suiteConfig['suite_id']           = \Yii::$app->request->post("suite_id");
				$suiteConfig['description']        = \Yii::$app->request->post("description");
				$suiteConfig['suite_secret']       = \Yii::$app->request->post("suite_secret");
				$suiteConfig['token']              = \Yii::$app->request->post("token");
				$suiteConfig['encode_aes_key']     = \Yii::$app->request->post("encode_aes_key");
				$suiteConfig['status']             = 1;
				$suiteConfig['create_time']        = $providerConfig['create_time'] = date('Y-m-d H:i:s', time());
				foreach ($providerConfig as $value) {
					if (empty($value)) {
						return Json::encode(["error" => 1, "msg" => "数据未填写完整"], JSON_UNESCAPED_UNICODE);
					}
				}
				foreach ($suiteConfig as $key => $value) {
					if (empty($value)) {
						return Json::encode(["error" => 1, "msg" => "数据未填写完整"], JSON_UNESCAPED_UNICODE);
					}
				}
				if (!isset($_FILES['checkText'])) {
					return Json::encode(["error" => 1, "msg" => "校验文件未上传"], JSON_UNESCAPED_UNICODE);
				}
				if (isset($_FILES['checkText']) && !empty($_FILES['checkText'])) {
					move_uploaded_file($_FILES["checkText"]["tmp_name"], \Yii::$app->basePath . "/web/" . $_FILES["checkText"]["name"]);
				}

				if ($chooseAction == 1) {
					$suiteConfig['logo_url'] = \Yii::$app->request->post("logo_url");
				} else {

					if (isset($_FILES["logFileInfo"])) {
						$tempName = explode("/", $_FILES["logFileInfo"]["type"]);
						$savePath = \Yii::getAlias('@upload') . '/images/' . date('Ymd') . '/';
						if (!file_exists($savePath)) {
							if (!mkdir($savePath, 0777, true)) {
								return Json::encode(["error" => 1, "msg" => "服务器没有文件操作权限"], JSON_UNESCAPED_UNICODE);
							}
						}
						$fileName = md5($_FILES["logFileInfo"]["name"]) . time() . '.' . $tempName[1];
						move_uploaded_file($_FILES["logFileInfo"]["tmp_name"], $savePath . $fileName);
						$suiteConfig['logo_url'] = '/upload/images/' . date('Ymd') . '/' . $fileName;
					}
				}
				$Transaction = \Yii::$app->db->beginTransaction();
				try {
					if (empty($id)) {
						$WorkProviderConfig = new WorkProviderConfig();
					} else {
						$WorkProviderConfig = WorkProviderConfig::findOne($id);
						unset($providerConfig['create_time']);
					}
					$WorkProviderConfig->setAttributes($providerConfig);
					if (!$WorkProviderConfig->validate() || !$WorkProviderConfig->save()) {
						$Transaction->rollBack();

						throw new InvalidDataException(SUtils::modelError($WorkProviderConfig));
					}
					if (empty($id)) {
						$suiteConfig["provider_id"] = $WorkProviderConfig->id;
						$WorkSuiteConfig            = new WorkSuiteConfig();
					} else {
						$WorkSuiteConfig = WorkSuiteConfig::findOne(["provider_id" => $WorkProviderConfig->id]);
						unset($suiteConfig['create_time']);

					}

					$WorkSuiteConfig->setAttributes($suiteConfig, false);
					if (!$WorkSuiteConfig->validate() || !$WorkSuiteConfig->save()) {
						throw new InvalidDataException(SUtils::modelError($WorkSuiteConfig));
					}
					$Transaction->commit();
				} catch (\Exception $e) {
					$Transaction->rollBack();

					return Json::encode(["error" => 1, "msg" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
				}

				return Json::encode(["error" => 0], JSON_UNESCAPED_UNICODE);

			}

			return Json::encode(["error" => 1, "msg" => "请求方式不正确"], JSON_UNESCAPED_UNICODE);

		}

		/**
		 * 添加公众号配置
		 */
		public function actionAddWechatConfig ()
		{
			if (\Yii::$app->request->isGet) {
				return Json::encode(["error" => 1, "msg" => "请求方式不正确"], JSON_UNESCAPED_UNICODE);

			}
			$wid                            = \Yii::$app->request->post('wid');
			$WechatConfig['appid']          = \Yii::$app->request->post('appid');
			$WechatConfig['appSecret']      = \Yii::$app->request->post('appSecret');
			$WechatConfig['token']          = \Yii::$app->request->post('token');
			$WechatConfig['encode_aes_key'] = \Yii::$app->request->post("encode_aes_key");
			$WechatConfig['status']         = 1;
			$WechatConfig['create_time']    = date('Y-m-d H:i:s', time());
			foreach ($WechatConfig as $value) {
				if (empty($value)) {
					return Json::encode(["error" => 1, "msg" => "数据未填写完整"], JSON_UNESCAPED_UNICODE);
				}
			}
			if (!isset($_FILES['checkText'])) {
				return Json::encode(["error" => 1, "msg" => "校验文件未上传"], JSON_UNESCAPED_UNICODE);
			}
			if (isset($_FILES['checkText']) && !empty($_FILES['checkText'])) {
				move_uploaded_file($_FILES["checkText"]["tmp_name"], \Yii::$app->basePath . "/web/" . $_FILES["checkText"]["name"]);
			}
			$Transaction = \Yii::$app->db->beginTransaction();
			try {
				if (!empty($wid)) {
					$wConfig = WxAuthorizeConfig::findOne($wid);
					unset($WechatConfig['create_time']);
				} else {
					$wConfig = new WxAuthorizeConfig();
				}
				$wConfig->setAttributes($WechatConfig);
				if (!$wConfig->validate() || !$wConfig->save()) {
					throw new InvalidDataException(SUtils::modelError($wConfig));
				}
				$Transaction->commit();

			} catch (\Exception $e) {
				$Transaction->rollBack();

				return Json::encode(["error" => 1, "msg" => $e->getMessage()], JSON_UNESCAPED_UNICODE);

			}

			return Json::encode(["error" => 0], JSON_UNESCAPED_UNICODE);
		}

	}