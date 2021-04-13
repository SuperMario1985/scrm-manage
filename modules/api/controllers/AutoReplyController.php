<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/11/08
	 * Time: 10:03
	 */

	namespace app\modules\api\controllers;

	use app\models\WxAuthorizeInfo;
	use app\util\SUtils;
	use app\components\InvalidDataException;
	use app\models\Material;
	use app\models\AutoReply;
	use app\models\ReplyInfo;
	use app\models\UserAuthorRelation;
	use app\modules\api\components\BaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use yii\web\MethodNotAllowedHttpException;
	use app\util\DateUtil;
	use app\components\InvalidParameterException;

	class AutoReplyController extends BaseController
	{
		function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'list' => ['POST'],
					],
				],
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/auto-reply/list
		 * @title           被关注回复列表
		 * @description     被关注回复列表
		 * @method   请求方式
		 * @url  http://{host_name}/api/auto-reply/list
		 *
		 * @param uid 必选 int 登录账号
		 * @param nick_name 选填 int 公众号名称
		 * @param status 选填 int 1全部2未设置3已设置
		 * @param page 选填 int 当前页
		 * @param pageSize 选填 int 页数
		 *
		 * @return          {"error":0,"data":{"count":"0","info":[{"nick_name":"小猪的智慧店铺","head_img":"http://wx.qlogo.cn/mmopen/qbvaL9taELsfibgnbr0jBxaiayVy2GNE3HY0SrusXbQmVeBBpDzFF8VOibSBCshTIu6lPX5O10UBNwZBibGRicqGo6WkWPccHJZaV/0","status":"0","push_type":"1","count":"0","id":"2"},{"nick_name":"老陈头嘚啵嘚","head_img":"http://wx.qlogo.cn/mmopen/ajNVdqHZLLBOHgH9kCKRRrj2QiajGeKfJKFgFwNVu2ZLwzG7RMOfvZbW0MsQGNicG8JSfMpVZoDgbosN5s1Z4aHa2RJv4U3rFzRn9Ulwk7F8g/0","status":"0","push_type":"1","count":"0","id":"3"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    nick_name string 公众号名称
		 * @return_param    head_img string 头像
		 * @return_param    status int 状态1全部2未设置3设置
		 * @return_param    push_type int 推送方式1随机推一条2全部推送
		 * @return_param    count int 推送数量
		 * @return_param    id int 关注列表id
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/8 15:56
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty(\Yii::$app->request->post('uid'))) {
					throw new InvalidParameterException('参数不正确！');
				}
				$uid          = \Yii::$app->request->post('uid'); //登录账号id
				$nick_name    = \Yii::$app->request->post('nick_name'); //公众号名称
				$status       = \Yii::$app->request->post('status'); //状态 1 全部 2 未设置 3 设置
				$page         = \Yii::$app->request->post('page'); //分页
				$pageSize     = \Yii::$app->request->post('pageSize'); //页数
				$status       = !empty($status) ? $status : 0;
				$page         = !empty($page) ? $page : 1;
				$pageSize     = !empty($pageSize) ? $pageSize : 10;
				$offset       = ($page - 1) * $pageSize;
				$userRelation = UserAuthorRelation::find()->andWhere(['uid' => $uid])->all();
				foreach ($userRelation as $relation) {
					$auto = AutoReply::findOne(['author_id' => $relation->author_id, 'replay_type' => 1]);
					if (empty($auto)) {
						$autoReply              = new AutoReply();
						$autoReply->author_id   = $relation->author_id;
						$autoReply->replay_type = 1;
						$autoReply->status      = 0;
						$autoReply->create_time = DateUtil::getCurrentTime();;
						if (!$autoReply->validate() || !$autoReply->save()) {
							throw new InvalidDataException(SUtils::modelError($autoReply));
						}
					}
				}
				$authData = WxAuthorizeInfo::find()->alias('wxau');
				$authData = $authData->leftJoin('{{%auto_reply}} auto', '`wxau`.`author_id` = `auto`.`author_id`');
				$authData = $authData->leftJoin('{{%user_author_relation}} re', '`wxau`.`author_id` = `re`.`author_id`');
				$authData = $authData->andWhere(['re.uid' => $uid]);
				if ($status == 2) {
					$authData = $authData->andWhere(['auto.status' => 0]);
				} elseif ($status == 3) {
					$authData = $authData->andWhere(['auto.status' => 1]);
				}
				if (!empty($nick_name)) {
					$authData = $authData->andWhere(['like', 'wxau.nick_name', $nick_name]);
				}
				$authData = $authData->select('wxau.nick_name,wxau.head_img,auto.*');
				$count    = $authData->count();
				$info     = $authData->limit($pageSize)->offset($offset)->asArray()->orderBy(['wxau.create_time' => 'DESC'])->all();
				$result   = [];
				if (!empty($info)) {
					foreach ($info as $k => $v) {
						$count                   = ReplyInfo::find()->andWhere(['rp_id' => $v['id']])->count();
						$result[$k]['nick_name'] = $v['nick_name'];
						$result[$k]['head_img']  = $v['head_img'];
						$result[$k]['status']    = $v['status'];
						$result[$k]['push_type'] = $v['push_type'];
						$result[$k]['count']     = $count;
						$result[$k]['id']     = $v['id'];
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new MethodNotAllowedHttpException("请求方式不允许！");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/auto-reply/detail
		 * @title           推送内容详情页
		 * @description     接口推送内容详情页描述
		 * @method   请求方式
		 * @url  http://{host_name}/api/auto-reply/detail
		 *
		 * @param id 必选 int 当前选择的id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    replyList array 回复列表
		 * @return_param    nick_name string 公众号名称
		 * @return_param    head_img string 公众号头像
		 * @return_param    push_type int 1随机推送一条2全部推送
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/11 17:36
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 */
		public function actionDetail(){
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post("id");
				if (empty($id)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				$auto = AutoReply::findOne($id);
				$wxAuto = WxAuthorizeInfo::find()->where(['author_id'=>$auto->author_id])->one();
				//回复内容
				$replyInfo = $auto->replyInfos;
				$replyList = [];
				if (!empty($replyInfo)) {
					foreach ($replyInfo as $rv) {
						if ($rv['type'] != 1) {
							$temp = Material::findOne(['id' => $rv['material_id']]);
							$url  = !empty($temp->local_path) ? $temp->local_path : '';
						}
						if ($rv['type'] == 5) {
							if (!isset($tempId)) {
								$tempId                     = $rv['id'];
								$replyList[$tempId]['type'] = 5;
							}
							$replyList[$tempId]['content'][] = ['type' => $rv['type'], 'title' => $rv['title'], 'digest' => $rv['digest'], 'content_url' => $rv['content_url'], 'mediaID' => $rv['content'], 'material_id' => $rv['material_id'], 'url' => $url];
						} elseif ($rv['type'] == 1) {
							$replyList[$rv['id']] = ['type' => $rv['type'], 'content' => $rv['content']];
						} else {
							$replyList[$rv['id']] = ['type' => $rv['type'], 'mediaID' => $rv['content'], 'material_id' => $rv['material_id'], 'url' => $url];
						}
					}
				}
				$data['replyList'] = $replyList;
				$data['nick_name'] = $wxAuto->nick_name;
				$data['head_img'] = $wxAuto->head_img;
				$data['push_type'] = $auto->push_type;
				return $data;
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/auto-reply/delete
		 * @title           删除推送内容
		 * @description     删除推送内容
		 * @method   请求方式
		 * @url  http://{host_name}/api/auto-reply/delete
		 *
		 * @param id 必选 int 当前选择的id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/8 17:33
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws \Throwable
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post("id");
				if (empty($id)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				try {
					ReplyInfo::deleteAll(['rp_id'=>$id]);
					return true;
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}

				return [
					'error'     => 0,
					'error_msg' => "删除成功",
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/auto-reply/set-on-off
		 * @title           开关接口
		 * @description     开关接口
		 * @method   请求方式
		 * @url  http://{host_name}/api/auto-reply/set-on-off
		 *
		 * @param id 必选 int 当前选择的id
		 * @param status 必选 int 0关闭1开启
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/11 16:01
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionSetOnOff ()
		{
			if (\Yii::$app->request->isPost) {
				$id     = \Yii::$app->request->post('id');
				$status = \Yii::$app->request->post('status');
				if (empty($id)) {
					throw new InvalidDataException('参数不正确');
				}
				$reply         = ReplyInfo::find()->where(['id' => $id])->one();
				$reply->status = $status;
				$reply->save();
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/auto-reply/add
		 * @title           设置推送内容
		 * @description     设置推送内容
		 * @method   请求方式
		 * @url  http://{host_name}/api/auto-reply/add
		 *
		 * @param id 必选 int 当前选择的id
		 * @param msgData 必选 array 推送内容
		 * @param push_type 必选 int 1随机推送一条2全部推送
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/11 17:05
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				$id        = \Yii::$app->request->post('id');
				$msgData   = \Yii::$app->request->post('msgData');
				$push_type = \Yii::$app->request->post('push_type');
				if (!empty($msgData)) {
					foreach ($msgData as $mv) {
						if ($mv['type'] == 5) {
							foreach ($mv['newsList'] as $nv) {
								if (!empty($nv['id'])) {
									$reply = ReplyInfo::findOne($nv['id']);
								} else {
									$reply              = new ReplyInfo();
									$reply->create_time = DateUtil::getCurrentTime();
								}
								$reply->rp_id       = $id;
								$reply->type        = $mv['type'];
								$reply->content     = $nv['mediaID'];
								$reply->material_id = $nv['material_id'];
								$reply->title       = $nv['title'];
								$reply->digest      = $nv['digest'];
								$reply->cover_url   = $nv['cover_url'];
								$reply->content_url = $nv['content_url'];
								if (!$reply->save()) {
									throw new InvalidDataException(SUtils::modelError($reply));
								}
							}
						} else {
							if (!empty($nv['id'])) {
								$reply = ReplyInfo::findOne($nv['id']);
							} else {
								$reply              = new ReplyInfo();
								$reply->create_time = DateUtil::getCurrentTime();
							}
							if ($mv['type'] == 1) {
								$reply->content = $mv['content'];
							} elseif ($mv['type'] == 2 || $mv['type'] == 3 || $mv['type'] == 4) {
								$material = Material::findOne(['id' => $mv['material_id']]);
								if (empty($material)) {
									throw new InvalidDataException('此素材不存在');
								}
								$reply->content     = $material->media_id;
								$reply->material_id = $mv['material_id'];
							}
							if (!$reply->save()) {
								throw new InvalidDataException(SUtils::modelError($reply));
							}
						}

					}

					$auto            = AutoReply::findOne(['id' => $id]);
					$auto->push_type = $push_type;
					$auto->save();
				} else {
					throw new InvalidDataException('请设置推送内容');
				}
			} else {
				throw new NotAllowException("请求方式不正确");
			}

		}
		
	}