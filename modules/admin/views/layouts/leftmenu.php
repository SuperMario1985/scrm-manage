<?php
	$route_control = \Yii::$app->controller->id;
	$route_action  = \Yii::$app->controller->action->id;

	$user_id   = Yii::$app->adminUser->identity->id;
	$user_type = Yii::$app->adminUser->identity->type;
	$isAgent   = $user_type != 0 ? 1 : 0;//1代理商

	$eid = isset(Yii::$app->adminUserEmployee->identity->id) ? Yii::$app->adminUserEmployee->identity->id : 0;

	$authority     = [];
	$agentEmployee = [];
	if ($isAgent == 0) {
		if ($eid > 0) {
			$authority = \app\models\AdminUserEmployee::getEmployeeAuthority($eid);
		} else {
			$authority = \app\models\SystemAuthority::getAllAuthority();
		}
	} else {
		if ($eid > 0) {
			$agentEmployee = \app\models\AdminUserEmployee::findOne($eid);
		}
	}

	use yii\helpers\Html;
	?>
<nav role="navigation" class="navbar-default navbar-static-side">
	<div class="sidebar-collapse">
		<ul id="side-menu" class="nav metismenu">
			<li class="nav-header header_top ">
				<div class="dropdown profile-element logo_img" >
					<span>
						<a href="#">
							<?php echo Html::img(Yii::getAlias('@web') . '/images/logo.png', [
								'width'  => 160,
								'height' => 39,
								'style'  => "border-radius:0",
								'class'  => ''
							]); ?>
						</a>
					</span>
				</div>
			</li>

			<?php if ($isAgent == 0){ ?>
				<?php if (!empty($authority)){ ?>

					<?php foreach ($authority as $a){ ?>
						<?php if ($a['title'] == '系统设置'){ ?>
							<li <?= (in_array($route_control, ['index', 'custom-manage', 'system-manage', 'limit-word','service-provider','admin-config']))?'class="active"':'';?>>
								<a href="#">
									<i class="fa fa-cog"></i>
									<span class="nav-label"><?= $a['title'];?></span>
									<span class="label label-info pull-right"></span>
								</a>
								<ul class="nav nav-second-level collapse">
									<?php foreach ($a['children'] as $c){ ?>
										<li <?= ($route_control == $c['controller'] && $route_action == $c['method'])?'class="active"':'';?>>
											<a href="<?= $c['url'];?>"><?= $c['title'];?></a>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php }else{ ?>
							<li <?= ($route_control == $a['controller'])?'class="active"':'';?>>
								<a href="#">
									<i class="fa fa-book"></i>
									<span class="nav-label"><?= $a['title'];?></span>
									<span class="label label-info pull-right"></span>
								</a>
								<ul class="nav nav-second-level collapse">
									<?php foreach ($a['children'] as $c){ ?>
										<li <?= ($route_control == $c['controller'] && $route_action == $c['method'])?'class="active"':'';?>>
											<!-- 特殊处理 -->
											<?php if ($c['url'] == '/admin/agent-manage/agent-balance-list'){$c['url'] .= '?aid=' . $user_id;} ?>
											<a href="<?= $c['url'];?>"><?= $c['title'];?></a>
										</li>
									<?php } ?>
								</ul>
							</li>
						<?php } ?>
					<?php } ?>

				<?php }else{ ?>
					<!-- 备用 -->
					<li <?= ($route_control == 'agent-manage')?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-book"></i>
							<span class="nav-label">代理商管理</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<?php if ($isAgent == 0){ ?>
								<li <?= ($route_control == 'agent-manage' && $route_action == 'agent-list')?'class="active"':'';?>>
									<a href="/admin/agent-manage/agent-list">代理商列表</a>
								</li>
							<?php }else{ ?>
								<li <?= ($route_control == 'agent-manage' && $route_action == 'agent-balance-list')?'class="active"':'';?>>
									<a href="/admin/agent-manage/agent-balance-list?aid=<?= $user_id; ?>">服务点数明细</a>
								</li>
							<?php } ?>

						</ul>
					</li>

					<li <?= ($route_control == 'user-manage')?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-book"></i>
							<span class="nav-label">客户管理</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'user-manage' && $route_action == 'user-statistics')?'class="active"':'';?>>
								<a href="/admin/user-manage/user-statistics">意向客户</a>
							</li>
							<li <?= ($route_control == 'user-manage' && $route_action == 'user-check')?'class="active"':'';?>>
								<a href="/admin/user-manage/user-check">客户资料审核</a>
							</li>
							<li <?= ($route_control == 'user-manage' && $route_action == 'user-merchant-statistics')?'class="active"':'';?>>
								<a href="/admin/user-manage/user-merchant-statistics">入驻客户</a>
							</li>
							<li <?= ($route_control == 'user-manage' && $route_action == 'agent-bill')?'class="active"':'';?>>
								<a href="/admin/user-manage/agent-bill">提单管理</a>
							</li>
						</ul>
					</li>

					<li <?= ($route_control == 'order-manage')?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-book"></i>
							<span class="nav-label">交易流水</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'order-manage' && $route_action == 'user-order')?'class="active"':'';?>>
								<a href="/admin/order-manage/user-order">短信订单</a>
							</li>
							<li <?= ($route_control == 'order-manage' && $route_action == 'package-order')?'class="active"':'';?>>
								<a href="/admin/order-manage/package-order">自提订单</a>
							</li>
						</ul>
					</li>

					<li <?= ($route_control == 'short-message')?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-book"></i>
							<span class="nav-label">短信管理</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'short-message' && $route_action == 'template')?'class="active"':'';?>>
								<a href="/admin/short-message/template">系统模版</a>
							</li>
							<li <?= ($route_control == 'short-message' && $route_action == 'user-template')?'class="active"':'';?>>
								<a href="/admin/short-message/user-template">模版审核</a>
							</li>
							<li <?= ($route_control == 'short-message' && $route_action == 'sign')?'class="active"':'';?>>
								<a href="/admin/short-message/sign">签名审核</a>
							</li>
							<li <?= ($route_control == 'short-message' && $route_action == 'pack')?'class="active"':'';?>>
								<a href="/admin/short-message/pack">短信包管理</a>
							</li>
							<li <?= ($route_control == 'short-message' && $route_action == 'type')?'class="active"':'';?>>
								<a href="/admin/short-message/type">短信类型</a>
							</li>
						</ul>
					</li>

					<li <?= (in_array($route_control, ['index', 'custom-manage', 'system-manage', 'limit-word']))?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-cog"></i>
							<span class="nav-label">系统设置</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'index' && $route_action == 'package') ? 'class="active"' : ''; ?>>
								<a href="/admin/index/package">套餐管理</a>
							</li>
							<li <?= ($route_control == 'index' && $route_action == 'menu') ? 'class="active"' : ''; ?>>
								<a href="/admin/index/menu">菜单管理</a>
							</li>
							<li <?= ($route_control == 'index' && $route_action == 'authority') ? 'class="active"' : ''; ?>>
								<a href="/admin/index/authority">权限管理</a>
							</li>

							<li <?= ($route_control == 'custom-manage' && $route_action == 'custom-field') ? 'class="active"' : ''; ?>>
								<a href="/admin/custom-manage/custom-field">客户高级属性</a>
							</li>

							<li <?= ($route_control == 'system-manage' && $route_action == 'role') ? 'class="active"' : ''; ?>>
								<a href="/admin/system-manage/role">角色管理</a>
							</li>

							<li <?= ($route_control == 'system-manage' && $route_action == 'employee') ? 'class="active"' : ''; ?>>
								<a href="/admin/system-manage/employee">员工管理</a>
							</li>

							<li <?= ($route_control == 'limit-word' && $route_action == 'group')?'class="active"':'';?>>
								<a href="/admin/limit-word/group">敏感词分组</a>
							</li>
							<li <?= ($route_control == 'limit-word' && $route_action == 'list')?'class="active"':'';?>>
								<a href="/admin/limit-word/list">敏感词词库</a>
							</li>

							<li <?= ($route_control == 'index' && $route_action == 'modify-pwd')?'class="active"':'';?>>
								<a href="/admin/index/modify-pwd">修改密码</a>
							</li>
						</ul>
					</li>

				<?php } ?>

			<?php }else{ ?>
				<!-- 代理商菜单手动设置 -->
				<?php if ($eid == 0 || (isset($agentEmployee->role_id) && $agentEmployee->role_id == 2)){ ?>
					<li <?= ($route_control == 'agent-manage')?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-book"></i>
							<span class="nav-label">代理商管理</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'agent-manage' && $route_action == 'agent-balance-list')?'class="active"':'';?>>
								<a href="/admin/agent-manage/agent-balance-list?aid=<?= $user_id; ?>">服务点数明细</a>
							</li>

						</ul>
					</li>
				<?php } ?>

				<li <?= ($route_control == 'user-manage')?'class="active"':'';?>>
					<a href="#">
						<i class="fa fa-book"></i>
						<span class="nav-label">客户管理</span>
						<span class="label label-info pull-right"></span>
					</a>
					<ul class="nav nav-second-level collapse">
						<li <?= ($route_control == 'user-manage' && $route_action == 'user-statistics')?'class="active"':'';?>>
							<a href="/admin/user-manage/user-statistics">意向客户</a>
						</li>
						<li <?= ($route_control == 'user-manage' && $route_action == 'user-check')?'class="active"':'';?>>
							<a href="/admin/user-manage/user-check">客户资料审核</a>
						</li>
						<li <?= ($route_control == 'user-manage' && $route_action == 'user-merchant-statistics')?'class="active"':'';?>>
							<a href="/admin/user-manage/user-merchant-statistics">入驻客户</a>
						</li>
						<li <?= ($route_control == 'user-manage' && $route_action == 'agent-bill')?'class="active"':'';?>>
							<a href="/admin/user-manage/agent-bill">提单管理</a>
						</li>
					</ul>
				</li>

				<?php if ($eid == 0){ ?>
					<li <?= (in_array($route_control, ['index', 'custom-manage', 'system-manage', 'limit-word']))?'class="active"':'';?>>
						<a href="#">
							<i class="fa fa-cog"></i>
							<span class="nav-label">系统设置</span>
							<span class="label label-info pull-right"></span>
						</a>
						<ul class="nav nav-second-level collapse">
							<li <?= ($route_control == 'system-manage' && $route_action == 'employee') ? 'class="active"' : ''; ?>>
								<a href="/admin/system-manage/employee">员工管理</a>
							</li>
							<li <?= ($route_control == 'index' && $route_action == 'modify-pwd')?'class="active"':'';?>>
								<a href="/admin/index/modify-pwd">修改密码</a>
							</li>
						</ul>
					</li>
				<?php } ?>
			<?php } ?>

		</ul>

	</div>
</nav>
<?=Html::jsFile('@web/js/jquery.metisMenu.js')?>
<script>
	$('#side-menu').metisMenu();
	function change_color (obj,type) {
		if(type=='in'){
			obj.css('color','#44b549');
		}else {
			obj.css('color','');
		}
	}
</script>
