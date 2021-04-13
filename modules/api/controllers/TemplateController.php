<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/9/17
	 * Time: 09:39
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\Template;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;

	class TemplateController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'get-template-content' => ['POST'],
						'sync-template'        => ['POST'],
						'get-all-template'     => ['POST'],
					],
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template/
		 * @title           同步所有模板
		 * @description     同步所有模板
		 * @method   post
		 * @url  http://{host_name}/api/template/sync-template
		 *
		 * @param wx_id 必选 string 公众号原始id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 17:27
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
//		public function actionSyncTemplate()
//		{
//			try {
//				if (empty($this->wxAuthorInfo)) {
//					throw new InvalidParameterException('参数不正确！');
//				}
//				$res = Template::getTemplate($this->wxAuthorInfo->authorizer_appid,$this->wxAuthorInfo->author_id);
//				if($res){
//					return true;
//				}else{
//					throw new InvalidParameterException('该公众号暂时没有模板消息！');
//				}
//			} catch (\Exception $e) {
//				throw new InvalidParameterException('同步失败，请稍后再试！');
//			}
//
//		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template/
		 * @title           获取模板内容
		 * @description     获取模板内容
		 * @method   post
		 * @url  http://{host_name}/api/template/get-template-content
		 *
		 * @param id 必选 int 模板id
		 *
		 * @return          {"error":0,"data":["账号","当前余额"]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 17:24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionGetTemplateContent ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('模板id不能为空！');
				}
				try {

					$temp         = Template::findOne(['id' => $id]);
					$template     = $temp->content;
					$templateData = explode("{{", $template);
					if (!empty($templateData[0])) {
						unset($templateData[0]);
						$templateData = implode("{{", $templateData);
						$template     = "{{" . $templateData;
					}
					$arr     = explode(PHP_EOL, $template);
					$newData = [];
					$tmpData = [];
					if (!empty($arr)) {
						foreach ($arr as $key => $value) {
							if (!empty($value)) {
								$data                     = explode(".DATA}}", $value);
								$data1                    = explode("{{", $data[0]);
								$newData[$key][$data1[1]] = $data1[0];
								if (isset($data[1]) && !empty(trim($data[1]))) {
									$data2                    = explode("{{", $data[1]);
									$newData[$key][$data2[1]] = $data2[0];
								}
								if (isset($data[2]) && !empty(trim($data[2]))) {
									$data3                    = explode("{{", $data[2]);
									$newData[$key][$data3[1]] = $data3[0];
								}
								$show    = 1;
								$type    = 0;//不显示前后
								$start   = '';
								$end     = '';
								$title   = '';
								$keyWord = '';
								if (count($data) > 2) {
									if (empty($data2[0])) {
										$show = 0;//不显示文字
									} else {
										$title = $data2[0];
									}
									$keyWord = $data2[1];
								} else {
									if (empty($data1[0])) {
										$show = 0;//不显示文字
									}
									$title   = $data1[0];
									$keyWord = $data1[1];
								}
								if (!empty($data[1]) && empty($data[2])) {
									$type  = 1;//显示前
									$start = $data1[1];
								}
								if (empty($data[1]) && !empty($data[2])) {
									$type = 2;//显示后
									$end  = $data3[1];
								}
								if (!empty($data[1]) && !empty($data[2])) {
									$type  = 3;//显示前后
									$start = $data1[1];
									$end   = $data3[1];
								}
								$tmpData[$key]['show']  = $show;
								$tmpData[$key]['type']  = $type;
								$tmpData[$key]['start'] = ['key' => $start, 'value' => ''];
								$tmpData[$key]['end']   = ['key' => $end, 'value' => ''];
								$tmpData[$key]['title'] = $title;
								$tmpData[$key]['key']   = $keyWord;
							}
						}
					}
					$newData         = array_values($tmpData);
					$result['title'] = $temp->title;
					$result['data']  = $newData;

					return $result;
				} catch (\Exception $e) {
					throw new InvalidParameterException('获取模板内容失败！');
				}

			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template/
		 * @title           获取所有模板信息
		 * @description     获取所有模板信息
		 * @method   请求方式
		 * @url  http://{host_name}/api/template/get-all-template
		 *
		 * @param wx_id 必选 string 公众号原始id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/14 15:57
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetAllTemplate ()
		{
			if (empty($this->wxAuthorInfo)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$data = [];
			$temp = Template::sysncTemplate($this->wxAuthorInfo->author_id);
			if ($temp) {
				$data = Template::find()->andWhere(['author_id' => $this->wxAuthorInfo->author_id])->andWhere(['<>', 'title', '订阅模板消息'])->asArray()->all();
			}

			return [
				'info' => $data,
			];
		}

	}