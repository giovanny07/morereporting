<?php

namespace Modules\MoreReporting\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;

use Modules\MoreReporting\Includes\ReportStorage;

class ReportStatus extends CController {

	protected function checkInput(): bool {
		$fields = [
			'reportid' => 'required|id',
			'status' =>	'required|in '.ZBX_REPORT_STATUS_ENABLED.','.ZBX_REPORT_STATUS_DISABLED
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
		ReportStorage::setStatus((int) $this->getInput('reportid'), (int) $this->getInput('status'));

		CMessageHelper::setSuccessTitle((int) $this->getInput('status') == ZBX_REPORT_STATUS_ENABLED
			? _('Report enabled')
			: _('Report disabled')
		);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list')
		);
		$this->setResponse($response);
	}
}
