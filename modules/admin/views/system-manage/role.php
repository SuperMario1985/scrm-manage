<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '角色管理';
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
<?=Html::jsFile('@web/plugins/dataTables/jquery.dataTables.js')?>
<?=Html::jsFile('@web/plugins/dataTables/dataTables.tableTools.min.js')?>
<?=Html::cssFile('@web/plugins/iCheck/custom.css')?>
<?=Html::jsFile('@web/plugins/iCheck/icheck.min.js')?>
<?=Html::cssFile('@web/plugins/chosen/chosen.css')?>
<?=Html::jsFile('@web/plugins/chosen/chosen.jquery.js')?>
<?=Html::jsFile('@web/js/moment.min.js')?>
<?=Html::jsFile('@web/plugins/daterangepicker/daterangepicker.js')?>
<?=Html::cssFile('@web/plugins/daterangepicker/daterangepicker-bs3.css')?>
<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<style>
	.animated {
		background-color: #FFFFFF;
		padding: 10px 20px;
	}
</style>
<div class="row wrapper border-bottom white-bg page-heading">
	<div class="col-lg-10">
		<h2>管理后台</h2>
		<ol class="breadcrumb">
			<li>
				<a>系统</a>
			</li>
			<li>
				<a>系统设置</a>
			</li>
			<li class="active">
				<strong>角色管理</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">
			<div id="actionListBox" class="ibox float-e-margins ">
				<div class="ibox-title clearfix">
					<ul class="nav">
						<li>
							<button class="btn btn-primary addRole">
								<i class="fa fa-plus"></i>添加角色
							</button>

							<button class="btn btn-primary addRule">权限管理</button>

						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="10%">序号ID</th>
							<th width="25%">角色名称</th>
							<th width="25%">所属父级</th>
							<!--<th width="25%">区域限制</th>-->
							<th>操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($roleArr as $role):?>
							<tr class="widget-list-item action-info-<?= $role['id']; ?>">
								<td class="action-id"><?= $role['id']; ?></td>
								<td class="title"><?= $role['title']; ?></td>
								<td ><?= $role['parentAuthorityName']; ?></td>
								<!--<td ><?/*= $role['is_city'] == 1 ? '开启' : '关闭'; */?></td>-->
								<td>
									<?php if ($role['status'] == 1){ ?>
									<button type="button" class="btn btn-primary btn-xs btn-office editRole" data-parent_id="<?= $role['parent_id']; ?>" data-is_city="<?= $role['is_city']; ?>" data-status="<?= $role['status']; ?>">&nbsp;编 辑&nbsp;</button>
									<?php }else{ ?>
										<button class="btn btn-danger btn-xs btn-office" data-name="disable" disabled type="button"><i class="fa fa-exclamation-triangle"></i>&nbsp;禁用</button>
									<?php } ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="ibox-footer">
					<div class="row">
						<?php
							echo LinkPager::widget([
								'pagination' => $pages,
							]);
						?>
						<div class="col-sm-10"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- 添加角色 -->
<!--<div class="modal fade inmodal" tabindex="-1" id="actionModal" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="actionModalTitle" class="modal-title">添加角色</h4>
			</div>

			<div class="modal-body">
				<div class="row load-box">
					<div class="spiner-example">
						<div class="sk-spinner sk-spinner-wave">
							<div class="sk-rect1"></div>
							<div class="sk-rect2"></div>
							<div class="sk-rect3"></div>
							<div class="sk-rect4"></div>
							<div class="sk-rect5"></div>
						</div>
					</div>
				</div>

				<div class="row data-box hide">
					<div id="packageAlertBox"></div>

					<form role="form">
						<input type="hidden" id="roleId" value="">

						<div class="form-group">
							<label class="control-label" for="title"><span class="red">* </span>角色名称</label>
							<input type="text" class="form-control menu-input" id="title" value="" placeholder="角色名称">
						</div>
						<div class="form-group">
							<label class="control-label">所属父级</label>
							<select class="form-control m-b" id="parent_id">
								<option value="0">请选择</option>
								<?php /*foreach ($roleAll as $v){ if ($v['status'] == 1){ */?>
									<option value="<?/*= $v['id']; */?>"><?/*= $v['title']; */?></option>
								<?php /*} } */?>
							</select>
						</div>
						<div class="form-group">
							<label class="control-label">状态</label>
							<select class="form-control m-b" id="status">
								<option value="1">开启</option>
								<option value="0">禁用</option>
							</select>
						</div>
					</form>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary btn-confirm" data-action="">确定</button>
			</div>
		</div>
	</div>
