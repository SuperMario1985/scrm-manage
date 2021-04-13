<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '签名审核';
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
<?=Html::cssFile('@web/plugins/chosen/chosen.css')?>
<?=Html::jsFile('@web/plugins/chosen/chosen.jquery.js')?>
<?=Html::jsFile('@web/js/moment.min.js')?>
<?=Html::jsFile('@web/plugins/daterangepicker/daterangepicker.js')?>
<?=Html::cssFile('@web/plugins/daterangepicker/daterangepicker-bs3.css')?>
<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<style>
	.chosen-container-single .chosen-single {background: #fff;border-radius: 0;border-color: #e5e6e7;height: 34px !important;line-height: 34px;box-shadow: 0 0 0 white inset, 0 1px 1px rgba(0, 0, 0, 0);}
	.chosen-container-single .chosen-single div b {background-position-y: 7px;}
</style>
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
				<strong>签名审核</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">
			<div id="actionListBox" class="ibox float-e-margins ">
				<div class="ibox-content">
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/short-message/sign">
								<div class="col-lg-2">
									<select class="form-control select" name="uid" id="uid">
										<option value="0">账户查询</option>
										<?php foreach($userArr as $user):?>
											<option <?= ($uid == $user['uid'])?'selected':'';?> value="<?=$user['uid'];?>"><?=\app\util\SUtils::hideString($user['account']);?></option>
										<?php endforeach;?>
									</select>
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="status" id="status">
										<option <?= ($status == -1)?'selected':'';?> value="-1">审核状态</option>
										<option <?= ($status == 0)?'selected':'';?> value="0">待审核</option>
										<option <?= ($status == 1)?'selected':'';?> value="1">已通过</option>
										<option <?= ($status == 2)?'selected':'';?> value="2">未通过</option>
									</select>
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="sid" id="sid">
										<option value="0">短信签名</option>
										<?php foreach($titleArr as $title):?>
											<option <?= ($sid == $title['id'])?'selected':'';?> value="<?=$title['id'];?>"><?=$title['title'];?></option>
										<?php endforeach;?>
									</select>
								</div>
								<div class="col-lg-2">
									<input class="input form-control" name="dates" value="<?=$dates?>" placeholder="申请时间">
								</div>
								<div class="col-lg-1"><a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a></div>
							</form>
						</div>
					</div>
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="5%">ID</th>
							<th width="15%">申请时间</th>
							<th width="15%">账户</th>
							<th width="20%">短信签名</th>
							<th width="20%">审核状态</th>
							<th width="15%">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($signArr as $sign):?>
							<tr class="widget-list-item action-info-<?=$sign['id'];?>">
								<td class="action-id"><?=$sign['id'];?></td>
								<td><?=$sign['apply_time'];?></td>
								<td>
									<?= !empty($sign['account']) ? \app\util\SUtils::hideString($sign['account']) : '系统默认';?>
								</td>
								<td><?=$sign['title'];?></td>
								<td>
									<?php
										$msg = '';
										if($sign['status'] == 1){
											$msg = '已通过';
										}elseif($sign['status'] == 2){
											$msg = '未通过，<span style="color:red">'.$sign['error_msg'].'</span>';
										}elseif($sign['status'] == 0){
											$msg = '待审核';
										}
										echo $msg;
									?>
								</td>
								<td>
									<?php if($sign['status'] == 0):?>
										<a href="javascript:void(0);" class="btn btn-primary addAction">审核</a>
									<?php else:?>
										--
									<?php endif;?>
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

				<h4 id="actionModalTitle" class="modal-title">审核</h4>
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
							<label class="control-label" for="checkStatus"><span class="red">* </span>审核状态</label>
							<select name="method" class="form-control" id="checkStatus">
								<option value="0">请选择</option>
								<option value="1">通过</option>
								<option value="2">不通过</option>
							</select>
						</div>
						<div class="form-group error_msg" style="display: none;">
							<label class="control-label" for="error_msg"><span class="red">* </span>不通过原因</label>
							<input type="text" class="form-control menu-input" id="error_msg" value="" placeholder="不通过原因">
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
	$(function(){
		$("#uid,#sid").chosen({
			no_results_text: "没有找到结果！",//搜索无结果时显示的提示
			placeholder_text_single:'没找到活动！',
			search_contains:true,   //关键字模糊搜索，设置为false，则只从开头开始匹配
			allow_single_deselect:true, //是否允许取消选择
			max_selected_options:1,  //当select为多选时，最多选择个数
			width:"100%"
		});
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
	});

	$('body').on('change','#checkStatus',function(){
		if($(this).val() == 2){
			$('.error_msg').show();
		}else{
			$('.error_msg').hide();
		}
		$('#error_msg').val('');
	});

	// 添加方法
	$("#actionEmptyBox, #actionListBox").on('click', '.addAction', function () {
		$("#packageAlertBox").html('');

		$("#ActionId").val($(this).parent().siblings('.action-id').html());
		$("#content").val('');

		$("#actionModal .btn-confirm").data("action", "add");
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
		var status    = $("#checkStatus").val();
		var error_msg = $("#error_msg").val();
		error_msg = error_msg.trim();
		if(status == 0){
			layer.msg('请选择审核状态');
			return false;
		}else if(status == 2){
			if(error_msg == ''){
				layer.msg('请填写不通过原因');
				return false;
			}
		}
		$("#actionModal .form-group").removeClass("has-success");
		$("#actionModal .form-group").removeClass("has-error");
		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");

		var postData = {id: id, status: status, error_msg: error_msg};
		$.ajax({
			url     : "/admin/short-message/sign-status",
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