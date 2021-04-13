<?php
	/**
	 * 敏感词监控
	 * User: xcy
	 * Date: 2020-07-16
	 * Time: 19:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\AuthoritySubUserDetail;
	use app\models\LimitWord;
	use app\models\LimitWordGroup;
	use app\models\LimitWordGroupSort;
	use app\models\LimitWordMsg;
	use app\models\LimitWordRemind;
	use app\models\UserCorpRelation;
	use app\models\WorkDepartment;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkMsgAuditInfoText;
	use app\models\WorkMsgAuditUser;
	use app\models\WorkUser;
	use app\models\WorkUserTagRule;
	use app\modules\api\components\WorkBaseController;
	use app\queue\SyncWordMsgJob;
	use app\util\SUtils;
	use yii\db\Expression;

	class LimitWordController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           获取敏感词列表
		 * @description     获取敏感词列表
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/list
		 *
		 * @param uid 必选 string 账户id
		 * @param group_id 可选 string 分组id
		 * @param corp_id 可选 string 企业id
		 * @param title 可选 string 名称
		 * @param page 可选 string 分页页码
		 * @param pageSize 可选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"1","keys":[],"limitWord":[{"key":"1","id":"1","title":"fuck","add_time":"2020-07-17 11:55:41","is_forbid":1,"staff_times":0,"custom_times":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    keys array id集合
		 * @return_param    limitWord array 列表
		 * @return_param    limitWord.key string key
		 * @return_param    limitWord.id string 敏感词id
		 * @return_param    limitWord.title string 敏感词名称
		 * @return_param    limitWord.add_time string 添加时间
		 * @return_param    limitWord.is_forbid string 是否禁止修改删除
		 * @return_param    limitWord.status string 状态：1开启，2关闭
		 * @return_param    limitWord.staff_times string 员工触发次数
		 * @return_param    limitWord.custom_times string 客户触发次数
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确！');
			}
			$title    = \Yii::$app->request->post('title') ?: '';
			$group_id = \Yii::$app->request->post('group_id') ?: '';
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);

			$joinStr = 'wt.word_id = lw.id and wt.uid=' . $uid;
			if (!empty($this->corp)) {
				$joinStr .= ' and wt.corp_id = ' . $this->corp->id;
			}
			$limitWord = LimitWord::find()->alias('lw');
			$limitWord = $limitWord->leftJoin('{{%limit_word_times}} wt', $joinStr);
			$limitWord = $limitWord->andWhere(['lw.status' => [1, 2]]);
			$limitWord = $limitWord->andWhere(['or', ['lw.uid' => NULL], ['lw.uid' => $uid]]);
			if (!empty($title)) {
				$limitWord = $limitWord->andWhere(['like', 'lw.title', $title]);
			}

			if (!empty($group_id)) {
				$limitWord = $limitWord->andWhere(['lw.group_id' => $group_id]);
			}
			$limitWord = $limitWord->select('wt.staff_times,wt.custom_times,lw.*');
			//获取符合条件的keys
			$keys   = [];
			$idList = $limitWord->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					/**@var LimitWord $idInfo * */
					if (!empty($idInfo->uid)) {
						array_push($keys, (string) $idInfo['id']);
					}
				}
			}

			$count     = $limitWord->count();
			$offset    = ($page - 1) * $pageSize;
			$limitWord = $limitWord->limit($pageSize)->offset($offset)->orderBy(['lw.id' => SORT_DESC])->asArray()->all();
			$wordData  = [];
			foreach ($limitWord as $key => $word) {
				$wordData[$key]['key']          = $word['id'];
				$wordData[$key]['id']           = $word['id'];
				$wordData[$key]['title']        = $word['title'];
				$wordData[$key]['add_time']     = substr($word['add_time'], 0, 16);
				$wordData[$key]['is_forbid']    = empty($word['uid']) ? 1 : 0;
				$wordData[$key]['status']       = $word['status'];
				$wordData[$key]['staff_times']  = intval($word['staff_times']);
				$wordData[$key]['custom_times'] = intval($word['custom_times']);
			}

			return [
				'count'     => $count,
				'keys'      => $keys,
				'limitWord' => $wordData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           敏感词设置
		 * @description     敏感词设置
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/set
		 *
		 * @param uid 必选 string 账户id
		 * @param title 必选 array 名称
		 * @param id 可选 string 修改时必填
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:38
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$id       = \Yii::$app->request->post('id', 0);
			$uid      = \Yii::$app->request->post('uid', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			$title    = \Yii::$app->request->post('title', []);
			if (empty($uid) || empty($group_id)) {
				throw new InvalidDataException('参数不正确！');
			}

			$data = ['id' => $id, 'uid' => $uid, 'title' => $title, 'group_id' => $group_id];
			LimitWord::setName($data);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           敏感词改变状态
		 * @description     敏感词改变状态
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/change-status
		 *
		 * @param uid 必选 string 账户id
		 * @param ids 必选 string 敏感词id
		 * @param status 必选 string 状态：0删除，1开启，2关闭
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:39
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ids    = \Yii::$app->request->post('ids', 0);
			$uid    = \Yii::$app->request->post('uid');
			$status = \Yii::$app->request->post('status', 0);
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确');
			}
			if (!in_array($status, [0, 1, 2])) {
				throw new InvalidDataException('状态值不正确');
			}
			LimitWord::updateAll(['status' => $status], ['uid' => $uid, 'id' => $ids]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           敏感词换组
		 * @description     敏感词换组
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/group-change
		 *
		 * @param uid 必选 string 账户id
		 * @param id 必选 string|array 敏感词id
		 * @param group_id 必选 string 分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:42
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupChange ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$id       = \Yii::$app->request->post('id', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			if (empty($uid) || empty($id) || empty($group_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			//批量更新
			LimitWord::updateAll(['group_id' => $group_id], ['uid' => $uid, 'status' => [1, 2], 'id' => $id]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           分组列表
		 * @description     分组列表
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/group
		 *
		 * @param uid 必选 string 账户id
		 *
		 * @return          {"error":0,"data":{"group":[{"id":1,"title":"未分组","is_forbid":1}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    group array 分组列表
		 * @return_param    group.id string 分组id
		 * @return_param    group.title string 分组名称
		 * @return_param    group.is_forbid string 是否禁止操作
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:44
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroup ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确！');
			}
			$limitGroup = LimitWordGroup::find()->alias('wg');
			$limitGroup = $limitGroup->leftJoin('{{%limit_word_group_sort}} gs', 'wg.id = gs.group_id');
			$limitGroup = $limitGroup->where(['wg.status' => 1]);
			$limitGroup = $limitGroup->andWhere(['or', ['wg.uid' => NULL], ['wg.uid' => $uid]]);
			$limitGroup = $limitGroup->orderBy(['sort' => SORT_ASC])->asArray()->all();
			$groupData  = [];
			/**@var LimitWordGroup $group * */
			foreach ($limitGroup as $key => $group) {
				$groupData[$key]['id']           = $group['id'];
				$groupData[$key]['key']          = $group['id'];
				$groupData[$key]['title']        = $group['title'];
				$groupData[$key]['is_forbid']    = empty($group['uid']) ? 1 : 0;
				$groupData[$key]['is_not_group'] = $group['is_not_group'];
			}

			return ['group' => $groupData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           分组添加
		 * @description     分组添加
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/group-add
		 *
		 * @param uid 必选 string 账户id
		 * @param title 必选 string 名称
		 * @param id 可选 string 修改时必填
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:53
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$id    = \Yii::$app->request->post('id', 0);
			$uid   = \Yii::$app->request->post('uid', 0);
			$title = \Yii::$app->request->post('title', '');
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确！');
			}
			try {
				$data = ['id' => $id, 'uid' => $uid, 'title' => $title];
				LimitWordGroup::setGroup($data);
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           分组排序
		 * @description     分组排序
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/group-sort
		 *
		 * @param uid 必选 string 账户id
		 * @param ids 必选 array 分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 17:09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionGroupSort ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$ids = \Yii::$app->request->post('ids');
			if (empty($uid) || empty($ids) || !is_array($ids)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			foreach ($ids as $key => $id) {
				$sort      = $key + 1;
				$groupSort = LimitWordGroupSort::findOne(['uid' => $uid, 'group_id' => $id]);
				if (!empty($groupSort)) {
					$groupSort->sort = $sort;
					$groupSort->update();
				} else {
					$groupSort           = new LimitWordGroupSort();
					$groupSort->uid      = $uid;
					$groupSort->group_id = $id;
					$groupSort->sort     = $sort;
					$groupSort->save();
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           分组删除
		 * @description     分组删除
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/del-group
		 *
		 * @param uid 必选 string 账户id
		 * @param id 必选 string 分组id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-17 14:55
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionDelGroup ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$id  = \Yii::$app->request->post('id', 0);
			$uid = \Yii::$app->request->post('uid');
			if (empty($uid)) {
				throw new InvalidDataException('参数不正确');
			}
			$group = LimitWordGroup::findOne($id);
			if (empty($group)) {
				throw new InvalidDataException('参数不正确');
			}
			if (empty($group->uid)) {
				throw new InvalidDataException('系统分组不能删除');
			}
			$group->status = 0;
			$group->update();

			//更改敏感词分组
			$defaultGroupId = LimitWordGroup::defaultGroup();
			LimitWord::updateAll(['group_id' => $defaultGroupId], ['status' => [1, 2], 'group_id' => $group->id, 'uid' => $uid]);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           获取分组敏感词数据
		 * @description     获取分组敏感词数据
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/word-group
		 *
		 * @param uid 必选 string 账户id
		 * @param is_system 必选 string 是否显示系统分组数据
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-20 13:52
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionWordGroup ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$is_system = \Yii::$app->request->post('is_system', 0);
			$is_close  = \Yii::$app->request->post('is_close', 0);

			return LimitWordGroup::groupWordData($uid, $is_close);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           监控列表
		 * @description     监控列表
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/monitor
		 *
		 * @param uid 必选 string 账户id
		 * @param corp_id 可选 string 企业id
		 * @param start_date 可选 string 开始日期
		 * @param end_date 可选 string 结束日期
		 * @param limit_id 可选 string 关键词id
		 * @param user_id 可选 string 成员id
		 * @param chat_id 可选 string 群id
		 * @param is_selected 必选 string 是否有选中关键词
		 *
		 * @return          {"error":0,"data":{"count":"1","infoList":[{"key":"1","from_name":"xcy","to_name":"哈客V6项目沟通群","avatar":"https://AkibwFwT38micgIJVaIib5wA0QEgliaq54/100","is_chat":1,"content":"都是这样替换的。晚上执行，如果超时了就多执行几次。","msg_time":"2020-06-01 20:11"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    infoList array 监控列表
		 * @return_param    infoList.key string 监控信息key
		 * @return_param    infoList.from_name string 发送人
		 * @return_param    infoList.from_type string 发送人类型：1、企业成员；2、外部联系人
		 * @return_param    infoList.to_name string 收件人
		 * @return_param    infoList.to_type string 收件人类型：1、企业成员；2、外部联系人
		 * @return_param    infoList.is_chat string 是否是群
		 * @return_param    infoList.content string 内容
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 14:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionMonitor ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid         = \Yii::$app->request->post('uid');
			$start_date  = \Yii::$app->request->post('start_date', '');
			$end_date    = \Yii::$app->request->post('end_date', '');
			$user_id     = \Yii::$app->request->post('user_id', []);
			$chat_id     = \Yii::$app->request->post('chat_id', []);
			$is_selected = \Yii::$app->request->post('is_selected', 0);
			$page        = \Yii::$app->request->post('page', 1);
			$pageSize    = \Yii::$app->request->post('pageSize', 10);
			if (empty($this->corp) || empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$sub_id = isset($this->subUser->sub_id ) ? $this->subUser->sub_id  : 0;
			if (!empty($user_id)) {
				$Temp = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException('未配置会话存档功能！');
				}
				$userDepartData = WorkMsgAuditUser::getUserIdDepartId($this->corp->workMsgAudit->id, 1);
				if (empty($userDepartData)) {
					return [];
				}
				$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 1, true, 0, $userDepartData['userIdData'],$sub_id);
				if(empty($user_id)){
					$user_id = [0];
				}
			}
			$limitIds = \Yii::$app->request->post('limit_id', []);
			$corpId   = $this->corp->id;
			if (empty($this->corp->workMsgAudit)) {
				return ['count' => 0, 'infoList' => []];
			}
			if(empty($user_id) && isset($this->subUser->sub_id)){
				$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id,$this->corp->id);
				if(is_array($user)){
					$user_id = $user;
				}
				if($user === false){
					return [
						'count'    => 0,
						'infoList' => [],
					];
				}
			}
			//获取敏感词
			$limitWord  = LimitWord::getList($uid, $limitIds, 1);
			$keywordArr = array_column($limitWord, 'title', 'id');
			if (empty($limitIds) && empty($is_selected)) {
				$limitIds = array_keys($keywordArr);
			}

			//查询数据
			$limitWordMsg = LimitWordMsg::find()->alias('wm');
			$limitWordMsg = $limitWordMsg->leftJoin('{{%work_msg_audit_info}} ai', 'wm.audit_info_id = ai.id');
			$limitWordMsg = $limitWordMsg->where(['wm.corp_id' => $corpId, 'ai.msgtype' => 'text']);

			//关键词
			$limitWordMsg = $limitWordMsg->andWhere(['wm.word_id' => $limitIds]);

			//成员
			if (!empty($user_id)) {
				if (empty($chat_id)) {
					$userList     = WorkUser::find()->where(['id' => $user_id])->select('userid')->all();
					$toList       = array_column($userList, 'userid');
					$limitWordMsg = $limitWordMsg->andWhere(['or', ['from_type' => 1, 'user_id' => $user_id], ['tolist' => $toList]]);
				} else {
					$limitWordMsg = $limitWordMsg->andWhere(['from_type' => 1, 'user_id' => $user_id]);
				}
			}

			//群
			if (!empty($chat_id)) {
				$limitWordMsg = $limitWordMsg->andWhere(['ai.chat_id' => $chat_id]);
			}

			//日期
			if (!empty($start_date) && !empty($end_date)) {
				$start_time   = strtotime($start_date);
				$end_time     = strtotime($end_date . ' 23:59:59');
				$start_time   = $start_time * 1000;
				$end_time     = $end_time * 1000;
				$limitWordMsg = $limitWordMsg->andFilterWhere(['between', 'ai.msgtime', $start_time, $end_time]);
			}
			$select = new Expression("wm.*,GROUP_CONCAT(wm.word_id) wordIdStr,ai.from_type,ai.user_id,ai.external_id,ai.to_type,ai.to_user_id,ai.to_external_id,ai.chat_id,ai.content,ai.msgtime");

			$limitWordMsg = $limitWordMsg->select($select);
			$limitWordMsg = $limitWordMsg->groupBy('ai.id');
			$count        = $limitWordMsg->count();
			$offset       = ($page - 1) * $pageSize;
			$limitWordMsg = $limitWordMsg->limit($pageSize)->offset($offset)->orderBy(['ai.id' => SORT_DESC])->asArray()->all();
			//$limitWordMsg  = $limitWordMsg->limit($pageSize)->offset($offset)->createCommand()->getRawSql();

			$limitWordData = [];

			foreach ($limitWordMsg as $msg) {
				$result = LimitWordMsg::getMsg($msg, $keywordArr);

				if (!empty($result)) {
					$limitWordData[] = $result;
				}
			}

			return [
				'count'    => $count,
				'infoList' => $limitWordData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           成员违规提醒列表
		 * @description     成员违规提醒列表
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-remind
		 *
		 * @param corp_id      必选 string 企业的唯一ID
		 * @param agent_id     必选 string 应用ID
		 * @param status       必选 string 状态：1关闭、2开启
		 * @param user_id      可选 array 成员id
		 * @param page         必选 string 页码
		 * @param pageSize     必选 string 每页数量
		 *
		 * @return          {"error":0,"data":{"count":"1","keys":["1"],"limitWord":[{"id":1,"key":1,"status":2,"name":"张婷","avatar":"https://wework.qpic.cn/bizmail/vDkSAfVWoKHzBP5Hh2ueqiaUicsevZvbYuDl4GGddZHtxoRrsAics37dw/0","word_name":"fuck、他妈的","user_name":"张婷"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 数量
		 * @return_param    keys array 键值
		 * @return_param    limitWord array 键值
		 * @return_param    limitWord.id string 提醒id
		 * @return_param    limitWord.key string 提醒key
		 * @return_param    limitWord.status string 提醒状态
		 * @return_param    limitWord.name string 成员姓名
		 * @return_param    limitWord.avatar string 成员头像
		 * @return_param    limitWord.gender string 性别：0表示未定义，1表示男性，2表示女性',
		 * @return_param    limitWord.department_name string 部门名称
		 * @return_param    limitWord.word_name string 敏感词
		 * @return_param    limitWord.user_name string 通知人
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 13:15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserRemind ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corpId   = $this->corp->id;
			$agentId  = \Yii::$app->request->post('agent_id', 0);
			$status   = \Yii::$app->request->post('status', 0);
			$userId   = \Yii::$app->request->post('user_id', 0);
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			if (!empty($userId)) {
				$Temp = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($userId);
				if (empty($this->corp->workMsgAudit)) {
					throw new InvalidParameterException('未配置会话存档功能！');
				}
				$userDepartData = WorkMsgAuditUser::getUserIdDepartId($this->corp->workMsgAudit->id, 1);
				if (empty($userDepartData)) {
					return [];
				}
				$userId = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true, 0, $userDepartData['userIdData']);
				if(empty($userId)){
					$userId = [0];
				}
			}

			$wordRemind = LimitWordRemind::find()->where(['corp_id' => $corpId]);
			if (!empty($agentId)) {
				$wordRemind = $wordRemind->andWhere(['agent_id' => $agentId]);
			}
			if (!empty($status)) {
				$wordRemind = $wordRemind->andWhere(['status' => $status]);
			}
			if (!empty($userId)) {
				$wordRemind = $wordRemind->andWhere(['limit_user_id' => $userId]);
			}
			//获取符合条件的keys
			$keys   = [];
			$idList = $wordRemind->all();
			if (!empty($idList)) {
				foreach ($idList as $idInfo) {
					array_push($keys, (string) $idInfo['id']);
				}
			}

			$count      = $wordRemind->count();
			$offset     = ($page - 1) * $pageSize;
			$wordRemind = $wordRemind->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			$remindData = [];
			foreach ($wordRemind as $remind) {
				$remindData[] = LimitWordRemind::getData($remind);
			}

			return [
				'count'     => $count,
				'keys'      => $keys,
				'limitWord' => $remindData
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           成员违规提醒设置
		 * @description     成员违规提醒设置
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-remind-set
		 *
		 * @param corp_id       必选 string 企业的唯一ID
		 * @param agent_id      必选 string 应用ID
		 * @param userIdArr     必选 array 监控成员id
		 * @param is_leader     可选 string 是否通知成员负责人
		 * @param remind_user   可选 array 通知成员
		 * @param word_ids      可选 array 敏感词id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 13:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserRemindSet ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($this->corp->workMsgAudit) || $this->corp->workMsgAudit->status != 1) {
				throw new InvalidDataException('还未开启会话存档！');
			}
			$userCorp             = UserCorpRelation::findOne(['corp_id' => $this->corp->id]);
			$postData             = \Yii::$app->request->post();
			$postData['corp_id']  = $this->corp->id;
			$postData['uid']      = $userCorp->uid;
			$postData['audit_id'] = $this->corp->workMsgAudit->id;
			LimitWordRemind::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           获取提醒详情
		 * @description     获取提醒详情
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-remind-detail
		 *
		 * @param id 必选 string 提醒id
		 *
		 * @return          {"error":0,"data":{"id":1,"is_leader":0,"remind_user":[{"title":"张婷","key":"3-1","id":3}],"word_ids":["1","3"]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    data.id string 提醒id
		 * @return_param    data.is_leader string 是否通知成员负责人
		 * @return_param    data.remind_user string 通知成员
		 * @return_param    data.word_ids string 敏感词id
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 14:32
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserRemindDetail ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$id         = \Yii::$app->request->post('id', 0);
			$wordRemind = LimitWordRemind::findOne($id);
			if (empty($wordRemind)) {
				throw new InvalidDataException('参数不正确！');
			}
			$data                = [];
			$data['id']          = $wordRemind->id;
			$data['corp_id']     = $wordRemind->corp->corpid;
			$data['corp_name']   = $wordRemind->corp->corp_name;
			$data['is_leader']   = $wordRemind->is_leader;
			$data['remind_user'] = json_decode($wordRemind->remind_user, 1);
			foreach ( $data['remind_user'] as &$datum){
				if(!isset($datum['scopedSlots'])){
					$datum['scopedSlots'] = ['title' => 'custom'];
				}
			}
			$wordIds             = explode(',', $wordRemind->word_ids);
			$limitWord           = LimitWord::getList('', $wordIds);
			$wordIdArr = [];
			if (!empty($limitWord)) {
				foreach ($limitWord as $word) {
					array_push($wordIdArr, (string) $word->id);
				}
			}
			$data['word_ids'] = $wordIdArr;

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           成员违规提醒修改
		 * @description     成员违规提醒修改
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-remind-update
		 *
		 * @param id            必选 string 提醒ID
		 * @param agent_id      必选 string 应用ID
		 * @param is_leader     可选 string 是否通知成员负责人
		 * @param remind_user   可选 array 通知成员
		 * @param word_ids      可选 array 敏感词id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 14:33
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserRemindUpdate ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$postData = \Yii::$app->request->post();
			LimitWordRemind::updateData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           成员违规提醒修改状态
		 * @description     成员违规提醒修改状态
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-remind-status
		 *
		 * @param ids 必选 array 提醒id
		 * @param status 必选 array 状态：1关闭、2开启
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-22 18:06
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserRemindStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ids    = \Yii::$app->request->post('ids');
			$status = \Yii::$app->request->post('status', 0);
			if (empty($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (!in_array($status, [1, 2])) {
				throw new InvalidDataException('状态值不正确！');
			}
			LimitWordRemind::updateAll(['status' => $status], ['id' => $ids]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           批量修改提醒敏感词
		 * @description     批量修改提醒敏感词
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/user-batch-word
		 *
		 * @param ids 必选 array 提醒id
		 * @param word_ids 必选 array 敏感词id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-21 14:35
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionUserBatchWord ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ids     = \Yii::$app->request->post('ids', []);
			$wordIds = \Yii::$app->request->post('word_ids', []);
			$uid     = \Yii::$app->request->post('uid', '');
			if (empty($ids) || empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($wordIds)) {
				throw new InvalidDataException('请选择敏感词！');
			}

			if (empty($this->corp->workMsgAudit) || $this->corp->workMsgAudit->status != 1) {
				throw new InvalidDataException('还未开启会话存档！');
			}

			$wordIdStr = implode(',', $wordIds);
			LimitWordRemind::updateAll(['word_ids' => $wordIdStr], ['id' => $ids]);

			//同步以前的消息敏感词监控
			$wordRemind = LimitWordRemind::find()->where(['id' => $ids])->all();
			if (!empty($wordRemind)) {
				/**@var LimitWordRemind $remind * */
				foreach ($wordRemind as $remind) {
					LimitWordMsg::pushJob($wordIds, ['corp_id' => $remind->corp_id, 'user_id' => $remind->limit_user_id, 'audit_id' => $this->corp->workMsgAudit->id, 'uid' => $uid]);
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/limit-word/
		 * @title           批量修改通知人
		 * @description     批量修改通知人
		 * @method   post
		 * @url  http://{host_name}/api/limit-word/batch-user
		 *
		 * @param ids 必选 array 提醒id
		 * @param is_leader 必选 string 是否通知部门负责人
		 * @param remind_user 必选 array 通知成员
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: win7. Date: 2020-07-24 16:22
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \yii\db\Exception
		 */
		public function actionBatchUser ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$ids         = \Yii::$app->request->post('ids', []);
			$is_leader   = \Yii::$app->request->post('is_leader', 0);
			$remind_user = \Yii::$app->request->post('remind_user', []);

			if (empty($ids)) {
				throw new InvalidDataException('参数不正确！');
			}
			$wordRemind = LimitWordRemind::find()->where(['id' => $ids])->all();
			if (empty($wordRemind)) {
				throw new InvalidDataException('参数不正确！');
			}
			if (empty($is_leader) && empty($remind_user)) {
				throw new InvalidDataException('请选择通知人！');
			}

			$transaction = \Yii::$app->db->beginTransaction();
			try {
				/**@var LimitWordRemind $remind * */
				foreach ($wordRemind as $remind) {
					if (empty($remind_user) && !empty($is_leader)) {
						$leaderUserId = WorkUser::getLeaderUserId($remind->limit_user_id);
						if (empty($leaderUserId)) {
							throw new InvalidDataException('指定的成员没有部门负责人，请重新设置');
						}
					}
					$remind->is_leader   = $is_leader;
					$remind->remind_user = json_encode($remind_user, JSON_UNESCAPED_UNICODE);
					if (!$remind->validate() || !$remind->save()) {
						throw new InvalidDataException(SUtils::modelError($remind));
					}
				}
				$transaction->commit();
			} catch (InvalidDataException $e) {
				$transaction->rollBack();
				throw new InvalidDataException($e->getMessage());
			}

		}

		//内容转换
		public function actionBatchContent ()
		{
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$page     = 1;
			$pageSize = 5000;
			while (true) {
				$offset   = ($page - 1) * $pageSize;
				$textList = WorkMsgAuditInfoText::find()->where(['content_convert' => ''])->limit($pageSize)->offset($offset)->all();
				if (empty($textList)) {
					break;
				}
				/**@var WorkMsgAuditInfoText $text * */
				foreach ($textList as $text) {
					$text->content_convert = rawurldecode($text->content);
					$text->update();
				}
				$page++;
			}
			echo '更新完成';
		}

	}