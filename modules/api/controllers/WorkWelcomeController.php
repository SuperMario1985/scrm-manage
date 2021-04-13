<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/1/13
	 * Time: 15:19
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\Attachment;
	use app\models\WorkDepartment;
	use app\models\WorkMaterial;
	use app\models\WorkUser;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use yii\web\MethodNotAllowedHttpException;
	use function GuzzleHttp\Psr7\try_fopen;

	class WorkWelcomeController extends WorkBaseController
	{
		/**
		 * @inheritDoc
		 *
		 * @param \yii\base\Action $action
		 *
		 * @return bool
		 *
		 * @throws \app\components\InvalidParameterException
		 * @throws \yii\web\BadRequestHttpException
		 */
		public function beforeAction ($action)
		{
			return parent::beforeAction($action);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-welcome/
		 * @title           欢迎语列表
		 * @description     欢迎语列表
		 * @method   post
		 * @url  http://{host_name}/api/work-welcome/list
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    wel_type string 欢迎语类型
		 * @return_param    type int 适用类型1全体成员2部分成员
		 * @return_param    users string/array 当type为1时string类型2时数组
		 * @return_param    time string 创建时间
		 * @return_param    add_type int 1图片2网页3小程序0都没有
		 * @return_param    text_content string 文本内容
		 * @return_param    image_url string 图片的url地址
		 * @return_param    link_title string 网页标题
		 * @return_param    link_pic_url string 图文封面的url地址
		 * @return_param    link_desc string 图文消息描述
		 * @return_param    link_url string 图文消息链接
		 * @return_param    mini_title string 小程序消息标题
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/14 9:49
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$page     = \Yii::$app->request->post('page'); //分页
				$pageSize = \Yii::$app->request->post('pageSize'); //分页
				$page     = !empty($page) ? $page : 1;
				$pageSize = !empty($pageSize) ? $pageSize : 15;
				$offset   = ($page - 1) * $pageSize;
				$welcome  = WorkWelcome::find()->andWhere(['corp_id' => $this->corp['id']]);
				$count    = $welcome->count();
				$welcome  = $welcome->limit($pageSize)->offset($offset)->orderBy(['create_time' => SORT_DESC])->all();
				$result   = [];
				if (!empty($welcome)) {
					foreach ($welcome as $key => $wel) {
						$work_wel                 = WorkWelcome::getData($wel);
						$result[$key]['id']       = $wel['id'];
						$result[$key]['wel_type'] = $work_wel['wel_type'];
						$result[$key]['type']     = $work_wel['type'];
						$result[$key]['users']    = $work_wel['users'];
						$result[$key]['time']     = $wel->create_time;
						$result[$key]['add_type'] = 0;
						$content = [];
						if(!empty($wel->context)){
							$content                  = json_decode($wel->context, true);
						}
						$contentData              = WorkWelcome::getContentData($content);

						if (!empty($content['text'])) {
							$result[$key]['text_content'] = $contentData['text_content'];
						}
						if (!empty($content['image'])) {
							$result[$key]['image_url'] = $contentData['image_url'];
							$result[$key]['add_type']  = $contentData['add_type'];
						}
						if (!empty($content['link'])) {
							if(!empty($wel->attachment_id)){
								$attachment = Attachment::findOne($wel->attachment_id);
								$link_pic_url = $attachment->local_path;
							}else{
								$link_pic_url = $contentData['link_pic_url'];
							}
							$result[$key]['link_title']   = $contentData['link_title'];
							$result[$key]['link_pic_url'] = $link_pic_url;
							$result[$key]['link_desc']    = $contentData['link_desc'];
							$result[$key]['link_url']     = $contentData['link_url'];
							$result[$key]['add_type']     = $contentData['add_type'];
						}
						if (!empty($content['miniprogram'])) {
							$result[$key]['mini_title'] = $contentData['mini_title'];
							$result[$key]['add_type']   = $contentData['add_type'];
						}
					}
				}

				//是否设置过全体成员
				$info   = WorkWelcome::findOne(['corp_id' => $this->corp['id'], 'type' => 1]);
				$hasAll = !empty($info) ? 1 : 0;
				return [
					'count' => $count,
					'info'  => $result,
					'hasAll' => $hasAll,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-welcome/
		 * @title           添加/修改欢迎语
		 * @description     添加/修改欢迎语
		 * @method   post
		 * @url  http://{host_name}/api/work-welcome/add
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param id 可选 int 修改时传
		 * @param type 必选 int 1通用2成员
		 * @param users 可选 array 成员id
		 * @param add_type 可选 int 1图片2网页3小程序
		 * @param text_content 可选 string 文本内容
		 * @param media_id 可选 int 图片企业微信素材表id
		 * @param link_title 可选 string 网页标题
		 * @param link_attachment_id 可选 int 网页封面id来源素材表
		 * @param link_desc 可选 string    网页描述
		 * @param link_url 可选 string    网页链接
		 * @param mini_title 可选 string    小程序标题
		 * @param mini_pic_media_id 可选 int  小程序封面企业微信素材表id
		 * @param mini_appid 可选 string    小程序appid
		 * @param mini_page 可选 string    小程序page路径
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/14 13:23
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$data['id']                 = \Yii::$app->request->post('id'); //编辑时传
				$data['type']               = \Yii::$app->request->post('type'); //1通用2成员
				$data['users']              = \Yii::$app->request->post('users'); //成员id
				$data['add_type']           = \Yii::$app->request->post('add_type');//1图片2网页3小程序
				$data['text_content']       = \Yii::$app->request->post('text_content');
				$data['media_id']           = \Yii::$app->request->post('media_id');
				$data['link_title']         = \Yii::$app->request->post('link_title');
				$data['link_attachment_id'] = \Yii::$app->request->post('link_attachment_id');
				$data['link_desc']          = \Yii::$app->request->post('link_desc');
				$data['link_url']           = \Yii::$app->request->post('link_url');
				$data['mini_title']         = \Yii::$app->request->post('mini_title');
				$data['mini_pic_media_id']  = \Yii::$app->request->post('mini_pic_media_id');
				$data['mini_appid']         = \Yii::$app->request->post('mini_appid');
				$data['mini_page']          = \Yii::$app->request->post('mini_page');
				$data['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
				$data['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
				$data['group_id']           = \Yii::$app->request->post('groupId') ?: 0;
				$data['uid']                = \Yii::$app->request->post('uid') ?: 0;
				$data['mini_title']         = trim($data['mini_title']);
				$data['text_content']       = trim($data['text_content']);
				$data['corp_id']            = $this->corp['id'];
				$data['source']             = 0;
				if (empty($data['id'])) {
					if ($data['type'] == 1) {
						$welcome = WorkWelcome::findOne(['corp_id' => $this->corp['id'], 'type' => 1]);
						if (!empty($welcome)) {
							throw new InvalidParameterException('通用成员已经创建！');
						}
					}
				}
				//添加/修改
				WorkWelcome::add($data);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-welcome/
		 * @title           欢迎语详情
		 * @description     欢迎语详情
		 * @method   post
		 * @url  http://{host_name}/api/work-welcome/detail
		 *
		 * @param id 必选 int 欢迎语id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    type int 1：全体成员2：成员
		 * @return_param    status int 1：启用0：不启用
		 * @return_param    time array 生效时间
		 * @return_param    add_type int 1图片2网页3小程序0都没有
		 * @return_param    users array 成员id
		 * @return_param    text_content string 文本内容
		 * @return_param    image_attachment_id string 图片的id
		 * @return_param    image_url string 图片的url地址
		 * @return_param    link_title string 网页标题
		 * @return_param    link_attachment_id int 网页封面的id
		 * @return_param    link_pic_url string 图文封面的url地址
		 * @return_param    link_desc string 图文消息描述
		 * @return_param    link_url string 图文消息链接
		 * @return_param    mini_title string 小程序消息标题
		 * @return_param    mini_attachment_id string 小程序消息封面的id
		 * @return_param    mini_pic_url string 小程序消息封面url
		 * @return_param    mini_appid string 小程序appid
		 * @return_param    mini_page string 小程序路径
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/15 9:52
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$users        = [];
				$data         = [];
				$work_welcome = WorkWelcome::findOne(['id' => $id]);
				$data['type'] = $work_welcome->type;
				if (!empty($work_welcome->user_ids)) {
					$user_keys = json_decode($work_welcome->user_ids, true);
					WorkDepartment::ActivityDataFormat($user_keys,$work_welcome->corp_id,[]);
					$users = $user_keys;
				}
				$data['users']  = [];
				if($work_welcome->type != 1){
					$data['users']         = $users;
				}
				$data['add_type']      = 0;
				$data['status']        = $work_welcome->status;
				$data['material_sync'] = $work_welcome->material_sync;
				$data['attachment_id'] = $work_welcome->attachment_id;
				$data['groupId']       = $work_welcome->groupId;
				$data['text_content']  = '';
				$time                  = '';
				if (!empty($work_welcome->time_json)) {
					$time = explode(',', $work_welcome->time_json);
				}
				$data['time'] = $time;
				$content      = [];
				if (!empty($work_welcome->context)) {
					$content = json_decode($work_welcome->context, true);
				}
				$contentData = WorkWelcome::getContentData($content);

				$data = WorkWelcome::getWelcomeData($data, $content, $contentData);

				return $data;

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-welcome/
		 * @title           欢迎语删除
		 * @description     欢迎语删除
		 * @method   post
		 * @url  http://{host_name}/api/work-welcome/delete
		 *
		 * @param id 必选 int 欢迎语id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/19 13:43
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				WorkWelcome::deleteAll(['id' => $id]);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-welcome/
		 * @title           设置生效时间
		 * @description     设置生效时间
		 * @method   post
		 * @url  http://{host_name}/api/work-welcome/set-time
		 *
		 * @param id 必选 int 欢迎语id
		 * @param status 必选 int 1启用0不启用
		 * @param time 必选 array 生效时间
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/19 14:59
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetTime ()
		{
			if (\Yii::$app->request->isPost) {
				$id     = \Yii::$app->request->post('id');
				$status = \Yii::$app->request->post('status');
				$time   = \Yii::$app->request->post('time');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$time_json = '';
				if (!empty($time)) {
					$time_json = implode(',', $time);
				}
				$work_welcome            = WorkWelcome::findOne($id);
				$work_welcome->time_json = $time_json;
				$work_welcome->status    = $status;
				$work_welcome->save();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}




	}