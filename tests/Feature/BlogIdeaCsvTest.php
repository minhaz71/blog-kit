<?php

namespace Tests\Feature;

use App\Models\BlogTopicIdea;
use App\Services\Ai\BlogIdeaCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogIdeaCsvTest extends TestCase
{
    use RefreshDatabase;

    private function idea(array $overrides = []): BlogTopicIdea
    {
        return BlogTopicIdea::create(array_merge([
            'title' => 'Best Beginner Composting Setup',
            'fingerprint' => BlogTopicIdea::fingerprint('Best Beginner Composting Setup'),
            'cluster' => 'composting',
            'role' => 'spoke',
            'funnel_stage' => 'top',
            'primary_keyword' => 'beginner composting',
            'secondary_keywords' => [],
            'pain_point' => null,
            'angle' => null,
            'outline' => [],
            'status' => 'waiting',
        ], $overrides));
    }

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ideacsv');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_export_includes_id_and_brief_columns(): void
    {
        $idea = $this->idea(['secondary_keywords' => ['worm bin', 'bokashi'], 'outline' => ['Intro', 'Steps']]);

        $csv = BlogIdeaCsv::export([$idea]);

        $this->assertStringContainsString('id,title,primary_keyword,secondary_keywords,pain_point', $csv);
        $this->assertStringContainsString((string) $idea->id, $csv);
        $this->assertStringContainsString('worm bin | bokashi', $csv);
        $this->assertStringContainsString('Intro | Steps', $csv);
    }

    public function test_reimport_updates_the_brief_by_id(): void
    {
        $idea = $this->idea();

        $csv = "id,title,pain_point,angle,outline,secondary_keywords\n"
            ."{$idea->id},Best Beginner Composting Setup,No space and fear of smell,A smell-free studio method with a 4-week plan,Why bins smell | The setup | Week-by-week | Troubleshooting,worm bin | bokashi\n";

        $res = BlogIdeaCsv::import($this->writeCsv($csv));

        $this->assertSame(['updated' => 1, 'created' => 0, 'skipped' => 0], $res);

        $idea->refresh();
        $this->assertSame('No space and fear of smell', $idea->pain_point);
        $this->assertStringContainsString('smell-free studio', $idea->angle);
        $this->assertSame(['Why bins smell', 'The setup', 'Week-by-week', 'Troubleshooting'], $idea->outline);
        $this->assertSame(['worm bin', 'bokashi'], $idea->secondary_keywords);
    }

    public function test_blank_id_creates_a_new_idea(): void
    {
        $csv = "id,title,pain_point,angle,outline\n"
            .",How to Balance a Worm Bin's Moisture,Bin too wet or too dry,The exact squeeze test pros use,Signs | The fix | Maintenance\n";

        $res = BlogIdeaCsv::import($this->writeCsv($csv));

        $this->assertSame(1, $res['created']);
        $new = BlogTopicIdea::where('title', "How to Balance a Worm Bin's Moisture")->first();
        $this->assertNotNull($new);
        $this->assertSame('waiting', $new->status);
        $this->assertSame(['Signs', 'The fix', 'Maintenance'], $new->outline);
    }

    public function test_duplicate_title_on_create_is_skipped(): void
    {
        $this->idea(); // existing "Best Beginner Composting Setup"

        $csv = "id,title,pain_point\n"
            .",Best Beginner Composting Setup,dupe\n";

        $res = BlogIdeaCsv::import($this->writeCsv($csv));

        $this->assertSame(0, $res['created']);
        $this->assertSame(1, $res['skipped']);
        $this->assertSame(1, BlogTopicIdea::count());
    }

    public function test_missing_id_row_is_skipped_not_created(): void
    {
        $csv = "id,title,pain_point\n"
            ."999999,Nonexistent Idea,x\n";

        $res = BlogIdeaCsv::import($this->writeCsv($csv));

        $this->assertSame(['updated' => 0, 'created' => 0, 'skipped' => 1], $res);
    }
}
