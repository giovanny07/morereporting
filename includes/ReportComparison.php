<?php

namespace Modules\MoreReporting\Includes;

/**
 * Generic period-over-period pairing: merges two runs of the *same* ReportType (current
 * period + the immediately preceding period of equal length) by a shared row key, so any
 * report can offer a "vs previous period" view without its own comparison logic. Field
 * names differ per report type, so computing deltas is left to the view - this only
 * pairs rows up.
 */
class ReportComparison {

	/**
	 * @param array  $current_rows   Rows from ItemPercentilesReport/AvailabilityReport::compute() for the
	 *                                selected period.
	 * @param array  $previous_rows  Same, for the immediately preceding period of equal length.
	 * @param string $key_field      Row field identifying the same entity across periods (e.g. 'itemid').
	 *
	 * @return array  List of ['current' => array, 'previous' => array|null] pairs, ordered like $current_rows.
	 *                'previous' is null when that entity had no data in the earlier period.
	 */
	public static function pair(array $current_rows, array $previous_rows, string $key_field): array {
		$previous_by_key = array_column($previous_rows, null, $key_field);

		$pairs = [];

		foreach ($current_rows as $row) {
			$pairs[] = [
				'current' => $row,
				'previous' => $previous_by_key[$row[$key_field]] ?? null
			];
		}

		return $pairs;
	}

	/**
	 * @return array{from: int, to: int}  The immediately preceding period of equal length
	 *                                     (from-duration to from), e.g. last week for a "last 7 days" window.
	 */
	public static function previousPeriod(int $time_from, int $time_to): array {
		$duration = $time_to - $time_from;

		return [
			'from' => $time_from - $duration,
			'to' => $time_from
		];
	}
}
