<?php

/**
 * Sole special page for OWL Wiki Forms
 * Developer's revision number: $Revision: 1.170 $ 
 */

define("WIKIURI", "http://localhost/OWF/");

// Prefixes used in all SPARQL queries
define("PREFIXES",
		'PREFIX :        <' . WIKIURI  . '>' . <<<EOT
		PREFIX rdf:     <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
		PREFIX rdfs:    <http://www.w3.org/2000/01/rdf-schema#>
		PREFIX owl:     <http://www.w3.org/2002/07/owl#>
		PREFIX fresnel: <http://www.w3.org/2004/09/fresnel#>
EOT
);

class SpecialFresnelForms extends SpecialPage {

	public function __construct() {
		parent::__construct     ('FresnelForms') ;
	}

	public function execute( $par ) {
		global $wgOut, $wgRequest, $wgScript, $owfgSparqlQueryEndpoint, $inOntosStr;

		$wgOut->setPageTitle ( wfMessage( 'fresnelforms') );
		$action = $wgRequest->getText( 'action' );
		if ( $action == 'generate' ) {
		    $inOntosStr = $wgRequest->getText( 'InOntosStr' );
			ontos2DefFresnel ( $inOntosStr ) ;
			$domStr = allFresnel2wiki  ( $inOntosStr ) ;

			$wgOut->addWikiText ( '= OWL Wiki Forms ='                                 );
			$wgOut->addWikiText ( 'The interface was last regenerated from the ontology code at <code>' . $inOntosStr . '</code> at ' . date('g:i A l , F j Y.') );
			$wgOut->addWikiText ( 'The following forms and templates were generated:' );
			$wgOut->addWikiText ( "{|  style='border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; text-align:left;'\n|-\n! Box !! Properties\n" . $domStr . '|}'                                                                   );
		}

		$html = '<form name="generate" action="" method="POST">' . "\n" .
				'<input type="hidden" name="action" value="generate" />' . "\n" .
//				'URIs of ontologies to generate interface from: <input size="75" name="InOntosStr" value="" />' . "\n" .
				'<input type="submit" value="Generate"/></form>' . "\n";
		$wgOut->addHTML( $html );
	}

}

function ontos2DefFresnel ( $inOntosStr ) {

	$inOntoArr=explode(",",$inOntosStr);        // Split comma-seperated URIs
	
// short term preload instead cuz LOAD doens't work from here	endpointUpd ('CLEAR ALL');                  // Empty endpoint
// short term preload instead cuz LOAD doens't work from here	foreach ($inOntoArr as $inOnto) {           // For each URI
// short term preload instead cuz LOAD doens't work from here		endpointUpd ('LOAD <' . $inOnto . '>'); // Load ontology to endpoint
// short term preload instead cuz LOAD doens't work from here	}
	
	///////////////////////////////////////////////////////////////////////////
	// Generate Fresnel triples
	///////////////////////////////////////////////////////////////////////////
	
	// for properties with asserted or inferred domains
	endpointUpd ( <<<EOT
		INSERT {
			?lensuri rdf:type       fresnel:Lens ;
			fresnel:classLensDomain ?domain      ;
			fresnel:showProperties  ?prop                 .
		}

		WHERE {
			?prop a           rdf:Property ;
				  rdfs:domain ?domain        .
			FILTER ( ! bound(?subDomain) )
			FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/1999/02/22-rdf-syntax-ns#" ) )
			FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2000/01/rdf-schema#"       ) )
			FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2002/07/owl#"              ) )
			FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2004/09/fresnel#"          ) )
			BIND(IRI(CONCAT("
EOT
			. WIKIURI . 'defaultLens", ' . <<<EOT

			REPLACE(str(?domain), "[^A-Za-z0-9]", "", "i" )
				)) AS ?lensuri)
			OPTIONAL {
				?prop      rdfs:domain     ?subDomain .
				?subDomain rdfs:subClassOf ?domain    .
				FILTER ( ! sameterm ( ?domain , ?subDomain) )
			}
		}
EOT
	);
	
	// for properties with no domains
	endpointUpd ( <<<EOT
	INSERT {
		?lensuri rdf:type       fresnel:Lens ;
		fresnel:classLensDomain owl:Thing    ;
		fresnel:showProperties  ?prop                 .
	}
	
	WHERE {
		?prop a rdf:Property   .
		FILTER ( ! bound(?domain) )
		FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/1999/02/22-rdf-syntax-ns#" ) )
		FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2000/01/rdf-schema#"       ) )
		FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2002/07/owl#"              ) )
		FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2004/09/fresnel#"          ) )
		BIND(IRI(CONCAT("
EOT
		. WIKIURI . 'defaultLens", ' . <<<EOT
	
		REPLACE("http://www.w3.org/2002/07/owl#Thing", "[^A-Za-z0-9]", "", "i" )
			)) AS ?lensuri)
		OPTIONAL { ?prop rdfs:domain?domain }
	}
EOT
	);
}

