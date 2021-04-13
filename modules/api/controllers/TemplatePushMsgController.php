<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/9/17
	 * Time: 09:39
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidParameterException;
	use app\components\NotAllowException;
	use app\models\Template;
	use app\models\Fans;
	use app\models\TemplatePushMsg;
	use app\models\WxAuthorizeInfo;
	use app\models\WxAuthorize;
	use app\modules\api\components\AuthBaseController;
	use app\queue\TemplateJob;
	use callmez\wechat\sdk\Wechat;
	use yii\db\Exception;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\helpers\Json;

	class TemplatePushMsgController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'edit-template-message'   => ['POST'],
						'get-all-template-msg'    => ['POST'],
						'post-template-message'   => ['POST'],
						'delete-template-message' => ['POST'],
					],
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           模板消息列表
		 * @description     模板消息列表
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/get-all-template-msg
		 *
		 * @param wx_id 必选 int 公众号的原始ID
		 * @param page 可选 int 当前页
		 * @param date 可选 string 发送日期
		 * @param title 可选 string 消息名称
		 * @param status 可选 int 状态1、未发送2、已发送3、发送失败
		 *
		 * @return          {"error":0,"data":{"count":"1","info":[{"id":"1","head_img":"http://wx.qlogo.cn/mmopen/vSgib1JZE1myUFgFic1R8x0hxt3OLFyNRKw09O6r4skpw3MkszKrNe0olL1Feqic2ZtQaasXGXw8aNf7ygJESlHn1nyib29OOn3A/0","nick_name":"彼岸智慧店铺","msg_title":"消息1","send_people":"按条件筛选的粉丝","tpl_name":"余额不足提醒","push_time":"2019-10-13 18:52:00","fans_num":"0","status":"未发送","show":1}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 消息id
		 * @return_param    head_img string 公众号头像
		 * @return_param    nick_name string 公众号名称
		 * @return_param    msg_title string 消息名称
		 * @return_param    send_people string 发送对象
		 * @return_param    tpl_id string 模板id
		 * @return_param    tpl_name string 模板名称
		 * @return_param    push_time string 发送时间
		 * @return_param    err_msg string 错误信息
		 * @return_param    fans_num int 发送成功人数
		 * @return_param    will_fans_num int 预计到达人数
		 * @return_param    status string 发送状态
		 * @return_param    show int 1显示4个按钮2只显示复制和详情
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 14:30
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionGetAllTemplateMsg ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				try {
					$page     = \Yii::$app->request->post('page');
					$date     = \Yii::$app->request->post('date');
					$title    = \Yii::$app->request->post('title');
					$status   = \Yii::$app->request->post('status'); //0 全部 1未发送 2 已发送 3 发送失败
					$pageSize = \Yii::$app->request->post('pageSize'); // 页数
					$page     = !empty($page) ? $page : 1;
					$pageSize = !empty($pageSize) ? $pageSize : 10;
					$offset   = ($page - 1) * $pageSize;
					$data     = TemplatePushMsg::find();
					$data     = $data->andWhere(['author_id' => $this->wxAuthorInfo->author_id]);
					if (!empty($status)) {
						switch ($status) {
							case 1:
								$status = 0;
								break;
							case 2:
								$status = 1;
								break;
							case 3:
								$status = 2;
								break;
							case 4:
								$status = 3;
								break;
						}
						$data->andWhere(['status' => $status]);
					}
					if (!empty($title)) {
						$data = $data->andWhere(['like', 'msg_title', trim($title)]);
					}
					if (!empty($date)) {
						$data   = $data->andFilterWhere(['between', 'push_time', $date, $date.' 23:59:59']);
					}
					$count  = $data->count();
					$info   = $data->limit($pageSize)->offset($offset)->orderBy('id DESC')->all();
					$result = [];
					foreach ($info as $msg) {
						$msgData              = $msg->dumpData();
						$wxInfo               = WxAuthorizeInfo::findOne(['author_id' => $msg->author->author_id]);
						$msgData['head_img']  = $wxInfo->head_img;
						$msgData['nick_name'] = $wxInfo->nick_name;
						if ($msg->push_type == 1) {
							$msgData['send_people'] = "按条件筛选的粉丝";
						} elseif ($msg->push_type == 2) {
							$msgData['send_people'] = "全部粉丝";
						} elseif ($msg->push_type == 3) {
							$msgData['send_people'] = "指定粉丝";
						}
						$temp                = Template::findOne(['id' => $msg->template_id]);
						$msgData['tpl_id']   = $temp->id;
						$msgData['tpl_name'] = $temp->title;
						if ($msg->status == 0) {
							$msgData['status'] = '未发送';
						} elseif ($msg->status == 1) {
							$msgData['status'] = '已发送';
						} elseif ($msg->status == 2) {
							$msgData['status'] = '发送失败';
						} elseif ($msg->status == 3) {
							$msgData['status'] = '发送中';
						}
						$msgData['show'] = 1;
						if (strtotime($msg->push_time) <= time() || $msg->status != 0) {
							$msgData['show'] = 2;
						}
						array_push($result, $msgData);
					}

					return [
						'count' => $count,
						'info'  => $result,
					];
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           获取模板消息详情
		 * @description     获取模板消息详情
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/edit-template-message
		 *
		 * @param id 必选 int 模板消息id
		 *
		 * @return          {"error":0,"data":{"template_id":2,"template_data":{"first":{"value":"","color":"#000000"},"remark":{"value":"","color":"#000000"},"keyword1":{"value":"小猪科技","color":"#bd10e0"},"keyword2":{"value":"500","color":"#f5a623"}},"head_img":"http://wx.qlogo.cn/mmopen/vSgib1JZE1myUFgFic1R8x0hxt3OLFyNRKw09O6r4skpw3MkszKrNe0olL1Feqic2ZtQaasXGXw8aNf7ygJESlHn1nyib29OOn3A/0","nick_name":"彼岸智慧店铺","wx_id":1,"msg_title":"消息1","redirect_type":1,"url":"https://www.baidu.com","appid":null,"pagepath":null,"push_type":1,"sex":"1","stime":"2019-10-01 18:52","etime":"2019-10-11 18:52","province":"安徽","city":"合肥","openids":"","send_type":"2","tag_ids":["30","31"],"push_time":"2019-10-13 18:52:00"}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    template_id int 模板消息id
		 * @return_param    template_data string 模板消息数据
		 * @return_param    wx_id string 公众号原始id
		 * @return_param    head_img string 公众号头像
		 * @return_param    nick_name string 公众号名称
		 * @return_param    msg_title string 消息名称
		 * @return_param    redirect_type int 跳转类型，1：链接、2：小程序
		 * @return_param    url string url地址
		 * @return_param    appid string appid
		 * @return_param    pagepath string 小程序路径
		 * @return_param    push_type int 1按条件筛选、2全部粉丝、3指定粉丝
		 * @return_param    sex int 1、男2、女0、未知
		 * @return_param    stime string 开始时间
		 * @return_param    etime string 结束时间
		 * @return_param    province int 省名称
		 * @return_param    city int 市名称
		 * @return_param    openids string openids
		 * @return_param    send_type int 1立即发送2指定发送
		 * @return_param    tag_ids array 标签id
		 * @return_param    push_time string 发送时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 17:10
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionEditTemplateMessage ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$info      = [];
				$tmpMsg    = TemplatePushMsg::findOne(['id' => $id]);
				$push_time = $tmpMsg->push_time;
				if (strtotime($push_time) <= time() && $tmpMsg->status!=0) {
					throw new InvalidParameterException('消息已发送，不可修改！');
				}
				$creditName = '';
				$creditChange = '';
				//模板内容
				$template_data      = $tmpMsg->template_data;
				$temp_data          = json_decode($template_data, true);
				$info['template_id']   = $tmpMsg->template_id;
				$info['template_data'] = $temp_data;
				$info['template_content'] = json_decode($tmpMsg->template_content, true);
				//公众号信息
				$infoWx                = WxAuthorizeInfo::find()->alias('wxau');
				$infoWx                = $infoWx->leftJoin('{{%wx_authorize}} wx', '`wxau`.`author_id` = `wx`.`author_id`');
				$infoWx                = $infoWx->andWhere(['wx.author_id' => $tmpMsg->author_id]);
				$infoWx                = $infoWx->all();
				$info['wx_id']         = $infoWx[0]['author_id'];
				$info['head_img']      = $infoWx[0]['head_img'];
				$info['nick_name']     = $infoWx[0]['nick_name'];
				$info['msg_title']     = $tmpMsg->msg_title;
				$info['redirect_type'] = $tmpMsg->redirect_type;
				$info['url']           = $tmpMsg->url;
				$info['appid']         = $tmpMsg->appid;
				$info['pagepath']      = $tmpMsg->pagepath;
				$info['push_type']     = $tmpMsg->push_type;
				$push_rule             = $tmpMsg->push_rule;
				$push_rule             = json_decode(base64_decode($push_rule), true);
				$info['sex']           = $push_rule['sex'];
				$info['stime']         = $push_rule['stime'];
				$info['etime']         = $push_rule['etime'];
				$info['province']      = $push_rule['province'];
				$info['city']          = $push_rule['city'];
				$info['openids']       = $push_rule['openids'];
				$info['send_type']     = $push_rule['send_type'];
				if(!empty($push_rule['tag_ids'])){
					$info['tag_ids']       = explode(',', $push_rule['tag_ids']);
				}else{
					$info['tag_ids'] = [];
				}
				$info['push_time']     = strtotime($tmpMsg->push_time);
				$info['credit_name']     = $creditName;
				$info['credit_change']     = $creditChange;

				return $info;
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           列表详情预览
		 * @description     列表详情预览
		 * @method   请求方式
		 * @url  http://{host_name}/api/template-push-msg/preview
		 *
		 * @param id 必选 int id值
		 *
		 * @return          {"error":0,"data":[{"first":{"value":"1 {nickname} 2 {nickname} 3","color":"#ff0000"},"remark":{"value":" {nickname} 8 {nickname}9  {nickname} ","color":"#ff0000"},"keyword1":{"value":"4 {nickname} ","color":"#ff0000"},"keyword2":{"value":"5 {nickname} 6 {nickname}  {nickname} ","color":"#ff0000"},"keyword3":{"value":"7","color":"#ff0000"}}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/18 16:44
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionPreview ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$tmpMsg = TemplatePushMsg::findOne(['id' => $id]);
				//模板内容
				$temp_data = json_decode($tmpMsg->template_content, true);
				return [
					'info'  => $temp_data,
				];
			} else {
				throw new NotAllowException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           设置模板消息
		 * @description     设置模板消息
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/post-template-message
		 *
		 * @param id 可选 int 修改时传列表id
		 * @param wx_id 必选 int 公众号的原始ID
		 * @param msg_title 必选 string 消息名称
		 * @param template_id 必选 int 模板id
		 * @param template_data 必选 string 模板内容
		 * @param redirect_type 必选 int 跳转类型1链接、2小程序
		 * @param url 可选 string 跳转地址
		 * @param appid 可选 string 跳转到的小程序appid
		 * @param pagepath 可选 string 跳转到小程序的具体页面路径
		 * @param push_type 必选 int 1按条件筛选、2全部粉丝、3指定粉丝
		 * @param sex 必选 int 1男、2女、0未知、-1全部
		 * @param s_time 可选 int 开始时间、格式：2019-10-10 12:15
		 * @param e_time 可选 int 结束时间、格式：2019-10-10 12:15
		 * @param province 可选 int 省名称
		 * @param city 可选 int 市名称
		 * @param tag_ids 可选 int 标签多个,分割
		 * @param send_type 必选 int 1立即发送、2指定时间
		 * @param send_time 可选 int 指定发送时间
		 * @param openids 可选 int 指定粉丝多个;分割
		 * @param credit_change 可选 string 模板为积分提醒时积分前的字段
		 * @param credit_name 可选 string 模板为积分提醒时余额前的字段
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 9:13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 */
		public function actionPostTemplateMessage(){
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$id = \Yii::$app->request->post('id');
				$msg_title = \Yii::$app->request->post('msg_title');
				$template_id = \Yii::$app->request->post('template_id');
				$template_data = \Yii::$app->request->post('template_data');
				$redirect_type = \Yii::$app->request->post('redirect_type');
				$url = \Yii::$app->request->post('url');
				$appid = \Yii::$app->request->post('appid');
				$pagepath = \Yii::$app->request->post('pagepath');
				$push_type = \Yii::$app->request->post('push_type'); // 1 按条件筛选 2 全部粉丝 3 指定粉丝
				$sex = \Yii::$app->request->post('sex'); //1 男 2 女 0未知 -1 全部
				$stime = \Yii::$app->request->post('s_time');
				$etime = \Yii::$app->request->post('e_time');
				$province = \Yii::$app->request->post('province');
				$city = \Yii::$app->request->post('city');
				$tag_ids = \Yii::$app->request->post('tag_ids');
				$send_type = \Yii::$app->request->post('send_type'); //1立即发送 2指定时间
				$send_time = \Yii::$app->request->post('send_time');
				$openids = \Yii::$app->request->post('openids');
				$credit_change = \Yii::$app->request->post('credit_change');
				$credit_name = \Yii::$app->request->post('credit_name');
				if(empty($msg_title)){
					throw new InvalidParameterException('消息名称不能为空！');
				}
				if(empty($id)){
					$title = TemplatePushMsg::find()->where(['msg_title'=>$msg_title,'author_id'=>$this->wxAuthorInfo->author_id])->one();
				}else{
					$title = TemplatePushMsg::find()->where(['msg_title'=>$msg_title,'author_id'=>$this->wxAuthorInfo->author_id])->andWhere(['<>','id', $id])->one();
				}
				if(!empty($title)){
					throw new InvalidParameterException('消息名称不能重复！');
				}
				if(empty($template_id)){
					throw new InvalidParameterException('请选择模板！');
				}
				if(empty($template_data)){
					throw new InvalidParameterException('请填写模板内容！');
				}
				if(empty($redirect_type)){
					throw new InvalidParameterException('请设置跳转类型！');
				}
				if($redirect_type==1){
					if(empty($url)){
						throw new InvalidParameterException('请填写跳转地址！');
					}else{
						$preg = "/^http(s)?:\\/\\/.+/";
						if(!preg_match($preg,$url)){
							throw new InvalidParameterException('跳转链接地址不正确！');
						}
					}
				}elseif ($redirect_type==2){
					if(empty($appid) || empty($pagepath)){
						throw new InvalidParameterException('请填写小程序信息！');
					}
				}
				if($push_type==3){
					if(empty($openids)){
						throw new InvalidParameterException("指定粉丝不能为空！");
					}
					$openids = str_replace("；",";",$openids);
					$count = explode(';',$openids);
					if(count($count)>8){
						throw new InvalidParameterException("指定粉丝数量不能超过8个！");
					}
				}
				if($send_type==2){
					//指定时间
					if($send_time<=time()){
						throw new InvalidParameterException("群发时间小于当前时间，无法提交，请重新设置群发时间");
					}
				}
				if(!empty($id)){
					$tmpMsg = TemplatePushMsg::findOne($id);
					$status = $tmpMsg->status;
					if($status==1){
						throw new InvalidParameterException("当前模板消息已发送，无法修改");
					}
				}
				$data['author_id'] = $this->wxAuthorInfo->author_id;
				$data['id'] = $id;
				$data['msg_title'] = $msg_title;
				$data['template_id'] = $template_id;
				$data['template_data'] = $template_data;
				$data['redirect_type'] = $redirect_type;
				$data['url'] = $url;
				$data['appid'] = $appid;
				$data['pagepath'] = $pagepath;
				$data['push_type'] = $push_type;
				$data['sex'] = $sex;
				$data['stime'] = $stime;
				$data['etime'] = $etime;
				$data['province'] = $province;
				$data['city'] = $city;
				$data['tag_ids'] = $tag_ids;
				$data['send_type'] = $send_type;
				$data['send_time'] = date("Y-m-d H:i:s",$send_time);;
				$data['openids'] = $openids;
				$data['credit_change'] = $credit_change;
				$data['credit_name'] = $credit_name;
				if(!empty($openids)){
					$openids = explode(';',$openids);
					if (count($openids) != count(array_unique($openids))) {
						throw new InvalidParameterException("openid存在重复值，请重新输入");
					}
					foreach ($openids as $openid){
						$fans = Fans::find()->andWhere(['subscribe'=>1,'openid'=>$openid])->one();
						if(empty($fans)){
							throw new InvalidParameterException("粉丝不存在，可能因没有同步粉丝数据或是其他公众号的粉丝等原因");
						}
					}
				}
				$res = TemplatePushMsg::setTemplateMessage($data);
				if ($res) {
					try{
						$appid       = $this->wxAuthorInfo->authorizer_appid;
						//立即发送
						if($send_type==1){
							$jobId         = \Yii::$app->template->push(new TemplateJob([
								'template_push_msg_id' => $res,
								'appid' => $appid
							]));
						}else{
							//指定时间发送
							$second = $send_time-time();
							$jobId         = \Yii::$app->template->delay($second)->push(new TemplateJob([
								'template_push_msg_id' => $res,
								'appid' => $appid
							]));
						}
						\Yii::error($jobId,'jobId_msg');
						\Yii::error($res,'res_msg');
						if($jobId){
							$tmp           = TemplatePushMsg::findOne(['id'=>$res]);
							if($tmp){
								$queue_id = $tmp->queue_id;
								if (!empty($queue_id)) {
									\Yii::$app->queue->remove($queue_id);
								}
								$tmp->queue_id = $jobId;
								$push_rule     = $tmp->push_rule;
								$push_rule     = json_decode(base64_decode($push_rule), true);
								if ($push_rule['send_type'] == 1) {
									$tmp->status = 3; //发送中
								}
								$tmp->save();
							}
						}

					} catch (\Exception $e) {
						throw new InvalidParameterException($e->getMessage());
					}
					return true;
				} else {
					if (empty($id)) {
						throw new InvalidParameterException('创建失败！');
					} else {
						throw new InvalidParameterException('修改失败！');
					}
				}

			}else{
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           获取模板标题
		 * @description     获取模板标题
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/get-title
		 *
		 * @param wx_id 必选 int 公众号的原始ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/2 17:03
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionGetTitle ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$templateMsg = TemplatePushMsg::find()->where(['author_id' => $this->wxAuthorInfo->author_id])->select('msg_title')->asArray()->all();
				$tag_name    = [];
				if (!empty($templateMsg)) {
					$tag_name = array_column($templateMsg, 'msg_title');
				}

				return $tag_name;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           删除模板消息
		 * @description     删除模板消息
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/delete-template-message
		 *
		 * @param id 必选 int 模板消息id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/10/12 17:42
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \Throwable
		 */
		public function actionDeleteTemplateMessage ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$tmpMsg = TemplatePushMsg::findOne(['id' => $id]);
				try {
					$push_time = $tmpMsg->push_time;
					$queue_id  = $tmpMsg->queue_id;
					if ($tmpMsg->status == 1 && strtotime($push_time) <= time()) {
						throw new InvalidParameterException('消息正在发送中，不可删除！');
					}
					TemplatePushMsg::deleteAll(['id' => $id]);
					$tmpMsg->delete();
					\Yii::$app->queue->remove($queue_id);

					return true;
				} catch (\Exception $e) {
					throw new InvalidParameterException('删除失败！'.$e->getMessage());
				}
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/template-push-msg/
		 * @title           预览
		 * @description     预览
		 * @method   post
		 * @url  http://{host_name}/api/template-push-msg/down
		 *
		 * @param wx_id 必选 string wx_id
		 * @param template_id 必选 int 模板id
		 * @param template_data 必选 string 模板内容
		 * @param redirect_type 必选 int 跳转类型1链接、2小程序
		 * @param url 可选 string 跳转地址
		 * @param appid 可选 string 跳转到的小程序appid
		 * @param pagepath 可选 string 跳转到小程序的具体页面路径
		 * @param credit_change 可选 string 积分提醒时传
		 * @param credit_name 可选 string 积分提醒时传
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    url string 二维码地址
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/12/5 11:41
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws NotAllowException
		 * @throws \app\components\InvalidDataException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionDown ()
		{
			if (\Yii::$app->request->isPost) {
				$wxId          = \Yii::$app->request->post('wx_id');
				$template_id   = \Yii::$app->request->post('template_id');
				$template_data = \Yii::$app->request->post('template_data');
				$redirect_type = \Yii::$app->request->post('redirect_type');
				$url           = \Yii::$app->request->post('url');
				$appid         = \Yii::$app->request->post('appid');
				$pagepath      = \Yii::$app->request->post('pagepath');
				$credit_change = \Yii::$app->request->post('credit_change');
				$credit_name   = \Yii::$app->request->post('credit_name');
				$author_id     = $this->wxAuthorInfo->author_id;
				if (empty($author_id)) {
					throw new InvalidParameterException('缺少必要参数！');
				}
				if (empty($template_id)) {
					throw new InvalidParameterException('请选择模板！');
				}
				if (empty($template_data)) {
					throw new InvalidParameterException('请填写模板内容！');
				}
				if (empty($redirect_type)) {
					throw new InvalidParameterException('请设置跳转类型！');
				}
				if ($redirect_type == 1) {
					if (empty($url)) {
						throw new InvalidParameterException('请填写跳转地址！');
					} else {
						$preg = "/^http(s)?:\\/\\/.+/";
						if (!preg_match($preg, $url)) {
							throw new InvalidParameterException('跳转链接地址不正确！');
						}
					}
				} elseif ($redirect_type == 2) {
					if (empty($appid) || empty($pagepath)) {
						throw new InvalidParameterException('请填写小程序信息！');
					}
				}
				$data                  = [];
				$data['template_id']   = $template_id;
				$data['template_data'] = $template_data;
				$data['redirect_type'] = $redirect_type;
				$data['url']           = $url;
				$data['appid']         = $appid;
				$data['pagepath']      = $pagepath;
				$data['credit_change'] = $credit_change;
				$data['credit_name']   = $credit_name;
				$data                  = TemplatePushMsg::setTemplateMessage($data, 1);
				$code = 'template-'.rand(10000, 999999999999);
				\Yii::$app->cache->set(strval($code), $data, 3600);
				$qrCodeData            = ['expire_seconds' => 2592000, 'action_name' => 'QR_STR_SCENE', 'action_info' => ['scene' => ['scene_str' => $code]]];
				//获取微信api相关配置
				$wxAuthInfo  = WxAuthorize::findOne(['author_id' => $author_id]);
				$wxAuthorize = WxAuthorize::getTokenInfo($wxAuthInfo->authorizer_appid, false, true);
				if (empty($wxAuthorize)) {
					throw new InvalidParameterException('获取token失败');
				}
				$wechat = \Yii::createObject([
					'class'          => Wechat::className(),
					'appId'          => $wxAuthInfo->authorizer_appid,
					'appSecret'      => $wxAuthorize['config']->appSecret,
					'token'          => $wxAuthorize['config']->token,
					'componentAppId' => $wxAuthorize['config']->appid,
				]);
				//生成二维码数据
				$result = $wechat->createQrCode($qrCodeData);
				if (isset($result["errcode"]) && $result["errcode"] == "48001") {
					throw new InvalidParameterException('公众号未认证或未取得接口权限');
				}
				if (isset($result["errcode"])) {
					throw new InvalidParameterException('创建二维码失败');
				}

				return [
					'url'=>$result['url']
				];


			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * 发送模板消息（测试使用）
		 */
		public function actionSendTemplateMessage ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$id = \Yii::$app->request->post('id');
				$id = 9;
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$appid       = $this->wxAuthorInfo->authorizer_appid;
				$wxAuthorize = WxAuthorize::getTokenInfo($appid, false, true);
				if (!empty($wxAuthorize)) {
					$wechat = \Yii::createObject([
						'class'          => Wechat::className(),
						'appId'          => $appid,
						'appSecret'      => $wxAuthorize['config']->appSecret,
						'token'          => $wxAuthorize['config']->token,
						'componentAppId' => $wxAuthorize['config']->appid,
					]);
				}
				$tmpMsg      = TemplatePushMsg::findOne(['id' => $id]);
				$temp        = Template::findOne(['id' => $tmpMsg->template_id]);
				$template_id = $temp->template_id;

				$push_rule = json_decode(base64_decode($tmpMsg->push_rule), true);
				$sex       = $push_rule['sex'];
				$stime     = $push_rule['stime'];
				$etime     = $push_rule['etime'];
				$province  = $push_rule['province'];
				$city      = $push_rule['city'];
				$tag_ids   = $push_rule['tag_ids'];

				//是否包含昵称
				$arr           = json_decode($tmpMsg->template_data, true);
				$template_data = $arr[0];
				$content       = $temp->content;
				$content       = ltrim($content, '{{first.DATA}}');
				$content       = rtrim($content, '{{remark.DATA}}');
				$con           = explode(PHP_EOL, $content);
				$flag          = 0;
				for ($i = 1; $i <= count($con) - 2; $i++) {
					$value = $template_data['keyword' . $i]['value'];
					if (strpos($value, '{nickname}') !== false) {
						$flag = 1; //包含
					}
				}
				$miniprogram = [];
				if ($tmpMsg->redirect_type == 1) {
					$url = $tmpMsg->url;
				} elseif ($tmpMsg->redirect_type == 2) {
					$miniprogram['appid']    = $tmpMsg->appid;
					$miniprogram['pagepath'] = $tmpMsg->pagepath;
				}
				$fans_num = 0;
				//获取发送粉丝
				$push_type = $tmpMsg->push_type;
				if ($push_type == 1) {
					$fans = Fans::find(['f.author_id' => $tmpMsg->author_id])->alias('f');;
					if ($sex != -1) {
						$fans = $fans->andWhere(['f.sex' => $sex]);
					}
					if (!empty($stime)) {
						$fans = $fans->andWhere(['>=', 'f.subscribe_time', $stime]);
					}
					if (!empty($etime)) {
						$fans = $fans->andWhere(['<=', 'f.subscribe_time', $etime]);
					}
					if (!empty($province)) {
						$fans = $fans->andWhere(['f.province' => $province]);
					}
					if (!empty($city)) {
						$fans = $fans->andWhere(['f.city' => $city]);
					}
					if (!empty($tag_ids)) {
						$tagIds = explode(',', $tag_ids);
						if (!in_array(0, $tagIds)) {
							$fans = $fans->leftJoin('{{%fans_tags}} ft', '`ft`.`fans_id` = `f`.`id`')->where(['and', ['f.author_id' => $tmpMsg->author_id], ['in', 'ft.tags_id', $tagIds]]);
						} else {
							$keyList = array_keys($tagIds, 0);
							unset($tagIds[$keyList[0]]);

							if (!empty($tagIds)) {
								$fans = $fans->leftJoin('{{%fans_tags}} ft', '`ft`.`fans_id` = `f`.`id`')->where(['and', ['f.author_id' => $tmpMsg->author_id], ['or', ['f.tagid_list' => '[]'], ['in', 'ft.tags_id', $tagIds]]]);
							} else {
								$fans = $fans->where(['f.author_id' => $tmpMsg->author_id, 'f.tagid_list' => '[]']);
							}
						}
					}
					$fans = $fans->asArray()->all();
					foreach ($fans as $v) {
						$openid = $v['openid'];
						//模板内容包含昵称
						if ($flag == 1) {
							$nickname = $v['nickname'];
							for ($i = 1; $i <= count($con) - 2; $i++) {
								$value = $template_data['keyword' . $i]['value'];
								if (strpos($value, '{nickname}') !== false) {
									$template_data['keyword' . $i]['value'] = str_replace("{nickname}", $nickname, $value);
								}
							}
						}

						$res = $wechat->sendTemplateMessage($v, $template_id, $template_data, $url, $miniprogram, $tmpMsg->redirect_type);
						if ($res) {
							$fans_num++;
						}

					}

				} elseif ($push_type == 2) {
					//全部粉丝
					$fans = Fans::find(['author_id' => $tmpMsg->author_id])->asArray()->all();
					foreach ($fans as $v) {
						$openid = $v['openid'];
						//模板内容包含昵称
						if ($flag == 1) {
							$nickname = $v['nickname'];
							for ($i = 1; $i <= count($con) - 2; $i++) {
								$value = $template_data['keyword' . $i]['value'];
								if (strpos($value, '{nickname}') !== false) {
									$template_data['keyword' . $i]['value'] = str_replace("{nickname}", $nickname, $value);
								}
							}
						}

						$res = $wechat->sendTemplateMessage($v, $template_id, $template_data, $url, $miniprogram, $tmpMsg->redirect_type);
						if ($res) {
							$fans_num++;
						}

					}
				} elseif ($push_type == 3) {
					//指定粉丝
					$push_rule['openids'] = str_replace("；",";",$push_rule['openids']);
					$openidArr = explode(';', $push_rule['openids']);
					if (!empty($openidArr)) {
						foreach ($openidArr as $v) {
							$fans = Fans::findOne(['author_id' => $tmpMsg->author_id, 'openid' => $v]);
							//模板内容包含昵称
							if ($flag == 1) {
								$nickname = $fans->nickname;
								for ($i = 1; $i <= count($con) - 2; $i++) {
									$value = $template_data['keyword' . $i]['value'];
									if (strpos($value, '{nickname}') !== false) {
										$template_data['keyword' . $i]['value'] = str_replace("{nickname}", $nickname, $value);
									}
								}
							}
							$res = $wechat->sendTemplateMessage($v, $template_id, $template_data, $url, $miniprogram, $tmpMsg->redirect_type);
							if ($res) {
								$fans_num++;
							}
						}
					}
				}

				if (empty($fans_num)) {
					//发送失败
					$tmpMsg->status = 2;
					$tmpMsg->save();
				} else {
					//更新发送成功粉丝数
					$tmpMsg->fans_num = $fans_num;
					$tmpMsg->save();
				}

				return $fans_num;
			} else {
				throw new InvalidParameterException('请求方式不允许！');
			}
		}

		/**
		 * 批量更新模板的旧数据
		 *
		 */
		public function actionUpdateOldData ()
		{
			TemplatePushMsg::updateData();
		}



	}