<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\versions\Models\VersionStore;

/**
 * Tests for the path-traversal guard in VersionStore::isValidSnapshotPath().
 *
 * This method is the security boundary that prevents a tampered version record
 * from writing files outside the intended storage directory during a restore.
 */
class VersionStorePathTest extends TestCase
{
    private \ReflectionMethod $method;
    private VersionStore $store;

    protected function setUp(): void
    {
        $this->store  = new VersionStore();
        $this->method = new \ReflectionMethod($this->store, 'isValidSnapshotPath');
    }

    // -------------------------------------------------------------------------
    // Valid paths
    // -------------------------------------------------------------------------

    public function testSimpleFilenameIsValid(): void
    {
        $this->assertValid('page.md');
    }

    public function testRelativePathIsValid(): void
    {
        $this->assertValid('folder/page.md');
    }

    public function testDeepRelativePathIsValid(): void
    {
        $this->assertValid('a/b/c/file.yaml');
    }

    public function testFileWithDotInNameIsValid(): void
    {
        $this->assertValid('my.page.txt');
    }

    // -------------------------------------------------------------------------
    // Invalid paths
    // -------------------------------------------------------------------------

    public function testEmptyPathIsInvalid(): void
    {
        $this->assertInvalid('');
    }

    public function testAbsolutePathIsInvalid(): void
    {
        $this->assertInvalid('/etc/passwd');
    }

    public function testAbsolutePathToSettingsIsInvalid(): void
    {
        $this->assertInvalid('/var/www/html/settings/settings.yaml');
    }

    public function testAbsoluteTmpPathIsInvalid(): void
    {
        $this->assertInvalid('/tmp/evil.md');
    }

    public function testSimpleTraversalIsInvalid(): void
    {
        $this->assertInvalid('../etc/passwd');
    }

    public function testNestedTraversalIsInvalid(): void
    {
        $this->assertInvalid('foo/../../../etc/passwd');
    }

    public function testTraversalInMiddleOfPathIsInvalid(): void
    {
        $this->assertInvalid('a/b/../../../c');
    }

    public function testSingleDotDotIsInvalid(): void
    {
        $this->assertInvalid('..');
    }

    public function testNullByteIsInvalid(): void
    {
        $this->assertInvalid("foo\0bar.md");
    }

    public function testNullByteAloneIsInvalid(): void
    {
        $this->assertInvalid("\0");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertValid(string $path): void
    {
        $this->assertTrue(
            $this->method->invoke($this->store, $path),
            "Expected path to be valid: \"{$path}\""
        );
    }

    private function assertInvalid(string $path): void
    {
        $this->assertFalse(
            $this->method->invoke($this->store, $path),
            "Expected path to be invalid: \"{$path}\""
        );
    }
}
