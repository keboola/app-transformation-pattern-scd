<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Twig\Extension\ExtensionInterface;

interface Pattern
{
    public function getInputTableName(): string;

    public function getSnapshotInputTable(): string;

    public function getSnapshotOutputTable(): string;

    public function getSnapshotPrimaryKey(): string;

    public function getSnapshotTableHeader(): array;

    public function getTemplatePath(): string;

    public function getTemplateVariables(): array;

    public function getTwigExtension(): ?ExtensionInterface;
}
