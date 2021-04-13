<?php
	/**
	 * Created by PhpStorm.
	 * User: wangpan
	 * Date: 2020/8/14
	 * Time: 15:27
	 */

	use \yii\helpers\Html;

	$this->title                   = '跑客户数据';
	$this->params['breadcrumbs'][] = $this->title;
?>
<?=Html::cssFile('@web/css/ucenter.css')?>
<?=Html::jsFile('@web/js/ucenter.js')?>
<h1><?= Html::encode($this->title) ?></h1>

<div class="row">
	<div class="col-lg-12">
		<?= \yii\bootstrap\Progress::widget([
			'id'         => 're-push-wx-event-id-asc',
			'percent'    => 0,
			'label'      => '<em>0%</em>',
			'barOptions' => ['id' => 'process-bar-asc', 'class' => 'progress-bar-success'],
			'options'    => ['class' => 'active progress-striped']
		]); ?>
	</div>
	<div class="col-lg-12 text-center">
		<?= \yii\bootstrap\Button::widget([
			'id'      => 'update-re-push-wx-event-union-id-asc',
			'label'   => '正序推送',
			'options' => ['class' => 'btn btn-success', 'data-action' => \yii\helpers\Url::to(['/admin/index/custom', 'sort' => 0], true)],
		]); ?>
	</div>
</div>

<br />

<div class="row">
	<div class="col-lg-12">
		<?= \yii\bootstrap\Progress::widget([
			'id'         => 're-push-wx-event-id-desc',
			'percent'    => 0,
			'label'      => '<em>0%</em>',
			'barOptions' => ['id' => 'process-bar-desc', 'class' => 'progress-bar-success'],
			'options'    => ['class' => 'active progress-striped']
		]); ?>
	</div>
	<div class="col-lg-12 text-center">
		<?= \yii\bootstrap\Button::widget([
			'id'      => 'update-re-push-wx-event-union-id-desc',
			'label'   => '倒序推送',
			'options' => ['class' => 'btn btn-success', 'data-action' => \yii\helpers\Url::to(['/admin/index/custom', 'sort' => 1], true)],
		]); ?>
	</div>
</div>
