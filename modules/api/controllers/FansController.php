<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/9/16
	 * Time: 11:38
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\Authority;
	use app\models\Fans;
	use app\models\FansTags;
	use app\models\FansTimeLine;
	use app\models\Scene;
	use app\models\Tags;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WxAuthorize;
	use app\models\SubUserAuthority;
	use app\models\CustomField;
	use app\models\WorkUser;
	use app\models\UserProfile;
	use app\models\SubUserProfile;
	use app\models\WorkExternalContactFollowRecord;
	use app\modules\api\components\AuthBaseController;
	use app\queue\SyncFansListJob;
	use app\util\SUtils;
	use app\util\DateUtil;
	use phpDocumentor\Reflection\Types\Null_;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;
	use moonland\phpexcel\Excel;

	class FansController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'refresh-user-list' => ['POST'],
						'get-user-list'     => ['POST'],
						'msg-list'          => ['POST'],
						'get-scene'         => ['POST'],
						'fans-export'       => ['POST'],
						'check-refresh'     => ['POST'],
						'give-user-tags'    => ['POST'],
					]
				]
			]);
		}

		/**
		 * @param $action
		 *
		 * @return bool
		 * @throws InvalidParameterException
		 * @throws \yii\web\BadRequestHttpException
		 */
		public function beforeAction ($action)
		{
			return parent::beforeAction($action);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           刷新粉丝列表
		 * @description     刷新粉丝列表
		 * @method   post
		 * @url  http://{host_name}/api/fans/refresh-user-list
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param next_openid 可选 string 开始的用户ID
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/11 14:56
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		public function actionRefreshUserList ()
		{
			if (\Yii::$app->request->isPost) {
				ignore_user_abort();
				set_time_limit(0);
				$nextOpenid = \Yii::$app->request->post('next_openid');

				$result = [
					'error' => 0
				];

				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$cacheKey     = 'refresh_user_' . $this->wxAuthorInfo->user_name;
				$currentYmd   = DateUtil::getCurrentYMD();
				$refreshCache = \Yii::$app->cache->get($cacheKey);
				if (empty($refreshCache) || empty($refreshCache[$currentYmd])) {
					$refreshCache = [
						$currentYmd => [
							'refresh'           => 0,
							'last_refresh_time' => 0,
						]
					];
				}

				//  每日请求次数验证 最多三次
				if ($refreshCache[$currentYmd]['refresh'] > 2) {
//					throw new NotAllowException('今日请求已达上限！');
				}

				//  两次请求时间间隔验证 间隔两小时
				if (($refreshCache[$currentYmd]['last_refresh_time'] + 2 * 60 * 60) > time()) {
//					throw new NotAllowException('距离上次请求时间不足两小时！');
				}

				++$refreshCache[$currentYmd]['refresh'];
				$refreshCache[$currentYmd]['last_refresh_time'] = time();
				\Yii::$app->cache->set($cacheKey, $refreshCache);

				$jobId = \Yii::$app->queue->push(new SyncFansListJob([
					'wxAuthorInfo' => $this->wxAuthorInfo,
					'nextOpenid'   => $nextOpenid,
				]));

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           获取粉丝列表
		 * @description     获取粉丝列表
		 * @method   post
		 * @url  http://{host_name}/api/fans/get-user-list
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param fans_id 可选 array 选中的粉丝ID
		 * @param sort 可选 sort 排序1正序2倒序
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param tag_ids 可选 string 标签值（多标签用,分开，无标签为0）
		 * @param sex 可选 int 性别（1：男；2、女；3、未知）
		 * @param source 可选 string 来源
		 * @param province 可选 string 省份
		 * @param city 可选 string 城市
		 * @param s_time 可选 string 开始时间
		 * @param e_time 可选 string 结束时间
		 * @param last_s_time 可选 string 最后活跃开始时间
		 * @param last_e_time 可选 string 最后活跃结束时间
		 * @param keyword 可选 string 粉丝昵称或者备注
		 * @param is_kf 可选 int 是否是从客服来源
		 * @param scene_id 可选 int 渠道二维码id
		 * @param is_custom 可选 int 是否是客户1是2否
		 *
		 * @return          {"error":0,"data":{"count":"182","fans":[{"fans_id":1,"key":1,"openid":"ogw9Mw_ycy1FFwxylgBNW2rrudL8","nickname":"小猪%26云美来%26轻晓云%26nbsp;陈允","remark":"","sex":1,"country":"中国","province":"安徽","city":"合肥","language":"zh_CN","headerimg":"http://thirdwx.qlogo.cn/mmopen/ajNVdqHZLLAu6gQTptC7hVhtvvyXlvJFpD79V04OSqBXgIcEfLBtZeNVV1ULSAfFBgTdicI9I7ZhLqaiaOSuRVaw/132","subscribe_time":"1484124173","last_time":"1484124173","unionid":"osVLA1cpkGdIVFPi_c5TP19VN8-E","subscribe_scene":"ADD_SCENE_OTHERS","subscribe_scene_str":"其他","subscribe_time_str":"2017-01-11 16:42:53","subscribe_day":1007,"interact_nums":"0","last_time_str":"2017-01-11 16:42:53","tags_info":[{"tag_id":1,"name":"星标组","count":175},{"loop":"……"}]},{"loop":"……"}],"keys":[1,2,3]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 满足条件的粉丝数
		 * @return_param    fans array 粉丝数据
		 * @return_param    fans_id int 粉丝ID
		 * @return_param    key int 粉丝ID
		 * @return_param    openid string 粉丝唯一ID
		 * @return_param    nickname string 昵称
		 * @return_param    remark string 备注
		 * @return_param    sex int 性别、1：男；2、女；3、未知
		 * @return_param    country string 国家
		 * @return_param    province string 省份
		 * @return_param    city string 城市
		 * @return_param    language string 语言
		 * @return_param    headerimg string 头像
		 * @return_param    subscribe_time string 关注时间戳
		 * @return_param    last_time string 最后活跃时间戳
		 * @return_param    unionid string 平台唯一ID
		 * @return_param    subscribe_scene string 来源
		 * @return_param    subscribe_scene_str string 来源描述
		 * @return_param    subscribe_time_str string 关注时间
		 * @return_param    subscribe_day int 关注时长
		 * @return_param    interact_nums string 互动次数
		 * @return_param    last_time_str string 最后活跃时间
		 * @return_param    tags_info array 标签详情
		 * @return_param    tag_id int 标签ID
		 * @return_param    name string 标签名称
		 * @return_param    count int 标签粉丝数
		 * @return_param    keys array 所有的粉丝ID
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/11 15:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetUserList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page        = \Yii::$app->request->post('page') ?: 1;
				$sort        = \Yii::$app->request->post('sort') ?: 1;
				$pageSize    = \Yii::$app->request->post('page_size') ?: 15;
				$isCondition = \Yii::$app->request->post('is_condition') ?: 0;
				$is_custom   = \Yii::$app->request->post('is_custom') ?: 0;
				$offset      = ($page - 1) * $pageSize;

				$authorInfo = $this->wxAuthorInfo->author;
				if (!empty($authorInfo)) {
					$groupBy = '';
//					$having  = '';

					$fansData = Fans::find()->alias('f')->leftJoin('{{%fans_tags}} ft', '`ft`.`fans_id` = `f`.`id`');

					// 标签
					if (!empty(\Yii::$app->request->post('tag_ids')) || \Yii::$app->request->post('tag_ids') === (string) Tags::NO_TAGS) {
						$tagIds = explode(',', \Yii::$app->request->post('tag_ids'));

						if (!in_array(0, $tagIds)) {
							$fansData = $fansData->where(['and', ['f.author_id' => $authorInfo->author_id], ['in', 'ft.tags_id', $tagIds]]);

							$groupBy = 'f.id';
//							$having  = 'count(f.id) >=' . count($tagIds);
						} else {
							$keyList = array_keys($tagIds, 0);
							unset($tagIds[$keyList[0]]);

							if (!empty($tagIds)) {
								$fansData = $fansData->where(['and', ['f.author_id' => $authorInfo->author_id], ['or', ['f.tagid_list' => '[]'], ['in', 'ft.tags_id', $tagIds]]]);

								$groupBy = 'f.id';
//								$having  = '(count(ft.id) = 0 or count(f.id) >= ' . count($tagIds) . ')';
							} else {
								$fansData = $fansData->where(['f.author_id' => $authorInfo->author_id, 'f.tagid_list' => '[]']);
							}
						}
					} else {
						$fansData = $fansData->where(['f.author_id' => $authorInfo->author_id]);
					}

					// 性别
					if (!empty(\Yii::$app->request->post('sex')) || \Yii::$app->request->post('sex') === (string) Fans::SEX_UNKNOW) {
						$sex = \Yii::$app->request->post('sex');
						if ($sex == 3) {
							$sex = 0;
						}
						$fansData = $fansData->andWhere(['f.sex' => $sex]);
					}

					// 粉丝来源
					if (!empty(\Yii::$app->request->post('source'))) {
						$fansData = $fansData->andWhere(['f.subscribe_scene' => \Yii::$app->request->post('source')]);
					}

					// 地域（省份）
					if (!empty(\Yii::$app->request->post('province'))) {
						$fansData = $fansData->andWhere(['f.province' => \Yii::$app->request->post('province')]);
					}

					// 地域（市）
					if (!empty(\Yii::$app->request->post('city'))) {
						$fansData = $fansData->andWhere(['f.city' => \Yii::$app->request->post('city')]);
					}

					// 选中的粉丝
					if (!empty(\Yii::$app->request->post('fans_id'))) {
						$fansData = $fansData->andWhere(['f.id' => \Yii::$app->request->post('fans_id')]);
					}

					// 关注开始时间
					if (!empty(\Yii::$app->request->post('s_time'))) {
						$fansData = $fansData->andWhere(['>=', 'f.subscribe_time', \Yii::$app->request->post('s_time')]);
					}

					// 关注结束时间
					if (!empty(\Yii::$app->request->post('e_time'))) {
						$fansData = $fansData->andWhere(['<=', 'f.subscribe_time', \Yii::$app->request->post('e_time')]);
					}

					// 最后活跃开始时间
					if (!empty(\Yii::$app->request->post('last_s_time'))) {
						$fansData = $fansData->andWhere(['>=', 'f.last_time', \Yii::$app->request->post('last_s_time')]);
					}

					// 最后活跃结束时间
					if (!empty(\Yii::$app->request->post('last_e_time'))) {
						$fansData = $fansData->andWhere(['<=', 'f.last_time', \Yii::$app->request->post('last_e_time')]);
					}

					// 粉丝昵称或者备注
					if (!empty(\Yii::$app->request->post('keyword'))) {
						$keyword  = \Yii::$app->request->post('keyword');
						$keyword  = trim($keyword);
						$fansData = $fansData->andWhere(['or', ['like', 'f.nickname', $keyword], ['like', 'f.openid', $keyword]]);

					}
					//渠道二维码
					if (!empty(\Yii::$app->request->post('scene_id'))) {
						$fansData = $fansData->andWhere(['f.qr_scene' => \Yii::$app->request->post('scene_id')]);
					}
					//是否是客服消息来源
					if (!empty(\Yii::$app->request->post('is_kf'))) {
						$last_time = time() - 172800;
						$fansData  = $fansData->andWhere(['>', 'f.last_time', $last_time]);//活跃时间在48小时之内
					}
					//是否是客户 1不是 2是
					if (!empty($is_custom)) {
						if ($is_custom == 1) {
							$fansData = $fansData->andWhere(['!=', 'f.external_userid', '']);
						} else {
							$fansData = $fansData->andWhere(['f.external_userid' => NULL]);
						}
					}

					$fansData = $fansData->andWhere(['f.subscribe' => 1]);

					//套餐限制数量
					//$packageLimit = Package::packageLimitNum($uid, 'fans');



					if (!empty($groupBy)) {
//						$fansData = $fansData->groupBy($groupBy)->having($having);
						$fansData = $fansData->groupBy($groupBy);
					}
					$count = $fansData->count();
					if ($isCondition == 1) {
						return [
							'count' => $count
						];
					}
					if ($sort == 1) {
						if (!empty($groupBy)) {
							$fansIdInfo = $fansData->select('f.id, count(ft.id) as cnt')->orderBy(['f.subscribe_time' => SORT_ASC])->asArray()->all();
						} else {
							$fansIdInfo = $fansData->groupBy('f.id')->select('f.id, count(ft.id) as cnt')->orderBy(['f.subscribe_time' => SORT_ASC])->asArray()->all();
						}

						if (empty($groupBy)) {
							$fansData->groupBy([]);
						}

						$fansData = $fansData->select('f.*')->limit($pageSize)->offset($offset)->orderBy(['f.subscribe_time' => SORT_ASC])->asArray(false)->all();
					} else {
						if (!empty($groupBy)) {
							$fansIdInfo = $fansData->select('f.id, count(ft.id) as cnt')->orderBy(['f.subscribe_time' => SORT_DESC])->asArray()->all();
						} else {
							$fansIdInfo = $fansData->groupBy('f.id')->select('f.id, count(ft.id) as cnt')->orderBy(['f.subscribe_time' => SORT_DESC])->asArray()->all();
						}

						if (empty($groupBy)) {
							$fansData->groupBy([]);
						}

						$fansData = $fansData->select('f.*')->limit($pageSize)->offset($offset)->orderBy(['f.subscribe_time' => SORT_DESC])->asArray(false)->all();
					}
					//格式化粉丝数据并且获取标签信息
					$fansInfo = [];
					/** @var Fans $fans */
					foreach ($fansData as $fans) {
						$fansTags = $fans->fansTags;
						$tagsInfo = [];
						if (!empty($fansTags)) {
							foreach ($fansTags as $fansTag) {
								array_push($tagsInfo, $fansTag->tags->dumpData());
							}
						}

						$fansInfoData        = $fans->dumpData();
						$fansInfoData['key'] = $fans->id;
						$scene_name          = '';
						if (!empty($fans->qr_scene)) {
							$scene      = Scene::findOne($fans->qr_scene);
							$scene_name = '（' . $scene->title . '）';
						}
						$fansInfoData['subscribe_scene_str'] = Fans::getSubscribeScene($fans->subscribe_scene) . $scene_name;
						$fansInfoData['subscribe_time_str']  = DateUtil::getFormattedTime($fans->subscribe_time);
						$fansInfoData['subscribe_day']       = DateUtil::getDiffDay($fans->subscribe_time);
						$fansTime                            = FansTimeLine::find()->where(['fans_id' => $fans->id])
							->andWhere(['source' => 0])
							->andWhere(['>=', 'event_time', date("Y-m-d H:i:s", $fans->subscribe_time)])
							->andWhere(['event' => [FansTimeLine::SCAN_EVENT, FansTimeLine::CLICK_EVENT, FansTimeLine::VIEW_EVENT, FansTimeLine::SEND_TEXT, FansTimeLine::SEND_IMAGE, FansTimeLine::SEND_VOICE, FansTimeLine::SEND_VIDEO, FansTimeLine::SEND_SHORTVIDEO, FansTimeLine::SEND_LOCATION, FansTimeLine::SEND_LINK]]);
						$fansTime                            = $fansTime->count();
						$fansInfoData['interact_nums']       = $fansTime;
						$fansInfoData['last_time_str']       = !empty($fans->last_time) ? DateUtil::getFormattedTime($fans->last_time) : DateUtil::getFormattedTime($fans->subscribe_time);
						$fansInfoData['tags_info']           = $tagsInfo;
						$corpName                            = '';
						if (!empty($fans->external_userid)) {
							$user = WorkExternalContactFollowUser::findOne(['external_userid' => $fans->external_userid, 'del_type' => WorkExternalContactFollowUser::WORK_CON_EX]);
							if (!empty($user)) {
								$corpName = $fans->externalUser->corp->corp_name;
								$workUser = WorkUser::findOne(['userid' => $user->userid, 'corp_id' => $fans->externalUser->corp->id]);
								if (!empty($workUser)) {
									$department = WorkDepartment::getDepartNameByUserId($workUser->department, $workUser->corp_id);
									$corpName   .= '-' . $workUser->name . '（' . $department . '）';
								}
							}
						}
						$fansInfoData['corp_name'] = $corpName;

						array_push($fansInfo, $fansInfoData);
					}

					$fansIds        = [];
					$fansTagCount   = [];
					if (!empty($fansIdInfo)) {
						foreach ($fansIdInfo as $fanId) {
							array_push($fansIds, (int) $fanId['id']);
							array_push($fansTagCount, $fanId['cnt']);
						}
					}

					return [
						'count'     => $count,
						'fans'      => $fansInfo,
						'keys'      => $fansIds,
						'tag_count' => $fansTagCount,
					];
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           给粉丝打和移除标签
		 * @description     给粉丝打和移除标签
		 * @method   post
		 * @url  http://{host_name}/api/fans/give-user-tags
		 *
		 * @param wx_id 必选 int 公众号id
		 * @param tag_ids 必选 array 标签id
		 * @param fans_ids 必选 array 粉丝id
		 * @param type 必选 int 类型0打1移除
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/3 17:13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGiveUserTags ()
		{
			if (\Yii::$app->request->isPost) {
				$wxId     = \Yii::$app->request->post('wx_id');
				$tag_ids  = \Yii::$app->request->post('tag_ids');
				$fans_ids = \Yii::$app->request->post('fans_ids');
				$type     = \Yii::$app->request->post('type');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($wxId) || empty($tag_ids) || empty($fans_ids)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				if (!is_array($tag_ids) || !is_array($fans_ids)) {
					throw new InvalidParameterException('标签id和openid必须为数组格式！');
				}
				try {
					$fansData    = Fans::find()->where(['id' => $fans_ids])->asArray()->all();
					$fansOpenids = array_column($fansData, 'openid');
					$authorInfo  = $this->wxAuthorInfo->author;
					$cc          = count($fans_ids);
					$res         = Tags::giveUserTags($this->wxAuthorInfo->authorizer_appid, $authorInfo->author_id, $tag_ids, $fansOpenids, $type, $cc);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}
				if ($type == 0) {
					$active = '打';
				} else {
					$active = '移除';
				}
				$suc = $res < 0 ? 0 : $res;

				return [
					'error'     => 0,
					'error_msg' => "本次共给" . $cc . "人" . $active . "标签，成功" . $suc . "人，失败" . ($cc - $res) . "人。",
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           粉丝导出
		 * @description     粉丝导出
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-export
		 *
		 * @param fans_id 必选 array 粉丝id
		 * @param sort 可选 int sort 1正序2倒序
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    url string 地址
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/21 11:23
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansExport ()
		{
			if (\Yii::$app->request->isPost) {
				try {
					$fansId = \Yii::$app->request->post('fans_id');
					$sort   = \Yii::$app->request->post('sort') ?: 1;
					if (empty($fansId)) {
						return ['error' => 1, 'msg' => '参数不正确'];
					}
					if ($sort == 1) {
						$data = Fans::find()->where(['id' => $fansId])->orderBy(['subscribe_time' => SORT_ASC])->all();
					} else {
						$data = Fans::find()->where(['id' => $fansId])->orderBy(['subscribe_time' => SORT_DESC])->all();
					}
					$info     = Fans::getExportData($data);
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['nickname', 'openid', 'sex', 'subscribe_day', 'interact_nums', 'subscribe_time_str', 'tags_info', 'subscribe_scene_str', 'last_time_str'];
					$headers  = [
						'nickname'            => '昵称',
						'openid'              => 'openid',
						'sex'                 => '性别',
						'subscribe_day'       => '关注时长',
						'interact_nums'       => '互动次数',
						'subscribe_time_str'  => '关注时间',
						'tags_info'           => '标签',
						'subscribe_scene_str' => '关注渠道',
						'last_time_str'       => '最后活跃时间',
					];
					$headerInfo = $headers;
					$columnInfo = $columns;
					$fileName = '粉丝数据_' . date("YmdHis", time());
					$pageSize = 5000;
					if (count($fansId) <= $pageSize) {
						Excel::export([
							'models'       => $info,//数库
							'fileName'     => $fileName,//文件名
							'savePath'     => $save_dir,//下载保存的路径
							'asAttachment' => true,//是否下载
							'columns'      => $columns,//要导出的字段
							'headers'      => $headers
						]);
					} else {
						$num     = ceil(count($fansId) / $pageSize);
						$models  = [];
						$columns = [];
						$headers = [];
						for ($i = 1; $i <= $num; $i++) {
							$offset                = ($i - 1);
							$temp                  = array_slice($info, $offset * $pageSize, $pageSize);
							$models['sheet' . $i]  = $temp;
							$columns['sheet' . $i] = $columnInfo;
							$headers['sheet' . $i] = $headerInfo;
						}
						Excel::export([
							'isMultipleSheet' => true,
							'models'          => $models,
							'fileName'        => $fileName,//文件名
							'savePath'        => $save_dir,//下载保存的路径
							'asAttachment'    => true,//是否下载
							'columns'         => $columns,
							'headers'         => $headers
						]);
					}
					$url = \Yii::$app->params['site_url'] . str_replace(\Yii::getAlias('@upload'), '/upload', $save_dir) . $fileName . '.xlsx';

					return [
						'url' => $url,
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
		 * 获取用户的标签列表
		 * @return array|mixed
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetUserTags ()
		{
			if (\Yii::$app->request->isPost) {
				$wxId    = \Yii::$app->request->post('wx_id');
				$fans_id = \Yii::$app->request->post('fans_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($wxId) || empty($fans_id)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				try {
					$fansData = Fans::findOne(['id' => $fans_id]);
					$res      = Tags::getTagByOpenId($this->wxAuthorInfo->authorizer_appid, $fansData->openid);

					return $res;
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
		 * @catalog         数据接口/api/fans/
		 * @title           消息列表接口
		 * @description     消息列表接口
		 * @method   post
		 * @url  http://{host_name}/api/fans/msg-list
		 *
		 * @param           * * * *
		 *
		 * @return          {"error":0,"data":[{"id":"gh_a5a2b5c4f175","avatar":"http://wx.qlogo.cn/mmopen/qbvaL9taELsfibgnbr0jBxaiayVy2GNE3HY0SrusXbQmVeBBpDzFF8VOibSBCshTIu6lPX5O10UBNwZBibGRicqGo6WkWPccHJZaV/0","name":"小猪的智慧店铺","fans_list":[{"fans_id":652,"openid":"oHPnN1exRY8QOsKpjDhVZRXbLOxc","nickname":"空白","remark":"","sex":2,"country":"中国","province":"安徽","city":"合肥","language":"zh_CN","headerimg":"http://thirdwx.qlogo.cn/mmopen/uz4yibblmCLXmZsicYl8FSKJDYduicrvESJUg00Nbr6CGtibnFdchssw8zqz5ySzoBbL6LBP2KyGPIaXCA1RLJO2QNtNxXsG3ic4L/132","subscribe_time":"1573695494","last_time":"1574910108","unionid":"oYBYj0pGRbL-CdcLnbulYh6xHslI","subscribe_scene":"ADD_SCENE_SEARCH","last_content":[{"id":932,"is_read":1,"from":{"type":1,"data":{"fans_id":652,"openid":"oHPnN1exRY8QOsKpjDhVZRXbLOxc","nickname":"空白","remark":"","headerimg":"http://thirdwx.qlogo.cn/mmopen/uz4yibblmCLXmZsicYl8FSKJDYduicrvESJUg00Nbr6CGtibnFdchssw8zqz5ySzoBbL6LBP2KyGPIaXCA1RLJO2QNtNxXsG3ic4L/132"}},"to":{"type":2,"data":{"alias":"","user_name":"gh_a5a2b5c4f175","nick_name":"小猪的智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/qbvaL9taELsfibgnbr0jBxaiayVy2GNE3HY0SrusXbQmVeBBpDzFF8VOibSBCshTIu6lPX5O10UBNwZBibGRicqGo6WkWPccHJZaV/0"}},"content":"96661/::P/::'(/::P/::'(/::P/::'(/::P/::P/::'(","type":1,"create_time":"2019-11-28 11:01:48"}]},{"loop":"……"}]},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 公众号唯一ID
		 * @return_param    avatar string 公众号头像
		 * @return_param    name string 公众号名称
		 * @return_param    fans_list array 活跃粉丝列表
		 * @return_param    fans_id int 粉丝ID
		 * @return_param    openid string 粉丝唯一ID
		 * @return_param    nickname string 昵称
		 * @return_param    remark string 备注
		 * @return_param    sex int 性别、1：男；2、女；3、未知
		 * @return_param    country string 国家
		 * @return_param    province string 省份
		 * @return_param    city string 城市
		 * @return_param    language string 语言
		 * @return_param    headerimg string 头像
		 * @return_param    subscribe_time string 关注事件
		 * @return_param    unionid string 平台唯一ID
		 * @return_param    subscribe_scene string 来源
		 * @return_param    last_content data 最后一条的消息信息
		 * @return_param    id string 消息ID
		 * @return_param    is_read int 是否已读
		 * @return_param    from array 发送方信息
		 * @return_param    type int 发送方类型，1：粉丝、2：用户、3：客服
		 * @return_param    data array 发送方详细信息
		 * @return_param    fans_id int 粉丝ID
		 * @return_param    openid string 粉丝openid
		 * @return_param    nickname string 昵称
		 * @return_param    remark string 备注
		 * @return_param    headerimg string 头像
		 * @return_param    to array 接收方信息
		 * @return_param    type int 接收方类型，1：粉丝、2：用户、3：客服
		 * @return_param    data array 接收方详细信息
		 * @return_param    alias string 微信号
		 * @return_param    user_name string 公众号ID
		 * @return_param    nick_name string 昵称
		 * @return_param    head_img string 头像
		 * @return_param    content string 消息内容
		 * @return_param    type int 消息类型，1：文本、2：图片、3：音频、4：视频、5、图文、6：音乐
		 * @return_param    create_time string 消息时间
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/12 14:30
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMsgList ()
		{
			if (\Yii::$app->request->isPost) {
				$result     = [];
				$wxAuthorId = [];//当前公众号的author_id
				$sub_id     = \Yii::$app->request->post('sub_id');
				if (!empty($sub_id)) {
					$subAuth = SubUserAuthority::find()->andWhere(['sub_user_id' => $sub_id, 'type' => 1])->andWhere(['<>', 'authority_ids', ''])->asArray()->all();
					if (!empty($subAuth)) {
						foreach ($subAuth as $auth) {
							$authorityIds = explode(',', $auth['authority_ids']);
							$routes       = Authority::find()->where(['in', 'id', $authorityIds])->asArray()->all();
							$routes       = array_column($routes, 'route');
							if (in_array('miniMsg', $routes) || in_array('fansMsg', $routes)) {
								array_push($wxAuthorId, $auth['wx_id']);
							}
						}
					}
				}
				$userAuthorRelations = $this->user->userAuthorRelations;
				if (!empty($userAuthorRelations)) {
					foreach ($userAuthorRelations as $relation) {
						if ($relation->author->authorizer_type != WxAuthorize::AUTH_TYPE_UNAUTH && $relation->author->auth_type == WxAuthorize::AUTH_TYPE_APP) {
							$author = $relation->author->wxAuthorizeInfo;
							if (!empty($wxAuthorId) && !in_array($relation->author_id, $wxAuthorId)) {
								continue;
							}
							$data              = [];
							$data['id']        = $author->user_name;
							$data['avatar']    = $author->head_img;
							$data['name']      = $author->nick_name;
							$data['fans_list'] = Fans::getActiveFans($relation->author->author_id);

							array_push($result, $data);
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
		 * @catalog         数据接口/api/fans/
		 * @title           获取关注渠道
		 * @description     获取关注渠道
		 * @method   post
		 * @url  http://{host_name}/api/fans/get-scene
		 *
		 * @param wx_id 必选 string 公众号ID
		 * @param scene 可选 string 关注渠道
		 *
		 * @return          {"error":0,"data":{"ADD_SCENE_SEARCH":"公众号搜索","ADD_SCENE_ACCOUNT_MIGRATION":"公众号迁移","ADD_SCENE_PROFILE_CARD":"名片分享","ADD_SCENE_QR_CODE":"扫描二维码","ADD_SCENE_PROFILE_LINK":"图文页内名称点击","ADD_SCENE_PROFILE_ITEM":"图文页右上角菜单","ADD_SCENE_PAID":"支付后关注","ADD_SCENE_OTHERS":"其他"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/15 11:39
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetScene ()
		{
			if (\Yii::$app->request->isPost) {
				$scene = \Yii::$app->request->post('scene') ?: NULL;

				return Fans::getSubscribeScene($scene);
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		//获取粉丝共同具有的标签
		public function actionFansCommonTags ()
		{
			if (\Yii::$app->request->isPost) {
				$fans_ids = \Yii::$app->request->post('fans_ids');
				$fans_id  = implode(',', $fans_ids);
				$count    = count($fans_ids);
				$result   = [];
				$tags     = FansTags::find()->select('count(*) as num,tags_id')->where("fans_id in (" . $fans_id . ")")->groupBy('tags_id')->asArray()->all();
				if (!empty($tags)) {
					foreach ($tags as $v) {
						if ($v['num'] == $count) {
							$result[] = $v['tags_id'];
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
		 * @catalog         数据接口/api/fans/
		 * @title           核查刷新粉丝列表
		 * @description     核查刷新粉丝列表
		 * @method   POST
		 * @url  http://{host_name}/api/fans/check-refresh
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 *
		 * @return          {"error":0,"data":{"refresh":1,"last_refresh_time":1571727432}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    refresh int 今日刷新次数
		 * @return_param    last_refresh_time int 上次刷新的时间戳
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/22 15:03
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 * @throws NotAllowException
		 */
		public function actionCheckRefresh ()
		{
			if (\Yii::$app->request->isPost) {
				$cacheKey     = 'refresh_user_' . $this->wxAuthorInfo->user_name;
				$currentYmd   = DateUtil::getCurrentYMD();
				$refreshCache = \Yii::$app->cache->get($cacheKey);
				if (empty($refreshCache) || empty($refreshCache[$currentYmd])) {
					return [
						'refresh'           => 0,
						'last_refresh_time' => 0,
					];
				}

				//  每日请求次数验证 最多三次
				if ($refreshCache[$currentYmd]['refresh'] > 2) {
//					throw new NotAllowException('今日请求已达上限！');
				}
				//  两次请求时间间隔验证 间隔两小时
				if (($refreshCache[$currentYmd]['last_refresh_time'] + 2 * 60 * 60) > time()) {
//					throw new NotAllowException('距离上次请求时间不足两小时');
				}

				return $refreshCache[$currentYmd];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           获取粉丝标签
		 * @description     获取粉丝标签
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-tags
		 *
		 * @param fans_ids 必选 array 粉丝id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/25 20:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansTags ()
		{
			if (\Yii::$app->request->isPost) {
				$fans_ids = \Yii::$app->request->post('fans_ids');
				if (empty($fans_ids)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$fans_id = implode(',', $fans_ids);
				$tags    = FansTags::find()->alias('ft');
				$tags    = $tags->leftJoin('{{%tags}} t', '`t`.`id` = `ft`.`tags_id`');
				$tags    = $tags->select('ft.tags_id,t.name as tag_name')->where("ft.fans_id in (" . $fans_id . ")")->groupBy('ft.tags_id')->asArray()->all();

				return $tags;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           修改粉丝备注
		 * @description     修改粉丝的备注信息
		 * @method   POST
		 * @url  http://{host_name}/api/fans/set-remark
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param fans_id 必选 int 粉丝ID
		 * @param remark 可选 string 备注信息
		 *
		 * @return          {"error":0}
		 *
		 * @return_param    error int 状态码
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/12/6 15:08
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetRemark ()
		{
			if (\Yii::$app->request->isPost) {
				$fans_id = \Yii::$app->request->post('fans_id');
				$remark  = \Yii::$app->request->post('remark');
				if (empty($fans_id)) {
					throw new InvalidParameterException('参数不正确！');
				}

				try {
					Fans::modifyFansRemark($fans_id, $remark);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return true;

			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           粉丝属性详情
		 * @description     粉丝属性详情
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-field
		 *
		 * @param uid  必选 int 用户ID
		 * @param fans_id 必选 int 粉丝ID
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    field_list array 客户属性
		 * @return_param    field_list.fieldid int 属性ID
		 * @return_param    field_list.key string 属性key
		 * @return_param    field_list.title string 属性名称
		 * @return_param    field_list.type int 属性类型
		 * @return_param    field_list.optionVal string 属性选项
		 * @return_param    field_list.value string 已设置属性值
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/14
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansField ()
		{
			if (\Yii::$app->request->isPost) {
				$uid = \Yii::$app->request->post('uid', 0);
				$cid = \Yii::$app->request->post('fans_id', 0);
				if (empty($uid) || empty($cid)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$fansInfo = Fans::findOne($cid);
				if (empty($fansInfo)){
					throw new InvalidParameterException('粉丝数据错误！');
				}

				$result = [];
				//自定义属性
				$fieldList = CustomField::getCustomField($uid, $cid, 2);

				$result['field_list'] = $fieldList;

				return $result;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           修改粉丝自定义属性
		 * @description     修改粉丝自定义属性
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-field-update
		 *
		 * @param uid         必选 int 用户ID
		 * @param fans_id     必选 int 粉丝ID
		 * @param fieldData   必选 array 客户属性
		 * @param fieldData.fieldid  必选 int 属性ID
		 * @param fieldData.value    可选 int 属性值
		 *
		 * @return       {"error":0,"data":[]}
		 *
		 * @return_param error int 状态码
		 * @return_param data array 结果数据
		 *
		 * @remark       Create by PhpStorm. User: fulu. Date: 2020/04/14
		 * @number       0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionFansFieldUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$uid       = \Yii::$app->request->post('uid', 0);
				$cid       = \Yii::$app->request->post('fans_id', 0);
				$fieldData = \Yii::$app->request->post('fieldData', []);
				if (empty($uid) || empty($cid) || empty($fieldData)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$fansInfo = Fans::findOne($cid);
				if (empty($fansInfo)){
					throw new InvalidParameterException('粉丝数据错误！');
				}

				try {
					Fans::modifyFansField($uid, $cid, $fieldData);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}


		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           粉丝跟进记录
		 * @description     粉丝跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-follow-record
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid         必选 int 用户ID
		 * @param sub_id      必选 int 子账户ID
		 * @param fans_id     必选 int 粉丝ID
		 * @param page        可选 int 页码
		 * @param page_size   可选 int 每页数据量，默认15
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    followRecord array 跟进记录
		 * @return_param    followRecord.id int 记录id
		 * @return_param    followRecord.record string 记录内容
		 * @return_param    followRecord.name string 记录人名称
		 * @return_param    followRecord.time string 记录时间
		 * @return_param    followRecord.file array 附件图片
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/4/15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionFansFollowRecord ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$uid             = \Yii::$app->request->post('uid', 0);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$cid             = \Yii::$app->request->post('fans_id', 0);
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($uid) || empty($cid) || empty($sub_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$offset = ($page - 1) * $pageSize;

				$userInfo = UserProfile::findOne(['uid' => $uid]);

				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $cid, 'type' => 2, 'status' => 1]);
				if ($isMasterAccount == 2) {
					//子账户
					$followRecord = $followRecord->andWhere(['sub_id' => $sub_id]);
					$subInfo      = SubUserProfile::findOne(['sub_user_id' => $sub_id]);
				}
				$count = $followRecord->count();

				$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`')->orderBy(['id' => SORT_DESC])->asArray()->all();

				foreach ($followRecord as $k => $v) {
					if ($isMasterAccount == 2) {
						//子账户
						$name = $subInfo->name;
					} else {
						if (!empty($v['user_id'])) {
							$userInfo = WorkUser::findOne($v['user_id']);
							$name     = $userInfo->name;
						} elseif (!empty($v['sub_id'])) {
							$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
							$name    = $subInfo->name;
						}else{
							$name = $userInfo->nick_name;
						}
					}
					$followRecord[$k]['name'] = $name;
					$followRecord[$k]['time'] = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
					$followRecord[$k]['file'] = !empty($v['file']) ? json_decode($v['file']) : [];
				}

				return [
					'count'        => $count,
					'followRecord' => $followRecord,
				];
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           添加粉丝跟进记录
		 * @description     添加粉丝跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/fans/fans-follow-record-set
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid         必选 int 用户ID
		 * @param sub_id      必选 int 子账户ID
		 * @param fans_id     必选 int 粉丝ID
		 * @param follow_id   必选 int 跟进状态id
		 * @param record_id   可选 int 记录ID
		 * @param record      可选 string 记录内容
		 * @param file        可选 array 图片附件链接
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/4/15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionFansFollowRecordSet ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$uid             = \Yii::$app->request->post('uid', 0);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$follow_id       = \Yii::$app->request->post('follow_id', 0);
				$cid             = \Yii::$app->request->post('fans_id', 0);
				$record_id       = \Yii::$app->request->post('record_id', 0);
				$record          = \Yii::$app->request->post('record', '');
				$file            = \Yii::$app->request->post('file', '');
				$record          = trim($record);

				if (empty($uid) || empty($cid) || empty($sub_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if(empty($follow_id)){
					throw new InvalidParameterException('请选择跟进状态！');
				}
				if (empty($record)) {
					throw new InvalidParameterException('跟进内容不能为空！');
				}

				$fansInfo = Fans::findOne($cid);
				if (empty($fansInfo)){
					throw new InvalidParameterException('粉丝数据错误！');
				}
				//更新跟进状态
				$fansInfo->follow_id = $follow_id;
				if (!$fansInfo->save()) {
					throw new InvalidParameterException(SUtils::modelError($fansInfo));
				}

				if (empty($record_id)) {
					$followRecord              = new WorkExternalContactFollowRecord();
					$followRecord->uid         = $uid;
					$followRecord->type        = 2;
					$followRecord->external_id = $cid;
					$followRecord->user_id     = 0;
					$followRecord->status      = 1;
					$followRecord->sub_id      = $isMasterAccount == 1 ? 0 : $sub_id;
					$followRecord->time        = time();
				} else {
					$followRecord           = WorkExternalContactFollowRecord::findOne($record_id);
					$followRecord->upt_time = time();
				}
				$followRecord->record = $record;
				$followRecord->file   = !empty($file) ? json_encode($file) : '';


				if (!$followRecord->save()) {
					throw new InvalidParameterException(SUtils::modelError($followRecord));
				}

				//记录粉丝轨迹
				if (empty($record_id)){
					if ($followRecord->sub_id == 0) {
						$userInfo = UserProfile::findOne(['uid' => $uid]);
						$name     = $userInfo->nick_name;
					} else {
						$subInfo = SubUserProfile::findOne(['sub_user_id' => $followRecord->sub_id]);
						$name    = $subInfo->name;
					}
					$count             = WorkExternalContactFollowRecord::find()->where(['external_id' => $cid, 'type' => 2, 'status' => 1])->count();//跟进次数
					$remark            = '【' . $name . '】第' . $count . '次跟进';
					$fansTimeLineEvent = FansTimeLine::FOLLOW_EVENT;
					FansTimeLine::create($cid, $fansTimeLineEvent, time(), 0, 4, $remark);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           跑粉丝表的客户ID
		 * @description     跑粉丝表的客户ID
		 * @method   post
		 * @url  http://{host_name}/api/fans/update-external-id
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/18 17:22
		 * @number          0
		 *
		 */
		public function actionUpdateExternalId ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$fans = Fans::find()->alias('f')->leftJoin('{{%work_external_contact}} c', '`f`.`unionid` = `c`.`unionid`')->where(['!=', 'c.unionid', ""])->andWhere(['f.external_userid' => NULL])->select('f.id fid,c.id cid');
			$fans = $fans->groupBy('f.id')->asArray()->all();
			if (!empty($fans)) {
				foreach ($fans as $data) {
					if (!empty($data['fid']) && $data['cid']) {
						$fData                  = Fans::findOne($data['fid']);
						$fData->external_userid = $data['cid'];
						$fData->save();
					}

				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/fans/
		 * @title           客户是否是粉丝
		 * @description     客户是否是粉丝
		 * @method   post
		 * @url  http://{host_name}/api/fans/update-contact
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/25 9:53
		 * @number          0
		 *
		 */
		public function actionUpdateContact ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$contact = WorkExternalContact::find()->alias('c')->leftJoin('{{%fans}} f', '`f`.`unionid` = `c`.`unionid`')->where(['!=', 'c.unionid', ""])->andWhere(['f.subscribe' => 1])->select('c.id')->asArray()->all();
			if (!empty($contact)) {
				foreach ($contact as $cnt) {
					if (isset($cnt['id']) && !empty($cnt['id'])) {
						$workEx = WorkExternalContact::findOne($cnt['id']);
						if (!empty($workEx)) {
							$workEx->is_fans = 1;
							$workEx->save();
						}
					}
				}
			}
		}

	}