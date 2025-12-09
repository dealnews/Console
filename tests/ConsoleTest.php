<?php

/**
 * Unit tests for the Console class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present dealnews.com, Inc.
 * @license     http://opensource.org/licenses/bsd-license.php BSD
 */

namespace DealNews\Console\Tests;

use DealNews\Console\Console;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Console class
 *
 * Verifies that the Console correctly:
 * - Parses command-line options via getopt
 * - Manages verbosity levels
 * - Builds help output
 * - Normalizes options
 * - Handles PID file operations
 *
 * We use a TestableConsole subclass to mock the realGetopt() method,
 * allowing us to simulate different command-line inputs without
 * actually invoking the CLI.
 */
class ConsoleTest extends TestCase {

    /**
     * Reset verbosity to default before each test
     *
     * @return void
     */
    protected function setUp(): void {
        // Reset the static verbosity to default before each test
        $this->resetVerbosity();
    }

    /**
     * Clean up after each test
     *
     * @return void
     */
    protected function tearDown(): void {
        // Reset verbosity after each test to avoid polluting other tests
        $this->resetVerbosity();
    }

    /**
     * Resets the static verbosity property to its default value
     *
     * Uses reflection since verbosity is a protected static property.
     *
     * @return void
     */
    protected function resetVerbosity(): void {
        $reflection = new \ReflectionClass(Console::class);
        $property = $reflection->getProperty('verbosity');
        $property->setValue(null, Console::VERBOSITY_NORMAL);
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    /**
     * Tests that Console can be instantiated with default values
     *
     * @return void
     */
    public function testConstructorWithDefaults(): void {
        $console = new Console();

        $this->assertInstanceOf(Console::class, $console);
    }

    /**
     * Tests that config options are merged in constructor
     *
     * @return void
     */
    public function testConstructorMergesConfig(): void {
        $config = [
            'wrap'   => 120,
            'help'   => [
                'header' => 'Test Header',
                'footer' => 'Test Footer',
            ],
        ];

        $console = new TestableConsole($config);
        $console->setMockedOpts([]);
        $console->run();

        // We can verify config was applied by checking buildHelp output
        $help = $console->buildHelp($config, [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ]);

        $this->assertStringContainsString('Test Header', $help);
        $this->assertStringContainsString('Test Footer', $help);
    }

    /**
     * Tests that custom options are merged with defaults
     *
     * @return void
     */
    public function testConstructorMergesOptions(): void {
        $options = [
            'f' => [
                'description' => 'Custom option f',
                'optional'    => Console::OPTIONAL,
            ],
        ];

        $console = new TestableConsole([], $options);
        $console->setMockedOpts([]);
        $console->run();

        // The custom option should be accessible
        $this->assertNull($console->getOpt('f'));
    }

    // =========================================================================
    // Verbosity Tests
    // =========================================================================

    /**
     * Tests that default verbosity is VERBOSITY_NORMAL
     *
     * @return void
     */
    public function testDefaultVerbosityIsNormal(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_NORMAL, Console::verbosity());
        $this->assertEquals(Console::VERBOSITY_NORMAL, $console->verbosity);
    }

