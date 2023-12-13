SyncRecordsAcrossProjects = {};

SyncRecordsAcrossProjects.ajax_endpoint = "AJAX_ENDPOINT";

SyncRecordsAcrossProjects.ajax_complete = function(data, status, xhr) {
	console.log("ajax completed", {data: data, status: status, xhr: xhr});
	$(".sync_all_loader").css('display', 'none');
	$("button#sync_all_records").attr('disabled', false);
	
	if (status == 'success' && data.responseJSON && data.responseJSON['success'] == true) {
		window.location.reload();
	} else {
		if (data.responseJSON && data.responseJSON['error']) {
			alert(data.responseJSON['error']);
		} else {
			alert("The Sync Records Across Projects module failed to get a response for your action. Please contact a REDCap administrator or the author of this module.");
		}
	}
}

SyncRecordsAcrossProjects.addButtonAfterJqueryLoaded = function() {
	if (typeof($) != 'undefined') {
		// wait 
		$(function() {
			$("form#dashboard-config + div").append("<button id='sync_all_records' class='btn btn-xs btn-rcpurple fs13'><div class='sync_all_loader'></div>Sync All Records</button>");
			
			$("body").on("click", "button#sync_all_records", function(event) {
				// show spinning loader icon and disabled pipe button
				$(".sync_all_loader").css('display', 'inline-block');
				$("button#sync_all_records").attr('disabled', true);
				
				// send ajax request to sync_all_records endpoint
				$.get({
					url: SyncRecordsAcrossProjects.ajax_endpoint,
					complete: SyncRecordsAcrossProjects.ajax_complete,
				});
			});
		});
	} else {
		setTimeout(SyncRecordsAcrossProjects.addButtonAfterJqueryLoaded, 100);
	}
}

SyncRecordsAcrossProjects.addButtonAfterJqueryLoaded();