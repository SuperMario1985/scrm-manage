<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '方法管理';
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
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
				<strong>方法管理</strong>
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
							<button class="btn btn-primary addAction">
								<i class="fa fa-plus"></i>添加方法
							</button>
						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/index/method">
								<div class="col-lg-2">
									<input type="text" value="<?= $search;?>" name="search" class="input form-control" placeholder="输入方法id或方法名称">
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="type">
										<option value="">不选择</option>
										<option <?= ($type == 1)?'selected':'';?> value="1">GET</option>
										<option <?= ($type == 2)?'selected':'';?> value="2">POST</option>
										<option <?= ($type == 3)?'selected':'';?> value="3">AJAX(GET)</option>
										<option <?= ($type == 4)?'selected':'';?> value="4">AJAX(POST)</option>
										<option <?= ($type == 5)?'selected':'';?> value="5">内部调用</option>
										<option <?= ($type == 6)?'selected':'';?> value="6">其他</option>
									</select>
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="searchControl">
										<option value="">不选择</option>
										<?php foreach($allControl as $control):?>
										<option <?= ($control['control'] == $searchControl)?'selected':'';?> value="<?= $control['control'];?>"><?= $control['control'];?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-lg-1"><a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a></div>
							</form>
						</div>
					</div>

					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th>方法ID</th>
							<th>方法名称</th>
							<th>控制器名称</th>
							<th>模块名称</th>
							<th>请求访问方式</th>
							<th>方法介绍</th>
							<th>操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($actionArr as $action):?>
						<tr class="widget-list-item action-info-<?=$action['id'];?>">
							<td class="action-id"><?=$action['id'];?></td>
							<td class="action-action"><?=$action['action'];?></td>
							<td class="action-control"><?=$action['control'];?></td>
							<td class="action-model"><?=$action['model'];?></td>
							<td class="action-method" valueId="<?=$action['method'];?>">
								<?php
									$method = '';
									if($action['method'] == 1){
										$method = 'GET';
									}elseif($action['method'] == 2){
										$method = 'POST';
									}elseif($action['method'] == 3){
										$method = 'AJAX(GET)';
									}elseif($action['method'] == 4){
										$method = 'AJAX(POST)';
									}elseif($action['method'] == 5){
										$method = '内部调用';
									}elseif($action['method'] == 6){
										$method = '其它';
									}
									echo $method;
								?>
							</td>
							<td class="action-introduction"><?=$action['introduction'];?></td>
							<td>
								<a href="javascript:void(0);" class="btn btn-primary editAction">编辑</a>
								<a href="javascript:void(0);" class="btn btn-danger delAction">删除</a>
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
						<div class="col-sm-6"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade inmodal" tabindex="-1" id="actionModal" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="actionModalTitle" class="modal-title">方法详细</h4>
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
						<input type="hidden" id="ActionId" value="">
						<div class="form-group">
							<label class="control-label" for="menuModels"><span class="red">* </span>菜单模块名称</label>
							<input type="text" class="form-control menu-input" id="menuModels" value="" placeholder="菜单模块名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuController"><span class="red">* </span>菜单控制器名称</label>
							<input type="text" class="form-control menu-input" id="menuController" value="" placeholder="菜单控制器名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuAction"><span class="red">* </span>菜单方法名称</label>
							<input type="text" class="form-control" id="menuAction" value="" placeholder="菜单方法名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="requestId"><span class="red">* </span>请求访问方式</label>
							<select name="method" class="form-control" id="requestId">
								<option value="1">GET</option>
								<option value="2">POST</option>
								<option value="3">AJAX(GET)</option>
								<option value="4">AJAX(POST)</option>
								<option value="5">内部调用</option>
								<option value="6">其他</option>
							</select>
						</div>
						<div class="form-group">
							<label class="control-label" for="introduction"><span class="red">*</span>方法简介</label>
							<textarea class="form-control" id="introduction" placeholder="方法简介"></textarea>
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

<script>
	function search(){
		$("#searchForm").submit();
	}
	// 添加方法
	$("#actionEmptyBox, #actionListBox").on('click', '.addAction', function () {
		$("#actionModalTitle").html('添加方法');

		$("#actionModal .package-check-box").prop('checked', false);

		$("#packageAlertBox").html('');

		$("#ActionId").val('');
		$("#menuAction").val('');
		$("#menuController").val('');
		$("#menuModels").val('');
		$("#requestId").val('');
		$("#introduction").val('');

		$("#actionModal .btn-confirm").data("action", "add");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	// 修改方法
	$("#actionEmptyBox, #actionListBox").on('click', '.editAction', function () {
		var $this = $(this);

		$("#actionModalTitle").html('修改方法');

		$("#packageAlertBox").html('');
		$("#ActionId").val($this.parent().siblings('.action-id').html());
		$("#menuAction").val($this.parent().siblings('.action-action').html());
		$("#menuController").val($this.parent().siblings('.action-control').html());
		$("#menuModels").val($this.parent().siblings('.action-model').html());
		$("#requestId").val($this.parent().siblings('.action-method').attr('valueid'));
		$("#introduction").val($this.parent().siblings('.action-introduction').html());

		$("#actionModal .btn-confirm").data("action", "edit");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	// 删除方法
	$("#actionEmptyBox, #actionListBox").on('click', '.delAction', function () {
		var $this = $(this);
		var action_id     = $this.parent().siblings('.action-id').html();
		var postData = {id: action_id};
		$.ajax({
			url     : "/admin/index/del-action",
			type    : "POST",
			data    : postData,
			dataType: "JSON",
			success : function (result) {
				if (result.error == 0) {
					$('.action-info-' + action_id).remove();
					swal("成功!", result.msg, "success");
				} else {
					swal("失败!", result.msg, "error");
				}
			}
		});
	});

	// model 输入框状态恢复
	$("#actionModal input").bind('input propertychange', function () {
		var $this = $(this);
		$this.parents('.form-group').removeClass('has-success');
		$this.parents('.form-group').removeClass('has-error');
	});

	// 关闭model
	$("#actionModal ._close").click(function () {
		$("#actionModal").modal('hide');
	});

	// 提交model内容
	$("#actionModal .btn-confirm").click(function () {
		var hasError         = false;
		var $this            = $(this);
		var addOrEdit    	 = $this.data('action');
		var ActionId 		 = $("#ActionId").val();
		var menuModels       = $("#menuModels").val();
		var menuController   = $("#menuController").val();
		var menuAction       = $("#menuAction").val();
		var requestId        = $("#requestId").val();
		var introduction     = $("#introduction").val();

		if (!menuModels || !menuController || !menuAction || !requestId || !introduction) {
			swal("数据填写不完整!", "操作失败！", "error");return;
		}

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		switch (addOrEdit) {
			case 'add':
				var postData = {ActionId: ActionId, menuModels: menuModels, menuController: menuController,menuAction: menuAction, requestId: requestId, introduction: introduction};

				break;
			case 'edit':
				var postData = {ActionId: ActionId, menuModels: menuModels, menuController: menuController,menuAction: menuAction, requestId: requestId, introduction: introduction};

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
				url     : "/admin/index/set-action",
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

						swal("成功!", "操作成功！", "success");
						window.location.reload();
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
</script>