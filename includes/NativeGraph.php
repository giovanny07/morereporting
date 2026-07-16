<?php

namespace Modules\MoreReporting\Includes;

use Widgets\SvgGraph\Includes\CSvgGraphHelper;
use Widgets\SvgGraph\Includes\CWidgetFieldDataSet;
use Widgets\SvgGraph\Includes\WidgetForm;

/**
 * Renders line graphs through Zabbix's own SVG Graph engine (the same one used by the
 * native "Graph" dashboard widget), so report charts look pixel-identical to the rest of
 * Zabbix instead of relying on a third-party charting library.
 */
class NativeGraph {

	private const COLOR_PALETTE = ['1F88E5', '43A047', 'FB8C00', '8E24AA', 'E53935'];

	public static function renderItems(array $itemids, int $time_from, int $time_to, ?int $percentile = null,
			int $width = 900, int $height = 300): string {

		$data_sets = [];

		foreach (array_values($itemids) as $index => $itemid) {
			$data_sets[] = [
				'dataset_type' => CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM,
				'hosts' => [],
				'items' => [],
				'itemids' => [(string) $itemid],
				'references' => [],
				'color' => self::COLOR_PALETTE[$index % count(self::COLOR_PALETTE)],
				'type' => SVG_GRAPH_TYPE_LINE,
				'stacked' => SVG_GRAPH_STACKED_OFF,
				'width' => SVG_GRAPH_DEFAULT_WIDTH,
				'pointsize' => SVG_GRAPH_DEFAULT_POINTSIZE,
				'transparency' => SVG_GRAPH_DEFAULT_TRANSPARENCY,
				'fill' => SVG_GRAPH_DEFAULT_FILL,
				'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE,
				'axisy' => GRAPH_YAXIS_SIDE_LEFT,
				'timeshift' => '',
				'aggregate_function' => AGGREGATE_NONE,
				'aggregate_interval' => GRAPH_AGGREGATE_DEFAULT_INTERVAL,
				'aggregate_grouping' => GRAPH_AGGREGATE_BY_ITEM,
				'approximation' => APPROXIMATION_AVG,
				'data_set_label' => ''
			];
		}

		$graph_data = [
			'data_sets' => $data_sets,
			'data_source' => SVG_GRAPH_DATA_SOURCE_AUTO,
			'fix_time_period' => true,
			'displaying' => [
				'show_simple_triggers' => false,
				'show_working_time' => false,
				'show_percentile_left' => $percentile !== null,
				'percentile_left_value' => $percentile,
				'show_percentile_right' => false,
				'percentile_right_value' => null
			],
			'time_period' => [
				'time_from' => $time_from,
				'time_to' => $time_to
			],
			'axes' => [
				'show_left_y_axis' => true,
				'left_y_min' => null,
				'left_y_max' => null,
				'left_y_units' => null,
				'show_right_y_axis' => false,
				'right_y_min' => null,
				'right_y_max' => null,
				'right_y_units' => null,
				'show_x_axis' => true
			],
			'legend' => [
				'show_legend' => WidgetForm::LEGEND_ON,
				'legend_columns' => 4,
				'legend_lines' => 1,
				'legend_lines_mode' => 0,
				'legend_statistic' => WidgetForm::LEGEND_STATISTIC_ON,
				'show_aggregation' => false
			],
			'problems' => [
				'show_problems' => false,
				'graph_item_problems' => false,
				'problemhosts' => '',
				'severities' => [],
				'problem_name' => '',
				'evaltype' => TAG_EVAL_TYPE_AND_OR,
				'tags' => []
			],
			'overrides' => [],
			'templateid' => '',
			'override_hostid' => ''
		];

		$svg_options = CSvgGraphHelper::get($graph_data, $width, $height);

		return $svg_options['svg'].$svg_options['legend'];
	}
}
