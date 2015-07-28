<?php
/**
 * DealNews Console
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present dealnews.com, Inc.
 * @license     http://opensource.org/licenses/bsd-license.php BSD
 */

namespace DealNews\Console;

/**
 * Various helper functions for interacting with users on the command line
 */
class Interact {

    /**
     * Prompts the user for input. Optionally masking it.
     *
     * @param   string  $prompt     The prompt to show the user
     * @param   bool    $masked     If true, the users input will not be shown. e.g. password input
     * @param   int     $limit      The maximum amount of input to accept
     * @return  string
     */
    public static function prompt($prompt, $masked=false, $limit=100)
    {
        echo "$prompt: ";
        if ($masked) {
            `stty -echo`; // disable shell echo
        }
        $buffer = "";
        $char = "";
        $f = fopen('php://stdin', 'r');
        while (strlen($buffer) < $limit) {
            $char = fread($f, 1);
            if ($char == "\n" || $char == "\r") {
                break;
            }
            $buffer.= $char;
        }
        if ($masked) {
            `stty echo`; // enable shell echo
            echo "\n";
        }
        return $buffer;
    }

    /**
     * Prompts the user with a yes/no question.
     *
     * @param   string  $prompt     The prompt to show the user
     * @return  bool
     */
    public static function confirm($prompt)
    {
        $answer = false;

        if (strtolower(self::prompt($prompt." [y/N]", false, 1)) == "y") {
            $answer = true;
        }

        return $answer;

    }

    /**
     * Attempts to determine if the current process is attached to an
     * interactive terminal
     *
     * @return  bool
     */
    public static function isInteractive() {
        return defined("STDOUT") && posix_isatty(STDOUT);
    }
}
