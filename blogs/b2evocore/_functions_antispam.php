<?php
/*
 * b2evolution - http://b2evolution.net/
 *
 * Copyright (c) 2003 by Francois PLANQUE - http://fplanque.net/
 * Released under GNU GPL License - http://b2evolution.net/about/license.html
 */


/*
 * antispam_create(-)
 *
 * Insert a new abuse string into DB
 */
function antispam_create( $abuse_string )
{
	global $tableantispam, $querycount, $cache_antispam;
	
	// Cut the crap if the string is empty:
	$abuse_string = trim( $abuse_string );
	if( empty( $abuse_string ) ) return false;
	
	// Check if the string already is in the blacklist:
	if( antispam_url($abuse_string) ) return false;
	
	// Insert new string into DB:
	$sql ="INSERT INTO $tableantispam(domain) VALUES('$abuse_string')";
	$querycount++;
	mysql_query($sql) or mysql_oops( $sql );

	// Insert into cache:
	$cache_antispam[] = $abuse_string;

	return true;
}

/*
 * remove_ban(-)
 *
 * Remove a domain from the ban list
 */
function remove_ban( $string_ID )
{
	global $tableantispam, $querycount;

	$sql ="DELETE FROM $tableantispam WHERE ID = '$string_ID'";
	$querycount++;
	mysql_query($sql) or mysql_oops( $sql );
}


/*
 * list_antiSpam(-)
 *
 * Extract anti-spam
 */
function list_antiSpam()
{
	global 	$querycount, $tableantispam, $res_stats;

	$sql = "SELECT * FROM $tableantispam ORDER BY domain ASC";
	$res_stats = mysql_query( $sql ) or mysql_oops( $sql );
	$querycount++;
}

/*
 * antiSpam_ID(-)
 */
function antiSpam_ID()
{
	global $row_stats;
	echo $row_stats['ID'];
}

/*
 * antiSpam_domain(-)
 */
function antiSpam_domain()
{
	global $row_stats;
	echo $row_stats['domain'];
}

/*
 * keyword_ban(-)
 *
 * Ban any URL containing a certain keyword
 */
function keyword_ban( $keyword )
{
	global $tablehitlog, $tablecomments, $querycount, $deluxe_ban, $auto_report_abuse;

	// Cut the crap if the string is empty:
	$keyword = trim( $keyword );
	if( empty( $keyword ) ) return false;

	echo '<div class="panelinfo">';
	printf( '<p>'.T_('Banning the keyword %s...').'</p>', $keyword);

	// Insert into DB:
	antispam_create( $keyword );
		
	if ( $deluxe_ban )
	{ // Delete all banned comments and stats entries
		echo '<p>'.T_('Removing all related comments and hits...').'</p>';
		// Stats entries first
		$sql ="DELETE FROM $tablehitlog WHERE baseDomain LIKE '%$keyword%'";	// This is quite drastic!
		$querycount++;
		mysql_query($sql) or mysql_oops( $sql );
		
		// Then comments
		$sql ="DELETE FROM $tablecomments WHERE comment_author_url LIKE '%$keyword%'";	// This is quite drastic!
		$querycount++;
		mysql_query($sql) or mysql_oops( $sql );
	}
	
	echo '</div>';
	
	// Report this keyword as abuse:
	if( $auto_report_abuse )
	{
		b2evonet_report_abuse( $keyword );
	}
}


/*
 * ban_affected_hits(-)
 */
function ban_affected_hits($banned, $type)
{
	global  $querycount, $tablehitlog, $res_affected_hits;

	switch( $type )
	{
		case "hit_ID":
			$domain = get_domain_from_hit_ID($banned);
			$sql = "SELECT * FROM $tablehitlog WHERE baseDomain = '$domain' ORDER BY baseDomain ASC";
			break;
		case "keyword":
		default:
			// Assume it's a keyword
			$sql = "SELECT * FROM $tablehitlog WHERE baseDomain LIKE '%$banned%' ORDER BY baseDomain ASC";
			break;
	}
	$res_affected_hits = mysql_query( $sql ) or mysql_oops( $sql );
	$querycount++;
}

/*
 * ban_affected_comments(-)
 */
