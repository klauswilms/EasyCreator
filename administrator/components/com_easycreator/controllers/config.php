<?php
/**
 * @package    EasyCreator
 * @subpackage Controllers
 * @author     Nikolai Plath
 * @author     Created on 12-Okt-2009
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
class EasyCreatorControllerConfig extends JController
{
    /**
     * Standard display method.
     *
     * @param boolean    $cachable  If true, the view output will be cached
     * @param array|bool $urlparams An array of safe url parameters and their variable types,
     *                              for valid values see {
     *
     * @link JFilterInput::clean()}.
     *
     * @return void
     * @see  JController::display()
     */
    public function display($cachable = false, $urlparams = false)
    {
        if(class_exists('g11n'))
        {
            g11n::loadLanguage('com_easycreator.config');
        }

        JRequest::setVar('view', 'config');

        parent::display($cachable, $urlparams);
    }//function

    /**
     * Save the configuration.
     *
     * @return void
     */
    public function save_config()
    {
        try
        {
            switch(ECR_JVERSION)
            {
                case '1.5':
                    $table = JTable::getInstance('component');
                    $table->loadByOption('com_easycreator');
                    break;
                case '1.6':
                case '1.7':
                case '2.5':
                    $component = JComponentHelper::getComponent('com_easycreator');
                    $table = JTable::getInstance('extension');
                    $table->load($component->id);
                    break;

                default:
                    throw new Exception(__METHOD__.' - '.jgettext('Unsupported Joomla! version'));
                    break;
            }//switch

            if( ! $table->bind(JRequest::get('post'))
            || ! $table->check()
            || ! $table->store())
            throw new Exception($table->getError());

            $ecr_project = JRequest::getCmd('ecr_project');

            $adds = '';

            if(strpos($ecr_project, 'ecr') !== 0)
            $adds =($ecr_project) ? '&view=stuffer&ecr_project='.$ecr_project : '';

            $this->setRedirect('index.php?option=com_easycreator'.$adds, jgettext('Configuration has been saved'));
        }
        catch(Exception $e)
        {
            EcrHtml::displayMessage($e);

            EcrHtml::easyFormEnd();
        }//try
    }//function
}//class
