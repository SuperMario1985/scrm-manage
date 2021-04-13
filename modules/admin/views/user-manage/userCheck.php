<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '客户资料审核';

	$user_type = Yii::$app->adminUser->identity->type;
	$isAgent   = $user_type != 0 ? 1 : 0;
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
<?=Html::cssFile('@web/plugins/chosen/chosen.css')?>
<?=Html::jsFile('@web/plugins/chosen/chosen.jquery.js')?>
<?=Html::jsFile('@web/js/moment.min.js')?>
<?=Html::jsFile('@web/plugins/daterangepicker/daterangepicker.js')?>
<?=Html::cssFile('@web/plugins/daterangepicker/daterangepicker-bs3.css')?>
<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<?=Html::jsFile('@web/plugins/dropzone\dropzone.js')?>
<style>
	.chosen-container-single .chosen-single {background: #fff;border-radius: 0;border-color: #e5e6e7;height: 34px !important;line-height: 34px;box-shadow: 0 0 0 white inset, 0 1px 1px rgba(0, 0, 0, 0);}
	.chosen-container-single .chosen-single div b {background-position-y: 7px;}
	.middleLine {
                    position: absolute;
                    width: 15px;
                    border-top: 1px solid #999;
                    right: -7.5px;
                    bottom: 16px
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
	.dz-preview {
		display: none;
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
				<a>客户管理</a>
			</li>
			<li class="active">
				<strong>客户资料审核</strong>
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
						<?php if ($isAgent == 1){ ?>
						<li><button id="addmerproxy" class="btn btn-primary"><i class="fa fa-plus"></i> 新增 </button></li>
						<?php } ?>
						<li><h2 class="realtime-title">客户资料审核&nbsp;&nbsp;<span style="font-size:14px;">(共：<?= $snum;?> 条)<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<form id="searchForm" method="get" action="/admin/user-manage/user-check">

							<div class="row search_top" style="margin-bottom: 10px">
								<div class="col-sm-2 m-b-sm">
									<select class="form-control select" name="searchStatus">
										<option value="0">状态</option>
										<option style="color: #f8ac59"  value="1" <?= ($status == 1)?'selected':'';?>>已提交未审核</option>
										<option style="color: #1c84c6"  value="2" <?= ($status == 2)?'selected':'';?>>通过审核</option>
										<option style="color: #ed5565"  value="3" <?= ($status == 3)?'selected':'';?>>审核失败</option>
									</select>
								</div>
								<div class="col-sm-2 m-b-sm">
									<div class="input-group">
										<input type="text" value="<?=$uName?>" name="uname" class="input form-control" placeholder="输入商户账号/名称" id="usertoname">
									</div>
								</div>

								<div class="col-sm-2 m-b-sm" style="margin-bottom: 0;width: 170px">
									<div class="m-b-xs text-left">
										<div class="input-group" style="width:100%">
											<a class="btn btn-primary" style="margin-bottom:0" href="javascript:search()">查 询</a>&nbsp;
											<a class="btn btn-white" href="/admin/user-manage/user-check">清 空</a>
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
											<th width="5%">单号</th>
											<th width="10%">申请时间</th>
											<th width="15%">商户账号</th>
											<th width="15%">所在区域</th>
											<th>代理商</th>
											<th>状态</th>
											<th style="text-align: center;">操作</th>
										</tr>
										</thead>

										<tbody id="table-list-body" class="js-list-body-region">
										<?php if (!empty($userArr)) { ?>
										<?php foreach($userArr as $user){ ?>
										<tr class="widget-list-item">
											<td><?= $user['appli_id']; ?></td>
											<td><?= $user['addtime']; ?></td>
											<td><?= \app\util\SUtils::hideString($user['account']); ?></td>
											<td><?= $user['province'] . '-' . $user['city']; ?></td>
											<td><?= \app\util\SUtils::hideString($user['agentName'], 1, 8); ?></td>
											<td><?= $user['statusName']; ?></td>
											<td style="text-align: center;">
												<?php if ($user['application_status'] == 1 || $user['application_status'] == 3){ ?>
													<?php if ($isAgent == 1){ ?>
														<a class="btn btn-primary" onclick="editUserApplication(<?=$user['uid'];?>, 1)">编辑</a>
													<?php }else{ ?>
														<a data-actionid="<?=$user['uid'];?>" data-wxstatus="" class="btn btn-primary js_modify_status"> 审 核 </a>
													<?php } ?>
												<?php } ?>

												<a class="btn btn-primary" target="_blank" href="/admin/user-manage/user-application-info?uid=<?=$user['uid'];?>">查看详情</a>
											</td>
										</tr>
										<?php } ?>
										<?php }else{ ?>
										<tr class="widget-list-item"><td colspan="15">暂无客户信息</td></tr>
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

<!-- 申请审核 -->
<div class="popover" id="changeStatus" style="padding: 30px">
	<div class="popover_inner">
		<div class="popover_content">
			<p class="popover_title" style="font-weight: bold;color: #22911B;text-align: center;">申请审核</p>
			<div class="pop_store">
				<div class="frm_control_group">
					<div class="frm_controls">
						<label class="frm_radio_label">
							<input type="radio" value="3" name="customer_status">
							<span class="lbl_content">资料有问题，打回重填</span>
						</label>
						<div class="form-group" style="display: none" id="deal_detail">
							<div class="" style="width: 210px;">
								<textarea style="height: 110px;resize: none;border-radius: 10px;" placeholder="请填写问题/300字" name="deal_detail" rows="3" class="form-control deal_detail" maxlength="300" ></textarea>
							</div>
							<span class="text-limit help-block">0/300</span>
						</div>
						<label class="frm_radio_label">
							<input type="radio" value="2" name="customer_status">
							<span class="lbl_content">申请已完成</span>
						</label>
					</div>
				</div>
			</div>
		</div>
		<div class="popover_bar">
			<button type="button" class="btn btn-primary btn_confirm">确 定</button>
			<button type="button" class="btn btn-white c-close">取 消</button>
		</div>
	</div>
	<i class="popover_arrow popover_arrow_out"></i>
	<i class="popover_arrow popover_arrow_in"></i>
</div>

<!-- 未申请商家列表 -->
<div class="modal inmodal" tabindex="-1" id="addmerproxyPop" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">

			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal"><span aria-hidden="true">×</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title">未申请商家列表</h4>
			</div>
			<div class="modal-body">
				<form action="" method="post" enctype="multipart/form-data">
					<div class="setting_rows">
						<div id="addinputBox" class="wxpay_box">
							<div class="form-group oisproxy" style="margin-bottom: 0px;">
								<div class="col-sm-12">
									<div class="input-group pull-right">
										<input type="text" placeholder="名称或帐号" class="input-sm form-control m-b-xs" id="merValue" style="width: 120px;">
										<button type="button" class="btn btn-sm btn-primary" id="merSearch">搜索</button>
									</div>
								</div>
								<table style="padding: 0px;" data-page-size="20"
								       class="ui-table ui-table-list default no-paging footable-loaded footable">
									<thead class="js-list-header-region tableFloatingHeaderOriginal">
									<tr class="widget-list-header">
										<th>选择</th>
										<th>商户名称</th>
										<th>商户账号</th>
									</tr>
									</thead>
									<tbody id="no_applist">
									<tr>
										<td>
											<div class="i-checks">
												<label>
													<input type="radio" value="1" name="isproxy" class="isproxy1"
													       checked="checked"> <i></i>
												</label>
											</div>
										</td>
										<td></td>
										<td></td>
									</tr>
									</tbody>
									<tfoot>
									<tr>
										<td colspan="3" class="footable-visible" id="pagebar" style="border-bottom:none;"></td>
									</tr>
									</tfoot>
								</table>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer" style="border-top: none; ">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">取消</button>
				<button type="button" class="btn btn-primary btn-confirm">确定</button>
			</div>
		</div>
	</div>
</div>

<!-- 编辑客户资料 -->
<div class="modal inmodal" tabindex="-1" id="editCustomer" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">

			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal"><span aria-hidden="true">×</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title">编辑客户资料</h4>
			</div>

			<div class="modal-body">
				<form action="" method="post" enctype="multipart/form-data">
					<div class="setting_rows">
						<div id="addinputBox" class="wxpay_box">
							<input type="hidden" name="uid" value="0">

							<div class="form-group">
								<label>公司主体名称：</label>
								<input type="text" placeholder="公司主体名称(必填)" value="" class="form-control" name="sh_merchant">
							</div>

							<div class="form-group">
								<label>营业执照号：</label>
								<input type="text" placeholder="营业执照号(必填)" value="" class="form-control" name="sh_license">
							</div>

							<div class="form-group uploade">
								<label>营业执照照片：</label>
								<input type="text" placeholder="营业执照照片(必填)" value="" class="form-control">
								<input type="hidden" placeholder="营业执照照片(必填)" value="" name="sh_license_cp" class="hiddeninput">
								<div class="dropz_customer" style="height: 34px;line-height: 34px;border: 1px solid #e5e6e7;width: 70px;text-align: center;position: relative;top: -34px;float: right;cursor: pointer;">
									文件上传
								</div>
							</div>

							<!--<div class="form-group uploade">
								<label>组织机构代码照片：</label>
								<input type="text" placeholder="组织机构代码照片(必填)"  value="" class="form-control">
								<input type="hidden" placeholder="组织机构代码照片(必填)" value="" name="sh_organization_cp" class="hiddeninput">
								<div class="dropz_customer" style="height: 34px;line-height: 34px;border: 1px solid #e5e6e7;width: 70px;text-align: center;position: relative;top: -34px;float: right;cursor: pointer;">
									文件上传
								</div>
							</div>-->

							<div class="form-group">
								<label>法人姓名：</label>
								<input type="text" placeholder="法人姓名(必填)" value="" class="form-control" name="sh_possessor">
							</div>

							<div class="form-group">
								<label>法人手机号：</label>
								<input type="text" placeholder="法人手机号(必填)" class="form-control" name="sh_id">
							</div>

							<!--<div class="form-group">
								<label>法人身份证号：</label>
								<input type="text" placeholder="法人身份证(必填)" class="form-control" name="sh_id">
							</div>

							<div class="form-group">
								<label>证件所属人身份 :</label>
								<br>
								<div class="radio radio-success radio-inline">
									<input type="radio" id="legal_person" value="1" name="use_custom_code" checked>
									<label for="use_custom_code"> 法人 &nbsp;&nbsp;</label>
								</div>
								<div class="radio radio-success radio-inline">
									<input type="radio" id="Agent_person" value="2" name="use_custom_code">
									<label for="is_phone_card_number"> 代理人 &nbsp;&nbsp;</label>
								</div>
							</div>

							<div class="form-group uploade">
								<label>身份证正面照片：</label>
								<input type="text" placeholder="身份证正面照片(必填)" value="" class="form-control">
								<input type="hidden" placeholder="身份证正面照片(必填)" value="" name="sh_id_cp_a" class="hiddeninput">
								<div class="dropz_customer" style="height: 34px;line-height: 34px;border: 1px solid #e5e6e7;width: 70px;text-align: center;position: relative;top: -34px;float: right;cursor: pointer;">
									文件上传
								</div>
							</div>
							<div class="form-group uploade">
								<label>身份证反面照片：</label>
								<input type="text" placeholder="身份证反面照片(必填)" value="" class="form-control">
								<input type="hidden" placeholder="身份证反面照片(必填)" value="" name="sh_id_cp_b" class="hiddeninput">
								<div class="dropz_customer" style="height: 34px;line-height: 34px;border: 1px solid #e5e6e7;width: 70px;text-align: center;position: relative;top: -34px;float: right;cursor: pointer;">
									文件上传
								</div>
							</div>

							<div class="form-group uploade">
								<label>手持身份证照片：</label>
								<input type="text" placeholder="手持身份证照片(必填)"  value="" class="form-control">
								<input type="hidden" placeholder="手持身份证照片(必填)" value="" name="sh_id_cp_c" class="hiddeninput">
								<div class="dropz_customer" style="height: 34px;line-height: 34px;border: 1px solid #e5e6e7;width: 70px;text-align: center;position: relative;top: -34px;float: right;cursor: pointer;">
									文件上传
								</div>
							</div>-->

						</div>
					</div>
				</form>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">取消</button>
				<button type="button" class="btn btn-primary btn-confirm">确定</button>
			</div>
		</div>
	</div>
</div>

<script>
	function search(){
		$("#searchForm").submit();
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

	//新建按钮
	$('#addmerproxy').click(function () {
		$('#merValue').val('');
		addMerProxy();
	});
	//商户名称或帐号搜索
	$('#merSearch').on('click',function(event) {
		var search_str=$('#merValue').val();
		addMerProxy('', search_str);
	});
	//未申请商家列表
	function addMerProxy(url, search_str){
		if(!url){
			url='/admin/user-manage/get-user-not-application';
		}
		$.post(url, {search_user:search_str}, function (ret) {
			if (!ret.error) {
				$('.modal-backdrop').remove();
				$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
				$('#no_applist').html(ret.html);

				$('#addmerproxyPop .btn-confirm').prop('disabled', false);
				$('#addmerproxyPop').show();
				$('#pagebar').html(ret.pageBar);
				$('#addinputBox .edit_s').hide();
				$("#pagebar a").on("click",function(event){
					event.preventDefault();
					addMerProxy($(this).attr('href'),search_str);
				});
			} else {
				swal("温馨提示", '没有找到未申请客户，请确认', "error");
			}
		}, 'JSON');
	}
	$('#addmerproxyPop ._close').click(function () {
		$('#addmerproxyPop').hide();
		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
	});

	$('#addmerproxyPop .btn-confirm').click(function () {
		var chooseUid = $('#addinputBox .oisproxy input:checked').val();
		if (!chooseUid) {
			swal("温馨提示", '请至少选择一个商家', "error");
			return false;
		}
		var _thisobj = $(this);
		_thisobj.prop('disabled', true);
		$('#addmerproxyPop').hide();
		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
		editUserApplication(chooseUid, 0);
	});
	//添加/编辑
	function editUserApplication (uid, type) {
		$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
		$('#editCustomer input[name="uid"]').val(uid);
		if (type == 1){
			$('#editCustomer .modal-title').html('编辑客户资料');
			$.post('/admin/user-manage/user-application-info', {uid: uid}, function (ret) {
				if (!ret.error) {
					ret.msg.merchant && $('#editCustomer input[name="sh_merchant"]').val(ret.msg.merchant);
					ret.msg.license && $('#editCustomer input[name="sh_license"]').val(ret.msg.license);
					ret.msg.license_cp && $('#editCustomer input[name="sh_license_cp"]').val(ret.msg.license_cp);
					ret.msg.license_cp && $('#editCustomer input[name="sh_license_cp"]').siblings('.form-control').val('文件已上传');
					ret.msg.license_cp && $('#editCustomer input[name="sh_license_cp"]').siblings('.form-control').attr('readonly', 'readonly');
					ret.msg.organization_cp && $('#editCustomer input[name="sh_organization_cp"]').val(ret.msg.organization_cp);
					ret.msg.organization_cp && $('#editCustomer input[name="sh_organization_cp"]').siblings('.form-control').val('文件已上传');
					ret.msg.organization_cp && $('#editCustomer input[name="sh_organization_cp"]').siblings('.form-control').attr('readonly', 'readonly');
					ret.msg.possessor && $('#editCustomer input[name="sh_possessor"]').val(ret.msg.possessor);
					ret.msg.id_number && $('#editCustomer input[name="sh_id"]').val(ret.msg.id_number);
					if(ret.msg.possessor_type == '法人'){
						$('#legal_person').attr("checked",true);
					}else{
						$('#Agent_person').attr("checked",true);
					}
					ret.msg.id_cp_a && $('#editCustomer input[name="sh_id_cp_a"]').val(ret.msg.id_cp_a);
					ret.msg.id_cp_a && $('#editCustomer input[name="sh_id_cp_a"]').siblings('.form-control').val('文件已上传');
					ret.msg.id_cp_a && $('#editCustomer input[name="sh_id_cp_a"]').siblings('.form-control').attr('readonly', 'readonly');
					ret.msg.id_cp_b && $('#editCustomer input[name="sh_id_cp_b"]').val(ret.msg.id_cp_b);
					ret.msg.id_cp_b && $('#editCustomer input[name="sh_id_cp_b"]').siblings('.form-control').val('文件已上传');
					ret.msg.id_cp_b && $('#editCustomer input[name="sh_id_cp_b"]').siblings('.form-control').attr('readonly', 'readonly');
					ret.msg.id_cp_c && $('#editCustomer input[name="sh_id_cp_c"]').val(ret.msg.id_cp_c);
					ret.msg.id_cp_c && $('#editCustomer input[name="sh_id_cp_c"]').siblings('.form-control').val('文件已上传');
					ret.msg.id_cp_c && $('#editCustomer input[name="sh_id_cp_c"]').siblings('.form-control').attr('readonly', 'readonly');

				} else {
					swal("温馨提示", '没有找到未申请商家，请确认', "error");
				}
			}, 'JSON');
		}else{
			$('#editCustomer .modal-title').html('新增客户资料');
			$('#editCustomer input[type="text"]').val('');
			$('#editCustomer .dropz_customer').siblings('.form-control').val();
			$('#editCustomer .dropz_customer').siblings('.form-control').attr('readonly', false);
			$('#editCustomer .dropz_customer').siblings('.hiddeninput').val('');
			$('#editCustomer .btn-confirm').prop('disabled', false);
		}
		$('#editCustomer').show();
	}

	$(".dropz_customer").dropzone({
		url:            "/admin/index/img-upload",
		addRemoveLinks: false,
		maxFilesize:    2,
		acceptedFiles:  ".jpeg,.jpg,.png",
		uploadMultiple: false,
		init:           function () {
			this.on("sending", function (file, responseText) {
				$('#editCustomer .modal-dialog .modal-content').append('<div class="modal-backdrop in" ></div><div id="loading_span" style="color:#44b549; position: fixed; top: 40%; left: 46.5%;z-index:2048;"><h2>正在上传,请稍候...</h2></div><div class="sk-spinner sk-spinner-wave" style="position: fixed; top: 50%; left: 50%;z-index:2048;"> <div class="sk-rect1"></div> <div class="sk-rect2"></div> <div class="sk-rect3"></div> <div class="sk-rect4"></div> <div class="sk-rect5"></div> </div>');
			});
			this.on("error", function (file, errorMessage) {
				swal("温馨提示", "上传错误：" + errorMessage, "error");
				$('#editCustomer .modal-dialog .modal-content .modal-backdrop').remove();
				$('#editCustomer .modal-dialog .modal-content .sk-spinner-wave').remove();
				$('#editCustomer .modal-dialog .modal-content #loading_span').remove();
				$('#editCustomer .modal-dialog .modal-content .dz-error').remove();
			});

			this.on("complete", function (file, errorMessage) {
				$('#editCustomer .modal-dialog .modal-content .modal-backdrop').remove();
				$('#editCustomer .modal-dialog .modal-content .sk-spinner-wave').remove();
				$('#editCustomer .modal-dialog .modal-content #loading_span').remove();
				$('#editCustomer .modal-dialog .modal-content .dz-error').remove();
			});

			this.on("success", function (file, responseText) {
				swal("温馨提示", "上传成功", "success");
				$('#editCustomer .modal-dialog .modal-content .modal-backdrop').remove();
				$('#editCustomer .modal-dialog .modal-content .sk-spinner-wave').remove();
				$('#editCustomer .modal-dialog .modal-content #loading_span').remove();
				$('#editCustomer .modal-dialog .modal-content .dz-error').remove();
				var rept = $.parseJSON(responseText);
				$(this.element).siblings('.form-control').val('文件已上传');
				$(this.element).siblings('.form-control').attr('readonly', 'readonly');
				$(this.element).siblings('.hiddeninput').val(rept.fileUrl);
			});
		}
	});

	$('#editCustomer ._close').click(function () {
		$('#editCustomer').hide();
		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
	});
	//资料提交
	$('#editCustomer .btn-confirm').click(function () {
		var postData = {};
		tempData = $('#editCustomer input[name="uid"]').val();
		postData.uid = tempData;
		tempData = $('#editCustomer input[name="sh_merchant"]').val();
		if (!tempData) {
			swal("温馨提示", '公司主体名称不能为空！', "error");
			return false;
		}
		postData.merchant = tempData;

		tempData = $('#editCustomer input[name="sh_license"]').val();
		if (!tempData) {
			swal("温馨提示", '营业执照号不能为空！', "error");
			return false;
		}
		postData.license = tempData;

		tempData = $('#editCustomer input[name="sh_license_cp"]').val();
		if (!tempData) {
			swal("温馨提示", '营业执照照片不能为空！', "error");
			return false;
		}
		postData.license_cp = tempData;

		/*tempData = $('#editCustomer input[name="sh_organization_cp"]').val();
		if (!tempData) {
			swal("温馨提示", '组织代码照片不能为空！', "error");
			return false;
		}
		postData.organization_cp = tempData;*/

		tempData = $('#editCustomer input[name="sh_possessor"]').val();
		if (!tempData) {
			swal("温馨提示", '法人姓名不能为空！', "error");
			return false;
		}
		postData.possessor = tempData;

		tempData = $('#editCustomer input[name="sh_id"]').val();
		if (!tempData) {
			swal("温馨提示", '法人手机号不能为空！', "error");
			return false;
		}
		var myreg=/^[1][3,4,5,7,8,9][0-9]{9}$/;
		if (!myreg.test(tempData)) {
			swal("温馨提示", '请输入正确手机号！', "error");
			return false;
		}
		postData.id_number = tempData;

		/*tempData = $('#editCustomer input[name="sh_id"]').val();
		if (!tempData) {
			swal("温馨提示", '法人身份证不能为空！', "error");
			return false;
		}
		postData.id_number = tempData;

		tempData = $('#editCustomer input[name="sh_id_cp_a"]').val();
		if (!tempData) {
			swal("温馨提示", '身份证正面照不能为空！', "error");
			return false;
		}
		postData.id_cp_a = tempData;

		tempData = $('#editCustomer input[name="sh_id_cp_b"]').val();
		if (!tempData) {
			swal("温馨提示", '身份证反面照不能为空！', "error");
			return false;
		}
		postData.id_cp_b = tempData;

		tempData = $('#editCustomer input[name="sh_id_cp_c"]').val();
		if (!tempData) {
			swal("温馨提示", '手持身份证照片不能为空！', "error");
			return false;
		}
		postData.id_cp_c = tempData;

		tempData = $('#editCustomer input[name="use_custom_code"]:checked').val();
		if (tempData == '1') {
			tempData = '法人';
		}else if(tempData == '2'){
			tempData = '代理人';
		}else{
			tempData = '';
		}
		postData.possessor_type = tempData;*/

		$.post('/admin/user-manage/user-application-post', postData, function (ret) {
			if(ret.error){
				swal({
					title: "温馨提示",
					text:  ret.msg,
					type:  "error"
				},function () {
					window.location.reload();
				});
			}else{
				swal({
					title: "温馨提示",
					text:  "处理成功",
					type:  "success"
				},function () {
					window.location.reload();
				});
			}
		},'json');
	});

	//资料审核
	var actid = 0, numObj = '';
	$(document).on('click', function (e) {
		var target = $(e.target);
		var statusobj = target.closest(".js_modify_status");
		if (statusobj.size() != 0) {
			actid = statusobj.data('actionid');
			numObj = statusobj.siblings('span');
			var offsetpx = statusobj.offset();
			$('#changeStatus').css('position', 'absolute').css('left', offsetpx.left - 141).css('top', offsetpx.top + 5).css('zIndex', '100').show();
		}
	});
	$('#changeStatus input:radio[value="3"]').click(function () {
		$("#deal_detail").show(400);
	})
	$('#changeStatus input:radio[value="2"]').click(function () {
		$("#deal_detail").hide(400);
	})
	$("body").on("input","textarea[name=deal_detail]",function(){
		var length = $(this).val().length;
		$(this).closest(".form-group").find("span").text(length+"/300");
	})

	$("#changeStatus .c-close").click(function () {
		actid = 0;
		numObj = '';
		$('#changeStatus input:radio').attr('checked',false);
		$("#deal_detail").hide();
		$(".deal_detail").val('');
		$("#changeStatus").hide();
	});

	$("#changeStatus .btn_confirm").click(function () {
		var datas = {uid: actid};
		var customer_status = $('#changeStatus .frm_control_group input:checked').val();
		if (typeof(customer_status) == 'undefined') {
			swal({title: "修改失败", text: '请选择审核状态', type: "error"});
			return false;
		}

		var checkedRadioVal = $('#changeStatus input:radio[name="customer_status"]:checked').val();
		if(checkedRadioVal == 3){
			var deal_detail = $(".deal_detail").val();
			if(deal_detail==''){
				swal({title: "修改失败", text: '请填写错误问题', type: "error"});
				return false;
			}else{
				datas.remark = $(".deal_detail").val();
			}
		}

		datas.customer_status = customer_status;
		if (actid > 0) {
			$("#changeStatus").hide();
			actid = 0;
			$.ajax({
				url:      "/admin/user-manage/user-application-check",
				type:     "POST",
				dataType: "json",
				data:     datas,
				success:  function (res) {
					if (!res.error) {
						if (numObj) {
							numObj.html(res.data);
						}
						numObj = '';
						swal({
							title: "修改成功",
							text:  "处理成功",
							type:  "success"
						},function () {
							window.location.reload();
						});
					} else {
						swal({
							title: "修改失败",
							text:  res.msg,
							type:  "error"
						},function () {
							window.location.reload();
						});
					}
				}
			});
		}
	});

</script>