function allFresnel2wiki ( $inOntosStr ) {
	
	///////////////////////////////////////////////////////////////////////////
	// Query Fresnel triples
	///////////////////////////////////////////////////////////////////////////
	
	// Query all classes for which Fresnel lenses were made
	$qryRtnArrDom = endpointQry ( <<<EOT
		SELECT DISTINCT ?domain
		WHERE { ?lens fresnel:classLensDomain  ?domain }
		ORDER BY ?domain
EOT
	);
	
	$domStr = ''; // Start $domStr as OWF special page table display
	
	// For each class with a Fresnel lens ...
	foreach ( array_keys($qryRtnArrDom['results']['bindings']) as $key ) {
	
		$domain = qryRtnCell ( $qryRtnArrDom , $key , 'domain' ) ;
	
		//  Query properties with the class as domain
		$qryRtnArr = endpointQry ( '
		SELECT DISTINCT ?prop
		WHERE {
			?lens fresnel:classLensDomain <' . $domain . '> ;
			      fresnel:showProperties  ?prop .
			OPTIONAL {?lens fresnel:hideProperties  ?prop, ?hideDetect  }
			FILTER ( !bound (?hideDetect) )
		}
		ORDER BY ?prop
	' );
	
		if      ( strpos  ($domain , '#' )) $domain = substr ( $domain , 1 + strpos  ( $domain , '#' ) );
		else if ( strrpos ($domain , '/' )) $domain = substr ( $domain , 1 + strrpos ( $domain , '/' ) );
		$propsCell = writeBox ( $domain , $qryRtnArr );  // Create the box wiki pages
		$domStr    = $domStr . "|-\n| ''" . $domain . "'' <small>([[:Category:" . $domain . "|category]],[[Form:" . $domain . "|form]],[[Template:Informbox " . $domain . "|Informbox]])</small> || " . $propsCell . "\n";
	}
	
	return $domStr;
}

function writeBox ( $boxName , $qryRtnArr ) {

	// initialize strings that get built up per property
	$propsCell ='';
	$FrmRows   ='';
	$TplRows   ='';

	foreach(array_keys($qryRtnArr['results']['bindings']) as $propKey) {
		
		// empty any values from previous cycles as precaution (should be unnecessary)
		$rngURI     = ''  ; 
		$rngName    = ''  ; 
		$frmsParams = ''  ; 
		$autocomp   = ''  ; 
		$defForm    = ''  ; 
		
		$propURI   = qryRtnCell ( $qryRtnArr , $propKey , 'prop' ) ;
		$propName  = URIstripName ( $propURI );
		
		if ( FresFrmtImg ( $propURI ) ) {
			$hasType = 'URL' ;
		}
		else {
			$rngURI  = rngURI  ( $propURI ) ;
			$hasType = hasType ( $rngURI  ) ;
		}
		
		// set parameters used in page code generating function
		$rngName    = rngName    ( $hasType , $rngURI  ) ;
		$frmsParams = frmsParams ( $hasType            ) ;
		$autocomp   = autocomp   ( $hasType , $rngName ) ;
		$defForm    = defForm    ( $hasType , $rngName ) ;
				
		// Create code managing property for pages for form, template and property
		$propsCell = $propsCell . '[[Property:' . $propName . '|' . $propName . ']], ';
		$TplRows   = $TplRows   . tplRow ( $propName                                  ) ;
		$FrmRows   = $FrmRows   . frmRow ( $propName , $autocomp , $frmsParams        ) ;
		makePropertyPage                 ( $propName , $propURI , $hasType , $defForm ) ;
	}

	// Create pages
	makeCategoryPage ( $boxName            ) ;
	makeTemplatePage ( $boxName , $TplRows ) ;
	makeFormPage     ( $boxName , $FrmRows ) ;

	return $propsCell;
}

function FresFrmtImg ( $propURI ) {	// check if Fresnel override
	
	$formatRtnArr = endpointQry  ( '
		SELECT ?type 
		WHERE {
			?format fresnel:propertyFormatDomain <' . $propURI . '> ;
			        fresnel:value                ?type                .
		}
	' );
	$formatURI = qryRtnCell ( $formatRtnArr , 0 , 'type' ) ;
	
	return $formatURI == 'http://www.w3.org/2004/09/fresnel#image' ;
}

function rngURI ( $propURI ) { 	// assign Forms autocompletion to range if any
	
	$qryRngRtnArr = endpointQry ( '
		SELECT DISTINCT ?range
		WHERE {
			<' . $propURI . '> rdfs:range ?range
			FILTER ( ! regex( str(?range), "http://www.w3.org/2002/07/owl#Thing"           ) )
			FILTER ( ! regex( str(?range), "http://www.w3.org/2000/01/rdf-schema#Resource" ) )
		}
		LIMIT 1
	' ) ;
	
	return qryRtnCell ( $qryRngRtnArr , 0 , 'range' ) ;
}
	
function hasType ( $rngURI ) {
	
	// Create property page as either SMW datatype or default form
	if      ( $suffix = stripPrefix ( $rngURI, 'http://www.w3.org/2000/01/rdf-schema#' ) )
		switch ( $suffix ) {
			case 'Literal' :
				$hasType = 'String' ; break ;
	}
	elseif ( $suffix = stripPrefix ( $rngURI, 'http://www.w3.org/2001/XMLSchema#'     ) )
		switch ( $suffix ) {
			// external URI not as wiki page
			case 'anyURI' :
				$hasType = 'URL' ; break ;
			// Boolean via radiobuttons
			case 'boolean' :
				$hasType = 'Boolean' ; break ;
			// Date
			case 'dateTime' : case 'date' :
				$hasType = 'Date' ; break ;
			// Number
			case 'decimal' : case 'rational' : case 'real' : case 'int' : case 'integer' :
					case 'double' : case 'float' : case 'long' : case 'short' :
					case 'negativeInteger' : case 'positiveInteger' : case 'nonPositiveInteger' : case 'nonNegativeInteger' :
					case 'unsignedByte' : case 'unsignedLong' : case 'unsignedInt' : case 'unsignedShort' :
				$hasType = 'Number' ; break ;
			// String
			case 'string' : case 'XMLLiteral' : case 'normalizedString' : case 'language' : 
					case 'Name' : case 'token' : case 'NMTOKEN' : case 'NCName' :
					case 'base64Binary' : case 'byte' : case 'hexBinary' :
					case 'time' : case 'gYearMonth' : case 'gYear' : case 'gMonthDay' : case 'gDay' : case 'gMonth' :
				$hasType = 'String' ; break ;
			break ;
		}
	else $hasType  = 'Page' ;
	
	return $hasType ;
}

function rngName  ( $hasType , $rngURI ) {
	if ( $hasType != 'Page' ) $rngName = '' ;
	else {
		$rngName = URIstripName ( $rngURI ) ;
		if ( $hasType == 'Page' && $rngName == '' ) $rngName  = 'Thing' ;
	}
	return $rngName ;
}

function frmsParams ( $hasType ) {
	if ( $hasType == 'Boolean' ) $frmsParams = '|input type=radiobutton|values=Yes,No' ;
	else                         $frmsParams = '' ;
	return $frmsParams ;
}

function autocomp ( $hasType , $rngName ) {
	if ( $hasType == 'Page' ) $autocomp = '|autocomplete on category=' . $rngName ;
	else                      $autocomp = '' ;
	return $autocomp ;
}

function defForm ( $hasType , $rngName ) {
	if ( $hasType == 'Page' ) $defForm  = $rngName ;
	else                      $defForm  = '' ;
	return $defForm ;
}

function tplRow ( $propName ) {
	return
'|- {{#if: {{{' . $propName . '|}}}||style="display:none"}}
! [[Property:' . $propName . '|' . $propName . ']]
| {{#arraymap:{{{' . $propName . '|}}}|,|xxx|[[' . $propName . '::xxx]]}}
';
}

function frmRow ( $propName , $autocomp , $frmsParams ) {
	return
'|-
! ' . $propName . ':
| {{{field|' . $propName . $autocomp . $frmsParams . '|list }}}
';
}

function makePage ( $prefix , $name , $content ) {
	global $wgOut;
	if ( $content == '' ) $content =  '<span> </span>' ;
	$newarticle = new Article(Title::newFromText( $prefix . ':' . $name ) , 0);
// stop until can get working	$newarticle->doEdit( $content , EDIT_UPDATE);
    $preColon = "" ;
    if ( $prefix == 'Category' ) $preColon = ":" ;
    $wgOut->addWikiText ( "= [[" . $preColon . $prefix . ":" . $name . "]] =\n\n<pre><nowiki>" . $content . "</nowiki></pre>" ) ; // page code on special page
}

function makeCategoryPage ( $catName ) {
	makePage ( 'Category' , $catName, '' );
}

function makePropertyPage ( $propName , $propURI , $hasType , $defForm ) {
	$pageContent = "" ;
	$pageContent = $pageContent . annotConstruct ( $hasType , "[[Has type::"              ) ;
	$pageContent = $pageContent . annotConstruct ( $propURI , "[[Equivalent URI::"        ) ;
    $pageContent = $pageContent . annotConstruct ( $defForm , "[[Has default form::Form:" ) ;
	makePage ( 'Property' , $propName , $pageContent ) ;	
}

function annotConstruct ( $value , $text ) {
	if ( $value ) return $text . $value . "| ]]" ;
	else return "" ;
}

function makeTemplatePage ( $boxName , $TplRows ) {

	// Start of Template page

	$TplStr =
'<includeonly>
{| style="width: 30em; font-size: 90%; border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; float: right; clear: right; text-align:left;"
! style="text-align: center; background-color:#ccccff;" colspan="2" |<big>[[:Category:' . $boxName . '|' . $boxName . ']] data</big> [[Special:FormEdit/' . $boxName . '/{{FULLPAGENAME}}|form]]
';
	
	// End of Template page

	$TplEnd =
'|}

[[Category:' . $boxName . ']]
{{#ifexist: Template:InformboxTop ' . $boxName . '|{{InformboxTop ' . $boxName . '}}| }}
</includeonly>
';

	makePage ( 'Template' , 'Informbox ' . $boxName , $TplStr . $TplRows . $TplEnd );
}

function makeFormPage ( $boxName , $FrmRows ) {
	
	// Start of Form page
	
	$FrmStr =
'<noinclude>
{{#forminput:form=' . $boxName . '}}
</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Informbox ' . $boxName . '|label=' . $boxName . '}}}
{| class="formtable"
';
	
	// End of Form page
	
	$FrmEnd = <<<EOT
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
{{{standard input|summary}}}
{{{standard input|save}}}
{{{standard input|preview}}}
{{{standard input|changes}}}
{{{standard input|cancel}}}
</includeonly>
EOT;
	
	makePage ( 'Form' , $boxName , $FrmStr . $FrmRows . $FrmEnd );
}

function stripPrefix ( $fullStr, $prefix ) {
	if ( ! strncmp ( $fullStr, $prefix, strlen($prefix) ) ) return substr ( $fullStr , strlen($prefix) ) ;
	else                                                    return 0                                     ;
}

function URIstripName ( $URI ) {
	if      ( strpos  ($URI , '#' )) $name = substr ( $URI , 1 + strpos  ( $URI , '#' ) ) ;
	else if ( strrpos ($URI , '/' )) $name = substr ( $URI , 1 + strrpos ( $URI , '/' ) ) ;
	else                            $name = $URI                                       ;
	return $name;
}

function qryRtnCell ( $array , $key , $varName ) {
	return $array['results']['bindings'][$key][$varName]['value'];
}

function endpointQry ( $query ) {

	global $owfgSparqlQueryEndpoint;

	$qryRtnJsonDom = file_get_contents( $owfgSparqlQueryEndpoint . '?query=' . urlencode( PREFIXES . $query ) . '&output=json' ) ;
	return json_decode ( $qryRtnJsonDom , true );
}

function endpointUpd ( $update ) {

	global $owfgSparqlUpdateEndpoint;

	file_get_contents($owfgSparqlUpdateEndpoint, false,
			stream_context_create(array('http' => array(
					'method' => 'POST',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content' => http_build_query(array('update' => PREFIXES . $update))))));
}
