<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '员工列表';
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
	.middleLine {
                    display: inline-block;
                    width: 15px;
                    border-top: 1px solid #999;
                    vertical-align : middle;
    }
    .float-e-margins .btn {
       margin-bottom:0;
    }

    .pagination {
        float:right;
        margin:20px;
    }

	.ibox-title .nav li {
		margin-right: 55px;
	}

	.ibox-title .nav li {
		float: left;
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
				<strong>员工列表</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">
			<div class="ibox float-e-margins">
				<div class="ibox-title clearfix">
					<ul class="nav">
						<li>
							<button class="btn btn-primary" id="addmerproxy" onclick="addEmployee()"><i class="fa fa-plus"></i> 添加员工 </button>
						</li>
						<li><h2 class="realtime-title">员工列表&nbsp;&nbsp;<span style="font-size:14px;">(共：<?= $totalnum;?> 条)<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<form id="searchForm" method="get" action="/admin/system-manage/employee">
							<div class="row text-center" style="padding: 0 15px;">
								<div class="row search_top" style="margin-bottom: 10px">
									<div class="col-lg-4">
										<select style="width: calc(40% - 23px);display: inline-block;" class="form-control select" name="searchType" >
											<option value="1" <?= ($searchType == '1')?'selected':'';?> >帐号</option>
											<option value="2" <?= ($searchType == '2')?'selected':'';?> >手机号</option>
											<option value="3" <?= ($searchType == '3')?'selected':'';?> >姓名</option>
										</select>
										<span class="middleLine"></span>
										<input type="text" value="<?= $uname;?>" name="uname" class="input form-control" title="账号/手机号/姓名" placeholder="请输入查询内容" id="usertoname" style="width: 60%; display: inline-block;">
									</div>

									<div class="col-sm-3 m-b-sm">
										<div class="m-b-sm text-left">
											<div class="input-group m-t-xs" style="width:100%">
												<a class="btn btn-primary btn-sm" style="margin-bottom:0; padding: 7px 16px;" href="javascript:search()">查 询</a>&nbsp;
												<a class="btn btn-white btn-sm" style="margin-bottom:0; padding: 7px 16px;" href="javascript:clear()">清 空</a>
											</div>
										</div>
									</div>
								</div>
							</div>
						</form>

						<div class="js-real-time-region realtime-list-box loading">
							<div class="widget-list">
								<div style="position: relative;"
								     class="js-list-filter-region clearfix ui-box">
									<div class="widget-list-filter"></div>
								</div>
								<div class="ui-box">
									<table style="padding: 0px;font-size: 13px;" data-page-size="20" class="ui-table ui-table-list default no-paging footable-loaded footable">
										<thead class="js-list-header-region tableFloatingHeaderOriginal">
										<tr class="widget-list-header">
											<th width="5%">序号</th>
											<th width="10%">员工账号</th>
											<th width="10%">手机号</th>
											<th>员工姓名</th>
											<th width="15%">身份角色</th>
											<th width="10%">所属上级</th>
											<th>状态</th>
											<th>操作</th>
										</tr>
										</thead>

										<tbody id="table-list-body" class="js-list-body-region">
										<?php if (!empty($employeeList)) { ?>
										<?php foreach($employeeList as $k=>$employee){ ?>
										<tr class="widget-list-item">
											<td><?= $k + 1; ?></td>
											<td><?= $employee['account']; ?></td>
											<td><?= \app\util\SUtils::hideString($employee['phone']); ?></td>
											<td><?= $employee['name']; ?></td>
											<td><?= $employee['roleName']; ?></td>
											<td><?= $employee['pname']; ?></td>
											<td><?= $employee['status'] == 1 ? '开启' : '关闭'; ?></td>
											<td>
												<a class="btn btn-primary" onclick="editEmployee(<?= $employee['id']; ?>)"> 编 辑 </a>
											</td>
										</tr>

										<?php } ?>
										<?php }else{ ?>
										<tr class="widget-list-item"><td colspan="15">暂无员工信息</td></tr>
										<?php } ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
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

<!-- 录入员工 -->
<div class="modal inmodal" tabindex="-1" id="addOppModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">添加员工</h4>
			</div>
			<div class="modal-body">
				<div class="setting_rows">
					<div id="wxActionBox" class="wxpay_box">
						<form action="" method="post" enctype="multipart/form-data">
							<div class="setting_rows">
								<div id="addinputBox" class="wxpay_box">
									<input type="hidden" name="eid" value="0">

									<div class="form-group">
										<label class="control-label" ><span class="red">*</span>角 色</label>
										<select name="role_id" id="role_id" class="form-control">
											<option value="0">选择角色</option>
											<?php foreach($roleArr as $role):?>
												<option value="<?= $role['id']?>"><?= $role['title']?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="form-group">
										<label><font color="red">*</font>账 号：</label>
										<input type="text" placeholder="请填帐号(必填)" class="form-control" name="account">
									</div>
									<div class="form-group">
										<label><font color="red">*</font>密 码：</label>
										<input type="text" placeholder="请填密码(添加员工必填，密码为6-20位)" class="form-control" name="pwd">
									</div>
									<div class="form-group ">
										<label><font color="red">*</font>姓 名：</label>
										<input type="text" placeholder="员工姓名(必填)" value="" class="form-control" name="name">
									</div>
									<div class="form-group">
										<label>手机号：</label>
										<input type="text" placeholder="请填手机号" class="form-control" name="phone" onkeyup="onlytelNumber(this,11)">
									</div>
									<div class="form-group">
										<label class="control-label" >状 态</label>
										<select name="status" class="form-control">
											<option value="1">开启</option>
											<option value="0">关闭</option>
										</select>
									</div>

								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary">确定</button>
				<button type="button" class="btn btn-white _close">关闭</button>
			</div>
		</div>
	</div>
</div>

<script>
	function search(){
		$("#searchForm").submit();
	}
	function clear(){
		window.location.href = '/admin/system-manage/employee';
	}
	$(function(){
		$("#uid").chosen({
			no_results_text: "没有找到结果！",//搜索无结果时显示的提示
			placeholder_text_single:'没找到活动！',
			search_contains:true,   //关键字模糊搜索，设置为false，则只从开头开始匹配
			allow_single_deselect:true, //是否允许取消选择
			max_selected_options:1,  //当select为多选时，最多选择个数
			width:"100%"
		});
		$('input[name="dates"]').daterangepicker({
			//maxDate: moment(), //最大时间
			maxDate: '2100-01-01', //最大时间
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

	//验证输入
	function onlytelNumber (obj, len) {
		var thisv = $.trim($(obj).val());
		if (len > 0) {
			thisv = thisv.replace(/[^1234567890]*/g, '');
			if (thisv.length > len) {
				thisv = thisv.substring(0, len);
			}
		} else {
			thisv = thisv.replace(/[^1234567890]*/g, '');
		}
		$(obj).val(thisv);
	}

	//录入员工
	function addEmployee () {
		$('#addOppModal input[name="eid"]').val('0');
		$('#addOppModal .modal-title').html('添加员工');
		$('#addOppModal input[name="account"]').prop('readonly', false);

		$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
		$('#addOppModal .btn-confirm').prop('disabled', false);
		$('#addOppModal input[name="phone"]').parent().attr('style', 'display: block');
		$('#addOppModal').show();
	}
	$('#addOppModal ._close').click(function () {
		$('#addOppModal').hide();
		$('#addOppModal form')[0].reset();
		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
	});
	$('#addOppModal .btn-primary').click(function () {
		var eid     = $('#addOppModal input[name="eid"]').val();
		var role_id = $('#role_id').val();
		var account = $('#addOppModal input[name="account"]').val();
		var pwd     = $('#addOppModal input[name="pwd"]').val();
		var name    = $('#addOppModal input[name="name"]').val();

		if (!role_id) {
			swal("温馨提示", '请选择角色！', "error");
			return false;
		}
		if (!account) {
			swal("温馨提示", '请填写账号！', "error");
			return false;
		}
		if (!eid && !pwd) {
			swal("温馨提示", '请填写密码！', "error");
			return false;
		}
		if (!name) {
			swal("温馨提示", '请填写姓名！', "error");
			return false;
		}

		var addData = $('#addOppModal form').serialize();
		$.post('/admin/system-manage/add-employee', addData, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				$('#addOppModal').hide();
				$('#addOppModal form')[0].reset();
				$('.modal-backdrop').remove();
				$('body').css('padding-right', '0px').removeClass('modal-open');
				swal({
					title            : '温馨提示',
					text             : '提交成功！',
					type             : "success",
					confirmButtonText: "确定",
				}, function () {
					window.location.reload();
				});
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'json');
	});

	//编辑员工
	function editEmployee (eid) {
		if (eid) {
			$('body').append('<div class="modal-backdrop in"></div><div class="sk-spinner sk-spinner-wave" style="position: fixed; top: 50%; left: 50%;z-index:2048;"> <div class="sk-rect1"></div> <div class="sk-rect2"></div> <div class="sk-rect3"></div> <div class="sk-rect4"></div> <div class="sk-rect5"></div> </div>');
			$.post('/admin/system-manage/get-one-employee', {eid: eid}, function (ret) {
				$('.modal-backdrop').remove();
				$('.sk-spinner-wave').remove();
				if (!ret.error) {
					$('#addOppModal input[name="eid"]').val(ret.data.id);
					$('#addOppModal select[name="role_id"]').val(ret.data.role_id);
					$('#addOppModal input[name="account"]').val(ret.data.account);
					$('#addOppModal input[name="name"]').val(ret.data.name);
					$('#addOppModal input[name="phone"]').val(ret.data.phone);
					<?php if (Yii::$app->params['hide_str']): ?>
					$('#addOppModal input[name="phone"]').parent().attr('style', 'display: none');
					<?php endif; ?>
					$('#addOppModal select[name="status"]').val(ret.data.status);
					$('#addOppModal input[name="account"]').prop('readonly', true);

					$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
					$('#addOppModal .modal-title').html('编辑员工');
					$('#addOppModal .btn-confirm').prop('readonly', false);
					$('#addOppModal').show();
				} else {
					swal("温馨提示", '员工信息不存在！', "error");
				}
			}, 'JSON');
		}
		return false;
	}

</script>
