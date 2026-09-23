<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\InteractsWithCartSession;

abstract class TestCase extends BaseTestCase
{
    use ActsAsAdmin;
    use InteractsWithCartSession;
}
