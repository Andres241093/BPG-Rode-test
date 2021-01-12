<?php 
require_once("mysql_compatibility_php7.php");

Class Database
{
	private $host;
	private $user;
	private $pass;

	public function __construct()
	{
		$this->host = 'localhost';
		$this->user = 'root';
		$this->pass = '';
		$this->db_name = 'admin_new';
	}

	public function connectToDB()
	{
		return mysql_connect($this->host,$this->user,$this->pass,$this->db_name) or self::connectionFailed();
	}

	public function closeConnectionToDB()
	{
		return mysql_close();
	}

	function connectionFailed()
	{
		http_response_code(500);
	}
}

?>