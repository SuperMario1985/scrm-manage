<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '入驻客户统计';

	$user_type = Yii::$app->adminUser->identity->type;
	$isAgent   = $user_type != 0 ? 1 : 0;
	$eid       = isset(Yii::$app->adminUserEmployee->identity->id) ? Yii::$app->adminUserEmployee->identity->id : 0;
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
                            width: 23px;
                            border-top: 1px solid #999;
                            vertical-align : middle;
            }

    .pagination {
            float:right;
            margin:20px;
    }
    .nav > li.active {
            border-left: 4px solid #44B549 !important;
    }
    .btn-primary {
        width : 80px;
        background-color: #44b549 !important;
        border-color: #44b549 !important;
        color: #FFFFFF !important;
    }
    .btn-danger {
            width : 80px;
            background-color: #FF562D !important;
            border-color: #FF562D !important;
            color: #FFFFFF !important;
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
				<strong>入驻客户统计</strong>
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
							<form id="searchForm" method="get" action="/admin/user-manage/user-merchant-statistics">
								<input type="hidden" id="aid" name="aid" value="<?= $aid;?>">
								<div class="col-lg-2" style="width: 200px;margin-bottom: 10px;">
									<select class="form-control select" name="uid" id="uid">
										<option value="0">账户查询</option>
										<?php foreach($allUser as $user):?>
											<option <?= ($uid == $user['uid'])?'selected':'';?> value="<?=$user['uid'];?>"><?=\app\util\SUtils::hideString($user['account']);?></option>
										<?php endforeach;?>
									</select>
								</div>
								<div class="col-lg-2" style="width: 200px;margin-bottom: 10px;">
									<select class="form-control select" name="status" id="status">
										<option <?= ($status == 0)?'selected':'';?> value="0">状态</option>
										<option <?= ($status == 1)?'selected':'';?> value="1">使用中</option>
										<option <?= ($status == 2)?'selected':'';?> value="2">已到期</option>
										<option <?= ($status == 3)?'selected':'';?> value="3">已禁用</option>
									</select>
								</div>
								<?php if ($isAgent == 0){ ?>
								<div class="col-lg-2" style="width: 200px;margin-bottom: 10px;">
									<select class="form-control select" name="aid" id="aid">
										<option value="0">来源</option>
										<option <?= ($aid == -1)?'selected':'';?> value="-1">总后台</option>
										<?php foreach ($agentData as $a){ ?>
											<option value="<?= $a['uid'];?>" <?= ($a['uid'] == $aid)?'selected':'';?>><?= $a['aname'];?></option>
										<?php } ?>
									</select>
								</div>
								<?php } ?>

								<div class="col-lg-2" style="width: 200px;margin-bottom: 10px;">
									<select class="form-control select" name="source" id="source">
										<option <?= ($source == 0)?'selected':'';?> value="0">类型</option>
										<option <?= ($source == 1)?'selected':'';?> value="1">自助注册</option>
										<option <?= ($source == 2)?'selected':'';?> value="2">手动录入</option>
									</select>
								</div>
								<div class="col-lg-2" style="width: 200px;margin-bottom: 10px;">
									<input class="input form-control" name="companyName" value="<?=$companyName;?>" placeholder="公司名称">
								</div>
								<div class="col-lg-2" style="width: 578px;">
									<select style='width: calc(50% - 104px); display: inline-block;' class="form-control select" name="time_type" id="time_type">
										<option <?= ($time_type == 1)?'selected':'';?> value="1">注册时间</option>
										<option <?= ($time_type == 2)?'selected':'';?> value="2">到期时间</option>
										<option <?= ($time_type == 3)?'selected':'';?> value="3">最后一次登录时间</option>
									</select>
									<span class="middleLine"></span>
									<input style='width: calc(50% - 104px); display: inline-block;' class="input form-control" name="dates" value="<?=$dates?>" placeholder="时间">
                                    <a class="btn btn-primary" style="width:70px;margin-top: 1px;margin-left: 26px;" href="javascript:search()">查 询</a>
                                    <a class="btn btn-primary" style="width:70px;margin-top: 1px;" href="javascript:clear()">清 空</a>
								</div>

							</form>
						</div>
						<div class="row" style="margin-top: 10px;margin-left: 15px">
							<h3>总客户数：<?=$snum?><span style='color: #FF562D;'><?php if ($isAgent == 0){ echo $eid == 0 ? '（代理商和自己的入驻客户）' : '（自己的入驻客户）'; } ?></span></h3>
						</div>
					</div>
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="3%">ID</th>
							<th width="6%">账户</th>
							<th width="6%">来源</th>
							<th width="6%">员工</th>
							<th width="5%">类型</th>
							<th width="6%">套餐</th>
							<th width="10%">公司名称</th>
							<th width="10%">授权公众号/企业微信</th>
							<th width="5%">员工数</th>
							<th width="5%">客户数</th>
							<th width="9%">注册时间</th>
							<th width="8%">入驻时间</th>
							<th width="8%">到期时间</th>
							<th width="8%">最近登录时间</th>
							<th width="8%">状态</th>
							<th width="">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($userArr as $user){ ?>
							<tr class="widget-list-item action-info-<?=$user['uid'];?>">
								<td class="action-id"><?= $user['uid']; ?></td>
								<td>
									<?= !empty($user['account']) ? \app\util\SUtils::hideString($user['account']) : '系统默认';?>
								</td>
								<td><?= $user['agentName']; ?></td>
								<td><?= $user['employeeName']; ?></td>
								<td><?= $user['source']; ?></td>
								<td><?= $user['packageName']; ?></td>
								<td><?= \app\util\SUtils::hideString($user['companyName'],1, 8); ?></td>
								<td><?= $user['authorNum'] . '个'; ?>/<?= $user['corpNum'] . '个'; ?></td>
								<td><?= $user['workUserNum']; ?></td>
								<td><?= $user['externalNum']; ?></td>
								<td><?= $user['create_time']; ?></td>
								<td><?= $user['merchant_time']; ?></td>
								<td><?= $user['end_time']; ?></td>
								<td><?= $user['login_time']; ?></td>
								<td><?= $user['statusName']; ?></td>

								<td>
									<a class="btn btn-primary" onclick="showMore(<?=$user['uid'];?>)">查看更多</a>
								</td>
							</tr>
							<tr class="more-btn more-btn-<?=$user['uid'];?>" style="display:none;">
								<td colspan="16" style="text-align: right;">
								<?php if ($isAgent == 0){ ?>
									<a class="btn btn-primary" onclick="msgAudit(<?=$user['uid'];?>)" href="javascript:void(0);">会话存档</a>
								<?php } ?>
								<?php if ($isAgent == 0){ ?>
									<a class="btn btn-primary setUser" href="javascript:void(0);" uid="<?=$user['uid'];?>" limitAuthorNum="<?=$user['limit_author_num'];?>" limitCorpNum="<?=$user['limit_corp_num'];?>">账号设置</a>
									<?php if ($user['agent_uid'] == 0){ ?>
										<a class="btn btn-primary" target="_blank" href="/admin/order-manage/package-order?isJump=1&uid=<?=$user['uid'];?>&account=<?=$user['account'];?>">交易查询</a>
									<?php }else{ ?>
										<a class="btn btn-primary" target="_blank" href="/admin/user-manage/agent-bill?aid=<?=$user['agent_uid'];?>&uname=<?=$user['account'];?>">交易查询</a>
									<?php } ?>
								<?php } ?>
								<?php if ($isAgent == 1 && $employeeRoleId == 1){}else{ ?>
									<a class="btn btn-primary" onclick="resetPwd(<?=$user['uid'];?>)">重置密码</a>
								<?php } ?>

								<?php if (($isAgent == 1 && ($eid == 0 || $employeeRoleId == 2) || ($isAgent == 0 && $user['agent_uid'] == 0)) && $user['status'] == 1){ ?>
									<?php if ($user['statusName'] == '已到期'){ ?>
										<a class="btn btn-primary" onclick="userMerchant(<?=$user['uid'];?>)">重新入驻</a>
									<?php }else{ ?>
										<a class="btn btn-primary" onclick='userPackage(<?=$user['uid'];?>,<?=$user['package_id'];?>,"<?=$user['packageN'];?>")'>套餐操作</a>
									<?php } ?>
								<?php } ?>

								<?php if ($isAgent == 0){ ?>
									<?php if ($user['status'] == 1){ ?>
										<a class="btn btn-danger" onclick="userStatus(<?=$user['uid'];?>, 0)">设为禁用</a>
									<?php }else{ ?>
										<a class="btn btn-primary" onclick="userStatus(<?=$user['uid'];?>, 1)">设为启用</a>
									<?php } ?>
									<a class="btn btn-primary" href="/admin/index/quick-login?code=<?=$user['code'];?>" target="_blank">自动登录</a>
								<?php } ?>
								</td>
							</tr>
						<?php } ?>
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

<div class="modal fade inmodal" tabindex="-1" id="actionModal" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="actionModalTitle" class="modal-title">账号设置</h4>
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
						<input type="hidden" id="userid" value="">
						<div class="form-group">
							<label class="control-label" for="limitCorpNum"><span class="red">* </span>可添加企业微信数量</label>
							<div>
								<input type="text" class="form-control menu-input" id="limitCorpNum" value="" style="width: 350px;display: inline-block;"> <span style="display: inline-block;">个（0或不填则使用默认值）</span>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label" for="limitAuthorNum"><span class="red">* </span>可添加公众号数量</label>
							<div>
								<input type="text" class="form-control menu-input" id="limitAuthorNum" value="" style="width: 350px;display: inline-block;"> <span style="display: inline-block;">个（0或不填则使用默认值）</span>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary btn-confirm" data-action="edit">确定</button>
			</div>
		</div>
	</div>
</div>
<!-- 会话存档 -->
<div class="modal fade inmodal" tabindex="-1" id="msgAuditModel" role="dialog" aria-hidden="true">
	<div class="modal-dialog" style="width: 50%">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="msgAuditModelTitle" class="modal-title">会话存档</h4>
			</div>

			<div class="modal-body" style="height: 30rem">
				<ul class="nav metismenu" id="answer" role="tablist" style="margin-left: 3px;margin-top: 0px;position: relative;width: 21%;background-color: white;color: #333333;">

				</ul>
				<div class="tab-content" id="answer1" style="position: absolute;left: 23%;top: 9px; width: 73%;margin: 10px 0;background-color: #FFFFFF;">
				</div>

			</div>
			<!-- <div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>
			</div> -->
		</div>
	</div>
</div>
<div class="modal inmodal" tabindex="-1" id="resetPwdMer">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">修改客户密码</h4>
			</div>
			<div class="modal-body">
				<div class="setting_rows">
					<div id="wxActionBox" class="wxpay_box">
						<div class="form-group">
							<label>输入新密码：</label>
							<input type="text" value="" name="resetPwd" id="resetPwd" class="input form-control" placeholder="输入新密码">
							<input type="hidden" value="" name="merid" id="merid">
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary btn-confirm" onclick="mdyMerpwd()">确定</button>
				<button type="button" class="btn btn-white _close">关闭</button>
			</div>
		</div>
	</div>
</div>

<!-- 重新入驻 -->
<div class="modal inmodal" tabindex="-1" id="setUserMerchant">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">重新入驻</h4>
			</div>
			<div class="modal-body">
				<div class="data-box">
					<form role="form">
						<input type="hidden" value="" name="merchantUid" id="merchantUid">
						<div class="form-group" style="overflow:hidden;">
							<label class="control-label" style="margin:0 10px 0 0;float:left;line-height:34px;">使用套餐</label>
							<select class="form-control" name="packageChoose" id="packageChoose" style="width:160px;float:left;">
								<?php foreach($packageList as $k=>$package){?>
									<option value="<?php echo $package['id']?>" <?= ($k == 0)?'selected':'';?>><?php echo $package['name']?></option>
								<?php }?>
							</select>
						</div>
						<div class="form-group" style="overflow:hidden;">
							<label class="control-label" style="margin:0 10px 0 0;float:left;line-height:34px;">套餐时长</label>
							<select class="form-control" name="packageTime" id="packageTime" style="width:160px;float:left;">

							</select>
						</div>

						<div class="form-group">
							<label class="control-label" id="packageSendTime"></label>
						</div>
					</form>
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary btn-confirm" onclick="setUserMerchant()">确定</button>
				<button type="button" class="btn btn-white _close">关闭</button>
			</div>
		</div>
	</div>
</div>

<!-- 套餐操作 -->
<div class="modal inmodal" tabindex="-1" id="setUserPackage">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">套餐操作</h4>
			</div>
			<div class="modal-body">
				<div class="data-box">
					<form role="form">
						<input type="hidden" value="" name="packageUid" id="packageUid">

						<div class="form-group" style="overflow:hidden;">
							<label class="control-label" style="margin:0 10px 0 0;float:left;line-height:34px;">操作类型</label>
							<select class="form-control" name="packageTypeChange" id="packageTypeChange" style="width:160px;float:left;">
								<option value="2" >套餐延期</option>
								<option value="3" >套餐升级</option>
							</select>
						</div>
						<div class="form-group" style="overflow:hidden;">
							<label class="control-label" style="margin:0 10px 0 0;float:left;line-height:34px;">使用套餐</label>
							<select class="form-control" name="packageChooseChange" id="packageChooseChange" style="width:160px;float:left;">
								<option value="0" ></option>
							</select>
						</div>
						<div id="packageTimeChangeDiv" style="display: none;">
							<div class="form-group" style="overflow:hidden;">
								<label class="control-label" style="margin:0 10px 0 0;float:left;line-height:34px;">套餐时长</label>
								<select class="form-control" name="packageTimeChange" id="packageTimeChange" style="width:160px;float:left;">

								</select>
							</div>

							<div class="form-group">
								<label class="control-label" id="packageSendTimeChange"></label>
							</div>
						</div>

					</form>
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary btn-confirm" onclick="setUserPackage()">确定</button>
				<button type="button" class="btn btn-white _close">关闭</button>
			</div>
		</div>
	</div>
</div>

<script>
	var packageListJson       = <?php echo $packageListJson;?>;
	var packageLocalPriceJson = <?php echo $packageLocalPriceJson;?>;
	var packagePriceJson      = '';

	function eidtDataMsg (id,e) {
		$(e).prev().removeAttr("disabled")
		$(e).text("保存")
		$(e).removeAttr("onclick")
		$(e).attr("onclick", "saveMsgAudt("+id+",this)")
	}
	function saveMsgAudt (id,e) {
		var name = $(e).prev().attr("msg-name")
		var value = $(e).prev().val();
		$.ajax({
			url    : "/admin/user-manage/save-msg-audit",
			type   : "POST",
			data   : {key:name,value:value,id:id},
			success: function (res) {
				res = JSON.parse(res)
				$(e).prev().attr("disabled",true)
				$(e).text("修改")
				$(e).removeAttr("onclick")
				$(e).attr("onclick", "eidtDataMsg("+id+",this)")
				if(res.error == 0){
					swal("修改完成!", "修改完成！", "success");
				}else{
					$(e).prev().attr("disabled",true)
					swal("操作失败!", "操作失败！", "error");
				}
			}
		})
	}
	function changeMsgAudt (id,value) {
		$.ajax({
			url    : "/admin/user-manage/save-msg-audit",
			type   : "POST",
			data   : {key:'status',value:value,id:id},
			success: function (res) {
				res = JSON.parse(res)
				if(res.error == 0){
					if(value == 1){
						$("#audit_id").find("h3").text("通过")
						$("#audit_id").find("h3").css("color","green")
					}
					if(value == -1){
						$("#audit_id").find("h3").text("未通过")
						$("#audit_id").find("h3").css("color","red")
						$("#audit_id").find("input").val("")
					}
					swal("修改完成!", res.msg, "success");
				}else{
					swal("操作失败!",  res.msg, "error");
				}
			}
		})
	}
	function msgAudit (id) {
		$.ajax({
			url    : "/admin/user-manage/get-msg-audit",
			type   : "POST",
			async  : false,
			data   : {id: id},
			success: function (res) {
				res = JSON.parse(res)
				if(res.length<1){
					swal("无任何配置!", "无任何配置！", "error");
					return;
				}
				$("#answer").children().remove()
				$("#answer1").children().remove()
				$("#answer").append(res.heard_str);
				$("#answer1").append(res.body_str);
				$("#msgAuditModel").modal('show');
			}
		})
	}
	function search(){
		$("#searchForm").submit();
	}
	function clear(){
		window.location.href = '/admin/user-manage/user-merchant-statistics';
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
		/*$("#aid").chosen({
			no_results_text: "没有找到结果！",//搜索无结果时显示的提示
			placeholder_text_single:'没找到活动！',
			search_contains:true,   //关键字模糊搜索，设置为false，则只从开头开始匹配
			allow_single_deselect:true, //是否允许取消选择
			max_selected_options:1,  //当select为多选时，最多选择个数
			width:"100%"
		});*/
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

	//账号设置
	$(".setUser").on('click', function () {
		$("#packageAlertBox").html('');
		$("#userid").val($(this).attr('uid'));
		var limitCorpNum   = '';
		var limitAuthorNum = '';
		if ($(this).attr('limitCorpNum') > 0) {
			limitCorpNum = $(this).attr('limitCorpNum');
		}
		if ($(this).attr('limitAuthorNum') > 0) {
			limitAuthorNum = $(this).attr('limitAuthorNum');
		}
		$("#limitCorpNum").val(limitCorpNum);
		$("#limitAuthorNum").val(limitAuthorNum);
		$("#actionModal .btn-confirm").data("action", "edit");
		$("#actionModal .load-box").addClass("hide");
		$("#actionModal .data-box").removeClass("hide");

		$("#actionModal").modal('show');
	});
	// 关闭model
	$("#actionModal ._close").click(function () {
		$("#actionModal").modal('hide');
	});
	// 提交model内容
	$("#actionModal .btn-confirm").click(function () {
		var uid            = $("#userid").val();
		var limitCorpNum   = $("#limitCorpNum").val();
		var limitAuthorNum = $("#limitAuthorNum").val();

		$("#actionModal .data-box").addClass("hide");
		$("#actionModal .load-box").removeClass("hide");
		var postData = {uid: uid, limitCorpNum: limitCorpNum, limitAuthorNum: limitAuthorNum};
		$.ajax({
			url     : "/admin/user-manage/set-user",
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

	//显示更多
    function showMore (uid) {
    	$('.more-btn').map(x=>{
    		$('.more-btn')[x].style.display = 'none'
    	})
    	if($('.more-btn-'+uid)[0].style.display == 'none'){
    		$('.more-btn-'+uid)[0].style.display = ''
    	}else{
    		$('.more-btn-'+uid)[0].style.display = 'none'
    	}
    }
	//修改客户密码
	function resetPwd (uid) {
		$('#merid').val(uid);
		$('#resetPwd').val('');
		$('#resetPwdMer').show();
		$('body').append('<div class="modal-backdrop in"></div>');
	}
	$('#resetPwdMer ._close').click(function () {
		$('#resetPwdMer').hide();
		$('.modal-backdrop').remove();
		$('#merid').val('0');
	});
	function mdyMerpwd () {
		var rpwd = $.trim($('#resetPwd').val());
		if (!rpwd) {
			swal("温馨提示", '请设置一个新密码！', "error");
			return false;
		}
		var uid = $.trim($('#merid').val());
		if (!uid) {
			swal("温馨提示", '没有选择客户！', "error");
			return false;
		}
		$.post('/admin/user-manage/reset-pwd', {password: rpwd, uid: uid}, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				$('#resetPwdMer').hide();
				$('.modal-backdrop').remove();
				$('#merid').val('0');
				swal("温馨提示", '修改成功！', "success");
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'JSON');
	}

	//禁用/启用
	function userStatus (uid, status) {
		if(status == 0){
			var title ='禁用客户信息';
			var title1 ='确定要禁用该客户吗？';
		} else {
			var title ='启用客户信息';
			var title1 ='确定要启用该客户吗？';

		}
		swal({
			title: title,
			text:  title1,
			type:  "warning",
			confirmButtonText: "确定",
			cancelButtonText: "取消",
			showCancelButton: true
		}, function () {
			$.post('/admin/user-manage/set-user-status', {uid : uid, status : status}, function (data) {
				data.error = parseInt(data.error);
				if (!data.error) {
					swal("温馨提示", '设置成功！', "success");
					window.location.reload();
				} else {
					swal("温馨提示", data.msg, "error");
				}
			}, 'JSON');
		});
	}

	/**
	 * 重新入驻
	 */
	function userMerchant (uid) {
		$('#merchantUid').val(uid);
		packageChoose();
		$('#setUserMerchant').show();
		$('body').append('<div class="modal-backdrop in"></div>');
	}
	$('#setUserMerchant ._close').click(function () {
		$('#setUserMerchant').hide();
		$('.modal-backdrop').remove();
		$('#merchantUid').val('0');
	});
	//套餐切换
	$('#packageChoose').bind('change', function () {
		packageChoose();
	});
	function packageChoose () {
		var packageChooseId = $('#packageChoose').val();
		if (packageChooseId != 0) {
			packagePriceJson = packageLocalPriceJson[packageChooseId];
			packageSetPrice();
		}else{
			$('#packageTime').html('');
			$('#packageSendTime').html('');
		}
	}
	//套餐档位
	function packageSetPrice(){
		for(var key in packagePriceJson){
			var timeType = '日';
			if (packagePriceJson[key].timeType == 2){
				timeType = '月';
			}else if (packagePriceJson[key].timeType == 3){
				timeType = '年';
			}
			var price = '免费';
			if (packagePriceJson[key].nowPrice > 0){
				price = packagePriceJson[key].nowPrice + '￥';
			}
			price = '（' + price + '）';
			//赠送时长
			var sendTime = '';
			if (packagePriceJson[key].sendTimeNum > 0){
				var sendTimeType = '日';
				if (packagePriceJson[key].sendTimeType == 2){
					sendTimeType = '月';
				}else if (packagePriceJson[key].sendTimeType == 3){
					sendTimeType = '年';
				}
				sendTime = packagePriceJson[key].sendTimeNum + sendTimeType;
			}
			var option = '<option value="' + key + '" data-send="' + sendTime + '">' + packagePriceJson[key].timeNum + timeType + price + '</option>';

			if(key == 0){
				$('#packageTime').html(option);
				//赠送时长
				if (sendTime){
					$('#packageSendTime').html('赠送时长：' + sendTime);
				}else{
					$('#packageSendTime').html('');
				}
			}else{
				$('#packageTime').append(option);
			}
		}
	}
	//套餐档位切换
	$('#packageTime').bind('change', function () {
		var sendTime = $(this).find("option:selected").data('send');
		if (sendTime) {
			$('#packageSendTime').html('赠送时长：' + sendTime);
		}else{
			$('#packageSendTime').html('');
		}
	});
	//入驻提交
	function setUserMerchant () {
		var uid         = $.trim($('#merchantUid').val());
		var package_id  = $('#packageChoose').val();
		var package_key = $('#packageTime').val();
		if (!uid) {
			swal("温馨提示", '没有选择客户！', "error");
			return false;
		}
		if (package_id == 0) {
			swal("温馨提示", '请选择套餐！', "error");
			return false;
		}
		if (package_key == '') {
			swal("温馨提示", '请选择套餐时长！', "error");
			return false;
		}
		$.post('/admin/user-manage/set-user-merchant', {
			package_id : package_id,
			package_key: package_key,
			uid        : uid,
			type       : 5
		}, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				$('#setUserMerchant').hide();
				$('.modal-backdrop').remove();
				$('#merchantUid').val('0');

				var isAgent = <?php echo $isAgent;?>;
				if (isAgent == 1){
					swal({
						title: "温馨提示",
						text : '重新入驻后，请在提单管理进行确认审核。审核通过后，即可正常使用！',
						type : "success"
					}, function () {
						window.location.href = '/admin/user-manage/agent-bill';
					});
				}else{
					swal({
						title: "温馨提示",
						text : '提交成功！',
						type : "success"
					}, function () {
						window.location.reload();
					});
				}
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'JSON');
	}

	/**
	 * 套餐延期、升级
	 */
	var choosePackageId   = '';
	var choosePackageName = '';
	function userPackage (uid, package_id, package_name) {
		$('#packageUid').val(uid);
		choosePackageId   = package_id;
		choosePackageName = package_name;
		$('#packageTypeChange').val(2);
		packageChooseLong();
		$('#setUserPackage').show();
		$('body').append('<div class="modal-backdrop in"></div>');
	}
	$('#setUserPackage ._close').click(function () {
		$('#setUserPackage').hide();
		$('.modal-backdrop').remove();
		$('#packageUid').val('0');
	});
	//类型切换
	$('#packageTypeChange').bind('change', function () {
		if ($(this).val() == 2){
			packageChooseLong();
		}else{
			packageChooseUp();
		}
	});
	//套餐延期
	function packageChooseLong () {
		$("#packageChooseChange option").val(choosePackageId);
		$("#packageChooseChange option").html(choosePackageName);
		//$("#packageChooseChange").prop('disabled', true);
		var option = '<option value="' + choosePackageId + '" >' + choosePackageName + '</option>';
		$('#packageChooseChange').html(option);
		$('#packageTimeChangeDiv').show();

		if (choosePackageName == ''){
			$("#packageChooseChange").html('无可延期套餐');
		}

		if (choosePackageId != 0) {
			packagePriceJson = packageLocalPriceJson[choosePackageId];
			packageChangeSetPrice();
		}else{
			$('#packageTimeChange').html('');
			$('#packageSendTimeChange').html('');
		}
	}
	//套餐升级
	function packageChooseUp () {
		var uid = $.trim($('#packageUid').val());
		$.post('/admin/user-manage/get-upgrade-package', {uid: uid}, function (ret) {
			if (!ret.error) {
				var packageUp = ret.data;

				for(var key in packageUp){
					var option = '<option value="' + packageUp[key].id + '" >' + packageUp[key].name + '</option>';
					if(key == 0){
						$('#packageChooseChange').html(option);
					}else{
						$('#packageChooseChange').append(option);
					}
				}
				$('#packageTimeChangeDiv').hide();
			} else {
				swal("温馨提示", ret.msg, "error");
				$('#packageTypeChange').val('2');
			}
		}, 'JSON');
	}
	//套餐切换
	$('#packageChooseChange').bind('change', function () {
		packageChooseChange();
	});
	function packageChooseChange () {
		var packageChooseId = $('#packageChooseChange').val();
		if (packageChooseId != 0) {
			packagePriceJson = packageLocalPriceJson[packageChooseId];
			packageSetPrice();
		}else{
			$('#packageTimeChange').html('');
			$('#packageSendTimeChange').html('');
		}
	}
	//套餐档位
	function packageChangeSetPrice(){
		for(var key in packagePriceJson){
			var timeType = '日';
			if (packagePriceJson[key].timeType == 2){
				timeType = '月';
			}else if (packagePriceJson[key].timeType == 3){
				timeType = '年';
			}
			var price = '免费';
			if (packagePriceJson[key].nowPrice > 0){
				price = packagePriceJson[key].nowPrice + '￥';
			}
			price = '（' + price + '）';
			//赠送时长
			var sendTime = '';
			if (packagePriceJson[key].sendTimeNum > 0){
				var sendTimeType = '日';
				if (packagePriceJson[key].sendTimeType == 2){
					sendTimeType = '月';
				}else if (packagePriceJson[key].sendTimeType == 3){
					sendTimeType = '年';
				}
				sendTime = packagePriceJson[key].sendTimeNum + sendTimeType;
			}
			var option = '<option value="' + key + '" data-send="' + sendTime + '">' + packagePriceJson[key].timeNum + timeType + price + '</option>';

			if(key == 0){
				$('#packageTimeChange').html(option);
				//赠送时长
				if (sendTime){
					$('#packageSendTimeChange').html('赠送时长：' + sendTime);
				}else{
					$('#packageSendTimeChange').html('');
				}
			}else{
				$('#packageTimeChange').append(option);
			}
		}
	}
	//套餐档位切换
	$('#packageTimeChange').bind('change', function () {
		var sendTime = $(this).find("option:selected").data('send');
		if (sendTime) {
			$('#packageSendTimeChange').html('赠送时长：' + sendTime);
		}
	});
	//套餐变更提交
	function setUserPackage () {
		var uid         = $.trim($('#packageUid').val());
		var type        = $('#packageTypeChange').val();
		var package_id  = $('#packageChooseChange').val();
		var package_key = $('#packageTimeChange').val();
		if (!uid) {
			swal("温馨提示", '没有选择客户！', "error");
			return false;
		}
		if (package_id == 0) {
			swal("温馨提示", '请选择套餐！', "error");
			return false;
		}
		if (type != 2 && type != 3){
			swal("温馨提示", '套餐操作类型错误！', "error");
			return false;
		}

		var typeName = '';
		var url      = '';
		if (type == 2) {
			typeName = '套餐延期';
			url      = '/admin/user-manage/set-user-merchant';
		} else {
			typeName = '套餐升级';
			url      = '/admin/user-manage/set-user-package-up';
		}

		$.post(url, {
			package_id : package_id,
			package_key: package_key,
			uid        : uid,
			type       : type
		}, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				$('#setUserPackage').hide();
				$('.modal-backdrop').remove();
				$('#packageUid').val('0');

				var isAgent = <?php echo $isAgent;?>;
				if (isAgent == 1){
					swal({
						title: "温馨提示",
						text : typeName + '后，请在提单管理进行确认审核。审核通过后，即可正常使用！',
						type : "success"
					}, function () {
						window.location.href = '/admin/user-manage/agent-bill';
					});
				}else{
					swal({
						title: "温馨提示",
						text : '提交成功！',
						type : "success"
					}, function () {
						window.location.reload();
					});
				}
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'JSON');
	}

</script>