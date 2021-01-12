<?php

/*
Hi.

Use this link to make an array with the country prefix and name https://en.wikipedia.org/wiki/List_of_country_calling_codes
If you can't validate a prefix, then: 07, 02, 03 are Romanian, else german

00 will be converted to +
- and space will be repalced with NULL


Test with the following numbers the operations: Insert, attempt to duplicate insert, search, delete.
Insert should return the last insert id, and if duplicate found, it should return the original row id


RO:
+40-368-401454
0742601660
+40742601660
+401234

Rusia:
+79064313004

UA:
+380937687938
00380937687938

DE:
+495620233
004914281

04271952763
01725667130

+49-30-6789-4900
+49-30-6789-3210
+493374460196
+4933744707611
+493371621233

CN:
+86-28-85056019




*/



class Phonebook {
	
	private static $prefix;
	private static $number;
	private static $name;
	private static $data=[];
	private static $phone_codes = [];
	
	public function __construct($phonenumber='',$name='') {
		if($phonenumber) {
			self::processPhone($phonenumber);
		}
		if($name) {
			self::$name = $name;
		}
	}
	
	/**
	* adding new phone numbers into db, table all_phone_book
	*/
	public static function addPhone() {
		$sql = "INSERT INTO `all_phone_book` (`prefix`, `number`, `name`) VALUES ('".self::$prefix."','".self::$number."','".mysql_real_escape_string(self::$name)."') ON DUPLICATE KEY UPDATE name='".mysql_real_escape_string(self::$name)."'";
		//return self::phoneExist();
		if(!self::phoneExist())
		{
			$result = mysql_query($sql);
			http_response_code(201);//Created: the request fulfilled successfully
			return json_encode('Phone created successfully!');
		}else{
			http_response_code(422);//Unprocessable entity: the request is fine but the server cannot process it
			return json_encode('The phone already exists');
		}   
		//should return last insert id, or in case of dupplicate entry, it should return the id id the line
	}
	
	/** 
	* get phone number from db, tables all_phone_book
	*/	
	public static function getPhones($limit=50) { //??? it should be reimplemented
		$sql = "SELECT * FROM all_phone_book WHERE deleted=0 ORDER BY id ASC LIMIT ".floor($limit);
		$result = mysql_query($sql);
		while($row = mysql_fetch_assoc($result)){
			self::$data[] = $row;
		}
		return self::$data;
	}
	
	/** 
	* check if phone number exists in db, table all_phone_book
	*/	
	public static function searchPhone() { //it should check the concatenated prefix and phone numbber
		if(!self::$number) {
			return false;
		}
		$sql = "SELECT * FROM all_phone_book WHERE number like '%".self::$number."%' AND deleted=0";
		
		$result = mysql_query($sql);
		while($row = mysql_fetch_assoc($result)){
			self::$data[] = $row;
		}
		return self::$data;
	}
	
	/** 
	* make a relation to other modules, table all_phone_book_links
	*/
	public static function linkPhone($id,$table_id,$table_name) {
		if(!$id || !$table_id) {
			return false;
		}
		
		$sql = "INSERT INTO all_phone_book_links
		(phone_book_id, table_id, table_name) VALUES
		('".floor($id)."', 
		'".floor($table_id)."', 
		'".mysql_real_escape_string($table_name)."') 
				ON DUPLICATE KEY UPDATE deleted=0";         ///this query is poorly written. the deleted column does not exist into all_phone_book_links 
				$result = mysql_query($sql);
				return $result;
			}

	/** 
	* remove a relation to other modules and mark number as deleted, in case when phone number need to delete from db, 
	* table all_phone_book_links
	*/
	public static function unlinkPhone($link_id) {
		if (!$link_id) {
			return false;
		}
		$sql = "DELETE FROM all_phone_book_links WHERE phone_book_id='".floor($link_id)."'";
		$result = mysql_query($sql);
		if($result) {
			$query = "UPDATE all_phone_book SET deleted=1 WHERE id=".floor($link_id);
			$update_result = mysql_query($query);			
		}	
		return $update_result;
	}
	
