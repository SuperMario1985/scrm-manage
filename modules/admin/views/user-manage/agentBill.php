<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '提单列表';

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
				<a>客户管理</a>
			</li>
			<li class="active">
				<strong>提单列表</strong>
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
						<li><h2 class="realtime-title">提单列表&nbsp;&nbsp;<span style="font-size:14px;">(共：<?= $snum;?> 条<?php if ($isAgent == 0){ ?>，<span style='color: #FF562D; font-weight: 500;'>只显示代理商的提单情况</span><?php } ?>)<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<form id="searchForm" method="get" action="/admin/user-manage/agent-bill">
							<div class="row search_top" style="margin-bottom: 10px; padding-right: 15px;">
							<?php if ($isAgent == 0){ ?>
								<!--<label style="float: left;height: 34px;line-height: 34px;" class="control-label title_label">提单代理商：</label>
								<div class="col-sm-2 m-b-sm">
									<div class="input-group"  style="width:100%">
										<select class="form-control m-b" name="searchProvince" id="searchProvince" >
											<option value="0">请选择省</option>
										</select>
									</div>
								</div>
								<div class="col-sm-2 m-b-sm">
									<div class="input-group"  style="width:100%">
										<select class="form-control m-b" name="searchCity" id="searchCity" >
											<option value="0">请选择市</option>
										</select>
									</div>
								</div>-->

								<div class="col-sm-2 m-b-sm">
									<select class="form-control select chosen-select" name="aid" id="aid">
										<option value="">请选择代理商</option>
										<?php foreach ($agentData as $a){ ?>
											<option value="<?= $a['uid'];?>" <?= ($a['uid'] == $aid)?'selected':'';?>><?= \app\util\SUtils::hideString($a['aname'], 1, 8);?></option>
										<?php } ?>
									</select>
								</div>
							<?php } ?>
								<!--<div class="col-sm-2 m-b-sm">
									<select class="form-control select" name="searchType">
										<option value="0">请选择类别</option>
										<option value="1" style="color: #f8ac59"  <?/*= ($searchType == 1)?'selected':'';*/?>>新开</option>
										<option value="2" style="color: #ed5565"  <?/*= ($searchType == 2)?'selected':'';*/?>>延期</option>
										<option value="3" style="color: #1c84c6"  <?/*= ($searchType == 3)?'selected':'';*/?>>升级</option>
										<option value="4" style="color: green"  <?/*= ($searchType == 4)?'selected':'';*/?>>降级</option>
									</select>
								</div>-->

								<div class="col-sm-2 m-b-sm">
									<select class="form-control select" name="searchStatus">
										<option value="0">提单状态</option>
										<option style="color: #f8ac59"  value="1" <?= ($searchStatus == 1)?'selected':'';?>>未审核</option>
										<option style="color: #1c84c6"  value="2" <?= ($searchStatus == 2)?'selected':'';?>>已审核</option>
										<option style="color: #ed5565"  value="3" <?= ($searchStatus == 3)?'selected':'';?>>已撤销</option>
									</select>
								</div>
								<div class="col-lg-2">
									<input class="input form-control" name="dates" value="<?=$dates?>" placeholder="创建/开通时间">
								</div>

								<div class="col-sm-2 m-b-sm">
									<div class="input-group">
										<input type="text" value="<?=$uname?>" name="uname" class="input form-control" placeholder="输入商户账号/名称" id="usertoname">
									</div>
								</div>

								<div class="col-sm-2 m-b-sm" style="margin-bottom: 0;width: 170px">
									<div class="m-b-xs text-left">
										<div class="input-group" style="width:100%">
											<a class="btn btn-primary" style="margin-bottom:0" href="javascript:search()">查 询</a>&nbsp;
											<a class="btn btn-white" href="/admin/user-manage/agent-bill">清 空</a>
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
											<th width="10%">类别</th>
											<th>状态</th>
											<th>代理商</th>
											<th width="15%">商户名称</th>
											<th width="15%">商户帐号</th>
											<th width="8%">审核时间</th>
											<th>套餐</th>
											<?php if ($isAgent == 0 || $eid == 0){ ?>
											<th>折扣</th>
											<th>扣服务点数</th>
											<?php } ?>
											<th style="text-align: center;">操作</th>
										</tr>
										</thead>

										<tbody id="table-list-body" class="js-list-body-region">
										<?php if (!empty($agentOrder)) { ?>
										<?php foreach($agentOrder as $order){ ?>
										<tr class="widget-list-item">
											<td><?= $order['id']; ?></td>
											<td><?= date('Y-m-d', $order['create_time']); ?></td>
											<td><?= $order['typeName']; ?></td>
											<td><?= $order['statusName']; ?></td>
											<td><?= \app\util\SUtils::hideString($order['agentName'], 1, 8); ?></td>
											<td><?= \app\util\SUtils::hideString($order['companyName'], 1, 8); ?></td>
											<td><?= \app\util\SUtils::hideString($order['account']); ?></td>
											<td><?= $order['pass_time'] ? date('Y-m-d', $order['pass_time']) : '--'; ?></td>
											<td><?= $order['packageName']; ?></td>
											<?php if ($isAgent == 0 || $eid == 0){ ?>
											<td><?= $order['discountStr']; ?></td>
											<td><?= $order['money']; ?></td>
											<?php } ?>
											<td style="text-align: center;">
												<?php if ($order['status'] == 1 && $isAgent == 1){ ?>
													<?php if ($employeeRoleId == 2 || $eid == 0){ ?>
													<a class="btn btn-primary" onclick="orderStatus(<?=$order['id'];?>, 2)">审核</a>
													<?php } ?>
												<a class="btn btn-warning" onclick="orderStatus(<?=$order['id'];?>, 3)">撤销</a>
												<?php } ?>
											</td>
										</tr>
										<?php } ?>
										<?php }else{ ?>
										<tr class="widget-list-item"><td colspan="15">暂无提单信息</td></tr>
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
		});
	}

	//审核/撤销
	function orderStatus (oid, status) {
		if(status == 3){
			var title ='确定要撤销该提单吗？';
			var title1 ='撤销提单';
		} else {
			var title ='确定审核该提单吗？';
			var title1 ='审核提单';
		}
		swal({
			title: title1,
			text:  title,
			type:  "warning",
			confirmButtonText: "确定",
			cancelButtonText: "取消",
			showCancelButton: true
		}, function () {
			$.post('/admin/user-manage/set-bill-status', {oid : oid, status : status}, function (data) {
				data.error = parseInt(data.error);
				if (!data.error) {
					swal({
						title: "温馨提示",
						text: "提交成功！",
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

</script>