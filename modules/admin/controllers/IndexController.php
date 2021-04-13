<?php

	namespace app\modules\admin\controllers;

	use app\models\AdminConfig;
	use app\models\AdminUser;
	use app\models\AdminUserEmployee;
	use app\models\Area;
	use app\models\DefaultPackage;
	use app\models\User;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkPerTagFollowUser;
	use app\models\WorkProviderConfig;
	use app\models\WorkTag;
	use app\models\WorkTagFollowUser;
	use app\models\WorkTagGroup;
	use app\util\DateUtil;
	use app\util\SmsUtil;
	use app\util\StringUtil;
	use app\util\SUtils;
	use app\util\UploadFileUtil;
	use app\util\WorkUtils;
	use Yii;
	use app\models\AdminLoginForm;
	use app\modules\admin\components\BaseController;
	use app\models\Authority;
	use app\models\Menu;
	use app\models\MenuAction;
	use app\models\Package;
	use yii\data\Pagination;
	use app\components\InvalidDataException;
	use yii\helpers\Json;
	use yii\log\EmailTarget;
	use yii\web\MethodNotAllowedHttpException;

	/**
	 * Default controller for the `admin` module
	 */
	class IndexController extends BaseController
	{
		public $enableCsrfValidation = false;

		//登录
		public function actionLogin ()
		{
			if (!Yii::$app->adminUser->isGuest) {
				$url = $this->actionGetLoginUrl();

				return $this->goBack($url);
			}
			$model = new AdminLoginForm();
			if ($model->load(Yii::$app->request->post()) && $model->login()) {
				$url = $this->actionGetLoginUrl();

				return $this->goBack($url);
			}

			$model->password = '';

			return $this->renderPartial('login', [
				'model' => $model,
			]);
		}

		//登录跳转页面
		private function actionGetLoginUrl ()
		{
			$url = '/admin/user-manage/user-statistics';

			$isAgent = Yii::$app->adminUser->identity->type != 0 ? 1 : 0;//1代理商
			$eid     = isset(Yii::$app->adminUserEmployee->identity->id) ? Yii::$app->adminUserEmployee->identity->id : 0;
			if ($isAgent == 0 && $eid) {
				$eidUrl    = '';
				$authority = AdminUserEmployee::getEmployeeAuthority($eid);
				foreach ($authority as $v) {
					if (!empty($v['children'])) {
						foreach ($v['children'] as $vv) {
							if ($vv['url']) {
								$eidUrl = $vv['url'];
								break 2;
							}
						}
					}
				}

				if (empty($eidUrl)) {
					Yii::$app->adminUser->logout();
					Yii::$app->adminUserEmployee->logout();
					throw new InvalidDataException('当前员工角色无权限，请先配置角色权限！');
				} else {
					$url = $eidUrl;
				}
			}

			return $url;
		}

		//退出
		public function actionLogout ()
		{
			Yii::$app->adminUser->logout();
			Yii::$app->adminUserEmployee->logout();

			return $this->goBack('/admin/index/login');
		}

		//修改登录密码页面
		public function actionModifyPwd ()
		{
			$account = Yii::$app->adminUser->identity->account;
			$phone   = '';
			if ($account) {
				$phone = SUtils::hideString($account, 3, 4, true);
			}

			return $this->render('modifyPwd', ['phone' => $phone]);
		}

		//获取验证码
		public function actionGetCode ()
		{
			if (\Yii::$app->request->isPost) {
				$mobile = Yii::$app->adminUser->identity->account;
				if (empty($mobile)) {
					$this->dexit(['error' => 1, 'msg' => '手机号码为空']);
				} elseif (!preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $mobile)) {
					$this->dexit(['error' => 1, 'msg' => '手机号码不正确']);
				}
				$cache = \Yii::$app->cache;
				$time  = $_SERVER['REQUEST_TIME'];

				//获取手机验证码时间限制
				if (!empty($cache['get_code_time_' . $mobile])) {
					$getCodeTime = $time - $cache['get_code_time_' . $mobile];
					if ($getCodeTime < 60) {
						$this->dexit(['error' => 1, 'msg' => '请' . (60 - $getCodeTime) . '秒之后再试']);
					}
				}
				//生成手机验证码
				$string   = '0123456789';
				$count    = strlen($string) - 1;
				$rand_num = '';
				for ($i = 0; $i < 6; $i++) {
					$rand_num .= $string[mt_rand(0, $count)];
				}
				$mobileData   = [$mobile => [$rand_num, $time]];
				$seconds      = 1800;
				$validateTime = '30';

				$cache->set('modifyPwd_phone', $mobileData, $seconds);
				\Yii::error($mobileData, 'mobileData');
				$return_status = SmsUtil::sendSms($mobile, '您的验证码是：' . $rand_num . '。 此验证码' . $validateTime . '分钟内有效，请不要把验证码泄露给其他人。如非本人操作，可不用理会！');
				if ($return_status == 0 && strlen($return_status) == 1) {
					$cache->set('get_code_time_' . $mobile, $time, 60);

					$this->dexit(['error' => 0, 'msg' => '']);
				} elseif ($return_status == NULL) {
					$this->dexit(['error' => 1, 'msg' => '没有购买短信']);
				} else {
					$this->dexit(['error' => 1, 'msg' => '短信发送失败,请稍后再试' . $return_status]);
				}
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//修改登录密码提交
		public function actionModifyPwdPost ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();

				$oldpwd  = trim($postData['oldpwd']);
				$newpwd  = trim($postData['newpwd']);
				$new2pwd = trim($postData['new2pwd']);
				$code    = trim($postData['code']);

				$account   = Yii::$app->adminUser->identity->account;
				$cache     = \Yii::$app->cache;
				$phoneData = !empty($cache['modifyPwd_phone'][$account]) ? $cache['modifyPwd_phone'][$account] : '';
				if (!($phoneData && $phoneData[0] == $code && (($_SERVER['REQUEST_TIME'] - $phoneData[1]) < 1800))) {
					$this->dexit(['error' => 1, 'msg' => '手机验证码不正确！']);
				}

				if (empty($oldpwd)) {
					$this->dexit(['error' => 1, 'msg' => '旧密码不能为空！']);
				}
				if (empty($newpwd)) {
					$this->dexit(['error' => 1, 'msg' => '新密码不能为空！']);
				}
				if ($newpwd != $new2pwd) {
					$this->dexit(['error' => 1, 'msg' => '两次输入的密码不一致！']);
				}

				$id        = Yii::$app->adminUser->identity->id;
				$adminUser = AdminUser::findOne($id);
				if (empty($adminUser)) {
					$this->dexit(['error' => 1, 'msg' => '用户数据错误！']);
				}

				$salt       = $adminUser->salt;
				$currentPwd = $adminUser->password;

				$oldpwd = StringUtil::encodePassword($salt, $oldpwd);
				if ($oldpwd != $currentPwd) {
					$this->dexit(['error' => 1, 'msg' => '旧密码不对！']);
				}
				$newpwdstr              = StringUtil::encodePassword($salt, $newpwd);
				$adminUser->password    = $newpwdstr;
				$adminUser->update_time = DateUtil::getCurrentTime();

				if ($adminUser->save()) {
					$this->dexit(['error' => 0, 'msg' => '']);
				} else {
					$this->dexit(['error' => 1, 'msg' => SUtils::modelError($adminUser)]);
				}
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//套餐管理
		public function actionPackage ()
		{
			$wechatLists  = Menu::getMenuList(1, true, false, 5);//企业微信功能
			$accountLists = Menu::getMenuList(1, true, false, 4);//公众号功能
			$commonLists  = Menu::getMenuList(1, true, false, 3);//公共功能

			$packageList            = Package::getAllPackageInfo();
			$packageLocalMenuId     = [];
			$packageLocalMenuStatus = [];
			$packageLocalMenuLimit  = [];
			$packageLocalPriceJson  = [];

			foreach ($packageList as $k => $package) {
				$packageLocalMenuId[$package['id']]     = $package['authority']['menu_id'];
				$packageLocalMenuStatus[$package['id']] = $package['authority']['status'];
				//$packageLocalMenuLimit[$package['id']]  = $package['authority']['limit'];
				$packagePrice                          = !empty($package['priceJson']) ? json_decode($package['priceJson'], true) : [];
				$packageLocalPriceJson[$package['id']] = $packagePrice;

				$priceStr = '';
				foreach ($packagePrice as $kk => $vv) {
					if ($vv['timeType'] == 1) {
						$timeType = '日';
					} elseif ($vv['timeType'] == 2) {
						$timeType = '月';
					} else {
						$timeType = '年';
					}
					$sendStr = '';
					if (!empty($vv['sendTimeType']) && !empty($vv['sendTimeNum'])) {
						if ($vv['sendTimeType'] == 1) {
							$sendTimeType = '日';
						} elseif ($vv['sendTimeType'] == 2) {
							$sendTimeType = '月';
						} else {
							$sendTimeType = '年';
						}
						$sendStr = ' 赠送时长：' . $vv['sendTimeNum'] . $sendTimeType;
					}
					$priceStr .= '时长：' . $vv['timeNum'] . $timeType . $sendStr . ' 价格：' . $vv['nowPrice'] . '<br/>';
				}
				$packageList[$k]['packagePrice'] = $priceStr;
			}

			$packageSet      = DefaultPackage::find()->asArray()->one();
			$expirePackageId = !empty($packageSet) && $packageSet['expire_type'] == 2 && $packageSet['expire_package_id'] ? $packageSet['expire_package_id'] : 0;

			$web_tech_img = AdminConfig::getValueByKey('web_tech_img');

			return $this->render('package', ['title' => '套餐管理', 'packageList' => $packageList, 'packageSet' => $packageSet, 'packageSetInfo' => json_encode($packageSet), 'wechatLists' => $wechatLists, 'accountLists' => $accountLists, 'commonLists' => $commonLists, 'packageLocalMenuId' => json_encode($packageLocalMenuId), 'packageLocalMenuStatus' => json_encode($packageLocalMenuStatus), 'packageLocalMenuLimit' => json_encode($packageLocalMenuLimit), 'packageLocalPriceJson' => json_encode($packageLocalPriceJson), 'expirePackageId' => $expirePackageId, 'web_tech_img' => $web_tech_img]);
		}

		//设置套餐
		public function actionSetPackage ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				if (empty($postData['name'])) {
					$this->dexit(['error' => 1, 'msg' => '套餐名称不能为空']);
				}
				$oldPrice  = !empty($postData['old_price']) ? $postData['old_price'] : 0;
				$price     = !empty($postData['price']) ? $postData['price'] : 0;
				$priceJson = !empty($postData['priceJson']) ? $postData['priceJson'] : '';
				if ($oldPrice < $price) {
					$this->dexit(['error' => 1, 'msg' => '现价不能大于原价']);
				}
				/*if (empty($postData['message_num'])) {
					$this->dexit(['error' => 1, 'msg' => '消息配额不能为空']);
				}
				if (empty($postData['sub_account_num'])) {
					$this->dexit(['error' => 1, 'msg' => '子账户数量不能为空']);
				}
				if (empty($postData['wechat_num']) || $postData['wechat_num'] > 5) {
					$this->dexit(['error' => 1, 'msg' => '企业微信数量不能为空或大于5']);
				}*/
				if (empty($priceJson)) {
					$this->dexit(['error' => 1, 'msg' => '请设置套餐档位价格']);
				}
				if (count($postData['authority']) == 0) {
					$this->dexit(['error' => 1, 'msg' => '请选择套餐权限']);
				}
				try {
					$result = Package::setPackage($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}

				$this->dexit($result);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//设置默认套餐
		public function actionSetDefaultPackage ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();

				if (empty($postData['package_id'])) {
					$this->dexit(['error' => 1, 'msg' => '套餐不能为空']);
				}

				try {
					$result = DefaultPackage::setDefaultPackage($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}

				$this->dexit($result);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//删除套餐
		public function actionDelPackage ()
		{
			if (\Yii::$app->request->isPost) {
				$packageId = \Yii::$app->request->post('id');
				if (empty($packageId)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$package = Package::findOne($packageId);
				if (empty($package)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$package->status = 3;
				$package->update();
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//菜单管理
		public function actionMenu ()
		{
			$menuLists = Menu::getMenuList(1, true, 1, 2);

			return $this->render('menu', ['menuLists' => $menuLists]);
		}

		//获取菜单详情
		public function actionGetMenu ()
		{
			$menuId = \Yii::$app->request->get('id');
			if (empty($menuId)) {
				$this->dexit(['error' => 1, 'msg' => '参数不正确']);
			}
			$menu = Menu::find()->where(['id' => $menuId])->asArray()->one();

			$this->dexit(['error' => 0, 'data' => $menu]);
		}

		//添加或者更新菜单
		public function actionSetMenu ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				try {
					$result = Menu::setMenu($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//修改菜单状态
		public function actionChangeMenuStatus ()
		{
			$menuId = \Yii::$app->request->get('id');
			if (empty($menuId)) {
				$this->dexit(['error' => 1, 'msg' => '参数不正确']);
			}
			$menu         = Menu::findOne($menuId);
			$menu->status = 1 - $menu->status;
			if (!$menu->save()) {
				$this->dexit(['error' => 1, 'msg' => '修改失败']);
			}
			$this->dexit(['error' => 0, 'msg' => '']);
		}

		//修改菜单排序
		public function actionChangeMenuSort ()
		{
			if (\Yii::$app->request->isPost) {
				$sortData = \Yii::$app->request->post('sort');
				$sortData = json_decode(stripslashes(htmlspecialchars_decode($sortData)));
				if (!empty($sortData)) {
					foreach ($sortData as $key => $parentData) {
						$parentSort = $key + 1;
						Menu::updateAll(['sort' => $parentSort, 'parent_id' => NULL, 'level' => 1], ['id' => $parentData->id]);
						if (isset($parentData->children)) {
							foreach ($parentData->children as $k => $child) {
								$childSort = $k + 1;
								Menu::updateAll(['sort' => $childSort, 'parent_id' => $parentData->id, 'level' => 2], ['id' => $child->id]);
							}
						}
					}
				}
			}
		}

		//菜单管理
		public function actionMethod ()
		{
			$pageSize      = \Yii::$app->request->post('pageSize') ?: 10;
			$search        = \Yii::$app->request->get('search', '');
			$methodType    = \Yii::$app->request->get('type', '');
			$searchControl = \Yii::$app->request->get('searchControl', '');

			$menuAction = MenuAction::find()->where(['status' => 1]);
			if ($search) {
				if (is_numeric($search)) {
					$menuAction = $menuAction->andWhere(['id' => $search]);
				} else {
					$menuAction = $menuAction->andWhere(['like', 'action', $search]);
				}
			}
			if ($methodType) {
				$menuAction = $menuAction->andWhere(['method' => $methodType]);
			}
			if ($searchControl) {
				$menuAction = $menuAction->andWhere(['control' => $searchControl]);
			}

			$count      = $menuAction->count();
			$pages      = new Pagination(['totalCount' => $count, 'pageSize' => $pageSize]);
			$menuAction = $menuAction->offset($pages->offset)->limit($pages->limit)->all();
			$allControl = MenuAction::find()->where(['status' => 1])->groupBy('control')->all();

			return $this->render('method', ['actionArr' => $menuAction, 'search' => $search, 'type' => $methodType, 'searchControl' => $searchControl, 'allControl' => $allControl, 'pages' => $pages]);
		}

		//添加或者更新方法
		public function actionSetAction ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				try {
					$result = MenuAction::setAction($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//删除方法
		public function actionDelAction ()
		{
			if (\Yii::$app->request->isPost) {
				$actionId = \Yii::$app->request->post('id');
				if (empty($actionId)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$menuAction         = MenuAction::findOne($actionId);
				$menuAction->status = 0;
				if (!$menuAction->save()) {
					$this->dexit(['error' => 1, 'msg' => '修改失败']);
				}
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//权限管理
		public function actionAuthority ()
		{
			$pageSize = \Yii::$app->request->post('pageSize') ?: 10;
			$search   = \Yii::$app->request->get('search', '');
			$look     = \Yii::$app->request->get('look', '');

			$authority = Authority::find()->where(['status' => 0]);
			if ($search) {
				$search = trim($search);
				if (is_numeric($search)) {
					$authority = $authority->andWhere(['id' => $search]);
				} else {
					$authority = $authority->andWhere(['like', 'name', $search]);
				}
			}
			$parent = '';
			$level  = 1;
			if ($look) {
				if (is_numeric($look)) {
					$authority = $authority->andWhere(['pid' => $look]);
					$auth      = Authority::findOne($look);
					$level     = $auth->level + 1;
					$parent    = $auth->name;
				}
			}

			$count     = $authority->count();
			$pages     = new Pagination(['totalCount' => $count, 'pageSize' => $pageSize]);
			$authority = $authority->offset($pages->offset)->limit($pages->limit)->orderBy('id desc')->all();

			return $this->render('authority', ['actionArr' => $authority, 'look' => $look, 'search' => $search, 'parent' => $parent, 'level' => $level, 'pages' => $pages]);
		}

		//添加或修改权限
		public function actionSetAuthority ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				try {
					$result = Authority::setAuthority($postData);
				} catch (InvalidDataException $e) {
					$result = ['error' => 1, 'msg' => $e->getMessage()];
				}
				$this->dexit($result);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		//删除权限
		public function actionDelAuthority ()
		{
			if (\Yii::$app->request->isPost) {
				$actionId = \Yii::$app->request->post('id');
				if (empty($actionId)) {
					$this->dexit(['error' => 1, 'msg' => '参数不正确']);
				}
				$menuAction         = Authority::findOne($actionId);
				$menuAction->status = 1;
				if (!$menuAction->save()) {
					$this->dexit(['error' => 1, 'msg' => '删除失败']);
				}
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		/**
		 * 获取区域
		 */
		public function actionGetDistrict ()
		{
			if (\Yii::$app->request->isPost) {
				$postData = \Yii::$app->request->post();
				$type     = $postData['type'];
				switch ($type) {
					case 'getAll':
						$leafId = $postData['leafId'];
						$level  = $postData['level'];
						if (isset($postData['nowCheck']) && !empty($postData['nowCheck']) && isset($postData['nowCheck']['province']) && $postData['nowCheck']['province'] > 0) {
							if (isset($postData['nowCheck']) && !empty($postData['nowCheck']) && isset($postData['nowCheck']['city']) && $postData['nowCheck']['city'] > 0) {
								$leafId = $postData['nowCheck']['city'];
							} else {
								$districtInfo = Area::findOne(['parent_id' => $postData['nowCheck']['province']]);
								$leafId       = $districtInfo->id;
							}
						}
						$districts = Area::getDistrict($leafId, $level);
						$res       = [];
						if (isset($districts['province']) && !empty($districts['province'])) {
							$provinceOption = '<option value="0">*请选择省</option>';
							foreach ($districts['province'] as $province) {
								if (isset($postData['nowCheck']) && !empty($postData['nowCheck']) && isset($postData['nowCheck']['province']) && $postData['nowCheck']['province'] == $province['id']) {
									$provinceOption .= '<option selected="selected" value="' . $province['id'] . '">' . $province['full_name'] . '</option>';
								} else {
									$provinceOption .= '<option value="' . $province['id'] . '">' . $province['full_name'] . '</option>';
								}
							}
							$res['province'] = $provinceOption;
						}
						if (isset($districts['city']) && !empty($districts['city'])) {
							$cityOption = '<option value="0">*请选择市</option>';
							foreach ($districts['city'] as $city) {
								if (isset($postData['nowCheck']) && !empty($postData['nowCheck']) && isset($postData['nowCheck']['city']) && $postData['nowCheck']['city'] == $city['id']) {
									$cityOption .= '<option selected="selected" value="' . $city['id'] . '">' . $city['full_name'] . '</option>';
								} else {
									$cityOption .= '<option value="' . $city['id'] . '">' . $city['full_name'] . '</option>';
								}
							}
							$res['city'] = $cityOption;
						}
						if (isset($districts['district']) && !empty($districts['district'])) {
							$districtOption = '<option value="">请选择地区</option>';
							foreach ($districts['district'] as $district) {
								$districtOption .= '<option value="' . $district['id'] . '">' . $district['full_name'] . '</option>';
							}
							$res['district'] = $districtOption;
						}
						break;
					case 'getNext':
						$nextId   = $postData['nextId'];
						$NextInfo = Area::findOne(['id' => $nextId]);
						if ($NextInfo->level == 2) {
							$res = Area::find()->where(['id' => $nextId, 'level' => 2])->asArray()->all();
						} else {
							$res = Area::find()->where(['parent_id' => $nextId, 'level' => 2])->asArray()->all();
						}

						if (isset($res) && !empty($res)) {
							$cityOption = '<option value="0">*请选择市</option>';
							foreach ($res as $city) {
								$cityOption .= '<option value="' . $city['id'] . '">' . $city['full_name'] . '</option>';

							}
							$res         = [];
							$res['city'] = $cityOption;
						}
						break;
					case 'withManager'://后台代理商列表中，当选择了区域经理后获取区域经理所管辖的省市

						break;
				}

				$this->dexit($res);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		/**
		 * 自动登录
		 */
		public function actionQuickLogin ()
		{
			$code     = \Yii::$app->request->get('code');
			$codeStr  = base64_decode($code);
			$codeArr  = explode('-', $codeStr);
			$uid      = $codeArr[1];
			$password = $codeArr[0];
			//$uid = \Yii::$app->request->get('uid');
			if (empty($uid)) {
				echo "<script>alert('参数不正确');</script>";
				exit;
			}
			$user = User::findOne(['uid' => $uid, 'password' => $password]);
			if (empty($user)) {
				echo "<script>alert('参数错误，请刷新页面后重试');</script>";
				exit;
			}

			$cache = \Yii::$app->cache;
			//生成随机码
			$string   = '0123456789abcdefghijklmnopqrstuvwxyz';
			$count    = strlen($string) - 1;
			$rand_num = '';
			for ($i = 0; $i < 10; $i++) {
				$rand_num .= $string[mt_rand(0, $count)];
			}

			$cache->set($rand_num, $uid, 5);

			//自动登录链接
			if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], Yii::$app->params['site_url']) !== false) {
				$url = Yii::$app->params['scrm_url'] . '/passLogin?code=' . $rand_num;
			} else {
				$url = Yii::$app->params['scrm_url'];
			}

			return $this->redirect($url);
		}

		/**
		 * 上传图片
		 */
		public function actionImgUpload ()
		{
			if (\Yii::$app->request->isPost) {
				if (!empty($_FILES)) {
					$flag = false;
					try {
						$saveDir                 = 'agent';
						$maxSize                 = 2 * 1024 * 1024;
						$allowExts               = ['jpg', 'png', 'jpeg'];
						$allowTypes              = ['image/jpeg', 'image/png', 'application/octet-stream'];
						$uploadFileUtil          = new UploadFileUtil();
						$uploadFileUtil->saveDir = $saveDir;//上传文件保存路径
						$uploadFileUtil->maxSize = $maxSize;//大小限制
						if (isset($allowExts)) {
							$uploadFileUtil->allowExts = $allowExts;
						}
						if (isset($allowTypes)) {
							$uploadFileUtil->allowTypes = $allowTypes;
						}
						$result = $uploadFileUtil->upload();
						if (empty($result)) {
							$flag = true;
							$this->dexit(['error' => 1, 'msg' => $uploadFileUtil->getErrorMsg()]);
						}
						$uploadFileList = $uploadFileUtil->getUploadFileList();
						$uploadInfo     = $uploadFileList[0];
						$local_path     = $uploadInfo['local_path'];

						$this->dexit(['error' => 0, 'msg' => 'OK', 'fileUrl' => $local_path]);
					} catch (\Exception $e) {
						if (!$flag) {
							$this->dexit(['error' => 1, 'msg' => '上传失败']);
						} else {
							$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
						}
					}
				}

				$this->dexit(['error' => 1, 'msg' => '数据错误']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '请求方式不正确']);
			}
		}

		public function actionPushCustom ()
		{
			return $this->render('custom');
		}

		public function actionCustom ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);

			$offset = !empty($_GET['offset']) ? $_GET['offset'] : 0;
			$limit  = !empty($_GET['limit']) ? $_GET['limit'] : 100;
			$sort   = !empty($_GET['sort']) ? $_GET['sort'] : 0;
			$lastId = !empty($_GET['last_id']) ? $_GET['last_id'] : 0;

			if ($lastId > 0) {
				if ($sort == 0) {
					$total = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['>', 'id', $lastId])->count();
				} else {
					$total = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['<', 'id', $lastId])->count();
				}
			} else {
				$total = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->count();
			}

			if ($sort == 0) {
				if ($lastId > 0) {
					$needPushData = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['>', 'id', $lastId])->orderBy('`id` ASC')->limit($limit)->all();
				} else {
					$needPushData = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['>', 'id', $lastId])->orderBy('`id` ASC')->limit($limit)->offset($offset)->all();
				}
			} elseif ($sort == 1) {
				if ($lastId > 0) {
					$needPushData = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['<', 'id', $lastId])->orderBy('`id` DESC')->limit($limit)->all();
				} else {
					$needPushData = WorkExternalContact::find()->where(['!=', 'external_userid', ''])->andWhere(['<', 'id', $lastId])->orderBy('`id` DESC')->limit($limit)->offset($offset)->all();
				}
			} else {
				$result = [
					'offset'  => 0,
					'limit'   => 0,
					'total'   => 0,
					'empty'   => 0,
					'last_id' => 0
				];

				echo json_encode($result);
				exit();
			}

			if (!empty($needPushData)) {
				$lastId = 0;
				/** @var WorkExternalContact $pushData */
				foreach ($needPushData as $pushData) {
					$con = $pushData;
					try {
						if (!empty($con->corp_id)) {
							$workApi = WorkUtils::getWorkApi($con->corp_id, 1);
						}
						if (!empty($workApi)) {
							if (!empty($con->external_userid)) {
								$externalUserInfo    = $workApi->ECGet($con->external_userid);
								$externalContactInfo = SUtils::Object2Array($externalUserInfo);
							}
							if (isset($externalContactInfo['follow_user']) && !empty($externalContactInfo['follow_user'])) {
								foreach ($externalContactInfo['follow_user'] as $user) {
									try {
										$followUser = WorkExternalContactFollowUser::findOne(['userid' => $user['userid'], 'external_userid' => $con->id]);
										if (empty($followUser->nickname) && !empty($user['remark'])) {
											$followUser->nickname = $user['remark'];
										}
										if (empty($followUser->remark_corp_name) && !empty($user['remark_corp_name'])) {
											$followUser->remark_corp_name = $user['remark_corp_name'];
										}
										if (empty($followUser->remark_mobiles) && !empty($user['remark_mobiles'])) {
											$followUser->remark_mobiles = $user['remark_mobiles'];
										}
										$followUser->save();
										if (!empty($user['tags'])) {
											foreach ($user['tags'] as $tag) {
												if ($tag['type'] == 1) {
													$workTagGroup = WorkTagGroup::findOne(['corp_id' => $con->corp_id, 'group_name' => $tag['group_name'], 'type' => 0]);
													if (!empty($workTagGroup)) {
														$workTag = WorkTag::findOne(['corp_id' => $con->corp_id, 'tagname' => $tag['tag_name'], 'group_id' => $workTagGroup->id, 'is_del' => 0]);
														if (!empty($workTag)) {
															$workTagFollow = WorkTagFollowUser::findOne(['tag_id' => $workTag->id, 'follow_user_id' => $followUser->id]);
															if (empty($workTagFollow)) {
																try {
																	$workTagFollow                 = new WorkTagFollowUser();
																	$workTagFollow->tag_id         = $workTag->id;
																	$workTagFollow->follow_user_id = $followUser->id;
																	$workTagFollow->corp_id        = $con->corp_id;
																	$workTagFollow->status         = 1;
																	if (!$workTagFollow->validate() || !$workTagFollow->save()) {
																		\Yii::error(SUtils::modelError($workTagFollow), 'tagMessage');
																	}
																} catch (\Exception $e) {
																	\Yii::error($e->getMessage(), 'message');
																}
															} else {
																$workTagFollow->status = 1;
																$workTagFollow->save();
															}
														}
													}
												} else {
													$workPerTag = WorkPerTagFollowUser::findOne(['corp_id' => $con->corp_id, 'group_name' => $tag['group_name'], 'tag_name' => $tag['tag_name'], 'follow_user_id' => $followUser->id]);
													if (empty($workPerTag)) {
														$workPerTag                 = new WorkPerTagFollowUser();
														$workPerTag->group_name     = $tag['group_name'];
														$workPerTag->tag_name       = $tag['tag_name'];
														$workPerTag->follow_user_id = $followUser->id;
														$workPerTag->corp_id        = $con->corp_id;
														$workPerTag->status         = 1;
														if (!$workPerTag->validate() || !$workPerTag->save()) {
															\Yii::error(SUtils::modelError($workPerTag), '$workPerTag');
														}
													} else {
														$workPerTag->status = 1;
														$workPerTag->save();
													}
												}
											}
										}
									} catch (\Exception $e) {
										\Yii::error($e->getMessage(), 'workExternalContactGet');
									}
								}
							}
						}

					} catch (\Exception $e) {
						\Yii::error($e->getMessage(), 'workExternalContactGet');
					}

					$lastId = $pushData->id;
				}
			}

			$empty = ($total - $limit) > 0 ? $total - $limit : 0;

			$result = [
				'offset'  => $offset + $limit,
				'limit'   => $limit,
				'total'   => $total,
				'empty'   => $empty,
				'last_id' => $lastId,
			];

			echo json_encode($result);
			exit();
		}

		public function actionSetting ()
		{
			$this->render('setting');
		}

		public function actionProviderSetting ()
		{
			if (Yii::$app->request->isPost) {
				$providerCorpid = Yii::$app->request->post('provider_corpid', '');
				$providerSecret = Yii::$app->request->post('provider_secret', '');
				$token          = Yii::$app->request->post('token', '');
				$encodeAesKey   = Yii::$app->request->post('encode_aes_key', '');

				if (empty($providerCorpid) || empty($providerSecret) || empty($token) || empty($encodeAesKey)) {
					return Json::encode([
						'error'     => 4001,
						'error_msg' => '缺少必要参数'
					], JSON_UNESCAPED_UNICODE);
				}

				$providerId = Yii::$app->request->post('id', 0);


			} elseif (Yii::$app->request->isGet) {
				$proSetting = [
					'id'              => 1,
					'provider_corpid' => '',
					'provider_secret' => '',
					'token'           => '',
					'encode_aes_key'  => '',
					'status'          => 0,
					'create_time'     => '',
				];
				$proConfig  = WorkProviderConfig::find()->one();
				if (!empty($proConfig)) {
					$proSetting = $proConfig->dumpMiniData();
				}

				return Json::encode($proSetting, JSON_UNESCAPED_UNICODE);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许');
			}
		}
	}