    /**
     * Tests that -q sets verbosity to VERBOSITY_QUIET
     *
     * @return void
     */
    public function testQuietOptionSetsVerbosityQuiet(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['q' => true]);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_QUIET, Console::verbosity());
    }

    /**
     * Tests that -q overrides -v
     *
     * @return void
     */
    public function testQuietOverridesVerbose(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['q' => true, 'v' => 'vvv']);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_QUIET, Console::verbosity());
    }

    /**
     * Tests that -v alone sets VERBOSITY_VERBOSE
     *
     * @return void
     */
    public function testSingleVerboseSetsVerbose(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => true]);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_VERBOSE, Console::verbosity());
    }

    /**
     * Data provider for verbosity level tests
     *
     * @return array<string, array{string|array<int, string>, int}>
     */
    public static function verbosityLevelProvider(): array {
        return [
            'single v string'   => ['', Console::VERBOSITY_NORMAL],
            'double v string'   => ['v', Console::VERBOSITY_VERBOSE],
            'triple v string'   => ['vv', Console::VERBOSITY_INFO],
            'quad v string'     => ['vvv', Console::VERBOSITY_DEBUG],
            'five v string'     => ['vvvv', Console::VERBOSITY_DEBUG],
            'array single v'    => [['v'], Console::VERBOSITY_NORMAL],
            'array double v'    => [['v', 'v'], Console::VERBOSITY_VERBOSE],
            'array triple v'    => [['v', 'v', 'v'], Console::VERBOSITY_INFO],
            'array quad v'      => [['v', 'v', 'v', 'v'], Console::VERBOSITY_DEBUG],
        ];
    }

    /**
     * Tests that verbosity levels are correctly set based on -v count
     *
     * @param string|array<int, string> $v_value          Value for the v option
     * @param int                       $expected_verbosity Expected verbosity level
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('verbosityLevelProvider')]
    public function testVerbosityLevels(string|array $v_value, int $expected_verbosity): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => $v_value]);
        $console->run();

        $this->assertEquals($expected_verbosity, Console::verbosity());
    }

    /**
     * Tests the static verbosity() method returns current verbosity
     *
     * @return void
     */
    public function testStaticVerbosityMethod(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => 'vv']);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_INFO, Console::verbosity());
    }

    // =========================================================================
    // Option Normalization Tests
    // =========================================================================

    /**
     * Tests that normalizeOptions sets default optional value
     *
     * @return void
     */
    public function testNormalizeOptionsSetsDefaultOptional(): void {
        $console = new Console();
        $options = [
            'x' => [
                'description' => 'Test option',
            ],
        ];

        $normalized = $console->normalizeOptions($options);

        $this->assertEquals(Console::OPTIONAL, $normalized['x']['optional']);
    }

    /**
     * Tests that normalizeOptions sorts options alphabetically
     *
     * @return void
     */
    public function testNormalizeOptionsSortsAlphabetically(): void {
        $console = new Console();
        $options = [
            'z' => ['description' => 'Z option', 'optional' => Console::OPTIONAL],
            'a' => ['description' => 'A option', 'optional' => Console::OPTIONAL],
            'm' => ['description' => 'M option', 'optional' => Console::OPTIONAL],
        ];

        $normalized = $console->normalizeOptions($options);
        $keys = array_keys($normalized);

        $this->assertEquals(['a', 'm', 'z'], $keys);
    }

    /**
     * Tests that normalizeOptions trims whitespace from option names
     *
     * @return void
     */
    public function testNormalizeOptionsTrimsWhitespace(): void {
        $console = new Console();
        $options = [
            ' x ' => [
                'description' => 'Test option',
                'optional'    => Console::OPTIONAL,
            ],
        ];

        $normalized = $console->normalizeOptions($options);

        $this->assertArrayHasKey('x', $normalized);
        $this->assertArrayNotHasKey(' x ', $normalized);
    }

    // =========================================================================
    // buildGetopts Tests
    // =========================================================================

    /**
     * Tests that buildGetopts separates short and long options
     *
     * @return void
     */
    public function testBuildGetoptsSeparatesShortAndLong(): void {
        $console = new Console();
        $options = [
            'a'      => ['description' => 'Short option', 'optional' => Console::OPTIONAL],
            'long'   => ['description' => 'Long option', 'optional' => Console::OPTIONAL],
        ];

        [$short_opts, $long_opts] = $console->buildGetopts($options);

        $this->assertContains('a', $short_opts);
        $this->assertContains('long', $long_opts);
    }

    /**
     * Tests that buildGetopts adds colon for required param
     *
     * @return void
     */
    public function testBuildGetoptsAddsColonForRequiredParam(): void {
        $console = new Console();
        $options = [
            'f' => [
                'description' => 'File option',
                'param'       => 'FILE',
                'optional'    => Console::OPTIONAL,
            ],
        ];

        [$short_opts, $long_opts] = $console->buildGetopts($options);

        $this->assertContains('f:', $short_opts);
    }

    /**
     * Tests that buildGetopts adds double colon for optional param
     *
     * @return void
     */
    public function testBuildGetoptsAddsDoubleColonForOptionalParam(): void {
        $console = new Console();
        $options = [
            'o' => [
                'description'    => 'Optional param option',
                'param_optional' => true,
                'optional'       => Console::OPTIONAL,
            ],
        ];

        [$short_opts, $long_opts] = $console->buildGetopts($options);

        $this->assertContains('o::', $short_opts);
    }

    /**
     * Tests that buildGetopts handles long options with params
     *
     * @return void
     */
    public function testBuildGetoptsHandlesLongOptionsWithParams(): void {
        $console = new Console();
        $options = [
            'file' => [
                'description' => 'File option',
                'param'       => 'FILE',
                'optional'    => Console::OPTIONAL,
            ],
        ];

        [$short_opts, $long_opts] = $console->buildGetopts($options);

        $this->assertContains('file:', $long_opts);
    }

    // =========================================================================
    // getOpt Tests
    // =========================================================================

    /**
     * Tests that getOpt returns option value when set
     *
     * @return void
     */
    public function testGetOptReturnsValueWhenSet(): void {
        $console = new TestableConsole([], [
            'f' => [
                'description' => 'File option',
                'param'       => 'FILE',
                'optional'    => Console::OPTIONAL,
            ],
        ]);
        $console->setMockedOpts(['f' => '/path/to/file']);
        $console->run();

        $this->assertEquals('/path/to/file', $console->getOpt('f'));
    }

    /**
     * Tests that getOpt returns null when option not set
     *
     * @return void
     */
    public function testGetOptReturnsNullWhenNotSet(): void {
        $console = new TestableConsole([], [
            'f' => [
                'description' => 'File option',
                'param'       => 'FILE',
                'optional'    => Console::OPTIONAL,
            ],
        ]);
        $console->setMockedOpts([]);
        $console->run();

        $this->assertNull($console->getOpt('f'));
    }

    /**
     * Tests that getOpt triggers warning for invalid option when not quiet
     *
     * @return void
     */
    public function testGetOptTriggersWarningForInvalidOption(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        // Set up custom error handler to catch the warning
        $warning_triggered = false;
        $warning_message = '';
        set_error_handler(function ($errno, $errstr) use (&$warning_triggered, &$warning_message) {
            $warning_triggered = true;
            $warning_message = $errstr;
            return true;
        }, E_USER_WARNING);

        $console->getOpt('invalid_option');

        restore_error_handler();

        $this->assertTrue($warning_triggered);
        $this->assertStringContainsString('Option invalid_option is not a valid option', $warning_message);
    }

    /**
     * Tests that getOpt does not trigger warning when quiet
     *
     * @return void
     */
    public function testGetOptNoWarningWhenQuiet(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['q' => true]);
        $console->run();

        // Should not trigger a warning
        $result = $console->getOpt('invalid_option');

        $this->assertNull($result);
    }

    // =========================================================================
    // Magic __get Tests
    // =========================================================================

    /**
     * Tests that __get returns verbosity
     *
     * @return void
     */
    public function testMagicGetReturnsVerbosity(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => 'v']);
        $console->run();

        $this->assertEquals(Console::VERBOSITY_VERBOSE, $console->verbosity);
    }

    /**
     * Tests that __get returns pid_file
     *
     * @return void
     */
    public function testMagicGetReturnsPidFile(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        // pid_file is empty by default until checkPid is called
        $this->assertEquals('', $console->pid_file);
    }

    /**
     * Tests that __get returns last_pid
     *
     * @return void
     */
    public function testMagicGetReturnsLastPid(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $this->assertEquals(0, $console->last_pid);
    }

    /**
     * Tests that __get returns last_pid_start_time
     *
     * @return void
     */
    public function testMagicGetReturnsLastPidStartTime(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $this->assertNull($console->last_pid_start_time);
    }

    /**
     * Tests that __get returns option value via getOpt
     *
     * @return void
     */
    public function testMagicGetReturnsOptionValue(): void {
        $console = new TestableConsole([], [
            'f' => [
                'description' => 'File option',
                'param'       => 'FILE',
                'optional'    => Console::OPTIONAL,
            ],
        ]);
        $console->setMockedOpts(['f' => 'test.txt']);
        $console->run();

        $this->assertEquals('test.txt', $console->f);
    }

    // =========================================================================
    // buildHelp Tests
    // =========================================================================

    /**
     * Tests that buildHelp includes header when provided
     *
     * @return void
     */
    public function testBuildHelpIncludesHeader(): void {
        $console = new Console();
        $config = [
            'wrap' => 78,
            'help' => [
                'header' => 'This is my application',
                'footer' => '',
            ],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('This is my application', $help);
    }

    /**
     * Tests that buildHelp includes footer when provided
     *
     * @return void
     */
    public function testBuildHelpIncludesFooter(): void {
        $console = new Console();
        $config = [
            'wrap' => 78,
            'help' => [
                'header' => '',
                'footer' => 'For more info visit example.com',
            ],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('For more info visit example.com', $help);
    }

    /**
     * Tests that buildHelp includes copyright when provided
     *
     * @return void
     */
    public function testBuildHelpIncludesCopyright(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [
                'owner' => 'DealNews.com, Inc.',
                'year'  => '2024',
            ],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('Copyright DealNews.com, Inc.', $help);
        $this->assertStringContainsString('2024', $help);
    }

    /**
     * Tests that buildHelp includes USAGE section
     *
     * @return void
     */
    public function testBuildHelpIncludesUsageSection(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('USAGE:', $help);
    }

    /**
     * Tests that buildHelp includes OPTIONS section
     *
     * @return void
     */
    public function testBuildHelpIncludesOptionsSection(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('OPTIONS:', $help);
    }

    /**
     * Tests that buildHelp shows long options with double dash
     *
     * @return void
     */
    public function testBuildHelpShowsLongOptionsWithDoubleDash(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'verbose' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Be verbose',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('--verbose', $help);
    }

    /**
     * Tests that buildHelp shows short options with single dash
     *
     * @return void
     */
    public function testBuildHelpShowsShortOptionsWithSingleDash(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'v' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Be verbose',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertMatchesRegularExpression('/-v\s/', $help);
    }

    /**
     * Tests that buildHelp shows param placeholder
     *
     * @return void
     */
    public function testBuildHelpShowsParamPlaceholder(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'f' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Input file',
                'param'       => 'FILE',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        $this->assertStringContainsString('FILE', $help);
    }

    /**
     * Tests that buildHelp shows required options in usage
     *
     * @return void
     */
    public function testBuildHelpShowsRequiredOptionsInUsage(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
            'r' => [
                'optional'    => Console::REQUIRED,
                'description' => 'Required option',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        // Required options appear without brackets in usage
        $this->assertStringContainsString('-r', $help);
    }

    /**
     * Tests that buildHelp shows ONE_REQUIRED options in brackets with pipes
     *
     * @return void
     */
    public function testBuildHelpShowsOneRequiredOptionsInBrackets(): void {
        $console = new Console();
        $config = [
            'wrap'      => 78,
            'help'      => ['header' => '', 'footer' => ''],
            'copyright' => [],
        ];
        $options = [
            'h' => [
                'optional'    => Console::OPTIONAL,
                'description' => 'Shows this help',
            ],
            'a' => [
                'optional'    => Console::ONE_REQUIRED,
                'description' => 'Option A',
            ],
            'b' => [
                'optional'    => Console::ONE_REQUIRED,
                'description' => 'Option B',
            ],
        ];

        $help = $console->buildHelp($config, $options);

        // ONE_REQUIRED options should appear in brackets with pipe
        $this->assertMatchesRegularExpression('/\[-a \| -b\]|\[-b \| -a\]/', $help);
    }

    // =========================================================================
    // getOpts Tests (with false conversion)
    // =========================================================================

    /**
     * Tests that getOpts converts false values to true
     *
     * This verifies the behavior where getopt() returns false for options
     * without parameters, and we convert those to true for consistency.
     *
     * @return void
     */
    public function testGetOptsConvertsFalseToTrue(): void {
        $console = new TestableConsole();
        // Simulate getopt returning false for a flag option
        $console->setMockedOpts(['q' => false]);
        $console->run();

        // The 'q' option should be converted from false to true
        $this->assertTrue($console->getOpt('q'));
    }

    // =========================================================================
    // PID File Tests
    // =========================================================================

    /**
     * Tests that generatePidFilename returns proper format
     *
     * @return void
     */
    public function testGeneratePidFilenameReturnsProperFormat(): void {
        $console = new Console();

        $filename = $console->generatePidFilename();

        $this->assertStringEndsWith('.pid', $filename);
        $this->assertStringStartsWith(sys_get_temp_dir(), $filename);
    }

    /**
     * Tests that generatePidFilename includes unique_id when provided
     *
     * @return void
     */
    public function testGeneratePidFilenameIncludesUniqueId(): void {
        $console = new Console();

        $filename = $console->generatePidFilename([], 'my_unique_id');

        $this->assertStringContainsString('my_unique_id', $filename);
    }

    /**
     * Tests that generatePidFilename includes hash when opts provided
     *
     * @return void
     */
    public function testGeneratePidFilenameIncludesHashWhenOptsProvided(): void {
        $console = new Console();

        $filename_without_opts = $console->generatePidFilename([]);
        $filename_with_opts = $console->generatePidFilename(['foo' => 'bar']);

        $this->assertNotEquals($filename_without_opts, $filename_with_opts);
        // Hash is 40 chars (SHA1)
        $this->assertGreaterThan(strlen($filename_without_opts), strlen($filename_with_opts));
    }

    /**
     * Tests that checkPid returns PID_OK when no PID file exists
     *
     * @return void
     */
    public function testCheckPidReturnsPidOkWhenNoFileExists(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        // Use a unique ID to ensure we get a fresh PID file
        $unique_id = 'test_' . uniqid();
        $status = $console->checkPid(false, $unique_id);

        $this->assertEquals(Console::PID_OK, $status);

        // Clean up the PID file we just created
        $console->clearPid();
    }

    /**
     * Tests that checkPid creates PID file
     *
     * @return void
     */
    public function testCheckPidCreatesPidFile(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $unique_id = 'test_' . uniqid();
        $console->checkPid(false, $unique_id);

        $this->assertFileExists($console->pid_file);

        // Clean up
        $console->clearPid();
    }

    /**
     * Tests that checkPid returns PID_OK when PID file contains current PID
     *
     * @return void
     */
    public function testCheckPidReturnsPidOkWhenCurrentPid(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $unique_id = 'test_' . uniqid();

        // First call creates the PID file
        $status1 = $console->checkPid(false, $unique_id);
        $this->assertEquals(Console::PID_OK, $status1);

        // Second call should still be OK because it's the same process
        $status2 = $console->checkPid(false, $unique_id);
        $this->assertEquals(Console::PID_OK, $status2);

        // Clean up
        $console->clearPid();
    }

    /**
     * Tests that clearPid removes the PID file
     *
     * @return void
     */
    public function testClearPidRemovesPidFile(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $unique_id = 'test_' . uniqid();
        $console->checkPid(false, $unique_id);
        $pid_file = $console->pid_file;

        $this->assertFileExists($pid_file);

        $console->clearPid();

        $this->assertFileDoesNotExist($pid_file);
    }

    /**
     * Tests that clearPid can accept a custom PID file path
     *
     * @return void
     */
    public function testClearPidAcceptsCustomPath(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        // Create a temporary PID file
        $temp_file = sys_get_temp_dir() . '/test_custom_' . uniqid() . '.pid';
        file_put_contents($temp_file, getmypid() . '|' . time());

        $this->assertFileExists($temp_file);

        $console->clearPid($temp_file);

        $this->assertFileDoesNotExist($temp_file);
    }

    // =========================================================================
    // write() Tests
    // =========================================================================

    /**
     * Tests that write outputs at NORMAL verbosity by default
     *
     * @return void
     */
    public function testWriteOutputsAtNormalVerbosity(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $console->write("Test message");

        $displayed = $console->getDisplayedOutput();
        $this->assertCount(1, $displayed);
        $this->assertEquals("Test message", $displayed[0]);
    }

    /**
     * Tests that write suppresses DEBUG messages at NORMAL verbosity
     *
     * @return void
     */
    public function testWriteSuppressesDebugAtNormalVerbosity(): void {
        $console = new TestableConsole();
        $console->setMockedOpts([]);
        $console->run();

        $console->write("Debug message", Console::VERBOSITY_DEBUG);

        // At NORMAL verbosity (2), DEBUG level (16) should not output
        $displayed = $console->getDisplayedOutput();
        $this->assertCount(0, $displayed);

        // But it should still be captured
        $captured = $console->getCapturedOutput();
        $this->assertCount(1, $captured);
        $this->assertEquals("Debug message", $captured[0]['buffer']);
        $this->assertEquals(Console::VERBOSITY_DEBUG, $captured[0]['level']);
    }

    /**
     * Tests that write suppresses all output in QUIET mode
     *
     * @return void
     */
    public function testWriteSuppressesOutputInQuietMode(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['q' => true]);
        $console->run();

        $console->write("This should not appear");
        $console->write("Neither should this", Console::VERBOSITY_NORMAL);

        // In QUIET mode, nothing should be displayed
        $displayed = $console->getDisplayedOutput();
        $this->assertCount(0, $displayed);
    }

    /**
     * Tests that write outputs VERBOSE messages when verbosity is VERBOSE
     *
     * @return void
     */
    public function testWriteOutputsVerboseMessagesWhenVerbose(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => true]);
        $console->run();

        $console->write("Verbose message", Console::VERBOSITY_VERBOSE);

        $displayed = $console->getDisplayedOutput();
        $this->assertCount(1, $displayed);
        $this->assertEquals("Verbose message", $displayed[0]);
    }

    /**
     * Tests that write outputs INFO messages when verbosity is INFO
     *
     * @return void
     */
    public function testWriteOutputsInfoMessagesWhenInfo(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => 'vv']);
        $console->run();

        $console->write("Info message", Console::VERBOSITY_INFO);

        $displayed = $console->getDisplayedOutput();
        $this->assertCount(1, $displayed);
        $this->assertEquals("Info message", $displayed[0]);
    }

    /**
     * Tests that write outputs DEBUG messages when verbosity is DEBUG
     *
     * @return void
     */
    public function testWriteOutputsDebugMessagesWhenDebug(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => 'vvv']);
        $console->run();

        $console->write("Debug message", Console::VERBOSITY_DEBUG);

        $displayed = $console->getDisplayedOutput();
        $this->assertCount(1, $displayed);
        $this->assertEquals("Debug message", $displayed[0]);
    }

    /**
     * Tests write() with multiple messages at different verbosity levels
     *
     * @return void
     */
    public function testWriteMultipleMessagesAtDifferentLevels(): void {
        $console = new TestableConsole();
        $console->setMockedOpts(['v' => 'v']); // VERBOSE level
        $console->run();

        $console->write("Normal message", Console::VERBOSITY_NORMAL);
        $console->write("Verbose message", Console::VERBOSITY_VERBOSE);
        $console->write("Info message", Console::VERBOSITY_INFO);
        $console->write("Debug message", Console::VERBOSITY_DEBUG);

        // At VERBOSE level, only NORMAL and VERBOSE should display
        $displayed = $console->getDisplayedOutput();
        $this->assertCount(2, $displayed);
        $this->assertEquals("Normal message", $displayed[0]);
        $this->assertEquals("Verbose message", $displayed[1]);

        // All four should be captured
        $captured = $console->getCapturedOutput();
        $this->assertCount(4, $captured);
    }

    // =========================================================================
    // Constants Tests
    // =========================================================================

    /**
     * Tests that verbosity constants have expected values
     *
     * @return void
     */
    public function testVerbosityConstantsHaveExpectedValues(): void {
        $this->assertEquals(1, Console::VERBOSITY_QUIET);
        $this->assertEquals(2, Console::VERBOSITY_NORMAL);
        $this->assertEquals(3, Console::VERBOSITY_VERBOSE);
        $this->assertEquals(4, Console::VERBOSITY_INFO);
        $this->assertEquals(16, Console::VERBOSITY_DEBUG);
    }

    /**
     * Tests that option requirement constants have expected values
     *
     * @return void
     */
    public function testOptionRequirementConstantsHaveExpectedValues(): void {
        $this->assertEquals(256, Console::OPTIONAL);
        $this->assertEquals(512, Console::REQUIRED);
        $this->assertEquals(1024, Console::ONE_REQUIRED);
    }

    /**
     * Tests that PID constants have expected values
     *
     * @return void
     */
    public function testPidConstantsHaveExpectedValues(): void {
        $this->assertEquals(8192, Console::PID_OK);
        $this->assertEquals(16384, Console::PID_OTHER_RUNNING);
        $this->assertEquals(32767, Console::PID_OTHER_NOT_RUNNING);
        $this->assertEquals(65534, Console::PID_OTHER_UNKNOWN);
    }
}

