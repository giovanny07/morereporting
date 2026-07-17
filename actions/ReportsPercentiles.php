<?php

namespace Modules\MoreReporting\Actions;

use API;
use CArrayHelper;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CProfile;
use CRangeTimeParser;
use CTimezoneHelper;
use DateTimeZone;

use Modules\MoreReporting\Includes\NativeGraph;
use Modules\MoreReporting\Includes\PdfReportHtml;
use Modules\MoreReporting\Includes\PdfRenderer;
use Modules\MoreReporting\Includes\ReportComparison;
use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\Reports\ItemPercentilesReport;
use Modules\MoreReporting\Includes\TimePresets;

class ReportsPercentiles extends CController {

	private const PROFILE_PREFIX = 'web.morereporting.percentiles.filter.';

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
			'filter_compare' =>		'in 1',
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
				'date_to' => $this->getInput('filter_date_to', $config['period']['to'] ?? 'now'),
				'compare' => $this->hasInput('filter_compare')
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
				CProfile::update(self::PROFILE_PREFIX.'compare', $this->hasInput('filter_compare') ? 1 : 0,
					PROFILE_TYPE_INT
				);
			}
			elseif ($this->hasInput('filter_rst')) {
				CProfile::deleteIdx(self::PROFILE_PREFIX.'groupids');
				CProfile::deleteIdx(self::PROFILE_PREFIX.'hostids');
				CProfile::deleteIdx(self::PROFILE_PREFIX.'patterns');
				CProfile::delete(self::PROFILE_PREFIX.'date_from');
				CProfile::delete(self::PROFILE_PREFIX.'date_to');
				CProfile::delete(self::PROFILE_PREFIX.'compare');
			}

			$filter = [
				'groupids' => CProfile::getArray(self::PROFILE_PREFIX.'groupids', []),
				'hostids' => CProfile::getArray(self::PROFILE_PREFIX.'hostids', []),
				'patterns' => CProfile::getArray(self::PROFILE_PREFIX.'patterns', []),
				'date_from' => CProfile::get(self::PROFILE_PREFIX.'date_from', 'now-7d'),
				'date_to' => CProfile::get(self::PROFILE_PREFIX.'date_to', 'now'),
				'compare' => (bool) CProfile::get(self::PROFILE_PREFIX.'compare', 0)
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

		$report = new ItemPercentilesReport();

		$raw_data = $report->getData([
			'groupids' => $filter['groupids'],
			'hostids' => $filter['hostids'],
			'patterns' => $filter['patterns'],
			'time_from' => $time_from,
			'time_to' => $time_to
		]);

		$rows = $report->render($report->compute($raw_data), 'interactive');

		$export_formats = [
			'morereporting.percentiles.json' => 'json',
			'morereporting.percentiles.csv' => 'csv',
			'morereporting.percentiles.yaml' => 'yaml',
			'morereporting.percentiles.pdf' => 'pdf'
		];
		$export_format = $export_formats[$this->getAction()] ?? null;
		$is_export = $export_format !== null;

		$export_headers = null;
		$export_rows = null;
		$pdf_bytes = null;

		if ($export_format === 'csv' || $export_format === 'pdf') {
			$export_headers = [
				_('Host'), _('Item'), _('Count'), _('Min'), _('Avg'), _('Max'), _('P50'), _('P90'), _('P95'), _('P99')
			];
			$export_rows = array_map(static function(array $row): array {
				return [
					$row['host'],
					$row['name'],
					$row['count'],
					round($row['min'], 4),
					round($row['avg'], 4),
					round($row['max'], 4),
					round($row['p50'], 4),
					round($row['p90'], 4),
					round($row['p95'], 4),
					round($row['p99'], 4)
				];
			}, $rows);

			if ($export_format === 'pdf') {
				$html = PdfReportHtml::build(_('Item percentiles'), $export_headers, $export_rows);

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

		$compare_pairs = null;

		if ($filter['compare'] && !$is_export) {
			$previous_period = ReportComparison::previousPeriod($time_from, $time_to);

			$previous_raw_data = $report->getData([
				'groupids' => $filter['groupids'],
				'hostids' => $filter['hostids'],
				'patterns' => $filter['patterns'],
				'time_from' => $previous_period['from'],
				'time_to' => $previous_period['to']
			]);

			$previous_rows = $report->render($report->compute($previous_raw_data), 'interactive');

			$compare_pairs = ReportComparison::pair($rows, $previous_rows, 'itemid');
		}

		$graph = null;

		if ($rows && !$is_export) {
			$top_itemids = array_slice(array_column($rows, 'itemid'), 0, 3);
			$graph = NativeGraph::renderItems($top_itemids, $time_from, $time_to, 95);
		}

		$data = [
			'filter' => $filter,
			'groups' => $groups,
			'hosts' => $hosts,
			'active_tab' => CProfile::get(self::PROFILE_PREFIX.'active', 1),
			'rows' => $rows,
			'export_headers' => $export_headers,
			'export_rows' => $export_rows,
			'pdf_bytes' => $pdf_bytes,
			'compare_pairs' => $compare_pairs,
			'graph' => $graph,
			'time_presets' => TimePresets::all(),
			'definition' => $definition !== null
				? ['reportid' => $definition['reportid'], 'name' => $definition['name']]
				: null
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item percentiles'));

		if ($is_export) {
			$response->setFileName('morereporting_percentiles_'.date('Ymd_His').'.'.$export_format);
		}

		$this->setResponse($response);
	}
}
