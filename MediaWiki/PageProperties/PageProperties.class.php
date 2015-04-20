<?php
class PageProperties extends WikiPage {

	/**
	 * Contstruct as a normal article with no differences
	 */
	function __construct( $param ) {
		global $wgHooks;

		// This hook allows us to change the class of article to one of our classes
		$wgHooks['InitializeArticleMaybeRedirect'][] = $this;

		// Allow sub-classes to have an edit method that can add its own fields
		$wgHooks['EditPage::showEditForm:fields'][] = array( $this, 'onShowEditFormFields' );

		// Allow sub-classes to have a save method that can store its fields into the page_props
		$wgHooks['ArticleSave'][] = $this;

		return parent::__construct( $param );
	}


	/**
	 * WikiPage uses a factory class to determine which sub-class a created instance should be based on title
	 * - this method uses a global array of namespaces for which our classes should be used
	 */
	public static function factory( Title $title ) {
		global $wgPagePropertyNamespaces;

		// Determine page class name from title and namespace
		$ns = $title->getNamespace();
		$class = array_key_exists( $ns, $wgPagePropertyNamespaces[$ns] )? $wgPagePropertyNamespaces[$ns] : false;

		// If not one of our namespaces, return a normal Article instance
		if( $class === false ) return new Article( $title );

		// Otherwise import the page class definition
		require_once( dirname( __FILE__ ) . "$class.class.php" );

		// And return an instance of it
		return new $classname( $title );
	}

	/**
	 * This hook passes the current Article singleton by reference so we can change it to a PropertyPage
	 * if the factory method determines it's one of our classes
	 */
	public static function onInitializeArticleMaybeRedirect( $title, $request, $ignoreRedirect, $target, &$article ) {
		$ap = self::factory( $title );
		if( !is_a( $ap, 'Article' ) ) $article = $ap;
		return true;
	}

	/**
	 * Executed for showEditForm hook of our article types and calls the sub-class edit function if exists
	 */
	static function onShowEditFormFields( &$editpage, $out ) {
		$ap = self::factory( $editpage->getArticle()->getTitle() );
		return $ap->edit( $editpage, $out );
	}

	/**
	 * Executed for ArticleSave hook of our article types and calls the sub-class save function if exists
	 */
	static function onArticleSave( &$article, &$user, &$text, &$summary, $minor, $watchthis, $sectionanchor, &$flags, &$status ) {
		$ap = self::factory( $article->getTitle() );
		return $ap->save();
	}

	/**
	 * Default methods for view/edit and save incase the sub-classes don't define them
	 */
	function view() {
		$article = new Article( $this->getTitle() );
		return $article->render();
	}
	function edit( &$editpage, $out ) {
		return true;
	}
	function save() {
		return true;
	}

	/**
	 * Add a properties method to interface with the article's page_props
	 */
	public function properties( $props = array() ) {
		if( $id = $this->getArticleId() ) {
			$changed = false;
			$dbr = wfGetDB( DB_SLAVE );
			$dbw = false;

			// If the input array is empty, return all properties
			if( count( $props ) == 0 ) {
				$res = $dbr->select( 'page_props', 'pp_propname,pp_value', array( 'pp_page' => $id ) );
				while( $row = $dbr->fetchRow( $res ) ) $props[$row[0]] = $row[1];
				$dbr->freeResult( $res );
			}

			// Otherwise return only those specified
			else {
				foreach( $props as $k => $v1 ) {

					// Read the current value of this property
					$key = "ap_$k";
					$v0 = $dbr->selectField( 'page_props', 'pp_value', array( 'pp_page' => $id, 'pp_propname' => $key ) );

					// If a key has a null value, then read the value if there was one
					if( $v1 === null && $v0 !== false ) $v1 = $v0;

					// Otherwise set the value if it's changed
					elseif( $v0 !== $v1 ) {

						// Get a db connection to write to if we don't have one yet
						if( $dbw === false ) $dbw = wfGetDB( DB_MASTER );

						// Update the existing value in the props table
						if( $v0 === false ) {
							$dbw->insert( 'page_props', array( 'pp_page' => $id, 'prop_name' => $key, 'pp_value' => $v ) );
						}

						// Create this value in the props table
						else {
							$dbw->update( 'page_props', array( 'pp_value' => $v ), array( 'pp_page' => $id, 'pp_propname' => $key ) );
						}

						// add to array that will be sent ot the change event
						$changed[$k] = array( $v0, $v1 );
					}
				}
			}

			if( $changed ) wfRunHook( 'ArticlePropertiesChanged', array( &$this, &$changed ) );
		}

		return $props;
	}

