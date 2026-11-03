<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page tests assert Inertia props, so they must not depend on a built asset manifest.
        $this->withoutVite();
    }
}
