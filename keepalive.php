<?

while (true)
{
	$output = "";
	exec("ps auxwww|grep rev-daemon.php|grep -v grep", $output);

	if (empty($output) || $output === false || $output === null)
	{
		exec("php rev-daemon.php > rev-log.log &");
	}
	else
	{
		//echo "Daemon is running.";
	}

	sleep(5);

}