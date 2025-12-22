<?php declare(strict_types=1);

namespace Base3IliasLab\DataHawk;

use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\JoinMetadata;
use ResourceFoundation\Dto\TableMetadata;

class IliasQuerySchemaProvider implements IQuerySchemaProvider {

        private string $schemaDir;

        public function __construct() {
                $this->schemaDir = rtrim(DIR_PLUGIN, '/\\') . '/Base3IliasLab/local/DataHawk';
        }

        /**
         * Returns all available table definitions as TableMetadata objects.
         */
        public function getSchema(): array {
                $tables = [];
                foreach (glob($this->schemaDir . '/*.json') as $file) {
                        if ($table = $this->loadTableFromFile($file)) {
                                $tables[] = $table;
                        }
                }
                return $tables;
        }

        /**
         * Returns a single table definition by name.
         */
        public function getTable(string $tableName): ?TableMetadata {
                $file = $this->schemaDir . '/' . $tableName . '.json';
                if (is_file($file)) {
                        return $this->loadTableFromFile($file);
                }
                return null;
        }

        /**
         * Loads and converts a table definition JSON file to TableMetadata.
         */
        private function loadTableFromFile(string $file): ?TableMetadata {
                $json = file_get_contents($file);
                if (!$json) {
                        return null;
                }

                $data = json_decode($json, true);
                if (!is_array($data) || empty($data['name'])) {
                        return null;
                }

                $fields = array_map(fn($f) => new FieldMetadata(
                        $f['name'],
                        $f['type'] ?? 'string',
                        $f['label'] ?? $f['name'],
                        $f['required'] ?? false
                ), $data['fields'] ?? []);

                $joins = array_map(fn($j) => new JoinMetadata(
                        $j['targetTable'],
                        $j['on'] ?? [],
                        $j['type'] ?? 'LEFT',
                        $j['meta'] ?? []
                ), $data['joins'] ?? []);

                return new TableMetadata(
                        name: $data['name'],
                        label: $data['label'] ?? $data['name'],
                        description: $data['description'] ?? '',
                        domain: $data['domain'] ?? '',
                        category: $data['category'] ?? '',
                        tags: $data['tags'] ?? [],
                        fields: $fields,
                        joins: $joins,
                        defaultFilters: $data['defaultFilters'] ?? [],
                        position: $data['position'] ?? []
                );
        }
}
