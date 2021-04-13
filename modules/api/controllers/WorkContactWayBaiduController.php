<?php
	/**
	 * 百度渠道活码
	 * User: xingchanngyu
	 * Date: 2020/07/10
	 * Time: 14:30
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\UserBaidu;
	use app\models\WorkContactWayBaidu;
	use app\models\WorkContactWayBaiduDate;
	use app\models\WorkContactWayBaiduDateUser;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkTag;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWorkWayBaiduJob;
	use app\util\DateUtil;
	use app\util\SUtils;

	class WorkContactWayBaiduController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way-baidu/
		 * @title           百度统计列表
		 * @description     百度统计列表
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way-baidu/list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param title 可选 string 活码名称
		 * @param type 可选 int 1单人、2多人
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 14:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id             = $this->corp->id;
			$page                = \Yii::$app->request->post('page') ?: 1;
			$pageSize            = \Yii::$app->request->post('pageSize') ?: 15;
			$type                = \Yii::$app->request->post('type') ?: 0;
			$title               = \Yii::$app->request->post('title');
			$uid                 = \Yii::$app->request->post('uid',0);
			$workContactWayBaidu = WorkContactWayBaidu::find()->where(['corp_id' => $corp_id, 'is_del' => 0]);
			if (!empty($type)) {
				$workContactWayBaidu = $workContactWayBaidu->andWhere(['type' => $type]);
			}
			if (!empty($title)) {
				$workContactWayBaidu = $workContactWayBaidu->andWhere(['like', 'title', trim($title)]);
			}
			//获取所有的key
			$keys   = [];
			$idList = $workContactWayBaidu->select('id')->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					array_push($keys, (string) $idInfo['id']);
				}
			}

			$offset              = ($page - 1) * $pageSize;
			$count               = $workContactWayBaidu->count();
			$workContactWayBaidu = $workContactWayBaidu->select('*')->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$contactWay          = [];
			$site_url            = \Yii::$app->params['site_url'];
			if (!empty($workContactWayBaidu)) {
				foreach ($workContactWayBaidu as $way) {
					/**@var WorkContactWayBaidu $way * */
					$wayInfo        = $way->dumpData();
					$wayInfo['key'] = $wayInfo['id'];
					$tag_name       = [];
					if (!empty($wayInfo['tag_ids'])) {
						$tag_ids = explode(',', $wayInfo['tag_ids']);
						foreach ($tag_ids as $tag_id) {
							if(!empty($tag_id)){
								$work_tag = WorkTag::findOne($tag_id);
								if(!empty($work_tag)){
									array_push($tag_name, $work_tag->tagname);
								}
							}
						}
					}
					$wayInfo['tag_ids'] = $tag_name;
					$wayInfo['url']     = $site_url . '/baidu/get-code-url?code_id=' . $wayInfo['id'] . '&bd_vid=&logidUrl=&newType=';
					array_push($contactWay, $wayInfo);
				}
			}

			//获取token
			$token_id  = $token = '';
			$userBaidu = UserBaidu::findOne(['uid' => $uid]);
			if (!empty($userBaidu)) {
				$token_id = $userBaidu->id;
				$token    = $userBaidu->token;
			}

			return [
				'count'            => $count,
				'contact_way_list' => $contactWay,
				'keys'             => $keys,
				'token_id'         => $token_id,
				'token'            => $token,
			];
		}

		/**
		 *
		 * @return mixed
		 *
		 * @throws InvalidParameterException
		 */
		private function getOtherInfo ()
		{
			$data['id']                 = \Yii::$app->request->post('id') ?: 0;
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
			$data['status']             = \Yii::$app->request->post('status');
			$data['tag_ids']            = \Yii::$app->request->post('tag_ids');
			$data['user_key']           = \Yii::$app->request->post('user');
			$data['title']              = \Yii::$app->request->post('title');
			$data['type']               = \Yii::$app->request->post('type') ?: 1;
			$data['skip_verify']        = \Yii::$app->request->post('skip_verify') ?: 0;
			$data['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
			$data['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
			$data['group_id']           = \Yii::$app->request->post('groupId') ?: 0;
			$data['uid']                = \Yii::$app->request->post('uid') ?: 0;
			$data['corp_id']            = $this->corp->id;
			$data['way_group_id']       = \Yii::$app->request->post('way_group_id') ?: '';
			$data['open_date']          = \Yii::$app->request->post('specialTime') ?: 0;
			$data['choose_date']        = \Yii::$app->request->post('specialDateList');
			$data['week_user']          = \Yii::$app->request->post('specialWeekList');
			$data['mini_title']         = trim($data['mini_title']);
			$data['text_content']       = trim($data['text_content']);
			if ($data['open_date'] && empty($data['choose_date'])) {
				throw new InvalidDataException('选填时间段的成员不能为空！');
			}
			if (empty($data['week_user'])) {
				throw new InvalidDataException('每周配置的企业成员不能为空！');
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way-baidu/
		 * @title           新增百度活码
		 * @description     新增百度活码
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way-baidu/add
		 *
		 * @param id 可选 int 修改时必填
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param way_group_id 必选 string 渠道活码分组id
		 * @param type 必选 int 联系方式类型,1-单人, 2-多人
		 * @param scene 必选 int 场景，1-在小程序中联系，2-通过二维码联系
		 * @param style 可选 int 在小程序中联系时使用的控件样式，详见附表
		 * @param remark 可选 string 联系方式的备注信息，用于助记，不超过30个字符
		 * @param skip_verify 可选 boolean 外部客户添加时是否无需验证，默认为true
		 * @param state string 可选 企业自定义的state参数，用于区分不同的添加渠道
		 * @param user array 可选 使用该联系方式的用户userID列表，在type为1时为必填，且只能有一个
		 * @param party array 可选 使用该联系方式的部门id列表，只在type为2时有效
		 * @param tag_ids string 可选 标签id多个逗号隔开
		 * @param open_date bool 可选 true开启false关闭
		 * @param choose_date array 可选 日期活码
		 * @param week_user array 可选 每周配置的人员
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 14:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionAdd ()
		{
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id   = $this->corp->id;
			$otherInfo = $this->getOtherInfo();

			$contact_way = WorkContactWayBaidu::find()->where(['title' => $otherInfo['title'], 'corp_id' => $corp_id, 'is_del' => 0]);
			if (!empty($otherInfo['id'])) {
				$contact_way = $contact_way->andwhere(['<>', 'id', $otherInfo['id']]);
			}
			$contact_way = $contact_way->one();
			if (!empty($contact_way)) {
				throw new InvalidDataException('活动名称不能存在重复！');
			}

			$week_user = \Yii::$app->request->post('specialWeekList');
			$type      = \Yii::$app->request->post('type') ?: 1;
			WorkContactWayBaiduDate::verifyData($week_user, $type);

			WorkWelcome::verify($otherInfo, 1);

			$wayId = WorkContactWayBaidu::setWay($otherInfo);

			return [
				'way_id' => $wayId,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way-baidu/
		 * @title           获取活动详情
		 * @description     获取活动详情
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way-baidu/get
		 *
		 * @param config_id 必选 int 活动id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 14:26
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$way_id = \Yii::$app->request->post('config_id') ?: '';

			if (empty($way_id)) {
				throw new InvalidDataException('参数不正确！');
			}
			$workContactWayBaidu = WorkContactWayBaidu::findOne($way_id);
			if (empty($workContactWayBaidu) || $workContactWayBaidu->corp_id != $this->corp->id) {
				throw new InvalidDataException('参数不正确！');
			}
			$contact_way                      = $workContactWayBaidu->dumpData();
			$welcome_content['add_type']      = 0;
			$welcome_content['status']        = $contact_way['status'];
			$welcome_content['material_sync'] = $contact_way['material_sync'];
			$welcome_content['groupId']       = $contact_way['groupId'];
			$welcome_content['attachment_id'] = $contact_way['attachment_id'];
			$welcome_content['text_content']  = '';
			$content                          = [];
			if (!empty($contact_way['content'])) {
				$content = json_decode($contact_way['content'], true);
			}
			$contentData                    = WorkWelcome::getContentData($content);
			$welcome_content                = WorkWelcome::getWelcomeData($welcome_content, $content, $contentData);
			$contact_way['welcome_content'] = $welcome_content;
			$contact_way['tag_ids']         = !empty($contact_way['tag_ids']) ? explode(',', $contact_way['tag_ids']) : [];
			$contact_way['specialDateList'] = WorkContactWayBaiduDate::getChooseDate($contact_way['id'], $contact_way['corp_id']);
			$contact_way['specialWeekList'] = WorkContactWayBaiduDate::getWeekUser($contact_way['id'], $contact_way['corp_id']);

			return [
				'contact_way' => $contact_way,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-chat-way-baidu/
		 * @title           获取客户接口
		 * @description     获取客户接口
		 * @method   post
		 * @url  http://{host_name}/api/work-chat-way-baidu/get-custom
		 *
		 * @param way_id 必选 int 活动id
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 14:28
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGetCustom ()
		{
			$id       = \Yii::$app->request->post('way_id') ?: '';
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
			if (empty($id)) {
				throw new InvalidDataException('参数不正确！');
			}
			$followUser = WorkExternalContactFollowUser::find()->where(['baidu_way_id' => $id]);
			$offset     = ($page - 1) * $pageSize;
			$count      = $followUser->count();
			$followUser = $followUser->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$info       = [];
			if (!empty($followUser)) {
				/** @var WorkExternalContactFollowUser $follow */
				foreach ($followUser as $key => $follow) {
					$userInfo        = $follow->user->dumpData();
					$customInfo      = $follow->externalUser->dumpData();
					$department_name = $userInfo['department_name'] . '--' . $userInfo['name'];
					$info[$key]      = [
						'key'             => $follow->id,
						'name'            => $customInfo['name'],
						'department_name' => $department_name,
						'create_time'     => date('Y-m-d H:i:s', $follow->createtime)
					];
				}
			}

			return [
				'count'            => $count,
				'contact_way_list' => $info,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-baidu/
		 * @title           批量编辑欢迎语
		 * @description     批量编辑欢迎语
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-baidu/edit-welcome
		 *
		 * @param ids 必选 array 批量编辑的id
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020/7/13 10:16
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionEditWelcome ()
		{
			$ids                             = \Yii::$app->request->post('ids');
			$otherInfo['add_type']           = \Yii::$app->request->post('add_type');//1图片2网页3小程序
			$otherInfo['text_content']       = \Yii::$app->request->post('text_content');
			$otherInfo['media_id']           = \Yii::$app->request->post('media_id');
			$otherInfo['link_title']         = \Yii::$app->request->post('link_title');
			$otherInfo['link_attachment_id'] = \Yii::$app->request->post('link_attachment_id');
			$otherInfo['link_desc']          = \Yii::$app->request->post('link_desc');
			$otherInfo['link_url']           = \Yii::$app->request->post('link_url');
			$otherInfo['mini_title']         = \Yii::$app->request->post('mini_title');
			$otherInfo['mini_pic_media_id']  = \Yii::$app->request->post('mini_pic_media_id');
			$otherInfo['mini_appid']         = \Yii::$app->request->post('mini_appid');
			$otherInfo['mini_page']          = \Yii::$app->request->post('mini_page');
			$otherInfo['status']             = \Yii::$app->request->post('status');
			$otherInfo['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
			$otherInfo['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
			$otherInfo['group_id']           = \Yii::$app->request->post('groupId') ?: 0;
			$otherInfo['uid']                = \Yii::$app->request->post('uid') ?: 0;

			if (empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			$transaction = \Yii::$app->db->beginTransaction();
			try {
				$sync_attachment_id = 0;
				foreach ($ids as $id) {
					$way = WorkContactWayBaidu::findOne($id);
					if (empty($way)) {
						continue;
					}
					$way->status        = $otherInfo['status'];
					$way->attachment_id = $otherInfo['attachment_id'];
					if ($otherInfo['status'] == 1) {
						$content      = WorkWelcome::getContent($otherInfo);
						$way->content = json_encode($content);
						//欢迎语同步到内容库
						if ($otherInfo['material_sync'] == 1 && empty($attachment_id) && empty($sync_attachment_id)) {
							$otherInfo['sync_attachment_id'] = $sync_attachment_id;
							$sync_attachment_id              = WorkWelcome::syncData($otherInfo);
						}
						$way->sync_attachment_id = $sync_attachment_id;
						$way->material_sync      = $otherInfo['material_sync'];
						$way->groupId            = $otherInfo['group_id'];
						if ($otherInfo['add_type'] == 3 && !empty($otherInfo['attachment_id'])) {
							$otherInfo['corp_id']  = $way->corp_id;
							$way->work_material_id = WorkWelcome::getMaterialId($otherInfo);
						}
					}
					$way->save();
				}
				$transaction->commit();
			} catch (\Exception $e) {
				$transaction->rollBack();
				\Yii::error($e->getMessage(), 'message');
				throw new InvalidDataException("编辑失败");
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way-baidu/
		 * @title           批量编辑成员
		 * @description     批量编辑成员
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way-baidu/edit-all
		 *
		 * @param ids 必选 array 批量编辑的id
		 * @param type int 可选 0不提交1提交
		 * @param open_date bool 可选 true开启false关闭
		 * @param choose_date array 可选 日期活码
		 * @param week_user array 可选 每周配置的人员
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-14 14:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionEditAll ()
		{
			$ids         = \Yii::$app->request->post('ids');
			$type        = \Yii::$app->request->post('type');
			$week_user   = \Yii::$app->request->post('specialWeekList');
			$choose_date = \Yii::$app->request->post('specialDateList');
			$open_date   = \Yii::$app->request->post('specialTime');
			$specialType = \Yii::$app->request->post('specialType') ?: 1;
			if (empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			/**sym 刪除選擇部門但是查询需要回写*/
			WorkDepartment::FormatData($choose_date,$week_user);
			$contactWay = WorkContactWayBaidu::find()->where(['id' => $ids])->select('type')->asArray()->all();
			$userType   = $contactWay[0]['type'];
			if (empty($type)) {
				$data = array_column($contactWay, 'type');
				if (count(array_unique($data)) > 1) {
					throw new InvalidDataException('只针对同一活码类型进行批量操作，请重新选择！');
				}

				return [
					'type' => $userType,
				];
			}
			$transaction = \Yii::$app->db->beginTransaction();
			try {
				foreach ($ids as $id) {
					$way = WorkContactWayBaidu::findOne($id);
					if (empty($way)) {
						continue;
					}
					if ($specialType == 1 || ($specialType == 2 && $open_date == 1)) {
						$wayDate = WorkContactWayBaiduDate::getNowUser($week_user, $choose_date, $way->corp_id, $open_date, $userType, 1);
					}
					if ($specialType == 2 && empty($open_date)) {
						$w = date("w");
						switch ($w) {
							case 0:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::SUNDAY_DAY);
								break;
							case 1:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::MONDAY_DAY);
								break;
							case 2:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::TUESDAY_DAY);
								break;
							case 3:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::WEDNESDAY_DAY);
								break;
							case 4:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::THURSDAY_DAY);
								break;
							case 5:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::FRIDAY_DAY);
								break;
							case 6:
								$wayDate = WorkContactWayBaiduDate::getEditDateUser($id, $way->type, WorkContactWayBaiduDate::SATURDAY_DAY);
								break;
						}
					}
					$userId  = $wayDate['userId'];
					$partyId = $wayDate['partyId'];

					if ($specialType == 1) {
						if (!empty($week_user)) {
							WorkContactWayBaiduDate::setWeekData($week_user, $way->id);
						}
					} else {
						if ($open_date) {
							$way->open_date = 1;
							$way->save();
						} else {
							$way->open_date = 0;
							$way->save();
						}
						$wayDate = WorkContactWayBaiduDate::find()->where(['way_id' => $way->id, 'type' => 1])->all();
						if (!empty($wayDate)) {
							foreach ($wayDate as $date) {
								WorkContactWayBaiduDateUser::deleteAll(['date_id' => $date->id]);
							}
							WorkContactWayBaiduDate::deleteAll(['way_id' => $way->id, 'type' => 1]);
						}
						if ($way->open_date == 1) {
							//同步到渠道活码日期成员表
							$res = WorkContactWayBaiduDate::setData($choose_date, $way->id, 1);
							\Yii::error($res, '$res');
						}
					}
					if (!empty($userId) || !empty($partyId)) {
						//不用实时改变，下面的代码就不需要了
//						\Yii::$app->queue->push(new SyncWorkWayBaiduJob([
//							'baidu_id' => $way->id,
//							'user'     => $userId,
//							'party'    => $partyId,
//						]));
					}
				}
				$transaction->commit();
			} catch (\Exception $e) {
				$transaction->rollBack();
				\Yii::error($e->getMessage(), 'message');
				throw new InvalidDataException("编辑失败");
			}

			return true;
		}
	}