	/** 
	* get all related to other modules phone numbers, tables all_phone_book, all_phone_book_links
	* Alex comment: should retrieve all the phones linked
	*/
	public static function getLinkedPhones($table_id,$table_name) {
		$sql = "SELECT * FROM all_phone_book as apb 
		LEFT JOIN all_phone_book_links as apbl 
		ON (apbl.phone_book_id=apb.id) 
		WHERE table_id = '".floor($table_id)."' 
		AND table_name = '".mysql_real_escape_string($table_name)."' ORDER BY apb.id ASC";
		$result = mysql_query($sql);
		while($row = mysql_fetch_assoc($result)){
			self::$data[] = $row;
		}
		return self::$data;		
	}
	
	/** 
	* all the rows from "all_phone_book_links" where is the all_phone_book.id present
	*/
	public static function getPhoneRefferencesId($id) {
		$sql = "SELECT * FROM all_phone_book_links as apbl 
		WHERE apbl.phone_book_id = '".floor($id)."'
		ORDER BY apbl.phone_book_id ASC";
		$result = mysql_query($sql);
		while($row = mysql_fetch_assoc($result)){
			self::$data[] = $row;
		}
		return self::$data;
	}
	
	/** 
	* all the rows from "all_phone_book_links" where is the all_phone_book.phone present 
	*/
	public static function getPhoneRefferencesByPhone($phone) {
		if(!$phone) {
			return false;
		}
		self::processPhone($phone);
		$sql = "SELECT *  
		FROM all_phone_book_links as apbl
		LEFT JOIN all_phone_book as apbb on apbb.id=apbl.phone_book_id
		WHERE apbl.phone_book_id IN( 
		SELECT apb.id  FROM all_phone_book as apb 
		WHERE apb.deleted = 0 
		AND (apb.number LIKE '".self::$number."%' OR apb.name LIKE '%".self::$name."%' )
	)";

	$result = mysql_query($sql);
	while($row = mysql_fetch_assoc($result)){
		self::$data[] = $row;
	}
	return self::$data;
}

	/** 
	this should be the main parse function. It was not design correctly. This is where you shouls prove your selv!
	*/
	public function processPhone($phone_str) {
		/*****   ******/
		$phone_str=str_replace('00','+',$phone_str); // this should be the other way arround! the leading 00 becomes + and the + will be later trimmed after the country prefix validation
		$phone_str=str_replace(array(' ','	','-','+'),array('','','',''),trim($phone_str));
		/*****   ******/
		
		//get prefix 

		$bigger_prefix = self::getBiggerPrefix();

		for($i=0; $i < $bigger_prefix; $i++) 
		{ 
			if(self::validatePrefix($phone_str,$bigger_prefix-$i))
			{
				//valid prefix!
				self::$prefix = substr($phone_str, 0, $bigger_prefix-$i);
				self::$number = substr($phone_str, $bigger_prefix-$i);
				return self::$prefix.self::$number;
			}
			if($i === $bigger_prefix-1)
			{
				self::$number = substr($phone_str, 2);
				return self::checkLocalPrefix(substr($phone_str, 0, 3));
			}
		}
	}

	public static function checkLocalPrefix($local_prefix)
	{
		if($local_prefix === '+07' || $local_prefix === '+02' || $local_prefix === '+03')
		{
			return self::$prefix = '40';//Romanian prefix
		}else{
			return self::$prefix = '49';//German prefix
		}
	}

	public static function getBiggerPrefix()
	{
		foreach (self::phoneCodes() as $value) 
		{
			$prefix_aux[]=$value['code'];
		}

		$bigger_prefix = max($prefix_aux);

		return strlen($bigger_prefix);
	}

	public static function validatePrefix($phonenumber,$prefix_length)
	{
		//store phone number prefix

		$phonenumber_prefix = substr($phonenumber,0,$prefix_length);

		//store all prefixes in array

		foreach (self::phoneCodes() as $value) 
		{
			$prefixes[]=$value['code'];
		}

		//Searching the phone prefix in array of prefixes

		if(in_array($phonenumber_prefix,$prefixes))
		{
			return true; //prefix exist
		}else{
			return false; //prefix does not exist
		}
	}

	public static function updatePhone($phone_id) {
		$sql = "UPDATE `all_phone_book` SET `prefix`='".self::$prefix."',`number`='".self::$number."',`name`='".mysql_real_escape_string(self::$name)."' WHERE `id`=".$phone_id;   
		$result = mysql_query($sql);
		return $result;
	}

