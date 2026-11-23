<?php

namespace Tests\Feature;

use App\Support\StorageUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageUsageTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        StorageUsage::flush();
        $this->root = sys_get_temp_dir().'/uebtv-storage-test-'.Str::uuid();
        File::makeDirectory($this->root.'/recordings/raw', 0770, true);
        File::makeDirectory($this->root.'/uploads', 0770, true);
        config([
            'streaming.recordings_root' => $this->root.'/recordings',
            'streaming.media_storage_root' => $this->root.'/uploads',
            'streaming.media_quota_gb' => 0.000001,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_configured_quota_counts_recordings_and_uploaded_media_and_warns_when_low(): void
    {
        file_put_contents($this->root.'/recordings/raw/live.flv', str_repeat('a', 500));
        file_put_contents($this->root.'/uploads/ad.mp4', str_repeat('b', 500));

        $usage = StorageUsage::recordings();
        $this->assertSame('quota', $usage['basis']);
        $this->assertSame(1000, $usage['mediaUsedBytes']);
        $this->assertSame(1000, $usage['usedBytes']);
        $this->assertSame(1074, $usage['totalBytes']);
        $this->assertSame(74, $usage['freeBytes']);
        $this->assertTrue($usage['lowSpace']);

        file_put_contents($this->root.'/uploads/ad.mp4', str_repeat('b', 100));
        StorageUsage::flush();
        $this->assertSame(600, StorageUsage::recordings()['mediaUsedBytes']);
    }

    public function test_without_quota_labels_physical_filesystem_instead_of_claiming_a_host_limit(): void
    {
        config(['streaming.media_quota_gb' => null]);
        StorageUsage::flush();
        $usage = StorageUsage::recordings();
        $this->assertTrue($usage['available']);
        $this->assertSame('filesystem', $usage['basis']);
        $this->assertGreaterThan(0, $usage['totalBytes']);
    }

    public function test_breakdown_groups_media_by_category_and_counts_nested_recordings_once(): void
    {
        // Local setup: the recordings folder lives inside the media folder.
        config(['streaming.media_storage_root' => $this->root, 'streaming.media_quota_gb' => 1]);
        StorageUsage::flush();
        File::makeDirectory($this->root.'/private/scheduled-sources', 0770, true);
        File::makeDirectory($this->root.'/private/institutional-ads', 0770, true);
        File::makeDirectory($this->root.'/private/renditions/scheduled-1', 0770, true);
        File::makeDirectory($this->root.'/public/eventos', 0770, true);
        file_put_contents($this->root.'/recordings/raw/live.flv', str_repeat('a', 400));
        file_put_contents($this->root.'/private/scheduled-sources/v.mp4', str_repeat('b', 300));
        file_put_contents($this->root.'/private/institutional-ads/ad.mp4', str_repeat('c', 200));
        file_put_contents($this->root.'/private/renditions/scheduled-1/480p.ts', str_repeat('d', 100));
        file_put_contents($this->root.'/public/eventos/thumb.jpg', str_repeat('e', 50));

        $usage = StorageUsage::recordings();
        $bytes = collect($usage['breakdown'])->pluck('bytes', 'key')->all();

        $this->assertSame(['recordings' => 400, 'scheduled' => 300, 'ads' => 200, 'renditions' => 100, 'other' => 50], $bytes);
        $this->assertSame(1050, $usage['mediaUsedBytes']);
        $this->assertStringContainsString('liberar=1', $usage['freeUrl']);
    }

    public function test_levels_warn_with_little_space_and_turn_critical_below_the_live_reserve(): void
    {
        $gb = 1024 ** 3;
        $this->assertSame('ok', StorageUsage::level(50 * $gb, 100 * $gb));
        $this->assertSame('low', StorageUsage::level(14 * $gb, 100 * $gb));
        $this->assertSame('critical', StorageUsage::level(2 * $gb, 100 * $gb));
        // A tiny quota is critical: not even the live reserve fits.
        $this->assertSame('critical', StorageUsage::recordings()['level']);
    }

    public function test_admin_panel_shows_the_space_alert_and_the_dashboard_breakdown(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('&quot;level&quot;:&quot;critical&quot;', false)
            ->assertSee('Videos de la parrilla');
    }
}
