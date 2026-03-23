<?php

namespace Modules\FlappingDetector\Actions;

use API;
use CController;
use CControllerResponseData;
use CRoleHelper;
use CWebUser;

class CControllerFlappingView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'time_window' => 'in 1,6,12,24,168',
			'min_flaps'   => 'int32',
			'groupid'     => 'id',
			'page'        => 'ge 1',
			'sort'        => 'in flap_count,flap_rate,last_clock,host,priority',
			'sortorder'   => 'in ASC,DESC',
		];
		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$time_window = (int) $this->getInput('time_window', 24);
		$min_flaps   = (int) $this->getInput('min_flaps', 3);
		$groupid     = $this->getInput('groupid', 0);
		$sort        = $this->getInput('sort', 'flap_count');
		$sortorder   = $this->getInput('sortorder', 'DESC');

		$flapping = $this->detectFlapping($time_window, $min_flaps, $groupid);

		// Sort
		usort($flapping, function ($a, $b) use ($sort, $sortorder) {
			$cmp = match ($sort) {
				'host'       => strcmp($a['host'], $b['host']),
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

		$this->setResponse(new CControllerResponseData([
			'title'       => _('Flapping Detector'),
			'flapping'    => $flapping,
			'time_window' => $time_window,
			'min_flaps'   => $min_flaps,
			'groupid'     => $groupid,
			'groups'      => $groups,
			'sort'        => $sort,
			'sortorder'   => $sortorder,
			'total'       => count($flapping),
			'user'        => ['debug_mode' => $this->getDebugMode()],
		]));
	}

	// -------------------------------------------------------------------------
	// Core flapping detection algorithm
	// Inspired by Cloudflare's definition: an alert that changes state too
	// frequently. We count PROBLEM↔OK transitions within a sliding window.
	// -------------------------------------------------------------------------
	private function detectFlapping(int $time_window_h, int $min_flaps, int $groupid): array {
		$time_from = time() - ($time_window_h * 3600);

		$event_options = [
			'output'            => ['eventid', 'objectid', 'clock', 'value', 'name'],
			'source'            => EVENT_SOURCE_TRIGGERS,
			'time_from'         => $time_from,
			'sortfield'         => ['objectid', 'clock'],
			'sortorder'         => 'ASC',
			'selectHosts'       => ['hostid', 'name'],
			'selectRelatedObject' => ['triggerid', 'description', 'priority'],
		];

		if ($groupid) {
			$event_options['groupids'] = [$groupid];
		}

		$events = API::Event()->get($event_options);
		if (!$events) {
			return [];
		}

		// Group events by triggerid
		$by_trigger = [];
		foreach ($events as $ev) {
			$tid = $ev['objectid'];
			if (!isset($by_trigger[$tid])) {
				$host = $ev['hosts'][0] ?? [];
				$obj  = $ev['relatedObject'] ?? [];
				$by_trigger[$tid] = [
					'triggerid' => $tid,
					'name'      => $obj['description'] ?? $ev['name'],
					'priority'  => (int) ($obj['priority'] ?? 0),
					'host'      => $host['name'] ?? '',
					'hostid'    => $host['hostid'] ?? 0,
					'events'    => [],
					'last_clock'=> 0,
					'last_value'=> -1,
				];
			}
			$by_trigger[$tid]['events'][] = [
				'clock' => (int) $ev['clock'],
				'value' => (int) $ev['value'],
			];
			if ((int) $ev['clock'] > $by_trigger[$tid]['last_clock']) {
				$by_trigger[$tid]['last_clock'] = (int) $ev['clock'];
				$by_trigger[$tid]['last_value'] = (int) $ev['value'];
			}
		}

		$flapping = [];
		foreach ($by_trigger as $tid => $data) {
			$evts        = $data['events'];
			$transitions = 0;

			for ($i = 1, $n = count($evts); $i < $n; $i++) {
				if ($evts[$i]['value'] !== $evts[$i - 1]['value']) {
					$transitions++;
				}
			}

			if ($transitions < $min_flaps) {
				continue;
			}

			// flap_rate = transitions per hour in the window
			$flap_rate = $time_window_h > 0
				? round($transitions / $time_window_h, 2)
				: $transitions;

			$flapping[] = [
				'triggerid'    => $tid,
				'name'         => $data['name'],
				'priority'     => $data['priority'],
				'host'         => $data['host'],
				'hostid'       => $data['hostid'],
				'flap_count'   => $transitions,
				'flap_rate'    => $flap_rate,
				'last_clock'   => $data['last_clock'],
				'last_value'   => $data['last_value'], // 0=OK 1=PROBLEM
				'severity'     => $this->flapSeverity($transitions, $flap_rate),
				'events'       => $evts, // used by history view
			];
		}

		return $flapping;
	}

	/**
	 * Severity thresholds (aligned with Cloudflare's swimlane coloring):
	 *   low    — 3–4 flips, rate < 0.5/h  → yellow
	 *   medium — 5–9 flips or rate ≥ 0.5/h → orange
	 *   high   — 10+ flips or rate ≥ 1/h   → red
	 */
	private function flapSeverity(int $flaps, float $rate): string {
		if ($flaps >= 10 || $rate >= 1.0) return 'high';
		if ($flaps >= 5  || $rate >= 0.5) return 'medium';
		return 'low';
	}
}
