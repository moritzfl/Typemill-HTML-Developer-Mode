<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\versions\Models\VersionStore;

/**
 * Tests for VersionStore::generateVersionId().
 *
 * The ID must be unpredictable (cryptographically random) and unique. It
 * previously used uniqid() which is time-based and collision-prone under
 * concurrent load; it now uses random_bytes().
 */
class VersionStoreIdTest extends TestCase
{
    private \ReflectionMethod $method;
    private VersionStore $store;

    protected function setUp(): void
    {
        $this->store  = new VersionStore();
        $this->method = new \ReflectionMethod($this->store, 'generateVersionId');
    }

    public function testIdStartsWithVersionPrefix(): void
    {
        $id = $this->generate();
        $this->assertStringStartsWith('version_', $id);
    }

    public function testSuffixIsLowercaseHex(): void
    {
        $id     = $this->generate();
        $suffix = substr($id, strlen('version_'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $suffix, 'Suffix must be lowercase hex');
    }

    public function testSuffixIs24CharactersLong(): void
    {
        // 12 random bytes → 24 hex characters
        $id     = $this->generate();
        $suffix = substr($id, strlen('version_'));
        $this->assertSame(24, strlen($suffix));
    }

    public function testGenerates50UniqueIds(): void
    {
        $ids = array_map(fn () => $this->generate(), range(1, 50));
        $this->assertCount(50, array_unique($ids), 'All generated IDs must be unique');
    }

    private function generate(): string
    {
        return $this->method->invoke($this->store);
    }
}
