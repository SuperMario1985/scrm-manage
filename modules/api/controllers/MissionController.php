<?php

	namespace app\modules\api\controllers;

	use app\models\WorkGroupClockActivity;
	use app\modules\api\components\BaseController;
	use app\util\ShortUrlUtil;

	class MissionController extends BaseController
	{
		/**
		 * 任务宝短连接获取完整连接
		 * showdoc
		 * @catalog         数据接口/mission/short-url
		 * @title           任务宝短连接获取完整连接
		 * @description     任务宝短连接获取完整连接
		 * @method   POST
		 * @url  http://{host_name}/api/mission/short-url
		 *
		 * @param short_url 必填 string 短连接
		 * @param type 必填 string 类型：1、任务宝，2、群打卡
		 *
		 * @return_param    url  string 完整连接
		 *
		 * @remark          Create by PhpStorm. User: sym. Date: 2020/10/14
		 */
		public function actionShortUrl ()
		{
			$shortUrl = \Yii::$app->request->post("short_url");
			$type     = \Yii::$app->request->post("type", 1);
			if (!empty($shortUrl)) {
				if ($type == 1) {
					$Url = ShortUrlUtil::getLongUrl($shortUrl);
					if (!empty($Url)) {
						return ["url" => $Url];
					}
				} elseif ($type == 2) {
					$clockAct = WorkGroupClockActivity::findOne(["short_url" => $shortUrl]);
					if (!empty($clockAct)) {
						$webUrl = \Yii::$app->params['web_url'];

						return ["url" => $webUrl . $clockAct->url];
					}
				}
			}

			return [];
		}
	}