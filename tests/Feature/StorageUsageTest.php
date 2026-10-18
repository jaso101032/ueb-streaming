<?php

namespace Tests\Feature;

use App\Support\StorageUsage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageUsageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
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
        $this->assertSame(600, StorageUsage::recordings()['mediaUsedBytes']);
    }

    public function test_without_quota_labels_physical_filesystem_instead_of_claiming_a_host_limit(): void
    {
        config(['streaming.media_quota_gb' => null]);
        $usage = StorageUsage::recordings();
        $this->assertTrue($usage['available']);
        $this->assertSame('filesystem', $usage['basis']);
        $this->assertGreaterThan(0, $usage['totalBytes']);
    }
}
