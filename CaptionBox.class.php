<?php
class CaptionBox
{ 
	// All of the Regular Expressions we use to match videos
	// /s makes . match newlines
	const YOUTUBE_RE = "/<object(.*?)<embed.*? src=[\"']http:\/\/(www.)?youtube.com\/v\/(.*?)([&\?].*?)?[\"'](.*?)<\/object>/s";
	const BRIGHTCOVE_RE = "/brightcove(.*?)@videoPlayer([\"'] *value)?=[\"']?(\d+)(.*?)<\/object>/is"; 
	const BLIP_RE = "/<embed(.*?)src=[\"']http:\/\/blip.tv\/play\/([a-zA-Z0-9]*)(.*?)[\"'](.*?)<\/embed>(.*?<\/object>)?/s";
	const JW_RE = "/<object.*?file=(.*?)[&\"].*?<\/object>/s";
	const OOYALA_RE = "/<script.*?src=\"https?:\/\/player.ooyala.com\/player.js\?(.*?)embedCode=(.*?)[\"&].*?<\/noscript>/s";
	const YOUTUBE_IFRAME_RE = "/<iframe.*?youtube.com\/embed\/(.*?)['\"?].*?<\/iframe>/";
	const VIMEO_RE = "/<iframe.*?src=['\"]http:\/\/player.vimeo.com\/video\/(\d+).*?<\/iframe>/s";
	const SOUNDCLOUD_RE = "/<object.*?api.soundcloud.com%2Ftracks%2F(\d+).*?<\/object>/s";
	
	const YOUTUBE_PLATFORM = 1;
	const VIMEO_PLATFORM = 2;
	const BRIGHTCOVE_PLATFORM = 3;
	const BLIP_PLATFORM = 4;
	const OOYALA_PLATFORM = 5;
	const SOUNDCLOUD_PLATFORM = 6;
	const SELF_PLATFORM = 7;
	
	const OOYALA_JS_INSERT = "callback=st_ooyala_callback&";
	
	var $plugin_prefix;
	var $plugin_name;
	var $plugin_dir;
	
	function CaptionBox($pp, $pn, $pd) {
		$this->plugin_prefix = $pp;
		$this->plugin_name = $pn;
		$this->plugin_dir = $pd;
		return true;
	}
	
	function activate() {
		if( get_option("captionbox_default_height") === false )
			add_option("captionbox_default_height", 210);
			
		if( get_option("captionbox_initial_state") === false )
			add_option("captionbox_initial_state", "open");
			
		if( get_option("captionbox_load_jquery") === false )
			add_option("captionbox_load_jquery", "yes");
	}
	
	function deactivate() {
	  unregister_setting("captionbox_options", "captionbox_transcript_path");
		unregister_setting('captionbox_options', 'captionbox_player_margin');
		unregister_setting("captionbox_options", "captionbox_default_height");
		unregister_setting("captionbox_options", "captionbox_initial_state");
		unregister_setting("captionbox_options", "captionbox_load_jquery");
	}
	
	
	function filter_the_content($content) {
		$content = $this->filter_videos($content, self::YOUTUBE_RE, self::YOUTUBE_PLATFORM, 3);
		$content = $this->filter_videos($content, self::BRIGHTCOVE_RE, self::BRIGHTCOVE_PLATFORM, 3);
		$content = $this->filter_videos($content, self::BLIP_RE, self::BLIP_PLATFORM, 2);
		$content = $this->filter_videos($content, self::JW_RE, self::SELF_PLATFORM, 1);
		$content = $this->filter_videos($content, self::OOYALA_RE, self::OOYALA_PLATFORM, 2);
		$content = $this->filter_videos($content, self::YOUTUBE_IFRAME_RE, self::YOUTUBE_PLATFORM, 1);
		$content = $this->filter_videos($content, self::SOUNDCLOUD_RE, self::SOUNDCLOUD_PLATFORM, 1);
		$content = $this->filter_videos($content, self::VIMEO_RE, self::VIMEO_PLATFORM, 1);
		return $content;
	}
	
