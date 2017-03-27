jQuery( document ).ready( function( $ ) {
	$("#sefa-user-list").change( function() {
		$recipients = $("#sefa-recipient-emails");
		if ( $recipients.val() == '' ) {
			$recipients.val( $(this).find("option:selected").attr("value") );
		} else if ( $(this).val() != '' ) {
			$recipients.val( $recipients.val() + ',' + $(this).find("option:selected").attr("value") );
		}
		$(this).find("option:selected").attr("disabled","disabled");
    })
} );