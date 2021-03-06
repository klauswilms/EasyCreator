<?php
/**
 * @package    EasyCreator
 * @subpackage Views
 * @author     Nikolai Plath
 * @author     Created on 06.-Apr-2010
 * @license    GNU/GPL, see JROOT/LICENSE.php
 */

//-- No direct access
defined('_JEXEC') || die('=;)');
?>
<h2><?php echo jgettext('Install - Uninstall - Update'); ?></h2>
<?php

echo $this->loadTemplate('php');

echo $this->loadTemplate('sql');

echo $this->loadTemplate('update');

?>
<div class="ecr_floatbox">
    <strong><?php echo jgettext('Build options'); ?>:</strong>
    <?php EcrHtml::drawLoggingOptions(); ?>
</div>

<input type="hidden" name="old_task" value="install" />
<?php
