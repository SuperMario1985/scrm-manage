<?php
	use yii\helpers\Html;
	$this->title = '套餐管理';
	?>
<?=Html::cssFile('@web/css/dataTable.css')?>
<?=Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css')?>
<style>

    .num_input{
        width: 50%;
        display:inline-block;
        margin-left: 20px;
    }

    /*switch开关*/
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0px;
        right: 0;
        bottom: 0px;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: -1px;
        bottom: -1px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
    }

    input:checked + .slider {
        background-color: #2196F3;
    }

    input:focus + .slider {
        box-shadow: 0 0 1px #2196F3;
    }

    input:checked + .slider:before {
        -webkit-transform: translateX(26px);
        -ms-transform: translateX(26px);
        transform: translateX(26px);
    }

    /* Rounded sliders */
    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
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
				<strong>套餐列表</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2">

	</div>
</div>
<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-12">
			<div id="packageListBox" class="ibox float-e-margins">
				<div class="ibox-title clearfix">
					<ul class="nav">
						<li>
							<button class="btn btn-primary addPackage">
								<i class="fa fa-plus"></i>添加套餐
							</button>
							<button class="btn btn-primary packageSet">
								高级设置
							</button>
						</li>
					</ul>
				</div>
				<div class="ibox-content">
					<table class="ui-table ui-table-list default no-paging footable-loaded footable" style="font-size: 13px;">
						<thead class="js-list-header-region tableFloatingHeaderOriginal">
						<tr class="widget-list-header">
							<th>套餐ID</th>
							<th>套餐名称</th>
							<th>套餐价格</th>
							<th>代理商是否可用</th>
							<th>套餐等级排序</th>
							<!--<th>原价</th>
							<th>现价</th>
							<th>消息配额</th>
							<th>子账户数量</th>
							<th>企业微信数量</th>
							<th>公众号数量</th>-->
							<th>操作</th>
						</tr>
						</thead>
						<tbody id="packageListBody" class="js-list-body-region">
						<?php foreach($packageList as $package):?>
							<tr class="widget-list-item package-info-<?php echo $package['id']; ?>">
								<td class="package-id"><?php echo $package['id']; ?></td>
								<td class="package-name"><?php echo $package['name']; ?></td>
								<td class="package-price1"><?php echo $package['packagePrice']; ?></td>
								<td class="package-agent"><?php echo $package['is_agent'] == 1 ? '是' : '否'; ?></td>
								<td class="package-sort"><?php echo $package['sort']; ?></td>
								<!--<td class="package-price1"><?php echo $package['old_price']; ?></td>
								<td class="package-price2"><?php echo $package['price']; ?></td>
								<td class="package-messageNum"><?php /*echo $package['message_num']; */?></td>
								<td class="package-subAccountNum"><?php /*echo $package['sub_account_num']; */?></td>
								<td class="package-wechatNum"><?php /*echo $package['wechat_num']; */?></td>
								<td class="package-accountNum"><?php /*echo $package['account_num']; */?></td>-->
								<td>
									<a href="javascript:void(0);" class="btn btn-primary editPackage" data-trial="<?php echo $package['is_trial']; ?>">编辑</a>
                                    <input type="hidden" class="market_config_is_open" value="<?php echo $package['market_config_is_open']?>">
                                    <input type="hidden" class="fission_num" value="<?php echo $package['fission_num']?>">
                                    <input type="hidden" class="lottery_draw_num" value="<?php echo $package['lottery_draw_num']?>">
                                    <input type="hidden" class="red_envelopes_num" value="<?php echo $package['red_envelopes_num']?>">
                                    <input type="hidden" class="tech_img_show" value="<?php echo $package['tech_img_show']?>">
									<?php if(empty($package['is_trial'])):?>
									<a href="javascript:void(0);" class="btn btn-danger delPackage">删除</a>
									<?php endif;?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- 添加套餐 -->
