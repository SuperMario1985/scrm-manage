<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '短信订单';
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
				<a>交易流水</a>
			</li>
			<li class="active">
				<strong>短信订单</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">
			<div id="actionListBox" class="ibox float-e-margins ">
				<?php if ($isJump){ ?>
					<div class="ibox-title clearfix">
						<h2 class="wx_title col-sm-8">
							<?php echo \app\util\SUtils::hideString($account);?>：
						</h2>
						<div class="col-sm-4 text-right"><a class="btn btn-primary" href="javascript:;" onclick="history.go(-1)">返回</a></div>
					</div>
					<hr>
				<?php } ?>

				<div class="ibox-content">
					<?php if ($isJump == 0){ ?>
					<div class="form-group">
						<div class="row">
							<form id="searchForm" method="get" action="/admin/order-manage/user-order">
								<div class="col-lg-2">
									<input class="input form-control" name="orderId" value="<?=$orderId?>" placeholder="订单号">
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="uid" id="uid">
										<option value="0">账户查询</option>
										<?php foreach($allUser as $user):?>
											<option <?= ($uid == $user['uid'])?'selected':'';?> value="<?=$user['uid'];?>"><?=\app\util\SUtils::hideString($user['account']);?></option>
										<?php endforeach;?>
									</select>
								</div>
								<div class="col-lg-2">
									<select class="form-control select" name="packId" id="packId">
										<option value="0">选择短信包</option>
										<?php foreach($messagePack as $pack):?>
											<option <?= ($packId == $pack['id'])?'selected':'';?> value="<?=$pack['id'];?>"><?='短信包' . $pack['num'] . '条';?></option>
										<?php endforeach;?>
									</select>
								</div>
								<!--<div class="col-lg-2">
									<select class="form-control select" name="status" id="status">
										<option <?/*= ($status == -1)?'selected':'';*/?> value="-1">审核状态</option>
										<option <?/*= ($status == 0)?'selected':'';*/?> value="0">待审核</option>
										<option <?/*= ($status == 1)?'selected':'';*/?> value="1">已通过</option>
										<option <?/*= ($status == 2)?'selected':'';*/?> value="2">未通过</option>
									</select>
								</div>-->

								<div class="col-lg-2">
									<input class="input form-control" name="dates" value="<?=$dates?>" placeholder="购买时间">
								</div>
								<a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a>
								<a class="btn btn-primary" style="width:70px;" href="javascript:clear()">清 空</a>
							</form>
						</div>
					</div>
					<?php } ?>
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="5%">序号</th>
							<th width="10%">订单编号</th>
							<th width="15%">购买时间</th>
							<th width="15%">账户</th>
							<th width="15%">购买类型</th>
							<th width="">购买套餐</th>
							<th width="10%">金额</th>
							<th width="10%">状态</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php $num = 1; ?>
						<?php foreach($orderList as $order){ ?>
							<tr class="widget-list-item action-info-<?=$order['id'];?>">
								<td class="action-id"><?=$num;?></td>
								<td><?= $order['order_id'] ? $order['order_id'] : '--'; ?></td>
								<td><?= $order['paytime']; ?></td>
								<td>
									<?= !empty($order['account']) ? \app\util\SUtils::hideString($order['account']) : '系统默认';?>
								</td>
								<td><?= $order['goods_type']; ?></td>
								<td><?= $order['goods_name']; ?></td>
								<td><?= $order['goods_price']; ?></td>
								<td><?= $order['status']; ?></td>
							</tr>
						<?php $num++; } ?>
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

<script>
	function search(){
		$("#searchForm").submit();
	}
	function clear(){
		window.location.href = '/admin/order-manage/user-order';
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

</script>