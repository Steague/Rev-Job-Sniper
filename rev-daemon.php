<?php

date_default_timezone_set('America/Los_Angeles');
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
require('./Services/Twilio.php');
require('./lib/mycurl.php');

class rev
{
    private static $_instance = null;
    private $_twilioClient = null;
    private $_config = null;

    private function __construct()
    {
        $this->_config = include('config.php');

        $sid = $this->_config['twilioSid']; // Your Account SID from www.twilio.com/user/account
        $token = $this->_config['twilioToken']; // Your Auth Token from www.twilio.com/user/account

        $this->_twilioClient = new Services_Twilio($sid, $token);

        $this->checkJobs();

        // $jobsPage = file_get_contents('rev.1427832409.html');
        // $this->revlog($this->getJobsFromPage($jobsPage));
    }

    // Making this a Singleton because we only want one instand of checkJobs() running at a time.
    public static function getInstance()
    {
        if (self::$_instance === null)
        {
            $c = __CLASS__;
            self::$_instance = new $c;
        }

        return self::$_instance;
    }

    public function revlog($msg, $sms = false)
    {
        $timestamp = "[".date("Y-m-d H:i:s")."]";
        switch (true)
        {
            case (is_string($msg)):
                break;
            default:
                ob_start();
                var_dump($msg);
                $msg = ob_get_clean();
                break;
        }

        echo $timestamp." ".$msg."\n";

        if ($sms === true)
        {
            $this->smsme($timestamp." ".$msg);
        }
    }

    protected function smsme($msg)
    {
        if ($this->_config['env'] === 'prod')
        {
            $message = $this->_twilioClient->account->messages->sendMessage(
                $this->_config['twilioFromNumber'], // From a valid Twilio number
                $this->_config['twilioToNumber'],
                $msg
            );
            $this->revlog("Sent notification. SID: ".$message->sid.".");
        }
        else
        {
            $this->revlog("SMS not sent due to not being on production.");
        }
    }

    protected function getJobsFromPage($jobspage)
    {
        try
        {
            # Create a DOM parser object
            $dom = new DOMDocument();

            # Parse the HTML from Google.
            # The @ before the method call suppresses any warnings that
            # loadHTML might throw because of invalid HTML in the page.
            @$dom->loadHTML($jobspage);

            foreach($dom->getElementsByTagName('table') as $table)
            {
                if ($table->getAttribute('class') == 'table orders sortable')
                {
                    $jobMatches = array();
                    $i = 0;
                    foreach($dom->getElementsByTagName('tr') as $tableRow)
                    {
                        //Skip the first (header) row
                        if ($i < 1)
                        {
                            $i++;
                            continue;
                        }
                        $jobData = explode("\n", trim($tableRow->textContent));
                        $jobMatches[] = array(
                            "jobID"     => (string)trim($jobData[0]),
                            "worth"     => preg_replace("/[^0-9,.]/", "", (string)trim($jobData[5])),
                            "jobLength" => $this->getPagesCount($jobData[3]),
                            "jobTime"   => $this->getTimeInSecs($jobData[6])
                        );
                    }

                    if (!empty($jobMatches))
                    {
                        return $jobMatches;
                    }
                }
            }
        }
        catch (Exception $e)
        {
            $this->revlog('Caught exception: '.$e->getMessage());
            file_put_contents("rev.".time().".html", $jobspage);
        }
        return false;
    }

    protected function getBestDesireableJob($allJobs)
    {
        $validJobs = array();
        if (!is_array($allJobs) || empty($allJobs))
        {
            return false;
        }
        foreach ($allJobs as $job)
        {
            if ($job["jobLength"] < 4)
            {
                continue;
            }
            $pricePerPage = $job["worth"] / $job["jobLength"];
            if ($pricePerPage < 10)
            {
                continue;
            }
            if ($job["jobTime"] < 24)
            {
                continue;
            }

            $validJobs[] = $job;
        }
        usort($validJobs, array("rev", "cmp"));

        return array_pop($validJobs);
    }

