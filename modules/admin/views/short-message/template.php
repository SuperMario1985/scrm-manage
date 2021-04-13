<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '模版管理';
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
				<strong>系统模版</strong>
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
								<i class="fa fa-plus"></i>添加模版
							</button>
						</li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/short-message/template">
								<div class="col-lg-2">
									<select class="form-control select" name="type_id">
										<option value="">请选择</option>
										<?php foreach($typeArr as $type):?>
											<option <?= ($type['id'] == $type_id)?'selected':'';?> value="<?= $type['id'];?>"><?= $type['title'];?></option>
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
							<th width="10%">ID</th>
							<th width="10%">短信类型</th>
							<th width="70%">短信内容</th>
							<th width="10%">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($template as $temp):?>
							<tr class="widget-list-item action-info-<?=$temp['id'];?>">
								<td class="action-id"><?=$temp['id'];?></td>
								<td class="action-method" valueId="<?=$temp['type_id'];?>" ><?=$idTitle[$temp['type_id']];?></td>
								<td class="action-action"><?=$temp['content'];?></td>
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
							<label class="control-label" for="type_id"><span class="red">* </span>短信类型</label>
							<select name="method" class="form-control" id="type_id">
								<option value="0">短信类型</option>
								<?php foreach($typeArr as $type):?>
								<option value="<?= $type['id']?>"><?= $type['title']?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="form-group">
							<label class="control-label" for="content"><span class="red">*</span>模版内容（最多250个字符）</label>
							<textarea class="form-control" style="resize:none;" id="content" rows="10" cols="10" placeholder="模版内容(最多250个字符)" maxlength="250"></textarea>
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
		$("#actionModalTitle").html('添加模版');

		$("#actionModal .package-check-box").prop('checked', false);

		$("#packageAlertBox").html('');

		$("#ActionId").val('');
		$("#type_id").val(0);
		$("#content").val('');

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
		$("#content").val($this.parent().siblings('.action-action').html());
		$("#type_id").val($this.parent().siblings('.action-method').attr('valueid'));

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
		var type_id        = $("#type_id").val();
		var content       = $("#content").val();
		if(type_id == 0){
			swal("请选择短信类型!", "操作失败！", "error");return;
		}
		if(content == ''){
			swal("请填写模版内容!", "操作失败！", "error");return;
		}

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		switch (addOrEdit) {
			case 'add':
				var postData = {id: id, type_id: type_id, content: content};

				break;
			case 'edit':
				var postData = {id: id, type_id: type_id, content: content};

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
				url     : "/admin/short-message/template",
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