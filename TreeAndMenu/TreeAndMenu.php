<?php
/**
 * TreeAndMenu extension - Adds #tree and #menu parser functions for collapsible treeview's and dropdown menus
 *
 * See http://www.mediawiki.org/wiki/Extension:TreeAndMenu for installation and usage details
 * See http://www.organicdesign.co.nz/Extension_talk:TreeAndMenu.php for development notes and disucssion
 * 
 * @file
 * @ingroup Extensions
 * @author Aran Dunkley [http://www.organicdesign.co.nz/nad User:Nad]
 * @copyright © 2007 Aran Dunkley
 * @licence GNU General Public Licence 2.0 or later
 */

if( !defined( 'MEDIAWIKI' ) ) die( 'Not an entry point.' );

define( 'TREEANDMENU_VERSION','3.1.3, 2015-03-15' );

// Tree defaults
if( !isset( $wgTreeViewImages ) || !is_array( $wgTreeViewImages ) ) $wgTreeViewImages = array();
$wgTreeViewShowLines = false;  // whether to render the dotted lines joining nodes

$wgExtensionFunctions[] = 'wfSetupTreeAndMenu';
$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'TreeAndMenu',
	'author'         => array( '[http://www.organicdesign.co.nz/User:Nad Nad]', '[http://www.organicdesign.co.nz/User:Sven Sven]' ),
	'url'            => 'http://www.mediawiki.org/wiki/Extension:TreeAndMenu',
	'descriptionmsg' => 'treeandmenu-desc',
	'version'        => TREEANDMENU_VERSION,
);

$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['TreeAndMenu'] = $dir . 'TreeAndMenu.i18n.php';
$wgExtensionMessagesFiles['TreeAndMenuMagic'] = $dir . 'TreeAndMenu.i18n.magic.php';

// @todo FIXME: Move classes out of init file.

class TreeAndMenu {

	var $version    = TREEANDMENU_VERSION;
	var $uniq       = '';      // uniq part of all tree id's
	var $uniqname   = 'tam';   // input name for uniqid
	var $id         = '';      // id for specific tree
	var $baseDir    = '';      // internal absolute path to treeview directory
	var $baseUrl    = '';      // external URL to treeview directory (relative to domain)
	var $images     = '';      // internal JS to update dTree images
	var $useLines   = true;    // internal variable determining whether to render connector lines
	var $args       = array(); // args for each tree

	/**
	 * Constructor
	 */
	function __construct() {
		global $wgOut, $wgHooks, $wgParser, $wgJsMimeType, $wgExtensionAssetsPath,
			$wgTreeViewImages, $wgTreeViewShowLines;

		// Add hooks
		$wgParser->setFunctionHook( 'tree', array( $this, 'expandTree' ) );
		$wgParser->setFunctionHook( 'menu', array( $this, 'expandMenu' ) );

		// Update general tree paths and properties
		$this->baseDir  = __DIR__;
		$this->baseUrl  = $wgExtensionAssetsPath . '/' . basename( __DIR__ );
		$this->useLines = $wgTreeViewShowLines ? 'true' : 'false';
		$this->uniq     = uniqid( $this->uniqname );

		// Convert image titles to file paths and store as JS to update dTree
		foreach( $wgTreeViewImages as $k => $v ) {
			$image = wfLocalFile( $v );
			$v = ( is_object( $image ) && $image->exists() ) ? $image->getURL() : $wgTreeViewImages[$k];
			$this->images .= "tree.icon['$k'] = '$v';";
		}

		// Set up JavaScript and CSS resources
		$wgOut->addStyle( $this->baseUrl . '/treeandmenu.css' );
		$wgOut->addHeadItem( 'treeBaseUrl', "<script type=\"$wgJsMimeType\">window.tamBaseUrl='{$this->baseUrl}'</script>\n" );
		$wgOut->addHeadItem( 'dTree', "<script type=\"$wgJsMimeType\" src=\"{$this->baseUrl}/dtree.js\"></script>\n" );
	}

	/**
	 * Expand #tree parser-functions
	 */
	public function expandTree() {
		return $this->expandTreeAndMenu( 'tree', func_get_args() );
	}

	/**
	 * Expand #menu parser-functions
	 */
	public function expandMenu() {
		return $this->expandTreeAndMenu( 'menu', func_get_args() );
	}

