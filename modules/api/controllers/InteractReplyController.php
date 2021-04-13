<?php
	/**
	 * Create by PhpStorm
	 * User: wangpan
	 * Date: 2019/11/14
	 * Time: 09:35
	 */
	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\Attachment;
	use app\models\AttachmentGroup;
	use app\models\InteractReplyDetail;
	use app\models\InteractStatistic;
	use app\models\UserAuthorRelation;
	use app\models\WxAuthorizeInfo;
	use app\models\Article;
	use yii\web\MethodNotAllowedHttpException;
	use app\modules\api\components\AuthBaseController;
	use app\models\InteractReply;
	use app\models\AutoReply;
	use app\models\Material;
	use app\models\ReplyInfo;
	use app\util\SUtils;
	use app\util\DateUtil;
	use moonland\phpexcel\Excel;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;
	use app\queue\CalculateReplyJob;

	class InteractReplyController extends AuthBaseController
	{
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'list'              => ['POST'],
						'add'               => ['POST'],
						'set-on-off'        => ['POST'],
						'delete'            => ['POST'],
						'preview'           => ['POST'],
						'detail'            => ['POST'],
						'push-detail'       => ['POST'],
						'statistics'        => ['POST'],
						'statistics-export' => ['POST'],
					],
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/list
		 * @title           智能互动列表
		 * @description     智能互动列表
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/list
		 *
		 * @param wx_id 必选 int 公众号id
		 * @param type 必选 int 1关注2发消息
		 * @param status 选填 int 状态1全部2未设置3设置
		 * @param page 选填 int 分页
		 * @param pageSize 选填 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/15 11:40
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionList ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$status     = \Yii::$app->request->post('status'); //状态 1 全部 2 未设置 3 设置
				$page       = \Yii::$app->request->post('page'); //分页
				$pageSize   = \Yii::$app->request->post('pageSize'); //页数
				$type       = \Yii::$app->request->post('type');
				$authorInfo = $this->wxAuthorInfo->author;
				$type       = !empty($type) ? $type : 1; //1关注 2发消息
				$page       = !empty($page) ? $page : 1;
				$pageSize   = !empty($pageSize) ? $pageSize : 10;
				$offset     = ($page - 1) * $pageSize;
				$reply      = InteractReply::find()->where(['type' => $type, 'author_id' => $authorInfo->author_id]);
				if ($status == 2) {
					$reply = $reply->andWhere(['status' => 0]);
				} elseif ($status == 3) {
					$reply = $reply->andWhere(['status' => 1]);
				}
				$count  = $reply->count();
				$info   = $reply->limit($pageSize)->offset($offset)->orderBy('id desc')->all();
				$result = [];
				foreach ($info as $v) {
					$data              = $v->dumpData();
					$data['is_show']   = 1;
					$data['total_num'] = 0;
					if ($v->reply_type == 2) {
						$data['push_time'] = "每天";
					} else {
						if (time() > strtotime($v->end_time)) {
							$data['is_show'] = 0;
						}
						$stime             = explode(' ', $v->start_time);
						$etime             = explode(' ', $v->end_time);
						$data['push_time'] = $stime[0] . ' 至 ' . $etime[0];
					}
					$date1             = date('Y-m-d');
					$date2             = date('Y-m-d H:i:s', time());
					$data['push_num']  = InteractReplyDetail::find()->where(['inter_id' => $v->id, 'status' => 0])->andFilterWhere(['between', 'create_time', $date1, $date2])->groupBy('openid')->count();
					$data['total_num'] = InteractReplyDetail::find()->where(['inter_id' => $v->id, 'status' => 0])->groupBy('openid')->count();
					$auto              = AutoReply::find()->where(['inter_id' => $v->id])->select('time_json')->all();
					$time              = [];
					$str               = '关注';
					if ($v->type == 2) {
						$str = '收到消息';
					}
					foreach ($auto as $au) {
						$time_de = json_decode($au->time_json, true);
						
						if ($time_de[0] == 0 && $time_de[1] == 0) {
							$str .= '立即推送 、';
						} elseif (($time_de[0] == 0 && !empty($time_de[1]))) {
							$str .= $time_de[1] . '分钟后、';
						} elseif ((!empty($time_de[0]) && $time_de[1] == 0)) {
							$str .= $time_de[0] . '小时后、';
						} elseif ((!empty($time_de[0]) && !empty($time_de[1]))) {
							$str .= $time_de[0] . '小时' . $time_de[1] . '分钟后、';
						}
					
					}
				
					$str = rtrim($str,'、');

					
					$data['time'] = $str;
					unset($time);
					$data['content'] = '预览';
					array_push($result, $data);
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
		 * @catalog         数据接口/api/interact-reply/delete
		 * @title           删除接口
		 * @description     删除接口
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/delete
		 *
		 * @param id 必选 int 列表的id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/15 13:21
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 */
		public function actionDelete ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post("id");
				if (empty($id)) {
					throw new InvalidParameterException("缺少必要参数");
				}
				$transaction = \Yii::$app->db->beginTransaction();
				try {
					$auto = AutoReply::find()->andWhere(['inter_id' => $id])->asArray()->all();
					foreach ($auto as $v) {
						ReplyInfo::deleteAll(['rp_id' => $v['id']]);
					}
					AutoReply::deleteAll(['inter_id' => $id]);
					InteractReplyDetail::deleteAll(['inter_id' => $id]);
					InteractStatistic::deleteAll(['inter_id' => $id]);
					InteractReply::deleteAll(['id' => $id]);
					$transaction->commit();
				} catch (\Exception $e) {
					$transaction->rollBack();
					throw new InvalidDataException($e->getMessage());
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
		 * @catalog         数据接口/api/interact-reply/add
		 * @title           添加和修改接口
		 * @description     添加和修改接口
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/add
		 *
		 * @param scene_id 选填 int 添加时传0修改时传具体的id
		 * @param type 必选 int 1关注2消息回复
		 * @param title 必选 string 名称
		 * @param pushTimeType 必选 int 1今天2每天3指定日期
		 * @param ps_date 选填 string 指定日期的开始时间
		 * @param pe_date 选填 string 指定日期的结束时间
		 * @param unpushType 选填 bool true/false
		 * @param us_date 选填 string 不推送时间00:00
		 * @param ue_date 选填 string 不推送时间07:00
		 * @param msgData 必选 array 推送内容[[auto_id=>1,push_time=>[1,30],msgData=>[]],[auto_id=>1,push_time=>[1,30],msgData=>[]]]
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/15 15:05
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws \yii\db\Exception
		 */
		public function actionAdd ()
		{
			if (\Yii::$app->request->isPost) {
				if (empty($this->wxAuthorInfo)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$id              = \Yii::$app->request->post('scene_id');
				$type            = \Yii::$app->request->post('type') ?: 1;//1关注2发消息
				$title           = \Yii::$app->request->post('title');
				$reply_type      = \Yii::$app->request->post('pushTimeType');
				$start_time      = \Yii::$app->request->post('ps_date');
				$end_time        = \Yii::$app->request->post('pe_date');
				$no_send_type    = \Yii::$app->request->post('unpushType');
				$us_date         = \Yii::$app->request->post('us_date');
				$ue_date         = \Yii::$app->request->post('ue_date');
				$content         = \Yii::$app->request->post('msgData');
				$no_send_time[0] = $us_date;
				$no_send_time[1] = $ue_date;
				$authorInfo      = $this->wxAuthorInfo->author;
				if (empty($id) && empty($type)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($title)) {
					throw new InvalidParameterException('名称不能为空！');
				}
				if (empty($content)) {
					throw new InvalidParameterException('推送内容不能为空！');
				}
				if ($no_send_type && ($no_send_time[0] == $no_send_time[1])) {
					throw new InvalidParameterException('不推送时间段不能完全相同！');
				}
				if ($no_send_type) {
					$no_send_type = 1;
				} else {
					$no_send_type = 0;
				}
				if (!empty($id)) {
					$inter_reply   = InteractReply::findOne($id);
					$auto_reply_id = '';
					$reply_info_id = [];
					$auto          = AutoReply::find()->andWhere(['inter_id' => $id])->asArray()->all();
					if (!empty($auto)) {
						$auto_reply_id = array_column($auto, 'id');
						foreach ($auto as $v) {
							$reply_info = ReplyInfo::find()->andWhere(['rp_id' => $v['id']])->asArray()->all();
							if (!empty($reply_info)) {
								foreach ($reply_info as $info) {
									array_push($reply_info_id, $info['id']);
								}
							}
						}
					}
					$inter_reply->update_time = DateUtil::getCurrentTime();
					$intr                     = InteractReply::find()->andWhere(['type' => $type, 'title' => trim($title), 'author_id' => $authorInfo->author_id])->andWhere(['!=', 'id', $id])->one();
				} else {
					$inter_reply              = new InteractReply();
					$inter_reply->create_time = DateUtil::getCurrentTime();
					$inter_reply->author_id   = $authorInfo->author_id;
					$inter_reply->status      = 1;
					$intr                     = InteractReply::find()->andWhere(['type' => $type, 'title' => trim($title), 'author_id' => $authorInfo->author_id])->one();
				}
				if (!empty($intr)) {
					throw new InvalidParameterException('任务名称存在重复！');
				}
				$transaction = \Yii::$app->db->beginTransaction();
				try {
//					if ($reply_type == 1 && empty($id)) {
//						$start_time = date("Y-m-d", time());
//						$end_time   = date("Y-m-d", time()) . ' 23:59:59';
//						//今天
//						$intr       = InteractReply::find()->andWhere(['reply_type' => 1,'status'=>1,'author_id' => $authorInfo->author_id])->andFilterWhere(['between', 'create_time', $start_time, $end_time])->one();
//						if (!empty($intr)) {
//							throw new InvalidParameterException('今天日期不能重复设置！');
//						}
//					}
//					if ($reply_type == 2 && empty($id)) {
//						//每天
//						$intr = InteractReply::find()->andWhere(['reply_type' => 2, 'status'=>1,'author_id' => $authorInfo->author_id])->one();
//						if (!empty($intr)) {
//							throw new InvalidParameterException('每天日期不能重复设置！');
//						}
//					}
					if ($reply_type == 3 && (empty($start_time) || empty($end_time))) {
						throw new InvalidParameterException('指定日期不能为空！');
					}
					if ($reply_type == 1) {
						$start_time = date("Y-m-d", time());
						$end_time   = date("Y-m-d", time()) . ' 23:59:59';
					}
					if ($reply_type == 3) {
						$end_time = $end_time . ' 23:59:59';
					}
					if ($reply_type == 2) {
						$start_time = DateUtil::getCurrentTime();
						$end_time   = DateUtil::getCurrentTime();
					}
					$inter_reply->title        = $title;
					$inter_reply->type         = $type;
					$inter_reply->reply_type   = $reply_type;
					$inter_reply->start_time   = $start_time;
					$inter_reply->end_time     = $end_time;
					$inter_reply->no_send_type = $no_send_type;
					$inter_reply->no_send_time = json_encode($no_send_time);
					if (!$inter_reply->validate() || !$inter_reply->save()) {
						throw new InvalidDataException(SUtils::modelError($inter_reply));
					}
					$auto_reply_id_new = [];
					$i = 0;
					foreach ($content as $con) {
						$auto              = new AutoReply();
						$auto->author_id   = $authorInfo->author_id;
						$auto->create_time = DateUtil::getCurrentTime();
						$auto->inter_id    = $inter_reply->id;
						$auto->replay_type = 1;
						$push_time[0]      = $con['pushHour'];
						$push_time[1]      = $con['pushMinutes'];
						$auto->time_json   = json_encode($push_time);
						if (!$auto->validate() || !$auto->save()) {
							throw new InvalidDataException(SUtils::modelError($auto));
						}
						array_push($auto_reply_id_new, $auto->id);
						$msgData = $con['list'];
						foreach ($msgData as $mv) {
							if ($mv['type'] == 5) {
								foreach ($mv['newsList'] as $nv) {
									$reply              = new ReplyInfo();
									$reply->rp_id       = $auto->id;
									$reply->type        = $mv['type'];
									$reply->status      = 1;//默认开启
									$reply->create_time = DateUtil::getCurrentTime();
									if (empty($nv['is_use'])) {
										//改版 采用文件柜
										$attachment = Attachment::findOne(['id' => $nv['material_id'], 'status' => 1]);
										if (empty($attachment)) {
											throw new InvalidDataException('内容'.($i+1).'图文素材不存在');
										}
										$reply->attachment_id = $attachment->id;
										if (!empty($attachment->material_id) && $attachment->material->author_id == $authorInfo->author_id && !empty($attachment->material->status)) {
											$reply->content     = $attachment->material->media_id;
											$reply->material_id = $attachment->material->id;
										}else{
											//$material = Material::findOne(['author_id'=>$authorInfo->author_id,'attachment_id' => $attachment->id,'status'=>1]);
											$material = Material::getMaterial(['author_id' => $authorInfo->author_id, 'attachment_id' => $attachment->id, 'file_type' => $attachment->file_type]);
											if (!empty($material)) {
												$reply->content     = $material->media_id;
												$reply->material_id = $material->id;
											} else {
												$reply->title       = $attachment->file_name;
												$reply->digest      = $attachment->content;
												$reply->cover_url   = $attachment->local_path;
												$reply->content_url = $attachment->jump_url;
											}
										}

//										$material = Material::findOne(['id' => $nv['material_id'], 'status' => 1]);
//										if (empty($material)) {
//											throw new InvalidDataException('内容'.($i+1).'图文素材不存在');
//										}
//										$reply->content     = $material->media_id;
//										$reply->material_id = $nv['material_id'];
									} else {
										$reply->title       = $nv['title'];
										$reply->digest      = $nv['digest'];
										$site_url           = \Yii::$app->params['site_url'];
										$cover_url          = str_replace($site_url, '', $nv['cover_url']);
										$reply->cover_url   = $cover_url;
										$reply->content_url = $nv['content_url'];
										//$reply->material_id = !empty($nv['material_id']) ? $nv['material_id'] : '';
										$reply->attachment_id = !empty($nv['material_id']) ? $nv['material_id'] : '';
										$reply->is_use = 1;
										//是否同步自定义图文
										if(!empty($nv['is_sync'])){
											$reply->is_sync = 1;

											$userRelation = UserAuthorRelation::findOne(['author_id'=>$authorInfo->author_id]);
											if(!empty($nv['attach_id'])){
												$attachment = Attachment::findOne($nv['attach_id']);
												$attachment->file_name  = $nv['title'];
												$attachment->content    = $nv['digest'];
												$attachment->local_path = $cover_url;
												$attachment->jump_url   = $nv['content_url'];
												if(!empty($attachment->dirtyAttributes)){
													$attachment = new Attachment();
													$attachment->uid = $userRelation->uid;
													$attachment->file_type   = 4;
													$attachment->create_time = DateUtil::getCurrentTime();
												}
												$attachment->group_id   = $nv['group_id'];
											}else{
												$attachment = new Attachment();
												$attachment->uid = $userRelation->uid;
												$attachment->file_type   = 4;
												$attachment->create_time = DateUtil::getCurrentTime();
											}
											$attachment->group_id   = $nv['group_id'];
											$attachment->file_name  = $nv['title'];
											$attachment->content    = $nv['digest'];
											$attachment->local_path = $cover_url;
											$attachment->jump_url   = $nv['content_url'];
											if (!$attachment->validate() || !$attachment->save()) {
												throw new InvalidDataException(SUtils::modelError($attachment));
											}
											$reply->attach_id = $attachment->id;
										}
									}
									if (!$reply->save()) {
										throw new InvalidDataException(SUtils::modelError($reply));
									}
								}
							} else {
								$reply              = new ReplyInfo();
								$reply->rp_id       = $auto->id;
								$reply->type        = $mv['type'];
								$reply->status      = 1;//默认开启
								$reply->create_time = DateUtil::getCurrentTime();
								if ($mv['type'] == 1) {
									$reply->content = rawurlencode($mv['content']);
								} elseif ($mv['type'] == 2 || $mv['type'] == 3 || $mv['type'] == 4) {
									//改版 采用文件柜
									$attachment = Attachment::findOne(['id' => $mv['material_id'], 'status' => 1]);
									if (empty($attachment)) {
										if ($mv['type'] == 2) {
											$msg = '图片';
										} elseif ($mv['type'] == 3) {
											$msg = '音频';
										} elseif ($mv['type'] == 4) {
											$msg = '视频';
										}
										throw new InvalidDataException('内容'.($i+1).$msg . '素材不存在');
									}
									$reply->attachment_id = $attachment->id;
									if (!empty($attachment->material_id) && $attachment->material->author_id == $authorInfo->author_id && !empty($attachment->material->status)) {
										$reply->content     = $attachment->material->media_id;
										$reply->material_id = $attachment->material->id;
									}else{
										//$material = Material::findOne(['author_id'=>$authorInfo->author_id,'attachment_id' => $attachment->id,'status'=>1]);
										$material = Material::getMaterial(['author_id' => $authorInfo->author_id, 'attachment_id' => $attachment->id, 'file_type' => $attachment->file_type]);
										if (!empty($material)) {
											$reply->content     = $material->media_id;
											$reply->material_id = $material->id;
										}
									}

//									$material = Material::findOne(['id' => $mv['material_id'], 'status' => 1]);
//									if (empty($material)) {
//										if ($mv['type'] == 2) {
//											$msg = '图片';
//										} elseif ($mv['type'] == 3) {
//											$msg = '音频';
//										} elseif ($mv['type'] == 4) {
//											$msg = '视频';
//										}
//										throw new InvalidDataException('内容'.($i+1).$msg . '素材不存在');
//									}
//									$reply->content     = $material->media_id;
//									$reply->material_id = $mv['material_id'];
								}
								if (!$reply->save()) {
									throw new InvalidDataException(SUtils::modelError($reply));
								}
							}
							$i++;

						}

					}
					$transaction->commit();
				} catch (InvalidDataException $e) {
					$transaction->rollBack();
					throw new InvalidDataException($e->getMessage());
				}
				if (!empty($inter_reply->id)) {
					\Yii::$app->queue->push(new CalculateReplyJob([
						'inter_id'      => $inter_reply->id,
						'type'          => $type,
					]));
					if (!empty($reply_info_id)) {
						ReplyInfo::deleteAll(['id' => $reply_info_id]);
					}
					if (!empty($auto_reply_id)) {
						AutoReply::deleteAll(['id' => $auto_reply_id]);
					}
				}
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/preview
		 * @title           单个预览接口
		 * @description     单个预览接口
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/preview
		 *
		 * @param auto_id 必选 int 推送内容id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/17 13:20
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionPreview(){
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('auto_id');
				if (empty($id)) {
					throw new InvalidDataException('参数不正确');
				}
				$auto   = AutoReply::findOne($id);
				$wxAuto = WxAuthorizeInfo::find()->where(['author_id' => $auto->author_id])->one();
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

				return $replyList;
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/detail
		 * @title           获取详情
		 * @description     获取详情
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/detail
		 *
		 * @param id 必选 int 详情id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 标题
		 * @return_param    reply_type string 1今天2每天3指定日期
		 * @return_param    start_time string 指定日期的开始时间
		 * @return_param    end_time string 指定日期的结束时间
		 * @return_param    no_send_type string 1开2关
		 * @return_param    no_send_time array 不推送时段
		 * @return_param    content array 推送内容
		 * @return_param    time_json array 推送时间
		 * @return_param    auto_id string 预览所用的id
		 * @return_param    replyList array 图文材料信息
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/17 13:54
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$id = \Yii::$app->request->post('id');
				if (empty($id)) {
					throw new InvalidDataException('参数不正确');
				}
				$result = [];
				try {
					$interact               = InteractReply::findOne($id);
					$result['title']        = $interact->title;
					$result['reply_type']   = $interact->reply_type;
					$start_time = explode(' ',$interact->start_time);
					$end_time = explode(' ',$interact->end_time);
					$result['start_time']   = $start_time[0];
					$result['end_time']   = $end_time[0];
					$result['no_send_type'] = $interact->no_send_type;
					$result['no_send_time'] = json_decode($interact->no_send_time, true);
					$auto                   = $interact->autoReplies;
					$content                = [];
					foreach ($auto as $key => $reply) {
						$content[$key]['time_json'] = json_decode($reply['time_json'], true);
						$json = $content[$key]['time_json'];
						$replyInfo = ReplyInfo::find()->andWhere(['rp_id'=>$reply['id']])->asArray()->all();
						$replyList = [];
						$typeNum   = 0;
						$sketchId  = 0;
						$tempId = 0;
						foreach ($replyInfo as $rv) {
							if ($rv['type'] == 5) {
								if($interact->type==2 || !($json[0]==0 && $json[1]==0)){
									//$tempId                                    = $rv['id'];
									$replyList[$tempId]['id']                  = $typeNum;
									$replyList[$tempId]['typeValue']           = 5;
									$replyList[$tempId]['file_name']           = '';
									$replyList[$tempId]['material_id']         = 0;
									$replyList[$tempId]['local_path']          = ['img' => '', 'audio' => ''];
									$replyList[$tempId]['textAreaValueHeader'] = '';
									$typeNum++;
								}else{
									if (empty($tempId)) {
										$tempId                                    = $rv['id'];
										$replyList[$tempId]['id']                  = $typeNum;
										$replyList[$tempId]['typeValue']           = 5;
										$replyList[$tempId]['file_name']           = '';
										$replyList[$tempId]['material_id']         = 0;
										$replyList[$tempId]['local_path']          = ['img' => '', 'audio' => ''];
										$replyList[$tempId]['textAreaValueHeader'] = '';
										$typeNum++;
									}
								}
								if (!empty($rv['material_id']) && empty($rv['title'])) {
									$material = Material::findOne(['id' => $rv['material_id']]);
									$article  = Article::find()->alias('a');
									$article  = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
									$article  = $article->where(['a.id' => $material->article_sort])->select('a.title,a.digest,a.content_source_url,m.local_path')->asArray()->one();

									if (strpos($article['local_path'], 'http') === false) {
										$article['local_path'] = \Yii::$app->params['site_url'] . $article['local_path'];
									}

									$replyList[$tempId]['sketchList'][] = ['id' => $sketchId, 'addType' => 0, 'inputTitle' => $article['title'], 'digest' => $article['digest'], 'content_source_url' => $article['content_source_url'], 'material_id' => $rv['attachment_id'], 'local_path' => ['img' => $article['local_path'], 'audio' => '']];
								} else {
									if (strpos($rv['cover_url'], 'http') === false) {
										$rv['cover_url'] = \Yii::$app->params['site_url'] . $rv['cover_url'];
									}
									$material_id = !empty($rv['attachment_id'])?$rv['attachment_id']:0;
									$group_id = "";
									$attach_id = "";
									if(empty($rv['is_use'])){
										$addType = 0;
										$is_sync = 0;
									}else{
										$addType = 1;
										$is_sync = (int)$rv['is_sync'];
										if(!empty($is_sync)){
											$attach_id = $rv['attach_id'];
											$attachment = Attachment::findOne($rv['attach_id']);
											$group_id = (string)$attachment->group_id;
										}
									}
									$replyList[$tempId]['sketchList'][] = ['id' => $sketchId, 'addType' => $addType, 'inputTitle' => $rv['title'], 'digest' => $rv['digest'], 'content_source_url' => $rv['content_url'], 'material_id' => $material_id, 'local_path' => ['img' => $rv['cover_url'], 'audio' => ''], 'is_sync' => $is_sync, 'group_id' => $group_id,'attach_id'=>$attach_id];
								}
								$sketchId++;
								if($interact->type==2 || !($json[0]==0 && $json[1]==0)){
									$tempId++;
								}
							} elseif ($rv['type'] == 1) {
								$replyList[$rv['id']] = ['id' => $typeNum, 'typeValue' => (int) $rv['type'], 'file_name' => '', 'material_id' => 0, 'local_path' => ['img' => '', 'audio' => ''], 'sketchList' => [], 'textAreaValueHeader' => rawurldecode($rv['content'])];
								$typeNum++;
							} else {
								if(!empty($rv['attachment_id'])){
									$temp      = Attachment::findOne(['id' => $rv['attachment_id']]);
									$cover_url = !empty($temp->local_path) ? $temp->local_path : '';
									if (strpos($cover_url, 'http') === false) {
										$cover_url = \Yii::$app->params['site_url'] . $cover_url;
									}
									$file_name = $temp->file_name;
								}else{
									$cover_url = '';
									$file_name = '';
								}

								if ($rv['type'] == 2) {
									$local_path = ['img' => $cover_url, 'audio' => ''];
								} else {
									$local_path = ['img' => '', 'audio' => $cover_url];
								}
								$replyList[$rv['id']] = ['id' => $typeNum, 'typeValue' => (int) $rv['type'], 'file_name' => $file_name, 'material_id' => $rv['attachment_id'], 'local_path' => $local_path, 'sketchList' => [], 'textAreaValueHeader' => ''];
								$typeNum++;
							}
						}
						$replyList         = array_values($replyList);
						$content[$key]['replyList'] = $replyList;
					}
					$result['content'] = $content;
					return $result;
				} catch (InvalidDataException $e) {
					throw new InvalidDataException($e->getMessage());
				}
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/set-on-off
		 * @title           开关接口
		 * @description     开关接口
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/set-on-off
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
				$reply         = InteractReply::find()->where(['id' => $id])->one();
				$reply->status = $status;
				if(empty($status)){
					$reply->close_time = DateUtil::getCurrentTime();
				}
				$reply->save();
				return true;
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/push-detail
		 * @title           获取推送日志
		 * @description     获取推送日志
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/push-detail
		 *
		 * @param id 必选 int 列表的id
		 * @param page 选填 int 当前页
		 * @param pageSize 选填 int 页数
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    nickname string 昵称
		 * @return_param    headerimg string 头像
		 * @return_param    create_time string 时间
		 * @return_param    status string 状态
		 * @return_param    error_msg string 错误信息
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/21 9:36
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionPushDetail ()
		{
			if (\Yii::$app->request->isPost) {
				$id       = \Yii::$app->request->post('id');
				$page     = \Yii::$app->request->post('page'); //分页
				$pageSize = \Yii::$app->request->post('pageSize'); //页数
				if (empty($id)) {
					throw new InvalidDataException('参数不正确');
				}
				$page      = !empty($page) ? $page : 1;
				$pageSize  = !empty($pageSize) ? $pageSize : 10;
				$offset    = ($page - 1) * $pageSize;
				$interData = InteractReplyDetail::find()->alias('inter');
				$interData = $interData->leftJoin('{{%fans}} f', '`inter`.`openid` = `f`.`openid`');
				$interData = $interData->andWhere(['inter.inter_id' => $id]);
				$interData = $interData->andWhere(['<>', 'inter.status', 2]);//去除未发送的
				$count     = $interData->count();
				$info      = $interData->select('f.nickname,f.headerimg,inter.create_time,inter.push_time,inter.status,inter.error_msg')->limit($pageSize)->offset($offset)->asArray()->orderBy('inter.create_time desc')->all();
				$result    = [];
				if (!empty($info)) {
					foreach ($info as $k => $v) {
						$result[$k]['nickname']    = $v['nickname'];
						$result[$k]['headerimg']   = $v['headerimg'];
						$result[$k]['create_time'] = $v['push_time'];
						if ($v['status'] == 0) {
							$result[$k]['status']    = "发送成功";
							$result[$k]['error_msg'] = "--";
						} else {
							$result[$k]['status']    = "发送失败";
							$result[$k]['error_msg'] = $v['error_msg'];
						}
					}
				}

				return [
					'count' => $count,
					'info'  => $result,
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/statistics
		 * @title           统计
		 * @description     统计
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/statistics
		 *
		 * @param id 必选 int 列表id
		 * @param page 选填 int 当前页默认1
		 * @param pageSize 选填 int 页数默认15
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    name string 公众号名称
		 * @return_param    send_num string 发送次数
		 * @return_param    receive_num string 接收次数
		 * @return_param    date_time string 统计时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/21 19:40
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionStatistics ()
		{
			if (\Yii::$app->request->isPost) {
				$id       = \Yii::$app->request->post('id');
				$page     = \Yii::$app->request->post('page'); //分页
				$pageSize = \Yii::$app->request->post('pageSize'); //页数
				if (empty($id)) {
					throw new InvalidDataException('参数不正确');
				}
				$page      = !empty($page) ? $page : 1;
				$pageSize  = !empty($pageSize) ? $pageSize : 15;
				$interData = InteractReply::findOne($id);
				$wx_info   = WxAuthorizeInfo::find()->where(['author_id' => $interData->author_id])->one();
				$count  = 0;
				$result = [];
				$info   = InteractReply::getLastData($count, $result, $interData, $wx_info->nick_name, $id, $page, $pageSize);

				return [
					'count' => $info['count'],
					'info'  => $info['result'],
				];
			} else {
				throw new NotAllowException("请求方式不正确");
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/interact-reply/statistics-export
		 * @title           统计导出
		 * @description     统计导出
		 * @method   post
		 * @url  http://{host_name}/api/interact-reply/statistics-export
		 *
		 * @param id 必选 int 列表id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2019/11/22 9:52
		 * @number          0
		 *
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionStatisticsExport ()
		{
			if (\Yii::$app->request->isPost) {
				try {
					$id = \Yii::$app->request->post('id');
					if (empty($id)) {
						return ['error' => 1, 'msg' => '参数不正确'];
					}
					$interData = InteractReply::findOne($id);
					$wx_info   = WxAuthorizeInfo::find()->where(['author_id' => $interData->author_id])->one();
					$count     = 0;
					$result    = [];
					$data      = InteractReply::getLastData($count, $result, $interData, $wx_info->nick_name, $id, '', '', 1);
					$info      = $data['result'];
					if (empty($info)) {
						return ['error' => 1, 'msg' => '暂无数据，无法导出'];
					}
					$save_dir = \Yii::getAlias('@upload') . '/exportfile/' . date('Ymd') . '/';
					//创建保存目录
					if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
						return ['error' => 1, 'msg' => '无法创建目录'];
					}
					$columns  = ['date_time', 'name', 'send_num', 'receive_num'];
					$headers  = [
						'date_time'        => '日期',
						'name'        => '公众号',
						'send_num'  => '发送次数',
						'receive_num' => '接收人数'
					];
					$name = '关注回复';
					if ($interData->type == 2) {
						$name = '收到消息回复';
					}
					$fileName = $name . '统计_' . date("YmdHis", time());
					Excel::export([
						'models'       => $info,//数库
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
				} catch (\Exception $e) {
					return [
						'error'     => $e->getCode(),
						'error_msg' => $e->getMessage(),
					];
				}
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

	}