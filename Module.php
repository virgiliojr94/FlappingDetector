<?php

namespace Modules\FlappingDetector;

use APP;
use CMenuItem;
use Zabbix\Core\CModule;

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
}