<div class="modal fade inmodal" tabindex="-1" id="packageModel" role="dialog" aria-hidden="true">
	<div class="modal-dialog" style="width: 750px;">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="packageModelTitle" class="modal-title">套餐详细</h4>
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
						<input type="hidden" id="packageId" value="">
						<div class="form-group">
							<label class="control-label" for="packageName">套餐名称</label>
							<input type="text" class="form-control" id="packageName" maxlength="20" value="" placeholder="套餐名称">
						</div>

						<div class="form-group">
							<label class="control-label" for="agentCanUser">代理商是否可用</label>
							<div class="checkbox checkbox-success">
								<input  type="checkbox"  id="agentCanUser">
								<label for="">可用</label>
							</div>
						</div>

                        <div class="form-group">
                            <label class="control-label" for="agentCanUser">营销引流客户添加数量限制</label>
                            <div>
                                <label class="switch">
                                    <input type="checkbox" id="market_config_is_open" value="open">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div id="num_div">
                            <div class="form-group">
                                <span>裂变引流</span>
                                <input type="text" class="form-control num_input" id="fission_num"
                                       maxlength="20" value="" placeholder="用户参与人数上限，不填默认无上限"
                                       onkeyup="value=value.replace([^\d]+/g,'')">
                            </div>

                            <div class="form-group">
                                <span>抽奖引流</span>
                                <input type="text" class="form-control num_input" id="lottery_draw_num"
                                       maxlength="20" value="" placeholder="用户参与人数上限，不填默认无上限"
                                       onkeyup="value=value.replace([^\d]+/g,'')">
                            </div>

                            <div class="form-group">
                                <span>红包裂变</span>
                                <input type="text" class="form-control num_input" id="red_envelopes_num"
                                       maxlength="20" value="" placeholder="用户参与人数上限，不填默认无上限"
                                       onkeyup="value=value.replace([^\d]+/g,'')">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label" for="">底部版权展示<span id="tech_explain">(您暂未配置底部版权信息，<a href="/admin/admin-config/index" target="_blank">点击配置</a>)</span></label>
                            <div class="radio">
                                <input  type="radio" name="tech" id="tech_open" value="1">
                                <label for="tech_open">展示</label>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                <input  type="radio" name="tech" id="tech_not_open" value="0">
                                <label for="tech_not_open">不展示</label>
                            </div>
                        </div>

						<div class="form-group">
							<label class="control-label" for="packageSort">套餐等级排序</label>
							<input type="text" class="form-control" id="packageSort" maxlength="20" value="0" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="套餐等级排序（套餐权限高，对应的等级也应设置较高）">
						</div>

						<!--日月年  时长  价格-->
						<div class="form-group">
							<label style='display: block !important;' class="control-label">套餐价格</label>
							<div class="col-xs-12">
								<div class="col-xs-12" id="insAdd" style="padding-left: 0px;">

								</div>
							</div>
						</div>

						<!--<div class="form-group">
							<label class="control-label" for="packageOldPrice">套餐原价</label>
							<input type="text" class="form-control" id="packageOldPrice" value="" placeholder="套餐原价">
						</div>
						<div class="form-group">
							<label class="control-label" for="packagePrice">套餐现价</label>
							<input type="text" class="form-control" id="packagePrice" value="" placeholder="套餐现价">
						</div>
						<div class="form-group">
							<label class="control-label" for="messageNum">消息配额</label>
							<input type="text" class="form-control" id="messageNum" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="消息配额 （条/日）">
						</div>
						<div class="form-group">
							<label class="control-label" for="subAccountNum">子账户数量</label>
							<input type="text" class="form-control" id="subAccountNum" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="子账户数量 （个）">
						</div>
						<div class="form-group">
							<label class="control-label" for="wechatNum">企业微信数量</label>
							<input type="text" class="form-control" id="wechatNum" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="企业微信数量 （个）">
						</div>
						<div class="form-group">
							<label class="control-label" for="accountNum">公众号数量</label>
							<input type="text" class="form-control" id="accountNum" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="公众号数量 （个）">
						</div>-->

						<div class="form-group">
							<label class="control-label" for="packagePrice">套餐权限</label>

							<div id="nestable-menu">
								<li type="button" data-action="wechat" class="nestable-menu btn btn-white active"> 企业微信 </li>
								<li type="button" data-action="account" class="nestable-menu btn btn-white"> 公众号 </li>
								<li type="button" data-action="common" class="nestable-menu btn btn-white"> 公共模块 </li>
							</div>

							<div class="checkbox checkbox-success">
								<input  type="checkbox"  class="allOrNoCheck">
								<label for="">全选</label>
							</div>

							<div class="row">
								<div class="col-lg-12">
									<div class="panel-group" id="accordion1">
										<!-- 两层 -->
										<?php foreach($wechatLists as $mk=>$menuList):?>
										<div class="panel panel-default">
											<div class="panel-heading">
												<h5 class="panel-title">
													<a data-toggle="collapse" data-parent="#accordion1" href="#collapse<?php echo $menuList['id'];?>" style="display: block; "><?php echo $menuList['title'];?></a>
												</h5>
											</div>
											<div id="collapse<?php echo $menuList['id'];?>" class="panel-collapse collapse <?php echo empty($mk)?'in':'';?> ">
												<div class="panel-body">
													<?php if(!empty($menuList['children'])):?>
														<div class="checkbox checkbox-success">
															<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" data-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-all package-check-box-all-<?php echo $menuList['id'];?>">
															<label for="packageCheckbox<?php echo $menuList['id'];?>">
																<?php echo $menuList['title'];?>
															</label>
														</div>
														<?php foreach($menuList['children'] as $mv):?>
														<div class="col-lg-3">
															<div class="checkbox checkbox-success">
																<input id="packageCheckbox<?php echo $mv['id'];?>" value="<?php echo $mv['id'];?>" type="checkbox" data-parent-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-child package-check-box-<?php echo $menuList['id'];?>">
																<label for="packageCheckbox<?php echo $mv['id'];?>">
																	<?php echo $mv['title'];?>
																</label>
															</div>
														</div>
														<?php endforeach; ?>
													<?php else:?>
													<div class="checkbox checkbox-success">
														<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" class="package-check-box">
														<label for="packageCheckbox<?php echo $menuList['id'];?>">
															<?php echo $menuList['title'];?>
														</label>

														<?php /*if ($menuList['id'] == '28'){ */?><!--
															<input type="text" class="useLimit" id="useLimit<?php /*echo $menuList['id'];*/?>" data-menu-id="<?php /*echo $menuList['id'];*/?>" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="渠道活码数量 （个）">
														--><?php /*} */?>
													</div>
													<?php endif;?>
												</div>
											</div>
										</div>
										<?php endforeach; ?>

										<!-- 一层 -->
										<?php /*foreach($wechatLists as $mk=>$menuList):*/?><!--
											<div class="panel panel-default">
												<div class="panel-body" style="padding-top: 0px;padding-bottom: 0px">
													<div class="checkbox checkbox-success">
														<input id="packageCheckbox<?php /*echo $menuList['id'];*/?>" value="<?php /*echo $menuList['id'];*/?>" type="checkbox" class="package-check-box">
														<label for="packageCheckbox<?php /*echo $menuList['id'];*/?>">
															<?php /*echo $menuList['title'];*/?>
														</label>
													</div>
												</div>
											</div>
										--><?php /*endforeach; */?>
									</div>

									<div class="panel-group" id="accordion2" style="display: none">
										<?php foreach($accountLists as $mk=>$menuList):?>
											<div class="panel panel-default">
												<div class="panel-heading">
													<h5 class="panel-title">
														<a data-toggle="collapse" data-parent="#accordion2" href="#collapse<?php echo $menuList['id'];?>" style="display: block; "><?php echo $menuList['title'];?></a>
													</h5>
												</div>
												<div id="collapse<?php echo $menuList['id'];?>" class="panel-collapse collapse <?php echo empty($mk)?'in':'';?> ">
													<div class="panel-body">
														<?php if(!empty($menuList['children'])):?>
															<div class="checkbox checkbox-success">
																<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" data-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-all package-check-box-all-<?php echo $menuList['id'];?>">
																<label for="packageCheckbox<?php echo $menuList['id'];?>">
																	<?php echo $menuList['title'];?>
																</label>
															</div>
															<?php foreach($menuList['children'] as $mv):?>
																<div class="col-lg-3">
																	<div class="checkbox checkbox-success">
																		<input id="packageCheckbox<?php echo $mv['id'];?>" value="<?php echo $mv['id'];?>" type="checkbox" data-parent-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-child package-check-box-<?php echo $menuList['id'];?>">
																		<label for="packageCheckbox<?php echo $mv['id'];?>">
																			<?php echo $mv['title'];?>
																		</label>

																		<?php /*if ($mv['id'] == '13'){ */?><!--
																			<input type="text" class="useLimit" id="useLimit<?php /*echo $mv['id'];*/?>" data-menu-id="<?php /*echo $mv['id'];*/?>" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="粉丝数量 （个）">
																		<?php /*} */?>
																		<?php /*if ($mv['id'] == '8'){ */?>
																			<input type="text" class="useLimit" id="useLimit<?php /*echo $mv['id'];*/?>" data-menu-id="<?php /*echo $mv['id'];*/?>" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="二维码数量 （个）">
																		--><?php /*} */?>
																	</div>
																</div>
															<?php endforeach; ?>
														<?php else:?>
															<div class="checkbox checkbox-success">
																<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" class="package-check-box">
																<label for="packageCheckbox<?php echo $menuList['id'];?>">
																	<?php echo $menuList['title'];?>
																</label>
															</div>
														<?php endif;?>
													</div>
												</div>
											</div>
										<?php endforeach; ?>

										<?php /*foreach($accountLists as $mk=>$menuList):*/?><!--
											<div class="panel panel-default">
												<div class="panel-body" style="padding-top: 0px;padding-bottom: 0px">
													<div class="checkbox checkbox-success">
														<input id="packageCheckbox<?php /*echo $menuList['id'];*/?>" value="<?php /*echo $menuList['id'];*/?>" type="checkbox" class="package-check-box">
														<label for="packageCheckbox<?php /*echo $menuList['id'];*/?>">
															<?php /*echo $menuList['title'];*/?>
														</label>
													</div>
												</div>
											</div>
										--><?php /*endforeach; */?>
									</div>

									<div class="panel-group" id="accordion3" style="display: none">
										<?php foreach($commonLists as $mk=>$menuList):?>
											<div class="panel panel-default">
												<div class="panel-heading">
													<h5 class="panel-title">
														<a data-toggle="collapse" data-parent="#accordion3" href="#collapse<?php echo $menuList['id'];?>" style="display: block; "><?php echo $menuList['title'];?></a>
													</h5>
												</div>
												<div id="collapse<?php echo $menuList['id'];?>" class="panel-collapse collapse <?php echo empty($mk)?'in':'';?> ">
													<div class="panel-body">
														<?php if(!empty($menuList['children'])):?>
															<div class="checkbox checkbox-success">
																<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" data-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-all package-check-box-all-<?php echo $menuList['id'];?>">
																<label for="packageCheckbox<?php echo $menuList['id'];?>">
																	<?php echo $menuList['title'];?>
																</label>
															</div>
															<?php foreach($menuList['children'] as $mv):?>
																<div class="col-lg-3">
																	<div class="checkbox checkbox-success">
																		<input id="packageCheckbox<?php echo $mv['id'];?>" value="<?php echo $mv['id'];?>" type="checkbox" data-parent-id="<?php echo $menuList['id'];?>" class="package-check-box package-check-box-child package-check-box-<?php echo $menuList['id'];?>">
																		<label for="packageCheckbox<?php echo $mv['id'];?>">
																			<?php echo $mv['title'];?>
																		</label>
																	</div>
																</div>
															<?php endforeach; ?>
														<?php else:?>
															<div class="checkbox checkbox-success">
																<input id="packageCheckbox<?php echo $menuList['id'];?>" value="<?php echo $menuList['id'];?>" type="checkbox" class="package-check-box">
																<label for="packageCheckbox<?php echo $menuList['id'];?>">
																	<?php echo $menuList['title'];?>
																</label>

																<?php /*if ($menuList['id'] == '31'){ */?><!--
																	<input type="text" class="useLimit" id="useLimit<?php /*echo $menuList['id'];*/?>" data-menu-id="<?php /*echo $menuList['id'];*/?>" value="" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" placeholder="存储空间 （M）">
																--><?php /*} */?>
															</div>
														<?php endif;?>
													</div>
												</div>
											</div>
										<?php endforeach; ?>

										<?php /*foreach($commonLists as $mk=>$menuList):*/?><!--
											<div class="panel panel-default">
												<div class="panel-body" style="padding-top: 0px;padding-bottom: 0px">
													<div class="checkbox checkbox-success">
														<input id="packageCheckbox<?php /*echo $menuList['id'];*/?>" value="<?php /*echo $menuList['id'];*/?>" type="checkbox" class="package-check-box">
														<label for="packageCheckbox<?php /*echo $menuList['id'];*/?>">
															<?php /*echo $menuList['title'];*/?>
														</label>
													</div>
												</div>
											</div>
										--><?php /*endforeach; */?>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary btn-confirm" data-action="">确定</button>
			</div>
		</div>
	</div>
