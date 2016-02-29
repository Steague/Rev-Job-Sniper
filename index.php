<?php

date_default_timezone_set('America/Los_Angeles');
error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();

# Check for session timeout, else initiliaze time
if (isset($_SESSION['timeout'])) {  
    # Check Session Time for expiry
    #
    # Time is in seconds. 10 * 60 = 600s = 10 minutes
    if ($_SESSION['timeout'] + 30 * 60 < time()){
        session_destroy();
    }
}
else {
    # Initialize variables
    $_SESSION['user']="";
    $_SESSION['pass']="";
    $_SESSION['timeout']=time();
}

# Store POST data in session variables
if (isset($_POST["user"])) {    
    $_SESSION['user']=$_POST['user'];
    $_SESSION['pass']=hash('sha256',$_POST['pass']);
}

# Check Login Data
#
# Password is hashed (SHA256). In this case it is 'admin'.
if($_SESSION['user'] != "admin" ||
    $_SESSION['pass'] != "5092ab784ba9643669c982ee084baef9d9d2979a2cc29cc64b69e27270602b52")
{
    # Show login form. Request for username and password
    {
        ?>
        <html>
        <body>      
            <form method="POST" action="">
                Username: <input type="text" name="user"><br/>
                Password: <input type="password" name="pass"><br/>
                <input type="submit" name="submit" value="Login">
            </form>
        </body>
        </html> 
        <?
        exit();
    }
}

require('./lib/configrev.php');

$config = ConfigRev::getInstance();

function splitAtUpperCase($s)
{
    return preg_split('/(?=[A-Z])/', $s, -1, PREG_SPLIT_NO_EMPTY);
}

?>
<html>
<head>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">
<style>
h1 {
    margin-top:80px;
}
.input-group{
    width: 100%;
}
.input-group-addon{
    width: 25%;
    text-align: left;
}
.form-control {
    width: 75%;
}
</style>
</head>
<body>
    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">Rev Job Sniper</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li class="active"><a href="#">Home</a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>
    <div class="container">
        <div class="starter-template">
            <h1>Rev Job Sniper Config</h1>
            <p class="lead">
                <?php
                if ($_POST)
                {
                    echo "<br /><br />";
                    foreach ($_POST as $key => $value)
                    {
                        if (!$config->get($key))
                        {
                            continue;
                        }

                        //echo "Setting: ".$key." -> ".$value."<br />";
                        $config->$key = $value;
                    }
                }
                ?>
                <form action="?save" method="post">
                <?php
                foreach ($config->getAll() as $row)
                {
                    ?>
                    <div class="input-group">
                        <span class="input-group-addon" id="<?php echo $row['key']; ?>"><?php echo ucwords(implode(" ", splitAtUpperCase($row['key']))); ?></span>
                        <input type="text" class="form-control" name="<?php echo $row['key']; ?>" value="<?php echo $row['value']; ?>" aria-describedby="<?php echo $row['key']; ?>" />
                    </div>
                    <?php
                }
                ?>
                <input type="submit" />
                </form>
            </p>
            <h1>Sniper Info</h1>
            <?php
            $filename = 'rev-log.log';
            if (file_exists($filename)) {
                ?>
                <div class="input-group">
                    <span class="input-group-addon" id="sniperRunAt">Sniper was last run at</span>
                    <input type="text" class="form-control" name="sniperRunAt" value="<?php echo date ("F d Y H:i:s.", filemtime($filename)); ?>" aria-describedby="sniperRunAt" disabled />
                </div>
                <?php
                $mystring = "rev-daemon.php";
                exec("ps aux | grep '$mystring' | grep -v grep | awk '{ print $2 }' | head -1", $out);
                if ($out[0]) {
                    ?>
                    <div class="input-group">
                        <span class="input-group-addon" id="sniperPID">Sniper PID is</span>
                        <input type="text" class="form-control" name="sniperPID" value="<?php echo $out[0]; ?>" aria-describedby="sniperPID" disabled />
                    </div>
                    <?php
                }
            } else {
                echo "Sniper has never been run!";
            }
            ?>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        initAPI();
    });

    function initAPI() {
        $.getJSON("api.php?route=foo", function(data) {
            if (data.hasOwnProperty("response") &&
                data.response == "Invalid API call.") {
                console.log("API Ready.");
                startAPITick(5);
            }
        });
    }

    function startAPITick(secDelay) {
        $.getJSON("api.php?route=lastRun", function(data) {
            if (data.hasOwnProperty("response")) {
                console.log("Last run", data.response);
                $("#sniperRunAt").next('[name="sniperRunAt"]').value(data.response.readable);
                setTimeout(startAPITick(secDelay), secDelay * 1000);
            }
        });
    }
    </script>
</body>
</html>
