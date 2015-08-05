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

        $this->_db->exec("CREATE TABLE IF NOT EXISTS config (key STRING, value STRING)");

        $this->_config = include('config.php');

        foreach ($this->_config as $k => $v)
        {
        	// Don't overwrite values currently set
        	if (!$this->get($k))
        	{
            	$this->$k = $v;
            }
        }

		$this->_closeConnection();        
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
    	$this->_openConnection();
        $db = $this->_db;

        $result = $db->query("SELECT * FROM config");

        foreach($result as $row)
        {
            echo "key: " . $row['key'] . "\n";
            echo "value: " . $row['value'] . "\n";
            echo "\n";
        }
        $this->_closeConnection();
    }

    public function getAll()
    {
    	$this->_openConnection();
    	$db = $this->_db;

        $stmt = $db->prepare("SELECT * FROM config");

        if (!$stmt->execute())
        {
            return null;
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->_closeConnection();

        return $result;
    }

    public function get($k)
    {
    	$this->_openConnection();
        $db = $this->_db;

        $stmt = $db->prepare("SELECT * FROM config WHERE key = ?");
        if (!$stmt->execute(array($k)))
        {
            return null;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->_closeConnection();

        return $result["value"];
    }

    public function set($k, $v)
    {
    	$this->_openConnection();
        $db = $this->_db;

        $get = $this->get($k);
        if ($get !== null &&
            $get !== false)
        {
            $stmt = $db->prepare("UPDATE config SET value = ? WHERE key = ?");
            echo "UPDATING ".$k." -> ".$v."\n";
        }
        else
        {
            $stmt = $db->prepare("INSERT INTO config (value, key) VALUES(?, ?)");
            echo "INSERTING ".$k." -> ".$v."\n";
        }

        if (!$stmt->execute(array($v, $k)))
        {
        	$this->_closeConnection();
            return false;
        }
        $this->_closeConnection();

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