	function filter_videos($content, $re, $platform, $match_num) {
		$matches = array();
		preg_match_all($re, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		
		$global_offset = 0;
		foreach($matches as $match) {
			$video_id = $match[$match_num][0];
			
			// Check for overwriting of video id
			if( $platform == self::SELF_PLATFORM ) {
			  $stid_matches = array();
			  $num_stid_matches = preg_match("/ data-stid=['\"](.*?)['\"] /", $match[0][0], $stid_matches);
			  
			  if( $stid_matches > 0 ) {
			    $video_id = $stid_matches[1];
			    $video_id = str_replace('STtranscriptEmbed_', '', $video_id);
			    $video_id = str_replace('7_', '', $video_id);
		    } else {
				  $video_id = sha1(basename($video_id));
			  }
			}
			
			if( $platform == self::OOYALA_PLATFORM && strpos($match[0][0], "callback") === false ) {
				// Add in javascript callback after all url params
				$offset = $global_offset + $match[1][1];
				$content = substr($content, 0, $offset) . self::OOYALA_JS_INSERT . substr($content, $offset);
				$global_offset += strlen(self::OOYALA_JS_INSERT);
			}
			
			$offset = $global_offset + $match[0][1] + strlen($match[0][0]);

			// Add in SpeakerText text embed right after video
			$embed_code = $this->get_text_embed($platform, $video_id);
			$content = substr($content, 0, $offset) . $embed_code . substr($content, $offset);
			$global_offset += strlen($embed_code);
		}
		
		return $content;
	}
	
	function get_text_embed($platform_id, $video_id) {
	  $tpath = get_option('captionbox_transcript_path');
		$transcript_id = $platform_id . "-" . $video_id;
		$filename = trailingslashit( $tpath ) . $transcript_id . ".html";
		
		if( file_exists( $filename ) ) {
		  $response = file_get_contents( $filename );
		
		  if( $response !== false )
		    return $response;
    }
    
    return "";
	}
	
	function add_captionbox_scripts() {		
		$is = get_option('captionbox_initial_state', 'open');
		$dh = get_option('captionbox_default_height', 210);
		$lj = get_option('captionbox_load_jquery', 'yes');
		
		if( $lj == "no" ) {
			wp_enqueue_script('captionbox', $this->plugin_dir . '/cbox/jquery.captionbox.js', array(), '1.0');
		}
		else {
			wp_enqueue_script('captionbox', $this->plugin_dir . '/cbox/jquery.captionbox.js', array('jquery'), '1.0');
		}
		
		
		if( $is == "" )
			$is = "open";
		
		if( $dh == "" )
			$dh = 210;
			
		echo "<script>\n";
		echo "	var STglobalSettings = {initialState: '".$is."', defaultHeight: ".$dh."};\n";
		echo "</script>\n";
	}
	
	function add_captionbox_styles() {
		wp_enqueue_style('st_player_style', $this->plugin_dir . '/cbox/captionbox.css');

		$pm = get_option('captionbox_player_margin');
		if( $pm != "" )
			echo "<style>div.STplayer { margin-top: -" . $pm . "; }</style>\n";
	}
	
	/* BELOW IS TO MANAGE SPEAKERTEXT PLUGIN SETTINGS */
	
	function create_menu() {
		// create settings submenu
		add_options_page('CaptionBox Plugin Settings', 'CaptionBox', 'manage_options', 'captionbox', array($this, 'captionbox_settings_page'));
	}
	
	function register_settings() {
	  register_setting("captionbox_options", "captionbox_transcript_path");
		register_setting("captionbox_options", "captionbox_player_margin");
		register_setting("captionbox_options", "captionbox_default_height");
		register_setting("captionbox_options", "captionbox_initial_state");
		register_setting("captionbox_options", "captionbox_load_jquery");
		
		add_settings_section("captionbox_configuration", "Configuration", array($this, 'configuration_text'), 'captionbox');
		add_settings_field('transcript_path', 'Transcript Files Path', array($this, 'transcript_path_text'), 'captionbox', 'captionbox_configuration');
		
		add_settings_section("captionbox_options", "Options", array($this, 'options_text'), 'captionbox');
		add_settings_field('default_height', 'Default Transcript Height', array($this, 'default_height_text'), 'captionbox', 'captionbox_options');
		add_settings_field('initial_state', 'Transcript Starts', array($this, 'initial_state_text'), 'captionbox', 'captionbox_options');
		add_settings_field('load_jquery', 'Load jQuery', array($this, 'load_jquery_text'), 'captionbox', 'captionbox_options');
		add_settings_field('player_margin', 'Player Margin Correction', array($this, 'player_margin_text'), 'captionbox', 'captionbox_options');
	}
	
	function configuration_text() {
	  echo "These configuration settings must be filled out for this plugin to function correctly.";
  }
	
	function options_text() {
	}
	
	function transcript_path_text() {
	  $tp = get_option('captionbox_transcript_path');
	  
	  echo "<p>CaptionBox will automatically load your transcript files from a directory on your webserver below every matching video. For reference, the path of your wordpress install is " . ABSPATH . ". Please specify this directory below.</p>";
	  echo "<input id='captionbox_transcript_path' name='captionbox_transcript_path' type='text' value='" . $tp . "' size='80' />";
	}
	
	function load_jquery_text() {
		$lj = get_option('captionbox_load_jquery');
		$no_checked = $lj == "no" ? "checked" : "";
		$yes_checked = $no_checked == "checked" ? "" : "checked";
		
		echo "<label><input ".$yes_checked." id='captionbox_load_jquery_yes' name='captionbox_load_jquery' type='radio' value='yes' /> Yes</label><br><label><input ".$no_checked." id='captionbox_load_jquery_no' name='captionbox_load_jquery' type='radio' value='no' /> No</label>";
		
		echo "<p>CaptionBox will ask Wordpress to load the jQuery javascript library. If your theme or already installed plugins load their own version of jQuery, this could cause them not to work.  If you experience issues, please try turning this option off.</p>";
	}
	
	function player_margin_text() {
		$pm = get_option('captionbox_player_margin');
		echo "<input id='player_margin' name='captionbox_player_margin' size='5' type='text' value='{$pm}' />";
		
		echo '<p>Wordpress\' default formatting causes there to be a space between CaptionBox and the video.</p>';
					
		echo '<p>To fix this, we can move the transcript up using CSS. The value specified below should be approximately equal to
					the bottom margin of your paragraph tags.  Common values are <code>1em</code>, <code>1.5em</code>, or <code>2em</code>.  You can also specify the value in pixels, such as <code>24px</code>. 
					You may have to play around with this number to get the correct value for your theme.</p>';
	}
	
	function default_height_text() {
		$dh = get_option('captionbox_default_height');
		echo "<input id='default_height' name='captionbox_default_height' size='5' type='text' value='{$dh}' />px";
	}
	
	function initial_state_text() {
		$is = get_option('captionbox_initial_state');
		$open_checked = $is == "open" ? "checked" : "";
		$closed_checked = $is == "closed" ? "checked" : "";
		
		echo "<label><input ".$open_checked." id='captionbox_initial_state_open' name='captionbox_initial_state' type='radio' value='open' /> Open</label><br><label><input ".$closed_checked." id='captionbox_initial_state_closed' name='captionbox_initial_state' type='radio' value='closed' /> Closed</label>";
	}
	
	function captionbox_settings_page() { ?>
	<div class="wrap">
	<h2>CaptionBox Settings</h2>

	<form method="post" action="options.php">
	    <?php settings_fields( 'captionbox_options' ); ?>
			<?php do_settings_sections('captionbox'); ?>
			
			<p><input name="Submit" type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>

	</form>
	</div><?php
	}
	

}
?>