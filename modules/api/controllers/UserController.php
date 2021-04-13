<?php

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\Attachment;
	use app\models\SubUser;
	use app\models\SubUserProfile;
	use app\models\User;
	use app\models\UserBaidu;
	use app\models\UserProfile;
	use app\modules\api\components\AuthBaseController;
	use app\util\DateUtil;
	use app\util\StringUtil;
	use app\util\SUtils;
	use yii\web\MethodNotAllowedHttpException;
	use yii\web\UnauthorizedHttpException;

	class UserController extends AuthBaseController
	{
		/**
		 * @return User
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (!empty($this->user)) {
				return $this->user;
			} else {
				throw new UnauthorizedHttpException('access_token has time out.');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/user/
		 * @title           修改账户信息
		 * @description     修改账户信息
		 * @method   post
		 * @url  http://{host_name}/api/user/update
		 *
		 * @param code 必选 string 验证码
		 * @param password 必选 string 新密码
		 * @param password2 必选 string 第二次密码
		 * @param id 必选 int 主账户或子账户的id
		 * @param name 可选 string 名称
		 * @param sex 可选 int 性别1男2女
		 * @param department 可选 string 部门
		 * @param position 可选 string 职务
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/6 13:59
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			//$oldPwd          = \Yii::$app->request->post('oldPwd');
			$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;
			$id              = \Yii::$app->request->post('sub_id');
			$code            = \Yii::$app->request->post('code');
			if (empty($id) && $isMasterAccount==2) {
				throw new InvalidDataException('参数不正确');
			}
			if ($isMasterAccount == 1) {
				$user = $this->user;
				$phone = $user->account;
			} else {
				$sub_user = SubUser::findOne($id);
				$phone = $sub_user->account;
			}
//			if (empty($oldPwd)) {
//				throw new InvalidDataException('请输入旧密码');
//			} else {
//				if ($isMasterAccount == 1) {
//					$checkPwd = $user->validatePassword($oldPwd);
//				} else {
//					$checkPwd = $sub_user->validatePassword($oldPwd);
//				}
//				if (!$checkPwd) {
//					throw new InvalidDataException('旧密码不正确');
//				}
//			}

			//验证码
			if (empty($code)) {
				throw new InvalidDataException('请输入正确的验证码');
			} else {
				$cache     = \Yii::$app->cache;
				$phoneData = !empty($cache['update_phone'][$phone]) ? $cache['update_phone'][$phone] : '';
				if (!($phoneData && $phoneData[0] == $code && (($_SERVER['REQUEST_TIME'] - $phoneData[1]) < 1800))) {
					throw new InvalidDataException('手机验证码不正确');
				}
			}
			$password = \Yii::$app->request->post('password');
			if (empty($password)) {
				throw new InvalidDataException('请输入新的密码');
			} else {
				$length = strlen($password);
				if ($length < 6 || $length > 20) {
					throw new InvalidDataException('请输入6-20位密码');
				}
			}
			$password2 = \Yii::$app->request->post('password2');
			if ($password != $password2) {
				throw new InvalidDataException('两次输入的密码不一致');
			}
			if ($isMasterAccount == 1) {
				$user->salt        = StringUtil::randomStr(6, true);
				$user->password    = StringUtil::encodePassword($user->salt, $password);
				$user->update_time = DateUtil::getCurrentTime();
				//$user->access_token = '';
				if ($user->save()) {
					//修改密码重新登录
					\Yii::$app->user->logout();

					return true;
				} else {
					throw new InvalidDataException(SUtils::modelError($user));
				}
			} else {
				$sub_user->salt        = StringUtil::randomStr(6, true);
				$sub_user->password    = StringUtil::encodePassword($sub_user->salt, $password);
				$sub_user->update_time = DateUtil::getCurrentTime();
				//$user->access_token = '';
				if ($sub_user->save()) {
					//修改密码重新登录
					\Yii::$app->user->logout();

					return true;
				} else {
					throw new InvalidDataException(SUtils::modelError($sub_user));
				}
			}

		}

		/**
		 * 退出登录
		 * @return bool
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionLogout ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$this->user->access_token = '';
			if (!$this->user->save()) {
				throw new InvalidDataException(SUtils::modelError($this->user));
			}
			\Yii::$app->user->logout();

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/user/
		 * @title           主账户信息完善
		 * @description     主账户信息完善
		 * @method   post
		 * @url  http://{host_name}/api/user/set-user-info
		 *
		 * @param uid 必选 int uid
		 * @param company_name 必选 string 公司名称
		 * @param local_path 可选 string 图片路径
		 * @param phone 必选 string 手机号
		 * @param nick_name 可选 string 姓名
		 * @param sex 可选 int 性别1男2女
		 * @param department 可选 string 部门
		 * @param position 可选 string 职务
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/22 16:35
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSetUserInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$uid          = \Yii::$app->request->post('uid');
				$company_name = \Yii::$app->request->post('company_name');
				$local_path   = \Yii::$app->request->post('local_path');
				$nick_name    = \Yii::$app->request->post('nick_name');
				$sex          = \Yii::$app->request->post('sex');
				$department   = \Yii::$app->request->post('department');
				$position     = \Yii::$app->request->post('position');
				if (empty($uid)) {
					throw new InvalidDataException('参数不正确');
				}
				if (empty($nick_name)) {
					throw new InvalidDataException('姓名不能为空');
				}
				if (empty($sex)) {
					throw new InvalidDataException('性别不能为空');
				}
				if (empty($company_name)) {
					throw new InvalidDataException('企业名称不能为空');
				}
				$user = UserProfile::findOne(['uid' => $uid]);
				if (!empty($user)) {
					$user->nick_name    = $nick_name;
					$user->sex          = $sex;
					$user->department   = $department;
					$user->position     = $position;
					$user->company_name = $company_name;
					$user->company_logo = !empty($local_path) ? $local_path : '/upload/images/20191208/15757704175dec5931a5e29.jpg';
					$user->save();
				}

				return true;
			} else {
				throw new InvalidDataException('请求方式不允许');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/user/
		 * @title           获取账户信息
		 * @description     获取账户信息
		 * @method   post
		 * @url  http://{host_name}/api/user/get-user-info
		 *
		 * @param sub_id 必选 int  当前子账户的id
		 *
		 * @return mixed
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/6 13:35
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGetUserInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$sub_id = \Yii::$app->request->post('sub_id');
				if (empty($sub_id)) {
					throw new InvalidDataException('参数不正确');
				}
				$sub_user             = SubUser::findOne($sub_id);
				$user_profile         = UserProfile::findOne(['uid' => $sub_user->uid]);
				$data['account']      = $sub_user->account;
				$data['nick_name']    = $user_profile->nick_name;
				$data['sex']          = $user_profile->sex;
				$data['department']   = $user_profile->department;
				$data['position']     = $user_profile->position;
				$data['company_name'] = $user_profile->company_name;
				$data['company_logo'] = $user_profile->company_logo;

				return $data;
			} else {
				throw new InvalidDataException('请求方式不允许');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/user/
		 * @title           设置token
		 * @description     设置token
		 * @method   post
		 * @url  http://{host_name}/api/user/set-token
		 *
		 * @param uid 必选 string 账户id
		 * @param token 必选 string token
		 * @param token_id 可选 string 修改时必填
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 16:45
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSetToken ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$token_id = \Yii::$app->request->post('token_id');
			$uid      = \Yii::$app->request->post('uid');
			$token    = \Yii::$app->request->post('token');
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($token)) {
				throw new InvalidDataException('请设置token！');
			}
			if (!empty($token_id)) {
				$userBaiDu = UserBaidu::findOne($token_id);
			} else {
				$userBaiDu              = new UserBaidu();
				$userBaiDu->uid         = $uid;
				$userBaiDu->create_time = DateUtil::getCurrentTime();
			}
			$userBaiDu->token = $token;
			if (!$userBaiDu->validate() || !$userBaiDu->save()) {
				throw new InvalidDataException(SUtils::modelError($userBaiDu));
			}

			return true;
		}

	}