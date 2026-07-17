<?php

namespace Modules\MoreReporting\Includes;

/**
 * Builds a small standalone HTML document for a report export - not using Zabbix's own
 * CHtmlPage/CTag builders, since this is rendered outside the Zabbix UI entirely (as a
 * PDF file via headless Chrome), not as a page in the frontend.
 */
class PdfReportHtml {

	/**
	 * @param string $title    Report title.
	 * @param array  $headers  Column header labels.
	 * @param array  $rows     List of rows, each a list of already-formatted cell values.
	 */
	public static function build(string $title, array $headers, array $rows): string {
		$thead = '<tr>';

		foreach ($headers as $header) {
			$thead .= '<th>'.htmlspecialchars($header).'</th>';
		}

		$thead .= '</tr>';

		$tbody = '';

		foreach ($rows as $row) {
			$tbody .= '<tr>';

			foreach ($row as $value) {
				$tbody .= '<td>'.htmlspecialchars((string) $value).'</td>';
			}

			$tbody .= '</tr>';
		}

		$title_escaped = htmlspecialchars($title);
		$generated = htmlspecialchars(date('Y-m-d H:i:s'));

		return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$title_escaped}</title>
<style>
	body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2c33; margin: 24px; }
	h1 { font-size: 16px; margin: 0 0 4px; }
	.meta { color: #6c7a89; font-size: 10px; margin-bottom: 16px; }
	table { border-collapse: collapse; width: 100%; }
	th, td { border: 1px solid #ccd5d9; padding: 4px 8px; text-align: left; }
	th { background: #ebeff2; }
	tr:nth-child(even) td { background: #f7f9fa; }
</style>
</head>
<body>
	<h1>{$title_escaped}</h1>
	<div class="meta">Generated: {$generated}</div>
	<table><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>
</body>
</html>
HTML;
	}
}
