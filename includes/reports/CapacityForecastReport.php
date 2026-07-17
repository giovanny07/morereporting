<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Capacity planning / forecast: for numeric items matching the filter, fits a simple
 * linear trend (least squares) over the history values in the requested time window,
 * then estimates how many days until the trend reaches a configurable threshold -
 * the same "linear fit + extrapolate" idea as native Zabbix's timeleft()/forecast()
 * trigger functions, but computed here so it can run over many items in one report
 * instead of one item per trigger.
 */
class CapacityForecastReport extends ReportType {

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

		$history = [];

		foreach ($items_by_value_type as $value_type => $itemids) {
			$values = API::History()->get([
				'output' => 'extend',
				'history' => $value_type,
				'itemids' => $itemids,
				'time_from' => $filter['time_from'],
				'time_till' => $filter['time_to'],
				'sortfield' => 'clock',
				'sortorder' => 'ASC'
			]);

			foreach ($values as $value) {
				$history[$value['itemid']][] = ['value' => (float) $value['value'], 'clock' => (int) $value['clock']];
			}
		}

		return [
			'items' => $items,
			'history' => $history,
			'time_from' => $filter['time_from'],
			'threshold' => $filter['threshold']
		];
	}

	public function compute(array $data): array {
		$threshold = $data['threshold'];
		$time_from = $data['time_from'];
		$rows = [];

		foreach ($data['items'] as $item) {
			$points = $data['history'][$item['itemid']] ?? [];

			if (count($points) < 2) {
				continue;
			}

			// x = seconds since the window start, for numerical stability (avoids huge
			// Unix timestamps dominating the regression arithmetic).
			$xs = array_map(static fn(array $p): float => (float) ($p['clock'] - $time_from), $points);
			$ys = array_column($points, 'value');

			$n = count($points);
			$x_mean = array_sum($xs) / $n;
			$y_mean = array_sum($ys) / $n;

			$numerator = 0.0;
			$denominator = 0.0;

			foreach ($xs as $i => $x) {
				$numerator += ($x - $x_mean) * ($ys[$i] - $y_mean);
				$denominator += ($x - $x_mean) ** 2;
			}

			// A flat (single-timestamp-equivalent) window makes the slope undefined.
			if ($denominator == 0.0) {
				continue;
			}

			$slope = $numerator / $denominator;
			$intercept = $y_mean - $slope * $x_mean;
			$slope_per_day = $slope * SEC_PER_DAY;

			$x_last = end($xs);
			$current_value = end($ys);
			$fitted_last = $intercept + $slope * $x_last;

			$days_to_threshold = null;

			if ($slope != 0.0) {
				$x_threshold = ($threshold - $intercept) / $slope;
				$days = ($x_threshold - $x_last) / SEC_PER_DAY;

				// Only a forecast that lies in the future, in the direction the trend is
				// actually moving, is meaningful - a negative result means the threshold is
				// behind the trend (already crossed, or the trend is moving away from it).
				if ($days > 0) {
					$days_to_threshold = $days;
				}
			}

			$rows[] = [
				'itemid' => $item['itemid'],
				'host' => $item['hosts'][0]['name'] ?? '',
				'name' => $item['name'],
				'current_value' => $current_value,
				'fitted_value' => $fitted_last,
				'slope_per_day' => $slope_per_day,
				'days_to_threshold' => $days_to_threshold,
				'eta_clock' => $days_to_threshold !== null
					? (int) round($time_from + $x_last + $days_to_threshold * SEC_PER_DAY)
					: null
			];
		}

		usort($rows, static function(array $a, array $b): int {
			if ($a['days_to_threshold'] === null && $b['days_to_threshold'] === null) {
				return 0;
			}

			if ($a['days_to_threshold'] === null) {
				return 1;
			}

			if ($b['days_to_threshold'] === null) {
				return -1;
			}

			return $a['days_to_threshold'] <=> $b['days_to_threshold'];
		});

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		return $channel === 'interactive' ? $result : null;
	}
}
