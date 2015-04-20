<?php
/**
 * Bliki extension - Adds "Bliki" (blog in a wiki) functionality
 * 
 * See http://www.organicdesign.co.nz/bliki for more detail
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author [http://www.organicdesign.co.nz/nad Nad]
 * @copyright © 2013 [http://www.organicdesign.co.nz/nad Nad]
 * @licence GNU General Public Licence 2.0 or later
 */

/**
 * Add a new special page for blog feeds based on Special:RecentChanges
 */
if( !isset( $wgBlikiDefaultCat ) ) $wgBlikiDefaultCat = 'Blog items';
$wgSpecialPages['BlikiFeed'] = 'SpecialBlikiFeed';
class SpecialBlikiFeed extends SpecialRecentChanges {

	// Construct special page with our new name, and force to feed type
	public function __construct() {
		global $wgHooks;
		$wgHooks['SpecialRecentChangesQuery'][] = $this;
		if( !$this->getRequest()->getVal( 'feed' ) ) $this->getRequest()->setVal( 'feed', 'rss' );
		if( !$this->getRequest()->getVal( 'days' ) ) $this->getRequest()->setVal( 'days', 1000 );
		parent::__construct( 'BlikiFeed' );
	}

	// Inject a value into opts so we can know on the hook function that its a bliki feed
	public function doMainQuery( $conds, $opts ) {
		global $wgBlikiDefaultCat, $wgServer, $wgArticlePath, $wgScriptPath, $wgScript;
		$opts->add( 'bliki', false );
		$opts['bliki'] = array_key_exists( 'q', $_REQUEST ) ? $_REQUEST['q'] : $wgBlikiDefaultCat;

		// Make all links absolute
		$wgArticlePath = $wgServer.$wgArticlePath;
		$wgScriptPath  = $wgServer.$wgScriptPath;
		$wgScript      = $wgServer.$wgScript;

		// Allow printing raw desc results
		if(  array_key_exists( 'test', $_REQUEST ) ) {
			$t = Title::newFromText( $_REQUEST['test'] );
			print BlikiChangesFeed::desc($t);
			die;
		}

		// Add the rollback right to the user object so that the page join exists, because without it the new category join fails
		$user = $this->getUser();
		$rights = $user->mRights;
		$user->mRights[] = 'rollback';
 		$res = parent::doMainQuery( $conds, $opts );
		$user->mRights = $rights;

		return $res;
	}

	// If it's a bliki list, filter the list to onlynew items and to the tag cat if q supplied
	public static function onSpecialRecentChangesQuery( &$conds, &$tables, &$join_conds, $opts, &$query_options, &$fields ) {
		if( $opts->validateName( 'bliki' ) ) {
			$tables[] = 'categorylinks';
			$conds[] = 'rc_new=1';
			$dbr = wfGetDB( DB_SLAVE );
			if( is_array( $opts['bliki'] ) ) {
				foreach( $opts['bliki'] as $i => $cat ) $opts['bliki'][$i] = Title::newFromText( $cat )->getDBkey();
				$catCond = 'cl_to IN (' . $dbr->makeList( $opts['bliki'] ) . ')';
			} else $catCond = 'cl_to =' . $dbr->addQuotes( Title::newFromText( $opts['bliki'] )->getDBkey() );
			$join_conds['categorylinks'] = array( 'RIGHT JOIN', "cl_from=page_id AND $catCond" );
		}
		return true;
	}

