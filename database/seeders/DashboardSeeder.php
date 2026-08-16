<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\Banner;
use App\Models\Page;
use App\Models\Review;
use App\Models\WebsiteAnalytic;
use Illuminate\Support\Str;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── 1. SEED CATEGORIES ──
        $c1 = Category::create(['name' => 'Personalized Frames', 'slug' => 'personalized-frames']);
        $c2 = Category::create(['name' => 'Handmade Rakhis', 'slug' => 'handmade-rakhis']);
        $c3 = Category::create(['name' => 'Gift Hampers', 'slug' => 'gift-hampers']);

        // ── 2. SEED PRODUCTS WITH CUSTOM ATTRIBUTES ──
        $weddingFrame = Product::create([
            'category_id' => $c1->id,
            'title' => 'Wedding Memory Frame',
            'slug' => 'wedding-memory-frame',
            'base_price' => 1299.00,
            'description' => 'A beautiful bespoke wedding keepsake frame with customized attributes.',
            'image' => '/images/products/wedding-frame.jpg',
            'is_new_discovery' => true,
            'is_wedding_special' => true,
        ]);

        // Attributes configuration matching handwritten notes exactly:
        
        // 1. Type of Preservation
        $attrPreserv = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Type of Preservation']);
        ProductAttributeValue::create(['product_attribute_id' => $attrPreserv->id, 'value' => '3D', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrPreserv->id, 'value' => 'Covered with Acrylic', 'price_modifier' => 150.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrPreserv->id, 'value' => 'Deep Cast', 'price_modifier' => 450.00]);

        // 2. Shapes
        $attrShapes = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Shapes']);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Round', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Round Zig Zag', 'price_modifier' => 50.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Heart', 'price_modifier' => 100.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Rectangle', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Rectangle Zig Zag', 'price_modifier' => 50.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrShapes->id, 'value' => 'Hexagon', 'price_modifier' => 120.00]);

        // 3. Sizes
        $attrSizes = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Sizes']);
        ProductAttributeValue::create(['product_attribute_id' => $attrSizes->id, 'value' => '8"x10" format', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrSizes->id, 'value' => '12"x12" format', 'price_modifier' => 200.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrSizes->id, 'value' => '8" format', 'price_modifier' => -50.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrSizes->id, 'value' => '10" format', 'price_modifier' => 50.00]);

        // 4. Type of Filling
        $attrFilling = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Type of Filling']);
        ProductAttributeValue::create(['product_attribute_id' => $attrFilling->id, 'value' => 'Broken petal with shimmer', 'price_modifier' => 40.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrFilling->id, 'value' => 'Filled with crushed petal', 'price_modifier' => 30.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrFilling->id, 'value' => 'Crushed Petals', 'price_modifier' => 20.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrFilling->id, 'value' => 'Loose Petals', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrFilling->id, 'value' => 'with color', 'price_modifier' => 50.00]);

        // 5. Textual Format
        $attrTextual = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Textual Format']);
        ProductAttributeValue::create(['product_attribute_id' => $attrTextual->id, 'value' => 'With text', 'price_modifier' => 80.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrTextual->id, 'value' => 'Without text', 'price_modifier' => 0.00]);

        // 6. Type of Flowers
        $attrFlowers = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Type of Flowers']);
        ProductAttributeValue::create(['product_attribute_id' => $attrFlowers->id, 'value' => 'Your flowers', 'price_modifier' => 0.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrFlowers->id, 'value' => 'Our flowers', 'price_modifier' => 120.00]);

        // 7. Pictorial Format
        $attrPictorial = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Pictorial Format']);
        ProductAttributeValue::create(['product_attribute_id' => $attrPictorial->id, 'value' => 'With picture', 'price_modifier' => 100.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrPictorial->id, 'value' => 'Without picture', 'price_modifier' => 0.00]);

        // 8. Accessories
        $attrAccessories = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Accessories']);
        ProductAttributeValue::create(['product_attribute_id' => $attrAccessories->id, 'value' => 'With Stand', 'price_modifier' => 60.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrAccessories->id, 'value' => 'With Chains', 'price_modifier' => 80.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrAccessories->id, 'value' => 'With Hook', 'price_modifier' => 30.00]);

        // 9. Outline
        $attrOutline = ProductAttribute::create(['product_id' => $weddingFrame->id, 'name' => 'Outline']);
        ProductAttributeValue::create(['product_attribute_id' => $attrOutline->id, 'value' => 'With frame', 'price_modifier' => 150.00]);
        ProductAttributeValue::create(['product_attribute_id' => $attrOutline->id, 'value' => 'Without frame (frameless)', 'price_modifier' => 0.00]);

        // Add some more mock products
        Product::create([
            'category_id' => $c2->id,
            'title' => 'Resin Floral Rakhi',
            'slug' => 'resin-floral-rakhi',
            'base_price' => 299.00,
            'description' => 'Beautiful resin casted Rakhi with real flowers embedded inside.',
            'image' => '/images/products/floral-rakhi.jpg',
            'is_new_discovery' => true,
        ]);

        // ── 3. SEED BANNERS ──
        Banner::create([
            'title' => 'MADE BY HANDS. MEANT FOR THE HEART.',
            'subtitle' => 'Personalized handmade frames and keepsakes that tell your story.',
            'image_url' => '/images/banner-hero.jpg',
            'button_text' => 'SHOP THE COLLECTION',
            'button_url' => '/shop',
            'position' => 'hero',
        ]);

        // ── 4. SEED PAGES ──
        Page::create([
            'title' => 'Festival Specials',
            'slug' => 'festival-specials',
            'content' => 'Explore our limited-run customized frames crafted especially for festival seasons.',
        ]);

        // ── 5. SEED REVIEWS ──
        Review::create([
            'product_id' => $weddingFrame->id,
            'reviewer_name' => 'Aditi Sharma',
            'review_text' => 'The deep cast frame with preserved flowers is absolutely gorgeous!',
            'rating' => 5,
            'is_approved' => true,
        ]);

        // ── 6. SEED WEBSITE ANALYTICS ──
        $today = now();
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            WebsiteAnalytic::create([
                'date' => $date->format('Y-m-d'),
                'daily_user_count' => rand(1200, 2000),
                'weekly_user_count' => rand(8000, 11000),
                'bounce_rate' => rand(3500, 5000) / 100, // 35.00% to 50.00%
                'session_duration' => rand(150, 320), // in seconds
            ]);
        }
    }
}
