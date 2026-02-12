<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$item = App\Models\VocabItem::orderBy('id','desc')->first();
var_export($item ? $item->toArray() : null);
