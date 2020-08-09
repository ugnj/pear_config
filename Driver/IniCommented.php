<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Exception;

/**
 * Config parser for PHP .ini files with comments
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @package     Config
 */
class IniCommented extends AbstractDriver implements DriverInterface
{
    /**
     * Constructor
     *
     * @param array $options [optional] Options to be used by renderer
     */
    public function __construct(array $options = [])
    {
        parent::__construct(array_merge(['linebreak' => PHP_EOL], $options));
    }

    /**
     * Parses the data of the given configuration file
     * @access public
     *
     * @param string $source path to the configuration file
     * @param Config $obj    reference to a config object
     *
     * @return bool returns a true if ok
     * @throws Exception
     */
    public function parseData($source, Config $obj): bool
    {
        $return = true;

        if (!file_exists($source)) {
            throw new Exception('Datasource file does not exist.');
        }

        $lines = file($source);

        if ($lines === false) {
            throw new Exception('File could not be read');
        }

        $n = 0;
        //$lastLine = '';
        $currentSection = $obj->getContainer();
        foreach ($lines as $line) {
            $n++;
            if (preg_match('/^\s*;(.*?)\s*$/', $line, $match)) {
                // a comment
                $currentSection->createComment($match[1]);
            } elseif (preg_match('/^\s*$/', $line)) {
                // a blank line
                $currentSection->createBlank();
            } elseif (preg_match('/^\s*([a-zA-Z0-9_\-.\s:]*)\s*=\s*(.*)\s*$/', $line, $match)) {
                // a directive

                $values = $this->_quoteAndCommaParser($match[2]);

                if (count($values)) {
                    foreach ($values as $value) {
                        if ($value[0] === 'normal') {
                            $currentSection->createDirective(trim($match[1]), $value[1]);
                        }
                        if ($value[0] === 'comment') {
                            $currentSection->createComment(substr($value[1], 1));
                        }
                    }
                }
            } elseif (preg_match('/^\s*\[\s*(.*)\s*]\s*$/', $line, $match)) {
                // a section
                $currentSection = $obj->getContainer()->createSection($match[1]);
            } else {
                throw new Exception("Syntax error in '$source' at line $n.");
            }
        }

        return $return;
    }

    /**
     * Quote and Comma Parser for INI files
     * This function allows complex values such as:
     * <samp>
     * mydirective = "Item, number \"1\"", Item 2 ; "This" is really, really tricky
     * </samp>
     *
     * @param string $text value of a directive to parse for quotes/multiple values
     *
     * @return array   The array returned contains multiple values, if any (unquoted literals
     *                 to be used as is), and a comment, if any.  The format of the array is:
     * <pre>
     * array(array('normal', 'first value'),
     *       array('normal', 'next value'),...
     *       array('comment', '; comment with leading ;'))
     * </pre>
     * @throws Exception
     * @author Greg Beaver <cellog@users.sourceforge.net>
     * @access private
     */
    private function _quoteAndCommaParser(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            $emptyNode = [];
            $emptyNode[0][0] = 'normal';
            $emptyNode[0][1] = '';

            return $emptyNode;
        }

        // tokens
        $tokens['normal'] = ['"', ';', ','];
        $tokens['quote'] = ['"', '\\'];
        $tokens['escape'] = false; // cycle
        $tokens['after_quote'] = [',', ';'];

        // events
        $events['normal'] = ['"' => 'quote', ';' => 'comment', ',' => 'normal'];
        $events['quote'] = ['"' => 'after_quote', '\\' => 'escape'];
        $events['after_quote'] = [',' => 'normal', ';' => 'comment'];

        // state stack
        $stack = [];

        // return information
        $return = [];
        $returnPos = 0;
        //$returnType = 'normal';

        // initialize
        $stack[] = 'normal';
        $pos = 0; // position in $text

