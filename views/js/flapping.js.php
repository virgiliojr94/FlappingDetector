<?php
// Passes PHP-side data to JS (badge config)
?>
<script>
window.FlappingConfig = {
	badgeEndpoint: <?= json_encode((new CUrl('zabbix.php'))
		->setArgument('action', 'flapping.badge.data')
		->getUrl()) ?>,
	timeWindow:    <?= json_encode($data['time_window'] ?? 6) ?>,
	minFlaps:      <?= json_encode($data['min_flaps'] ?? 3) ?>,
	viewUrl:       <?= json_encode((new CUrl('zabbix.php'))
		->setArgument('action', 'flapping.view')
		->getUrl()) ?>,
	historyUrl:    <?= json_encode((new CUrl('zabbix.php'))
		->setArgument('action', 'flapping.history')
		->getUrl()) ?>,
};
</script>
