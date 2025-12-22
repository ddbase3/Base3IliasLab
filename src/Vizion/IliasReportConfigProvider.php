<?php declare(strict_types=1);

namespace Base3IliasLab\Vizion;

use Vizion\Api\IReportConfigProvider;

class IliasReportConfigProvider implements IReportConfigProvider {

	private const REPORTS_DIR = DIR_PLUGIN . 'Base3IliasLab/local/Vizion';

	public function getConfig(string $report): array {
		$report = trim($report);
		if ($report === '') {
			throw new \InvalidArgumentException('Missing report identifier');
		}

		// Basic hardening to prevent path traversal.
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $report)) {
			throw new \InvalidArgumentException('Invalid report identifier');
		}

		$file = self::REPORTS_DIR . '/' . $report . '.json';
		if (!is_file($file) || !is_readable($file)) {
			throw new \RuntimeException("Report not found or not readable: {$report} ({$file})");
		}

		$raw = file_get_contents($file);
		if ($raw === false) {
			throw new \RuntimeException("Failed to read report file: {$file}");
		}

		$config = json_decode($raw, true);
		if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException("Invalid JSON in report file {$file}: " . json_last_error_msg());
		}

		return $config;
	}
}
