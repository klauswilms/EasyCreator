<?php defined('_JEXEC') || die('=;)');
/**
 * @package    EasyCreator
 * @subpackage Helpers
 * @author     Nikolai Plath (elkuku)
 * @author     Created on 29-Feb-2008
 * @license    GNU/GPL, see JROOT/LICENSE.php
 */

/**
 * Creates Joomla! extensions from EasyCreator extension templates.
 *
 * @package EasyCreator
 */
class EcrBuilder extends JObject
{
    /**
     * @var EcrProject
     */
    public $project = null;

    private $_substitutes = array();

    private $_buildBase = '';

    private $_buildDir = '';

    /**
     * @var SimpleXMLElement
     */
    private $_buildManifest = null;

    /**
     * @var EcrLogger
     */
    private $logger = null;

    private $testMode = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->testMode =(JRequest::getCmd('ecr_test_mode') == 'test') ? true : false;
    }//function

    /**
     * Creates the frame.
     *
     * @param string $type Project type (component, plugin...)
     * @param string $template Name of the extension template
     * @param string $name Babys name
     *
     * @return EcrProject on success | false on error.
     */
    public function build($type, $template, $name)
    {
        //-- Setup logging
        $buildOpts = JRequest::getVar('buildopts', array());
        $buildOpts['fileName'] = date('ymd_Hi').'_building.log';

        $this->logger = EcrLogger::getInstance('ecr', $buildOpts);

        $this->_buildBase = JPath::clean(ECRPATH_EXTENSIONTEMPLATES.DS.$type.DS.$template);
        $this->logger->log('buildBase set to: '.$this->_buildBase);

        $projectTypes = EcrProjectHelper::getProjectTypesTags();

        if( ! in_array($type, $projectTypes))
        {
            $this->logger->log(sprintf('Unknown project type [%s]', $type));
            $this->logger->writeLog();

            $this->setError(sprintf('Unknown project type [%s]', $type));

            return false;
        }

        foreach($projectTypes as $tag => $pType)
        {
            if($pType == $type)
            {
                break;
            }
        }//foreach

        $this->project = EcrProjectHelper::newProject($type);

        $this->project->name = $name;
        $this->project->type = $type;
        $this->project->fromTpl = $template;

        $this->project->dbTypes = JRequest::getVar('dbtypes', array(), 'array');
        $this->project->headerType = JRequest::getCmd('headerType');

        if( ! $this->customOptions('process'))
        {
            $this->logger->log('Custom options failed');
            $this->logger->writeLog();

            $this->setError('Custom options failed');

            return false;
        }

        try
        {
            $this->setUp()
                ->setUpProject()
                ->readHeader()
                ->createBuildDir()
                ->copyFiles()
                ->processMoreOptions()
                ->createJoomlaManifest()
                ->install()
                ->createEasyCreatorManifest();
        }
        catch(EcrBuilderException $e)
        {
            $this->logger->log('ERROR', $e->getMessage());
            $this->logger->writeLog();

            $this->setError('ERROR: '.$e->getMessage());

            return false;
        }//try

        $this->logger->log('FINISHED');
        $this->logger->writeLog();

        return $this->project;
    }//function

    /**
     * Setup the builder.
     *
     * @return EcrBuilder
     */
    private function setUp()
    {
        if( ! JFile::exists($this->_buildBase.DS.'manifest.xml'))
            throw new EcrBuilderException('Failed to open: '.$this->_buildBase.DS.'manifest.xml');

        if( ! JFolder::exists($this->_buildBase.DS.'tmpl'))
            throw new EcrBuilderException('Template must be in folder named tmpl - '
                .$this->_buildBase.DS.'tmpl');

        $folders = JFolder::folders($this->_buildBase.DS.'tmpl');

        if( ! in_array('site', $folders) && ! in_array('admin', $folders))
            throw new EcrBuilderException('Template must contain folders named admin or site');

        $this->_buildManifest = EcrProjectHelper::getXML($this->_buildBase.DS.'manifest.xml');

        $this->logger->log('Build manifest loaded');

        return $this;
    }//function

    /**
     * Setup the project.
     *
     * @return EcrBuilder
     */
    private function setUpProject()
    {
        switch($this->project->type)
        {
            case 'component':
                $this->project->comName = strtolower($this->project->prefix.$this->project->name);
                $this->project->buildPath = $this->_buildDir;
                break;

            case 'module':
                $s = (string)$this->_buildManifest->attributes()->scope;
                $this->project->comName = strtolower($this->project->prefix.$this->project->name);
                $this->project->scope =($s == 'administrator' || ! $s) ? 'admin' : 'site';
                break;

            case 'plugin':
                $this->project->comName = strtolower($this->project->name);

                if( ! $this->project->scope)
                {
                    //-- Scope has been set previously by temlate options
                    $this->project->scope = (string)$this->_buildManifest->attributes()->scope;
                }
                break;

            case 'template':
                $this->project->comName = strtolower($this->project->name);
                $this->project->scope = (string)$this->_buildManifest->attributes()->scope;
                break;

            case 'library':
                $this->project->comName = strtolower($this->project->name);

                if( ! $this->project->scope)
                    throw new EcrBuilderException(__METHOD__.': Missing scope for library');
                break;

            case 'package':
                $this->project->comName = $this->project->prefix.strtolower($this->project->name);
                $this->project->buildPath = $this->_buildDir;
                break;

            default:
                throw new EcrBuilderException(__METHOD__.' - Undefined type : '.$this->project->type);
                break;
        }//switch

        $this->project->JCompat = (string)$this->_buildManifest->jVersion;

        $this->project->version = JRequest::getVar('version');
        $this->project->description = JRequest::getVar('description');
        $this->project->author = JRequest::getVar('author');
        $this->project->authorEmail = JRequest::getVar('authorEmail');
        $this->project->authorUrl = JRequest::getVar('authorUrl');
        $this->project->copyright = JRequest::getVar('copyright');
        $this->project->license = JRequest::getVar('license');
        $this->project->listPostfix = JRequest::getVar('list_postfix');

        $this->addSubstitute('_ECR_COM_NAME_', $this->project->name);
        $this->addSubstitute('_ECR_LOWER_COM_NAME_', strtolower($this->project->name));
        $this->addSubstitute('_ECR_UPPER_COM_NAME_', strtoupper($this->project->name));

        $this->addSubstitute('_ECR_COM_COM_NAME_', $this->project->comName);

        if('template' == $this->project->type
        || 'template' == $this->project->type)
        {
            $this->addSubstitute('_ECR_UPPER_COM_COM_NAME_'
                , strtoupper($this->project->prefix.$this->project->comName));
        }
        else
        {
            $this->addSubstitute('_ECR_UPPER_COM_COM_NAME_'
                , strtoupper($this->project->comName));
        }

        $this->addSubstitute('_ECR_COM_TBL_NAME_', strtolower($this->project->name));

        $this->addSubstitute('_ECR_ACT_DATE_', date('d-M-Y'));

        $this->addSubstitute('VERSION', $this->project->version);
        $this->addSubstitute('ECR_DESCRIPTION', $this->project->description);
        $this->addSubstitute('ECR_AUTHOR', $this->project->author);
        $this->addSubstitute('AUTHOREMAIL', $this->project->authorEmail);
        $this->addSubstitute('AUTHORURL', $this->project->authorUrl);
        $this->addSubstitute('COPYRIGHT', $this->project->copyright);
        $this->addSubstitute('LICENSE', $this->project->license);

        $this->addSubstitute('$@@Id@@$', '$Id$');

        if('component' == $this->project->type)
        {
            //-- AutoCode
            $this->addSubstitute('_ECR_LIST_POSTFIX_', $this->project->listPostfix);
            $this->addSubstitute('_ECR_LOWER_LIST_POSTFIX_', strtolower($this->project->listPostfix));
            $this->addSubstitute('_ECR_UPPER_LIST_POSTFIX_', strtoupper($this->project->listPostfix));

            //-- Menu
            /* @var SimpleXMLElement $buildMenuElement */
            $buildMenuElement = $this->_buildManifest->menu;

            if($buildMenuElement instanceof SimpleXmlElement)
            {
                $m = array();

                $s = $buildMenuElement;
                $s = $this->_substitute($s);

                $m['text'] = $s;
                $m['img'] = $this->_substitute($buildMenuElement->attributes()->img);

                $this->project->menu = $m;

                //-- SubMenu
                $buildSubMenuElement = $this->_buildManifest->submenu;

                if($buildSubMenuElement instanceof SimpleXmlElement
                && count($buildSubMenuElement->menu))
                {
                    /* @var SimpleXMLElement $subElement */
                    foreach($buildSubMenuElement->menu as $subElement)
                    {
                        $m = array();
                        $m['text'] = $this->_substitute($subElement);
                        $m['link'] = $this->_substitute($subElement->attributes()->link);
                        $m['img'] = $this->_substitute($subElement->attributes()->img);

                        $this->project->submenu[] = $m;
                    }//foreach
                }
            }
        }

        return $this;
    }//function

    /**
     * Set the scope.
     *
     * @param string $scope The scope admin/site
     *
     * @return void
     */
    public function setScope($scope)
    {
        $this->project->scope = $scope;
    }//function

    /**
     * Create the build directory.
     *
     * @return EcrBuilder
     */
    private function createBuildDir()
    {
        //-- Create build directory
        $this->_buildDir = JFactory::getConfig()->get('tmp_path').DS.uniqid($this->project->comName.'_');

        //-- Clean the path @since J 1.7
        $this->_buildDir = JPath::clean($this->_buildDir);

        if( ! JFolder::create($this->_buildDir))
            throw new EcrBuilderException('Failed to create build directory: '.$this->_buildDir);

        $this->logger->log('TempDir created at: '.$this->_buildDir);
        $this->logger->log('Building: '.$this->project->name.'<br />'
        .'aka "'.$this->project->comName.'"<br />Template: '.$this->project->fromTpl);

        $this->project->buildPath = $this->_buildDir;

        return $this;
    }//function

    /**
     * Copy the files.
     *
     * @return EcrBuilder
     */
    private function copyFiles()
    {
        $scopes = array('site', 'admin', 'media');

        $tplFolders = JFolder::folders($this->_buildBase.DS.'tmpl');

        //-- Copy files
        foreach($scopes as $scope)
        {
            if( ! in_array($scope, $tplFolders))
                continue;

            JFolder::create($this->_buildDir.DS.$scope);

            $fileList = JFolder::files($this->_buildBase.DS.'tmpl'.DS.$scope, '.', true, true);

            foreach($fileList as $fileName)
            {
                $subPack = str_replace($this->_buildBase.DS.'tmpl'.DS.$scope.DS, '', $fileName);
                $subPack = ucfirst(substr($subPack, 0, strpos($subPack, DS)));
                $subPack =($subPack) ? $subPack : 'Base';
                $this->addSubstitute('_ECR_SUBPACKAGE_', $subPack);

                $fContents = JFile::read($fileName);
                $fContents = $this->_substitute($fContents);

                $path = str_replace($this->_buildBase.DS.'tmpl', $this->_buildDir, $fileName);
                $path = str_replace('ecr_comname', strtolower($this->project->name), $path);
                $path = str_replace('_ecr_list_postfix', strtolower($this->project->listPostfix), $path);

                if( ! JFile::write($path, $fContents))
                    throw new EcrBuilderException(sprintf(jgettext('Can not write the file at %s'), $path));

                $this->logger->logFileWrite($fileName, $path, $fContents);
            }//foreach
        }//foreach

        return $this;
    }//function

    /**
     * Process additional options.
     *
     * @return EcrBuilder
     */
    private function processMoreOptions()
    {
        if( ! JRequest::getVar('create_changelog'))
        {
            //-- No changelog requested
            return $this;
        }

        $changelog = $this->_substitute(
            JFile::read(ECRPATH_PARTS.DS.'various'.DS.'changelog'.DS.'tmpl'.DS.'CHANGELOG.php'));

        switch($this->project->type)
        {
            case 'plugin':
                $fileName = $this->project->comName.'_CHANGELOG.php';
                break;

            case 'library':
                $fileName = $this->project->comName.'_CHANGELOG.php';
                break;

            default:
                $fileName = 'CHANGELOG.php';
                break;
        }//switch

        if(JFolder::exists($this->_buildDir.DS.'admin'))
        {
            $path = $this->_buildDir.DS.'admin';
        }
        else if(JFolder::exists($this->_buildDir.DS.'site'))
        {
            $path = $this->_buildDir.DS.'site';
        }
        else
        {
            throw new EcrBuilderException('No suiteable path found for CHANGELOG in '.$this->_buildDir);
        }

        //-- @todo other options ?

        if(JFile::write($path.DS.$fileName, $changelog))
        {
            $this->logger->logFileWrite('', $path.DS.$fileName, $changelog);
        }
        else
        {
            throw new EcrBuilderException('Cannot create CHANGELOG');
        }

        return $this;
    }//function

    /**
     * Create the Joomla! manifest.
     *
     * @return EcrBuilder
     */
    private function createJoomlaManifest()
    {
        $manifest = new EcrManifest;

        $this->project->isNew = true;
        $this->project->basepath = $this->_buildDir;
        $this->project->creationDate = date("d-M-Y");

        if($manifest->create($this->project))
        {
            $this->logger->logFileWrite('', $this->_buildDir.DS.'manifest.xml', $manifest->formatXML());
        }
        else
        {
            throw new EcrBuilderException('Error creating manifest file: '
            .implode("\n", $manifest->getErrors()), 'Error creating manifest file');
        }

        return $this;
    }//function

    /**
     * Create the EasyCreator manifest.
     *
     * @return boolean true on success
     */
    private function createEasyCreatorManifest()
    {
        if($this->project->type == 'plugin'
        || $this->project->type == 'module'
        || $this->project->type == 'library')
        {
            $this->project->name = ucfirst($this->project->scope).' - '.$this->project->name;
        }

        $xmlContents = $this->project->writeProjectXml($this->testMode);

        if(false == $xmlContents)
            throw new EcrBuilderException('Unable to create EasyCreator manifest');

        $this->logger->log('EasyCreator manifest created');
        $this->logger->logFileWrite('', 'ECR'.DS.'EasyCreatorManifest.xml', $xmlContents);

        return $this;
    }//function

    /**
     * Installs an extension with the standard Joomla! installer.
     *
     * @return EcrBuilder
     */
    private function install()
    {
        if($this->testMode)
        {
            //-- Exiting in test mode
            $this->logger->log('TEST MODE - not installing');

            return $this;
        }

        if($this->project->type == 'package')
        {
            //-- J! 1.6 package - only copy the manifest xml
            $src = $this->_buildDir.DS.$this->project->getJoomlaManifestName();
            $dest = $this->project->getJoomlaManifestPath().DS.$this->project->getJoomlaManifestName();

            if( ! JFile::copy($src, $dest))
                throw new EcrBuilderException(
                    sprintf('Failed to copy package manifest xml from %s to %s', $src, $dest));

            $this->logger->log(
                sprintf('Package manifest xml has been copied from %s to %s', $src, $dest));

            return $this;
        }

        jimport('joomla.installer.installer');
        jimport('joomla.installer.helper');

        $this->logger->log('Starting Install');

        //-- Did you give us a valid package ?
        if( ! $type = JInstallerHelper::detectType($this->_buildDir))
            throw new EcrBuilderException(jgettext('Path does not have a valid package'));

        //-- Get an installer instance
        $installer = JInstaller::getInstance();

        //-- Install the package
        $result = $installer->install($this->_buildDir);

        $this->logger->log('Installer Message: '.$installer->message);
        $this->logger->log('Extension Message: '.$installer->get('extension.message'));

        //-- Clean up the install directory. If we are not debugging.
        ECR_DEBUG ? null : JInstallerHelper::cleanupInstall('', $this->_buildDir);

        //-- There was an error installing the package
        if( ! $result)
            throw new EcrBuilderException(sprintf(jgettext('An error happened while installing your %s'), jgettext($type)));

        return $this;
    }//function

    /**
     * Process custom otions.
     *
     * @param string $action The action to perform
     * @param EcrProject $project The project
     *
     * @return mixed [array custom options | boolean false on error]
     */
    public function customOptions($action = 'display', EcrProject $project = null)
    {
        static $templateOptions = null;

        if( ! $templateOptions)
        {
            $tplType = JRequest::getVar('tpl_type');
            $tplName = JRequest::getVar('tpl_name');

            $template_path = ECRPATH_EXTENSIONTEMPLATES.DS.$tplType.DS.$tplName;

            if( ! JFile::exists($template_path.DS.'options.php'))
            {
                if($action == 'requireds')
                return array();

                if($action == 'process')
                return true;

                return false;
            }

            include_once $template_path.DS.'options.php';

            if( ! class_exists('EasyTemplateOptions'))
            {
                echo sprintf(jgettext('Required class %s not found'), 'EasyTemplateOptions');

                if($action == 'requireds')
                return array();

                return false;
            }

            $templateOptions = new EasyTemplateOptions;
        }

        switch($action)
        {
            case 'display':
                if( ! method_exists('EasyTemplateOptions', 'displayOptions'))
                {
                    echo sprintf(jgettext('Required method %s not found'), 'displayOptions');

                    return false;
                }

                echo '<div class="ecrBigInfo">';
                echo '<h3>'.jgettext('Custom options').'</h3>';
                echo '</div>';

                echo $templateOptions->displayOptions($project);

                echo '<br /><br />';
                break;
            case 'process':
                if( ! method_exists('EasyTemplateOptions', 'processOptions'))
                {
                    echo sprintf(jgettext('Required method %s not found'), 'processOptions');

                    return false;
                }

                return $templateOptions->processOptions($this);
                break;
            case 'requireds':
                if( ! method_exists('EasyTemplateOptions', 'getRequireds'))
                {
                    echo sprintf(jgettext('Required method %s not found'), 'getRequireds');

                    return array();
                }

                return $templateOptions->getRequireds();
                break;

            default:
                echo sprintf(jgettext('Action %s not defined'), $action);

                return false;
                break;
        }//switch

        return true;
    }//function

    /**
     * Register an existing project.
     *
     * @param string $type Project type
     * @param string $name Project name
     * @param string $scope Project scope e.g. admin, site
     *
     * @return EcrProject on success | false on error
     */
    public function registerProject($type, $name, $scope = '')
    {
        //-- Setup logging
        $comParams = JComponentHelper::getParams('com_easycreator');
        $options = array();
        $opts = array('logging', 'hotlogging', 'files', 'profile');

        foreach($opts as $o)
        {
            if($comParams->get($o))
            {
                $options[] = $o;
            }
        }//foreach

        $options['fileName'] = date('ymd_Hi').'_register.log';

        $this->logger = EcrLogger::getInstance('ecr', $options);

        $projectTypes = EcrProjectHelper::getProjectTypes();

        if( ! array_key_exists($type, $projectTypes))
        {
            JFactory::getApplication()->enqueueMessage(sprintf(jgettext('The project type %s is not defined yet'), $type), 'error');
            $this->setError(sprintf(jgettext('The project type %s is not defined yet'), $type));

            return false;
        }

        $project = EcrProjectHelper::newProject($type);
        $project->comName = $name;
        $project->scope = $scope;

        if( ! $xmlPath = EcrProjectHelper::findManifest($project))
        {
            JFactory::getApplication()->enqueueMessage(jgettext('No manifest file found'), 'error');

            $this->setError(jgettext('No manifest file found'));

            return false;
        }

        $data = EcrProjectHelper::parseXMLInstallFile(JPATH_ROOT.DS.$xmlPath);

        $project->name = $data->name;

        $this->logger->log('Registering: '.$project->type.' - '.$project->name.'<br />'
        .'aka "'.$project->comName.'"<br />scope: '.$project->scope);

        $pXml = $project->writeProjectXml();

        if($pXml === false)
        {
            $this->logger->log('', 'Unable to create EasyCreator manifest');
            $this->logger->writeLog();

            return false;
        }
        else
        {
            $this->logger->log('EasyCreator manifest created');
            $this->logger->logFileWrite('', '', $pXml);
        }

        $this->logger->log('FINISHED');
        $this->logger->writeLog();

        return $project;
    }//function

    /**
     * Read the header file.
     *
     * @return EcrBuilder
     */
    private function readHeader()
    {
        $types = array('', 'js', 'css');

        $format = JRequest::getCmd('optHeader', 'git');

        foreach($types as $type)
        {
            $path = ECRPATH_EXTENSIONTEMPLATES.'/std/header/'.$format.'/header'.$type.'.txt';

            if( ! JFile::exists($path))
            continue;

            $header = JFile::read($path);

            //-- Replace vars in header
            $header = $this->_substitute($header);

            $this->addSubstitute('##*HEADER'.strtoupper($type).'*##', $header);
        }//foreach

        return $this;
    }//function

    /**
     * Adds a string to the substitutes array.
     *
     * @param string $key String to be search for
     * @param string $value String to replacer
     *
     * @return void
     */
    public function addSubstitute($key, $value)
    {
        $this->_substitutes[$key] = $value;
    }//function

    /**
     * Replaces tags in text from substitutes array.
     *
     * @param string $text The text to process
     *
     * @return string substituted string
     */
    private function _substitute($text)
    {
        foreach($this->_substitutes as $k => $v)
        {
            $text = str_replace($k, $v, $text);
        }//foreach

        return $text;
    }//function

    /**
     * Print out HTML error list.
     *
     * @return string html
     */
    public function printErrors()
    {
        if(count($this->_errors))
        {
            echo '<h4 style="color: red;">ERRORS !</h4>';
            echo '<ul>';

            foreach($this->_errors as $entry)
            {
                echo '<li>'.$entry.'</li>';
            }//foreach

            echo '</ul>';
        }
        else
        {
            echo '<h4 style="color: green;">No errors...</h4>';
        }

        return true;
    }//function

    /**
     * HTML log output.
     *
     * @return string html
     */
    public function printLog()
    {
        return $this->logger->printLog();
    }//function
}//class
