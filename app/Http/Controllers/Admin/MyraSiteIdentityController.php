<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource\SingularResource;
use App\Admin\Traits\HandlesSingularRecord;
use App\Http\Controllers\Controller;
use App\Models\MyraSiteIdentity;

class MyraSiteIdentityController extends Controller
{
    use HandlesSingularRecord;

    protected function singular(): SingularResource
    {
        return SingularResource::make('learning.site-identity')
            // The demo module declares a single `view` ability (config/shield.php),
            // as every other demo write route already assumes.
            ->abilities('demo.view', 'demo.view')
            ->model(MyraSiteIdentity::class)
            ->page('Admin/Learning/SiteIdentity/Edit')
            ->rules([
                'name' => ['nullable', 'string', 'max:255'],
                'tagline' => ['nullable', 'string', 'max:255'],
            ]);
    }
}
