<?php
	/**
	 * 聊天打标签
	 * User: xingchangyu
	 * Date: 2020/08/04
	 * Time: 15:57
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\UserCorpRelation;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkMsgAuditInfoText;
	use app\models\WorkMsgAuditUser;
	use app\util\WebsocketUtil;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkTag;
	use app\models\WorkTagKeywordRule;
	use app\models\WorkUser;
	use app\models\WorkUserTagExternal;
	use app\models\WorkUserTagRule;
	use app\modules\api\components\WorkBaseController;
	use yii\db\Expression;

	class WorkTagKeywordRuleController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           标签规则列表
		 * @description     标签规则列表
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/list
		 *
		 * @param corp_id 必选 string 企业id
		 * @param tag_ids 可选 string 标签id
		 * @param status 可选 string 状态：0全部、1关闭、2开启
		 * @param page 可选 string 分页页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"2","keys":["1","2"],"tagIdData":["852","853","867","866","868"],"ruleData":[{"id":2,"key":"2","corp_id":1,"keyword":["ewqewq","eqwqw"],"status":2,"tagData":["怪兽","呵呵呵呵","dasd"],"user_num":0,"external_num":0},{"id":1,"key":"1","corp_id":1,"keyword":["ewq","ewqw"],"status":2,"tagData":["8888888888888","uyyt"],"user_num":0,"external_num":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    keys array 总键值
		 * @return_param    tagIdData array 已添加过的标签id
		 * @return_param    ruleData array 规则列表
		 * @return_param    ruleData.id string 规则id
		 * @return_param    ruleData.key string 规则key
		 * @return_param    ruleData.keyword array 关键词名称
		 * @return_param    ruleData.tagData array 标签名称
		 * @return_param    ruleData.status string 状态
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-04 19:12
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
			$tagIds   = \Yii::$app->request->post('tag_ids', []);
			$status   = \Yii::$app->request->post('status', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 15);

			$keyWordRule = WorkTagKeywordRule::find()->where(['corp_id' => $this->corp->id, 'status' => 2]);

			//获取已设置的标签
			$tagIdData = [];
			$ruleList  = $keyWordRule->all();
			if (!empty($ruleList)) {
				foreach ($ruleList as $rule) {
					/**@var WorkTagKeywordRule $rule * */
					if (!empty($rule->tags_id)) {
						$tagIdArr  = explode(',', $rule->tags_id);
						$tagIdData = array_merge($tagIdData, $tagIdArr);
					}
				}
			}

			if (!empty($status)) {
				$keyWordRule = $keyWordRule->andWhere(['status' => $status]);
			}

			if (!empty($tagIds)) {
				$tagWhere = '';
				foreach ($tagIds as $tag_id) {
					$tagWhere .= " find_in_set ($tag_id,tags_id) or";
				}
				$tagWhere = trim($tagWhere, ' or');
				if (!empty($tagWhere)) {
					$keyWordRule = $keyWordRule->andWhere($tagWhere);
				}
			}

			//获取符合条件的keys
			$keys   = [];
			$idList = $keyWordRule->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					/**@var WorkTagKeywordRule $idInfo * */
					array_push($keys, (string) $idInfo->id);
					WorkTagKeywordRule::updateStatus($idInfo);
				}
			}
			$count       = $keyWordRule->count();
			$offset      = ($page - 1) * $pageSize;
			$keyWordRule = $keyWordRule->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$ruleData    = [];
			/**@var WorkTagKeywordRule $rule * */
			foreach ($keyWordRule as $key => $rule) {
				$ruleInfo = $rule->dumpData();
				array_push($ruleData, $ruleInfo);
			}

			return [
				'count'     => $count,
				'keys'      => $keys,
				'tagIdData' => $tagIdData,
				'ruleData'  => $ruleData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           标签规则添加
		 * @description     标签规则添加
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/add
		 *
		 * @param corp_id 必选 string 企业id
		 * @param tag_rules 必选 string 规则内容
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-04 19:20
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$postData            = \Yii::$app->request->post();
			$postData['corp_id'] = $this->corp->id;
			WorkTagKeywordRule::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           标签规则修改
		 * @description     标签规则修改
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/update
		 *
		 * @param id 必选 string 标签规则id
		 * @param tags 必选 array 标签id
		 * @param words 必选 string 关键词
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-04 19:56
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			WorkTagKeywordRule::updateData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           修改标签规则状态
		 * @description     修改标签规则状态
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/change-status
		 *
		 * @param corp_id 必选 string 企业id
		 * @param ids 必选 array 规则id
		 * @param status 必选 string 状态：0删除、1关闭、2开启
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-04 19:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$ids    = \Yii::$app->request->post('ids', []);
			$status = \Yii::$app->request->post('status', 0);
			if (empty($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($status, [0, 1, 2])) {
				throw new InvalidDataException('参数不正确！');
			}

			if ($status == 2) {
				if (count($ids) == 1) {//只针对单个做判断
					$keyWordRule = WorkTagKeywordRule::findOne(['id' => $ids, 'tags_id' => '']);
					if (!empty($keyWordRule)) {
						throw new InvalidDataException('未设置标签，无法开启，请检查！');
					}
				}
				WorkTagKeywordRule::updateAll(['status' => $status], ['and', ['corp_id' => $this->corp->id, 'id' => $ids], ['!=', 'tags_id', '']]);
			} else {
				WorkTagKeywordRule::updateAll(['status' => $status], ['corp_id' => $this->corp->id, 'id' => $ids]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工列表
		 * @description     生效员工列表
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-list
		 *
		 * @param corp_id 必选 string 企业id
		 * @param user_id 可选 array 成员id
		 * @param tag_ids 可选 array 标签id
		 * @param page 可选 string 分页页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 11:56
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$userId   = \Yii::$app->request->post('user_id', []);
			$tagIds   = \Yii::$app->request->post('tag_ids', []);
			$status   = \Yii::$app->request->post('status', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 15);

			$userRule = WorkUserTagRule::find()->where(['corp_id' => $this->corp->id]);
			if(!empty($userId)){
				$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($userId);
				$userId = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,2);
				$userId = empty($userId) ? [0] : $userId;
			}
			//成员
			if (!empty($userId)) {
				$userRule = $userRule->andWhere(['user_id' => $userId]);
			}
			//标签
			if (!empty($tagIds)) {
				$tagWhere = '';
				foreach ($tagIds as $tag_id) {
					$tagWhere .= " find_in_set ($tag_id,tags_id) or";
				}
				$tagWhere = trim($tagWhere, ' or');
				if (!empty($tagWhere)) {
					$userRule = $userRule->andWhere($tagWhere);
				}
			}
			//状态
			if (!empty($status)) {
				$userRule = $userRule->andWhere(['status' => $status]);
			} else {
				$userRule = $userRule->andWhere(['status' => [1, 2]]);
			}

			//获取符合条件的keys
			$keys   = [];
			$idList = $userRule->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					/**@var WorkUserTagRule $idInfo * */
					array_push($keys, (string) $idInfo->id);
					WorkUserTagRule::updateStatus($idInfo);
				}
			}
			$count    = $userRule->count();
			$offset   = ($page - 1) * $pageSize;
			$userRule = $userRule->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$userData = [];
			/**@var WorkUserTagRule $rule * */
			foreach ($userRule as $rule) {
				$ruleInfo = $rule->dumpData();
				array_push($userData, $ruleInfo);
			}

			return [
				'count'    => $count,
				'keys'     => $keys,
				'userData' => $userData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           获取标签规则中的标签
		 * @description     获取标签规则中的标签
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/get-rule-tag
		 *
		 * @param corp_id 必选 string 企业id
		 *
		 * @return          {"error":0,"data":[{"id":"16","name":"未分组","data":[{"id":"859","tagname":"tyuyiyuiuyiuyi"},{"id":"867","tagname":"呵呵呵呵"},{"id":"868","tagname":"dasd"}]}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 分组名
		 * @return_param    id string 标签id
		 * @return_param    tagname string 标签名字
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 13:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGetRuleTag ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$groupData = [];

			$keyWordRule = WorkTagKeywordRule::find()->where(['corp_id' => $this->corp->id, 'status' => 2])->all();
			if (!empty($keyWordRule)) {
				$tagIdData = [];
				/**@var WorkTagKeywordRule $rule * */
				foreach ($keyWordRule as $rule) {
					$tagId     = explode(',', $rule->tags_id);
					$tagIdData = array_merge($tagIdData, $tagId);
				}
				$tagIdData = array_unique($tagIdData);
				$workTag   = WorkTag::find()->alias('wt');
				$workTag   = $workTag->leftJoin('{{%work_tag_group}} tg', 'wt.group_id = tg.id');
				$workTag   = $workTag->where(['wt.id' => $tagIdData, 'wt.is_del' => 0, 'wt.type' => 0]);
				$workTag   = $workTag->select('wt.id,wt.tagname,wt.group_id,tg.group_name');
				$workTag   = $workTag->orderBy('tg.sort')->asArray()->all();
				foreach ($workTag as $tag) {
					if (!isset($groupData[$tag['group_id']])) {
						$groupData[$tag['group_id']] = ['id' => $tag['group_id'], 'name' => $tag['group_name'], 'data' => []];
					}
					$groupData[$tag['group_id']]['data'][] = ['id' => $tag['id'], 'tagname' => $tag['tagname']];
				}
				$groupData = array_values($groupData);
			}

			return $groupData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工添加
		 * @description     生效员工添加
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-tag-add
		 *
		 * @param corp_id 必选 string 企业id
		 * @param user_ids 必选 array 成员id
		 * @param tag_ids 必选 array 标签id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 13:01
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserTagAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$postData            = \Yii::$app->request->post();
			$postData['corp_id'] = $this->corp->id;
			WorkUserTagRule::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工修改
		 * @description     生效员工修改
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-tag-update
		 *
		 * @param ids 必选 array 规则id
		 * @param tag_ids 必选 array 标签id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 13:03
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserTagUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ids    = \Yii::$app->request->post('ids', []);
			$tagIds = \Yii::$app->request->post('tag_ids', []);
			if (empty($ids) || empty($tagIds)) {
				throw new InvalidDataException('参数不正确！');
			}
			$tagIdStr = implode(',', $tagIds);

			WorkUserTagRule::updateAll(['tags_id' => $tagIdStr], ['id' => $ids]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工更改状态
		 * @description     生效员工更改状态
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-change-status
		 *
		 * @param ids 必选 array 规则id
		 * @param status 必选 string 0删除、1关闭、2开启
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 13:05
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$ids    = \Yii::$app->request->post('ids', []);
			$status = \Yii::$app->request->post('status', 0);
			if (empty($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($status, [0, 1, 2])) {
				throw new InvalidDataException('参数不正确！');
			}

			if ($status == 2) {
				if (count($ids) == 1) {//只针对单个做判断
					$keyWordRule = WorkUserTagRule::findOne(['id' => $ids, 'tags_id' => '']);
					if (!empty($keyWordRule)) {
						throw new InvalidDataException('未设置标签，无法开启，请检查！');
					}
				}
				WorkUserTagRule::updateAll(['status' => $status], ['and', ['corp_id' => $this->corp->id, 'id' => $ids], ['!=', 'tags_id', '']]);
			} else {
				WorkUserTagRule::updateAll(['status' => $status], ['corp_id' => $this->corp->id, 'id' => $ids]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工详情
		 * @description     生效员工详情
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-tag-detail
		 *
		 * @param rule_id 必选 string 规则id
		 *
		 * @return          {"error":0,"data":{"user_name":"陈志尧","tagData":[{"id":"789","title":"测试打标签"},{"id":"868","title":"dasd"}],"ruleData":[{"tag_name":"dasd","keyWord":["321","2312"]}],"total":"61","today_num":"61"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_name string 员工名称
		 * @return_param    tagData array 标签列表
		 * @return_param    ruleData array 规则列表
		 * @return_param    ruleData.tag_name string 标签名称
		 * @return_param    ruleData.keyWord array 关键词列表
		 * @return_param    total string 总客户数
		 * @return_param    today_num string 今日客户数
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 14:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserTagDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ruleId  = \Yii::$app->request->post('rule_id', 0);
			$userTag = WorkUserTagRule::findOne($ruleId);
			if (empty($userTag)) {
				throw new InvalidDataException('参数不正确！');
			}

			$result = [];
			//成员名
			$workUser            = WorkUser::findOne($userTag->user_id);
			$result['user_name'] = !empty($workUser) ? $workUser->name : '';

			//标签规则
			$tagData  = [];
			$ruleData = [];

			//客户数量
			if (!empty($userTag->tags_id)) {
				$tagIds  = explode(',', $userTag->tags_id);
				$workTag = WorkTag::find()->where(['id' => $tagIds, 'is_del' => 0])->all();
				/**@var WorkTag $tag * */
				foreach ($workTag as $tag) {
					$tag_id = $tag->id;
					//获取关键词
					$temp        = [];
					$keyWordRule = WorkTagKeywordRule::find()->where(['corp_id' => $userTag->corp_id, 'status' => 2])->andWhere("find_in_set ($tag_id,tags_id)")->one();
					if (!empty($keyWordRule)) {
						$tagData[]        = ['id' => (string) $tag_id, 'title' => $tag->tagname];
						$temp['tag_name'] = $tag->tagname;
						$keyWord          = json_decode($keyWordRule->keyword);
						$temp['keyWord']  = $keyWord;
						array_push($ruleData, $temp);
					}
				}
				$result['tagData']  = $tagData;
				$result['ruleData'] = $ruleData;

				$time        = strtotime(date('Y-m-d'));
				$tagExternal = WorkUserTagExternal::find()->alias('ute');
				$tagExternal = $tagExternal->leftJoin('{{%work_tag_follow_user}} tfu', 'ute.tag_id = tfu.tag_id and ute.follow_user_id = tfu.follow_user_id');
				$tagExternal = $tagExternal->where(['ute.user_id' => $userTag->user_id, 'ute.tag_id' => $tagIds, 'ute.status' => 1, 'tfu.status' => 1]);
				$tagExternal = $tagExternal->groupBy('ute.external_id');

				//累计客户
				$total           = $tagExternal->count();
				$result['total'] = $total;

				//今日客户
				$todayNum            = $tagExternal->andWhere(['>', 'ute.add_time', $time])->count();
				$result['today_num'] = $todayNum;
			} else {
				$result['tagData']   = [];
				$result['ruleData']  = [];
				$result['total']     = 0;
				$result['today_num'] = 0;
			}

			return $result;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-keyword-rule/
		 * @title           生效员工客户列表
		 * @description     生效员工客户列表
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-keyword-rule/user-tag-custom
		 *
		 * @param rule_id 必选 string 规则id
		 * @param name 可选 string 客户名称
		 * @param tag_id 可选 string 标签id
		 * @param page 可选 string 分页页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"61","externalData":[{"id":"1","name":"一切随缘","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5NSMiaygS8Qfd80LcgrMy0bBcJa1xlxeI86X4zp4onQAg/0","content":"还是<span style='color:#1890FF;cursor: pointer;'>被迫</span>添加的，这次真的要88了","tagName":["测试打标签"],"add_time":"2020-08-05 16:45"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    externalData array 客户列表
		 * @return_param    externalData.name string 客户名称
		 * @return_param    externalData.avatar string 客户头像
		 * @return_param    externalData.content string 关键词内容
		 * @return_param    externalData.tag_name string 标签名称
		 * @return_param    externalData.add_time string 打标签时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-08-05 14:16
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserTagCustom ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ruleId   = \Yii::$app->request->post('rule_id', 0);
			$name     = \Yii::$app->request->post('name', 0);
			$tagId    = \Yii::$app->request->post('tag_id', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 15);

			$userTag = WorkUserTagRule::findOne($ruleId);
			if (empty($userTag)) {
				throw new InvalidDataException('参数不正确！');
			}
			//无标签时返回空
			if (empty($userTag->tags_id)) {
				return [
					'count'        => 0,
					'externalData' => []
				];
			}

			$tagExternal = WorkUserTagExternal::find()->alias('ute');
			$tagExternal = $tagExternal->leftJoin('{{%work_external_contact}} ec', 'ute.external_id = ec.id');
			$tagExternal = $tagExternal->leftJoin('{{%work_tag_follow_user}} tfu', 'ute.tag_id = tfu.tag_id and ute.follow_user_id = tfu.follow_user_id');
			$tagExternal = $tagExternal->where(['ute.user_id' => $userTag->user_id, 'ute.status' => 1, 'tfu.status' => 1]);
			//客户名称
			if (!empty($name)) {
				$tagExternal = $tagExternal->andWhere(['like', 'ec.name_convert', $name]);
			}

			//标签
			if (!empty($tagId)) {
				$tagExternal = $tagExternal->andWhere(['ute.tag_id' => $tagId]);
			} else {//无标签时取当前可用标签
				$tagIdArr = [];
				$tagIds   = explode(',', $userTag->tags_id);
				$workTag  = WorkTag::find()->where(['id' => $tagIds, 'is_del' => 0])->all();
				/**@var WorkTag $tag * */
				foreach ($workTag as $tag) {
					$tag_id      = $tag->id;
					$keyWordRule = WorkTagKeywordRule::find()->where(['corp_id' => $userTag->corp_id, 'status' => 2])->andWhere("find_in_set ($tag_id,tags_id)")->one();
					if (!empty($keyWordRule)) {
						array_push($tagIdArr, $tag_id);
					}
				}
				if (empty($tagIdArr)) {
					return [
						'count'        => 0,
						'externalData' => []
					];
				}
				$tagExternal = $tagExternal->andWhere(['ute.tag_id' => $tagIdArr]);
			}

			$tagExternal = $tagExternal->select('ute.id,ute.tag_id,ute.keyword,ute.add_time,ec.name,ec.avatar,ec.corp_name,ute.audit_info_id,ute.keyword,ute.tag_id,ute.add_time');

			$sql = $tagExternal->createCommand()->getRawSql();

			$count       = $tagExternal->count();
			$offset      = ($page - 1) * $pageSize;
			$tagExternal = $tagExternal->limit($pageSize)->offset($offset)->orderBy(['ute.id' => SORT_DESC])->asArray()->all();

			$externalData = [];
			foreach ($tagExternal as $key => $info) {
				$externalData[$key]['id']        = $info['id'];
				$externalData[$key]['name']      = rawurldecode($info['name']);
				$externalData[$key]['avatar']    = $info['avatar'];
				$externalData[$key]['corp_name'] = $info['corp_name'];

				//关键词
				$content = '';
				if (!empty($info['audit_info_id'])) {
					$auditInfo = WorkMsgAuditInfo::findOne($info['audit_info_id']);
					if (!empty($auditInfo)) {
						$keyword = $info['keyword'];
						$content = rawurldecode($auditInfo->content);
						$content = str_replace($keyword, "<span style='color:#1890FF;cursor: pointer;'>" . $keyword . "</span>", $content);
					}
				}
				$externalData[$key]['content'] = $content;

				//标签
				$workTag                        = WorkTag::findOne($info['tag_id']);
				$externalData[$key]['tag_name'] = !empty($workTag) ? $workTag->tagname : '';
				$externalData[$key]['add_time'] = date('Y-m-d H:i', $info['add_time']);
			}

			return [
				'count'        => $count,
				'sql'          => $sql,
				'externalData' => $externalData
			];
		}
	}
