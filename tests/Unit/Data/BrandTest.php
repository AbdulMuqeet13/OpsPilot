<?php

use App\Data\Brand;

it('picks a readable text color for the primary color', function (string $primaryColor, string $expectedForeground) {
    $brand = new Brand(name: 'Acme', primaryColor: $primaryColor, logoUrl: null, supportEmail: null);

    expect($brand->primaryForegroundColor())->toBe($expectedForeground);
})->with([
    'dark indigo' => ['#4f46e5', '#ffffff'],
    'deep brown' => ['#9a5b2e', '#ffffff'],
    'black' => ['#000000', '#ffffff'],
    'pale yellow' => ['#fde68a', '#0f172a'],
    'white' => ['#ffffff', '#0f172a'],
]);
