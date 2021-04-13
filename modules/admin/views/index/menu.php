<?php
	use yii\helpers\Html;
	$this->title = '菜单管理';
?>
<style>
	.menu-list-label {
		display: inline-block;
		margin-right: 10px;
		min-width: 30px;
		vertical-align: middle;
	}
	.dd-handle span {
		font-weight: normal;
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
				<strong>菜单管理</strong>
			</li>
		</ol>
	</div>
	<div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
	<div class="row">
		<div class="col-lg-6">
			<div id="menuListBox" class="ibox float-e-margins <?= empty($menuLists)?'hide':'';?>">
				<div class="ibox-content">
					<div id="nestable-menu">
						<button type="button" data-action="expand-all" class="btn btn-white btn-sm"> 全部展开
						</button>
						<button type="button" data-action="collapse-all" class="btn btn-white btn-sm"> 全部折叠
						</button>
					</div>

					<div class="dd" id="menuList">
						<ol class="dd-list">
							<?php foreach($menuLists as $mk=>$menuList):?>
							<li id="menuInfo<?= $menuList['id'];?>" class="dd-item <?= ($menuList['status'] == 0)?'text-warning':'';?>" data-id="<?= $menuList['id'];?>">
								<span class="pull-right" style="margin-top: 6px;">
									<button type="button" data-id="<?= $menuList['id'];?>" class="btn btn-xs btn-link changeMenu"><?= !empty($menuList['status'])?'隐藏菜单':'释放菜单';?></button>
									<button type="button" data-id="<?= $menuList['id'];?>" class="btn btn-xs btn-link addMenu">添加子菜单</button>
									<button type="button" data-id="<?= $menuList['id'];?>" class="btn btn-xs btn-link editMenu">编辑菜单</button>
								</span>
								<div class="dd-handle">
									<span class="label label-info menu-list-label">
										<i class="fa fa-home"></i>
									</span>
									<span id="title<?= $menuList['id'];?>"><?= $menuList['title'];?></span>
								</div>
								<?php if(!empty($menuList['children'])):?>
								<ol id="menuList<?= $menuList['id'];?>" class="dd-list">
									<?php foreach($menuList['children'] as $mv):?>
									<li class="dd-item <?= ($mv['status'] == 0)?'text-warning':'';?>" data-id="<?= $mv['id'];?>">
										<span class="pull-right" style="margin-top: 6px;">
											<button type="button" data-id="<?= $mv['id'];?>" class="btn btn-xs btn-link changeMenu"><?= !empty($mv['status'])?'隐藏菜单':'释放菜单';?></button>
											<button type="button" data-id="<?= $mv['id'];?>" class="btn btn-xs btn-link editMenu">编辑菜单</button>
										</span>
										<div class="dd-handle">
											<span id="title<?= $mv['id'];?>"><?= $mv['title'];?></span>
										</div>
									</li>
									<?php endforeach; ?>
								</ol>
								<?php endif;?>
							</li>
							<?php endforeach; ?>
						</ol>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade inmodal" tabindex="-1" id="menuModel" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close _close" data-dismiss="modal">
					<span aria-hidden="true">×</span>
					<span class="sr-only">关闭</span>
				</button>

				<h4 id="menuModelTitle" class="modal-title">菜单详细</h4>
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
					<div id="menuAlertBox"></div>

					<form role="form">
						<input type="hidden" id="menuId" value="">
						<div class="form-group">
							<label class="control-label" for="menuTitle">菜单名称</label>
							<input type="text" class="form-control menu-input" id="menuTitle" value="" placeholder="套餐名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuIcon">菜单图标</label>
							<input type="text" class="form-control menu-input" id="menuIcon" value="" placeholder="套餐图标">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuUrl">菜单标识</label>
							<input type="text" class="form-control menu-input" id="menuKey" value="" placeholder="菜单URL">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuModels">菜单地址</label>
							<input type="text" class="form-control menu-input" id="menuLink" value="" placeholder="菜单模块名称">
						</div>
						<div class="form-group">
							<label class="control-label" for="menuAuth">菜单归属</label>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="comefrom1" value="0" name="comefrom" aria-label="Yes">
								<label for="comefrom1"> 公众号 </label>
							</div>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="comefrom2" value="1" name="comefrom" aria-label="No">
								<label for="comefrom2"> 企业微信 </label>
							</div>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="comefrom3" value="2" name="comefrom" aria-label="No">
								<label for="comefrom3"> 公共菜单 </label>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label" for="menuAuth">是否为新菜单</label>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="menuNew1" value="1" name="menuNew" aria-label="Yes">
								<label for="menuNew1"> 是 </label>
							</div>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="menuNew2" value="0" name="menuNew" aria-label="No">
								<label for="menuNew2"> 否 </label>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label" for="menuAuth">是否为热菜单</label>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="menuHot1" value="1" name="menuHot" aria-label="Yes">
								<label for="menuHot1"> 是 </label>
							</div>
							<div class="radio radio-success radio-inline">
								<input class="radio-check" type="radio" id="menuHot2" value="0" name="menuHot" aria-label="No">
								<label for="menuHot2"> 否 </label>
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

<?=Html::jsFile('@web/js/jquery.nestable.js')?>

<script>
	$(function(){
		var updateOutput = function (e) {
			var list = e.length ? e : $(e.target);
			if (window.JSON) {
				var sortData = window.JSON.stringify(list.nestable('serialize'));
				console.log(sortData);
				$.ajax({
					url     : "/admin/index/change-menu-sort",
					type    : "POST",
					data    : {sort: sortData},
					dataType: "JSON",
					success : function (result) {
						if (result.status) {
							//TODO success code
						} else {
							//TODO error code
						}
					}
				});
			}
		};


		$('#menuList').nestable({
			group          : 1,
			maxDepth       : 2,
			expandBtnHTML  : "<button class=\"menu-list-collapse\" data-action=\"expand\" type=\"button\">展开</button>",
			collapseBtnHTML: "<button class=\"menu-list-collapse\" data-action=\"collapse\" type=\"button\">折叠</button>"
		}).on('change', updateOutput);

		// output initial serialised data
		//updateOutput($('#menuList'));

		$('#nestable-menu').on('click', function (e) {
			var target = $(e.target),
				action = target.data('action');
			if (action === 'expand-all') {
				$('.dd').nestable('expandAll');
			}
			if (action === 'collapse-all') {
				$('.dd').nestable('collapseAll');
			}
		});

		$('.dd').nestable('collapseAll');

		// 添加菜单
		$("#menuListBox").on('click', '.addMenu', function () {
			var $this  = $(this);
			var menuId = $this.data("id");

			$("#menuModelTitle").html('添加菜单');

			$("#menuModel .radio-check").prop('checked', false);
			$("#comefrom1").prop('checked', true);
			$("#menuNew1").prop('checked', true);
			$("#menuHot2").prop('checked', true);

			$("#menuAlertBox").html('');
			$("#menuId").val(menuId);
			$(".menu-input").val('');

			$("#menuModel .btn-confirm").data("action", "add");
			$("#menuModel .load-box").addClass("hide");
			$("#menuModel .data-box").removeClass("hide");

			$("#menuModel").modal('show');
		});

		// 修改菜单
		$("#menuListBox").on('click', '.editMenu', function () {
			var $this  = $(this);
			var menuId = $this.data("id");

			$("#menuModelTitle").html('修改菜单');

			$("#menuModel .radio-check").prop('checked', false);

			$("#menuAlertBox").html('');
			$("#menuId").val(menuId);
			$(".menu-input").val('');

			$.ajax({
				url     : "/admin/index/get-menu?id=" + menuId,
				type    : "GET",
				dataType: "JSON",
				success : function (result) {
					if (result.error == 0) {
						$('#menuTitle').val(result.data.title);
						$('#menuIcon').val(result.data.icon);
						$('#menuKey').val(result.data.key);
						$('#menuLink').val(result.data.link);
						$("#comefrom1").prop('checked', result.data.comefrom == 0);
						$("#comefrom2").prop('checked', result.data.comefrom == 1);
						$("#comefrom3").prop('checked', result.data.comefrom == 2);
						$("#menuNew1").prop('checked', result.data.is_new == 1);
						$("#menuNew2").prop('checked', result.data.is_new == 0);
						$("#menuHot1").prop('checked', result.data.is_hot == 1);
						$("#menuHot2").prop('checked', result.data.is_hot == 0);

						$("#menuModel .btn-confirm").data("action", "edit");

						$("#menuModel .load-box").addClass("hide");
						$("#menuModel .data-box").removeClass("hide");
					} else {
						var html = '<div class="alert alert-danger alert-dismissable">' +
							'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' + result.msg +
							'</div>';

						$("#menuAlertBox").html(html);

						$("#menuModel .load-box").addClass("hide");
						$("#menuModel .data-box").removeClass("hide");
					}
				}
			});

			$("#menuModel").modal('show');
		});

		// 修改菜单状态
		$("#menuListBox").on('click', '.changeMenu', function () {
			var $this         = $(this);
			var menuId        = $this.data("id");
			var $menuLi       = $this.parent().parent();
			var $menuNameArea = $this.parent().siblings(".dd-handle");
			var menuName      = $.trim($menuNameArea.text());

			$.ajax({
				url     : "/admin/index/change-menu-status?id=" + menuId,
				type    : "GET",
				dataType: "JSON",
				success : function (result) {
					if (result.error == 0) {
						$menuLi.toggleClass('text-warning');
						if ($menuLi.hasClass('text-warning')) {
							$this.text("释放菜单");
						} else {
							$this.text("隐藏菜单");
						}
						swal("成功!", "菜单 " + menuName + " 状态修改成功！", "success");
					} else {
						swal("失败!", "菜单 " + menuName + " 状态修改失败！", "error");
					}
				}
			});
		});

		// 关闭model
		$("#menuModel ._close").click(function () {
			$("#menuId").val('');
			$("#menuModel .btn-confirm").data("action", "");
			$("#menuModel").modal('hide');
		});

		//提交
		$("#menuModel .btn-confirm").click(function () {
			$("#menuModel .data-box").addClass("hide");
			$("#menuModel .load-box").removeClass("hide");

			var $this          = $(this);
			var menuType       = $this.data('action');
			var menuId         = $("#menuId").val();
			var menuTitle      = $('#menuTitle').val();
			var menuIcon       = $('#menuIcon').val();
			var menuKey        = $('#menuKey').val();
			var menuLink        = $('#menuLink').val();
			var menuNew        = $('input[name=menuNew]:checked').val();
			var menuHot        = $('input[name=menuHot]:checked').val();
			var comefrom       = $('input[name=comefrom]:checked').val();

			if (!menuType) {
				var html = '<div class="alert alert-danger alert-dismissable">' +
					'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' +
					'哪里出了点问题！' +
					'</div>';

				$("#menuAlertBox").html(html);

				$("#menuModel .load-box").addClass("hide");
				$("#menuModel .data-box").removeClass("hide");
			} else {
				var postData = {
					type      : menuType,
					id        : menuId,
					title     : menuTitle,
					icon      : menuIcon,
					key       : menuKey,
					link     : menuLink,
					is_new   : menuNew,
					is_hot   : menuHot,
					comefrom  : comefrom,
					sort      : $("#menuList" + menuId).children().length
				};

				$.ajax({
					url     : "/admin/index/set-menu",
					type    : "POST",
					data    : postData,
					dataType: "JSON",
					success : function (result) {
						if (result.error == 0) {
							if ($("#menuListBox").hasClass('hide')) {
								$("#menuListBox").removeClass('hide');
							}
							switch (menuType) {
								case 'add':
									var newMenuId   = result.id;
									var newMenuHtml = '<li class="dd-item" data-id="' + newMenuId + '">' +
										'<span class="pull-right" style="margin-top: 6px;">' +
										'<button type="button" data-id="' + newMenuId + '" class="btn btn-xs btn-link editMenu">编辑菜单</button>' +
										'</span>' +
										'<div class="dd-handle">' + menuTitle + '</div>' +
										'</li>';
									if ($("#menuList" + menuId).length > 0) {
										$("#menuList" + menuId).append(newMenuHtml);
									} else {
										$("#menuInfo" + menuId).append('<ol id="menuList' + newMenuId + '" class="dd-list">' + newMenuHtml + '</ol>');
									}

									updateOutput($('#menuList'));

									break;
								case 'edit':
									$('#title'+menuId).html(result.title);
									break;
							}

							$("#menuModel").modal('hide');
						} else {
							var html = '<div class="alert alert-danger alert-dismissable">' +
								'<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' + result.msg +
								'</div>';

							$("#menuAlertBox").html(html);

							$("#menuModel .load-box").addClass("hide");
							$("#menuModel .data-box").removeClass("hide");
						}
					}
				});
			}
		});
	});

</script>