	public function getFeedObject( $feedFormat ) {
			global $wgRequest, $wgSitename;

			// Blog title & description
			$q = $wgRequest->getVal( 'q', false );
			$cat = $q ? Title::newFromText( $q )->getText() : false;
			$tag = $cat ? self::inCat( 'Tags', $cat ) : false;
			$title = str_replace( ' wiki', '', $wgSitename ) . ' blog';
			$desc = $cat ? ( $tag ? "\"$cat\" posts" : lcfirst( $cat ) ) : 'posts';
			$desc = wfMsg( 'bliki-desc', $desc, $wgSitename );

			// Blog URL
			$blog = Title::newFromText( 'Blog' );
			$url = $cat ? $blog->getFullURL( "q=$cat" ) : $blog->getFullURL();

			// Instantiate our custom ChangesFeed class
			$changesFeed = new BlikiChangesFeed( $feedFormat, 'rcfeed' );
			$formatter = $changesFeed->getFeedObject( $title, $desc, $url );
			return array( $changesFeed, $formatter );
		}

	/**
	 * Return whether or not the passed title is a member of the passed cat
	 */
	public static function inCat( $cat, $title = false ) {
		global $wgTitle;
		if( $title === false ) $title = $wgTitle;
		if( !is_object( $title ) ) $title = Title::newFromText( $title );
		$id  = $title->getArticleID();
		$dbr = wfGetDB( DB_SLAVE );
		$cat = $dbr->addQuotes( Title::newFromText( $cat, NS_CATEGORY )->getDBkey() );
		return $dbr->selectRow( 'categorylinks', '1', "cl_from = $id AND cl_to = $cat" );
	}
}

/**
 * Our BlikiChanges special page uses this custom ChangesFeed
 * which has the item description changed to the plain-text blog item summary instead of the usual diff/wikitext
 */
class BlikiChangesFeed extends ChangesFeed {

	// This is just an exact copy of the parent, we had to override because it calls self::generateFeed
	public function execute( $feed, $rows, $lastmod, $opts ) {
		global $wgLang, $wgRenderHashAppend;

		if ( !FeedUtils::checkFeedOutput( $this->format ) ) {
			return null;
		}

		$optionsHash = md5( serialize( $opts->getAllValues() ) ) . $wgRenderHashAppend;
		$timekey = wfMemcKey( $this->type, $this->format, $wgLang->getCode(), $optionsHash, 'timestamp' );
		$key = wfMemcKey( $this->type, $this->format, $wgLang->getCode(), $optionsHash );

		FeedUtils::checkPurge( $timekey, $key );

		$cachedFeed = $this->loadFromCache( $lastmod, $timekey, $key );
		if( is_string( $cachedFeed ) ) {
			$feed->httpHeaders();
			echo $cachedFeed;
		} else {
			ob_start();
			self::generateFeed( $rows, $feed );
			$cachedFeed = ob_get_contents();
			ob_end_flush();
			$this->saveToCache( $cachedFeed, $timekey, $key );
		}
		return true;
	}

	// Much more compact version than parent because only new items by known authors will be in the list
	public static function generateFeed( $rows, &$feed ) {
		$feed->outHeader();
		foreach( $rows as $obj ) {
			$title = Title::makeTitle( $obj->rc_namespace, $obj->rc_title );
			$url = $title->getFullURL();
			$item = new FeedItem( $title->getPrefixedText(), self::desc( $title ), $url, $obj->rc_timestamp, $obj->rc_user_text, $url );
			$feed->outItem( $item );
		}
		$feed->outFooter();
	}

	// Use the plain-text of the summary for the item description
	static function desc( $title ) {
		global $wgParser;
		$article = new Article( $title );
		$content = $article->getContent();
		$text = preg_match( "/^.+?1=(.+?)\|2=/s", $content, $m ) ? $m[1] : $title->getText();
		$html = $wgParser->parse( trim( $text ), $title, new ParserOptions(), true, true )->getText();
		$html = preg_replace( '|<a[^<]+<img .+?</a>|', '', $html );
		$desc = strip_tags( $html, '<p><a><i><b><u><s>' );
		$desc = preg_replace( "/[\r\n]+/", "", $desc );
		$desc = preg_replace( "|<p></p>|", "", $desc );
		$desc = trim( preg_replace( "|<p>|", "\n<p>", $desc ) );
		return $desc;
	}
}
