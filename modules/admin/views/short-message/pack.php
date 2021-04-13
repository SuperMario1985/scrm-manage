<?php
	use yii\helpers\Html;
	$this->title = '短信包管理';
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
<?=Html::jsFile('@web/js/moment.min.js')?>
<?=Html::jsFile('@web/plugins/daterangepicker/daterangepicker.js')?>
<?=Html::cssFile('@web/plugins/daterangepicker/daterangepicker-bs3.css')?>

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
				<strong>短信包管理</strong>
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
							<button class="btn btn-primary addAction" pack_id="0">
								<i class="fa fa-plus"></i>添加短信包
							</button>
						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/short-message/pack">
								<div class="col-lg-2">
									<input class="input form-control" name="dates" value="<?=$dates?>" placeholder="时间">
								</div>
								<div class="col-lg-1"><a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a></div>
							</form>
						</div>
					</div>
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="15%">短信包</th>
							<th width="15%">当前售价</th>
							<th width="15%">售卖份数</th>
							<th width="20%">累计销售额</th>
							<th width="15%">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($packArr as $pack):?>
							<tr class="widget-list-item action-info-<?=$pack['id'];?>">
								<td><?=$pack['num'].'条';?></td>
								<td><?=$pack['price'].'元';?></td>
								<td><?=$pack['times'].'份';?></td>
								<td><?=!empty($pack['prices'])?$pack['prices']:'0';?></td>
								<td>
									<a href="javascript:void(0);" pack_id="<?=$pack['id'];?>" num="<?=$pack['num'];?>" price="<?=$pack['price'];?>" class="btn btn-primary editAction">修改售价</a>
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

				<h4 id="actionModalTitle" class="modal-title">添加</h4>
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
							<label class="control-label" for="num"><span class="red">* </span>短信包条数</label>
							<div>
								<input type="text" class="form-control menu-input" id="num" value="" style="width: 350px;display: inline-block;"> <span style="display: inline-block;">条（一旦提交，不可修改）</span>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label" for="price"><span class="red">* </span>售价</label>
							<div>
								<input type="text" class="form-control menu-input" id="price" value="" style="width: 350px;display: inline-block;"> <span style="display: inline-block;">元</span>
							</div>
						</div>
						<div class="form-group error_msg" style="display: none;">
							<label class="control-label" for="error_msg"><span class="red">* </span>失败原因</label>
							<input type="text" class="form-control menu-input" id="error_msg" value="" placeholder="失败原因">
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
<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<script>
	function search(){
		$("#searchForm").submit();
	}
	$(function(){
		$('input[name="dates"]').daterangepicker({
			maxDate: moment(), //最大时间
			autoUpdateInput:false,
			format: 'YYYY-MM-DD', //控件中from和to 显示的日期格式
			separator: ' to ',
			locale: {
				applyLabel: '确定',
				cancelLabel: '取消',
				fromLabel: '起始时间',
				toLabel: '结束时间',
				customRangeLabel: '手动选择',
				daysOfWeek: ['日', '一', '二', '三', '四', '五', '六'],
				monthNames: ['一月', '二月', '三月', '四月', '五月', '六月',
					'七月', '八月', '九月', '十月', '十一月', '十二月'
				],
				firstDay: 1,
				format: 'YYYY-MM-DD'
			}
		});
		$('input[name="dates"]').on('apply.daterangepicker', function(ev, picker) {
			$(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
		});
		$('input[name="dates"]').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});
		$('body').on('input','#price',function(){
			$("#packageAlertBox").html('');
			this.value = this.value.replace(/^\./g, '');
			this.value = this.value.replace(/[^\d|\.]+/g, '');
			this.value = this.value.replace(/^0[^\.]?/, '0');
			this.value = this.value.replace(/^0\.[^\d]/, '0.');
			this.value = this.value.replace(/^(\d+)(\.?)(\d{0,2})([^\d]|[\d]?)$/g, '$1$2$3');
			this.value = this.value.replace('0.00', '0.0');
			if( this.value > 99999999 ){
				layer.msg('填写的售价不能大于99999999');
				this.value = 99999999;
			}
		});
		$('body').on('input','#num',function(){
			$("#packageAlertBox").html('');
			this.value = this.value.replace(/^[^1-9]/g, '').replace(/(\d{1})(?=\d)/g, "$1");
			this.value = this.value.replace(/[^\d]/g, '').replace(/(\d)(?=\d)/g, "$1");
			if( this.value > 99999999 ){
				layer.msg('填写的条数不能大于99999999');
				this.value = 99999999;
			}
		})
	});

	// 添加方法
	$("#actionEmptyBox, #actionListBox").on('click', '.addAction', function () {
		$("#packageAlertBox").html('');

		$("#ActionId").val($(this).attr('pack_id'));
		$("#num").prop('disabled',false);
		$("#num").val('');
		$("#price").val('');

		$("#actionModal .btn-confirm").data("action", "add");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});

	// 修改方法
	$("#actionEmptyBox, #actionListBox").on('click', '.editAction', function () {
		$("#actionModalTitle").html('修改');

		$("#packageAlertBox").html('');
		$("#ActionId").val($(this).attr('pack_id'));
		$("#num").val($(this).attr('num'));
		$("#price").val($(this).attr('price'));
		$("#num").prop('disabled',true);
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
		var id 		  = $("#ActionId").val();
		var num    = $("#num").val();
		var price = $("#price").val();

		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		var postData = {id: id, num: num, price: price};
		$.ajax({
			url     : "/admin/short-message/pack",
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
	});
</script>