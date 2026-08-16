<?php

namespace App\Http\Controllers;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrandAssetController extends Controller
{
    public function icon(Request $request, BrandManager $brand, BrandAssetPipeline $pipeline, string $size): Response
    {
        $px = (int) $size;
        $bytes = $pipeline->derivativeBytes('icon-'.$px) ?? $pipeline->markPng($px, $px);

        abort_if($bytes === null, 404);

        return $this->cached($request, $brand, $bytes, 'image/png');
    }

    /** The generated fallback — the stock Laravel icon is never served. */
    public function favicon(Request $request, BrandManager $brand): Response
    {
        $current = $brand->current();
        $primary = $current->palette->primaryHex;
        $foreground = $current->palette->foregroundOn($primary);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img">'
            .'<title>'.e($current->name).'</title>'
            .'<rect width="64" height="64" rx="12" fill="'.e($primary).'"/>'
            .'<text x="32" y="43" text-anchor="middle" font-family="system-ui, sans-serif" '
            .'font-size="36" font-weight="700" fill="'.e($foreground).'">'.e($current->initial()).'</text>'
            .'</svg>';

        return $this->cached($request, $brand, $svg, 'image/svg+xml');
    }

    private function cached(Request $request, BrandManager $brand, string $body, string $mime): Response
    {
        $etag = '"'.$brand->hash().'"';

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($body, 200, [
            'Content-Type' => $mime,
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
