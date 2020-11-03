<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use SqlFormatter;

class ParametersGenerator
{
    public function generate(string $sql): array
    {
        // Split SQL to statements
        $statements = array_map(function ($sql) {
            return SqlFormatter::format($sql, false);
        }, SqlFormatter::splitQuery($sql));

        // Map statements to codes, add name
        $sqlCodes = [];
        foreach ($statements as $index => $statement) {
            $sqlCodes[] = [
                'name' => 'Code ' . ($index + 1),
                'script' => $statement,
            ];
        }

        // Return generated blocks
        return [
            'blocks' => [
                [
                    'name' => 'Generated SCD block',
                    'codes' => $sqlCodes,
                ],
            ],
        ];
    }
}
