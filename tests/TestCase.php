<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keine echten Anfragen an externe Dienste (Wetter); Tests faken sie gezielt.
        Http::preventStrayRequests();
    }
}
