<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/7/15
	 * Time: 17:33
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\CustomField;
	use app\models\WorkChat;
	use app\models\WorkDepartment;
	use app\models\WorkGroupSending;
	use app\models\WorkTagGroupStatistic;
	use app\models\WorkTagGroupUserStatistic;
	use app\models\WorkTagPullGroup;
	use app\models\WorkUser;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\queue\WorkGroupSendingJob;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;
	use app\util\WorkUtils;
	use app\queue\GetGroupMsgResultJob;
	use yii\db\Expression;

	class WorkTagPullGroupController extends WorkBaseController
	{
		/**
		 * @inheritDoc
		 *
		 * @return array
		 */
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'list'   => ['POST'],
						'add'    => ['POST'],
						'detail' => ['POST'],
						'delete' => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           标签拉群添加/修改接口
		 * @description     标签拉群添加/修改接口
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/add
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param id 可选 int 修改时添加时可传0
		 * @param title 必选 string 消息名称
		 * @param send_type 必选 int 1、全部客户2、按条件筛选客户
		 * @param sex 可选 int 当筛选客户时传-1全部0未知1男2女
		 * @param tag_ids 可选 array 客户筛选标签id
		 * @param users 可选 array 非全部客户时传
		 * @param content 可选 string 文本内容
		 * @param user_ids 可选 array 客户筛选时用
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param update_time 可选 array 跟进时间
		 * @param chat_time 可选 array 单聊时间
		 * @param sign_id 可选 int 店铺id
		 * @param follow_num1 可选 int 跟进次数
		 * @param follow_num2 可选 int 跟进次数
		 * @param province 可选 string 省
		 * @param city 可选 string 市
		 * @param attribute 可选 array 高级属性字段
		 * @param follow_id 可选 int 跟进次数
		 * @param chat_list 可选 array 群聊
		 * @param is_filter 可选 array 是否过滤客户1过滤0不过滤
		 * @param sender 可选 array 确认客户
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/17 9:02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$data                    = [];
				$data['corp_id']         = $this->corp['id'];
				$data['isMasterAccount'] = \Yii::$app->request->post('isMasterAccount', 1);
				$data['sub_id']          = \Yii::$app->request->post('sub_id', 0);
				$data['id']              = \Yii::$app->request->post('id') ?: 0;
				$data['title']           = \Yii::$app->request->post('title');
				$data['is_filter']       = \Yii::$app->request->post('is_filter');
				$data['send_type']       = \Yii::$app->request->post('send_type');//1全部客户2筛选客户3员工
				//筛选客户
				$data['users']       = \Yii::$app->request->post('users');
				$data['user_ids']    = \Yii::$app->request->post('user_ids');
				$data['sex']         = \Yii::$app->request->post('sex');
				$data['start_time']  = \Yii::$app->request->post('start_time');
				$data['end_time']    = \Yii::$app->request->post('end_time');
				$data['update_time'] = \Yii::$app->request->post('update_time');
				$data['chat_time']   = \Yii::$app->request->post('chat_time');
				$data['sign_id']     = \Yii::$app->request->post('sign_id');
				$data['follow_num1'] = \Yii::$app->request->post('follow_num1');
				$data['follow_num2'] = \Yii::$app->request->post('follow_num2');
				$data['tag_ids']     = \Yii::$app->request->post('tag_ids');
				$data['province']    = \Yii::$app->request->post('province');
				$data['city']        = \Yii::$app->request->post('city');
				$data['is_fans']     = \Yii::$app->request->post('is_fans');
				//高级属性字段
				$data['attribute']       = \Yii::$app->request->post('attribute');
				$data['follow_id']       = \Yii::$app->request->post('follow_id');
				$data['content']         = \Yii::$app->request->post('content');
				$data['chat_list']       = \Yii::$app->request->post('chat_list');
				$data['isMasterAccount'] = \Yii::$app->request->post('isMasterAccount');
				$data['sub_id']          = \Yii::$app->request->post('sub_id');
				$data['belong_id']       = \Yii::$app->request->post('belong_id');
				$data['uid']             = $this->user->uid;
				if (!empty($data["user_ids"])) {
					$user_id             = $data["user_ids"];
					$department = [];
					$UserKey = [];
					if (is_array($user_id)) {
						foreach ($user_id as $value) {
							if (strpos($value["id"], 'd') !== false) {
								$T = explode("-", $value["id"]);
								if (isset($T[1])) {
									array_push($department, $T[1]);
								}
							} else {
								array_push($UserKey, $value);
							}
						}
					}
					$data["user_ids"] = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $department, $UserKey, 1, false);
					if(empty($data["user_ids"])){
						throw new InvalidDataException('请选择成员');
					}
					if (!empty($data["users"])) {
						$data["users"] = array_column($data["user_ids"], "id");
					}
				}
				//添加/修改
				WorkTagPullGroup::add($data);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           列表接口
		 * @description     列表接口
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/list
		 *
		 * @param title 可选 int 名称
		 * @param chat_id 可选 array 群聊id
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 * @param corp_id 必选 string 企业微信标志
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 名称
		 * @return_param    chat_name string 群名称
		 * @return_param    send_name array 成员确认
		 * @return_param    create_time string 创建时间
		 * @return_param    will_num string 预计发送客户
		 * @return_param    real_num string 实际发送客户
		 * @return_param    has_group string 已入群客户
		 * @return_param    no_group string 未入群客户
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/17 9:44
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
				$page         = \Yii::$app->request->post('page') ?: 1;
				$pageSize     = \Yii::$app->request->post('pageSize') ?: 15;
				$title        = \Yii::$app->request->post('title');
				$chat_id      = \Yii::$app->request->post('chat_id');
				$tagPullGroup = WorkTagPullGroup::find()->alias('p')->leftJoin('{{%work_chat_way_list}} w', '`p`.`id` = `w`.`tag_pull_id`');
				$tagPullGroup = $tagPullGroup->where(['p.corp_id' => $this->corp->id, 'p.is_del' => 0]);
				if (!empty($title) || $title == '0') {
					$title        = trim($title);
					$tagPullGroup = $tagPullGroup->andWhere(['like', 'p.title', $title]);
				}
				if (!empty($chat_id)) {
					$tagPullGroup = $tagPullGroup->andWhere(['w.chat_id' => $chat_id]);
				}
				$count  = $tagPullGroup->groupBy('w.tag_pull_id')->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $tagPullGroup->limit($pageSize)->offset($offset)->groupBy('w.tag_pull_id')->orderBy(['p.id' => SORT_DESC])->all();
				if (!empty($info)) {
					foreach ($info as $val) {
						$data = $val->dumpData(0);
						array_push($result, $data);
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           详情接口
		 * @description     详情接口
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/detail
		 *
		 * @param id 必选 int 拉群id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/17 9:46
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
				$data     = [];
				$tagGroup = WorkTagPullGroup::findOne($id);
				if (!empty($tagGroup)) {
					$data = $tagGroup->dumpData(1);
				}

				return $data;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           删除接口
		 * @description     删除接口
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/delete
		 *
		 * @param id 必选 int 拉群id
		 *
		 * @return      true
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/17 9:46
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
				$tagGroup         = WorkTagPullGroup::findOne($id);
				$tagGroup->is_del = 1;
				$tagGroup->save();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           群发记录
		 * @description     群发记录
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/group-sending-records
		 *
		 * @param id 必选 int 群列表id
		 * @param name 可选 string 客户名称
		 * @param user_ids 可选 array 筛选的员工
		 * @param send 可选 int 送达状态默认传-1、0未收到邀请、1已收到、2客户不是好友、3客户已达上限
		 * @param status 可选 int 是否入群默认传-1、0、未入群1、已入群
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":"8","info":[{"key":"8","id":"8","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM43dk4bjtjPicRTXupyia1zKlVV0UpYWml6oTIY6Jia5PFag/0","corp_name":null,"customer_name":"空白","gender":"女性"},{"key":"7","id":"7","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM5NSMiaygS8Qfd80LcgrMy0bBcJa1xlxeI86X4zp4onQAg/0","corp_name":null,"customer_name":"一切随缘","gender":"男性"},{"key":"6","id":"6","status":"0","send":"0","title":"717","name":"汪博文","avatar":null,"corp_name":null,"customer_name":"简迷离","gender":"男性"},{"key":"5","id":"5","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM7dkzHjCq3DdyjmcLmJlaxrmBCAAYEbRcicEgOwUOsb4Lw/0","corp_name":"123","customer_name":"flu","gender":"男性"},{"key":"4","id":"4","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM4nzvQhYWyRLicINDico8UCyiayYCu2d8z218CJFyD9WvYJQ/0","corp_name":null,"customer_name":"简迷离","gender":"男性"},{"key":"3","id":"3","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM7pibwaHtwiaHrm7jroeuz8nPJmibJY2lic6Csocf3uEQ5iblw/0","corp_name":null,"customer_name":"SHAKALAKA","gender":"女性"},{"key":"2","id":"2","status":"0","send":"0","title":"717","name":"汪博文","avatar":"http://wx.qlogo.cn/mmhead/GibvHudxmlJbHQEV84mpeundfic12MygBduhEaAGN01ibrCBiaeibBLOJAg/0","corp_name":null,"customer_name":"王盼","gender":"男性"},{"key":"1","id":"1","status":"0","send":"0","title":"717","name":"","avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM4CnFtibNrxEibCkBOvChlrUQs15sVPGqKHicrXOdtG0NbOA/0","corp_name":null,"customer_name":"Dove_Chen","gender":"男性"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    status int 0、未入群1、已入群
		 * @return_param    send int 0未收到邀请、1已收到、2客户不是好友、3客户已达上限
		 * @return_param    title string 群聊名称
		 * @return_param    name string 成员名称
		 * @return_param    avatar string 头像
		 * @return_param    gender string 性别
		 * @return_param    corp_name string 公司名称
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/21 13:49
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupSendingRecords ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$name      = \Yii::$app->request->post('name');
				$user_ids  = \Yii::$app->request->post('user_ids');
				$send      = \Yii::$app->request->post('send');
				$status    = \Yii::$app->request->post('status');
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('pageSize') ?: 15;

				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}

				$statistic = WorkTagGroupStatistic::find()->alias('s')->leftJoin('{{%work_external_contact}} c', '`c`.`id` = `s`.`external_id`')->where(['s.pull_id' => $id]);
				if (!empty($name) || $name == '0') {
					$name = trim($name);
					//高级属性搜索
					$fieldList = CustomField::find()->where('is_define=0')->select('`id`,`key`')->asArray()->all();//默认属性
					$fieldD    = [];
					foreach ($fieldList as $k => $v) {
						$fieldD[$v['key']] = $v['id'];
					}
					$statistic = $statistic->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `c`.`id` AND `cf`.`type`=1');
					$statistic = $statistic->leftJoin('{{%work_external_contact_follow_user}} wf', '`wf`.`external_userid` = `c`.`id`');
					$statistic = $statistic->andWhere(' c.name_convert like \'%' . $name . '%\' or wf.remark like \'%' . $name . '%\' or wf.nickname like \'%' . $name . '%\' or (cf.fieldid in (' . $fieldD['company'] . ',' . $fieldD['name'] . ') and cf.value like \'%' . $name . '%\')');
				}
				if (!empty($user_ids)) {
					$statistic = $statistic->andWhere(['s.user_id' => $user_ids]);
				}
				if ($send != -1) {
					$statistic = $statistic->andWhere(['s.send' => $send]);
				}
				if ($status != -1) {
					$statistic = $statistic->andWhere(['s.status' => $status]);
				}
				$select         = new Expression('count(DISTINCT(s.id)) cc');
				$statisticCount = $statistic->select($select)->asArray()->one();
				$count          = $statisticCount['cc'];
				$offset         = ($page - 1) * $pageSize;
				$result         = [];
				$select1        = new Expression('DISTINCT c.name,c.gender,c.corp_name,c.avatar,c.corp_name,s.id,s.status,s.send,s.chat_id,s.user_id');
				$info           = $statistic->select($select1)->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				$info           = $info->asArray()->all();
				if (!empty($info)) {
					foreach ($info as $key => $value) {
						$title    = WorkChat::getChatName($value['chat_id']);
						$workUser = WorkUser::findOne($value['user_id']);
						$name     = '';
						if (!empty($workUser)) {
							$name = $workUser->name;
						}
						$gender = '';
						if ($value['gender'] == 0) {
							$gender = '未知';
						} elseif ($value['gender'] == 1) {
							$gender = '男性';
						} elseif ($value['gender'] == 2) {
							$gender = '女性';
						}
						$result[$key]['key']           = $value['id'];
						$result[$key]['id']            = $value['id'];
						$result[$key]['status']        = $value['status'];
						$result[$key]['send']          = $value['send'];
						$result[$key]['title']         = $title;
						$result[$key]['name']          = $name;
						$result[$key]['avatar']        = $value['avatar'];
						$result[$key]['corp_name']     = $value['corp_name'];
						$result[$key]['customer_name'] = !empty($value['name']) ? rawurldecode($value['name']) : '';
						$result[$key]['gender']        = $gender;
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           成员邀请
		 * @description     成员邀请
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/members
		 *
		 * @param id 必选 int 群列表id
		 * @param user_ids 可选 array 筛选的员工
		 * @param status 可选 int 是否发送0、未发送、1已发送
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":1,"info":[{"key":1,"id":1,"avatar":"http://wx.qlogo.cn/mmhead/Q3auHgzwzM43dk4bjtjPicRTXupyia1zKlVV0UpYWml6oTIY6Jia5PFag/0","name":"技术-李云莉","will_num":0,"real_num":0,"has_num":0,"status":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    status int 0、未发送、已发送
		 * @return_param    will_num int 预计人数
		 * @return_param    real_num int 实际人数
		 * @return_param    has_num int 已入群人数
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/21 16:33
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionMembers ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id');
				$user_name = \Yii::$app->request->post('user_name');
				$status    = \Yii::$app->request->post('status');
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('pageSize') ?: 15;
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$statistic = WorkTagGroupUserStatistic::find()->alias('s')->leftJoin('{{%work_user}} w', '`w`.`id` = `s`.`user_id`');
				$statistic = $statistic->andWhere(['s.pull_id' => $id]);
				if ($status != -1) {
					$statistic = $statistic->andWhere(['s.status' => $status]);
				}
				if (!empty($user_name) || $user_name == '0') {
					$user_name = trim($user_name);
					$statistic = $statistic->andWhere(['like', 'w.name', $user_name]);
				}
				$count  = $statistic->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $statistic->select('w.name,w.id as user_id,s.id as id,w.avatar,s.will_num,s.real_num,s.has_num,s.status')->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC])->asArray()->all();
				if (!empty($info)) {
					foreach ($info as $key => $val) {
						$currentWorkUser = WorkUser::findOne($val['user_id']);
						if (!empty($currentWorkUser)) {
							$departName = WorkDepartment::getDepartNameByUserId($currentWorkUser->department, $currentWorkUser->corp_id);
						} else {
							$departName = '';
						}
						$result[$key]['key']      = $val['id'];
						$result[$key]['id']       = $val['id'];
						$result[$key]['avatar']   = $val['avatar'];
						$result[$key]['name']     = $departName . '-' . $val['name'];
						$result[$key]['will_num'] = intval($val['will_num']);
						$result[$key]['real_num'] = intval($val['real_num']);
						$result[$key]['has_num']  = intval($val['has_num']);
						$result[$key]['status']   = intval($val['status']);
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-tag-pull-group/
		 * @title           同步成员发送数据
		 * @description     同步成员发送数据
		 * @method   post
		 * @url  http://{host_name}/api/work-tag-pull-group/refresh-data
		 *
		 * @param corp_id 必选 string 企业微信标志
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/23 17:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionRefreshData ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$tagPullGroup = WorkTagPullGroup::find()->where(['corp_id' => $this->corp->id, 'is_del' => 0]);
			$tagPullGroup = $tagPullGroup->asArray()->all();
			if (!empty($tagPullGroup)) {
				foreach ($tagPullGroup as $val) {
					$statistic = WorkTagGroupStatistic::findOne(['pull_id' => $val['id'], 'push_type' => 0]);
					if (!empty($statistic)) {
						//如果当前群发明细表的发送状态为0 则跑队列去拉取最新的数据
						$jobId = \Yii::$app->queue->push(new GetGroupMsgResultJob([
							'sendId' => $val['id'],
							'type'   => 0,
						]));
					}
				}
			}

			return true;
		}

		public function actionTest ()
		{
			$msgid   = 'msgiWVTDwAABX24jt9pgud_-CAaPpY8Uw';
			$workApi = WorkUtils::getWorkApi(1, 1);
			$result  = $workApi->ECGetGroupMsgResult($msgid);
			var_dump($result);
		}

	}