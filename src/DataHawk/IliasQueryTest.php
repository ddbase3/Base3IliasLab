<?php declare(strict_types=1);

namespace Base3IliasLab\DataHawk;

use Base3\Api\IOutput;
use ResourceFoundation\Api\IQueryService;

class IliasQueryTest implements IOutput {

	public function __construct(
		private readonly IQueryService $queryservice
	) {}

	public static function getName(): string {
		return 'iliasquerytest';
	}

	public function getOutput($out = "html") {

		$out = '<h1>DataHawk Test</h1>';

		$query = [
			"type" => "select",
			"fields" => [
				[
					"element" => [ "type" => "fld", "table" => "usr_data", "field" => "usr_id" ],
					"alias" => "User ID"
				], [
					"element" => [ "type" => "fld", "table" => "usr_data", "field" => "firstname" ],
					"alias" => "Vorname"
				], [
					"element" => [ "type" => "fld", "table" => "usr_data", "field" => "lastname" ],
					"alias" => "Nachname"
				], [
					"element" => [ "type" => "fld", "table" => "obj_members", "field" => "tutor" ],
					"alias" => "Tutor"
				], [
					"element" => [ "type" => "fld", "table" => "obj_members", "field" => "admin" ],
					"alias" => "Admin"
				]
			],
			"table" => "usr_data",
			"limit" => 20
		];

		try {
			$result = $this->queryservice->executeQuery($query);

			$out .= '<table><thead><tr>';
			foreach ($result->columns as $col) {
				$out .= "<th>{$col['name']}</th>";
			}
			$out .= '</tr></thead><tbody>';
			foreach ($result->rows as $row) {
				$out .= "<tr>";
				foreach ($row as $cell) {
					$out .= "<td>" . htmlspecialchars((string)$cell) . "</td>";
				}
				$out .= "</tr>";
			}
			$out .= '</tbody></table>';

			$out .= '<style>table { border-collapse:collapse; } td, th { padding:5px 10px; border:1px solid black; text-align:left; }</style>';

		} catch (\Throwable $e) {
			$out .= '<p>Query failed: ' . $e->getMessage() . '</p>';
		}

		return $out;
	}
	
	public function getHelp() {
		return 'Help of IliasQueryTest' . "\n";
	}
}
