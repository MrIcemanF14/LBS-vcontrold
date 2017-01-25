###[DEF]###
[name = VClient v0.1 ]

[e#1 = Trigger ]
[e#2 = IP #init=localhost]
[e#3 = Port #init=3002]
[e#4 = Command ]

[e#6 = Log level #init=8 ]

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

[v#100 = 0.1 ]
[v#101 = 19000930 ]
[v#102 = VClient v0.1 ]
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

###[/HELP]###

###[LBS]###
<?
function LB_LBSID($id) {
	if ($E = getLogicEingangDataAll($id)) {
		setLogicElementVar($id, 103, $E[6]['value']); //set loglevel to #VAR 103
		if ($E[1]['refresh'] == 1) callLogicFunctionExec(LBSID, $id);
	}
}
?>
###[/LBS]###

###[EXEC]###
<?
require(dirname(__FILE__)."/../../../../main/include/php/incl_lbsexec.php");
set_time_limit(60); //Script soll maximal 60 Sekunden laufen

function logging($id,$msg, $var=NULL, $priority=8)
{
	$E=getLogicEingangDataAll($id);
	$logLevel = getLogicElementVar($id,103);
	if (is_int($priority) && $priority<=$logLevel && $priority>0)
	{
		$logLevelNames = array('none','emerg','alert','crit','err','warning','notice','info','debug');
		$version = getLogicElementVar($id,100);
		$lbsNo = getLogicElementVar($id,101);
		$logName = getLogicElementVar($id,102) . ' --- LBS'.$lbsNo;
		strpos($_SERVER['SCRIPT_NAME'],$lbsNo) ? $scriptname='EXE'.$lbsNo : $scriptname = 'LBS'.$lbsNo;
		writeToCustomLog($logName,str_pad($logLevelNames[$logLevel],7), $scriptname." [v$version]:\t".$msg);
		
		if (is_object($var)) $var = get_object_vars($var); // transfer object into array
		if (is_array($var)) // print out array
		{
			writeToCustomLog($logName,str_pad($logLevelNames[$logLevel],7), $scriptname." [v$version]:\t================ ARRAY/OBJECT START ================");
			foreach ($var as $index => $line)
				writeToCustomLog($logName,str_pad($logLevelNames[$logLevel],7), $scriptname." [v$version]:\t".$index." => ".$line);
			writeToCustomLog($logName,str_pad($logLevelNames[$logLevel],7), $scriptname." [v$version]:\t================ ARRAY/OBJECT END ================");
		}
	}
}

if ($E=logic_getInputs($id)) {
		
	logging($id,"LBS started");
	
	$ip = $E[2]['value']; // IP where apcupsd is running
	$port = $E[3]['value']; // Port of apcupsd (default: 3551)
	$cmd = split("|", ($E[4]['value']));
	
	$n = count($cmd);
		
	if ($n>10) logging($id, "Too many command elements specfied on input E4. Please limit to 10.");
	
	$i=1;
	
	foreach ($cmd as $c)
	{
		$command = '/usr/local/bin/vclient -h '.$ip.':'.$port.' -c '.$c.' 2>&1';
		exec($command, $output, $returnCode);
				
		if ($returnCode != 0)
		{
			logging($id, "FEHLER - Rückgabewert $returnCode",2);
			logging($id, $output, 7);
		}
		else {
			logic_setOutput($id,$i,$output[1]);
		}
		$i++;		
		if ($i>10) break;
	}
	
	logging($id,"LBS stopped");
}


?>

###[/EXEC]###