</div>
<!-- 高级设置 -->
<div class="modal fade inmodal" tabindex="-1" id="packageSetModel" role="dialog" aria-hidden="true">
	<div class="modal-dialog" style="width: 750px;">
		<div class="modal-content">
			<div class="modal-header">
				<!--<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>-->

				<h4 class="modal-title">高级设置</h4>
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
						<div class="form-group">
							<label class="control-label" style="margin: 0 10px 0 0;line-height: 34px;float:left;">意向客户使用套餐</label>
							<select class="form-control" name="packageChoose" id="packageChoose" style="width:160px;">
								<option value="0" style="<?php if(empty($fInfo['id'])){echo 'display:none';}?>">请选择套餐</option>
								<?php foreach($packageList as $package){?>
									<option value="<?php echo $package['id']?>" <?php if((empty($packageSet['package_id']) && $package['is_trial'] == 1) || $packageSet['package_id'] == $package['id']){echo "selected";} ?> ><?php echo $package['name']?></option>
								<?php }?>
							</select>
						</div>
						<div class="form-group">
							<label class="control-label" style="margin: 0 10px 0 0;line-height: 34px;float:left;">使用时长</label>
							<select class="form-control" name="packageTime" id="packageTime" style="width:160px;">

							</select>
						</div>

						<div class="form-group">
							<label class="control-label">到期处理：针对意向客户和付费客户到期后，登录后台时的处理</label>
							<div>
								<div class="radio radio-success radio-inline">
									<input id="direction0" name="direction" value="1" type="radio" <?php echo ($packageSet['expire_type'] != 2)?'checked':'';?> >
									<label for="direction0"> 账号禁用（既无法登陆） </label>
								</div>
								<div class="radio radio-success radio-inline" style="300px">
									<input id="direction1" name="direction" value="2" type="radio" <?php echo $packageSet['expire_type'] == 2 ? 'checked' : '';?> >
									<label for="direction1"> 进入 </label>
									<select class="form-control" name="directionPackage" id="directionPackage" style="width:160px;display:inline-block">
										<?php foreach($packageList as $package){?>
											<option value="<?php echo $package['id']?>" <?php if((empty($packageSet['expire_package_id']) && $package['is_trial'] == 1) || $packageSet['expire_package_id'] == $package['id']){echo "selected";} ?> ><?php echo $package['name']?></option>
										<?php }?>
									</select>（永久使用，选择后该套餐将对代理商不可用）
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="modal-footer">
				<!--<button type="button" class="btn btn-white _close" data-dismiss="modal">关闭</button>-->
				<button type="button" class="btn btn-primary btn-confirm" data-action="packageSet">确定</button>
			</div>
		</div>
	</div>
