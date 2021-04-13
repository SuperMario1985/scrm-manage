<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/9/16
	 * Time: 11:03
	 */

	namespace app\modules\api\controllers;

	use app\models\Menu;
	use app\models\Package;
	use app\models\PackageMenu;
	use app\models\SubUserAuthority;
	use app\models\WorkCorp;
	use app\models\WxAuthorizeInfo;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;

	class MenuController extends AuthBaseController
	{
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'get-menu-list' => ['POST'],
					],
				],
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/menu/
		 * @title           获取菜单列表
		 * @description     获取菜单列表
		 * @method   post
		 * @url  http://{host_name}/api/menu/get-menu-list
		 *
		 * @param level      可选 int 菜单等级，默认1
		 * @param type       可选 int 类型，默认0公众号，1企业微信
		 * @param sub_id     可选 int 子账户的id
		 * @param account_id 可选 int 公众号或企业微信id
		 *
		 * @return          {"error":0,"data":[[{"id":1,"title":"运营中心","icon":"home","key":"home","link":"home","level":1,"sort":1,"is_new":0,"is_hot":0,"status":1,"comefrom":2,"children":[]},{"loop":"……"}],[{"id":35,"title":"运营中心","icon":"home","key":"operationCenter","link":"operationCenter","level":1,"sort":7,"is_new":0,"is_hot":0,"status":0,"comefrom":2,"children":[]},{"loop":"……"}]]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    package_name string 套餐名称
		 * @return_param    package_endtime string 套餐到期时间
		 * @return_param    key int 0：公众号；1：企业微信
		 * @return_param    title string 标题
		 * @return_param    icon string 图标
		 * @return_param    key string key
		 * @return_param    link string 地址
		 * @return_param    level int 等级
		 * @return_param    sort int 排序
		 * @return_param    is_new int 是否为新菜单
		 * @return_param    is_hot int 是否为热门菜单
		 * @return_param    status int 菜单状态：0：隐藏、1：显示
		 * @return_param    comefrom int 菜单归属：0公众号、1企业微信、2公共
		 * @return_param    packageHas int 套餐是否有权限1是0否
		 * @return_param    children array 子菜单列表
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/11 14:28
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetMenuList ()
		{
			if (\Yii::$app->request->isPost) {
				$level    = \Yii::$app->request->post('level');
				$sub_id   = \Yii::$app->request->post('sub_id') ?: 0;
				$isMasterAccount   = \Yii::$app->request->post('isMasterAccount') ?: 1;//1主账户 2子账户
				$wx_id    = \Yii::$app->request->post('wx_account_id') ?: 0;
				$work_id  = \Yii::$app->request->post('work_account_id') ?: 0;
				$level    = !empty($level) ? $level : 1;
				$menuList = [
					0 => [],
					1 => [],
				];
				if($isMasterAccount==1){
					$sub_id = 0;
				}
				if(!empty($wx_id)){
					$wxAuth = WxAuthorizeInfo::findOne(['user_name' => $wx_id]);
					$wx_id  = $wxAuth->author_id;
				}
				if(!empty($work_id)){
					$wxCorp = WorkCorp::findOne(['corpid' => $work_id]);
					$work_id = $wxCorp->id;
				}
				$menuList[0] = $this->develop ? Menu::getMenuList($level, true, true, 0, $sub_id, $wx_id) : Menu::getMenuList($level, true, false, 0, $sub_id, $wx_id);
				$menuList[1] = $this->develop ? Menu::getMenuList($level, true, true, 1, $sub_id, $work_id) : Menu::getMenuList($level, true, false, 1, $sub_id, $work_id);

				//套餐权限 是否有功能权限字段添加
				$packageId       = $this->user->package_id;
				$endTime         = $this->user->end_time;
				$package_endtime = !empty($endTime) ? date('Y-m-d', $endTime) : '';
				if ($endTime < time()) {
					//到期使用套餐
					$defaultPackage = Package::getDefaultPackage();
					if ($defaultPackage->expire_type == 2) {
						$packageId       = $defaultPackage->expire_package_id;
						$package_endtime = '永久有效';
					}
				}
				$package      = Package::findOne($packageId);
				$package_name = '';
				if(!empty($package)){
					$package_name = $package->name;
                    $retPackage = $package->toArray(['fission_num','lottery_draw_num','red_envelopes_num']);
				}else{
                    $retPackage = ['fission_num'=>0,'lottery_draw_num'=>0,'red_envelopes_num'=>0];
                }

				$packageMenu   = PackageMenu::find()->where(['package_id' => $packageId, 'status' => 1])->all();
				$packageMenuId = [];
				foreach ($packageMenu as $v) {
					array_push($packageMenuId, $v['menu_id']);
				}
				foreach ($menuList as $k => $menu) {
					foreach ($menu as $kk => $mv) {
						if (!in_array($mv['id'], $packageMenuId)) {
							if (!empty($mv['children'])) {
								$packageHas   = 0;
								$childrenData = [];
								foreach ($mv['children'] as $kkk => $vvv) {
									if (in_array($vvv['id'], $packageMenuId)) {
										$packageHas = 1;
										array_push($childrenData, $vvv);
									}
								}
								if ($packageHas == 1) {
									$menu[$kk]['children'] = $childrenData;
								} else {
									unset($menu[$kk]);
								}
							} else {
								unset($menu[$kk]);
							}
						}
					}
					$menu = array_values($menu);
					$menuList[$k] = $menu;
				}

				return [
					'menuList'        => $menuList,
					'package_name'    => $package_name,
					'package_endtime' => $package_endtime,
                    'package'         => $retPackage,
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}