<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '代理商列表';
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
				<a>代理商管理</a>
			</li>
			<li class="active">
				<strong>代理商列表</strong>
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
						<!-- 总后台可添加代理商 -->
						<li>
							<button class="btn btn-primary" id="addmerproxy" onclick="addAgent()"><i class="fa fa-plus"></i> 添加代理商 </button>
						</li>
						<li><h2 class="realtime-title">代理商列表&nbsp;&nbsp;<span style="font-size:14px;">(共：<?= $totalnum;?> 条)<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<form id="searchForm" method="get" action="/admin/agent-manage/agent-list">
							<div class="row text-center" style="padding: 0 15px;">
								<div class="row search_top" style="margin-bottom: 10px">
									<!--<div class="col-sm-3 m-b-sm row"  id="temselect1" style="margin: 0">
										<div style="width: 180px;min-width: 45px; flex-shrink: 0;padding:0;height:34px;border:1px solid #ddd" class="col-sm-5" >
											<select class="form-control" name="searchType" style="line-height:28px;height:30px;border:none;">
												<option value="1" <?/*= ($searchType == '1')?'selected':'';*/?> >帐号</option>
												<option value="4" <?/*= ($searchType == '4')?'selected':'';*/?> >手机号</option>
												<option value="2" <?/*= ($searchType == '2')?'selected':'';*/?> >联系人</option>
												<option value="3" <?/*= ($searchType == '3')?'selected':'';*/?> >公司名称</option>
											</select>
										</div>
										<div class="col-sm-7" style="padding:0;height:34px;width: 180px;">
											<div class="input-group" style="width:100%">
												<input type="text" value="<?/*= $uname;*/?>" name="uname" class="input form-control" title="账号/手机号/联系人/公司名称" placeholder="请输入查询内容" id="usertoname" style="border-left: none;outline-color: transparent;">
											</div>
										</div>
									</div>-->

									<div class="col-lg-4">
										<select style="width: calc(40% - 23px);display: inline-block;" class="form-control select" name="searchType" >
											<option value="1" <?= ($searchType == '1')?'selected':'';?> >帐号</option>
											<option value="4" <?= ($searchType == '4')?'selected':'';?> >手机号</option>
											<option value="2" <?= ($searchType == '2')?'selected':'';?> >联系人</option>
											<option value="3" <?= ($searchType == '3')?'selected':'';?> >公司名称</option>
										</select>
										<span class="middleLine"></span>
										<input type="text" value="<?= $uname;?>" name="uname" class="input form-control" title="账号/手机号/联系人/公司名称" placeholder="请输入查询内容" id="usertoname" style="width: 58%; display: inline-block;">
									</div>

									<div class="col-lg-2">
										<div class="input-group"  style="width:100%">
											<select class="form-control m-b" name="searchAgentType">
												<option value="0" <?= ($searchAgentType == 0)?'selected':'';?> >代理商类型</option>
												<option value="1" <?= ($searchAgentType == 1)?'selected':'';?> >独家代理</option>
												<option value="2" <?= ($searchAgentType == 2)?'selected':'';?> >普通代理</option>
											</select>
										</div>
									</div>

									<!--<div class="col-lg-2" style="width: 210px;">
										<div class="input-group"  style="width:100%">
											<select class="form-control m-b" name="searchProvince" id="searchProvince" >
												<option value="0">选择省</option>
											</select>
										</div>
									</div>
									<div class="col-lg-2" style="width: 210px;">
										<div class="input-group"  style="width:100%">
											<select class="form-control m-b" name="searchCity" id="searchCity" >
												<option value="0">选择市</option>
											</select>
										</div>
									</div>-->

									<div class="col-lg-4" style="padding: 0;">
										<select style="width: calc(50% - 23px);display: inline-block;" class="form-control select" name="searchProvince" id="searchProvince">
											<option value="0">选择省</option>
										</select>
										<span class="middleLine"></span>
										<select style="width: calc(50% - 23px);display: inline-block;" class="form-control select" name="searchCity" id="searchCity">
											<option value="0">选择市</option>
										</select>
									</div>

									<div class="col-lg-2">
										<div class="input-group"  style="width:100%">
											<select class="form-control m-b" name="searchStatus">
												<option value="0">代理商状态</option>
												<option value="1" <?= ($searchStatus == 1)?'selected':'';?>>待签约</option>
												<option value="2" <?= ($searchStatus == 2)?'selected':'';?>>未到期</option>
												<option value="3" <?= ($searchStatus == 3)?'selected':'';?>>已到期</option>
												<option value="4" <?= ($searchStatus == 4)?'selected':'';?>>禁用</option>
											</select>
										</div>
									</div>
								</div>

								<div class="row search_top" style="margin-bottom: 10px">
									<div class="col-lg-4">
										<select style="width: calc(40% - 23px);display: inline-block;" class="form-control select" name="time_type" id="time_type">
											<option <?= ($time_type == 'addtime')?'selected':'';?> value="addtime">创建时间</option>
											<option <?= ($time_type == 'contract_time')?'selected':'';?> value="contract_time">签约时间</option>
											<option <?= ($time_type == 'endtime')?'selected':'';?> value="endtime">到期时间</option>
										</select>
										<span class="middleLine"></span>
										<input style="width: 58%; display: inline-block;" class="input form-control" name="dates" value="<?=$dates?>" placeholder="时间">
									</div>


									<div class="col-lg-2">
										<div class="input-group"  style="width:100%">
											<select class="form-control m-b" name="searchSortType">
												<option value="0">排序规则</option>
												<option value="1" <?= ($searchSortType == 1)?'selected':'';?>>服务点数从高到低</option>
												<option value="2" <?= ($searchSortType == 2)?'selected':'';?>>服务点数从低到高</option>
											</select>
										</div>
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
											<th width="5%">代理商ID</th>
											<th width="10%">账号</th>
											<th width="10%">手机号</th>
											<th>联系人</th>
											<th width="15%">公司名称</th>
											<th width="8%">省市</th>
											<th>代理类型</th>
											<th>服务点数</th>
											<th>保证金</th>
											<th>折扣</th>
											<th>签约时间</th>
											<th>到期时间</th>
											<th>状态</th>
											<th style="text-align: center;">操作</th>
										</tr>
										</thead>

										<tbody id="table-list-body" class="js-list-body-region">
										<?php if (!empty($agentList)) { ?>
										<?php foreach($agentList as $agent){ ?>
										<tr class="widget-list-item">
											<td><?= $agent['agent_id']; ?></td>
											<td><?= $agent['account']; ?></td>
											<td><?= \app\util\SUtils::hideString($agent['phone']); ?></td>
											<td><?= \app\util\SUtils::hideString($agent['lxname'], 1, 1); ?></td>
											<td><?= \app\util\SUtils::hideString($agent['aname'], 1, 8); ?></td>
											<td><?= $agent['province'] . '-' . $agent['city']; ?></td>
											<td><?= ($agent['type'] == 1) ? '独家代理' : '普通代理'; ?></td>
											<td><?= $agent['balance']; ?></td>
											<td><?= $agent['cash_deposit']; ?></td>
											<td><?= $agent['discountStr']; ?></td>
											<td><?= $agent['contract_time'] ? date('Y-m-d', $agent['contract_time']) : '--'; ?></td>
											<td><?= $agent['endtime'] ? date('Y-m-d', $agent['endtime']) : '--'; ?></td>
											<td><?= $agent['statusName']; ?></td>
											<td style="text-align: center;">
												<a class="btn btn-primary" onclick="showMore(<?=$agent['agent_id'];?>)">查看更多</a>
											</td>
										</tr>
										<tr class="more-btn more-btn-<?=$agent['agent_id'];?>" style="display:none;">
											<td colspan="15" align="right">
												<?php if ($agent['is_contract']){ ?>
													<a class="btn btn-primary" onclick='setEndTimeLong("<?= $agent['agent_id']; ?>","<?= $agent['endtime']; ?>")'> 签约延期 </a>

													<?php if ($agent['status'] == 1){ ?>
														<a class="btn btn-danger" onclick="agentStatus(<?=$agent['agent_id'];?>, 0)">设为禁用</a>
													<?php }else{ ?>
														<a class="btn btn-primary" onclick="agentStatus(<?=$agent['agent_id'];?>, 1)">设为启用</a>
													<?php } ?>

													<a class="btn btn-primary" onclick="agentBalance(<?=$agent['agent_id'];?>)">服务点数修改</a>

													<a class="btn btn-primary" target="_blank" href="/admin/agent-manage/agent-balance-list?aid=<?= $agent['agent_id']; ?>"> 服务点数明细 </a>

													<a class="btn btn-primary" target="_blank" href="/admin/user-manage/user-merchant-statistics?aid=<?= $agent['agent_id']; ?>"> 查看商户 </a>

												<?php }else{ ?>
													<a class="btn btn-primary" onclick='setContract("<?= $agent['agent_id']; ?>")'> 签 约 </a>
												<?php } ?>
												<a class="btn btn-primary" onclick="resetPwd(<?= $agent['agent_id']; ?>)"> 重置密码 </a>

												<a class="btn btn-primary" onclick="editAgent(<?= $agent['agent_id']; ?>)"> 编 辑 </a>

											</td>
										</tr>
										<?php } ?>
										<?php }else{ ?>
										<tr class="widget-list-item"><td colspan="15">暂无代理商信息</td></tr>
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

<!-- 录入代理商 -->
<div class="modal inmodal" tabindex="-1" id="addOppModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">添加代理商</h4>
			</div>
			<div class="modal-body">
				<div class="setting_rows">
					<div id="wxActionBox" class="wxpay_box">
						<form action="" method="post" enctype="multipart/form-data">
							<div class="setting_rows">
								<div id="addinputBox" class="wxpay_box">
									<input type="hidden" name="uid" value="0">
									<div class="form-group">
										<label><font color="red">*</font>账 号：</label>
										<input type="text" placeholder="请填帐号(必填)" class="form-control" name="account">
									</div>
									<div class="form-group">
										<label><font color="red">*</font>手 机 号：</label>
										<input type="text" placeholder="请填手机号(必填)" class="form-control" name="phone" onkeyup="onlytelNumber(this,11)">
									</div>
									<div class="form-group ">
										<label><font color="red">*</font>联 系 人：</label>
										<input type="text" placeholder="联系人名称(必填)" value="" class="form-control" name="lxname">
									</div>

									<div class="form-group">
										<label><font color="red">*</font>公司名称：</label>
										<input type="text" placeholder="公司名称(必填)" value="" class="form-control" name="lxcompany">
									</div>

									<div class="form-group">
										<label><font color="red">*</font>负责区域：</label>
										<div class="" style="width: 40%;display: inline-block;">
											<select class="form-control select" name="province" id="province" >
												<option value="0">*请选择省</option>
											</select>
										</div>
										<div class="" style="width: 40%;display: inline-block;">
											<select class="form-control select" name="city" id="city" >
												<option value="0">*请选择市</option>
											</select>
										</div>
									</div>

									<div class="form-group oagent_agency_type">
										<label><font color="red">*</font>代理类型：</label>
										<div class="radio radio-success radio-inline">
											<input type="radio" value="1" name="agent_agency_type" class="agent_agency_type1" id="agent_agency_type1">
											<label for="agent_agency_type1">独家代理</label>
										</div>
										<div class="radio radio-success radio-inline">
											<input type="radio" value="2" checked="checked" name="agent_agency_type" class="agent_agency_type2" id="agent_agency_type2">
											<label for="agent_agency_type2">普通代理</label>
										</div>
									</div>

									<div class="form-group">
										<label><font color="red">*</font>折 扣 率：</label>
										<div class="" style="width: 40%;display: inline-block;">
											<select class="form-control select" name="discount" id="discount" >
												<option value="1">1折</option>
												<option value="2">2折</option>
												<option value="3">3折</option>
												<option value="4">4折</option>
												<option value="5">5折</option>
												<option value="6">6折</option>
												<option value="7">7折</option>
												<option value="8">8折</option>
												<option value="9">9折</option>
												<option value="10">不打折</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label><font color="red">*</font>保证金：</label>
										<input type="text" placeholder="请填保证金(必填)" class="form-control" name="deposit" onkeyup="value=value.replace(/[^1234567890\.]+/g,'')">
									</div>
									<div class="form-group">
										<label><font color="red">*</font>服务点数：</label>
										<input type="text" placeholder="请填服务点数(必填)" class="form-control" name="prestore" onkeyup="value=value.replace(/[^1234567890\.]+/g,'')">
									</div>

									<div class="form-group ostatus">
										<label><font color="red">*</font>账户状态：</label>
										<div class="radio radio-success radio-inline">
											<input disabled type="radio" value="1" name="status" class="status1" id="status1">
											<label for="status1">启用</label>
										</div>
										<div class="radio radio-success radio-inline">
											<input disabled type="radio" value="0" checked="checked" name="status" class="status0" id="status0">
											<label for="status0">禁用</label>
										</div>
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

<!-- 重置密码 -->
<div class="modal inmodal" tabindex="-1" id="resetPwdMer">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close"><span>×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">修改代理商密码</h4>
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

<!-- 服务点数修改 -->
<div class="modal inmodal" tabindex="-1" id="addpreDeposit" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">修改金额</h4>
			</div>
			<div class="modal-body">
				<form action="" method="post" enctype="multipart/form-data">
					<div class="setting_rows">
						<div id="addinputBox" class="wxpay_box">
							<input type="hidden" name="uid" value="0">
							<div class="form-group type_list">
								<label><font color="red">*</font>增加/减少：</label>
								<div class="radio radio-success radio-inline">
									<input type="radio" value="1" checked="checked" name="type" id="type1">
									<label for="type1">增加</label>
								</div>
								<div class="radio radio-success radio-inline">
									<input type="radio" value="0" name="type" id="type0">
									<label for="type0">减少</label>
								</div>
							</div>
							<div class="form-group">
								<label><font color="red">*</font>类型：</label>
								<select class="form-control" name="balance_type" id="balance_type">
									<option value="1" class="add_type1">充值</option>
									<option value="9" class="add_type9">其他</option>
								</select>
							</div>
							<div class="form-group">
								<label><font color="red">*</font>金额：</label>
								<input type="text" placeholder="金额(必填)" value="" class="form-control" name="balance">
							</div>

							<div class="form-group">
								<label>备注：</label>
								<textarea name="desc" id="desc" class="form-control" placeholder="备注(选填)"></textarea>
							</div>
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
	function clear(){
		window.location.href = '/admin/agent-manage/agent-list';
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

		//省市查询
		getSearchProvinceCity();
	});

	function getSearchProvinceCity () {
		var leafId         = 1;
		var searchCity     = '<?= $searchCity;?>';
		var searchProvince = '<?= $searchProvince;?>';
		if (searchCity > 0) {
			leafId = searchCity;
		} else if (searchProvince > 0) {
			leafId = searchProvince;
		}
		$.post('/admin/index/get-district', {
			type    : 'getAll',
			leafId  : leafId,
			level   : 2,
			nowCheck: {'province': searchProvince, 'city': searchCity}
		}, function (data) {
			if (data) {
				if (data.province) {
					$('#searchProvince').html(data.province);
				}
				if (data.city) {
					$('#searchCity').html(data.city);
				}
			}
		}, 'json');

		$('#searchProvince').bind('change', function () {
			var nextId = $(this).val();
			if (nextId > 0){
				$.post('/admin/index/get-district', {
					type  : 'getNext',
					nextId: nextId,
					level : 2
				}, function (data) {
					if (data) {
						if (data.city) {
							$('#searchCity').html(data.city);
						}
					}
				}, 'json');
			}else{
				$('#searchCity').html('<option value="0">*请选择市</option>');
			}
		});
	}

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

	//录入代理商
	function addAgent () {
		$.post('/admin/index/get-district', {type:'getAll',leafId:1,level:2},function (data) {
			if(data){
				if(data.province){
					$('#addOppModal select[name="province"]').html(data.province);
				}
				if(data.city){
					$('#addOppModal select[name="city"]').html(data.city);
				}
			}
		},'json');

		$('#addOppModal input[name="uid"]').val('0');
		$('#addOppModal .modal-title').html('添加代理商');
		$('#addOppModal input[name="account"]').prop('readonly', false);
		$('#addOppModal input[name="deposit"]').prop('readonly', false);
		$('#addOppModal input[name="prestore"]').prop('readonly', false);
		$('#addOppModal select[name="discount"]').attr('disabled', false);

		$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
		$('#addOppModal .btn-confirm').prop('disabled', false);
		$('#addOppModal').show();

		$('#addOppModal select[name="province"]').bind('change',function () {
			var nextId = $(this).val();
			$.post('/admin/index/get-district', {type:'getNext',nextId:nextId,level:2},function (data) {
				if(data){
					if(data.city){
						$('#addOppModal select[name="city"]').html(data.city);
					}
				}
			},'json');
		});
	}
	$('#addOppModal ._close').click(function () {
		$('#addOppModal').hide();
		$('#addOppModal form')[0].reset();
		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
	});
	$('#addOppModal .btn-primary').click(function () {
		var account   = $('#addOppModal input[name="account"]').val();
		var phone     = $('#addOppModal input[name="phone"]').val();
		var lxname    = $('#addOppModal input[name="lxname"]').val();
		var lxcompany = $('#addOppModal input[name="lxcompany"]').val();
		var deposit   = $('#addOppModal input[name="deposit"]').val();
		var prestore  = $('#addOppModal input[name="prestore"]').val();
		var province  = $('#province').val();
		var city      = $('#city').val();

		if (!account) {
			swal("温馨提示", '请填写账号！', "error");
			return false;
		}
		if (!phone) {
			swal("温馨提示", '请填写手机号！', "error");
			return false;
		}
		if (!lxname) {
			swal("温馨提示", '请填写联系人！', "error");
			return false;
		}
		if (!lxcompany) {
			swal("温馨提示", '请填写公司名称！', "error");
			return false;
		}
		if (!deposit) {
			swal("温馨提示", '请填写保证金！', "error");
			return false;
		}
		if (!prestore) {
			swal("温馨提示", '请填写服务点数！', "error");
			return false;
		}
		if (!province) {
			swal("温馨提示", '请选择一个省份！', "error");
			return false;
		}
		if (!city) {
			swal("温馨提示", '请选择一个城市！', "error");
			return false;
		}

		var addData = $('#addOppModal form').serialize();
		$.post('/admin/agent-manage/add-agent', addData, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				$('#addOppModal').hide();
				$('#addOppModal form')[0].reset();
				$('.modal-backdrop').remove();
				$('body').css('padding-right', '0px').removeClass('modal-open');
				swal({
					title: '温馨提示',
					text:  '提交成功！',
					type:  "success",
					confirmButtonText: "确定",
				}, function () {
					window.location.reload();
				});
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'json');
	});

	//设置签约
	function setContract (agentId) {
		if (agentId) {
			var endTime    = Date.parse(new Date()) + 365 * 86400 * 1000;
			var myDate = new Date(endTime);
			var ymd    = myDate.getFullYear() + "-" + (myDate.getMonth() + 1) + "-" + myDate.getDate();
			var tip    = '确定签约到' + ymd +'吗？';
			swal({
				title: "温馨提醒",
				text: tip,
				type: "warning",
				showCancelButton: true,
				confirmButtonText: "确定",
				cancelButtonText: "取消",
				closeOnConfirm: false,
				closeOnCancel: true
			}, function (isConfirm) {
				if (isConfirm) {
					$.post('/admin/agent-manage/set-contract', {agentId: agentId}, function (ret) {
						if (!ret.error) {
							swal({
								title: "温馨提示",
								text: "设置成功！",
								type: "success"
							},function(){
								window.location.reload();
							});
						} else {
							swal("温馨提示", ret.msg, "error");
						}
					}, 'json');
				}
			});
		} else {
			layer.msg('参数错误！');
		}
		return false;
	}

	//设置签约延期
	function setEndTimeLong (agentId, endTime) {
		if (agentId) {
			endTime    = (parseInt(endTime) + parseInt(365 * 86400)) * 1000;
			var myDate = new Date(endTime);
			var ymd    = myDate.getFullYear() + "-" + (myDate.getMonth() + 1) + "-" + myDate.getDate();
			var tip    = '确定将签约时间延期到' + ymd +'吗？';
			swal({
				title: "温馨提醒",
				text: tip,
				type: "warning",
				showCancelButton: true,
				confirmButtonText: "确定",
				cancelButtonText: "取消",
				closeOnConfirm: false,
				closeOnCancel: true
			}, function (isConfirm) {
				if (isConfirm) {
					$.post('/admin/agent-manage/set-time-long', {agentId: agentId}, function (ret) {
						if (!ret.error) {
							swal({
								title: "温馨提示",
								text: "延期成功！",
								type: "success"
							},function(){
								window.location.reload();
							});
						} else {
							swal("温馨提示", ret.msg, "error");
						}
					}, 'json');
				}
			});
		} else {
			layer.msg('参数错误！');
		}
		return false;
	}

	//修改代理商密码
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
			swal("温馨提示", '没有选择代理商！', "error");
			return false;
		}
		$.post('/admin/agent-manage/reset-agent-pwd', {password: rpwd, uid: uid}, function (rets) {
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

	//编辑代理商
	function editAgent (uid) {
		if (uid) {
			$('body').append('<div class="modal-backdrop in"></div><div class="sk-spinner sk-spinner-wave" style="position: fixed; top: 50%; left: 50%;z-index:2048;"> <div class="sk-rect1"></div> <div class="sk-rect2"></div> <div class="sk-rect3"></div> <div class="sk-rect4"></div> <div class="sk-rect5"></div> </div>');
			$.post('/admin/agent-manage/get-one-agent', {uid: uid}, function (ret) {
				$('.modal-backdrop').remove();
				$('.sk-spinner-wave').remove();
				if (!ret.error) {
					$('#addOppModal input[name="uid"]').val(ret.data.agent_id);
					$('#addOppModal input[name="lxcompany"]').val(ret.data.aname);
					$('#addOppModal input[name="lxname"]').val(ret.data.lxname);
					$('#addOppModal input[name="account"]').val(ret.data.account);
					$('#addOppModal input[name="phone"]').val(ret.data.phone);
					$('#addOppModal input[name="deposit"]').val(ret.data.cash_deposit);
					$('#addOppModal input[name="prestore"]').val(ret.data.balance);
					$('#addOppModal select[name="discount"]').val(ret.data.discount * 10);
					$('#addOppModal .agent_agency_type' + ret.data.type).prop("checked", true);
					$('#addOppModal .ostatus .status' + ret.data.status).prop("checked", true);

					$('#addOppModal input[name="account"]').prop('readonly', true);
					$('#addOppModal input[name="prestore"]').prop("readonly",true);
					$('#addOppModal input[name="deposit"]').prop("readonly",true);

					var leafId = 1;
					if (ret.data.city) {
						leafId = ret.data.city;
					} else if (ret.data.province) {
						leafId = ret.data.province;
					}
					$.post('/admin/index/get-district', {
						type    : 'getAll',
						leafId  : leafId,
						level   : 2,
						nowCheck: {'province': ret.data.province, 'city': ret.data.city}
					}, function (data) {
						if (data) {
							if (data.province) {
								$('#addOppModal select[name="province"]').html(data.province);
							}
							if (data.city) {
								$('#addOppModal select[name="city"]').html(data.city);
							}
						}
					}, 'json');

					$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
					$('#addOppModal .modal-title').html('编辑代理商');
					$('#addOppModal .btn-confirm').prop('readonly', false);
					$('#addOppModal').show();

					$('#addOppModal select[name="province"]').bind('change', function () {
						var nextId = $(this).val();
						$.post('/admin/index/get-district', {
							type  : 'getNext',
							nextId: nextId,
							level : 2
						}, function (data) {
							if (data) {
								if (data.city) {
									$('#addOppModal select[name="city"]').html(data.city);
								}
							}
						}, 'json');
					});
				} else {
					swal("温馨提示", '账号信息不存在！', "error");
				}
			}, 'JSON');
		}
		return false;
	}

	//禁用/启用
	function agentStatus (agentId, status) {
		if(status == 0){
			var title ='确定要禁用该代理商吗？';
			var title1 ='禁用代理商信息';
		} else {
			var title ='确定要启用该代理商吗？';
			var title1 ='启用代理商信息';
		}
		swal({
			title: title1,
			text:  title,
			type:  "warning",
			confirmButtonText: "确定",
			cancelButtonText: "取消",
			showCancelButton: true
		}, function () {
			$.post('/admin/agent-manage/set-agent-status', {agentId : agentId, status : status}, function (data) {
				data.error = parseInt(data.error);
				if (!data.error) {
					swal({
						title: "温馨提示",
						text: "设置成功！",
						type: "success"
					},function(){
						window.location.reload();
					});
				} else {
					swal("温馨提示", data.msg, "error");
				}
			}, 'JSON');
		});
	}

	//服务点数修改
	function agentBalance (agentId) {
		$('body').append('<div class="modal-backdrop in"></div>').css('padding-right', '20px').addClass('modal-open');
		$('#addpreDeposit input[name="uid"]').val(agentId);

		$('#addpreDeposit .btn-confirm').prop('disabled', false);
		$('#addpreDeposit').show();
	}
	$('#addpreDeposit ._close').click(function () {
		$('#addpreDeposit').hide();
		$('#addpreDeposit  input[type="text"]').val('');
		$('#addpreDeposit  textarea').val('');
		$('#addpreDeposit  input[name="uid"]').val('0');

		$('.modal-backdrop').remove();
		$('body').css('padding-right', '0px').removeClass('modal-open');
	});
	$('#addpreDeposit input[name="type"]').on('click',function(){
		var type = $(this).val();
		if(type == 0){
			$('#addpreDeposit select[name="balance_type"]').val(9).html('<option value="9" class="add_type9">减少</option>');
		} else {
			$('#addpreDeposit select[name="balance_type"]').val(1).html('<option value="1" class="add_type1">充值</option><option value="9" class="add_type9">其他</option>');
		}
	});
	$('#addpreDeposit .btn-confirm').click(function () {
		var postdata = {};
		var tmpdata  = '';

		postdata.uid = $('#addpreDeposit input[name="uid"]').val();
		postdata.uid = parseInt(postdata.uid);

		tmpdata       = $('#addpreDeposit .type_list input[name="type"]:checked').val();
		postdata.type = parseInt(tmpdata);

		tmpdata = $('#addpreDeposit input[name="balance"]').val();
		if (!tmpdata) {
			swal("温馨提示", '金额不能为空', "error");
			return false;
		}
		reg = /^\d+(\.\d+)?$/;
		if (!reg.test(tmpdata) || tmpdata <= 0) {
			swal("温馨提示", '金额为大于0的数字', "error");
			return false;
		}
		postdata.balance      = tmpdata;
		tmpdata               = $('#addpreDeposit textarea[name="desc"]').val();
		postdata.desc         = tmpdata;
		postdata.balance_type = $('#balance_type').val();

		var _thisobj = $(this);
		_thisobj.prop('disabled', true);

		$('#addpreDeposit .btn-confirm').prop('disabled', true);
		$.post('/admin/agent-manage/agent-balance-edit', postdata, function (ret) {
			_thisobj.prop('disabled', false);
			if (!ret.error) {
				swal({
					title: "温馨提示",
					text : ret.msg,
					type : "success"
				}, function () {
					window.location.reload();
				});
			} else {
				swal("温馨提示", ret.msg, "error");
			}
		}, 'JSON');
	});

</script>
