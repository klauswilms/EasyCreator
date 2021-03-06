<?php
/**
 * @package    EasyCreator
 * @subpackage Controllers
 * @author     Nikolai Plath
 * @author     Created on 24-Mar-2010
 * @license    GNU/GPL, see JROOT/LICENSE.php
 */

//-- No direct access
defined('_JEXEC') || die('=;)');

jimport('joomla.application.component.controller');

/**
 * EasyCreator Controller.
 *
 * @package    EasyCreator
 * @subpackage Controllers
 */
class EasyCreatorControllerRegister extends JController
{
    /**
     * Register a project.
     *
     * @return void
     */
    public function register()
    {
        JRequest::setVar('view', 'register');

        parent::display();
    }//function
}//class
