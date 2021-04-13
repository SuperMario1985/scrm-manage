<?php

	namespace app\modules\admin\controllers;

	use app\models\AdminUser;
	use app\models\AdminUserEmployee;
	use app\models\SystemAuthority;
	use app\models\SystemRole;
	use app\modules\admin\components\BaseController;
	use Yii;
	use yii\data\Pagination;
	use app\components\InvalidDataException;

	class SystemManageController extends BaseController
	{
		public $enableCsrfValidation = false;
		public $pageSize;
		public $adminUser;
		public $eid;

		public function __construct ($id, $module, $config = [])
		{
			parent::__construct($id, $module, $config);
			$this->pageSize = \Yii::$app->request->post('pageSize') ?: 10;

			if (!\Yii::$app->adminUser->isGuest){
				$account         = Yii::$app->adminUser->identity->account;
				$this->adminUser = AdminUser::findOne(['account' => $account]);

				$this->eid = isset(Yii::$app->adminUserEmployee->identity->id) ? Yii::$app->adminUserEmployee->identity->id : 0;
			}
		}

		/**
		 * 角色管理
		 */
		public function actionRole ()
		{
			$role = SystemRole::find();

			$count    = $role->count();
			$pages    = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$rolepage = $role->offset($pages->offset)->limit($pages->limit)->asArray()->all();
			$rules = [];
			foreach ($rolepage as $k => $v) {
				$authority       = !empty($v['authority']) ? explode(',', $v['authority']) : [];
				$rules[$v['id']] = $authority;

				$parentAuthorityName = '--';
				if ($v['parent_id']) {
					$parentAuthority     = SystemRole::findOne($v['parent_id']);
					$parentAuthorityName = !empty($parentAuthority) ? $parentAuthority->title : '--';
				}
				$rolepage[$k]['parentAuthorityName'] = $parentAuthorityName;
			}

			$roleAll = $role->asArray()->all();

			//全部权限
			$authorityList = SystemAuthority::getAllAuthority(1);

			return $this->render('role', ['roleArr' => $rolepage, 'roleAll' => $roleAll, 'roleJson' => json_encode($roleAll), 'rulesJson' => json_encode($rules), 'pages' => $pages, 'authorityList' => $authorityList]);
		}

		/**
		 * 录入角色
		 */
		public function actionSetRole ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post          = \Yii::$app->request->post();
			$roleId        = !empty($post['roleId']) ? $post['roleId'] : '';
			$title         = !empty($post['title']) ? $post['title'] : '';
			$parent_id     = !empty($post['parent_id']) ? $post['parent_id'] : 0;
			$is_city       = !empty($post['is_city']) ? $post['is_city'] : 0;
			$status        = isset($post['status']) ? $post['status'] : 1;
			$roleAuthority = isset($post['roleAuthority']) ? $post['roleAuthority'] : [];

			if (empty($title)) {
				$this->dexit(['error' => 1, 'msg' => '请输入角色名称']);
			}

			//保存
			try {
				$roleData = [
					'roleId'    => $roleId,
					'title'     => $title,
					'parent_id' => $parent_id,
					'is_city'   => $is_city,
					'status'    => $status,
					'authority' => $roleAuthority,
				];
				SystemRole::create($roleData);

				$this->dexit(['error' => 0, 'msg' => '']);
			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 获取权限列表
		 */
		public function actionAuthorityList ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$postData      = \Yii::$app->request->post();
			foreach ($postData as $key => $val) {
				if (!in_array($key, ['data_type', 'draw', 'start', 'length', 'search', 'order', 'key', 'pid', 'role_id'])) {
					unset($postData[$key]);
				}
			}
			$data_json = [
				'draw'            => intval($postData['draw']),
				'recordsTotal'    => 0,//数据库里总共记录数
				'recordsFiltered' => 0
			];
			//条数处理
			$start  = intval($postData['start']);
			$length = intval($postData['length']);

			$authority = SystemAuthority::find()->andWhere(['pid' => $postData['pid'], 'status' => 1]);
			$total = $authority->count();

			$authority = $authority->offset($start)->limit($length)->asArray()->all();

			if ($total > 0) {
				$data_json['recordsTotal']    = $total;
				$data_json['recordsFiltered'] = $total;
			}

			$roleAuthority = [];
			if (isset($postData['role_id']) && $postData['role_id']){
				$role = SystemRole::findOne($postData['role_id']);
				if ($role){
					$roleAuthority = explode(',', $role->authority);
				}
			}

			$data = [];
			if (is_array($authority)) {
				foreach ($authority as $key => $val) {
					$val['isCheck'] = $roleAuthority && in_array($val['id'], $roleAuthority) ? 1 : 0;
					$data[]         = $val;
				}
			}
			$data_json['data'] = $data;

			echo json_encode($data_json);
			exit();
		}

		/**
		 * 添加/编辑权限
		 */
		public function actionAuthorityPost ()
		{
			$postData = \Yii::$app->request->post();

			if (empty($postData['title'])) {
				$this->dexit(['error' => 1, 'msg' => '规则名称不能为空']);
			}
			if (empty($postData['module'])) {
				$this->dexit(['error' => 1, 'msg' => 'module不能为空']);
			}
			if (empty($postData['controller'])) {
				$this->dexit(['error' => 1, 'msg' => 'controller不能为空']);
			}

			//添加编辑的时候可以填写父级id
			if (isset($postData['pid']) && $postData['pid'] > 0 && empty($postData['autoPid'])) {
				$postData['autoPid'] = $postData['pid'];
			}

			//保存
			try {
				$data        = [
					'id'          => isset($postData['id']) ? $postData['id'] : 0,
					'pid'         => $postData['autoPid'],
					'title'       => $postData['title'],
					'url'         => isset($postData['name']) ? htmlspecialchars_decode($postData['name']) : '',
					'nav_display' => $postData['nav_display'],
					'nav_type'    => $postData['nav_type'],
					'module'      => $postData['module'],
					'controller'  => $postData['controller'],
					'method'      => $postData['method'],
					'status'      => $postData['status']
				];
				$authorityId = SystemAuthority::create($data);
				$authority   = SystemAuthority::find()->andWhere(['id' => $authorityId])->asArray()->one();

				$this->dexit(['error' => 0, 'msg' => $authority, 'authorityId' => $authorityId]);
			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}
		/**
		 * 删除权限
		 */
		public function actionAuthorityDelete ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post   = \Yii::$app->request->post();
			$id    = !empty($post['ruleId']) ? $post['ruleId'] : '';

			$authority = SystemAuthority::findOne($id);
			if (empty($authority)) {
				$this->dexit(['error' => 1, 'msg' => '权限数据错误']);
			}

			$authority->status = 0;

			if ($authority->save()) {
				$this->dexit(['error' => 0, 'msg' => '']);
			} else {
				$this->dexit(['error' => 1, 'msg' => '设置失败']);
			}
		}

		/**
		 * 员工管理
		 */
		public function actionEmployee ()
		{
			$searchType = \Yii::$app->request->get('searchType', 1);
			$uname      = \Yii::$app->request->get('uname', '');

			$employee = AdminUserEmployee::find()->andWhere(['uid' => $this->adminUser->id]);
			if ($this->eid) {
				$employee = $employee->andWhere(['pid' => $this->eid]);
			}
			$totalnum = $employee->count();

			//账户、手机号、姓名
			if (!empty($searchType) && !empty($uname)) {
				switch ($searchType) {
					case 1:
						$employee = $employee->andWhere(['like', 'account', $uname]);
						break;
					case 2:
						$employee = $employee->andWhere(['like', 'phone', $uname]);
						break;
					case 3:
						$employee = $employee->andWhere(['like', 'name', $uname]);
						break;
				}
			}

			$count    = $employee->count();
			$pages    = new Pagination(['totalCount' => $count, 'pageSize' => $this->pageSize]);
			$employee = $employee->offset($pages->offset)->limit($pages->limit)->asArray()->all();

			$roleD = [];
			if ($this->adminUser->type != 0) {
				//代理商登录
				$role = SystemRole::AGENT_ROLE;
			} else {
				//角色
				$role = SystemRole::find()->andWhere(['status' => 1])->andWhere(['>', 'authority', '0']);
				if ($this->eid) {
					$eidRoleId = Yii::$app->adminUserEmployee->identity->role_id;
					$role      = $role->andWhere(['parent_id' => $eidRoleId]);
				}
				$role = $role->select('`id`,`title`')->asArray()->all();
			}
			foreach ($role as $v) {
				$roleD[$v['id']] = $v['title'];
			}

			foreach ($employee as $k => $v) {
				$employee[$k]['roleName'] = isset($roleD[$v['role_id']]) ? $roleD[$v['role_id']] : '--';
				$pname                    = '--';
				if (!empty($v['pid'])) {
					$pEmployee = AdminUserEmployee::findOne($v['pid']);
					$pname     = isset($pEmployee->name) ? $pEmployee->name : '--';
				}
				$employee[$k]['pname'] = $pname;
			}

			return $this->render('employee', ['employeeList' => $employee, 'roleArr' => $role, 'totalnum' => $totalnum, 'searchType' => $searchType, 'uname' => $uname, 'pages' => $pages]);
		}

		/**
		 * 录入员工
		 */
		public function actionAddEmployee ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post    = \Yii::$app->request->post();
			$eid     = !empty($post['eid']) ? $post['eid'] : '';
			$role_id = !empty($post['role_id']) ? $post['role_id'] : 0;
			$account = !empty($post['account']) ? $post['account'] : '';
			$pwd     = !empty($post['pwd']) ? $post['pwd'] : '';
			$phone   = !empty($post['phone']) ? $post['phone'] : '';
			$name    = !empty($post['name']) ? $post['name'] : '';
			$status  = isset($post['status']) ? $post['status'] : 1;

			if (empty($role_id)) {
				$this->dexit(['error' => 1, 'msg' => '请选择角色']);
			}
			//帐号
			if (empty($account)) {
				$this->dexit(['error' => 1, 'msg' => '请输入帐号']);
			}
			//密码
			if (empty($pwd) && empty($eid)) {
				$this->dexit(['error' => 1, 'msg' => '请输入密码']);
			}
			if ($pwd) {
				$length = strlen($pwd);
				if ($length < 6 || $length > 20) {
					$this->dexit(['error' => 1, 'msg' => '请输入6-20位密码']);
				}
			}
			if (!empty($phone) && !preg_match("/^((13[0-9])|(14[0-9])|(15([0-9]))|(16([0-9]))|(17([0-9]))|(18[0-9])|(19[0-9]))\d{8}$/", $phone)) {
				$this->dexit(['error' => 1, 'msg' => '请输入正确的手机号']);
			}
			if (empty($name)) {
				$this->dexit(['error' => 1, 'msg' => '请填写姓名']);
			}

			//保存
			try {
				$eData = [
					'id'       => $eid,
					'uid'      => $this->adminUser->id,
					'pid'      => $this->eid,
					'role_id'  => $role_id,
					'account'  => $account,
					'pwd'      => $pwd,
					'phone'    => $phone,
					'name'     => $name,
					'status'   => $status,
					'city_all' => 1,
					'status'   => $status,
				];
				AdminUserEmployee::create($eData);

				$this->dexit(['error' => 0, 'msg' => '']);
			} catch (InvalidDataException $e) {
				$this->dexit(['error' => 1, 'msg' => $e->getMessage()]);
			}
		}

		/**
		 * 获取员工信息
		 */
		public function actionGetOneEmployee ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}
			$post = \Yii::$app->request->post();
			$eid  = !empty($post['eid']) ? $post['eid'] : '';

			$employee = AdminUserEmployee::find()->where(['id' => $eid])->asArray()->one();
			if ($this->adminUser->type == 0) {
				$employeeRole = SystemRole::findOne($employee['role_id']);
				if ($employeeRole->status == 0) {
					$employee['role_id'] = 0;
				}
			}

			if (empty($employee)) {
				$this->dexit(['error' => 1, 'msg' => '员工数据错误']);
			}

			$this->dexit(['error' => 0, 'msg' => 'ok', 'data' => $employee]);
		}

		/**
		 * 代理商销售员列表/总后台员工表
		 */
		public function actionGetSalerEmployee ()
		{
			if (\Yii::$app->request->isGet) {
				$this->dexit(['error' => 1, 'msg' => '请求方式不允许']);
			}

			if ($this->adminUser->type == 0) {
				$employee = AdminUserEmployee::find()->andWhere(['uid' => $this->adminUser->id, 'status' => 1])->select('id,account,name')->asArray()->all();
			} else {
				$agentRole = SystemRole::AGENT_ROLE;
				$employee  = AdminUserEmployee::find()->andWhere(['uid' => $this->adminUser->id, 'role_id' => $agentRole[1]['id'], 'status' => 1])->select('id,account,name')->asArray()->all();
			}

			$this->dexit(['error' => 0, 'msg' => 'ok', 'data' => $employee]);
		}



	}