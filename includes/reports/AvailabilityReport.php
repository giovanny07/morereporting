<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Trigger availability report: for triggers matching the filter, computes the percentage
 * of the requested time window spent in OK vs PROBLEM state using Zabbix's own
 * calculateAvailability() (include/triggers.inc.php) - the same algorithm behind the
 * classic "Availability report", so results match what the rest of Zabbix would show.
 * Also computes MTTR (mean time to repair) and MTBF (mean time between failures) from
 * the number of problem episodes in the window.
 */
class AvailabilityReport extends ReportType {

	private const TRIGGER_LIMIT = 25;

	public function getData(array $filter): array {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'priority'],
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

		$episode_counts = [];

		if ($triggers) {
			// One problem (TRIGGER_VALUE_TRUE) event marks the start of one episode, so
			// counting events per trigger gives the episode count without walking the raw
			// event stream ourselves - same aggregation approach as the native
			// "Top 100 triggers" report.
			$counts = API::Event()->get([
				'countOutput' => true,
				'groupBy' => ['objectid'],
				'objectids' => array_column($triggers, 'triggerid'),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'value' => TRIGGER_VALUE_TRUE,
				'time_from' => $filter['time_from'],
				'time_till' => $filter['time_to']
			]);

			foreach ($counts as $count) {
				$episode_counts[$count['objectid']] = (int) $count['rowscount'];
			}
		}

		return [
			'triggers' => $triggers,
			'episode_counts' => $episode_counts,
			'time_from' => $filter['time_from'],
			'time_to' => $filter['time_to']
		];
	}

	public function compute(array $data): array {
		$rows = [];

		foreach ($data['triggers'] as $trigger) {
			$availability = calculateAvailability($trigger['triggerid'], $data['time_from'], $data['time_to']);
			$episodes = $data['episode_counts'][$trigger['triggerid']] ?? 0;

			$downtime_seconds = (int) $availability['true_time'];
			$uptime_seconds = (int) $availability['false_time'];

			$rows[] = [
				'triggerid' => $trigger['triggerid'],
				'host' => $trigger['hosts'][0]['name'] ?? '',
				'description' => $trigger['description'],
				'priority' => (int) $trigger['priority'],
				'availability' => $availability['false'],
				'downtime' => $availability['true'],
				'downtime_seconds' => $downtime_seconds,
				'episodes' => $episodes,
				// Mean time to repair: average duration of a problem episode.
				'mttr_seconds' => $episodes > 0 ? (int) round($downtime_seconds / $episodes) : null,
				// Mean time between failures: average uptime stretch between episodes.
				'mtbf_seconds' => $episodes > 0 ? (int) round($uptime_seconds / $episodes) : null
			];
		}

		usort($rows, static fn(array $a, array $b) => $a['availability'] <=> $b['availability']);

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		// Export/PDF channels land in a later phase (see ROADMAP.md).
		return $channel === 'interactive' ? $result : null;
	}
}
