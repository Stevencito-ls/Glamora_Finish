<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CosmeticImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerAndRealImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seeded_catalog_banners_and_blog_have_zero_svg_images(): void
    {
        $this->assertGreaterThanOrEqual(6, Banner::query()->count());

        foreach (Banner::all() as $banner) {
            $this->assertStringNotContainsString('.svg', strtolower($banner->image_url));
            $this->assertStringNotContainsString('.svg', strtolower($banner->accent_image_url));
        }

        foreach (Category::all() as $category) {
            $this->assertStringNotContainsString('.svg', strtolower($category->image_url));
        }

        foreach (Brand::all() as $brand) {
            $this->assertStringNotContainsString('.svg', strtolower($brand->logo_url));
            $this->assertStringNotContainsString('.svg', strtolower($brand->banner_url));
        }

        foreach (Product::all() as $product) {
            $this->assertStringNotContainsString('.svg', strtolower($product->image_url));
            $this->assertGreaterThanOrEqual(2, count($product->gallery_urls));
            foreach ($product->gallery_urls as $galleryUrl) {
                $this->assertStringNotContainsString('.svg', strtolower($galleryUrl));
            }
        }

        foreach (ProductVariant::all() as $variant) {
            $this->assertStringNotContainsString('.svg', strtolower($variant->image_url));
        }

        foreach (Post::all() as $post) {
            $this->assertStringNotContainsString('.svg', strtolower($post->cover_url));
        }

        // CosmeticImageResolver rejects .svg paths and returns a real photo URL
        $resolvedFromSvg = CosmeticImageResolver::resolve('/images/products/old-placeholder.svg', 'product', 'labial-liquido-velvet-matte');
        $this->assertStringStartsWith('https://images.unsplash.com/photo-', $resolvedFromSvg);
        $this->assertStringNotContainsString('.svg', strtolower($resolvedFromSvg));
    }

    public function test_admin_can_manage_banners_and_upload_real_images_with_cleanup(): void
    {
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('Banners Editoriales');

        $this->actingAs($admin)
            ->get(route('admin.multimedia.index'))
            ->assertOk()
            ->assertSee('Repositorio Multimedia');

        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertNotFalse($pngBinary);

        $firstFile = UploadedFile::fake()->createWithContent('hero-campana.png', $pngBinary);
        $accentFile = UploadedFile::fake()->createWithContent('hero-accent.png', $pngBinary);

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'title' => 'Campaña Botánica Invierno',
                'subtitle' => 'Sérums reparadores con ceramidas y camu camu.',
                'eyebrow' => 'Edición Invierno',
                'badge_text' => 'NSOC DIGEMID',
                'button_text' => 'Ver Colección',
                'button_url' => '/catalogo',
                'position' => BannerPosition::HeroPrincipal->value,
                'sort_order' => 10,
                'is_active' => '1',
                'image_file' => $firstFile,
                'accent_image_file' => $accentFile,
            ])
            ->assertRedirect(route('admin.banners.index'));

        $banner = Banner::query()->where('title', 'Campaña Botánica Invierno')->firstOrFail();
        $this->assertStringStartsWith('storage/banners/', $banner->image_path);
        $oldRelativeMain = substr($banner->image_path, strlen('storage/'));
        Storage::disk('public')->assertExists($oldRelativeMain);

        // Replace main image and verify old file is deleted from disk
        $replacementFile = UploadedFile::fake()->createWithContent('hero-campana-v2.png', $pngBinary);

        $this->actingAs($admin)
            ->put(route('admin.banners.update', $banner), [
                'title' => 'Campaña Botánica Invierno Actualizada',
                'subtitle' => 'Nueva fotografía real.',
                'position' => BannerPosition::PromoSecundario->value,
                'sort_order' => 2,
                'is_active' => '1',
                'image_file' => $replacementFile,
            ])
            ->assertRedirect(route('admin.banners.index'));

        $banner->refresh();
        $newRelativeMain = substr($banner->image_path, strlen('storage/'));
        Storage::disk('public')->assertMissing($oldRelativeMain);
        Storage::disk('public')->assertExists($newRelativeMain);

        // Delete banner and verify files are cleaned up
        $this->actingAs($admin)
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect(route('admin.banners.index'));

        Storage::disk('public')->assertMissing($newRelativeMain);
    }
}
