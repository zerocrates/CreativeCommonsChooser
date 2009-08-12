<?php
define('CC_PLUGIN_VERSION', '0.2');

require_once 'CreativeCommonsLicense.php';

// Plugin Hooks
add_plugin_hook('install', 'cc_install');
add_plugin_hook('uninstall', 'cc_uninstall');
add_plugin_hook('config_form', 'cc_config_form');
add_plugin_hook('config', 'cc_config');

add_plugin_hook('after_save_item', 'cc_save_license');
add_plugin_hook('append_to_item_form', 'cc_form');
// add_filter('admin_items_form_tabs', 'cc_item_form_tabs');
add_plugin_hook('admin_append_to_items_show_secondary', 'cc_admin_for_item');

add_plugin_hook('append_to_item_show', 'cc_for_item');
add_plugin_hook('public_append_to_items_show', 'public_cc_for_item');


// Hook Functions
function cc_install()
{    
    $db = get_db();
    $sql = "
    CREATE TABLE IF NOT EXISTS $db->CC (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `item_id` BIGINT UNSIGNED NOT NULL ,
    `is_cc` BOOLEAN NOT NULL ,
    `cc_name` TEXT ,
    `cc_uri` TEXT ,
	`cc_img` TEXT ,
    INDEX (`item_id`)) ENGINE = MYISAM";
    $db->query($sql);
    set_option('cc_plugin_version', CC_PLUGIN_VERSION);    
}

function cc_uninstall()
{
	delete_option('cc_plugin_version');

	$db = get_db();
	$db->query("DROP TABLE $db->CC");
}

function cc_config_form()
{
	include 'config_form.php';
}

function cc_config()
{
    //Use the form to set a bunch of default options in the db
    set_option('default_license_uri', $_POST['cc_js_result_uri']);
}


/**
 * Add Creative Commons tab to the edit item page
 * @return array
 **/
function cc_item_form_tabs($tabs)
{
    // insert the map tab before the Miscellaneous tab
    $item = get_current_item();
    $ttabs = array();
    foreach($tabs as $key => $html) {
        if ($key == 'Miscellaneous') {
            $ttabs['CreativeCommons'] = cc_form($item);
        }
        $ttabs[$key] = $html;
    }
    $tabs = $ttabs;
    return $tabs;
}

/**
 * Each time we save an item, check the POST to see if we are also saving a 
 * license
 * @return void
 **/
function cc_save_license($item)
{	
	$cc_post = $_POST;    
					
    // If we don't have the creative commons form on the page, don't do anything!
	// echo '<br/> cc_post: '. $cc_post;
	
    if (!$cc_post['cc_js_result_uri']) {
        return;
    }

    // Find the ActiveRecord location object
    $cc_item = get_db()->getTable('CC')->findLicenseByItem($item, true);
	
    // If we have filled out info for the license, then submit to the db
    if (!empty($cc_post) 
        && (!empty($cc_post['cc_js_result_uri']) 
            && !empty($cc_post['cc_js_result_name']))) {

        if (!$cc_item) {
            $cc_item = new CC;
            $cc_item->item_id = $item->id;
        }
		
		if( $cc_post['cc_js_result_name'] != 'No license chosen' )
		{
			$cc_item->cc_uri = $cc_post['cc_js_result_uri'];
			$cc_item->cc_name = $cc_post['cc_js_result_name'];
			$cc_item->cc_img = $cc_post['cc_js_result_img'];
			$cc_item->is_cc = true;
		}	
		else
		{
			$cc_item->is_cc = false;
		}		
		
        
        if ($cc_item->saveForm($cc_post) ) {
            return true;
        }
    // If the form is empty, then we want to delete whatever license is 
    // currently stored
    } else {
        if ($cc_item) {
            $cc_item->delete();
        }
    }
}


// Helpers

/**
 * Output the script tags that include the GMaps JS from afar
 * @return void
 **/
