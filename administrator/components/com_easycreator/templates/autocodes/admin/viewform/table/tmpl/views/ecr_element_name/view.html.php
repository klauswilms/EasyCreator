<?php
##*HEADER*##

jimport('joomla.application.component.view');

/**
 * HTML View class for the _ECR_COM_NAME_ Component.
 *
 * @package    _ECR_COM_NAME_
 * @subpackage Views
 */
class _ECR_COM_NAME_sView_ECR_COM_NAME_ extends JView
{
    /**
     * _ECR_COM_NAME_ view display method.
     *
     * @param string $tpl The name of the template file to parse;
     *
     * @return void
     */
    public function display($tpl = null)
    {
        //-- Get the _ECR_COM_NAME_
        $_ECR_COM_NAME_ =& $this->get('Data');
        $isNew = ($_ECR_COM_NAME_->id < 1);

        $text =($isNew) ? JText::_('New') : JText::_('Edit');

        JToolBarHelper::title('_ECR_COM_NAME_: <small><small>[ '.$text.' ]</small></small>');
        JToolBarHelper::save();

        if($isNew)
        {
            JToolBarHelper::cancel();
        }
        else
        {
            //-- For existing items the button is renamed `close`
            JToolBarHelper::cancel('cancel', JText::_('Close'));
        }

        $this->assignRef('_ECR_COM_NAME_', $_ECR_COM_NAME_);

        parent::display($tpl);
    }//function
}//class
