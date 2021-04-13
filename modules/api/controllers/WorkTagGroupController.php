<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/1/8
	 * Time: 10:01
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\models\GroupSort;
	use app\models\WorkTagGroup;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use yii\web\MethodNotAllowedHttpException;

	class WorkTagGroupController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           获取所有的标签分组
		 * @description     获取所有的标签分组
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-group/list
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param type 必选 int 0客户管理1通讯录
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    key int key
		 * @return_param    corp_id int corp_id
		 * @return_param    group_name string 分组名称
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 13:15
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
				$type             = \Yii::$app->request->post('type') ?: 0;
				$isMasterAccount  = \Yii::$app->request->post('isMasterAccount') ?: 1;//1账户2子账户
				$sub_id           = \Yii::$app->request->post('sub_id');
				$workTagGroupData = WorkTagGroup::find()->andWhere(['corp_id' => $this->corp['id'], 'type' => $type])->orderBy(['sort' => SORT_ASC])->all();
				$result           = [];
				if (!empty($workTagGroupData)) {
					foreach ($workTagGroupData as $group) {
						$data = $group->dumpData();
						array_push($result, $data);
					}
//					if (!empty($isMasterAccount) && !empty($sub_id)) {
//						$group = GroupSort::findOne(['isMasterAccount' => $isMasterAccount, 'corp_id' => $this->corp['id'], 'sub_id' => $sub_id]);
//						if (!empty($group)) {
//							$sort_ids = explode(',', $group->sort_ids);
//							if (!empty($sort_ids)) {
//								$result = [];
//								foreach ($sort_ids as $id) {
//									$tag_group = WorkTagGroup::findOne($id);
//									if (!empty($tag_group)) {
//										$dumpData = $tag_group->dumpData();
//										array_push($result, $dumpData);
//									}
//								}
//							}
//						}
//					}
				}

				return [
					'info' => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           添加标签组
		 * @description     添加标签组
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-group/add
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param name 必选 string 标签组名称
		 * @param type 必选 int 0客户管理1通讯录
		 * @param tagName 必选 array 标签名称，type=0时必选
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 13:46
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$name            = \Yii::$app->request->post('name');
				$type            = \Yii::$app->request->post('type') ?: 0;
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;//1账户2子账户
				$sub_id          = \Yii::$app->request->post('sub_id');
				$tagName         = \Yii::$app->request->post('tag_name',[]);
				$len             = mb_strlen($name, "utf-8");
				if($this->corp->corp_type!='verified'){
					throw new InvalidParameterException('当前企业号未认证！');
				}
				if ($len > 15) {
					throw new InvalidParameterException('名称不能超过15个字');
				}
				$workName = WorkTagGroup::find()->andWhere(['group_name' => $name, 'type' => $type, 'corp_id' => $this->corp['id']])->one();
				if (!empty($workName)) {
					throw new InvalidParameterException('分组名称不能重复');
				}
				WorkTagGroup::add(0, $this->corp['id'], $name, $type, $tagName);
				if (!empty($isMasterAccount) && !empty($sub_id)) {
					GroupSort::setTagSortId($isMasterAccount, $sub_id, $type, $this->corp['id']);
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           修改标签组
		 * @description     修改标签组
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-group/update
		 *
		 * @param id 必选 int 分组id
		 * @param name 必选 string 标签组名称
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 13:46
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$id   = \Yii::$app->request->post('id');
				$name = \Yii::$app->request->post('name');
				$type = \Yii::$app->request->post('type');
				if (empty($id) || empty($name)) {
					throw new InvalidParameterException('参数不正确');
				}
				$len = mb_strlen($name, "utf-8");
				if ($len > 15) {
					throw new InvalidParameterException('名称不能超过15个字');
				}
				$tag_group = WorkTagGroup::findOne($id);
				if($name == $tag_group->group_name){
					return true;
				}
				$workName  = WorkTagGroup::find()->andWhere(['!=', 'id', $id])->andWhere(['group_name' => $name, 'type' => $type, 'corp_id' => $tag_group->corp_id])->one();
				if (!empty($workName)) {
					throw new InvalidParameterException('分组名称不能重复');
				}
				WorkTagGroup::add($id, $this->corp['id'], $name);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           删除标签组
		 * @description     删除标签组
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-group/delete
		 *
		 * @param id 必选 int 分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/8 13:55
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id              = \Yii::$app->request->post('id');
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;//1账户2子账户
				$sub_id          = \Yii::$app->request->post('sub_id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确');
				}
				$work_group = WorkTagGroup::findOne($id);
				$type       = $work_group->type;
				if ($work_group->type == 1) {
					WorkTagGroup::updateGroupId($id, 1, $work_group);
				} else {
					$result = WorkTagGroup::syncWxGroup($id);
					if ($result) {
						WorkTagGroup::updateGroupId($id, 0, $work_group);
					}
				}
				WorkTagGroup::deleteAll(['id' => $id]);
				if (!empty($isMasterAccount) && !empty($sub_id)) {
					GroupSort::setTagSortId($isMasterAccount, $sub_id, $type, $this->corp['id']);
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           分组排序
		 * @description     分组排序
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-group/group-sort
		 *
		 * @param ids 必选 array  分组id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/1/22 9:37
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupSort ()
		{
			if (\Yii::$app->request->isPost) {
				$ids             = \Yii::$app->request->post('ids');
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount') ?: 1;//1账户2子账户
				$sub_id          = \Yii::$app->request->post('sub_id');
				$source          = 1;
				if (empty($ids) || !is_array($ids)) {
					throw new InvalidParameterException('参数不正确！');
				}
				foreach ($ids as $k => $v) {
					$tag_group       = WorkTagGroup::findOne($v);
					$tag_group->sort = $k;
					$tag_group->save();
					if (empty($tag_group->type)) {
						$source = 2;
					}
				}
				if (!empty($isMasterAccount) && !empty($sub_id)) {
					$group_sort = GroupSort::findOne(['isMasterAccount' => $isMasterAccount, 'sub_id' => $sub_id]);
					if (empty($group_sort)) {
						$group_sort              = new GroupSort();
						$group_sort->create_time = DateUtil::getCurrentTime();
					}
					$group_sort->source          = $source;
					$group_sort->sort_ids        = implode(',', $ids);
					$group_sort->isMasterAccount = $isMasterAccount;
					$group_sort->sub_id          = $sub_id;
					$group_sort->save();
				}

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-group/
		 * @title           分组标签数据
		 * @description     分组标签
		 * @method   请求方式
		 * @url  http://{host_name}/api/work-tag-group/tag-group
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param type 必选 string 0客户管理1通讯录
		 * @param is_not_add 必选 string 是否不显示空分组，1是、0否
		 * @param external_userid 必选 string 外部联系人
		 *
		 * @return          {"error":0,"data":[{"name":"未分组","data":[{"id":660,"tagname":"jjjyh9999"}]},{"name":"iooo","data":[]}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 分组名
		 * @return_param    id string 标签id
		 * @return_param    tagname string 标签名字
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-05-28 9:37
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionTagGroup ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$type            = \Yii::$app->request->post('type', 0);
			$isNotAdd        = \Yii::$app->request->post('is_not_add', 0);
			$external_userid = \Yii::$app->request->post('external_userid', 0);

			return WorkTagGroup::groupTagData($this->corp->id, $type, $isNotAdd, $external_userid);
		}
	}