function cc_scripts()
{    
	$frontController = Zend_Controller_Front::getInstance();
	$baseUri = $frontController->getBaseUrl();
	?>
		<style type="text/css" >
		/* Import Styles for the CC License Chooser */
		<?php include('cc.css') ?>
		</style>
		
		<p />
     <div id="cc_widget_container" style='display:block;clear:both;'>
	<script type="text/javascript" src="http://api.creativecommons.org/jswidget/tags/0.97/complete.js?locale=en_US"></script>
	<?php //echo js('complete'); ?>
     </div>

	<?php
}

/**
 * Return the license record for the given item_id (if exists)
 * @param array|int $item_id
 * @return array
 **/
function get_license_for_item($item_id)
{
    return get_db()->getTable('CC')->findLicenseByItem($item_id);
}

function public_cc_for_item()
{
	$item = get_current_item();

	$cc = current(get_license_for_item($item->id));
    
    if ($cc) {
    
	?>
		<style type="text/css" media="screen">
		
			#cc_license { text-align:left;margin:auto;}
			div.cc_info  { text-align:left;}
			a.cc_js_a { padding-bottom:1em;}

		</style>
		
		<div id="cc_license"><!-- Creative Commonts License -->
			<h3>License</h3>	
				<a href="<?php echo $cc->cc_uri; ?>" rel="license" class="cc_js_a"><img width="88" height="31" border="0" class="cc_js_cc-button" src="<?php echo $cc->cc_img; ?>" alt="Creative Commons License"/></a>
			<?php /*	<div class="cc_info">This work is licensed under a <a href="<?php echo $cc->cc_uri; ?>" rel="license"><?php echo $cc->cc_name; ?></a>.
				</div> */ ?>
		</div>
		
	<?php
	
	}
	
}

function cc_for_item($item, $width = 0) {        

   $divId = "item-license-{$item->id}";

// Need some formatting for displaying the CC License Box.
?>
<style type="text/css" media="screen">

    #<?php echo $divId;?> {
        width: <?php if ($width == 0) {echo 'auto';} else { echo $width;} ?>px;	
        border:         1px solid #c2e0cf;
	    text-align:     center;
	    padding:        1%;
	    margin-bottom: 2.7%;
	}
	
	#cc_license { text-align:center;margin:auto;}
	div.cc_info  { text-align:center;margin:auto;}

</style>
<?php        
    $cc = current(get_license_for_item($item->id));
    
    if ($cc) {
	     
		?>
			<h2>License</h2>
			
			<div id="<?php echo $divId; ?>" style="display: block;">
				<div id="cc_license"><!-- Creative Commonts License -->
					<a href="<?php echo $cc->cc_uri; ?>" rel="license" class="cc_js_a"><img width="88" height="31" border="0" class="cc_js_cc-button" src="<?php echo $cc->cc_img; ?>" alt="Creative Commons License"/></a>
				<div class="cc_info">This work is licensed under a <a href="<?php echo $cc->cc_uri; ?>" rel="license"><?php echo $cc->cc_name; ?></a>.
				</div>
			</div>
			</div>
		<?php
		
	}
}


function cc_form($item, $width = 612, $height = 400) { 

		if(isset($item->id))
			$cc = current(get_license_for_item($item->id));
		
		if($cc)
		{
			
			?>
			
				<!-- <form id="cc_js_seed_container"> -->
				     <input type="hidden" id="cc_js_seed_uri" value="<?php echo $cc->cc_uri; ?>" />
				<!-- </form> -->
			
			<?php
		}
		else
		{
				?>
			<!-- <form id="cc_js_seed_container"> -->
			     <input type="hidden" id="cc_js_seed_uri" value="<?php echo get_option('default_license_uri'); ?>" />
			<!-- </form> -->
			
				<?php
		}

    	cc_scripts();    

}

function cc_admin_for_item($item)
{
?>
<style type="text/css" media="screen">
	.info-panel .map {margin-top:-18px;display:block; margin-left:-18px; margin-bottom:0;border-top:3px solid #eae9db; padding:0;}
</style>
<?php
}