<?php

namespace Modules\MoreReporting\Includes;

/**
 * Renders a standalone HTML document to PDF bytes via headless Chrome, run as a subprocess.
 * No native Zabbix PDF pipeline is used (zabbix-web-service, which Scheduled reports depends
 * on, is not installed on this deployment).
 */
class PdfRenderer {

	private const CHROME_BINARY = 'google-chrome-stable';
	private const TIMEOUT_SECONDS = 30;

	public static function render(string $html): string {
		$tmp_dir = sys_get_temp_dir();
		$html_file = tempnam($tmp_dir, 'mr_html_').'.html';
		$pdf_file = tempnam($tmp_dir, 'mr_pdf_').'.pdf';
		$chrome_profile_dir = $tmp_dir.'/mr_chrome_profile_'.bin2hex(random_bytes(8));

		file_put_contents($html_file, $html);

		// Chrome insists on a writable $HOME for its own config/cache dirs even when
		// --user-data-dir is set explicitly; the web server user's real $HOME is often
		// not writable, so it is overridden to a known-writable temp dir here.
		$cmd = 'HOME='.escapeshellarg($tmp_dir).' timeout '.self::TIMEOUT_SECONDS.' '.
			escapeshellcmd(self::CHROME_BINARY).
			' --headless --disable-gpu --no-sandbox'.
			' --user-data-dir='.escapeshellarg($chrome_profile_dir).
			' --print-to-pdf='.escapeshellarg($pdf_file).
			' --no-pdf-header-footer'.
			' '.escapeshellarg('file://'.$html_file).
			' 2>&1';

		exec($cmd, $output, $exit_code);

		$pdf_bytes = is_file($pdf_file) ? file_get_contents($pdf_file) : false;

		@unlink($html_file);
		@unlink($pdf_file);
		self::removeDirectory($chrome_profile_dir);

		if ($pdf_bytes === false || $pdf_bytes === '') {
			throw new \RuntimeException('PDF generation failed (exit code '.$exit_code.'): '.implode("\n", $output));
		}

		return $pdf_bytes;
	}

	private static function removeDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}

		@rmdir($dir);
	}
}
