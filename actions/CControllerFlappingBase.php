<?php

namespace Modules\FlappingDetector\Actions;

use CController;
use CControllerResponseData;

abstract class CControllerFlappingBase extends CController {

	private const DEFAULT_TIME_WINDOW = 6;
	private const DEFAULT_MIN_FLAPS = 3;

	private static ?array $settings = null;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function getDefaultTimeWindow(): int {
		return (int) $this->getModuleSettings()['default_time_window'];
	}

	protected function getDefaultMinFlaps(): int {
		return (int) $this->getModuleSettings()['default_min_flaps'];
	}

	protected function classifyFlapping(int $flaps, float $rate): string {
		$thresholds = $this->getModuleSettings()['severity_thresholds'];

		if ($flaps >= $thresholds['high']['min_flaps'] || $rate >= $thresholds['high']['min_rate_per_hour']) {
			return 'high';
		}

		if ($flaps >= $thresholds['medium']['min_flaps'] || $rate >= $thresholds['medium']['min_rate_per_hour']) {
			return 'medium';
		}

		return 'low';
	}

	protected function setJsonResponse(array $payload): void {
		$this->setResponse(
			(new CControllerResponseData([
				'main_block' => json_encode($payload)
			]))->disableView()
		);
	}

	protected function getValidationErrorMessages(): array {
		return array_column(get_and_clear_messages(), 'message');
	}

	private function getModuleSettings(): array {
		if (self::$settings !== null) {
			return self::$settings;
		}

		$settings = [
			'default_time_window' => self::DEFAULT_TIME_WINDOW,
			'default_min_flaps' => self::DEFAULT_MIN_FLAPS,
			'severity_thresholds' => [
				'high' => ['min_flaps' => 10, 'min_rate_per_hour' => 1.0],
				'medium' => ['min_flaps' => 5, 'min_rate_per_hour' => 0.5],
				'low' => ['min_flaps' => 3, 'min_rate_per_hour' => 0.0]
			]
		];

		$config_file = dirname(__DIR__).'/config/flapping_config.json';

		if (is_readable($config_file)) {
			$config = json_decode(file_get_contents($config_file), true);

			if (is_array($config)) {
				$settings['default_time_window'] = (int) ($config['default_time_window'] ?? $settings['default_time_window']);
				$settings['default_min_flaps'] = (int) ($config['default_min_flaps'] ?? $settings['default_min_flaps']);

				if (isset($config['severity_thresholds']) && is_array($config['severity_thresholds'])) {
					$settings['severity_thresholds'] = array_replace_recursive(
						$settings['severity_thresholds'],
						$config['severity_thresholds']
					);
				}
			}
		}

		self::$settings = $settings;

		return self::$settings;
	}
}
