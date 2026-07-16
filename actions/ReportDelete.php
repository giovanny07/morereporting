<?php

namespace Modules\MoreReporting\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;

use Modules\MoreReporting\Includes\ReportStorage;

class ReportDelete extends CController {

	protected function checkInput(): bool {
		$fields = [
			'reportid' => 'required|id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		ReportStorage::delete((int) $this->getInput('reportid'));

		CMessageHelper::setSuccessTitle(_('Report deleted'));

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list')
		);
		$this->setResponse($response);
	}
}