</div>-->

<!-- 添加角色及权限 -->
<div class="modal fade inmodal" tabindex="-1" id="actionModal" role="dialog" aria-hidden="true">
	<div class="modal-dialog" style="width: 750px;">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="actionModalTitle" class="modal-title">添加角色</h4>
			</div>

			<div class="modal-body">
				<div class="row load-box">
					<div class="spiner-example">
						<div class="sk-spinner sk-spinner-wave">
							<div class="sk-rect1"></div>
							<div class="sk-rect2"></div>
							<div class="sk-rect3"></div>
							<div class="sk-rect4"></div>
							<div class="sk-rect5"></div>
						</div>
					</div>
				</div>

				<div class="row data-box hide">
					<div id="packageAlertBox"></div>

					<form role="form">
						<input type="hidden" id="roleId" value="">

						<div class="form-group">
							<label class="control-label" for="title"><span class="red">* </span>角色名称</label>
							<input type="text" class="form-control menu-input" id="title" value="" placeholder="角色名称">
						</div>
						<div class="form-group">
							<label class="control-label">所属父级</label>
							<select class="form-control m-b" id="parent_id">
								<option value="0">请选择</option>
								<?php foreach ($roleAll as $v){ if ($v['status'] == 1){ ?>
									<option value="<?= $v['id']; ?>"><?= $v['title']; ?></option>
								<?php } } ?>
							</select>
						</div>
						<!--<div class="form-group">
							<label class="control-label">区域限制</label>
							<select class="form-control m-b" id="is_city">
								<option value="0">关闭</option>
								<option value="1">开启</option>
							</select>
						</div>-->
						<div class="form-group">
							<label class="control-label">状态</label>
							<select class="form-control m-b" id="status">
								<option value="1">开启</option>
								<option value="0">禁用</option>
							</select>
						</div>

						<div class="form-group">
							<label class="control-label" for="packagePrice"><span class="red">* </span>角色权限</label>

							<div class="row">
								<div class="col-lg-12">
									<div class="panel-group" id="accordion1">
										<!-- 两层 -->
										<?php foreach($authorityList as $mk=>$menuList):?>
											<div class="panel panel-default">
												<div class="panel-heading">
													<h5 class="panel-title">
														<a data-toggle="collapse" data-parent="#accordion1" href="#collapse<?php echo $menuList['id'];?>" style="display: block; "><?php echo $menuList['title'];?></a>
													</h5>
												</div>
												<div id="collapse<?php echo $menuList['id'];?>" class="panel-collapse collapse <?php echo empty($mk)?'in':'';?> ">
													<div class="panel-body">
														<div class="checkbox checkbox-success">
															<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" data-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-all package-check-box-all-<?php echo $menuList['id'];?>">
															<label for="packageCheckbox<?php echo $menuList['id'];?>">
																<?php echo $menuList['title'];?>
															</label>
														</div>
														<?php foreach($menuList['children'] as $mv):?>
															<div class="col-lg-3">
																<div class="checkbox checkbox-success">
																	<input id="packageCheckbox<?php echo $mv['id'];?>" value="<?php echo $mv['id'];?>" type="checkbox" data-parent-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-child package-check-box-<?php echo $menuList['id'];?>">
																	<label for="packageCheckbox<?php echo $mv['id'];?>">
																		<?php echo $mv['title'];?>
																	</label>
																</div>
															</div>
														<?php endforeach; ?>
													</div>
												</div>
											</div>
										<?php endforeach; ?>

									</div>

								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary btn-confirm" data-action="">确定</button>
			</div>
		</div>
	</div>
</div>



