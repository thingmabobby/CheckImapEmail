<?php

/*
// This is a class to pull emails from a mailbox using IMAP. You can probably safely ignore the "bid" you'll see. 
// It's something I was testing to be able to organize emails by subject if it found a string of #N (n = a number). Kinda like how ticketing systems work.
// I was testing to see if I could write a script to analyze incoming email messages as a cron job and save them to a database and associate them with specific items.

// Usage:
//
// Instantiating the class requires connection information located in the class found in CheckImapConnection.php 
//
// There are 3 methods available and they all return data in an array unless the error property is set to true (then it returns an error string):
//
// 	checkEmail() 
//		- this in theory should return all of your emails, but I haven't fully tested it in a large inbox.
//  
//	checkSinceDate($thedate) 
//		- this will search for emails since the given date from the mailbox. $thedate needs to be in a string of date format: "d M Y" (e.g. 24 May 2024)
//
//	checkSinceLastUID($uid)
//		- this will search for emails since the last specified email number (UID) - ideally you would save whatever the last UID was so you can lookup the new emails the next time using that value
//
//
//	Public Properties:
//		error - true/false if there was an error
//		msg_count - total emails that were found
//		lastuid - if searching by last UID it will return the last UID found so you can store it and refer to it in your next search to pull the latest emails since the last time you searched
//		messages - associative array containing the email messages found with the following fields:
//			-> subject - the email subject
//			-> message_body - the body of the email
//			-> fromaddress - the email address that sent the email
//			-> from - the friendly name (e.g. Jon Doe)
//			-> message_number - the unique ID of the message (UID) from the mailbox
//			-> date - the date and time with UTC difference the message was sent (e.g. Fri, 24 May 2024 15:53:38 -0400)
*/

declare(strict_types=1);
require_once('CheckImapConnection.php');

class CheckImapEmail extends CheckImapConnection {	
	private $conn;
		
	public function __construct(
		public int $msg_count = 0,
		public int $lastuid = 0,
		public array $messages = [],
		public bool $error = false
	) {
		if (!$this->server || !$this->acct || !$this->pass) { return false; }
		
		if (!empty($this->port)) { $this->server .= ":" . $this->port; }
		
		$this->conn = @imap_open("{" . $this->server . "}", $this->acct, $this->pass);
		if (!$this->conn) { return $this->showError(imap_errors()); }
	}
	
	
	public function __destruct() {
		if (isset($this->conn)) { imap_close($this->conn); }
	}
	
	
	private function showError(array $error) {
		if ($error) {
			$errorstring = "";
			foreach ($error as $thiserror) {
				$errorstring .= $thiserror . "\n";
			}
			$this->error = true;
			return $errorstring;
		}
	}
	
	
	/*
	// this method will check the mailbox and return each email it finds as an array
	*/
	public function checkEmail() {	
		$this->msg_count = imap_num_msg($this->conn);
	
		for ($i = 1; $i <= $this->msg_count; $i++) {
			$header = imap_headerinfo($this->conn, $i);
		
			$this->messages[$i]['subject'] = $header->Subject;
			$this->messages[$i]['message_body'] = imap_fetchbody($this->conn, $i, "2");
			$this->messages[$i]['fromaddress'] = $header->sender[0]->mailbox . "@" . $header->sender[0]->host;
			$this->messages[$i]['from'] = $header->fromaddress;
			$this->messages[$i]['message_number'] = $header->Msgno;
			$this->messages[$i]['date'] = $header->date;	
			
			$thisbid = "n/a";
			if (property_exists($header,"Subject") && preg_match("/#(\d+)/", $header->Subject, $matches)) {
				$thisbid = $matches[0];
			}
			$this->messages[$i]['bid'] = $thisbid;
			
			$this->messages[$i]['unseen'] = $header->Unseen;			
		}
		
		return $this->messages;
	}
	
	
	/*
	// this method will search for emails since the given date from the mailbox and return each email it finds as an array
	// $thedate needs to be in a string of date format: "d M Y" (e.g. 24 May 2024)
	*/
	public function checkSinceDate(string $thedate) {
		if (!isset($thedate)) { return false; }
		
		$search = imap_search($this->conn, "SINCE \"" . $thedate . "\"", SE_UID); 
		
		if (!$search) { return false; }
		if (!is_array($search)) { return false; }
		if (count($search) == 0) { return false; }
		
		$this->msg_count = count($search);
		
		foreach ($search as $thismsg) {
			$header = imap_headerinfo($this->conn, $thismsg);
		
			$this->messages[$thismsg]['subject'] = $header->Subject;
			$this->messages[$thismsg]['message_body'] = imap_fetchbody($this->conn, $thismsg, "2");
			$this->messages[$thismsg]['fromaddress'] = $header->sender[0]->mailbox . "@" . $header->sender[0]->host;
			$this->messages[$thismsg]['from'] = $header->fromaddress;
			$this->messages[$thismsg]['message_number'] = $header->Msgno;
			$this->messages[$thismsg]['date'] = $header->date;	
			
			$thisbid = "n/a";
			if (property_exists($header,"Subject") && preg_match("/#(\d+)/", $header->Subject, $matches)) {
				$thisbid = $matches[0];
			}				
			$this->messages[$thismsg]['bid'] = $thisbid;
			
			$this->messages[$thismsg]['unseen'] = $header->Unseen;
		}
		
		return $this->messages;
	}
	
	
	/*
	// this method checks all emails since the last specified email number (UID) - ideally you would save whatever the last UID was so you can lookup the new emails the next time using that value
	*/
	public function checkSinceLastUID(int $uid) {
		if (!isset($uid)) { return false; }
		
		// grab the overview details from the mailbox starting from the specified message ID (UID)
		$search = imap_fetch_overview($this->conn, $uid . ":*", FT_UID); 
		
		if (!$search) { return false; }
		if (!is_array($search)) { return false; }
		if (count($search) == 0) { return false; }
			
		$this->msg_count = count($search);
		
		foreach ($search as $thisuid) {
			$thismsg = $thisuid->uid;
						
			$header = imap_headerinfo($this->conn, $thismsg);
			
			$this->messages[$thismsg]['subject'] = $header->Subject;
			$this->messages[$thismsg]['message_body'] = imap_fetchbody($this->conn, $thismsg, "2");
			$this->messages[$thismsg]['fromaddress'] = $header->sender[0]->mailbox . "@" . $header->sender[0]->host;
			$this->messages[$thismsg]['from'] = $header->fromaddress;
			$this->messages[$thismsg]['message_number'] = $header->Msgno;
			$this->messages[$thismsg]['date'] = $header->date;

			$thisbid = "n/a";
			if (property_exists($header,"Subject") && preg_match("/#(\d+)/", $header->Subject, $matches)) {
				$thisbid = $matches[0];
			}			
			$this->messages[$thismsg]['bid'] = $thisbid;			
			
			$this->messages[$thismsg]['unseen'] = $header->Unseen;
		}		
		$this->lastuid = $thismsg;		
		
		return $this->messages;
	}
}