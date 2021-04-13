<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/1/8
	 * Time: 09:15
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\InvalidDataException;
	use app\models\PublicSeaContactFollowUser;
	use app\models\PublicSeaCustomer;
	use app\models\UserCorpRelation;
	use app\models\WorkContactWay;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkTag;
	use app\models\WorkTagChat;
	use app\models\WorkTagContact;
	use app\models\WorkTagFollowUser;
	use app\models\WorkTagGroup;
	use app\models\WorkTagUser;
	use app\modules\api\components\WorkBaseController;
	use app\queue\WorkTagFollowUserJob;
	use yii\web\MethodNotAllowedHttpException;
	use app\queue\SyncWorkTagUserJob;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\src\dataStructure\ExternalContactTagGroup;
	use app\util\SUtils;

	class WorkTagController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-tag/
		 * @title           通讯录/客户管理标签列表
		 * @description     通讯录/客户管理标签列表
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/list
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param group_id 可选 int 0表示全部标签否则是对应的分组标签
		 * @param type 必选 int 0客户管理1通讯录
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":"1","info":[{"key":1,"id":1,"tagname":"aa","num":0}],"last_sys_time":"2020-01-08 14:00:00"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 唯一id
		 * @return_param    key int key
		 * @return_param    tagname string 标签名称
		 * @return_param    num int 客户数
		 * @return_param    last_sys_time string 上次同步时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 15:50
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				$group_id = \Yii::$app->request->post('group_id');
				$is_all   = \Yii::$app->request->post('is_all'); //0带分页1不带分页
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				$type     = \Yii::$app->request->post('type');
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$userInfo = UserCorpRelation::findOne(['corp_id' => $this->corp->id]);
				if (empty($userInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$tagIds = [];
				$offset = ($page - 1) * $pageSize;
				if ($is_all == 0) {
					$tagData = WorkTag::find()->andWhere(['type' => $type, 'is_del' => 0, 'corp_id' => $this->corp['id']]);
					if (!empty($group_id)) {
						$tagData = $tagData->andWhere(['in', 'group_id', $group_id]);
					}
					$count      = $tagData->count();
					$tagsList   = $tagData->orderBy(['id' => SORT_DESC])->all();
					$tagInfoNew = $tagData->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
				} else {
					$tagData = WorkTag::find()->andWhere(['type' => $type, 'is_del' => 0, 'corp_id' => $this->corp['id']]);
					if (!empty($group_id)) {
						$tagData = $tagData->andWhere(['in', 'group_id', $group_id]);
					}
					$count = $tagData->count();
					//$tagsList = $tagData->orderBy(['id' => SORT_DESC])->all();
					$tagInfoNew = $tagData->orderBy(['id' => SORT_DESC])->all();
				}
				$result = [];
				if (!empty($tagInfoNew)) {
					foreach ($tagInfoNew as $key => $tag) {
						if (empty($type)) {
							$followUser = WorkExternalContactFollowUser::find()->alias('f');
							$followUser = $followUser->leftJoin('{{%work_tag_follow_user}} c', '`f`.`id` = `c`.`follow_user_id`');
							$followUser = $followUser->leftJoin('{{%work_tag}} t', '`t`.`id` = `c`.`tag_id`');
							$num        = $followUser->where(['c.tag_id' => $tag->id, 'c.status' => 1, 'c.corp_id' => $this->corp['id'], 'f.del_type' => WorkExternalContactFollowUser::WORK_CON_EX, 't.is_del' => 0])->groupBy('f.id');
							$num        = $num->count();
							//非企微客户数量
							$publicFollowUser = PublicSeaContactFollowUser::find()->alias('f');
							$publicFollowUser = $publicFollowUser->leftJoin('{{%public_sea_tag}} c', '`f`.`id` = `c`.`follow_user_id`');
							$publicFollowUser = $publicFollowUser->leftJoin('{{%work_tag}} t', '`t`.`id` = `c`.`tag_id`');
							$publicFollowUser = $publicFollowUser->where(['f.corp_id' => $this->corp['id'], 'f.is_reclaim' => 0, 'c.tag_id' => $tag->id, 'c.status' => 1, 'c.corp_id' => $this->corp['id'], 't.is_del' => 0])->groupBy('f.id');
							$publicNum        = $publicFollowUser->count();
							//公海池客户
							$publicSea = PublicSeaCustomer::find()->alias('f');
							$publicSea = $publicSea->leftJoin('{{%public_sea_private_tag}} pt', '`f`.`id` = `pt`.`sea_id`');
							$publicSea = $publicSea->leftJoin('{{%work_tag}} t', '`t`.`id` = `pt`.`tag_id`');
							$publicSea = $publicSea->where(['f.uid' => $userInfo->uid, 'f.is_claim' => 0, 'pt.corp_id' => $this->corp['id'], 'pt.tag_id' => $tag->id, 'pt.status' => 1, 't.is_del' => 0])->groupBy('f.id');
							$seaNum    = $publicSea->count();

							$num = $num + $publicNum + $seaNum;
							//客户群
							$workTagChat = WorkTagChat::find()->alias('wtc');
							$workTagChat = $workTagChat->leftJoin('{{%work_tag}} t', '`t`.`id` = `wtc`.`tag_id`');
							$workTagChat = $workTagChat->where(['wtc.tag_id' => $tag->id,'wtc.corp_id' => $this->corp['id'], 'wtc.status' => 1])->groupBy('wtc.chat_id');

							$chatNum = $workTagChat->count();
						} else {
							$num = WorkTagUser::find()->andWhere(['tag_id' => $tag->id])->count();
							$chatNum = 0;
						}
						$result[$key]['key']     = $tag->id;
						$result[$key]['id']      = $tag->id;
						$result[$key]['tagname'] = $tag->tagname;
						$result[$key]['num']     = $num;
						$result[$key]['chatNum'] = $chatNum;
					}
				}
				$tag_all = [];
				if (!empty($tagsList) && $is_all == 0) {
					foreach ($tagsList as $key => $tag) {
						array_push($tagIds, $tag->id);
					}
				}

				//最后一次同步标签时间
				$last_tag_time          = $this->corp->last_tag_time;
				$last_customer_tag_time = $this->corp->last_customer_tag_time;
				if (!empty($last_tag_time)) {
					$last_tag_time = date('Y-m-d H:i:s', $last_tag_time);
				}
				if (!empty($last_customer_tag_time)) {
					$last_customer_tag_time = date('Y-m-d H:i:s', $last_customer_tag_time);
				}

				return [
					'count'                  => $count,
					'info'                   => $result,
					'tag_ids'                => $tagIds,
					'tag_all'                => $tag_all,
					'last_sys_time'          => $last_tag_time,
					'last_customer_tag_time' => $last_customer_tag_time,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           通讯录/客户管理标签添加
		 * @description     通讯录/客户管理标签添加
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/add
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param type 必选 int 0客户管理1通讯录
		 * @param group_id 必选 int 分组id
		 * @param name 必选 array 标签名称
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 10:35
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$name     = \Yii::$app->request->post('name');
				$type     = \Yii::$app->request->post('type') ?: 0;
				$group_id = \Yii::$app->request->post('group_id');
				if (empty($this->corp) || empty($group_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($this->corp->corp_type != 'verified') {
					throw new InvalidParameterException('当前企业号未认证！');
				}
				if (empty($name)) {
					throw new InvalidParameterException('标签名称不能为空！');
				}
				if (count($name) != count(array_unique($name))) {
					throw new InvalidParameterException('标签名称存在重复');
				}
				foreach ($name as $v) {
					$len = mb_strlen($v, "utf-8");
					if ($len > 15) {
						throw new InvalidParameterException('名称不能超过15个字');
					}
				}
				if ($type == 1) {
					//企业微信标签不能超过3000个
					$tagCount = WorkTag::find()->andWhere(['is_del' => 0, 'type' => $type, 'corp_id' => $this->corp['id']])->count();
					$count    = count($name) + $tagCount;
					if ($tagCount >= 3000 || $count > 3000) {
						throw new InvalidParameterException('标签数量不能超过10个');
					}
				}
				$tagName = WorkTag::find()->andWhere(['tagname' => $name, 'is_del' => 0, 'type' => $type, 'corp_id' => $this->corp['id']])->one();
				if (!empty($tagName)) {
					throw new InvalidParameterException('标签名称不能重复');
				}
				WorkTag::add(0, $this->corp['id'], $name, $type, $group_id);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           通讯录/客户管理标签修改
		 * @description     通讯录/客户管理标签修改
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/update
		 *
		 * @param id 必选 int 标签id
		 * @param name 必选 string 标签名称
		 * @param type 必选 int 0客户管理1通讯录
		 * @param group_id 必选 int 分组id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 10:35
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 * @throws \yii\db\Exception
		 */
		public function actionUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$id       = \Yii::$app->request->post('id');
				$name     = \Yii::$app->request->post('name');
				$group_id = \Yii::$app->request->post('group_id');
				$type     = \Yii::$app->request->post('type') ?: 0;
				if (empty($id) || empty($group_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($name)) {
					throw new InvalidParameterException('标签名称不能为空！');
				}
				$len = mb_strlen($name, "utf-8");
				if ($len > 15) {
					throw new InvalidParameterException('名称不能超过15个字');
				}
				$tag = WorkTag::findOne($id);
				if ($tag->group_id == $group_id && $name == $tag->tagname && !empty($tag->tagid)) {
					return true;
				}
				$tagName = WorkTag::find()->andWhere(['tagname' => $name, 'is_del' => 0, 'type' => $type, 'corp_id' => $tag->corp_id])->andWhere(['<>', 'id', $id]);
				$tagName = $tagName->one();
				if (!empty($tagName)) {
					throw new InvalidParameterException('标签名称不能重复');
				}
				WorkTag::add($id, $this->corp['id'], $name, $type, $group_id);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           通讯录/客户管理标签删除
		 * @description     通讯录/客户管理标签删除
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/delete
		 *
		 * @param id 必选 int 标签id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 10:38
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidDataException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确');
				}
				$transaction = \Yii::$app->mdb->beginTransaction();
				try {
					$workTag         = WorkTag::findOne($id);
					$workTag->is_del = 1;
					$workTag->save();
					//同步到企业微信
					if ($workTag->tagid) {
						$tagId = $workTag->tagid;
						WorkTag::deleteTag($workTag->corp_id, $workTag->type, $tagId);
					}
					$transaction->commit();
					//删除渠道活码的标签
					WorkContactWay::deleteContactTag($workTag->corp_id, $id);
					//删除客户和员工的标签
					WorkTagContact::deleteTag($workTag->type, $id);
				} catch (\Exception $e) {
					$transaction->rollBack();
					\Yii::error($e->getMessage(), 'actionDelete');
					throw new InvalidDataException($e->getMessage());
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           批量删除接口
		 * @description     批量删除接口
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/delete-more
		 *
		 * @param ids 必选 array 删除的标签id数组
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 10:59
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDeleteMore ()
		{
			if (\Yii::$app->request->isPost) {
				$ids = \Yii::$app->request->post('ids');
				if (empty($ids) || !is_array($ids)) {
					throw new InvalidParameterException('参数不正确');
				}
				$success = 0;
				$errorData = [];
				foreach ($ids as $id) {
					$transaction = \Yii::$app->mdb->beginTransaction();
					try {
						$workTag         = WorkTag::findOne($id);
						$workTag->is_del = 1;
						$workTag->save();
						//同步到企业微信
						if (!empty($workTag->tagid)) {
							WorkTag::deleteTag($workTag->corp_id, $workTag->type, $workTag->tagid);
						}
						$transaction->commit();
					} catch (\Exception $e) {
						$transaction->rollBack();
						\Yii::error($e->getMessage(), 'actionDeleteMore');
						array_push($errorData, $e->getMessage());
						continue;
					}
					//删除渠道活码的标签
					WorkContactWay::deleteContactTag($workTag->corp_id, $id);
					//删除客户和员工的标签
					WorkTagContact::deleteTag($workTag->type, $id);
					$success++;
				}

				$sum = count($ids);
				$textHtml = '删除' . $sum . '条标签，';
				if (!empty($success)) {
					$textHtml .= '成功' . $success . '条，';
				}
				if (!empty($errorData)) {
					$errorData = array_unique($errorData);
					$errorStr  = implode('、', $errorData);
					$restNum   = $sum - $success;
					if ($restNum > 0) {
						$textHtml .= '失败' . $restNum . '条，原因如下：' . $errorStr . '。';
					}
				}
				$textHtml = trim($textHtml, '，');
				if (!empty($restNum) && $sum == $restNum) {
					throw new InvalidParameterException($textHtml);
				}

				return ['textHtml' => $textHtml];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           同步企业微信标签
		 * @description     同步企业微信标签
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/sync-work-tag
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param group_id 必选 int 分组id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 14:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionSyncWorkTag ()
		{
			if (\Yii::$app->request->isPost) {
				$group_id = \Yii::$app->request->post('group_id');
				if (empty($this->corp) || empty($group_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$jobId = \Yii::$app->work->push(new SyncWorkTagUserJob([
					'corp_id'  => $this->corp['id'],
					'group_id' => $group_id
				]));

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           同步客户标签
		 * @description     同步客户标签
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/sync-work-tag-external
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/21 15:47
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionSyncWorkTagExternal ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				WorkTag::syncWorkTagExternal($this->corp['id']);

				$this->corp->last_customer_tag_time = time();
				$this->corp->save();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag/
		 * @title           批量移动标签
		 * @description     批量移动标签
		 * @method   post
		 * @url  http://{host_name}/api/work-tag/remove-tag
		 *
		 * @param new_group_id 必选 int 新的分组id
		 * @param tag_id 必选 array 标签列表的id
		 * @param type 必选 int 0客户管理1通讯录
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/4 10:43
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionRemoveTag ()
		{
			if (\Yii::$app->request->isPost) {
				$new_group_id = \Yii::$app->request->post('new_group_id');
				$tag_id       = \Yii::$app->request->post('tag_id');
				$type         = \Yii::$app->request->post('type') ?: 0; //1 通讯录 0 客户管理
				if (empty($new_group_id) || empty($tag_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($type != 0 && $type != 1) {
					throw new InvalidParameterException('参数不正确！');
				}
				$tagFollowId = [];
				$success = 0;
				$errorData = [];
				if (!empty($tag_id)) {
					foreach ($tag_id as $tag) {
						$transaction = \Yii::$app->mdb->beginTransaction();
						try{
							$work_tag           = WorkTag::findOne($tag);
							$old_group_id       = $work_tag->group_id;
							$work_tag->group_id = $new_group_id;
							$work_tag->save();

							$tag_group     = WorkTagGroup::findOne($old_group_id);
							$new_tag_group = WorkTagGroup::findOne($new_group_id);
							if (empty($work_tag->type)) {
								try {
									$workApi = WorkUtils::getWorkApi($tag_group->corp_id, WorkUtils::EXTERNAL_API);
									$flag    = false;
									if (!empty($work_tag->tagid)) {
										$drop_tag = $workApi->ECDelCorpTag([$work_tag->tagid]);
										if ($drop_tag['errcode'] == 0) {
											$flag = true;
										}
									} else {
										$flag = true;
									}
									if ($flag) {
										$etcTag['group_name'] = strval($new_tag_group->group_name);
										$tag_new              = [
											'name' => $work_tag->tagname,
										];
										$etcTag['tag']        = [$tag_new];
										$ECTagGroup           = ExternalContactTagGroup::parseFromArray($etcTag);
										$data                 = $workApi->ECAddCorpTag($ECTagGroup);
										$tag_data             = SUtils::Object2Array($data);
										$work_tag->tagid      = $tag_data['tag'][0]['id'];
										$work_tag->save();

										$followUserIds = WorkTagFollowUser::find()->where(['tag_id' => $work_tag->id, 'status' => 1])->asArray()->all();
										if (!empty($followUserIds)) {
											foreach ($followUserIds as $userId) {
												array_push($tagFollowId, $userId['id']);
											}
										}

									}
								} catch (\Exception $e) {
									\Yii::error($e->getMessage(), 'actionRemoveTag');
									$message = $e->getMessage();
									if (strpos($message, '40013') !== false) {
										$message = 'corpid无效';
									}
									if (strpos($message, '81011') !== false) {
										$message = '无权限操作标签';
									}
									throw new InvalidDataException($message);
								}

							}
							$transaction->commit();
						} catch (\Exception $e) {
							$transaction->rollBack();
							\Yii::error($e->getMessage(), 'actionDeleteMore');
							array_push($errorData, $e->getMessage());
							continue;
						}
						$success++;
					}
				}
				if (!empty($tagFollowId)) {
					\Yii::$app->queue->push(new WorkTagFollowUserJob(
						['followIds' => $tagFollowId]
					));
				}
				$sum = count($tag_id);
				$textHtml = '移动' . $sum . '条标签，';
				if (!empty($success)) {
					$textHtml .= '成功' . $success . '条，';
				}
				if (!empty($errorData)) {
					$errorData = array_unique($errorData);
					$errorStr  = implode('、', $errorData);
					$restNum   = $sum - $success;
					if ($restNum > 0) {
						$textHtml .= '失败' . $restNum . '条，原因如下：' . $errorStr . '。';
					}
				}
				$textHtml = trim($textHtml, '，');
				if (!empty($restNum) && $sum == $restNum) {
					throw new InvalidParameterException($textHtml);
				}

				return ['textHtml' => $textHtml];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

	}