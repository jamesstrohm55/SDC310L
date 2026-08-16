<?php
/**
 * Minimal assertion harness for the SDC310L course project.
 *
 * No external dependencies: PHPUnit is not part of the XAMPP stack this
 * project is graded on, so the tests run with the same `php` binary as the
 * application.
 *
 * Run the suite with:  php tests/run.php
 */

declare(strict_types=1);

final class TestRun
{
    public static int $passed = 0;
    /** @var string[] */
    public static array $failures = [];
    public static string $group = '';
}

function describe(string $group): void
{
    TestRun::$group = $group;
    echo "\n" . $group . "\n";
}

function pass(string $label): void
{
    TestRun::$passed++;
    echo "  ok    " . $label . "\n";
}

function fail(string $label, string $detail): void
{
    TestRun::$failures[] = TestRun::$group . ' / ' . $label . "\n        " . $detail;
    echo "  FAIL  " . $label . "\n        " . $detail . "\n";
}

/** Strict equality assertion. */
function assert_same($expected, $actual, string $label): void
{
    if ($expected === $actual) {
        pass($label);
        return;
    }
    fail($label, sprintf(
        'expected %s, got %s',
        var_export($expected, true),
        var_export($actual, true)
    ));
}

function assert_true($actual, string $label): void
{
    assert_same(true, $actual, $label);
}

/** Asserts the callable throws, and returns the exception for inspection. */
function assert_throws(callable $fn, string $label): ?Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        pass($label);
        return $e;
    }
    fail($label, 'expected an exception, none was thrown');
    return null;
}

function report_and_exit(): void
{
    $failed = count(TestRun::$failures);
    echo "\n" . str_repeat('-', 60) . "\n";
    echo sprintf("%d passed, %d failed\n", TestRun::$passed, $failed);
    if ($failed > 0) {
        echo "\nFailures:\n";
        foreach (TestRun::$failures as $f) {
            echo '  - ' . $f . "\n";
        }
        exit(1);
    }
    exit(0);
}
