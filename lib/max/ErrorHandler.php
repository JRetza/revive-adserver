<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

// This class was originally taken from Seagull, with permission.
// See: http://seagull.phpkitchen.com

require_once RV_PATH . '/lib/RV.php';

require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/Max.php';

require_once OX_PATH . '/lib/OX.php';

/**
 * Global error handler class, modifies behaviour for PHP errors, not PEAR.
 *
 * @package Openads
 * @author  Peter James <petej@shaman.ca>
 * @author  Demian Turner <demian@phpkitchen.com>
 */
class MAX_ErrorHandler
{
    public $errorType = [
        1 => ['Error', 3],
        2 => ['Warning', 4],
        4 => ['Parsing Error', 3],
        8 => ['Notice', 5],
        16 => ['Core Error', 3],
        32 => ['Core Warning', 4],
        64 => ['Compile Error', 3],
        128 => ['Compile Warning', 4],
        256 => ['User Error', 3],
        512 => ['User Warning', 4],
        1024 => ['User Notice', 5],
        2048 => ['Strict', 5],
        4096 => ['Recoverable', 5],
        8192 => ['Deprecated', 5],
    ];
    public $sourceContextOptions = ['lines' => 5];

    /**
     * BC hack to assign custom error handler in a method.
     *
     * @access  public
     * @return  void
     */
    public function startHandler()
    {
        set_error_handler($this->errHandler(...));
    }

    /**
     * Enhances PHP's default error handling.
     *
     *  o overrides notices in certain cases
     *  o obeys @muffled errors,
     *  o error logged to selected target
     *  o context info presented for developer
     *  o error data emailed to admin if theshold passed
     *
     * @access  public
     * @param   int     $errNo      PHP's error number
     * @param   string  $errStr     PHP's error message
     * @param   string  $file       filename where error occurred
     * @param   int     $line       line number where error occurred
     */
    public function errHandler($errNo, $errStr, $file, $line): void
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        // do not show notices
        if ($conf['debug']['errorOverride'] == true) {
            if ($errNo == E_NOTICE) {
                return;
            }
        }

        if (!(error_reporting() & $errNo)) {
            // Silenced!
            return;
        }

        if (in_array($errNo, array_keys($this->errorType))) {
            //  final param is 2nd dimension element from errorType array,
            //  representing PEAR error codes mapped to PHP's

            $oOA = new OA();
            $oOA->debug($errStr, $this->errorType[$errNo][1]);

            //  if a debug sesssion has been started, or the site in in
            //  development mode, send error info to screen
            if (!$conf['debug']['production']) {
                $source = $this->_getSourceContext($file, $line);
                //  generate screen debug html
                //  type is 1st dimension element from $errorType array, ie,
                //  PHP error code
                $output = <<<EOF
<hr />
<p class="error">
  <strong>MESSAGE</strong>: $errStr<br />
  <strong>TYPE:</strong> {$this->errorType[$errNo][0]}<br />
  <strong>FILE:</strong> $file<br />
  <strong>LINE:</strong> $line<br />
  <strong>DEBUG INFO:</strong>
  <p>$source</p>
</p>
<hr />
EOF;
                echo $output;
            }

            //  email the error to admin if threshold reached
            //  never send email if error occurred in test
            //
            $emailAdminThreshold = is_numeric($conf['debug']['emailAdminThreshold']) ? $conf['debug']['emailAdminThreshold'] :
                @constant($conf['debug']['emailAdminThreshold']);
            if ($conf['debug']['sendErrorEmails'] && !defined('TEST_ENVIRONMENT_RUNNING') && $this->errorType[$errNo][1] <= $emailAdminThreshold) {
                //  get extra info
                $oDbh = OA_DB::singleton();
                $lastQuery = $oDbh->last_query;
                $aExtraInfo['callingURL'] = $_SERVER['SCRIPT_NAME'];
                $aExtraInfo['lastSQL'] = $oDbh->last_query ?? null;
                $aExtraInfo['clientData']['HTTP_REFERER'] = &$_SERVER['HTTP_REFERER'];
                $aExtraInfo['clientData']['HTTP_USER_AGENT'] = &$_SERVER['HTTP_USER_AGENT'];
                $aExtraInfo['clientData']['REMOTE_ADDR'] = &$_SERVER['REMOTE_ADDR'];
                $aExtraInfo['clientData']['SERVER_PORT'] = &$_SERVER['SERVER_PORT'];

                //  store formatted output
                ob_start();
                print_r($aExtraInfo);
                $info = ob_get_contents();
                ob_end_clean();

                //  rebuild error output w/out html
                $crlf = "\n";
                $output = $errStr . $crlf .
                    'type: ' . $this->errorType[$errNo][0] . $crlf .
                    'file: ' . $file . $crlf .
                    'line: ' . $line . $crlf . $crlf;
                $message = $output . $info;
                @mail($conf['debug']['email'], $conf['debug']['emailSubject'], $message);
            }
        }
    }

    /**
     * Provides enhanced error info for developer.
     *
     * Gives 10 lines before and after error occurred, hightlight erroroneous
     * line in red.
     *
     * @access  private
     * @param   string  $file       filename where error occurred
     * @param   int     $line       line number where error occurred
     * @param   string  $context    contextual info
     * @return  string  contextual error info
     */
    public function _getSourceContext($file, $line)
    {
        $sourceContext = null;

        //  check that file exists
        if (!file_exists($file)) {
            $sourceContext = "Context cannot be shown - ($file) does not exist";
            //  check if line number is valid
        } elseif ((!is_int($line)) || ($line <= 0)) {
            $sourceContext = "Context cannot be shown - ($line) is an invalid line number";
        } else {
            $lines = file($file);

            //  get the source ## core dump in windows, scrap colour highlighting :-(
            //  $source = highlight_file($file, true);
            //  $lines = split("<br />", $source);
            //  get line numbers
            $start = $line - $this->sourceContextOptions['lines'] - 1;
            $finish = $line + $this->sourceContextOptions['lines'];

            //  get lines
            if ($start < 0) {
                $start = 0;
            }

            if ($start >= count($lines)) {
                $start = count($lines) - 1;
            }

            for ($i = $start; $i < $finish; $i++) {
                //  highlight line in question
                if ($i == ($line - 1)) {
                    $context_lines[] = '<div class="error"><strong>' . ($i + 1) .
                        "\t" . strip_tags($lines[$line - 1]) . '</strong></div>';
                } else {
                    $context_lines[] = '<strong>' . ($i + 1) .
                        "</strong>\t" . $lines[$i];
                }
            }

            $sourceContext = trim(implode("<br />\n", $context_lines)) . "<br />\n";
        }

        return $sourceContext;
    }
}
