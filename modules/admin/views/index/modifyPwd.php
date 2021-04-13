<?php
	use yii\helpers\Html;
	$this->title = '修改密码';
	?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>

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
				<strong>修改密码</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2">

	</div>
</div>
<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-6">
			<div class="ibox float-e-margins">
				<div class="ibox-title">
					<h5>密码修改</h5>
				</div>
				<div class="ibox-content">
					<form class="form-horizontal" id="pwdform" action="" method="POST" enctype="multipart/form-data">
						<p>密码很重要，请记住。每个手机号每天最多只可以收到五条验证码信息。</p>
						<div class="form-group"><label class="col-lg-2 control-label">手机号</label>
							<div class="col-sm-9 input-group"><input type="text" class="form-control" value="验证码将发送到【<?php echo $phone; ?>】上" readonly/>
							</div>
						</div>

						<div class="form-group"><label class="col-lg-2 control-label">旧密码</label>
							<div class="col-sm-9 input-group"><input type="password" class="form-control" placeholder="旧密码" name="oldpwd"> <span class="help-block m-b-none"></span>
							</div>
						</div>
						<div class="form-group"><label class="col-lg-2 control-label">新密码</label>
							<div class="col-sm-9 input-group"><input type="password" class="form-control" placeholder="新密码" name="newpwd"></div>
						</div>
						<div class="form-group"><label class="col-lg-2 control-label">新密码</label>
							<div class="col-sm-9 input-group"><input type="password" class="form-control" placeholder="再输入一次新密码" name="new2pwd"></div>
						</div>

						<div class="form-group">
							<label class="col-lg-2 control-label">验证码</label>
							<div class="col-sm-9 input-group">
								<input type="text" class="form-control" placeholder="输入您获取的短信验证码" name="code">
								<input type="hidden" value="-1" id="codetime">
								<a class="input-group-addon">获取验证码</a>
							</div>
						</div>

						<div class="form-group">
							<div class="col-lg-offset-2 col-lg-10">
								<button type="button" class="btn btn-sm btn-primary" onclick="modifyPwd()"> 修 改 </button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<script>
	var flag = false;
	$(document).ready(function(){
		$('.input-group-addon').click(function(){
			if (flag) return false;
			flag = true;
			$.ajax({
				url:'/admin/index/get-code',
				type:"post",
				dataType:"JSON",
				success:function(ret){
					if(!ret.error){
						$('#codetime').val(60);
						count_down();
					} else {
						flag = false;
						swal({
							title: "获取短信验证码",
							text: ret.info,
							type: "error"
						});
					}
				}
			});
		});
	});

	function count_down(){
		var down = setInterval(function(){
			var num = $('#codetime').val();
			if(num > 0){
				$('#codetime').val(num - 1);
				$('.input-group-addon').html('(' + parseInt(num - 1) + ')秒后重新获取');
			}else{
				flag = false;
				$('#codetime').val(-1);
				$('.input-group-addon').html('获取验证码');
				clearInterval(down);
			}
		},1000);
	}

	//修改密码提交
	function modifyPwd () {
		var oldpwd=$.trim($('input[name="oldpwd"]').val());
		var newpwd=$.trim($('input[name="newpwd"]').val());
		var new2pwd=$.trim($('input[name="new2pwd"]').val());

		if(!oldpwd){
			swal("温馨提醒", "您没有输入旧密码", "error");
			$('input[name="oldpwd"]').focus();
			return false;
		}
		if(!newpwd){
			swal("温馨提醒", "您没有输入新密码", "error");
			$('input[name="newpwd"]').focus();
			return false;
		}
		if(!new2pwd){
			swal("温馨提醒", "您没有输入新密码！", "error");
			$('input[name="new2pwd"]').focus();
			return false;
		}
		if(newpwd != new2pwd){
			swal("温馨提醒", "两次输入的新密码不一致", "error");
			$('input[name="new2pwd"]').focus();
			return false;
		}
		if(newpwd.length < 6 || new2pwd.length < 6){
			swal("温馨提醒", "新密码长度至少为6位", "error");
			$('input[name="newpwd"]').focus();
			return false;
		}
		var code = $.trim($('input[name="code"]').val());
		if(!code){
			swal("温馨提醒", "短信验证码不能为空", "error");
			$('input[name="code"]').focus();
			return false;
		}

		var pwdData = $('#pwdform').serialize();
		$.post('/admin/index/modify-pwd-post', pwdData, function (rets) {
			rets.error = parseInt(rets.error);
			if (!rets.error) {
				swal({
					title: "温馨提示",
					text:  "修改成功，请重新登录！",
					type:  "warning",
					confirmButtonText: "确定",
				}, function () {
					window.location = '/admin/index/logout';
				});
			} else {
				swal("温馨提示", rets.msg, "error");
			}
		}, 'json');
	}
</script>