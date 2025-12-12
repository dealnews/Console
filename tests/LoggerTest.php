<?php

/**
 * Unit tests for the Logger class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present dealnews.com, Inc.
 * @license     http://opensource.org/licenses/bsd-license.php BSD
 */

namespace DealNews\Console\Tests;

use DealNews\Console\Console;
use DealNews\Console\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Tests for the PSR-3 Logger implementation
 *
 * Verifies that the Logger correctly:
 * - Maps PSR-3 log levels to Console verbosity levels
 * - Formats log messages with aligned level prefixes
 * - Interpolates context placeholders into messages
 */
class LoggerTest extends TestCase {

    /**
     * Mock Console instance for capturing write() calls
     *
     * @var \PHPUnit\Framework\MockObject\MockObject&Console
     */
    protected $console_mock;

    /**
     * Logger instance under test
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Sets up test fixtures before each test
     *
     * @return void
     */
    protected function setUp(): void {
        $this->console_mock = $this->createMock(Console::class);
        $this->logger = new Logger($this->console_mock);
    }

    /**
     * Data provider for log level to verbosity mapping tests
     *
     * @return array<string, array{string, int}>
     */
    public static function logLevelVerbosityProvider(): array {
        return [
            'emergency maps to VERBOSITY_QUIET' => [LogLevel::EMERGENCY, Console::VERBOSITY_QUIET],
            'alert maps to VERBOSITY_QUIET'     => [LogLevel::ALERT, Console::VERBOSITY_QUIET],
            'critical maps to VERBOSITY_QUIET'  => [LogLevel::CRITICAL, Console::VERBOSITY_QUIET],
            'error maps to VERBOSITY_QUIET'     => [LogLevel::ERROR, Console::VERBOSITY_QUIET],
            'warning maps to VERBOSITY_NORMAL'  => [LogLevel::WARNING, Console::VERBOSITY_NORMAL],
            'notice maps to VERBOSITY_VERBOSE'  => [LogLevel::NOTICE, Console::VERBOSITY_VERBOSE],
            'info maps to VERBOSITY_INFO'       => [LogLevel::INFO, Console::VERBOSITY_INFO],
            'debug maps to VERBOSITY_DEBUG'     => [LogLevel::DEBUG, Console::VERBOSITY_DEBUG],
        ];
    }

    /**
     * Tests that each PSR-3 log level maps to the correct Console verbosity
     *
     * @param string $level              PSR-3 log level
     * @param int    $expected_verbosity Expected Console verbosity constant
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('logLevelVerbosityProvider')]
    public function testLogLevelMapsToCorrectVerbosity(string $level, int $expected_verbosity): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->anything(),
                $expected_verbosity
            );

        $this->logger->log($level, 'Test message');
    }

    /**
     * Data provider for level prefix formatting tests
     *
     * @return array<string, array{string, string}>
     */
    public static function levelPrefixProvider(): array {
        return [
            'emergency prefix' => [LogLevel::EMERGENCY, '[EMERGENCY]'],
            'alert prefix'     => [LogLevel::ALERT, '[ALERT    ]'],
            'critical prefix'  => [LogLevel::CRITICAL, '[CRITICAL ]'],
            'error prefix'     => [LogLevel::ERROR, '[ERROR    ]'],
            'warning prefix'   => [LogLevel::WARNING, '[WARNING  ]'],
            'notice prefix'    => [LogLevel::NOTICE, '[NOTICE   ]'],
            'info prefix'      => [LogLevel::INFO, '[INFO     ]'],
            'debug prefix'     => [LogLevel::DEBUG, '[DEBUG    ]'],
        ];
    }

    /**
     * Tests that log messages are prefixed with properly padded level names
     *
     * @param string $level           PSR-3 log level
     * @param string $expected_prefix Expected formatted prefix
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('levelPrefixProvider')]
    public function testLogMessageHasPaddedLevelPrefix(string $level, string $expected_prefix): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->stringStartsWith($expected_prefix),
                $this->anything()
            );

        $this->logger->log($level, 'Test message');
    }

    /**
     * Tests that the full formatted message includes prefix and message
     *
     * @return void
     */
    public function testLogMessageFormatIncludesMessage(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Hello world',
                $this->anything()
            );

