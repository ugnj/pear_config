<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Exception;

/**
 * Config parser for generic .conf files
 */
class Generic extends AbstractDriver implements DriverInterface
{
    /**
     * Constructor
     * @access public
     *
     * @param array $options [optional] Options to be used by renderer
     */
    public function __construct(array $options = [])
    {
        if (empty($options['comment'])) {
            $options['comment'] = '#';
        }

        if (empty($options['equals'])) {
            $options['equals'] = ':';
        }

        if (empty($options['newline'])) {
            $options['newline'] = '\\';
        }

        parent::__construct($options);
    }

    /**
     * Parses the data of the given configuration file
     * @access public
     *
     * @param string $source path to the configuration file
     * @param Config $obj    reference to a config object
     *
     * @return mixed returns a PEAR_ERROR, if error occurs or true if ok
     * @throws Exception
     */
    public function parseData($source, Config $obj): bool
    {
        $return = true;

        if (!is_readable($source)) {
            throw new Exception("Datasource file cannot be read.");
        }

        $lines = file($source);
        $n = 0;
        $lastLine = '';
        $currentSection = $obj->getContainer();

        foreach ($lines as $line) {
            $n++;
            if (!preg_match('/^\s*' . $this->getOptions()['comment'] . '/', $line)
                && preg_match('/^\s*(.*)' . $this->getOptions()['newline'] . '\s*$/', $line, $match)) {
                // directive on more than one line
                $lastLine .= $match[1];
                continue;
            }

            if ($lastLine !== '') {
                $line = $lastLine . trim($line);
                $lastLine = '';
            }

            if (preg_match('/^\s*' . $this->getOptions()['comment'] . '+\s*(.*?)\s*$/', $line, $match)) {
                // a comment
                $currentSection->createComment($match[1]);
            } else if (preg_match('/^\s*$/', $line)) {
                // a blank line
                $currentSection->createBlank();
            } else if (preg_match('/^\s*([\w-]+)\s*' . $this->getOptions()['equals'] . '\s*((.*?)|)\s*$/', $line, $match)) {
                // a directive
                $currentSection->createDirective($match[1], $match[2]);
            } else {
                throw new Exception("Syntax error in '$source' at line $n.");
            }
        }

        return $return;
    }

    /**
     * Returns a formatted string of the object
     *
     * @param object $obj Container object to be output as string
     *
     * @access public
     * @return string
     */
    public function toString($obj): string
    {
        $string = '';
        switch ($obj->type) {
            case 'blank':
                $string = "\n";
                break;
            case 'comment':
                $string = $this->getOptions()['comment'] . $obj->content . "\n";
                break;
            case 'directive':
                $string = $obj->name . $this->getOptions()['equals'] . $obj->content . "\n";
                break;
            case 'section':
                // How to deal with sections ???
                if (count($obj->children) > 0) {
                    for ($i = 0, $iMax = count($obj->children); $i < $iMax; $i++) {
                        $string .= $this->toString($obj->getChild($i));
                    }
                }
                break;
            default:
                $string = '';
        }

        return $string;
    }
}