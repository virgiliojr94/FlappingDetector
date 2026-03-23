<?php

namespace Modules\FlappingDetector\Actions;

use API;
use CController;
use CControllerResponseData;
use CRoleHelper;

/**
 * Lightweight endpoint called by flapping-badge.js on the Problems page.
 * Accepts a list of triggerids and returns their flapping status.
 * Designed to be fast: uses a short fixed window (1h) for badge purposes.
 */
class CControllerFlappingBadge extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'triggerids'  => 'array_id',
			'time_window' => 'in 1,6,12,24',
			'min_flaps'   => 'int32',
		];
		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$triggerids  = $this->getInput('triggerids', []);
		$time_window = (int) $this->getInput('time_window', 6);
		$min_flaps   = (int) $this->getInput('min_flaps', 3);

		$result = [];

		if (!$triggerids) {
			$this->setResponse(new CControllerResponseData(['flapping' => $result]));
			return;
		}

		$time_from = time() - ($time_window * 3600);

		$events = API::Event()->get([
			'output'    => ['objectid', 'clock', 'value'],
			'source'    => EVENT_SOURCE_TRIGGERS,
			'objectids' => $triggerids,
			'time_from' => $time_from,
			'sortfield' => ['objectid', 'clock'],
			'sortorder' => 'ASC',
		]);

		// Group and count transitions
		$by_trigger = [];
		foreach ($events as $ev) {
			$tid = $ev['objectid'];
			$by_trigger[$tid][] = (int) $ev['value'];
		}

		foreach ($by_trigger as $tid => $values) {
			$transitions = 0;
			for ($i = 1, $n = count($values); $i < $n; $i++) {
				if ($values[$i] !== $values[$i - 1]) $transitions++;
			}
			if ($transitions >= $min_flaps) {
				$rate = round($transitions / $time_window, 2);
				$result[$tid] = [
					'flap_count' => $transitions,
					'flap_rate'  => $rate,
					'severity'   => $this->severity($transitions, $rate),
				];
			}
		}

		$this->setResponse(new CControllerResponseData(['flapping' => $result]));
	}

	private function severity(int $flaps, float $rate): string {
		if ($flaps >= 10 || $rate >= 1.0) return 'high';
		if ($flaps >= 5  || $rate >= 0.5) return 'medium';
		return 'low';
	}
}
