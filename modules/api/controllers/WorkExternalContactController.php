<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/2/5
	 * Time: 15:16
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\util\DateUtil;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkExternalContactJob;
	use yii\web\MethodNotAllowedHttpException;

	class WorkExternalContactController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           同步客户
		 * @description     同步客户
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact/refresh-work-external-contact-list
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/5 17:18
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 */
		public function actionRefreshWorkExternalContactList ()
		{
			if (\Yii::$app->request->isPost) {
				ignore_user_abort();
				set_time_limit(0);

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$cacheKey     = 'refresh_work_external_contact_' . $this->corp->id;
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
					throw new NotAllowException('今日请求已达上限！');
				}

				//  两次请求时间间隔验证 间隔两小时
				if (($refreshCache[$currentYmd]['last_refresh_time'] + 2 * 60 * 60) > time()) {
					throw new NotAllowException('距离上次请求时间不足两小时！');
				}

				++$refreshCache[$currentYmd]['refresh'];
				$refreshCache[$currentYmd]['last_refresh_time'] = time();
				\Yii::$app->cache->set($cacheKey, $refreshCache);

				$jobId = \Yii::$app->work->push(new SyncWorkExternalContactJob([
					'corp' => $this->corp,
				]));

				return ['error' => 0];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

	}