function ban_affected_comments($banned, $type)
{
	global  $querycount, $tablecomments, $res_affected_comments;

	switch( $type )
	{
		case "hit_ID":
			$domain = get_domain_from_hit_ID($banned);
			$sql = "SELECT comment_author, comment_author_url, comment_date, comment_content FROM $tablecomments WHERE comment_author_url LIKE '%$domain%' ORDER BY comment_date ASC";
			break;
		case "keyword":
		default:
			// Assume it's a keyword
			$sql = "SELECT comment_author, comment_author_url, comment_date, comment_content FROM $tablecomments WHERE comment_author_url LIKE '%$banned%' ORDER BY comment_date ASC";
			break;
	}
	$res_affected_comments = mysql_query( $sql ) or mysql_oops( $sql );
	$querycount++;
}


// -------------------- XML-RPC callers ---------------------------

/*
 * b2evonet_report_abuse(-)
 *
 * pings b2evolution.net to report abuse from a particular domain
 */
function b2evonet_report_abuse( $abuse_string, $display = true ) 
{
	$test = 0;

	global $baseurl;
	if( $display )
	{	
		echo "<div class=\"panelinfo\">\n";
		echo '<h3>', T_('Reporting abuse to b2evolution.net...'), "</h3>\n";
	}
	if( !preg_match( '#^http://localhost[/:]#', $baseurl) || $test ) 
	{
		// Construct XML-RPC client:
		if( $test == 2 )
		{
		 	$client = new xmlrpc_client('/b2evolution/blogs/evonetsrv/xmlrpc.php', 'localhost', 8088);
			$client->debug = 1;
		}
		else
		{
			$client = new xmlrpc_client('/evonetsrv/xmlrpc.php', 'b2evolution.net', 80);
			// $client->debug = 1;
		}
		
		// Construct XML-RPC message:
		$message = new xmlrpcmsg( 
									'b2evo.reportabuse',	 											// Function to be called
									array( 
										new xmlrpcval(0,'int'),										// Reserved
										new xmlrpcval('annonymous','string'),			// Reserved
										new xmlrpcval('nopassrequired','string'),	// Reserved
										new xmlrpcval($abuse_string,'string'),		// The abusive string to report
										new xmlrpcval($baseurl,'string')					// The base URL of this b2evo
									)  
								);
		$result = $client->send($message);
		$ret = xmlrpc_displayresult( $result );

		if( $display ) echo '<p>', T_('Done.'), "</p>\n</div>\n";
		return($ret);
	} 
	else 
	{
		if( $display ) echo "<p>", T_('Aborted (Running on localhost).'), "</p>\n</div>\n";
		return(false);
	}
}


/*
 * b2evonet_poll_abuse(-)
 *
 * request abuse list from central blacklist
 */
function b2evonet_poll_abuse( $display = true ) 
{
	$test = 0;

	global $baseurl;
	if( $display )
	{	
		echo "<div class=\"panelinfo\">\n";
		echo '<h3>', T_('Requesting abuse list from b2evolution.net...'), "</h3>\n";
	}

	// Construct XML-RPC client:
	if( $test == 2 )
	{
		$client = new xmlrpc_client('/b2evolution/blogs/evonetsrv/xmlrpc.php', 'localhost', 8088);
		$client->debug = 1;
	}
	else
	{
		$client = new xmlrpc_client('/evonetsrv/xmlrpc.php', 'b2evolution.net', 80);
		$client->debug = 1;
	}
	
	// Construct XML-RPC message:
	$message = new xmlrpcmsg( 
								'b2evo.pollabuse',	 											// Function to be called
								array( 
									new xmlrpcval(0,'int'),										// Reserved
									new xmlrpcval('annonymous','string'),			// Reserved
									new xmlrpcval('nopassrequired','string')	// Reserved
								)  
							);
	$result = $client->send($message);
	
	if( $ret = xmlrpc_displayresult( $result ) )
	{	// Response is not an error, let's process it:
		$value = xmlrpc_decode($result->value());
		if (is_array($value))
		{	// We got an array of strings:
			echo '<p>Adding strings to local blacklist:</p><ul>';
			foreach($value as $banned_string)
			{
				echo '<li>Adding: [', $banned_string, '] : ';
				echo antispam_create( $banned_string ) ? 'OK.' : 'Not necessary! (Already handled)';
				echo '</li>';
			}
			echo '</ul>';
		}
		else
		{
			echo T_('Invalid reponse.')."\n";
			$ret = false;
		}
	}

	if( $display ) echo '<p>', T_('Done.'), "</p>\n</div>\n";
	return($ret);
}


?>