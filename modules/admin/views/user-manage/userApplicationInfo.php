<meta content="always" name="referrer"/>
<?php
	use yii\helpers\Html;
	use yii\widgets\LinkPager;
	$this->title = '客户资料';
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
				<a>客户管理</a>
			</li>
			<li class="active">
				<strong>客户资料</strong>
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
						<li><h2 class="realtime-title">客户资料<span></span></span></h2></li>
					</ul>
				</div>

				<div class="ibox-content">
					<div class="app__content js-app-main page-cashier">
						<div>
							<div class="js-real-time-region realtime-list-box loading">
								<div class="widget-list">
									<div style="position: relative;"
									     class="js-list-filter-region clearfix ui-box">
										<div class="widget-list-filter"></div>
									</div>
									<div class="ui-box">
										<div class="row">
											<div class="col-lg-8">
												<div class="ibox ">
													<div class="ibox-title">
														<h5>审核状态</h5>
													</div>
													<div class="ibox-content" style="min-height:auto;">
														<div class="form-group">
															<label class="col-lg-3 control-label">状态</label>
															<div>
																<?php if ($userInfo['status'] == 1){ ?>
																	已提交待审核
																<?php }elseif ($userInfo['status'] == 2){ ?>
																	通过审核
																<?php }elseif ($userInfo['status'] == 3){ ?>
																	审核失败<?= (!empty($userInfo['remark']))?'（审核失败原因:' . $userInfo['remark'] . '）':'';?>
																<?php } ?>

															</div>
														</div>
													</div>
												</div>
												<div class="ibox ">
													<div class="ibox-title">
														<h5>商户信息</h5>
													</div>
													<div class="ibox-content" style="min-height:auto;">

														<div class="form-group">
															<label class="col-lg-4 control-label">公司主体名称</label>
															<div><?= \app\util\SUtils::hideString($userInfo['merchant'], 1, 8);?></div>
														</div>
														<div class="form-group">
															<label class="col-lg-4 control-label">营业执照号</label>
															<div><?= $userInfo['license'];?></div>
														</div>
														<div class="form-group">
															<label class="col-lg-4 control-label">营业执照照片</label>
															<div>
																<?php if (!empty($userInfo['license_cp'])){ ?>
																	<a href="<?= $userInfo['license_cp'];?>" title="营业执照照片" data-gallery=""><img width="80" class="popupimg" src="<?= $userInfo['license_cp'];?>" /></a>
																<?php }else{ ?>
																	暂无
																<?php } ?>
															</div>
														</div>

														<!--<div class="form-group">
															<label
																	class="col-lg-4 control-label">组织机构代码照片</label>
															<div>
																<?php /*if (!empty($userInfo['organization_cp'])){ */?>
																	<a href="<?/*= $userInfo['organization_cp'];*/?>" title="组织机构代码照片" data-gallery=""><img width="80" class="popupimg" src="<?/*= $userInfo['organization_cp'];*/?>" /></a>
																<?php /*}else{ */?>
																	暂无
																<?php /*} */?>
															</div>
														</div>-->

														<div class="form-group">
															<label class="col-lg-4 control-label">法人姓名</label>
															<div><?= \app\util\SUtils::hideString($userInfo['possessor'], 1, 1);?></div>
														</div>

														<div class="form-group">
															<label class="col-lg-4 control-label">法人手机号</label>
															<div><?= \app\util\SUtils::hideString($userInfo['id_number']);?></div>
														</div>

														<!--<div class="form-group">
															<label class="col-lg-4 control-label">法人身份证号</label>
															<div><?/*= $userInfo['id_number'];*/?></div>
														</div>

														<div class="form-group">
															<label class="col-lg-4 control-label">证件所属人身份</label>

															<div class="radio radio-success radio-inline">
																<input type="radio" id="use_custom_code" value="2" name="use_custom_code" <?/*= ($userInfo['possessor_type'] == '法人')?'checked':'disabled';*/?>>
																<label for="use_custom_code"> 法人 &nbsp;&nbsp;</label>
															</div>
															<div class="radio radio-success radio-inline">
																<input type="radio" id="is_phone_card_number" value="1" name="use_custom_code" <?/*= ($userInfo['possessor_type'] != '法人')?'checked':'disabled';*/?>>
																<label for="is_phone_card_number"> 代理人 &nbsp;&nbsp;</label>
															</div>
														</div>

														<div class="form-group">
															<label class="col-lg-4 control-label">身份证正面照片</label>
															<div>
																<?php /*if (!empty($userInfo['id_cp_a'])){ */?>
																	<a href="<?/*= $userInfo['id_cp_a'];*/?>" title="身份证正面照片" data-gallery=""><img width="80" class="popupimg" src="<?/*= $userInfo['id_cp_a'];*/?>" /></a>
																<?php /*}else{ */?>
																	暂无
																<?php /*} */?>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-4 control-label">身份证反面照片</label>
															<div>
																<?php /*if (!empty($userInfo['id_cp_b'])){ */?>
																	<a href="<?/*= $userInfo['id_cp_b'];*/?>" title="身份证反面照片" data-gallery=""><img width="80" class="popupimg" src="<?/*= $userInfo['id_cp_b'];*/?>" /></a>
																<?php /*}else{ */?>
																	暂无
																<?php /*} */?>
															</div>
														</div>

														<div class="form-group">
															<label class="col-lg-4 control-label">手持身份证照片</label>
															<div>
																<?php /*if (!empty($userInfo['id_cp_c'])){ */?>
																	<a href="<?/*= $userInfo['id_cp_c'];*/?>" title="手持身份证照片" data-gallery=""><img width="80" class="popupimg" src="<?/*= $userInfo['id_cp_c'];*/?>" /></a>
																<?php /*}else{ */?>
																	暂无
																<?php /*} */?>
															</div>
														</div>-->
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div id="blueimp-gallery" class="blueimp-gallery blueimp-gallery-controls"
						     style="display: none;">
							<div class="slides" style="width: 81288px;"></div>
							<h3 class="title">查看图片</h3>
							<a class="prev">‹</a>
							<a class="next">›</a>
							<a class="close">×</a>
							<a class="play-pause"></a>
							<ol class="indicator"></ol>
						</div>

			</div>
		</div>
	</div>
</div>

<script>

</script>