	/**
	 * Expand either kind of parser-function (reformats tree rows for matching later) and store args
	 */
	public function expandTreeAndMenu( $magic, $args ) {
		global $wgUser;
		$parser = array_shift( $args );
	
		// Store args for this tree for later use
		$text = '';
		foreach( $args as $arg ) if ( preg_match( '/^(\\w+?)\\s*=\\s*(.+)$/s', $arg, $m ) ) $args[$m[1]] = $m[2]; else $text = $arg;
		$args['type'] = $magic;

		// If root, parse as wikitext
		if( isset( $args['root'] ) ) {
			$p = clone $parser;
			$o = clone $parser->mOptions;
			$o->mTidy = $o->mEnableLimitReport = false;
			$html = $p->parse( $args['root'], $parser->mTitle, $o, false, true )->getText();
			$args['root'] = addslashes( $html );
		}

		// Create a unique id for this tree or use id supplied in args and store args wrt id
		$this->id = isset($args['id']) ? $args['id'] : uniqid( '' ) .'t';
		$this->args[$this->id] = $args;

		// Reformat tree rows
		$text = preg_replace( '/(?<=\\*)\\s*\\[\\[Image:(.+?)\\]\\]/', "{$this->uniq}3$1{$this->uniq}4", $text );
		$text = str_replace( '$', '\$', $text );
		$text = preg_replace_callback( '/^(\\*+)(.*?)$/m', array( $this, 'formatRow' ), $text );

		// Parse the structure
		$psr = new Parser;
		$opt = ParserOptions::newFromUser( $wgUser );
		$html = $psr->parse( $text, $parser->mTitle, $opt, true, true )->getText();

		// Do the final rendering
		$html = $this->renderTreeAndMenu( $html );

		// Hack to undo Parser::doBlockLevels adding <p>'s to the html
		global $wgHooks;
		$wgHooks['ParserBeforeTidy'][] = "TreeAndMenu::removeP";

		return array( $html, 'isHTML' => true );
	}

	/**
	 * Hack to undo Parser::doBlockLevels adding <p>'s to the html
	 * - this will be called shortly after it's added in the parsing of the parser-function
	 * - it removes itself from $wgHooks so it's only called once during the parse that installed it
	 */
	static function removeP( $parser, &$text ) {
		global $wgHooks;
		$tmp = array();
		foreach( $wgHooks['ParserBeforeTidy'] as $hook ) {
			if( $hook !== "TreeAndMenu::removeP" ) $tmp[] = $hook;
		}
		$wgHooks['ParserBeforeTidy'] = $tmp;
		$text = preg_replace( "|</?p>|", "", $text );
		return true;
	}

	/**
	 * Reformat tree bullet structure recording row, depth and id in a format which is not altered by wiki-parsing
	 * - format is: 1{uniq}-{id}-{depth}-{item}-2{uniq}
	 * - sequences of this format will be matched in ParserAfterTidy and converted into dTree JavaScript
	 * - NOTE: we can't encode a unique row-id because if the same tree instranscluded twice a cached version
	 *         may be used (even if parser-cache disabled) this also means that tree id's may be repeated
	 */
	private function formatRow( $m ) {
		return "~x7f1{$this->uniq}~x7f{$this->id}~x7f" . ( strlen( $m[1] )-1 ) . "~x7f$m[2]~x7f2{$this->uniq}";
	}


