<?php
/**
 * @var array  $data
 * @var array  $data['trigger']
 * @var array  $data['events']
 * @var int    $data['transitions']
 * @var float  $data['flap_rate']
 * @var array  $data['hourly_buckets']
 * @var int    $data['time_window']
 */

$this->includeJsFile('flapping.js.php');

$module_relative_path = APP::ModuleManager()->getActionModule()->getRelativePath();
$trigger = $data['trigger'];
$host = $trigger ? ($trigger['hosts'][0]['name'] ?? '—') : '—';
?>

<script src="<?= (new CUrl($module_relative_path.'/assets/js/flapping.js'))->getUrl() ?>"></script>

<div class="flapping-page flapping-page-history">
	<div class="flap-breadcrumb">
		<a href="<?= (new CUrl('zabbix.php'))->setArgument('action', 'flapping.view')->getUrl() ?>">
			← <?= _('Flapping Detector') ?>
		</a>
	</div>

	<div class="flapping-header">
		<h1><?= _('Flip History') ?></h1>
		<p class="flapping-subtitle">
			<strong><?= htmlspecialchars($host) ?></strong>
			— <?= htmlspecialchars($trigger ? $trigger['description'] : '') ?>
		</p>
	</div>

	<div class="flap-stats-row">
		<div class="stat-card">
			<div class="stat-value"><?= $data['transitions'] ?></div>
			<div class="stat-label"><?= _('Total flips') ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value"><?= $data['flap_rate'] ?>/h</div>
			<div class="stat-label"><?= _('Flip rate') ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value"><?= count($data['events']) ?></div>
			<div class="stat-label"><?= _('Events in window') ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-value">
				<?= $data['time_window'] >= 168 ? '7d' : $data['time_window'].'h' ?>
			</div>
			<div class="stat-label"><?= _('Window') ?></div>
		</div>
	</div>

	<div class="flap-history-grid">
		<div class="flap-section flap-section-chart">
			<h3><?= _('Flip activity (hourly buckets)') ?></h3>
			<canvas id="flap-chart" height="220"></canvas>
		</div>

		<div class="flap-section flap-section-timeline">
			<h3><?= _('State timeline') ?></h3>
			<div class="flap-timeline" id="flap-timeline">
				<?php foreach ($data['events'] as $event): ?>
					<div class="tl-event <?= $event['value'] ? 'problem' : 'ok' ?>" title="<?= zbx_date2str(DATE_TIME_FORMAT, $event['clock']) ?>">
						<span class="tl-dot"></span>
						<span class="tl-label"><?= $event['value'] ? 'PROBLEM' : 'OK' ?></span>
						<span class="tl-time"><?= zbx_date2str(DATE_TIME_FORMAT_SHORT, $event['clock']) ?></span>
					</div>
				<?php endforeach ?>
			</div>
		</div>

		<div class="flap-section flap-section-recommendation flap-recommendation">
			<h3><?= _('Recommendation') ?></h3>
			<?php if ($data['flap_rate'] >= 1.0): ?>
				<p><?= _('High flip rate detected (≥ 1 flip/h). Consider increasing the <code>for duration</code> on this trigger rule to add time tolerance before firing — this prevents alerts from firing and recovering within seconds.') ?></p>
			<?php elseif ($data['transitions'] >= 5): ?>
				<p><?= _('Moderate flapping. Review the trigger threshold: the condition may be borderline. Try widening the threshold range or using a time-based function (e.g., <code>avg(5m)</code> instead of <code>last()</code>).') ?></p>
			<?php else: ?>
				<p><?= _('Low-level flapping. Monitor over a longer period. If it persists, review the trigger expression for sensitivity.') ?></p>
			<?php endif ?>
		</div>
	</div>
</div>

<script>
FlappingCharts.renderHourly(
	document.getElementById('flap-chart'),
	<?= json_encode($data['hourly_buckets']) ?>,
	<?= $data['time_window'] ?>
);
</script>
