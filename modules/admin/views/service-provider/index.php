<?php

	use yii\helpers\Html;
	use yii\widgets\LinkPager;

	$this->title = '权限管理';
?>
<?= Html::cssFile('@web/css/dataTable.css') ?>
<?= Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css') ?>
<style>
	.sym-public1 {
		width: 50%;
		display: inline-block;
	}

	.sym-display {
		display: none !important;
	}

	#home span {
		line-height: 2.8rem;
		font-size: 14px;
	}

	.sym-is-setting {
		background: white !important;
		border: none !important;
	}
	#copyTitle {
		display: none;
		border-radius: 5px;
		position: fixed;top: 20px;
		left: 50%;
		transform: translateX(-50%);
		background-color: #FFDDA6;
		padding: 10px 30px;
		z-index: 99999;
		animation: 1s opacity2 0s infinite;
		-webkit-animation: 1s opacity2 0s infinite;
		-moz-animation: 1s opacity2 0s infinite;
	}
    @keyframes opacity2{
        0%{opacity:0}
        30%{opacity:.8;}
        50%{opacity: 1;}
        100%{opacity:1;}
    }
    @-webkit-keyframes opacity2{
        0%{opacity:0}
        30%{opacity:.8;}
        50%{opacity: 1;}
        100%{opacity:1;}
    }
    @-moz-keyframes opacity2{
        0%{opacity:0}
        30%{opacity:.8;}
        50%{opacity: 1;}
        100%{opacity:1;}
    }
    .radio-inline {
        line-height: 20px !important;
    }
    .btn-primary {
        width : 80px;
        background-color: #44b549 !important;
        border-color: #44b549 !important;
        color: #FFFFFF !important;
    }
    .btn-cancel {
        width : 80px;
        background-color: #FFFFFF !important;
        border-color: #E2E2E2 !important;
        color: #333333 !important;
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
				<strong>服务商配置</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>
<div class="wrapper wrapper-content">
	<div class="row">

		<div>

			<!-- Nav tabs -->
			<ul class="nav nav-tabs" role="tablist">
				<li role="presentation" class="active" style="background:none !important;border:none">
					<a href="#home" style="color:black" aria-controls="home" role="tab"
					   data-toggle="tab">企业微信服务商</a>
				</li>
				<li role="presentation" style="background:none !important;border:none">
					<a href="#profile" style="color:black" aria-controls="profile" role="tab"
					   data-toggle="tab">微信开放平台</a>
				</li>
			</ul>
			<div class="ibox-content">
				<div class="form-group">
					<div class="row">
						<!-- Tab panes -->
						<div class="tab-content">
							<!--企业微信服务商-->
							<div role="tabpanel" class="tab-pane active" id="home">
								<?php if ($setting == 1){ ?>
								<div id="saveLoad">
									<form class="form-horizontal" id="form" method="post" enctype="multipart/form-data">
										<!-- Tab panes -->
										<div class="tab-content">
											<?php if ($setting == 1) { ?>
												<div style="float: right;margin-right: 3rem;margin-top: 3rem;">
													<input type="text" hidden name="id"
													       value="<?= $serverConfig['id'] ?>">
													<a
															id="editData"
															onclick="editDataConfig(this,1)"
															type="button" class="btn btn-primary">编辑
													</a>
												</div>
												<br>
												<br>
												<br>
												<br>
											<?php } ?>
											<div class="form-group" style="margin-top: 3rem;">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">企业微信CorpId</label>
												<div class="col-sm-10">
													<span>
														<?= $serverConfig['provider_corpid'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">Secret</label>
												<div class="col-sm-10">
													<span>
														<?= $serverConfig['provider_secret'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">Token</label>
												<div class="col-sm-10">
													<span>
														<?= $serverConfig['token'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">EncodeAesKey</label>
												<div class="col-sm-10">
													<span>
														<?= $serverConfig['encode_aes_key'] ?>
													</span>
												</div>
											</div>
											<div class="form-group" style="margin-top: 3rem;">
												<label for="inputEmail3" class="col-sm-2 control-label">应用名称</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['name'] ?>
													</span>

												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">suiteid为应用的唯一身份标识</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['suite_id'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">应用描述</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['description'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">应用可信域名</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['redirect_1'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
													<br>
													<span>
														<?= $appConfig['redirect_2'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
													<br>
													<span>
														<?= $appConfig['redirect_3'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;"  style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">应用主页url</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['home_url'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">业务设置URL</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['business_url'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">回调地址</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['redirect_url'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">数据回调URL</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['data_redirect'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">指令回调URL</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['instruct_redirect'] ?>
													</span>
													<?php if ($setting == 1) {
														echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
													} ?>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">SuiteSecret</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['suite_secret'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">Token</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['token'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">EncodingAESKey</label>
												<div class="col-sm-10">
													<span>
														<?= $appConfig['encode_aes_key'] ?>
													</span>
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">logo</label>
												<div class="col-sm-10">
													<img src="<?= $appConfig['logo_url'] ?>" id="img"  alt="" width="32"
													     height="32" style='margin-left : 10px;'>
												</div>
											</div>
									</form>

								</div>
							</div>
							<?php } ?>

							<div id="beforeAfter" class="<?php if ($setting == 1) {
								echo 'sym-display';
							} ?>">
								<ul class="nav nav-tabs" role="tablist">
									<li role="presentation" id="serve-config-tab" class="active"
									    style="background:none !important;width: 50%;border: none;">
										<a href="#serve-config" style="color:black;" aria-controls="serve-config"
										   role="tab"
										   id="serve-config-tab1"
										   data-toggle="tab">企业微信服务商配置</a>
									</li>
									<li role="presentation" id="web-app-tab"
									    class="<?php if ($setting != 1) {
										    echo "sym-display";
									    } ?>"
									    style="width: 50%;    <?php if ($setting != 1) {
										    echo "pointer-events:none;";
									    } ?> border: none;background:none !important;">
										<a href="#web-app" id="web-app-tab1" style="color:black"
										   aria-controls="web-app" role="tab"
										   data-toggle="tab">网页应用</a>
									</li>
								</ul>
								<form class="form-horizontal" id="form" method="post" enctype="multipart/form-data">
									<!-- Tab panes -->
									<div class="tab-content">
										<div role="tabpanel" class="tab-pane active" id="serve-config">
											<div class="form-group" style="margin-top: 3rem;">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">企业微信CorpId <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text" class="form-control sym-public1"
															name="provider_corpid"
															value="<?= $serverConfig['provider_corpid'] ?>"
															placeholder="每个服务商同时也是一个企业微信的企业，都有唯一的corpid">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">Secret <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text" class="form-control sym-public1"
															name="provider_secret"
															value="<?= $serverConfig['provider_secret'] ?>"
															placeholder="作为服务商身份的调用凭证，应妥善保管好该密钥，务必不能泄漏">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">Token <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text" class="form-control sym-public1"
															name="token"
															value="<?= $serverConfig['token'] ?>"
															placeholder="Token用于计算签名">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">EncodeAesKey <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text" class="form-control sym-public1"
															name="encode_aes_key"
															value="<?= $serverConfig['encode_aes_key'] ?>"
															placeholder="EncodingAESKey用于消息内容加密">
												</div>
											</div>
											<a onclick="changeTableNext(<?= $setting ?>)"
											        style="float: right;margin-right: 25%;"
											        type="button" class="btn btn-primary">下一步
											</a>
										</div>
										<div role="tabpanel" class="tab-pane" id="web-app">
											<div class="form-group" style="margin-top: 3rem;">
												<label for="inputEmail3" class="col-sm-2 control-label">应用名称 <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="name"
															value="<?= isset($appConfig['name']) ? $appConfig['name'] : '' ?>"
															class="form-control sym-public1"
															placeholder="应用名称">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">suiteid为应用的唯一身份标识
													<span style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="suite_id"
															value="<?= isset($appConfig['suite_id']) ? $appConfig['suite_id'] : '' ?>"
															class="form-control sym-public1"
															placeholder="suiteid为应用的唯一身份标识">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3" class="col-sm-2 control-label">应用描述 <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="description"
															value="<?= isset($appConfig['description']) ? $appConfig['description'] : '' ?>"
															class="form-control sym-public1"
															placeholder="应用描述">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">验证文件</label>
												<div class="col-sm-10">
													<a type="button" class="btn btn-primary"
													        onclick="changeUpdalodClick(1)">上传文件
													</a>
													<input type="file" style="display: none" id="checkText"
													       accept=".txt" onchange="uploadfile(2)">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">SuiteSecret <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="suite_secret"
															value="<?= isset($appConfig['suite_secret']) ? $appConfig['suite_secret'] : '' ?>"
															class="form-control sym-public1"
															placeholder="suite_secret为对应的调用身份密钥">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">Token <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="token"
															value="<?= isset($appConfig['token']) ? $appConfig['token'] : '' ?>"
															class="form-control sym-public1"
															placeholder="Token用于计算签名">
												</div>
											</div>
											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">EncodingAESKey <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input
														<?php if ($setting == 1) {
															echo "disabled style='cursor: pointer'";
														} ?>
															type="text"
															name="encode_aes_key"
															value="<?= isset($appConfig['encode_aes_key']) ? $appConfig['encode_aes_key'] : '' ?>"
															class="form-control sym-public1"
															placeholder="EncodingAESKey用于消息内容加密">
												</div>
											</div>

											<div class="form-group">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">LOG设置 <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<label class="radio-inline">
														<input type="radio" onclick="chooseUploadAction(1)"
														       name="chooseAction" value="1" checked
														       id="inlineRadio1">
														复制链接
													</label>
													<label class="radio-inline">
														<input type="radio" onclick="chooseUploadAction(2)"
														       name="chooseAction" <?php if (isset($appConfig['choose']) && $appConfig['choose'] == 2)
															echo 'checked' ?> value="2" id="inlineRadio2">
														本地上传
													</label>
												</div>
											</div>
											<div class="form-group <?php if (isset($appConfig['choose']) && $appConfig['choose'] == 2)
												echo 'sym-display' ?>" id="urlUP">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">log复制链接 <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<input type="text" name="logo_url"
													       class="form-control sym-public1"
													       value="<?= isset($appConfig['logo_url']) ? $appConfig['logo_url'] : '' ?>"
													       placeholder="log复制链接">
												</div>
											</div>
											<div class="form-group <?php if (isset($appConfig['choose']) && $appConfig['choose'] == 1)
												echo 'sym-display' ?><?php if ($setting == 0)
												echo 'sym-display' ?>" id="localUp">
												<label for="inputEmail3"
												       class="col-sm-2 control-label">本地上传log <span
															style="color: red;line-height: ">*</span></label>
												<div class="col-sm-10">
													<a style="" type="button" class="btn btn-primary"
													        onclick="changeUpdalodClick(2)">上传文件
													</a>
													<input type="file" style="display: none"
													       id="exampleInputFile"
													       accept="image/*"
													       onchange="LogUpload(this.value)">
													<img src="<?php if (isset($appConfig['choose']) && $appConfig['choose'] == 2 && $setting == 1)
														echo $appConfig['logo_url']; ?>" id="img" class="sym-display" alt="" width="32" height="32" style='margin-left : 10px;'>
												</div>
											</div>
											<div style="float: right;margin-right: 25%;">
												<?php if ($setting == 1) { ?>
													<input type="text" name="id" hidden
													       value="<?= $serverConfig['id'] ?>">
												<?php } ?>
												<a onclick="changeTableLastStep()"
												        type="button" class="btn btn-primary">上一步
												</a>
												<a
														onclick="addDataConfig(1)"
														type="button" class="btn btn-primary">提交
												</a>
												<?php if ($setting == 1) { ?>
												<a
														onclick="loaseEdit()"
														type="button" class="btn btn-cancel">取消
												</a>
												<?php } ?>
											</div>
										</div>
								</form>
							</div>
						</div>
					</div>
					<!--微信开放平台-->
					<div role="tabpanel" class="tab-pane" id="profile">
						<form class="form-horizontal" id="form-p" method="post" enctype="multipart/form-data">
							<div class="form-group" style="margin-top: 3rem;">
								<label for="inputEmail3" class="col-sm-2 control-label">appid</label>
								<div class="col-sm-10">
									<input
										<?php if ($w_setting == 1) {
											echo "disabled style='cursor: pointer'";
										} ?>
											type="text"
											name="appid"
											value="<?= $wConfig['appid'] ?>"
											class="form-control sym-public1"
											placeholder="第三方开放平台应用APPID">
								</div>
							</div>
							<div class="form-group" style="margin-top: 3rem;">
								<label for="inputEmail3" class="col-sm-2 control-label">appSecret</label>
								<div class="col-sm-10">
									<input
										<?php if ($w_setting == 1) {
											echo "disabled style='cursor: pointer'";
										} ?>
											type="text"
											name="appSecret"
											value="<?= $wConfig['appSecret'] ?>"
											class="form-control sym-public1"
											placeholder="第三方开放平台应用APPSECRET">
								</div>
							</div>
							<div class="form-group" style="margin-top: 3rem;">
								<label for="inputEmail3" class="col-sm-2 control-label">Token</label>
								<div class="col-sm-10">
									<input
										<?php if ($w_setting == 1) {
											echo "disabled style='cursor: pointer'";
										} ?>
											type="text"
											name="token"
											value="<?= $wConfig['token'] ?>"
											class="form-control sym-public1"
											placeholder="第三方开放平台应用对接TOKEN">
								</div>
							</div>
							<div class="form-group" style="margin-top: 3rem;">
								<label for="inputEmail3" class="col-sm-2 control-label">EncodeAesKey</label>
								<div class="col-sm-10">
									<input
										<?php if ($w_setting == 1) {
											echo "disabled style='cursor: pointer'";
										} ?>
											type="text"
											name="encode_aes_key"
											value="<?= $wConfig['encode_aes_key'] ?>"
											class="form-control sym-public1"
											placeholder="第三方开放平台应用对接ENCODE_AES_KEY">
								</div>
							</div>
							<?php if ($w_setting == 1) { ?>
								<div class="form-group" style="margin-top: 3rem;" id="url1">
									<label for="inputEmail3" class="col-sm-2 control-label">授权事件接收URL</label>
									<div class="col-sm-10">
										<input
											<?php if ($w_setting == 1) {
												echo "disabled style='cursor: pointer'";
											} ?>
												type="text"
												value="<?= $wConfig['url1'] ?>"
												class="form-control sym-public1"
												placeholder="授权事件接收URL">
											<?php if ($w_setting == 1) {
                                                echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
                                            } ?>
									</div>
								</div>
								<div class="form-group" style="margin-top: 3rem;" id="url2">
									<label for="inputEmail3" class="col-sm-2 control-label">消息与事件接收URL</label>
									<div class="col-sm-10">
										<input
											<?php if ($w_setting == 1) {
												echo "disabled style='cursor: pointer'";
											} ?>
												type="text"
												value="<?= $wConfig['url2'] ?>"
												class="form-control sym-public1"
												placeholder="消息与事件接收URL">
										<?php if ($w_setting == 1) {
	                                        echo '<a href="javascript:0;" style="display: inline-block; margin-left: 60px; color: #1890FF;" onclick="copyValue(this)">点击复制</a>';
	                                    } ?>
									</div>
								</div>
							<?php } ?>
							<div class="form-group <?php if ($w_setting == 1) { echo 'sym-display'?><?php } ?>" style="margin-top: 3rem;" id="wx_upload">
								<label for="inputEmail3"
								       class="col-sm-2 control-label">验证文件</label>
								<div class="col-sm-10">
									<a type="button" class="btn btn-primary"
									        onclick="changeUpdalodClick(3)">上传文件
									</a>
									<input type="file" style="display: none" id="checkText1"
									       accept=".txt" onchange="uploadfile(1)">
								</div>
							</div>
							<div style="float: right;margin-right: 25%;">
								<?php if ($w_setting == 1) { ?>
									<input type="text" hidden name="wid"
									       value="<?= $wConfig['id'] ?>">
									<a
											id="editData1"
											onclick="editDataConfig(this,2)"
											type="button" class="btn btn-primary">编辑
									</a>
									<a style='display: none'
						                    onclick="loaseEditWX()"
						                    type="button" id='dataConfigCancel' class="btn btn-cancel">取消
						            </a>
								<?php } else { ?>
									<a
											onclick="addDataConfig(3)"
											type="button" class="btn btn-primary">提交
									</a>



								<?php } ?>
							</div>


						</form>


					</div>
				</div>

			</div>
		</div>
	</div>
	<div id='copyTitle'>
		<span>复制成功</span>
	</div>
</div>

</div>
</div>

<script>

	$(
		function changeInputCss () {
			<?php if ($setting == 1) {
			echo '$("#home").find("input").addClass("sym-is-setting");';
		} ?>
			<?php if ($w_setting == 1) {
			echo '$("#profile").find("input").addClass("sym-is-setting");';
		} ?>
		}
	)
	var saveload;
	var saveload_wx;

	function loaseEditWX () {
		$("#profile").children().remove()
		$("#profile").append(saveload_wx)
		$("#profile").find("input").addClass("sym-is-setting");
	}

	function loaseEdit () {
		$("#beforeAfter").addClass("sym-display");
		$("#home").append(saveload)
		$("#home").find("input").addClass('sym-is-setting')

	}

	function copyValue (e) {
		var inputTemp = $(e).prev()
		console.log(inputTemp.val())
		var input = document.createElement('input')
		document.body.appendChild(input)
		input.setAttribute('value', inputTemp.text() || inputTemp.val())
		input.select()
		if (document.execCommand('copy')) {
			document.execCommand('copy')
		}
		document.body.removeChild(input)
		document.getElementById('copyTitle').style.display = 'block'
		setTimeout(function(){
			document.getElementById('copyTitle').style.display = 'none'
		}, 1000)
	}

	function editDataConfig (e, local = 1) {
		if (local == 2) {
			$("#profile input").removeClass("sym-is-setting")
			saveload_wx = $("#profile").children().clone(true);
			$("#profile").find("input").attr("disabled", false)
			$("#wx_upload").removeClass("sym-display")
			$("#editData1").text("提交")
			$("#dataConfigCancel")[0].style.display = 'inline-block'
			$("#url1").remove();
			$("#url2").remove();
			$("#editData1").attr("onclick", "addDataConfig(3)")
			return;
		}
		$("#home input").removeClass("sym-is-setting")
		saveload = $("#saveLoad").clone(true);
		$("#saveLoad").remove();
		$("#beforeAfter").removeClass("sym-display")
		$("#home").find("input").attr("disabled", false)
		$("#editData").text("提交")
		$("#log-div").children().remove();
		var str = `<div class="form-group">
		              <label for="inputEmail3" class="col-sm-2 control-label">LOG设置</label>
		              <div class="col-sm-10">
		               <label class="radio-inline">
		                <input type="radio" onclick="chooseUploadAction(1)"
		                       name="chooseAction" value="1" checked id="inlineRadio1">
		                复制链接
		               </label>
		               <label class="radio-inline">
		                <input type="radio" onclick="chooseUploadAction(2)"
		                       name="chooseAction" value="2" <?php if (isset($appConfig['choose']) && $appConfig['choose'] == 2)
			echo "checked";?> id="inlineRadio2">
		                本地上传
		               </label>
		              </div>
		             </div>
		             <div class="form-group " id="urlUP">
		              <label for="inputEmail3" class="col-sm-2 control-label">log复制链接</label>
		              <div class="col-sm-10">
		               <input type="text" name="logo_url" value="<?= isset($appConfig['logo_url']) ? $appConfig['logo_url'] : '' ?>" class="form-control sym-public1"
		                      placeholder="log复制链接">
		               <p class="help-block">本地上传可以不填</p>
		              </div>
		             </div>
		             <div class="form-group sym-display" id="localUp">
		              <label for="inputEmail3"
		                     class="col-sm-2 control-label">本地上传log</label>
		              <div class="col-sm-10">
		               <button style="" type="button" class="btn btn-primary"
		                       onclick="changeUpdalodClick(2)">上传文件
		               </button>
		               <input type="file" style="display: none" id="exampleInputFile"
		                      accept="image/*"
		                      onchange="LogUpload(this.value)">
		               <img src="<?= isset($appConfig['logo_url']) ? $appConfig['logo_url'] : '' ?>" id="img" class="sym-display" alt="" width="32" height="32" style='margin-left : 10px;'>
		               <p class="help-block">复制链接可以不填</p>
		              </div>
		             </div>`
		$("#log-div").append(str);
		$("#editData").removeAttr("onclick")
		$("#editData").attr("onclick", "addDataConfig(2)")
	}

	function chooseUploadAction (action) {
		console.log(action)
		if (action == 2) {
			$("#localUp").removeClass("sym-display")
			$("#urlUP").addClass("sym-display")
			$("#urlUP").find("input").removeAttr("name")
		} else {
			$("#localUp").addClass("sym-display")
			$("#urlUP").removeClass("sym-display")
			$("#urlUP").find("input").attr("name")
		}
	}

	function uploadfile (locl) {
		if (locl == 1) {
			$("#checkText1").prev().text($("#checkText1")[0].files[0].name)
		} else {
			console.log($("#checkText")[0].files[0])
			$("#checkText").prev().text($("#checkText")[0].files[0].name)
		}
	}

	function changeUpdalodClick (changeLocal) {
		if (changeLocal == 1) {
			$("#checkText").click()
		} else if (changeLocal == 3) {
			$("#checkText1").click()
		} else {
			$("#exampleInputFile").click()
		}
	}

	function changeTableNext (setting) {
		var data = $("#form").serializeArray();
		if (setting == 0) {
			for (var i = 0; i < 4; i++) {
				if (data[i].value == null || data[i].value == undefined || data[i].value == '') {
					swal("请填写完整数据!", "请填写完整数据！", "error");
					return;
				}
			}
		}
		$("#serve-config-tab").removeClass("active")
		$("#serve-config-tab1").removeClass("active")
		$("#web-app-tab").addClass("active")
		$("#web-app-tab1").addClass("active")
		$("#serve-config").removeClass("active")
		$("#web-app").addClass("active")
		$("#web-app-tab").attr("aria-expanded", true)
		$("#serve-config-tab").attr("aria-expanded", false)
		$("#serve-config-tab").attr("aria-expanded", false)
		$("#web-app-tab").css("pointer-events", null)
		$("#web-app-tab").removeClass("sym-display")
	}

	function changeTableLastStep () {
		$("#serve-config-tab").addClass("active")
		$("#serve-config-tab1").addClass("active")
		$("#web-app-tab").removeClass("active")
		$("#web-app-tab1").removeClass("active")
		$("#serve-config").addClass("active")
		$("#web-app").removeClass("active")
		$("#web-app-tab").attr("aria-expanded", false)
		$("#serve-config-tab").attr("aria-expanded", true)
	}

	function LogUpload (e) {
		console.log($('#exampleInputFile')[0].files[0]);
		var windowURL = window.URL || window.webkitURL;
		var dataURL = windowURL.createObjectURL($('#exampleInputFile')[0].files[0]);
		$("#img").removeClass("sym-display")
		$('#img').attr('src', dataURL)
	}

	function addDataConfig (local) {
		var formData = new FormData();
		if (local == 3) {
			var data = $("#form-p").serializeArray();
			var url = '/admin/service-provider/add-wechat-config'
			if ($("#checkText1")[0].files[0] != undefined) {
				formData.append("checkText", $("#checkText1")[0].files[0]);
			}
			if ($("#checkText1")[0].files[0] == undefined) {
				swal("数据填写不完整!", "操作失败！", "error");
				return;
			}
		} else {
			var data = $("#form").serializeArray();
			var url = '/admin/service-provider/add-config'
			if ($("#exampleInputFile")[0].files[0] != undefined) {
				formData.append("logFileInfo", $("#exampleInputFile")[0].files[0]);
			}
			if ($("#checkText")[0].files[0] != undefined && local == 1) {
				formData.append("checkText", $("#checkText")[0].files[0]);
			}
			if ($("#checkText")[0].files[0] = undefined && local == 1) {
				swal("数据填写不完整!", "操作失败！", "error");
				return;
			}
		}
		for (var i = 0; i < data.length; i++) {
			if (data[i].value == null || data[i].value == undefined || data[i].value == '') {
				console.log(data[i].name)
				swal("数据填写不完整!", "操作失败！", "error");
				return;
			}
			formData.append(data[i].name, data[i].value);
		}

		$.ajax({
			url        : url,
			type       : 'POST',
			cache      : false,
			data       : formData,
			processData: false,
			contentType: false,
			success    : function (result) {
				result = JSON.parse(result);
				if (result.error == 1) {
					swal(result.msg, result.msg, "error");
				} else {
					swal("完成", "操作已完完成", "success");
					setTimeout(function () {
						window.location.reload()
					}, 1000)
				}
			}
		});
	}

</script>
