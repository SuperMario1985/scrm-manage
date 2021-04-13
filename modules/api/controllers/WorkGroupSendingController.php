<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2020/2/12
	 * Time: 16:16
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\CustomField;
	use app\models\CustomFieldValue;
	use app\models\WorkChat;
	use app\models\WorkChatInfo;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContact;
	use app\models\WorkGroupSendingRedpacketSend;
	use app\models\WorkGroupSendingUser;
	use app\models\WorkTagGroupStatistic;
	use app\models\WorkTagGroupUserStatistic;
	use app\models\WorkTagPullGroup;
	use app\models\WorkUser;
	use app\models\WorkCorpAgent;
	use app\models\WorkGroupSending;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\queue\GetGroupMsgResultJob;
	use app\queue\WorkGroupSendingJob;
	use app\util\DateUtil;
	use PhpOffice\PhpSpreadsheet\Shared\Date;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;
	use yii\db\Expression;

	class WorkGroupSendingController extends WorkBaseController
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
						'list'       => ['POST'],
						'agent-list' => ['POST'],
						'add'        => ['POST'],
						'detail'     => ['POST'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           群发列表
		 * @description     群发列表
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param title 可选 int 名称
		 * @param status 可选 int 发送状态：-1全部0未发送1已发送2发送失败
		 * @param date 可选 string 日期
		 * @param agent_id 可选 string 应用id
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    key int key
		 * @return_param    title string 名称
		 * @return_param    send_type int 1全部客户2按条件筛选客户3员工
		 * @return_param    send_people string 发送对象
		 * @return_param    show int 1显示编辑按钮0不显示
		 * @return_param    push_time string 发送时间
		 * @return_param    type string 群发类型
		 * @return_param    error_msg string 错误信息
		 * @return_param    is_redpacket int 是否群发红包1是0否
		 * @return_param    redpacket_amount string 活动投放金额
		 * @return_param    send_amount string 已领取金额
		 * @return_param    send_num int 已领取人数
		 * @return_param    rule_type int 红包金额类型：1、固定金额，2、随机金额
		 * @return_param    rule_fixed_amount string 固定金额
		 * @return_param    rule_min_random_amount string 最小随机金额
		 * @return_param    rule_max_random_amount string 最大随机金额
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/14 10:14
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
				$status       = \Yii::$app->request->post('status');
				$date         = \Yii::$app->request->post('sendDate');
				$agentId      = \Yii::$app->request->post('agent_id');
				$send_type    = \Yii::$app->request->post('send_type');
				$sTime        = \Yii::$app->request->post('stime');
				$eTime        = \Yii::$app->request->post('etime');
				$work_sending = WorkGroupSending::find()->andWhere(['corp_id' => $this->corp['id'], 'is_del' => 0]);
				//消息名称
				if (!empty($title) || $title == '0') {
					$work_sending = $work_sending->andWhere(['like', 'title', $title]);
				}
				//应用id
				if (!empty($agentId)) {
					$work_sending = $work_sending->andWhere(['agentid' => $agentId]);
				}
				//发送状态 0未发送 1已发送 2发送失败 -1 全部
				if ($status != -1) {
					$work_sending = $work_sending->andWhere(['status' => $status]);
				}
				if (!empty($send_type) && in_array($send_type, [1, 3, 4])) {
					if ($send_type == 1) {
						$send_type = [1, 2];
					}
					$work_sending = $work_sending->andWhere(['send_type' => $send_type]);
				}
				//发送日期
				if (!empty($date)) {
					$start_date   = $date . ' 00:00:00';
					$end_date     = $date . ' 23:59:59';
					$work_sending = $work_sending->andWhere(['>=', 'push_time', $start_date]);
					$work_sending = $work_sending->andWhere(['<=', 'push_time', $end_date]);
				}
				if(!empty($sTime) && !empty($eTime)){
					$work_sending = $work_sending->andWhere(['>=', 'push_time', $sTime]);
					$work_sending = $work_sending->andWhere(['<=', 'push_time', $eTime]);
				}
				$count  = $work_sending->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $work_sending->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
				if (!empty($info)) {
					foreach ($info as $val) {
						$data = $val->dumpData();
						if (strtotime($data['push_time']) > time()) {
							$data['show'] = 1;
						} else {
							$data['show'] = 0;
						}
						if ($data['send_type'] == 1) {
							$data['send_people'] = '客户（全部客户）';
							$data['type'] = '客户';
						} elseif ($data['send_type'] == 2) {
							$data['send_people'] = '客户（按条件筛选）';
							$data['type'] = '客户';
						} elseif ($data['send_type'] == 3) {
							$user_keys = json_decode($data['user_key'], true);
							$userIds   = array_column($user_keys, 'id');
							$work_user = WorkUser::find()->where(["in", "id", $userIds])->select("name")->asArray()->all();
							$users     = array_column($work_user, 'name');
//
//							$users     = [];
//							foreach ($user_keys as $k => $u) {
//								if (isset($u['id'])) {
//									$work_user = WorkUser::findOne($u['id']);
//									if (!empty($work_user)) {
//										$dataUser = $work_user->dumpData();
//										array_push($users, $dataUser['name']);
//									}
//								}
//							}
							$name                = implode(',', $users);
							$data['send_people'] = '企业成员（' . $name . '）';
							$data['type'] = '企业成员';
						} elseif ($data['send_type'] == 4) {
							$data['type'] = '客户群';
//							$sendPeople = WorkGroupSending::getSendData ($data['corp_id'], $data['user_key'], 1);
//							$data['send_people'] = $sendPeople;

							$data['redpacket_amount'] = sprintf('%.2f', $data['redpacket_amount'] * $data['will_num']);
						}
						array_push($result, $data);
					}
				}
				$cacheKey     = 'get_group_msg_result' . $this->corp->id;
				$currentYmd   = DateUtil::getCurrentYMD();
				$refreshCache = \Yii::$app->cache->get($cacheKey);
				if (empty($refreshCache) || empty($refreshCache[$currentYmd])) {
					$refreshCache = [
						$currentYmd => [
							'last_refresh_time' => 0,
						]
					];
				}
				if (($refreshCache[$currentYmd]['last_refresh_time'] + 60) > time()) {
					\Yii::error('不足10分钟', '$refreshCache');
				} else {
					$refreshCache[$currentYmd]['last_refresh_time'] = time();
					\Yii::$app->cache->set($cacheKey, $refreshCache);
					WorkGroupSending::getSendResult($this->corp->id);
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
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           获取当前企业的应用
		 * @description     获取当前企业的应用
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/agent-list
		 *
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param suite_id 必选 int 应用id
		 * @param agent_type 可选 int 应用类型：1、基础；2、自建；3、授权；4、小程序
		 *
		 * @return          {"error":0,"data":[{"name":"云美来SCRM","id":"5","agent_type":"2","square_logo_url":"http://wework.qpic.cn/bizmail/VeW5ZhyQFlwpg1Izf41eHtvxM0oSeRDiaiaICrlp8ib7iaKQKFYHVADvuw/0"},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 应用名称
		 * @return_param    id string 应用ID
		 * @return_param    agent_type string 应用类型：1、基础；2、自建；3、授权；4、小程序
		 * @return_param    square_logo_url string 图标
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/14 10:32
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAgentList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->corp)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$workAgent = WorkCorpAgent::find()->where(['corp_id' => $this->corp['id'], 'is_del' => WorkCorpAgent::AGENT_NO_DEL, 'close' => WorkCorpAgent::AGENT_NOT_CLOSE, 'agent_type' => [WorkCorpAgent::CUSTOM_AGENT, WorkCorpAgent::AUTH_AGENT]]);

				$agentType = \Yii::$app->request->post('agent_type', 0);

				if ($agentType != 0) {
					$workAgent = $workAgent->andWhere(['agent_type' => $agentType]);
				} else {
					$workAgent = $workAgent->andWhere(['!=', 'agent_type', WorkCorpAgent::MINIAPP_AGENT]);
				}

				$workAgent = $workAgent->select('name,id,agent_type,square_logo_url')->asArray()->all();

				return $workAgent;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           群发添加/修改接口
		 * @description     群发添加/修改接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/add
		 *
		 * @param isMasterAccount  必选 int 1主账户2子账户
		 * @param sub_id           必选 int 子账户ID
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param id 可选 int 修改时添加时可传0
		 * @param title 必选 string 消息名称
		 * @param agent_id 必选 int 应用id
		 * @param send_type 必选 int 1、全部客户2、按条件筛选客户3、企业成员4、选择群主
		 * @param sex 可选 int 当筛选客户时传-1全部0未知1男2女
		 * @param tag_ids 可选 array 标签id
		 * @param msg_type 可选 int 当选员工时传-消息类型1文本2图片3图文4音频5视频6小程序7文件
		 * @param users 可选 array 成员id/客户时传custom-list接口里的key类似于[1922,1929]或客户群的id
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
		 * @param voice_media_id 可选 int    音频素材id
		 * @param video_media_id 可选 int    视频素材id
		 * @param file_media_id 可选 int    文件素材id
		 * @param push_type 可选 int 0立即发送1指定时间发送
		 * @param push_time 可选 string 发送时间
		 * @param interval 可选 string 间隔开关1关2开
		 * @param interval_time 可选 string 间隔时间
		 * @param interval_num 可选 string 间隔人数
		 *
		 * @param is_redpacket           必选 int 是否群发红包1是0否
		 * @param rule_id                可选 int 红包规则id
		 * @param rule_save              可选 int 是否保存规则1是0否
		 * @param rule_name              可选 string 红包规则名称
		 * @param rule_type              可选 int 单个红包金额类型：1、固定金额，2、随机金额
		 * @param rule_fixed_amount      可选 string 固定金额
		 * @param rule_min_random_amount 可选 string 最小随机金额
		 * @param rule_max_random_amount 可选 string 最大随机金额
		 * @param rule_pic_url           可选 string 红包封面路径
		 * @param rule_title             可选 string 红包标题
		 * @param rule_des               可选 string 红包描述
		 * @param rule_thanking          可选 string 感谢语
		 * @param redpacket_amount       必选 string 投放金额
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/15 15:05
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
				$data                     = [];
				$data['isMasterAccount']  = \Yii::$app->request->post('isMasterAccount', 1);
				$data['sub_id']           = \Yii::$app->request->post('sub_id', 0);
				$data['id']               = \Yii::$app->request->post('id') ?: 0;
				$data['agent_id']         = \Yii::$app->request->post('agent_id');
				$data['title']            = \Yii::$app->request->post('title');
				$data['send_type']        = \Yii::$app->request->post('send_type');//1、全部客户 2、按条件筛选客户 3、企业成员 4、选择群主
				$data['users']            = \Yii::$app->request->post('users');//员工跟之前一样，客户直接类似[1,2,3]
				$data['sex']              = \Yii::$app->request->post('sex');
				$data['tag_ids']          = \Yii::$app->request->post('tag_ids');
				$data['msg_type']         = \Yii::$app->request->post('msg_type');//消息类型1文本2图片3图文4音频5视频6小程序7文件
				$data['push_type']        = \Yii::$app->request->post('push_type') ?: 0;//0立即发1延迟发
				$data['push_time']        = \Yii::$app->request->post('push_time') ?: 0;//发送时间
				$data['is_redpacket']     = \Yii::$app->request->post('is_redpacket') ?: 0;//是否群发红包1是0否
				$data['redpacket_amount'] = \Yii::$app->request->post('redpacket_amount') ?: 0;//红包金额
				//红包
				$data['rule_id'] = \Yii::$app->request->post('rule_id', 0);//红包规则id
				if ($data['is_redpacket'] == 1 && $data['rule_id'] == 0) {
					$data['rule_save']              = \Yii::$app->request->post('rule_save') ?: 0;//是否保存规则 1是0否
					$data['rule_name']              = \Yii::$app->request->post('rule_name');
					$data['rule_type']              = \Yii::$app->request->post('rule_type');
					$data['rule_fixed_amount']      = \Yii::$app->request->post('rule_fixed_amount');
					$data['rule_min_random_amount'] = \Yii::$app->request->post('rule_min_random_amount');
					$data['rule_max_random_amount'] = \Yii::$app->request->post('rule_max_random_amount');
					$data['rule_pic_url']           = \Yii::$app->request->post('rule_pic_url');
					$data['rule_title']             = \Yii::$app->request->post('rule_title');
					$data['rule_des']               = \Yii::$app->request->post('rule_des');
					$data['rule_thanking']          = \Yii::$app->request->post('rule_thanking');
				}
				//内容
				$data['add_type']           = \Yii::$app->request->post('add_type');//1图片2图文3小程序
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
				$data['voice_media_id']     = \Yii::$app->request->post('voice_media_id');
				$data['video_media_id']     = \Yii::$app->request->post('video_media_id');
				$data['file_media_id']      = \Yii::$app->request->post('file_media_id');
				$data['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
				$data['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
				$data['groupId']            = \Yii::$app->request->post('groupId') ?: 0;
				$data['uid']                = \Yii::$app->request->post('uid') ?: 0;
				$data['belong_id']          = \Yii::$app->request->post('belong_id', 2);
				$data['attribute']          = \Yii::$app->request->post('attribute');
				$data['group_id']           = \Yii::$app->request->post('group_id');
				$data['tag_type']           = \Yii::$app->request->post('tag_type');
				$data['province']           = \Yii::$app->request->post('province');
				$data['city']               = \Yii::$app->request->post('city');
				$data['user_ids']           = \Yii::$app->request->post('user_ids');
				$data['follow_id']          = \Yii::$app->request->post('follow_id');
				$data['start_time']         = \Yii::$app->request->post('start_time');
				$data['end_time']           = \Yii::$app->request->post('end_time');
				$data['update_time']        = \Yii::$app->request->post('update_time');
				$data['chat_time']          = \Yii::$app->request->post('chat_time');
				$data['sign_id']            = \Yii::$app->request->post('sign_id');
				$data['follow_num1']        = \Yii::$app->request->post('follow_num1');
				$data['follow_num2']        = \Yii::$app->request->post('follow_num2');
				$data['is_fans']            = \Yii::$app->request->post('is_fans');
				$data['interval']           = \Yii::$app->request->post('interval', 1);
				$data['interval_time']      = \Yii::$app->request->post('interval_time');
				$data['interval_num']       = \Yii::$app->request->post('interval_num');
				$data['mini_title']         = trim($data['mini_title']);
				$data['text_content']       = trim($data['text_content']);
				$data['corp_id']            = $this->corp['id'];
				$data['uid']                = $this->user->uid;

//				if ($data['send_type'] == 1 || $data['send_type'] == 2) {
//					$sending = WorkGroupSending::findOne(['corp_id' => $data['corp_id']]);
//					if (!empty($sending)) {
//						$date1   = date('Y-m-d', strtotime('this week'));//						$date2   = date('Y-m-d', strtotime('this week +6 day'));

//						$s_time1 = strtotime($date1);
//						$s_time2 = strtotime($date2 . ' 23:59:59');
//						$time    = strtotime($sending->create_time);
//						if (($sending->send_type == 1 || $sending->send_type == 2) && $time >= $s_time1 && $time <= $s_time2) {
//							throw new InvalidDataException();
//						}
//					}
//				}
				//添加/修改
				WorkGroupSending::add($data);

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           群发详情
		 * @description     群发详情
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/detail
		 *
		 * @param id 必选 int 群发id
		 *
		 * @return array|mixed
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title  string 消息名称
		 * @return_param    agent_id int 应用id
		 * @return_param    send_type  int 1全部客户2筛选客户3员工
		 * @return_param    sex  int 当筛选客户时传-1全部0未知1男2女
		 * @return_param    tag_ids  array 标签id
		 * @return_param    msg_type  int 当选员工时传-消息类型1文本2图片3图文4音频5视频6小程序7文件
		 * @return_param    users  array 成员id/客户时传custom-list接口里的key类似于[1922,1929]或客户群的id
		 * @return_param    add_type  int 1图片2网页3小程序
		 * @return_param    text_content  string 文本内容
		 * @return_param    media_id  int 图片企业微信素材表id
		 * @return_param    link_title  string 网页标题
		 * @return_param    link_attachment_id int 网页封面id来源素材表
		 * @return_param    link_desc  string    网页描述
		 * @return_param    link_url  string    网页链接
		 * @return_param    mini_title  string    小程序标题
		 * @return_param    mini_pic_media_id  int  小程序封面企业微信素材表id
		 * @return_param    mini_appid  string    小程序appid
		 * @return_param    mini_page  string    小程序page路径
		 * @return_param    voice_media_id  int    音频素材id
		 * @return_param    video_media_id  int    视频素材id
		 * @return_param    file_media_id  int    文件素材id
		 * @return_param    file_name  string   文件名称
		 * @return_param    push_type  int 0立即发送1指定时间发送
		 * @return_param    push_time  string 发送时间
		 * @return_param    is_redpacket int 是否群发红包1是0否
		 * @return_param    rule_id int 红包规则id
		 * @return_param    rule_name string 规则名称
		 * @return_param    rule_type int 单个红包金额类型：1、固定金额，2、随机金额
		 * @return_param    rule_fixed_amount string 固定金额
		 * @return_param    rule_min_random_amount string 最小随机金额
		 * @return_param    rule_max_random_amount string 最大随机金额
		 * @return_param    rule_title string 红包标题
		 * @return_param    rule_pic_url string 红包封面路径
		 * @return_param    rule_des string 红包描述
		 * @return_param    rule_thanking string 感谢语
		 * @return_param    all_money string 活动投放总金额
		 * @return_param    all_get_money string 已领取金额
		 * @return_param    all_get_num string 已领取人数
		 * @return_param    all_not_get_money string 未领取金额
		 * @return_param    all_not_get_num string 未领取人数
		 * @return_param    all_expired_money string 已过期金额
		 * @return_param    all_expired_num string 已过期人数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/15 15:51
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
				$sending          = WorkGroupSending::findOne(['id' => $id]);
				$data             = $sending->dumpData(1);
				$data['user_key'] = !empty($data['user_key']) ? json_decode($data['user_key'], true) : [];
				foreach ( $data['user_key'] as &$datum){
					if (strpos($datum['id'], 'd') === false) {
						if(isset($datum["name"])){
							$datum['title'] = $datum["name"];
						}
					}
				}
				if ($data['is_redpacket'] == 1) {
					$data['link_title']   = $data['rule_title'];
					$data['link_desc']    = $data['rule_des'];
					$data['link_pic_url'] = $data['rule_pic_url'];
					$data['link_url']     = '';
				} else {
					$content = [];
					if (!empty($data['content'])) {
						$content = json_decode($data['content'], true);
					}
					$content['msg_type'] = $sending->msg_type;
					$contentData         = WorkWelcome::getContentData($content);

					$data = WorkWelcome::getWelcomeData($data, $content, $contentData);
				}

				return $data;

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           删除接口
		 * @description     删除接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/detail
		 *
		 * @param id 必选 int id
		 *
		 * @return bool
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/18 15:58
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
					throw new InvalidParameterException('参数不正确');
				}
				$sending         = WorkGroupSending::findOne($id);
				$sending->is_del = 1;
				if (!empty($sending->queue_id)) {
					\Yii::$app->queue->remove($sending->queue_id);
				}
				$sending->save();

				return true;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群发记录接口
		 * @description     客户群发记录接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/group-sending-customer-records
		 *
		 * @param id 必选 int 群列表id
		 * @param name 可选 string 客户名称
		 * @param user_name 可选 string 筛选的员工
		 * @param send 可选 int 送达状态默认传-1、0未收到邀请、1已收到、2客户不是好友、3客户已达上限
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":1,"info":[{"key":1,"id":1,"send":1,"name":"","avatar":"","corp_name":"aa","customer_name":"","gender":"男性"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    is_redpacket int 是否群发红包1是否
		 * @return_param    info array 数据列表
		 * @return_param    info.send int 0未收到邀请、1已收到、2客户不是好友、3客户已达上限
		 * @return_param    info.customer_name string 客户名称
		 * @return_param    info.name string 所属成员
		 * @return_param    info.avatar string 头像
		 * @return_param    info.gender string 性别
		 * @return_param    info.corp_name string 公司名称
		 * @return_param    info.redpacket_status int 红包领取状态0待领取1已领取2已过期3已领完4发放失败
		 * @return_param    info.redpacket_money string 红包金额
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/24 15:48
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupSendingCustomerRecords ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$groupSend = WorkGroupSending::findOne($id);
				if (empty($groupSend)) {
					throw new InvalidParameterException('活动参数不正确！');
				}

				$name     = \Yii::$app->request->post('name');
				$user_ids = \Yii::$app->request->post('user_ids');
				$send     = \Yii::$app->request->post('send');
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true,0);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				$statistic = WorkTagGroupStatistic::find()->alias('s')->leftJoin('{{%work_external_contact}} c', '`c`.`id` = `s`.`external_id`')->where(['s.send_id' => $id]);
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
				$select         = new Expression('count(DISTINCT(s.id)) cc');
				$statisticCount = $statistic->select($select)->asArray()->one();
				$count          = $statisticCount['cc'];
				$offset         = ($page - 1) * $pageSize;
				$result         = [];
				$select1        = new Expression('DISTINCT c.name,c.gender,c.corp_id,c.corp_name,c.avatar,c.corp_name,c.id cid,s.id,s.status,s.send,s.chat_id,s.user_id,s.push_time');
				$info           = $statistic->select($select1)->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				//\Yii::error($info->createCommand()->getRawSql(),'sql');
				$info = $info->asArray()->all();
				if (!empty($info)) {
					foreach ($info as $key => $value) {
						$workUser = WorkUser::findOne($value['user_id']);
						$name     = '';
						if (!empty($workUser)) {
							$name = $workUser->name;
						}
						$gender   = '';
						$custom = CustomFieldValue::findOne(['type' => 1, 'fieldid' => 3, 'cid' => $value['cid']]);
						if (!empty($custom)) {
							$sex = $custom->value;
							if ($sex == '男') {
								$gender = '男性';
							} elseif ($sex == '女') {
								$gender = '女性';
							} else {
								$gender = $sex;
							}
						}

						$result[$key]['key']           = $value['id'];
						$result[$key]['id']            = $value['id'];
						$result[$key]['send']          = $value['send'];
						$result[$key]['name']          = $name;
						$result[$key]['avatar']        = $value['avatar'];
						$result[$key]['corp_name']     = $value['corp_name'];
						$result[$key]['customer_name'] = !empty($value['name']) ? rawurldecode($value['name']) : '--';
						$result[$key]['gender']        = $gender;
						$result[$key]['push_time']     = !empty($value['push_time']) ? date('Y-m-d H:i',strtotime($value['push_time'])) : '--';

						if ($groupSend->is_redpacket == 1 && $value['send'] == 1) {
							$redpacket_status = 0;
							$redpacket_money  = '';
							$redpacketSend    = WorkGroupSendingRedpacketSend::findOne(['send_id' => $id, 'group_send_id' => $value['id']]);
							if (!empty($redpacketSend)) {
								$redpacket_status = $redpacketSend->status;
								if ($redpacketSend->status == 1) {
									$redpacket_money = $redpacketSend->send_money;//已领取
								} elseif ($redpacketSend->status == 2){
									$redpacket_status = 0;
								}
								/*elseif ($redpacketSend->status == 0) {
									$create_time = $redpacketSend->create_time ? $redpacketSend->create_time : $value['push_time'];
									if (time() - $create_time > 86400) {
										$redpacket_status = 2;
									}
								}*/
							}
							$result[$key]['redpacket_status'] = $redpacket_status;
							$result[$key]['redpacket_money']  = $redpacket_money;
						}
					}
				}

				return [
					'count'        => $count,
					'is_redpacket' => $groupSend->is_redpacket,
					'info'         => $result,
				];

			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群发成员确认接口
		 * @description     客户群发成员确认接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/customer-members
		 *
		 * @param id 必选 int 群列表id
		 * @param user_ids 可选 array 筛选的员工
		 * @param status 可选 int 是否发送0、未发送、1已发送、2排队发送
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":1,"info":[{"key":1,"id":1,"avatar":"","name":"aa","will_num":0,"real_num":0,"status":0}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    status int 0、未发送、1已发送
		 * @return_param    will_num int 预计人数
		 * @return_param    real_num int 实际人数
		 * @return_param    name string 名称
		 * @return_param    avatar string 头像
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/24 16:05
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionCustomerMembers ()
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
				$statistic = $statistic->andWhere(['s.send_id' => $id]);
				if (!empty($user_name) || $user_name == '0') {
					$user_name = trim($user_name);
					$statistic = $statistic->andWhere(['like', 'w.name', $user_name]);
				}
				if($status!=-1){
					$statistic = $statistic->andWhere(['s.status'=>$status]);
				}
				$count  = $statistic->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $statistic->select('w.name,w.id as user_id,s.id as id,w.avatar,s.will_num,s.real_num,s.push_time,s.status,s.send_id')->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				//\Yii::error($info->createCommand()->getRawSql(),'sql');
				$info = $info->asArray()->all();
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
						$result[$key]['real_num'] = !empty($val['real_num']) ? intval($val['real_num']) : 0;
						$result[$key]['status']   = intval($val['status']);
						$push_time                = ['--'];
						$sending = WorkGroupSending::findOne($val['send_id']);
						if ($sending->interval == 1) {
							if (!empty($val['push_time'])) {
								//$time        = date('Y-m-d H:i', $val['push_time']);
								//$push_time[] = '--';
							}
						} else {
							$sendUser = WorkGroupSendingUser::find()->where(['send_id' => $val['send_id'], 'user_id' => $val['user_id']])->asArray()->all();
							if (!empty($sendUser)) {
								$i             = 0;
								$push_time     = [];
								$intervalTime = $sending->interval_time;
								switch ($intervalTime) {
									case 1:
										$delay = 1800;
										break;
									case 2:
										$delay = 3600;
										break;
									case 3:
										$delay = 7200;
										break;
									case 4:
										$delay = 10800;
										break;
									case 5:
										$delay = 14400;
										break;
								}
								foreach ($sendUser as $k => $user) {
									$pTime = ($user['times'] - 1) * $delay;
									if ($sending->push_type == 0) {
										$pTime = strtotime($sending->create_time) + $pTime;
									} else {
										$pTime = strtotime($sending->push_time) + $pTime;
									}
									$send = '(未发送)';
									if ($user['status'] == 1) {
										$send = '(已发送)';
									}
									$pTime = !empty($pTime) ? date('Y-m-d H:i', $pTime) . $send : '--';
									array_push($push_time, $pTime);
								}
							}
						}
						$result[$key]['push_time'] = $push_time;
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
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群群发记录接口
		 * @description     客户群群发记录接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/group-sending-chat-records
		 *
		 * @param id 必选 int 群列表id
		 * @param name 可选 string 群名称
		 * @param user_ids 可选 array 群主
		 * @param send 可选 int 送达状态默认传-1、0未送达、1已送达、3客户已达上限
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":1,"info":[{"key":1,"id":1,"send":1,"name":"","user_name":"群主","count":2,"create_time":"2020-07-02 12:59"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    is_redpacket int 是否群发红包1是否
		 * @return_param    info array 数据列表
		 * @return_param    info.send int 送达状态、0未送达、1已送达、3客户已达上限
		 * @return_param    info.name string 群名称
		 * @return_param    info.count string 群人数
		 * @return_param    info.user_name string 群主
		 * @return_param    info.create_time string 创建时间
		 * @return_param    info.redpacket_status int 红包领取状态0待领取1已领取2已过期3已领完
		 * @return_param    info.redpacket_money string 红包金额
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/24 16:31
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupSendingChatRecords ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$groupSend = WorkGroupSending::findOne($id);
				if (empty($groupSend)) {
					throw new InvalidParameterException('活动参数不正确！');
				}
				$name     = \Yii::$app->request->post('name');
				$user_ids = \Yii::$app->request->post('user_ids');
				$send     = \Yii::$app->request->post('send');
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_ids = WorkGroupSending::sendChat($this->corp->id, $user_ids);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				$statistic = WorkTagGroupStatistic::find()->alias('s')->leftJoin('{{%work_chat}} c', '`s`.`chat_id` = `c`.`id`');
				$statistic = $statistic->where(['s.send_id' => $id]);
				if (!empty($name) || $name === '0') {
					$statistic = $statistic->andWhere(['like', 'c.name', $name]);
				}
				if (!empty($user_ids)) {
					$statistic = $statistic->andWhere(['c.owner_id' => $user_ids]);
				}
				if ($send != -1) {
					$statistic = $statistic->andWhere(['s.send' => $send]);
				}
				$count  = $statistic->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $statistic->select('s.id,s.chat_id,s.send,c.owner_id,c.create_time,s.push_time')->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				//\Yii::error($info->createCommand()->getRawSql(),'sql');
				$info = $info->asArray()->all();
				if (!empty($info)) {
					foreach ($info as $key => $val) {
						$result[$key]['key']  = $val['id'];
						$result[$key]['id']   = $val['id'];
						$result[$key]['send'] = $val['send'];
						$userName             = '';
						$workUser             = WorkUser::findOne($val['owner_id']);
						if (!empty($workUser)) {
							$userName = $workUser->name;
						}
						$result[$key]['user_name'] = $userName;
						$result[$key]['name']      = WorkChat::getChatName($val['chat_id']);
						$chatCount                 = WorkChatInfo::find()->where(['status' => 1, 'chat_id' => $val['chat_id']])->count();
						$result[$key]['count']     = $chatCount;
						$createTime                = '--';
						if (!empty($val['create_time'])) {
							$createTime = date('Y-m-d H:i', $val['create_time']);
						}
						$result[$key]['create_time'] = $createTime;
						$result[$key]['push_time']   = !empty($val['push_time']) ? date('Y-m-d H:i',strtotime($val['push_time']))  : '--';
						$result[$key]['avatarData'] = WorkChat::getChatAvatar($val['chat_id']);

						if ($groupSend->is_redpacket == 1 && $val['send'] == 1) {
							$redpacket_status = 0;
							$redpacket_money  = '';
							$redpacketSend    = WorkGroupSendingRedpacketSend::findOne(['send_id' => $id, 'group_send_id' => $val['id']]);
							if (!empty($redpacketSend)) {
								if ($redpacketSend->get_money > 0) {
									$redpacket_status = 1;
									$redpacket_money  = $redpacketSend->get_money;//已领取
								}
								/*else {
									$create_time      = $redpacketSend->create_time ? $redpacketSend->create_time : $val['push_time'];
									$redpacket_status = (time() - $create_time) > 86400 ? 2 : 1;

								}*/
							}
							$result[$key]['redpacket_status'] = $redpacket_status;
							$result[$key]['redpacket_money']  = $redpacket_money;
						}
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
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群群主确认接口
		 * @description     客户群群主确认接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/chat-members
		 *
		 * @param id 必选 int 群列表id
		 * @param user_ids 可选 array 群主
		 * @param status 可选 int 状态默认传-1、0未发送、1已发送
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_name string 群主
		 * @return_param    status string 0未发送、1已发送
		 * @return_param    push_num string 推送数量
		 * @return_param    send_num string 已送达数量
		 * @return_param    push_time string 群创建时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/24 16:43
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatMembers ()
		{
			if (\Yii::$app->request->isPost) {
				$id       = \Yii::$app->request->post('id');
				$user_ids = \Yii::$app->request->post('user_ids');
				$status   = \Yii::$app->request->post('status');
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if(!empty($user_ids)){
					$Temp     = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_ids);
					$user_ids = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
					$user_ids = WorkGroupSending::sendChat($this->corp->id, $user_ids);
					$user_ids = empty($user_ids) ? [0] : $user_ids;
				}
				$statistic = WorkTagGroupUserStatistic::find()->alias('s')->leftJoin('{{%work_user}} w', '`s`.`user_id` = `w`.`id`');
				$statistic = $statistic->where(['s.send_id' => $id]);
				if (!empty($user_ids)) {
					$statistic = $statistic->andWhere(['s.user_id' => $user_ids]);
				}
				if ($status != -1) {
					$statistic = $statistic->andWhere(['s.status' => $status]);
				}
				$count  = $statistic->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $statistic->select('s.id,s.status,s.will_num,s.real_num,s.push_time,s.user_id')->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				//\Yii::error($info->createCommand()->getRawSql(),'sql');
				$info = $info->asArray()->all();
				if (!empty($info)) {
					foreach ($info as $key => $val) {
						$result[$key]['key'] = $val['id'];
						$result[$key]['id']  = $val['id'];
						$userName            = '';
						$workUser            = WorkUser::findOne($val['user_id']);
						if (!empty($workUser)) {
							$userName = $workUser->name;
						}
						$result[$key]['user_name'] = $userName;
						$result[$key]['status']    = $val['status'];
						$result[$key]['push_num']  = intval($val['will_num']);
						$result[$key]['real_num']  = intval($val['real_num']);
						$pushTime              = '--';
						if (!empty($val['push_time'])) {
							$pushTime = date('Y-m-d H:i', $val['push_time']);
						}
						$result[$key]['push_time'] = $pushTime;
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
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群发确认群发详情接口
		 * @description     客户群发确认群发详情接口
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/chat-info
		 *
		 * @param id 必选 int 群列表id
		 * @param user_name 可选 string 群名称
		 * @param send 可选 int 送达状态默认传空数组、0未送达、1已送达、3客户已达上限
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return         {"error":0,"data":{"count":1,"info":[{"key":1,"id":1,"name":"吃货群","count":0,"send":0}],"send_num":1,"not_num":1,"limit_num":1}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    send_num int 已发送人数
		 * @return_param    not_num int 未发送人数
		 * @return_param    limit_num int 上限人数
		 * @return_param    name string 群名称
		 * @return_param    count string 入群人数
		 * @return_param    send string 0未送达、1已送达、3客户已达上限
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/24 17:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionChatInfo ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id');
				$user_name = \Yii::$app->request->post('user_name');
				$user_id   = \Yii::$app->request->post('user_id');
				$send      = \Yii::$app->request->post('send');
				$page      = \Yii::$app->request->post('page') ?: 1;
				$pageSize  = \Yii::$app->request->post('pageSize') ?: 15;
				if (empty($id) || empty($user_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$user      = WorkTagGroupUserStatistic::findOne($user_id);
				$statistic = WorkTagGroupStatistic::find()->alias('s')->leftJoin('{{%work_chat}} c', '`s`.`chat_id` = `c`.`id`');
				$statistic = $statistic->where(['s.send_id' => $id, 's.user_id' => $user->user_id]);
				if (!empty($user_name) || $user_name === '0') {
					$statistic = $statistic->andWhere(['like', 'c.name', $user_name]);
				}
				if (!empty($send) || $send == 0) {
					$statistic = $statistic->andWhere(['s.send' => $send]);
				}
				$count     = $statistic->count();
				$offset = ($page - 1) * $pageSize;
				$result = [];
				$info   = $statistic->select('s.id,s.chat_id,s.send')->limit($pageSize)->offset($offset)->orderBy(['s.id' => SORT_DESC]);
				$info     = $info->asArray()->all();
				$sendNum  = 0;
				$notNum   = 0;
				$limitNum = 0;
				if (!empty($info)) {
					foreach ($info as $key => $val) {
						if ($val['send'] == 0) {
							$notNum++;
						} elseif ($val['send'] == 1) {
							$sendNum++;
						} elseif ($val['send'] == 3) {
							$limitNum++;
						}
						$result[$key]['key']   = $val['id'];
						$result[$key]['id']    = $val['id'];
						$result[$key]['name']  = WorkChat::getChatName($val['chat_id']);
						$chatCount             = WorkChatInfo::find()->where(['status' => 1, 'chat_id' => $val['chat_id']])->count();
						$result[$key]['count'] = $chatCount;
						$result[$key]['send']  = $val['send'];
						$result[$key]['avatarData'] = WorkChat::getChatAvatar($val['chat_id']);
					}
				}

				return [
					'count'     => $count,
					'info'      => $result,
					'send_num'  => $sendNum,
					'not_num'   => $notNum,
					'limit_num' => $limitNum,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           同步成员发送数据
		 * @description     同步成员发送数据
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/refresh-data
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
			WorkGroupSending::getSendResult($this->corp->id);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-group-sending/
		 * @title           客户群群发红包获取客户群数量
		 * @description     客户群群发红包获取客户群数量
		 * @method   post
		 * @url  http://{host_name}/api/work-group-sending/get-chat-num
		 *
		 * @param corp_id  必选 string 企业微信标志
		 * @param user_ids 可选 array 员工集合
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chatNum int 群数量
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020/10/20
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetChatNum ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$user_ids = \Yii::$app->request->post('user_ids', []);

			$chatNum = WorkChat::find()->andWhere(['corp_id' => $this->corp->id, 'status' => [0, 1, 2, 3]]);
			if (!empty($user_ids)) {
				$chatNum = $chatNum->andWhere(['owner_id' => $user_ids]);
			}
			$chatNum = $chatNum->count();

			return ['chatNum' => $chatNum];
		}

	}