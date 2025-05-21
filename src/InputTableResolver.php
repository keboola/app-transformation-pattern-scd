<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;

class InputTableResolver
{
    private Config $config;
    private ApiFacade $apiFacade;
    private ?array $inputTableDetail = null;

    public function __construct(Config $config, ApiFacade $apiFacade)
    {
        $this->config = $config;
        $this->apiFacade = $apiFacade;
    }

    public function getInputTableId(): string
    {
        $tables = $this->config->getInputTables();

        if (empty($tables)) {
            throw new UserException('Please specify one input table in the input mapping.');
        }

        if (count($tables) === 1) {
            // One table in input mapping
            return $tables[0]['source'];
        } else {
            // Multiple tables, find the one with destination = "input_table"
            foreach ($tables as $table) {
                if (($table['destination'] ?? '') === 'input_table') {
                    return $table['source'];
                }
            }

            throw new UserException(sprintf(
                'Found "%d" tables in input mapping, but no source table with "destination" = "input_table". ' .
                'Please set the source table in the input mapping.',
                count($tables)
            ));
        }
    }

    public function getInputTableColumns(): array
    {
        return $this->getInputTableDetail()['columns'];
    }

    private function getInputTableDetail(): array
    {
        if (!$this->inputTableDetail) {
            $this->inputTableDetail = $this->apiFacade->getTable($this->getInputTableId());
        }

        return $this->inputTableDetail;
    }
}