<div class="modal inmodal fade" id="publicModalInfo" tabindex="-1" role="dialog"  aria-hidden="true">

	<!--等待过度模块-->
	<div id="publicSpiner" class="modal-dialog hidden ">
		<div class="modal-content animated">
			<div class="spiner-example">
				<div class="sk-spinner sk-spinner-wave">
					<div class="sk-rect1"></div>
					<div class="sk-rect2"></div>
					<div class="sk-rect3"></div>
					<div class="sk-rect4"></div>
					<div class="sk-rect5"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- 权限列表 -->
	<div id="rule-info" class="modal-dialog modal-lg hidden ">
		<div id="rule-info-list" class="animated" style="">
			<div class="mail-box-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<div class="pull-right tooltip-demo">
					<button class="btn btn-info btn-sm" data-toggle="tooltip" data-name="ruleInfoBtnAdd" id="rule-info-btn-edit" style="margin-right: 60px;"><i class="fa fa-pencil"></i> 新增权限</button>
				</div>
				<h2>
					权限列表
				</h2>
			</div>
			<div class="mail-box">
				<div class="mail-body">
					<table class="table table-striped table-bordered table-hover dataTables-example" id="rule-table">
					</table>
				</div>
			</div>
		</div>

		<div id="rule-info-edit" class="animated hidden">
			<div class="mail-box-header">
				<div class="pull-right tooltip-demo">
				</div>
				<h2>
					权限详情
				</h2>
			</div>
			<div class="mail-box">

				<div class="mail-body">
					<form class="form-horizontal" >
						<div class="form-group">
							<label class="col-sm-2 control-label">Module:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-module" name="module" class="form-control" placeholder="例：admin" value="">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">Controller:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-controller" name="controller" class="form-control" placeholder="例：system-manage" value="">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">Method:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-method" name="method" class="form-control" placeholder="例：role (一级菜单不填)" value="">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">菜单:</label>
							<div class="col-sm-10">
								<select class="form-control m-b"  id="rule-info-edit-nav-display"  name="nav_display">
									<option value="1">显示</option>
									<option value="0">隐藏</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">类型:</label>
							<div class="col-sm-10">
								<select class="form-control m-b"  id="rule-info-edit-nav-type"  name="nav_type">
									<option value="0">菜单</option>
									<option value="1">URL</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">权限url:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-name" name="name" class="form-control" placeholder="例：/admin/system-manage/role (一级菜单不填)" value="">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">规则名称:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-title" name="title" class="form-control" placeholder="例：角色管理" value="">
							</div>
						</div>
						<!--<div class="form-group">
							<label class="col-sm-2 control-label">排序:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-sort" name="sort" class="form-control" placeholder="例：0" value="">
							</div>
						</div>-->
						<div class="form-group">
							<label class="col-sm-2 control-label">父级id:</label>
							<div class="col-sm-10">
								<input type="text" id="rule-info-edit-pid" name="pid" class="form-control" placeholder="可不填,不填则自动识别当前级" value="">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label">状态:</label>
							<div class="col-sm-10">
								<select class="form-control m-b"  id="rule-info-edit-status"  name="status">
									<option value="1">开启</option>
									<option value="0">关闭</option>
								</select>
							</div>
						</div>
					</form>
				</div>

				<div class="mail-body text-right tooltip-demo">
					<button type="button" class="btn btn-white btn-sm" id="rule-info-cancel">取消</button>
					<button type="button" class="btn btn-primary btn-sm" id="rule-info-edit-save">保存</button>
				</div>
				<div class="clearfix"></div>
			</div>
		</div>
	</div>
	<!-- 授权列表 -->
	<div id="group-set-rule" class="modal-dialog modal-lg hidden ">
		<div class="col-lg-8 col-lg-offset-2 animated">
			<div class="mail-box-header">
				<h2>
					授权列表
				</h2>
			</div>
			<div class="mail-box">
				<form class="form-horizontal">
					<div class="ibox-content">
						<table class="table table-striped table-bordered table-hover dataTables-example" id="rule-data-table">
						</table>
					</div>
				</form>
				<div class="mail-body text-right tooltip-demo">
					<button type="button" class="btn btn-white btn-sm" data-dismiss="modal">关闭</button>
					<button type="button" class="btn btn-primary btn-sm" id="group-set-rule-save">保存</button>
				</div>
				<div class="clearfix"></div>
			</div>
		</div>
	</div>

</div>



