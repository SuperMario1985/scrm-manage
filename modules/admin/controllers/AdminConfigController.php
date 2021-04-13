<?php

	namespace app\modules\admin\controllers;


	use app\modules\admin\components\BaseController;
	use app\util\SUtils;
	use app\components\InvalidDataException;
    use app\models\AdminConfig;
    use yii\helpers\Json;

    class AdminConfigController extends BaseController
	{
		public $enableCsrfValidation = false;



		public function actionIndex ()
		{
            $data = AdminConfig::find()->asArray()->all();
            $result = [];
            foreach ($data as $v)
            {
                $result[$v['key']] = $v['value'];
            }

			return $this->render('index',['data'=>$result]);
		}

		/*
		 * 基础信息设置
		 * */
        public function actionAddConfig ()
        {

            if (!\Yii::$app->request->isPost) {
                return Json::encode(["error" => 1, "msg" => "请求方式不正确"], JSON_UNESCAPED_UNICODE);
            }

            $model = new AdminConfig();


            if (isset($_FILES["techImg"])) {
                $uploadResult = $this->localUpload($_FILES["techImg"]);
                if ($uploadResult['error']) {
                    return Json::encode(["error" => 1, "msg" => $uploadResult['data']], JSON_UNESCAPED_UNICODE);
                }
                $model->updateTechImg($uploadResult['data']);
            }

            return Json::encode(["error" => 0], JSON_UNESCAPED_UNICODE);
        }


        public function localUpload($file)
        {
            $tempName = explode("/", $file["type"]);
            $savePath = \Yii::getAlias('@upload') . '/images/' . date('Ymd') . '/';
            if (!file_exists($savePath)) {
                if (!mkdir($savePath, 0777, true)) {
                    return ["error" => 1, "data" => "服务器没有文件操作权限"];
                }
            }
            $fileName = md5($file["name"]) . time() . '.' . $tempName[1];
            move_uploaded_file($file["tmp_name"], $savePath . $fileName);
            $url = '/upload/images/' . date('Ymd') . '/' . $fileName;
            return ['data'=>$url, 'error' => 0];
        }

	}