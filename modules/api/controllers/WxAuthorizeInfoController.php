<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/9/16
	 * Time: 11:03
	 */

	namespace app\modules\api\controllers;

	use app\models\Authority;
	use app\models\MaterialPullTime;
	use app\models\SubUserAuthority;
	use app\models\UserAuthorRelation;
	use app\models\WxAuthorize;
	use app\models\WxAuthorizeInfo;
	use app\models\Fans;
	use app\models\User;
	use app\models\Package;
	use app\modules\api\components\AuthBaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;
	use app\components\InvalidParameterException;
	use callmez\wechat\sdk\Wechat;

	class WxAuthorizeInfoController extends AuthBaseController
	{
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'get-authrize-info' => ['POST'],
						'get-industry'      => ['GET'],
					],
				],
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wx-authorize-info/
		 * @title           获取所有的公众号或者小程序
		 * @description     获取所有的公众号或者小程序
		 * @method   post
		 * @url  http://{host_name}/api/wx-authorize-info/get-authrize-info
		 *
		 * @param uid 可选 int 登录账号id
		 * @param sub_id 可选 int 当前登录员工id
		 * @param auth_status 可选 int 认证状态1已认证2未认证
		 * @param account_type 可选 int 账号类型1订阅号2服务号
		 * @param nick_name 可选 int 公众号名称
		 * @param is_page 可选 int 是否需要分页：0不需要，1需要
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 * @param type 可选 int 获取公众号还是小程序：1、公众号；2、小程序（默认：1）
		 *
		 * @return          {"error":0,"data":{"count":"2","info":[{"id":2,"wx_id":"gh_a5a2b5c4f175","nick_name":"小猪的智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/qbvaL9taELsfibgnbr0jBxaiayVy2GNE3HY0SrusXbQmVeBBpDzFF8VOibSBCshTIu6lPX5O10UBNwZBibGRicqGo6WkWPccHJZaV/0","service_type":2,"verify_type":"已认证","fans_num":"468","active_fans":"1","create_time":"2019-11-26 11:51:51","industry":"IT科技|互联网|电子商务；其他|其他","authorizer_type":"authorized","authorizer_type_name":"已授权"},{"loop":"……"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总公众号个数
		 * @return_param    canAdd int 是否可添加公众号
		 * @return_param    info array 公众号详细信息
		 * @return_param    id int 公众号编号
		 * @return_param    wx_id string 公众号原始ID
		 * @return_param    nick_name string 公众号名称
		 * @return_param    head_img string 公众号头像
		 * @return_param    service_type int 类型
		 * @return_param    verify_type string 认证状态
		 * @return_param    fans_num int 粉丝数
		 * @return_param    active_fans int 互动粉丝数
		 * @return_param    create_time string 创建时间
		 * @return_param    industry string 行业
		 * @return_param    authorizer_type string 授权状态:<br/>authorized：已授权；<br/>updateauthorized：更新授权；<br/>unauthorized：取消授权
		 * @return_param    authorizer_type_name string 授权信息
		 * @return_param    pull_time string 素材拉取时间，传material_type时才有
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/11 14:58
		 * @number          1
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAuthrizeInfo ()
		{
			if (\Yii::$app->request->isPost) {
				try {
					if (empty(\Yii::$app->request->post('uid'))) {
						throw new InvalidParameterException('参数不正确！');
					}
					$sub_id          = \Yii::$app->request->post('sub_id');
					$uid             = \Yii::$app->request->post('uid'); //登录账号id
					$auth_status     = \Yii::$app->request->post('auth_status'); //认证状态 1 已认证 2 未认证
					$account_type    = \Yii::$app->request->post('account_type'); //账号类型  1 订阅号 2 服务号
					$nick_name       = \Yii::$app->request->post('nick_name'); //公众号名称
					$is_page         = \Yii::$app->request->post('is_page', 0); //是否需要分页：0不需要，1需要
					$page            = \Yii::$app->request->post('page'); //分页
					$pageSize        = \Yii::$app->request->post('pageSize'); //分页
					$material_type   = \Yii::$app->request->post('material_type'); //素材类型
					$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;//1主账户 2子账户
					$fromContent     = \Yii::$app->request->post('is_from_content') ?: 0;//1 代表来自内容引擎
					$type            = \Yii::$app->request->post('type', WxAuthorizeInfo::AUTH_TYPE_APP);//0 代表公众号 1代表小程序
					$page            = !empty($page) ? $page : 1;
					$pageSize        = !empty($pageSize) ? $pageSize : 10;
					$offset          = ($page - 1) * $pageSize;
					$authData        = WxAuthorizeInfo::find()->alias('wxau');
					$authData        = $authData->leftJoin('{{%user_author_relation}} re', '`wxau`.`author_id` = `re`.`author_id`');
					$showAccount = 0;//是否显示头部公众号
					if ($isMasterAccount == 1) {
						$sub_id = 0;
					}
					$authData        = $authData->andWhere(['re.uid' => $uid, 'wxau.auth_type' => $type]);
					if (!empty($sub_id) && empty($fromContent)) {
						$sub_auth = SubUserAuthority::find()->andWhere(['sub_user_id' => $sub_id, 'type' => 1])->andWhere(['<>', 'authority_ids', ''])->asArray()->all();
						if (empty($sub_auth)) {
							return [
								'count' => 0,
								'info'  => [],
							];
						} else {
							$wx_account_id = array_column($sub_auth, 'wx_id');
							$authData      = $authData->andWhere(['in', 'wxau.author_id', $wx_account_id]);
							foreach ($sub_auth as $auth) {
								$authorityIds = explode(',', $auth['authority_ids']);
								$routes       = Authority::find()->where(['in', 'id', $authorityIds])->asArray()->all();
								$routes       = array_column($routes, 'route');
								if (in_array('miniMsg', $routes) || in_array('mini', $routes)) {
									$showAccount = 1;
								}
							}
						}
					}
					if ($auth_status == 1) {
						$authData = $authData->andWhere(['>=', 'wxau.verify_type_info', 0]);
					} elseif ($auth_status == 2) {
						$authData = $authData->andWhere(['wxau.verify_type_info' => -1]);
					}
					if ($account_type == 1) {
						$authData = $authData->andWhere(['<', 'wxau.service_type_info', 2]);
					} elseif ($account_type == 2) {
						$authData = $authData->andWhere(['wxau.service_type_info' => 2]);
					}
					if (!empty($nick_name)) {
						$authData = $authData->andWhere(['like', 'wxau.nick_name', $nick_name]);
					}
					$count = $authData->count();
					if($count>0){
						$showAccount = 1;
					}
					if (!empty($is_page)) {
						$authData = $authData->limit($pageSize)->offset($offset);
					}
					$info      = $authData->orderBy(['wxau.id' => SORT_ASC])->all();
					$result    = [];
					$last_time = time() - 172800;
					if (!empty($info)) {
						foreach ($info as $key => $val) {
							$result[$key]['id']           = $val->author_id;
							$result[$key]['key']          = $val->author_id;
							$result[$key]['wx_id']        = $val->user_name;
							$result[$key]['nick_name']    = $val->nick_name;
							$result[$key]['head_img']     = $val->head_img;
							$result[$key]['wx_id']        = $val->user_name;
							$result[$key]['service_type'] = $val->service_type_info;
//							if($val->service_type_info == 2){
//								$result[$key]['service_type'] = '服务号';
//							}else{
//								$result[$key]['service_type'] = '订阅号';
//							}
							if (!in_array($val->verify_type_info, [-1, 1, 2])) {
								$result[$key]['verify_type'] = '已认证';
							} else {
								$result[$key]['verify_type'] = '未认证';
							}
							//获取粉丝数
							$fans_num = Fans::find()->andWhere(['subscribe' => 1])->andWhere(['author_id' => $val->author_id])->count();
							//互动粉丝数
							$active_num                  = Fans::find()->andWhere(['subscribe' => 1])->andWhere(['author_id' => $val->author_id])->andWhere(['>', 'last_time', $last_time])->count();
							$result[$key]['fans_num']    = $fans_num;
							$result[$key]['active_fans'] = $active_num;
							$result[$key]['create_time'] = $val->create_time;
							if (empty($val->industry) && $val->author->authorizer_type != WxAuthorize::AUTH_TYPE_UNAUTH) {
								$appid       = $val->authorizer_appid;
								$wxAuthorize = WxAuthorize::getTokenInfo($appid, false, true);
								if (!empty($wxAuthorize)) {
									$wechat = \Yii::createObject([
										'class'          => Wechat::className(),
										'appId'          => $appid,
										'appSecret'      => $wxAuthorize['config']->appSecret,
										'token'          => $wxAuthorize['config']->token,
										'componentAppId' => $wxAuthorize['config']->appid,
									]);
								}
								$res = $wechat->getTemplateIndustry();
								if (!empty($res) && !isset($res['errcode'])) {
									foreach ($res as $industryInfo) {
										$val->industry .= $industryInfo['first_class'] . "|" . $industryInfo['second_class'] . '；';
									}

									$val->industry = rtrim($val->industry, '；');
									$val->save();
								}
							}
							$result[$key]['industry'] = $val->industry;

							$result[$key]['authorizer_type']      = $val->author->authorizer_type;
							$result[$key]['authorizer_type_name'] = $val->author->getAuthorType();
							if (!empty($material_type)) {
								//附件类型和素材类型做转换
								if ($material_type == 1) {
									$material_type = 2;
								} elseif ($material_type == 2) {
									$material_type = 3;
								} elseif ($material_type == 3) {
									$material_type = 4;
								} elseif ($material_type == 4) {
									$material_type = 1;
								}
								$pullTime = MaterialPullTime::find()->where(['author_id' => $val->author_id, 'material_type' => $material_type])->orderBy('pull_time desc')->one();
								if (!empty($pullTime)) {
									$result[$key]['pull_time'] = $pullTime->pull_time;
								} else {
									$result[$key]['pull_time'] = '';
								}
							}

						}
					}

					//添加公众号数量限制
					$canAdd       = 1;//是否可添加公众号
					$user         = User::findOne($uid);
					$accountCount = UserAuthorRelation::find()->where(['uid' => $uid])->count();
					if ($user->limit_author_num > 0) {
						if ($accountCount >= $user->limit_author_num) {
							$canAdd = 0;
						}
					}else{
						if ($accountCount >= \Yii::$app->params['default_author_num']){
							$canAdd = 0;//默认5个
						}
					}
					/*//套餐限制数量
					$canAdd       = 1;//是否可添加公众号
					$packageLimit = Package::packageLimitNum($uid, 'account_num');
					if ($packageLimit > 0) {
						$accountCount = UserAuthorRelation::find()->where(['uid' => $uid])->count();
						if ($accountCount >= $packageLimit) {
							$canAdd = 0;
						}
					}*/

					return [
						'count'         => $count,
						'canAdd'        => $canAdd,
						'is_hide_phone' => $user->is_hide_phone,
						'info'          => $result,
						'showAccount'   => $showAccount,
					];
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wx-authorize-info/
		 * @title           刷新公众号
		 * @description     刷新公众号信息
		 * @method   POST
		 * @url  http://{host_name}/api/wx-authorize-info/refresh-authorize
		 *
		 * @param wx_id 必选 string 公众号ID
		 * @param refresh_id 可选 string 刷新的公众号ID（为空时，默认刷新选中的公众号）
		 *
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/3/23 16:46
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\NotAllowException
		 */
		public function actionRefreshAuthorize ()
		{
			if (\Yii::$app->request->isPost) {
				$refreshId = \Yii::$app->request->post('refresh_id', '');
				if (empty($this->user) || (empty($this->wxAuthorInfo) && empty($refreshId))) {
					throw new InvalidParameterException('参数不正确！');
				}

				$refreshId = \Yii::$app->request->post('refresh_id', '');
				if (!empty($refreshId)) {
					$wxAuthorizeInfo = WxAuthorizeInfo::findOne(['user_name' => $refreshId]);
					if (empty($wxAuthorizeInfo)) {
						throw new InvalidParameterException('参数不正确！');
					}

					$userWxRelation = UserAuthorRelation::findOne(['uid' => $this->user->uid, 'author_id' => $wxAuthorizeInfo->author_id]);
					if (empty($userWxRelation)) {
						throw new InvalidParameterException('参数不正确！');
					}

					$authAppid = $wxAuthorizeInfo->authorizer_appid;
				} else {
					$authAppid = $this->wxAuthorInfo->authorizer_appid;
				}

				$authorizerInfo = WxAuthorizeInfo::getAuthorizerInfo($authAppid, $this->user->uid, true);

				if (!empty($authorizerInfo)) {
					return true;
				}

				return false;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		public function actionGetAccountList ()
		{
			if (\Yii::$app->request->isPost) {
				$result = [
					'wx'   => [],
					'mini' => []
				];

				$wxAuthorId = [];//当前公众号的author_id
				$subId      = \Yii::$app->request->post('sub_id');
				if (!empty($subId)) {
					$subAuth    = SubUserAuthority::find()->andWhere(['sub_user_id' => $subId, 'type' => 1])->andWhere(['<>', 'authority_ids', ''])->asArray()->all();
					$wxAuthorId = array_column($subAuth, 'wx_id');
				}
				$userAuthorRelations = $this->user->userAuthorRelations;
				if (!empty($userAuthorRelations)) {
					foreach ($userAuthorRelations as $relation) {
						if ($relation->author->authorizer_type != WxAuthorize::AUTH_TYPE_UNAUTH) {
							$author = $relation->author->wxAuthorizeInfo;
							if (!empty($wxAuthorId) && !in_array($relation->author_id, $wxAuthorId)) {
								continue;
							}
							$data              = [];
							$data['id']        = $author->user_name;
							$data['avatar']    = $author->head_img;
							$data['name']      = $author->nick_name;
							$data['fans_list'] = Fans::getActiveFans($relation->author->author_id);

							if ($relation->author->auth_type == WxAuthorize::AUTH_TYPE_APP) {
								array_push($result['wx'], $data);
							} elseif ($relation->author->auth_type == WxAuthorize::AUTH_TYPE_MINI_APP) {
								array_push($result['mini'], $data);
							}
						}
					}
				}

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wx-authorize-info/
		 * @title           批量更新权限接口
		 * @description     批量更新权限接口
		 * @method   post
		 * @url  http://{host_name}/api/wx-authorize-info/update-account-mini
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/4/26 10:46
		 * @number          0
		 *
		 */
		public function actionUpdateAccountMini ()
		{
			$authority = Authority::find()->where(['pid' => 1])->andWhere(['<>', 'name', '公众号'])->andWhere(['<>', 'name', '小程序'])->all();
			$account   = Authority::findOne(['pid' => 1, 'name' => '公众号']);
			if (!empty($authority)) {
				foreach ($authority as $auth) {
					$auth->pid = $account->id;
					$auth->save();
				}
			}
		}

	}