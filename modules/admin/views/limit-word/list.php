<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '敏感词词库';
?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
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
				<a>系统设置</a>
			</li>
			<li class="active">
				<strong>敏感词管理</strong>
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
							<form id="searchForm" method="get" action="/admin/limit-word/list">
								<div class="col-lg-2">
									<input class="input form-control" name="name" value="<?=$title?>" placeholder="名称">
								</div>
								<div class="col-lg-2">
									<select name="sort_id" class="form-control">
										<option value="0">全部分组</option>
										<?php foreach($groupArr as $key=>$title):?>
											<option <?= ($key == $group_id)?'selected':'';?> value="<?= $key?>" ><?= $title?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a>
								<a class="btn btn-primary addFieldBox" style="margin-left:10px;"><i class="fa fa-plus"></i> 添加敏感词</a>
							</form>
						</div>
					</div>

					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="10%">ID</th>
							<th width="15%">分组</th>
							<th width="15%">名称</th>
							<th width="15%">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php if (!empty($limitWord)){ ?>
							<?php foreach($limitWord as $field){ ?>
								<tr data-id="<?=$field['id'];?>">
									<td class=""><?=$field['id'];?></td>
									<td class=""><?=!empty($groupArr[$field['group_id']])?$groupArr[$field['group_id']]:'--';?></td>
									<td class=""><?=$field['title'];?></td>
									<td>
										<span class="btn btn-primary updateField" value="<?=$field['id'];?>" title="<?=$field['title'];?>" groupId="<?=$field['group_id'];?>">修改</span>
										<span class="btn btn-danger updateStatus" value="0">删除</span>
									</td>
								</tr>
							<?php } ?>
						<?php }else{ ?>
							<tr class="widget-list-item"><td colspan="3" style="text-align: center">暂无敏感词信息</td></tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="ibox-footer" style="border:none;">
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

<div class="modal inmodal" tabindex="-1" id="addFieldBox" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title">添加敏感词</h4>
			</div>
			<div class="modal-body">
				<form action="/admin/limit-word/set" method="post" name="ajax_addField"  enctype="multipart/form-data" onsubmit="return false">
					<input type="hidden" name="id" value="">
					<div class="row">
						<div id="addinputBox" class="addinputBox">
							<div class="form-group">
								<label><span class="red">* </span>名称：</label>
								<input type="text" name="title" value="" class="form-control" placeholder="请填写名称 (必填且唯一)" >
							</div>
							<div class="form-group">
								<label class="control-label" for="group_id"><span class="red">* </span>分组</label>
								<select name="group_id" class="form-control" id="group_id">
									<?php foreach($groupArr as $key=>$title):?>
										<option value="<?= $key?>"><?= $title?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">取消</button>
				<button type="button" id="addFieldButton" class="btn btn-primary btn-confirm">确定</button>
			</div>
		</div>
	</div>
</div>

<script>
	function search(){
		$("#searchForm").submit();
	}

	$(function(){
		//新增页面弹出
		$('.addFieldBox').click(function(){
			$('#addFieldBox h4.modal-title').html('添加敏感词');
			$('input[name=id]').val(0);
			$('input[name=title]').val('');
			$("#group_id option:first").prop("selected", 'selected');
			$("#addFieldBox").modal("show");
		});
		//添加操作
		$('#addFieldButton').click(function (){
			swal({
				title: "操作确认",
				text: "是否确认执行！",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: "确认",
				cancelButtonText : "取消",
				closeOnConfirm: false
			}, function () {
				if (this.confirmButtonText == '确认') {
					//检查数据
					if(!checkData()){
						return false;
					}
					//发送数据
					$.ajax({
						url: $('form[name="ajax_addField"]').attr('action'),
						type: "post",
						data: $('form[name="ajax_addField"]').serialize(),
						dataType: "JSON",
						success: function (ret) {
							if (!ret.error) {
								swal({
									title: "温馨提示",
									text: "操作成功！",
									type: "success",
									closeOnConfirm: false
								}, function () {
									window.location.reload();
								});
							} else {
								swal("温馨提示", ret.msg, "error");
							}
						}
					});
				}
			});
			return false;
		});

		//开启关闭状态操作
		$('.updateStatus').click(function () {
			var $this=this;
			var textStr = '执行当前操作，商家客户该字段将无法查看！';

			swal({
				title: "操作确认",
				text: textStr,
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: "确认",
				cancelButtonText : "取消",
				closeOnConfirm: false
			}, function () {
				if (this.confirmButtonText == '确认') {
					$.ajax({
						url: '/admin/limit-word/delete',
						type: "post",
						data: {
							'id': $($this).parents('tr').attr('data-id'),
							'status': $($this).attr('value')
						},
						dataType: "JSON",
						success: function (ret) {
							if (!ret.error) {
								swal({
									title: "温馨提示",
									text: "操作成功！",
									type: "success",
									closeOnConfirm: false
								}, function () {
									window.location.reload();
								});
							} else {
								swal("温馨提示", ret.msg, "error");
							}
						}
					});
				}
			});
			return false;
		});

		//修改操作
		$('.updateField').click(function(){
			$('#addFieldBox h4.modal-title').html('修改敏感词');
			$('input[name=id]').val($(this).attr('value'));
			$('input[name=title]').val($(this).attr('title'));
			$("#group_id").val($(this).attr('groupId'));
			$("#addFieldBox").modal("show");
		});
	});

	//检查数据
	function checkData(){
		var title = $('input[name="title"]').val();
		if(title == ''){
			swal("温馨提示", '名称不能为空', "error");
			return false;
		}else{
			if(title.length >32 ){
				swal("温馨提示", '名称长度不能超过32个', "error");
				return false;
			}
		}
		return true;
	}

</script>