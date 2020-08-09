<?php
namespace PEAR\Config;

use PEAR\Config\Driver\Apache;
use PEAR\Config\Driver\DriverInterface;
use PEAR\Config\Driver\Generic;
use PEAR\Config\Driver\IniCommented;
use PEAR\Config\Driver\IniFile;
use PEAR\Config\Driver\PHPArray;
use PEAR\Config\Driver\PHPConstants;
use PEAR\Config\Driver\XML;

/**
 * Config
 * This class allows for parsing and editing of configuration data sources.
 * Do not use this class only to read data sources because of the overhead
 * it creates to keep track of the configuration structure.
 * @author   Bertrand Mansion <bmansion@mamasam.com>
 * @package  Config
 */
class Config
{
    public const APACHE_CONF = 'apache';
    public const GENERIC = 'generic';
    public const INI_CONF = 'ini';
    public const INI_COMMENTED = 'ini_commented';
    public const PHP_ARRAY = 'php_array';
    public const PHP_CONSTANTS = 'php_constants';
    public const XML = 'xml';

    private static array $registry = [
        self::APACHE_CONF   => Apache::class,
        self::GENERIC       => Generic::class,
        self::INI_COMMENTED => IniCommented::class,
        self::INI_CONF      => IniFile::class,
        self::PHP_ARRAY     => PHPArray::class,
        self::PHP_CONSTANTS => PHPConstants::class,
        self::XML           => XML::class,
    ];

    /**
     * Datasource
     * Can be a file url, a dsn, an object...
     * @var mixed
     */
    private $source;
    /**
     * Type of datasource for config
     * Ex: IniCommented, Apache...
     * @var string
     */
    private string $configType = '';
    /**
     * Options for parser
     * @var array
     */
    private array $parserOptions = [];
    /**
     * Container object
     * @var Container
     */
    private Container $container;

    /**
     * Constructor
     * Creates a root container
     */
    public function __construct()
    {
        $this->container = new Container('section', 'root');
    }

    /**
     * Register a new container
     *
     * @param string  $configType Type of config
     * @param ?string $className  Config driver class name
     *
     * @return   bool  true on success
     * @throws Exception
     */
    public function registerConfigType(string $configType, string $className = null): bool
    {
        if ($this->isConfigTypeRegistered($configType)) {
            return true;
        }

        if ($className === null) {
            throw new Exception('Undefined class name for config "' . $configType . '"');
        }

        self::$registry[$configType] = $className;

        return true;
    }

    /**
     * Returns true if container is registered
     *
     * @param string $configType Type of config
     *
     * @return   bool
     */
    public function isConfigTypeRegistered(string $configType): bool
    {
        return isset(self::$registry[$configType]);
    }

    /**
     * Returns the root container for this config object
     * @return Container reference to config's root container object
     */
    public function getRoot(): Container
    {
        return $this->container;
    }

    /**
     * Sets the content of the root Config_container object.
     * This method will replace the current child of the root
     * Container object by the given object.
     *
     * @param Container $rootContainer container to be used as the first child to root
     *
     * @return   mixed    true on success or PEAR_Error
     * @throws Exception
     */
    public function setRoot(Container $rootContainer): bool
    {
        if ($rootContainer->getName() === 'root' && $rootContainer->getType() === 'section') {
            $this->container = $rootContainer;
        } else {
            $this->container = new Container('section', 'root');
            $this->container->addItem($rootContainer);
        }

        return true;
    }

    /**
     * Parses the datasource contents
     * This method will parse the datasource given and fill the root
     * Container object with other Container objects.
     *
     * @param mixed  $dataSrc    Datasource to parse
     * @param string $configType Type of configuration
     * @param array  $options    Options for the parser
     *
     * @return mixed PEAR_Error on error or Container object
     * @throws Exception
     */
    public function parseConfig($dataSrc, string $configType, array $options = []): Container
    {
        $driver = $this->getDriver($configType, $options);
        $driver->parseData($dataSrc, $this);

        $this->parserOptions = $driver->getOptions();
        $this->source = $dataSrc;
        $this->configType = $configType;

        return $this->container;
    }

    /**
     * Writes the container contents to the datasource.
     *
     * @param ?string $dataSrc    [optional] Datasource to write to
     * @param ?string $configType [optional] Type of configuration
     * @param ?array  $options    [optional] Options for config container
     *
     * @return bool true if ok
     * @throws Exception
     */
    public function writeConfig(string $dataSrc = null, string $configType = null, array $options = null): bool
    {
        if ($dataSrc === null) {
            $dataSrc = $this->source;
        }

        if ($configType === null) {
            $configType = $this->configType;
        }

        if ($options === null) {
            $options = $this->parserOptions;
        }

        $driver = $this->getDriver($configType, $options);
        return $this->container->writeData($dataSrc, $driver);
    }

    /**
     * Call the toString methods in the container plugin
     *
     * @param string $configType Type of configuration used to generate the string
     * @param array  $options    Specify special options used by the parser
     *
     * @return   mixed   true on success or PEAR_ERROR
     * @throws Exception
     */
    public function toString(string $configType, array $options = []): string
    {
        $driver = $this->getDriver($configType, $options);
        return $driver->toString($this->container);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param string $configType
     * @param array  $options
     *
     * @return DriverInterface
     * @throws Exception
     */
    public function getDriver(string $configType, array $options = []): DriverInterface
    {
        if (!$this->isConfigTypeRegistered($configType)) {
            throw new Exception("Configuration type '$configType' is not registered in Config::parseConfig.");
        }

        $className = self::$registry[$configType];
        $driver = new $className($options);

        if (!$driver instanceof DriverInterface) {
            throw new Exception("Driver '$className' must implement DriverInterface.");
        }

        return $driver;
    }
}