<?php

namespace Modules\MoreReporting\Actions;

use API;
use CAbsoluteTimeParser;
use CArrayHelper;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CProfile;
use CTimezoneHelper;
use DateTimeZone;

use Modules\MoreReporting\Includes\NativeGraph;
use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\Reports\ItemPercentilesReport;

class ReportsPercentiles extends CController {

	private const PROFILE_PREFIX = 'web.morereporting.percentiles.filter.';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'reportid' =>			'id',
			'filter_groupids' =>	'array_db hosts_groups.groupid',
			'filter_pattern' =>	'string',
			'filter_date_from' =>	'string',
			'filter_date_to' =>	'string',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1'
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
		$definition = $this->hasInput('reportid') ? ReportStorage::get((int) $this->getInput('reportid')) : null;

		if ($this->hasInput('filter_set')) {
			CProfile::updateArray(self::PROFILE_PREFIX.'groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::update(self::PROFILE_PREFIX.'pattern', $this->getInput('filter_pattern', ''), PROFILE_TYPE_STR);
			CProfile::update(self::PROFILE_PREFIX.'date_from', $this->getInput('filter_date_from', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update(self::PROFILE_PREFIX.'date_to', $this->getInput('filter_date_to', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx(self::PROFILE_PREFIX.'groupids');
			CProfile::delete(self::PROFILE_PREFIX.'pattern');
			CProfile::delete(self::PROFILE_PREFIX.'date_from');
			CProfile::delete(self::PROFILE_PREFIX.'date_to');
		}

		$filter = $definition !== null
			? [
				'groupids' => $definition['config']['groupids'] ?? [],
				'pattern' => $definition['config']['pattern'] ?? '',
				'date_from' => CProfile::get(self::PROFILE_PREFIX.'date_from', date(ZBX_DATE_TIME, time() - SEC_PER_DAY)),
				'date_to' => CProfile::get(self::PROFILE_PREFIX.'date_to', date(ZBX_DATE_TIME, time()))
			]
			: [
				'groupids' => CProfile::getArray(self::PROFILE_PREFIX.'groupids', []),
				'pattern' => CProfile::get(self::PROFILE_PREFIX.'pattern', ''),
				'date_from' => CProfile::get(self::PROFILE_PREFIX.'date_from', date(ZBX_DATE_TIME, time() - SEC_PER_DAY)),
				'date_to' => CProfile::get(self::PROFILE_PREFIX.'date_to', date(ZBX_DATE_TIME, time()))
			];

		$timezone = new DateTimeZone(CTimezoneHelper::getSystemTimezone());
		$time_parser = new CAbsoluteTimeParser();

		$time_parser->parse($filter['date_from']);
		$time_from = $time_parser->getDateTime(true, $timezone)->getTimestamp();

		$time_parser->parse($filter['date_to']);
		$time_to = $time_parser->getDateTime(false, $timezone)->getTimestamp();

		$groups = $filter['groupids']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids']
			]), ['groupid' => 'id'])
			: [];

		$report = new ItemPercentilesReport();

		$raw_data = $report->getData([
			'groupids' => $filter['groupids'],
			'pattern' => $filter['pattern'],
			'time_from' => $time_from,
			'time_to' => $time_to
		]);

		$rows = $report->render($report->compute($raw_data), 'interactive');

		$graph = null;

		if ($rows) {
			$top_itemids = array_slice(array_column($rows, 'itemid'), 0, 3);
			$graph = NativeGraph::renderItems($top_itemids, $time_from, $time_to, 95);
		}

		$data = [
			'filter' => $filter,
			'groups' => $groups,
			'active_tab' => CProfile::get(self::PROFILE_PREFIX.'active', 1),
			'rows' => $rows,
			'graph' => $graph,
			'definition' => $definition !== null
				? ['reportid' => $definition['reportid'], 'name' => $definition['name']]
				: null
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item percentiles'));
		$this->setResponse($response);
	}
}