        $this->logger->info('Hello world');
    }

    /**
     * Tests that context placeholders are interpolated into messages
     *
     * @return void
     */
    public function testContextInterpolation(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[ERROR    ] User 123 failed to login',
                $this->anything()
            );

        $this->logger->error('User {user_id} failed to login', ['user_id' => 123]);
    }

    /**
     * Tests interpolation with multiple placeholders
     *
     * @return void
     */
    public function testMultiplePlaceholderInterpolation(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[WARNING  ] User john performed action delete on resource 456',
                $this->anything()
            );

        $this->logger->warning(
            'User {username} performed action {action} on resource {resource_id}',
            [
                'username'    => 'john',
                'action'      => 'delete',
                'resource_id' => 456,
            ]
        );
    }

    /**
     * Tests that unmatched placeholders are left intact
     *
     * @return void
     */
    public function testUnmatchedPlaceholdersRemainIntact(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Value is foo but {missing} is not replaced',
                $this->anything()
            );

        $this->logger->info('Value is {present} but {missing} is not replaced', ['present' => 'foo']);
    }

    /**
     * Tests that Stringable objects in context are converted to strings
     *
     * @return void
     */
    public function testStringableObjectInterpolation(): void {
        $stringable = new class {
            public function __toString(): string {
                return 'stringable_value';
            }
        };

        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[DEBUG    ] Object value: stringable_value',
                $this->anything()
            );

        $this->logger->debug('Object value: {obj}', ['obj' => $stringable]);
    }

    /**
     * Tests that non-stringable values cannot be interpolated but are included in JSON context
     *
     * @return void
     */
    public function testNonStringableContextValuesIgnored(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Array value: {arr} {"arr":["nested","array"]}',
                $this->anything()
            );

        $this->logger->info('Array value: {arr}', ['arr' => ['nested', 'array']]);
    }

    /**
     * Tests boolean context values are converted to strings
     *
     * @return void
     */
    public function testBooleanContextInterpolation(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Boolean value: 1',
                $this->anything()
            );

        $this->logger->info('Boolean value: {bool}', ['bool' => true]);
    }

    /**
     * Tests float context values are converted to strings
     *
     * @return void
     */
    public function testFloatContextInterpolation(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Float value: 3.14',
                $this->anything()
            );

        $this->logger->info('Float value: {num}', ['num' => 3.14]);
    }

    /**
     * Tests the emergency() convenience method
     *
     * @return void
     */
    public function testEmergencyMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[EMERGENCY] System down',
                Console::VERBOSITY_QUIET
            );

        $this->logger->emergency('System down');
    }

    /**
     * Tests the alert() convenience method
     *
     * @return void
     */
    public function testAlertMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[ALERT    ] Alert message',
                Console::VERBOSITY_QUIET
            );

        $this->logger->alert('Alert message');
    }

    /**
     * Tests the critical() convenience method
     *
     * @return void
     */
    public function testCriticalMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[CRITICAL ] Critical error',
                Console::VERBOSITY_QUIET
            );

        $this->logger->critical('Critical error');
    }

    /**
     * Tests the error() convenience method
     *
     * @return void
     */
    public function testErrorMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[ERROR    ] Error occurred',
                Console::VERBOSITY_QUIET
            );

        $this->logger->error('Error occurred');
    }

    /**
     * Tests the warning() convenience method
     *
     * @return void
     */
    public function testWarningMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[WARNING  ] Warning issued',
                Console::VERBOSITY_NORMAL
            );

        $this->logger->warning('Warning issued');
    }

    /**
     * Tests the notice() convenience method
     *
     * @return void
     */
    public function testNoticeMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[NOTICE   ] Notice message',
                Console::VERBOSITY_VERBOSE
            );

        $this->logger->notice('Notice message');
    }

    /**
     * Tests the info() convenience method
     *
     * @return void
     */
    public function testInfoMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Info message',
                Console::VERBOSITY_INFO
            );

        $this->logger->info('Info message');
    }

    /**
     * Tests the debug() convenience method
     *
     * @return void
     */
    public function testDebugMethod(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[DEBUG    ] Debug message',
                Console::VERBOSITY_DEBUG
            );

        $this->logger->debug('Debug message');
    }

    /**
     * Tests that unknown log levels default to VERBOSITY_NORMAL
     *
     * @return void
     */
    public function testUnknownLevelDefaultsToNormalVerbosity(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->anything(),
                Console::VERBOSITY_NORMAL
            );

        $this->logger->log('unknown_level', 'Test message');
    }

    /**
     * Tests that empty context array works correctly
     *
     * @return void
     */
    public function testEmptyContextArray(): void {
        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Message without context',
                $this->anything()
            );

        $this->logger->info('Message without context', []);
    }

    /**
     * Tests that Stringable message objects are converted to strings
     *
     * @return void
     */
    public function testStringableMessage(): void {
        $message = new class {
            public function __toString(): string {
                return 'Stringable message content';
            }
        };

        $this->console_mock
            ->expects($this->once())
            ->method('write')
            ->with(
                '[INFO     ] Stringable message content',
                $this->anything()
            );

        $this->logger->info($message);
    }

    /**
     * Tests that context not used for interpolation is appended as JSON
     *
     * @return void
     */
    public function testRemainingContextAppendedAsJson(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[ERROR    ] User 123 failed {"ip":"1.2.3.4","attempt":3}',
                 $this->anything()
             );

        $this->logger->error(
            'User {user_id} failed',
            [
                'user_id' => 123,
                'ip'      => '1.2.3.4',
                'attempt' => 3,
            ]
        );
    }

    /**
     * Tests that fully interpolated context produces no JSON suffix
     *
     * @return void
     */
    public function testFullyInterpolatedContextProducesNoJson(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[INFO     ] User 123 logged in from 1.2.3.4',
                 $this->anything()
             );

        $this->logger->info(
            'User {user_id} logged in from {ip}',
            [
                'user_id' => 123,
                'ip'      => '1.2.3.4',
            ]
        );
    }

    /**
     * Tests that context with no placeholders is fully appended as JSON
     *
     * @return void
     */
    public function testContextWithoutPlaceholdersAppendedAsJson(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[WARNING  ] System alert {"severity":"high","code":500}',
                 $this->anything()
             );

        $this->logger->warning(
            'System alert',
            [
                'severity' => 'high',
                'code'     => 500,
            ]
        );
    }

    /**
     * Tests that nested arrays in context are properly JSON-encoded
     *
     * @return void
     */
    public function testNestedArraysInContextEncodedAsJson(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[ERROR    ] Database error {"query":"SELECT * FROM users","params":["foo","bar"],"error":{"code":1045,"message":"Access denied"}}',
                 $this->anything()
             );

        $this->logger->error(
            'Database error',
            [
                'query'  => 'SELECT * FROM users',
                'params' => ['foo', 'bar'],
                'error'  => [
                    'code'    => 1045,
                    'message' => 'Access denied',
                ],
            ]
        );
    }

    /**
     * Tests that special characters are properly handled in JSON output
     *
     * @return void
     */
    public function testSpecialCharactersInJsonContext(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[INFO     ] Processing file {"path":"/home/user/file.txt","content":"Line 1\nLine 2"}',
                 $this->anything()
             );

        $this->logger->info(
            'Processing file',
            [
                'path'    => '/home/user/file.txt',
                'content' => "Line 1\nLine 2",
            ]
        );
    }

    /**
     * Tests that Unicode characters are not escaped in JSON output
     *
     * @return void
     */
    public function testUnicodeInJsonContext(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[INFO     ] User action {"name":"José","action":"café"}',
                 $this->anything()
             );

        $this->logger->info(
            'User action',
            [
                'name'   => 'José',
                'action' => 'café',
            ]
        );
    }

    /**
     * Tests mixed scenario with some context interpolated and some appended
     *
     * @return void
     */
    public function testMixedInterpolationAndJsonContext(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[WARNING  ] Failed login attempt for user admin {"ip":"192.168.1.1","attempt":5,"locked":true}',
                 $this->anything()
             );

        $this->logger->warning(
            'Failed login attempt for user {username}',
            [
                'username' => 'admin',
                'ip'       => '192.168.1.1',
                'attempt'  => 5,
                'locked'   => true,
            ]
        );
    }

    /**
     * Tests that only used placeholders are excluded from JSON context
     *
     * @return void
     */
    public function testOnlyUsedPlaceholdersExcludedFromJson(): void {
        $this->console_mock
             ->expects($this->once())
             ->method('write')
             ->with(
                 '[ERROR    ] Error code 404 {"unused":"value","another":"context"}',
                 $this->anything()
             );

        $this->logger->error(
            'Error code {code}',
            [
                'code'    => 404,
                'unused'  => 'value',
                'another' => 'context',
            ]
        );
    }
}
