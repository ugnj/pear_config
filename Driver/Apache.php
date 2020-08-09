<?php

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Exception;

/**
 * Simple config parser for apache .conf files
 * A more complex version could handle directives as
 * associative arrays.
 */
class Apache extends AbstractDriver implements DriverInterface
{
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
        $sections[0] = $obj->getContainer();

        foreach ($lines as $line) {
            $n++;
            if (!preg_match('/^\s*#/', $line)
                && preg_match('/^\s*(.*)\s+\\$/', $line, $match)) {
                // directive on more than one line
                $lastLine .= $match[1] . ' ';
                continue;
            }
            if ($lastLine !== '') {
                $line = $lastLine . trim($line);
                $lastLine = '';
            }
            if (preg_match('/^\s*#+\s*(.*?)\s*$/', $line, $match)) {
                // a comment
                $currentSection =& $sections[count($sections) - 1];
                $currentSection->createComment($match[1]);
            } else if (trim($line) === '') {
                // a blank line
                $currentSection =& $sections[count($sections) - 1];
                $currentSection->createBlank();
            } else if (preg_match('/^\s*(\w+)(?:\s+(.*?)|)\s*$/', $line, $match)) {
                // a directive
                $currentSection =& $sections[count($sections) - 1];
                $currentSection->createDirective($match[1], $match[2]);
            } else if (preg_match('/^\s*<(\w+)(?:\s+([^>]*)|\s*)>\s*$/', $line, $match)) {
                // a section opening
                if (!isset($match[2])) {
                    $match[2] = '';
                }
                $currentSection =& $sections[count($sections) - 1];
                $attributes = explode(' ', $match[2]);
                $sections[] = $currentSection->createSection($match[1], $attributes);
            } else if (preg_match('/^\s*<\/(\w+)\s*>\s*$/', $line, $match)) {
                // a section closing
                $currentSection =& $sections[count($sections) - 1];

                if ($currentSection->getName() !== $match[1]) {
                    throw new Exception("Section not closed in '$source' at line $n.");
                }
                array_pop($sections);
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
     * @access   public
     * @return   string
     */
    public function toString($obj): string
    {
        static $deep = -1;
        $ident = '';
        if (!$obj->isRoot()) {
            // no indent for root
            $deep++;
            $ident = str_repeat('  ', $deep);
        }

        $string = '';

        switch ($obj->type) {
            case 'blank':
                $string = "\n";
                break;
            case 'comment':
                $string = $ident . '# ' . $obj->content . "\n";
                break;
            case 'directive':
                $string = $ident . $obj->name . ' ' . $obj->content . "\n";
                break;
            case 'section':
                if (!$obj->isRoot()) {
                    $string = $ident . '<' . $obj->name;
                    if (is_array($obj->attributes) && count($obj->attributes) > 0) {
                        foreach ($obj->attributes as $attr => $val) {
                            $string .= ' ' . $val;
                        }
                    }
                    $string .= ">\n";
                }
                if (count($obj->children) > 0) {
                    for ($i = 0, $iMax = count($obj->children); $i < $iMax; $i++) {
                        $string .= $this->toString($obj->getChild($i));
                    }
                }
                if (!$obj->isRoot()) {
                    // object is not root
                    $string .= $ident . '</' . $obj->name . ">\n";
                }
                break;
            default:
                $string = '';
        }
        if (!$obj->isRoot()) {
            $deep--;
        }

        return $string;
    } // end func toString
}
