<?php
/**
 * @package    EasyCreator
 * @subpackage AutoCodes
 * @author     Nikolai Plath (elkuku)
 * @author     Created on 07-Mar-2010
 */

//-- No direct access
defined('_JEXEC') || die('=;)');

/**
 * Enter description here ...@todo class doccomment.
 *
 */
class AutoCodeSiteTableclassClassvar extends EcrAutoCode
{
    private $varScopes = array(
    'var'
    , 'private'
    , 'protected'
    , 'public'
    );

    /**
     * Get the AutoCode to insert.
     *
     * @param string    $type AutoCode type
     * @param EcrTable $table
     *
     * @return string
     */
    public function getCode($type, EcrTable $table)
    {
        $element = $this->getElement($type, dirname(__FILE__));

        if( ! $element)
        {
            return false;
        }

        return $element->getCode($table);
    }//function

    /**
     * Here you define custom options that will be displayed along with the input form.
     *
     * @return void
     */
    public function getOptions()
    {
        /* Array with required fields */
        $requireds = array();

        $requireds[] = EcrHtml::drawSelectScope($this->scope);

        if( ! $this->element)
        {
            $db = JFactory::getDBO();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();

            echo '<strong id="element_label">'.jgettext('Table').'</strong> : ';
            echo '<select name="element" id="table_name" onchange="$(\'element_name\').value=$(\'element\').value;">';
            echo '<option value="">'.jgettext('Choose').'...</option>';

            foreach($tables as $table)
            {
                $v = str_replace($prefix, '', $table);
                echo '<option value="'.$v.'">'.$v.'</option>';
            }//foreach

            echo '</select>';
            echo '<br />';
        }
        else
        {
            echo '<input type="hidden" name="element" value="'.$this->element.'" />';
        }

        /* Draws an input box for a name field */
        $requireds[] = EcrHtml::drawSelectName($this->element, jgettext('Table'));

        echo '<strong>Var Scope:</strong><br />';

        foreach($this->varScopes as $vScope)
        {
            $checked =($vScope == 'var') ? ' checked="checked"' : '';
            echo '<input type="radio" name="var_scope" value="'.$vScope.'"'
            .' id="vscope-'.$vScope.'"'.$checked.'> <label for="vscope-'.$vScope.'">'.$vScope.'</label><br />';
        }//foreach

        /*
         * Add your custom options
         * ...
         */

        /* Displays options for logging */
        EcrHtml::drawLoggingOptions();

        /* Draws the submit button */
        EcrHtml::drawSubmitParts($requireds);
    }//function

    /**
     * Open the part for edit.
     *
     * @param EcrAutoCode $AutoCode TheAutocode
     *
     * @return void
     */
    public function edit(EcrAutoCode $AutoCode)
    {
        $var_scope = $AutoCode->options['varscope'];

        /* Array with required fields */
        $requireds = array();

        $requireds[] = EcrHtml::drawSelectScope($this->scope);

        echo '<input type="hidden" name="element" value="'.$this->element.'" />';

        /* Draws an input box for a name field */
        $requireds[] = EcrHtml::drawSelectName($this->element, jgettext('Table'));

        echo '<strong>Var Scope:</strong><br />';

        foreach($this->varScopes as $vScope)
        {
            $checked =($vScope == $var_scope) ? ' checked="checked"' : '';
            echo '<input type="radio" name="var_scope" value="'.$vScope.'"'
            .' id="vscope-'.$vScope.'"'.$checked.'> <label for="vscope-'.$vScope.'">'.$vScope.'</label><br />';
        }//foreach

        $tableFields = EcrTableHelper::getTableColumns($this->element);

        $acFields = $AutoCode->fields[$AutoCode->getKey().'.var'];

        echo '<table>';
        echo '<tr>';
        echo '<th>'.jgettext('Field').'</th>';
        echo '<th>'.jgettext('Label').'</th>';
        echo '</tr>';

        foreach($tableFields as $name => $tableField)
        {
            if(array_key_exists($name, $acFields))
            {
                //-- Autocode present
                $value = $acFields[$name]->label;
            }
            else
            {
                //-- New field
                $value = $tableField[$name]->field;
            }

            echo '<tr>';
            echo '<th>';
            echo '<input type="hidden" name="field['.$name.'][name]" value="'.$name.'" />';
            echo $name;
            echo '</th>';

            echo '<td>';
            echo '<input type="text" name="field['.$name.'][label]" value="'.$value.'" />';
            echo '</td>';

            echo '</tr>';
        }//foreach
        echo '</table>';

        /* Draws the submit button */
        EcrHtml::drawSubmitAutoCode($requireds);
    }//function

    /**
     * Inserts the AutoCode into the project.
     *
     * @param EcrProject $project The project.
     * @param array $options Insert options.
     * @param EcrLogger $logger EcrLogger.
     *
     * @return boolean
     */
    public function insert(EcrProject $project, $options, EcrLogger $logger)
    {
        $table_name = JRequest::getCmd('element');
        $var_scope = JRequest::getCmd('var_scope');
        $element_scope = JRequest::getCmd('element_scope');

        if( ! $table_name)
        {
            JFactory::getApplication()->enqueueMessage(jgettext('No table given'), 'error');

            return false;
        }

        $db = JFactory::getDBO();
        $prefix = $db->getPrefix();
        $fields = $db->getTableFields($prefix.$table_name);

        $table_vars = '';
        $autoCodeFields = array();

        $reqFields = JRequest::getVar('field');

        if(count($fields))
        {
            foreach($fields[$prefix.$table_name] as $name => $type)
            {
                $reqField = $reqFields[$name];

                if(isset($project->autoCodes[$this->key]->fields[$this->key.'.var'][$name]))
                {
                    $field = $project->autoCodes[$this->key]->fields[$this->key.'.var'][$name];
                    $field->label = $reqField['label'];
                }
                else
                {
                    $field = new EcrTableField;
                    $field->name = $name;
                    $field->label = $name;
                    $field->type = $type;
                }

                $autoCodeFields[] = $field;

                $adds = array($field->label);

                $table_vars .= EcrTableHelper::formatTableVar($name, $type, $adds, $var_scope);
            }//foreach
        }

        $AutoCode = new EcrAutoCode($this->group, $this->name, $table_name, $element_scope);

        $AutoCode->options = array();
        $AutoCode->options['varscope'] = $var_scope;

        $AutoCode->fields[$AutoCode->getKey().'.var'] = $autoCodeFields;
        $AutoCode->codes[$AutoCode->getKey().'.var'] = $AutoCode->enclose($table_vars, $AutoCode->getKey().'.var');

        $project->addAutoCode($AutoCode);

        /*
         * Add substitutes
         *
         * Define keys that will be substitutes in the code
         */
        $project->addSubstitute('_ECR_SUBPACKAGE_', 'Tables');
        $project->addSubstitute('_ECR_TABLE_NAME_', $table_name);

        foreach($AutoCode->codes as $key => $code)
        {
            $project->addSubstitute($key, $code);
        }//foreach

        /* Insert the part to your project and return the results */
        return $project->insertPart($options, $logger);
    }//function
}//class
