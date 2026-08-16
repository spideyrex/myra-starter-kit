<?php

namespace App\Http\Controllers;

use App\Brand\BrandManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrandManifestController extends Controller
{
    public function __invoke(Request $request, BrandManager $brand): Response
    {
        $etag = '"'.$brand->hash().'"';

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()
            ->json($brand->manifest(), 200, [], JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=0, must-revalidate');
    }
}
