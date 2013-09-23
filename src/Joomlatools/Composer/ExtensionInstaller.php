<?php
namespace Joomlatools\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Installer\LibraryInstaller;

class ExtensionInstaller extends LibraryInstaller
{
    protected $_application = null;

    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);

        $this->_initialize();
    }

    protected function _initialize()
    {
        $this->_bootstrap();
    }

    public function getInstallPath(PackageInterface $package)
    {
        return 'tmp/' . $package->getPrettyName();
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Install the package into the temporary directory, so we can access all it's files
        parent::install($repo, $package);

        // Now install into Joomla
        if(!$this->_application->install($this->getInstallPath($package)))
        {
            // Get all error messages that were stored in the message queue
            $descriptions = $this->_getApplicationMessages();

            $error = 'Error while installing '.$package->getPrettyName();
            if(count($descriptions)) {
                $error .= ':'.PHP_EOL.implode(PHP_EOL, $descriptions);
            }

            throw new \RuntimeException($error);
        }
    }

    public function supports($packageType)
    {
        return 'joomlatools-extension' === $packageType;
    }

    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $installer = $this->_application->getInstaller();
        $installer->setPath('source', $this->getInstallPath($package));

        $manifest = $installer->getManifest();

        if($manifest)
        {
            $identifier = (string) $manifest->identifier;

            if(empty($identifier))
            {
                $name = strtolower((string) $manifest->name);
                $name = preg_replace('/[^A-Z0-9_\.-]/i', '', $name);

                $identifier = $name;
                if(substr($name, 0, 4) != "com_") {
                    $identifier = "com_$name";
                }
            }
            else $identifier = str_replace(':', '_', $identifier);

            return $this->_application->hasExtension($identifier);
        }

        return false;
    }

    protected function _bootstrap()
    {
        if(defined('_JEXEC')) {
            return;
        }

        $_SERVER['HTTP_HOST']   = 'localhost';

        define('_JEXEC', 1);
        define('DS', DIRECTORY_SEPARATOR);

        define('JPATH_BASE', realpath('.'));
        require_once JPATH_BASE . '/includes/defines.php';

        require_once JPATH_BASE . '/includes/framework.php';
        require_once JPATH_LIBRARIES . '/import.php';

        require_once JPATH_LIBRARIES . '/cms.php';

        $this->_application = new Application();
        $this->_application->authenticate();
    }

    protected function _getApplicationMessages()
    {
        $messages       = $this->_application->getMessageQueue();
        $descriptions   = array();

        foreach($messages as $message)
        {
            if($message['type'] == 'error') {
                $descriptions[] = $message['message'];
            }
        }

        return $descriptions;
    }

    public function __destruct()
    {
        // Clean-up to prevent PHP calling the session object's __destruct() method;
        // which will burp out Fatal Errors all over the place because the MySQLI connection
        // has already closed at that point.
        $session = \JFactory::$session;
        if(!is_null($session) && is_a($session, 'JSession')) {
            $session->close();
        }
    }
}