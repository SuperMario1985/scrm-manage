<?php
	/**
	 * 抽奖接口
	 * User: wangpan
	 * Date: 2020/3/27
	 * Time: 17:17
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\components\InvalidParameterException;
	use app\models\AwardsJoin;
	use app\models\AwardsJoinDetail;
	use app\models\AwardsShare;
	use app\models\Ucenter;
	use app\models\AwardsActivity;
	use app\models\AwardsList;
	use app\models\AwardsRecords;
    use app\models\User;
    use app\models\WorkCorpAgent;
	use app\models\WorkExternalContact;
	use app\models\WorkExternalContactFollowUser;
	use app\models\WorkMaterial;
	use app\models\WorkUser;
	use app\modules\api\components\BaseController;
	use app\queue\SyncAwardJob;
	use app\util\DateUtil;
	use app\util\MsgUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;
	use linslin\yii2\curl\Curl;
	use yii\db\Expression;
	use yii\web\MethodNotAllowedHttpException;
	use app\models\WorkCorp;
	use app\models\UserCorpRelation;
	use app\models\RedPack;

	class WapAwardController extends BaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-award/
		 * @title           H5抽奖页面
		 * @description     H5抽奖页面
		 * @method   post
		 * @url  http://{host_name}/api/wap-award/get-awards
		 *
		 * @param corp_id 必选 int 企业id
		 * @param agent_id 必选 int 应用id
		 * @param code 必选 int code
		 * @param assist 必选 string assist
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    join_id int 参与人id
		 * @return_param    info array 奖项规则
		 * @return_param    records array 中奖纪录
		 * @return_param    rules array 活动规则
		 * @return_param    awards array 奖品列表
		 * @return_param    status int 1、活动已结束2、活动未开始
		 * @return_param    chance int 剩余抽奖机会
		 * @return_param    isContact int 0是外部联系人1不是
		 * @return_param    picRule array 海报规则
		 * @return_param    externalId int 上级id
		 * @return_param    awardId int 活动id
		 * @return_param    nick_name string 昵称
		 * @return_param    head_url string 头像
		 * @return_param    share_url string 分享链接和海报链接
		 * @return_param    my_url string 进入我的
		 * @return_param    base64Data string 海报的头像
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/18 13:26
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionGetAwards ()
		{
			if (\Yii::$app->request->isPost) {
				$corp_id       = \Yii::$app->request->post('corp_id', '');
				$agent_id      = \Yii::$app->request->post('agent_id', '');
				$code          = \Yii::$app->request->post('code', '');
				$assist        = \Yii::$app->request->post('assist', '');
				$web_url       = \Yii::$app->params['web_url'];
				$stateArr      = explode('_', $assist);//award_id_0
				$award_id      = isset($stateArr[1]) ? intval($stateArr[1]) : 0;
				$externalId    = isset($stateArr[2]) ? intval($stateArr[2]) : 0;//上级id
				$isContact     = 0; //0 不是外部联系人 1 是
				$awardActivity = AwardsActivity::findOne($award_id);
				$joinId        = 0;
				$is_mine       = 0;
				$external_id   = \Yii::$app->request->post('external_id', '');//外部联系人id
				$openId        = '';
				if (empty($awardActivity)) {
					throw new InvalidDataException('链接地址不正确，请检查链接地址');
				}
				$corpAgent   = WorkCorpAgent::findOne($awardActivity->agent_id);
				try {
					if (empty($external_id)) {

						if (empty($code)) {
							throw new InvalidDataException('参数不正确');
						}
						WorkUtils::getUserData($code, $corp_id, $result, [], true);
						if (!empty($result)) {
							if (!empty($result->UserId)) {
								throw new InvalidDataException('您已是企业成员或已绑定过个人微信，均无法参与活动！');
							}
							if (!empty($result->OpenId)) {
								$externalContact = WorkExternalContact::find()->where(['corp_id' => $corp_id, 'openid' => $result->OpenId])->andWhere(['!=', 'avatar', ''])->one();
								if (empty($externalContact)) {
									$externalContact = WorkExternalContact::findOne(['corp_id' => $corp_id, 'openid' => $result->OpenId]);
								}
								if (!empty($externalContact)) {
									$external_id = $externalContact->id;
								}
								$openId = $result->OpenId;
							}
							\Yii::error($result->OpenId, 'openid');
						} else {
							throw new InvalidDataException('获取用户信息失败，请重新刷新');
						}
					} else {
						$externalContact = WorkExternalContact::findOne($external_id);
						if (!empty($externalContact)) {
							$openId = $externalContact->openid;
						}
					}
				} catch (\Exception $e) {
					$message = $e->getMessage();
					if (strpos($message, '40029') !== false) {
						$message = '不合法的oauth_code';
					} elseif (strpos($message, '50001') !== false) {
						$message = 'redirect_url未登记可信域名';
					}
					throw new InvalidDataException($message);
				}
				$new_apply_setting = json_decode($awardActivity->apply_setting, true);
				if(empty($new_apply_setting['limit'])){
					$initNum = $awardActivity->init_num;
				}else{
					if ($new_apply_setting['total_num'] >= $awardActivity->init_num) {
						$initNum = $awardActivity->init_num;
					} else {
						$initNum = $new_apply_setting['total_num'];
					}
				}
				$id      = $awardActivity->id;

				if (!empty($externalContact)) {
//					if($externalId == $externalContact->id){
//						$is_mine = 1;
//					}
					$userArr         = json_decode($awardActivity->user, true);
					$contactFollower = WorkExternalContactFollowUser::find()->where(['external_userid' => $externalContact->id, 'del_type' => 0])->select('userid')->asArray()->all();
					if (!empty($contactFollower)) {
						$userId    = array_column($contactFollower, 'userid');
						$intersect = array_intersect($userId, $userArr);
					}
//					$awardJoin = AwardsJoin::findOne(['award_id' => $awardActivity->id, 'external_id' => $externalContact->id]);
//					if (!empty($awardJoin)) {
//						$joinId    = $awardJoin->id;
//						$isContact = 1;
//					}
					if (!empty($intersect)) {
						$is_add = 1;//第一次进来，是否可以添加为参与者或者助力者
						if ($awardActivity->area_type == 2) {
							$is_add = 0;
						} elseif ($awardActivity->sex_type != 1) {
							$is_limit = RedPack::checkSex($externalContact->id, $awardActivity->sex_type);
							$is_add   = !empty($is_limit) ? 0 : 1;
						}
						if (!empty($is_add)) {
							//当前已经是外部联系人
							AwardsJoin::add($awardActivity->id, $externalContact->id, $externalId, $externalContact, $initNum, $awardActivity->share_setting);
//							\Yii::error($joinId, '$joinId');
//							if (!empty($joinId)) {
//								$isContact = 1;
//							}
						}
					}
				}

				$info   = [];
				$rules  = []; //规则
				$status = 0; //0 正常 1 活动已结束 2 活动未开始 3 活动尚未发布 4 活动已删除

				if (!empty($awardActivity)) {
					if ($awardActivity->is_del == 1) {
						$status = 4;
					}
					if (strtotime($awardActivity->start_time) > time()) {
						$status = 2;
					}
					if (strtotime($awardActivity->end_time) < time() || $awardActivity->status==4 || $awardActivity->status==3) {
						$status = 1;
						if($awardActivity->status==0 || $awardActivity->status==1){
							$awardActivity->status = 2;
							$awardActivity->save();
							\Yii::$app->queue->push(new SyncAwardJob([
								'award_id'     => $awardActivity->id,
								'award_status' => 2
							]));
						}
					}
					if (empty($awardActivity->status)) {
						$status = 3;
					}
					//$start_time             = explode(' ', $awardActivity->start_time);
					//$end_time               = explode(' ', $awardActivity->end_time);
					$rules['start_time']    = substr($awardActivity->start_time, 0, 16);
					$rules['end_time']      = substr($awardActivity->end_time, 0, 16);
					$rules['description']   = $awardActivity->description;
					$rules['init_num']      = $initNum;
					$rules['apply_setting'] = json_decode($awardActivity->apply_setting, true);
					$rules['share_setting'] = json_decode($awardActivity->share_setting, true);
					$rules['share_setting'][1]['limit1'] = $rules['share_setting'][1]['limit'];
					//中奖数据
					$data            = AwardsRecords::getRecords($id);
					$content         = AwardsList::find()->where(['award_id' => $id])->asArray()->all();
					$info['awards']  = AwardsActivity::getAwards($content);
					$info['rules']   = $rules;
					$info['records'] = $data;
				}
				$head_url   = '';//当前人的头像
				$nick_name  = '';//当前人的昵称
//				$contact_id = 0;
//				if ($isContact == 1) {
//					$awardJoin = AwardsJoin::findOne($joinId);
//					if (!empty($awardJoin)) {
//						$contact = WorkExternalContact::findOne($awardJoin->external_id);
//						if (!empty($contact)) {
//							$head_url   = $contact->avatar;
//							$nick_name  = $contact->name;
//							$contact_id = $contact->id;
//						}
//					}
//				}
				$workCorp = WorkCorp::findOne($awardActivity->corp_id);
				$state    = AwardsActivity::AWARD_HEAD . '_' . $awardActivity->id . '_0';
				//进入我的
				$my_url = '';
				$is_help = 0;
				//没有上级
				if (!empty($externalId)) {
					$awardsJoin = AwardsJoin::findOne(['award_id' => $awardActivity->id, 'external_id' => $externalId]);
					if (!empty($awardsJoin)) {
						$workContact = WorkExternalContact::findOne($externalId);
						if (!empty($workContact)) {
							$state = AwardsActivity::AWARD_HEAD . '_' . $awardActivity->id . '_' . $workContact->id;
						}
						$is_help = 1;
						if (!empty($externalContact) && ($externalId == $externalContact->id)) {
							$is_mine = 1;
							$is_help = 0;
						}
						$head_url  = $workContact->avatar;
						$nick_name = $workContact->name;
						if (empty($is_mine) && !empty($external_id)) {
							$joinDetail = AwardsJoinDetail::findOne(['awards_join_id' => $awardsJoin->id, 'external_id' => $external_id]);
							if (!empty($joinDetail)) {
								$is_help = 2;
							}else{
								$shareSetting = json_decode($awardActivity->share_setting, true);
								$shareNum = $shareSetting[0]['total_num']; //分享一次增加的抽奖次数
								$dayNum   = $shareSetting[1]['day_num']; //日分享获得最大抽奖次数
								$limit    = $shareSetting[1]['limit']; //0代表 日分享获得最大抽奖次数不限
								if(!empty($limit)){
									$dayStart = date('Y-m-d') . ' 00:00:00';
									$dayEnd   = date('Y-m-d') . ' 23:59:59';
									$select   = new Expression('sum(num) amount');
									$share    = AwardsShare::find()->where(['join_id' => $awardsJoin->id])->andFilterWhere(['between', 'create_time', $dayStart, $dayEnd])->select($select)->one();
									$num = !empty($share['amount'])?$share['amount']:0;
									if ($num >= $dayNum) {
										$is_help = 2;
									} elseif (($dayNum - $num) < $shareNum) {
										$is_help = 2;
									}
								}
							}
						}
					} else {
						$externalId = 0;
					}
					$my_url = $web_url . '/h5/pages/raffle/index?corp_id=' . $awardActivity->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $awardActivity->agent_id . '&assist=' . AwardsActivity::AWARD_HEAD . '_' . $awardActivity->id . '_0';
				}

				if (empty($externalId) && !empty($externalContact)) {
					$awardsJoin = AwardsJoin::findOne(['award_id' => $awardActivity->id, 'external_id' => $externalContact->id]);
					if (!empty($awardsJoin)) {
						$is_mine = 1;
					}
					$nick_name = urldecode($externalContact->name);
					$head_url  = $externalContact->avatar;
					$state     = AwardsActivity::AWARD_HEAD . '_' . $awardActivity->id . '_' . $externalContact->id;
				}

				$chance = $initNum;
				if(!empty($awardsJoin)){
					$isContact = 1;
					$joinId = $awardsJoin->id;
					$chance = $awardsJoin->num;
				}

				if (!empty($my_url)) {
					$my_url = $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT ? $my_url . '&suite_id=' . $corpAgent->suite->suite_id : $my_url;
				}
				if (!empty($head_url)) {
					//获取远程文件所采用的方法
					$curl = new Curl();
					$response = $curl->setOptions([
						CURLOPT_CONNECTTIMEOUT => 300,
						CURLOPT_FOLLOWLOCATION => true
					])->get($head_url);
					$base64Data = 'data:image/png;base64,' . base64_encode($response);
				} else {//默认头像
					$site_url   = \Yii::$app->params['site_url'];
					$base64Data = $head_url = $site_url . '/static/image/default-avatar.png';
				}
				$share_url = $web_url . '/h5/pages/raffle/index?corp_id=' . $awardActivity->corp_id . '&corpid=' . $workCorp->corpid . '&agent_id=' . $awardActivity->agent_id . '&assist=' . $state;
				$picRule   = json_decode($awardActivity->pic_rule, 1);
				$info = [
					'title'      => $awardActivity->title,
					'join_id'    => $joinId,
					'status'     => $status,
					'info'       => $info,
					'chance'     => $chance,
					'isContact'  => $isContact,
					'picRule'    => $picRule,
					'externalId' => $externalId,//上级id
					'awardId'    => $award_id,//活动id
					'nick_name'  => $nick_name,//当前昵称
					'head_url'   => $head_url,//当前头像
					'share_url'  => $corpAgent->agent_type == WorkCorpAgent::AUTH_AGENT ? $share_url . '&suite_id=' . $corpAgent->suite->suite_id : $share_url,//分享链接和海报链接
					'my_url'     => $my_url,//进入我的
					'base64Data' => $base64Data,
					'is_mine'    => $is_mine,
					'openid'     => $openId,
					'area_type'  => $awardActivity->area_type,//区域类型：1、不限制，2、部分地区
					'external_id'=> $external_id,//外部联系人id
					'is_help'    => $is_help,//帮他助力按钮 0 隐藏 1 帮他助力 2 帮他分享
					'is_share_open' => $awardActivity->is_share_open,//是否开启分享设置
				];

				return $info;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-award/
		 * @title           点击抽奖
		 * @description     点击抽奖
		 * @method   post
		 * @url  http://{host_name}/api/wap-award/set-prize
		 *
		 * @param id 必选 int 活动id
		 * @param join_id 必选 int 参与者id
		 *
		 * @return array
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    chance int 剩余抽奖机会
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/20 9:15
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionSetPrize ()
		{
			if (\Yii::$app->request->isPost) {
				$activity_id = \Yii::$app->request->post('id');
				$join_id     = \Yii::$app->request->post('join_id');
				if (empty($activity_id) || empty($join_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$awardActivity = AwardsActivity::findOne($activity_id);
				if (empty($awardActivity)) {
					throw new InvalidDataException("当前活动不存在");
				}
				if ($awardActivity->is_del == 1) {
					throw new InvalidDataException("此活动已删除");
				}
				if (empty($awardActivity->status)) {
					throw new InvalidDataException("此活动尚未发布");
				}
				$sTime = strtotime($awardActivity->start_time);
				$eTime = strtotime($awardActivity->end_time);
				if (time() < $sTime) {
					throw new InvalidDataException("此活动尚还未开始");
				}
				if (time() > $eTime || $awardActivity->status==3 || $awardActivity->status==4) {
					$status = $awardActivity->status;
					if($status==0 || $status==1){
						$awardActivity->status = 2;
						$awardActivity->save();
						\Yii::$app->queue->push(new SyncAwardJob([
							'award_id'     => $awardActivity->id,
							'award_status' => 2
						]));
					}
					throw new InvalidDataException("此活动已经结束");
				}
				$data = AwardsActivity::getChance($join_id, $activity_id);

				return $data;
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-award/
		 * @title           获取活码
		 * @description     获取活码
		 * @method   post
		 * @url  http://{host_name}/api/wap-award/code
		 *
		 * @param externalId 可选 int 上级id
		 * @param id 必选 int 活动id
		 *
		 * @return array
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/30 15:46
		 * @number          0
		 *
		 * @throws InvalidDataException
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 * @throws \ParameterError
		 * @throws \QyApiError
		 * @throws \yii\base\InvalidConfigException
		 */
		public function actionCode ()
		{
			if (\Yii::$app->request->isPost) {
				$externalId = \Yii::$app->request->post('externalId') ?: 0;
				$id         = \Yii::$app->request->post('id');
				$lat         = \Yii::$app->request->post('lat', 0);
				$lng         = \Yii::$app->request->post('lng', 0);
				$external_id = \Yii::$app->request->post('external_id', 0);//外部联系人id
				$uid = \Yii::$app->request->post('uid', 0);//
				\Yii::error(\Yii::$app->request->post(),'postData');
				if (empty($id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				if (empty($uid)) {
                    throw new InvalidParameterException('参数不正确,请刷新页面或清理缓存后重试！');
                }
				$awardActivity = AwardsActivity::findOne($id);
				if (empty($awardActivity)) {
					throw new InvalidDataException("当前活动不存在");
				}
				if ($awardActivity->is_del == 1) {
					throw new InvalidDataException("此活动已删除");
				}
				if (empty($awardActivity->status)) {
					throw new InvalidDataException("此活动尚未发布");
				}
				$sTime = strtotime($awardActivity->start_time);
				$eTime = strtotime($awardActivity->end_time);
				if (time() < $sTime) {
					throw new InvalidDataException("此活动尚还未开始");
				}
				if (time() > $eTime  || $awardActivity->status==3 || $awardActivity->status==4) {
					$status = $awardActivity->status;
					if($status==0 || $status==1){
						$awardActivity->status = 2;
						$awardActivity->save();
						\Yii::$app->queue->push(new SyncAwardJob([
							'award_id'     => $awardActivity->id,
							'award_status' => 2
						]));
					}
					throw new InvalidDataException("此活动已经结束");
				}
				//检查区域限制
				if ($awardActivity->area_type == 2) {
					$areaData = json_decode($awardActivity->area_data, 1);
					$address  = RedPack::getAddress($lat, $lng);
					$is_limit = RedPack::checkArea($address, $areaData);
					if (!empty($is_limit)) {
						if (empty($externalId)) {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与。';
						} else {
							$message = '很抱歉，您目前所在区域不在此活动范围内，无法参与，无法帮好友助力。';
						}
						throw new InvalidDataException($message);
					}
				}

                //判断人数限制
                $lotteryDrawNum = (int)User::getPackageAboutNum($uid,'lottery_draw_num');
                if ($lotteryDrawNum > 0) {
                    $joinNum = AwardsJoin::find()->where(['award_id'=>$id])->count();
                    if ($joinNum >= $lotteryDrawNum) {
                        AwardsActivity::setActivityOver($id);
                        throw new InvalidDataException('很抱歉，当前活动过于火爆暂时无法参与');
                    }
                }

				//检查性别
				if (!empty($external_id)) {
					if($awardActivity->sex_type != 1){
						$is_limit = RedPack::checkSex($external_id, $awardActivity->sex_type);
						if ($awardActivity->sex_type == 2) {
							$sex_name = '男性';
						} elseif ($awardActivity->sex_type == 3) {
							$sex_name = '女性';
						} else {
							$sex_name = '未知';
						}
						if (!empty($is_limit)) {
							if (empty($jid)) {
								$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与。';
							} else {
								$message = '很抱歉，目前该活动仅限于' . $sex_name . '参与，您无法帮好友助力。';
							}
							throw new InvalidDataException($message);
						}
					}
					//如果都满足 判断是否加过
					$userArr         = json_decode($awardActivity->user, true);
					$contactFollower = WorkExternalContactFollowUser::find()->where(['external_userid' => $external_id, 'del_type' => 0])->select('userid')->asArray()->all();
					if (!empty($contactFollower)) {
						$userId    = array_column($contactFollower, 'userid');
						$intersect = array_intersect($userId, $userArr);
					}
					if(!empty($intersect)){
						$newApplySetting = json_decode($awardActivity->apply_setting, true);
						if(empty($newApplySetting['limit'])){
							$initNum = $awardActivity->init_num;
						}else{
							if ($newApplySetting['total_num'] >= $awardActivity->init_num) {
								$initNum = $awardActivity->init_num;
							} else {
								$initNum = $newApplySetting['total_num'];
							}
						}

						$externalContact = WorkExternalContact::findOne($external_id);
						$joinId = AwardsJoin::add($awardActivity->id, $external_id, $externalId, $externalContact, $initNum, $awardActivity->share_setting,1);
						$chance = $initNum;
						if (!empty($joinId)) {
							$awardJoin = AwardsJoin::findOne($joinId);
							if (!empty($awardJoin)) {
								$chance = $awardJoin->num;
							}

							return ['open_type' => 1,'join_id' => $joinId, 'chance' => $chance,'isContact'=>1, 'is_refresh' => 1];
						}
					}
				}

				if (empty($externalId)) {
					//生成的活动码
					$qr_code = $awardActivity->qr_code;
				} else {
					//生成的是带参数的个人码
					$join = AwardsJoin::findOne(['external_id' => $externalId, 'award_id' => $id]);
					if (empty($join)) {
						throw new InvalidParameterException('参数不正确！');
					}
					$qr_code = AwardsJoin::changeConfig($join->id);
				}

				return [
					'open_type' => 0,
					'qr_code'   => $qr_code
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/wap-award/
		 * @title           获奖名单列表
		 * @description     获奖名单列表
		 * @method   post
		 * @url  http://{host_name}/api/wap-award/records
		 *
		 * @param id  必选 int 活动id
		 * @param join_id  必选 int 参与者id
		 * @param page 可选 int 页码
		 * @param pageSize 可选 int 页数
		 *
		 * @return {"error":0,"data":{"count":"2","info":[{"key":"30","name":"婷爷香吻","logo":"/upload/images/2/20200325/15851366595e7b4413518e3.jpg","create_time":"2020-03-27 14:13:14"},{"key":"6","name":"婷爷香吻","logo":"/upload/images/2/20200325/15851366595e7b4413518e3.jpg","create_time":"2020-03-27 20:21:48"}]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int id
		 * @return_param    name string 昵称
		 * @return_param    logo string logo
		 * @return_param    create_time string 参与时间
		 *
		 * @remark          Create by PhpStorm. User: wangpan. Date: 2020/3/18 11:17
		 * @number          0
		 *
		 * @throws InvalidParameterException
		 * @throws MethodNotAllowedHttpException
		 */
		public function actionRecords ()
		{
			if (\Yii::$app->request->isPost) {
				$award_id = \Yii::$app->request->post('id') ?: 0;
				$join_id  = \Yii::$app->request->post('join_id') ?: 0;
				$page     = \Yii::$app->request->post('page') ?: 1;
				$pageSize = \Yii::$app->request->post('pageSize') ?: 15;
				if (empty($award_id)) {
					throw new InvalidParameterException('参数不正确！');
				}
				$records = AwardsRecords::find()->alias('ads')->select('ads.id,al.name,al.logo,ads.create_time,ads.status');
				$records = $records->leftJoin('{{%awards_list}} al', '`al`.`id` = `ads`.`aid`');
				$records = $records->where(['ads.award_id' => $award_id, 'ads.join_id' => $join_id, 'ads.is_record' => 1]);
				$count   = $records->count();
				$info    = [];
				$offset  = ($page - 1) * $pageSize;
				$records = $records->limit($pageSize)->offset($offset)->orderBy(['id' => SORT_DESC])->asArray()->all();
				if (!empty($records)) {
					foreach ($records as $key => $recordData) {
						$info[$key]['key']         = $recordData['id'];
						$info[$key]['name']        = $recordData['name'];
						$info[$key]['logo']        = $recordData['logo'];
						$info[$key]['status']      = $recordData['status'];
						$info[$key]['create_time'] = $recordData['create_time'];
					}
				}
				$awardsActivity = AwardsActivity::findOne($award_id);
				$uid = $awardsActivity->uid ?? 0;
				return [
					'count' => $count,
					'info'  => $info,
                    'uid'   => $uid,
				];
			} else {
				throw new MethodNotAllowedHttpException('请求方式不允许！');
			}
		}

	}