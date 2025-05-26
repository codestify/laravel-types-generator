<?php

namespace Codemystify\TypesGenerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class GenerateTypes
{
    public function __construct(
        public readonly string $name,
        public readonly bool $export = true,
        public readonly ?string $group = null,
        public readonly array $options = [],
        public readonly bool $recursive = true,
        public readonly ?string $description = null
    ) {}
}
