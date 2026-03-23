<?php

namespace Modules\FlappingDetector\Actions;

use API;
use CControllerResponseData;
use CRoleHelper;

class CControllerFlappingView extends CControllerFlappingBase {

	protected function checkInput(): bool {
		$fields = [
			'time_window' => 'in 1,6,12,24,168',
			'min_flaps'   => 'ge 2|le 100',
			'groupid'     => 'ge 0',
			'sort'        => 'in flap_count,flap_rate,last_clock,host,name,priority',
			'sortorder'   => 'in ASC,DESC',
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$time_window = (int) $this->getInput('time_window', $this->getDefaultTimeWindow());
		$min_flaps   = (int) $this->getInput('min_flaps', $this->getDefaultMinFlaps());
		$groupid     = (int) $this->getInput('groupid', 0);
		$sort        = $this->getInput('sort', 'flap_count');
		$sortorder   = $this->getInput('sortorder', 'DESC');

		$flapping = $this->detectFlapping($time_window, $min_flaps, $groupid);

		usort($flapping, function (array $a, array $b) use ($sort, $sortorder): int {
			$cmp = match ($sort) {
				'host'       => strcmp($a['host'], $b['host']),
				'name'       => strcmp($a['name'], $b['name']),
				'priority'   => $a['priority'] <=> $b['priority'],
				'last_clock' => $a['last_clock'] <=> $b['last_clock'],
				'flap_rate'  => $a['flap_rate'] <=> $b['flap_rate'],
				default      => $a['flap_count'] <=> $b['flap_count'],
			};

			return $sortorder === 'DESC' ? -$cmp : $cmp;
		});

		$groups = API::HostGroup()->get([
			'output'    => ['groupid', 'name'],
			'sortfield' => 'name',
		]);

		$response = new CControllerResponseData([
			'flapping'    => $flapping,
			'time_window' => $time_window,
			'min_flaps'   => $min_flaps,
			'groupid'     => $groupid,
			'groups'      => $groups,
			'sort'        => $sort,
			'sortorder'   => $sortorder,
			'total'       => count($flapping),
			'user'        => ['debug_mode' => $this->getDebugMode()],
		]);
		$response->setTitle(_('Flapping Detector'));

		$this->setResponse($response);
	}

	private function detectFlapping(int $time_window_h, int $min_flaps, int $groupid): array {
		$time_from = time() - ($time_window_h * 3600);

		$event_options = [
			'output'              => ['eventid', 'objectid', 'clock', 'value', 'name'],
			'source'              => EVENT_SOURCE_TRIGGERS,
			'object'              => EVENT_OBJECT_TRIGGER,
			'time_from'           => $time_from,
			'sortfield'           => ['objectid', 'clock'],
			'sortorder'           => 'ASC',
			'selectHosts'         => ['hostid', 'name'],
			'selectRelatedObject' => ['triggerid', 'description', 'priority'],
		];

		if ($groupid) {
			$event_options['groupids'] = [$groupid];
		}

		$events = API::Event()->get($event_options);
		if (!$events) {
			return [];
		}

		$by_trigger = [];

		foreach ($events as $event) {
			$triggerid = $event['objectid'];

			if (!array_key_exists($triggerid, $by_trigger)) {
				$host = $event['hosts'][0] ?? [];
				$related_object = $event['relatedObject'] ?? [];

				$by_trigger[$triggerid] = [
					'triggerid' => $triggerid,
					'name' => $related_object['description'] ?? $event['name'],
					'priority' => (int) ($related_object['priority'] ?? 0),
					'host' => $host['name'] ?? '',
					'hostid' => $host['hostid'] ?? 0,
					'events' => [],
					'last_clock' => 0,
					'last_value' => -1,
				];
			}

			$by_trigger[$triggerid]['events'][] = [
				'clock' => (int) $event['clock'],
				'value' => (int) $event['value'],
			];

			if ((int) $event['clock'] > $by_trigger[$triggerid]['last_clock']) {
				$by_trigger[$triggerid]['last_clock'] = (int) $event['clock'];
				$by_trigger[$triggerid]['last_value'] = (int) $event['value'];
			}
		}

		$flapping = [];

		foreach ($by_trigger as $triggerid => $data) {
			$events = $data['events'];
			$transitions = 0;

			for ($i = 1, $n = count($events); $i < $n; $i++) {
				if ($events[$i]['value'] !== $events[$i - 1]['value']) {
					$transitions++;
				}
			}

			if ($transitions < $min_flaps) {
				continue;
			}

			$flap_rate = $time_window_h > 0
				? round($transitions / $time_window_h, 2)
				: (float) $transitions;

			$flapping[] = [
				'triggerid' => $triggerid,
				'name' => $data['name'],
				'priority' => $data['priority'],
				'host' => $data['host'],
				'hostid' => $data['hostid'],
				'flap_count' => $transitions,
				'flap_rate' => $flap_rate,
				'last_clock' => $data['last_clock'],
				'last_value' => $data['last_value'],
				'severity' => $this->classifyFlapping($transitions, $flap_rate),
				'events' => $events,
			];
		}

		return $flapping;
	}
}
