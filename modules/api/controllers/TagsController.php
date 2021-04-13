<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/9/17
	 * Time: 09:39
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\FansTags;
	use app\models\Tags;
	use app\models\WxAuthorize;
	use app\modules\api\components\AuthBaseController;
	use app\models\WxAuthorizeInfo;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;

	class TagsController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'refresh-tags-list' => ['POST'],
					],
				]
			]);
		}

		/**
		 * 同步微信的标签到本地
		 *
		 * @return bool
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionRefreshTagsList ()
		{
			if (\Yii::$app->request->isPost) {
				$wxId = \Yii::$app->request->post('wx_id');

				if (empty($wxId)) {
					throw new InvalidParameterException('缺少必要参数！');
				}

				$wxAuthorInfo = WxAuthorizeInfo::findOne(['user_name' => $wxId]);

				if (empty($wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$wxAuthor = WxAuthorize::find()->where(['authorizer_appid'=>$wxAuthorInfo->authorizer_appid])->one();
				if($wxAuthor['authorizer_type']=='unauthorized'){
					throw new InvalidParameterException('该公众号未授权！');
				}
				$result = Tags::syncTagsFromWx($wxAuthorInfo->authorizer_appid);

				return $result;
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/tags/
		 * @title           创建标签
		 * @description     创建标签
		 * @method   请求方式
		 * @url  http://{host_name}/api/tags/tags-create
		 *
		 * @param wx_id 必选 string 公众号原始id
		 * @param tag_name 必选 int 标签名称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/14 11:25
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 * @throws \app\models\NotAllowException
		 */
		public function actionTagsCreate ()
		{
			if (\Yii::$app->request->isPost) {
				$tag_name = \Yii::$app->request->post('tag_name');
				if (empty($tag_name)) {
					throw new InvalidParameterException("请输入标签名称");
				}
				try {
					if (!is_array($tag_name)) {
						throw new InvalidParameterException('标签名称必须为数组格式！');
					}
					$authorInfo = $this->wxAuthorInfo->author;
					$wxAuthor = WxAuthorize::find()->where(['authorizer_appid'=>$this->wxAuthorInfo->authorizer_appid])->one();
					if($wxAuthor['authorizer_type']=='unauthorized'){
						throw new InvalidParameterException('该公众号未授权！');
					}
					Tags::createTag($this->wxAuthorInfo->authorizer_appid,$authorInfo->author_id, $tag_name);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => "创建成功",
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/tags/
		 * @title           删除标签
		 * @description     删除标签
		 * @method   post
		 * @url  http://{host_name}/api/tags/tags-del
		 *
		 * @param tag_id 必选 int 标签id
		 *
		 * @return          {"error":0,"error_msg":"删除成功"}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/14 11:18
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionTagsDel ()
		{
			if (\Yii::$app->request->isPost) {
				$tagId = \Yii::$app->request->post("tag_id");
				if (empty($tagId)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				try {
					$wxAuthor = WxAuthorize::find()->where(['authorizer_appid'=>$this->wxAuthorInfo->authorizer_appid])->one();
					if($wxAuthor['authorizer_type']=='unauthorized'){
						throw new InvalidParameterException('该公众号未授权！');
					}
					Tags::deleteTag($this->wxAuthorInfo->authorizer_appid, $tagId,$this->wxAuthorInfo->author->author_id);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => "删除成功",
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/tags/
		 * @title           修改标签
		 * @description     修改标签
		 * @method   post
		 * @url  http://{host_name}/api/tags/tags-update
		 *
		 * @param tag_id 必选 int 标签id
		 * @param tag_name 必选 string 标签名称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/14 11:24
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 * @throws \Throwable
		 */
		public function actionTagsUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$tagId    = \Yii::$app->request->post("tag_id");
				$tag_name = \Yii::$app->request->post('tag_name');
				if (empty($tagId) || empty($tag_name)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				try {
					$wxAuthor = WxAuthorize::find()->where(['authorizer_appid'=>$this->wxAuthorInfo->authorizer_appid])->one();
					if($wxAuthor['authorizer_type']=='unauthorized'){
						throw new InvalidParameterException('该公众号未授权！');
					}
					Tags::updateTags($this->wxAuthorInfo->authorizer_appid, $tagId, $tag_name);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => "修改成功",
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * 获取标签详情
		 * @return array
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionGetTagName ()
		{
			if (\Yii::$app->request->isPost) {
				$tagId = \Yii::$app->request->post('tag_id');
				if (empty($tagId)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				try {
					$res  = Tags::findOne(['id' => $tagId]);
					$name = $res->name;
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => $name,
				];

			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * 获取标签下的openId
		 * @return array
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionGetOpenIdByTag ()
		{
			if (\Yii::$app->request->isPost) {
				$tagId = \Yii::$app->request->post('tag_id');
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($tagId)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				try {
					$res = Tags::getOpenIdByTag($this->wxAuthorInfo->authorizer_appid, $tagId);
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => $res,
				];

			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/tags/
		 * @title           标签列表
		 * @description     标签列表
		 * @method   请求方式
		 * @url  http://{host_name}/api/tags/tags-get-all
		 *
		 * @param type 必选 int 传1不分页0分页
		 * @param wx_id 必选 string 公众号原始id
		 *
		 * @return          {"error":0,"data":{"count":"22","info":[{"id":"31","name":"222222","fans_num":"0","wx_fans_num":0},{"id":"30","name":"121","fans_num":"0","wx_fans_num":0},{"id":"29","name":"王盼","fans_num":"2","wx_fans_num":2},{"id":"28","name":"团订单","fans_num":"0","wx_fans_num":0},{"id":"26","name":"的","fans_num":"12","wx_fans_num":12},{"id":"25","name":"为","fans_num":"12","wx_fans_num":12},{"id":"24","name":"4445","fans_num":"0","wx_fans_num":0},{"id":"23","name":"22","fans_num":"0","wx_fans_num":0},{"id":"22","name":"合肥","fans_num":"1","wx_fans_num":1},{"id":"15","name":"88","fans_num":"2","wx_fans_num":1},{"id":"14","name":"撒大声","fans_num":"1","wx_fans_num":1},{"id":"13","name":"撒大声地","fans_num":"13","wx_fans_num":12},{"id":"10","name":"我不是唯一5","fans_num":"13","wx_fans_num":13},{"id":"9","name":"我不是唯一4","fans_num":"0","wx_fans_num":0},{"id":"8","name":"我不是唯一3","fans_num":"0","wx_fans_num":0},{"id":"7","name":"32DASD","fans_num":"13","wx_fans_num":13},{"id":"6","name":"我不是唯2","fans_num":"0","wx_fans_num":0},{"id":"5","name":"恶趣味群无","fans_num":"0","wx_fans_num":0},{"id":"4","name":"45646","fans_num":"177","wx_fans_num":174},{"id":"3","name":"临时群发标签","fans_num":"0","wx_fans_num":0},{"id":"2","name":"发券","fans_num":"1","wx_fans_num":0},{"id":"1","name":"星标组","fans_num":"178","wx_fans_num":174}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id id 标签id
		 * @return_param    name string 标签名称
		 * @return_param    fans_num int 粉丝数
		 * @return_param    wx_fans_num int 微信后台粉丝数
		 * @return_param    last_tag_time string 最后一次同步时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/15 16:48
		 * @number          0
		 *
		 */
		public function actionTagsGetAll ()
		{
			try {
				$type     = \Yii::$app->request->post('type'); //1 不分页
				$page     = \Yii::$app->request->post('page'); //分页
				$pageSize = \Yii::$app->request->post('pageSize'); //每页数量
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page       = !empty($page) ? $page : 1;
				$pageSize   = !empty($pageSize) ? $pageSize : 10;
				$offset     = ($page - 1) * $pageSize;
				$authorInfo = $this->wxAuthorInfo->author;
				$tagsData   = Tags::find()->where(['author_id' => $authorInfo->author_id]);
				if (empty($type)) {
					$count = $tagsData->count();
					$tagsData->orderBy(['id' => SORT_DESC]);
					$info = $tagsData->limit($pageSize)->offset($offset)->asArray()->all();
				} else {
					$count = $tagsData->count();
					$tagsData->orderBy(['id' => SORT_DESC]);
					$info = $tagsData->asArray()->all();
				}
				$result = [];
				if (!empty($info)) {
					foreach ($info as $k => $v) {
						$fagsTag  = FansTags::find()->alias('ft');
						$fagsTag  = $fagsTag->leftJoin('{{%fans}} f', 'ft.fans_id = f.id');
						$fans_num  = $fagsTag->where(['ft.tags_id' => $v['id'],'f.subscribe'=>1])->count();
						$result[$k]['key']           = $v['id'];
						$result[$k]['name']          = $v['name'];
						$result[$k]['fans_num']      = $fans_num;
						$result[$k]['wx_fans_num']   = $v['wx_fans_num'];
					}
				}
				$last_tag_time = '';
				if ($this->wxAuthorInfo->last_tag_time != '0000-00-00 00:00:00') {
					$last_tag_time = $this->wxAuthorInfo->last_tag_time;
				}
			} catch (\Exception $e) {
				return [
					'error'     => $e->getCode(),
					'error_msg' => $e->getMessage(),
				];
			}

			return [
				'count'         => $count,
				'last_tag_time' => $last_tag_time,
				'info'          => $result,
			];
		}

	}