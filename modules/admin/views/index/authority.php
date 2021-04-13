<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '权限管理';
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
				<strong>权限管理</strong>
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
								<i class="fa fa-plus"></i>添加权限
							</button>
							<button onclick="javascript:history.go(-1);" class="btn btn-primary" <?php if(!$look){echo 'style="display:none"';} ?>>
								<i class="fa"></i>返回
							</button>
						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/index/authority">
								<div class="col-lg-2">
									<input type="text" value="<?= $search;?>" name="search" class="input form-control" placeholder="输入权限id或权限名称">
								</div>
<!--								<div class="col-lg-2">-->
<!--									<select class="form-control select" name="type">-->
<!--										<option value="">不选择</option>-->
<!--										<option --><?//= ($type == 1)?'selected':'';?><!-- value="1">GET</option>-->
<!--										<option --><?//= ($type == 2)?'selected':'';?><!-- value="2">POST</option>-->
<!--										<option --><?//= ($type == 3)?'selected':'';?><!-- value="3">AJAX(GET)</option>-->
<!--										<option --><?//= ($type == 4)?'selected':'';?><!-- value="4">AJAX(POST)</option>-->
<!--										<option --><?//= ($type == 5)?'selected':'';?><!-- value="5">内部调用</option>-->
<!--										<option --><?//= ($type == 6)?'selected':'';?><!-- value="6">其他</option>-->
<!--									</select>-->
<!--								</div>-->
<!--								<div class="col-lg-2">-->
<!--									<select class="form-control select" name="searchControl">-->
<!--										<option value="">不选择</option>-->
<!--										--><?php //foreach($allControl as $control):?>
<!--											<option --><?//= ($control['control'] == $searchControl)?'selected':'';?><!-- value="--><?//= $control['control'];?><!--">--><?//= $control['control'];?><!--</option>-->
<!--										--><?php //endforeach; ?>
<!--									</select>-->
<!--								</div>-->
								<div class="col-lg-1"><a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a></div>
								<div class="col-lg-1"><a class="btn btn-primary" style="width:70px;" href="javascript:clear()">清 空</a></div>
							</form>
						</div>
					</div>

					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th>权限ID</th>
							<th>权限名称</th>
							<th>权限等级</th>
							<th>权限相关路由</th>
							<th>权限介绍</th>
							<th>排序（越小越靠前）</th>
							<th>操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($actionArr as $action):?>
							<tr class="widget-list-item action-info-<?= $action['id']; ?>">
								<td class="action-id"><?= $action['id']; ?></td>
								<td class="name"><?= $action['name']; ?></td>
								<td class="level"><?= $action['level']; ?></td>
								<td class="route"><?= $action['route']; ?></td>
								<td class="description"><?= $action['description']; ?></td>
								<td class="sort"><?= $action['sort']; ?></td>
								<td>
									<a href="javascript:void(0);" class="btn btn-primary authority_look">查看</a>
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
						<input type="hidden" id="AuthorityId" value="">
						<input type="hidden" id="LookId" value="<?php echo $look; ?>">
						<div class="form-group">
							<label class="control-label" for="name"><span class="red">* </span>权限名称</label>
							<input type="text" class="form-control menu-input" id="name" value="" placeholder="权限名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="level"><span class="red">* </span>权限等级</label>
							<input type="text" class="form-control menu-input" id="level" value="<?php echo $level;?>" disabled placeholder="权限等级">
						</div>
						<div class="form-group" <?php if(empty($look)){echo 'style="display:none"';} ?>>
							<label class="control-label" for="level"><span class="red">* </span>父级权限</label>
							<input type="text" class="form-control menu-input" id="level" value="<?php echo $parent;?>" disabled placeholder="权限等级">
						</div>
						<div class="form-group">
							<label class="control-label" for="route"><span class="red">* </span>权限相关路由</label>
							<input type="text" class="form-control" id="route" value="" placeholder="权限相关路由">
						</div>
						<div class="form-group">
							<label class="control-label" for="description">权限简介</label>
							<textarea class="form-control" id="description" placeholder="权限简介"></textarea>
						</div>
						<div class="form-group">
							<label class="control-label" for="sort">排序（越小越靠前）</label>
							<input type="number" class="form-control" id="sort" value="0" placeholder="排序">
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

	function clear () {
		var look = $("#LookId").val();
		var param = '';
		if (look != '') {
			param = '?look=' + look;
		}
		window.location.href = '/admin/index/authority' + param;
	}

	//查看
	$("#actionEmptyBox, #actionListBox").on('click', '.authority_look', function () {
		$AuthorityId = $(this).parent().siblings('.action-id').html();
		window.location.href = '/admin/index/authority?look=' + $AuthorityId;
	});

	// 添加方法
	$("#actionEmptyBox, #actionListBox").on('click', '.addAction', function () {
		$("#actionModalTitle").html('添加权限');

		$("#actionModal .package-check-box").prop('checked', false);

		$("#packageAlertBox").html('');

		$("#AuthorityId").val('');
		$("#menuAction").val('');
		$("#menuController").val('');
		$("#menuModels").val('');
		$("#requestId").val('');
		$("#introduction").val('');
		$("#sort").val('');

		$("#actionModal .btn-confirm").data("action", "add");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	// 修改方法
	$("#actionEmptyBox, #actionListBox").on('click', '.editAction', function () {
		var $this = $(this);

		$("#actionModalTitle").html('修改权限');

		$("#packageAlertBox").html('');
		$("#AuthorityId").val($this.parent().siblings('.action-id').html());
		$("#name").val($this.parent().siblings('.name').html());
		$("#route").val($this.parent().siblings('.route').html());
		$("#level").val($this.parent().siblings('.level').html());
		$("#description").val($this.parent().siblings('.description').html());
		$("#sort").val($this.parent().siblings('.sort').html());

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
			url     : "/admin/index/del-authority",
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
		var hasError = false;
		var $this = $(this);
		var addOrEdit = $this.data('action');
		var AuthorityId = $("#AuthorityId").val();
		var name = $("#name").val();
		var level = $("#level").val();
		var route = $("#route").val();
		var description = $("#description").val();
		var LookId = $("#LookId").val();
		var sort = $("#sort").val();

		if (!name || !level || !route) {
			swal("数据填写不完整!", "操作失败！", "error");
			return;
		}

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		switch (addOrEdit) {
			case 'add':
				var postData = {name: name, level: level, route: route, description: description, LookId: LookId,sort:sort};

				break;
			case 'edit':
				var postData = {
					AuthorityId: AuthorityId,
					name       : name,
					level      : level,
					route      : route,
					LookId     : LookId,
					description: description,
					sort       : sort,
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
				url     : "/admin/index/set-authority",
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