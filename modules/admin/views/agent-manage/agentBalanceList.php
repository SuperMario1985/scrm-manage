<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '服务点数明细';
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
				<strong>服务点数明细</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">

			<div class="col-md-3">
				<div class="ibox float-e-margins">
					<div class="ibox-title">
						<span class="label label-success pull-right">服务点数</span>
						<h5>当前服务点数（<?= \app\util\SUtils::hideString($aname, 1, 8);?>）</h5>
					</div>
					<div>
						<div class="ibox-content border-left-right">
							<h4><strong>
									<?= $balance;?>
								</strong></h4>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="ibox float-e-margins">
					<div class="ibox-title">
						<span class="label label-success pull-right">保证金</span>
						<h5>当前保证金（<?= \app\util\SUtils::hideString($aname, 1, 8);?>）</h5>
					</div>
					<div>
						<div class="ibox-content border-left-right">
							<h4><strong>
									<?= $deposit;?>
								</strong></h4>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="ibox float-e-margins">
					<div class="ibox-title">
						<span class="label label-success pull-right">意向客户数量</span>
						<h5>当前意向客户（<?= \app\util\SUtils::hideString($aname, 1, 8);?>）</h5>
					</div>
					<div>
						<div class="ibox-content border-left-right">
							<h4><strong>
									<?= $userNum;?>
								</strong></h4>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="ibox float-e-margins">
					<div class="ibox-title">
						<span class="label label-success pull-right">入驻客户数量</span>
						<h5>当前入驻客户（<?= \app\util\SUtils::hideString($aname, 1, 8);?>）</h5>
					</div>
					<div>
						<div class="ibox-content border-left-right">
							<h4><strong>
									<?= $userMerchantNum;?>
								</strong></h4>
						</div>
					</div>
				</div>
			</div>

			<div class="ibox float-e-margins">
				<div class="ibox-title clearfix">
					<ul class="nav">
						<li><h2 class="realtime-title">服务点数明细&nbsp;&nbsp;<span style="font-size:14px;">(共：<?= $totalnum;?> 条)<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="form-group">
						<form id="searchForm" method="get" action="/admin/agent-manage/agent-balance-list">
							<input type="hidden" id="aid" name="aid" value="<?= $agentid;?>">
							<div class="row text-center" style="margin-bottom: 10px">
								<div class="col-lg-2" style="width: 210px;">
									<div class="input-group"  style="width:100%">
										<select class="form-control m-b" name="searchType">
											<option value="0" <?= ($searchType == 0)?'selected':'';?> >全部</option>
											<option value="1" <?= ($searchType == 1)?'selected':'';?> >充值</option>
											<option value="2" <?= ($searchType == 2)?'selected':'';?> >提单</option>
											<option value="3" <?= ($searchType == 3)?'selected':'';?> >其它</option>
										</select>
									</div>
								</div>

								<div class="col-lg-2">
									<input class="input form-control" name="dates" value="<?=$dates?>" placeholder="时间">
								</div>

								<div class="col-sm-2 m-b-sm">
									<div class="m-b-sm text-left">
										<div class="input-group m-t-xs" style="width:100%">
											<a class="btn btn-primary btn-sm" style="margin-bottom:0" href="javascript:search()">查 询</a>&nbsp;
											<a class="btn btn-white btn-sm" style="margin-bottom:0" href="javascript:clear()">清 空</a>
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
											<th width="10%">时间</th>
											<th>增加/减少</th>
											<th>类型</th>
											<th>余点变动</th>
											<th width="20%">备注</th>
											<th>操作人</th>
										</tr>
										</thead>

										<tbody id="table-list-body" class="js-list-body-region">
										<?php if (!empty($balanceList)) { ?>
										<?php foreach($balanceList as $agent){ ?>
										<tr class="widget-list-item">
											<td><?= $agent['id']; ?></td>
											<td><?= date('Y-m-d H:i:s', $agent['time']); ?></td>
											<td><?= $agent['type'] == 1 ? '增加' : '减少'; ?></td>
											<td><?= $agent['blance_type']; ?></td>
											<td><?= $agent['balance']; ?></td>
											<td><?= $agent['des']; ?></td>
											<td><?= $agent['operator_type']; ?></td>
										</tr>
										<?php } ?>
										<?php }else{ ?>
										<tr class="widget-list-item"><td colspan="15">暂无数据</td></tr>
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
	function clear(){
		var aid = $('#aid').val();
		window.location.href = '/admin/agent-manage/agent-balance-list?aid=' + aid;
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

</script>