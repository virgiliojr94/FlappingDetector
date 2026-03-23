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

if (!function_exists('flapping_sort_header')) {
	function flapping_sort_header(string $field, string $label, array $data): string {
		$is_current = $data['sort'] === $field;
		$next_sortorder = $is_current && $data['sortorder'] === 'ASC' ? 'DESC' : 'ASC';
		$indicator = $is_current ? ($data['sortorder'] === 'ASC' ? ' ▲' : ' ▼') : '';

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'flapping.view')
			->setArgument('time_window', $data['time_window'])
			->setArgument('min_flaps', $data['min_flaps'])
			->setArgument('groupid', $data['groupid'])
			->setArgument('sort', $field)
			->setArgument('sortorder', $next_sortorder);

		return sprintf(
			'<th><a href="%s">%s%s</a></th>',
			htmlspecialchars($url->getUrl()),
			htmlspecialchars($label),
			$indicator
		);
	}
}

$counts = ['high' => 0, 'medium' => 0, 'low' => 0];
foreach ($data['flapping'] as $flapping_item) {
	$counts[$flapping_item['severity']]++;
}
?>

<div class="flapping-page">
	<div class="flapping-header">
		<h1><?= _('Flapping Detector') ?></h1>
		<p class="flapping-subtitle">
			<?= _('Triggers changing state too frequently — PROBLEM ↔ OK — within a sliding time window.') ?>
		</p>
	</div>

	<div class="flapping-filters">
		<form method="get" action="<?= (new CUrl('zabbix.php'))->setArgument('action', 'flapping.view')->getUrl() ?>">
			<input type="hidden" name="action" value="flapping.view">

			<div class="filter-group">
				<label><?= _('Time window') ?></label>
				<select name="time_window" class="filter-select">
					<?php foreach ([1 => '1h', 6 => '6h', 12 => '12h', 24 => '24h', 168 => '7d'] as $value => $label): ?>
						<option value="<?= $value ?>" <?= $data['time_window'] == $value ? 'selected' : '' ?>>
							<?= $label ?>
						</option>
					<?php endforeach ?>
				</select>
			</div>

			<div class="filter-group">
				<label><?= _('Min flips') ?></label>
				<input
					type="number"
					name="min_flaps"
					value="<?= $data['min_flaps'] ?>"
					min="2"
					max="100"
					class="filter-input-num"
				>
			</div>

			<div class="filter-group">
				<label><?= _('Host group') ?></label>
				<select name="groupid" class="filter-select">
					<option value="0"><?= _('All groups') ?></option>
					<?php foreach ($data['groups'] as $group): ?>
						<option value="<?= $group['groupid'] ?>" <?= $data['groupid'] == $group['groupid'] ? 'selected' : '' ?>>
							<?= htmlspecialchars($group['name']) ?>
						</option>
					<?php endforeach ?>
				</select>
			</div>

			<button type="submit" class="btn-apply"><?= _('Apply') ?></button>
		</form>
	</div>

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

	<?php if ($data['flapping']): ?>
		<div class="flapping-table-wrap">
			<table class="flapping-table">
				<thead>
					<tr>
						<th><?= _('Severity') ?></th>
						<?= flapping_sort_header('host', _('Host'), $data) ?>
						<?= flapping_sort_header('name', _('Trigger'), $data) ?>
						<?= flapping_sort_header('priority', _('Priority'), $data) ?>
						<?= flapping_sort_header('flap_count', _('Flips'), $data) ?>
						<?= flapping_sort_header('flap_rate', _('Rate /h'), $data) ?>
						<?= flapping_sort_header('last_clock', _('Last flip'), $data) ?>
						<th><?= _('State') ?></th>
						<th><?= _('History') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($data['flapping'] as $flapping_item): ?>
						<tr class="flap-row severity-<?= $flapping_item['severity'] ?>">
							<td>
								<span class="flap-badge <?= $flapping_item['severity'] ?>">
									<?= strtoupper($flapping_item['severity']) ?>
								</span>
							</td>
							<td class="cell-host">
								<a href="<?= (new CUrl('zabbix.php'))
									->setArgument('action', 'host.edit')
									->setArgument('hostid', $flapping_item['hostid'])
									->getUrl() ?>">
									<?= htmlspecialchars($flapping_item['host']) ?>
								</a>
							</td>
							<td class="cell-trigger"><?= htmlspecialchars($flapping_item['name']) ?></td>
							<td><?= CSeverityHelper::getName((int) $flapping_item['priority']) ?></td>
							<td class="cell-count">
								<strong><?= $flapping_item['flap_count'] ?></strong>
								<span class="cell-sub"><?= _n('flip', 'flips', $flapping_item['flap_count']) ?></span>
							</td>
							<td class="cell-rate"><?= $flapping_item['flap_rate'] ?>/h</td>
							<td class="cell-time">
								<?= $flapping_item['last_clock'] > 0 ? zbx_date2str(DATE_TIME_FORMAT, $flapping_item['last_clock']) : '—' ?>
							</td>
							<td>
								<span class="state-dot <?= $flapping_item['last_value'] ? 'problem' : 'ok' ?>"></span>
								<?= $flapping_item['last_value'] ? _('PROBLEM') : _('OK') ?>
							</td>
							<td>
								<a
									href="<?= (new CUrl('zabbix.php'))
										->setArgument('action', 'flapping.history')
										->setArgument('triggerid', $flapping_item['triggerid'])
										->setArgument('time_window', $data['time_window'])
										->getUrl() ?>"
									class="btn-history"
									title="<?= _('View flip history') ?>"
								>
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
				<?= sprintf(
					_('Criteria: ≥ %d state flips in the last %s.'),
					$data['min_flaps'],
					$data['time_window'] >= 168 ? '7 days' : $data['time_window'].'h'
				) ?>
			</p>
		</div>
	<?php endif ?>
</div>
