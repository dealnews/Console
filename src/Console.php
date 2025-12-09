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
 * Manages command line arguments, pid files and verbosity levels
 */
class Console {

    /**
     * Verbosity levels
     */
    const VERBOSITY_QUIET   = 1;
    const VERBOSITY_NORMAL  = 2;
    const VERBOSITY_VERBOSE = 3;
    const VERBOSITY_INFO    = 4;
    const VERBOSITY_DEBUG   = 16;

    /**
     * Settings for the optional argument for command line options
     */
    const OPTIONAL          = 256;
    const REQUIRED          = 512;
    const ONE_REQUIRED      = 1024;

    /**
     * Return values for the checkPid function
     */
    const PID_OK                = 8192;
    const PID_OTHER_RUNNING     = 16384;
    const PID_OTHER_NOT_RUNNING = 32767;
    const PID_OTHER_UNKNOWN     = 65534;

    /**
     * Stores the verbosity for this console application
     *
     * @var int
     */
    protected static $verbosity = self::VERBOSITY_NORMAL;

    /**
     * The unique PID file for this command
     *
     * @var string
     */
    protected $pid_file = "";

    /**
     * The last pid which ran this command
     *
     * @var integer
     */
    protected $last_pid = 0;

    /**
     * The timestamp the last time the same command was run
     *
     * @var mixed
     */
    protected $last_pid_start_time = null;

    /**
     * Holds various config options
     *
     * @var array
     */
    protected $config = array(
        "wrap" => 78,
        "copyright" => array(),
        "help" => array(
            "header" => "",
            "footer" => ""
        )
    );

    /**
     * Holds the getopt() options return value
     * @var array
     */
    protected $opts = array();


    /**
     * Holds the defined options
     *
     * There are some built in options.
     *
     *  -h for help
     *  -v for verbosity
     *  -q for quiet with overrides verbosity
     *
     * @var array
     */
    protected $options = array(
        "h" => array(
            "optional"       => self::OPTIONAL,
            "description"    => "Shows this help"
        ),
        "v" => array(
            "optional"       => self::OPTIONAL,
            "description"    => "Be verbose. Additional v will increase verbosity. e.g. -vvv",
            "param_optional" => true
        ),
        "q" => array(
            "optional"       => self::OPTIONAL,
            "description"    => "Be quiet. Will override -v"
        )
    );

