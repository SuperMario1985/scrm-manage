<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/2/3
	 * Time: 13:28
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\ApplicationSign;
	use app\models\AuthoritySubUserDetail;
	use app\models\AwardsActivity;
	use app\models\Fans;
	use app\models\Fission;
	use app\models\Follow;
	use app\models\PublicSeaProtect;
	use app\models\PublicSeaReclaimSet;
	use app\models\RedPack;
	use app\models\SubUser;
	use app\models\User;
	use app\models\UserCorpRelation;
	use app\models\UserProfile;
	use app\models\SubUserProfile;
	use app\models\WaitCustomerTask;
	use app\models\WaitTask;
	use app\models\WorkChat;
	use app\models\WorkChatContactWay;
	use app\models\WorkChatInfo;
	use app\models\WorkContactWay;
	use app\models\WorkContactWayLine;
	use app\models\WorkContactWayRedpacket;
	use app\models\WorkCorp;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkExternalContactFollowRecord;
	use app\models\WorkExternalContactMember;
	use app\models\WorkMaterial;
	use app\models\WorkMsgAuditInfo;
	use app\models\WorkPerTagFollowUser;
	use app\models\WorkPublicActivity;
	use app\models\WorkTag;
	use app\models\WorkTagContact;
	use app\models\WorkTagFollowUser;
	use app\models\WorkUser;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\ExternalTimeLine;
	use app\modules\api\components\WorkBaseController;
	use app\queue\ExportCustomJob;
	use app\queue\WaitUserTaskJob;
	use app\util\DateUtil;
	use app\util\MsgUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use dovechen\yii2\weWork\src\dataStructure\ExternalContactRemark;
	use moonland\phpexcel\Excel;
	use mysql_xdevapi\Exception;
	use yii\db\Expression;
	use dovechen\yii2\weWork\Work;
	use yii\helpers\Json;
	use yii\web\MethodNotAllowedHttpException;

	class WorkExternalContactFollowUserController extends WorkBaseController
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
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           外部联系人删除成员
		 * @description     外部联系人删除成员
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/list
		 *
		 * @param suite_id  可选 int 应用ID（授权的必填）
		 * @param corp_id   必选 string 企业的唯一ID
		 * @param user_ids  可选 array 成员id
		 * @param page      可选 int 页码
		 * @param name      可选 string 客户姓名、备注、昵称、公司名称
		 * @param tag_ids   可选 string 标签值（多标签用,分开）
		 * @param page_size 可选 int 每页数据量，默认15
		 * @param type      可选 int 0全部1今日流失2本周流失3本月流失用于H5
		 * @param time_type 可选 int 1删除时间2添加时间
		 * @param sData     可选 string 开始时间
		 * @param eData     可选 string 结束时间
		 * @param user_id   可选 int H5时必传
		 *
		 *
		 * @return          {"error":0,"data":{"count":"1","info":[{"name":"简迷离","gender":"男性","tag_name":[],"member":"39--简迷离","source":"test2","remark":"","del_time":""}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    tag_name array 标签
		 * @return_param    member string 归属成员
		 * @return_param    source string 来源
		 * @return_param    remark string 备注
		 * @return_param    del_time string 删除时间
		 * @return_param    createtime string 添加时间
		 * @return_param    show string 0显示1不显示
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/3 17:13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				$user_ids  = \Yii::$app->request->post('user_ids');
				$user_id   = \Yii::$app->request->post('user_id');
				$name      = \Yii::$app->request->post('name');
				$tag_ids   = \Yii::$app->request->post('tag_ids');
				$type      = \Yii::$app->request->post('type');
				$time_type = \Yii::$app->request->post('time_type', 0);
				$sData     = \Yii::$app->request->post('sData', '');
				$eData     = \Yii::$app->request->post('eData', '');
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('page_size') ?: 15;
				$is_export = \Yii::$app->request->post('is_export', 0);
				$is_all    = \Yii::$app->request->post('is_all', 0);
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
					if(empty($user_ids)){
						$user_ids = [0];
					}
				}

				if (empty($user_ids) && isset($this->subUser->sub_id)) {
					$user = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if (is_array($user)) {
						$user_ids = $user;
					}
					if ($user === false) {
						return [
							"info"  => [],
							"count" => 0,
						];
					}
				}
				$userIds = $user_ids;
				$show = 0;
				$userCount = 0;
				if (!empty($user_id)) {
					$detail   = AuthoritySubUserDetail::getUserIds($user_id, $this->user->uid, $this->corp->id, $user_ids);
					$user_ids = $detail['user_ids'];
					$show     = $detail['show'];
					$userCount = $detail['userCount'];
				}
				if (empty($userIds)) {
					$userCount = 0;
				}
				$offset               = ($page - 1) * $pageSize;
				$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp['id'], 'wf.delete_type' => 2]);
				if (!empty($name)) {
					$workExternalUserData = $workExternalUserData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
					//高级属性搜索
					$fieldList    = CustomField::find()->where('is_define=0')->select('`id`,`key`')->asArray()->all();//默认属性
					$fieldD       = [];
					$contactField = [];//列表展示字段
					foreach ($fieldList as $k => $v) {
						$fieldD[$v['key']] = $v['id'];
					}
					$workExternalUserData = $workExternalUserData->andWhere(' we.name_convert like \'%' . $name . '%\' or wf.remark_corp_name like \'%' . $name . '%\'  or wf.remark like \'%' . $name . '%\' or wf.nickname like \'%' . $name . '%\' or (cf.fieldid in (' . $fieldD['name'] . ') and cf.value like \'%' . $name . '%\')');
				}
				if (!empty($tag_ids)) {
					if (is_array($tag_ids)) {
						$tagIds = $tag_ids;
					} else {
						$tagIds = $tag_ids;
					}
					$workExternalUserData = $workExternalUserData->leftJoin('{{%work_tag_follow_user}} wt', '`wt`.`follow_user_id` = `wf`.`id`')->andWhere(['or', ['in', 'wt.tag_id', $tagIds], ['wf.id' => []]])->andWhere(['wt.corp_id' => $this->corp->id, 'wt.status' => 1]);
				}
				if (!empty($user_ids)) {
					$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
				}
				if (!empty($type)) {
					$sTime = '';
					$eTime = '';
					switch ($type) {
						case 1:
							$sTime = strtotime(date('Y-m-d'));
							$eTime = strtotime(date('Y-m-d') . ' 23:59:59');
							break;
						case 2:
							$sDefaultDate = date("Y-m-d");
							$w            = date('w', strtotime($sDefaultDate));
							$weekStart    = date('Y-m-d', strtotime("$sDefaultDate -" . ($w ? $w - 1 : 6) . ' days'));
							$weekEnd      = date('Y-m-d', strtotime("$weekStart +6 days"));
							$sTime        = strtotime($weekStart);
							$eTime        = strtotime($weekEnd . ' 23:59:59');
							break;
						case 3:
							$firstDay = date('Y-m-01', strtotime(date("Y-m-d")));
							$lastDay  = date('Y-m-d', strtotime("$firstDay +1 month -1 day"));
							$sTime    = strtotime($firstDay);
							$eTime    = strtotime($lastDay . ' 23:59:59');
							break;
					}

					if (!empty($sTime) && !empty($eTime)) {
						$workExternalUserData = $workExternalUserData->andFilterWhere(['between', 'wf.del_time', $sTime, $eTime]);
					}
				}

				if (!empty($sData) && !empty($eData)){
					if ($time_type == 1){
						$workExternalUserData = $workExternalUserData->andFilterWhere(['between', 'wf.del_time', strtotime($sData), strtotime($eData . ' 23:59:59')]);
					} elseif ($time_type == 2) {
						$workExternalUserData = $workExternalUserData->andFilterWhere(['between', 'wf.createtime', strtotime($sData), strtotime($eData . ' 23:59:59')]);
					}
				}

				$count = $workExternalUserData->groupBy('wf.id')->count();
				if (empty($is_all)) {
					$workExternalUserData = $workExternalUserData->limit($pageSize)->offset($offset);
				}
				$workExternalUserData = $workExternalUserData->select('wf.id fid,we.id as wid,we.corp_name as wcorp_name,wf.id as id,we.name,we.gender,we.avatar,wf.user_id,wf.state,wf.remark,wf.createtime,wf.del_time,wf.repeat_type')->orderBy(['wf.del_time' => SORT_DESC])->asArray()->all();
				$result               = [];
				if (!empty($workExternalUserData)) {
					foreach ($workExternalUserData as $key => $val) {
						if ($val['gender'] == 0) {
							$gender = '未知';
						} elseif ($val['gender'] == 1) {
							$gender = '男性';
						} elseif ($val['gender'] == 2) {
							$gender = '女性';
						}
						$work_user                                 = WorkUser::findOne($val['user_id']);
						$departName                                = WorkDepartment::getDepartNameByUserId($work_user->department, $work_user->corp_id);
						$tagName                                   = WorkTagContact::getTagNameByContactId($val['id'], 0,0,[],$work_user->corp_id);
						$perName                                   = WorkPerTagFollowUser::getTagName($val['fid']);
						$result[$key]['customerInfo']['avatar']    = $val['avatar'];
						$result[$key]['customerInfo']['name']      = !empty($val['name']) ? rawurldecode($val['name']) : '';
						$result[$key]['customerInfo']['gender']    = $gender;
						$result[$key]['customerInfo']['tag_name']  = $tagName;
						$result[$key]['customerInfo']['per_name']  = $perName;
						$result[$key]['customerInfo']['corp_name'] = $val['wcorp_name'];
						$result[$key]['member']                    = $departName . '--' . $work_user->name;
						$result[$key]['source']                    = $val['state'];
						$result[$key]['key']                       = $val['id'];
						$result[$key]['remark']                    = !empty($val['remark']) ? $val['remark'] : '--';
						$result[$key]['fid']                       = $val['fid'];
						$repeat                                    = '';
						if ($val['repeat_type'] == 1) {
							$repeat = '（重复添加）';
						}
						$result[$key]['del_time'] = !empty($val['del_time']) ? date("Y-m-d H:i", $val['del_time']) : '';
						$result[$key]['createtime'] = !empty($val['createtime']) ? date("Y-m-d H:i", $val['createtime']) . $repeat : '';
					}
				}

				//导出
				if ($is_export == 1) {
					if (empty($result)) {
						throw new InvalidDataException('暂无数据，无法导出！');
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					foreach ($result as $k => $v) {
						$result[$k]['name']     = !empty($v['customerInfo']['name']) ? $v['customerInfo']['name'] : '未知客户';
						$result[$k]['gender']   = !empty($v['customerInfo']['gender']) ? $v['customerInfo']['gender'] : '未知';
						$result[$k]['tag_name'] = !empty($v['customerInfo']['tag_name']) ? implode(',', $v['customerInfo']['tag_name']) : '--';
						unset($result[$k]['customerInfo']);
					}
					$columns  = ['name', 'gender', 'tag_name', 'member', 'remark', 'del_time', 'createtime'];
					$headers = [
						'name'       => '客户昵称',
						'gender'     => '客户性别',
						'tag_name'   => '客户标签',
						'member'     => '归属成员',
						'remark'     => '备注',
						'del_time'   => '删除时间',
						'createtime' => '添加时间',
					];
					$fileName = '流失客户_' . date("YmdHis", time());
					Excel::export([
						'models'       => $result,//数库
						'fileName'     => $fileName,//文件名
						'savePath'     => $save_dir,//下载保存的路径
						'asAttachment' => true,//是否下载
						'columns'      => $columns,//要导出的字段
						'headers'      => $headers
					]);
					$url = \Yii::$app->params['site_url'] . str_replace(\Yii::getAlias('@upload'), '/upload', $save_dir) . $fileName . '.xlsx';

					return [
						'url' => $url,
					];
				}

				return [
					'count'      => $count,
					'info'       => $result,
					'show'       => $show,
					'user_count' => $userCount,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           客户列表
		 * @description     客户列表
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-list
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param sub_id           必选 int 子账户ID
		 * @param suite_id      可选 int 应用ID（授权的必填）
		 * @param corp_id       必选 string 企业的唯一ID
		 * @param user_ids      可选 array 成员id
		 * @param name          可选 string 客户姓名、公司名称
		 * @param phone         可选 string 手机号、QQ号
		 * @param qq            可选 string QQ号
		 * @param company       可选 string 公司名称
		 * @param sex           可选 string 性别-1全部1男2女3未知
		 * @param work          可选 string 行业
		 * @param province      可选 string 区域-省
		 * @param city          可选 string 区域-市
		 * @param follow_status 可选 int 跟进状态-1全部0未跟进1跟进中2已拒绝3已成交
		 * @param fieldData     可选 array 自定义高级属性搜索
		 * @param fieldData     .field 可选 int 属性id
		 * @param fieldData     .type 可选 int 属性类型2单选3多选
		 * @param fieldData     .match 可选 string 属性值
		 * @param follow_id     可选 int 跟进状态id
		 * @param tag_ids       可选 string 标签值（多标签用,分开）
		 * @param group_id      可选 array 标签分组
		 * @param tag_type      可选 int 标签1或2且
		 * @param no_tag        可选 int 无标签1选中，0未选中
		 * @param add_way       可选 string 来源
		 * @param way_id        可选 string 渠道活码id、群活码id
		 * @param chat_id       可选 string 客户群id
		 * @param chat_type     可选 string 群类型：0全部，1无，2一个，3多个
		 * @param page          可选 int 页码
		 * @param page_size     可选 int 每页数据量，默认15
		 * @param correctness   可选 int 1全部2按条件筛选
		 * @param update_time   可选 array 最后一次跟进时间
		 * @param follow_num1    可选 int 开始跟进次数
		 * @param follow_num2    可选 int 结束跟进次数
		 * @param from_unique   可选 int 0不去重1去重复
		 * @param chat_time   可选 array 上次单聊时间
		 * @param sign_id   可选 int 绑定店铺
		 * @param is_fans   可选 int 是否是粉丝1是2否
		 * @param is_export  可选 int 是否导出1导0不导
		 * @param selected_row_keys  可选 导出选中的key
		 * @param other_way  可选 是否是公海池,全部-1、是1、否0
		 * @param is_protect 可选 string 是否已保护：-1全部、0否、1是
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    follow_status string 跟进状态
		 * @return_param    tag_name array 标签
		 * @return_param    key int 客户id
		 * @return_param    member string 归属成员
		 * @return_param    source string 来源
		 * @return_param    remark string 备注
		 * @return_param    create_time string 添加时间
		 * @return_param    nickname string 姓名
		 * @return_param    phone string 手机号
		 * @return_param    area string 区域
		 * @return_param    chat_time string 上次单聊时间
		 * @return_param    add_way_info string 来源
		 * @return_param    leave int 0全部1离职未分配2离职已分配
		 * @return_param    unshare_chat int 不共享所在群1是0否
		 * @return_param    unshare_follow int 不共享跟进记录1是0否
		 * @return_param    unshare_line int 不共享互动轨迹1是0否
		 * @return_param    unshare_field int 不共享客户画像1是0否
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/20 16:15
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\db\Exception
		 */
		public function actionCustomList ()
		{
			if (\Yii::$app->request->isPost) {
				$user_ids        = \Yii::$app->request->post('user_ids');
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$name            = \Yii::$app->request->post('name', '');
				$phone           = \Yii::$app->request->post('phone');
				$leave           = \Yii::$app->request->post('leave');
				$name            = trim($name);
				$phone           = trim($phone);
				//$qq      = \Yii::$app->request->post('qq', '');
				//$company = \Yii::$app->request->post('company', '');

				$sex           = \Yii::$app->request->post('sex', 0);
				$work          = \Yii::$app->request->post('work', '');
				$province      = \Yii::$app->request->post('province', '');
				$city          = \Yii::$app->request->post('city', '');
				$follow_status = \Yii::$app->request->post('follow_status', '-1');
				$fieldData     = \Yii::$app->request->post('fieldData', []);
				$follow_id     = \Yii::$app->request->post('follow_id', '-1');

				$tag_ids           = \Yii::$app->request->post('tag_ids', '');
				$group_id          = \Yii::$app->request->post('group_id', '');
				$tag_type          = \Yii::$app->request->post('tag_type', 1);
				$no_tag            = \Yii::$app->request->post('no_tag', 0);
				$add_way           = \Yii::$app->request->post('add_way', '-1');
				$way_id            = \Yii::$app->request->post('way_id', '');
				$chat_id           = \Yii::$app->request->post('chat_id', '');
				$chat_type         = \Yii::$app->request->post('chat_type', 0);
				$start_time        = \Yii::$app->request->post('start_time');
				$end_time          = \Yii::$app->request->post('end_time');
				$is_all            = \Yii::$app->request->post('is_all') ?: 0;
				$correctness       = \Yii::$app->request->post('correctness') ?: 2;//1全部 2条件
				$page              = \Yii::$app->request->post('page') ?: 1;
				$pageSize          = \Yii::$app->request->post('page_size') ?: 15;
				$update_time       = \Yii::$app->request->post('update_time');
				$follow_num1       = \Yii::$app->request->post('follow_num1');
				$follow_num2       = \Yii::$app->request->post('follow_num2');
				$from_unique       = \Yii::$app->request->post('from_unique') ?: 0;
				$chat_time         = \Yii::$app->request->post('chat_time');
				$sign_id           = \Yii::$app->request->post('sign_id');
				$is_fans           = \Yii::$app->request->post('is_fans');
				$is_export         = \Yii::$app->request->post('is_export');
				$selected_row_keys = \Yii::$app->request->post('selected_row_keys');
				$otherWay          = \Yii::$app->request->post('other_way', '-1');
				$isProtect         = \Yii::$app->request->post('is_protect','-1');

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$sub_id = isset($this->subUser->sub_id) ? $this->subUser->sub_id : 0;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0,[],$sub_id);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				if(!empty($sub_id) && empty($user_ids)){
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, [], [], 0, true,0,[],$sub_id,0,true);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				$userId               = 0;
				$uid                  = $this->user->uid;
				$offset               = ($page - 1) * $pageSize;
				$workExternalUserData = WorkExternalContactFollowUser::find()->alias('wf');
				$workExternalUserData = $workExternalUserData->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid');
				$workExternalUserData = $workExternalUserData->andWhere(['we.corp_id' => $this->corp['id']]);
				if (empty($leave)) {
					$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);
				}
				if ($isMasterAccount == 2) {
//					$subUser = SubUser::findOne($sub_id);
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if (is_array($sub_detail)) {
						$workExternalUserData = $workExternalUserData->andWhere(["in", 'wf.user_id', $sub_detail]);
					} else if ($sub_detail === false) {
						return ["count" => 0, "info" => [], "keys" => [], "tag_count" => [], "uniqueCount" => 0];
					}
//					if ($subUser->sub_id != 61 && $subUser->sub_id != 32) {
//						$department        = '';
//						$is_leader_ain_dept = '';
//						if (!empty($subUser)) {
//							$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'mobile' => $subUser->account]);
//							if (!empty($workUser)) {
//								$userId            = $workUser->id;
//								$department        = $workUser->department;
//								$is_leader_in_dept = $workUser->is_leader_in_dept;
//							}
//						}
//						if (!empty($department)) {
//							$userID = WorkDepartment::getDepartId($department, $this->corp->id, $is_leader_in_dept);
//							if (!empty($userID)) {
//								array_push($userID, $userId);
//							} else {
//								$userID = $userId;
//							}
//						} else {
//							$userID = [$userId];
//						}
//
//					}
//					$workExternalUserData = $workExternalUserData->andWhere(["in",'wf.user_id',$userID]);
				}
				if (!empty($is_fans)) {
					if ($is_fans == 1) {
						$workExternalUserData = $workExternalUserData->andWhere(['we.is_fans' => 1]);
					} else {
						$workExternalUserData = $workExternalUserData->andWhere(['we.is_fans' => 0]);
					}
				}
				if (!empty($leave)) {
					$workExternalUserData = $workExternalUserData->leftJoin('{{%work_user}} wu', 'wf.userid=wu.userid')->andWhere(['wu.is_del' => WorkUser::USER_IS_DEL]);
					if ($leave == 1) {
						$workExternalUserData = $workExternalUserData->andWhere(['wf.del_type' => WorkExternalContactFollowUser::NO_ASSIGN]);
					} else {
						$workExternalUserData = $workExternalUserData->andWhere(['wf.del_type' => WorkExternalContactFollowUser::HAS_ASSIGN]);
					}
				}
				if (!empty($update_time)) {
					$workExternalUserData = $workExternalUserData->andFilterWhere(['between', 'wf.update_time', strtotime($update_time[0]), strtotime($update_time[1] . ':59')]);
				}
				if ($follow_num1 != '' || $follow_num2 != '') {
					if (($follow_num1 == '0' && $follow_num2 == '0')) {
						$follow_num           = '0';
						$workExternalUserData = $workExternalUserData->andWhere(['wf.follow_num' => $follow_num]);
					} else {
						if ((($follow_num1 == '') && $follow_num2 >= 0)) {
							$workExternalUserData = $workExternalUserData->andWhere(['>=', 'wf.follow_num', 0]);
							$workExternalUserData = $workExternalUserData->andWhere(['<=', 'wf.follow_num', $follow_num2]);
						}
						if (($follow_num1 >= 0 && ($follow_num2 == ''))) {
							$workExternalUserData = $workExternalUserData->andWhere(['>=', 'wf.follow_num', $follow_num1]);
						}
						if (!empty($follow_num1) && !empty($follow_num2)) {
							$workExternalUserData = $workExternalUserData->andWhere(['>=', 'wf.follow_num', $follow_num1]);
							$workExternalUserData = $workExternalUserData->andWhere(['<=', 'wf.follow_num', $follow_num2]);
						}
					}

				}
				if (!empty($chat_time)) {
					$contactId      = [];
					$chartStartTime = strtotime($chat_time[0]) . '000';
					$chartEndTime   = strtotime($chat_time[1] . ':59') . '000';
					$sql            = 'SELECT we.id, max(wa.msgtime) AS msgtime FROM {{%work_msg_audit_info}} AS wa LEFT JOIN {{%work_external_contact}} AS we ON we.external_userid = ( CASE WHEN wa.from_type = 1 THEN wa.tolist ELSE wa.from END ) WHERE wa.audit_id = 1 AND wa.roomid IS NULL AND we.corp_id = ' . $this->corp['id'] . ' AND wa.from_type != 3 AND (( CASE WHEN wa.from_type = 1 THEN wa.tolist ELSE wa.from END ) LIKE (\'wm_%\') OR ( CASE WHEN wa.from_type = 1 THEN wa.tolist ELSE wa.from END ) LIKE (\'wo_%\')) GROUP BY we.id';
					\Yii::error($sql, 'chat_sql');
					$auditInfo = \Yii::$app->getDb()->createCommand($sql)->queryAll();
					if (!empty($auditInfo)) {
						foreach ($auditInfo as $info) {
							if ($info['msgtime'] >= $chartStartTime && $info['msgtime'] <= $chartEndTime) {
								if (!empty($info['id'])) {
									array_push($contactId, $info['id']);
								}
							}
						}
					}
					$workExternalUserData = $workExternalUserData->andWhere(['we.id' => $contactId]);
				}
				if (!empty($sign_id)) {
					$contactId = [];
					$member    = WorkExternalContactMember::find()->where(['sign_id' => $sign_id, 'is_bind' => 1])->select('external_userid')->groupBy('external_userid');
					$member    = $member->asArray()->all();
					if (!empty($member)) {
						foreach ($member as $mem) {
							array_push($contactId, $mem['external_userid']);
						}
					}
					$workExternalUserData = $workExternalUserData->andWhere(['we.id' => $contactId]);
				}

				if (!empty($selected_row_keys) && $is_export == 1) {
					$workExternalUserData = $workExternalUserData->andWhere(['wf.id' => $selected_row_keys]);
				}
				//添加客户的其它来源
				if ($otherWay != '-1') {
					$workExternalUserData = $workExternalUserData->andWhere(['wf.other_way' => $otherWay]);
				}

				//是否保护
				if ($isProtect != '-1') {
					$workExternalUserData = $workExternalUserData->andWhere(['wf.is_protect' => $isProtect]);
				}

				//高级属性搜索
				$fieldList    = CustomField::find()->where('is_define=0')->select('`id`,`key`')->asArray()->all();//默认属性
				$fieldD       = [];
				$contactField = [];//列表展示字段
				foreach ($fieldList as $k => $v) {
					$fieldD[$v['key']] = $v['id'];
					if (in_array($v['key'], ['name', 'sex', 'phone', 'area'])) {
						array_push($contactField, $v['id']);
					}
				}
				if ($correctness == 2) {
					if (!empty($user_ids)) {
						$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.user_id', $user_ids]);
					}
					if (!empty($start_time) || !empty($end_time)) {
						if (!empty($start_time) && !empty($end_time)) {
							$workExternalUserData = $workExternalUserData->andFilterWhere(['between', 'createtime', strtotime($start_time), strtotime($end_time . ':59')]);
						} elseif (!empty($start_time)) {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'createtime', strtotime($start_time)]);
						} else {
							$workExternalUserData = $workExternalUserData->andWhere(['<', 'createtime', strtotime($end_time)]);
						}
					}

					/*if (!empty($name)) {
						$workExternalUserData = $workExternalUserData->andWhere(['we.name' => $name]);
					}
					if (!empty($sex) && $sex != '-1') {
						$sex = $sex == 3 ? 0 : $sex;
						$workExternalUserData = $workExternalUserData->andWhere(['we.gender' => $sex]);
					}*/
					if ($follow_status != '-1') {
						$workExternalUserData = $workExternalUserData->andWhere(['we.follow_status' => $follow_status]);
					}
					if ($follow_id != '-1') {
						$workExternalUserData = $workExternalUserData->andWhere(['wf.follow_id' => $follow_id]);
					}
					//来源搜索
					if ($add_way != '-1') {
						if ($add_way === 'way') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.way_id', 0]);
						} elseif ($add_way === 'chatWay') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.chat_way_id', 0]);
						} elseif ($add_way === 'fission') {
							$workExternalUserData = $workExternalUserData->andWhere(['or', ['>', 'wf.fission_id', 0], ['>', 'wf.activity_id', 0]]);
						} elseif ($add_way === 'award') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.award_id', 0]);
						} elseif ($add_way === 'redPack') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.red_pack_id', 0]);
						} elseif ($add_way === 'redWay') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.way_redpack_id', 0]);
						} elseif ($add_way === 'punch') {
							$workExternalUserData = $workExternalUserData->andWhere(['>', 'wf.punch_id', 0]);
						} else {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.add_way' => $add_way]);
						}
					}

					//活码搜索
					if (!empty($way_id)) {
						$wayArr = explode('_', $way_id);
						if ($wayArr[0] == 'way') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.way_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'chatWay') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.chat_way_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'fission') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.fission_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'activity') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.activity_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'award') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.award_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'redPack') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.red_pack_id' => $wayArr[1]]);
						} elseif ($wayArr[0] == 'redWay') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.way_redpack_id' => $wayArr[1]]);
						}elseif ($wayArr[0] == 'punch') {
							$workExternalUserData = $workExternalUserData->andWhere(['wf.punch_id' => $wayArr[1]]);
						}
					}
					//客户群搜索
					if (!empty($chat_id)) {
						$chatInfo = WorkChatInfo::find()->where(['chat_id' => $chat_id, 'type' => 2, 'status' => 1])->select('id,external_id')->all();
						if (!empty($chatInfo)) {
							$tempId               = array_column($chatInfo, 'external_id');
							$workExternalUserData = $workExternalUserData->andWhere(['in', 'we.id', $tempId]);
						} else {
							$workExternalUserData = $workExternalUserData->andWhere(['we.id' => 0]);
						}
					}

					//群搜索
					if (!empty($chat_type)) {
						$tempContact = WorkExternalContact::find()->alias('wec');
						$tempContact = $tempContact->leftJoin('{{%work_chat_info}} wci', '`wci`.`external_id` = `wec`.`id` and `wci`.`status`=1 and `wci`.`type`=2');
						$tempContact = $tempContact->where(['wec.corp_id' => $this->corp['id']]);
						$tempContact = $tempContact->select('wec.id,count(wci.id) count');
						$tempContact = $tempContact->groupBy('wec.id');
						if ($chat_type == 1) {
							$tempContact = $tempContact->having('count=0');
						} elseif ($chat_type == 2) {
							$tempContact = $tempContact->having('count=1');
						} elseif ($chat_type == 3) {
							$tempContact = $tempContact->having('count > 1');
						}
						$tempContact = $tempContact->all();
						if (!empty($tempContact)) {
							$tempId               = array_column($tempContact, 'id');
							$workExternalUserData = $workExternalUserData->andWhere(['in', 'we.id', $tempId]);
						} else {
							$workExternalUserData = $workExternalUserData->andWhere(['we.id' => 0]);
						}
					}
					//标签搜索
					if ((!empty($tag_ids) || !empty($group_id) || !empty($no_tag)) && in_array($tag_type, [1, 2])) {
						$tagIds = [];
						if (empty($tag_ids)) {
							//标签组搜索
							$tagData = WorkTag::find()->andWhere(['type' => 0, 'is_del' => 0, 'corp_id' => $this->corp['id']]);
							$tagData = $tagData->andWhere(['in', 'group_id', $group_id])->select('id')->all();
							foreach ($tagData as $tagD) {
								array_push($tagIds, $tagD->id);
							}
						} else {
							//标签搜索
							if (is_array($tag_ids)) {
								$tagIds = $tag_ids;
							} else {
								$tagIds = explode(',', $tag_ids);
							}
						}
						$tempContactId = [];
						if (!empty($no_tag)) {
							//获取无标签外部联系人
							$tempWorkExternalUserData = clone $workExternalUserData;
							$tempWorkExternalUserData = $tempWorkExternalUserData->leftJoin('{{%work_tag_follow_user}} wt', '`wt`.`follow_user_id` = `wf`.`id`');
							$tempWorkExternalUserData = $tempWorkExternalUserData->andWhere(['wt.id' => NULL, 'wt.corp_id' => $this->corp['id'], 'wt.status' => 1]);
							$tempContactArr           = $tempWorkExternalUserData->select('wt.id')->all();
							if (!empty($tempContactArr)) {
								$tempContactId = array_column($tempContactArr, 'id');
							}
						}
						if ($tag_type == 1) {
							//或
							$workExternalUserData = $workExternalUserData->leftJoin('{{%work_tag_follow_user}} wt', '`wt`.`follow_user_id` = `wf`.`id`')->andWhere(['or', ['in', 'wt.tag_id', $tagIds], ['wf.id' => $tempContactId]])->andWhere(['wt.corp_id' => $this->corp['id'], 'wt.status' => 1]);
						} else {
							//且
							$contactArr = [];//符合标签并集客户
							$havaTag    = 1;//有符合标签的客户
							foreach ($tagIds as $tagId) {
								if ($havaTag) {
									$contactTag = WorkTagFollowUser::find()->where(['tag_id' => $tagId, 'status' => 1, 'corp_id' => $this->corp['id']])->select('`follow_user_id`')->asArray()->all();
									$contactId  = array_column($contactTag, 'follow_user_id');
									if (!empty($contactArr)) {
										$contactArr = array_intersect($contactArr, $contactId);
									} else {
										$contactArr = $contactId;
									}
									$havaTag = empty($contactArr) ? 0 : $havaTag;
								}
							}
							\Yii::error($contactArr, '$contactArr');
							if (!empty($no_tag)) {
								if (empty($tag_ids)) {
									$contactArr = $tempContactId;
								} else {
									$contactArr = [];
								}
							}
							if (!empty($contactArr)) {
								$workExternalUserData = $workExternalUserData->andWhere(['in', 'wf.id', $contactArr]);
							} else {
								$workExternalUserData = $workExternalUserData->andWhere(['wf.id' => 0]);
							}
						}
					}

					$fieldSubUser = [];
					if (!empty($sub_detail) && is_array($sub_detail)){
						$fieldSubUser = array_merge($sub_detail, [0]);
					}
					if ($name || $phone !== '' || $work || $province || $sex != '-1' || !empty($fieldData)) {
						if (!empty($phone)) {
							$workExternalUserData = $workExternalUserData->andWhere(' wf.remark_mobiles like  \'%' . $phone . '%\' ');
						}

						if ($isMasterAccount == 2 && $this->corp->unshare_field == 1 && !empty($fieldSubUser)) {
							$workExternalUserData = $workExternalUserData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1 AND `cf`.`user_id` in (0,' . implode(',', $sub_detail) . ')');
						}else{
							$workExternalUserData = $workExternalUserData->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
						}
						if (!empty($name)) {
							$workExternalUserData = $workExternalUserData->andWhere(' we.name_convert like \'%' . $name . '%\' or wf.remark_corp_name like \'%' . $name . '%\'  or wf.remark like \'%' . $name . '%\' or wf.nickname like \'%' . $name . '%\' or (cf.fieldid in (' . $fieldD['name'] . ') and cf.value like \'%' . $name . '%\')');
						}

						if ($work || $province || $sex != '-1' || !empty($fieldData)) {
							$fieldUserArr = [];
							$havaField    = 1;//有符合条件的客户

							if ($sex != '-1') {
								if ($sex == 1) {
									$sex = '男';
								} elseif ($sex == 2) {
									$sex = '女';
								} else {
									$sex = '未知';
								}
								$fieldUserData = CustomFieldValue::find()->where(['in', 'uid', [0, $uid]])->andWhere(['type' => 1, 'fieldid' => $fieldD['sex'], 'value' => $sex]);
								if ($isMasterAccount == 2 && $this->corp->unshare_field == 1 && !empty($fieldSubUser)) {
									$fieldUserData = $fieldUserData->andWhere(['user_id' => $fieldSubUser]);
								}
								$fieldUserData        = $fieldUserData->select('`cid`')->asArray()->all();
								$contactId            = array_column($fieldUserData, 'cid');
								$fieldUserArr         = !empty($fieldUserArr) ? array_intersect($fieldUserArr, $contactId) : $contactId;
								$havaField            = empty($fieldUserArr) ? 0 : $havaField;
								$workExternalUserData = $workExternalUserData->andWhere(['cf.fieldid' => $fieldD['sex']]);
							}
//							if ($phone !== '' && $havaField) {
//								$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['and', ['in', 'fieldid', [$fieldD['phone'], $fieldD['qq']]], ['like', 'value', $phone]])->select('`cid`')->asArray()->all();
//								$contactId     = array_column($fieldUserData, 'cid');
//								$fieldUserArr  = !empty($fieldUserArr) ? array_intersect($fieldUserArr, $contactId) : $contactId;
//								$havaField     = empty($fieldUserArr) ? 0 : $havaField;
//							}
							if (!empty($work) && $havaField) {
								$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['fieldid' => $fieldD['work'], 'value' => $work]);
								if ($isMasterAccount == 2 && $this->corp->unshare_field == 1 && !empty($fieldSubUser)) {
									$fieldUserData = $fieldUserData->andWhere(['user_id' => $fieldSubUser]);
								}
								$fieldUserData = $fieldUserData->select('`cid`')->asArray()->all();
								$contactId     = array_column($fieldUserData, 'cid');
								$fieldUserArr  = !empty($fieldUserArr) ? array_intersect($fieldUserArr, $contactId) : $contactId;
								$havaField     = empty($fieldUserArr) ? 0 : $havaField;
							}
							if (!empty($province) && $havaField) {
								if (!empty($city)) {
									$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['fieldid' => $fieldD['area'], 'value' => $province . '-' . $city]);
								} else {
									$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['and', ['fieldid' => $fieldD['area']], ['like', 'value', $province . '-']]);
								}
								if ($isMasterAccount == 2 && $this->corp->unshare_field == 1 && !empty($fieldSubUser)) {
									$fieldUserData = $fieldUserData->andWhere(['user_id' => $fieldSubUser]);
								}
								$fieldUserData = $fieldUserData->select('`cid`')->asArray()->all();
								$contactId    = array_column($fieldUserData, 'cid');
								$fieldUserArr = !empty($fieldUserArr) ? array_intersect($fieldUserArr, $contactId) : $contactId;
								$havaField    = empty($fieldUserArr) ? 0 : $havaField;
							}

							if (!empty($fieldData) && $havaField) {
								foreach ($fieldData as $val) {
									if ($havaField) {
										if ($val['type'] == 3) {
											//多选属性需模糊匹配
											$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['and', ['fieldid' => $val['field']], ['like', 'value', $val['match']]]);
										} else {
											$fieldUserData = CustomFieldValue::find()->where(['uid' => $uid, 'type' => 1])->andWhere(['fieldid' => $val['field'], 'value' => $val['match']]);
										}
										if ($isMasterAccount == 2 && $this->corp->unshare_field == 1 && !empty($fieldSubUser)) {
											$fieldUserData = $fieldUserData->andWhere(['user_id' => $fieldSubUser]);
										}
										$fieldUserData = $fieldUserData->select('`cid`')->asArray()->all();
										$contactId    = array_column($fieldUserData, 'cid');
										$fieldUserArr = !empty($fieldUserArr) ? array_intersect($fieldUserArr, $contactId) : $contactId;
										$havaField    = empty($fieldUserArr) ? 0 : $havaField;
									}
								}
							}

							if (!empty($fieldUserArr)) {
								$workExternalUserData = $workExternalUserData->andWhere(['in', 'we.id', $fieldUserArr]);
							} else {
								$workExternalUserData = $workExternalUserData->andWhere(['we.id' => 0]);
							}
						}

						/*if ($sex != '-1') {
							if ($sex == 1) {
								$sex = '男';
							} elseif ($sex == 2) {
								$sex = '女';
							} else {
								$sex = '未知';
							}
							$workExternalUserData = $workExternalUserData->andWhere(['cf.fieldid' => $fieldD['sex'], 'cf.value' => $sex]);
						}
						if (!empty($phone)) {
							$workExternalUserData = $workExternalUserData->andWhere(['and', ['in', 'cf.fieldid', [$fieldD['phone'], $fieldD['qq']]], ['like', 'cf.value', $phone]]);
						}
						if (!empty($work)) {
							$workExternalUserData = $workExternalUserData->andWhere(['cf.fieldid' => $fieldD['work'], 'cf.value' => $work]);
						}
						if (!empty($province)) {
							if (!empty($city)) {
								$workExternalUserData = $workExternalUserData->andWhere(['cf.fieldid' => $fieldD['area'], 'cf.value' => $province . '-' . $city]);
							} else {
								$workExternalUserData = $workExternalUserData->andWhere(['and', ['cf.fieldid' => $fieldD['area']], ['like', 'cf.value', $province . '-']]);
							}
						}
						if (!empty($fieldData)) {
							foreach ($fieldData as $val) {
								if ($val['type'] == 3){
									//多选属性需模糊匹配
									$workExternalUserData = $workExternalUserData->andWhere(['and', ['cf.fieldid' => $val['field']], ['like', 'cf.value', $val['match']]]);
								}else{
									$workExternalUserData = $workExternalUserData->andWhere(['cf.fieldid' => $val['field'], 'cf.value' => $val['match']]);
								}
							}
						}*/
					}
				}
				if (empty($from_unique)) {
					$group = 'wf.id';
					$count = $workExternalUserData->groupBy($group);
					$count = $count->count();
					$allExternalUser = clone $workExternalUserData;
					//所有客户id
					$allExternalUser = $allExternalUser->select('wf.id')->limit($pageSize)->offset($offset)->groupBy($group)->orderBy(['wf.createtime' => SORT_DESC])->asArray()->all();
				} else {
					$group = 'we.id';
					//所有客户id
					$allExternalUser = clone $workExternalUserData;
					$allExternalUser = $allExternalUser->select('wf.id,count(DISTINCT(wf.user_id)) as count')->limit($pageSize)->offset($offset)->groupBy($group)->having(['>', 'count', 1])->orderBy(['wf.createtime' => SORT_DESC]);
					$allExternalUser = $allExternalUser->asArray()->all();
					$count           = count($allExternalUser);
				}
				$workExternalUserData1 = $workExternalUserData;
				$uniqueCount           = $workExternalUserData1->groupBy('we.id')->count();

				$externalIds    = [];
				$userTagCount   = [];
				$userIndexArray = [];
				foreach ($allExternalUser as $key => $val) {
					array_push($externalIds, $val['id']);
					array_push($userTagCount, 0);
					$userIndexArray[$val['id']] = $key;
				}
				$workTagContact = WorkTagFollowUser::find()->alias('w');
				$workTagContact = $workTagContact->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`');
				$tag_count      = $workTagContact->select('w.`follow_user_id`, count(w.`follow_user_id`) as cnt')->where(['w.follow_user_id' => $externalIds, 'w.status' => 1, 't.is_del' => 0, 'w.corp_id' => $this->corp['id']])->groupBy('w.follow_user_id')->asArray()->all();
				$tagCount       = array_column($tag_count, 'cnt', 'follow_user_id');
				if (!empty($tagCount)) {
					foreach ($tagCount as $cId => $cnt) {
						$userTagCount[$userIndexArray[$cId]] = $cnt;
					}
				}
				if ($is_export == 1) {
					$workExternalUserData = $workExternalUserData->select('wf.activity_id,we.id as wid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.id fid,wf.way_id,wf.add_way,wf.follow_num,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.way_redpack_id,wf.update_time,wf.remark_mobiles')->groupBy($group)->orderBy(['wf.createtime' => SORT_DESC]);
					$workExternalUserData = $workExternalUserData->asArray()->all();
					if (empty($workExternalUserData)) {
						throw new InvalidParameterException('当前数据为空，无法导出！');
					}
					\Yii::$app->queue->push(new ExportCustomJob([
						'count'      => $count,
						'exportData' => $workExternalUserData,
						'uid'        => $this->user->uid,
						'corpId'     => $this->corp->id,
					]));

					return true;
				}
				if (empty($is_all) && empty($is_export)) {
					$workExternalUserData = $workExternalUserData->limit($pageSize)->offset($offset);
				}
				if (empty($from_unique)) {
					$workExternalUserData = $workExternalUserData->select('wf.activity_id,we.id as wid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.id fid,wf.way_id,wf.add_way,wf.follow_num,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.way_redpack_id,wf.update_time,wf.is_reclaim,wf.remark_mobiles,wf.other_way,wf.is_protect,wf.external_userid')->groupBy($group)->orderBy(['wf.createtime' => SORT_DESC]);
					$workExternalUserData = $workExternalUserData->asArray()->all();
				} else {
					$workExternalUserData = $workExternalUserData->select('wf.activity_id,we.id as wid,we.corp_id corp_id,we.corp_name as wcorp_name,wf.id as id,we.name,we.name_convert,we.gender,we.avatar,we.follow_status,wf.follow_id,wf.user_id,wf.userid,wf.state,wf.remark,wf.nickname,wf.createtime,wf.id fid,wf.way_id,wf.follow_num,wf.add_way,wf.chat_way_id,wf.fission_id,wf.award_id,wf.red_pack_id,wf.way_redpack_id,wf.update_time,wf.is_reclaim,wf.remark_mobiles,count(DISTINCT(wf.user_id)) as count,wf.other_way,wf.is_protect,wf.external_userid')->groupBy($group)->having(['>', 'count', 1])->orderBy(['wf.createtime' => SORT_DESC]);
					$workExternalUserData = $workExternalUserData->asArray()->all();
				}
				//查询客户保护
				if ($isMasterAccount == 1) {
					$isShow = $isRest = 1;
				} else {
					$protectFollowData = PublicSeaProtect::getDataByFollowUserId($this->corp->id, $externalIds,1);
					$protectData       = PublicSeaProtect::getProtectBySubId($this->corp->id, $sub_id);
					$isShow            = $protectData['is_show'];
					$isRest            = $protectData['is_rest'];
					$subUserId         = $protectData['sub_user_id'];
				}
				$isCancel = 0;//取消按钮是否可用
				$result     = [];
				$exportData = [];
				if (!empty($workExternalUserData)) {
					foreach ($workExternalUserData as $key => $val) {
						if ($val['gender'] == 0) {
							$gender = '未知';
						} elseif ($val['gender'] == 1) {
							$gender = '男性';
						} elseif ($val['gender'] == 2) {
							$gender = '女性';
						}
						if (empty($from_unique)) {
							$workExternal = WorkExternalContactFollowUser::find()->andWhere(['id' => $val['fid']]);
							if (isset($sub_detail) && is_array($sub_detail)) {
								$workExternal = $workExternal->andWhere(["in", 'user_id', $sub_detail]);
							}
							$workExternal = $workExternal->select('user_id,nickname,remark,del_type,createtime')->all();
						} else {
							$workExternal = WorkExternalContactFollowUser::find()->alias('f')->where(['f.external_userid' => $val['wid']])->select('f.user_id,f.createtime,f.del_type,f.nickname,f.remark');
							if (isset($sub_detail) && is_array($sub_detail)) {
								$workExternal = $workExternal->andWhere(["in", 'f.user_id', $sub_detail]);
							}
							$workExternal = $workExternal->select('user_id,nickname,remark,del_type,createtime')->all();
						}
						$memberInfo = [];
						$userId     = [];
						if (!empty($workExternal)) {
							/**
							 * @var int                           $k
							 * @var WorkExternalContactFollowUser $user
							 */
							foreach ($workExternal as $k => $user) {
								$work_user  = WorkUser::findOne($user->user_id);
								$departName = WorkDepartment::getDepartNameByUserId($work_user->department, $work_user->corp_id);
								$member     = $work_user->name . '--' . $departName;
								$remark     = !empty($user->nickname) ? "（备注：" . $user->nickname . "）" : ((!empty($user->remark) && $user->remark != $val['name_convert']) ? "（备注：" . $user->remark . "）" : "");
//								if (empty($this->subUser) || $this->subUser->account == $work_user->mobile) {
//									$member .= !empty($user->nickname) ? "（备注：" . $user->nickname . "）" : ((!empty($user->remark) && $user->remark != $val['name_convert']) ? "（备注：" . $user->remark . "）" : "");
//								}
								array_push($userId, $work_user->id);
								$memberInfo[$k]['remark']      = $remark;
								$memberInfo[$k]['member']      = $member;
								$memberInfo[$k]['del_type']    = $user->del_type;
								$memberInfo[$k]['create_time'] = !empty($user->createtime) ? date("Y-m-d H:i:s", $user->createtime) : '';
							}
						}
						$perName = WorkPerTagFollowUser::getTagName($val['fid'], $from_unique, $userId);
						$tagName = WorkTagContact::getTagNameByContactId($val['fid'], 0, $from_unique, $userId,$this->corp->id);
						//获取所在群名称
						if ($isMasterAccount == 2 && $this->corp->unshare_chat == 1 && !empty($fieldSubUser)){
							$chatName = WorkChatInfo::getChatList(2, $val['wid'], '', $this->corp->unshare_chat, $fieldSubUser);
						}else{
							$chatName = WorkChatInfo::getChatList(2, $val['wid']);
						}
						//获取绑定店铺名称
						$member       = WorkExternalContactMember::find()->alias('m');
						$member       = $member->leftJoin('{{%application_sign}} s', '`s`.`id` = `m`.`sign_id`');
						$member       = $member->where(['m.is_bind' => 1, 's.is_bind' => 1, 's.uid' => $this->user->uid, 'm.external_userid' => $val['wid']])->select('s.username,s.come_from')->groupBy('s.username')->asArray()->all();
						$username     = [];
						$merchantType = [];
						$appSing      = ApplicationSign::find()->where(['uid' => $this->user->uid, 'is_bind' => 1])->andWhere(['<>', 'come_from', 0])->groupBy('come_from')->asArray()->all();
						if (!empty($appSing)) {
							foreach ($appSing as $v) {
								if ($v['come_from'] == 1) {
									array_push($merchantType, '小猪智慧店铺');
								} elseif ($v['come_from'] == 2) {
									array_push($merchantType, '有赞');
								} elseif ($v['come_from'] == 3) {
									array_push($merchantType, '淘宝');
								} elseif ($v['come_from'] == 4) {
									array_push($merchantType, '天猫');
								}
							}
						}
						if (!empty($member)) {
							foreach ($member as $mem) {
								if ($mem['come_from'] == 1) {
									array_push($username, '小猪智慧店铺：' . $mem['username']);
								} elseif ($mem['come_from'] == 2) {
									array_push($username, '有赞：' . $mem['username']);
								} elseif ($mem['come_from'] == 3) {
									array_push($username, '淘宝：' . $mem['username']);
								} elseif ($mem['come_from'] == 4) {
									array_push($username, '天猫：' . $mem['username']);
								}
							}
						}
						$result[$key]['customerInfo']['merchant_type'] = $merchantType;
						$result[$key]['customerInfo']['user_name']     = $username;
						$result[$key]['customerInfo']['avatar']        = $val['avatar'];
						$result[$key]['customerInfo']['name']          = !empty($val['name']) ? rawurldecode($val['name']) : '';
						$result[$key]['customerInfo']['corp_name']     = $val['wcorp_name'];
						$fans                                          = Fans::findOne(['external_userid' => $val['wid'], 'subscribe' => Fans::USER_SUBSCRIBE]);
						$wxName                                        = '';
						if (!empty($fans)) {
							$wxName = $fans->author->wxAuthorizeInfo->nick_name;
						}
						$result[$key]['wx_name']         = $wxName;

//						if (!empty($this->subUser)) {
//							$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'mobile' => $this->subUser->account]);
//							if (!empty($workUser)) {
//								$workExternalContactFollowUser = WorkExternalContactFollowUser::findOne(['external_userid' => $val['wid'], 'user_id' => $workUser->id]);
//								if (!empty($workExternalContactFollowUser) && !empty($workExternalContactFollowUser->remark)) {
//									$result[$key]['customerInfo']['name'] = !empty($result[$key]['customerInfo']['name']) ? $workExternalContactFollowUser->remark . "（" . $result[$key]['customerInfo']['name'] . "）" : $workExternalContactFollowUser->remark;
//								}
//							}
//						}

						//跟进状态
						$follow_status = '';
						if (!empty($val['follow_id'])) {
							$follow = Follow::findOne($val['follow_id']);
							if (!empty($follow)) {
								$follow_status = $follow->title;
								if ($follow->status == 0) {
									$follow_status .= '（已删除）';
								}
							}
						}
						$result[$key]['customerInfo']['follow_status'] = $follow_status;
						if ($val['createtime'] == $val['update_time']) {
							$result[$key]['customerInfo']['update_time'] = '';
						} else {
							$result[$key]['customerInfo']['update_time'] = !empty($val['update_time']) ? date('Y-m-d H:i', $val['update_time']) : '--';
						}

						//高级属性
						$fieldValue  = CustomFieldValue::find()->where(['type' => 1, 'cid' => $val['wid']])->andWhere(['in', 'fieldid', $contactField])->asArray()->all();
						$fieldValueD = [];
						foreach ($fieldValue as $field) {
							$fieldValueD[$field['fieldid']] = $field['value'];
						}
						$result[$key]['customerInfo']['nickname'] = isset($fieldValueD[$fieldD['name']]) ? $fieldValueD[$fieldD['name']] : '';
						$result[$key]['customerInfo']['phone']    = isset($val['remark_mobiles']) && !empty($val['remark_mobiles']) ? $val['remark_mobiles'] : '';
						$result[$key]['customerInfo']['area']     = isset($fieldValueD[$fieldD['area']]) ? $fieldValueD[$fieldD['area']] : '';
						$is_hide_phone = $this->user->is_hide_phone;
						if ($is_hide_phone){
							$result[$key]['customerInfo']['phone'] = '';
						}
						if (isset($fieldValueD[$fieldD['sex']])) {
							if ($fieldValueD[$fieldD['sex']] == '男') {
								$gender = '男性';
							} elseif ($fieldValueD[$fieldD['sex']] == '女') {
								$gender = '女性';
							} else {
								$gender = '未知';
							}
						}
						$result[$key]['customerInfo']['gender'] = $gender;

						//$result[$key]['customerInfo']['tag_name'] = $tagName;
						$result[$key]['tag_name']   = $tagName;
						$result[$key]['per_name']   = $perName;
						$result[$key]['chat_name']  = $chatName;
						$result[$key]['key']        = $val['fid'];
						$result[$key]['source']     = $val['state'];
						$result[$key]['memberInfo'] = $memberInfo;
						$addWayInfo                 = '';
						$addWayInfo                 = WorkExternalContactFollowUser::getAddWay($val['add_way']);
						$title                      = '';
						$wayInfo                    = '';
						if ($val['way_id'] > 0) {
							$wayInfo    = '渠道活码';
							$contactWay = WorkContactWay::findOne($val['way_id']);
							if (!empty($contactWay)) {
								$title = $contactWay->title;
							}
						} elseif ($val['chat_way_id'] > 0) {
							$wayInfo = '自动拉群';
							$way     = WorkChatContactWay::findOne($val['chat_way_id']);
							if (!empty($way)) {
								$title = $way->title;
							}
						} elseif ($val['fission_id'] > 0) {
							$wayInfo = '裂变引流';
							$fission = Fission::findOne($val['fission_id']);
							if (!empty($fission)) {
								$title = $fission->title;
							}
						} elseif ($val['award_id'] > 0) {
							$wayInfo = '抽奖引流';
							$award   = AwardsActivity::findOne($val['award_id']);
							if (!empty($award)) {
								$title = $award->title;
							}
						} elseif ($val['red_pack_id'] > 0) {
							$wayInfo = '红包裂变';
							$red     = RedPack::findOne($val['red_pack_id']);
							if (!empty($red)) {
								$title = $red->title;
							}
						} elseif ($val['activity_id'] > 0) {
							$wayInfo = '裂变引流';
							$red     = WorkPublicActivity::findOne($val['activity_id']);
							if (!empty($red)) {
								$title = $red->activity_name;
							}
						} elseif ($val['way_redpack_id'] > 0) {
							$wayInfo   = '红包拉新';
							$redpacket = WorkContactWayRedpacket::findOne($val['way_redpack_id']);
							if (!empty($redpacket)) {
								$title = $redpacket->name;
							}
						}
						$result[$key]['add_other_info'] = $addWayInfo;
						$result[$key]['add_way_info']   = $wayInfo;
						$result[$key]['add_way_title']  = $title;
						$audit                          = WorkMsgAuditInfo::find()->where(['external_id' => $val['wid']])->andWhere("find_in_set ('" . $val['userid'] . "',tolist)")->select('max(msgtime) as time')->asArray()->one();
						$chatTime                       = '--';
						if (!empty($audit['time'])) {
							$chatTime = date('Y-m-d H:i:s', $audit['time'] / 1000);
						}
						$result[$key]['chat_time']  = $chatTime;
						$result[$key]['follow_num'] = $val['follow_num'];
						if (!empty($val['is_reclaim']) || !empty($val['is_protect'])) {
							$result[$key]['claimTip'] = '';
							if (!empty($val['is_protect'])) {
								$isCancel = 1;
							}
						} else {
							$isTask = WaitTask::getTaskById(1, $val['external_userid']);
							if (!empty($isTask)) {
								$result[$key]['claimTip'] = '';
							} else {
								$result[$key]['claimTip'] = PublicSeaReclaimSet::getSeaRule($this->corp->id, $val['user_id'], ['follow_id' => $val['follow_id'], 'last_follow_time' => $val['update_time']]);
							}
						}
						$result[$key]['is_protect'] = (int)$val['is_protect'];
						$result[$key]['other_way']  = (int)$val['other_way'];
						$result[$key]['is_show']    = $isShow;
						$result[$key]['is_rest']    = $isRest;
						$result[$key]['is_reclaim'] = !empty($val['is_reclaim']) ? 0 : 1;//客户转交按钮是否显示
						$result[$key]['protect_str'] = '';
						if ($isMasterAccount != 1) {
							if (!empty($protectFollowData[$val['fid']])) {
								$followData = $protectFollowData[$val['fid']];
								if ($sub_id != $followData['sub_id'] && $val['user_id'] != $subUserId) {
									$nickname                    = $result[$key]['customerInfo']['name'];
									$result[$key]['protect_str'] = '【' . $nickname . '】已被【' . $followData['name'] . '】保护，您无法取消保护';
								}
							}
						}
					}
				}

				return [
					'count'          => $count,
					'uniqueCount'    => $uniqueCount,
					'info'           => $result,
					'keys'           => $externalIds,
					'tag_count'      => $userTagCount,
					'is_show'        => $isShow,
					'is_rest'        => $isRest,
					'is_cancel'      => $isCancel,
					'unshare_chat'   => $this->corp->unshare_chat,
					'unshare_follow' => $this->corp->unshare_follow,
					'unshare_line'   => $this->corp->unshare_line,
					'unshare_field'  => $this->corp->unshare_field,
					'is_hide_phone'  => $this->user->is_hide_phone,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           群发消息客户筛选
		 * @description     群发消息客户筛选
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/group-custom-list
		 *
		 * @param uid           可选 int uid
		 * @param suite_id      可选 int 应用ID（授权的必填）
		 * @param corp_id       必选 string 企业的唯一ID
		 * @param user_ids      可选 array 成员id
		 * @param name          可选 string 客户姓名、公司名称
		 * @param phone         可选 string 手机号、QQ号
		 * @param qq            可选 string QQ号
		 * @param company       可选 string 公司名称
		 * @param sex           可选 string 性别-1全部1男2女3未知
		 * @param fieldData     可选 array 自定义高级属性搜索
		 * @param fieldData     .field 可选 int 属性id
		 * @param fieldData     .match 可选 string 属性值
		 * @param work          可选 string 行业
		 * @param province      可选 string 区域-省
		 * @param city          可选 string 区域-市
		 * @param follow_status 可选 int 跟进状态-1全部0未跟进1跟进中2已拒绝3已成交
		 * @param follow_id     可选 int 跟进状态id
		 * @param tag_ids       可选 string 标签值（多标签用,分开）
		 * @param group_id      可选 array 标签分组
		 * @param tag_type      可选 int 标签1或2且
		 * @param page          可选 int 页码
		 * @param page_size     可选 int 每页数据量，默认15
		 * @param correctness   可选 int 1全部2按条件筛选
		 * @param start_time    可选 string 添加的开始时间
		 * @param end_time      可选 string 添加的结束时间
		 * @param update_time   可选 array 最后一次跟进时间
		 * @param follow_num1    可选 int 开始跟进次数
		 * @param follow_num2    可选 int 结束跟进次数
		 * @param chat_time    可选 array 上次单聊时间
		 * @param sign_id    可选 int 绑定客户
		 * @param chat_id    可选 array 群聊id
		 * @param is_fans   可选 int 是否是粉丝1是2否
		 * @param is_moment   可选 int 是否朋友圈1是0否
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/20 17:06
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \yii\db\Exception
		 */
		public function actionGroupCustomList ()
		{
			if (\Yii::$app->request->isPost) {
				$user_ids = \Yii::$app->request->post('user_ids');

				$name  = \Yii::$app->request->post('name', '');
				$phone = \Yii::$app->request->post('phone', '');
				$name  = trim($name);
				$phone = trim($phone);

				$uid             = \Yii::$app->request->post('uid', 2);
				$sex             = \Yii::$app->request->post('sex', 0);
				$work            = \Yii::$app->request->post('work', '');
				$province        = \Yii::$app->request->post('province', '');
				$city            = \Yii::$app->request->post('city', '');
				$follow_status   = \Yii::$app->request->post('follow_status', '-1');
				$follow_id       = \Yii::$app->request->post('follow_id', '-1');
				$fieldData       = \Yii::$app->request->post('fieldData', []);
				$tag_ids         = \Yii::$app->request->post('tag_ids', '');
				$group_id        = \Yii::$app->request->post('group_id', '');
				$tag_type        = \Yii::$app->request->post('tag_type', 1);
				$belong_id       = \Yii::$app->request->post('belong_id', 0);
				$start_time      = \Yii::$app->request->post('start_time');
				$end_time        = \Yii::$app->request->post('end_time');
				$correctness     = \Yii::$app->request->post('correctness') ?: 2;//1全部 2条件
				$update_time     = \Yii::$app->request->post('update_time');
				$follow_num1     = \Yii::$app->request->post('follow_num1');
				$follow_num2     = \Yii::$app->request->post('follow_num2');
				$chat_time       = \Yii::$app->request->post('chat_time');
				$sign_id         = \Yii::$app->request->post('sign_id');
				$chat_id         = \Yii::$app->request->post('chat_id');
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$is_fans         = \Yii::$app->request->post('is_fans');
				$is_moment       = \Yii::$app->request->post('is_moment',0);

				if (empty($this->corp) || empty($uid)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 1, true);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				$data['corp_id']         = $this->corp['id'];
				$data['isMasterAccount'] = $isMasterAccount;
				$data['sub_id']          = $sub_id;
				$data['name']            = $name;
				$data['phone']           = $phone;
				$data['sex']             = $sex;
				$data['work']            = $work;
				$data['province']        = $province;
				$data['city']            = $city;
				$data['follow_status']   = $follow_status;
				$data['follow_id']       = $follow_id;
				$data['fieldData']       = $fieldData;
				$data['tag_ids']         = $tag_ids;
				$data['group_id']        = $group_id;
				$data['tag_type']        = $tag_type;
				$data['start_time']      = $start_time;
				$data['end_time']        = $end_time;
				$data['correctness']     = $correctness;
				$data['update_time']     = $update_time;
				$data['follow_num1']     = $follow_num1;
				$data['follow_num2']     = $follow_num2;
				$data['chat_time']       = $chat_time;
				$data['sign_id']         = $sign_id;
				$data['chat_id']         = $chat_id;
				$data['user_ids']        = $user_ids;
				$data['belong_id']       = $belong_id;
				$data['is_fans']         = $is_fans;
				$data['uid']             = $this->user->uid;
				$data['is_moment']       = $is_moment;


				$info     = WorkExternalContactFollowUser::getData($data);
				$result   = $info['result'];
				$real_num = $info['real_num'];

				return [
					'info'     => $result,
					'count'    => count($result),
					'real_num' => $real_num,
				];


			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           外部联系人详情
		 * @description     外部联系人详情
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-detail
		 *
		 * @param uid  必选 int 用户ID
		 * @param cid  必选 int 客户ID
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 名称
		 * @return_param    gender string 性别
		 * @return_param    nickname string 设置的昵称
		 * @return_param    avatar string 头像
		 * @return_param    des string 描述
		 * @return_param    close_rate int 预计成交率
		 * @return_param    follow_time string 上次跟进时间
		 * @return_param    follow_num int 跟进次数
		 * @return_param    follow_status string 跟进状态：0未跟进1跟进中2已拒绝3已成交
		 * @return_param    phone string 手机号
		 * @return_param    area string 区域
		 * @return_param    memberInfo array 归属企业成员
		 * @return_param    memberInfo.member string 企业成员姓名
		 * @return_param    memberInfo.create_time string 归属时间
		 * @return_param    memberInfo.del_type int 删除类型
		 * @return_param    memberInfo.source string 渠道
		 * @return_param    tag_name array 标签
		 * @return_param    tag_name.tid int 标签id
		 * @return_param    tag_name.tname int 标签名称
		 * @return_param    field_list array 客户属性
		 * @return_param    field_list.fieldid int 属性ID
		 * @return_param    field_list.key string 属性key
		 * @return_param    field_list.title string 属性名称
		 * @return_param    field_list.type int 属性类型
		 * @return_param    field_list.optionVal string 属性选项
		 * @return_param    field_list.value string 已设置属性值
		 * @return_param    project array 待办项目
		 * @return_param    project.start_time string 项目开始时间
		 * @return_param    project.end_time string 项目结束时间
		 * @return_param    project.finish_time string 项目完成时间
		 * @return_param    project.name string 项目处理人
		 * @return_param    project.days string 项目完成天数
		 * @return_param    project.project_name string 项目名称
		 * @return_param    project.delay_days string 超时天数
		 * @return_param    project.pre_days string 提前天数
		 * @return_param    project.is_finish string 完成状态、0未完成1按时完成2超时完成3提前完成
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$uid = \Yii::$app->request->post('uid', 0);
				$cid = \Yii::$app->request->post('cid', 0);
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				if (empty($uid) || empty($cid)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$userInfo = UserProfile::findOne(['uid' => $uid]);

				$result           = [];
				$external_userid  = $cid;
				$follow           = WorkExternalContactFollowUser::findOne($cid);

				if (empty($follow)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpRelationList = $this->user->userCorpRelations;
				if (empty($corpRelationList)) {
					throw new InvalidParameterException('参数不正确！');
				}

				$corpId = $follow->user->corp_id;
				$isOwner = false;
				/** @var UserCorpRelation $corpRelation */
				foreach ($corpRelationList as $corpRelation) {
					if ($corpRelation->corp_id == $corpId) {
						$isOwner = true;

						break;
					}
				}

				if (!$isOwner) {
					throw new InvalidParameterException('参数不正确！');
				}

				$externalUserData = WorkExternalContact::findOne($follow->external_userid);
				$workCorp         = WorkCorp::findOne($externalUserData->corp_id);
				$cid              = $follow->external_userid;
				if ($isMasterAccount == 2) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $workCorp->id);
					$unShare    = 1;
					if (!empty($sub_detail)) {
						if (is_array($sub_detail)) {
							array_push($sub_detail, 0);
						} else {
							$unShare = 0;
						}
					} else {
						$sub_detail = 0;
					}
				}

				if ($externalUserData->gender == 0) {
					$gender = '未知';
				} elseif ($externalUserData->gender == 1) {
					$gender = '男性';
				} elseif ($externalUserData->gender == 2) {
					$gender = '女性';
				}
				$desc    = '';
				$mediaId = '';
				if (!empty($follow->des)) {
					$desc = $follow->des;
				}
				$result['avatar']        = $externalUserData->avatar;
				$result['name']          = !empty($externalUserData->name) ? rawurldecode($externalUserData->name) : '';
				$result['corp_name']     = $externalUserData->corp_name;
				$nickname                = !empty($follow->nickname) ? $follow->nickname : $follow->remark;
				$result['nickname']      = $nickname;
				$result['des']           = $desc;
				$result['external_id']   = $follow->id;
				$result['close_rate']    = !empty($follow->close_rate) ? $follow->close_rate : NULL;
				$result['follow_status'] = $externalUserData->follow_status ? $externalUserData->follow_status : 0;//跟进状态
				if (!empty($follow->follow_id)) {
					$follow_id     = $follow->follow_id;
					$foll          = Follow::findOne($follow_id);
					$follow_title  = $foll->title;
					$is_follow_del = 0;
					//$follow_title = $externalUserData->follow->title;
					if ($foll->status == 0) {
						$follow_title  .= '（已删除）';
						$is_follow_del = 1;
					}
					$result['follow_id']     = $follow_id;
					$result['follow_title']  = $follow_title;
					$result['is_follow_del'] = $is_follow_del;
				} else {
					$result['follow_id']     = '';
					$result['follow_title']  = '';
					$result['is_follow_del'] = 0;
				}

				//跟进信息
				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $cid, 'type' => 1, 'status' => 1]);
				//不共享跟进记录
				if ($isMasterAccount == 2 && $workCorp->unshare_follow == 1 && $unShare){
					$followRecord = $followRecord->andWhere(['user_id' => $sub_detail]);
				}
				$followRecord = $followRecord->select('`sub_id`,`user_id`,`time`')->orderBy('id DESC')->asArray()->all();

				//$result['follow_num']  = $follow->follow_num;//跟进次数
				$result['follow_time'] = '';//上次跟进时间
				if (!empty($followRecord)) {
					if (!empty($followRecord[0]['user_id'])) {
						$userInfo = WorkUser::findOne($followRecord[0]['user_id']);
						$name     = $userInfo->name;
					} elseif (!empty($followRecord[0]['sub_id'])) {
						$subInfo = SubUserProfile::findOne(['sub_user_id' => $followRecord[0]['sub_id']]);
						$name    = $subInfo->name;
					} else {
						$name = $userInfo->nick_name;
					}

					$time                  = !empty($followRecord[0]['time']) ? date('Y-m-d H:i:s', $followRecord[0]['time']) : '';
					$result['follow_time'] = $name . ' ' . $time;
				}

				//归属企业成员
				$workExternal = WorkExternalContactFollowUser::find()->andWhere(['external_userid' => $externalUserData->id]);
				//不共享跟进记录
				if ($isMasterAccount == 2 && $workCorp->unshare_follow == 1 && $unShare){
					$workExternal = $workExternal->andWhere(['user_id' => $sub_detail]);
				}
				$workExternal = $workExternal->all();
				$memberInfo   = [];
				$folNum       = [];
				$num          = 0;
				$i            = 0;
				/**
				 * @var int                           $k
				 * @var WorkExternalContactFollowUser $user
				 */
				foreach ($workExternal as $k => $user) {
					$work_user  = WorkUser::findOne($user->user_id);
					$departName = WorkDepartment::getDepartNameByUserId($work_user->department, $work_user->corp_id);
					$member     = $work_user->name . '--' . $departName;

					if (empty($this->subUser) || $this->subUser->account == $work_user->mobile) {
						$member .= !empty($user->nickname) ? "（备注：" . $user->nickname . "）" : ((!empty($user->remark) && $user->remark != $externalUserData->name_convert) ? "（备注：" . $user->remark . "）" : "");
					}
					if (!empty($user['follow_num'])) {
						$folNum[$i]['name']       = $work_user->name;
						$folNum[$i]['follow_num'] = $user['follow_num'];
						$i++;
					}
					$num                           += $user['follow_num'];
					$memberInfo[$k]['member']      = $member;
					$memberInfo[$k]['del_type']    = $user->del_type;
					$memberInfo[$k]['source']      = $user->state;
					$memberInfo[$k]['create_time'] = !empty($user->createtime) ? date("Y-m-d H:i:s", $user->createtime) : '';
				}
				$result['follow_num']   = $num;
				$result['follow_times'] = $folNum;
				$result['memberInfo']   = $memberInfo;
				//用户标签
				$contactTag = WorkTagFollowUser::find()->alias('w');
				$contactTag = $contactTag->leftJoin('{{%work_tag}} t', '`t`.`id` = `w`.`tag_id`')->andWhere(['w.follow_user_id' => $follow->id, 'w.status' => 1, 't.is_del' => 0, 'w.corp_id' => $externalUserData->corp_id])->asArray()->all();
				$tagName    = [];
				foreach ($contactTag as $k => $v) {
					$workTag           = WorkTag::findOne($v['tag_id']);
					$workTagD          = [];
					$workTagD['tid']   = (string)$workTag['id'];
					$workTagD['tname'] = $workTag['tagname'];
					$tagName[]         = $workTagD;
				}
				$result['tag_name'] = $tagName;

				//获取所在群名称
				if ($isMasterAccount == 2 && $workCorp->unshare_chat == 1 && $unShare){
					$result['chat_name'] = WorkChatInfo::getChatList(2, $cid, '', $workCorp->unshare_chat, $sub_detail);
				}else{
					$result['chat_name'] = WorkChatInfo::getChatList(2, $cid);
				}

				//自定义属性
				$fieldList = CustomField::getCustomField($uid, $cid, 1, $workCorp->unshare_field, $follow->user_id);
				$phone     = '';
				$area      = '';
				$hasPhone  = 0;
				$hasArea   = 0;
				foreach ($fieldList as $k => $v) {
					if ($v['key'] == 'phone') {
						$hasPhone = 1;
						$phone    = $v['value'];
					} elseif ($v['key'] == 'area') {
						$hasArea = 1;
						$area    = $v['value'];
					} elseif ($v['key'] == 'sex') {
						if ($v['value'] == '男') {
							$gender = '男性';
						} elseif ($v['value'] == '女') {
							$gender = '女性';
						} elseif ($v['value'] == '未知') {
							$gender = '未知';
						}
					}
				}
//				if ($hasPhone == 0) {
//					$phone = '';
//					//$fieldValue = CustomFieldValue::find()->alias('fv')->leftJoin('{{%custom_field}} cf', 'fv.fieldid=cf.id and cf.key=\'phone\'')->andWhere(['fv.type' => 1, 'fv.cid' => $cid])->select('fv.value')->one();
//					$customField = CustomField::findOne(['key' => 'phone']);
//					if(!empty($customField)){
//						$fieldValue = CustomFieldValue::findOne(['type' => 1, 'fieldid' => $customField->id, 'cid' => $cid]);
//						$phone      = !empty($fieldValue->value) ? $fieldValue->value : '';
//					}
//				}
				if ($hasArea == 0) {
					$area        = '';
					$customField = CustomField::findOne(['key' => 'area', 'is_define' => 0]);
					if (!empty($customField)) {
						if ($workCorp->unshare_field == 0) {
							$fieldValue = CustomFieldValue::findOne(['type' => 1, 'fieldid' => $customField->id, 'cid' => $cid]);
						} else {
							$fieldValue = CustomFieldValue::find()->where(['type' => 1, 'fieldid' => $customField->id, 'cid' => $cid])->andWhere(['user_id' => [0, $follow->user_id]])->orderBy(['user_id' => SORT_DESC])->one();
						}
						$area = !empty($fieldValue->value) ? $fieldValue->value : '';
					}
				}
				//$result['phone']   = !empty($follow->remark_mobiles) ? $follow->remark_mobiles : '';
				$result['phone']   = !empty($phone) ? $phone : $follow->remark_mobiles;
				$is_hide_phone = $this->user->is_hide_phone;
				if ($is_hide_phone){
					$result['phone'] = '';
				}
				$result['is_hide_phone'] = $is_hide_phone;
				$result['company'] = !empty($follow->remark_corp_name) ? $follow->remark_corp_name : '';
				$result['area']    = $area;
				$result['gender']  = $gender;

				$result['field_list'] = $fieldList;
				$project              = WaitCustomerTask::getDetail(2, $follow->external_userid, $uid, $follow->follow_id);
				$result['project']    = $project;

				return $result;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           修改外部联系人字段
		 * @description     修改外部联系人字段
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-update
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid    必选 int 客户ID
		 * @param type   必选 string 修改类型：nickname昵称、des描述、close_rate预计成交率
		 * @param value  可选 string 修改值
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$uid             = \Yii::$app->request->post('uid', 0);
				$cid             = \Yii::$app->request->post('cid', 0);
				$type            = \Yii::$app->request->post('type', '');
				$value           = \Yii::$app->request->post('value', '');
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);

				if (empty($cid) || empty($type)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($isMasterAccount == 1) {
					$sub_id = 0;
				}
				$followUser = WorkExternalContactFollowUser::findOne($cid);
				if (!empty($followUser)) {
					$remark = '';
					switch ($type) {
						case 'nickname':
							$contact = WorkExternalContact::findOne($followUser->external_userid);
							if (empty($value)) {
								$value = $contact->name_convert;
							}
							$followUser->nickname = $value;
							$remark               = '备注名';
							$workApi              = '';
							try {
								$workApi = WorkUtils::getWorkApi($contact->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}

							if ($workApi instanceof Work) {
								$sendData['userid']          = $followUser->userid;
								$sendData['external_userid'] = $contact->external_userid;
								$sendData['remark']          = $followUser->nickname;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'CustomUpdate1');
								\Yii::error($sendData, '$sendData0');
							}
							break;
						case 'des':
							$desc            = $value;
							$followUser->des = $value;
							$contact         = WorkExternalContact::findOne($followUser->external_userid);
							$workApi         = '';
							try {
								$workApi = WorkUtils::getWorkApi($contact->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}

							if ($workApi instanceof Work) {
								$sendData['userid']          = $followUser->userid;
								$sendData['external_userid'] = $contact->external_userid;
								$sendData['description']     = $desc;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'CustomUpdate2');
								\Yii::error($sendData, '$sendData');
							}

							$remark = '描述';
							break;
						case 'close_rate':
							if ($value < 0 || $value > 100) {
								throw new InvalidParameterException('预计成交率不正确！');
							}
							$followUser->close_rate = $value;
							$remark                 = '预计成交率';
							break;
					}
					if (!$followUser->save()) {
						throw new InvalidParameterException(SUtils::modelError($followUser));
					}

					//记录客户轨迹
					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'sub_id' => $sub_id, 'external_id' => $followUser->external_userid, 'event' => 'set_field', 'remark' => $remark]);

					return true;
				}

			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           修改外部联系人高级属性
		 * @description     修改外部联系人高级属性
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-field-update
		 *
		 * @param uid         必选 int 用户ID
		 * @param cid         必选 int 客户ID
		 * @param fieldData   必选 array 客户属性
		 * @param fieldData   .fieldid  必选 int 属性ID
		 * @param fieldData   .value    可选 int 属性值
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/04/13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFieldUpdate ()
		{
			if (\Yii::$app->request->isPost) {
				$uid       = \Yii::$app->request->post('uid', 0);
				$cid       = \Yii::$app->request->post('cid', 0);
				$fieldData = \Yii::$app->request->post('fieldData', []);
				if (empty($uid) || empty($cid) || empty($fieldData)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$follow           = WorkExternalContactFollowUser::findOne($cid);
				$cid              = $follow->external_userid;
				$externalUserData = WorkExternalContact::findOne($cid);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$workCorp = WorkCorp::findOne($externalUserData->corp_id);

				$time     = time();
				$uptField = '';
				foreach ($fieldData as $k => $v) {
					$fieldid = intval($v['fieldid']);
					$value   = is_array($v['value']) ? $v['value'] : trim($v['value']);
					if (empty($fieldid)) {
						throw new InvalidParameterException('客户高级属性数据错误！');
					}
					if ($fieldid == 3) {
						$gender = 0;
						if ($v['value'] == '男') {
							$gender = 1;
						} elseif ($v['value'] == '女') {
							$gender = 2;
						}
						WorkContactWayLine::updateAll(['gender' => $gender], ['external_userid' => $cid]);
					}
					if ($workCorp->unshare_field == 0){
						$fieldValue = CustomFieldValue::findOne(['cid' => $cid, 'type' => 1, 'fieldid' => $fieldid]);
					}else{
						$fieldValue = CustomFieldValue::findOne(['cid' => $cid, 'type' => 1, 'fieldid' => $fieldid, 'user_id' => $follow->user_id]);
					}

					if (empty($fieldValue)) {
						if (empty($value)) {
							continue;
						}
						$fieldValue          = new CustomFieldValue();
						$fieldValue->type    = 1;
						$fieldValue->uid     = $uid;
						$fieldValue->cid     = $cid;
						$fieldValue->fieldid = $fieldid;
						$fieldValue->value   = '';
						if ($workCorp->unshare_field == 1) {
							$fieldValue->user_id = $follow->user_id;
						}
					} else {
						if (($value == $fieldValue->value) && !in_array($v['key'], ['company', 'phone'])) {
							continue;
						}
					}
					if ($v['type'] == 8) {
						$imgVal = json_decode($fieldValue->value, true);
						if ($imgVal == $value) {
							continue;
						}
						$value = json_encode($value);
					}
					if ($v['key'] == 'company') {
						if (($follow->remark_corp_name == $value) && ($fieldValue->value == $value)) {
							continue;
						}
						//公司
						if($follow->remark_corp_name != $value){
							$contact = WorkExternalContact::findOne($follow->external_userid);
							$workApi = '';
							try {
								$workApi = WorkUtils::getWorkApi($contact->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}
							if ($workApi instanceof Work) {
								$sendData['userid']          = $follow->userid;
								$sendData['external_userid'] = $contact->external_userid;
								$sendData['remark_company']  = $value;
								$contactRemark               = ExternalContactRemark::parseFromArray($sendData);
								$data                        = $workApi->ECRemark($contactRemark);
								$result                      = SUtils::Object2Array($data);
								\Yii::error($result, 'CustomUpdate3');
							}
							$follow->remark_corp_name = $value;
						}
					}

					$fieldValue->uid  = $uid;
					$fieldValue->time = $time;

					if ($v['type'] == 5) {
//						if (!is_array($value)) {
//							throw new InvalidParameterException('手机号参数格式不正确！');
//						}
						if (!empty($value)) {
							$phones = explode(',', $value);
						} else {
							$phones = '';
						}
						if (($follow->remark_mobiles == $value) && ($fieldValue->value == $value)) {
							continue;
						}
//						foreach ($phones as $phone) {
//							if (strlen($phone) == 11 && !preg_match("/^1[34578]{1}\d{9}$/", $phone)) {
//								throw new InvalidParameterException('手机号格式不正确！');
//							}
//						}
						if ($follow->remark_mobiles != $value) {
							$contact = WorkExternalContact::findOne($follow->external_userid);
							$workApi = '';
							try {
								$workApi = WorkUtils::getWorkApi($contact->corp_id, WorkUtils::EXTERNAL_API);
							} catch (\Exception $e) {
								\Yii::error($e->getMessage(), 'getMessage');
							}
							if ($workApi instanceof Work) {
								\Yii::error($phones, '$phones');
								$sendData['userid']          = $follow->userid;
								$sendData['external_userid'] = $contact->external_userid;
								$sendData['remark_mobiles']  = $phones;
								try {
									$contactRemark = ExternalContactRemark::parseFromArray($sendData);
									$data          = $workApi->ECRemark($contactRemark);
									$result        = SUtils::Object2Array($data);
									\Yii::error($result, 'CustomUpdate4');
								} catch (\Exception $e) {
									$message = $e->getMessage();
									if (strpos($message, '60103') !== false) {
										$message = '手机号格式不正确';
									}
									throw new InvalidParameterException($message);
								}
							}
							$follow->remark_mobiles = $value;
						}
					} elseif ($v['type'] == 6 && !empty($value)) {
						if (!preg_match("/^\w[-\w.+]*@([A-Za-z0-9][-A-Za-z0-9]+\.)+[A-Za-z]{2,14}$/", $value)) {
							throw new InvalidParameterException('邮箱格式不正确！');
						}
					}

					$fieldValue->value = $value;
					if (!$fieldValue->save()) {
						throw new InvalidParameterException(SUtils::modelError($fieldValue));
					}
					$uptField .= $fieldid . ',';
				}
				$follow->save();
				//记录客户轨迹
				if (!empty($uptField)) {
					$customField = CustomField::find()->where('id IN (' . trim($uptField, ',') . ')')->select('`title`')->asArray()->all();
					$remark      = '';
					foreach ($customField as $v) {
						$remark .= $v['title'] . '、';
					}
					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => $cid, 'user_id' => $follow->user_id, 'event' => 'set_field', 'remark' => substr($remark, 0, -1)]);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           客户跟进记录
		 * @description     客户跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-follow-record
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 客户ID
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
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
		 * @return_param    followRecord.can_edit int 是否可编辑1是0否
		 * @return_param    followRecord.file array 附件图片
		 * @return_param    followRecord.follow_status 跟进状态
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/4/14
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFollowRecord ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$uid             = \Yii::$app->request->post('uid', 0);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$cid             = \Yii::$app->request->post('cid', 0);
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($uid) || empty($cid) || empty($sub_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$sub_id = $isMasterAccount == 1 ? 0 : $sub_id;
				$offset = ($page - 1) * $pageSize;

				$userInfo     = UserProfile::findOne(['uid' => $uid]);
				$follow       = WorkExternalContactFollowUser::findOne($cid);
				$cid          = $follow->external_userid;
				$userId       = $follow->user_id;
				$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $cid, 'type' => 1, 'status' => 1]);
				if ($isMasterAccount == 2 && $this->corp->unshare_follow == 1) {
					//$followRecord = $followRecord->andWhere(['sub_id'=>$sub_id]);
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail){
						if (is_array($sub_detail)){
							array_push($sub_detail, 0);
							$followRecord = $followRecord->andWhere(['user_id' => $sub_detail]);
						}
					}else{
						$followRecord = $followRecord->andWhere(['user_id' => 0]);
					}
				}
				$count = $followRecord->count();

				$followRecord = $followRecord->limit($pageSize)->offset($offset)->select('`id`,`sub_id`,`user_id`,`record`,`file`,`time`,`follow_id`,`is_master`')->orderBy(['id' => SORT_DESC]);
				$followRecord = $followRecord->asArray()->all();
				$name         = '';
				foreach ($followRecord as $k => $v) {
					$can_edit = 0;
					if (!empty($v['user_id']) && $v['is_master'] == 1) {
						$workUser = WorkUser::findOne($v['user_id']);
						if (!empty($workUser)) {
							$name = $workUser->name;
						}
					} elseif (!empty($v['sub_id']) && $v['is_master'] == 1) {
						$subInfo = SubUserProfile::findOne(['sub_user_id' => $v['sub_id']]);
						if (!empty($subInfo)) {
							$name = $subInfo->name;
						}
						$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
					} else {
						if (!empty($userInfo)) {
							$name = $userInfo->nick_name;
						}
						$can_edit = $sub_id == $v['sub_id'] ? 1 : 0;
					}
					if ($isMasterAccount == 2 && ($sub_id == $v['sub_id'] || $userId == $v['user_id']) && $v['is_master'] == 1) {
						$can_edit = 1;
					}
					$followRecord[$k]['name']     = $name;
					$followRecord[$k]['time']     = !empty($v['time']) ? date('Y-m-d H:i:s', $v['time']) : '';
					$followRecord[$k]['file']     = !empty($v['file']) ? json_decode($v['file']) : [];
					$followRecord[$k]['can_edit'] = $can_edit;
					$follow_status                = '';
					if ($v['follow_id']) {
						$follow        = Follow::findOne($v['follow_id']);
						$follow_status = $follow->title;
						if ($follow->status == 0) {
							$follow_status .= '（已删除）';
						}
					}
					$followRecord[$k]['follow_status'] = $follow_status;
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
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           添加客户跟进记录
		 * @description     添加客户跟进记录
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-follow-record-set
		 *
		 * @param corp_id  必选 int 企业微信id
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 客户ID
		 * @param follow_id        必选 int 跟进状态id
		 * @param record_id        可选 int 记录ID
		 * @param record           可选 string 记录内容
		 * @param file             可选 array 图片附件链接
		 *
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/4/14
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionCustomFollowRecordSet ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$uid             = \Yii::$app->request->post('uid', 0);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$follow_id       = \Yii::$app->request->post('follow_id', 0);
				$cid             = \Yii::$app->request->post('cid', 0);
				$record_id       = \Yii::$app->request->post('record_id', 0);
				$record          = \Yii::$app->request->post('record', '');
				$file            = \Yii::$app->request->post('file', '');
				$close_rate      = \Yii::$app->request->post('close_rate', '-1');
				$tag_ids         = \Yii::$app->request->post('tag_ids', '');
				$record          = trim($record);
				if (empty($uid) || empty($cid) || empty($sub_id) || empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($follow_id)) {
					throw new InvalidParameterException('请选择跟进状态！');
				}
				if (empty($record) && empty($file)) {
					throw new InvalidParameterException('跟进内容和附件至少要填写一个！');
				}
				$follow           = WorkExternalContactFollowUser::findOne($cid);
				$oldFollowId      = $follow->follow_id;
				$cid              = $follow->external_userid;
				$externalUserData = WorkExternalContact::findOne($cid);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}
				$followInfo = Follow::findOne(['id' => $follow_id, 'status' => 1]);
				if (empty($followInfo)) {
					throw new InvalidParameterException('跟进状态已被删除，请更换！');
				}
				//更新跟进状态
				//$externalUserData->follow_status = $follow_status;
				$externalUserData->follow_id = $follow_id;
				if (!$externalUserData->save()) {
					throw new InvalidParameterException(SUtils::modelError($externalUserData));
				}
				$userId = 0;
				//子账户
				if ($isMasterAccount == 2) {
					$subUser = SubUser::findOne($sub_id);
					if (!empty($subUser)) {
						$workUser = WorkUser::findOne(['corp_id' => $this->corp->id, 'mobile' => $subUser->account]);
						if (!empty($workUser)) {
							$userId = $workUser->id;
							if (!empty($follow)) {
								$follow->is_chat     = WorkExternalContactFollowUser::HAS_CHAT;
								$follow->follow_id   = $follow_id;
								$follow->update_time = time();
								$follow_num          = $follow->follow_num;
								$follow->follow_num  = $follow_num + 1;
								if ($close_rate != '-1'){
									$follow->close_rate  = $close_rate;
								}
								$follow->save();
							}
						}
					}
				} else {
					$follow->is_chat     = WorkExternalContactFollowUser::HAS_CHAT;
					$userId              = $follow->user_id;
					$follow->follow_id   = $follow_id;
					$follow->update_time = time();
					$follow_num          = $follow->follow_num;
					$follow->follow_num  = $follow_num + 1;
					if ($close_rate != '-1'){
						$follow->close_rate  = $close_rate;
					}
					$follow->save();
				}

				if (empty($record_id)) {
					$followRecord              = new WorkExternalContactFollowRecord();
					$followRecord->uid         = $uid;
					$followRecord->type        = 1;
					$followRecord->external_id = $cid;
					$followRecord->user_id     = $userId;
					$followRecord->status      = 1;
					$followRecord->sub_id      = $isMasterAccount == 1 ? 0 : $sub_id;
					$followRecord->time        = time();
					$followRecord->is_master   = $isMasterAccount == 1 ? 0 : 1;
				} else {
					$followRecord           = WorkExternalContactFollowRecord::findOne($record_id);
					$followRecord->upt_time = time();
				}
				$followRecord->record    = $record;
				$followRecord->file      = !empty($file) ? json_encode($file) : '';
				$followRecord->follow_id = $follow_id;

				if (!$followRecord->save()) {
					throw new InvalidParameterException(SUtils::modelError($followRecord));
				}
				if (!empty($tag_ids)) {
					WorkTag::addUserTag(2, [$follow->id], $tag_ids);
				}

				//记录客户轨迹
				if (empty($record_id)) {
					$count = WorkExternalContactFollowRecord::find()->where(['external_id' => $cid, 'type' => 1, 'status' => 1])->count();//跟进次数
					ExternalTimeLine::addExternalTimeLine(['uid' => $uid, 'external_id' => $cid, 'user_id' => $userId, 'sub_id' => $followRecord->sub_id, 'event' => 'follow', 'event_id' => $follow_id, 'related_id' => $followRecord->id, 'remark' => $count]);
				}

				if ($oldFollowId > 0 && $oldFollowId != $follow_id) {
					$waitTask = WaitTask::find()->alias('w')->leftJoin('{{%wait_project}} p', 'w.project_id=p.id')->where(['w.follow_id' => $follow_id, 'p.is_del' => 0, 'w.is_del' => 0])->all();
					if (!empty($waitTask)) {
						$jobId = \Yii::$app->queue->push(new WaitUserTaskJob([
							'followUserId' => $follow->id,
							'followId'     => $follow_id,
							'type'         => 3,
							'corpId'       => $this->corp->id,
							'daysNew'      => 0,
						]));
					}
					WaitCustomerTask::deleteData($externalUserData->id, '', $oldFollowId);
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           客户互动轨迹
		 * @description     客户互动轨迹
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-track
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param uid              必选 int 用户ID
		 * @param sub_id           必选 int 子账户ID
		 * @param cid              必选 int 客户ID
		 * @param page             可选 int 页码
		 * @param page_size        可选 int 每页数据量，默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    event_time string 时间
		 * @return_param    content string 内容
		 * @return_param    icon int 图标
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/4/16
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionCustomTrack ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$uid             = \Yii::$app->request->post('uid', 0);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$cid             = \Yii::$app->request->post('cid', 0);
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('page_size', 15);

				if (empty($uid) || empty($cid) || empty($sub_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$offset           = ($page - 1) * $pageSize;
				$follow           = WorkExternalContactFollowUser::findOne($cid);
				$cid              = $follow->external_userid;
				$externalUserData = WorkExternalContact::findOne($cid);
				if (empty($externalUserData)) {
					throw new InvalidParameterException('客户数据错误！');
				}

				$external_line = ExternalTimeLine::find()->where(['external_id' => $cid]);
				if ($isMasterAccount == 2 && $this->corp->unshare_line == 1) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail) {
						if (is_array($sub_detail)){
							array_push($sub_detail, 0);
							$external_line = $external_line->andWhere(['user_id' => $sub_detail]);
						}
					} else {
						$external_line = $external_line->andWhere(['user_id' => 0]);
					}
				}
				$external_line = $external_line->andWhere(['!=', 'event', 'chat_track_money']);
				$external_line = $external_line->limit($pageSize)->offset($offset)->orderBy(['event_time' => SORT_DESC])->asArray()->all();

				return ExternalTimeLine::getExternalTimeLine($uid, $external_line);
			} else {
				throw new InvalidParameterException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           设置共享配置项
		 * @description     设置共享配置项
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/set-share-data
		 *
		 * @param corp_id           必选 string 企业的唯一ID
		 * @param unshare_chat      必选 int 不共享所在群1是0否
		 * @param unshare_follow    必选 int 不共享跟进记录1是0否
		 * @param unshare_line      必选 int 不共享互动轨迹1是0否
		 * @param unshare_field     必选 int 不共享客户画像1是0否
		 * @param is_hide_phone     必选 int 手机号隐藏1是0否
		 *
		 * @return          {"error":0,"data":{}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/11/13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSetShareData ()
		{
			if (\Yii::$app->request->isPost) {
				$corpId         = \Yii::$app->request->post('corp_id');
				$unshare_chat   = \Yii::$app->request->post('unshare_chat', 0);
				$unshare_follow = \Yii::$app->request->post('unshare_follow', 0);
				$unshare_line   = \Yii::$app->request->post('unshare_line', 0);
				$unshare_field  = \Yii::$app->request->post('unshare_field', 0);
				$is_hide_phone  = \Yii::$app->request->post('is_hide_phone', 0);

				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if ($this->corp->unshare_field == 1 && $unshare_field == 0) {
					throw new InvalidParameterException('客户画像设置不共享后不能修改！');
				}

				$this->corp->unshare_chat   = $unshare_chat;
				$this->corp->unshare_follow = $unshare_follow;
				$this->corp->unshare_line   = $unshare_line;
				$this->corp->unshare_field  = $unshare_field;

				if (!$this->corp->save()) {
					throw new InvalidParameterException(SUtils::modelError($this->corp));
				}

				$this->user->is_hide_phone = $is_hide_phone;
				if (!$this->user->save()) {
					throw new InvalidParameterException(SUtils::modelError($this->user));
				}

				return true;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新客户状态
		 * @description     批量更新客户状态
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-data
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/4/28 15:25
		 * @number          0
		 *
		 */
		public function actionUpdateData ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$followUser = WorkExternalContactFollowUser::find()->all();
			if (!empty($followUser)) {
				foreach ($followUser as $user) {
					$user->delete_type = $user->del_type;
					$user->save();
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新客户的跟进状态
		 * @description     批量更新客户的跟进状态
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-follow-id
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/17 10:06
		 * @number          0
		 *
		 */
		public function actionUpdateFollowId ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$data            = [];
			$externalContact = WorkExternalContact::find()->where(['!=', 'corp_id', 1])->select('id,follow_id')->all();
			/** @var WorkExternalContact $contact */
			foreach ($externalContact as $contact) {
				$data[$contact->id] = $contact->follow_id;
			}
			$followUser = WorkExternalContactFollowUser::find()->where(['follow_id' => 0])->select('id,follow_id,external_userid')->all();
			if (!empty($followUser)) {
				/** @var WorkExternalContactFollowUser $user */
				foreach ($followUser as $user) {
					if (isset($data[$user->external_userid]) && !empty($data[$user->external_userid])) {
						$user->follow_id = $data[$user->external_userid];
						$user->save();
					}
				}
			}
			echo '更新完成';
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新客户的最后一次跟进时间
		 * @description     批量更新客户的最后一次跟进时间
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-time
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/17 14:41
		 * @number          0
		 *
		 */
		public function actionUpdateTime ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$record     = WorkExternalContactFollowRecord::find()->where(['or', ['>', 'user_id', '0'], ['>', 'sub_id', '0']])->andWhere(['!=', 'uid', 2])->select('external_id')->groupBy('external_id')->asArray()->all();
			$externalId = array_column($record, 'external_id');
			if (!empty($externalId)) {
				foreach ($externalId as $external) {
					$followRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $external])->andWhere(['>', 'sub_id', '0'])->orderBy(['time' => SORT_DESC])->one();
					/** @var WorkExternalContactFollowRecord $followRecord */
					if (!empty($followRecord)) {
						$uid      = $followRecord->uid;
						$relation = UserCorpRelation::find()->where(['uid' => $uid])->all();
						if (!empty($relation)) {
							/** @var UserCorpRelation $relat */
							foreach ($relation as $relat) {
								$subUser  = SubUser::findOne($followRecord->sub_id);
								$workUser = WorkUser::findOne(['corp_id' => $relat->corp_id, 'mobile' => $subUser->account]);
								if (!empty($workUser)) {
									$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $external, 'user_id' => $workUser->id]);
									if (!empty($followUser)) {
										if (!empty($followRecord->follow_id)) {
											$followUser->follow_id = $followRecord->follow_id;
										}
										$followUser->update_time = $followRecord->time;
										$followUser->is_chat     = 1;
										$followUser->save();
									}
								}
							}
						}

					}
					$followUserRecord = WorkExternalContactFollowRecord::find()->where(['external_id' => $external])->andWhere(['>', 'user_id', '0'])->orderBy(['time' => SORT_DESC])->groupBy('user_id')->all();
					/** @var WorkExternalContactFollowRecord $followUserRecord */
					if (!empty($followUserRecord)) {
						/** @var WorkExternalContactFollowRecord $rec */
						foreach ($followUserRecord as $rec) {
							$uid      = $rec->uid;
							$relation = UserCorpRelation::findOne(['uid' => $uid]);
							if (!empty($relation)) {
								$workUser = WorkUser::findOne($rec->user_id);
								if (!empty($workUser)) {
									$followUser = WorkExternalContactFollowUser::findOne(['external_userid' => $external, 'user_id' => $workUser->id]);
									if (!empty($followUser)) {
										if ($followUser->update_time == 0) {
											if (!empty($rec->follow_id)) {
												$followUser->follow_id = $rec->follow_id;
											}
											$followUser->update_time = $rec->time;
											$followUser->is_chat     = 1;
											$followUser->save();
										} else {
											$time1 = $followUser->update_time;
											$time2 = $rec->time;
											if ($time2 > $time1) {
												if (!empty($rec->follow_id)) {
													$followUser->follow_id = $rec->follow_id;
												}
												$followUser->is_chat     = 1;
												$followUser->update_time = $time2;
												$followUser->save();
											}
										}

									}
								}
							}
						}
					}
				}
			}

			$record     = WorkExternalContactFollowRecord::find()->where(['sub_id' => 0, 'sub_id' => 0])->andWhere(['!=', 'uid', 2])->select('external_id')->groupBy('external_id')->asArray()->all();
			$externalId = array_column($record, 'external_id');
			if (!empty($externalId)) {
				foreach ($externalId as $id) {
					$follow = WorkExternalContactFollowRecord::find()->where(['external_id' => $id])->orderBy(['time' => SORT_DESC])->one();
					/** @var WorkExternalContactFollowRecord $follow */
					if (!empty($follow->follow_id)) {
						WorkExternalContactFollowUser::updateAll(['update_time' => $follow->time, 'is_chat' => 1, 'follow_id' => $follow->follow_id], ['update_time' => 0, 'external_userid' => $id]);
					} else {
						WorkExternalContactFollowUser::updateAll(['update_time' => $follow->time, 'is_chat' => 1], ['update_time' => 0, 'external_userid' => $id]);
					}
				}
			}
			echo '更新成功';

		}

		/**
		 ** showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           更新创建时间为最后一次跟进时间
		 * @description     更新创建时间为最后一次跟进时间
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-empty-time
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/30 14:16
		 * @number          0
		 *
		 */
		public function actionUpdateEmptyTime ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$followUser = WorkExternalContactFollowUser::find()->where(['update_time' => 0])->all();
			/** @var WorkExternalContactFollowUser $user */
			foreach ($followUser as $user) {
				$user->update_time = $user->createtime;
				$user->save();
			}
		}

		/**
		 ** showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新客户的描述、备注、预计成交率
		 * @description     批量更新客户的描述、备注、预计成交率
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-desc
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/17 16:29
		 * @number          0
		 *
		 */
		public function actionUpdateDesc ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$contact = WorkExternalContact::find()->where(['!=', 'close_rate', ''])->andWhere(['!=', 'corp_id', 1])->all();
			if (!empty($contact)) {
				/** @var WorkExternalContact $cnt */
				foreach ($contact as $cnt) {
					WorkExternalContactFollowUser::updateAll(['close_rate' => $cnt->close_rate], ['close_rate' => NULL, 'external_userid' => $cnt->id]);
				}
			}
			$contact = WorkExternalContact::find()->where(['!=', 'nickname', ''])->andWhere(['!=', 'corp_id', 1])->all();
			if (!empty($contact)) {
				foreach ($contact as $cnt) {
					WorkExternalContactFollowUser::updateAll(['nickname' => $cnt->nickname], ['nickname' => '', 'external_userid' => $cnt->id]);
				}
			}
			$contact = WorkExternalContact::find()->where(['!=', 'des', ''])->andWhere(['!=', 'corp_id', 1])->all();
			if (!empty($contact)) {
				foreach ($contact as $cnt) {
					WorkExternalContactFollowUser::updateAll(['des' => $cnt->des], ['des' => '', 'external_userid' => $cnt->id]);
				}
			}
			echo '更新成功';
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新根据记录表的user_id
		 * @description     批量更新根据记录表的user_id
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-follow-user-id
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/24 9:59
		 * @number          0
		 *
		 */
		public function actionUpdateFollowUserId ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$followRecord = WorkExternalContactFollowRecord::find()->where(['!=', 'sub_id', 0])->andWhere(['user_id' => 0])->all();
			if (!empty($followRecord)) {
				/** @var WorkExternalContactFollowRecord $record */
				foreach ($followRecord as $record) {
					$subUser  = SubUser::findOne($record->sub_id);
					$userCorp = UserCorpRelation::find()->where(['uid' => $record->uid])->asArray()->all();
					if (!empty($userCorp) && !empty($subUser->account)) {
						foreach ($userCorp as $corp) {
							$user = WorkUser::findOne(['corp_id' => $corp['corp_id'], 'mobile' => $subUser->account]);
							if (!empty($user)) {
								$record->user_id = $user->id;
								$record->save();
							}
						}
					}
				}
			}
			echo '更新完成';
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           批量更新根据记录表的状态
		 * @description     批量更新根据记录表的状态
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/update-master
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/24 9:59
		 * @number          0
		 *
		 */
		public function actionUpdateMaster ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$record = WorkExternalContactFollowRecord::find()->all();
			/** @var WorkExternalContactFollowRecord $value */
			foreach ($record as $value) {
				if ($value->sub_id != 0 || $value->user_id != 0) {
					$value->is_master = 1;
					$value->save();
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           客户看版
		 * @description     客户看版
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/custom-board
		 *
		 * @param corp_id 必选 int 企业微信id
		 * @param uid 必选 int 账户id
		 * @param name 可选 string 客户姓名等
		 * @param phone 可选 string 手机号或QQ
		 * @param user_ids 可选 array 成员id
		 * @param sort 可选 int 时间排序0到3
		 * @param day 可选 int 联系0到9
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 * @param id 可选 int 当前状态的id用于滚动
		 * @param start_time 可选 string 开始时间
		 * @param end_time 可选 string 结束时间
		 * @param pages 可选 array 所有列的当前页数
		 * @param is_fans 可选 int 是否是粉丝1是2否
		 * @param is_protect 可选 string 是否已保护：-1全部、0否、1是
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 跟进状态id
		 * @return_param    title string 状态名称
		 * @return_param    status string 0代表已删除要标记下
		 * @return_param    count string 客户数
		 * @return_param    members array 客户信息
		 * @return_param    members.nickname string 昵称
		 * @return_param    members.avatar string 头像可能为空需要前端补默认头像
		 * @return_param    members.employee string 员工姓名
		 * @return_param    members.cid string 客户id
		 * @return_param    members.chat string 沟通时间
		 * @return_param    members.remark string 备注
		 * @return_param    members.close_rate string 成交率
		 * @return_param    members.company_name string 公司名称
		 * @return_param    members.name string 姓名
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/17 19:23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionCustomBoard ()
		{
			if (\Yii::$app->request->isPost) {
				$isMasterAccount = \Yii::$app->request->post('isMasterAccount', 1);
				$sub_id          = \Yii::$app->request->post('sub_id', 0);
				$cid             = \Yii::$app->request->post('cid', 0);
				$uid             = \Yii::$app->request->post('uid', 0);
				$page            = \Yii::$app->request->post('page', 1);
				$pageSize        = \Yii::$app->request->post('pageSize', 15);
				$name            = \Yii::$app->request->post('name');
				$phone           = \Yii::$app->request->post('phone');
				$user_ids        = \Yii::$app->request->post('user_ids');
				$sort            = \Yii::$app->request->post('sort', 0);
				$day             = \Yii::$app->request->post('day', 0);
				$id              = \Yii::$app->request->post('id');
				$start_time      = \Yii::$app->request->post('start_time');
				$end_time        = \Yii::$app->request->post('end_time');
				$pages           = \Yii::$app->request->post('pages');
				$is_fans         = \Yii::$app->request->post('is_fans');
				$isProtect       = \Yii::$app->request->post('is_protect', '-1');

				$name  = trim($name);
				$phone = trim($phone);
				if (empty($uid) || empty($this->corp)) {
					throw new InvalidDataException('缺少必要参数！');
				}

				if (!empty($user_ids)) {
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}

				//高级属性搜索
				$fieldList = CustomField::find()->where('is_define=0')->select('`id`,`key`')->asArray()->all();//默认属性
				$fieldD    = [];
				foreach ($fieldList as $k => $v) {
					$fieldD[$v['key']] = $v['id'];
				}
				//查询客户保护
				if ($isMasterAccount == 1) {
					$isShow = $isRest = 1;
				} else {
					$protectData = PublicSeaProtect::getProtectBySubId($this->corp->id, $sub_id);
					$isShow      = $protectData['is_show'];
					$isRest      = $protectData['is_rest'];
				}

				$info      = [];
				$followNew = Follow::findOne(['uid' => $uid, 'status' => 1]);
				$follow    = Follow::find()->where(['uid' => $uid]);
				if (!empty($id)) {
					$follow = $follow->andWhere(["id" => $id]);
				}
				$follow     = $follow->select('id,uid,title,status')->orderBy(['status' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();
				$offset     = ($page - 1) * $pageSize;
				$sub_detail = [];
				if ($isMasterAccount == 2) {
					$sub_detail = AuthoritySubUserDetail::getDepartmentUserLists($this->subUser->sub_id, $this->corp->id);
					if ($sub_detail === false) {
						return [];
					}
				}
				if (!empty($follow)) {
					foreach ($follow as $key => $value) {
						$followUser = WorkExternalContactFollowUser::find()->alias('wf');
						$followUser = $followUser->leftJoin('{{%work_external_contact}} we', 'we.id=wf.external_userid')->where(['we.corp_id' => $this->corp->id, 'wf.follow_id' => $value['id']])->andWhere(['in', 'wf.del_type', [WorkExternalContactFollowUser::WORK_CON_EX, WorkExternalContactFollowUser::NO_ASSIGN]]);;
						$followUser = $followUser->leftJoin('{{%work_user}} a', 'wf.user_id=a.id');
						if (!empty($phone)) {
							$followUser = $followUser->andWhere(' wf.remark_mobiles like  \'%' . $phone . '%\' ');
						}
						if ($name) {
							$followUser = $followUser->leftJoin('{{%custom_field_value}} cf', '`cf`.`cid` = `we`.`id` AND `cf`.`type`=1');
							if (!empty($name)) {
								$followUser = $followUser->andWhere(' we.name_convert like \'%' . $name . '%\' or wf.remark_corp_name like \'%' . $name . '%\'  or wf.remark like \'%' . $name . '%\' or wf.nickname like \'%' . $name . '%\' or (cf.fieldid in (' . $fieldD['name'] . ') and cf.value like \'%' . $name . '%\')');
							}

						}
						if (!empty($is_fans)) {
							if ($is_fans == 1) {
								$followUser = $followUser->andWhere(['we.is_fans' => 1]);
							} else {
								$followUser = $followUser->andWhere(['we.is_fans' => 0]);
							}
						}
						if ($isProtect != '-1') {
							$followUser = $followUser->andWhere(['wf.is_protect' => $isProtect]);
						}
						if (!empty($user_ids)) {
							$followUser = $followUser->andWhere(['in', 'wf.user_id', $user_ids]);
						}
						if (!empty($cid)) {
							$followUser = $followUser->andWhere(['wf.id' => $cid]);
						}
						if (!empty($sub_detail) && is_array($sub_detail)) {
							$followUser = $followUser->andWhere(["in", 'wf.user_id', $sub_detail]);
						}
						if (!empty($start_time) && !empty($end_time)) {
							$followUser = $followUser->andFilterWhere(['between', 'wf.createtime', strtotime($start_time), strtotime($end_time)]);
						}
						$followUser = $followUser->select('a.name as employee,we.name,we.name_convert,we.avatar,we.corp_name as wcorp_name,wf.user_id,wf.update_time,we.id as wid,wf.id as fid,wf.nickname,wf.remark,wf.close_rate,wf.remark_corp_name,wf.createtime,wf.is_protect');
						if (!empty($day)) {
							switch ($day) {
								case 1:
									$followUser = $followUser->andWhere('wf.update_time=wf.createtime');
									break;
								case 2:
									$time       = Follow::getTime(1);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 3:
									$time       = Follow::getTime(2);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 4:
									$time       = Follow::getTime(3);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 5:
									$time       = Follow::getTime(4);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 6:
									$time       = Follow::getTime(5);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 7:
									$time       = Follow::getTime(6);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 8:
									$time       = Follow::getTime(7);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
								case 9:
									$time       = Follow::getTime(8);
									$followUser = $followUser->andWhere(['OR', ['<', 'wf.update_time', $time], 'wf.update_time=wf.createtime']);
									break;
							}
						}
						if ($sort == 0) {
							$followUser = $followUser->orderBy(['wf.createtime' => SORT_DESC]);
						} elseif ($sort == 1) {
							$followUser = $followUser->orderBy(['wf.createtime' => SORT_ASC]);
						} elseif ($sort == 2) {
							if ($followNew->id == $value['id']) {
								$followUser = $followUser->orderBy(['wf.is_chat' => SORT_DESC, 'wf.update_time' => SORT_DESC]);
							} else {
								$followUser = $followUser->orderBy(['wf.update_time' => SORT_DESC]);
							}
						} elseif ($sort == 3) {
							if ($followNew->id == $value['id']) {
								$followUser = $followUser->orderBy(['wf.is_chat' => SORT_ASC, 'wf.update_time' => SORT_ASC]);
							} else {
								$followUser = $followUser->orderBy(['wf.update_time' => SORT_ASC]);
							}
						}
						$count = $followUser->groupBy('wf.id')->count();

						if ($count == 0 && $value["status"] == 0) {
							continue;
						}
						if(empty($cid)){
							$followUser = $followUser->limit($pageSize)->offset($offset)->groupBy('wf.id');
						}
						$followUser = $followUser->asArray()->all();
						if ($isMasterAccount != 1) {
							$followUserIds     = array_column($followUser, 'fid');
							$protectFollowData = PublicSeaProtect::getDataByFollowUserId($this->corp->id, $followUserIds, 1);
						}

						$info[$key]['id']     = $value['id'];
						$info[$key]['title']  = $value['title'];
						$info[$key]['status'] = $value['status'];
						$info[$key]['count']  = (int) $count;
						$info[$key]['members'] = $members = [];
						if (!empty($followUser)) {
							$WorkUserIds = array_column($followUser, "user_id");
							$extId       = array_column($followUser, "wid");
							//自定义属性
							$customFieldValue = CustomFieldValue::find()->alias("a")
								->leftJoin("{{%custom_field}} as b", "a.fieldid = b.id and a.uid = b.uid")
								->where(["in", "a.user_id", $WorkUserIds])
								->where(["in", "a.cid", $extId])
								->andWhere(["a.uid" => $this->user->uid])
								->andWhere("b.key = 'name' and a.value is not null")
								->select("a.value,a.cid");
							$customFieldValue = $customFieldValue->asArray()->all();
							$newData          = [];
							if(!empty($customFieldValue)){
								$newData = array_column($customFieldValue,"value","cid");
							}
							foreach ($followUser as $k => $v) {
								$name_new = '暂无姓名';
								if (isset($newData[$v["wid"]])) {
									$name_new = $newData[$v["wid"]];
								}
								$fans   = Fans::find()->alias("a")
									->leftJoin("{{%wx_authorize_info}} as b", "a.author_id = b.author_id")
									->where(['a.external_userid' => $v['wid'], 'a.subscribe' => Fans::USER_SUBSCRIBE])
									->select("b.nick_name")->asArray()->one();
								$wxName = '';
								if (!empty($fans)) {
									$wxName = $fans["nick_name"];
								}
								$members[$k]['wx_name']   = $wxName;
								$members[$k]['nickname']  = rawurldecode($v['name']);
								$members[$k]['corp_name'] = $v['wcorp_name'];
								$members[$k]['avatar']    = $v['avatar'];
								$members[$k]['employee']  = $v['employee'];
								$members[$k]['cid']       = $v['fid'];
								$perName                  = WorkPerTagFollowUser::getTagName($v['fid']);
								$tagName                  = WorkTagContact::getTagNameByContactId($v['fid'], 0,0,[],$this->corp->id);
								$members[$k]['per_name']  = $perName;
								$members[$k]['tag_name']  = $tagName;
								if ($v['update_time'] == $v['createtime']) {
									$chat = '一直未沟通';
								} else {
									$time = $v['update_time'];
									$chat = DateUtil::getDiffText($time);
								}
								$nickname   = '暂无备注';
								$hasRemark  = false;
								$close_rate = 0;
								if (!empty($v['nickname'])) {
									$nickname  = $v['nickname'];
									$hasRemark = true;
								} elseif (!empty($v['nickname']) && $v['nickname'] != $v['name_convert']) {
									$nickname  = $v['remark'];
									$hasRemark = true;
								} elseif (!empty($v['remark']) && $v['remark'] != $v['name_convert']) {
									$nickname  = $v['remark'];
									$hasRemark = true;
								}
								if (!empty($v['close_rate'])) {
									$close_rate = $v['close_rate'];
								}
								$members[$k]['chat']         = $chat;
								$members[$k]['remark']       = $nickname;
								$members[$k]['has_remark']   = $hasRemark;
								$members[$k]['close_rate']   = $close_rate;
								$members[$k]['company_name'] = !empty($v['remark_corp_name']) ? $v['remark_corp_name'] : '暂无公司';
								$members[$k]['name']         = $name_new;
								$members[$k]['is_show']      = $isShow;
								$members[$k]['is_rest']      = $isRest;
								$members[$k]['is_protect']   = (int) $v['is_protect'];
								$members[$k]['protect_str']  = '';
								if ($isMasterAccount != 1) {
									if (!empty($protectFollowData[$v['fid']])) {
										$followData = $protectFollowData[$v['fid']];
										if ($sub_id != $followData['sub_id']) {
											$tempName = rawurldecode($v['name']);;
											$members[$k]['protect_str'] = '【' . $tempName . '】已被【' . $followData['name'] . '】保护，您无法取消保护';
										}
									}
								}
							}
							if (empty($cid)) {
								$info[$key]['members'] = $members;
							} else {
								$info = $members;
							}
						}
					}
				}
				$info = array_values($info);

				return $info;
			} else {
				throw new InvalidParameterException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           根据名称搜索渠道活码
		 * @description     根据名称搜索渠道活码
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/get-way-by-name
		 *
		 * @param corp_id 必选 string 企业id
		 * @param type 必选 string 类型
		 * @param title 可选 string 活码名称
		 *
		 * @return          {"error":0,"data":{"wayData":[{"id":"way_183","title":"测试21333"},{"id":"way_242","title":"23213213"},{"id":"way_261","title":"321332144345324213321323132131"}],"chatWayData":[{"id":"chatWay_15","title":"13245"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 活码id
		 * @return_param    title string 活码名称
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-13 15:52
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetWayByName ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$type  = \Yii::$app->request->post('type', '');
			$title = \Yii::$app->request->post('title', '');
			if (empty($this->corp)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			$data    = [];
			if ($type == 'way') {
				$wayList = WorkContactWay::find()->where(['corp_id' => $corp_id]);
				if (!empty($title) || $title === '0') {
					$wayList = $wayList->andWhere(['like', 'title', $title]);
				}
				$wayList = $wayList->select('id,title')->all();
				foreach ($wayList as $way) {
					$data[] = ['key' => 'way_' . $way->id, 'value' => 'way_' . $way->id, 'title' => $way->title];
				}
			} elseif ($type == 'chatWay') {
				$chatWayList = WorkChatContactWay::find()->where(['corp_id' => $corp_id]);
				if (!empty($title) || $title === '0') {
					$chatWayList = $chatWayList->andWhere(['like', 'title', $title]);
				}
				$chatWayList = $chatWayList->select('id,title')->all();
				foreach ($chatWayList as $chatWay) {
					$data[] = ['key' => 'chatWay_' . $chatWay->id, 'value' => 'chatWay_' . $chatWay->id, 'title' => $chatWay->title];
				}
			} elseif ($type == 'fission') {
				$fissionList = Fission::find()->where(['corp_id' => $corp_id]);
				$activitys    = WorkPublicActivity::find()->where(['corp_id' => $corp_id])->andWhere("type != 1");
				if (!empty($title) || $title === '0') {
					$fissionList = $fissionList->andWhere(['like', 'title', $title]);
					$activitys    = $activitys->andWhere(['like', 'activity_name', $title]);
				}
				$fissionList = $fissionList->select('id,title')->all();
				$activitys = $activitys->select('id,activity_name')->all();
				$data = [];
				foreach ($fissionList as $fission) {
					$tmp = ['key' => 'fission_' . $fission->id, 'value' => 'fission_' . $fission->id, 'title' => $fission->title];
					array_push($data,$tmp);
				}
				/** @var WorkPublicActivity $activity**/
				foreach ($activitys as $activity) {
					$tmp = ['key' => 'activity_' . $activity->id, 'value' => 'activity_' . $activity->id, 'title' => $activity->activity_name];
					array_push($data,$tmp);
				}
			} elseif ($type == 'award') {
				$awardList = AwardsActivity::find()->where(['corp_id' => $corp_id]);
				if (!empty($title) || $title === '0') {
					$awardList = $awardList->andWhere(['like', 'title', $title]);
				}
				$awardList = $awardList->select('id,title')->all();
				foreach ($awardList as $award) {
					$data[] = ['key' => 'award_' . $award->id, 'value' => 'award_' . $award->id, 'title' => $award->title];
				}
			} elseif ($type == 'redPack') {
				$redList = RedPack::find()->where(['corp_id' => $corp_id]);
				if (!empty($title) || $title === '0') {
					$redList = $redList->andWhere(['like', 'title', $title]);
				}
				$redList = $redList->select('id,title')->all();
				foreach ($redList as $red) {
					$data[] = ['key' => 'redPack_' . $red->id, 'value' => 'redPack_' . $red->id, 'title' => $red->title];
				}
			} elseif ($type == 'redWay') {
				$redWayList = WorkContactWayRedpacket::find()->where(['corp_id' => $corp_id]);
				if (!empty($title) || $title === '0') {
					$redWayList = $redWayList->andWhere(['like', 'name', $title]);
				}
				$redWayList = $redWayList->select('id,name')->all();
				foreach ($redWayList as $red) {
					$data[] = ['key' => 'redWay_' . $red->id, 'value' => 'redWay_' . $red->id, 'title' => $red->name];
				}
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-external-contact-follow-user/
		 * @title           获取外部联系人来源列表
		 * @description     获取外部联系人来源列表
		 * @method   post
		 * @url  http://{host_name}/api/work-external-contact-follow-user/add-way-list
		 *
		 * @param corp_id 必选 string 企业id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-06-15 18:58
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAddWayList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$wayList = WorkExternalContactFollowUser::getAddWay();
			$data    = [];
			$data[]  = ['key' => -1, 'value' => -1, 'title' => '全部来源', 'children' => []];
			foreach ($wayList as $key => $title) {
				if ($key == 1) {
					$data[] = [
						'key'      => $key,
						'value'    => $key,
						'title'    => $title,
						'children' => [
							['key' => 'way', 'value' => 'way', 'title' => '渠道活码'],
							['key' => 'chatWay', 'value' => 'chatWay', 'title' => '自动拉群'],
							['key' => 'fission', 'value' => 'fission', 'title' => '裂变引流'],
							['key' => 'award', 'value' => 'award', 'title' => '抽奖引流'],
							['key' => 'redPack', 'value' => 'redPack', 'title' => '红包裂变'],
							['key' => 'redWay', 'value' => 'redWay', 'title' => '红包拉新'],
						]
					];
				} else {
					$data[] = ['key' => $key, 'value' => $key, 'title' => $title, 'children' => []];
				}
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口|微信接口/modules/controller/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/api/work-external-contact-follow-user/updateFollowNum
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/13 19:18
		 * @number          0
		 *
		 */
		public function actionUpdateFollowNum ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$select       = new Expression('count(id) cc,external_id,user_id');
			$followRecord = WorkExternalContactFollowRecord::find()->select('user_id')->groupBy('user_id')->asArray()->all();
			$userIds      = array_column($followRecord, 'user_id');
			if (!empty($userIds)) {
				foreach ($userIds as $id) {
					if ($id > 0) {
						$follow = WorkExternalContactFollowRecord::find()->where(['user_id' => $id])->select($select)->groupBy('external_id')->asArray()->all();
						if (!empty($follow)) {
							foreach ($follow as $val) {
								$user = WorkExternalContactFollowUser::findOne(['user_id' => $val['user_id'], 'external_userid' => $val['external_id']]);
								if (!empty($user) && empty($user->follow_num)) {
									$user->follow_num = $val['cc'];
									$user->save();
								}
							}
						}
					}

				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口|微信接口/modules/controller/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/modules/controller/actionUpdateTags
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/31 20:26
		 * @number          0
		 *
		 * @throws \yii\db\Exception
		 */
		public function actionUpdateTags ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$sql  = "SELECT DISTINCT f.id,f.external_userid,f.userid,c.tag_id,c.contact_id,t.corp_id FROM {{%work_external_contact_follow_user}} f LEFT JOIN {{%work_tag_contact}} c ON c.contact_id=f.external_userid LEFT JOIN {{%work_tag}} t ON t.id=c.tag_id  WHERE f.del_type=0 and t.is_del=0";
			$info = \Yii::$app->getDb()->createCommand($sql)->queryAll();
			if (!empty($info)) {
				foreach ($info as $value) {
					if (!empty($value['id'])) {
						$followUser = WorkTagFollowUser::findOne(['tag_id' => $value['tag_id'], 'follow_user_id' => $value['id']]);
						if (empty($followUser)) {
							$followUser                 = new WorkTagFollowUser();
							$followUser->tag_id         = $value['tag_id'];
							$followUser->follow_user_id = $value['id'];
							$followUser->corp_id        = $value['corp_id'];
							$followUser->status         = 1;
							$followUser->save();
						}
					}
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口|微信接口/modules/controller/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/modules/controller/actionUpdateCustomPhone
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/11 13:50
		 * @number          0
		 *
		 */
		public function actionUpdateCustomPhone ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$custom = CustomField::findOne(['key' => 'phone']);
			if (!empty($custom)) {
				$customField = CustomFieldValue::find()->where(['type' => 1, 'fieldid' => $custom->id])->asArray()->all();
				if (!empty($customField)) {
					foreach ($customField as $val) {
						if (!empty($val['value'])) {
							WorkExternalContactFollowUser::updateAll(['remark_mobiles' => $val['value']], ['external_userid' => $val['cid']]);
						}
					}
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口|微信接口/modules/controller/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/modules/controller/actionUpdateCustomCompany
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/11 13:50
		 * @number          0
		 *
		 */
		public function actionUpdateCustomCompany ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$custom = CustomField::findOne(['key' => 'company']);
			if (!empty($custom)) {
				$customField = CustomFieldValue::find()->where(['type' => 1, 'fieldid' => $custom->id])->asArray()->all();
				if (!empty($customField)) {
					foreach ($customField as $val) {
						if (!empty($val['value'])) {
							WorkExternalContactFollowUser::updateAll(['remark_corp_name' => $val['value']], ['external_userid' => $val['cid']]);
						}
					}
				}
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口|微信接口/modules/controller/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/modules/controller/actionUpdateWxTag
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/8/11 13:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionUpdateWxTag ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$followUser = WorkTagFollowUser::find()->alias('t')->leftJoin('{{%work_external_contact_follow_user}} as f', '`t`.`follow_user_id` = `f`.`id`')
				->leftJoin('{{%work_external_contact}} as w', 'w.`id` = `f`.`external_userid`')
				->leftJoin('{{%work_tag}} as wt', 'wt.`id` = `t`.`tag_id`')
				->where(['wt.type' => 0, 'wt.is_del' => 0, 't.success' => 0, 'f.del_type' => WorkExternalContactFollowUser::WORK_CON_EX])->select('t.*,wt.tagid,w.corp_id,f.userid,w.external_userid');
			\Yii::error($followUser->createCommand()->getRawSql(), 'sql');
			$followUser = $followUser->asArray()->all();
			if (!empty($followUser)) {
				foreach ($followUser as $user) {
					$follow = WorkTagFollowUser::findOne($user['id']);
					if (!empty($follow)) {
						$workApi = WorkUtils::getWorkApi($user['corp_id'], 1);
						if ($workApi instanceof Work) {
							try {
								$res = $workApi->ECMarkTag($user['userid'], $user['external_userid'], [$user['tagid']]);
								if ($res['errcode'] == 0) {
									$follow->success = 1;
								}
							} catch (\Exception $e) {
								$follow->success = 2;
							}
						}
						$follow->save();
					}

				}
			}

		}

	}