<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GenerateDefinition extends BaseConfigDefinition
{
    public const SCD_TYPE_2 = 'scd2';

    public const SCD_TYPE_4 = 'scd4';

    private const START_DATE_NAME_DEFAULT = 'start_date';
    private const END_DATE_NAME_DEFAULT = 'end_date';
    private const ACTUAL_NAME_DEFAULT = 'actual';
    private const IS_DELETED_NAME_DEFAULT = 'is_deleted';
    private const DELETED_FLAG_VALUE_DEFAULT = '0/1';
    private const END_DATE_VALUE_DEFAULT = '9999-12-31';
    private const CURRENT_TIMESTAMP_MINUS_ONE = false;
    private const UPPERCASE_COLUMNS_DEFAULT = false;
    private const SNAPSHOT_TABLE_NAME_DEFAULT = '';

    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->scalarNode('_componentId')->isRequired()->cannotBeEmpty()->end()
                ->enumNode('scd_type')
                    ->isRequired()
                    ->values(
                        [
                            self::SCD_TYPE_2,
                            self::SCD_TYPE_4,
                        ]
                    )
                ->end()
                ->scalarNode('primary_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('monitored_parameters')->isRequired()->cannotBeEmpty()->end()
                ->booleanNode('deleted_flag')->defaultFalse()->end()
                ->booleanNode('use_datetime')->defaultFalse()->end()
                ->booleanNode('keep_del_active')->defaultFalse()->end()
                ->scalarNode('timezone')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('start_date_name')->defaultValue(self::START_DATE_NAME_DEFAULT)->end()
                ->scalarNode('end_date_name')->defaultValue(self::END_DATE_NAME_DEFAULT)->end()
                ->scalarNode('actual_name')->defaultValue(self::ACTUAL_NAME_DEFAULT)->end()
                ->scalarNode('is_deleted_name')->defaultValue(self::IS_DELETED_NAME_DEFAULT)->end()
                ->scalarNode('deleted_flag_value')->defaultValue(self::DELETED_FLAG_VALUE_DEFAULT)->end()
                ->scalarNode('end_date_value')->defaultValue(self::END_DATE_VALUE_DEFAULT)->end()
                ->booleanNode('current_timestamp_minus_one')->defaultValue(self::CURRENT_TIMESTAMP_MINUS_ONE)->end()
                ->booleanNode('uppercase_columns')->defaultValue(self::UPPERCASE_COLUMNS_DEFAULT)->end()
                ->scalarNode('effective_date_adjustment')->defaultValue(0)->end()
                ->scalarNode('snapshot_table_name')->defaultValue(self::SNAPSHOT_TABLE_NAME_DEFAULT)->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
