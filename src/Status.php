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
 * Helper functions for providing status to the user of a command
 * line application
 */
class Status {

    public static function clearLine()
    {
        echo "\033[2K"; // delete the current line
        echo "\r"; // return the cursor to the beginning of the line
    }

    /**
     * Spinner that updates every call up to 4 per second
     */
    public static function spinner()
    {
        static $spinner = 0;
        static $mtime = null;
        static $chars = array(
            "-",
            "\\",
            "|",
            "/"
        );

        $now = microtime(true);

        if (is_null($mtime) || $mtime < $now - 0.5) {

            $mtime = $now;

            self::clearLine();
            echo $chars[$spinner];
            $spinner++;
            if ($spinner > count($chars)-1) {
                $spinner = 0;
            }
        }
    }

    /**
     * show a status bar in the console
     *
     * @param   int     $done   how many items are completed
     * @param   int     $total  how many items are to be done total
     * @param   int     $size   optional size of the status bar
     * @return  mixed
     *
     */
    public static function progress($done, $total, $size = 30)
    {
        if ($done > $total) return;

        static $mtime = null;

        static $start_time;

        if (empty($start_time) || $done<=1) $start_time=time();

        $nowms = round(microtime(true), 1);

        if ($done == $total || is_null($mtime) || $mtime < $nowms - 0.5) {

            $mtime = $nowms;

            $now = time();

            $perc=(double)($done/$total);

            $bar_size=floor($perc*$size);
            $perc_disp=number_format($perc*100, 0);

            $space = str_pad("$perc_disp%", $size, " ", STR_PAD_BOTH);
            $bar = str_repeat("=", $bar_size);
            if ($bar_size < $size) {
                $bar.= ">";
            }

            for ($x=0;$x<strlen($bar);$x++) {
                if ($space[$x] == " ") {
                    $space[$x] = $bar[$x];
                }
            }

            if ($done > 9999) {
                $done_disp = number_format(($done / 1000), 0)."k";
            } else {
                $done_disp = number_format($done, 0);
            }

            if ($total > 9999) {
                $total_disp = number_format(($total / 1000), 0)."k";
            } else {
                $total_disp = number_format($total, 0);
            }

            $rate = ($now-$start_time)/$done;
            $left = $total - $done;
            $etc = $now + round($rate * $left, 0);

            $nice_elapsed = self::relativeDate($start_time, $now);

            $nice_etc = self::relativeDate($now, $etc);

            $status_bar = "[$space] $done_disp/$total_disp";

            if ($done == $total) {
                $status_bar.= " Total Time: $nice_elapsed\n";
            } else {
                $status_bar.= " ET: $nice_elapsed ETC: $nice_etc";
            }

            self::clearLine();
            echo "$status_bar";

            flush();
        }
    }

    /**
     * Creates a user friendly date interval
     *
     * @param  int $start Starting timestamp
     * @param  int $end   Ending timestamp
     *
     * @return string
     */
    public static function relativeDate($start, $end) {
        $start = (int)$start;
        $end = (int)$end;

        $diff = $end - $start;
        $s = new \DateTime(date("c", $start));
        $e = new \DateTime(date("c", $end));
        $dt = $e->diff($s);

        if ($diff < 60) {
            $format = "%s sec";
        } elseif ($diff < 3600) {
            $format = "%i min %s sec";
        } elseif ($diff < 86400) {
            $format = "%h hr %i min %s sec";
        } else {
            $format = "%d days %h hr %i min %s sec";
        }

        return $dt->format($format);
    }
}
