<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ComposerFoundationTest extends TestCase
{
    public function testProjectHasComposerAutoloadFoundation(): void
    {
        self::assertFileExists(__DIR__ . '/../../vendor/autoload.php');
        self::assertTrue(extension_loaded('pdo'));
        self::assertTrue(extension_loaded('dom'));
    }
}
