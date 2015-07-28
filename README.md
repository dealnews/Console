# DealNews Console Library

## Command Line Arguments

```php
#!/bin/env php
<?php

/**
 * This is a test app called test.php
 */

use \DealNews\Console\Console;

$console = new Console(
    array(
        "copyright" => array(
            "owner" => "DealNews.com, Inc.",
            "year" => "1997-".date("Y")
        ),
        "help" => array(
            "header" => "This is a test app"
        )
    ),
    array(
        "s" => array(
            "description" => "Some option called -s",
            "param" => "SPARAM",
            "optional" => Console::ONE_REQUIRED
        ),
        "e" => array(
            "description" => "Some option called -e",
            "param" => "EPARAM",
            "optional" => Console::ONE_REQUIRED
        ),
        "long" => array(
            "description" => "Some long option called --long",
            "param" => "LONG",
            "optional" => Console::OPTIONAL
        ),
    )
);

$console->run();

?>
```
You get `-h` for *FREE*!
Example help output:
```bash
$ php test.php -h
This is a test app
USAGE:
  test.php  -h [-e EPARAM | -s SPARAM] [--long LONG] [-q] [-v]

OPTIONS:
   -e     EPARAM  Some option called -e
   -h             Shows this help
  --long  LONG    Some long option called --long
   -q             Be quiet. Will override -v
   -s     SPARAM  Some option called -s
   -v             Be verbose. Additional v will increase verbosity. e.g. -vvv

Copyright DealNews.com, Inc.  1997-2015
```
### Verbosity
You also get `-v` and `-q` for *FREE*!
```php
<?php
if($console->verbosity >= Console::VERBOSITY_VERBOSE){
    // Write stuff out
}

// Or use the console's write method and let it decide
$console->write("Normal output");
$console->write("Normal output", Console::VERBOSITY_NORMAL);
$console->write("Verbose output", Console::VERBOSITY_VERBOSE);
$console->write("Info output", Console::VERBOSITY_INFO);
$console->write("Debug output", Console::VERBOSITY_DEBUG);
?>
```
If `-q` is set on the command line, verbosity will be set to quiet and `write()` will not output anyting.

The verbosity level is controlled by how many `-v` appear on the command line.

The verbosity setting also controls what level of error messages the Console class will throw when encountering an error.

### Checking options
```php
<?php
// You can use two methods to check options
$eValue = $console->getOpt("e");
if($eValue){
    // do stuff for e
}
// or simply use the object
if($console->long){
    // do stuff for long
}
?>
```
### Required vs Optional
Command line options can have three different settings for the optional argument.
* OPTIONAL - Totaly optional argument
* REQUIRED - Must always be present
* ONE_REQUIRED - At least one of the options of this type must be provided

## PID file management
```php
<?php
// check there is no other PID running this script. By default, the
// command line arguments are used to build a unique PID file name.
// You can disable this by passing false. You can also, pass in a
// second parameter that is a unique id for this script that will
// be used to create the pid file name.
$status = $console->checkPid();
if($status !== Console::PID_OK){
    /**
     * We could do more here for the other statuses as well.
     * PID_OTHER_RUNNING
     * PID_OTHER_NOT_RUNNING
     * PID_OTHER_UNKNOWN
     */
    fputs(STDERR, "A PID file already exists for this script");
    exit(1);
}
?>
```
## User Feedback

### Progress Meter
```php
<?php
// Show the status of some progress
// Includes elapsed time and estimated time to completion
use \DealNews\Console\Status;
$total = 100000;
for ($x=1; $x<=$total; $x++){
    Status::progress($x, $total);
    usleep(100);
}
?>
```
Example output:
```bash
[======>      23%              ] 23k/100k ET: 15 sec ETC: 50 sec
```
### Spinner
```php
<?php
// Unsure how long something will run? Just show a spinner
use \DealNews\Console\Status;
$total = rand(10000, 30000);
for ($x=1; $x<=$total; $x++) {
    Status::spinner();
    usleep(100);
}
// clear the line after the spinner is done
Status::clearLine();
?>
```

## User Input

### Check For Interactivity
```php
<?php
use \DealNews\Console\Interact;
if (Interact::isInteractive()) {
    // do stuff on the console like show status and ask questions
}
?>
```

### Ask For Input
You can ask the user to type things. If you pass true for the second argument, the user's input will not be echoed in the terminal.
```php
<?php
use \DealNews\Console\Interact;
$answer = Interact::prompt("What is the secret word?", true);
if ($answer == "secret") {
    $console->write("Yay! You got it!");
} else {
    $console->write("Boo! That is wrong!");
}
?>
```

### Ask Yes or No Questions
```php
<?php
use \DealNews\Console\Interact;
if (!Interact::confirm("Do you want to continue?")) {
    exit(0);
}
?>
```
