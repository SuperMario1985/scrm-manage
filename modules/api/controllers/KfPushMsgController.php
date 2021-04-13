<?php
	/**
	 * 客服消息
	 * User: xcy
	 * Date: 2019-10-24
	 * Time: 11:00
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Article;
	use app\models\Attachment;
	use app\models\KfPushMsg;
	use app\models\KfPushPreview;
	use app\models\Material;
	use app\models\WxAuthorize;
	use app\models\WxAuthorizeInfo;
	use app\modules\api\components\AuthBaseController;
	use app\queue\KfJob;
	use app\util\MsgUtil;
	use callmez\wechat\sdk\Wechat;
	use yii\db\Expression;
	use yii\web\MethodNotAllowedHttpException;

	class KfPushMsgController extends AuthBaseController
	{

		/**
		 * showdoc
		 * @catalog         数据接口/api/kf-push-msg/
		 * @title           客服消息列表
		 * @description     客服消息列表
		 * @method   请求方式
		 * @url  http://{host_name}/api/kf-push-msg/list
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param page 可选 int 页数，默认为1
		 * @param pageSize 可选 int 每页个数，默认10
		 * @param title 可选 string 消息标题
		 * @param nickname 可选 string 公众号名称
		 * @param status 可选 int 状态：0未发送、1已发送、2发送失败、3发送中
		 * @param push_time 可选 string 发送日期
		 *
		 * @return          {"error":0,"data":{"count":"15","kfData":[{"key":"17","id":"17","author_id":"2","msg_title":"测试712344","msg_type":"5","content":null,"material_id":null,"title":"测试图文","digest":"描述","cover_url":"/upload/images/20191023/15718249575db0253d8e81b.jpg","content_url":"http://www.baidu.com","push_type":"2",,"push_time":"2019-10-28 16:09:55","create_time":"2019-10-28 16:09:55","target_num":"1","fans_num":"2","queue_id":"0","status":"1","is_del":"0","nick_name":"小猪的智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/qbvaL9taELsfibgnbr0jBxaiayVy2GNE3HY0SrusXbQmVeBBpDzFF8VOibSBCshTIu6lPX5O10UBNwZBibGRicqGo6WkWPccHJZaV/0","user_name":"gh_a5a2b5c4f175","qrcode_url":"http://mmbiz.qpic.cn/mmbiz_jpg/yNRxJFCKeuABJug5Qqns1VcpD5BXtibr9HtSzKtdwHs3Nia1Qq8vK3TN5rMJNMAbNicAic37BJHvKmhnfnZOOPw8DA/0"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    kfData array 群发数据
		 * @return_param    key int 群发id
		 * @return_param    author_id int 公众号ID
		 * @return_param    msg_title string 消息标题
		 * @return_param    send_people string 发送条件
		 * @return_param    push_time string 发送时间
		 * @return_param    target_num int 预计发送粉丝数
		 * @return_param    fans_sum int 发送成功粉丝数
		 * @return_param    nick_name string 授权方昵称
		 * @return_param    create_time string 创建时间
		 * @return_param    push_time string 发送时间
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-28 16:30
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
			$author_id = $this->wxAuthorInfo->author_id;
			if (empty($author_id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$nickname  = \Yii::$app->request->post('nickname', '');
			$msg_title = \Yii::$app->request->post('title', '');
			$page      = \Yii::$app->request->post('page', 1);
			$pageSize  = \Yii::$app->request->post('pageSize', 10);
			$push_time = \Yii::$app->request->post('push_time', '');

			//获取参数二维码数据
			$kfData = KfPushMsg::find()->alias('kf');
			//$kfData = $kfData->leftJoin('{{%wx_authorize_info}} wai', '`wai`.`author_id` = `kf`.`author_id`');
			//$select = new Expression("kf.id as `key`,kf.*,wai.nick_name,wai.head_img,wai.user_name,wai.qrcode_url");
			$select = new Expression("kf.id as `key`,kf.*");
			$kfData = $kfData->select($select);
			$kfData = $kfData->where(['kf.author_id' => $author_id, 'kf.is_del' => 0]);
			//消息名称
			if (!empty($msg_title)) {
				$kfData = $kfData->andWhere(['like', 'kf.msg_title', $msg_title]);
			}
			//状态
			$status = \Yii::$app->request->post('status', 0);
			if ($status != -1) {
				$kfData = $kfData->andWhere(['kf.status' => $status]);
			}
			//发送时间
			if (!empty($push_time)) {
				$start_date = $push_time . ' 00:00:00';
				$end_date   = $push_time . ' 23:59:59';
				$kfData     = $kfData->andWhere(['>', 'kf.push_time', $start_date]);
				$kfData     = $kfData->andWhere(['<', 'kf.push_time', $end_date]);
			}

			$offset  = ($page - 1) * $pageSize;
			$count   = $kfData->count();
			$kfData  = $kfData->limit($pageSize)->offset($offset)->orderBy('kf.id desc')->asArray()->all();
			$typeArr = [
				1 => '文本',
				2 => '图片',
				3 => '语音',
				4 => '视频',
				5 => '图文',
			];
			foreach ($kfData as $hk => $hv) {
				if ($hv['push_type'] == 1) {
					$kfData[$hk]['send_people'] = "按条件筛选的粉丝";
				} elseif ($hv['push_type'] == 2) {
					$kfData[$hk]['send_people'] = "全部粉丝";
				}
				if ($hv['status'] == 0) {
					$kfData[$hk]['status'] = '未发送';
				} elseif ($hv['status'] == 1) {
					$kfData[$hk]['status'] = '已发送';
				} elseif ($hv['status'] == 2) {
					$kfData[$hk]['status'] = '发送失败';
				} elseif ($hv['status'] == 3) {
					$kfData[$hk]['status'] = '发送中';
				}
				$kfData[$hk]['type_name'] = $typeArr[$hv['msg_type']];
			}

			return [
				'count'  => $count,
				'kfData' => $kfData,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/kf-push-msg/
		 * @title           客服消息添加修改接口
		 * @description     客服消息添加修改接口
		 * @method   post
		 * @url  http://{host_name}/api/kf-push-msg/add
		 *
		 * @param id 必选 int  群发id：0添加、>0修改
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param msg_title 必选 string 消息名称
		 * @param msg_type 必选 int 1：文本（text）、2：图片（img）、3：语音（voice）、4：视频（video）、5：图文（news）
		 * @param material_id 必选 int 附件id，当msg_type=1时可不传或is_use=1时不传
		 * @param content 可选 string 文本消息内容，当msg_type!=1时可不传
		 * @param is_use 可选 string 是否使用自己填写，当msg_type=5时传
		 * @param title 可选 string 图文消息标题，当is_use=1时传
		 * @param digest 可选 string 图文消息的摘要，当is_use=1时传
		 * @param cover_url 可选 string 封面图片的URL，当is_use=1时传
		 * @param content_url 可选 string 正文的URL，当is_use=1时传
		 * @param push_type 必选 int 发送类别：1：标签、2：全部粉丝
		 * @param sex 可选 int 性别：1男、2女、0未知、-1全部
		 * @param s_time 可选 sting 开始时间
		 * @param e_time 可选 sting 结束时间
		 * @param tag_ids 可选 sting 标签id，多个以,隔开
		 * @param target_num 必选 int 预计发送粉丝数
		 * @param send_type 必选 int 1立即发送 2指定时间
		 * @param send_time 可选 int 发送时间
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-28 10:50
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
			//检查此公众号是否授权
			if ($this->wxAuthorInfo->author->authorizer_type == WxAuthorize::AUTH_TYPE_UNAUTH) {
				throw new InvalidDataException('此公众号未授权，请先授权');
			}
			$id            = \Yii::$app->request->post('id', 0);
			$author_id     = $this->wxAuthorInfo->author_id;
			$msg_title     = \Yii::$app->request->post('msg_title', '');
			$msg_type      = \Yii::$app->request->post('msg_type', 0);
			$is_use        = \Yii::$app->request->post('is_use', 0);
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
			$send_type     = \Yii::$app->request->post('send_type', 1); //1立即发送 2指定时间
			$send_time     = \Yii::$app->request->post('send_time', '');
			$title         = \Yii::$app->request->post('title', '');
			$digest        = \Yii::$app->request->post('digest', '');
			$cover_url     = \Yii::$app->request->post('cover_url', '');
			$content_url   = \Yii::$app->request->post('content_url', '');
			$is_sync       = \Yii::$app->request->post('is_sync', '');//自定义图文是否同步到文件柜
			$group_id      = \Yii::$app->request->post('group_id', NULL);//分组id

			if (empty($author_id)) {
				throw new InvalidParameterException('请选择公众号！');
			}
			if (empty($msg_title)) {
				throw new InvalidParameterException('消息名称不能为空！');
			} elseif (mb_strlen($msg_title, 'utf-8') > 20) {
				throw new InvalidParameterException('消息名称最多20个字符！');
			}
			if (empty($msg_type)) {
				throw new InvalidParameterException('请选择消息类型！');
			}
			if ($msg_type != 1) {
				if (!empty($is_use)) {
					if (empty($title)) {
						throw new InvalidParameterException('请填写标题！');
					} elseif (mb_strlen($title, 'utf-8') > 32) {
						throw new InvalidParameterException('标题最多32个字符！');
					}
					if (empty($cover_url)) {
						throw new InvalidParameterException('请选择图片封面！');
					}
					if (!empty($digest) && mb_strlen($digest, 'utf-8') > 128) {
						throw new InvalidParameterException('描述最多128个字符！');
					}
					if (empty($content_url)) {
						throw new InvalidParameterException('请填写跳转链接！');
					} else {
						$contentUrl = strtolower($content_url);
						$pattern    = '/(http|https)(.)*([a-z0-9\-\.\_])+/i';
						if (!preg_match($pattern, $contentUrl)) {
							throw new InvalidParameterException('跳转链接格式不正确！');
						}
					}
				} else {
//					if (empty($material_id)) {
//						throw new InvalidParameterException('请选择消息！');
//					}
					if (empty($attachment_id)) {
						throw new InvalidParameterException('请选择消息！');
					}
				}
			} else {
				$content = trim($content, "\n");
				if (empty($content)) {
					throw new InvalidParameterException('请填写文本消息！');
				}
			}
			$time = time();
			if ($send_type == 2) {
				//指定时间
				if ($send_time <= $time) {
					throw new InvalidParameterException("当前时间已超过发送时间，无法提交，请重新设置群发时间");
				}
			}
			if (!empty($id)) {
				$kfData = KfPushMsg::findOne($id);
				if (strtotime($kfData->push_time) <= $time) {
					throw new InvalidParameterException("已经开始发送，不准许修改！");
				}
			}
			$data                  = [];
			$data['id']            = $id;
			$data['author_id']     = $author_id;
			$data['msg_title']     = trim($msg_title);
			$data['msg_type']      = $msg_type;
			$data['is_use']        = $is_use;
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
			$data['title']         = $title;
			$data['digest']        = $digest;
			$data['cover_url']     = $cover_url;
			$data['content_url']   = $content_url;
			$data['is_sync']       = $is_sync;
			$data['group_id']      = $group_id;

			try {
				$kf_id = KfPushMsg::setKfData($data);
				if (!empty($kf_id)) {
					if ($send_type == 1) {
						$jobId = \Yii::$app->queue->push(new KfJob([
							'kf_push_msg_id' => $kf_id
						]));
					} else {
						//指定时间发送
						$second = $send_time - $time;
						$jobId  = \Yii::$app->queue->delay($second)->push(new KfJob([
							'kf_push_msg_id' => $kf_id
						]));
					}
					$kfData = KfPushMsg::findOne($kf_id);
					if (!empty($id) && !empty($kfData->queue_id)) {
						\Yii::$app->queue->remove($kfData->queue_id);
					}
					$kfData->queue_id = $jobId;
					$kfData->save();

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
		 * @catalog           数据接口/api/kf-push-msg/
		 * @title             消息详情
		 * @description       消息详情
		 * @method   post
		 * @url  http://{host_name}/api/kf-push-msg/info
		 *
		 * @param id 必选 int 客服消息id
		 *
		 * @return            {"error":0,"data":{"id":17,"msg_type":5,"artList":[{"title":"测试图文","digest":"描述","local_path":"/upload/images/20191023/15718249575db0253d8e81b.jpg"}]}}
		 *
		 * @return_param      error int 状态码
		 * @return_param      data array 结果数据
		 * @return_param      id int 客服消息id
		 * @return_param      msg_type int 类型，1：文本（text）、2：图片（img）、3：语音（voice）、4：视频（video）、5：图文（news）
		 * @return_param      artList array 当msg_type=5时存在
		 * @return_param      title string 图文消息的标题，当msg_type=5时存在
		 * @return_param      digest string 图文消息的摘要，当msg_type=5时存在
		 * @return_param      local_path string 地址，当msg_type=5时存在
		 * @return_param      content string 文本内容，当msg_type=1时存在
		 * @return_param      local_path string 地址，当msg_type=2、3、4时存在
		 * @return_param      title string 标题
		 *
		 * @remark            Create by PhpStorm. User: xingchangyu. Date: 2019-10-28 17:15
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
			$kfData = KfPushMsg::findOne($id);
			//$kfData = KfPushMsg::find()->where(['id' => $id])->one();
			if (empty($kfData)) {
				throw new InvalidParameterException('参数不正确！');
			}
			$data             = [];
			$data['id']       = $kfData->id;
			$data['msg_type'] = $kfData->msg_type;
			$data['title']    = '';
			if ($kfData->msg_type == 1) {
				$data['content'] = $kfData->content;
			} elseif ($kfData->msg_type == 5) {
				if (!empty($kfData->material_id)) {
					$material = Material::findOne($kfData->material_id);
					if (!empty($material)) {
						$article         = Article::find()->alias('a');
						$article         = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
						$data['artList'] = $article->where('a.id in(' . $material->article_sort . ')')->orderBy(["FIELD(a.id," . $material->article_sort . ")" => true])->select('a.title,a.digest,m.local_path')->asArray()->all();
					}
				} else {
					$arr             = [
						'title'      => $kfData->title,
						'digest'     => $kfData->digest,
						'local_path' => $kfData->cover_url,
					];
					if (!empty($kfData->attachment_id) && empty($kfData->status)) {
						$attachment = Attachment::findOne($kfData->attachment_id);
						$arr        = [
							'title'      => $attachment->file_name,
							'digest'     => $attachment->content,
							'local_path' => $attachment->local_path,
						];
					}
					$data['artList'] = [$arr];
				}
			} else {
				if (!empty($kfData->material_id)) {
					$material = Material::findOne($kfData->material_id);
					if (!empty($material)) {
						$data['local_path'] = $material->local_path;
						$data['title']      = $material->file_name;
					}
				} elseif (!empty($kfData->attachment_id)) {
					$attachment         = Attachment::findOne($kfData->attachment_id);
					$data['local_path'] = $attachment->local_path;
					$data['title']      = $attachment->file_name;
				}
			}

			return $data;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/kf-push-msg/
		 * @title           接口标题
		 * @description     接口描述
		 * @method   请求方式
		 * @url  http://{host_name}/api/kf-push-msg/delete
		 *
		 * @param id 必选 int 客服消息id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-28 17:38
		 * @number          0
		 *
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
			$id = \Yii::$app->request->post('id', 0);
			if (empty($id)) {
				throw new InvalidParameterException('缺少必要参数！');
			}
			$kfData = KfPushMsg::findOne($id);
			if (empty($kfData)) {
				throw new InvalidParameterException('参数不正确！');
			}
			try {
				$push_time = $kfData->push_time;
				$queue_id  = $kfData->queue_id;
				if ($kfData->status == 1 && strtotime($push_time) <= time()) {
					throw new InvalidDataException('消息已发送，不可删除！');
				}
				$kfData->is_del = 1;
				$kfData->update();
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
		 * @catalog         数据接口/api/kf-push-msg/
		 * @title           客服消息预览
		 * @description     客服消息预览
		 * @method   post
		 * @url  http://{host_name}/api/kf-push-msg/preview
		 *
		 * @param wx_id 必选 string 公众号唯一ID
		 * @param random 必选 int 公众号回复的随机数
		 * @param msg_type 必选 int 1：文本（text）、2：图片（img）、3：语音（voice）、4：视频（video）、5：图文（news）
		 * @param material_id 必选 int 素材id 当msg_type=1时可不传或is_use=1时不传
		 * @param content 可选 string 文本消息内容，当msg_type!=1时可不传
		 * @param is_use 可选 string 是否使用自己填写，当msg_type=5时传
		 * @param title 可选 string 图文消息标题，当is_use=1时传
		 * @param digest 可选 string 图文消息的摘要，当is_use=1时传
		 * @param cover_url 可选 string 封面图片的URL，当is_use=1时传
		 * @param content_url 可选 string 正文的URL，当is_use=1时传
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2019-10-29 13:24
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
			$author_id   = $this->wxAuthorInfo->author_id;
			$random      = \Yii::$app->request->post('random', 0);
			$msg_type    = \Yii::$app->request->post('msg_type', 0);
			$material_id = \Yii::$app->request->post('material_id', 0);
			$is_use      = \Yii::$app->request->post('is_use', 0);
			$content     = \Yii::$app->request->post('content', '');
			$title       = \Yii::$app->request->post('title', '');
			$digest      = \Yii::$app->request->post('digest', '');
			$cover_url   = \Yii::$app->request->post('cover_url', '');
			$content_url = \Yii::$app->request->post('content_url', '');
			if (empty($author_id)) {
				throw new InvalidParameterException('请选择公众号！');
			}
			if (empty($random)) {
				throw new InvalidParameterException('请填写公众号回复的内容！');
			}
			if ($msg_type != 1) {
				if (!empty($is_use)) {
					if (empty($title)) {
						throw new InvalidParameterException('请填写标题！');
					}
				} else {
					if (empty($material_id)) {
						throw new InvalidParameterException('请选择消息！');
					}
				}
			} else {
				if (empty($content)) {
					throw new InvalidParameterException('请填写文本消息！');
				}
			}

			//附件id转素材id
			if (in_array($msg_type, [2, 3, 4])) {
				//$material = Material::findOne(['author_id' => $author_id, 'attachment_id' => $material_id, 'status' => 1]);
				$attachment = Attachment::findOne($material_id);
				$material = Material::getMaterial(['author_id' => $author_id, 'attachment_id' => $material_id, 'file_type' => $attachment->file_type]);
				if (!empty($material)) {
					$material_id = $material->id;
				}
			}

			$previewInfo = KfPushPreview::findOne(['random' => $random]);
			if (empty($previewInfo)) {
				throw new InvalidParameterException('请确认测试ID是否正确！');
			} elseif ($previewInfo->fans->author_id != $author_id) {
				throw new InvalidParameterException('请确认测试ID是否正确！');
			} elseif ($previewInfo->expire_time < time()) {
				throw new InvalidParameterException('测试ID已过期，请重新获取！');
			}

			$msgContent = [];
			if ($msg_type == 1) {
				if (strpos($content, '{nickname}') !== false) {
					$nickname = $previewInfo->fans->nickname;
					$content  = str_replace("{nickname}", $nickname, $content);
				}
				$msgContent['text'] = $content;
			} elseif ($msg_type == 2) {
				$msgContent['media_id'] = $material_id;
			} elseif ($msg_type == 3) {
				$msgContent['media_id'] = $material_id;
			} elseif ($msg_type == 4) {
				$msgContent['media_id']       = $material_id;
				$msgContent['thumb_media_id'] = '';
			} elseif ($msg_type == 5) {
				if (!empty($is_use)) {
					$site_url   = \Yii::$app->params['site_url'];
					$msgContent = [
						"title"       => $title,
						"description" => $digest,
						"url"         => $content_url,
						"pic_url"     => $site_url . $cover_url
					];
				} else {
					$attachment = Attachment::findOne($material_id);
					if (!empty($attachment->material_id) && $attachment->material->author_id == $author_id && !empty($attachment->material->status)) {
						$msgContent['media_id'] = $attachment->material_id;
					} else {
						//$material = Material::findOne(['author_id' => $author_id, 'attachment_id' => $material_id, 'status' => 1]);
						$material = Material::getMaterial(['author_id' => $author_id, 'attachment_id' => $material_id, 'file_type' => $attachment->file_type]);
						if (!empty($material)) {
							$msgContent['media_id'] = $material->id;
						} else {
							$site_url   = \Yii::$app->params['site_url'];
							$msgContent = [
								"title"       => $attachment->file_name,
								"description" => $attachment->content,
								"url"         => $attachment->jump_url,
								"pic_url"     => $site_url . $attachment->local_path
							];
						}
					}
				}
			}

			$openid          = $previewInfo->fans->openid;
			$wxAuthorizeInfo = WxAuthorizeInfo::findOne(['author_id' => $author_id]);
			$result          = MsgUtil::send($wxAuthorizeInfo->authorizer_appid, $openid, $msg_type, $msgContent);
			if ($result['errcode'] == 0) {
				return true;
			} else {
				throw new InvalidParameterException($result['errmsg']);
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/kf-push-msg/
		 * @title           获取客服标题
		 * @description     获取客服标题
		 * @method   post
		 * @url  http://{host_name}/api/kf-push-msg/get-title
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
			$data     = KfPushMsg::find()->where(['author_id' => $author_id, 'is_del' => 0])->select('msg_title')->all();
			$titleArr = [];
			if (!empty($data)) {
				$titleArr = array_column($data, 'msg_title');
			}

			return $titleArr;
		}
	}