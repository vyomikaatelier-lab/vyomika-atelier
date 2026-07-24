<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ActsAsAdmin;

abstract class TestCase extends BaseTestCase
{
    use ActsAsAdmin;
}
