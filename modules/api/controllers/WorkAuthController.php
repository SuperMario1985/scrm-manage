<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/11/30
	 * Time: 11:57 上午
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\WorkCorp;
	use app\models\WorkProviderConfig;
	use app\models\WorkProviderTemplate;
	use app\models\WorkRegisterCode;
	use app\modules\api\components\BaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\ServiceProvider;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;

	class WorkAuthController extends BaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-auth/
		 * @title           获取注册code
		 * @description     获取注册code
		 * @method   GET
		 * @url  http://{host_name}/api/work-auth/get-code
		 *
		 * @param pid 可选 int 服务商ID，默认为1
		 * @param tid 可选 int 推广包ID，默认为第一个
		 *
		 * @return          {"error":0,"data":{"register_code":"-kln4dklTH09FHNE8b95PemONWmIBCbS8Tk5lpQO5fzFfH-TsJ428RTxDYURTDkP","expires_in":600}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    register_code string 注册码，只能消费一次。在访问注册链接时消费。最长为512个字节
		 * @return_param    expires_in int register_code有效期，生成链接需要在有效期内点击跳转
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/11/30 12:21 下午
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionGetCode ()
		{
			if (\Yii::$app->request->isGet) {
				$providerId = \Yii::$app->request->get('pid', 1);

				$workProviderConfig = WorkProviderConfig::findOne($providerId);
				if (empty($workProviderConfig) || empty($workProviderConfig->workProviderTemplates)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$templateId = \Yii::$app->request->get('tid', '');
				if (!empty($templateId)) {
					$templateInfo = WorkProviderTemplate::findOne(['id' => $templateId, 'provider_id' => $workProviderConfig->id, 'status' => WorkProviderTemplate::TEMPLATE_OPEN]);
				} else {
					$templateInfo = WorkProviderTemplate::find()
						->where(['provider_id' => $workProviderConfig->id, 'status' => WorkProviderTemplate::TEMPLATE_OPEN])
						->orderBy(['id' => SORT_ASC])
						->one();
				}

				if (empty($templateInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$state = WorkRegisterCode::getState();

				$registerCodeInfo                        = new WorkRegisterCode();
				$registerCodeInfo->template_id           = $templateInfo->id;
				$registerCodeInfo->state                 = $state;
				$registerCodeInfo->register_code         = '';
				$registerCodeInfo->register_code_expires = '';
				$registerCodeInfo->create_time           = DateUtil::getCurrentTime();
				if (!$registerCodeInfo->validate() || !$registerCodeInfo->save()) {
					throw new InvalidDataException(SUtils::modelError($registerCodeInfo));
				}

				$workApi      = WorkUtils::getProviderApi($providerId);
				$args         = [
					'template_id' => $templateInfo->template_id,
					'state'       => $state,
				];
				$registerData = $workApi->getRegisterCode($args);

				$registerCodeInfo->register_code         = $registerData['register_code'];
				$registerCodeInfo->register_code_expires = (string) (time() + $registerData['expires_in']);
				if (!$registerCodeInfo->validate() || !$registerCodeInfo->save()) {
					throw new InvalidDataException(SUtils::modelError($registerCodeInfo));
				}

				return [
					'register_code' => $registerData['register_code'],
					'expires_in'    => $registerData['expires_in'],
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-auth/
		 * @title           查询注册状态
		 * @description     查询注册状态
		 * @method   post
		 * @url  http://{host_name}/api/work-auth/register-status
		 *
		 * @param pid 可选 int 服务商ID，默认为1
		 * @param tid 可选 int 推广包ID，默认为第一个
		 * @param code 必选 string 查询的注册码
		 *
		 * @return          {"error":0,"data":{"error_msg":"register","error":0}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    error int  错误码
		 * @return_param    error_msg string 错误信息
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-12-22 13:17
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionRegisterStatus ()
		{
			if (\Yii::$app->request->isPost) {
				$providerId   = \Yii::$app->request->post('pid', 1);
				$registerCode = \Yii::$app->request->post('code', '');

				if (empty($registerCode)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$workProviderConfig = WorkProviderConfig::findOne($providerId);
				if (empty($workProviderConfig) || empty($workProviderConfig->workProviderTemplates)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$templateId = \Yii::$app->request->get('tid', '');
				if (!empty($templateId)) {
					$templateInfo = WorkProviderTemplate::findOne(['id' => $templateId, 'provider_id' => $workProviderConfig->id, 'status' => WorkProviderTemplate::TEMPLATE_OPEN]);
				} else {
					$templateInfo = WorkProviderTemplate::find()
						->where(['provider_id' => $workProviderConfig->id, 'status' => WorkProviderTemplate::TEMPLATE_OPEN])
						->orderBy(['id' => SORT_ASC])
						->one();
				}

				if (empty($templateInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$registerCodeInfo = WorkRegisterCode::findOne(['template_id' => $templateInfo->id, 'register_code' => $registerCode]);
				if (empty($registerCodeInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$expiresTime = DateUtil::getNextSecondsTime(24 * 60 * 60, $registerCodeInfo->create_time);

				if (strtotime($expiresTime) <= (time() - 60)) {
					$registerInfo = [
						'error'     => '40001',
						'error_msg' => 'expires',
					];
				} else {
					/** @var ServiceProvider $workApi */
					$workApi = WorkUtils::getProviderApi($providerId);
					try {
						$registerInfo = $workApi->getRegisterInfo($registerCode);
						$cacheKey     = 'authRegister-' . $registerCodeInfo->state;

						if (empty(\Yii::$app->cache->get($cacheKey))) {
							\Yii::$app->cache->set($cacheKey, $registerInfo, 1800);

							$registerCodeInfo->setData($registerInfo);
						}

						$hasSet = false;
						while (!$hasSet) {
							$corpInfo = WorkCorp::findOne(['corpid' => $registerCodeInfo->corpid]);
							if (!empty($corpInfo)) {

								$cacheRegisterKey = md5($corpInfo->corpid);
								\Yii::$app->cache->set($cacheRegisterKey, $corpInfo->id, 86400);

								$registerInfo = [
									'error'     => 0,
									'error_msg' => 'register',
									'corp_id'   => $cacheRegisterKey
								];

								$hasSet = true;
							}
						}
					} catch (\Exception $e) {
						if (strpos($e->getMessage(), 'response error:') !== false) {
							$errorInfo = Json::decode(str_replace('response error:', '', $e->getMessage()));

							switch ($errorInfo['errcode']) {
								case 84024:
									$registerInfo = [
										'error'     => 84024,
										'error_msg' => 'no_register',
									];
									break;
								default:
									$registerInfo = [
										'error'     => $errorInfo['errcode'],
										'error_msg' => $errorInfo['errmsg'],
									];
									break;
							}
						} else {
							$registerInfo = [
								'error'     => $e->getCode(),
								'error_msg' => $e->getMessage(),
							];
						}
					}
				}

				return $registerInfo;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}