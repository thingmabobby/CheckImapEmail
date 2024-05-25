<?php

require_once('CheckImapEmail.php');
$checkemail = new CheckImapEmail;

/* show all emails test */
$messages = $checkemail->checkEmail();

// if an error was returned then show the error, otherwise loop through the messages
if ($checkemail->error) {
	echo "Error(s): " . $messages;
}
else {
	if ($messages) {
		echo "<h2>Emails found: " . $checkemail->msg_count . "</h2>";
		
		// loop through the emails
		foreach ($messages as $message) {
			echo "
			Message #" . $message['message_number'] . "<br>
			Bid: " . $message['bid'] . "<br>
			Date: " . $message['date'] . "<br>
			From: " . $message['from'] . " (" . $message['fromaddress'] . ")<br>
			Unread? " . $message['unseen'] . "<br>
			Subject: " . $message['subject'] . "<br>
			Body:<br>" . $message['message_body'] . "<br><br>";		
		}
		
		if ($checkemail->lastuid > 0) {
			echo "The last UID was " . $checkemail->lastuid;
		}
	}
	else {
		echo "No messages found.";
	}
}

?>