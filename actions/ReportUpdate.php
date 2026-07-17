<?php

namespace Modules\MoreReporting\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CParser;
use CRangeTimeParser;
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
			'hostids' =>		'array_db hosts.hostid',
			'item_patterns' =>		'array',
			'trigger_patterns' =>	'array',
			'slo' =>			'string',
			'period_from' =>	'required|string|not_empty',
			'period_to' =>		'required|string|not_empty',
			'status_enabled' =>	'in 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret && !ReportTypeRegistry::exists($this->getInput('report_type'))) {
			error(_('Unknown report type.'));
			$ret = false;
		}

		if ($ret) {
			$range_time_parser = new CRangeTimeParser();

			foreach (['period_from', 'period_to'] as $field) {
				if ($range_time_parser->parse($this->getInput($field)) !== CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s".', $field));
					$ret = false;
				}
			}
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
		$report_type = $this->getInput('report_type');

		// Only the field relevant to the selected type is trusted, even if the other one's
		// (hidden) inputs were also submitted - see ReportTypeRegistry::fields().
		$patterns_field = $report_type === ReportTypeRegistry::AVAILABILITY ? 'trigger_patterns' : 'item_patterns';
		$patterns = array_values(array_filter($this->getInput($patterns_field, []), static fn($p) => $p !== ''));

		$config = [
			'groupids' => $this->getInput('groupids', []),
			'hostids' => $this->getInput('hostids', []),
			'patterns' => $patterns,
			'slo' => $this->getInput('slo', '99.9'),
			'period' => [
				'from' => $this->getInput('period_from'),
				'to' => $this->getInput('period_to')
			]
		];

		$name = $this->getInput('name');
		$status = $this->hasInput('status_enabled') ? ZBX_REPORT_STATUS_ENABLED : ZBX_REPORT_STATUS_DISABLED;

		if ($this->hasInput('reportid')) {
			ReportStorage::update((int) $this->getInput('reportid'), $name, $report_type, $config, $status);
			CMessageHelper::setSuccessTitle(_('Report updated'));
		}
		else {
			ReportStorage::create($name, $report_type, $config, $status, (int) CWebUser::$data['userid']);
			CMessageHelper::setSuccessTitle(_('Report created'));
		}

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list')
		);
		$this->setResponse($response);
	}
}
