<?php

namespace Photobooth\Configuration\Section;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class GoogleDriveConfiguration
{
    public static function getNode(): NodeDefinition
    {
        return (new TreeBuilder('google_drive'))->getRootNode()->addDefaultsIfNotSet()
            ->ignoreExtraKeys()
            ->children()
                ->booleanNode('enabled')->defaultValue(false)->end()
                ->scalarNode('baseFolder')->defaultValue('')->end()
                ->booleanNode('delete')->defaultValue(false)->end()
            ->end();
    }
}
