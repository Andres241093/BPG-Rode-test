<?php 
require_once("connection.php");
require_once("phonebook.class.php");

$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];

$DB = new Database;

switch ($method) {
	case 'GET':

	if(isset($_GET['number']))
	{
		//Search number

		$DB->connectToDB();

		$phonebook = new Phonebook($_GET['number']);

		$data = $phonebook->searchPhone();

		echo json_encode($data);

	}else{
		//Get all phone numbers

		$DB->connectToDB();

		$phonebook = new Phonebook;

		$data = $phonebook->getPhones();

		echo json_encode($data);
	}

	break;

	case 'POST':
		//New phone number

		$body_request = json_decode(file_get_contents('php://input')); //Get body request and transform to Object

		//check if body request exist

		if($body_request){

			//check data of request
			if(isset($body_request->number) && isset($body_request->name))
			{
				//body request is fine
				$DB->connectToDB();

				$phonebook = new Phonebook($body_request->number,$body_request->name);

					$data = $phonebook->addPhone();
					
					echo $data;

			}else{
				http_response_code(422);
				echo json_encode('Data of request is missing or wrong');
			}			
		}else{
			http_response_code(422);
			echo json_encode('Body of request is missing');
		}

		break;

		case 'PUT':
		//Edit phone number

		$body_request = json_decode(file_get_contents('php://input')); //Get body request and transform to Object

		//check if body request exist

		if($body_request){

			//check data of request
			if(isset($body_request->id) && isset($body_request->number) && isset($body_request->name))
			{
				//body request is fine
				$DB->connectToDB();

				$phonebook = new Phonebook($body_request->number,$body_request->name);

				if($phonebook->processPhone($body_request->number) === 'invalid prefix')
				{
					http_response_code(422); //Unprocessable entity: the request is fine but the server cannot process it
					echo json_encode('Invalid prefix');

				}else{

					$data = $phonebook->updatePhone($body_request->id);

					http_response_code(200);//Created: the request fulfilled successfully
					echo json_encode('Phone edited successfully!');
				}

			}else{
				http_response_code(422);
				echo json_encode('Data of request is missing or wrong');
			}			
		}else{
			http_response_code(422);
			echo json_encode('Body of request is missing');
		}

		break;

		case 'DELETE':
		//Delete phone number

		$body_request = json_decode(file_get_contents('php://input')); //Get body request and transform to Object

		//check if body request exist

		if($body_request){

			//check data of request
			if(isset($body_request->id))
			{
				//body request is fine
				$DB->connectToDB();

				$phonebook = new Phonebook();

				$phonebook->unlinkPhone($body_request->id);

				http_response_code(200);//Created: the request fulfilled successfully
				echo json_encode('Phone deleted');
			}else{
				http_response_code(422);
				echo json_encode('Data of request is missing or wrong');
			}			
		}else{
			http_response_code(422);
			echo json_encode('Body of request is missing');
		}

		break;
		default:
		# code...
		break;
	}

	?>