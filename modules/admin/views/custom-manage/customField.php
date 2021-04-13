<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '客户高级属性';
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
				<a>客户属性</a>
			</li>
			<li class="active">
				<strong>客户高级属性</strong>
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
							<form id="searchForm" method="get" action="/admin/custom-manage/custom-field">
								<div class="col-lg-2">
									<input class="input form-control" name="key" value="<?=$key?>" placeholder="字段名">
								</div>
								<a class="btn btn-primary" style="width:70px;" href="javascript:search()">查 询</a>
								<a class="btn btn-primary addFieldBox" style="margin-left:10px;"><i class="fa fa-plus"></i> 插入字段</a>
							</form>
						</div>
					</div>

					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th width="10%">字段名</th>
							<th width="15%">字段标题</th>
							<th width="10%">类型</th>
							<th width="">选项内容</th>
							<th width="15%">操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php if (!empty($fieldList)){ ?>
						<?php foreach($fieldList as $field){ ?>
							<tr data-id="<?=$field['id'];?>">
								<td class=""><?=$field['key'];?></td>
								<td class=""><?=$field['title'];?></td>
								<td class=""><?=$field['typeName'];?></td>
								<td>
									<?php if (!empty($optionVal[$field['id']])){
										echo implode(',', $optionVal[$field['id']]);
									} ?>
								</td>
								<td>
									<?php if ($field['status'] == 1){ ?>
										<span class="btn btn-primary updateField"  value="<?=$field['id'];?>">修改</span>
										<span class="btn btn-danger updateStatus" value="0">禁用</span>
										<span class="btn btn-danger updateStatus" value="2">删除</span>
									<?php }else{ ?>
										<span class="btn btn-primary updateStatus" value="1">开启</span>
										<span class="btn btn-danger updateStatus" value="2">删除</span>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
						<?php }else{ ?>
							<tr class="widget-list-item"><td colspan="8">暂无字段信息</td></tr>
						<?php } ?>
						</tbody>
					</table>
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
				<h4 class="modal-title">插入字段</h4>
			</div>
			<div class="modal-body">
				<form action="/admin/custom-manage/add-field" method="post" name="ajax_addField"  enctype="multipart/form-data">
					<input type="hidden" name="id" value="">
					<div class="row">
						<div id="addinputBox" class="addinputBox">
							<div class="form-group">
								<label>字段名：</label>
								<input type="text" name="namekey" value="" class="form-control" placeholder="请填写字段名 (必填且唯一)" >
							</div>
							<div class="form-group">
								<label>名称：</label>
								<input type="text" name="title" value="" class="form-control" placeholder="请填写字段名称 (必填且唯一)" >
							</div>

							<div class="form-group">
								<label>类型：</label>
								<select class="form-control" name="type">
									<option value="1">文本类型</option>
									<option value="2">单选类型</option>
									<option value="3">多选类型</option>
									<option value="4">日期类型</option>
									<option value="5">手机号类型</option>
									<option value="6">邮箱类型</option>
									<option value="7">区域类型</option>
									<option value="8">图片类型</option>
								</select>
							</div>
							<div class="form-group" id="default_val" style="display:none;">
								<label>默认值：</label>
								<textarea name="default_val" class="form-control"  cols="45" rows="5" style="width:100%; height:120px;" placeholder="如果定义数据类型为select、radio、checkbox时，此处填写被选择的项目(用换行分开)。"></textarea>
							</div>
							<div class="form-group" style="display:none;" id="default_val_extend">
								<label>默认值增加：</label>
								<textarea name="default_val_extend" class="form-control"  cols="45" rows="5" style="width:100%; height:120px;" placeholder="如果定义数据类型为select、radio、checkbox时，此处填写被选择的项目(用换行分开)。"></textarea>
							</div>
							<div class="form-group">
								<label>状态：</label>
								<select class="form-control" name="status">
									<option value="1">开启</option>
									<option value="0">禁用</option>
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
			$('#addFieldBox h4.modal-title').html('插入字段');
			$('input[name=id]').val(0);
			$('input[name=namekey]').val('');
			$('input[name=namekey]').attr('disabled',false);
			$('input[name=title]').val('');
			$('input[name=title]').attr('disabled',false);
			$('select[name=type]').prop('selectedIndex', 0);
			$('select[name=type]').attr('disabled',false);
			$('textarea[name=default_val]').val('');
			$('textarea[name=default_val]').attr('disabled',false);
			$('textarea[name=default_val_extend]').val('');
			$('#default_val').hide();
			$('#default_val_extend').hide();
			$('select[name=status]').prop('selectedIndex', 0);
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
			var textStr = '';
			if($($this).attr('value') == 1){
				textStr = '执行当前操作，商家客户该字段将可以设置与查看！';
			}else{
				textStr = '执行当前操作，商家客户该字段将无法设置与查看！';
			}
			swal({
				title: "操作确认",
				text: textStr,
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: "ok",
				closeOnConfirm: false
			}, function () {
				if (this.confirmButtonText == 'ok') {
					$.ajax({
						url: '/admin/custom-manage/update-field',
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
			$('#addFieldBox h4.modal-title').html('修改字段');
			$.ajax({
				url: '/admin/custom-manage/get-field',
				type: "post",
				data: {id:$(this).attr('value')},
				dataType: "JSON",
				success: function (ret) {
					if (!ret.error) {
						var data = ret.msg;
						$('input[name=id]').val(data.id);
						$('input[name=namekey]').val(data.key);
						$('input[name=namekey]').attr('disabled',true);
						$('input[name=title]').val(data.title);
						$('input[name=title]').attr('disabled',true);

						if(data.type == 2 || data.type == 3){
							$('textarea[name=default_val]').val(data.match);
							$('#default_val').show();
							$('textarea[name=default_val]').attr('disabled',true);
							$('#default_val_extend').show();
						}else{
							$('textarea[name=default_val]').val('');
							$('#default_val').hide();
							$('textarea[name=default_val_extend]').val('');
							$('#default_val_extend').hide();
						}

						$('select[name=type]').val(data.type);
						$('select[name=type]').attr('disabled',true);
						$('select[name=status]').val(data.status);
						$("#addFieldBox").modal("show");
					} else {
						swal("温馨提示", ret.msg, "error");
						$('#addFieldBox ._close').click();
					}
				}
			});
		});

		//类型选择
		$('select[name=type]').change(function(){
			if($(this).val() == 2 || $(this).val() == 3){
				$('textarea[name=default_val]').val('');
				$('#default_val').show();
			}else{
				$('textarea[name=default_val]').val('');
				$('#default_val').hide();
			}
		});
	});

	//检查数据
	function checkData(){
		var key = $('input[name=namekey]').val();
		if(key == ''){
			swal("温馨提示", '字段名不能为空', "error");
			return false;
		}else{
			var reg = /^[A-Za-z_]+$/;
			if( !reg.test(key)){
				swal("温馨提示", '字段名只能有字母组成', "error");
				return false;
			}
			if(key.length >15 ){
				swal("温馨提示", '字段名长度不能超过15个', "error");
				return false;
			}
		}
		var title = $('input[name=title]').val();
		if(title == ''){
			swal("温馨提示", '名称不能为空', "error");
			return false;
		}else{
			if(title.length >15 ){
				swal("温馨提示", '名称长度不能超过15个', "error");
				return false;
			}
		}

		var type = $('select[name=type] option:selected').val();
		if(type == 2 || type == 3){
			var default_val = $('textarea[name=default_val]').val();
			if(default_val == ''){
				swal("温馨提示", '默认值不能为空', "error");
				return false;
			}
		}
		return true;
	}

</script>