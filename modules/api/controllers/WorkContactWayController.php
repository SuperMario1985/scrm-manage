<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2020/1/7
	 * Time: 20:22
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Package;
	use app\models\User;
	use app\models\WorkContactWay;
	use app\models\WorkContactWayDate;
	use app\models\WorkContactWayDateUser;
	use app\models\WorkContactWayDateWelcome;
	use app\models\WorkContactWayGroup;
	use app\models\WorkContactWayLine;
	use app\models\WorkContactWayStatistic;
	use app\models\WorkContactWayUserLimit;
	use app\models\WorkDepartment;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkFollowUser;
	use app\models\WorkTag;
	use app\models\WorkUser;
	use app\models\WorkWelcome;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use moonland\phpexcel\Excel;
	use yii\helpers\Json;
	use yii\web\ForbiddenHttpException;
	use yii\web\MethodNotAllowedHttpException;

	class WorkContactWayController extends WorkBaseController
	{
		/**
		 * @param WorkExternalContactFollowUser $a
		 * @param WorkExternalContactFollowUser $b
		 *
		 * @return int
		 */
		private function cmp ($a, $b)
		{
			return 0 - strcmp($a->createtime, $b->createtime);
		}

		/**
		 * @param $id
		 *
		 * @return array
		 *
		 * @throws \app\components\InvalidDataException
		 */
		private function getContactWayInfo ($id)
		{
			$verifyAllDay = \Yii::$app->request->post('verify_all_day');
			$verifyDate   = \Yii::$app->request->post('verify_date');
			$week_user    = \Yii::$app->request->post('specialWeekList');
			$choose_date  = \Yii::$app->request->post('specialDateList');
			$type         = \Yii::$app->request->post('type') ?: 1;
			$scene        = \Yii::$app->request->post('scene') ?: 2;
			$style        = \Yii::$app->request->post('style') ?: 1;
			$remark       = \Yii::$app->request->post('remark') ?: '';
			$skip_verify  = \Yii::$app->request->post('skip_verify');
			//$state       = \Yii::$app->request->post('state') ?: '';
			$user      = \Yii::$app->request->post('user') ?: [];
			$party     = \Yii::$app->request->post('party') ?: [];
			$open_date = \Yii::$app->request->post('specialTime');
			$title     = \Yii::$app->request->post('title');
			// 前端值1需要认证0无需认证   微信接口意思是是否无需认证
			if (empty($skip_verify)) {
				$skip_verify = true; //无需认证
				if ($verifyAllDay == 2) {
					$flag = 0;
					//开启了分时段自动通过
					foreach ($verifyDate as $date) {
						$startTime = strtotime(date('Y-m-d') . ' ' . $date['start_time']);
						if ($date['end_time'] == '00:00') {
							$date['end_time'] = '23:59:59';
						}
						$endTime = strtotime(date('Y-m-d') . ' ' . $date['end_time']);
						if ($startTime <= time() && time() <= $endTime) {
							$flag = 1;//当前时间在分时段自动通过的范围内
						}
					}
					if ($flag == 0) {
						$skip_verify = false; //需认证
					}
				}
			} else {
				$skip_verify = false; //需认证
			}
			$state = $title;
			if (!empty($id)) {
				$contact = WorkContactWay::findOne($id);
				if (!empty($contact->state)) {
					$state = $contact->state;
				}
			}
			WorkContactWayDate::verifyData($week_user, $type);
			$wayDate        = WorkContactWayDate::getNowUser($week_user, $choose_date, $this->corp->id, $open_date, $type);
			$userId         = $wayDate['userId'];
			$partyId        = $wayDate['partyId'];
			$contactWayInfo = [
				'type'        => (int) $type,
				'scene'       => (int) $scene,
				'style'       => (int) $style,
				'remark'      => $remark,
				'skip_verify' => $skip_verify,
				'state'       => $state,
				'user'        => $userId,
				'party'       => $partyId,
			];
			\Yii::error($contactWayInfo, '$contactWayInfo');

			return $contactWayInfo;
		}

		/**
		 *
		 * @return mixed
		 *
		 * @throws InvalidParameterException
		 */
		private function getOtherInfo ()
		{
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
			$data['attachment_id']      = \Yii::$app->request->post('attachment_id') ?: 0;
			$data['material_sync']      = \Yii::$app->request->post('materialSync') ?: 0;
			$data['group_id']           = \Yii::$app->request->post('groupId') ?: 0;
			$data['uid']                = \Yii::$app->request->post('uid') ?: 0;
			$data['way_group_id']       = \Yii::$app->request->post('way_group_id') ?: NULL;
			$data['open_date']          = \Yii::$app->request->post('specialTime');
			$data['choose_date']        = \Yii::$app->request->post('specialDateList');
			$data['week_user']          = \Yii::$app->request->post('specialWeekList');

			$data['user_limit']        = \Yii::$app->request->post('user_limit');
			$data['is_limit']          = \Yii::$app->request->post('is_limit', 1);
			$data['verify_all_day']    = \Yii::$app->request->post('verify_all_day', 1);
			$data['verify_date']       = \Yii::$app->request->post('verify_date');
			$data['spare_employee']    = \Yii::$app->request->post('spare_employee');
			$data['is_welcome_date']   = \Yii::$app->request->post('is_welcome_date', 1);
			$data['is_welcome_week']   = \Yii::$app->request->post('is_welcome_week', 1);
			$data['welcome_date_list'] = \Yii::$app->request->post('welcome_date_list');
			$data['welcome_week_list'] = \Yii::$app->request->post('welcome_week_list');
			$data['skip_verify']       = \Yii::$app->request->post('skip_verify');
			$data['mini_title']        = trim($data['mini_title']);
			$data['text_content']      = trim($data['text_content']);
			if ($data['open_date'] && empty($data['choose_date'])) {
				throw new InvalidParameterException('选填时间段的成员不能为空！');
			}
			if (empty($data['week_user'])) {
				throw new InvalidParameterException('每周配置的企业成员不能为空！');
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           新增渠道活码
		 * @description     新增渠道活码
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/add
		 *
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
		 * @param verify_all_day int 可选 自动验证1全天开启2分时段
		 * @param spare_employee array 可选 备用员工
		 * @param is_welcome_date int 可选 欢迎语时段日期1关2开
		 * @param is_welcome_week int 可选 欢迎语时段周关2开
		 * @param is_limit int 可选 员工上限1关2开
		 * @param user_limit array 可选 员工上限数组
		 * @param verify_date array 可选 验证自动通过好友时间段
		 * @param welcome_week_list   array   欢迎语周
		 * @param welcome_date_list   array   欢迎语日期
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/5 18:16
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionAdd ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$otherInfo = $this->getOtherInfo();

			//套餐限制数量
			$packageLimit = Package::packageLimitNum($otherInfo['uid'], 'channelCode');
			if ($packageLimit > 0) {
				$codeCount = WorkContactWay::find()->alias('w')->leftJoin('{{%user_corp_relation}} u', '`w`.`corp_id` = `u`.`corp_id`')->where(['w.is_del' => 0, 'u.uid' => $otherInfo['uid']])->count();
				if ($codeCount >= $packageLimit) {
					throw new InvalidParameterException('渠道活码数量已达套餐限制！');
				}
			}

			$contact_way = WorkContactWay::findOne(['title' => $otherInfo['title'], 'corp_id' => $this->corp->id, 'is_del' => 0]);
			if (!empty($contact_way)) {
				throw new InvalidParameterException('渠道活码名称不能存在重复！');
			}

//			if (!empty($contactWayInfo['state'])) {
//				$state = WorkContactWay::findOne(['state' => $contactWayInfo['state'], 'corp_id' => $this->corp->id, 'is_del' => 0]);
//				if (!empty($state)) {
//					throw new InvalidParameterException('渠道活码名称的自定义参数不能存在重复！');
//				}
//			}
			WorkContactWay::verify($otherInfo);

			$contactWayInfo = $this->getContactWayInfo(0);

			WorkWelcome::verify($otherInfo, 1);

			$wayId = WorkContactWay::addWay($this->corp->id, $contactWayInfo, $otherInfo);

			return [
				'way_id' => $wayId,
			];
		}

		public function actionUpdate ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$configId = \Yii::$app->request->post('config_id') ?: '';

			if (empty($configId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$otherInfo = $this->getOtherInfo();

			$contact_way = WorkContactWay::find()->andWhere(['<>', 'id', $id])->andWhere(['title' => $otherInfo['title'], 'corp_id' => $this->corp->id, 'is_del' => 0])->one();

			if (!empty($contact_way)) {
				throw new InvalidParameterException('渠道活码名称不能存在重复！');
			}

			WorkContactWay::verify($otherInfo);

			$contactWayInfo = $this->getContactWayInfo($id);

			$contactWayInfo['config_id'] = $configId;

//			if (!empty($contactWayInfo['state'])) {
//				$state = WorkContactWay::find()->andWhere(['<>', 'id', $id])->andWhere(['state' => $contactWayInfo['state'], 'corp_id' => $this->corp->id, 'is_del' => 0])->one();
//				if (!empty($state)) {
//					throw new InvalidParameterException('渠道活码的自定义参数不能存在重复！');
//				}
//			}

			WorkWelcome::verify($otherInfo, 1);

			$wayId = WorkContactWay::updateWay($this->corp->id, $contactWayInfo, $otherInfo);

			return [
				'way_id' => $wayId,
			];
		}

		public function actionDel ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$configId = \Yii::$app->request->post('config_id') ?: '';

			if (empty($configId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			return WorkContactWay::delWay($this->corp->id, $configId);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取渠道活码列表
		 * @description     获取渠道活码列表
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/get-list
		 *
		 * @param corp_id 必选 string 企业唯一标志
		 * @param suite_id 必选 int 应用id
		 * @param type 可选 int 1单人2多人
		 * @param title 可选 string 名称
		 * @param way_group_id 可选 string 分组id
		 * @param page 可选 int 页码
		 * @param page_size 可选 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/2/7 9:59
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$page         = \Yii::$app->request->post('page') ?: 1;
			$pageSize     = \Yii::$app->request->post('pageSize') ?: 15;
			$type         = \Yii::$app->request->post('type') ?: 0;
			$title        = \Yii::$app->request->post('title');
			$user_id      = \Yii::$app->request->post('user_id', []);
			$way_group_id = \Yii::$app->request->post('way_group_id', NULL);
			$contactWay   = [];
			if (!empty($user_id)) {
				$Temp    = WorkDepartment::GiveUserIdsReturnDepartmentAndUserIds($user_id);
				$user_id = WorkDepartment::GiveDepartmentReturnUserData($this->corp->id, $Temp["department"], $Temp["user"], 0, true);
				$user_id = empty($user_id) ? [0] : $user_id;
			}
			$workContactWay = WorkContactWay::find()->alias("a")
				->leftJoin("{{%work_contact_way_date}} as b", "a.id =b.way_id")
				->leftJoin("{{%work_contact_way_date_user}} as c", "b.id = c.date_id")
				->andWhere(['a.corp_id' => $this->corp->id, 'a.is_del' => WorkContactWay::WAY_NOT_DEL]);
			if (!empty($type)) {
				$workContactWay = $workContactWay->andWhere(['a.type' => $type]);
			}
			if ($title !== '') {
				$workContactWay = $workContactWay->andWhere(['like', 'a.title', trim($title)]);
			}
			if (!empty($user_id)) {
				$str = '(';
				foreach ($user_id as $id) {
					$str .= 'LOCATE(\'"id":' . $id . ',\',c.user_key) > 0 or LOCATE(\'"id":"' . $id . '",\',c.user_key) > 0 or ';
				}
				$str            = rtrim($str, ' or ');
				$str            .= ")";
				$workContactWay = $workContactWay->andWhere(["or",["in", 'c.user_key', $user_id],$str]);
			}
			if (!empty($way_group_id)) {
				if (is_array($way_group_id)) {
					$workContactWay = $workContactWay->andWhere(['a.way_group_id' => $way_group_id]);
				} else {
					$idList         = WorkContactWayGroup::getSubGroupId($way_group_id, $this->corp->id);
					$workContactWay = $workContactWay->andWhere(['a.way_group_id' => $idList]);
				}
			}
			//获取所有的key
			$keys = [];
			if (empty($comefrom)) {
				$idList = $workContactWay->select('a.id')->all();
				if (!empty($idList)) {
					foreach ($idList as $idInfo) {
						array_push($keys, (string) $idInfo['id']);
					}
				}
			}
			$offset         = ($page - 1) * $pageSize;
			$count          = $workContactWay->groupBy("a.id")->count();
//			var_dump($workContactWay->select('a.*')->groupBy("a.id")->createCommand()->getRawSql());
			$workContactWay = $workContactWay->select('a.*')->groupBy("a.id")->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->all();
			if (!empty($workContactWay)) {
				foreach ($workContactWay as $way) {
					$wayInfo        = $way->dumpData(true, true);
					$wayInfo['key'] = $wayInfo['id'];
//					if ($wayInfo['skip_verify'] == 1) {
//						$wayInfo['skip_verify'] = 0; //无需认证
//					} else {
//						$wayInfo['skip_verify'] = 1; //需认证
//					}
					$tag_name = [];
					if (!empty($wayInfo['tag_ids'])) {
						$tag_ids = explode(',', $wayInfo['tag_ids']);
						foreach ($tag_ids as $tag_id) {
							$tag_id   = trim($tag_id, ',');
							$work_tag = WorkTag::findOne($tag_id);
							if (!empty($work_tag)) {
								if ($work_tag->is_del == WorkTag::DEL_TAG) {
									$work_tag->tagname .= "（已删除）";
								}
								array_push($tag_name, $work_tag->tagname);
							}
						}
					}
					$wayInfo['tag_ids'] = $tag_name;
					array_push($contactWay, $wayInfo);
				}
			}

			return [
				'count'            => $count,
				'contact_way_list' => $contactWay,
				'keys'             => $keys,
			];
		}

		public function actionGet ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$configId = \Yii::$app->request->post('config_id') ?: '';

			if (empty($configId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$workContactWay = WorkContactWay::findOne($configId);

			if (empty($workContactWay) || $workContactWay->corp_id != $this->corp->id) {
				throw new InvalidParameterException('参数不正确！');
			}
			$contact_way = $workContactWay->dumpData(true, true);

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

			$user_key = !empty($contact_way['user_key']) ? json_decode($contact_way['user_key'], true) : [];
			if (!empty($contact_way['user'])) {
				foreach ($contact_way['user'] as $key => $user) {
					if ($contact_way['type'] == 2) {
						foreach ($user_key as $val) {
							if ($val['id'] == $user['id']) {
								$contact_way['user'][$key]['user_key'] = $val['user_key'];
							}
						}
					}
				}
			}
			$contact_way['tag_ids']         = !empty($contact_way['tag_ids']) ? explode(',', $contact_way['tag_ids']) : [];
			$contact_way['specialDateList'] = WorkContactWayDate::getChooseDate($contact_way['id'], $contact_way['corp_id']);
			$contact_way['specialWeekList'] = WorkContactWayDate::getWeekUser($contact_way['id'], $contact_way['corp_id']);

			return [
				'contact_way' => $contact_way,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取活码添加的外部联系人
		 * @description     获取活码添加的外部联系人的详情
		 * @method   POST
		 * @url  http://{host_name}/api/work-contact-way/get-info
		 *
		 * @param suite_id 可选 int 应用ID（授权的必填）
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param config_id 必选 int 活码的ID
		 *
		 * @return          {"error":0,"data":{"external_info":[{"external_userid":"wm_4OwBwAAjv-n-yaKEkIhu0tJBFaLAA","name":"简迷离","position":null,"avatar":null,"corp_name":null,"corp_full_name":null,"type":1,"gender":1,"unionid":null,"follow_user":[{"userid":"c396459ea5b1d8ae203d26ec042313af","remark":null,"description":null,"createtime":"1578451026","tags":null,"remark_corp_name":null,"remark_mobiles":null,"state":"test2","del_type":0,"user_info":{"id":2,"corp_id":1,"userid":"c396459ea5b1d8ae203d26ec042313af","name":"c396459ea5b1d8ae203d26ec042313af","department":"2,3","order":"0,0","position":null,"mobile":null,"gender":"1","email":null,"is_leader_in_dept":"","avatar":"https://rescdn.qqmail.com/node/wwmng/wwmng/style/images/independent/DefaultAvatar$73ba92b5.png","thumb_avatar":"https://rescdn.qqmail.com/node/wwmng/wwmng/style/images/independent/DefaultAvatar$73ba92b5.png","telephone":null,"enable":null,"alias":null,"address":null,"extattr":null,"status":1,"qr_code":null,"is_del":0,"department_info":[{"id":2,"corp_id":1,"department_id":2,"name":"2","name_en":null,"parentid":1,"order":100000000,"is_del":0},{"loop":"……"}]},"is_lock":true},{"loop":"…… "}]},{"loop":"…… "}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    external_userid string 外部联系人的userid
		 * @return_param    name string 外部联系人的姓名或别名
		 * @return_param    position string 外部联系人的职位，如果外部企业或用户选择隐藏职位，则不返回，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    avatar string 外部联系人头像，第三方不可获取
		 * @return_param    corp_name string 外部联系人所在企业的简称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    corp_full_name string 外部联系人所在企业的主体名称，仅当联系人类型是企业微信用户时有此字段
		 * @return_param    type int 外部联系人的类型，1表示该外部联系人是微信用户，2表示该外部联系人是企业微信用户
		 * @return_param    gender int 外部联系人性别 0-未知；1-男性；2-女性
		 * @return_param    unionid string 外部联系人在微信开放平台的唯一身份标识（微信unionid），通过此字段企业可将外部联系人与公众号/小程序用户关联起来。仅当联系人类型是微信用户，且企业绑定了微信开发者ID有此字段。查看绑定方法
		 * @return_param    follow_user array 添加了此外部联系人的企业成员
		 * @return_param    follow_user.userid string 添加了此外部联系人的企业成员userid
		 * @return_param    follow_user.remark string 该成员对此外部联系人的备注
		 * @return_param    follow_user.description string 该成员对此外部联系人的描述
		 * @return_param    follow_user.createtime string 该成员添加此外部联系人的时间
		 * @return_param    follow_user.tags string 该成员添加此外部联系人所打标签的分组名称（标签功能需要企业微信升级到2.7.5及以上版本）
		 * @return_param    follow_user.remark_corp_name string 该成员对此客户备注的企业名称
		 * @return_param    follow_user.remark_mobiles string 该成员对此客户备注的手机号码，第三方不可获取
		 * @return_param    follow_user.state string 该成员添加此客户的渠道，由用户通过创建「联系我」方式指定
		 * @return_param    follow_user.del_type int 0：未删除；1：成员删除外部联系人；2：外部联系人删除成员
		 * @return_param    follow_user.is_lock boolean 是否为本规则的归属人
		 * @return_param    follow_user.user_info array 成员信息
		 * @return_param    follow_user.user_info.id int 成员ID
		 * @return_param    follow_user.user_info.corp_id int 授权的企业ID
		 * @return_param    follow_user.user_info.userid string 成员UserID。对应管理端的帐号，企业内必须唯一。不区分大小写，长度为1~64个字节
		 * @return_param    follow_user.user_info.name string 成员名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示名字
		 * @return_param    follow_user.user_info.department string 成员所属部门id列表，仅返回该应用有查看权限的部门id
		 * @return_param    follow_user.user_info.order string 部门内的排序值，默认为0。数量必须和department一致，数值越大排序越前面。值范围是[0, 2^32)
		 * @return_param    follow_user.user_info.position string 职务信息；第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.mobile string 手机号码，第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.gender string 性别。0表示未定义，1表示男性，2表示女性
		 * @return_param    follow_user.user_info.email string 邮箱，第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.is_leader_in_dept string 表示在所在的部门内是否为上级。；第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.avatar string 头像url。 第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.thumb_avatar string 头像缩略图url。第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.telephone string 座机。第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.enable int 成员启用状态。1表示启用的成员，0表示被禁用。注意，服务商调用接口不会返回此字段
		 * @return_param    follow_user.user_info.alias string 别名；第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.address string 地址
		 * @return_param    follow_user.user_info.extattr string 扩展属性，第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.status int 激活状态: 1=已激活，2=已禁用，4=未激活。已激活代表已激活企业微信或已关注微工作台（原企业号）。未激活代表既未激活企业微信又未关注微工作台（原企业号）
		 * @return_param    follow_user.user_info.qr_code string 员工个人二维码，扫描可添加为外部联系人(注意返回的是一个url，可在浏览器上打开该url以展示二维码)；第三方仅通讯录应用可获取
		 * @return_param    follow_user.user_info.is_del int 0：未删除；1：已删除
		 * @return_param    follow_user.user_info.department_info array 部门信息
		 * @return_param    follow_user.user_info.department_info.id int 部门ID
		 * @return_param    follow_user.user_info.department_info.corp_id int 授权的企业ID
		 * @return_param    follow_user.user_info.department_info.department_id int 创建的部门id
		 * @return_param    follow_user.user_info.department_info.name string 部门名称，此字段从2019年12月30日起，对新创建第三方应用不再返回，2020年6月30日起，对所有历史第三方应用不再返回，后续第三方仅通讯录应用可获取，第三方页面需要通过通讯录展示组件来展示部门名称
		 * @return_param    follow_user.user_info.department_info.name_en string 英文名称
		 * @return_param    follow_user.user_info.department_info.parentid int 父亲部门id。根部门为1
		 * @return_param    follow_user.user_info.department_info.order int 在父部门中的次序值。order值大的排序靠前。值范围是[0, 2^32)
		 * @return_param    follow_user.user_info.department_info.is_del int 0：未删除；1：已删除
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/1/9 17:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetInfo ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$configId = \Yii::$app->request->post('config_id') ?: '';
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 15;

			if (empty($configId)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$workContactWay = WorkContactWay::findOne($configId);

			if (empty($workContactWay) || $workContactWay->corp_id != $this->corp->id) {
				throw new InvalidParameterException('参数不正确！');
			}

			$info = [];

			if (!empty($workContactWay)) {
				if (!empty($workContactWay->workExternalContactFollowUsers)) {
					$workExternalContactFollowUsers = $workContactWay->workExternalContactFollowUsers;
					usort($workExternalContactFollowUsers, [$this, 'cmp']);

					foreach ($workExternalContactFollowUsers as $workExternalContactFollowUser) {
						$workExternalContact = $workExternalContactFollowUser->externalUser->dumpData();

						$workExternalContact['follow_user'] = [];
						$followUser                         = $workExternalContactFollowUser->dumpData(true, true);
						$followUser['is_lock']              = true;

						array_push($workExternalContact['follow_user'], $followUser);

						array_push($info, $workExternalContact);
					}
				}
			}

			$count  = count($info);//总条数
			$offset = ($page - 1) * $pageSize;//偏移量，当前页-1乘以每页显示条数
			$info   = array_slice($info, $offset, $pageSize);

			return [
				'external_info' => $info,
				'count'         => $count,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取客户接口
		 * @description     获取客户接口
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/get-custom
		 *
		 * @param way_id 必选 int 活码id
		 * @param page 可选 int 当前页
		 * @param pageSize 可选 int 页数
		 *
		 * @return          {"error":0,"data":{"count":"4","contact_way_list":[{"department_name":"小猪科技公司/销售","name":"Dove_Chen","key":55,"create_time":"2020-04-28 10:52:30"},{"department_name":"小猪科技公司/销售","name":"Dove_Chen","key":127,"create_time":"2020-03-05 17:46:23"},{"department_name":"小猪科技公司/销售","name":"😂","key":318,"create_time":"2020-02-29 17:27:11"},{"department_name":"小猪科技公司/销售","name":"空白","key":207,"create_time":"2020-02-14 19:24:55"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/11 11:06
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetCustom ()
		{
			$id       = \Yii::$app->request->post('way_id') ?: '';
			$page     = \Yii::$app->request->post('page') ?: 1;
			$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$line   = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 1]);
			$offset = ($page - 1) * $pageSize;
			$count  = $line->count();
			$line   = $line->limit($pageSize)->offset($offset)->orderBy(['create_time' => SORT_DESC])->all();
			$info   = [];
			if (!empty($line)) {
				/** @var WorkContactWayLine $v */
				foreach ($line as $v) {
					array_push($info, $v->dumpData(true));
				}
			}

			return [
				'count'            => $count,
				'contact_way_list' => $info,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取客户接口
		 * @description     获取客户接口
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/delete
		 *
		 * @param id 必选 int 活码id
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/7/20 16:58
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionDelete ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$way = WorkContactWay::delWay($id);

			return true;
		}

		public function actionGetUserList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$userList = [];

			if (!empty($this->corp->workFollowUsers)) {
				foreach ($this->corp->workFollowUsers as $workFollowUser) {
					if ($workFollowUser->status == WorkFollowUser::CAN_USE) {
						$userList[$workFollowUser->user->id] = $workFollowUser->user->name;
					}
				}
			}

			return [
				'user_list' => $userList,
			];
		}

		public function actionGetPartyList ()
		{
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}

			$partyList = [];

			if (!empty($this->corp->workDepartments)) {
				foreach ($this->corp->workDepartments as $workDepartment) {
					$partyList[$workDepartment->id] = $workDepartment->name;
				}
			}

			return [
				'party_list' => $partyList,
			];
		}

		/**
		 * showdoc
		 * @catalog          数据接口/api/work-contact-way/
		 * @title            分组列表
		 * @description      分组列表
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/group
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 *
		 * @return           {"error":0,"data":{"group":[{"id":"611","title":"1"},{"id":"610","title":"1"}]}}
		 *
		 * @return_param     error int 状态码
		 * @return_param     data array 结果数据
		 * @return_param     group array 分组数据
		 * @return_param     key string 分组id
		 * @return_param     parent_id string 父级id
		 * @return_param     title string 分组名称
		 * @return_param     sort string 排序
		 * @return_param     is_not_group string 0已分组、1未分组
		 * @return_param     num string 附件数
		 * @return_param     children array 子分组数据
		 *
		 * @remark           Create by PhpStorm. User: xingchangyu. Date: 2020-04-16 16:12
		 * @number           0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroup ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			if (empty($this->corp)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('参数不正确！');
			}
			$corp_id   = $this->corp->id;
			$groupData = WorkContactWayGroup::getGroupData($uid, $corp_id);
			$count     = WorkContactWay::find()->where(['corp_id' => $corp_id, 'is_del' => 0])->count();

			return [
				'group' => $groupData,
				'count' => $count
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           分组添加修改
		 * @description     分组添加修改
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/group-add
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param title 必选 string 分组名
		 * @param parent_id 可选 父级ID
		 * @param group_id 可选 string 分组id，修改时必选
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-04-16 16:13
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$parent_id = \Yii::$app->request->post('parent_id', NULL);
			if (empty($parent_id)) {
				$parent_id = NULL;
			}
			if (empty($uid) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			$title   = \Yii::$app->request->post('title', '');
			$title   = trim($title);
			if (empty($title)) {
				throw new InvalidDataException('请填写分组名称！');
			} elseif (mb_strlen($title, 'utf-8') > 15) {
				throw new InvalidDataException('分组名称不能超过15个字符！');
			} elseif ($title == '未分组' || $title == '所有') {
				throw new InvalidDataException('分组名称不能为“' . $title . '”，请更换！');
			}
			//父级为未分组的不让添加子分组
			if (!empty($parent_id)) {
				$group = WorkContactWayGroup::findOne($parent_id);
				if ($group->is_not_group == 1) {
					throw new InvalidDataException('此分组不允许添加子分组！');
				}
			}
			if (!empty($group_id)) {
				$group = WorkContactWayGroup::findOne($group_id);
				if ($group->is_not_group == 1) {
					throw new InvalidDataException('此分组不允许修改或添加子分组或删除！');
				}
				$group->update_time = DateUtil::getCurrentTime();
			} else {
				$group              = new WorkContactWayGroup();
				$group->uid         = $uid;
				$group->corp_id     = $corp_id;
				$group->parent_id   = $parent_id;
				$group->create_time = DateUtil::getCurrentTime();
				$info               = WorkContactWayGroup::find()->where(['uid' => $uid, 'corp_id' => $corp_id, 'parent_id' => $parent_id, 'status' => 1])->orderBy('sort desc')->one();
				if (!empty($info)) {
					$group->sort = $info->sort + 1;
				} else {
					$group->sort = 1;
				}
			}
			$group->title = $title;
			if (!$group->validate() || !$group->save()) {
				throw new InvalidDataException(SUtils::modelError($group));
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           分组排序
		 * @description     分组排序
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/group-sort
		 *
		 * @param uid 必选 string 用户ID
		 * @param parent_id 必选 string 父级ID
		 * @param group_id 必选 string 当前移动的id
		 * @param sort 必选 array 移动后分组id排序
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-04-16 16:59
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupSort ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid       = \Yii::$app->request->post('uid', 0);
			$parent_id = \Yii::$app->request->post('parent_id', 0);
			$group_id  = \Yii::$app->request->post('group_id', 0);
			$sortData  = \Yii::$app->request->post('sort');
			if (empty($uid) || empty($group_id) || empty($sortData)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$groupInfo = WorkContactWayGroup::findOne($group_id);
			if (!empty($groupInfo)) {
				//修改父级
				$groupInfo->parent_id   = !empty($parent_id) ? $parent_id : NULL;
				$groupInfo->update_time = DateUtil::getCurrentTime();
				$groupInfo->save();

				//排序
				$idData = array_reverse($sortData);
				foreach ($idData as $k => $id) {
					$group       = WorkContactWayGroup::findOne($id);
					$group->sort = $k + 1;
					$group->save();
				}
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           分组删除
		 * @description     分组删除
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/group-del
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param group_id 必选 string 分组id
		 * @param status 必选 string 状态0：删除
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-04-16 17:14
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupDel ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			if (empty($uid) || empty($group_id) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			$status  = \Yii::$app->request->post('status', 0);
			if (!in_array($status, [0, 1])) {
				throw new InvalidDataException('状态值不存在！');
			}
			$group = WorkContactWayGroup::findOne($group_id);
			if (empty($group)) {
				throw new InvalidDataException('此分组已不存在！');
			}
			//未分组不允许删除
			if ($group->is_not_group == 1) {
				throw new InvalidDataException('此分组不允许删除！');
			}
			//分组下面如果有子分组不允许删除
			$parentGroup = WorkContactWayGroup::findOne(['parent_id' => $group_id, 'status' => 1]);
			if (!empty($parentGroup)) {
				throw new InvalidDataException('此分组下面还有子分组，不允许删除，请先删除子分组！');
			}

			$group->status      = 0;
			$group->update_time = DateUtil::getCurrentTime();
			if (!$group->validate() || !$group->save()) {
				throw new InvalidDataException(SUtils::modelError($group));
			}
			//更新附件分组
			$notGroup    = WorkContactWayGroup::setNoGroup($uid, $corp_id);
			$no_group_id = $notGroup->id;

			if (!empty($no_group_id)) {
				$update_time = DateUtil::getCurrentTime();
				WorkContactWay::updateAll(['way_group_id' => $no_group_id, 'update_time' => $update_time], ['corp_id' => $corp_id, 'way_group_id' => $group_id]);
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           渠道活码换组
		 * @description     渠道活码换组
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/group-change
		 *
		 * @param uid 必选 string 用户ID
		 * @param corp_id 必选 string 企业的唯一ID
		 * @param group_id 必选 string 新分组id
		 * @param way_id 必选 string|array 附件id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-04-16 17:17
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGroupChange ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$way_id   = \Yii::$app->request->post('way_id', 0);
			$group_id = \Yii::$app->request->post('group_id', 0);
			if (empty($uid) || empty($way_id) || empty($group_id) || empty($this->corp)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$corp_id = $this->corp->id;
			//批量更新
			$update_time = DateUtil::getCurrentTime();
			WorkContactWay::updateAll(['way_group_id' => $group_id, 'update_time' => $update_time], ['corp_id' => $corp_id, 'id' => $way_id, 'is_del' => 0]);

			return true;
		}

		/**
		 * 跑历史数据
		 * @throws InvalidDataException
		 */
		public function actionUpdateOldData ()
		{
			WorkContactWay::updateOldData();
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           批量编辑欢迎语
		 * @description     批量编辑欢迎语
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/edit-welcome
		 *
		 * @param ids 必选 array 批量编辑的id
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/4/27 19:16
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
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
			$otherInfo['is_welcome_date']    = \Yii::$app->request->post('is_welcome_date', 1);
			$otherInfo['is_welcome_week']    = \Yii::$app->request->post('is_welcome_week', 1);
			$otherInfo['welcome_date_list']  = \Yii::$app->request->post('welcome_date_list');
			$otherInfo['welcome_week_list']  = \Yii::$app->request->post('welcome_week_list');

			if (empty($ids) || !is_array($ids)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$transaction = \Yii::$app->db->beginTransaction();
			try {
				$sync_attachment_id = 0;
				foreach ($ids as $id) {
					WorkContactWay::verify($otherInfo);
					$way                = WorkContactWay::findOne($id);
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
					$way->is_welcome_date = $otherInfo['is_welcome_date'];
					$way->is_welcome_week = $otherInfo['is_welcome_week'];
					$way->save();

					//设置分时段欢迎语
					if ($way->is_welcome_date == 2) {
						WorkContactWayDateWelcome::add($otherInfo['welcome_date_list'], $way->id, 2, $way->corp->id, $otherInfo['uid']);
					}
					//设置每周欢迎语
					if ($way->is_welcome_week == 2) {
						WorkContactWayDateWelcome::add($otherInfo['welcome_week_list'], $way->id, 1, $way->corp->id, $otherInfo['uid']);
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

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           批量编辑
		 * @description     批量编辑
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/edit-all
		 *
		 * @param ids 必选 array 批量编辑的id
		 * @param type int 可选 类型
		 * @param specialTime int 可选 true开启false关闭
		 * @param specialWeekList array 可选 周的数据
		 * @param specialDateList array 可选 日期的数据
		 * @param specialType array 可选 1批量编辑周2批量编辑成员
		 * @param way_data array 可选 用于成员上限
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/11 19:18
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws \Throwable
		 */
		public function actionEditAll ()
		{
			$ids         = \Yii::$app->request->post('ids');
			$type        = \Yii::$app->request->post('type');
			$week_user   = \Yii::$app->request->post('specialWeekList');
			$choose_date = \Yii::$app->request->post('specialDateList');
			$open_date   = \Yii::$app->request->post('specialTime');
			$specialType = \Yii::$app->request->post('specialType') ?: 1;
			$wayData     = \Yii::$app->request->post('way_data');
			if (empty($ids) || !is_array($ids)) {
				throw new InvalidParameterException('参数不正确！');
			}
			/**sym 刪除選擇部門但是查询需要回写*/
			WorkDepartment::FormatData($choose_date, $week_user);
			$contactWay = WorkContactWay::find()->where(['id' => $ids])->select('type')->asArray()->all();
			$userType   = $contactWay[0]['type'];
			if (empty($type)) {
				$data = array_column($contactWay, 'type');
				if (count(array_unique($data)) > 1) {
					throw new InvalidParameterException('只针对同一活码类型进行批量操作，请重新选择！');
				}

				return [
					'type' => $userType,
				];
			}
			$transaction = \Yii::$app->db->beginTransaction();
			try {
				foreach ($ids as $id) {
					$way     = WorkContactWay::findOne($id);
					$wayDate = [];
					if ($specialType == 1 || ($specialType == 2 && $open_date == 1)) {
						$wayDate = WorkContactWayDate::getNowUser($week_user, $choose_date, $way->corp_id, $open_date, $userType, 1);
					}
					if ($specialType == 2 && empty($open_date)) {
						$w = date("w");
						switch ($w) {
							case 0:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::SUNDAY_DAY);
								break;
							case 1:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::MONDAY_DAY);
								break;
							case 2:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::TUESDAY_DAY);
								break;
							case 3:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::WEDNESDAY_DAY);
								break;
							case 4:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::THURSDAY_DAY);
								break;
							case 5:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::FRIDAY_DAY);
								break;
							case 6:
								$wayDate = WorkContactWayDate::getEditDateUser($id, $way->type, WorkContactWayDate::SATURDAY_DAY);
								break;
						}
					}
					$contactWayNew = WorkContactWay::find()->where(['id' => $id])->asArray()->one();
					//判断是否开启了分时段自动通过
					$verify = !(boolean) $contactWayNew['skip_verify'];
					$verify = WorkContactWay::getVerify($contactWayNew, $verify, time());
					\Yii::error($verify, '$verify');
					$userId  = isset($wayDate['userId']) ? $wayDate['userId'] : '';
					$partyId = isset($wayDate['partyId']) ? $wayDate['partyId'] : '';
					if (!empty($userId) || !empty($partyId)) {
						$contactWayInfo = [
							'type'        => (int) $way->type,
							'scene'       => (int) $way->scene,
							'style'       => (int) $way->style,
							'remark'      => $way->remark,
							'skip_verify' => $verify,
							'state'       => $way->state,
							'user'        => $userId,
							'party'       => $partyId,
							'config_id'   => $way->config_id,
						];
						\Yii::error($contactWayInfo, '$contactWayInfo');
						WorkContactWay::editContact($way->corp_id, $contactWayInfo);
					}
					if ($specialType == 1) {
						if (!empty($week_user)) {
							WorkContactWayDate::setWeekData($week_user, $way->id);
						}
					} else {
						if ($open_date) {
							$way->open_date = 1;
							$way->save();
						} else {
							$way->open_date = 0;
							$way->save();
						}
						$wayDate = WorkContactWayDate::find()->where(['way_id' => $way->id, 'type' => 1])->all();
						if (!empty($wayDate)) {
							foreach ($wayDate as $date) {
								WorkContactWayDateUser::deleteAll(['date_id' => $date->id]);
							}
							WorkContactWayDate::deleteAll(['way_id' => $way->id, 'type' => 1]);
						}
						if ($way->open_date == 1) {
							//同步到渠道活码日期成员表
							$res = WorkContactWayDate::setData($choose_date, $way->id, 1);
							\Yii::error($res, '$res');
						}
					}
					if ($way->is_limit == 2 && !empty($wayData) && ($specialType == 1 || $specialType == 2)) {
						foreach ($wayData as $dt) {
							if ($dt['id'] == $way->id) {
								$userIdLimit   = [];
								$userDateLimit = [];

								if ($way->open_date == 1) {
									$specialDateList = WorkContactWayDate::getChooseDate($way->id, $way->corp_id);
									if (!empty($specialDateList)) {
										foreach ($specialDateList as $list) {
											WorkContactWay::getWeekDateUserId($list['time'], $userDateLimit);
										}
									}
								}
								$specialWeekList = WorkContactWayDate::getWeekUser($way->id, $way->corp_id);
								if (!empty($specialWeekList)) {
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['mon'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['tues'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['wednes'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['thurs'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['fri'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['satur'], $userIdLimit);
									WorkContactWay::getWeekDateUserId($specialWeekList[0]['sun'], $userIdLimit);
								}

								$userDateLimit = array_unique($userDateLimit);
								$userIdLimit   = array_unique($userIdLimit);
								if ($specialType == 1) {
									//当前编辑的是周
									WorkContactWayUserLimit::deleteLimit($userDateLimit, $way->id);
									WorkContactWayUserLimit::addData($userIdLimit, $userDateLimit, $way->id);
								} else {
									//当前编辑的是日期
									WorkContactWayUserLimit::deleteLimit($userIdLimit, $way->id);
									WorkContactWayUserLimit::addData($userDateLimit, $userIdLimit, $way->id);
								}
								if (empty($userIdLimit) && empty($userDateLimit)) {
									$way->spare_employee = '';
									$way->is_limit       = 1;
									$way->save();
								}
								unset($userIdLimit);
								unset($userDateLimit);
							}
						}
					}

				}

				$transaction->commit();
			} catch (\Exception $e) {
				$transaction->rollBack();
				\Yii::error($e->getMessage(), 'message');
				\Yii::error($e->getLine(), 'message');
				\Yii::error($e->getFile(), 'message');
				throw new InvalidDataException($e->getMessage());
			}

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           活码总计接口
		 * @description     活码总计接口
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/sum
		 *
		 * @param id 必选 int 活码id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    newDayCount int 今日新增客户数
		 * @return_param    newDayDeleteCount int 今日客户删除员工数
		 * @return_param    newDayDeleteByUserCount int 今日员工删除客户数
		 * @return_param    newDayIncreaseCount int 今日净增客户数
		 * @return_param    newTotalCount int 累计新增客户数
		 * @return_param    newTotalDeleteCount int 累计客户删除员工数
		 * @return_param    newTotalDeleteByUserCount int 累计员工删除客户数
		 * @return_param    newTotalIncreaseCount int 累计净增客户数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/7 11:45
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionSum ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$start_date              = date('Y-m-d');
			$newDayCount             = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 1])->andWhere(['>', 'create_time', $start_date])->count();
			$newDayDeleteCount       = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 2])->andWhere(['>', 'create_time', $start_date])->count();
			$newDayDeleteByUserCount = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 3])->andWhere(['>', 'create_time', $start_date])->count();
			$newDayIncreaseCount     = $newDayCount - $newDayDeleteCount;

			$newTotalCount             = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 1])->count();
			$newTotalDeleteCount       = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 2])->count();
			$newTotalDeleteByUserCount = WorkContactWayLine::find()->where(['way_id' => $id, 'type' => 3])->count();
			$newTotalIncreaseCount     = $newTotalCount - $newTotalDeleteCount;
			if ($newDayIncreaseCount <= 0) {
				$newDayIncreaseCount = 0;
			}
			if ($newTotalIncreaseCount <= 0) {
				$newTotalIncreaseCount = 0;
			}

			return [
				'newDayCount'               => $newDayCount,
				'newDayDeleteCount'         => $newDayDeleteCount,
				'newDayDeleteByUserCount'   => $newDayDeleteByUserCount,
				'newDayIncreaseCount'       => $newDayIncreaseCount,
				'newTotalCount'             => $newTotalCount,
				'newTotalDeleteCount'       => $newTotalDeleteCount,
				'newTotalDeleteByUserCount' => $newTotalDeleteByUserCount,
				'newTotalIncreaseCount'     => $newTotalIncreaseCount,
			];

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           批量更新活码轨迹
		 * @description     批量更新活码轨迹
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/update-line
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/7 10:53
		 * @number          0
		 *
		 */
		public function actionUpdateLine ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$followUser = WorkExternalContactFollowUser::find()->where(['<>', 'way_id', ''])->asArray()->all();
			if (!empty($followUser)) {
				foreach ($followUser as $user) {
					if (!empty($user['way_id'])) {
						$type       = 0;
						$creat_time = '';
						if ($user['delete_type'] == 0 && $user['del_type'] == 0) {
							$type = 1;
							if (!empty($user['createtime'])) {
								$creat_time = date('Y-m-d H:i:s', $user['createtime']);
							}
						}
						if ($user['del_type'] == 1) {
							$type = 3;
							if (!empty($user['del_time'])) {
								$creat_time = date('Y-m-d H:i:s', $user['del_time']);
							}
						}
						if ($user['delete_type'] == 2) {
							$type = 2;
							if (!empty($user['del_time'])) {
								$creat_time = date('Y-m-d H:i:s', $user['del_time']);
							}
						}
						if (($type == 1 || $type == 2 || $type == 3) && $creat_time != '') {
							WorkContactWayLine::add($user['way_id'], NULL, $type, $user['external_userid'], $user['user_id'], $creat_time);
						}

					}
				}
			}
			echo '更新完成';

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           更新活码日统计
		 * @description     更新活码日统计
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/update-data-day
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/8 9:24
		 * @number          0
		 *
		 */
		public function actionUpdateDataDay ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			//按日
			$line       = WorkContactWayLine::find()->orderBy(['id' => SORT_ASC])->one();
			$start_time = $line->create_time;
			$dateRange  = DateUtil::getDateFromRange($start_time, date('Y-m-d'));
			$contactWay = WorkContactWay::find()->asArray()->all();
			if (!empty($contactWay)) {
				foreach ($contactWay as $way) {
					if (!empty($dateRange)) {
						foreach ($dateRange as $date) {
							if ($date == date('Y-m-d')) {
								continue;
							}
							$workContactStatistic = WorkContactWayStatistic::findOne(['way_id' => $way['id'], 'is_month' => 0, 'data_time' => $date]);
							if (empty($workContactStatistic)) {
								$workContactStatistic    = new WorkContactWayStatistic();
								$wayLine                 = WorkContactWayLine::find()->where(['way_id' => $way['id']])->andFilterWhere(['between', 'create_time', $date, $date . ' 23:59:59'])->count();
								$newDayCount             = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 1])->andFilterWhere(['between', 'create_time', $date, $date . ' 23:59:59'])->count();
								$newDayDeleteCount       = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 2])->andFilterWhere(['between', 'create_time', $date, $date . ' 23:59:59'])->count();
								$newDayDeleteByUserCount = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 3])->andFilterWhere(['between', 'create_time', $date, $date . ' 23:59:59'])->count();
								$newDayIncreaseCount     = $newDayCount - $newDayDeleteCount;
								$new_contact_cnt         = 0;
								$negative_feedback_cnt   = 0;
								$delete_cnt              = 0;
								$increase_cnt            = 0;
								if (!empty($wayLine)) {
									$new_contact_cnt       = $newDayCount;
									$negative_feedback_cnt = $newDayDeleteCount;
									$delete_cnt            = $newDayDeleteByUserCount;
									$increase_cnt          = $newDayIncreaseCount;
								}
								if ($increase_cnt <= 0) {
									$increase_cnt = 0;
								}
								$workContactStatistic->way_id                = $way['id'];
								$workContactStatistic->new_contact_cnt       = $new_contact_cnt;
								$workContactStatistic->negative_feedback_cnt = $negative_feedback_cnt;
								$workContactStatistic->delete_cnt            = $delete_cnt;
								$workContactStatistic->increase_cnt          = $increase_cnt;
								$workContactStatistic->data_time             = $date;
								$workContactStatistic->is_month              = 0;
								$workContactStatistic->save();
							}

						}
					}
				}
			}
			echo '更新完成';

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           更新活码周统计
		 * @description     更新活码周统计
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/update-data-week
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/8 9:26
		 * @number          0
		 *
		 */
		public function actionUpdateDataWeek ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			//按日
			$line       = WorkContactWayLine::find()->orderBy(['id' => SORT_ASC])->one();
			$start_time = $line->create_time;
			$w          = date("w", strtotime($start_time));
			if ($w == 0) {
				$start_time = date('Y-m-d', strtotime('-6 day', strtotime($start_time)));
			} elseif ($w == 1) {
				$time       = explode(' ', $start_time);
				$start_time = $time[0];
			} elseif ($w == 2) {
				$start_time = date('Y-m-d', strtotime('-1 day', strtotime($start_time)));
			} elseif ($w == 3) {
				$start_time = date('Y-m-d', strtotime('-2 day', strtotime($start_time)));
			} elseif ($w == 4) {
				$start_time = date('Y-m-d', strtotime('-3 day', strtotime($start_time)));
			} elseif ($w == 5) {
				$start_time = date('Y-m-d', strtotime('-4 day', strtotime($start_time)));
			} elseif ($w == 6) {
				$start_time = date('Y-m-d', strtotime('-5 day', strtotime($start_time)));
			}
			$dateRange = DateUtil::getDateFromRange($start_time, date('Y-m-d'));
			$data      = DateUtil::getWeekFromRange($dateRange);
			$s_date1   = $data['s_date'];
			$e_date1   = $data['e_date'];
			$new_time  = [];
			foreach ($s_date1 as $k => $v) {
				foreach ($e_date1 as $kk => $vv) {
					if ($k == $kk) {
						array_push($new_time, $v . '&' . $vv);
					}
				}
			}
			$contactWay = WorkContactWay::find()->asArray()->all();
			$nowWeek    = date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600));
			if (!empty($contactWay)) {
				foreach ($contactWay as $way) {
					foreach ($new_time as $time) {
						$wayTime              = explode('&', $time);
						$workContactStatistic = WorkContactWayStatistic::findOne(['way_id' => $way['id'], 'is_month' => 2, 'data_time' => $wayTime[0]]);
						if ($wayTime[0] == $nowWeek) {
							continue;
						}
						if (empty($workContactStatistic)) {
							$workContactStatistic    = new WorkContactWayStatistic();
							$wayLine                 = WorkContactWayLine::find()->where(['way_id' => $way['id']])->andFilterWhere(['between', 'create_time', $wayTime[0], $wayTime[1] . ' 23:59:59'])->count();
							$newDayCount             = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 1])->andFilterWhere(['between', 'create_time', $wayTime[0], $wayTime[1] . ' 23:59:59'])->count();
							$newDayDeleteCount       = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 2])->andFilterWhere(['between', 'create_time', $wayTime[0], $wayTime[1] . ' 23:59:59'])->count();
							$newDayDeleteByUserCount = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 3])->andFilterWhere(['between', 'create_time', $wayTime[0], $wayTime[1] . ' 23:59:59'])->count();
							$newDayIncreaseCount     = $newDayCount - $newDayDeleteCount;
							$new_contact_cnt         = 0;
							$negative_feedback_cnt   = 0;
							$delete_cnt              = 0;
							$increase_cnt            = 0;
							if (!empty($wayLine)) {
								$new_contact_cnt       = $newDayCount;
								$negative_feedback_cnt = $newDayDeleteCount;
								$delete_cnt            = $newDayDeleteByUserCount;
								$increase_cnt          = $newDayIncreaseCount;
							}
							if ($increase_cnt <= 0) {
								$increase_cnt = 0;
							}
							$workContactStatistic->way_id                = $way['id'];
							$workContactStatistic->new_contact_cnt       = $new_contact_cnt;
							$workContactStatistic->negative_feedback_cnt = $negative_feedback_cnt;
							$workContactStatistic->delete_cnt            = $delete_cnt;
							$workContactStatistic->increase_cnt          = $increase_cnt;
							$workContactStatistic->data_time             = $wayTime[0];
							$workContactStatistic->is_month              = 2;
							$workContactStatistic->save();
						}
					}
				}
			}
			echo '更新完成';

			return true;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           更新活码月统计
		 * @description     更新活码月统计
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/update-data-month
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/8 9:26
		 * @number          0
		 *
		 */
		public function actionUpdateDataMonth ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$contactWay = WorkContactWay::find()->asArray()->all();
			if (!empty($contactWay)) {
				foreach ($contactWay as $way) {
					for ($i = 1; $i <= 4; $i++) {
						if ($i == 1) {
							$start_time = '2020-01-01';
							$end_time   = '2020-01-31 23:59:59';
						} elseif ($i == 2) {
							$start_time = '2020-02-01';
							$end_time   = '2020-02-29 23:59:59';
						} elseif ($i == 3) {
							$start_time = '2020-03-01';
							$end_time   = '2020-03-31 23:59:59';
						} elseif ($i == 4) {
							$start_time = '2020-04-01';
							$end_time   = '2020-04-30 23:59:59';
						}
						$workContactStatistic = WorkContactWayStatistic::findOne(['way_id' => $way['id'], 'is_month' => 1, 'data_time' => $start_time]);
						if (empty($workContactStatistic)) {
							$workContactStatistic    = new WorkContactWayStatistic();
							$wayLine                 = WorkContactWayLine::find()->where(['way_id' => $way['id']])->andFilterWhere(['between', 'create_time', $start_time, $end_time . ' 23:59:59'])->count();
							$newDayCount             = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 1])->andFilterWhere(['between', 'create_time', $start_time, $end_time . ' 23:59:59'])->count();
							$newDayDeleteCount       = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 2])->andFilterWhere(['between', 'create_time', $start_time, $end_time . ' 23:59:59'])->count();
							$newDayDeleteByUserCount = WorkContactWayLine::find()->where(['way_id' => $way['id'], 'type' => 3])->andFilterWhere(['between', 'create_time', $start_time, $end_time . ' 23:59:59'])->count();
							$newDayIncreaseCount     = $newDayCount - $newDayDeleteCount;
							$new_contact_cnt         = 0;
							$negative_feedback_cnt   = 0;
							$delete_cnt              = 0;
							$increase_cnt            = 0;
							if (!empty($wayLine)) {
								$new_contact_cnt       = $newDayCount;
								$negative_feedback_cnt = $newDayDeleteCount;
								$delete_cnt            = $newDayDeleteByUserCount;
								$increase_cnt          = $newDayIncreaseCount;
							}
							if ($increase_cnt <= 0) {
								$increase_cnt = 0;
							}
							$workContactStatistic->way_id                = $way['id'];
							$workContactStatistic->new_contact_cnt       = $new_contact_cnt;
							$workContactStatistic->negative_feedback_cnt = $negative_feedback_cnt;
							$workContactStatistic->delete_cnt            = $delete_cnt;
							$workContactStatistic->increase_cnt          = $increase_cnt;
							$workContactStatistic->data_time             = $start_time;
							$workContactStatistic->is_month              = 1;
							$workContactStatistic->save();
						}


					}
				}
			}
			echo '更新完成';

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           渠道活码统计
		 * @description     渠道活码统计
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/work-contact-way-statistic
		 *
		 * @param way_id 必选 int 渠道活码id
		 * @param s_date 必选 string 开始时间
		 * @param e_date 必选 string 开始时间
		 * @param s_week 可选 int 起始周
		 * @param type 必选 int 1天2周3月
		 * @param is_export 可选 int 0不导出1导出
		 *
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    new_contact_cnt int 新增客户数
		 * @return_param    negative_feedback_cnt int 删除/拉黑成员的客户数
		 * @return_param    delete_cnt int 员工删除的客户数
		 * @return_param    increase_cnt int 净增客户数
		 * @return_param    code_new_contact_cnt int 时间段内新增客户数
		 * @return_param    code_negative_feedback_cnt int 时间段内删除/拉黑成员的客户数
		 * @return_param    code_delete_cnt int 时间段内员工删除的客户数
		 * @return_param    code_increase_cnt int 时间段内净增客户数
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/5/8 13:57
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionWorkContactWayStatistic ()
		{
			$way_id    = \Yii::$app->request->post('way_id');
			$date1     = \Yii::$app->request->post('s_date');
			$date2     = \Yii::$app->request->post('e_date');
			$s_week    = \Yii::$app->request->post('s_week');
			$type      = \Yii::$app->request->post('type') ?: 1; //1天2周3月
			$is_export = \Yii::$app->request->post('is_export');
			if (empty($way_id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			if (empty($date1) || empty($date2)) {
				throw new InvalidParameterException('请传入日期！');
			}
			if ($type == 2 && empty($s_week)) {
				throw new InvalidParameterException('请传入起始周！');
			}
			$xData                      = [];//X轴
			$new_contact_cnt            = [];//新增客户数
			$negative_feedback_cnt      = [];//删除/拉黑成员的客户数
			$delete_cnt                 = [];//员工删除的客户数
			$increase_cnt               = [];//净增客户数
			$result                     = [];
			$code_new_contact_cnt       = 0;
			$code_negative_feedback_cnt = 0;
			$code_delete_cnt            = 0;
			$code_increase_cnt          = 0;
			switch ($type) {
				case 1:
					$data = DateUtil::getDateFromRange($date1, $date2);
					foreach ($data as $k => $v) {
						$statistic         = WorkContactWayStatistic::findOne(['way_id' => $way_id, 'is_month' => 0, 'data_time' => $v]);
						$new_contact_cnt_1 = $negative_feedback_cnt1 = $delete_cnt1 = $increase_cnt1 = 0;
						if (!empty($statistic)) {
							$new_contact_cnt_1      = $statistic->new_contact_cnt;
							$negative_feedback_cnt1 = $statistic->negative_feedback_cnt;
							$delete_cnt1            = $statistic->delete_cnt;
							$increase_cnt1          = $statistic->increase_cnt;
						}
						if ($increase_cnt1 <= 0) {
							$increase_cnt1 = 0;
						}
						$code_new_contact_cnt                += $new_contact_cnt_1;
						$code_negative_feedback_cnt          += $negative_feedback_cnt1;
						$code_delete_cnt                     += $delete_cnt1;
						$code_increase_cnt                   += $increase_cnt1;
						$result[$k]['new_contact_cnt']       = $new_contact_cnt_1;
						$result[$k]['negative_feedback_cnt'] = $negative_feedback_cnt1;
						$result[$k]['delete_cnt']            = $delete_cnt1;
						$result[$k]['increase_cnt']          = $increase_cnt1;
						$result[$k]['date_time']             = $v;
						array_push($new_contact_cnt, $new_contact_cnt_1);
						array_push($negative_feedback_cnt, $negative_feedback_cnt1);
						array_push($delete_cnt, $delete_cnt1);
						array_push($increase_cnt, $increase_cnt1);
					}
					$xData = $data;
					break;
				case 2:
					//按周
					$data    = DateUtil::getDateFromRange($date1, $date2);
					$data    = DateUtil::getWeekFromRange($data);
					$s_date1 = $data['s_date'];
					$e_date1 = $data['e_date'];
					$result  = [];
					foreach ($s_date1 as $k => $v) {
						foreach ($e_date1 as $kk => $vv) {
							if ($k == $kk) {
								if ($s_week == 53) {
									$s_week = 1;
								}
								$statistic         = WorkContactWayStatistic::findOne(['way_id' => $way_id, 'is_month' => 2, 'data_time' => $v]);
								$new_contact_cnt_1 = $negative_feedback_cnt1 = $delete_cnt1 = $increase_cnt1 = 0;
								if (!empty($statistic)) {
									$new_contact_cnt_1      = $statistic->new_contact_cnt;
									$negative_feedback_cnt1 = $statistic->negative_feedback_cnt;
									$delete_cnt1            = $statistic->delete_cnt;
									$increase_cnt1          = $statistic->increase_cnt;
								}
								if ($increase_cnt1 <= 0) {
									$increase_cnt1 = 0;
								}
								$code_new_contact_cnt                += $new_contact_cnt_1;
								$code_negative_feedback_cnt          += $negative_feedback_cnt1;
								$code_delete_cnt                     += $delete_cnt1;
								$code_increase_cnt                   += $increase_cnt1;
								$result[$k]['new_contact_cnt']       = $new_contact_cnt_1;
								$result[$k]['negative_feedback_cnt'] = $negative_feedback_cnt1;
								$result[$k]['delete_cnt']            = $delete_cnt1;
								$result[$k]['increase_cnt']          = $increase_cnt1;
								$result[$k]['date_time']             = $v . '~' . $vv . '(' . $s_week . '周)';
								array_push($new_contact_cnt, $new_contact_cnt_1);
								array_push($negative_feedback_cnt, $negative_feedback_cnt1);
								array_push($delete_cnt, $delete_cnt1);
								array_push($increase_cnt, $increase_cnt1);
								array_push($xData, $result[$k]['date_time']);
								$s_week++;
							}
						}
					}
					break;
				case 3:
					//按月
					$date   = DateUtil::getMoreMonth();
					$result = [];
					foreach ($date as $k => $v) {
						$date_time = explode('/', $v['time']);
						if ($date_time[1] <= 9) {
							$date_time[1] = '0' . $date_time[1];
						}
						$date_time1        = $date_time[0] . '-' . $date_time[1] . '-' . '01';
						$statistic         = WorkContactWayStatistic::findOne(['way_id' => $way_id, 'is_month' => 1, 'data_time' => $date_time1]);
						$new_contact_cnt_1 = $negative_feedback_cnt1 = $delete_cnt1 = $increase_cnt1 = 0;
						if (!empty($statistic)) {
							$new_contact_cnt_1      = $statistic->new_contact_cnt;
							$negative_feedback_cnt1 = $statistic->negative_feedback_cnt;
							$delete_cnt1            = $statistic->delete_cnt;
							$increase_cnt1          = $statistic->increase_cnt;
						}
						if ($increase_cnt1 <= 0) {
							$increase_cnt1 = 0;
						}
						$code_new_contact_cnt                += $new_contact_cnt_1;
						$code_negative_feedback_cnt          += $negative_feedback_cnt1;
						$code_delete_cnt                     += $delete_cnt1;
						$code_increase_cnt                   += $increase_cnt1;
						$result[$k]['new_contact_cnt']       = $new_contact_cnt_1;
						$result[$k]['negative_feedback_cnt'] = $negative_feedback_cnt1;
						$result[$k]['delete_cnt']            = $delete_cnt1;
						$result[$k]['increase_cnt']          = $increase_cnt1;
						$result[$k]['date_time']             = $date_time[0] . '-' . $date_time[1];
						array_push($new_contact_cnt, $new_contact_cnt_1);
						array_push($negative_feedback_cnt, $negative_feedback_cnt1);
						array_push($delete_cnt, $delete_cnt1);
						array_push($increase_cnt, $increase_cnt1);
						array_push($xData, $v['time']);
					}
					$xData  = array_reverse($xData);
					$result = array_reverse($result);
					break;

			}
			$seriesData = [
				[
					'name'   => '新增客户数',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $new_contact_cnt,
				],
				[
					'name'   => '被客户删除/拉黑人数',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $negative_feedback_cnt,
				],
				[
					'name'   => '删除人数',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $delete_cnt,
				],
				[
					'name'   => '净增人数',
					'type'   => 'line',
					'smooth' => true,
					'data'   => $increase_cnt,
				],
			];
			$legData    = ['新增客户数', '被客户删除/拉黑人数', '删除人数', '净增人数'];
			$info       = [
				'data'                       => $result,
				'legData'                    => $legData,
				'xData'                      => $xData,
				'seriesData'                 => $seriesData,
				'code_new_contact_cnt'       => $code_new_contact_cnt,
				'code_negative_feedback_cnt' => $code_negative_feedback_cnt,
				'code_delete_cnt'            => $code_delete_cnt,
				'code_increase_cnt'          => $code_increase_cnt,
			];
			if ($is_export == 1) {
				if (empty($result)) {
					throw new InvalidParameterException('暂无数据，无法导出！');
				}
				$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
				//创建保存目录
				if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
					return ['error' => 1, 'msg' => '无法创建目录'];
				}
				$columns    = ['date_time', 'new_contact_cnt', 'negative_feedback_cnt', 'delete_cnt', 'increase_cnt'];
				$headers    = [
					'date_time'             => '时间',
					'new_contact_cnt'       => '新增客户数',
					'negative_feedback_cnt' => '被客户删除/拉黑人数',
					'delete_cnt'            => '删除人数',
					'increase_cnt'          => '净增人数',
				];
				$contactWay = WorkContactWay::findOne($way_id);
				$fileName   = '【' . $contactWay->title . '】_' . date("YmdHis", time());
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

			return $info;

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           更改活码的验证状态
		 * @description     更改活码的验证状态
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/update-verify
		 *
		 * @param param 必选|可选 int|string|array 参数描述
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/6/22 11:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionUpdateVerify ()
		{
			ini_set('memory_limit', '4096M');
			set_time_limit(0);
			$contactWay = WorkContactWay::find()->where(['!=', 'id', 1])->all();
			if (!empty($contactWay)) {
				/** @var WorkContactWay $way */
				foreach ($contactWay as $way) {
					if (!empty($way->config_id)) {
						$info = WorkContactWay::returnContactInfo($way->corp_id, $way->config_id);
						if (!empty($info)) {
							if (isset($info['skip_verify']) && !empty($info['skip_verify'])) {
								$way->skip_verify = 0;
							} else {
								$way->skip_verify = 1;
							}
							$way->save();
						}
					}
				}
			}
			echo '更新完成';

		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取部门成员
		 * @description     获取部门成员
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/get-depart-user
		 *
		 * @param id 必选 array 部门id
		 *
		 * @return          {"error":0,"data":[{"user_id":"5","name":"邢长宇","limit":100},{"user_id":"96","name":"李云莉","limit":100},{"user_id":"176","name":"林凤","limit":100},{"user_id":"184","name":"林","limit":100}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_id int 成员id
		 * @return_param    name int 成员名称
		 * @return_param    limit int 上限
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/3 15:36
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetDepartUser ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id) || !is_array($id) || empty($this->corp->id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$user = WorkContactWay::getSubUser($id, $this->corp->id);

			return $user;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取部门成员
		 * @description     获取部门成员
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/get-depart-user
		 *
		 * @param id 必选 array 成员id
		 *
		 * @return          {"error":0,"data":[{"user_id":"5","name":"邢长宇","limit":100},{"user_id":"96","name":"李云莉","limit":100},{"user_id":"176","name":"林凤","limit":100},{"user_id":"184","name":"林","limit":100}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    user_id int 成员id
		 * @return_param    name int 成员名称
		 * @return_param    limit int 上限
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/3 16:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetUsers ()
		{
			$id = \Yii::$app->request->post('id');
			if (empty($id) || !is_array($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$user = WorkContactWay::getUsers($id);

			return $user;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           获取当前活码的人员添加客户上限
		 * @description     获取当前活码的人员添加客户上限
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/get-user-limit
		 *
		 * @param ids 必选 array 活码id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 活码名称
		 * @return_param    is_limit string 员工上限 1关 2开
		 * @return_param    data array 员工上限的具体数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/6 8:53
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetUserLimit ()
		{
			$id   = \Yii::$app->request->post('ids');
			$type = \Yii::$app->request->post('type'); //0周1日期
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$contactWay = WorkContactWay::find()->where(['id' => $id])->orderBy(['id' => SORT_DESC])->all();
			if (!empty($contactWay)) {
				if ($type == 0) {
					$type = 1;
				} elseif ($type == 2) {
					$type = [0, 1];
				} else {
					$type = 0;
				}
				$result = [];
				/** @var WorkContactWay $way */
				foreach ($contactWay as $key => $way) {
					$info   = [];
					$userId = [];
					//返回日期、周的成员
					$wayDate  = WorkContactWayDate::find()->where(['type' => $type, 'way_id' => $way->id])->all();
					$wayDate1 = WorkContactWayDate::find()->where(['type' => $type, 'way_id' => $way->id])->asArray()->all();
					if (!empty($wayDate)) {
						/** @var WorkContactWayDate $date */
						foreach ($wayDate as $date) {
							$wayDateUser = WorkContactWayDateUser::find()->where(['date_id' => $date->id])->all();
							if (!empty($wayDateUser)) {
								/** @var WorkContactWayDateUser $user */
								foreach ($wayDateUser as $user) {
									if (is_numeric($user->user_key)) {
										array_push($userId, $user->user_key);
									} else {
										$userKey = Json::decode($user->user_key, true);
										if (!empty($userKey)) {
											foreach ($userKey as $val) {
												array_push($userId, $val['id']);
											}
										}
									}
								}

							}
						}
					}
					if ($way->is_limit == 2) {
						$userLimit = WorkContactWayUserLimit::find()->where(['way_id' => $way->id])->all();
						if (!empty($userLimit)) {
							/** @var WorkContactWayUserLimit $limit */
							foreach ($userLimit as $limit) {
								if (in_array($limit->user_id, $userId)) {
									array_push($info, $limit->dumpData());
								}
							}
						}
					} else {
						$userId = array_unique($userId);
						$i      = 0;
						foreach ($userId as $k => $val) {
							$workUser = WorkUser::findOne($val);
							$name     = '';
							if (!empty($workUser)) {
								$name = $workUser->name;
							}
							$info[$i]['user_id'] = $val;
							$info[$i]['name']    = $name;
							$info[$i]['limit']   = 100;
							$i++;
						}
					}
					$result[$key]['id']             = $way->id;
					$result[$key]['name']           = $way->title;
					$result[$key]['type']           = $way->type;
					$result[$key]['is_limit']       = $way->is_limit;
					$result[$key]['spare_employee'] = !empty($way->spare_employee) ? Json::decode($way->spare_employee, true) : [];
					$result[$key]['user_limit']     = $info;
				}

				return $result;
			} else {
				throw new InvalidParameterException('当前活码不存在！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/work-contact-way/
		 * @title           批量设置员工上限
		 * @description     批量设置员工上限
		 * @method   post
		 * @url  http://{host_name}/api/work-contact-way/set-user-limit
		 *
		 * @param way_data 必选 array 上限数据
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return bool
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/9/8 20:11
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionSetUserLimit ()
		{
			$wayData = \Yii::$app->request->post('way_data');
			if (empty($wayData)) {
				throw new InvalidParameterException('请设置员工上限！');
			}
			foreach ($wayData as $data) {
				if ($data['is_limit'] == 2) {
					$otherInfo             = [];
					$otherInfo['is_limit'] = 2;
					//验证成员添加上限
//					$userLimit                   = [];
//					$userLimit['user_id']        = $data['user_id'];
//					$userLimit['name']           = $data['name'];
//					$userLimit['limit']          = $data['limit'];
					$otherInfo['user_limit']     = $data['user_limit'];
					$otherInfo['spare_employee'] = $data['spare_employee'];
					WorkContactWay::verify($otherInfo);
					unset($otherInfo);
					WorkContactWayUserLimit::add($data['user_limit'], $data['id']);
//					unset($userLimit);
				}
				$way = WorkContactWay::findOne($data['id']);
				if (!empty($way)) {
					if ($data['is_limit'] == 2) {
						$way->spare_employee = !empty($data['spare_employee']) ? Json::encode($data['spare_employee']) : '';
					} else {
						$way->spare_employee = '';
					}
					$way->is_limit = $data['is_limit'];
					$way->save();
				}
				$everyContactWay = WorkContactWay::findOne($data['id']);
				if (!empty($everyContactWay) && $everyContactWay->is_limit == 2) {
					//根据添加上限再次生成活码
					WorkContactWay::getNewCode($everyContactWay->id, $everyContactWay->corp_id, $everyContactWay->open_date);
				}
			}

			return true;

		}

		public function actionGetQrcode ()
		{
			if (\Yii::$app->request->isPost) {
				$codeId = \Yii::$app->request->post('id', '');
				if (empty($codeId)) {
					throw new ForbiddenHttpException('非法的请求');
				}

				$contactWay = WorkContactWay::findOne(['id' => $codeId, 'corp_id' => $this->corp->id]);
				if (empty($contactWay)) {
					throw new InvalidDataException('参数不正确');
				}

				return [
					'wx_qrcode'    => $contactWay->qr_code,
					'local_qrcode' => \Yii::$app->params['site_url'] . $contactWay->local_path,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}