<?php

namespace Modules\MoreReporting\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;
use CWebUser;

use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\ReportTypeRegistry;

class ReportUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'reportid' =>		'id',
			'name' =>			'required|string|not_empty',
			'report_type' =>	'required|string|not_empty',
			'groupids' =>		'array_db hosts_groups.groupid',
			'pattern' =>		'string',
			'slo' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret && !ReportTypeRegistry::exists($this->getInput('report_type'))) {
			error(_('Unknown report type.'));
			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$config = [
			'groupids' => $this->getInput('groupids', []),
			'pattern' => $this->getInput('pattern', ''),
			'slo' => $this->getInput('slo', '99.9')
		];

		$name = $this->getInput('name');
		$report_type = $this->getInput('report_type');

		if ($this->hasInput('reportid')) {
			ReportStorage::update((int) $this->getInput('reportid'), $name, $report_type, $config);
			CMessageHelper::setSuccessTitle(_('Report updated'));
		}
		else {
			ReportStorage::create($name, $report_type, $config, (int) CWebUser::$data['userid']);
			CMessageHelper::setSuccessTitle(_('Report created'));
		}

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list')
		);
		$this->setResponse($response);
	}
}
