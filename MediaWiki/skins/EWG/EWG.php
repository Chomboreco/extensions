<?php
/**
 * EWG Skin based on MonoBook
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
 *
 * @todo document
 * @file
 * @ingroup Skins
 */

if( !defined( 'MEDIAWIKI' ) ) die( -1 );

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @todo document
 * @ingroup Skins
 */
class SkinEWG extends SkinTemplate {
	var $skinname = 'ewg', $stylename = 'ewg',
		$template = 'EWGTemplate', $useHeadElement = true;

	function setupSkinUserCss( OutputPage $out ) {
		global $wgHandheldStyle, $wgStyleVersion, $wgJsMimeType, $wgStylePath;

		parent::setupSkinUserCss( $out );

		// Append to the default screen common & print styles...
		$out->addStyle( 'ewg/main.css', 'screen' );

	}
}

/**
 * @todo document
 * @ingroup Skins
 */
class EWGTemplate extends QuickTemplate {
	var $skin;
	/**
	 * Template filter callback for MonoBook skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 *
	 * @access private
	 */
	function execute() {
		global $wgRequest;

		$this->skin = $skin = $this->data['skin'];
		$action = $wgRequest->getText( 'action' );

		// Suppress warnings to prevent notices about missing indexes in $this->data
		wfSuppressWarnings();

		$this->html( 'headelement' );
?><body<?php if($this->data['body_ondblclick']) { ?> ondblclick="<?php $this->text('body_ondblclick') ?>"<?php } ?>
<?php if($this->data['body_onload']) { ?> onload="<?php $this->text('body_onload') ?>"<?php } ?>
 class="mediawiki <?php $this->text('nsclass') ?> <?php $this->text('dir') ?> <?php $this->text('pageclass') ?>">
	<div id="globalWrapper">
		<div id="p-cactions" class="portlet">
			<h5><?php $this->msg('views') ?></h5>
			<div class="pBody">
				<ul>
		<?php		foreach($this->data['content_actions'] as $key => $tab) {
						echo '
					 <li id="ca-' . Sanitizer::escapeId($key).'"';
						if( $tab['class'] ) {
							echo ' class="'.htmlspecialchars($tab['class']).'"';
						}
						echo'><a href="'.htmlspecialchars($tab['href']).'"';
						# We don't want to give the watch tab an accesskey if the
						# page is being edited, because that conflicts with the
						# accesskey on the watch checkbox.  We also don't want to
						# give the edit tab an accesskey, because that's fairly su-
						# perfluous and conflicts with an accesskey (Ctrl-E) often
						# used for editing in Safari.
						if( in_array( $action, array( 'edit', 'submit' ) )
						&& in_array( $key, array( 'edit', 'watch', 'unwatch' ))) {
							echo $skin->tooltip( "ca-$key" );
						} else {
							echo $skin->tooltipAndAccesskey( "ca-$key" );
						}
						echo '>'.htmlspecialchars($tab['text']).'</a></li>';
					} ?>
				</ul>
			</div>
		</div>
	<?php 			$this->searchBox(); ?>
		<div class="portlet" id="p-personal">
			<h5><?php $this->msg('personaltools') ?></h5>
			<div class="pBody">
				<ul>
	<?php 			foreach($this->data['personal_urls'] as $key => $item) { ?>
					<li id="pt-<?php echo Sanitizer::escapeId($key) ?>"<?php
						if ($item['active']) { ?> class="active"<?php } ?>><a href="<?php
					echo htmlspecialchars($item['href']) ?>"<?php echo $skin->tooltipAndAccesskey('pt-'.$key) ?><?php
					if(!empty($item['class'])) { ?> class="<?php
					echo htmlspecialchars($item['class']) ?>"<?php } ?>><?php
					echo htmlspecialchars($item['text']) ?></a></li>
	<?php			} ?>
				</ul>
			</div>
		</div>
		<div class="portlet" id="p-logo">
			<a style="background-image: url(<?php $this->text('logopath') ?>);" <?php
				?>href="<?php echo htmlspecialchars($this->data['nav_urls']['mainpage']['href'])?>"<?php
				echo $skin->tooltipAndAccesskey('n-mainpage') ?>></a>
		</div>
		<table id="column-content" width="!00%" cellpadding="0" cellspacing="0">
			<tr>
				<td colspan="2">
					<div id="top-div" />
				</td>
			<tr>
				 <td id="column-one">
			<script type="<?php $this->text('jsmimetype') ?>"> if (window.isMSIE55) fixalpha(); </script>
		
<?php
# MediaWiki:Sidebar
global $wgUser,$wgTitle,$wgParser;
$title = 'sidebar';
$article = new Article( Title::newFromText( $title, NS_MEDIAWIKI ) );
$text = $article->fetchContent();
if ( empty( $text ) ) $text = wfMsg( $title );
if ( is_object( $wgParser ) ) { $psr = $wgParser; $opt = $wgParser->mOptions; }
else { $psr = new Parser; $opt = NULL; }
if ( !is_object( $opt ) ) $opt = ParserOptions::newFromUser( $wgUser );
echo $psr->parse( $text, $wgTitle, $opt, true, true )->getText();
?>
				</td><!-- end of the left (by default at least) column -->
				<td id="content" width="100%">
					<a name="top" id="top"></a>
					<?php if($this->data['sitenotice']) { ?><div id="siteNotice"><?php $this->html('sitenotice') ?></div><?php } ?>
					<h1 class="firstHeading"><?php $this->html('title') ?></h1>
					<div id="bodyContent">
						<h3 id="siteSub"><?php $this->msg('tagline') ?></h3>
						<div id="contentSub"><?php $this->html('subtitle') ?></div>
						<?php if($this->data['undelete']) { ?><div id="contentSub2"><?php     $this->html('undelete') ?></div><?php } ?>
						<?php if($this->data['newtalk'] ) { ?><div class="usermessage"><?php $this->html('newtalk')  ?></div><?php } ?>
						<?php if($this->data['showjumplinks']) { ?><div id="jump-to-nav"><?php $this->msg('jumpto') ?> <a href="#column-one"><?php $this->msg('jumptonavigation') ?></a>, <a href="#searchInput"><?php $this->msg('jumptosearch') ?></a></div><?php } ?>
						<!-- start content -->
						<?php $this->html('bodytext') ?>
						<?php if($this->data['catlinks']) { $this->html('catlinks'); } ?>
						<!-- end content -->
						<div class="visualClear"></div>
						</div>
					</div>
				</td>
			</tr>
		</table>
		
		<div class="visualClear"></div>
		<div id="footer">
<?php
global $wgUser,$wgTitle,$wgParser;
$title = 'footer';
$article = new Article( Title::newFromText( $title, NS_MEDIAWIKI ) );
$text = $article->fetchContent();
if ( empty( $text ) ) $text = wfMsg( $title );
if ( is_object( $wgParser ) ) { $psr = $wgParser; $opt = $wgParser->mOptions; }
else { $psr = new Parser; $opt = NULL; }
if ( !is_object( $opt ) ) $opt = ParserOptions::newFromUser( $wgUser );
echo $psr->parse( $text, $wgTitle, $opt, true, true )->getText();
?>
			</div>
	</div>
<?php $this->html('bottomscripts'); /* JS call to runBodyOnloadHook */ ?>
<?php $this->html('reporttime') ?>
<?php if ( $this->data['debug'] ): ?>
<!-- Debug output:
<?php $this->text( 'debug' ); ?>

-->
<?php endif; ?>
</body></html>
<?php
	wfRestoreWarnings();
	} // end of execute() method

	/*************************************************************************************************/
	function searchBox() {
?>
	<div id="p-search" class="portlet">
		<h5><label for="searchInput"><?php $this->msg('search') ?></label></h5>
		<div id="searchBody" class="pBody">
			<form name="searchform" action="<?php $this->text('searchaction') ?>" id="searchform"><div>
				<input id="searchInput" name="search" type="text"<?php echo $this->skin->tooltipAndAccesskey('search');
					if( isset( $this->data['search'] ) ) {
						?> value="<?php $this->text('search') ?>"<?php } ?> />
				<input type='submit' name="fulltext" class="searchButton" id="mw-searchButton" value="Go"<?php echo $this->skin->tooltipAndAccesskey( 'search-fulltext' ); ?> />
				<input type='button' class="searchButton" value="Search" onClick="document.searchform.findthis.value=document.searchform.search.value;findinpage(document.searchform, frametosearch);"
				/>
				<input type='hidden' name='findthis' />
			</div></form>
		</div>
	</div>
<?php
	}

} // end of class


