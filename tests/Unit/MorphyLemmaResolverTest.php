<?php

namespace Tests\Unit;

use App\Morphy;
use PHPUnit\Framework\TestCase;

class MorphyLemmaResolverTest extends TestCase
{
    public function testResolveAmbiguousGenitivePluralUsingCorpusContext(): void
    {
        $morphy = new Morphy();

        $candidates = [
            'участка' => $morphy->baseForms('участка'),
            'участков' => $morphy->baseForms('участков'),
        ];

        $resolved = $morphy->resolveRootsFromCandidates($candidates);

        $this->assertSame('участок', $resolved['участка']);
        $this->assertSame('участок', $resolved['участков']);
    }

    public function testResolveKeepsFirstCandidateWhenNoCorpusHint(): void
    {
        $morphy = new Morphy();

        $candidates = [
            'участков' => $morphy->baseForms('участков'),
        ];

        $resolved = $morphy->resolveRootsFromCandidates($candidates);

        $this->assertSame('участковый', $resolved['участков']);
    }
}
