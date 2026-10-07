<?php

namespace Tests;

use App\Domain\Company\Services\CurrentCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        CurrentCompany::clear();
        parent::tearDown();
    }
}
