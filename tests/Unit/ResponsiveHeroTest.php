<?php

namespace Tests\Unit;

use App\Support\ResponsiveHero;
use PHPUnit\Framework\TestCase;

class ResponsiveHeroTest extends TestCase
{
    public function test_admin_variants_include_recommended_sizes_for_each_context(): void
    {
        $cover = ResponsiveHero::adminVariants('cover');
        $this->assertSame('1920 × 1080 px', $cover['desktop']['size']);
        $this->assertSame('1200 × 800 px', $cover['tablet']['size']);
        $this->assertSame('800 × 1200 px', $cover['mobile']['size']);
        $this->assertStringContainsString('max 5 MB', $cover['desktop']['hint']);

        $homepage = ResponsiveHero::adminVariants('homepage');
        $this->assertSame('1200 × 900 px', $homepage['tablet']['size']);
        $this->assertSame('900 × 1200 px', $homepage['mobile']['size']);

        $service = ResponsiveHero::adminVariants('service');
        $this->assertStringContainsString('/services list thumbnail', $service['desktop']['hint']);

        $compact = ResponsiveHero::adminVariants('compact');
        $this->assertSame('800 × 1200 px', $compact['desktop']['size']);
        $this->assertSame('1200 × 600 px', $compact['mobile']['size']);
        $this->assertStringContainsString('compact side panel', $compact['desktop']['hint']);
        $this->assertStringContainsString('Compact split hero', ResponsiveHero::adminUploadIntro('compact'));
    }
}
