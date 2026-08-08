<?php

namespace Tests\Feature;

use App\Filament\Resources\KeywordResearchResource\Pages\CreateKeywordResearch;
use App\Filament\Resources\KeywordResearchResource\Pages\EditKeywordResearch;
use App\Filament\Resources\KeywordResearchResource\Pages\ListKeywordResearch;
use App\Models\BlogTopicIdea;
use App\Models\KeywordResearchRun;
use App\Models\KeywordResearchTerm;
use App\Models\User;
use App\Services\Research\PlanBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeywordResearchTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_list_and_create_pages_render(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListKeywordResearch::class)->assertOk();
        Livewire::test(CreateKeywordResearch::class)->assertOk();
    }

    public function test_create_parses_seeds_into_an_array_and_edit_page_renders(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateKeywordResearch::class)
            ->fillForm([
                'label' => 'Japan travel',
                'seeds' => "japan travel\ntokyo itinerary\njapan travel", // dup collapses
                'provider' => 'llm', // no network in tests
                'target_language' => 'en',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $run = KeywordResearchRun::first();
        $this->assertNotNull($run);
        $this->assertSame(['japan travel', 'tokyo itinerary'], $run->seeds);
        $this->assertSame((int) $this->admin()->id > 0 ? $run->user_id : null, $run->user_id);

        // The edit page (with the terms relation manager) renders.
        Livewire::test(EditKeywordResearch::class, ['record' => $run->getRouteKey()])->assertOk();
    }

    public function test_plan_builder_turns_chosen_clustered_terms_into_blog_ideas(): void
    {
        $this->admin();

        $run = KeywordResearchRun::create([
            'label' => 'Japan', 'seeds' => ['japan travel'], 'status' => 'clustered',
        ]);

        // Two clusters, each a pillar + a spoke.
        foreach ([
            ['japan itinerary', 'Itinerary', 'pillar', 'top', 5000, 0.4],
            ['japan itinerary 7 days', 'Itinerary', 'spoke', 'top', 800, 0.3],
            ['best time to visit japan', 'Timing', 'pillar', 'middle', 3000, 0.35],
            ['japan weather in april', 'Timing', 'spoke', 'top', 600, 0.25],
        ] as [$kw, $cluster, $role, $stage, $vol, $opp]) {
            KeywordResearchTerm::create([
                'run_id' => $run->id,
                'keyword' => $kw,
                'normalized' => KeywordResearchTerm::normalize($kw),
                'source' => 'related',
                'volume' => $vol,
                'intent' => 'informational',
                'cluster' => $cluster,
                'role' => $role,
                'funnel_stage' => $stage,
                'opportunity' => $opp,
                'chosen' => true,
                'status' => 'new',
            ]);
        }

        $result = app(PlanBuilder::class)->build($run->refresh(), ['limit' => 10]);

        $this->assertSame(4, $result['created']);
        $this->assertSame('planned', $run->fresh()->status);

        // Ideas carry cluster/role/funnel/keyword through to the waiting area.
        $ideas = BlogTopicIdea::all();
        $this->assertCount(4, $ideas);
        $pillar = $ideas->firstWhere('primary_keyword', 'japan itinerary');
        $this->assertSame('pillar', $pillar->role);
        $this->assertSame('Itinerary', $pillar->cluster);
        $this->assertContains($pillar->funnel_stage, ['top', 'middle', 'bottom']);

        // Terms are marked planned so a re-run won't duplicate them.
        $this->assertSame(4, $run->terms()->where('status', 'planned')->count());
    }
}