	/**
	 * Called after parser has finished (ParserAfterTidy) so all transcluded parts can be assembled into final trees
	 */
	public function renderTreeAndMenu( $text ) {
		global $wgJsMimeType, $wgOut;
		$u = $this->uniq;


		// Extract all the formatted tree rows in the page and if any, replace with dTree JavaScript
		if( preg_match_all( "/~x7f1$u~x7f(.+?)~x7f([0-9]+)~x7f({$u}3(.+?){$u}4)?(.*?)(?=~x7f[12]$u)/", $text, $matches, PREG_SET_ORDER ) ) {

			// PASS-1: build $rows array containing depth, and tree start/end information
			$subs   = array( 1 => array() );
			$rows   = array();
			$depths = array( '' => 0 ); // depth of each tree root
			$rootId = '';               // the id of the current root-tree (used as tree id in PASS2)
			$lastId = '';
			$lastDepth = 0;
			foreach( $matches as $match ) {
				list( , $id, $depth,, $icon, $item ) = $match;
				$start = false;
				if( $id != $lastId ) {
					if( !isset( $depths[$id] ) ) $depths[$id] = $depths[$lastId]+$lastDepth;
					if( $start = $rootId != $id && !in_array( $id, $subs[1] ) ) $depths[$rootId = $id] = 0;
				}
				if( $item ) $rows[] = array( $rootId, $depth + $depths[$id], $icon, $item, $start );
				$lastId    = $id;
				$lastDepth = $depth;
			}

			// PASS-2: build the JavaScript and replace into $text
			$parents   = array(); // parent node for each depth
			$parity    = array(); // keep track of odd/even rows for each depth
			$node      = 0;
			$last      = -1;
			$nodes     = '';
			$opennodes = array();
			foreach( $rows as $i => $info ) {
				$node++;
				list( $id, $depth, $icon, $item, $start ) = $info;
				$objid = $this->uniqname . preg_replace( '/\W/', '', $id );
				$args  = $this->args[$id];
				$type  = $args['type'];
				$end   = $i == count( $rows )-1 || $rows[$i+1][4];
				if( !isset( $args['root'] ) ) $args['root'] = ''; // tmp - need to handle rootless trees
				$openlevels = isset( $args['openlevels'] ) ? $args['openlevels']+1 : 0;
				if( $start ) $node = 1;

				// Append node script for this row
				if( $depth > $last ) $parents[$depth] = $node-1;
				$parent = $parents[$depth];
				if( $type == 'tree' ) {
					$nodes .= "$objid.add($node, $parent, '" . addslashes( $item ) . "');\n";
					if( $depth > 0 && $openlevels > $depth ) $opennodes[$parent] = true;
				}
				else {
					if( !$start ) {
						if( $depth < $last ) $nodes .= str_repeat( '</ul></li>', $last - $depth );
						elseif ( $depth > $last ) $nodes .= "\n<ul>";
					}
					$parity[$depth] = isset( $parity[$depth] ) ? $parity[$depth]^1 : 0;
					$class = $parity[$depth] ? 'odd' : 'even';
					$nodes .= "<li class=\"$class\">$item";
					if( !array_key_exists( $node, $rows ) || $depth >= $rows[$node][1] ) $nodes .= "</li>\n";
				}
				$last = $depth;

				// Last row of current root, surround nodes dtree or menu script and div etc
				if( $end ) {
					$class = isset( $args['class'] ) ? $args['class'] : "d$type";

					// Finalise a tree
					if ( $type == 'tree' ) {
						$add = isset( $args['root'] ) ? "tree.add(0,-1,'" . $args['root'] . "');" : '';
						$top = $bottom = $root = $opennodesjs = '';
						foreach( array_keys( $opennodes ) as $i ) $opennodesjs .= "$objid.o($i);";
						foreach( $args as $arg => $pos )
							if( ( $pos == 'top' || $pos == 'bottom' || $pos == 'root' ) && ( $arg == 'open' || $arg == 'close' ) )
								$$pos .= "<a href=\"javascript: $objid.{$arg}All();\">&#160;{$arg} all</a>&#160;";
						if( $top ) $top = "<p>&#160;$top</p>";
						if( $bottom ) $bottom = "<p>&#160;$bottom</p>";

						// Define the script to build this tree
						$script = "tree = new dTree('$objid');
							for (i in tree.icon) tree.icon[i] = '{$this->baseUrl}/'+tree.icon[i];{$this->images}
							tree.config.useLines = {$this->useLines};
							$add
							$objid = tree;
							$nodes
							document.getElementById('$id').innerHTML = $objid.toString();
							$opennodesjs
							for(i in window.tamOnload_$objid) { window.tamOnload_{$objid}[i](); }";
						$html = "$top<div class='$class' id='$id'></div>$bottom";
						$html .= "<script type=\"$wgJsMimeType\">window.tamOnload_$objid=[];$script</script>";
					}

					// Finalise a menu
					else {
						if( $depth > 0 ) $nodes .= str_repeat( '</ul></li>', $depth );
						$nodes = preg_replace( "/<(a.*? )title=\".+?\".*?>/", "<$1>", $nodes ); // IE has problems with title attribute in suckerfish menus
						$html = "<ul class='$class' id='$id'>\n$nodes</ul>
							<script type=\"$wgJsMimeType\">/*<![CDATA[*/
								if (window.attachEvent) {
									var sfEls = document.getElementById('$id').getElementsByTagName('li');
									for (var i=0; i<sfEls.length; i++) {
										sfEls[i].onmouseover=function() { this.className+=' sfhover'; }
										sfEls[i].onmouseout=function()  { this.className=this.className.replace(new RegExp(' sfhover *'),''); }
									}
								}
							/*]]>*/</script>";
					}

					$html = str_replace( '$', '\$', $html );
					$text  = preg_replace( "/~x7f1$u~x7f$id~x7f.+?$/m", $html, $text, 1 ); // replace first occurrence of this trees root-id
					$nodes = '';
					$last  = -1;
				}
			}
		}

		// Remove all unreplaced row information
		$html = preg_replace( "/~x7f1$u~x7f.+?[\\r\\n]+/m", '', $text );

		return $html;
	}
}

/**
 * Called from $wgExtensionFunctions array when initialising extensions
 */
function wfSetupTreeAndMenu() {
	global $wgTreeAndMenu;
	$wgTreeAndMenu = new TreeAndMenu();
}
