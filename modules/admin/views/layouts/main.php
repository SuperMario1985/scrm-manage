<?php

	/* @var $this \yii\web\View */

	/* @var $content string */

	use yii\helpers\Html;
	use app\assets\AdminAppAsset;

	AdminAppAsset::register($this);
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
	<?= Html::cssFile('@web/css/bootstrap.min.css') ?>
	<?= Html::cssFile('@web/css/main.css') ?>
	<?= Html::cssFile('@web/plugins/sweetalert/sweetalert.css') ?>
	<?= Html::cssFile('@web/font-awesome/css/font-awesome.css') ?>
	<?= Html::cssFile('@web/plugins/awesome-bootstrap-checkbox/awesome-bootstrap-checkbox.css') ?>
	<?= Html::jsFile('@web/js/jquery-2.1.1.js') ?>
</head>
<body>
<?php $this->beginBody() ?>
<div id="wrapper">
	<?= $this->render('leftmenu'); ?>
	<div id="page-wrapper" class="gray-bg dashbard-1">
		<?= $this->render('top'); ?>
		<?= $content ?>
	</div>
</div>
<?php $this->endBody() ?>

<?= Html::jsFile('@web/js/jquery.metisMenu.js') ?>
<?= Html::jsFile('@web/js/bootstrap.min.js') ?>
<?= Html::jsFile('@web/plugins/sweetalert/sweetalert.min.js') ?>

<script>
	$('#side-menu').metisMenu();

	function change_color (obj, type) {
		if (type == 'in') {
			obj.css('color', '#44B549');
		} else {
			obj.css('color', '');
		}
	}
</script>
</body>
</html>
<?php $this->endPage() ?>
