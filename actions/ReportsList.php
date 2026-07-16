<?php

namespace Modules\MoreReporting\Actions;

use CController, CControllerResponseData;

class ReportsList extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$data = [
			'title' => _('MoreReporting'),
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('MoreReporting'));
		$this->setResponse($response);
	}
}
