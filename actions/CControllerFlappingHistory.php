<?php

namespace Modules\FlappingDetector\Actions;

use API;
use CControllerResponseData;
use CRoleHelper;

class CControllerFlappingHistory extends CControllerFlappingBase {

	protected function checkInput(): bool {
		$fields = [
			'triggerid'   => 'required|id',
			'time_window' => 'in 1,6,12,24,168',
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$triggerid   = $this->getInput('triggerid');
		$time_window = (int) $this->getInput('time_window', $this->getDefaultTimeWindow());
		$time_from   = time() - ($time_window * 3600);

		$triggers = API::Trigger()->get([
			'output'      => ['triggerid', 'description', 'priority', 'status'],
			'triggerids'  => [$triggerid],
			'selectHosts' => ['hostid', 'name'],
		]);
		$trigger = $triggers ? $triggers[0] : null;

		$events = API::Event()->get([
			'output'    => ['eventid', 'clock', 'value', 'acknowledged'],
			'source'    => EVENT_SOURCE_TRIGGERS,
			'object'    => EVENT_OBJECT_TRIGGER,
			'objectids' => [$triggerid],
			'time_from' => $time_from,
			'sortfield' => 'clock',
			'sortorder' => 'ASC',
		]);

		$transitions = 0;
		$hourly_buckets = array_fill(0, $time_window, 0);

		for ($i = 1, $n = count($events); $i < $n; $i++) {
			if ((int) $events[$i]['value'] !== (int) $events[$i - 1]['value']) {
				$transitions++;
				$age_h = (int) floor((time() - (int) $events[$i]['clock']) / 3600);
				$bucket = max(0, min($age_h, $time_window - 1));
				$hourly_buckets[$time_window - 1 - $bucket]++;
			}
		}

		$flap_rate = $time_window > 0 ? round($transitions / $time_window, 2) : 0;

		$response = new CControllerResponseData([
			'trigger'        => $trigger,
			'events'         => $events,
			'transitions'    => $transitions,
			'flap_rate'      => $flap_rate,
			'hourly_buckets' => $hourly_buckets,
			'time_window'    => $time_window,
			'user'           => ['debug_mode' => $this->getDebugMode()],
		]);
		$response->setTitle(_('Flapping History'));

		$this->setResponse($response);
	}
}
