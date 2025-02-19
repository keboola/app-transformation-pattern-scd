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
    private const IS_DELETED_NAME_DEFAULT = 'isDeleted';

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
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
