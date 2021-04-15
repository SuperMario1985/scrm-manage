<div class="row border-bottom" id="alert_content_div">
	<nav class="navbar navbar-static-top" role="navigation">
		<ul class="nav navbar-top-links navbar-right">
			<li>
				<a class="dropdown-toggle count-info" href="javascript:;" style="background-color: #01B065;">
					<i  class="fa fa-user"></i>
					<?php
						if (isset(Yii::$app->adminUserEmployee->identity->id) && Yii::$app->adminUserEmployee->identity->id > 0){
							$employee = \app\models\AdminUserEmployee::findOne(Yii::$app->adminUserEmployee->identity->id);
							echo $employee->name;
						}else{
							echo Yii::$app->adminUser->identity->account;
						}
					?>
				</a>
			</li>
			<li>
				<a href="/admin/index/logout">
					<i  class="fa fa-sign-out"></i> 退出
				</a>
			</li>
		</ul>
	</nav>
</div>