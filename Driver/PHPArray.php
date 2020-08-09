<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Exception;

/**
 * Config parser for common PHP configuration array
 * such as found in the horde project.
 * Options expected is:
 * 'name' => 'conf'
 * Name of the configuration array.
 * Default is $conf[].
 * 'useAttr' => true
 * Whether we render attributes
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @package     Config
 */
class PHPArray extends AbstractDriver implements DriverInterface, WritableInterface
{
    /**
     * Constructor
     *
     * @param array $options Options to be used by renderer
     */
    public function __construct($options = [])
    {
        $defaults = [
            'name'                => 'conf',
            'useAttr'             => true,
            'duplicateDirectives' => true,
        ];

        parent::__construct(array_merge($defaults, $options));
    }

    /**
     * Parses the data of the given configuration file
     * @access public
     *
     * @param mixed $source path to the configuration file
     * @param Config $obj    reference to a config object
     *
     * @return mixed    returns a PEAR_ERROR, if error occurs or true if ok
     * @throws Exception
     */
    public function parseData($source, Config $obj): bool
    {
        $return = true;
        if (empty($source)) {
            throw new Exception("Datasource file path is empty.");
        }

        if (is_array($source)) {
            $this->_parseArray($source, $obj->getContainer());
        } else {
            if (!file_exists($source)) {
                throw new Exception("Datasource file does not exist.");
            }

            /** @noinspection PhpIncludeInspection */
            include($source);

            if (!isset(${$this->getOptions()['name']}) || !is_array(${$this->getOptions()['name']})) {
                throw new Exception("File '$source' does not contain a required '" . $this->getOptions()['name'] . "' array.");
            }

            $this->_parseArray(${$this->getOptions()['name']}, $obj->getContainer());
        }

        return $return;
    }

    /**
     * Parses the PHP array recursively
     *
     * @param array     $array     array values from the config file
     * @param Container $container reference to the container object
     *
     * @access private
     * @return void
     * @throws Exception
     */
    private function _parseArray(array $array, Container $container): void
    {
        foreach ($array as $key => $value) {
            switch ((string)$key) {
                case '@':
                    $container->setAttributes($value);
                    break;
                case '#':
                    $container->setType('directive');
                    $container->setContent($value);
                    break;
                default:
                    if (is_array($value)) {
                        if ($this->getOptions()['duplicateDirectives'] === true
                            //speed (first/one key is numeric)
                            && is_int(key($value))
                            //accuracy (all keys are numeric)
                            && 1 === count(array_unique(array_map('is_numeric', array_keys($value))))
                        ) {
                            foreach ($value as $nestedValue) {
                                if (is_array($nestedValue)) {
                                    $section = $container->createSection($key);
                                    $this->_parseArray($nestedValue, $section);
                                } else {
                                    $container->createDirective($key, $nestedValue);
                                }
                            }
                        } else {
                            $section = $container->createSection($key);
                            $this->_parseArray($value, $section);
                        }
                    } else {
                        $container->createDirective($key, $value);
                    }
            }
        }
    }

    /**
     * Writes the configuration to a file
     *
     * @param string    $fileName info on datasource such as path to the configuration file
     * @param Container $obj      (optional)type of configuration
     *
     * @access public
     * @return bool
     * @throws Exception
     */
    public function writeData(string $fileName, Container $obj): bool
    {
        $fp = @fopen($fileName, 'wb');
        if ($fp) {
            $string = "<?php\n" . $this->toString($obj) . "?>"; // <? : Fix my syntax coloring
            $len = strlen($string);
            @flock($fp, LOCK_EX);
            @fwrite($fp, $string, $len);
            @flock($fp, LOCK_UN);
            @fclose($fp);

            return true;
        }

        throw new Exception('Cannot open datasource for writing.');
    }

    /**
     * Returns a formatted string of the object
     *
     * @param Container $obj Container object to be output as string
     *
     * @access   public
     * @return   string
     */
    public function toString(Container $obj): string
    {
        $string = '';

        switch ($obj->getType()) {
            case 'blank':
                $string .= "\n";
                break;
            case 'comment':
                $string .= '// ' . $obj->getContent() . "\n";
                break;
            case 'directive':
                $attrString = '';
                $parentString = $this->_getParentString($obj);
                $attributes = $obj->getAttributes();
                if (is_array($attributes) && $this->getOptions()['useAttr'] && count($attributes) > 0) {
                    // Directive with attributes '@' and value '#'
                    $string .= $parentString . "['#']";
                    foreach ($attributes as $attr => $val) {
                        $attrString .= $parentString . "['@']"
                                       . "['" . $attr . "'] = '" . addcslashes($val, "\\'") . "';\n";
                    }
                } else {
                    $string .= $parentString;
                }
                $string .= ' = ';
                if (is_string($obj->getContent())) {
                    $string .= "'" . addcslashes($obj->getContent(), "\\'") . "'";
                } else if (is_int($obj->getContent()) || is_float($obj->getContent())) {
                    $string .= $obj->getContent();
                } else if (is_bool($obj->getContent())) {
                    $string .= ($obj->getContent())
                        ? 'true'
                        : 'false';
                } else if ($obj->getContent() === null) {
                    $string .= 'null';
                }
                $string .= ";\n";
                $string .= $attrString;
                break;
            case 'section':
                $attrString = '';
                $attributes = $obj->getAttributes();
                if (is_array($attributes) && $this->getOptions()['useAttr'] && count($attributes) > 0) {
                    $parentString = $this->_getParentString($obj);
                    foreach ($attributes as $attr => $val) {
                        $attrString .= $parentString . "['@']"
                                       . "['" . $attr . "'] = '" . addcslashes($val, "\\'") . "';\n";
                    }
                }
                $string .= $attrString;
                if ($count = $obj->countChildren()) {
                    for ($i = 0; $i < $count; $i++) {
                        $string .= $this->toString($obj->getChild($i));
                    }
                }
                break;
            default:
                $string = '';
        }

        return $string;
    }

    /**
     * Returns a formatted string of the object parents
     *
     * @param Container $obj
     *
     * @return string
     */
    private function _getParentString(Container $obj): string
    {
        $string = '';
        if (!$obj->isRoot()) {
            $string = is_int($obj->getName())
                ? "[" . $obj->getName() . "]"
                : "['" . $obj->getName() . "']";
            $string = $this->_getParentString($obj->getParent()) . $string;
            $count = $obj->getParent() !== null ? $obj->getParent()->countChildren(null, $obj->getName()) : 0;
            if ($count > 1) {
                $string .= '[' . $obj->getItemPosition(false) . ']';
            }
        } elseif (empty($this->getOptions()['name'])) {
            $string .= '$' . $obj->getName();
        } else {
            $string .= '$' . $this->getOptions()['name'];
        }

        return $string;
    }
}