    /**
     * Creates the new Console object
     *
     * @param   string  $msg        The message to be displayed that explains
     *                              what the programs does
     * @param   array   $options    An array of command line options.
     * @return  mixed
     *
     */
    public function __construct(array $config = array(), array $options = array())
    {
        if (!empty($config)) {
            foreach ($config as $key => $value) {
                if (isset($this->config[$key])) {
                    $this->config[$key] = $value;
                }
            }
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * Starts the process of getting command line arguments and preparing
     * them for use
     *
     * @return void
     */
    public function run()
    {
        $this->options = $this->normalizeOptions($this->options);

        $this->opts = $this->getOpts($this->options);

        if (!empty($this->opts["h"])) {
            $this->showHelp();
        }

        foreach ($this->options as $option => $config) {
            if ($config["optional"] == self::REQUIRED) {
                if (!isset($this->opts[$option])) {
                    $this->showHelp("Missing required option `$option`");
                }
            }
        }

        $missing_options = array();
        foreach ($this->options as $option => $config) {
            if ($config["optional"] == self::ONE_REQUIRED) {
                if (!isset($this->opts[$option])) {
                    $missing_options[] = $option;
                } else {
                    // we have at least one required option, break
                    $missing_options = array();
                    break;
                }
            }
        }
        if (!empty($missing_options)) {
            $this->showHelp("Missing one of the required options `".implode("`, `", $missing_options)."`");
        }


        if (isset($this->opts["q"])) {
            self::$verbosity = self::VERBOSITY_QUIET;
        } elseif (isset($this->opts["v"])) {
            self::$verbosity = self::VERBOSITY_VERBOSE;
            if (!is_bool($this->opts["v"])) {
                if (is_array($this->opts["v"])) {
                    $level = count($this->opts["v"]);
                } else {
                    $level = strlen($this->opts["v"]) + 1;
                }
                switch ($level) {
                    case 1:
                        self::$verbosity = self::VERBOSITY_NORMAL;
                        break;
                    case 2:
                        self::$verbosity = self::VERBOSITY_VERBOSE;
                        break;
                    case 3:
                        self::$verbosity = self::VERBOSITY_INFO;
                        break;
                    default:
                        self::$verbosity = self::VERBOSITY_DEBUG;
                        break;
                }
            }
        }

        $this->opts["v"] = self::$verbosity;
    }

    /**
     * Can be used by other Console objects to determine if a verbosity
     * for the running application has been set without having access
     * to the instance that was created.
     *
     * @return int
     */
    public static function verbosity()
    {
        return self::$verbosity;
    }

    /**
     * Magic getter for various properties
     *
     * @param  string $name Property to retrieve
     *
     * @return mixed
     */
    public function __get($name)
    {
        $value = null;
        switch ($name) {
            case "pid_file":
            case "last_pid":
            case "last_pid_start_time":
                $value = $this->$name;
                break;
            case "verbosity":
                $value = self::$verbosity;
                break;
            default:
                // Allow access to the options via the getter too
                if (array_key_exists($name, $this->options)) {
                    $value = $this->getOpt($name);
                } else {
                    $trace = debug_backtrace();
                    trigger_error("Undefined property $name in ".$trace[0]['file']." on line ".$trace[0]['line'], E_USER_ERROR);
                }
        }
        return $value;
    }

    /**
     * Get a single options value
     *
     * @param  string $name Option name to get the value of
     *
     * @return mixed
     */
    public function getOpt($name)
    {
        $value = null;
        if (array_key_exists($name, $this->opts)) {
            $value = $this->opts[$name];
        } elseif (!array_key_exists($name, $this->options)) {
            if (self::$verbosity != self::VERBOSITY_QUIET) {
                trigger_error("Option $name is not a valid option", E_USER_WARNING);
            }
        }
        return $value;
    }

    /**
     * Clean up the options and validate they are sane
     *
     * @param  array  $options Array of command line options
     *
     * @return array
     */
    public function normalizeOptions($options)
    {
        $normalized_options = array();

        foreach ($options as $option => $config) {
            if (!is_array($config)) {
                trigger_error("Invalid option $option", E_USER_ERROR);
            }

            if (!isset($config["optional"])) {
                $config["optional"] = self::OPTIONAL;
            }

            if (empty($config["description"])) {
                trigger_error("Missing description for option $option", E_USER_ERROR);
            }

            if (!empty($confi["param"])) {
                $config["param"] = trim($config["param"]);
            }

            $trim_option = trim($option);
            if ($trim_option != $option) {
                unset($options[$option]);
                $option = $trim_option;
            }

            $normalized_options[$option] = $config;
        }

        ksort($normalized_options);

        return $normalized_options;
    }

    /**
     * Writes to standard out conditionally based on the current verbosity
     * level
     *
     * @param  string $buffer Data to write
     * @param  int    $level  Verbosity level
     *
     * @return void
     */
    public function write($buffer, $level = self::VERBOSITY_NORMAL)
    {
        if (self::$verbosity != self::VERBOSITY_QUIET && $level <= self::$verbosity) {
            fputs(STDOUT, $buffer."\n");
        }
    }

    /**
     * Calls getopt
     *
     * @param  array  $options Command line options array
     *
     * @return array
     */
    public function getOpts(array $options)
    {
        list($short_opts, $long_opts) = $this->buildGetopts($options);

        $opts = $this->realGetopt(implode("", $short_opts), $long_opts);
        // getopt() sets options that don't take a parameter to false
        // this can create some confusing issues. So, set any truly
        // false values to true instead
        foreach ($opts as $key => $value) {
            if ($value === false) {
                $opts[$key] = true;
            }
        }
        return $opts;
    }

    /**
     * Wrapper around getopt to make testing easier
     *
     * @param string $short_opts
     * @param array  $long_opts
     * @return array|false
     */
    protected function realGetopt(string $short_opts, array $long_opts): array|false {
        return getopt($short_opts, $long_opts);
    }

    /**
     * Build the getopt input values. Returns an array where the first element
     * is the short options and the second is the long options
     *
     * @param  array  $options Command line options array
     *
     * @return array
     */
    public function buildGetopts($options)
    {

        $short_opts = array();
        $long_opts = array();

        foreach ($options as $o=>$i) {

            $opt = $o;

            if (!empty($i["param_optional"])) {
                $opt.="::";
            } elseif (!empty($i["param"])) {
                $opt.=":";
            }

            if (strlen($o) > 1) {
                $long_opts[] = $opt;
            } else {
                $short_opts[] = $opt;
            }

        }

        return array(
            $short_opts,
            $long_opts
        );
    }

    /**
     * Builds the help output
     *
     * @param  array  $config  A configuration array
     * @param  array  $options Command line options array
     *
     * @return string
     */
    public function buildHelp(array $config, array $options)
    {
        $max_name_len = 0;
        $max_param_len = 0;

        $required_params = array();
        $optional_params = array();
        $optional_required_params = array();

        foreach ($options as $o=>$i) {

            // determine the maximum length of options and parameters
            $max_name_len = max($max_name_len, strlen($o));

            if (isset($i["param"])) {
                $max_param_len = max($max_param_len, strlen($i["param"]));
            }

            if ($o == "h") {
                continue;
            }

            // build an example usage string
            if (strlen($o) > 1) {
                $usage = "--$o";
            } else {
                $usage = "-$o";
            }

            if (!empty($i["param"])) {
                $usage.= " ".$i["param"];
            }

            if ($i["optional"] == self::OPTIONAL) {
                $optional_params[] = $usage;
            } elseif ($i["optional"] == self::ONE_REQUIRED) {
                $optional_required_params[] = $usage;
            } else {
                $required_params[] = $usage;
            }

        }

        $usage_example = " -h";

        if (!empty($required_params)) {
            $usage_example.= " | ".implode(" | ", $required_params);
        }

        if (!empty($optional_required_params)) {
            $usage_example.= " [".implode(" | ", $optional_required_params)."]";
        }

        if (!empty($optional_params)) {
            $usage_example.= " [".implode("] [", $optional_params)."]";
        }

        $help = "";

        if (!empty($config["help"]["header"])) {
            $help.= wordwrap($config["help"]["header"], $config["wrap"])."\n";
        }

        $help.= "USAGE:\n";
        $help.= "  ".basename($_SERVER["PHP_SELF"])." $usage_example\n\n";
        $help.= "OPTIONS:\n";

        $name_pad = $max_name_len + 2;
        $param_pad = $max_param_len + 2;

        foreach ($options as $o=>$i) {

            if (strlen($o) > 1) {
                $opt = "  --";
            } else {
                $opt = "   -";
            }

            $opt.= str_pad($o, $name_pad);

            if ($max_param_len>0) {
                if (!empty($i["param"])) {
                    $param = $i["param"];
                } else {
                    $param = "";
                }
                $opt.= str_pad($param, $param_pad);
            }

            if (!empty($i["description"])) {
                $opt.= wordwrap($i["description"], $config["wrap"] - (strlen($opt)), "\n".str_repeat(" ", strlen($opt)));
            }

            $help .= "$opt\n";
        }
        if (!empty($config["copyright"])) {
            if (empty($config["copyright"]["owner"])) {
                trigger_error("Copyright owner is required", E_USER_ERROR);
            }
            $help.= "\n";
            $help.= "Copyright {$config["copyright"]["owner"]} ";
            if (!empty($config["copyright"]["year"])) {
                $help.= " ".$config["copyright"]["year"];
            }
            $help.= "\n";
        }
        if (!empty($config["help"]["footer"])) {
            $help.= "\n";
            $help.= $config["help"]["footer"]."\n";
        }
        $help.= "\n";
        return $help;
    }

    /**
     * Shows the help and an optional message. Exits with provided exit status
     *
     * @param   type    $message    A message to show above the help
     * @param   int     $exit       Exit status code
     * @return  void
     *
     */
    public function showHelp($message = "", $exit = 0)
    {

        $bt = debug_backtrace();

        if (!empty($message)) {
            fputs(STDERR, wordwrap($message)."\n\n");
        }

        $help = $this->buildHelp($this->config, $this->options);

        echo $help;

        exit((int)$exit);

    }

    /**
     * Check for existing pid file
     *
     * This function will create a pid file for the current process.
     * If a pid file exists, it will check to see if the process is running
     * and return of the PID_* constants.
     *
     * This function will exit if an existing pid file is found.
     *
     * @param  boolean     $use_arguments  If true, command line options will be
     *                                     used to create a unique pid file name
     * @param  string      $unique_id      Optional unique identifier used to
     *                                     create the pid file name
     * @return mixed
     */
    public function checkPid($use_arguments = true, $unique_id = null)
    {
        if ($use_arguments) {
            $opts = $this->opts;
            // remove the built paramaters that control verbosity
            // They don't likely effect the job the script is doing
            if (isset($opts["v"])) {
                unset($opts["v"]);
            }
            if (isset($opts["q"])) {
                unset($opts["q"]);
            }
        } else {
            $opts = array();
        }

        $this->pid_file = $this->generatePidFilename($opts, $unique_id);

        if (!file_exists($this->pid_file)) {
            $status = self::PID_OK;

            // write the pid file
            $fp = fopen($this->pid_file, "w");
            if ($fp) {
                fputs($fp, getmypid()."|".time());
                fclose($fp);
                if (self::$verbosity == self::VERBOSITY_DEBUG) {
                    echo "Creating PID file $this->pid_file\n";
                }
            } else {
                if (self::$verbosity != self::VERBOSITY_QUIET) {
                    trigger_error("Failed to create PID file $this->pid_file", E_USER_WARNING);
                }
            }

            register_shutdown_function(array($this, "clearPid"));

        } else {
            if (self::$verbosity == self::VERBOSITY_DEBUG) {
                echo "Found existing PID file $this->pid_file\n";
            }

            list($pid, $started) = explode("|", file_get_contents($this->pid_file));

            if ($pid == getmypid()) {
                $status = self::PID_OK;
            } else {
                $this->last_pid = $pid;
                $this->last_pid_start_time = $started;

                if (file_exists("/proc")) {
                    if (file_exists("/proc/$pid/status")) {
                        if (self::$verbosity == self::VERBOSITY_DEBUG) {
                            echo "Command still running with PID $this->last_pid\n";
                        }
                        $status = self::PID_OTHER_RUNNING;
                    } else {
                        if (self::$verbosity == self::VERBOSITY_DEBUG) {
                            echo "Command no longer running with PID $this->last_pid\n";
                        }
                        $status = self::PID_OTHER_NOT_RUNNING;
                    }
                } else {
                    if (self::$verbosity == self::VERBOSITY_DEBUG) {
                        echo "Unknown status of last PID $this->last_pid\n";
                    }
                    $status = self::PID_OTHER_UNKNOWN;
                }
            }
        }

        return $status;
    }

    /**
     * Clear the pid file for the current process
     *
     * @return bool
     */
    public function clearPid($pid_file = null)
    {
        if ($pid_file === null) {
            $pid_file = $this->pid_file;
        }

        $success = false;

        if (empty($pid_file)) {
            if (self::$verbosity != self::VERBOSITY_QUIET) {
                trigger_error("No PID file to clear.", E_USER_WARNING);
            }
        } else {
            if (file_exists($pid_file)) {
                $success = @unlink($pid_file);
                if (!$success) {
                    if (!is_writable($pid_file)) {
                        if (self::$verbosity != self::VERBOSITY_QUIET) {
                            trigger_error("Invalid permisssion to clear PID file $pid_file.", E_USER_WARNING);
                        }
                    }
                } else {
                    if (self::$verbosity == self::VERBOSITY_DEBUG) {
                        echo "Removed PID file $pid_file\n";
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Generates an array of PID filenames.
     *
     * @param  boolean     $opts        An array of options like those returned
     *                                  from getopt that can be used to generate
     *                                  a unique PID file name.
     * @param  string      $unique_id   Optional unique identifier used to
     *                                  create the pid file name
     * @return string
     */
    public function generatePidFilename(array $opts = array(), $unique_id = null)
    {
        $pid_file = sys_get_temp_dir();

        $pid_file.= DIRECTORY_SEPARATOR;

        $pid_file.= preg_replace("/[^A-Za-z0-9_-]+/", "_", basename($_SERVER["PHP_SELF"]));

        if (!empty($opts)) {
            $pid_file.= "_".sha1(serialize($opts));
        }

        if (!empty($unique_id)) {
            $pid_file.= "_".$unique_id;
        }

        $pid_file.= ".pid";

        return $pid_file;
    }
}
