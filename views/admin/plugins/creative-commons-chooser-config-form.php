 <link rel="stylesheet" type="text/css" href="<?php
     // Import Styles for the CC License Chooser.
     echo css_src('creative-commons-chooser');
 ?>">
<fieldset id="fieldset-creative-commons-chooser-sync"><legend><?php echo __('Default choice'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
        </div>
        <div class='inputs five columns omega'>
            <!-- <form id="cc_js_seed_container"> -->
                <input type="hidden" id="cc_js_seed_uri" value="<?php echo get_option('creativecommonschooser_default_license_uri') ?>" />
            <!-- </form> -->
            <div id="cc_widget_container" style='display:block;clear:both;'>
                <script type="text/javascript" src="http://api.creativecommons.org/jswidget/tags/0.97/complete.js?locale=en_US"></script>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-creative-commons-chooser-sync"><legend><?php echo __('Save choice'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('creativecommonschooser_sync_dclicense',
                __('Sync with Dublin Core')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('The choice can be synced with the standard element Dublin Core Rights, or License if Dublin Core Extended is installed [%s].',
                    plugin_is_active('DublinCoreExtended') ? __('yes') : __('no' )); ?>
                <?php echo __('Sync can be done from CC chooser into Dublin Core Rights or License only.'); ?>
            </p>
            <?php
            $select = array(
                'none' => __('Keep data unsynchronized'),
                'auto' => __('Copy from CC chooser into DC License if exists, else DC Rights'),
                'into_rights' => __('Copy from CC chooser into DC Rights'),
                // 'from_rights' => __('Copy from DC Rights into CC chooser'),
            );
            if (plugin_is_active('DublinCoreExtended')) {
                $select['into_license'] = __('Copy from CC chooser into DC License');
                // $select['from_license'] = __('Copy from DC License into CC chooser');
            }
            echo $this->formRadio('creativecommonschooser_sync_dclicense',
                get_option('creativecommonschooser_sync_dclicense'), null, $select); ?>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('creativecommonschooser_field_format',
                __('Format of field')); ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo __('The element Rights / License can be a simple text or an html link.'); ?>
            </p>
            <?php
            echo $this->formSelect('creativecommonschooser_field_format',
                get_option('creativecommonschooser_field_format'),
                array(),
                array(
                    'text' => __('Raw text'),
                    'url' => __('Raw url'),
                    'link' => __('Html link'),
                    'button' => __('Image link'),
                )); ?>
        </div>
    </div>
</fieldset>
