<?php
// #############################################################################
/**
 * Prints a row containing a number of <input type="checkbox" /> fields representing a available languages
 *
 * @param	string	Title for row
 * @param	string	Base name for checkboxes - $name[]
 * @param	array	Destination language array
 * @param	array	Selected destination language ids (separed by commas)
 * @param	integer	Number of columns to split checkboxes into
 */
function print_dstlanguage_row($title, $name = 'dstlanguageid', $languagearray, $selected = '', $columns = 0)
{
	global $vbulletin, $db;

	$uniqueid = fetch_uniqueid_counter();

	if (!is_array($languagearray) OR empty($languagearray))
	{
		// get all languages
		$languagearray = array();

		$languages = $db->query_read("SELECT title, languageid FROM " . TABLE_PREFIX . "language ORDER BY languagecode");
		while ($language = $db->fetch_array($languages))
		{
			$languagearray["$language[languageid]"] = $language['title'];
		}
		unset($language);
		$db->free_result($languages);
	}

	$options = array();
	foreach($languagearray AS $languageid => $languagetitle)
	{
		$options[] = "\t\t<div><label for=\"$name{$languageid}_$uniqueid\" title=\"usergroupid: $languageid\"><input type=\"checkbox\" tabindex=\"1\" name=\"$name"."[]\" id=\"$name{$languageid}_$uniqueid\" value=\"$languageid\"" . iif(strpos(',' . implode(',', $selected) . ',', ",$languageid,") !== false, ' checked="checked"') . iif($vbulletin->debug, " title=\"name=&quot;$name&quot;\"") . " />$languagetitle</label></div>\n";
	}

	$class = fetch_row_bgclass();
	if ($columns > 1)
	{
		$html = "\n\t<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr valign=\"top\">\n";
		$counter = 0;
		$totaloptions = sizeof($options);
		$percolumn = ceil($totaloptions/$columns);
		for ($i = 0; $i < $columns; $i++)
		{
			$html .= "\t<td class=\"$class\"><span class=\"smallfont\">\n";
			for ($j = 0; $j < $percolumn; $j++)
			{
				$html .= $options[$counter++];
			}
			$html .= "\t</span></td>\n";
		}
		$html .= "</tr></table>\n\t";
	}
	else
	{
		$html = "<div id=\"ctrl_$name\" class=\"smallfont\">\n" . implode('', $options) . "\t</div>";
	}

	print_label_row($title, $html, $class, 'top', $name);
}

class AccessTokenAuthentication
{
	/*
	 * Get the access token.
	 *
	 * @param string $grantType    Grant type.
	 * @param string $scopeUrl     Application Scope URL.
	 * @param string $clientID     Application client ID.
	 * @param string $clientSecret Application client ID.
	 * @param string $authUrl      Oauth Url.
	 *
	 * @return string.
	 */
	function getTokens($grantType, $scopeUrl, $clientID, $clientSecret, $authUrl)
	{
		try
		{
			// Initialize the Curl Session.
			$ch = curl_init();

			// Create the request Array.
			$paramArr = array (
				'grant_type'    => $grantType,
				'scope'         => $scopeUrl,
				'client_id'     => $clientID,
				'client_secret' => $clientSecret
			);

			// Create an Http Query.//
			$paramArr = http_build_query($paramArr);

			// Set the Curl URL.
			curl_setopt($ch, CURLOPT_URL, $authUrl);

			// Set HTTP POST Request.
			curl_setopt($ch, CURLOPT_POST, TRUE);

			// Set data to POST in HTTP "POST" Operation.
			curl_setopt($ch, CURLOPT_POSTFIELDS, $paramArr);

			// CURLOPT_RETURNTRANSFER- TRUE to return the transfer as a string of the return value of curl_exec().
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);

			// CURLOPT_SSL_VERIFYPEER- Set FALSE to stop cURL from verifying the peer's certificate.
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

			// Execute the cURL session.
			$strResponse = curl_exec($ch);

			// Get the Error Code returned by Curl.
			$curlErrno = curl_errno($ch);

			if($curlErrno)
			{
				$curlError = curl_error($ch);
				// throw new Exception($curlError);
				return false;
			}

			// Close the Curl Session.
			curl_close($ch);

			// Decode the returned JSON string.
			$objResponse = json_decode($strResponse);

			if ($objResponse->error)
			{
				// throw new Exception($objResponse->error_description);
				return false;
			}

			return $objResponse->access_token;
		}
		catch (Exception $e)
		{
			// echo "Exception-" . $e->getMessage();
			return false;
		}
	}
}

/*
 * Class:AccessTokenAuthentication
 *
 * Create SOAP Object.
 */
class SOAPMicrosoftTranslator
{
	/*
	 * Soap Object.
	 *
	 * @var ObjectArray.
	 */
	public $objSoap;

