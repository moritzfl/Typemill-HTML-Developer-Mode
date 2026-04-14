<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\versions\Models\LineDiff;

/**
 * Tests for LineDiff::compare().
 *
 * LineDiff implements an LCS-based line diff. These tests verify the
 * correctness of the algorithm — operation types, line numbers, and
 * summary stats — across the edge cases most likely to hide bugs.
 */
class LineDiffTest extends TestCase
{
    private LineDiff $diff;

    protected function setUp(): void
    {
        $this->diff = new LineDiff();
    }

    // -------------------------------------------------------------------------
    // Empty inputs
    // -------------------------------------------------------------------------

    public function testBothEmptyProducesNoOperations(): void
    {
        $result = $this->diff->compare('', '');

        $this->assertSame([], $result['lines']);
        $this->assertSame(['added' => 0, 'removed' => 0], $result['stats']);
    }

    public function testOldEmptyNewContentProducesOnlyAdds(): void
    {
        $result = $this->diff->compare('', "line1\nline2");

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['add', 'add'], $types);
        $this->assertSame(['added' => 2, 'removed' => 0], $result['stats']);
    }

    public function testOldContentNewEmptyProducesOnlyRemoves(): void
    {
        $result = $this->diff->compare("line1\nline2", '');

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['remove', 'remove'], $types);
        $this->assertSame(['added' => 0, 'removed' => 2], $result['stats']);
    }

    // -------------------------------------------------------------------------
    // Identical text
    // -------------------------------------------------------------------------

    public function testIdenticalSingleLineProducesContext(): void
    {
        $result = $this->diff->compare('hello', 'hello');

        $this->assertCount(1, $result['lines']);
        $this->assertSame('context', $result['lines'][0]['type']);
        $this->assertSame(['added' => 0, 'removed' => 0], $result['stats']);
    }

    public function testIdenticalMultilineProducesAllContext(): void
    {
        $text   = "line1\nline2\nline3";
        $result = $this->diff->compare($text, $text);

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['context', 'context', 'context'], $types);
        $this->assertSame(['added' => 0, 'removed' => 0], $result['stats']);
    }

    // -------------------------------------------------------------------------
    // Add lines
    // -------------------------------------------------------------------------

    public function testAddingLineAtEnd(): void
    {
        $result = $this->diff->compare('line1', "line1\nline2");

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['context', 'add'], $types);
        $this->assertSame(['added' => 1, 'removed' => 0], $result['stats']);
    }

    public function testAddingLineAtStart(): void
    {
        $result = $this->diff->compare('line2', "line1\nline2");

        $types = array_column($result['lines'], 'type');
        $this->assertContains('add', $types);
        $this->assertContains('context', $types);
        $this->assertSame(['added' => 1, 'removed' => 0], $result['stats']);
    }

    public function testAddingLineInMiddle(): void
    {
        $result = $this->diff->compare("a\nc", "a\nb\nc");

        $this->assertSame(['added' => 1, 'removed' => 0], $result['stats']);
    }

    // -------------------------------------------------------------------------
    // Remove lines
    // -------------------------------------------------------------------------

    public function testRemovingLineAtEnd(): void
    {
        $result = $this->diff->compare("line1\nline2", 'line1');

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['context', 'remove'], $types);
        $this->assertSame(['added' => 0, 'removed' => 1], $result['stats']);
    }

    public function testRemovingLineAtStart(): void
    {
        $result = $this->diff->compare("line1\nline2", 'line2');

        $types = array_column($result['lines'], 'type');
        $this->assertContains('remove', $types);
        $this->assertContains('context', $types);
        $this->assertSame(['added' => 0, 'removed' => 1], $result['stats']);
    }

    public function testRemovingLineInMiddle(): void
    {
        $result = $this->diff->compare("a\nb\nc", "a\nc");

        $this->assertSame(['added' => 0, 'removed' => 1], $result['stats']);
    }

    // -------------------------------------------------------------------------
    // Replace / mixed changes
    // -------------------------------------------------------------------------

    public function testReplacingAllLinesProducesRemoveAndAdd(): void
    {
        $result = $this->diff->compare('old', 'new');

        $types = array_column($result['lines'], 'type');
        $this->assertContains('remove', $types);
        $this->assertContains('add', $types);
        $this->assertSame(['added' => 1, 'removed' => 1], $result['stats']);
    }

    public function testPartialReplacementPreservesUnchangedLines(): void
    {
        $result = $this->diff->compare("header\nold body\nfooter", "header\nnew body\nfooter");

        $this->assertSame(['added' => 1, 'removed' => 1], $result['stats']);

        // header and footer must appear as context
        $contextLines = array_filter(
            $result['lines'],
            static fn ($op) => $op['type'] === 'context'
        );
        $contextText = array_column(array_values($contextLines), 'line');
        $this->assertContains('header', $contextText);
        $this->assertContains('footer', $contextText);
    }

    // -------------------------------------------------------------------------
    // Operation shape and line numbers
    // -------------------------------------------------------------------------

    public function testResultStructureHasRequiredKeys(): void
    {
        $result = $this->diff->compare('a', 'b');

        $this->assertArrayHasKey('lines', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('added', $result['stats']);
        $this->assertArrayHasKey('removed', $result['stats']);
    }

    public function testContextOperationHasBothLineNumbers(): void
    {
        $result = $this->diff->compare('hello', 'hello');
        $op     = $result['lines'][0];

        $this->assertSame(1, $op['old_line']);
        $this->assertSame(1, $op['new_line']);
    }

    public function testRemoveOperationHasNullNewLine(): void
    {
        $result = $this->diff->compare('line1', '');
        $op     = $result['lines'][0];

        $this->assertSame('remove', $op['type']);
        $this->assertNull($op['new_line']);
        $this->assertSame(1, $op['old_line']);
    }

    public function testAddOperationHasNullOldLine(): void
    {
        $result = $this->diff->compare('', 'line1');
        $op     = $result['lines'][0];

        $this->assertSame('add', $op['type']);
        $this->assertNull($op['old_line']);
        $this->assertSame(1, $op['new_line']);
    }

    public function testLineNumbersIncrementForContext(): void
    {
        $text   = "a\nb\nc";
        $result = $this->diff->compare($text, $text);

        $oldLines = array_column($result['lines'], 'old_line');
        $newLines = array_column($result['lines'], 'new_line');
        $this->assertSame([1, 2, 3], $oldLines);
        $this->assertSame([1, 2, 3], $newLines);
    }

    public function testEachOperationHasALineKey(): void
    {
        $result = $this->diff->compare("a\nb", "a\nc");

        foreach ($result['lines'] as $op) {
            $this->assertArrayHasKey('line', $op);
        }
    }

    // -------------------------------------------------------------------------
    // Line-ending normalisation
    // -------------------------------------------------------------------------

    public function testWindowsLineEndingsTreatedAsIdentical(): void
    {
        // \r\n and \n should both split into the same two lines
        $result = $this->diff->compare("line1\r\nline2", "line1\nline2");

        $types = array_column($result['lines'], 'type');
        $this->assertSame(['context', 'context'], $types);
        $this->assertSame(['added' => 0, 'removed' => 0], $result['stats']);
    }

    public function testMixedLineEndingsAreHandled(): void
    {
        $result = $this->diff->compare("a\r\nb\nc", "a\nb\nc");

        $this->assertSame(['added' => 0, 'removed' => 0], $result['stats']);
    }
}
