<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\Authority;
	use app\models\SubUser;
	use app\models\SubUserAuthority;
	use app\models\WorkCorp;
	use app\models\WorkUser;
	use app\modules\api\components\BaseController;
	use yii\web\MethodNotAllowedHttpException;

	class WapAuthUserController extends BaseController
	{

		public function beforeAction ($action)
		{
			return parent::beforeAction($action); // TODO: Change the autogenerated stub
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-auth-user/
		 * @title           获取H5成员权限
		 * @description     获取H5成员权限
		 * @method   post
		 * @url  http://{host_name}/api/wap-auth-user/get-wap-auth
		 *
		 * @param corp_id 必选 int 企业id
		 * @param user_id 必选 string 成员userid
		 *
		 * @return array
		 *
		 * @return_param    data array 权限列表
		 *
		 * @remark          Create by PhpStorm. User: Sym. Date: 2020/12/18 16:58
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetWapAuth ()
		{
			if(\Yii::$app->request->isGet){
				throw new MethodNotAllowedHttpException("请求方式不正确");
			}
			$corpid = \Yii::$app->request->post("corp_id");
			$userId = \Yii::$app->request->post("user_id");
			$corp   = WorkCorp::findOne(["corpid" => $corpid]);
			if (empty($corp)) {
				throw new InvalidDataException("企业微信不存在");
			}
			$workUser = WorkUser::findOne(["userid" => $userId, "corp_id" => $corp->id]);
			if (empty($workUser)) {
				throw new InvalidDataException("员工不存在");
			}
			//$ReturnParamsInitial = [];
			//if ($workUser->is_external) {
				$ReturnParamsInitial = SubUserAuthority::getDisabledParams(1);
			//}
			if (!empty($workUser)) {
				$SubUser = SubUser::find()->alias("a")
					->leftJoin("{{%user_corp_relation}} as b", "a.uid = b.uid")
					->leftJoin("{{%authority_sub_user_detail}} as c", "(a.sub_id = c.sub_id and b.corp_id = c.corp_id)")
					->where(["b.corp_id" => $corp->id, "a.account" => $workUser->mobile,"a.status"=>1])
					->select("c.*")->asArray();
				$SubUser = $SubUser->one();
				if (!empty($SubUser)) {
					$AuthSubUser = SubUserAuthority::findOne(["sub_user_id" => $SubUser["sub_id"], "wx_id" => $SubUser["corp_id"], "type" => 2]);
					if (!empty($AuthSubUser)) {
						if (!empty($AuthSubUser->authority_ids)) {
							$AuthIdsInitial      = explode(",", $AuthSubUser->authority_ids);
							$route               = SubUserAuthority::getDisabledParams(1);
							$AuthIds             = Authority::find()->where(["route" => $route, "status" => 0])->select("id")->asArray()->all();
							$AuthIds             = array_column($AuthIds, "id");
							$AuthIds             = array_intersect($AuthIds, $AuthIdsInitial);
							$ReturnParams        = Authority::find()->where(["id" => $AuthIds])->select("route")->asArray()->all();
							$ReturnParams        = array_column($ReturnParams, "route");
							$ReturnParamsInitial = array_intersect($ReturnParams, $ReturnParamsInitial);
						} else {
							$ReturnParamsInitial = [];
						}
					}
				}
				if($workUser->is_external == 0 && empty($SubUser)){
					$ReturnParamsInitial = [];
				}
			}

			return $ReturnParamsInitial;
		}
	}