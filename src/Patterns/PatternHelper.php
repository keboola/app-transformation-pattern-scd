<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

class PatternHelper
{
    public static function columnsToLower(array $columns): array
    {
        return array_map(fn(array $column) => [
            'name' => mb_strtolower($column['name']),
            'definition' => $column['definition'],
        ], $columns);
    }

    public static function columnsToUpper(array $columns): array
    {
        return array_map(fn(array $column) => [
            'name' => mb_strtoupper($column['name']),
            'definition' => $column['definition'],
        ], $columns);
    }

    public static function transformColumnsCase(array $inputColumns, bool $uppercaseColumns): array
    {
        return $uppercaseColumns ?
            self::columnsToUpper($inputColumns) :
            self::columnsToLower($inputColumns);
    }

    public static function mergeColumnsWithDefinition(array ...$columns): array
    {
        $merged = [];
        foreach ($columns as $columnSet) {
            foreach ($columnSet as $column) {
                $merged[$column['name']] = $column;
            }
        }
        return array_values($merged);
    }

    public static function noIndent(string $str): string
    {
        return implode(
            "\n",
            array_map(fn(string $line) => trim($line), explode("\n", $str))
        );
    }
}
