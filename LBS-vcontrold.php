###[DEF]###
[name = VClient v0.4 ]

[e#1 trigger = Trigger ]
[e#2 important = IP (vcontrold) #init=localhost]
[e#3 important = Port #init=3002]
[e#4 option = Command ]
[e#5 option = JSON-Commands ]
[e#6 = Log level #init=5 ]

[a#1 = Output 1 ]
[a#2 = Output 2 ]
[a#3 = Output 3 ]
[a#4 = Output 4 ]
[a#5 = Output 5 ]
[a#6 = Output 6 ]
[a#7 = Output 7 ]
[a#8 = Output 8 ]
[a#9 = Output 9 ]
[a#10 = Output 10 ]
[a#11 = JSON-Output]
[a#12 = Trigger]

[v#100 = 0.4 ]
[v#101 = 19000930 ]
[v#102 = VClient v0.4 ]
[v#103 = 0 ]

###[/DEF]###

###[HELP]###

Dieser LBS liest Werte einer Viessmann-Heizung über den vcontrold-Dienst und den vclient aus. Der Sourcecode stammt aus dem openv-Wiki und wurde für CentOS6.5 kompiliert. Es muss sichergestellt sein, dass der vcontrol-Dienst gestartet ist.

Der Aufruf erfolgt für jede Adresse separat. Achtung, die Anfragen benötigen aufgrund des Übertragungsprotokolls einige Zeit. Es sollte also bei mehreren Abfragen eine entsprechende Pause eingebaut werden.

V100: Version
V101: LBS Number
V102: Log file name
V103: Log level

Changelog:
==========
v0.1: Initial version
v0.2: für CentOS kompilierte vcontrold und vclient in Archiv beigefügt
v0.3: Ein- und Ausgang für JSON-Strings hinzugefügt
v0.4: Pausen und Wiederholungen eingefügt, da es bei Verwendung des Protokoll P300 zu Lesefehlern kommt, wenn Anfragen zu schnell hintereinander ausgeführt werden

###[/HELP]###

###[LBS]###
<?php
function LB_LBSID($id) {
	if ($E = logic_getInputs($id)) {
		setLogicElementVar($id, 103, $E[6]['value']);
		//set loglevel to #VAR 103
		if ($E[1]['refresh'] == 1)
			callLogicFunctionExec(LBSID, $id);
	}
}
?>
###[/LBS]###

###[EXEC]###
<?php
require (dirname(__FILE__) . "/../../../../main/include/php/incl_lbsexec.php");

set_time_limit(60);
//Script soll maximal 60 Sekunden laufen

function logging($id, $msg, $var = NULL, $priority = 8) {
	$E = getLogicEingangDataAll($id);
	$logLevel = getLogicElementVar($id, 103);
	if (is_int($priority) && $priority <= $logLevel && $priority > 0) {
		$logLevelNames = array('none', 'emerg', 'alert', 'crit', 'err', 'warning', 'notice', 'info', 'debug');
		$version = getLogicElementVar($id, 100);
		$lbsNo = getLogicElementVar($id, 101);
		$logName = getLogicElementVar($id, 102) . ' --- LBS' . $lbsNo;
		strpos($_SERVER['SCRIPT_NAME'], $lbsNo) ? $scriptname = 'EXE' . $lbsNo : $scriptname = 'LBS' . $lbsNo;
		writeToCustomLog($logName, str_pad($logLevelNames[$logLevel], 7), $scriptname . " [v$version]:\t" . $msg);

		if (is_object($var))
			$var = get_object_vars($var);
		// transfer object into array
		if (is_array($var))// print out array
		{
			writeToCustomLog($logName, str_pad($logLevelNames[$logLevel], 7), $scriptname . " [v$version]:\t================ ARRAY/OBJECT START ================");
			foreach ($var as $index => $line)
				writeToCustomLog($logName, str_pad($logLevelNames[$logLevel], 7), $scriptname . " [v$version]:\t" . $index . " => " . $line);
			writeToCustomLog($logName, str_pad($logLevelNames[$logLevel], 7), $scriptname . " [v$version]:\t================ ARRAY/OBJECT END ================");
		}
	}
}

if (!function_exists('json_last_error_msg')) {
	function json_last_error_msg() {
		static $ERRORS = array(JSON_ERROR_NONE => 'No error', JSON_ERROR_DEPTH => 'Maximum stack depth exceeded', JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)', JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded', JSON_ERROR_SYNTAX => 'Syntax error', JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded');

		$error = json_last_error();
		return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
	}

}

sql_connect();

if ($E = logic_getInputs($id)) {

	logging($id, "LBS started");

	$ip = $E[2]['value'];
	// IP where vcontrold is running
	$port = $E[3]['value'];
	// Port of vcontrold (default: 3002)
	if (!empty($E[4]['value'])) {
		$cmd = explode("|", $E[4]['value']);
		$n = count($cmd);

		if ($n > 10)
			logging($id, "Too many command elements specfied on input E4. Please limit to 10.");
		$i = 1;

		foreach ($cmd as $c) {
			$command = '/usr/local/bin/vclient -h ' . $ip . ':' . $port . ' -c ' . $c . ' 2>&1';
			$retrys = 3;
			do {
				$output = array();
				exec($command, $output, $returnCode);
				if (strpos($output[0], ' addr was still active') !== false) {
					$retrys--;
					// Warte 0,2 Sekunden
					usleep(200000);
				} else
					break;
			} while($retrys > 0);

			logging($id, "Command: $command");
			logging($id, "Return code: $returnCode");

			if ($returnCode !== 0) {
				logging($id, "FEHLER - Rückgabewert $returnCode", 2);
				logging($id, 'dump of $output:', $output, 2);
			} else if ($retrys == 0) {
				logging($id, "FEHLER - Adresse konnte nicht gelesen werden", 2);
				logging($id, 'dump of $output:', $output, 2);
			} else {

				$out = explode(" ", $output[1]);
				if ($out[0] != '128.500000') {
					logging($id, 'dump of $output:', $output);
					logic_setOutput($id, $i, $out[0]);
				} else {
					logging($id, '$output', $output, 5);
				}
			}

			$i++;
			if ($i > 10)
				break;
		}

	}
	if (!empty($E[5]['value'])) {

		$commands = json_decode($E[5]['value'], false);
		$json_out = array();

		if (json_last_error() === JSON_ERROR_NONE) {

			foreach ($commands as $key => $cmd) {
				if (is_string($cmd)) {
					if (!empty($cmd)) {
						$command = '/usr/local/bin/vclient -h ' . $ip . ':' . $port . ' -c ' . $cmd . ' 2>&1';
						$output = array();

						$retrys = 3;
						do {
							exec($command, $output, $returnCode);
							if (strpos($output[0], ' addr was still active') !== false) {
								$retrys--;
								// Warte 0,2 Sekunden
								usleep(200000);
							} else
								break;
						} while($retrys > 0);

						logging($id, "Command: $command");
						logging($id, "Return code: $returnCode");

						if ($returnCode != 0) {
							logging($id, "FEHLER vclient - Rückgabewert $returnCode", 2);
							logging($id, "dump of key:$key", $output, 2);
							$json_out[$key] = "";
						} else if ($retrys == 0) {
							logging($id, "FEHLER vclient - Rückgabewert $returnCode", 2);
							logging($id, "dump of key:$key", $output, 2);
							$json_out[$key] = "";
						} else {

							$out = explode(" ", $output[1]);
							if ($out[0] != '128.500000') {
								logging($id, "key:$key", $output);
								$json_out[$key] = $out[0];
							} else {
								$json_out[$key] = "";
								logging($id, '$output', $output, 5);
							}
						}
					} else {
						logging($id, "empty string given", 7);
					}
				} else {
					logging($id, "Array-Item is no valid string. Array-Item given:", 5);
					logging($id, "$key => $cmd", 5);
				}

			}
			logic_setOutput($id, 11, json_encode($json_out));
			logic_setOutput($id, 12, 1);
		} else {
			logging($id, "FEHLER - Ungültiger JSON-String", 2);
			logging($id, json_last_error_msg(), 2);
		}

	}

	logging($id, "LBS stopped");
}

sql_disconnect();
?>
###[/EXEC]###