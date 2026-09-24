<?php
namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

class TestRunner
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $errors = [];

    public static function run()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        echo "\n======================================================\n";
        echo "   MEEM LMS AUTOMATED REGRESSION SUITE\n";
        echo "======================================================\n\n";

        $testFiles = glob(__DIR__ . '/Feature/*Test.php');
        foreach ($testFiles as $file) {
            if (basename($file) === 'ExampleTest.php') continue;
            require_once $file;
            $className = 'Tests\\Feature\\' . basename($file, '.php');
            if (class_exists($className)) {
                self::runClass(new $className());
            }
        }

        echo "\n------------------------------------------------------\n";
        echo "TEST SUMMARY: " . (self::$passed + self::$failed) . " Total | " .
             "\033[32m" . self::$passed . " Passed\033[0m | " .
             (self::$failed > 0 ? "\033[31m" . self::$failed . " Failed\033[0m" : "0 Failed") . "\n";
        echo "------------------------------------------------------\n";

        if (!empty(self::$errors)) {
            echo "\nFAILURES:\n";
            foreach (self::$errors as $err) {
                echo "  ✖ " . $err . "\n";
            }
            exit(1);
        }
    }

    private static function runClass($instance)
    {
        $reflection = new \ReflectionClass($instance);
        echo "Suite: " . $reflection->getShortName() . "\n";

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'test')) {
                $name = $method->getName();
                try {
                    if (method_exists($instance, 'setUp')) {
                        $instance->setUp();
                    }
                    $instance->$name();
                    echo "  ✔ " . $name . "\n";
                    self::$passed++;
                } catch (\Throwable $e) {
                    echo "  ✖ " . $name . " (" . $e->getMessage() . ")\n";
                    self::$failed++;
                    self::$errors[] = $reflection->getShortName() . '::' . $name . ' -> ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine();
                }
            }
        }
        echo "\n";
    }

    public static function assertTrue($condition, $message = 'Failed asserting that condition is true')
    {
        if (!$condition) {
            throw new \Exception($message);
        }
    }

    public static function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Failed asserting that " . var_export($actual, true) . " equals " . var_export($expected, true);
            throw new \Exception($msg);
        }
    }

    public static function assertCount($expectedCount, $array, $message = '')
    {
        $actualCount = is_countable($array) ? count($array) : 0;
        if ($expectedCount !== $actualCount) {
            $msg = $message ?: "Failed asserting that array count $actualCount equals expected $expectedCount";
            throw new \Exception($msg);
        }
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    TestRunner::run();
}
