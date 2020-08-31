<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GenerateDefinition extends BaseConfigDefinition
{
    public const SCD_TYPE_2 = 'scd2';

    public const SCD_TYPE_4 = 'scd4';

    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->enumNode('scd_type')
                    ->isRequired()
                    ->values(
                        [
                            self::SCD_TYPE_2,
                            self::SCD_TYPE_4,
                        ]
                    )
                ->end()
                ->scalarNode('primary_key')->isRequired()->end()
                ->scalarNode('monitored_parameters')->end()
                ->booleanNode('deleted_flag')->defaultFalse()->end()
                ->booleanNode('use_datetime')->defaultFalse()->end()
                ->booleanNode('keep_del_active')->defaultFalse()->end()
                ->scalarNode('timezone')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
