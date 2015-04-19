<?php

date_default_timezone_set('America/Los_Angeles');
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
require('./Services/Twilio.php');
require('./lib/mycurl.php');

class rev
{
    private static $_instance = null;
    private $_twilioClient    = null;
    private $_config          = null;

    /**
     * Setting up the Twilio APU and running the while loop (In checkJobs()).
     */
    private function __construct()
    {
        $this->_config = include('config.php');

        $sid   = $this->_config['twilioSid']; // Your Account SID from www.twilio.com/user/account
        $token = $this->_config['twilioToken']; // Your Auth Token from www.twilio.com/user/account

        $this->_twilioClient = new Services_Twilio($sid, $token);

        $this->checkJobs();
    }

    // Making this a Singleton because we only want one instand of checkJobs() running at a time.
    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * Log helper method.
     * 
     * @param $msg String A message to be logged.
     * @param $sms Boolean Whether or not to also SMS someone the message
     * @return null
     */
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

    /**
     * A method used to send messages to people via SMS.
     *
     * @param $msg String A message to be sent
     * @return null
     */
    protected function smsme($msg)
    {
        if ($this->_config['env'] === 'prod')
        {
            $message = $this->_twilioClient->account->messages->sendMessage(
                $this->_config['twilioFromNumber'],
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

    // public function formatGetTimespan($n)
    // {
    //     $t = abs($n);
    //     $i = floor($t / 1000);
    //     $f = floor($t / 86400000);
    //     $r = floor($t / 3600000);
    //     $u = floor($t / 60000);
    //     return array(
    //         "mills" => ($t % 1000),
    //         "days"  => $f,
    //         "hrs"   => ($r % 24),
    //         "min"   => ($u % 60),
    //         "sec"   => ($i % 60)
    //     );
    // }

    /**
     * A parsing method for extracting job data from an HTML page.
     * 
     * @param $jobspage String An HTML string from the work page.
     * @return Mixed Boolean: flase | Array: An array of jobs data
     */
    protected function getJobsFromPage($jobspage)
    {
        $jobsObject = json_decode($jobspage);

        $jobMatches = array();
        foreach ($jobsObject as $jobObject)
        {
            // Make sure the word count is in multiples of 250
            if (($jobObject->sizeInWords % 250) != 0)
            {
                continue;
            }

            $jobLength = ($jobObject->sizeInWords / 250);
            $jobTime = ($jobObject->timeLimit / 1000);

            $jobMatches[] = array(
                "jobID"     => (string)trim($jobObject->projectNumber),
                "worth"     => preg_replace("/[^0-9,.]/", "", (string)trim($jobObject->payment->payTotal)),
                "jobLength" => $jobLength,
                "jobTime"   => $jobTime
            );
        }

        return $jobMatches;
    }

    /**
     * A method used to determine the "best" jobs in order. Additionally skips
     * jobs that don't match the given criteria.
     *
     * @param $allJobs Array An arry of jobs data from the getJobsFromPage Method.
     * @return Array A single (best) item from all the jobs available.
     */
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

    /**
    * A method to accept a job.
    *
    * @param $jobspage String The jobs page HTML
    * @return null
    */
    protected function tryToGetJob($jobspage)
    {
        $allJobs = $this->getJobsFromPage($jobspage);

        $worthy = (is_array($allJobs) && !empty($allJobs) ? "worthy " : "");
        $jobCount = (is_array($allJobs) && !empty($allJobs) ? count($allJobs) : 0);

        if (is_array($allJobs) && !empty($allJobs))
        {
            $this->revlog("ID               |    Worth |  Length |    Time");
            foreach ($allJobs as $job)
            {
                $this->revlog(str_pad($job["jobID"],16," ",STR_PAD_RIGHT)." | ".str_pad("$".$job["worth"],8," ",STR_PAD_LEFT)." | ".str_pad($job["jobLength"]."p",7," ",STR_PAD_LEFT)." | ".str_pad($job["jobTime"]."s",7," ",STR_PAD_LEFT));
            }
        }

        while (is_array($allJobs) && !empty($allJobs))
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

    /**
     * A method to curl and check for new jobs.
     *
     * @return null
     */
    protected function checkJobs()
    {
        $rev = new revMyCurl($this->_config['revFindworkUrl']);
        $rev->setCustomHttpHeaders(array(
            'Expect:',
            'Host: www.rev.com',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Cache-Control: no-cache',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2351.3 Safari/537.36',
            'Referer: https://www.rev.com/workspace/findwork',
            'Accept-Encoding: gzip, deflate, sdch',
            'Accept-Language: en-US,en;q=0.8'
        ));
        $rev->setIncludeHeader(false);

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

    /**
     * A helper comparison function used to order the jobs by worth
     */
    static function cmp($a, $b)
    {
        if ($a["worth"] == $b["worth"]) {
            return 0;
        }
        return ($a["worth"] < $b["worth"]) ? -1 : 1;
    }

    /**
     * A method to get a valid page count for jobs.
     *
     * @param $pCount String A human readable page count
     * @return Integer An amount of pages associated with the job.
     */
    protected function getPagesCount($pCount)
    {
        preg_match('/((?P<pages>\d+)p\s*)?/', (string)$pCount, $matches);
        $totalpageCount = 0;
        $totalpageCount += (isset($matches["pages"]) && !empty($matches["pages"]) ? ($matches["pages"]) : 0);
        return $totalpageCount;
    }

    /**
     * A method to get a valid job length for jobs.
     *
     * @param $time String A human readable length for the job
     * @return Integer An amount of time in second associated with the job.
     */
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