</div>

<?=Html::jsFile('@web/plugins/layer/layer.js')?>
<script>
	var packageLocalMenuId     = <?php echo $packageLocalMenuId;?>;
	var packageLocalMenuStatus = <?php echo $packageLocalMenuStatus;?>;
	var packageLocalMenuLimit  = <?php echo $packageLocalMenuLimit;?>;
	var packageLocalPriceJson  = <?php echo $packageLocalPriceJson;?>;
	var packageSetInfo  = <?php echo $packageSetInfo;?>;
	var expirePackageId  = <?php echo $expirePackageId;?>;

	var use_i            = 1;
	var packagePriceJson = '';
	var web_tech_img = <?php echo '"' . $web_tech_img . '"';?>;

	// 高级设置
	$("#packageListBox").on('click', '.packageSet', function () {
		var packageChooseId = $("#packageChoose").val();
		packagePriceJson    = packageLocalPriceJson[packageChooseId];
		packageSetPrice();

		$("#packageSetModel .load-box").addClass("hide");
		$("#packageSetModel .data-box").removeClass("hide");
		$("#packageSetModel").modal('show');
	});
	//套餐切换
	$('#packageChoose').bind('change', function () {
		var packageChooseId = $(this).val();
		if (packageChooseId != 0) {
			packagePriceJson = packageLocalPriceJson[packageChooseId];
			packageSetPrice();
		}
	});

	//高级设置套餐档位
	function packageSetPrice(){
		for(var key in packagePriceJson){
			var timeValue = packagePriceJson[key].timeNum + '_' + packagePriceJson[key].timeType;
			var timeSelected = '';
			if (packageSetInfo){
				var setTime = packageSetInfo['duration'] + '_' + packageSetInfo['duration_type'];
				if (setTime == timeValue){
					timeSelected = 'selected';
				}
			}else if (key == 0){
				timeSelected = 'selected';
			}
			var timeType = '日';
			if (packagePriceJson[key].timeType == 2){
				timeType = '月';
			}else if (packagePriceJson[key].timeType == 3){
				timeType = '年';
			}
			var option = '<option value="' + timeValue + '"' + timeSelected + '>' + packagePriceJson[key].timeNum + timeType + '</option>';

			if(key == 0){
				$('#packageTime').html(option);
			}else{
				$('#packageTime').append(option);
			}
		}
	}

	// 高级设置提交
	$("#packageSetModel .btn-confirm").click(function () {
		var packageChoose    = $("#packageChoose").val();
		var packageTime      = $("#packageTime").val();
		var direction        = $("input[name=direction]:checked").val();
		var directionPackage = $("#directionPackage").val();

		if (packageChoose == '') {
			layer.msg('请选择意向客户套餐');
			return false;
		}
		if (packageTime == '') {
			layer.msg('请选择意向客户套餐时长');
			return false;
		}
		if (direction == 1 && directionPackage == '') {
			layer.msg('请选择到期客户套餐');
			return false;
		}

		$("#packageSetModel .form-group").removeClass("has-success");
		$("#packageSetModel .form-group").removeClass("has-error");
		$("#packageSetModel .data-box").addClass("hide");
		$("#packageSetModel .load-box").removeClass("hide");

		var postData = {
			package_id       : packageChoose,
			duration         : packageTime,
			expire_type      : direction,
			expire_package_id: directionPackage
		};
		var text     = '设置成功';
		$.ajax({
			url     : "/admin/index/set-default-package",
			type    : "POST",
			data    : postData,
			dataType: "JSON",
			success : function (result) {
				if (result.error == 0) {
					$("#packageSetModel").modal('hide');
					swal({
							title: "成功",
							text : text,
							type : "success"
						},
						function () {
							window.location.reload();
						});
				} else {
					$("#packageSetModel .load-box").addClass("hide");
					$("#packageSetModel .data-box").removeClass("hide");
					swal("失败!", result.msg, "error");
				}
			}
		});
	});

	// 添加套餐
	$("#packageListBox").on('click', '.addPackage', function () {
		$("#packageModelTitle").html('添加套餐');

		$("#packageModel .allOrNoCheck").prop("checked", false);
		$("#packageModel .package-check-box").prop('checked', false);

		$("#packageAlertBox").html('');
		$("#packageId").val('');
		$("#packageName").val('');
		$("#packageSort").val('0');
		$("#packageOldPrice").val('');
		$("#packagePrice").val('');
		$("#messageNum").val('');
		$("#subAccountNum").val('');
		$("#wechatNum").val('');
		$("#accountNum").val('');

        $('#market_config_is_open').prop('checked', false)
        $('#num_div').hide()
        $("#fission_num").val(0)
        $("#lottery_draw_num").val(0)
        $("#red_envelopes_num").val(0)
        if (web_tech_img != '') {
            $("#tech_explain").hide()
        }else{
            $("#tech_explain").show()
        }
        $("#tech_not_open").prop("checked",true);

		$("#agentCanUser").prop("checked", false);
		$("#agentCanUser").prop("disabled", false);

		$("#packageModel .btn-confirm").data("action", "add");
		$("#packageModel .load-box").addClass("hide");
		$("#packageModel .data-box").removeClass("hide");

		packagePriceJson = '';
		initPrice(0);

		$("#packageModel").modal('show');
	});

	// 修改套餐
	$("#packageListBox").on('click', '.editPackage', function () {
		var $this = $(this);

		$("#packageModelTitle").html('修改套餐');

		setPackageAuthority($this.parent().siblings('.package-id').html());

		$("#packageAlertBox").html('');
		var uptPackageId = $this.parent().siblings('.package-id').html();
		$("#packageId").val(uptPackageId);
		$("#packageName").val($this.parent().siblings('.package-name').html());
		$("#packageSort").val($this.parent().siblings('.package-sort').html());
		$("#packageOldPrice").val($this.parent().siblings('.package-price1').html());
		$("#packagePrice").val($this.parent().siblings('.package-price2').html());
		$("#messageNum").val($this.parent().siblings('.package-messageNum').html());
		$("#subAccountNum").val($this.parent().siblings('.package-subAccountNum').html());
		$("#wechatNum").val($this.parent().siblings('.package-wechatNum').html());
		$("#accountNum").val($this.parent().siblings('.package-accountNum').html());


		/*营销引流设置和底部版权设置*/
		var market_config_is_open = $this.siblings('.market_config_is_open').val()
        if (market_config_is_open == 1) {
            $('#market_config_is_open').prop('checked', true)
            $('#num_div').show();
        }else{
            $('#market_config_is_open').prop('checked', false)
            $('#num_div').hide()
        }

        $("#fission_num").val($this.siblings('.fission_num').val())
        $("#lottery_draw_num").val($this.siblings('.lottery_draw_num').val())
        $("#red_envelopes_num").val($this.siblings('.red_envelopes_num').val())

        if (web_tech_img != '') {
            $("#tech_explain").hide()
            var tech_img_show =  $this.siblings('.tech_img_show').val()
            if (tech_img_show == 1) {
                $("#tech_open").prop("checked",true);
            }else{
                $("#tech_not_open").prop("checked",true);
            }
        }else{
            $("#tech_not_open").prop("checked",true);
            $("#tech_explain").show()
        }


		var agentStr = $this.parent().siblings('.package-agent').html();
		if (agentStr == '是'){
			$('#agentCanUser').prop('checked',true);
		} else {
			$('#agentCanUser').prop('checked',false);
		}
		if (uptPackageId == expirePackageId){
			$('#agentCanUser').prop('disabled',true);
		}else{
			$('#agentCanUser').prop('disabled',false);
		}

		$("#packageModel .btn-confirm").data("action", "edit");
		$("#packageModel .load-box").addClass("hide");
		$("#packageModel .data-box").removeClass("hide");

		var is_trial = $this.data('trial');
		if (is_trial == 1) {
			trialInitPrice();
		} else {
			initPrice(0);
		}

		var len = $(".package-check-box").length;
		if ($(".package-check-box:checked").length == len && len > 0) {
			$('.allOrNoCheck').prop('checked', true);
		}else{
			$('.allOrNoCheck').prop('checked', false);
		}

		$("#packageModel").modal('show');
	});

	//初始化套餐档位
	function initPrice(type = 0){
		//if(type == 0 && JSON.stringify(packagePriceJson) != "{}"){
		if(type == 0 && packagePriceJson != ''){
			use_i = 0;
			console.log(packagePriceJson,'packagePriceJson')
			for(var key in packagePriceJson){
				use_i ++;
				if(key == 0){
					var add_html = '<span class="btn btn-primary" style="height: 24px;inline-height: 24px;padding-top: 2px;" id="second_add">添加</span>';
				}else{
					var add_html = '<span class="btn btn-primary second_del" data-key="' + key + '" style="height: 24px;inline-height: 24px;padding-top: 2px;">删除</span>';
				}
				var option = '<option value="0">时间类型</option>';
				if (packagePriceJson[key].timeType == 1){
					option += '<option value="1" selected>日</option>';
				}else{
					option += '<option value="1">日</option>';
				}
				if (packagePriceJson[key].timeType == 2){
					option += '<option value="2" selected>月</option>';
				}else{
					option += '<option value="2">月</option>';
				}
				if (packagePriceJson[key].timeType == 3){
					option += '<option value="3" selected>年</option>';
				}else{
					option += '<option value="3">年</option>';
				}
				var sendOption = '<option value="0">时间类型</option>';
				if (packagePriceJson[key].sendTimeType == 1){
					sendOption += '<option value="1" selected>日</option>';
				}else{
					sendOption += '<option value="1">日</option>';
				}
				if (packagePriceJson[key].sendTimeType == 2){
					sendOption += '<option value="2" selected>月</option>';
				}else{
					sendOption += '<option value="2">月</option>';
				}
				if (packagePriceJson[key].sendTimeType == 3){
					sendOption += '<option value="3" selected>年</option>';
				}else{
					sendOption += '<option value="3">年</option>';
				}

				if (packagePriceJson[key].discount == undefined || packagePriceJson[key].discount == 0){
					packagePriceJson[key].discount = '';
				}

				var use_text = '<div style="display:inline-block;" class="form-group sele-check flex-box activeSec' + key + '" >\n' +
					'\t\t\t\t\t\t\t\t<div class="flex-box" style="padding-left: 0">\n' +
					'\t\t\t\t\t\t\t\t\t<div class="">\n' +
					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box radioInline-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t<input  type="text" value="' + packagePriceJson[key].timeNum + '" placeholder="时长" style="width:50px" name="packageTimeNum' + key + '" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box permanent-box checkbox-circle">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t<select class="form-control" style="width:80px; height: 24px; padding: 0" id="packageTimeType' + key + '">' + option + '</select>\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box radioInline-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t赠送 <input  type="text" value="' + packagePriceJson[key].sendTimeNum + '" placeholder="时长" style="width:50px" name="packageSendTimeNum' + key + '" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box permanent-box checkbox-circle">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t<select class="form-control" style="width:80px; height: 24px; padding: 0" id="packageSendTimeType' + key + '">' + sendOption + '</select>\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box fixedInterval-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t<input type="text" value="' + packagePriceJson[key].nowPrice + '" placeholder="价格（元）" style="width:60px" name="packageNowPrice'+key+'" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +

					'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box fixedInterval-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t\t\t<input type="text" value="' + packagePriceJson[key].discount + '" placeholder="延期折扣（1-10）" style="width:120px" name="packageDiscount'+key+'" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
					'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t\t\t</div>\n' +

					'\t\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t\t</div>\n' +
					'\t\t\t\t\t\t\t</div>';

				var str = '<div class="input-daterange input-group rowSecond" style="height: 24px;display: inline-block;margin-left:5px;">'+add_html+'</div>';
				use_text += str;
				if(key == 0){
					$('#insAdd').html(use_text);
				}else{
					$('#insAdd').append(use_text);
				}
			}
		}else{
			var type1 = use_i;
			if(type == 0){
				use_i = 1;
				type1 = type;
				var add_html = '<span class="btn btn-primary" style="height: 24px;inline-height: 24px;padding-top: 2px;" id="second_add">添加</span>';
			}else{
				var add_html = '<span class="btn btn-primary second_del" data-key="' + type1 + '" style="height: 24px;inline-height: 24px;padding-top: 2px;">删除</span>';
			}

			var option = '<option value="0">时间类型</option>';
			option += '<option value="1">日</option>';
			option += '<option value="2">月</option>';
			option += '<option value="3" selected="selected">年</option>';

			var option2 = '<option value="0">时间类型</option>';
            option2 += '<option value="1">日</option>';
            option2 += '<option value="2" selected="selected">月</option>';
            option2 += '<option value="3">年</option>';

			var use_text = '<div style="display:inline-block;" class="form-group sele-check flex-box activeSec' + type1 + '" >\n' +
				'\t\t\t\t\t\t\t\t<div class="flex-box" style="padding-left: 0">\n' +
				'\t\t\t\t\t\t\t\t\t<div class="">\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box radioInline-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<input  type="text" value="" placeholder="时长" style="width:50px" name="packageTimeNum' + type1 + '" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box permanent-box checkbox-circle">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<select class="form-control" style="width:80px; height: 24px; padding: 0" id="packageTimeType' + type1 + '">' + option + '</select>\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box radioInline-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t赠送 <input  type="text" value="" placeholder="时长" style="width:50px" name="packageSendTimeNum' + type1 + '" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box permanent-box checkbox-circle">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<select class="form-control" style="width:80px; height: 24px; padding: 0" id="packageSendTimeType' + type1 + '">' + option2 + '</select>\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box fixedInterval-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<input type="text" value="" placeholder="价格（元）" style="width:60px" name="packageNowPrice'+type1+'" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +

				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box fixedInterval-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<input type="text" value="" placeholder="延期折扣（1-10）" style="width:120px" name="packageDiscount'+type1+'" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +

				'\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t</div>';

			var str = '<div class="input-daterange input-group rowSecond" style="height: 24px;display: inline-block;margin-left:5px;">'+add_html+'</div>';
			use_text += str;
			if(type == 0){
				$('#insAdd').html(use_text);
			}else{
				$('#insAdd').append(use_text);
				use_i++;
				/*if(use_i >= 5){
					$('#second_add').css('display','none');
				}*/
			}
		}
	}

	//免费套餐档位
	function trialInitPrice(){
		for(var key in packagePriceJson){
			var option = '<option value="1" selected>日</option>';

			var use_text = '<div style="display:inline-block;" class="form-group sele-check flex-box activeSec' + key + '" >\n' +
				'\t\t\t\t\t\t\t\t<div class="flex-box" style="padding-left: 0">\n' +
				'\t\t\t\t\t\t\t\t\t<div class="">\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box radioInline-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t时长 <input  type="text" value="' + packagePriceJson[key].timeNum + '" placeholder="时长" style="width:80px" name="packageTimeNum' + key + '" onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box permanent-box checkbox-circle">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t<select class="form-control" style="width:120px; height: 24px; padding: 0" id="packageTimeType' + key + '" readonly>' + option + '</select>\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t<div class="checkbox-inline i-checks check-box fixedInterval-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t<div class="checks-box flex-box">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t\t\t价格（元）<input type="text" value="' + packagePriceJson[key].nowPrice + '" placeholder="价格（元）" style="width:80px" name="packageNowPrice'+key+'" disabled onkeyup="value=value.replace(/[^0-9]/g,\'\')">\n' +
				'\t\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t\t</div>\n' +
				'\t\t\t\t\t\t\t</div>';

			var str = '<div class="input-daterange input-group rowSecond" style="height: 24px;display: inline-block;margin-left:5px;"></div>';
			use_text += str;
			$('#insAdd').html(use_text);
		}
	}

	$("body").on("click", "#second_add", function () {//套餐添加档位
		initPrice(1);
	}).on('click','#insAdd .second_del',function(){//套餐删除档位
		use_i--;
		var delKey = $(this).data('key');
		$(this).parent().siblings(".activeSec"+delKey).remove();
		$(this).parent().remove();
	})

	//切换模块
	$('#nestable-menu').on('click', function (e) {
		var target = $(e.target),
		    action = target.data('action');
		$('.nestable-menu').removeClass('active');
		target.addClass('active');
		if (action === 'wechat') {
			$('#accordion1').show();
			$('#accordion2').hide();
			$('#accordion3').hide();
		} else if (action === 'account'){
			$('#accordion1').hide();
			$('#accordion2').show();
			$('#accordion3').hide();
		} else if (action === 'common'){
			$('#accordion1').hide();
			$('#accordion2').hide();
			$('#accordion3').show();
		}
	});

	//功能全选
	$('.allOrNoCheck').click(function () {
		if ( $(this).prop('checked') == true ){
			$("#packageModel .package-check-box").prop("checked", true);
		} else {
			$("#packageModel .package-check-box").prop("checked", false);
		}
	});

	// 全选功能
	$(".package-check-box-all").click(function () {
		var $this       = $(this);
		var selfId      = $this.data('id');
		var selfChecked = $this.is(':checked');

		$("#packageModel .package-check-box-" + selfId).prop('checked', selfChecked);

		if (selfChecked){
			var len = $(".package-check-box").length;
			if ($(".package-check-box:checked").length == len && len > 0) {
				$('.allOrNoCheck').prop('checked', true);
			}
		}else{
			$('.allOrNoCheck').prop('checked', false);
		}
	});

	// 是否勾选全选
	$(".package-check-box").click(function () {
		var $this       = $(this);
		var selfChecked = $this.is(':checked');

		if (selfChecked){
			var len = $(".package-check-box").length;
			if ($(".package-check-box:checked").length == len && len > 0) {
				$('.allOrNoCheck').prop('checked', true);
			}
		}else{
			$('.allOrNoCheck').prop('checked', false);
		}
	});

	// 是否需要全选
	$(".package-check-box-child").click(function () {
		var $this    = $(this);
		var parentId = $this.data('parent-id');

		var totalCount = $("#packageModel .package-check-box-" + parentId).length;
		var checkCount = $("#packageModel .package-check-box-" + parentId + ":checked").length;

		$("#packageModel .package-check-box-all-" + parentId).prop('checked', totalCount == checkCount);

		if (totalCount == checkCount){
			var len = $(".package-check-box").length;
			if ($(".package-check-box:checked").length == len && len > 0) {
				$('.allOrNoCheck').prop('checked', true);
			}
		}else{
			$('.allOrNoCheck').prop('checked', false);
		}
	});

	$('body').on('input','#packageOldPrice,#packagePrice',function(){
		this.value = this.value.replace(/^\./g, '');
		this.value = this.value.replace(/[^\d|\.]+/g, '');
		this.value = this.value.replace(/^0[^\.]?/, '0');
		this.value = this.value.replace(/^0\.[^\d]/, '0.');
		this.value = this.value.replace(/^(\d+)(\.?)(\d{0,2})([^\d]|[\d]?)$/g, '$1$2$3');
		this.value = this.value.replace('0.00', '0.0');
		if( this.value > 99999999 ){
			layer.msg('填写的价格不能大于99999999');
			this.value = 99999999;
		}
	})

	function getPackageAuthority () {
		var packageAuthority = [];
		$.each($("#packageModel .package-check-box"), function () {
			if ($(this).is(':checked')) {
				packageAuthority.push($(this).val());
			}
		});
		return packageAuthority;
	}

	function setPackageAuthority (packageId) {
		$("#packageModel .package-check-box").prop('checked', false);

		var packageMenuId     = packageLocalMenuId[packageId];
		var packageMenuStatus = packageLocalMenuStatus[packageId];
		//var packageMenuLimit  = packageLocalMenuLimit[packageId];
		packagePriceJson      = packageLocalPriceJson[packageId];

		if (packageMenuId.length > 0) {
			$.each(packageMenuId, function (key, menuId) {
				$("#packageCheckbox" + menuId).prop('checked', packageMenuStatus[key] == 1);
				//功能限制
				/*if (packageMenuLimit[menuId] > 0){
					$("#useLimit" + menuId).val(packageMenuLimit[menuId]);
				}*/
			});
		}
	}

	//获取功能限制
	function getMenuLimit () {
		var menuLimit = {};
		var limitNum  = '';
		var menuId    = 0;
		$.each($(".useLimit"), function () {
			limitNum = $(this).val();
			menuId   = $(this).data('menu-id');
			if (limitNum && menuId > 0) {
				menuLimit[menuId] = limitNum;
			}
		});
		return menuLimit;
	}

	// 删除套餐
	$("#packageListBox").on('click', '.delPackage', function () {
		var $this = $(this);
		swal({
        	title: "确定删除吗？",
        	type: "warning",
        	showCancelButton: true,
        	confirmButtonText: "确定",
        	cancelButtonText: "取消",
        	closeOnConfirm: false,
        	closeOnCancel: true
        }, function(isConfirm){
        	if(isConfirm){
				var packageId    = $this.parent().siblings('.package-id').html();
				var postData = {id: packageId};
				$.ajax({
					url     : "/admin/index/del-package",
					type    : "POST",
					data    : postData,
					dataType: "JSON",
					success : function (result) {
						if (result.error == 0) {
							swal({
									title: "成功",
									text:  "删除成功",
									type:  "success"
								},
								function(){
									window.location.reload();
								});
						} else {
							swal("失败!", result.msg, "error");
						}
					}
				});
        	}
        })
	});

	// 提交model内容
	$("#packageModel .btn-confirm").click(function () {

		var hasError         = false;
		var $this            = $(this);
		var packageAction    = $this.data('action');
		var packageId        = $("#packageId").val();
		var packageName      = $("#packageName").val();
		var packageSort      = $("#packageSort").val();
		var packageOldPrice  = $("#packageOldPrice").val();
		var packagePrice     = $("#packagePrice").val();
		var messageNum       = $("#messageNum").val();
		var subAccountNum    = $("#subAccountNum").val();
		var wechatNum        = $("#wechatNum").val();
		var accountNum       = $("#accountNum").val();
		var packageAuthority = getPackageAuthority();
		var menuLimit        = getMenuLimit();

        var techImgShow      = $("input[name='tech']:checked").val();
        var marketConIsOpen  = $("#market_config_is_open").prop('checked') ? 1 : 0
        var fissionNum       = $("#fission_num").val()
        var lotteryDrawNum   = $("#lottery_draw_num").val()
        var redEnvelopesNum  = $("#red_envelopes_num").val()

		var agentCanUser = $("#agentCanUser").is(':checked');
		if(agentCanUser == true){
			agentCanUser = 1;
		}else{
			agentCanUser = 0;
		}

		var priceJson = [];
		var ft        = true;
		$('.rowSecond').each(function (key, value) {
			var temp         = {};
			var timeType     = $("#packageTimeType" + key).val();
			var timeNum      = $("input[name='packageTimeNum" + key + "']").val();
			var sendTimeType = $("#packageSendTimeType" + key).val();
			var sendTimeNum  = $("input[name='packageSendTimeNum" + key + "']").val();
			var nowPrice     = $("input[name='packageNowPrice" + key + "']").val();
			var discount     = $("input[name='packageDiscount" + key + "']").val();

			if (timeType == '' || timeType == 0) {
				layer.msg('请选择时间类型');
				ft = false;
				return;
			}
			if (timeNum == '' || timeNum == 0) {
				layer.msg('请设置套餐时长');
				ft = false;
				return;
			}
			if (sendTimeType == 0 && sendTimeNum > 0){
				layer.msg('请选择赠送时间类型');
				ft = false;
				return;
			}
			if (discount > 10){
				layer.msg('延期折扣设置错误');
				ft = false;
				return;
			}
			/*if (sendTimeType > 0 && sendTimeNum == ''){
				layer.msg('请设置套餐赠送时长');
				ft = false;
				return;
			}*/
			if (timeType != 0 && timeNum != '') {
				temp.timeType     = timeType;
				temp.timeNum      = timeNum;
				temp.sendTimeType = sendTimeType > 0 ? sendTimeType : 0;
				temp.sendTimeNum  = sendTimeNum > 0 ? sendTimeNum : 0;
				temp.nowPrice     = nowPrice > 0 ? nowPrice : 0;
				temp.discount     = discount > 0 ? discount : '';
				priceJson.push(temp);
			}
		});
		if (priceJson.length > 0) {
			priceJson = JSON.stringify(priceJson);
		} else {
			layer.msg('请设置套餐价格');
			ft = false;
			return false;
		}
		if (!ft) {
			return false;
		}

		if(packageName == ''){
			layer.msg('请填写套餐名称');
			return false;
		}
		/*if(parseFloat(packageOldPrice) < parseFloat(packagePrice)){
			layer.msg('现价不能大于原价');
			return false;
		}
		if(wechatNum > 5){
			layer.msg('企业微信最多只能5个');
			return false;
		}*/
		if(packageAuthority.length == 0){
			layer.msg('请选择套餐权限');
			return false;
		}
		$("#packageModel .form-group").removeClass("has-success");
		$("#packageModel .form-group").removeClass("has-error");
		$("#packageModel .data-box").addClass("hide");
		$("#packageModel .load-box").removeClass("hide");

		switch (packageAction) {
			case 'add':
				var postData = {name: packageName, sort: packageSort, is_agent: agentCanUser, priceJson:priceJson,old_price:packageOldPrice, price: packagePrice, message_num: messageNum, sub_account_num: subAccountNum, wechat_num: wechatNum, account_num: accountNum,tech_img_show: techImgShow,market_config_is_open:marketConIsOpen,fission_num:fissionNum,lottery_draw_num:lotteryDrawNum,red_envelopes_num:redEnvelopesNum, authority: packageAuthority, menuLimit:menuLimit};

				break;
			case 'edit':
				var postData = {id: packageId, name: packageName, sort: packageSort, is_agent: agentCanUser, priceJson:priceJson,old_price:packageOldPrice, price: packagePrice, message_num: messageNum, sub_account_num: subAccountNum, wechat_num: wechatNum, account_num: accountNum,tech_img_show: techImgShow,market_config_is_open:marketConIsOpen,fission_num:fissionNum,lottery_draw_num:lotteryDrawNum,red_envelopes_num:redEnvelopesNum, authority: packageAuthority, menuLimit:menuLimit};

				break;
			default:
				hasError = true;

				var html = '<div class="alert alert-danger alert-dismissable">' +
					'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' +
					'哪里出了点问题！' +
					'</div>';

				$("#packageAlertBox").html(html);

				$("#packageModel .load-box").addClass("hide");
				$("#packageModel .data-box").removeClass("hide");
				break;
		}
		var text = '创建成功';
		if(packageId != ''){
			text = '操作成功';
		}
		if (!hasError) {
			$.ajax({
				url     : "/admin/index/set-package",
				type    : "POST",
				data    : postData,
				dataType: "JSON",
				success : function (result) {
					if (result.error == 0) {
						$("#packageModel").modal('hide');
						swal({
								title: "成功",
								text:  text,
								type:  "success"
							},
							function(){
								window.location.reload();
							});
					} else {
						$("#packageModel .load-box").addClass("hide");
						$("#packageModel .data-box").removeClass("hide");
						swal("失败!", result.msg, "error");
					}
				}
			});
		}
	});

	/*监听营销引流开关控制*/
    $(function(){
        $("#market_config_is_open").change(function(){
            let is_checked = $("#market_config_is_open").prop('checked')
            if (is_checked) {
                $("#num_div").show()
            }else{
                $("#num_div").hide()
            }
        })
    })

</script>