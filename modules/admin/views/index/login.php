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
	$this->title = '登录';
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
</head>
<body>
<?php $this->beginBody() ?>
<div class="wrap">
	<div class="container">
		<?= Breadcrumbs::widget([
			'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
		]) ?>
		<?= Alert::widget() ?>
		<?php
			use yii\bootstrap\ActiveForm;
			$this->title = '登录';
			$this->params['breadcrumbs'][] = $this->title;
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

			<?= $form->field($model, 'account')->label('帐号')->textInput(['autofocus' => true]) ?>

			<?= $form->field($model, 'password')->label('密码')->passwordInput() ?>

			<div class="form-group">
				<div class="col-lg-offset-1 col-lg-11">
					<?= Html::submitButton('登录', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
				</div>
			</div>

			<?php ActiveForm::end(); ?>
		</div>
	</div>
</div>

<footer class="footer">
	<div class="container">
		<p class="pull-left">&copy; My Company <?= date('Y') ?></p>

		<p class="pull-right"></p>
	</div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>




