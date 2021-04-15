<?php

	/* @var $this \yii\web\View */
	/* @var $content string */

	use app\widgets\Alert;
	use yii\helpers\Html;
	use yii\bootstrap\Nav;
	use yii\bootstrap\NavBar;
	use yii\widgets\Breadcrumbs;
	use app\assets\AppAsset;

	AppAsset::register($this);
	//$this->title = '登录';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
	<meta charset="<?= Yii::$app->charset ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php $this->registerCsrfMetaTags() ?>
	<title><?= Html::encode($this->title) ?></title>
	<?php $this->head() ?>
	<?=Html::cssFile('@web/css/bootstrap.min.css')?>
    <?=Html::cssFile('@web/css/change.css')?>
</head>
<body>
<?php $this->beginBody() ?>
<div class="wrap">
    <div class="box_left">
        <div class="img"></div>
        <div class="logo_div">
            <h1 class="logo_title">云 美 来 SCRM</h1>
            <p class="logo_des">私 域 流 量 运 营 时 代 的 全 新 增 长 引 擎</p>
        </div>
    </div>
	<div class="container box_right">
        <div>
            <h2><?=Html::img('@web/images/logo_0219.png',['alt'=>'云美来'])?></h2>
            <?= Breadcrumbs::widget([
                'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
            ]) ?>
            <div class="form_title">账号登录</div>
            <?= Alert::widget() ?>
            <?php
                use yii\bootstrap\ActiveForm;
                //$this->title = '账号登录';
                //$this->params['breadcrumbs'][] = $this->title;
            ?>
            <div class="site-login">
                <h1><?= Html::encode($this->title) ?></h1>
                <?php $form = ActiveForm::begin([
                    'id' => 'login-form',
                    'layout' => 'horizontal',
                    'fieldConfig' => [
                        'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
                        'labelOptions' => ['class' => 'col-lg-1 control-label'],
                    ],
                    'action'=>'/admin/index/login'
                ]); ?>

                <div class="form-group field-adminloginform-account required has-success">
                    <div class="col-lg-3 div_account">
                        <input type="text" id="adminloginform-account" class="form-control" name="AdminLoginForm[account]" autofocus="" aria-required="true" aria-invalid="false" placeholder="请输入账号"></div>
                    <div class="col-lg-8">
                        <p class="help-block help-block-error "></p>
                    </div>
                </div>
                <div class="form-group field-adminloginform-password required has-success">
                    <div class="col-lg-3 div_password">
                        <input type="password" placeholder="请输入登录密码" id="adminloginform-password" class="form-control"  name="AdminLoginForm[password]" value="" aria-required="true" aria-invalid="false">
                    </div>
                    <div class="col-lg-8">
                        <p class="help-block help-block-error "></p>
                    </div>
                </div>

                <!--<div class="form-group">
                    <div class="col-lg-offset-1 col-lg-11">
                        <?/*= Html::submitButton('登录', ['class' => 'btn btn-primary', 'name' => 'login-button']) */?>
                    </div>
                </div>-->
                <div class="form-group">
                    <div class="col-lg-11">
                        <button type="submit" class="btn btn-primary" name="login-button">登 录</button>
                    </div>
                </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
	</div>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>




