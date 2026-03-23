<?php

namespace Modules\FlappingDetector\Actions;

use API;
use CRoleHelper;

class CControllerFlappingBadge extends CControllerFlappingBase {

	protected function checkInput(): bool {
		$fields = [
			'triggerids'  => 'array_id',
			'time_window' => 'in 1,6,12,24',
			'min_flaps'   => 'ge 2|le 100',
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setJsonResponse([
				'error' => [
					'messages' => $this->getValidationErrorMessages()
				]
			]);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$triggerids  = $this->getInput('triggerids', []);
		$time_window = (int) $this->getInput('time_window', $this->getDefaultTimeWindow());
		$min_flaps   = (int) $this->getInput('min_flaps', $this->getDefaultMinFlaps());

		if (!$triggerids) {
			$this->setJsonResponse(['flapping' => []]);
			return;
		}

		$time_from = time() - ($time_window * 3600);
		$events = API::Event()->get([
			'output'    => ['objectid', 'clock', 'value'],
			'source'    => EVENT_SOURCE_TRIGGERS,
			'object'    => EVENT_OBJECT_TRIGGER,
			'objectids' => $triggerids,
			'time_from' => $time_from,
			'sortfield' => ['objectid', 'clock'],
			'sortorder' => 'ASC',
		]);

		$grouped_values = [];

		foreach ($events as $event) {
			$triggerid = $event['objectid'];
			$grouped_values[$triggerid][] = (int) $event['value'];
		}

		$result = [];

		foreach ($grouped_values as $triggerid => $values) {
			$transitions = 0;

			for ($i = 1, $n = count($values); $i < $n; $i++) {
				if ($values[$i] !== $values[$i - 1]) {
					$transitions++;
				}
			}

			if ($transitions < $min_flaps) {
				continue;
			}

			$rate = round($transitions / $time_window, 2);
			$result[$triggerid] = [
				'flap_count' => $transitions,
				'flap_rate'  => $rate,
				'severity'   => $this->classifyFlapping($transitions, $rate),
			];
		}

		$this->setJsonResponse(['flapping' => $result]);
	}
}
