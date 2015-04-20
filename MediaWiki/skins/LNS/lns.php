<?php
/**
 * Vector - Branch of MonoBook which has many usability improvements and
 * somewhat cleaner code.
 *
 * @todo document
 * @file
 * @ingroup Skins
 */

if( !defined( 'MEDIAWIKI' ) )
	die( -1 );

/**
 * SkinTemplate class for lns skin
 * @ingroup Skins
 */
class Skinlns extends SkinTemplate {

	/* Functions */
	var $skinname = 'lns', $stylename = 'lns',
		$template = 'lnsTemplate', $useHeadElement = true;

	/**
	 * Initializes output page and sets up skin-specific parameters
	 * @param object $out Output page object to initialize	
	 */
	public function initPage( OutputPage $out ) {
		global $wgStylePath, $wgJsMimeType, $wgStyleVersion, $wgScriptPath, $wgVectorExtraStyles;
		
		parent::initPage( $out );

		// Append skin-specific styles
		$out->addStyle( 'lns/main-rtl.css', 'screen', '', 'rtl' );		
		$out->addStyle( 'lns/main-ltr.css', 'screen', '', 'ltr' );		
		// Append CSS which includes IE only behavior fixes for hover support -
		// this is better than including this in a CSS fille since it doesn't
		// wait for the CSS file to load before fetching the HTC file.		
		$out->addScript(
			'<!--[if lt IE 7]><style type="text/css">body{behavior:url("' .
				$wgStylePath .
				'/lns/csshover.htc")}</style><![endif]-->'
		);
		// LNS Spry Menu bar
		$out->addScript(
			'<script type="text/javascript" src="'.$wgStylePath.'/lns/SpryAssets/SpryMenuBar.js"></script>'
		);
		// Add extra stylesheets
		// THIS IS ONLY USEFUL FOR EXPERIMENTING WITH DIFFERNT STYLE OPTIONS! THIS WILL BE REMOVED IN THE NEAR FUTURE.
		if ( is_array( $wgVectorExtraStyles ) ) {
			foreach ( $wgVectorExtraStyles as $style ) {
				$out->addStyle( 'lns/' . $style, 'screen' );
			}
		}
	}
	/**
	 * Builds a structured array of links used for tabs and menus
	 * @return array
	 * @private
	 */
	function buildNavigationUrls() {
		global $wgContLang, $wgLang, $wgOut, $wgUser, $wgRequest, $wgArticle, $wgStylePath;
		global $wgDisableLangConversion, $wgVectorUseIconWatch;

		wfProfileIn( __METHOD__ );

		$links = array(
			'namespaces' => array(),
			'views' => array(),
			'actions' => array(),
			'variants' => array()
		);

		// Detects parameters
		$action = $wgRequest->getVal( 'action', 'view' );
		$section = $wgRequest->getVal( 'section' );

		// Checks if page is some kind of content
		if( $this->iscontent ) {

			// Gets page objects for the related namespaces
			$subjectPage = $this->mTitle->getSubjectPage();
			$talkPage = $this->mTitle->getTalkPage();

			// Determines if this is a talk page
			$isTalk = $this->mTitle->isTalkPage();

			// Generates XML IDs from namespace names
			$subjectId = $this->mTitle->getNamespaceKey( '' );

			if ( $subjectId == 'main' ) {
				$talkId = 'talk';
			} else {
				$talkId = "{$subjectId}_talk";
			}
			$currentId = $isTalk ? $talkId : $subjectId;

			// Adds namespace links
			$links['namespaces'][$subjectId] = $this->tabAction(
				$subjectPage, 'vector-namespace-' . $subjectId, !$isTalk, '', true
			);
			$links['namespaces'][$subjectId]['context'] = 'subject';
			$links['namespaces'][$talkId] = $this->tabAction(
				$talkPage, 'vector-namespace-talk', $isTalk, '', true
			);
			$links['namespaces'][$talkId]['context'] = 'talk';

			// Adds view view link
			if ( $this->mTitle->exists() ) {
				$links['views']['view'] = $this->tabAction(
					$isTalk ? $talkPage : $subjectPage,
						'vector-view-view', ( $action == 'view' ), '', true
				);
			}

			wfProfileIn( __METHOD__ . '-edit' );

			// Checks if user can...
			if (
				// edit the current page
				$this->mTitle->quickUserCan( 'edit' ) &&
				(
					// if it exists
					$this->mTitle->exists() ||
					// or they can create one here
					$this->mTitle->quickUserCan( 'create' )
				)
			) {
				// Builds CSS class for talk page links
				$isTalkClass = $isTalk ? ' istalk' : '';

				// Determines if we're in edit mode
				$selected = (
					( $action == 'edit' || $action == 'submit' ) &&
					( $section != 'new' )
				);
				$links['views']['edit'] = array(
					'class' => ( $selected ? 'selected' : '' ) . $isTalkClass,
					'text' => $this->mTitle->exists()
						? wfMsg( 'vector-view-edit' )
						: wfMsg( 'vector-view-create' ),
					'href' =>
						$this->mTitle->getLocalUrl( $this->editUrlOptions() )
				);
				// Checks if this is a current rev of talk page and we should show a new
				// section link
				if ( ( $isTalk && $wgArticle->isCurrent() ) || ( $wgOut->showNewSectionLink() ) ) {
					// Checks if we should ever show a new section link
					if ( !$wgOut->forceHideNewSectionLink() ) {
						// Adds new section link
						//$links['actions']['addsection']
						$links['views']['addsection'] = array(
							'class' => 'collapsible ' . ( $section == 'new' ? 'selected' : false ),
							'text' => wfMsg( 'vector-action-addsection' ),
							'href' => $this->mTitle->getLocalUrl(
								'action=edit&section=new'
							)
						);
					}
				}
			// Checks if the page is known (some kind of viewable content)
			} elseif ( $this->mTitle->isKnown() ) {
				// Adds view source view link
				$links['views']['viewsource'] = array(
					'class' => ( $action == 'edit' ) ? 'selected' : false,
					'text' => wfMsg( 'vector-view-viewsource' ),
					'href' =>
						$this->mTitle->getLocalUrl( $this->editUrlOptions() )
				);
			}
			wfProfileOut( __METHOD__ . '-edit' );

			wfProfileIn( __METHOD__ . '-live' );

			// Checks if the page exists
			if ( $this->mTitle->exists() ) {
				// Adds history view link
				$links['views']['history'] = array(
					'class' => 'collapsible ' . ( ($action == 'history') ? 'selected' : false ),
					'text' => wfMsg( 'vector-view-history' ),
					'href' => $this->mTitle->getLocalUrl( 'action=history' ),
					'rel' => 'archives',
				);

				if( $wgUser->isAllowed( 'delete' ) ) {
					$links['actions']['delete'] = array(
						'class' => ($action == 'delete') ? 'selected' : false,
						'text' => wfMsg( 'vector-action-delete' ),
						'href' => $this->mTitle->getLocalUrl( 'action=delete' )
					);
				}
				if ( $this->mTitle->quickUserCan( 'move' ) ) {
					$moveTitle = SpecialPage::getTitleFor(
						'Movepage', $this->thispage
					);
					$links['actions']['move'] = array(
						'class' => $this->mTitle->isSpecial( 'Movepage' ) ?
										'selected' : false,
						'text' => wfMsg( 'vector-action-move' ),
						'href' => $moveTitle->getLocalUrl()
					);
				}

				if (
					$this->mTitle->getNamespace() !== NS_MEDIAWIKI &&
					$wgUser->isAllowed( 'protect' )
				) {
					if ( !$this->mTitle->isProtected() ){
						$links['actions']['protect'] = array(
							'class' => ($action == 'protect') ?
											'selected' : false,
							'text' => wfMsg( 'vector-action-protect' ),
							'href' =>
								$this->mTitle->getLocalUrl( 'action=protect' )
						);

					} else {
						$links['actions']['unprotect'] = array(
							'class' => ($action == 'unprotect') ?
											'selected' : false,
							'text' => wfMsg( 'vector-action-unprotect' ),
							'href' =>
								$this->mTitle->getLocalUrl( 'action=unprotect' )
						);
					}
				}
			} else {
				// article doesn't exist or is deleted
				if (
					$wgUser->isAllowed( 'deletedhistory' ) &&
					$wgUser->isAllowed( 'undelete' )
				) {
					if( $n = $this->mTitle->isDeleted() ) {
						$undelTitle = SpecialPage::getTitleFor( 'Undelete' );
						$links['actions']['undelete'] = array(
							'class' => false,
							'text' => wfMsgExt(
								'vector-action-undelete',
								array( 'parsemag' ),
								$wgLang->formatNum( $n )
							),
							'href' => $undelTitle->getLocalUrl(
								'target=' . urlencode( $this->thispage )
							)
						);
					}
				}

				if (
					$this->mTitle->getNamespace() !== NS_MEDIAWIKI &&
					$wgUser->isAllowed( 'protect' )
				) {
					if ( !$this->mTitle->getRestrictions( 'create' ) ) {
						$links['actions']['protect'] = array(
							'class' => ($action == 'protect') ?
											'selected' : false,
							'text' => wfMsg( 'vector-action-protect' ),
							'href' =>
								$this->mTitle->getLocalUrl( 'action=protect' )
						);

					} else {
						$links['actions']['unprotect'] = array(
							'class' => ($action == 'unprotect') ?
											'selected' : false,
							'text' => wfMsg( 'vector-action-unprotect' ),
							'href' =>
								$this->mTitle->getLocalUrl( 'action=unprotect' )
						);
					}
				}
			}
			wfProfileOut( __METHOD__ . '-live' );
			/**
			 * The following actions use messages which, if made particular to
			 * the Vector skin, would break the Ajax code which makes this
			 * action happen entirely inline. Skin::makeGlobalVariablesScript
			 * defines a set of messages in a javascript object - and these
			 * messages are assumed to be global for all skins. Without making
			 * a change to that procedure these messages will have to remain as
			 * the global versions.
			 */
			// Checks if the user is logged in
			if ( $this->loggedin ) {
				if ( $wgVectorUseIconWatch ) {
					$class = 'icon ';
					$place = 'views';
				} else {
					$class = '';
					$place = 'actions';
				}
				$mode = $this->mTitle->userIsWatching() ? 'unwatch' : 'watch';
				$links[$place][$mode] = array(
					'class' => $class . ( ( $action == 'watch' || $action == 'unwatch' ) ? ' selected' : false ),
					'text' => wfMsg( $mode ), // uses 'watch' or 'unwatch' message
					'href' => $this->mTitle->getLocalUrl( 'action=' . $mode )
				);
			}
			// This is instead of SkinTemplateTabs - which uses a flat array
			wfRunHooks( 'SkinTemplateNavigation', array( &$this, &$links ) );

		// If it's not content, it's got to be a special page
		} else {
			$links['namespaces']['special'] = array(
				'class' => 'selected',
				'text' => wfMsg( 'vector-namespace-special' ),
				'href' => $wgRequest->getRequestURL()
			);
		}

		// Gets list of language variants
		$variants = $wgContLang->getVariants();
		// Checks that language conversion is enabled and variants exist
		if( !$wgDisableLangConversion && count( $variants ) > 1 ) {
			// Gets preferred variant
			$preferred = $wgContLang->getPreferredVariant();
			// Loops over each variant
			foreach( $variants as $code ) {
				// Gets variant name from language code
				$varname = $wgContLang->getVariantname( $code );
				// Checks if the variant is marked as disabled
				if( $varname == 'disable' ) {
					// Skips this variant
					continue;
				}
				// Appends variant link
				$links['variants'][] = array(
					'class' => ( $code == $preferred ) ? 'selected' : false,
					'text' => $varname,
					'href' => $this->mTitle->getLocalURL( '', $code )
				);
			}
		}

		wfProfileOut( __METHOD__ );

		return $links;
	}
}

