<?php
/**
 * RecordAdminCreateForm extension - An extension to enable the creation of any RA record from any page.
 * See http://www.organicdesign.co.nz/Extension:RecordAdminCreateForm for installation and usage details
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author [http://www.organicdesign.co.nz/wiki/User:Jack User:Jack]
 * @copyright © 2008 [http://www.organicdesign.co.nz/wiki/User:Jack User:Jack]
 * @licence GNU General Public Licence 2.0 or later
 */
if ( !defined( 'MEDIAWIKI' ) )           die( 'Not an entry point.' );
if ( !defined( 'RECORDADMIN_VERSION' ) ) die( 'The RecordAdminCreateForm extension depends on the RecordAdmin extension' );

define( 'RECORDADMINCREATEFORM_VERSION', '1.0.5, 2010-03-08' );

$wgExtensionFunctions[] = 'efSetupRecordAdminCreateForm';

$wgExtensionCredits['parserhook'][] = array(
	'name'        => 'RecordAdminCreateForm',
	'author'      => '[http://www.organicdesign.co.nz/wiki/User:Jack User:Jack]',
	'description' => 'An extension to enable the creation of any RA record from any page. Made with [http://www.organicdesign.co.nz/Template:Extension Template:Extension].',
	'url'         => 'http://www.organicdesign.co.nz/Extension:RecordAdminCreateForm',
	'version'     => RECORDADMINCREATEFORM_VERSION
);

/**
 * Function called from the hook BeforePageDisplay, creates a form which links to a new RA form page with title and type arguments in the url.
 */
function efRecordAdminCreateForm (&$out) {

	# Make options list from items in records cat
	/*$options = '';
	global $wgRecordAdminCategory;
	$dbr = &wfGetDB(DB_SLAVE);
	$cl  = $dbr->tableName( 'categorylinks' );
	$cat = $dbr->addQuotes( $wgRecordAdminCategory );
	$res = $dbr->select( $cl, 'cl_from', "cl_to = $cat", __METHOD__, array( 'ORDER BY' => 'cl_sortkey' ) );
	while ( $row = $dbr->fetchRow( $res ) ) $options .= '<option>' . Title::newFromID( $row[0] )->getText() . '</option>';
	*/
	
	# Get an options list from the article 
	$title    = Title::newFromText( 'MediaWiki:Od-minform-record-list' );
	$article  = new Article( $title );
	$options = preg_replace( "|\*\s*(.+)$|m", "<option>$1</option>", $article->getContent() );

	# Post the form to Special:RecordAdmin
	$action = Title::makeTitle( NS_SPECIAL, 'RecordAdmin' )->getLocalUrl();

	# Add a form to the page
	$out->mBodytext .= "
		<div id='p-racreate' class='portlet'>
			<h5 ><label for='searchInput'>New record</label></h5>
			<div id='createBody' class='pBody'>
				<form id='RACreateForm' method='POST' action='$action'>
					<select name='wpType' id='selectwpType'>$options</select>
					<input type='submit' id='raCreateButton' class='searchButton' value='Create' />
				</form>
			</div>
		</div>";

	return true;
}

/**
 * Setup function
 */
function efSetupRecordAdminCreateForm() {
	global $wgHooks;
	$wgHooks['BeforePageDisplay'][] = 'efRecordAdminCreateForm';
}

	
	