	public static function phoneExist(){
		$sql = "SELECT * FROM `all_phone_book` WHERE `prefix`='".self::$prefix."' AND `number`='".self::$number."'";   
		//Checking if exist a row with this prefix and number
		$result = mysql_query($sql)->fetch_row();
		if($result === NULL)
		{
			return false; //number does not exist
		}else{
			//Checking if the number was erased
			if($result[5] === "1")
			{
				return false; //the number does not exist
			}else{
				return true; //the number exist
			}
		}
	}

	public static function getPrefix() {
		return self::$prefix;
	}

	public static function getNumber() {
		return self::$number;
	}

	public static function getName() {
		return self::$name;
	}

	public static function getData() {
		return self::$data;
	}

	public static function getPhoneCodes() {
		return self::phoneCodes();
	}

	public function phoneCodes () {
	//codes extracted from https://en.wikipedia.org/wiki/List_of_country_calling_codes
		return self::$phone_codes = [       
			[
				"country"=> "Afghanistan",
				"code"=> "+93"
			],
			[
				"country"=> "Aland Islands",
				"code"=> "+35818"
			],
			[
				"country"=> "Albania",
				"code"=> "+355"
			],
			[
				"country"=> "Algeria",
				"code"=> "+213"
			],
			[
				"country"=> "American Samoa",
				"code"=> "+1684"
			],
			[
				"country"=> "Andorra",
				"code"=> "+376"
			],
			[
				"country"=> "Angola",
				"code"=> "+244"
			],
			[
				"country"=> "Anguilla",
				"code"=> "+1264"
			],
			[
				"country"=> "Antigua and Barbuda",
				"code"=> "+1268"
			],
			[
				"country"=> "Argentina",
				"code"=> "+54"
			],
			[
				"country"=> "Armenia",
				"code"=> "+374"
			],
			[
				"country"=> "Aruba",
				"code"=> "+297"
			],
			[
				"country"=> "Ascension",
				"code"=> "+247"
			],
			[
				"country"=> "Australia",
				"code"=> "+61"
			],
			[
				"country"=> "Australian Antarctic Territory",
				"code"=> "+6721"
			],
			[
				"country"=> "Australian External Territories",
				"code"=> "+672"
			],
			[
				"country"=> "Austria",
				"code"=> "+43"
			],
			[
				"country"=> "Azerbaijan",
				"code"=> "+994"
			],
			[
				"country"=> "Bahamas",
				"code"=> "+1242"
			],
			[
				"country"=> "Bahrain",
				"code"=> "+973"
			],
			[
				"country"=> "Bangladesh",
				"code"=> "+880"
			],
			[
				"country"=> "Barbados",
				"code"=> "+1246"
			],
			[
				"country"=> "Barbuda",
				"code"=> "+1268"
			],
			[
				"country"=> "Belarus",
				"code"=> "+375"
			],
			[
				"country"=> "Belgium",
				"code"=> "+32"
			],
			[
				"country"=> "Belize",
				"code"=> "+501"
			],
			[
				"country"=> "Benin",
				"code"=> "+229"
			],
			[
				"country"=> "Bermuda",
				"code"=> "+1441"
			],
			[
				"country"=> "Bhutan",
				"code"=> "+975"
			],
			[
				"country"=> "Bolivia",
				"code"=> "+591"
			],
			[
				"country"=> "Bonaire",
				"code"=> "+5997"
			],
			[
				"country"=> "Bosnia and Herzegovina",
				"code"=> "+387"
			],
			[
				"country"=> "Botswana",
				"code"=> "+267"
			],
			[
				"country"=> "Brazil",
				"code"=> "+55"
			],
			[
				"country"=> "British Indian Ocean Territory",
				"code"=> "+246"
			],
			[
				"country"=> "British Virgin Islands",
				"code"=> "+1284"
			],
			[
				"country"=> "Brunei Darussalam",
				"code"=> "+673"
			],
			[
				"country"=> "Bulgaria",
				"code"=> "+359"
			],
			[
				"country"=> "Burkina Faso",
				"code"=> "+226"
			],
			[
				"country"=> "Burundi",
				"code"=> "+257"
			],
			[
				"country"=> "Cape Verde",
				"code"=> "+238"
			],
			[
				"country"=> "Cambodia",
				"code"=> "+855"
			],
			[
				"country"=> "Cameroon",
				"code"=> "+237"
			],
			[
				"country"=> "Canada",
				"code"=> "+1"
			],
			[
				"country"=> "Caribbean Netherlands",
				"code"=> "+5993"
			],
			[
				"country"=> "Caribbean Netherlands",
				"code"=> "+5994"
			],
			[
				"country"=> "Caribbean Netherlands",
				"code"=> "+5997"
			],
			[
				"country"=> "Cayman Islands",
				"code"=> "+1345"
			],
			[
				"country"=> "Central African Republic",
				"code"=> "+236"
			],
			[
				"country"=> "Chad",
				"code"=> "+235"
			],
			[
				"country"=> "Chatham Island, New Zealand",
				"code"=> "+64"
			],
			[
				"country"=> "Chile",
				"code"=> "+56"
			],
			[
				"country"=> "China",
				"code"=> "+86"
			],
			[
				"country"=> "Christmas Island",
				"code"=> "+6189164"
			],
			[
				"country"=> "Cocos (Keeling) Islands",
				"code"=> "+6189162"
			],
			[
				"country"=> "Colombia",
				"code"=> "+57"
			],
			[
				"country"=> "Comoros",
				"code"=> "+269"
			],
			[
				"country"=> "Congo",
				"code"=> "+242"
			],
			[
				"country"=> "Congo, Democratic Republic of the (Zaire)",
				"code"=> "+243"
			],
			[
				"country"=> "Cook Islands",
				"code"=> "+682"
			],
			[
				"country"=> "Costa Rica",
				"code"=> "+506"
			],
			[
				"country"=> "Ivory Coast",
				"code"=> "+225"
			],
			[
				"country"=> "Croatia",
				"code"=> "+385"
			],
			[
				"country"=> "Cuba",
				"code"=> "+53"
			],
			[
				"country"=> "Curaçao",
				"code"=> "+5999"
			],
			[
				"country"=> "Cyprus",
				"code"=> "+357"
			],
			[
				"country"=> "Czech Republic",
				"code"=> "+420"
			],
			[
				"country"=> "Denmark",
				"code"=> "+45"
			],
			[
				"country"=> "Diego Garcia",
				"code"=> "+246"
			],
			[
				"country"=> "Djibouti",
				"code"=> "+253"
			],
			[
				"country"=> "Dominica",
				"code"=> "+1767"
			],
			[
				"country"=> "Dominican Republic",
				"code"=> "+1809"
			],
			[
				"country"=> "Dominican Republic",
				"code"=> "+1829"
			],
			[
				"country"=> "Dominican Republic",
				"code"=> "+1849"
			],
			[
				"country"=> "Easter Island",
				"code"=> "+56"
			],
			[
				"country"=> "Ecuador",
				"code"=> "+593"
			],
			[
				"country"=> "Egypt",
				"code"=> "+20"
			],
			[
				"country"=> "El Salvador",
				"code"=> "+503"
			],
			[
				"country"=> "Ellipso (Mobile Satellite service)",
				"code"=> "+8812"
			],
			[
				"country"=> "Ellipso (Mobile Satellite service)",
				"code"=> "+8813"
			],
			[
				"country"=> "EMSAT (Mobile Satellite service)",
				"code"=> "+88213"
			],
			[
				"country"=> "Equatorial Guinea",
				"code"=> "+240"
			],
			[
				"country"=> "Eritrea",
				"code"=> "+291"
			],
			[
				"country"=> "Estonia",
				"code"=> "+372"
			],
			[
				"country"=> "eSwatini",
				"code"=> "+268"
			],
			[
				"country"=> "Ethiopia",
				"code"=> "+251"
			],
			[
				"country"=> "Falkland Islands",
				"code"=> "+500"
			],
			[
				"country"=> "Faroe Islands",
				"code"=> "+298"
			],
			[
				"country"=> "Fiji",
				"code"=> "+679"
			],
			[
				"country"=> "Finland",
				"code"=> "+358"
			],
			[
				"country"=> "France",
				"code"=> "+33"
			],
			[
				"country"=> "French Antilles",
				"code"=> "+596"
			],
			[
				"country"=> "French Guiana",
				"code"=> "+594"
			],
			[
				"country"=> "French Polynesia",
				"code"=> "+689"
			],
			[
				"country"=> "Gabon",
				"code"=> "+241"
			],
			[
				"country"=> "Gambia",
				"code"=> "+220"
			],
			[
				"country"=> "Georgia",
				"code"=> "+995"
			],
			[
				"country"=> "Germany",
				"code"=> "+49"
			],
			[
				"country"=> "Ghana",
				"code"=> "+233"
			],
			[
				"country"=> "Gibraltar",
				"code"=> "+350"
			],
			[
				"country"=> "Global Mobile Satellite System (GMSS)",
				"code"=> "+881"
			],
			[
				"country"=> "Globalstar (Mobile Satellite Service)",
				"code"=> "+8818"
			],
			[
				"country"=> "Globalstar (Mobile Satellite Service)",
				"code"=> "+8819"
			],
			[
				"country"=> "Greece",
				"code"=> "+30"
			],
			[
				"country"=> "Greenland",
				"code"=> "+299"
			],
			[
				"country"=> "Grenada",
				"code"=> "+1473"
			],
			[
				"country"=> "Guadeloupe",
				"code"=> "+590"
			],
			[
				"country"=> "Guam",
				"code"=> "+1671"
			],
			[
				"country"=> "Guatemala",
				"code"=> "+502"
			],
			[
				"country"=> "Guernsey",
				"code"=> "+441481"
			],
			[
				"country"=> "Guernsey",
				"code"=> "+447781,"
			],
			[
				"country"=> "Guernsey",
				"code"=> "+447839"
			],
			[
				"country"=> "Guernsey",
				"code"=> "+447911"
			],
			[
				"country"=> "Guinea",
				"code"=> "+224"
			],
			[
				"country"=> "Guinea-Bissau",
				"code"=> "+245"
			],
			[
				"country"=> "Guyana",
				"code"=> "+592"
			],
			[
				"country"=> "Haiti",
				"code"=> "+509"
			],
			[
				"country"=> "Honduras",
				"code"=> "+504"
			],
			[
				"country"=> "Hong Kong",
				"code"=> "+852"
			],
			[
				"country"=> "Hungary",
				"code"=> "+36"
			],
			[
				"country"=> "Iceland",
				"code"=> "+354"
			],
			[
				"country"=> "ICO Global (Mobile Satellite Service)",
				"code"=> "+8810"
			],
			[
				"country"=> "ICO Global (Mobile Satellite Service)",
				"code"=> "+8811"
			],
			[
				"country"=> "India",
				"code"=> "+91"
			],
			[
				"country"=> "Indonesia",
				"code"=> "+62"
			],
			[
				"country"=> "Inmarsat SNAC",
				"code"=> "+870"
			],
			[
				"country"=> "International Freephone Service (UIFN)",
				"code"=> "+800"
			],
			[
				"country"=> "International Networks",
				"code"=> "+882"
			],
			[
				"country"=> "International Networks",
				"code"=> "+883"
			],
			[
				"country"=> "International Premium Rate Service",
				"code"=> "+979"
			],
			[
				"country"=> "International Shared Cost Service (ISCS)",
				"code"=> "+808"
			],
			[
				"country"=> "Iran",
				"code"=> "+98"
			],
			[
				"country"=> "Iraq",
				"code"=> "+964"
			],
			[
				"country"=> "Ireland",
				"code"=> "+353"
			],
			[
				"country"=> "Iridium (Mobile Satellite service)",
				"code"=> "+8816"
			],
			[
				"country"=> "Iridium (Mobile Satellite service)",
				"code"=> "+8817"
			],
			[
				"country"=> "Isle of Man",
				"code"=> "+441624"
			],
			[
				"country"=> "Isle of Man",
				"code"=> "+447524"
			],
			[
				"country"=> "Isle of Man",
				"code"=> "+447624"
			],
			[
				"country"=> "Isle of Man",
				"code"=> "+447924"
			],
			[
				"country"=> "Israel",
				"code"=> "+972"
			],
			[
				"country"=> "Italy",
				"code"=> "+39"
			],
			[
				"country"=> "Jamaica",
				"code"=> "+1876"
			],
			[
				"country"=> "Jan Mayen",
				"code"=> "+4779"
			],
			[
				"country"=> "Japan",
				"code"=> "+81"
			],
			[
				"country"=> "Jersey",
				"code"=> "+441534"
			],
			[
				"country"=> "Jordan",
				"code"=> "+962"
			],
			[
				"country"=> "Kazakhstan",
				"code"=> "+76"
			],
			[
				"country"=> "Kazakhstan",
				"code"=> "+77"
			],
			[
				"country"=> "Kenya",
				"code"=> "+254"
			],
			[
				"country"=> "Kiribati",
				"code"=> "+686"
			],
			[
				"country"=> "Korea, North",
				"code"=> "+850"
			],
			[
				"country"=> "Korea, South",
				"code"=> "+82"
			],
			[
				"country"=> "Kosovo",
				"code"=> "+383"
			],
			[
				"country"=> "Kuwait",
				"code"=> "+965"
			],
			[
				"country"=> "Kyrgyzstan",
				"code"=> "+996"
			],
			[
				"country"=> "Laos",
				"code"=> "+856"
			],
			[
				"country"=> "Latvia",
				"code"=> "+371"
			],
			[
				"country"=> "Lebanon",
				"code"=> "+961"
			],
			[
				"country"=> "Lesotho",
				"code"=> "+266"
			],
			[
				"country"=> "Liberia",
				"code"=> "+231"
			],
			[
				"country"=> "Libya",
				"code"=> "+218"
			],
			[
				"country"=> "Liechtenstein",
				"code"=> "+423"
			],
			[
				"country"=> "Lithuania",
				"code"=> "+370"
			],
			[
				"country"=> "Luxembourg",
				"code"=> "+352"
			],
			[
				"country"=> "Macau",
				"code"=> "+853"
			],
			[
				"country"=> "Madagascar",
				"code"=> "+261"
			],
			[
				"country"=> "Malawi",
				"code"=> "+265"
			],
			[
				"country"=> "Malaysia",
				"code"=> "+60"
			],
			[
				"country"=> "Maldives",
				"code"=> "+960"
			],
			[
				"country"=> "Mali",
				"code"=> "+223"
			],
			[
				"country"=> "Malta",
				"code"=> "+356"
			],
			[
				"country"=> "Marshall Islands",
				"code"=> "+692"
			],
			[
				"country"=> "Martinique",
				"code"=> "+596"
			],
			[
				"country"=> "Mauritania",
				"code"=> "+222"
			],
			[
				"country"=> "Mauritius",
				"code"=> "+230"
			],
			[
				"country"=> "Mayotte",
				"code"=> "+262269"
			],
			[
				"country"=> "Mayotte",
				"code"=> "+262639"
			],
			[
				"country"=> "Mexico",
				"code"=> "+52"
			],
			[
				"country"=> "Micronesia, Federated States of",
				"code"=> "+691"
			],
			[
				"country"=> "Midway Island, USA",
				"code"=> "+1808"
			],
			[
				"country"=> "Moldova",
				"code"=> "+373"
			],
			[
				"country"=> "Monaco",
				"code"=> "+377"
			],
			[
				"country"=> "Mongolia",
				"code"=> "+976"
			],
			[
				"country"=> "Montenegro",
				"code"=> "+382"
			],
			[
				"country"=> "Montserrat",
				"code"=> "+1664"
			],
			[
				"country"=> "Morocco",
				"code"=> "+212"
			],
			[
				"country"=> "Mozambique",
				"code"=> "+258"
			],
			[
				"country"=> "Myanmar",
				"code"=> "+95"
			],
			[
				"country"=> "Nagorno-Karabakh",
				"code"=> "+37447"
			],
			[
				"country"=> "Nagorno-Karabakh",
				"code"=> "+37497"
			],
			[
				"country"=> "Namibia",
				"code"=> "+264"
			],
			[
				"country"=> "Nauru",
				"code"=> "+674"
			],
			[
				"country"=> "Nepal",
				"code"=> "+977"
			],
			[
				"country"=> "Netherlands",
				"code"=> "+31"
			],
			[
				"country"=> "Nevis",
				"code"=> "+1869"
			],
			[
				"country"=> "New Caledonia",
				"code"=> "+687"
			],
			[
				"country"=> "New Zealand",
				"code"=> "+64"
			],
			[
				"country"=> "Nicaragua",
				"code"=> "+505"
			],
			[
				"country"=> "Niger",
				"code"=> "+227"
			],
			[
				"country"=> "Nigeria",
				"code"=> "+234"
			],
			[
				"country"=> "Niue",
				"code"=> "+683"
			],
			[
				"country"=> "Norfolk Island",
				"code"=> "+6723"
			],
			[
				"country"=> "North Macedonia",
				"code"=> "+389"
			],
			[
				"country"=> "Northern Cyprus",
				"code"=> "+90392"
			],
			[
				"country"=> "Northern Ireland",
				"code"=> "+4428"
			],
			[
				"country"=> "Northern Mariana Islands",
				"code"=> "+1670"
			],
			[
				"country"=> "Norway",
				"code"=> "+47"
			],
			[
				"country"=> "Oman",
				"code"=> "+968"
			],
			[
				"country"=> "Pakistan",
				"code"=> "+92"
			],
			[
				"country"=> "Palau",
				"code"=> "+680"
			],
			[
				"country"=> "Palestine, State of",
				"code"=> "+970"
			],
			[
				"country"=> "Panama",
				"code"=> "+507"
			],
			[
				"country"=> "Papua New Guinea",
				"code"=> "+675"
			],
			[
				"country"=> "Paraguay",
				"code"=> "+595"
			],
			[
				"country"=> "Peru",
				"code"=> "+51"
			],
			[
				"country"=> "Philippines",
				"code"=> "+63"
			],
			[
				"country"=> "Pitcairn Islands",
				"code"=> "+64"
			],
			[
				"country"=> "Poland",
				"code"=> "+48"
			],
			[
				"country"=> "Portugal",
				"code"=> "+351"
			],
			[
				"country"=> "Puerto Rico",
				"code"=> "+1787"
			],
			[
				"country"=> "Puerto Rico",
				"code"=> "+1939"
			],
			[
				"country"=> "Qatar",
				"code"=> "+974"
			],
			[
				"country"=> "Réunion",
				"code"=> "+262"
			],
			[
				"country"=> "Romania",
				"code"=> "+40"
			],
			[
				"country"=> "Russia",
				"code"=> "+7"
			],
			[
				"country"=> "Rwanda",
				"code"=> "+250"
			],
			[
				"country"=> "Saba",
				"code"=> "+5994"
			],
			[
				"country"=> "Saint Barthélemy",
				"code"=> "+590"
			],
			[
				"country"=> "Saint Helena",
				"code"=> "+290"
			],
			[
				"country"=> "Saint Kitts and Nevis",
				"code"=> "+1869"
			],
			[
				"country"=> "Saint Lucia",
				"code"=> "+1758"
			],
			[
				"country"=> "Saint Martin (France)",
				"code"=> "+590"
			],
			[
				"country"=> "Saint Pierre and Miquelon",
				"code"=> "+508"
			],
			[
				"country"=> "Saint Vincent and the Grenadines",
				"code"=> "+1784"
			],
			[
				"country"=> "Samoa",
				"code"=> "+685"
			],
			[
				"country"=> "San Marino",
				"code"=> "+378"
			],
			[
				"country"=> "São Tomé and Príncipe",
				"code"=> "+239"
			],
			[
				"country"=> "Saudi Arabia",
				"code"=> "+966"
			],
			[
				"country"=> "Senegal",
				"code"=> "+221"
			],
			[
				"country"=> "Serbia",
				"code"=> "+381"
			],
			[
				"country"=> "Seychelles",
				"code"=> "+248"
			],
			[
				"country"=> "Sierra Leone",
				"code"=> "+232"
			],
			[
				"country"=> "Singapore",
				"code"=> "+65"
			],
			[
				"country"=> "Sint Eustatius",
				"code"=> "+5993"
			],
			[
				"country"=> "Sint Maarten (Netherlands)",
				"code"=> "+1721"
			],
			[
				"country"=> "Slovakia",
				"code"=> "+421"
			],
			[
				"country"=> "Slovenia",
				"code"=> "+386"
			],
			[
				"country"=> "Solomon Islands",
				"code"=> "+677"
			],
			[
				"country"=> "Somalia",
				"code"=> "+252"
			],
			[
				"country"=> "South Africa",
				"code"=> "+27"
			],
			[
				"country"=> "South Georgia and the South Sandwich Islands",
				"code"=> "+500"
			],
			[
				"country"=> "South Ossetia",
				"code"=> "+99534"
			],
			[
				"country"=> "South Sudan",
				"code"=> "+211"
			],
			[
				"country"=> "Spain",
				"code"=> "+34"
			],
			[
				"country"=> "Sri Lanka",
				"code"=> "+94"
			],
			[
				"country"=> "Sudan",
				"code"=> "+249"
			],
			[
				"country"=> "Suriname",
				"code"=> "+597"
			],
			[
				"country"=> "Svalbard",
				"code"=> "+4779"
			],
			[
				"country"=> "Sweden",
				"code"=> "+46"
			],
			[
				"country"=> "Switzerland",
				"code"=> "+41"
			],
			[
				"country"=> "Syria",
				"code"=> "+963"
			],
			[
				"country"=> "Taiwan",
				"code"=> "+886"
			],
			[
				"country"=> "Tajikistan",
				"code"=> "+992"
			],
			[
				"country"=> "Tanzania",
				"code"=> "+255"
			],
			[
				"country"=> "Telecommunications for Disaster Relief by OCHA",
				"code"=> "+888"
			],
			[
				"country"=> "Thailand",
				"code"=> "+66"
			],
			[
				"country"=> "Thuraya (Mobile Satellite service)",
				"code"=> "+88216"
			],
			[
				"country"=> "East Timor",
				"code"=> "+670"
			],
			[
				"country"=> "Togo",
				"code"=> "+228"
			],
			[
				"country"=> "Tokelau",
				"code"=> "+690"
			],
			[
				"country"=> "Tonga",
				"code"=> "+676"
			],
			[
				"country"=> "Transnistria",
				"code"=> "+3732"
			],
			[
				"country"=> "Transnistria",
				"code"=> "+3735"
			],
			[
				"country"=> "Trinidad and Tobago",
				"code"=> "+1868"
			],
			[
				"country"=> "Tristan da Cunha",
				"code"=> "+2908"
			],
			[
				"country"=> "Tunisia",
				"code"=> "+216"
			],
			[
				"country"=> "Turkey",
				"code"=> "+90"
			],
			[
				"country"=> "Turkmenistan",
				"code"=> "+993"
			],
			[
				"country"=> "Turks and Caicos Islands",
				"code"=> "+1649"
			],
			[
				"country"=> "Tuvalu",
				"code"=> "+688"
			],
			[
				"country"=> "Uganda",
				"code"=> "+256"
			],
			[
				"country"=> "Ukraine",
				"code"=> "+380"
			],
			[
				"country"=> "United Arab Emirates",
				"code"=> "+971"
			],
			[
				"country"=> "United Kingdom",
				"code"=> "+44"
			],
			[
				"country"=> "United States",
				"code"=> "+1"
			],
			[
				"country"=> "Universal Personal Telecommunications (UPT)",
				"code"=> "+878"
			],
			[
				"country"=> "Uruguay",
				"code"=> "+598"
			],
			[
				"country"=> "US Virgin Islands",
				"code"=> "+1340"
			],
			[
				"country"=> "Uzbekistan",
				"code"=> "+998"
			],
			[
				"country"=> "Vanuatu",
				"code"=> "+678"
			],
			[
				"country"=> "Vatican City State (Holy See)",
				"code"=> "+3906698"
			],
			[
				"country"=> "Venezuela",
				"code"=> "+58"
			],
			[
				"country"=> "Vietnam",
				"code"=> "+84"
			],
			[
				"country"=> "Wake Island, USA",
				"code"=> "+1808"
			],
			[
				"country"=> "Wallis and Futuna",
				"code"=> "+681"
			],
			[
				"country"=> "Yemen",
				"code"=> "+967"
			],
			[
				"country"=> "Zambia",
				"code"=> "+260"
			],
			[
				"country"=> "Zanzibar",
				"code"=> "+25524"
			],
			[
				"country"=> "Zimbabwe",
				"code"=> "+263"
			]
		];
	}
}