<?php

function troubleshooting()
{
	echo "<div class='hold_content_troubleshooting'>";
	notice("Basic troubleshooting <button id='troubleshooting_start' class='troubleshooting_start_button' onclick='display_troubleshoot();'>Start troubleshooting</button>","no", true, false);
        echo "<div id='troubleshoot_message'></div>";
        echo "<div id='troubleshooting_output'></div>";
        echo "<button id='troubleshooting_restart' class='troubleshooting_button' style='display:none;' onclick='display_troubleshoot(true);'>Restart troubleshooting</button>";
        echo "<button id='troubleshooting_stop' class='troubleshooting_button' style='display:none;' onclick='troubleshoot_finish(true)'>Stop troubleshooting</button>";
        echo "</div>"; 
}

?>