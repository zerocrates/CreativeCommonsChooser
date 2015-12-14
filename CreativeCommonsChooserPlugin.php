<?php
/**
 * CreativeCommonsChooser
 *
 * Adds a Creative Commons License Chooser to the admin interface and extends
 * Omeka items to be associated with individual CC licenses.
 *
 * @copyright Copyright Alexandria Archive Institute, African Commons, and the UC Berkeley School of Information, Information and Service Design Program, 2009
 * @copyright Copyright Daniel Berthereau, 2015 (upgrade to Omeka 2)
 * @license GNU/GPL v3
 * @package CreativeCommonsChooser
 */

/**
 * The CreativeCommonsChooser plugin.
 * @package Omeka\Plugins\CreativeCommonsChooser
 */
class CreativeCommonsChooserPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'initialize',
        'install',
        'uninstall',
        'config_form',
        'config',
        'before_save_item',
        'after_save_item',
        'after_delete_item',
        'admin_head',
        'admin_items_show_sidebar',
        'public_items_show',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_items_form_tabs',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        'creativecommonschooser_default_license_uri' => '',
        'creativecommonschooser_sync_dclicense' => 'none',
        'creativecommonschooser_field_format' => 'text',
    );

    /**
     * Initialize hook.
     */
    public function hookInitialize()
    {
        add_shortcode('cc', array($this, 'shortcodeCreativeCommons'));
    }

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $db = $this->_db;
        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->CC` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `item_id` int(10) unsigned NOT NULL,
            `is_cc` BOOLEAN NOT NULL ,
            `cc_name` TEXT COLLATE utf8_unicode_ci ,
            `cc_uri` TEXT COLLATE utf8_unicode_ci ,
            `cc_img` TEXT COLLATE utf8_unicode_ci ,
            PRIMARY KEY (`id`),
            KEY `item_id` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $db->query($sql);

        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `$db->CC`";
        $db->query($sql);

        $this->_uninstallOptions();
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial('plugins/creative-commons-chooser-config-form.php');
    }

    /**
     * Saves plugin configuration page.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        // The config form isn't standard, so it can't be managed automatically.
        $post = $args['post'];

        // Use the form to set a bunch of default options in the db.
        set_option('creativecommonschooser_default_license_uri', $post['cc_js_result_uri']);

        set_option('creativecommonschooser_sync_dclicense', $post['creativecommonschooser_sync_dclicense']);
        set_option('creativecommonschooser_field_format', $post['creativecommonschooser_field_format']);
    }

    /**
     * Each time an item is saved, update the standard Dublin Core element too.
     *
     * @internal Because element texts are managed after saved, the previous
     * value is removed in the hook after_save_item.
     *
     * @return void
     */
    public function hookBeforeSaveItem($args)
    {
        // Only manage manual changes.
        if (!$args['post']) {
            return;
        }

        $item = $args['record'];
        $post = $args['post'];

        // If there is no creative commons form on the page, don't do anything!
        // TODO Currently, there is no update from field to CC.
        if (empty($post['cc_js_result_uri'])) {
            return;
        }

        // Check if a sync should be done.
        $sync = get_option('creativecommonschooser_sync_dclicense') ?: 'none';
        if ($sync == 'none') {
            return;
        }

        // Avoid impossible choices.
        if (in_array($sync, array('into_license', 'from_license'))
                && !plugin_is_active('DublinCoreExtended')
            ) {
            return;
        }

        // Get the active license, if any.
        $cc = $this->_getLicenseForItem($item);

        // There is a cc, so this is an update, so check if the license changed.
        if ($cc) {
            // User update without a cc license.
            if ($post['cc_js_result_name'] == 'No license chosen') {
                if ($cc->is_cc == false) {
                    return;
                }
            }
            // User update with a license. Only the uri is checked, because this
            // is the most stable value.
            else {
                if ($cc->is_cc && $cc->cc_uri == $post['cc_js_result_uri']) {
                    return;
                }
            }
        }

        // The license changed, so update the Dublin Core element.

        // Copy the new cc license into DC.
        if (!empty($post)
                && (!empty($post['cc_js_result_uri'])
                    && !empty($post['cc_js_result_name']))
            ) {

            // No translation, because it comes from Creative Commons.
            if ($post['cc_js_result_name'] == 'No license chosen') {
                return;
            }

            // This isn't the true cc of the item. The true one is managed in
            // hook after_save_item.
            $cc = array();
            if ($post['cc_js_result_name'] == 'No license chosen') {
                $cc['is_cc'] = false;
            }
            else {
                $cc['is_cc'] = true;
                $cc['cc_name'] = $post['cc_js_result_name'];
                $cc['cc_uri'] = $post['cc_js_result_uri'];
                $cc['cc_img'] = $post['cc_js_result_img'];
            }
            $format = get_option('creativecommonschooser_field_format') ?: 'text';
            $elementText = $this->_getLicenseForDC($cc, $format);
            if ($elementText) {
                $elementSetName = 'Dublin Core';
                $elementName = in_array($sync, array('into_license', 'from_license'))
                        || ($sync == 'auto' && plugin_is_active('DublinCoreExtended'))
                    ? 'License'
                    : 'Rights';
                $element = $item->getElement($elementSetName, $elementName);
                $isHtml = in_array($format, array('html_link', 'image_link'));
                $item->addTextForElement($element, $elementText, $isHtml);
            }
        }
    }

    /**
     * Each time an item is saved, check if a license is saved too.
     *
     * @internal The sync with Dublin Core, if any, is done before save.
     *
     * @return void
     */
    public function hookAfterSaveItem($args)
    {
        // Only manage manual changes.
        if (!$args['post']) {
            return;
        }

        $item = $args['record'];
        $post = $args['post'];

        // If there is no creative commons form on the page, don't do anything!
        if (!$post['cc_js_result_uri']) {
            return;
        }

        // Get the active license, if any.
        $cc = $this->_getLicenseForItem($item);

        // Remove the active license in Dublin Core, if any, and if changed.
        // Internal: It can't be done easily before save for technical reasons.

        // Check if a sync should be done.
        $sync = get_option('creativecommonschooser_sync_dclicense') ?: 'none';

        // There is a cc, so this is an update, so check if the license changed.
        if ($sync != 'none' && !$args['insert'] && $cc) {
            $updated = false;
            // User update without a cc license.
            if ($post['cc_js_result_name'] == 'No license chosen') {
                $updated = $cc->is_cc;
            }
            // User update with a license. Only the uri is checked, because this
            // is the most stable value.
            else {
                $updated = !($cc->is_cc && $cc->cc_uri == $post['cc_js_result_uri']);
            }

            // If the license changed, remove the previous element.
            if ($updated) {
                // Get the element to update.
                $elementSetName = 'Dublin Core';
                $elementName = in_array($sync, array('into_license', 'from_license'))
                        || ($sync == 'auto' && plugin_is_active('DublinCoreExtended'))
                    ? 'License'
                    : 'Rights';
                if ($item->hasElementText($elementSetName, $elementName)) {
                    $licenses = $item->getElementTexts($elementSetName, $elementName);
                    $licenseValues = $this->_getLicenseForDC($cc);
                    // Don't use deleteElementTextsByElementId()
                    // because it isn't a clean way.
                    foreach ($licenses as $license) {
                        if (in_array($license->text, $licenseValues)) {
                            $license->delete();
                        }
                    }
                }
            }
        }

        // Set or remove the new cc license.
        // If the license is filled out, then submit to the db.
        if (!empty($post)
                && (!empty($post['cc_js_result_uri'])
                    && !empty($post['cc_js_result_name']))
            ) {

            if (empty($cc)) {
                $cc = new CC;
                $cc->item_id = $item->id;
            }

            // No translation, because it comes from Creative Commons.
            if ($post['cc_js_result_name'] != 'No license chosen') {
                $cc->is_cc = true;
                $cc->cc_name = $post['cc_js_result_name'];
                $cc->cc_uri = $post['cc_js_result_uri'];
                $cc->cc_img = $post['cc_js_result_img'];
            }
            else {
                $cc->is_cc = false;
            }

            if ($cc->save()) {
                return true;
            }
        }
        // If the form is empty, then we want to delete whatever license is
        // currently stored.
        else {
            if ($cc) {
                $cc->delete();
            }
        }
    }

    /**
     * Hook used when an item is removed.
     */
    public function hookAfterDeleteItem($args)
    {
        $item = $args['record'];
        $ccs = $this->_db->getTable('CC')->findLicenseByItem($item, false);
        foreach ($ccs as $cc) {
            $cc->delete();
        }
    }

    /**
     * Add css and js in the header of the admin theme.
     */
    public function hookAdminHead($args)
    {
        $view = $args['view'];

        $request = Zend_Controller_Front::getInstance()->getRequest();
        // If needed output the script tags that include the GMaps JS from afar.
        if ($request->getControllerName() == 'items'
                && in_array($request->getActionName(), array('edit', 'add'))
            ) {
            // Import Styles for the CC License Chooser
            queue_css_file('creative-commons-chooser');
            // Note: The official javascript should be append after the form.
            // queue_js_file('complete');
        }
    }

    public function hookAdminItemsShowSidebar($args)
    {
        $view = $args['view'];
        $item = $args['item'];

        $html = '<div class="info panel">';
        $html .= '<h4>' . __('Creative Commons License') . '</h4>';
        $html .= '<div><p>';
        $html .= $view->ccWidget($item);
        $html .= '</p></div>';
        $html .= '</div>';

        echo $html;
    }

    public function hookPublicItemsShow($args)
    {
        $view = $args['view'];
        $item = $args['item'];

        echo $view->ccWidget($item, array(
            'title' => true,
            'display' => 'image',
        ));
    }

    /**
     * Add Creative Commons tab to the edit item page.
     *
     * @return array of tabs
     */
    function filterAdminItemsFormTabs($tabs, $args)
    {
        $item = $args['item'];

        // Insert the map tab before the Tags tab.
        $ttabs = array();
        foreach($tabs as $key => $html) {
            if ($key == 'Tags') {
                $ttabs[__('Creative Commons')] = $this->_ccForm($item);
            }
            $ttabs[$key] = $html;
        }
        $tabs = $ttabs;
        return $tabs;
    }

    protected function _ccForm($item)
    {
        if (isset($item->id)) {
            $cc = $this->_getLicenseForItem($item->id);
        }

        $html = '<!-- <form id="cc_js_seed_container"> -->';
        $html .= '<input type="hidden" id="cc_js_seed_uri" value="';
        $html .= empty($cc) ? get_option('creativecommonschooser_default_license_uri') : $cc -> cc_uri;
        $html .= '" />';
        $html .= '<!-- </form> -->';
        $html .= '<div id="cc_widget_container" style="display:block; clear:both;">';
        $html .= '<script type="text/javascript" src="http://api.creativecommons.org/jswidget/tags/0.97/complete.js?locale=en_US"></script>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Shortcode for adding a Creative Commons widget.
     *
     * @param array $args
     * @param Omeka_View $view
     * @return string
     */
    public function shortcodeCreativeCommons($args, $view)
    {
        // Check required arguments
        if (empty($args['item_id'])) {
            $item = get_current_record('item');
        }
        else {
            $item = get_record_by_id('Item', (integer) $args['item_id']);
        }
        if (empty($item)) {
            return;
        }

        $title = isset($args['title'])
            ? $args['title']
            : false;

        $display = isset($args['display'])
            ? $args['display']
            : 'image';

        $options = array(
            'display' => $display,
            'title' => $title,
        );

        return $view->ccWidget($item, $options);
    }

    /**
     * Return the license record for the given item_id (if exists).
     *
     * @param array|Item|int $item
     * @return array
     */
    protected function _getLicenseForItem($item)
    {
        return $this->_db->getTable('CC')->findLicenseByItem($item, true);
    }

    /**
     * Return the license for Dublin Core, according to the selected dformat.
     *
     * @param CC|array $cc Contains chosen values (text, url and image).
     * @param string $format All format if 'all', else selected format.
     * @return string|array The license with the selected format or all formats.
     */
    protected function _getLicenseForDC($cc, $format = 'all')
    {
        if (is_array($cc)) {
            $cc = (object) $cc;
        }

        if (empty($cc->is_cc)) {
            return $format == 'all' ? array() : '';
        }

        switch ($format) {
            case 'text':
                return $cc->cc_name;
            case 'url':
                return $cc->cc_uri;
            case 'link':
                return sprintf('<a href="%s" rel="license" class="cc_info">%s</a>',
                    $cc->cc_uri, $cc->cc_name);
            case 'button':
                return sprintf('<a href="%s" rel="license" class="cc_info">'
                        . '<img class="cc_js_cc-button" src="%s" alt="Creative Commons License" />'
                        . '</a>',
                    $cc->cc_uri, $cc->cc_img);
            // Return the values for all formats.
            case 'all':
                return array(
                    'text' => $this->_getLicenseForDC($cc, 'text'),
                    'url' => $this->_getLicenseForDC($cc, 'url'),
                    'link' => $this->_getLicenseForDC($cc, 'link'),
                    'button' => $this->_getLicenseForDC($cc, 'button'),
                );
        }
    }
}
