# AGENTS.md - DealNews Console Library

This document provides context for AI agents working with the DealNews Console library.

## Project Overview

The DealNews Console library (`dealnews/console`) is a PHP utility library for building command-line applications. It provides:

- **Command-line argument parsing** with automatic help generation
- **Verbosity control** for output filtering
- **PID file management** for preventing duplicate processes
- **User feedback utilities** (progress bars, spinners)
- **User input handling** (prompts, confirmations)
- **PSR-3 compliant logging** that integrates with the Console verbosity system

## Namespace & Autoloading

All classes live under the `DealNews\Console` namespace and follow PSR-4 autoloading:

```
src/
├── Console.php    # Main class for argument parsing, verbosity, PID management
├── Interact.php   # Static methods for user input (prompts, confirmations)
├── Logger.php     # PSR-3 LoggerInterface implementation
└── Status.php     # Static methods for progress bars and spinners
```

## Key Classes

### Console

The core class that manages:

- **Command-line options**: Parses options via PHP's `getopt()`, supports short (`-v`) and long (`--verbose`) options
- **Verbosity levels**: `VERBOSITY_QUIET` (1), `VERBOSITY_NORMAL` (2), `VERBOSITY_VERBOSE` (3), `VERBOSITY_INFO` (4), `VERBOSITY_DEBUG` (16)
- **Option requirements**: `OPTIONAL`, `REQUIRED`, `ONE_REQUIRED`
- **PID file handling**: Prevents multiple instances of the same script
- **Output via `write()`**: Conditionally outputs based on verbosity level

**Usage Pattern:**
```php
$console = new Console($config, $options);
$console->run();
$console->write("Message", Console::VERBOSITY_NORMAL);
```

### Logger

A PSR-3 compliant logger that writes to `Console::write()`. Maps PSR-3 log levels to Console verbosity:

| PSR-3 Level | Console Verbosity     |
|-------------|-----------------------|
| emergency   | `VERBOSITY_QUIET`     |
| alert       | `VERBOSITY_QUIET`     |
| critical    | `VERBOSITY_QUIET`     |
| error       | `VERBOSITY_QUIET`     |
| warning     | `VERBOSITY_NORMAL`    |
| notice      | `VERBOSITY_VERBOSE`   |
| info        | `VERBOSITY_INFO`      |
| debug       | `VERBOSITY_DEBUG`     |

**Key Features:**
- Requires a `Console` instance via constructor injection
- Formats output with aligned level prefixes: `[EMERGENCY]`, `[WARNING  ]`, etc.
- Supports PSR-3 message interpolation: `{placeholder}` syntax

### Interact

Static utility class for user interaction:

- `prompt($prompt, $masked, $limit)`: Get text input, optionally masked (for passwords)
- `confirm($prompt)`: Yes/no questions returning boolean
- `isInteractive()`: Check if running in an interactive terminal

### Status

Static utility class for visual feedback:

- `progress($done, $total, $size)`: Progress bar with elapsed/estimated time
- `spinner()`: Animated spinner for indeterminate operations
- `clearLine()`: Clear current terminal line

## Dependencies

**Runtime:**
- PHP ^8.2
- ext-date
- psr/log ^3.0

**Development:**
- phpunit/phpunit ^11.5

## Testing

Tests live in `tests/` and use PHPUnit 11.5+. Run with:

```bash
./vendor/bin/phpunit
```

**Test Files:**
- `tests/ConsoleTest.php` — Tests for the Console class (62 tests)
- `tests/LoggerTest.php` — Tests for the Logger class (35 tests)

**Testing Console with Mocked getopt():**

The `ConsoleTest.php` file includes a `TestableConsole` subclass that overrides the protected `realGetopt()` method to allow mocking command-line input:

```php
$console = new TestableConsole();
$console->setMockedOpts(['v' => 'vv', 'f' => '/path/to/file']);
$console->run();
```

## Coding Standards

This project follows DealNews PHP coding standards. Key points:

- **Brace style**: 1TBS (opening braces on same line)
- **Variables/properties**: `snake_case`
- **Visibility**: Use `protected` over `private` unless there's a specific reason
- **Arrays**: Short syntax `[]`, trailing commas, aligned `=>` operators
- **Type hints**: Always declare argument and return types
- **Single return point**: Prefer single exit point from functions
- **Docblocks**: Required for all classes, methods, and functions

## Architecture Notes

- `Console::$verbosity` is a static property, allowing `Console::verbosity()` to be called without an instance
- The `write()` method adds a newline automatically via `fputs(STDOUT, $buffer."\n")`
- `Logger` does NOT add its own newline—it relies on `Console::write()` for that
- PID files are created in `sys_get_temp_dir()` with names derived from script name and options

## Common Tasks

### Adding a new feature to Console

1. Add properties/constants to `Console.php`
2. Add tests to `tests/` directory
3. Update docblocks and this file if needed

### Extending Logger functionality

The `Logger` class uses protected visibility, so you can extend it:

```php
class CustomLogger extends Logger {
    protected function formatMessage(...): string {
        // Custom formatting
    }
}
```

### Testing with Console

When testing code that uses `Console`, mock the `write()` method:

```php
$mock = $this->createMock(Console::class);
$mock->expects($this->once())
    ->method('write')
    ->with($expected_message, $expected_verbosity);
```

## File Headers

All PHP files should include the standard DealNews header:

```php
<?php
/**
 * DealNews Console
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present dealnews.com, Inc.
 * @license     http://opensource.org/licenses/bsd-license.php BSD
 */
```

## Edge Cases & Gotchas

1. **Verbosity is static**: Setting verbosity in one `Console` instance affects all instances
2. **VERBOSITY_QUIET suppresses all output**: Including from `write()`, but errors/critical/emergency in Logger are mapped to QUIET so they still appear
3. **Logger's unknown levels**: If an unknown log level is passed, it defaults to `VERBOSITY_NORMAL`
4. **PID files include arguments**: By default, different arguments create different PID files
5. **Console::write() adds newline**: Don't add `\n` to messages passed to `write()`
