<?php
/**
 * Display the Creative Commons Chooser for an item.
 *
 * @package CreativeCommonsChooser
 */
class CreativeCommonsChooser_View_Helper_CcWidget extends Zend_View_Helper_Abstract
{
    function ccWidget($item = null, $options = array())
    {
        if (empty($item)) {
            $item = get_current_record('item');
        }

        $cc = get_db()->getTable('CC')->findLicenseByItem($item, true);
        if (!$cc) {
            return;
        }

        $options['cc'] = $cc;

       return $this->view->partial('common/creative-commons-chooser.php', $options);
    }
}
