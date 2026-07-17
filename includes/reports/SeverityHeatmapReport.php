<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Severity heatmap: for triggers matching the filter, counts problem episodes
 * (TRIGGER_VALUE_TRUE events) per host and per severity within the requested time
 * window - a Host x Severity grid showing at a glance which hosts generate the most
 * (and the most severe) problems. Uses the event's own severity, not the trigger's
 * current one, since it can have been overridden or changed since the event fired -
 * same distinction native Problems pages make.
 */
class SeverityHeatmapReport extends ReportType {

	private const TRIGGER_LIMIT = 25;

	public function getData(array $filter): array {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid'],
			'selectHosts' => ['hostid', 'name'],
			'groupids' => $filter['groupids'] ?: null,
			'hostids' => $filter['hostids'] ?: null,
			'search' => $filter['patterns'] ? ['description' => $filter['patterns']] : null,
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'monitored' => true,
			'sortfield' => 'description',
			'limit' => self::TRIGGER_LIMIT
		]);

		$events = [];

		if ($triggers) {
			$events = API::Event()->get([
				'output' => ['objectid', 'severity'],
				'objectids' => array_column($triggers, 'triggerid'),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'value' => TRIGGER_VALUE_TRUE,
				'time_from' => $filter['time_from'],
				'time_till' => $filter['time_to']
			]);
		}

		return [
			'triggers' => $triggers,
			'events' => $events
		];
	}

	public function compute(array $data): array {
		$host_by_triggerid = [];

		foreach ($data['triggers'] as $trigger) {
			$host_by_triggerid[$trigger['triggerid']] = $trigger['hosts'][0]['name'] ?? '';
		}

		$counts_by_host = [];

		foreach ($data['events'] as $event) {
			$host = $host_by_triggerid[$event['objectid']] ?? null;

			if ($host === null) {
				continue;
			}

			$severity = (int) $event['severity'];
			$counts_by_host[$host][$severity] = ($counts_by_host[$host][$severity] ?? 0) + 1;
		}

		$rows = [];

		foreach ($counts_by_host as $host => $counts) {
			$rows[] = [
				'host' => $host,
				'counts' => $counts,
				'total' => array_sum($counts)
			];
		}

		usort($rows, static fn(array $a, array $b) => $b['total'] <=> $a['total']);

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		return $channel === 'interactive' ? $result : null;
	}
}
