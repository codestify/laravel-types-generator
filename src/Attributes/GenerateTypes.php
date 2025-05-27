<?php

namespace Codemystify\TypesGenerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class GenerateTypes
{
    public function __construct(
        public readonly string $name,
        public readonly array $structure = [],
        public readonly array $types = [],
        public readonly ?string $group = null,
        public readonly ?string $fileType = null,
        public readonly bool $export = true,
    ) {}
}
