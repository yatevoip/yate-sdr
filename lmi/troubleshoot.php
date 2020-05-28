<?php

require_once "ansql/lib.php";

$method = getparam("method");

if (call_user_func($method) === FALSE)
	    Debug::trigger_report("critical","Method requested from pages was not found: ".$method);

function exec_in_real_time()
{
	global $tshoot_path;

	//  Turn implicit flush on
	ob_implicit_flush(true);
	// Turns off output buffering, so we see results immediately.
	ob_end_flush();

	//command to be executed
	$cmd = $tshoot_path;
	$descriptorspec = array(
	  0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
	  1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
	  2 => array("pipe", "w")    // stderr is a pipe that the child will write to
	);

	$process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), null);

	?>
        <html>
                <head>
                        <link type="text/css" rel="stylesheet" href="css/main.css" />
                </head>
                <body>
                        <div>
	<?php

	//hold all outputed lines
	$outputed_lines = array();
	if (is_resource($process)) {
		while ($line = fgets($pipes[1])) {
			print style_string($line) . "<br>" ;
			$outputed_lines[] = $line;
		}

		if (!count($outputed_lines)) {
			$error_lines = stream_get_contents($pipes[2]);
			if ($error_lines)
				errormess($error_lines,"no");
		}
	} else {
		errormess("Could not execute command for IP troubleshooting.", "no");
	}
        
	?>
                        </div>
                </body>
        </html>
	<?php
}

function style_string($line)
{
	$exploded_line = explode(" ", $line);
	// remove first element from array and store it in $first_word
	$first_word = array_shift($exploded_line);
	// reasamle text without first word
	$text = implode(" ", $exploded_line);

	switch ($first_word) {
		case "---":
			return '<div class="troubleshooting_subtitle">' . $text . '</div>';
		case "✔":
			return '<span class="ok_sign_green">' . $first_word . '</span>'. " " .  '<span class="green_text">' . $text . "</span>";
		case "X":
			return '<span class="error_sign_red">' . $first_word . '</span>'. " " .  '<span class="red_text">' . $text . "</span>";
		default:
			return $line;
	}
}