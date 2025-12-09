<?php

/**
 * PSR-3 Logger for Console
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present dealnews.com, Inc.
 * @license     http://opensource.org/licenses/bsd-license.php BSD
 */

namespace DealNews\Console;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3 compliant logger that writes to Console::write()
 *
 * Maps PSR-3 log levels to Console verbosity levels and formats output
 * with aligned level prefixes for consistent, readable log output.
 *
 * Usage:
 * ```php
 * $console = new Console();
 * $console->run();
 * $logger = new Logger($console);
 * $logger->info('Processing started');
 * $logger->error('Something went wrong', ['error_code' => 123]);
 * ```
 */
class Logger implements LoggerInterface {

    /**
     * Maximum length of PSR-3 level names, used for padding
     */
    protected const LEVEL_PAD_LENGTH = 9;

    /**
     * Maps PSR-3 log levels to Console verbosity levels
     *
     * Errors and above are always shown (VERBOSITY_QUIET).
     * Warnings show at normal verbosity.
     * Informational messages require progressively higher verbosity.
     */
    protected const LEVEL_MAP = [
        LogLevel::EMERGENCY => Console::VERBOSITY_QUIET,
        LogLevel::ALERT     => Console::VERBOSITY_QUIET,
        LogLevel::CRITICAL  => Console::VERBOSITY_QUIET,
        LogLevel::ERROR     => Console::VERBOSITY_QUIET,
        LogLevel::WARNING   => Console::VERBOSITY_NORMAL,
        LogLevel::NOTICE    => Console::VERBOSITY_VERBOSE,
        LogLevel::INFO      => Console::VERBOSITY_INFO,
        LogLevel::DEBUG     => Console::VERBOSITY_DEBUG,
    ];

    /**
     * Console instance for output
     *
     * @var Console
     */
    protected Console $console;

    /**
     * Creates a new Logger instance
     *
     * @param Console $console Console instance to write log messages to
     */
    public function __construct(Console $console) {
        $this->console = $console;
    }

    /**
     * System is unusable
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     *
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level
     *
     * @param mixed              $level   PSR-3 log level
     * @param string|\Stringable $message Log message with optional {placeholder} tokens
     * @param array              $context Context array for interpolation
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        $verbosity = self::LEVEL_MAP[$level] ?? Console::VERBOSITY_NORMAL;
        $formatted_message = $this->formatMessage($level, $message, $context);
        $this->console->write($formatted_message, $verbosity);
    }

    /**
     * Formats a log message with level prefix and interpolated context
     *
     * @param string             $level   PSR-3 log level
     * @param string|\Stringable $message Log message
     * @param array              $context Context array for interpolation
     *
     * @return string Formatted message with level prefix
     */
    protected function formatMessage(
        string $level,
        string|\Stringable $message,
        array $context
    ): string {
        $interpolated = $this->interpolate((string)$message, $context);
        $padded_level = str_pad(strtoupper($level), self::LEVEL_PAD_LENGTH);

        return "[{$padded_level}] {$interpolated}";
    }

    /**
     * Interpolates context values into message placeholders
     *
     * Replaces {placeholder} tokens in the message with corresponding
     * values from the context array per PSR-3 specification.
     *
     * @param string $message Message with optional {placeholder} tokens
     * @param array  $context Key-value pairs for replacement
     *
     * @return string Interpolated message
     */
    protected function interpolate(string $message, array $context): string {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string)$value;
            } elseif (is_scalar($value)) {
                $replacements['{' . $key . '}'] = (string)$value;
            }
        }

        return strtr($message, $replacements);
    }
}
