<?php

namespace Modules\MoreReporting\Actions;

use API;
use CArrayHelper;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;

use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\ReportTypeRegistry;

class ReportEdit extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'reportid' => 'id'
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
		$definition = null;

		if ($this->hasInput('reportid')) {
			$definition = ReportStorage::get((int) $this->getInput('reportid'));

			if ($definition === null) {
				$this->setResponse(new CControllerResponseFatal());
				return;
			}
		}

		$config = $definition['config'] ?? [];

		$groupids = $config['groupids'] ?? [];

		$groups = $groupids
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids
			]), ['groupid' => 'id'])
			: [];

		$data = [
			'reportid' => $definition['reportid'] ?? null,
			'name' => $definition['name'] ?? '',
			'report_type' => $definition['report_type'] ?? ReportTypeRegistry::PERCENTILES,
			'groupids' => $groupids,
			'groups' => $groups,
			'pattern' => $config['pattern'] ?? '',
			'slo' => $config['slo'] ?? '99.9',
			'report_type_labels' => ReportTypeRegistry::labels()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle($definition === null ? _('New report') : _('Edit report'));
		$this->setResponse($response);
	}
}
