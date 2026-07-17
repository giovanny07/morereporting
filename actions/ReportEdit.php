<?php

namespace Modules\MoreReporting\Actions;

use API;
use CArrayHelper;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;

use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\ReportTypeRegistry;
use Modules\MoreReporting\Includes\TimePresets;

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
		$hostids = $config['hostids'] ?? [];

		$groups = $groupids
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids
			]), ['groupid' => 'id'])
			: [];

		$hosts = $hostids
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $hostids
			]), ['hostid' => 'id'])
			: [];

		$report_type = $definition['report_type'] ?? ReportTypeRegistry::PERCENTILES;
		$patterns = $config['patterns'] ?? [];

		$data = [
			'reportid' => $definition['reportid'] ?? null,
			'name' => $definition['name'] ?? '',
			'report_type' => $report_type,
			'groupids' => $groupids,
			'groups' => $groups,
			'hostids' => $hostids,
			'hosts' => $hosts,
			'item_patterns' => in_array($report_type,
				[ReportTypeRegistry::PERCENTILES, ReportTypeRegistry::ANOMALY, ReportTypeRegistry::CAPACITY], true)
					? $patterns : [],
			'trigger_patterns' => $report_type === ReportTypeRegistry::AVAILABILITY ? $patterns : [],
			'slo' => $config['slo'] ?? '99.9',
			'baseline_days' => $config['baseline_days'] ?? '30',
			'zscore_threshold' => $config['zscore_threshold'] ?? '3',
			'threshold' => $config['threshold'] ?? '0',
			'period_from' => $config['period']['from'] ?? 'now-7d',
			'period_to' => $config['period']['to'] ?? 'now',
			'status' => $definition['status'] ?? ZBX_REPORT_STATUS_ENABLED,
			'report_type_labels' => ReportTypeRegistry::labels(),
			'report_type_fields' => ReportTypeRegistry::fieldsMap(),
			'time_presets' => TimePresets::all()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle($definition === null ? _('New report') : _('Edit report'));
		$this->setResponse($response);
	}
}
