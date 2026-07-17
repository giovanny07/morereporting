<?php

namespace Modules\MoreReporting\Actions;

use API;
use CArrayHelper;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CProfile;
use CRangeTimeParser;
use CSeverityHelper;
use CTimezoneHelper;
use DateTimeZone;

use Modules\MoreReporting\Includes\PdfReportHtml;
use Modules\MoreReporting\Includes\PdfRenderer;
use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\Reports\SeverityHeatmapReport;
use Modules\MoreReporting\Includes\TimePresets;

class ReportsHeatmap extends CController {

	private const PROFILE_PREFIX = 'web.morereporting.heatmap.filter.';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'reportid' =>			'id',
			'filter_groupids' =>	'array_db hosts_groups.groupid',
			'filter_hostids' =>	'array_db hosts.hostid',
			'filter_patterns' =>	'array',
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

		if ($definition !== null) {
			$config = $definition['config'];

			$filter = [
				'groupids' => $config['groupids'] ?? [],
				'hostids' => $config['hostids'] ?? [],
				'patterns' => $config['patterns'] ?? [],
				'date_from' => $this->getInput('filter_date_from', $config['period']['from'] ?? 'now-7d'),
				'date_to' => $this->getInput('filter_date_to', $config['period']['to'] ?? 'now')
			];
		}
		else {
			if ($this->hasInput('filter_set')) {
				CProfile::updateArray(self::PROFILE_PREFIX.'groupids', $this->getInput('filter_groupids', []),
					PROFILE_TYPE_ID
				);
				CProfile::updateArray(self::PROFILE_PREFIX.'hostids', $this->getInput('filter_hostids', []),
					PROFILE_TYPE_ID
				);
				CProfile::updateArray(self::PROFILE_PREFIX.'patterns', $this->getInput('filter_patterns', []),
					PROFILE_TYPE_STR
				);
				CProfile::update(self::PROFILE_PREFIX.'date_from', $this->getInput('filter_date_from', ''),
					PROFILE_TYPE_STR
				);
				CProfile::update(self::PROFILE_PREFIX.'date_to', $this->getInput('filter_date_to', ''),
					PROFILE_TYPE_STR
				);
			}
			elseif ($this->hasInput('filter_rst')) {
				CProfile::deleteIdx(self::PROFILE_PREFIX.'groupids');
				CProfile::deleteIdx(self::PROFILE_PREFIX.'hostids');
				CProfile::deleteIdx(self::PROFILE_PREFIX.'patterns');
				CProfile::delete(self::PROFILE_PREFIX.'date_from');
				CProfile::delete(self::PROFILE_PREFIX.'date_to');
			}

			$filter = [
				'groupids' => CProfile::getArray(self::PROFILE_PREFIX.'groupids', []),
				'hostids' => CProfile::getArray(self::PROFILE_PREFIX.'hostids', []),
				'patterns' => CProfile::getArray(self::PROFILE_PREFIX.'patterns', []),
				'date_from' => CProfile::get(self::PROFILE_PREFIX.'date_from', 'now-7d'),
				'date_to' => CProfile::get(self::PROFILE_PREFIX.'date_to', 'now')
			];
		}

		$timezone = new DateTimeZone(CTimezoneHelper::getSystemTimezone());
		$time_parser = new CRangeTimeParser();

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

		$hosts = $filter['hostids']
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]), ['hostid' => 'id'])
			: [];

		$report = new SeverityHeatmapReport();

		$raw_data = $report->getData([
			'groupids' => $filter['groupids'],
			'hostids' => $filter['hostids'],
			'patterns' => $filter['patterns'],
			'time_from' => $time_from,
			'time_to' => $time_to
		]);

		$rows = $report->render($report->compute($raw_data), 'interactive');

		$severities = CSeverityHelper::getSeverities();

		$export_formats = [
			'morereporting.heatmap.json' => 'json',
			'morereporting.heatmap.csv' => 'csv',
			'morereporting.heatmap.yaml' => 'yaml',
			'morereporting.heatmap.pdf' => 'pdf'
		];
		$export_format = $export_formats[$this->getAction()] ?? null;
		$is_export = $export_format !== null;

		$export_headers = null;
		$export_rows = null;
		$pdf_bytes = null;

		if ($export_format === 'csv' || $export_format === 'pdf') {
			$export_headers = array_merge([_('Host')], array_column($severities, 'label'), [_('Total')]);
			$export_rows = array_map(static function(array $row) use ($severities): array {
				$cells = [$row['host']];

				foreach ($severities as $severity) {
					$cells[] = $row['counts'][$severity['value']] ?? 0;
				}

				$cells[] = $row['total'];

				return $cells;
			}, $rows);

			if ($export_format === 'pdf') {
				$html = PdfReportHtml::build(_('Severity heatmap'), $export_headers, $export_rows);

				try {
					$pdf_bytes = PdfRenderer::render($html);
				}
				catch (\RuntimeException $e) {
					error($e->getMessage());
					$this->setResponse(new CControllerResponseFatal());

					return;
				}
			}
		}

		$data = [
			'filter' => $filter,
			'groups' => $groups,
			'hosts' => $hosts,
			'active_tab' => CProfile::get(self::PROFILE_PREFIX.'active', 1),
			'rows' => $rows,
			'severities' => $severities,
			'export_headers' => $export_headers,
			'export_rows' => $export_rows,
			'pdf_bytes' => $pdf_bytes,
			'time_presets' => TimePresets::all(),
			'definition' => $definition !== null
				? ['reportid' => $definition['reportid'], 'name' => $definition['name']]
				: null
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Severity heatmap'));

		if ($is_export) {
			$response->setFileName('morereporting_heatmap_'.date('Ymd_His').'.'.$export_format);
		}

		$this->setResponse($response);
	}
}