    protected function tryToGetJob($jobspage)
    {
        $allJobs = $this->getJobsFromPage($jobspage);

        $worthy = ($allJobs !== false && is_array($allJobs) && !empty($allJobs) ? "worthy " : "");
        $jobCount = ($allJobs !== false && is_array($allJobs) && !empty($allJobs) ? count($allJobs) : 0);

        if ($allJobs !== false && is_array($allJobs) && !empty($allJobs))
        {
            $this->revlog("ID               |    Worth |  Length |    Time");
            foreach ($allJobs as $job)
            {
                $this->revlog(str_pad($job["jobID"],16," ",STR_PAD_RIGHT)." | ".str_pad("$".$job["worth"],8," ",STR_PAD_LEFT)." | ".str_pad($job["jobLength"]."p",7," ",STR_PAD_LEFT)." | ".str_pad($job["jobTime"]."s",7," ",STR_PAD_LEFT));
            }
        }


        while ($allJobs !== false && is_array($allJobs) && !empty($allJobs))
        {
            $job = $this->getBestDesireableJob($allJobs);

            if ($job === false)
            {
                break;
            }

            if (!is_array($job) || !array_key_exists("jobID", $job))
            {
                array_shift($allJobs);
                continue;
            }

            $this->revlog("(".$job["jobID"].") Attempting to accept job.");

            $claim = new revMyCurl($this->_config['revClaimUrl'].$job["jobID"]);
            $claim->setReferer($this->_config['revClaimReferrerUrl'].$job["jobID"]);
            $claim->setPost(array());
            $claim->setIncludeHeader(true);
            $claim->createCurl();
            ob_start();
            echo $claim;
            $claimpage = ob_get_clean();
            $taken = (strpos($claimpage, "sorry, but project ".$job["jobID"]." is no longer available") !== false ? true : false);
            $error = (strpos($claimpage, "Internal Server Error") !== false ? true : false);

            if ($taken === true ||
                $error === true)
            {
                switch (true)
                {
                    case ($taken === true):
                        $this->revlog("(".$job["jobID"].") Job no longer available.");
                        break;
                    case ($error === true):
                        $this->revlog("(".$job["jobID"].") There was an internal server error with the job.");
                        break;
                }
                array_shift($allJobs);
                sleep(3);
                continue;
            }
            $this->revlog("Accepted job: ".$job["jobID"].".",true);
            return;
        }

        $this->revlog("No ".$worthy."jobs to accept. Jobs: (".$jobCount.")");
    }

    protected function checkJobs()
    {
        $rev = new revMyCurl($this->_config['revFindworkUrl']);

        $this->revlog("Initializing first page load.");
        while (true)
        {
            $this->revlog("Looking for jobs.");
            try
            {
                $rev->createCurl();

                ob_start();
                echo $rev;
                $revpage = ob_get_clean();
            }
            catch (Exception $e)
            {
                $this->revlog("Error when getting rev page.");
                $this->revlog($rev);
                $this->revlog($revpage);
                sleep(30);
                continue;
            }

            //check to see if I need to sign in
            //<h1 class="home-title">Sign In</h1>
            if (strpos($revpage, '<h1 class="home-title">Sign In</h1>'))
            {
                $this->revlog("Found sign in link. Need to login/update cookie.");
                $rev->loginToRev();
                sleep(5);
                continue;
            }

            try
            {
                $this->tryToGetJob($revpage);
            }
            catch (Exception $e)
            {
                $this->revlog('Caught exception: '.$e->getMessage());
            }

            // Keeping the keepalive script running (only for production)
            if ($this->_config['env'] === 'prod')
            {
                $output = "";
                exec("ps auxwww|grep keepalive.php|grep -v grep", $output);
                if (empty($output) || $output === false || $output === null)
                {
                    exec("php keepalive.php > keepalive-log.log &");
                }
                else
                {
                    //echo "Daemon is running.";
                }
            }

            sleep(30);
        }
    }

    static function cmp($a, $b)
    {
        if ($a["worth"] == $b["worth"]) {
            return 0;
        }
        return ($a["worth"] < $b["worth"]) ? -1 : 1;
    }

    protected function getPagesCount($pCount)
    {
        preg_match('/((?P<pages>\d+)p\s*)?/', (string)$pCount, $matches);
        $totalpageCount = 0;
        $totalpageCount += (isset($matches["pages"]) && !empty($matches["pages"]) ? ($matches["pages"]) : 0);
        return $totalpageCount;
    }

    protected function getTimeInSecs($time)
    {
        preg_match('/((?P<days>\d+)d\s*)?((?P<hours>\d+)h\s*)?((?P<minutes>\d+)m\s*)?((?P<seconds>\d+)s)?/', (string)$time, $matches);
        $totalTimeInSecs = 0;
        $totalTimeInSecs += (isset($matches["days"]) && !empty($matches["days"]) ? ($matches["days"] * (60 * 60 * 24)) : 0);
        $totalTimeInSecs += (isset($matches["hours"]) && !empty($matches["hours"]) ? ($matches["hours"] * (60 * 60)) : 0);
        $totalTimeInSecs += (isset($matches["minutes"]) && !empty($matches["minutes"]) ? ($matches["minutes"] * (60)) : 0);
        $totalTimeInSecs += (isset($matches["seconds"]) && !empty($matches["seconds"]) ? ($matches["seconds"]) : 0);
        return $totalTimeInSecs;
    }
}

$rev = rev::getInstance();