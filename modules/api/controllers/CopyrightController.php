<?php
	/**
	 * Create by PhpStorm
	 * title: 成员工作台数据
	 * Date: 2021/01/07
	 */

	namespace app\modules\api\controllers;

    use app\components\InvalidParameterException;
    use app\models\AdminConfig;
    use app\models\Package;
    use app\modules\api\components\BaseController;
    use app\models\User;

    class CopyrightController extends BaseController
	{

		public function actionGetCopyright ()
		{


            if (\Yii::$app->request->isPost) {
                $uid        = \Yii::$app->request->post('uid', 0);

                if (empty($uid)) {
                    throw new InvalidParameterException( '参数不正确！');
                }

                $data = User::find()->alias('a')
                    ->select('b.tech_img_show as is_show,c.value as url')
                    ->leftJoin(Package::tableName() . ' b', 'a.package_id=b.id')
                    ->leftJoin(AdminConfig::tableName() . ' c', 'c.key="web_tech_img"')
                    ->where(['a.uid'=>$uid])
                    ->asArray()
                    ->one();

                if (!$data) {
                    throw new InvalidParameterException('参数不正确！', 1002);
                }

                if ($data['is_show'] === null) {
                    throw new InvalidParameterException( '套餐出现问题！');
                }

                if ($data['url'] === null) {
                    $data['is_show'] = "0";
                    $data['url'] = '';
                }

                return $data;
            } else {
                throw new InvalidParameterException('请求方式不允许！');
            }
		}

	}