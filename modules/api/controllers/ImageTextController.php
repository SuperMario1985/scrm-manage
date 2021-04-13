<?php
	/**
	 * H5图文接口
	 * User: fulu
	 * Date: 2020/02/29
	 * Time: 17:17
	 */

	namespace app\modules\api\controllers;

	use app\models\Attachment;
	use app\models\AttachmentStatistic;
	use app\models\Material;
	use app\models\Article;
	use app\components\InvalidDataException;
	use app\models\UserAuthorRelation;
	use app\models\WorkChatInfo;
	use app\models\WorkExternalContact;
	use app\modules\api\components\BaseController;
	use yii\web\MethodNotAllowedHttpException;

	class ImageTextController extends BaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/image-text/
		 * @title           获取图文预览内容
		 * @description     获取图文预览内容及图文详情接口
		 * @method   post
		 * @url  http://{host_name}/api/image-text/image-text-preview-info
		 *
		 * @param num  可选 int 缓存键值（预览必选）
		 * @param attach_id  可选 int 附件表id（单图文详情）
		 * @param article_id  可选 int 图文表id（多图文详情）
		 * @param user  可选 int|string 访问者
		 * @param user_type  可选 int 访问者类型：2、外部联系人；3、位置类型（默认2）
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    title string 图文标题
		 * @return_param    author string 作者
		 * @return_param    pic_url string 图片封面
		 * @return_param    image_text string 编辑器图文
		 * @return_param    statistic_id int 统计ID
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-02-28
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionImageTextPreviewInfo ()
		{
			$num        = \Yii::$app->request->post('num');
			$attach_id  = \Yii::$app->request->post('attach_id');
			$article_id = \Yii::$app->request->post('article_id');
			$chat_id    = \Yii::$app->request->post('chat_id');
			$user       = \Yii::$app->request->post('user');
			$userType   = \Yii::$app->request->post('user_type', AttachmentStatistic::EXTERNAL_USER);
			$num        = intval($num);
			$attach_id  = intval($attach_id);
			$article_id = intval($article_id);
			if (empty($num) && empty($attach_id) && empty($article_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$site_url    = \Yii::$app->params['site_url'];
			$previewData = [];
			if (!empty($num)) {
				//获取缓存预览内容
				$cacheKey    = 'image_text_' . $num;
				$previewData = \Yii::$app->cache->get($cacheKey);

				if (empty($previewData)) {
					throw new InvalidDataException('二维码已过期，请重新生成二维码！');
				}
				if ($previewData['show_cover_pic']) {
					$previewData['image_text'] = '<p style="text-align: center;"><img src="' . $site_url . $previewData['pic_url'] . '"/></p>' . $previewData['image_text'];
				}
			} elseif (!empty($attach_id)) {
				//单图文详情
				$info                   = Attachment::findOne($attach_id);
				$previewData['uid']     = $info->uid;
				$previewData['title']   = $info->file_name;
				$previewData['author']  = $info->author;
				$previewData['pic_url'] = $info->local_path;
				if ($info->show_cover_pic) {
					$info->image_text = '<p style="text-align: center;"><img src="' . $site_url . $info->local_path . '"/></p>' . $info->image_text;
				}
				$previewData['image_text'] = $info->image_text;

				if (!empty($user)) {
					$userId = 0;
					$statisticData = [];
					if ($userType == AttachmentStatistic::EXTERNAL_USER) {
						$externalContact = WorkExternalContact::findOne(['external_userid' => $user]);
						if (!empty($externalContact) && $externalContact->corp->userCorpRelations[0]->uid == $info->uid) {
							$userId = $externalContact->id;
							$statisticData['corp_id'] = $externalContact->corp->id;
						}
						if (!empty($chat_id) && !empty($externalContact)) {
							$chatInfo = WorkChatInfo::findOne(['chat_id' => $chat_id, 'external_id' => $externalContact->id, 'type' => 2, 'status' => 1]);
							if (!empty($chatInfo)) {
								$statisticData = [
									'chat_id' => $chat_id,
								];
							}
						}
					} elseif ($userType == AttachmentStatistic::PUBLIC_USER) {
						$userId = $user;
					}

					if (!empty($userId)) {
						$previewData['statistic_id'] = AttachmentStatistic::create($attach_id, $userId, $statisticData, AttachmentStatistic::ATTACHMENT_OPEN, $userType);
					}
				}
			} elseif (!empty($article_id)) {
				//多图文某一图文详情
				$info                   = Article::findOne($article_id);
				$previewData['title']   = $info->title;
				$previewData['author']  = $info->author;
				$materialCover          = Material::findOne(['id' => $info->thumb_media_id]);
				$userAuthor             = UserAuthorRelation::findOne($materialCover->author_id);
				$previewData['uid']     = !empty($userAuthor) ? $userAuthor->uid : 0;
				$previewData['pic_url'] = $materialCover->local_path;
				if ($info->show_cover_pic) {
					$info->content = '<p style="text-align: center;"><img src="' . $site_url . $materialCover->local_path . '"/></p>' . $info->content;
				}
				$previewData['image_text'] = $info->content;
			}

			return $previewData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/image-text/
		 * @title           H5图文详情
		 * @description     H5图文详情（不用）
		 * @method   post
		 * @url  http://{host_name}/api/image-text/image-text-detail
		 *
		 * @param attachment_id 必选 int 附件id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    group_id  string 分组id
		 * @return_param    attachment_id  int 附件id
		 * @return_param    data array 结果数据
		 * @return_param    data.title string 图文标题
		 * @return_param    data.content string 图文描述
		 * @return_param    data.image_text string 编辑器图文
		 * @return_param    data.author string 作者
		 * @return_param    data.show_cover_pic int 是否显示封面1是0否
		 * @return_param    data.attach_id int 封面图片id
		 * @return_param    data.pic_url string 图片封面
		 * @return_param    data.jump_url string 跳转链接
		 * @return_param    data.article_id int 图文表id
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-03-02
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionImageTextDetail ()
		{
			$attachment_id = \Yii::$app->request->post('attachment_id');
			if (empty($attachment_id)) {
				$attachment_id = \Yii::$app->request->get('attachment_id');
			}
			if (empty($attachment_id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$attachment = Attachment::findOne($attachment_id);
			$msgData    = [];
			if ($attachment->material_id) {
				$materialInfo = Material::find()->select('id,local_path,article_sort')->where(['id' => $attachment->material_id])->asArray()->one();
				if (!empty($materialInfo['article_sort'])) {
					$article = Article::find()->alias('a');
					$article = $article->leftJoin('{{%material}} m', 'm.id = a.thumb_media_id');
					$artList = $article->where('a.id in(' . $materialInfo['article_sort'] . ')')->orderBy(["FIELD(a.id," . $materialInfo['article_sort'] . ")" => true])->select('a.*,m.local_path,m.attachment_id')->asArray()->all();
					foreach ($artList as $v) {
						$data                   = [];
						$data['title']          = $v['title'];
						$data['content']        = $v['digest'];
						$data['image_text']     = $v['content'];
						$data['author']         = $v['author'];
						$data['show_cover_pic'] = $v['show_cover_pic'] ? true : false;
						$data['attach_id']      = $v['attachment_id'];
						$data['pic_url']        = $v['local_path'];
						$data['jump_url']       = $v['content_source_url'];
						$data['article_id']     = $v['id'];

						$msgData[] = $data;
					}
				}
			} else {
				$data                   = [];
				$data['title']          = $attachment->file_name;
				$data['content']        = $attachment->content;
				$data['image_text']     = $attachment->image_text;
				$data['author']         = $attachment->author;
				$data['show_cover_pic'] = $attachment->show_cover_pic ? true : false;
				$data['attach_id']      = $attachment->attach_id;
				$data['pic_url']        = $attachment->local_path;
				$data['jump_url']       = $attachment->jump_url;
				$data['article_id']     = '';

				$msgData[] = $data;
			}

			return ['attachment_id' => $attachment->id, 'group_id' => $attachment->group_id, 'data' => $msgData];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/image-text/
		 * @title           素材打开
		 * @description     素材打开时间
		 * @method   post
		 * @url  http://{host_name}/api/image-text/get-statistic-id
		 *
		 * @param attach_id  可选 int 附件表id
		 * @param user  可选 int|string 访问者
		 * @param user_type  可选 int 访问者类型：2、外部联系人；3、位置类型（默认2）
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    statistic_id int 统计ID
		 *
		 * @remark          Create by PhpStorm. User: xingchangyu. Date: 2020-07-29 14:07
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 * @throws \app\components\InvalidParameterException
		 */
		public function actionGetStatisticId ()
		{
			if (\Yii::$app->request->isGet) {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
			$attach_id   = \Yii::$app->request->post('attach_id');
			$user        = \Yii::$app->request->post('user');
			$userType    = \Yii::$app->request->post('user_type', AttachmentStatistic::EXTERNAL_USER);
			$info        = Attachment::findOne($attach_id);
			$statisticId = 0;
			if (!empty($user) && !empty($info)) {
				$userId        = 0;
				$statisticData = [];
				if ($userType == AttachmentStatistic::EXTERNAL_USER) {
					$externalContact = WorkExternalContact::findOne(['external_userid' => $user]);
					if (!empty($externalContact) && $externalContact->corp->userCorpRelations[0]->uid == $info->uid) {
						$userId                   = $externalContact->id;
						$statisticData['corp_id'] = $externalContact->corp->id;
					}
				} elseif ($userType == AttachmentStatistic::PUBLIC_USER) {
					$userId = $user;
				}

				if (!empty($userId)) {
					$statisticId = AttachmentStatistic::create($attach_id, $userId, $statisticData, AttachmentStatistic::ATTACHMENT_OPEN, $userType);
				}
			}

			return ['statistic_id' => $statisticId];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/image-text/
		 * @title           素材离开
		 * @description     离开素材时间更新
		 * @method   POST
		 * @url  http://{host_name}/api/image-text/leave
		 *
		 * @param statistic_id 必选 int 统计ID
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2020/4/16 12:09
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionLeave ()
		{
			if (\Yii::$app->request->isPost) {
				$statisticId = \Yii::$app->request->post('statistic_id');
				if (empty($statisticId)) {
					throw new InvalidDataException('缺少必要参数！');
				}

				$attachmentStatistic = AttachmentStatistic::findOne($statisticId);
				if (empty($attachmentStatistic)) {
					throw new InvalidDataException('参数不正确');
				}

				return $attachmentStatistic->setLeaveTime();
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}
	}