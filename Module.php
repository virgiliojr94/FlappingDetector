<?php

namespace Modules\FlappingDetector;

use APP;
use CController;
use Zabbix\Core\CModule;
use CMenuItem;

class Module extends CModule {

	public function init(): void {
		APP::Component()->get('menu.main')
			->findOrAdd(_('Monitoring'))
			->getSubmenu()
			->insertAfter(_('Problems'),
				(new CMenuItem(_('Flapping Detector')))
					->setAction('flapping.view')
			);
	}

	public function getActions(): array {
		return [
			'flapping.view' => [
				'class'  => 'Modules\\FlappingDetector\\Actions\\CControllerFlappingView',
				'layout' => 'layout.htmlpage',
				'view'   => 'flapping.view',
			],
			'flapping.history' => [
				'class'  => 'Modules\\FlappingDetector\\Actions\\CControllerFlappingHistory',
				'layout' => 'layout.htmlpage',
				'view'   => 'flapping.history.view',
			],
			'flapping.badge.data' => [
				'class' => 'Modules\\FlappingDetector\\Actions\\CControllerFlappingBadge',
			],
		];
	}
}
