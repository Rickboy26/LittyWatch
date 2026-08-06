<?php
declare(strict_types=1);
namespace LittyWatch\Parser;

final class SetQuantityResolver
{
    public function __construct(private ?DynamicKnowledge $knowledge = null) {}
    public function resolve(string $item, string $segment): ?float
    {
        if (!preg_match('/\bsets?\b/iu',$segment)) return null;
        $learned=$this->knowledge?->setSize($item);if($learned!==null)return$learned;
        return mb_strtolower(trim($item)) === 'rin relic' ? 25.0 : null;
    }
}
