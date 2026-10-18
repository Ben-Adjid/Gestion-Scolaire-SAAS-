<?php

namespace Tests;

use App\Models\Etablissement;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Etablissement::firstOrCreate(
            ['id' => 1],
            ['nom' => 'Etablissement de test']
        );

        Cache::flush();
    }
}
