<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '短信类型';
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
				<a>短信管理</a>
			</li>
			<li class="active">
				<strong>短信类型</strong>
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
								<i class="fa fa-plus"></i>添加类型
							</button>
						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th>ID</th>
							<th>名称</th>
							<th>状态</th>
							<th>操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($typeArr as $type):?>
							<tr class="widget-list-item action-info-<?=$type['id'];?>">
								<td class="action-id"><?=$type['id'];?></td>
								<td class="action-action"><?=$type['title'];?></td>
								<td class="action-method" valueId="<?=$type['status'];?>" ><?= !empty($type['status'])?'启用':'未启用';?></td>
								<td>
									<a href="javascript:void(0);" class="btn btn-primary editAction">编辑</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
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
							<label class="control-label" for="title"><span class="red">* </span>类型名称</label>
							<input type="text" class="form-control menu-input" id="title" value="" placeholder="短信类型名称，最多20个字符" maxlength="20">
						</div>
						<div class="form-group">
							<label class="control-label" for="status"><span class="red">* </span>状态</label>
							<select name="method" class="form-control" id="status">
								<option value="0">未启用</option>
								<option value="1">启用</option>
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
</div>

<script>
	// 添加方法
	$("#actionEmptyBox, #actionListBox").on('click', '.addAction', function () {
		$("#actionModalTitle").html('添加类型');

		$("#actionModal .package-check-box").prop('checked', false);

		$("#packageAlertBox").html('');

		$("#ActionId").val('');
		$("#title").val('');

		$("#actionModal .btn-confirm").data("action", "add");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	// 修改方法
	$("#actionEmptyBox, #actionListBox").on('click', '.editAction', function () {
		var $this = $(this);

		$("#actionModalTitle").html('修改类型');

		$("#packageAlertBox").html('');
		$("#ActionId").val($this.parent().siblings('.action-id').html());
		$("#title").val($this.parent().siblings('.action-action').html());
		$("#status").val($this.parent().siblings('.action-method').attr('valueid'));

		$("#actionModal .btn-confirm").data("action", "edit");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
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
		var id 		 = $("#ActionId").val();
		var title       = $("#title").val();
		var status        = $("#status").val();

		if (!title) {
			swal("数据填写不完整!", "操作失败！", "error");return;
		}

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		switch (addOrEdit) {
			case 'add':
				var postData = {id: id, title: title, status: status};

				break;
			case 'edit':
				var postData = {id: id, title: title, status: status};

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
				url     : "/admin/short-message/type",
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