<script>
	var roleJson = <?php echo $roleJson;?>;
	var rulesJson = <?php echo $rulesJson;?>;

	var $authGroupInfo = {};
	var $isAuthGroupSet          = 0;
	var $authRuleListTableLv     = 0;
	var $authRuleListTablePidObj = {0: 0};
	var $authRuleList            = {};
	var $ahthRuleInfo            = {};

	function search(){
		$("#searchForm").submit();
	}

	function clear () {
		window.location.href = '/admin/system-manage/role';
	}

	// 添加角色
	$("#actionEmptyBox, #actionListBox").on('click', '.addRole', function () {
		$("#actionModalTitle").html('添加角色');

		$("#packageAlertBox").html('');
		$("#actionModal .package-check-box").prop('checked', false);

		$("#roleId").val('');
		$("#title").val('');
		$("#parent_id").val(0);
		$("#is_city").val(0);
		$("#status").val(1);

		$("#actionModal .btn-confirm").data("action", "add");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	}).on('click', '.addRule', function () {
		authRuleListInitFn('show');
		modalFn('show');
	});

	// 修改角色
	$("#actionEmptyBox, #actionListBox").on('click', '.editRole', function () {
		var $this = $(this);
		var roleId = $this.parent().siblings('.action-id').html();

		$("#actionModalTitle").html('修改角色');

		$("#packageAlertBox").html('');
		$("#roleId").val(roleId);
		$("#title").val($this.parent().siblings('.title').html());
		$("#is_city").val($this.data('is_city'));
		$("#status").val($this.data('status'));

		var parent_id = $this.data('parent_id');
		var option = '<option value="0">请选择</option>';
		$('#parent_id').html(option);
		for(var key in roleJson){
			if (roleJson[key].id != roleId){
				var selected = '';
				if (roleJson[key].id == parent_id){
					selected = 'selected';
				}
				option = '<option value="' + roleJson[key].id + '" ' + selected + '>' + roleJson[key].title + '</option>';
				$('#parent_id').append(option);
			}
		}

		setRoleAuthority(roleId);

		$("#actionModal .btn-confirm").data("action", "edit");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	function getPackageAuthority () {
		var packageAuthority = [];
		$.each($("#actionModal .package-check-box"), function () {
			if ($(this).is(':checked')) {
				packageAuthority.push($(this).val());
			}
		});
		return packageAuthority;
	}

	function setRoleAuthority (roleId) {
		$("#actionModal .package-check-box").prop('checked', false);

		var roleMenuId     = rulesJson[roleId];

		if (roleMenuId.length > 0) {
			$.each(roleMenuId, function (key, menuId) {
				$("#packageCheckbox" + menuId).prop('checked', true);
			});
		}
	}

	// 全选功能
	$(".package-check-box-all").click(function () {
		var $this       = $(this);
		var selfId      = $this.data('id');
		var selfChecked = $this.is(':checked');

		$("#actionModal .package-check-box-" + selfId).prop('checked', selfChecked);
	});
	// 是否需要全选
	$(".package-check-box-child").click(function () {
		var $this    = $(this);
		var parentId = $this.data('parent-id');

		var totalCount = $("#actionModal .package-check-box-" + parentId).length;
		var checkCount = $("#actionModal .package-check-box-" + parentId + ":checked").length;

		$("#actionModal .package-check-box-all-" + parentId).prop('checked', totalCount == checkCount);
	});

	// 关闭model
	$("#actionModal ._close").click(function () {
		$("#actionModal").modal('hide');
	});

	// 提交model内容
	$("#actionModal .btn-confirm").click(function () {
		var hasError      = false;
		var $this         = $(this);
		var addOrEdit     = $this.data('action');
		var roleId        = $("#roleId").val();
		var title         = $("#title").val();
		var parent_id     = $("#parent_id").val();
		var is_city       = $("#is_city").val();
		var status        = $("#status").val();
		var roleAuthority = getPackageAuthority();

		if (!title) {
			swal("角色名称不能为空!", "操作失败！", "error");
			return;
		}
		if(roleAuthority.length == 0){
			layer.msg('请选择角色权限');
			return false;
		}

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		switch (addOrEdit) {
			case 'add':
				var postData = {title: title, parent_id: parent_id, is_city: is_city, status: status, roleAuthority: roleAuthority};
				break;
			case 'edit':
				var postData = {
					roleId       : roleId,
					title        : title,
					parent_id    : parent_id,
					is_city      : is_city,
					status       : status,
					roleAuthority: roleAuthority,
				};
				break;
			default:
				hasError = true;

				var html = '<div class="alert alert-danger alert-dismissable">' +
					'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' +
					'哪里出了点问题！' +
					'</div>';

				$("#packageAlertBox").html(html);

				$("#actionModal .load-box").addClass("hide");
				$("#actionModal .data-box").removeClass("hide");
				break;
		}

		if (!hasError) {
			$.ajax({
				url     : "/admin/system-manage/set-role",
				type    : "POST",
				data    : postData,
				dataType: "JSON",
				success : function (result) {
					if (result.error == 0) {
						if (!$("#actionEmptyBox").hasClass('hide')) {
							$("#actionEmptyBox").addClass('hide');
						}

						if ($("#actionListBox").hasClass('hide')) {
							$("#actionListBox").removeClass('hide');
						}
						$("#actionModal").modal('hide');

						swal({
							title: '温馨提示',
							text:  '提交成功！',
							type:  "success",
							confirmButtonText: "确定",
						}, function () {
							window.location.reload();
						});
					} else {
						var html = '<div class="alert alert-danger alert-dismissable">' +
							'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' + result.msg +
							'</div>';
						$("#packageAlertBox").html(html);
						$("#actionModal .load-box").addClass("hide");
						$("#actionModal .data-box").removeClass("hide");
					}
				}
			});
		}
	});


	//用户组数据列表-操作模块：按钮事件
	$('.editAuthority').click(function () {
		var $data = $(this).data();

		$authGroupInfo.id    = $data.id;
		$authGroupInfo.rules = rulesJson[$data.id];
		//显示等待层
		idHideFn('#publicSpiner', 'show');
		if ($authGroupInfo.rules != undefined && JSON.stringify($authGroupInfo.rules) != "{}") {
			$authGroupInfo['iChecksRules'] = {};
			$.each($authGroupInfo.rules, function (i, n) {
				$authGroupInfo.iChecksRules[$authGroupInfo['rules'][i]] = n;
			});
		}
		if (authGroupSetFn('show') == false) {
			idHideFn('#publicSpiner', 'hide');
			return false;
		}
		modalFn('show');
	});

	//角色-授权
	var authGroupSetFn = function (display) {
		idHideFn('#publicSpiner', 'hide');
		if (display == 'show') {
			$isAuthGroupSet = 1;
			authRuleListTable.fnReloadAjax(authRuleListTable.fnSettings());
			authRuleListInitFn('show');
		} else {
			/*$authGroupInfo = {};
			authRuleListInitFn('hide');*/
			window.location.reload();
		}
		return true;
	};

	$.fn.dataTableExt.oApi.fnReloadAjax = function (oSettings) {
		this.fnClearTable(this);
		this.oApi._fnProcessingDisplay(oSettings, true);
		var that = this;

		$.getJSON(oSettings.sAjaxSource, null, function (json) {
			/* Got the data - add it to the table */
			for (var i = 0; i < json.aaData.length; i++) {
				that.oApi._fnAddData(oSettings, json.aaData[i]);
			}
			oSettings.aiDisplay = oSettings.aiDisplayMaster.slice();
			that.fnDraw(that);
			that.oApi._fnProcessingDisplay(oSettings, false);
		});
	};

	//modal 显示隐藏处理
	var modalFn = function (key) {
		$('#publicModalInfo').modal(key);
	};


	$('#publicModalInfo').on('hidden.bs.modal', function () {
		if (!$('#publicSpiner').hasClass('hidden')) {
			//等待层隐藏
			idHideFn('#publicSpiner', 'hide');
		}
		if (!$('#rule-info').hasClass('hidden')) {
			//权限-列表实例化
			authRuleListInitFn('hide');
		}
		if ($isAuthGroupSet == 1) {
			$isAuthGroupSet = 0;
			authGroupSetFn('hide');
		}

	});


	//共有隐藏显示处理类
	var idHideFn = function (idName, key) {
		var $idObj = $(idName);
		switch (key) {
			case 'show':
				if ($idObj.hasClass('hidden')) {
					$idObj.removeClass('hidden');
				}
				break;
			case 'hide':
				if (!$idObj.hasClass('hidden')) {
					$idObj.addClass('hidden');
				}
				break;
			default :
				if ($idObj.hasClass('hidden')) {
					$idObj.removeClass('hidden');
				} else {
					$idObj.addClass('hidden');
				}
		}
	};
	//权限-详情表单初始化
	var ruleInfoFormInitFn = function (display) {
		if (display == 'show') {
			if (JSON.stringify($ahthRuleInfo) != "{}" && $ahthRuleInfo != undefined) {
				$('#rule-info-edit #rule-info-edit-name').val($ahthRuleInfo.url);
				$('#rule-info-edit #rule-info-edit-title').val($ahthRuleInfo.title);
				$('#rule-info-edit #rule-info-edit-module').val($ahthRuleInfo.module);
				$('#rule-info-edit #rule-info-edit-controller').val($ahthRuleInfo.controller);
				$('#rule-info-edit #rule-info-edit-method').val($ahthRuleInfo.method);
				$('#rule-info-edit #rule-info-edit-pid').val($ahthRuleInfo.pid);
				$('#rule-info-edit form select[name="nav_display"]').find('option[value="' + $ahthRuleInfo.status + '"]').prop('selected', true);
				$('#rule-info-edit form select[name="nav_display"]').find('option[value="' + $ahthRuleInfo.nav_display + '"]').prop('selected', true);
				$('#rule-info-edit form select[name="nav_type"]').find('option[value="' + $ahthRuleInfo.nav_type + '"]').prop('selected', true);
			}
			idHideFn('#rule-info-edit', 'show');
		} else {
			idHideFn('#rule-info-edit', 'hide');
			$ahthRuleInfo = {};
			$('#rule-info-edit form input').val('');
			$('#rule-info-edit form select').find('option:selected').prop('selected', false);
		}
	};


	$('#rule-info-edit-name').blur(function(){
		var $val = $(this).val();
		if($val!= undefined && $val!=''){
			$val = $val.split("/");
			var $m = $val[1];
			var $c = $val[2];
			var $e = $val[3];
			if($m != undefined && $m !='' ){
				$('#rule-info-edit-module').val($m);
			}
			if($c != undefined && $c !='' ){
				$('#rule-info-edit-controller').val($c);
			}
			if($e != undefined && $e !='' ){
				$('#rule-info-edit-method').val($e);
			}
		}


	});

	//删除权限方法
	var delRuleAction = function (actionId) {
		if(actionId > 0){
			$.ajax({
				url    : "/admin/system-manage/authority-delete",
				type   : "POST",
				data   : {ruleId : actionId},
				success: function (data) {
					idHideFn('#publicSpiner', 'hide');
					if (!data.error) {
						swal({
							title: '温馨提示',
							text:  '删除成功！',
							type:  "success",
							confirmButtonText: "确定",
						}, function () {
							window.location.reload();
						});
					} else {
						swal("操作提示", data.msg, "error");
						idHideFn('#rule-info-edit', 'show');
					}
				},
				error  : function (e) {
				}
			});
		}
	};

	//权限-详情修改
	var ruleInfoEditFn = function () {
		//隐藏列表
		idHideFn('#rule-info-list', 'hide');
		//显示等待层
		idHideFn('#publicSpiner', 'show');
		setTimeout(function () {
			//隐藏等待层
			idHideFn('#publicSpiner', 'hide');
			//权限-详情表单初始化
			ruleInfoFormInitFn('show');
		}, 500);
	};

	//权限-表格
	var authRuleListTable = $('#rule-table').dataTable({
		"dom"        : '<"dataTable_button">frtip',
		lengthMenu   : [
			[10, 20],
			[10, 20]
		],//每页显示条数设置
		searching    : false,//本地搜索
		ordering     : false,//字段排序状态
		info         : false,//数据统计详情状态
		processing   : true,
		serverSide   : true,
		ajax         : {
			url : "/admin/system-manage/authority-list",
			type: "POST",
			data: function (d) {
				d.pid     = $authRuleListTablePidObj[$authRuleListTableLv];
				d.role_id = $authGroupInfo.id;
				if ($authRuleListTablePidObj[$authRuleListTableLv] > 0) {
					idHideFn('#rule-table th a.btn', 'show');
				} else {
					idHideFn('#rule-table th a.btn', 'hide');
				}
			}
		},
		columnDefs   : [
			{
				data     : 'id',
				orderable: false,
				targets  : 0,
				title    : 'ID',
				width    : '80px',
				render   : function (data, type, row, meta) {
					if (!$authRuleList[row.id]) {
						$authRuleList[row.id] = row;
					}
					if ($isAuthGroupSet == 1) {
						//if ($authGroupInfo != undefined && $authGroupInfo['iChecksRules'][row.id] != undefined) {
						if (row.isCheck) {
							return '<div class="i-checks"><label> <input type="checkbox" name="authGroupInfoRules_id[]" checked  value="' + row.id + '" > <i></i> ' + row.id + ' </label></div>';
						} else {
							return '<div class="i-checks"><label> <input type="checkbox" name="authGroupInfoRules_id[]"   value="' + row.id + '"  > <i></i> ' + row.id + ' </label></div>';
						}
					} else {
						return row.id;
					}
				}
			},
			/*
			{
				data     : 'sort',
				orderable: false,
				targets  : 2,
				title    : '排序',
				width    : '68px',
				render   : function (data, type, row, meta) {
					return '<span class="rule-table-info">' + row.sort + '</span>';
				}
			},*/
			{
				data     : 'title',
				orderable: false,
				targets  : 1,
				title    : '名称',
				width    : '175px',
				render   : function (data, type, row, meta) {
					return '<span class="rule-table-info">' + row.title + '</span>';
				}
			},
			{
				data     : 'url',
				orderable: false,
				targets  : 2,
				title    : '权限url',
				render   : function (data, type, row, meta) {
					var url = row.url;
					url = url.substr(9);
					return '<span class="rule-table-info">' + url + '</span>';
				}
			},
			{
				data     : 'nav_display',
				orderable: false,
				targets  : 3,
				title    : '菜单',
				width    : '68px',
				render   : function (data, type, row, meta) {
					if (row.nav_display == 1) {
						return '<span class="rule-table-info">显示</span>';
					} else {
						return '<span class="rule-table-info">隐藏</span>';
					}
				}
			},
			{
				data     : 'nav_type',
				orderable: false,
				targets  : 4,
				title    : '类型',
				width    : '68px',
				render   : function (data, type, row, meta) {
					if (row.nav_type == 1) {
						return '<span class="rule-table-info">URL</span>';
					} else {
						return '<span class="rule-table-info">菜单</span>';
					}
				}
			},
			{
				data     : 'status',
				orderable: false,
				targets  : 5,
				title    : '状态',
				width    : '68px',
				render   : function (data, type, row, meta) {
					if (row.status == 1) {
						return '<span class="rule-table-info">开启</span>';
					} else {
						return '<span class="rule-table-info">关闭</span>';
					}
				}
			},
			{
				data     : 'office',
				orderable: false,
				targets  : 6,
				width    : '151px',
				title    : function () {
					var html = '操作 &nbsp;<a data-name="return" class="btn btn-xs btn-primary btn-bitbucket btn-rule-office hidden">返回</a>';
					return html;
				},
				render   : function (data, type, row, meta) {
					var html = '';
					if ($isAuthGroupSet == 0) {
						html = '<button class="btn btn-primary btn-xs btn-rule-office" data-id="' + row.id + '"  data-pid="' + row.pid + '"  data-name="edit" type="button">编辑</button>';
					}
					if (row.url == '') {
						html += '&nbsp;<button class="btn btn-primary btn-xs btn-rule-office" data-id="' + row.id + '" data-pid="' + row.pid + '" data-name="see" type="button">查看</button>';
					} else if ($isAuthGroupSet == 0) {
						html += '&nbsp;<button class="btn btn-danger btn-xs btn-rule-office" data-id="' + row.id + '"  data-pid="' + row.pid + '"  data-name="del" type="button">删除</button>';
					}

					return html;
				}
			}
		],
		"sEmptyTable": "无权限，请添加",
		language     : {
			"sProcessing"    : "处理中...",
			"sLengthMenu"    : "显示 _MENU_ 条记录",
			"sZeroRecords"   : "没有匹配结果",
			"sInfo"          : "显示第 _START_ 至 _END_ 条，共 _TOTAL_ 条记录",
			"sInfoEmpty"     : "显示第 0 至 0 条记录，共 0 条",
			"sInfoFiltered"  : "(由 _MAX_ 项结果过滤)",
			"sInfoPostFix"   : "",
			"sSearch"        : "搜索:",
			"sUrl"           : "",
			"sEmptyTable"    : "",
			"sLoadingRecords": "载入中...",
			"sInfoThousands" : ",",
			"oPaginate"      : {
				"sFirst"   : "首页",
				"sPrevious": "上页",
				"sNext"    : "下页",
				"sLast"    : "末页"
			},
			"oAria"          : {
				"sSortAscending" : ": 以升序排列此列",
				"sSortDescending": ": 以降序排列此列"
			}
		}
	}).on('draw.dt', function () {

	}).on('init.dt', function () {

	});

	//权限-列表实例化
	var authRuleListInitFn = function (key) {
		//初始化编辑层
		ruleInfoFormInitFn('hide');
		if ($isAuthGroupSet == 1) {
			idHideFn('#rule-info-btn-edit', 'hide');
		} else {
			idHideFn('#rule-info-btn-edit', 'show');
		}
		if (key == 'show') {
			idHideFn('#rule-info', 'show');
			idHideFn('#rule-info-list', 'show');
		} else {
			$authRuleListTableLv     = 0;
			$authRuleListTablePidObj = {0: 0};
			$authRuleList            = {};
			$ahthRuleInfo            = {};
			authRuleListTable.fnReloadAjax(authRuleListTable.fnSettings());
			idHideFn('#rule-info', 'hide');
			idHideFn('#rule-info-list', 'hide');
		}
	};
	//权限-列表操作
	$('#rule-table').on('click', '.btn-rule-office', function () {
		var $data = $(this).data();
		switch ($data.name) {
			//编辑
			case 'edit':
				if ($authRuleList[$data.id] == undefined || JSON.stringify($authRuleList[$data.id]) == "{}") {
					swal("操作提示", "权限详情数据异常", "error");
				}
				//获取编辑内容详情
				$ahthRuleInfo = $authRuleList[$data.id];
				//权限详情编辑
				ruleInfoEditFn();
				break;
			//查看
			case 'see':
				$authRuleList                                  = {};
				$ahthRuleInfo                                  = {};
				$authRuleListTableLv++;
				$authRuleListTablePidObj[$authRuleListTableLv] = $data.id;
				authRuleListTable.fnReloadAjax(authRuleListTable.fnSettings());
				break;
			case 'del':
				var id = $(this).data('id');
				if( id > 0 ){
					swal({
						title: "删除权限方法",
						text: "删除后不可恢复，你确定要删除么？",
						type: "warning",
						showCancelButton: true,
						confirmButtonText: "删除",
						cancelButtonText: "取消",
						closeOnConfirm: false,
						closeOnCancel: true
					}, function(isConfirm) {
						if (isConfirm) {
							delRuleAction(id);
						}
					});
				}
				break;
			//返回
			case 'return':
				delete $authRuleListTablePidObj[$authRuleListTableLv];
				$authRuleListTableLv--;
				authRuleListTable.fnReloadAjax(authRuleListTable.fnSettings());
				break;
			default :
		}
		return true;
	});

	//权限详情新增操作
	$('#rule-info #rule-info-list #rule-info-btn-edit').on('click', function () {
		$ahthRuleInfo = {};
		idHideFn('#rule-info-list', 'hide');
		ruleInfoFormInitFn('show');
	});

	//权限详情关闭操作
	$('#rule-info #rule-info-edit #rule-info-cancel').on('click', function () {
		ruleInfoFormInitFn('hide');
		idHideFn('#rule-info-list', 'show');
	});

	//权限详情保存操作
	$('#rule-info #rule-info-edit #rule-info-edit-save').on('click', function () {
		idHideFn('#rule-info-edit', 'hide');
		idHideFn('#publicSpiner', 'show');
		var $ruleInfoForm = $('#rule-info-edit form').serialize();
		$ruleInfoForm += '&autoPid=' + $authRuleListTablePidObj[$authRuleListTableLv];
		if ($ahthRuleInfo != undefined && JSON.stringify($ahthRuleInfo) != "{}") {
			$ruleInfoForm += '&id=' + $ahthRuleInfo.id;
		}
		$.ajax({
			url    : "/admin/system-manage/authority-post",
			type   : "POST",
			data   : $ruleInfoForm,
			success: function (data) {
				idHideFn('#publicSpiner', 'hide');
				if (!data.error) {
					$ahthRuleInfo                  = {};
					$authRuleList[data.authorityId] = data.msg;
					//显示列表
					idHideFn('#rule-info-list', 'show');
					//表单初始化
					ruleInfoFormInitFn('hide');
					authRuleListTable.fnReloadAjax(authRuleListTable.fnSettings());
				} else {
					swal("操作提示", data.msg, "error");
					idHideFn('#rule-info-edit', 'show');
				}
			},
			error  : function (e) {
			}
		});
	});
</script>