	/*
	 * Create the SAOP object.
	 *
	 * @param string $accessToken Access Token string.
	 * @param string $wsdlUrl     WSDL string.
	 *
	 * @return string.
	 */
	public function __construct($accessToken, $wsdlUrl)
	{
		try
		{
			// Authorization header string.
			$authHeader = "Authorization: Bearer ". $accessToken;
			$contextArr = array(
				'http'   => array(
					'header' => $authHeader
				)
			);

			// Create a streams context.
			$objContext = stream_context_create($contextArr);
			$optionsArr = array (
				'soap_version'   => 'SOAP_1_2',
				'encoding'          => 'UTF-8',
				'exceptions'      => true,
				'trace'          => true,
				'cache_wsdl'     => 'WSDL_CACHE_NONE',
				'stream_context' => $objContext,
				'user_agent'     => 'PHP-SOAP/'.PHP_VERSION."\r\n".$authHeader
			);

			//Call Soap Client.
			$this->objSoap = new SoapClient($wsdlUrl, $optionsArr);
		}
		catch(Exception $e)
		{
			echo "<h2>Exception Error!</h2>";
			echo $e->getMessage();
		}
	}
}


/*
 *	This function requries that the new vb framework is initialized.
 */
function phpkd_vbpts_language_export_xml($languageid, $product, $custom, $just_phrases, $charset = 'UTF-8')
{
	global $vbulletin;

	// Moved here from the top of language.php
	$default_skipped_groups = array(
		'cphelptext'
	);

	if ($languageid == -1)
	{
		// $language['title'] = $vbphrase['master_language'];
		$language['title'] = new vB_Phrase('language', 'master_language');
	}
	else
	{
		$language = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "language
			WHERE languageid = " . $languageid
		);
	}

	$title = str_replace('"', '\"', $language['title']);
	$version = str_replace('"', '\"', $vbulletin->options['templateversion']);

	$phrasetypes = fetch_phrasetypes_array(false);

	$phrases = array();
	$getphrases = $vbulletin->db->query_read("
		SELECT phrase.varname, phrase.text, phrase.fieldname, phrase.languageid,
			phrase.username, phrase.dateline, phrase.version
			" . (($languageid != -1) ? ", IF(ISNULL(phrase2.phraseid), 1, 0) AS iscustom" : "") . "
		FROM " . TABLE_PREFIX . "phrase AS phrase
		" . (($languageid != -1) ? "LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase2 ON (phrase.varname = phrase2.varname AND phrase2.languageid = -1 AND phrase.fieldname = phrase2.fieldname)" : "") . "
		WHERE phrase.languageid IN (" . $languageid . ($custom ? ", 0" : "") . ")
			AND (phrase.product = '" . $vbulletin->db->escape_string($product) . "'" .
			iif($product == 'vbulletin', " OR phrase.product = ''") . ")
			" . (($languageid == -1 AND !empty($default_skipped_groups)) ? "AND fieldname NOT IN ('" . implode("', '", $default_skipped_groups) . "')" : '') . "
		ORDER BY phrase.languageid, phrase.fieldname, phrase.varname
	");

	while ($getphrase = $vbulletin->db->fetch_array($getphrases))
	{
		if (!$custom AND $getphrase['iscustom'])
		{
			continue;
		}
		$phrases["$getphrase[fieldname]"]["$getphrase[varname]"] = $getphrase;
	}
	unset($getphrase);
	$vbulletin->db->free_result($getphrases);

	if (empty($phrases) AND $just_phrases)
	{
		throw new vB_Exception_AdminStopMessage('download_contains_no_customizations');
	}

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder($vbulletin, null, $charset);

	$xml->add_group('language',
		array
		(
			'name' => $title,
			'vbversion' => $version,
			'product' => $product,
			'type' => iif($languageid == -1, 'master', iif($just_phrases, 'phrases', 'custom'))
		)
	);

	if ($languageid != -1 AND !$just_phrases)
	{
		$xml->add_group('settings');
		$ignorefields = array('languageid', 'title', 'userselect');
		foreach ($language AS $fieldname => $value)
		{
			if (substr($fieldname, 0, 12) != 'phrasegroup_' AND !in_array($fieldname, $ignorefields))
			{
				$xml->add_tag($fieldname, $value, array(), true);
			}
		}
		$xml->close_group();
	}

	if ($languageid == -1 AND !empty($default_skipped_groups))
	{
		$xml->add_group('skippedgroups');
		foreach ($default_skipped_groups AS $skipped_group)
		{
			$xml->add_tag('skippedgroup', $skipped_group);
		}
		$xml->close_group();
	}

	foreach ($phrases AS $_fieldname => $typephrases)
	{
		$xml->add_group('phrasetype', array('name' => $phrasetypes["$_fieldname"]['title'], 'fieldname' => $_fieldname));
		foreach ($typephrases AS $phrase)
		{
			$attributes = array(
				'name' => $phrase['varname']
			);

			if ($phrase['dateline'])
			{
				$attributes['date'] = $phrase['dateline'];
			}
			if ($phrase['username'])
			{
				$attributes['username'] = $phrase['username'];
			}
			if ($phrase['version'])
			{
				$attributes['version'] = htmlspecialchars_uni($phrase['version']);
			}
			if ($custom AND $phrase['languageid'] == 0)
			{
				$attributes['custom'] = 1;
			}

			$xml->add_tag('phrase', ($vbulletin->GPC['fixhtmlentity'] ? html_entity_decode($phrase['text']) : $phrase['text']), $attributes, true);
		}
		$xml->close_group();
	}

	$xml->close_group();


	$doc = "<?xml version=\"1.0\" encoding=\"{$charset}\"?>\r\n\r\n";
	$doc .= $xml->output();
	$xml = null;

	return $doc;
}

