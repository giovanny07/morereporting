<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Anomaly detection: for numeric items matching the filter, computes a baseline
 * mean/stddev from a reference window preceding the analysis period, then flags
 * analysis-period values whose z-score (how many baseline standard deviations away
 * from the baseline mean) meets or exceeds a threshold.
 */
class AnomalyReport extends ReportType {

	private const ITEM_LIMIT = 25;

	public function getData(array $filter): array {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'value_type'],
			'selectHosts' => ['hostid', 'name'],
			'groupids' => $filter['groupids'] ?: null,
			'hostids' => $filter['hostids'] ?: null,
			'search' => $filter['patterns'] ? ['name' => $filter['patterns']] : null,
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'filter' => [
				'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64],
				'status' => ITEM_STATUS_ACTIVE
			],
			'monitored' => true,
			'sortfield' => 'name',
			'limit' => self::ITEM_LIMIT
		]);

		$items_by_value_type = [];

		foreach ($items as $item) {
			$items_by_value_type[$item['value_type']][] = $item['itemid'];
		}

		$baseline_history = [];
		$history = [];

		foreach ($items_by_value_type as $value_type => $itemids) {
			$baseline_history += $this->fetchHistory($itemids, $value_type,
				$filter['baseline_time_from'], $filter['baseline_time_to']);

			$history += $this->fetchHistory($itemids, $value_type, $filter['time_from'], $filter['time_to']);
		}

		return [
			'items' => $items,
			'baseline_history' => $baseline_history,
			'history' => $history,
			'zscore_threshold' => $filter['zscore_threshold']
		];
	}

	public function compute(array $data): array {
		$threshold = $data['zscore_threshold'];
		$rows = [];

		foreach ($data['items'] as $item) {
			$baseline_values = $data['baseline_history'][$item['itemid']] ?? [];
			$analysis_values = $data['history'][$item['itemid']] ?? [];

			// At least 2 baseline points are needed for a meaningful stddev, and a flat
			// baseline (stddev 0) makes the z-score undefined - both are skipped rather
			// than shown as a false/misleading anomaly signal.
			if (count($baseline_values) < 2 || !$analysis_values) {
				continue;
			}

			$baseline_numbers = array_column($baseline_values, 'value');

			$baseline_mean = array_sum($baseline_numbers) / count($baseline_numbers);
			$variance = array_sum(array_map(
				static fn(float $v): float => ($v - $baseline_mean) ** 2, $baseline_numbers
			)) / count($baseline_numbers);
			$baseline_stddev = sqrt($variance);

			if ($baseline_stddev == 0.0) {
				continue;
			}

			$anomalies = 0;
			$max_abs_z = 0.0;
			$worst_value = null;

			foreach ($analysis_values as $entry) {
				$z = ($entry['value'] - $baseline_mean) / $baseline_stddev;

				if (abs($z) >= $threshold) {
					$anomalies++;
				}

				if (abs($z) > $max_abs_z) {
					$max_abs_z = abs($z);
					$worst_value = $entry;
				}
			}

			$rows[] = [
				'itemid' => $item['itemid'],
				'host' => $item['hosts'][0]['name'] ?? '',
				'name' => $item['name'],
				'baseline_mean' => $baseline_mean,
				'baseline_stddev' => $baseline_stddev,
				'analysis_count' => count($analysis_values),
				'anomalies' => $anomalies,
				'anomaly_rate' => $anomalies / count($analysis_values) * 100,
				'max_zscore' => $max_abs_z,
				'worst_value' => $worst_value['value'] ?? null,
				'worst_clock' => $worst_value['clock'] ?? null
			];
		}

		usort($rows, static fn(array $a, array $b) => $b['anomalies'] <=> $a['anomalies']
			?: $b['max_zscore'] <=> $a['max_zscore']);

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		return $channel === 'interactive' ? $result : null;
	}

	/**
	 * @return array  itemid => list of ['value' => float, 'clock' => int], sorted by clock.
	 */
	private function fetchHistory(array $itemids, int $value_type, int $time_from, int $time_to): array {
		$values = API::History()->get([
			'output' => 'extend',
			'history' => $value_type,
			'itemids' => $itemids,
			'time_from' => $time_from,
			'time_till' => $time_to,
			'sortfield' => 'clock',
			'sortorder' => 'ASC'
		]);

		$by_item = [];

		foreach ($values as $value) {
			$by_item[$value['itemid']][] = ['value' => (float) $value['value'], 'clock' => (int) $value['clock']];
		}

		return $by_item;
	}
}