/**
 * QuickTemplate class for Vector skin
 * @ingroup Skins
 */
class lnsTemplate extends QuickTemplate {

	/* Members */

	/**
	 * @var Cached skin object
	 */
	var $skin;

	/* Functions */
	/**
	 * returns an Array of Titles for a Category ...
	 * @param unknown_type $cat String - Category Name	 * 
	 */
	private function get_title_by_category($cat)
			{
				$list = array();
				$dbr  = &wfGetDB(DB_SLAVE);
				$cl   = $dbr->tableName('categorylinks');						
				$cat  = $dbr->addQuotes($cat);			
				$res  = $dbr->select($cl, 'cl_from', "cl_to = $cat", __METHOD__);
				while ($row = $dbr->fetchRow($res)) $list[] = Title::newFromID($row[0])->getPrefixedText();
				return $list;			
			}	 		

	/**
	 * Outputs the entire contents of the XHTML page
	 */
	public function execute() {
		global $wgRequest, $wgOut, $wgContLang;

		$this->skin = $this->data['skin'];
		$action = $wgRequest->getText( 'action' );

		// Build additional attributes for navigation urls
		$nav = $this->skin->buildNavigationUrls();
		$indexpath = wfScript()."/";
		foreach ( $nav as $section => $links ) {
			foreach ( $links as $key => $link ) {
				$xmlID = $key;
				if ( isset( $link['context'] ) && $link['context'] == 'subject' ) {
					$xmlID = 'ca-nstab-' . $xmlID;
				} else if ( isset( $link['context'] ) && $link['context'] == 'talk' ) {
					$xmlID = 'ca-talk';
				} else {
					$xmlID = 'ca-' . $xmlID;
				}
				$nav[$section][$key]['attributes'] =
					' id="' . Sanitizer::escapeId( $xmlID ) . '"';
			 	if ( $nav[$section][$key]['class'] ) {
					$nav[$section][$key]['attributes'] .=
						' class="' . htmlspecialchars( $link['class'] ) . '"';
					unset( $nav[$section][$key]['class'] );
			 	}
				// We don't want to give the watch tab an accesskey if the page
				// is being edited, because that conflicts with the accesskey on
				// the watch checkbox.  We also don't want to give the edit tab
				// an accesskey, because that's fairly superfluous and conflicts
				// with an accesskey (Ctrl-E) often used for editing in Safari.
			 	if (
					in_array( $action, array( 'edit', 'submit' ) ) &&
					in_array( $key, array( 'edit', 'watch', 'unwatch' ) )
				) {
			 		$nav[$section][$key]['key'] =
						$this->skin->tooltip( $xmlID );
			 	} else {
			 		$nav[$section][$key]['key'] =
						$this->skin->tooltipAndAccesskey( $xmlID );
			 	}
			}
		}
		$this->data['namespace_urls'] = $nav['namespaces'];
		$this->data['view_urls'] = $nav['views'];
		$this->data['action_urls'] = $nav['actions'];
		$this->data['variant_urls'] = $nav['variants'];
		// Build additional attributes for personal_urls
		foreach ( $this->data['personal_urls'] as $key => $item) {
			$this->data['personal_urls'][$key]['attributes'] =
				' id="' . Sanitizer::escapeId( "pt-$key" ) . '"';
			if ( isset( $item['active'] ) && $item['active'] ) {
				$this->data['personal_urls'][$key]['attributes'] .=
					' class="active"';
			}
			$this->data['personal_urls'][$key]['key'] =
				$this->skin->tooltipAndAccesskey('pt-'.$key);
		}

		// Generate additional footer links
		$footerlinks = array(
			'info' => array(
				'lastmod',
				'viewcount',
				'numberofwatchingusers',
				'credits',
				'copyright',
				'tagline',
			),
			'places' => array(
				'privacy',
				'about',
				'disclaimer',
			),
		);
		// Reduce footer links down to only those which are being used
		$validFooterLinks = array();
		foreach( $footerlinks as $category => $links ) {
			$validFooterLinks[$category] = array();
			foreach( $links as $link ) {
				if( isset( $this->data[$link] ) && $this->data[$link] ) {
					$validFooterLinks[$category][] = $link;
				}
			}
		}
		// Reverse horizontally rendered navigation elements
		if ( $wgContLang->isRTL() ) {
			$this->data['view_urls'] =
				array_reverse( $this->data['view_urls'] );
			$this->data['namespace_urls'] =
				array_reverse( $this->data['namespace_urls'] );
			$this->data['personal_urls'] =
				array_reverse( $this->data['personal_urls'] );
		}
		// Output HTML Page
		//$this->html( 'headelement' );
  global $wgUser;
  $anon = $wgUser->isAnon() ? 'anonymous ' : '';
  echo str_replace(
     "<body class=\"",
     "<body class=\"$anon",
     $this->data['headelement']
  );
?>
<div id="wrapper">
<div id="logo">		  
</div>
<div id="filler">
    <h4>A member of Literacy Aotearoa Inc.</h4>
</div><!-- filler -->
<div id="filler2">
</div><!-- filler -->
<div id="outernav">
<div id="nav">
<!--
* Main Menu displays all Titles with Category: Public 
* !!! Important Make sure menu titles are short enough or else it will mess up the whole layout!!!
* Submenu displays all Titles with Category : as Main Menu item.
*eg: About Us - main menu item with Category : Public .Our Logo and Sponsors are set to Category: About Us 
*    About Us menu item will have sub menu items Our Logo and Sponsers  
* -->
<?php $menubar = $this->get_title_by_category('Public');	  
	  if ( is_array( $menubar ) && !(empty($menubar))):?>
	  <?php
	    for($varj=0;$varj<count($menubar)&& $varj<=6;$varj++)
		 {
		 	$finalMenuBar[$varj] = $menubar[$varj];
		 }	  		
		 
	  /*
	   * Menu item order is being set into $finalMenuBar array. 
	  */
	  for($varj=0;$varj<count($menubar)&& $varj<=6;$varj++)
	  {	  	
		  	if($menubar[$varj] == "Literacy North Shore")
		  	{
		  		$finalMenuBar[0]=$menubar[$varj];
		  	}		  	
		  	elseif($menubar[$varj] == "About us")
		  	{
		  		$finalMenuBar[1]=$menubar[$varj];
		  	}		  	
		  	elseif($menubar[$varj] == "Contact Us")
		  	{	  	
		  		$finalMenuBar[2]=$menubar[$varj];
		  	}	  		
		  	elseif($menubar[$varj] == "Tutors")
		  	{
		  		$finalMenuBar[3]=$menubar[$varj];
		  	}	  		
		  	elseif($menubar[$varj] == "Resources")
		  	{
		  		$finalMenuBar[4]=$menubar[$varj];
		  	}
		  	elseif($menubar[$varj]=="Literacy links")
		  	{	
		  		$finalMenuBar[5] = $menubar[$varj];
		  	}
		  	else 
		  	{
		  		$finalMenuBar[$varj] = $menubar[$varj];
		  	}
		  			  			  			  	
	   }//for loop ends for the
		 	
	   endif;
	   		  	
	  ?>
	  <?php if ( is_array( $finalMenuBar ) && !(empty($finalMenuBar))):?>
	  <ul id="MenuBar1" class="MenuBarHorizontal">  
	  <?php for($vari=0;$vari<count($finalMenuBar)&& $vari<=6;$vari++)
			  {?>  	<?php $submenu = $this->get_title_by_category(str_replace(' ', '_',$finalMenuBar[$vari]));
					  $submenuexists = false;
					  if(is_array($submenu)&& !(empty($submenu))):$submenuexists=true;endif;?>
					  <li><a <?php if($submenuexists): echo 'class="MenuBarItemSubmenu"'; endif;?>href="<?php echo $indexpath.htmlspecialchars( $finalMenuBar[$vari] ) ?>"><?php echo htmlspecialchars($finalMenuBar[$vari]) ?></a>
					  <?php if($submenuexists):?>
					  	 	<ul>
					  	 	<?php for($varj=0;$varj<count($submenu);$varj++)
			  					  {?>
		        					<li><a href="<?php echo $indexpath.htmlspecialchars( strval($submenu[$varj])) ?>"><?php echo htmlspecialchars($submenu[$varj]) ?></a></li>        					
		        			<?php }?>
		      				</ul>      				
					  	 <?php endif;?>			  	
					  </li> 
		 <?php }?>
 	 </ul>	
     <?php else: ?>
	  <?php echo $finalMenuBar; /* Allow raw HTML block to be defined by extensions */ 
 	  endif; ?>
	  
</div>
<!-- /nav-->
</div>
<!-- /outernav -->
<div id = "mbody">
	<div id="wrap-page-base" >
		<div id="mw-page-base" class="noprint">
		<!-- panel -->
			<!--  <div id="mw-panel" class="noprint">				
				<?php /* $this->renderPortals( $this->data['sidebar'] ); */?>
			</div>--> 
		<!-- /panel -->
		</div> 
	
		<div id="mw-head-base" class="noprint">
			<!-- header -->
		<div id="mw-head" class="noprint">
			<?php $this->renderNavigation( 'PERSONAL' ); ?>
			<div id="left-navigation">
				<?php $this->renderNavigation( array( 'NAMESPACES', 'VARIANTS' ) ); ?>
			</div>
			<div id="right-navigation">
				<?php				
				$this->renderNavigation( array( 'VIEWS', 'ACTIONS', 'SEARCH' ) );			
				?>
			</div>
		</div><!-- /mw-header -->		
		</div><!-- /mw-head-base -->
		</div><!-- /mw-wrap-page-base -->
	<div id="middle">
			<!-- content -->
		<div id="content" <?php $this->html('specialpageattributes') ?>>
			<a id="top"></a>
			<div id="mw-js-message" style="display:none;"<?php $this->html('userlangattributes') ?>></div>
			<?php if ( $this->data['sitenotice'] ): ?>
			<!-- sitenotice -->
			<div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div>
			<!-- /sitenotice -->
			<?php endif; ?>
			<!-- firstHeading -->
			<h1 id="firstHeading" class="firstHeading"><?php $this->html( 'title' ) ?></h1>
			<!-- /firstHeading -->
			<!-- bodyContent -->
			<div id="bodyContent">
				<!-- tagline -->
				<h3 id="siteSub"><?php $this->msg( 'tagline' ) ?></h3>
				<!-- /tagline -->
				<!-- subtitle -->
				<div id="contentSub"<?php $this->html('userlangattributes') ?>><?php $this->html( 'subtitle' ) ?></div>
				<!-- /subtitle -->
				<?php if ( $this->data['undelete'] ): ?>
				<!-- undelete -->
				<div id="contentSub2"><?php $this->html( 'undelete' ) ?></div>
				<!-- /undelete -->
				<?php endif; ?>
				<?php if($this->data['newtalk'] ): ?>
				<!-- newtalk -->
				<div class="usermessage"><?php $this->html( 'newtalk' )  ?></div>
				<!-- /newtalk -->
				<?php endif; ?>
				<?php if ( $this->data['showjumplinks'] ): ?>
				<!-- jumpto -->
				<div id="jump-to-nav">
					<?php $this->msg( 'jumpto' ) ?> <a href="#mw-head"><?php $this->msg( 'jumptonavigation' ) ?></a>,
					<a href="#p-search"><?php $this->msg( 'jumptosearch' ) ?></a>
				</div>
				<!-- /jumpto -->
				<?php endif; ?>
				<!-- bodytext -->
				<?php $this->html( 'bodytext' ) ?>
				<!-- /bodytext -->
				<?php if ( $this->data['catlinks'] ): ?>
				<!-- catlinks -->
				<?php $this->html( 'catlinks' ); ?>
				<!-- /catlinks -->
				<?php endif; ?>
				<?php if ( $this->data['dataAfterContent'] ): ?>
				<!-- dataAfterContent -->
				<?php $this->html( 'dataAfterContent' ); ?>
				<!-- /dataAfterContent -->
				<?php endif; ?>
				<div class="visualClear"></div>
			</div>
			<!-- /bodyContent -->
		</div>
		<!-- /content -->			
			</div>
			<!-- /middle content -->
		</div>
		<!-- /mbody -->
		<div id="footer"<?php $this->html('userlangattributes') ?>>
			<ul id="footer-icons" class="noprint">
				<?php if ( $this->data['poweredbyico'] ): ?>
				<li id="footer-icon-poweredby"><?php $this->html( 'poweredbyico' ) ?></li>
				<?php endif; ?>
				<?php if ( $this->data['copyrightico'] ): ?>
				<li id="footer-icon-copyright"><?php $this->html( 'copyrightico' ) ?></li>
				<?php endif; ?>
			</ul>						
			<div style="clear:both"></div>
		</div>
		<!-- /footer -->
		<!-- fixalpha -->
		<script type="<?php $this->text('jsmimetype') ?>"> if ( window.isMSIE55 ) fixalpha(); </script>
		<!-- /fixalpha -->
		<?php $this->html( 'bottomscripts' ); /* JS call to runBodyOnloadHook */ ?>
		<?php $this->html( 'reporttime' ) ?>
		<?php if ( $this->data['debug'] ): ?>
		<!-- Debug output: <?php $this->text( 'debug' ); ?> -->
		<?php endif; ?>
		<!--  Spry Menu Bar -->
		<script type="text/javascript">
			var MenuBar1 = new Spry.Widget.MenuBar("MenuBar1", {imgDown:"lns/SpryAssets/SpryMenuBarDownHover.gif", imgRight:"lns/SpryAssets/SpryMenuBarRightHover.gif"});
		</script>
		</div>
		<!-- /Wrapper -->
	</body>
</html>

<?php
	}
	/**
	 * Render one or more navigations elements by name, automatically reveresed
	 * when UI is in RTL mode
	 */
	private function renderNavigation( $elements ) {
		global $wgContLang, $wgVectorUseSimpleSearch, $wgStylePath;

		// If only one element was given, wrap it in an array, allowing more
		// flexible arguments
		if ( !is_array( $elements ) ) {
			$elements = array( $elements );
		// If there's a series of elements, reverse them when in RTL mode
		} else if ( $wgContLang->isRTL() ) {
			$elements = array_reverse( $elements );
		}
		// Render elements
		foreach ( $elements as $name => $element ) {
			echo "\n<!-- {$name} -->\n";
			switch ( $element ) {
				case 'NAMESPACES':
?>
<div id="p-namespaces" class="vectorTabs<?php if ( count( $this->data['namespace_urls'] ) == 0 ) echo ' emptyPortlet'; ?>">
	<h5><?php $this->msg('namespaces') ?></h5>
	<ul<?php $this->html('userlangattributes') ?>>
		<?php foreach ($this->data['namespace_urls'] as $key => $link ): ?>
			<li <?php echo $link['attributes'] ?>><a href="<?php echo htmlspecialchars( $link['href'] ) ?>" <?php echo $link['key'] ?>><span><?php echo htmlspecialchars( $link['text'] ) ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
				break;
				case 'VARIANTS':
?>
<div id="p-variants" class="vectorMenu<?php if ( count( $this->data['variant_urls'] ) == 0 ) echo ' emptyPortlet'; ?>">
	<h5><span><?php $this->msg('variants') ?></span><a href="#"></a></h5>
	<div class="menu">
		<ul<?php $this->html('userlangattributes') ?>>
			<?php foreach ($this->data['variant_urls'] as $key => $link ): ?>
				<li<?php echo $link['attributes'] ?>><a href="<?php echo htmlspecialchars( $link['href'] ) ?>" <?php echo $link['key'] ?>><?php echo htmlspecialchars( $link['text'] ) ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php
				break;
				case 'VIEWS':
?>
<div id="p-views" class="vectorTabs<?php if ( count( $this->data['view_urls'] ) == 0 ) echo ' emptyPortlet'; ?>">
	<h5><?php $this->msg('views') ?></h5>
	<ul<?php $this->html('userlangattributes') ?>>
		<?php foreach ($this->data['view_urls'] as $key => $link ): ?>
			<li<?php echo $link['attributes'] ?>><a href="<?php echo htmlspecialchars( $link['href'] ) ?>" <?php echo $link['key'] ?>><?php echo (array_key_exists('img',$link) ?  '<img src="'.$link['img'].'" alt="'.$link['text'].'" />' : '<span>'.htmlspecialchars( $link['text'] ).'</span>') ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
				break;
				case 'ACTIONS':
?>
<div id="p-cactions" class="vectorMenu<?php if ( count( $this->data['action_urls'] ) == 0 ) echo ' emptyPortlet'; ?>">
	<h5><span><?php $this->msg('actions') ?></span><a href="#"></a></h5>
	<div class="menu">
		<ul<?php $this->html('userlangattributes') ?>>
			<?php foreach ($this->data['action_urls'] as $key => $link ): ?>
				<li<?php echo $link['attributes'] ?>><a href="<?php echo htmlspecialchars( $link['href'] ) ?>" <?php echo $link['key'] ?>><?php echo htmlspecialchars( $link['text'] ) ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php
				break;
				case 'PERSONAL':
?>
<div id="p-personal" class="<?php if ( count( $this->data['personal_urls'] ) == 0 ) echo ' emptyPortlet'; ?>">
	<h5><?php $this->msg('personaltools') ?></h5>
	<ul<?php $this->html('userlangattributes') ?>>
		<?php foreach($this->data['personal_urls'] as $key => $item): ?>
		<?php if($item['attributes'] == 'pt')?>
			<li <?php echo $item['attributes'] ?>><a href="<?php echo htmlspecialchars($item['href']) ?>"<?php echo $item['key'] ?><?php if(!empty($item['class'])): ?> class="<?php echo htmlspecialchars($item['class']) ?>"<?php endif; ?>><?php echo htmlspecialchars($item['text']) ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
				break;
				case 'SEARCH':
?>
<div id="p-search">
	<h5<?php $this->html('userlangattributes') ?>><label for="searchInput"><?php $this->msg( 'search' ) ?></label></h5>
	<form action="<?php $this->text( 'wgScript' ) ?>" id="searchform">
		<input type='hidden' name="title" value="<?php $this->text( 'searchtitle' ) ?>"/>
		<?php if ( $wgVectorUseSimpleSearch ): ?>
		<div id="simpleSearch">
			<input id="searchInput" name="search" type="text" <?php echo $this->skin->tooltipAndAccesskey( 'search' ); ?> <?php if( isset( $this->data['search'] ) ): ?> value="<?php $this->text( 'search' ) ?>"<?php endif; ?> />
			<button id="searchButton" type='submit' name='button' <?php echo $this->skin->tooltipAndAccesskey( 'search-fulltext' ); ?>>&nbsp;</button>
		</div>
		<?php else: ?>
		<input id="searchInput" name="search" type="text" <?php echo $this->skin->tooltipAndAccesskey( 'search' ); ?> <?php if( isset( $this->data['search'] ) ): ?> value="<?php $this->text( 'search' ) ?>"<?php endif; ?> />
		<input type='submit' name="go" class="searchButton" id="searchGoButton"	value="<?php $this->msg( 'searcharticle' ) ?>"<?php echo $this->skin->tooltipAndAccesskey( 'search-go' ); ?> />
		<input type="submit" name="fulltext" class="searchButton" id="mw-searchButton" value="<?php $this->msg( 'searchbutton' ) ?>"<?php echo $this->skin->tooltipAndAccesskey( 'search-fulltext' ); ?> />
		<?php endif; ?>
	</form>
</div>
<?php

				break;
			}
			echo "\n<!-- /{$name} -->\n";
		}
	}
}
