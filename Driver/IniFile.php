<?php
declare(strict_types=1);
/**
 * Part of the PEAR Config package
 * PHP Version 4
 * @category Configuration
 * @package  Config
 * @author   Bertrand Mansion <bmansion@mamasam.com>
 * @license  http://www.php.net/license PHP License
 * @link     http://pear.php.net/package/Config
 */

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Exception;

/**
 * Config parser for PHP .ini files
 * Faster because it uses parse_ini_file() but get rid of comments,
 * quotes, types and converts On, Off, True, False, Yes, No to 0 and 1.
 * Empty lines and comments are not preserved.
 * @category Configuration
 * @package  Config
 * @author   Bertrand Mansion <bmansion@mamasam.com>
 * @license  http://www.php.net/license PHP License
 * @link     http://pear.php.net/package/Config
 */
class IniFile extends AbstractDriver implements DriverInterface
{
    /**
     * Parses the data of the given configuration file
     *
     * @param string $source path to the configuration file
     * @param Config $obj    reference to a config object
     *
     * @return mixed Returns a PEAR_ERROR, if error occurs or true if ok
     * @access public
     * @throws Exception
     */
    public function parseData($source, Config $obj): bool
    {
        $return = true;

        if (!file_exists($source)) {
            throw new Exception("Datasource file does not exist.");
        }

        $currentSection = $obj->getContainer();
        $confArray = parse_ini_file($source, true);

        if (!$confArray) {
            throw new Exception("File '$source' does not contain configuration data.");
        }

        foreach ($confArray as $key => $value) {
            if (is_array($value)) {
                $currentSection = $currentSection->createSection($key);
                foreach ($value as $directive => $content) {
                    // try to split the value if comma found
                    if (!is_array($content) && strpos($content, '"') === false) {
                        $values = preg_split('/\s*,\s+/', $content);
                        if (count($values) > 1) {
                            foreach ($values as $k => $v) {
                                $currentSection->createDirective($directive, $v);
                            }
                        } else {
                            $currentSection->createDirective($directive, $content);
                        }
                    } else {
                        $currentSection->createDirective($directive, $content);
                    }
                }
            } else {
                $currentSection->createDirective($key, $value);
            }
        }

        return $return;
    }

    /**
     * Returns a formatted string of the object
     *
     * @param Container $obj Container object to be output as string
     *
     * @return string
     * @access public
     */
    public function toString(Container $obj): string
    {
        static $childrenCount, $commaString;

        $string = '';
        switch ($obj->getType()) {
            case 'blank':
                $string = "\n";
                break;
            case 'comment':
                $string = ';' . $obj->getContent() . "\n";
                break;
            case 'directive':
                $count = $obj->getParent() !== null
                    ? $obj->getParent()->countChildren('directive', $obj->getName())
                    : 0;
                $content = $obj->getContent();
                if (!is_array($content)) {
                    $content = $this->contentToString($content);

                    if ($count > 1) {
                        // multiple values for a directive are separated by a comma
                        if (isset($childrenCount[$obj->getName()])) {
                            $childrenCount[$obj->getName()]++;
                        } else {
                            $childrenCount[$obj->getName()] = 0;
                            $commaString[$obj->getName()] = $obj->getName() . '=';
                        }
                        if ($childrenCount[$obj->getName()] === $count - 1) {
                            // Clean the static for future calls to toString
                            $string .= $commaString[$obj->getName()] . $content . "\n";
                            unset($childrenCount[$obj->getName()], $commaString[$obj->getName()]);
                        } else {
                            $commaString[$obj->getName()] .= $content . ', ';
                        }
                    } else {
                        $string = $obj->getName() . '=' . $content . "\n";
                    }
                } else {
                    //array
                    $string = '';
                    $n = 0;
                    foreach ($content as $contentKey => $contentValue) {
                        if (is_int($contentKey) && $contentKey === $n) {
                            $stringKey = '';
                            ++$n;
                        } else {
                            $stringKey = $contentKey;
                        }
                        $string .= $obj->getName() . '[' . $stringKey . ']='
                                   . $this->contentToString($contentValue) . "\n";
                    }
                }
                break;
            case 'section':
                if (!$obj->isRoot()) {
                    $string = '[' . $obj->getName() . "]\n";
                }
                if ($obj->countChildren() > 0) {
                    for ($i = 0, $iMax = $obj->countChildren(); $i < $iMax; $i++) {
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
     * Converts a given content variable to a string that can
     * be used as value in a ini file
     *
     * @param mixed $content Value
     *
     * @return string $content String to be used as ini value
     */
    public function contentToString($content): string
    {
        if ($content === false) {
            $content = '0';
        } else if ($content === true) {
            $content = '1';
        } else if ($content === 'none'
                   || strpos($content, ',') !== false
                   || strpos($content, ';') !== false
                   || strpos($content, '=') !== false
                   || strpos($content, '"') !== false
                   || strpos($content, '%') !== false
                   || strpos($content, '~') !== false
                   || strpos($content, '!') !== false
                   || strpos($content, '|') !== false
                   || strpos($content, '&') !== false
                   || strpos($content, '(') !== false
                   || strpos($content, ')') !== false
                   || strlen(trim($content)) < strlen($content)
        ) {
            $content = '"' . addslashes($content) . '"';
        }

        return $content;
    }
}