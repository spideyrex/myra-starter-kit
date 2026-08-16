<?php

return [
    App\Providers\AppServiceProvider::class,
    // >>> MYRA v2.6 [C] START
    App\Providers\BrandServiceProvider::class,
    // <<< MYRA v2.6 [C] END
    App\Providers\GlobalSearchServiceProvider::class,
    App\Providers\MyraServiceProvider::class,
];
