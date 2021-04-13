<?php
	/**
	 * 高级群发
	 * User: xcy
	 * Date: 2019-10-17
	 * Time: 16:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Article;
	use app\models\Attachment;
	use app\models\Fans;
	use app\models\Material;
	use app\models\WxAuthorize;
	use app\modules\api\components\AuthBaseController;
	use app\util\MsgUtil;
	use callmez\wechat\sdk\Wechat;
	use yii\web\MethodNotAllowedHttpException;
	use app\models\HighLevelPushMsg;
	use app\queue\HighLevelJob;

	class HighLevelPushMsgController extends AuthBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/high-level-push-msg/
		 * @title           高级群发列表
		 * @description     高级群发列表
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/list
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param page 可选 int 页数，默认为1
		 * @param pageSize 可选 int 每页个数，默认10
		 * @param status 可选 int 状态：0未发送、1已发送、2发送失败
		 * @param push_time 可选 string 发送日期
		 *
		 * @return          {"error":0,"data":{"count":"1","highLevel":[{"id":"6","author_id":"1","msg_title":"测试3","type_name":"文本","send_people":"全部粉丝","fans_sum":"1","target_num":"1","push_time":"2019-09-24 09:18:08","nick_name":"彼岸智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/vSgib1JZE1myUFgFic1R8x0hxt3OLFyNRKw09O6r4skpw3MkszKrNe0olL1Feqic2ZtQaasXGXw8aNf7ygJESlHn1nyib29OOn3A/0","error_msg":""}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count string 总数量
		 * @return_param    highLevel array 群发数据
		 * @return_param    id int 群发id
		 * @return_param    author_id int 公众号ID
		 * @return_param    msg_title string 二维码标题
		 * @return_param    type_name string 消息类型
		 * @return_param    send_people string 发送条件
		 * @return_param    push_time string 发送时间
		 * @return_param    target_num int 预计发送粉丝数
		 * @return_param    fans_sum int 发送成功粉丝数
		 * @return_param    nick_name string 授权方昵称
		 * @return_param    head_img string 授权方头像
		 * @return_param    error_msg string 发送失败原因
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-18 17:13
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$post      = \Yii::$app->request->post();
			$author_id = $this->wxAuthorInfo->author_id;
			if (empty($author_id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$highLevel = HighLevelPushMsg::find()->alias('h');
			//$highLevel = $highLevel->leftJoin('{{%wx_authorize_info}} wai', '`wai`.`author_id` = `h`.`author_id`');
			//$highLevel = $highLevel->select('h.*,wai.nick_name,wai.head_img,wai.user_name');
			$highLevel = $highLevel->select('h.*');
			$highLevel = $highLevel->where(['h.author_id' => $author_id, 'is_del' => 0]);
			//消息名称
			$title = \Yii::$app->request->post('title', '');
			if (!empty($title)) {
				$highLevel = $highLevel->andWhere(['like', 'h.msg_title', $title]);
			}
			//状态
			$status = \Yii::$app->request->post('status', 0);
			if ($status != -1) {
				$highLevel = $highLevel->andWhere(['h.status' => $status]);
			}
			//发送时间
			if (!empty($post['push_time'])) {
				$start_date = $post['push_time'] . ' 00:00:00';
				$end_date   = $post['push_time'] . ' 23:59:59';
				$highLevel  = $highLevel->andWhere(['>', 'h.push_time', $start_date]);
				$highLevel  = $highLevel->andWhere(['<', 'h.push_time', $end_date]);
			}
			//分页
			$page      = \Yii::$app->request->post('page', 1);
			$pageSize  = \Yii::$app->request->post('pageSize', 10);
			$offset    = ($page - 1) * $pageSize;
			$count     = $highLevel->count();
			$highLevel = $highLevel->limit($pageSize)->offset($offset)->orderBy('h.id desc')->asArray()->all();
			$typeArr   = [
				1 => '文本',
				2 => '图片',
				3 => '语音',
				4 => '视频',
				5 => '图文',
			];
			foreach ($highLevel as $hk => $hv) {
				$highLevel[$hk]['key'] = $hv['id'];
				if ($hv['push_type'] == 1) {
					$highLevel[$hk]['send_people'] = "按条件筛选的粉丝";
				} elseif ($hv['push_type'] == 2) {
					$highLevel[$hk]['send_people'] = "全部粉丝";
				} elseif ($hv['push_type'] == 3) {
					$highLevel[$hk]['send_people'] = "指定粉丝";
				}
				if ($hv['status'] == 0) {
					$highLevel[$hk]['status'] = '未发送';
				} elseif ($hv['status'] == 1) {
					$highLevel[$hk]['status'] = '已发送';
				} elseif ($hv['status'] == 2) {
					$highLevel[$hk]['status'] = '发送失败';
				}
				$highLevel[$hk]['type_name'] = $typeArr[$hv['msg_type']];
			}

			return [
				'count'     => $count,
				'highLevel' => $highLevel,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/high-level-push-msg/
		 * @title           高级群发添加修改接口
		 * @description     高级群发添加修改接口
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/add
		 *
		 * @param id 必选 int  群发id 0 添加 >0 修改
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param msg_title 必选 string 消息名称
		 * @param msg_type 必选 int 1：文本（text）、2：图片（img）、3：语音（voice）、4：视频（video）、5：图文（news）
		 * @param material_id 必选 int 附件id,当msg_type=1时可不传
		 * @param content 必选 string 文本消息内容,当msg_type!=1时可不传
		 * @param push_type 必选 int 发送类别：1：标签、2：全部粉丝、3：指定用户
		 * @param sex 可选 int 性别：1男、2女、0未知、-1、全部
		 * @param s_time 可选 sting 开始时间
		 * @param e_time 可选 sting 结束时间
		 * @param is_custom 可选 int 客户0全部1是2否
		 * @param province 可选 sting 省
		 * @param city 可选 sting 市
		 * @param tag_ids 可选 sting 标签id，多个以,隔开
		 * @param openids 可选 sting 粉丝openid，多个以;隔开
		 * @param target_num 必选 int 预计发送粉丝数
		 * @param send_type 必选 int 1立即发送、2指定时间
		 * @param send_time 可选 int 发送时间
		 * @param continue 必选 int 若有文章判定为转载，继续群发若有文章判定为转载，继续群发，0：不继续、1：继续
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-24 10:50
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$author_id   = $this->wxAuthorInfo->author_id;
			//检查此公众号是否授权
			if($this->wxAuthorInfo->author->authorizer_type == WxAuthorize::AUTH_TYPE_UNAUTH){
				throw new InvalidDataException('此公众号未授权，请先授权');
			}
			$id            = \Yii::$app->request->post('id', 0);
			$msg_title     = \Yii::$app->request->post('msg_title', '');
			$msg_type      = \Yii::$app->request->post('msg_type', 0);
			$attachment_id = \Yii::$app->request->post('material_id', 0);//附件id
			$content       = \Yii::$app->request->post('content', '');
			$push_type     = \Yii::$app->request->post('push_type', 1);
			$target_num    = \Yii::$app->request->post('target_num', 0);
			$sex           = \Yii::$app->request->post('sex', 0); //1 男 2 女 0未知 -1 全部
			$stime         = \Yii::$app->request->post('s_time', '');
			$etime         = \Yii::$app->request->post('e_time', '');
			$province      = \Yii::$app->request->post('province', '');
			$city          = \Yii::$app->request->post('city', '');
			$tag_ids       = \Yii::$app->request->post('tag_ids', '');
			$openids       = \Yii::$app->request->post('openids', '');
			$send_type     = \Yii::$app->request->post('send_type', 1); //1立即发送 2指定时间
			$send_time     = \Yii::$app->request->post('send_time', '');
			$continue      = \Yii::$app->request->post('continue', '');
			$is_custom     = \Yii::$app->request->post('is_custom', 0);
			if (empty($author_id)) {
				throw new InvalidParameterException('请选择公众号！');
			}
			if (empty($msg_title)) {
				throw new InvalidParameterException('消息名称不能为空！');
			} else {
				$length = mb_strlen($msg_title, 'utf-8');
				if ($length > 32) {
					throw new InvalidParameterException('消息名称长度不能超过32位！');
				}
			}
			if (empty($msg_type)) {
				throw new InvalidParameterException('请选择消息类型！');
			}
			if ($msg_type != 1) {
//				if (empty($material_id)) {
//					throw new InvalidParameterException('请选择消息！');
//				}
				if (empty($attachment_id)) {
					throw new InvalidParameterException('请选择消息！');
				}
			} else {
				$content = trim($content,"\n");
				if (empty($content)) {
					throw new InvalidParameterException('请填写文本消息！');
				}
			}
			if ($push_type == 3) {
				if (empty($openids)) {
					throw new InvalidParameterException("指定粉丝不能为空！");
				}
				$openidArr   = explode(';', $openids);
				$beforeCount = count($openidArr);
				$openidArr   = array_unique($openidArr);
				$count       = count($openidArr);
				if ($beforeCount != $count) {
					throw new InvalidParameterException("openid存在重复，请重新填写！");
				}
				if ($count < 2) {
					throw new InvalidParameterException("指定粉丝数量不能少于2个！");
				}
				if ($count > 8) {
					throw new InvalidParameterException("指定粉丝数量不能超过8个！");
				}
				foreach ($openidArr as $openid) {
					$fans = Fans::findOne(['author_id' => $author_id, 'openid' => $openid, 'subscribe' => 1]);
					if (empty($fans)) {
						throw new InvalidParameterException("粉丝不存在，可能因没有同步粉丝数据或是其他公众号的粉丝等原因");
					}
				}
				$target_num = $count;
			}
			if ($send_type == 2) {
				//指定时间
				if ($send_time <= time()) {
					throw new InvalidParameterException("当前时间已超过发送时间，无法提交，请重新设置群发时间");
				}
			}
			//再次修改提交时进行发送时间的判断
			if (!empty($id)) {
				$highLevel = HighLevelPushMsg::findOne($id);
				if (strtotime($highLevel->push_time) <= time()) {
					throw new InvalidParameterException("已经开始发送，不准许修改！");
				}
			}
			$data                  = [];
			$data['id']            = $id;
			$data['author_id']     = $author_id;
			$data['msg_title']     = trim($msg_title);
			$data['msg_type']      = $msg_type;
			$data['content']       = rtrim($content);//去除尾部换行
			$data['attachment_id'] = $attachment_id;
			$data['push_type']     = $push_type;
			$data['target_num']    = $target_num;
			$data['sex']           = $sex;
			$data['stime']         = $stime;
			$data['etime']         = $etime;
			$data['province']      = $province;
			$data['city']          = $city;
			$data['tag_ids']       = $tag_ids;
			$data['send_type']     = $send_type;
			$data['send_time']     = $send_time;
			$data['openids']       = $openids;
			$data['continue']      = $continue;
			$data['is_custom']     = $is_custom;
			try {
				$high_id = HighLevelPushMsg::setHighPush($data);
				if (!empty($high_id)) {
					if ($send_type == 1) {
						$jobId = \Yii::$app->queue->push(new HighLevelJob([
							'high_level_push_msg_id' => $high_id
						]));
					} else {
						//指定时间发送
						$second = $send_time - time();
						$jobId  = \Yii::$app->queue->delay($second)->push(new HighLevelJob([
							'high_level_push_msg_id' => $high_id
						]));
					}
					$highLevel = HighLevelPushMsg::findOne($high_id);
					if (!empty($id) && !empty($highLevel->queue_id)) {
						\Yii::$app->queue->remove($highLevel->queue_id);
					}
					$highLevel->queue_id = $jobId;
					$highLevel->save();

					return true;
				} else {
					$err_msg = !empty($id) ? '修改失败' : '创建失败';
					throw new InvalidDataException($err_msg);
				}
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/high-level-push-msg/
		 * @title           高级群发删除
		 * @description     高级群发删除
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/delete
		 *
		 * @param id 必选 int 群发消息id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    error_msg string 错误信息
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-18 17:43
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \Throwable
		 * @throws \yii\db\StaleObjectException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$id = \Yii::$app->request->post('id');
			if (empty($id)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$highLevel = HighLevelPushMsg::findOne($id);
			if (empty($highLevel)) {
				throw new InvalidParameterException('参数不正确！');
			}
			try {
				$push_time = $highLevel->push_time;
				$queue_id  = $highLevel->queue_id;
				if ($highLevel->status == 1 && strtotime($push_time) <= time()) {
					throw new InvalidDataException('消息已发送，不可删除！');
				}
				$highLevel->is_del = 1;
				$highLevel->update();
				if (!empty($queue_id)) {
					\Yii::$app->queue->remove($queue_id);
				}

				return true;
			} catch (InvalidDataException $e) {
				throw new InvalidDataException($e->getMessage());
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/high-level-push-msg/
		 * @title           高级群发预览
		 * @description     高级群发预览
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/preview
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param username 必选 string 微信名
		 * @param material_id 可选 int 素材id
		 * @param content 可选 string 文本内容
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    error_msg string 错误信息
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-21 9:19
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\NotAllowException
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionPreview ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$author_id = $this->wxAuthorInfo->author_id;
			if (empty($author_id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$material_id = \Yii::$app->request->post('material_id', 0);
			$content     = \Yii::$app->request->post('content', '');
			$username    = \Yii::$app->request->post('username');//微信名
			if (empty($material_id) && empty($content)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$wxAuthInfo  = WxAuthorize::findOne(['author_id' => $author_id]);
			$wxAuthorize = WxAuthorize::getTokenInfo($wxAuthInfo->authorizer_appid, false, true);
			if (empty($wxAuthorize)) {
				throw new InvalidDataException('获取token失败');
			}
			$wechat  = \Yii::createObject([
				'class'          => Wechat::className(),
				'appId'          => $wxAuthInfo->authorizer_appid,
				'appSecret'      => $wxAuthorize['config']->appSecret,
				'token'          => $wxAuthorize['config']->token,
				'componentAppId' => $wxAuthorize['config']->appid,
			]);
			$typeArr = [
				0 => 'text',
				1 => 'mpnews',
				2 => 'image',
				3 => 'voice',
				4 => 'mpvideo',
			];
			if (!empty($material_id)) {
				$attachment = Attachment::findOne($material_id);
				if (!empty($attachment->material_id) && $attachment->material->author_id == $author_id && !empty($attachment->material->status)) {
					$material = Material::findOne($attachment->material_id);
				} else {
					//$material = Material::findOne(['author_id' => $author_id, 'attachment_id' => $material_id]);
					$material = Material::getMaterial(['author_id' => $author_id, 'attachment_id' => $material_id,'file_type'=>$attachment->file_type]);
				}
				if (!empty($material)) {
					MsgUtil::checkNeedReload($material);
					$content = $material->media_id;
					if($attachment->file_type == 3){
						$content = HighLevelPushMsg::getVideoMediaId($author_id,$material->media_id,$material->title,$material->introduction);
						\Yii::error($content,'$content');
					}
				} elseif ($attachment->file_type == 4) {
					$appPath = \Yii::getAlias('@app');
					if(!empty($attachment->material_id) && !empty($attachment->material->article_sort)){
						$articles = [];
						$article = Article::find()->alias('a');
						$article = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
						$articleIds = explode(',', $attachment->material->article_sort);
						foreach ($articleIds as $aIds) {
							$artInfo = $article->where(['a.id' => $aIds])->select('a.title,a.digest,m.local_path,a.content_source_url')->asArray()->one();

							$result = $wechat->uploadMedia($appPath . $artInfo['local_path'],'image');

							$thumb_media_id = $result['media_id'];
							$temp = [
								'thumb_media_id'     => $thumb_media_id,
								'title'              => $artInfo['title'],
								'content'            => $artInfo['digest'],
								'content_source_url' => $artInfo['content_source_url'],
							];
							array_push($articles, $temp);
						}
					}else{
						$result         = $wechat->uploadMedia($appPath . $attachment->local_path, 'image');
						$thumb_media_id = $result['media_id'];

						$articles = [
							[
								'thumb_media_id'     => $thumb_media_id,
								'title'              => $attachment->file_name,
								'content'            => $attachment->content,
								'content_source_url' => $attachment->jump_url,
							]
						];
					}
					$result   = $wechat->uploadArticles($articles);
					$content  = $result['media_id'];
				}
				if ($attachment->file_type == 1) {
					$type = 'image';
				} elseif ($attachment->file_type == 2) {
					$type = 'voice';
				} elseif ($attachment->file_type == 3) {
					$type = 'mpvideo';
				} elseif ($attachment->file_type == 4) {
					$type = 'mpnews';
				}
				//$material = Material::findOne($material_id);
				//$type     = $typeArr[$material->material_type];
				//$content  = $material->media_id;
			} else {
				$type = 'text';
			}

			$result = $wechat->sendPreviewMessage($username, $type, $content);
			if ($result['errcode'] == 0) {
				return true;
			} else {
				throw new InvalidDataException($result['errmsg']);
			}
		}

		/**
		 * showdoc
		 * @catalog           数据接口/api/high-level-push-msg/
		 * @title             高级群发详情
		 * @description       高级群发详情
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/info
		 *
		 * @param id 必选 int 客服消息id
		 *
		 * @return            {"error":0,"data":{"id":17,"msg_type":5,"artList":[{"title":"测试图文","digest":"描述","local_path":"/upload/images/20191023/15718249575db0253d8e81b.jpg"}]}}
		 *
		 * @return_param      error int 状态码
		 * @return_param      data array 结果数据
		 * @return_param      id int 客服消息id
		 * @return_param      msg_type int 类型：1：文本（text）、2：图片（img）、3：语音（voice）、4：视频（video）、5：图文（news）
		 * @return_param      artList array 当msg_type=5时存在
		 * @return_param      title string 图文消息的标题：当msg_type=5时存在
		 * @return_param      digest string 图文消息的摘要：当msg_type=5时存在
		 * @return_param      local_path string 地址：当msg_type=5时存在
		 * @return_param      content string 文本内容：当msg_type=1时存在
		 * @return_param      local_path string 地址：当msg_type=2、3、4时存在
		 * @return_param      title string 标题
		 *
		 * @remark            Create by PhpStorm. User: xingchangyu. Date: 2019-10-29 09:15
		 * @number            0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$id = \Yii::$app->request->post('id', 0);
			if (empty($id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$highLevel = HighLevelPushMsg::findOne($id);
			if (empty($highLevel)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$data             = [];
			$data['id']       = $highLevel->id;
			$data['msg_type'] = $highLevel->msg_type;
			$data['title']    = '';
			if ($highLevel->msg_type == 1) {
				$data['content'] = $highLevel->content;
			} elseif ($highLevel->msg_type == 5) {
				if(!empty($highLevel->material_id)){
					$material = Material::findOne($highLevel->material_id);
					if (!empty($material)) {
						$article = Article::find()->alias('a');
						$article = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
						$articleIds = explode(',', $material->article_sort);
						$temp       = [];
						foreach ($articleIds as $aIds) {
							$artInfo = $article->where(['a.id' => $aIds])->select('a.title,a.digest,a.content_source_url,m.local_path')->asArray()->one();
							array_push($temp, $artInfo);
						}
						$data['artList'] = $temp;
					}
				}elseif(!empty($highLevel->attachment_id)){
					$attachment = Attachment::findOne($highLevel->attachment_id);
					if (!empty($attachment->material_id)) {
						$article    = Article::find()->alias('a');
						$article    = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
						$articleIds = explode(',', $attachment->material->article_sort);
						$temp       = [];
						foreach ($articleIds as $aIds) {
							$artInfo = $article->where(['a.id' => $aIds])->select('a.title,a.digest,a.content_source_url,m.local_path')->asArray()->one();
							array_push($temp, $artInfo);
						}
						$data['artList'] = $temp;
					} else {
						$data['artList'] = [
							[
								'title'              => $attachment->file_name,
								'digest'             => $attachment->content,
								'content_source_url' => $attachment->jump_url,
								'local_path'         => $attachment->local_path,
							]
						];
					}
				}
			} else {
				if (!empty($highLevel->material_id)) {
					$material = Material::findOne($highLevel->material_id);
					if (!empty($material)) {
						$data['local_path'] = $material->local_path;
						$data['title']      = $material->file_name;
					}
				} elseif (!empty($highLevel->attachment_id)) {
					$attachment         = Attachment::findOne($highLevel->attachment_id);
					$data['local_path'] = $attachment->local_path;
					$data['title']      = $attachment->file_name;
				}
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/high-level-push-msg/
		 * @title           获取高级群发标题
		 * @description     获取高级群发标题
		 * @method   post
		 * @url  http://{host_name}/api/high-level-push-msg/get-title
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-12-02 17:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetTitle ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$author_id = $this->wxAuthorInfo->author_id;
			if (empty($author_id)) {
				throw new InvalidParameterException('请选择公众号！');
			}
			$data     = HighLevelPushMsg::find()->where(['author_id' => $author_id, 'is_del' => 0])->select('msg_title')->all();
			$titleArr = [];
			if (!empty($data)) {
				$titleArr = array_column($data, 'msg_title');
			}

			return $titleArr;
		}
	}