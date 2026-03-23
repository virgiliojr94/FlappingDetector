<?php
/**
 * @var array  $data
 * @var array  $data['flapping']
 * @var int    $data['time_window']
 * @var int    $data['min_flaps']
 * @var int    $data['groupid']
 * @var array  $data['groups']
 * @var string $data['sort']
 * @var string $data['sortorder']
 * @var int    $data['total']
 */

$this->includeJsFile('flapping.js.php');
page_header();
?>
<link rel="stylesheet" href="<?= $this->getModule()->getDir() ?>/assets/css/flapping.css">

<div class="flapping-page">

	<!-- Header -->
	<div class="flapping-header">
		<h1><?= _('Flapping Detector') ?></h1>
		<p class="flapping-subtitle">
			<?= _('Triggers changing state too frequently — PROBLEM ↔ OK — within a sliding time window.') ?>
		</p>
	</div>

	<!-- Filter bar -->
	<div class="flapping-filters">
		<form method="get" action="<?= (new CUrl('zabbix.php'))->setArgument('action', 'flapping.view') ?>">
			<input type="hidden" name="action" value="flapping.view">

			<div class="filter-group">
				<label><?= _('Time window') ?></label>
				<select name="time_window" class="filter-select">
					<?php foreach ([1 => '1h', 6 => '6h', 12 => '12h', 24 => '24h', 168 => '7d'] as $val => $label): ?>
						<option value="<?= $val ?>" <?= $data['time_window'] == $val ? 'selected' : '' ?>>
							<?= $label ?>
						</option>
					<?php endforeach ?>
				</select>
			</div>

			<div class="filter-group">
				<label><?= _('Min flips') ?></label>
				<input type="number" name="min_flaps" value="<?= $data['min_flaps'] ?>"
					min="2" max="100" class="filter-input-num">
			</div>

			<div class="filter-group">
				<label><?= _('Host group') ?></label>
				<select name="groupid" class="filter-select">
					<option value="0"><?= _('All groups') ?></option>
					<?php foreach ($data['groups'] as $g): ?>
						<option value="<?= $g['groupid'] ?>" <?= $data['groupid'] == $g['groupid'] ? 'selected' : '' ?>>
							<?= htmlspecialchars($g['name']) ?>
						</option>
					<?php endforeach ?>
				</select>
			</div>

			<button type="submit" class="btn-apply"><?= _('Apply') ?></button>
		</form>
	</div>

	<!-- Summary pills -->
	<?php
	$counts = ['high' => 0, 'medium' => 0, 'low' => 0];
	foreach ($data['flapping'] as $f) $counts[$f['severity']]++;
	?>
	<div class="flapping-summary">
		<span class="summary-pill total"><?= $data['total'] ?> <?= _('flapping triggers') ?></span>
		<?php if ($counts['high']): ?>
			<span class="summary-pill high"><?= $counts['high'] ?> <?= _('high') ?></span>
		<?php endif ?>
		<?php if ($counts['medium']): ?>
			<span class="summary-pill medium"><?= $counts['medium'] ?> <?= _('medium') ?></span>
		<?php endif ?>
		<?php if ($counts['low']): ?>
			<span class="summary-pill low"><?= $counts['low'] ?> <?= _('low') ?></span>
		<?php endif ?>
	</div>

	<!-- Table -->
	<?php if ($data['flapping']): ?>
	<div class="flapping-table-wrap">
		<table class="flapping-table">
			<thead>
				<tr>
					<th><?= _('Severity') ?></th>
					<?= $this->sortHeader('host',       _('Host'),       $data) ?>
					<?= $this->sortHeader('name',       _('Trigger'),    $data) ?>
					<?= $this->sortHeader('priority',   _('Priority'),   $data) ?>
					<?= $this->sortHeader('flap_count', _('Flips'),      $data) ?>
					<?= $this->sortHeader('flap_rate',  _('Rate /h'),    $data) ?>
					<?= $this->sortHeader('last_clock', _('Last flip'),  $data) ?>
					<th><?= _('State') ?></th>
					<th><?= _('History') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data['flapping'] as $f): ?>
				<tr class="flap-row severity-<?= $f['severity'] ?>">
					<td>
						<span class="flap-badge <?= $f['severity'] ?>">
							<?= strtoupper($f['severity']) ?>
						</span>
					</td>
					<td class="cell-host">
						<a href="<?= (new CUrl('zabbix.php'))
							->setArgument('action', 'host.edit')
							->setArgument('hostid', $f['hostid']) ?>">
							<?= htmlspecialchars($f['host']) ?>
						</a>
					</td>
					<td class="cell-trigger"><?= htmlspecialchars($f['name']) ?></td>
					<td><?= severity_name($f['priority']) ?></td>
					<td class="cell-count">
						<strong><?= $f['flap_count'] ?></strong>
						<span class="cell-sub"><?= _n('flip', 'flips', $f['flap_count']) ?></span>
					</td>
					<td class="cell-rate"><?= $f['flap_rate'] ?>/h</td>
					<td class="cell-time">
						<?= zbx_date2str(DATE_TIME_FORMAT, $f['last_clock']) ?>
					</td>
					<td>
						<span class="state-dot <?= $f['last_value'] ? 'problem' : 'ok' ?>"></span>
						<?= $f['last_value'] ? _('PROBLEM') : _('OK') ?>
					</td>
					<td>
						<a href="<?= (new CUrl('zabbix.php'))
							->setArgument('action', 'flapping.history')
							->setArgument('triggerid', $f['triggerid'])
							->setArgument('time_window', $data['time_window']) ?>"
							class="btn-history" title="<?= _('View flip history') ?>">
							📈
						</a>
					</td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</div>
	<?php else: ?>
	<div class="flapping-empty">
		<div class="empty-icon">✅</div>
		<p><?= _('No flapping triggers detected in the selected window.') ?></p>
		<p class="empty-hint">
			<?= sprintf(_('Criteria: ≥ %d state flips in the last %s.'),
				$data['min_flaps'],
				$data['time_window'] >= 168 ? '7 days' : $data['time_window'].'h'
			) ?>
		</p>
	</div>
	<?php endif ?>

</div>

<?php
page_footer();

// Helper for sortable column headers
function severity_name(int $priority): string {
	$map = [0 => 'Not classified', 1 => 'Information', 2 => 'Warning',
	        3 => 'Average', 4 => 'High', 5 => 'Disaster'];
	return $map[$priority] ?? 'Unknown';
}
?>
