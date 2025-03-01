<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\ErrorTestCase;
use ParaTest\Logging\JUnit\FailureTestCase;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Tests\TestBase;
use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\ExpectationFailedException;

use function file_get_contents;
use function file_put_contents;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\JUnit\Reader
 * @covers \ParaTest\Logging\JUnit\TestCase
 * @covers \ParaTest\Logging\JUnit\TestCaseWithMessage
 * @covers \ParaTest\Logging\JUnit\TestSuite
 */
final class ReaderTest extends TestBase
{
    /** @var string  */
    private $mixedPath;
    /** @var Reader  */
    private $mixed;
    /** @var Reader  */
    private $single;
    /** @var Reader  */
    private $empty;
    /** @var Reader  */
    private $multi_errors;

    public function setUpTest(): void
    {
        $this->mixedPath    = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $this->mixed        = new Reader($this->mixedPath);
        $this->single       = new Reader(FIXTURES . DS . 'results' . DS . 'single-wfailure.xml');
        $this->empty        = new Reader(FIXTURES . DS . 'results' . DS . 'empty-test-suite.xml');
        $this->multi_errors = new Reader(FIXTURES . DS . 'results' . DS . 'mixed-results-with-system-out.xml');
    }

    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader('/path/to/nowhere');
    }

    public function testFileCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader(FIXTURES . DS . 'results' . DS . 'empty.xml');
    }

    public function testMixedSuiteShouldConstructRootSuite(): TestSuite
    {
        $suite = $this->mixed->getSuite();
        static::assertSame('./test/fixtures/failing_tests', $suite->name);
        static::assertSame(19, $suite->tests);
        static::assertSame(10, $suite->assertions);
        static::assertSame(3, $suite->failures);
        static::assertSame(1, $suite->errors);
        static::assertSame(1.234567, $suite->time);

        return $suite;
    }

    /**
     * @depends testMixedSuiteShouldConstructRootSuite
     */
    public function testMixedSuiteConstructsChildSuites(TestSuite $suite): TestSuite
    {
        static::assertCount(3, $suite->suites);
        static::assertArrayHasKey('ParaTest\Tests\fixtures\failing_tests\UnitTestWithClassAnnotationTest', $suite->suites);
        $first = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest'];
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(4, $first->tests);
        static::assertSame(4, $first->assertions);
        static::assertSame(1, $first->failures);
        static::assertSame(0, $first->errors);
        static::assertSame(1.234567, $first->time);

        return $first;
    }

    /**
     * @depends testMixedSuiteConstructsChildSuites
     */
    public function testMixedSuiteConstructsTestCases(TestSuite $suite): void
    {
        static::assertCount(4, $suite->cases);
        $first = $suite->cases[0];
        static::assertSame('testTruth', $first->name);
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest', $first->class);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(21, $first->line);
        static::assertSame(1, $first->assertions);
        static::assertSame(1.234567, $first->time);
    }

    public function testMixedSuiteCasesLoadFailures(): void
    {
        $suite   = $this->mixed->getSuite();
        $failure = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest']->cases[1];
        static::assertInstanceOf(FailureTestCase::class, $failure);
        static::assertSame(ExpectationFailedException::class, $failure->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest::testFalsehood\n"
            . "Failed asserting that true is false.\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            $failure->text
        );
    }

    public function testMixedSuiteCasesLoadErrors(): void
    {
        $suite = $this->mixed->getSuite();
        $error = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest']->cases[0];
        static::assertInstanceOf(ErrorTestCase::class, $error);
        static::assertSame('RuntimeException', $error->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n"
            . "RuntimeException: Error!!!\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithErrorTest.php:21',
            $error->text
        );
    }

    public function testSingleSuiteShouldConstructRootSuite(): TestSuite
    {
        $suite = $this->single->getSuite();
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest', $suite->name);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php',
            $suite->file
        );
        static::assertSame(3, $suite->tests);
        static::assertSame(3, $suite->assertions);
        static::assertSame(1, $suite->failures);
        static::assertSame(0, $suite->errors);
        static::assertSame(1.234567, $suite->time);

        return $suite;
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteShouldHaveNoChildSuites($suite): void
    {
        static::assertCount(0, $suite->suites);
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteConstructsTestCases($suite): void
    {
        static::assertCount(3, $suite->cases);
        $first = $suite->cases[0];
        static::assertSame('testTruth', $first->name);
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest', $first->class);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php',
            $first->file
        );
        static::assertSame(17, $first->line);
        static::assertSame(1, $first->assertions);
        static::assertSame(1.234567, $first->time);
    }

    public function testSingleSuiteCasesLoadFailures(): void
    {
        $suite   = $this->single->getSuite();
        $failure = $suite->cases[1];
        static::assertInstanceOf(FailureTestCase::class, $failure);
        static::assertSame(ExpectationFailedException::class, $failure->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27',
            $failure->text
        );
    }

    public function testEmptySuiteConstructsTestCase(): void
    {
        $suite = $this->empty->getSuite();
        static::assertSame('', $suite->name);
        static::assertSame('', $suite->file);
        static::assertSame(0, $suite->tests);
        static::assertSame(0, $suite->assertions);
        static::assertSame(0, $suite->failures);
        static::assertSame(0, $suite->errors);
        static::assertSame(0.0, $suite->time);
    }

    public function testMixedGetTotals(): void
    {
        static::assertSame(19, $this->mixed->getTotalTests());
        static::assertSame(10, $this->mixed->getTotalAssertions());
        static::assertSame(1, $this->mixed->getTotalErrors());
        static::assertSame(3, $this->mixed->getTotalFailures());
        static::assertSame(2, $this->mixed->getTotalWarnings());
        static::assertSame(4, $this->mixed->getTotalSkipped());
        static::assertSame(1.234567, $this->mixed->getTotalTime());
    }

    public function testSingleGetTotals(): void
    {
        static::assertSame(3, $this->single->getTotalTests());
        static::assertSame(3, $this->single->getTotalAssertions());
        static::assertSame(0, $this->single->getTotalErrors());
        static::assertSame(1, $this->single->getTotalFailures());
        static::assertSame(0, $this->single->getTotalWarnings());
        static::assertSame(4, $this->mixed->getTotalSkipped());
        static::assertSame(1.234567, $this->single->getTotalTime());
    }

    public function testMixedGetFailureMessages(): void
    {
        $failures = $this->mixed->getFailures();
        static::assertCount(3, $failures);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest::testFalsehood\n"
            . "Failed asserting that true is false.\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            $failures[0]
        );
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27',
            $failures[1]
        );
    }

    public function testMixedGetErrorMessages(): void
    {
        $errors = $this->mixed->getErrors();
        static::assertCount(1, $errors);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n"
            . "RuntimeException: Error!!!\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithErrorTest.php:21',
            $errors[0]
        );
    }

    public function testMixedGetWarningMessages(): void
    {
        $warnings = $this->mixed->getWarnings();
        static::assertCount(2, $warnings);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testWarning\n" .
                'MyWarning',
            $warnings[0]
        );
    }

    public function testSingleGetMessages(): void
    {
        $failures = $this->single->getFailures();
        static::assertCount(1, $failures);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27',
            $failures[0]
        );
    }

    /**
     * https://github.com/paratestphp/paratest/issues/352
     */
    public function testGetMultiErrorsMessages(): void
    {
        $risky = $this->multi_errors->getRisky();
        static::assertCount(1, $risky);
        static::assertSame(
            'ParaTest\Tests\fixtures\system_out\SystemOutTest::testRisky' . "\n"
            . 'This test did not perform any assertions' . "\n"
            . "\n"
            . './test/fixtures/system_out/SystemOutTest.php:23',
            $risky[0]
        );
    }

    public function testGetSkippedMessagesForSkippingTest(): void
    {
        $reader  = new Reader(FIXTURES . DS . 'results' . DS . 'single-skipped.xml');
        $skipped = $reader->getSkipped();
        static::assertCount(1, $skipped);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testSkipped\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:50',
            $skipped[0]
        );
    }

    public function testGetSkippedMessagesForSkippingDataProviders(): void
    {
        $reader  = new Reader(FIXTURES . DS . 'results' . DS . 'data-provider-errors.xml');
        $skipped = $reader->getSkipped();
        static::assertCount(2, $skipped);
        static::assertSame(
            'ParaTest\Tests\fixtures\github\GH565\IssueTest::testIncompleteByDataProvider',
            $skipped[0]
        );
        static::assertSame(
            'ParaTest\Tests\fixtures\github\GH565\IssueTest::testSkippedByDataProvider',
            $skipped[1]
        );
    }

    public function testMixedGetFeedback(): void
    {
        static::assertSame('EWWFFFRRSSSS.......', $this->mixed->getFeedback());
    }

    public function testRemoveLog(): void
    {
        $contents = file_get_contents($this->mixedPath);
        $tmp      = (new TmpDirCreator())->create() . DS . 'dummy.xml';
        file_put_contents($tmp, $contents);
        $reader = new Reader($tmp);
        $reader->removeLog();
        static::assertFileDoesNotExist($tmp);
    }
}
