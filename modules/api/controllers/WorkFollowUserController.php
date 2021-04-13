<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 19:36
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\WorkFollowUser;
	use app\models\WorkUser;
	use app\modules\api\components\WorkBaseController;

	class WorkFollowUserController extends WorkBaseController
	{
		public function actionRefreshFollowUser ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if($this->corp->corp_type!='verified'){
				throw new InvalidParameterException('当前企业号未认证！');
			}
			$status = WorkFollowUser::getFollowUser($this->corp->id);

			return $status;
		}

		public function actionGetFollowUser ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$page     = \Yii::$app->request->post('page') ?: 1;
			$sort     = \Yii::$app->request->post('sort') ?: 1;
			$pageSize = \Yii::$app->request->post('page_size') ?: 15;
			$offset   = ($page - 1) * $pageSize;

			$followUserInfo = [];
			if (!empty($this->corp->workFollowUsers)) {
				foreach ($this->corp->workFollowUsers as $followUser) {
					$followUserData = [
						'status'    => $followUser->status,
						'user_info' => $followUser->user->dumpData(true)
					];

					array_push($followUserInfo, $followUserData);
				}
			}

			return $followUserInfo;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-follow-user/
		 * @title           更新外部联系人权限脚本
		 * @description     更新外部联系人权限脚本
		 * @method   post
		 * @url  http://{host_name}/api/work-follow-user/update-external
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/12 17:52
		 * @number          0
		 *
		 */
		public function actionUpdateExternal ()
		{
			$workFollow = WorkFollowUser::find()->asArray()->all();
			if (!empty($workFollow)) {
				foreach ($workFollow as $user) {
					$workUser              = WorkUser::findOne($user['user_id']);
					$workUser->is_external = $user['status'];
					$workUser->save();
				}
			}
		}
	}