<?php
	/**
	 * Create by PhpStorm
	 * User: dovechen
	 * Date: 2019/10/10
	 * Time: 13:22
	 */

	namespace app\modules\api\controllers;

	use app\models\Area;
	use app\modules\api\components\BaseController;
	use yii\filters\VerbFilter;
	use yii\helpers\ArrayHelper;

	class AreaController extends BaseController
	{
		/**
		 * {@inheritdoc}
		 *
		 * @return array
		 */
		public function behaviors ()
		{
			return ArrayHelper::merge(parent::behaviors(), [
				[
					'class'   => VerbFilter::className(),
					'actions' => [
						'get-area-list'      => ['GET'],
						'get-next-area-list' => ['GET'],
					]
				]
			]);
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/area/
		 * @title           获取所有的城市列表
		 * @description     获取所有的城市列表
		 * @method   get
		 * @url  http://{host_name}/api/area/get-area-list
		 *
		 * @param           * * * *
		 *
		 * @return          {"error":0,"data":[{"id":1,"sid":110000,"name":"北京","full_name":"北京市","pinyin":"beijing","lng":"116.407170","lat":"39.904690","children":[{"id":2,"sid":110101,"name":"东城","full_name":"东城区","pinyin":"dongcheng","lng":"116.416370","lat":"39.928550","children":[]},{"loop":"……"}]},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 编号
		 * @return_param    sid int 国家行政区域编号
		 * @return_param    name string 名称
		 * @return_param    full_name string 全名
		 * @return_param    pinyin string 拼音
		 * @return_param    lng string 经度
		 * @return_param    lat string 纬度
		 * @return_param    children array 子区域
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/15 11:47
		 * @number          0
		 *
		 */
		public function actionGetAreaList ()
		{
			$areaList = [];

			$areaData = Area::findAll(['parent_id' => 0]);
			if (!empty($areaData)) {
				foreach ($areaData as $area) {
					$areaInfo             = $area->dumpData(true);
					$areaInfo['children'] = Area::getChildrenArea($areaInfo['id']);
					array_push($areaList, $areaInfo);
				}
			}

			return $areaList;
		}

		/**
		 * showdoc
		 * @catalog         数据接口/api/area/
		 * @title           根据父级 ID 获取子级一层城市列表
		 * @description     根据父级 ID 获取子级一层城市列表
		 * @method   get
		 * @url  http://{host_name}/api/area/get-next-area-list
		 *
		 * @param pid 可选 int 父级ID，默认0
		 *
		 * @return          {"error":0,"data":[{"id":1,"sid":110000,"name":"北京","full_name":"北京市","pinyin":"beijing","lng":"116.407170","lat":"39.904690"},{"loop":"……"}]}
		 *
		 * @return_param    error int 状态码
		 * @return_param    data array 结果数据
		 * @return_param    id int 编号
		 * @return_param    sid int 国家行政区域编号
		 * @return_param    name string 名称
		 * @return_param    full_name string 全名
		 * @return_param    pinyin string 拼音
		 * @return_param    lng string 经度
		 * @return_param    lat string 纬度
		 *
		 * @remark          Create by PhpStorm. User: dovechen. Date: 2019/10/15 11:49
		 * @number          0
		 *
		 */
		public function actionGetNextAreaList ()
		{
			$areaList = [];
			$parentId = isset($_GET['pid']) ? $_GET['pid'] : 0;

			$areaData = Area::findAll(['parent_id' => $parentId]);
			if (!empty($areaData)) {
				foreach ($areaData as $area) {
					$areaInfo = $area->dumpData();
					array_push($areaList, $areaInfo);
				}
			}

			return $areaList;
		}
	}