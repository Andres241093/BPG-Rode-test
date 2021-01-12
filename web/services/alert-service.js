class AlertService{

	show(mssg)
	{
		$('#toast').toast('show'); 
		$('.toast-body').html(mssg);
	}
	error(err)
	{
		$('#toast').toast('show'); 
		$('.toast-body').html(err.responseJSON);
	}

}