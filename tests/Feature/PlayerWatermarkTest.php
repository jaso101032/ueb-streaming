<?php

namespace Tests\Feature;

use App\Models\PlayerWatermark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerWatermarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_only_admin_can_view_change_or_reset_watermark(): void
    {
        foreach ([null, 'usuario', 'estudiante', 'operador', 'admin'] as $role) {
            auth()->logout();
            if ($role) {
                $this->actingAs(User::factory()->create(['role' => $role]));
            }
            $responses = [
                $this->get(route('admin.watermark.edit')),
                $this->put(route('admin.watermark.update'), ['watermark' => $this->png()]),
                $this->delete(route('admin.watermark.destroy')),
            ];
            foreach ($responses as $index => $response) {
                if (! $role) {
                    $response->assertRedirect(route('login'));
                } elseif ($role !== 'admin') {
                    $response->assertForbidden();
                } elseif ($index === 0) {
                    $response->assertOk()->assertSee('WatermarkSettings');
                } else {
                    $response->assertRedirect(route('admin.watermark.edit'))->assertSessionHasNoErrors();
                }
            }
        }
    }

    public function test_transparent_image_is_safely_saved_and_shared_without_changing_website_logos(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.watermark.update'), ['watermark' => $this->png(width: 1536, height: 768)])
            ->assertRedirect(route('admin.watermark.edit'))->assertSessionHasNoErrors();

        $path = PlayerWatermark::findOrFail(1)->path;
        $this->assertMatchesRegularExpression('~^player-watermarks/[a-f0-9-]{36}\.png$~', $path);
        Storage::disk('public')->assertExists($path);
        $stored = Storage::disk('public')->get($path);
        $size = getimagesizefromstring($stored);
        $this->assertSame([768, 384], [$size[0], $size[1]]);
        $this->assertStringNotContainsString('EXTRA_METADATA', $stored);
        $image = imagecreatefromstring($stored);
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($image, 384, 192) >> 24) & 0x7F);

        auth()->logout();
        $response = $this->get('/senal')->assertOk();
        preg_match('/data-shell="([^"]*)"/', $response->getContent(), $match);
        $shell = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertSame(['url' => asset('storage/'.$path), 'custom' => true], $shell['playerWatermark']);
        $this->assertSame(asset('images/brand/uebtv-logo-final.png'), $shell['tvLogo']);
        $this->assertSame(asset('images/brand/ueb-wordmark.png'), $shell['logo']);
        $this->assertSame([], $shell['links']);
    }

    public function test_replacement_uses_new_url_and_reset_restores_default(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([1, 2] as $attempt) {
            $this->put(route('admin.watermark.update'), ['watermark' => $this->png()])->assertSessionHasNoErrors();
            $paths[] = PlayerWatermark::findOrFail(1)->path;
        }
        $this->assertNotSame($paths[0], $paths[1]);
        $this->assertDatabaseCount('player_watermarks', 1);
        // Open tabs may still request their previous, immutable asset URL.
        Storage::disk('public')->assertExists($paths);
        $this->delete(route('admin.watermark.destroy'))->assertRedirect(route('admin.watermark.edit'));
        $this->assertDatabaseCount('player_watermarks', 0);
        $this->assertSame(['url' => asset('images/brand/uebtv-logo-final.png'), 'custom' => false], PlayerWatermark::current());
    }


    #[DataProvider('invalidImages')]
    public function test_unsuitable_images_are_rejected_without_changing_the_current_mark(string $kind): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        PlayerWatermark::create(['id' => 1, 'path' => 'player-watermarks/existing.png']);
        $file = match ($kind) {
            'opaque', 'single-transparent-pixel', 'opaque-border', 'empty', 'palette' => $this->png(kind: $kind),
            'small' => $this->png(width: 64, height: 32),
            'huge' => $this->png(width: 2049, height: 1024),
            'portrait' => $this->png(width: 128, height: 256),
            'too-wide' => $this->png(width: 768, height: 64),
            'jpg' => UploadedFile::fake()->image('logo.jpg', 256, 128),
            'renamed-jpg' => UploadedFile::fake()->createWithContent('logo.png', UploadedFile::fake()->image('logo.jpg', 256, 128)->getContent()),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'corrupt' => UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\ninvalid"),
            'oversized' => $this->png()->size(2049),
            'wrong-extension' => UploadedFile::fake()->createWithContent('logo.php', $this->png()->getContent()),
            'animated' => $this->animatedPng(),
        };
        $this->from(route('admin.watermark.edit'))->put(route('admin.watermark.update'), ['watermark' => $file])
            ->assertRedirect(route('admin.watermark.edit'))->assertSessionHasErrors('watermark');
        $this->assertSame('player-watermarks/existing.png', PlayerWatermark::findOrFail(1)->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function invalidImages(): array
    {
        return array_map(fn ($kind) => [$kind], ['opaque', 'single-transparent-pixel', 'opaque-border', 'empty', 'palette',
            'small', 'huge', 'portrait', 'too-wide', 'jpg', 'renamed-jpg', 'svg', 'corrupt', 'oversized', 'wrong-extension', 'animated']);
    }

    public function test_a_file_is_required_and_storage_failure_preserves_existing_setting(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        PlayerWatermark::create(['id' => 1, 'path' => 'player-watermarks/existing.png']);
        $this->put(route('admin.watermark.update'))->assertSessionHasErrors('watermark');
        Storage::shouldReceive('disk')->with('public')->once()->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturnFalse();
        $this->put(route('admin.watermark.update'), ['watermark' => $this->png()])->assertStatus(500);
        $this->assertSame('player-watermarks/existing.png', PlayerWatermark::findOrFail(1)->path);
    }

    public function test_palette_png_with_real_transparency_is_supported(): void
    {
        $image = imagecreate(256, 128);
        $clear = imagecolorallocate($image, 0, 0, 0);
        imagecolortransparent($image, $clear);
        imagefilledrectangle($image, 30, 30, 220, 100, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $file = UploadedFile::fake()->createWithContent('palette.png', ob_get_clean());
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.watermark.update'), ['watermark' => $file])->assertSessionHasNoErrors();
        $this->assertTrue(PlayerWatermark::current()['custom']);
    }

    private function png(string $kind = 'transparent', int $width = 256, int $height = 128): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 0);
        $opaque = in_array($kind, ['opaque', 'single-transparent-pixel', 'opaque-border', 'palette']);
        imagefill($image, 0, 0, $opaque ? $white : $clear);
        if ($kind !== 'empty') {
            imagefilledrectangle($image, (int) ($width / 4), (int) ($height / 4), (int) ($width * .75), (int) ($height * .75), $kind === 'opaque-border' ? $clear : $white);
        }
        if ($kind === 'single-transparent-pixel') {
            imagesetpixel($image, 0, 0, $clear);
        }
        if ($kind === 'palette') {
            imagetruecolortopalette($image, false, 8);
        }
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('logo.png', ob_get_clean().'EXTRA_METADATA');
    }

    private function animatedPng(): UploadedFile
    {
        $bytes = $this->png()->getContent();
        $data = pack('NN', 1, 0);
        $chunk = pack('N', 8).'acTL'.$data.pack('N', crc32('acTL'.$data));

        return UploadedFile::fake()->createWithContent('animated.png', substr($bytes, 0, 33).$chunk.substr($bytes, 33));
    }
}
