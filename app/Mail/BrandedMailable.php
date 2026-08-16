<?php

namespace App\Mail;

use App\Brand\BrandManager;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

abstract class BrandedMailable extends Mailable
{
    /**
     * Wraps rendered HTML in the brand layout. With the brand manager off this
     * returns the caller's HTML untouched — today's exact output.
     */
    protected function brandView(string $bodyHtml, array $data = []): Content
    {
        $manager = app(BrandManager::class);
        $brand = $manager->current();

        if (! $brand->enabled) {
            return new Content(htmlString: $bodyHtml);
        }

        return new Content(view: 'emails.layout', with: array_merge([
            'brand' => $brand,
            'bodyHtml' => $bodyHtml,
            'logoBytes' => $manager->logoBytes('email'),
            'subjectLine' => $data['subjectLine'] ?? $brand->name,
        ], $data));
    }
}
