<?php
/**
 * C Compatible PreProcessor for PHP - cli Interface
 *
 * LICENSE:
 *   Copyright (c) 2014, Francis Gendreau
 *   All rights reserved.
 *
 *   Redistribution and use in source and binary forms, with or without
 *   modification, are permitted provided that the following conditions are met:
 *       * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *       * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *   THIS SOFTWARE IS PROVIDED BY Marin Valeriev Ivanov ''AS IS'' AND ANY
 *   EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 *   WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 *   DISCLAIMED. IN NO EVENT SHALL Marin Valeriev Ivanov BE LIABLE FOR ANY
 *   DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 *   (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *   LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 *   ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 *   (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 *   SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
*/

error_reporting(E_ALL|E_STRICT);

function getopt_janitor(&$opts, $optstring) {
    $GLOBALS['argv_inval'] = array();
    global $argv;
    global $argv_inval;

    $values = array(); // getopt()'s accepted values
    foreach($opts as $v)
        if ($v)
            array_push($values, $v);

    $tmp = array();
    array_push($tmp, array_shift($argv)); // argv[0] ...

    while(count($argv)) {
        $cur = array_shift($argv);
        if ($cur[0] == '-') {
            if (strlen($cur) == 2 && $cur[1] == '-' ) { // '--' reached!
                array_push($tmp, '--'); // keep track that '--' was encountered and where is was.
                $tmp = array_merge($tmp, $argv);
                break;
            }

            if ($cur[1] != '-') { // short option
                for ($i = 1; $i < strlen($cur); $i++) {
                    $o = $cur[$i];
                    // validate the presence of this option in $optstring
                    $pos = strpos($optstring, $o);

                    if ($pos === false) {
                        array_push($GLOBALS['argv_inval'], $o);
                        continue;
                    }

                    if (!array_key_exists($o, $opts)) { // not detected
                        if (@$optstring[$pos+1] == ':') { // option accept a value
                            $val = false;
                            if ($i+1 < strlen($cur)) // pick attached value
                                $val = substr($cur, $i+1);
                            else if (count($argv) && $argv[0][0] != '-') { // next argv element
                                $val = array_shift($argv);
                                if ($val[0] == '\\' && $val[1] == '-') // escaped leading '-'
                                    $val = ltrim($val, '\\');
                            }

                            if (@$optstring[$pos+2] == ':') // optional value
                                $opts[$o] = $val;
                            else { // required value
                                if ($val === false)
                                    array_push($argv_inval, $o);
                                else
                                    $opts[$o] = $val;
                            }
                            break;
                        }
                        else
                            $opts[$o] = false;
                    }

                    if (@$optstring[$pos+1] == ':') { // option accept a value
                        if (@$optstring[$pos+2] == ':') { // optional value
                            if (!defined('GETOPT_NOFIX')) {
                                // A bug(?) reside in getopt() where short optional argument values must be
                                // attached to their option. Concider optstring "o::ba" with command line like
                                // "-o word -ba", the '-o' option will not be associated with 'word'. This
                                // overcome that behavior.
                                if ($opts[$o] == false && count($argv) && $argv[0][0] != '-') {
                                    $v = array_shift($argv);
                                    if ($v[0] == '\\' && $v[1] == '-')
                                        $v = ltrim($v, '\\');
                                    $opts[$o] = $v;
                                }
                            }
                        }
                        else { // required value
                            // A bug(?) reside in getopt() when optstring="o:ba" and
                            // the command line looks like: "-o -ba", getopt() thinks that -ba
                            // is the value associated with "-o". This fixes this. To have '-ba'
                            // be treated as '-o' value, it will have to be escaped like:
                            // ' -o \\-ba' or '-o "\\-ba more text" '
                            if (!defined('GETOPT_NOFIX')) {
                                if (array_key_exists($o, $opts)) { // option was detected
                                    $val = $opts[$o];
                                    if ($val[0] == '\\' && $val[1] == '-') // escaped leading '-', unescape it.
                                        $opts[$o] = ltrim($val, '\\');
                                    else if ($val[0] == '-') {
                                        array_push($argv_inval, $o); // declare invalid
                                        unset($opts[$o]); // remove from valid
                                    }
                                }
                            }
                        }
                        break; // exit the loop since the option was accepting argument.
                    }
                }
            }
            else { // long option
                $o = ltrim($cur, '-');
                if (($oo = strstr($o, '=', true)) !== false) // attached long optional value
                    $o = $oo;
                if (!array_key_exists($o, $opts))
                    array_push($GLOBALS['argv_inval'], $o);
            }
        }
        else { // validate its not an orphan value, otherwise keep it.
            foreach ($values as $v) 
                if ($v == $cur)
                    continue 2;
            array_push($tmp, $cur);
        }
    }
    $argv = $tmp;
    return $opts;
}

require_once("yy/installer/CCPP.class.php");
function CCPP_Usage() {
    global $argv;
    echo "{$argv[0]} -cOo filename input_file\n\n".
    "\tC Compatible PreProcessor for PHP, version ".CCPP_VERSION."\n\n".
    "\t-c              compact white spaces.\n".
    "\t-o <filename>   output to file. If not specified, stdout will be used.\n".
    "\t-O              overwrite the output file if it exists.\n\n";
    die();
}

if (php_sapi_name() == 'cli') {
    if (count($argv) < 2)
        CCPP_Usage();

    $optstring = 'cOo:';
    $opts = getopt($optstring);
    getopt_janitor($opts, $optstring);

    if (count($argv_inval))
        CCPP_Usage();

    if (!file_exists($argv[1]))
        CCPP_Usage();

    if (array_key_exists('o',$opts))
        if (file_exists($opts['o']) && !array_key_exists('O', $opts))
            die("CCPP: file {$opts['o']} already exists. Missing -O?\n");

    
    
    $ccpp = new CCPP();
    if (array_key_exists('c', $opts))
        $ccpp->options['translate.compactWhitespaces'] = true;

    $code = $ccpp->parseFilename($argv[1]);

    if (array_key_exists('o', $opts))
        file_put_contents($opts['o'], $code);
    else
        echo $code;
}
?>
