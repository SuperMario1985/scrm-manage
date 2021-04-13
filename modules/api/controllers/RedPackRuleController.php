<?php
	/**
	 * 红包规则
	 * User: fulu
	 * Date: 2020/09/23
	 */

	namespace app\modules\api\controllers;

	use app\components\InvalidDataException;
	use app\models\RedPackRule;
	use app\models\WorkContactWayRedpacket;
	use app\models\WorkGroupSending;
	use app\modules\api\components\WorkBaseController;
	use app\util\DateUtil;
	use app\util\SUtils;
	use app\util\WorkUtils;

	class RedPackRuleController extends WorkBaseController
	{
		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack-rule/
		 * @title           红包规则列表
		 * @description     红包规则列表
		 * @method   post
		 * @url  http://{host_name}/api/red-pack-rule/rule-list
		 *
		 * @param uid      必选 string 用户id
		 * @param name     可选 string 规则名称
		 * @param page     可选 string 页码，默认为1
		 * @param pageSize 可选 string 每页数量，默认为15
		 *
		 * @return          {"error":0,"data":{"count":"0","list":[]}}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    count int 数据条数
		 * @return_param    list array 列表数据
		 * @return_param    list.id int 规则id
		 * @return_param    list.name string 规则名称
		 * @return_param    list.type int 红包金额类型：1、固定金额，2、随机金额
		 * @return_param    list.fixed_amount string 固定金额
		 * @return_param    list.min_random_amount string 最小随机金额
		 * @return_param    list.max_random_amount string 最大随机金额
		 * @return_param    list.pic_url string 红包封面路径
		 * @return_param    list.title string 红包标题
		 * @return_param    list.des string 红包描述
		 * @return_param    list.thanking string 感谢语
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-09-23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionRuleList ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid      = \Yii::$app->request->post('uid', 0);
			$name     = \Yii::$app->request->post('name', '');
			$page     = \Yii::$app->request->post('page', 1);
			$pageSize = \Yii::$app->request->post('pageSize', 10);
			if (empty($uid)) {
				throw new InvalidDataException('缺少必要参数！');
			}

			$redPackRule = RedPackRule::find()->where(['uid' => $uid, 'status' => 1]);
			//规则名称
			if (!empty($name)) {
				$redPackRule = $redPackRule->andWhere(['like', 'name', $name]);
			}
			$redPackRule = $redPackRule->orderBy('id desc');

			$count = $redPackRule->count();

			$offset      = ($page - 1) * $pageSize;
			$redPackRule = $redPackRule->limit($pageSize)->offset($offset)->all();

			return [
				'count' => $count,
				'list'  => $redPackRule,
			];
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack-rule/
		 * @title           红包规则添加/修改
		 * @description     红包规则添加/修改
		 * @method   post
		 * @url  http://{host_name}/api/red-pack-rule/rule-add
		 *
		 * @param uid               必选 string 用户id
		 * @param id                可选 string 规则id，修改时必填
		 * @param name              必选 string 规则名称
		 * @param type              必选 int 单个红包金额类型：1、固定金额，2、随机金额
		 * @param fixed_amount      可选 string 固定金额
		 * @param min_random_amount 可选 string 最小随机金额
		 * @param max_random_amount 可选 string 最大随机金额
		 * @param pic_url           必选 string 红包封面路径
		 * @param title             必选 string 红包标题
		 * @param des               可选 string 红包描述
		 * @param thanking          可选 string 感谢语
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-09-23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionRuleAdd ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}

			$postData = \Yii::$app->request->post();
			RedPackRule::setData($postData);

			return true;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack-rule/
		 * @title           红包规则修改详情
		 * @description     红包规则修改详情
		 * @method   post
		 * @url  http://{host_name}/api/red-pack-rule/rule-info
		 *
		 * @param uid 必选 string 用户id
		 * @param id  必选 string 规则id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id string 修改id
		 * @return_param    uid string 用户id
		 * @return_param    name string 规则名称
		 * @return_param    type int 单个红包金额类型：1、固定金额，2、随机金额
		 * @return_param    fixed_amount string 固定金额
		 * @return_param    min_random_amount string 最小随机金额
		 * @return_param    max_random_amount string 最大随机金额
		 * @return_param    pic_url string 红包封面路径
		 * @return_param    title string 红包标题
		 * @return_param    des string 红包描述
		 * @return_param    thanking string 感谢语
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-09-23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionRuleInfo ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$id  = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$ruleData = RedPackRule::find()->andWhere(['id' => $id])->asArray()->one();
			if (empty($ruleData)) {
				throw new InvalidDataException('参数不正确！');
			}
			$ruleData['type'] = (int)$ruleData['type'];
			if ($ruleData['type'] == 1){
				$ruleData['min_random_amount'] = '';
				$ruleData['max_random_amount'] = '';
			}else{
				$ruleData['fixed_amount'] = '0.3';
			}

			return $ruleData;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/red-pack-rule/
		 * @title           红包规则删除
		 * @description     红包规则删除
		 * @method   post
		 * @url  http://{host_name}/api/red-pack-rule/rule-change-status
		 *
		 * @param uid 必选 string 用户id
		 * @param id  必选 string 任务id
		 *
		 * @return          {"error":0,"data":[]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 *
		 * @remark          Create by PhpStorm. User: fulu. Date: 2020-09-23
		 * @number          0
		 *
		 * @throws InvalidDataException
		 */
		public function actionRuleChangeStatus ()
		{
			if (\Yii::$app->request->isGet) {
				throw new InvalidDataException('请求方式不允许！');
			}
			$uid = \Yii::$app->request->post('uid', 0);
			$id  = \Yii::$app->request->post('id', 0);
			if (empty($uid) || empty($id)) {
				throw new InvalidDataException('缺少必要参数！');
			}
			$ruleData = RedPackRule::findOne($id);
			if (empty($ruleData)) {
				throw new InvalidDataException('数据错误！');
			}
			//目前【活动规则】正在使用中，无法删除
			$redpacketWay = WorkContactWayRedpacket::find()->andWhere(['rule_id' => $id, 'redpacket_status' => [1, 2]])->one();
			if ($redpacketWay){
				throw new InvalidDataException('目前【' . $ruleData->name . '】正在使用中，无法删除');
			}
			$redpacketGroup = WorkGroupSending::find()->andWhere(['is_redpacket' => 1, 'rule_id' => $id, 'status' => [0, 3]])->one();
			if ($redpacketGroup){
				throw new InvalidDataException('目前【' . $ruleData->name . '】正在使用中，无法删除');
			}

			$ruleData->status      = 0;
			$ruleData->update_time = DateUtil::getCurrentTime();

			if (!$ruleData->validate() || !$ruleData->save()) {
				throw new InvalidDataException('修改失败.' . SUtils::modelError($ruleData));
			}

			return true;
		}

	}