	/**
	 * Add a static query method to select a list of articles by SQL conditions and options
	 */
	public static function query( $type, $conds, $options = null ) {
		$list = array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props', 'pp_page', $conds, $options );
		while( $row = $dbr->fetchRow( $res ) ) $list[] = Title::newFromId( $row[0] );
		$dbr->freeResult( $res );
		return $list;
	}

	/**
	 * Add a static method to render results in a table format
	 */
	public static function table( &$titles, $atts = array(), $fields = false ) {

		// Open the table
		$html = "<table";
		if( array_key_exists( 'class', $atts ) ) $atts['class'] .= ' ap_results';
		else $atts['class'] = 'ap_results';
		foreach( $atts as $k => $v ) $html .= " $k=\"$v\"";
		$html .= ">\n";

		// Get fields from the first title if none specified
		if( !is_array( $fields ) ) {
			$ap = new ArticleProperties( $titles[0] );
			$fields = array_keys( $ap->properties() );
		}

		// Render the table header
		$html .= "<tr>";
		foreach( $fields as $field ) $html .= "<th>$field</th>";
		$html .= "</tr>\n";

		// Render the rows
		$html .= "<tr>";
		foreach( $titles as $title ) {
			$ap = new ArticleProperties( $title );
			foreach( $fields as $field ) {
				$prop = array( $field => null );
				$ap->properties( $prop );
				$val = $prop[$field];
				$html .= "<td>$val</td>";
			}
		}
		$html .= "</tr>\n";

		// Close the table and return content
		$html .= "</table>\n";
		return $html;
	}

	/**
	 * Get a value for a field from the current article
	 */
	function getValue( $name, $default = false ) {
		if( !$this->exists() ) return $default;
		$prop = $this->properties( array( $name => null ) );
		return $prop[$name] ? $prop[$name] : $default;
	}

	/**
	 * Render a label element for an input
	 */
	function label( $label, $name = false ) {
		if( $name === false ) $name = ucfirst( $label );
		return "<label for=\"wp$name\">" . wfMsg( "znazza-$label" ) . "</label>";
	}

	/**
	 * Render an input element with current value if the article already exists
	 */
	function input( $name, $default = '' ) {
		$value = $this->getValue( $name, $default );
		return "<input type=\"text\" value=\"$value\" name=\"wp$name\" id=\"wp$name\" />";
	}

	/**
	 * Render combined label and input as a table row
	 */
	function inputRow( $label, $name = false, $default = '', $extra = '' ) {
		$label = $this->label( $label, $name );
		$input = $this->input( $name, $default );
		return "<tr><td>$label</td><td>$input$extra</td></tr>";
	}

	/**
	 * Render a select list with supplied options list and selected/default value from page_props if any
	 */
	function select( $name, $options, $first = '', $default = '' ) {
		if( $first === false ) $first = '';
		elseif( $first == '' ) $first = "<option />";
		else $first = "<option value=\"\">$first</option>";
		$value = $this->getValue( $name, $default );
		$html = "<select name=\"wp$name\" id=\"wp$name\">$first";
		foreach( $options as $opt ) {
			$text = wfMsg( "znazza-$opt" );
			$selected = $value == $opt ? ' selected="yes"' : '';
			$html .= "<option value=\"$opt\"$selected>$text</option>";
		}
		return $html . "</select>";
	}

	/**
	 * Render a radio option group with supplied options list and selected/default value from page_props if any
	 */
	function options( $name, $options, $default = '' ) {
		$value = $this->getValue( $name, $default );
		$html = '';
		foreach( $options as $opt ) {
			$text = wfMsg( "znazza-$opt" );
			$checked = $value == $opt ? ' checked="yes"' : '';
			$html .= "<input type=\"radio\" name=\"wp$name\" value=\"$opt\"$checked>$text</input>";
		}
		return $html;
	}


}
