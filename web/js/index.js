$(document).ready(getPhones);

$( "#search-input" ).keyup(searchPhone);

function getPhones() 
{
	$.ajax({
		url: "../api/index.php/get-phones",
		success: printPhones,
		error: throwError,
		dataType: "json"
	});
	return false;
}

function printPhones(data) 
{
	let html = '';
	if(!data) getPhones();
	for(let i in data) 
	{
		html+='<tr>'; 
		html+='<th scope="row">'+data[i].id+'</th> ';	
		html+='<td>'+data[i].name+'</td>';	
		html+='<td>+'+data[i].prefix+'</td>';	 
		html+='<td>'+data[i].number+'</td>';
		html+='<td><button type="button" class="btn btn-default btn-sm" data-bs-toggle="modal" data-bs-target="#edit-modal" data-id="'+data[i].id+'" data-prefix="'+data[i].prefix+'" data-number="'+data[i].number+'" data-name="'+data[i].name+'"name="edit-modal">Edit</button></td>';
		html+='<td><button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-modal" data-id="'+data[i].id+'" data-prefix="'+data[i].prefix+'" data-number="'+data[i].number+'"name="delete-modal">Delete</button></td>';
		html+='</tr>';
	}
	$('#data-container').html(html);

			//Delete phone
			$('button[name="delete-modal"]').on("click", function(event) 
			{
				let id = $(this).data('id');
				let prefix = $(this).data('prefix');
				let number = $(this).data('number');
				let template = '';
				template +='<div class="modal-body">';
				template += 'You will delete this number<br>';
				template += '<span>+'+prefix+' '+number+'</span><br>';
				template += 'Are you sure?';
				template += '</div>';
				template+='<div class="modal-footer">'
				template+='<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>';
				template+='<button type="button" data-bs-dismiss="modal" onclick="deletePhone('+id+')" class="btn btn-danger">Yes</button></div>';

				$('#delete-modal-container').html(template);
			});

			//Edit phone
			$('button[name="edit-modal"]').on("click", function(event) 
			{
				let id = $(this).data('id');
				let prefix = $(this).data('prefix');
				let number = $(this).data('number');
				let name = $(this).data('name');

				//Set values to input
				$("#name-edit").val(name);
				$("#phone-edit").val("+"+prefix+" "+number);

				let template = '';

				template+='<div class="modal-footer">'
				template+='<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
				template+='<button type="button" data-bs-dismiss="modal" onclick="editPhone('+id+')" class="btn btn-primary">Save</button></div>';

				$('#edit-modal-container').html(template);
			});
		}

		function deletePhone(id)
		{
			$.ajax({
				url: "../api/index.php",
				method: "DELETE",
				contentType: "application/json",
				data: JSON.stringify({"id":id}),
				success: throwAlert,
				error: throwError,
				dataType: "json"
			});
		}

		function editPhone(id)
		{
			if($("#name-edit").val() === "" || $("#phone-edit").val() === "")
			{
				throwAlert("'Name' and 'Number' are required fields");
			}else{
				let body = {'id':id,'name': $("#name-edit").val(),'number': $("#phone-edit").val()};
				$.ajax({
					url: "../api/index.php",
					method: "PUT",
					contentType: "application/json",
					data: JSON.stringify(body),
					success: throwAlert,
					error: throwError,
					dataType: "json"
				});
			}
		}

		function searchPhone()
		{
			let input = $( "#search-input" ).val();
			$.ajax({
				url: "../api/index.php/search-phone?number="+input,
				success: printPhones,
				error: throwError,
				dataType: "json"
			});
		}

		function savePhone()
		{
			if($("#name-create").val() === "" || $("#phone-create").val() === "")
			{
				throwAlert("'Name' and 'Number' are required fields");
			}else{
				let body = {'name': $("#name-create").val(),'number': $("#phone-create").val()};
				$.ajax({
					url: "../api/index.php",
					method: "POST",
					contentType: "application/json",
					data: JSON.stringify(body),
					success: throwAlert,
					error: throwError,
					dataType: "json"
				});
				$("#create-form")[0].reset();
			}
		}

		function throwAlert(data)
		{
			let _alertService = new AlertService;
			_alertService.show(data);
			getPhones();
		}

		function throwError(err)
		{
			let _alertService = new AlertService;
			console.log(err);
			switch(err.status)
			{
				case 500:
				_alertService.show('Server error, code: 500');
				break;

				case 0:
				_alertService.show('Check your internet connection, code: 0');
				break;

				default:
				_alertService.error(err);
				break;
			}
		}