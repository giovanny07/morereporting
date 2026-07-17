<?php

namespace Modules\MoreReporting\Tests\Includes\Reports;

use Modules\MoreReporting\Includes\Reports\SeverityHeatmapReport;
use PHPUnit\Framework\TestCase;

class SeverityHeatmapReportTest extends TestCase {

	private SeverityHeatmapReport $report;

	protected function setUp(): void {
		$this->report = new SeverityHeatmapReport();
	}

	public function testComputeCountsEventsPerHostAndSeverity(): void {
		$data = [
			'triggers' => [
				['triggerid' => '1', 'hosts' => [['name' => 'Host A']]],
				['triggerid' => '2', 'hosts' => [['name' => 'Host B']]]
			],
			'events' => [
				['objectid' => '1', 'severity' => '3'],
				['objectid' => '1', 'severity' => '3'],
				['objectid' => '1', 'severity' => '5'],
				['objectid' => '2', 'severity' => '2']
			]
		];

		$rows = $this->report->compute($data);

		self::assertCount(2, $rows);

		$host_a = $rows[array_search('Host A', array_column($rows, 'host'), true)];

		self::assertSame(2, $host_a['counts'][3]);
		self::assertSame(1, $host_a['counts'][5]);
		self::assertSame(3, $host_a['total']);

		$host_b = $rows[array_search('Host B', array_column($rows, 'host'), true)];

		self::assertSame(1, $host_b['counts'][2]);
		self::assertSame(1, $host_b['total']);
	}

	public function testComputeSortsRowsByTotalDescending(): void {
		$data = [
			'triggers' => [
				['triggerid' => '1', 'hosts' => [['name' => 'Quiet host']]],
				['triggerid' => '2', 'hosts' => [['name' => 'Noisy host']]]
			],
			'events' => [
				['objectid' => '1', 'severity' => '1'],
				['objectid' => '2', 'severity' => '1'],
				['objectid' => '2', 'severity' => '2'],
				['objectid' => '2', 'severity' => '3']
			]
		];

		$rows = $this->report->compute($data);

		self::assertSame('Noisy host', $rows[0]['host']);
		self::assertSame('Quiet host', $rows[1]['host']);
	}

	public function testComputeReturnsEmptyForNoEvents(): void {
		$data = [
			'triggers' => [
				['triggerid' => '1', 'hosts' => [['name' => 'Host A']]]
			],
			'events' => []
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testComputeSkipsEventsForUnknownTriggers(): void {
		$data = [
			'triggers' => [
				['triggerid' => '1', 'hosts' => [['name' => 'Host A']]]
			],
			'events' => [
				['objectid' => '999', 'severity' => '4']
			]
		];

		self::assertSame([], $this->report->compute($data));
	}

	public function testRenderReturnsRowsForInteractiveChannel(): void {
		$rows = [['host' => 'Host A']];

		self::assertSame($rows, $this->report->render($rows, 'interactive'));
	}

	public function testRenderReturnsNullForUnimplementedChannels(): void {
		self::assertNull($this->report->render([['host' => 'Host A']], 'pdf'));
		self::assertNull($this->report->render([['host' => 'Host A']], 'export'));
	}
}