        do {
            $char = $text[$pos];
            $state = $this->_getQACEvent($stack);

            if ($tokens[$state]) {
                if (in_array($char, $tokens[$state], true)) {
                    switch ($events[$state][$char]) {
                        case 'quote' :
                            if ($state === 'normal'
                                && isset($return[$returnPos])
                                && !empty($return[$returnPos][1])) {
                                throw new Exception('invalid ini syntax, quotes cannot follow text "' . $text .'"');
                            }

                            if ($returnPos >= 0 && isset($return[$returnPos])) {
                                // trim any unnecessary whitespace in earlier entries
                                $return[$returnPos][1] = trim($return[$returnPos][1]);
                            } else {
                                $returnPos++;
                            }

                            $return[$returnPos] = ['normal', ''];
                            $stack[] = 'quote';
                            continue 2;
                        case 'comment' :
                            // comments go to the end of the line, so we are done
                            $return[++$returnPos] = ['comment', substr($text, $pos)];

                            return $return;
                        case 'after_quote' :
                            $stack[] = 'after_quote';
                            break;
                        case 'escape' :
                            // don't save the first slash
                            $stack[] = 'escape';
                            continue 2;
                        case 'normal' :
                            // start a new segment
                            if ($state === 'normal') {
                                $returnPos++;
                                continue 2;
                            }

                            while ($state !== 'normal') {
                                array_pop($stack);
                                $state = $this->_getQACEvent($stack);
                            }
                            $returnPos++;
                            break;
                        default :
                            throw new Exception("::_quoteAndCommaParser oops, state missing");
                    }
                } elseif ($state !== 'after_quote') {
                    if (!isset($return[$returnPos])) {
                        $return[$returnPos] = ['normal', ''];
                    }

                    // add this character to the current ini segment if
                    // non-empty, or if in a quote
                    if ($state === 'quote') {
                        $return[$returnPos][1] .= $char;
                    } elseif (!empty($return[$returnPos][1])
                               || (empty($return[$returnPos][1]) && trim($char) !== '')) {
                        if (!isset($return[$returnPos])) {
                            $return[$returnPos] = ['normal', ''];
                        }

                        $return[$returnPos][1] .= $char;

                        if (strcasecmp('true', $return[$returnPos][1]) === 0) {
                            $return[$returnPos][1] = '1';
                        } else if (strcasecmp('false', $return[$returnPos][1]) === 0) {
                            $return[$returnPos][1] = '';
                        }
                    }
                } elseif (trim($char) !== '') {
                    throw new Exception('invalid ini syntax, text after a quote'
                        . " not allowed '$text'");
                }
            } else {
                // no tokens, so add this one and cycle to previous state
                $return[$returnPos][1] .= $char;
                array_pop($stack);
            }
        } while (++$pos < strlen($text));

        return $return;
    }

    /**
     * Retrieve the state off of a state stack for the Quote and Comma Parser
     *
     * @param array $stack The parser state stack
     *
     * @return mixed
     * @author Greg Beaver <cellog@users.sourceforge.net>
     */
    private function _getQACEvent($stack)
    {
        return array_pop($stack);
    }

    /**
     * Returns a formatted string of the object
     *
     * @param Container $obj Container object to be output as string
     *
     * @return   string
     */
    public function toString(Container $obj): string
    {
        static $childrenCount, $commaString;
        $string = '';

        switch ($obj->getType()) {
            case 'blank':
                $string = $this->getOptions()['linebreak'];
                break;
            case 'comment':
                $string = sprintf(';%s%s', $obj->getContent(), $this->getOptions()['linebreak']);
                break;
            case 'directive':
                $count = $obj->getParent() !== null
                    ? $obj->getParent()->countChildren('directive', $obj->getName())
                    : 0;
                $content = $obj->getContent();

                if ($content === false) {
                    $content = '0';
                } elseif ($content === true) {
                    $content = '1';
                } elseif ($content === 'none'
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
                           || strlen(trim($content)) < strlen($content)) {
                    $content = sprintf('"%s"', addslashes($content));
                }

                if ($count > 1) {
                    // multiple values for a directive are separated by a comma
                    if (isset($childrenCount[$obj->getName()])) {
                        $childrenCount[$obj->getName()]++;
                    } else {
                        $childrenCount[$obj->getName()] = 0;
                        $commaString[$obj->getName()] = sprintf('%s = ', $obj->getName());
                    }
                    if ($childrenCount[$obj->getName()] === $count - 1) {
                        // Clean the static for future calls to toString
                        $string .= sprintf('%s%s%s', $commaString[$obj->getName()], $content, $this->getOptions()['linebreak']);
                        unset($childrenCount[$obj->getName()], $commaString[$obj->getName()]);
                    } else {
                        $commaString[$obj->getName()] .= sprintf('%s, ', $content);
                    }
                } else {
                    $string = sprintf('%s = %s%s', $obj->getName(), $content, $this->getOptions()['linebreak']);
                }
                break;
            case 'section':
                if (!$obj->isRoot()) {
                    $string = sprintf('[%s]%s', $obj->getName(), $this->getOptions()['linebreak']);
                }

                if ($obj->countChildren() > 0) {
                    for ($i = 0; $i < $obj->countChildren(); $i++) {
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