<?php

namespace Tests\Feature;

use App\Models\BlogTopicIdea;
use App\Models\KeywordResearchRun;
use App\Models\KeywordResearchTerm;
use App\Models\Setting;
use App\Services\Research\PlanBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeywordPlanBriefTest extends TestCase
{
    use RefreshDatabase;

    private function runWithTerms(): KeywordResearchRun
    {
        $run = KeywordResearchRun::create([
            'seeds' => ['composting'], 'provider' => 'llm',
            'target_country' => 'United States', 'status' => 'clustered',
        ]);

        KeywordResearchTerm::create([
            'run_id' => $run->id, 'keyword' => 'composting guide', 'normalized' => 'composting guide',
            'cluster' => 'composting', 'role' => 'pillar', 'funnel_stage' => 'top',
            'opportunity' => 90, 'chosen' => true,
        ]);
        KeywordResearchTerm::create([
            'run_id' => $run->id, 'keyword' => 'worm bin moisture', 'normalized' => 'worm bin moisture',
            'cluster' => 'composting', 'role' => 'spoke', 'funnel_stage' => 'top',
            'opportunity' => 70, 'chosen' => true,
        ]);

        return $run;
    }

    public function test_ai_brief_fills_pain_point_angle_outline_and_titles(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $run = $this->runWithTerms();

        $briefs = ['briefs' => [
            ['i' => 0, 'title' => 'Composting at Home: The Complete Guide', 'pain_point' => 'No idea where to start', 'audience_need' => 'Beginners who want a working bin', 'angle' => 'A no-jargon start-to-finish path', 'outline' => ['Why compost', 'Pick a method', 'Build it', 'Maintain it']],
            // Spoke wrongly tries to use "complete guide" — must be stripped.
            ['i' => 1, 'title' => 'Fixing Worm Bin Moisture: The Complete Guide', 'pain_point' => 'Bin too wet or dry', 'audience_need' => 'Worm-bin owners troubleshooting', 'angle' => 'The squeeze test pros use', 'outline' => ['Signs', 'The fix', 'Prevention']],
        ]];

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode($briefs)]]], 200)]);

        $res = (new PlanBuilder)->build($run, ['limit' => 60, 'ai_brief' => true]);
        $this->assertSame(2, $res['created']);

        $pillar = BlogTopicIdea::where('role', 'pillar')->first();
        $spoke = BlogTopicIdea::where('role', 'spoke')->first();

        $this->assertSame('No idea where to start', $pillar->pain_point);
        $this->assertSame(['Why compost', 'Pick a method', 'Build it', 'Maintain it'], $pillar->outline);
        $this->assertStringContainsString('Complete Guide', $pillar->title); // pillar keeps it

        $this->assertSame('Bin too wet or dry', $spoke->pain_point);
        $this->assertStringNotContainsStringIgnoringCase('complete guide', $spoke->title); // stripped on spoke
        $this->assertNotEmpty($spoke->angle);
        $this->assertNotEmpty($spoke->outline);
    }

    public function test_without_ai_brief_titles_are_mechanical_and_brief_is_empty(): void
    {
        // No provider key + ai_brief off → no LLM call, empty brief (current behavior).
        Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);
        $run = $this->runWithTerms();

        $res = (new PlanBuilder)->build($run, ['limit' => 60, 'ai_brief' => false]);
        $this->assertSame(2, $res['created']);

        $pillar = BlogTopicIdea::where('role', 'pillar')->first();
        $this->assertNull($pillar->pain_point);
        $this->assertSame([], $pillar->outline);
        Http::assertNothingSent();
    }
}
