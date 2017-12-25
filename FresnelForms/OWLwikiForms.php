<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/OWLwikiForms/OWLwikiForms.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
        'name'           => 'OWL Wiki Forms',
        'author'         => 'Lloyd Rutledge',
        'url'            => 'http://is.cs.ou.nl/OWF',
        'description'    => 'RDF(S)/OWL-based forms for RDF export and wiki interface generation',
        'descriptionmsg' => 'owlwikiforms', // Thanks to Cameron Angus McLean
        'version'        => '0.2.1',        // Thanks to Tobias Käfer
);
 
$dir = dirname(__FILE__) . '/';

// File location bug found by Cameron Angus McLean
$wgAutoloadClasses        ['SpecialOWLwikiForms'] = $dir . '/SpecialOWLwikiForms.php' ; # Location of the SpecialOWLwikiForms class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles ['OWLwikiForms'       ] = $dir . '/OWLwikiForms.i18n.php'   ; # Location of a messages file (Tell MediaWiki to load this file)
$wgSpecialPages           ['OWLwikiForms'       ] = 'SpecialOWLwikiForms'             ; # Tell MediaWiki about the new special page and its class name
