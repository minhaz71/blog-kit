<?php

namespace Tests\Feature;

use App\Services\Ai\BlogWriter;
use Tests\TestCase;

class WriterSelfBriefTest extends TestCase
{
    public function test_bare_title_gets_a_self_brief_instruction(): void
    {
        $block = BlogWriter::selfBriefBlock(['name' => 'Composting at Home', 'pain_point' => '', 'angle' => '', 'outline' => '']);

        $this->assertStringContainsString('NO BRIEF WAS PROVIDED', $block);
        $this->assertStringContainsString('pain point', $block);
        $this->assertStringContainsString('outline', $block);
    }

    public function test_item_with_a_brief_gets_no_extra_block(): void
    {
        $this->assertSame('', BlogWriter::selfBriefBlock([
            'name' => 'X', 'pain_point' => 'No space in a studio', 'angle' => '', 'outline' => '',
        ]));

        $this->assertSame('', BlogWriter::selfBriefBlock([
            'name' => 'X', 'outline' => ['Intro', 'Steps'],
        ]));
    }
}
