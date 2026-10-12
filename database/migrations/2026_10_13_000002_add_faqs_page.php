<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Starting content for the FAQs page (/faqs), editable in the dashboard under Pages.
 * Format: one question per block, with an empty line between blocks; the first line of a block
 * is the question and the lines under it are the answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'faqs')->exists()) return;

        $content = <<<'TEXT'
How do I place an order?
Add the pieces you like to your bag, fill in your name and phone number, and tap "Send the order list". WhatsApp opens with your order already written out. Send it, and we confirm the details, price and payment with you there.

Can I personalise my piece?
Yes. Our pieces are made to order and can carry names, dates, photos or a short message. Choose the options on the product page, attach a reference photo in your bag if you have one, and share any other details with us on WhatsApp.

Can you preserve my own flowers?
Yes. Our varmala, bouquet and other preservation pieces can be made with your own flowers. Message us on WhatsApp before sending them and we will guide you through the next steps.

How long does it take to make my order?
Every piece is handmade after you order it, so the time depends on the piece and how much it is personalised. We confirm the timeline with you on WhatsApp before we start.

How do I pay?
Once we confirm your order on WhatsApp, we share the payment details with you there.

How can I track my order?
Go to www.aartcafe.com/track-order and enter your order number (it looks like #AART-12) and the phone number you ordered with.

Do you offer free delivery?
Many of our pieces come with free delivery, shown on the product page. For delivery to your city and any charges, ask us on WhatsApp.

How do I reach you?
Tap Contact Us at the bottom of any page, or message us on WhatsApp at +91 92679 43830.
TEXT;

        DB::table('pages')->insert([
            'title' => 'Frequently Asked Questions',
            'slug' => 'faqs',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'faqs')->delete();
    }
};
