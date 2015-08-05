<?php

class ConfigRev
{
    private static $_instance = null;
    private $_db              = null;
    private $_config          = null;

    private function __construct()
    {
        //
        $this->_openConnection();

        $this->_db->exec("CREATE TABLE IF NOT EXISTS config (key varchar(255), value varchar(255))");

        $this->_config = include('config.php');

        foreach ($this->_config as $k => $v)
        {
        	// Don't overwrite values currently set
        	if (!$this->get($k))
        	{
            	$this->$k = $v;
            }
        }

    }

    private function _openConnection()
    {
    	if ($this->_db !== null)
    	{
    		return;
    	}
    	$this->_db = new PDO('sqlite:config.sqlite3');
        $this->_db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    private function _closeConnection()
    {
    	$this->_db = null;
    }

    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    public function printConfig()
    {
        $db = $this->_db;

        $result = $db->query("SELECT * FROM config");

        foreach($result as $row)
        {
            echo "key: " . $row['key'] . "\n";
            echo "value: " . $row['value'] . "\n";
            echo "\n";
        }
    }

    public function getAll()
    {
    	$db = $this->_db;

        $stmt = $db->prepare("SELECT * FROM config");

        if (!$stmt->execute())
        {
            return null;
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function get($k)
    {
        $db = $this->_db;

        $stmt = $db->prepare("SELECT * FROM config WHERE key = ?");
        if (!$stmt->execute(array($k)))
        {
            return null;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result["value"];
    }

    public function set($k, $v)
    {
        $db = $this->_db;

        $get = $this->get($k);
        if ($get !== null &&
            $get !== false)
        {
            $stmt = $db->prepare("UPDATE config SET value = ? WHERE key = ?");
            echo "UPDATING ".$k." -> ".$v."<br />\n";
        }
        else
        {
            $stmt = $db->prepare("INSERT INTO config (value, key) VALUES(?, ?)");
            echo "INSERTING ".$k." -> ".$v."<br />\n";
        }

        if (!$stmt->execute(array($v, $k)))
        {
        	$this->_closeConnection();
            return false;
        }

        return true;
    }

    public function __get($k)
    {
        return $this->get($k);
    }

    public function __set($k, $v)
    {
        return $this->set($k, $v);
    }
}