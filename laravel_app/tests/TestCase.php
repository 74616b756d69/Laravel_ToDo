<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 画面のテストはビルド済みの CSS / JS（public/build）に頼らない。
        // CI で先にビルドしなくても通り、開発中の vite build --watch が
        // 書き出し直している瞬間に走っても落ちないようにする
        $this->withoutVite();
    }
}
