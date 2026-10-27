<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/** Zieht den Klartext-Token aus dem Link einer verschickten Mail. */
function tokenFromUrl(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH));
}