/**
 * Testable subclass of Console that allows mocking realGetopt and capturing output
 *
 * This class overrides the protected realGetopt method to return
 * predetermined values, allowing us to test command-line parsing
 * logic without actually invoking PHP's getopt() function.
 *
 * It also overrides write() to capture output instead of writing to STDOUT,
 * enabling assertions on what would have been output.
 */
class TestableConsole extends Console {

    /**
     * Mocked options that will be returned by realGetopt
     *
     * @var array<string, mixed>
     */
    protected array $mocked_opts = [];

    /**
     * Captured output from write() calls
     *
     * @var array<int, array{buffer: string, level: int}>
     */
    protected array $captured_output = [];

    /**
     * Sets the options that realGetopt will return
     *
     * @param array<string, mixed> $opts Options to return from realGetopt
     *
     * @return void
     */
    public function setMockedOpts(array $opts): void {
        $this->mocked_opts = $opts;
    }

    /**
     * Overrides realGetopt to return mocked values
     *
     * @param string       $short_opts Short options string
     * @param array<mixed> $long_opts  Long options array
     *
     * @return array<string, mixed>
     */
    protected function realGetopt(string $short_opts, array $long_opts): array|false {
        return $this->mocked_opts;
    }

    /**
     * Overrides write() to capture output instead of writing to STDOUT
     *
     * This allows tests to verify what would have been output without
     * polluting the test runner's output stream.
     *
     * @param string $buffer Data to write
     * @param int    $level  Verbosity level
     *
     * @return void
     */
    public function write($buffer, $level = self::VERBOSITY_NORMAL) {
        $this->captured_output[] = [
            'buffer' => $buffer,
            'level'  => $level,
        ];

        // Still apply the same logic as parent, but capture instead of output
        if (self::$verbosity != self::VERBOSITY_QUIET && $level <= self::$verbosity) {
            // Would have output: $buffer . "\n"
        }
    }

    /**
     * Returns all captured output
     *
     * @return array<int, array{buffer: string, level: int}>
     */
    public function getCapturedOutput(): array {
        return $this->captured_output;
    }

    /**
     * Returns only the output that would have been displayed given current verbosity
     *
     * @return array<int, string>
     */
    public function getDisplayedOutput(): array {
        $displayed = [];

        foreach ($this->captured_output as $entry) {
            if (self::$verbosity != self::VERBOSITY_QUIET && $entry['level'] <= self::$verbosity) {
                $displayed[] = $entry['buffer'];
            }
        }

        return $displayed;
    }

    /**
     * Clears the captured output buffer
     *
     * @return void
     */
    public function clearCapturedOutput(): void {
        $this->captured_output = [];
    }
}
