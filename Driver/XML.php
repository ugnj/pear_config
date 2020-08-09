<?php

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Exception;

require_once('XML/Parser.php');
require_once('XML/Util.php');

/**
 * Config parser for XML Files
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @package     Config
 */
class XML extends XML_Parser implements DriverInterface
{
    /**
     * Deep level used for indentation
     * @var  int
     * @access private
     */
    private int $_deep = -1;
    /**
     * This class options:
     * version (1.0) : XML version
     * encoding (ISO-8859-1) : XML content encoding
     * name      : like in phparray, name of your config global entity
     * indent    : char used for indentation
     * linebreak : char used for linebreak
     * addDecl   : whether to add the xml declaration at beginning or not
     * useAttr   : whether to use the attributes
     * isFile    : whether the given content is a file or an XML string
     * useCData  : whether to surround data with <![CDATA[...]]>
     * @var  array
     */
    private array $options = [
        'version'   => '1.0',
        'encoding'  => 'ISO-8859-1',
        'name'      => '',
        'indent'    => '  ',
        'linebreak' => "\n",
        'addDecl'   => true,
        'useAttr'   => true,
        'isFile'    => true,
        'useCData'  => false,
    ];
    /**
     * Container objects
     * @var Container[]
     */
    private array $containers = [];
    private ?string $cdata = null;

    /**
     * Constructor
     * @access public
     *
     * @param string $options        Options to be used by renderer
     *                               version     : (1.0) XML version
     *                               encoding    : (ISO-8859-1) XML content encoding
     *                               name        : like in phparray, name of your config global entity
     *                               indent      : char used for indentation
     *                               linebreak   : char used for linebreak
     *                               addDecl     : whether to add the xml declaration at beginning or not
     *                               useAttr     : whether to use the attributes
     *                               isFile      : whether the given content is a file or an XML string
     */

    public function __construct($options = [])
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Parses the data of the given configuration file
     * @access public
     *
     * @param string $source path to the configuration file
     * @param Config $obj    reference to a config object
     *
     * @return bool returns true if ok
     */
    public function parseData($source, Config $obj): bool
    {
        $err = true;
        $this->folding = false;
        $this->cdata = null;
        $this->XML_Parser($this->options['encoding'], 'event');
        $this->containers[0] = $obj->getContainer();
        if (is_string($source)) {
            if ($this->options['isFile']) {
                $err = $this->setInputFile($source);
                if (PEAR::isError($err)) {
                    return $err;
                }
                $err = $this->parse();
            } else {
                $err = $this->parseString($source, true);
            }
        } else {
            $this->setInput($source);
            $err = $this->parse();
        }

        return $err;
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
        $indent = '';
        if (!$obj->isRoot()) {
            // no indent for root
            $this->_deep++;
            $indent = str_repeat($this->options['indent'], $this->_deep);
        } else {
            // Initialize string with xml declaration
            $string = '';
            if ($this->options['addDecl']) {
                $string .= XML_Util::getXMLDeclaration($this->options['version'], $this->options['encoding']);
                $string .= $this->options['linebreak'];
            }
            if (!empty($this->options['name'])) {
                $string .= '<' . $this->options['name'] . '>' . $this->options['linebreak'];
                $this->_deep++;
                $indent = str_repeat($this->options['indent'], $this->_deep);
            }
        }
        if (!isset($string)) {
            $string = '';
        }
        switch ($obj->getType()) {
            case 'directive':
                $attributes = ($this->options['useAttr'])
                    ? $obj->getAttributes()
                    : [];
                $string .= $indent . XML_Util::createTag(
                        $obj->getName(),
                        $attributes,
                        $obj->getContent(),
                        null,
                        ($this->options['useCData']
                            ? XML_UTIL_CDATA_SECTION
                            : XML_UTIL_REPLACE_ENTITIES)
                    );
                $string .= $this->options['linebreak'];
                break;
            case 'comment':
                $string .= $indent . '<!-- ' . $obj->getContent() . ' -->';
                $string .= $this->options['linebreak'];
                break;
            case 'section':
                if (!$obj->isRoot()) {
                    $string = $indent . '<' . $obj->getName();
                    $string .= ($this->options['useAttr'])
                        ? XML_Util::attributesToString($obj->getAttributes())
                        : '';
                }
                if ($children = $obj->countChildren()) {
                    if (!$obj->isRoot()) {
                        $string .= '>' . $this->options['linebreak'];
                    }
                    for ($i = 0; $i < $children; $i++) {
                        $string .= $this->toString($obj->getChild($i));
                    }
                }
                if (!$obj->isRoot()) {
                    if ($children) {
                        $string .= $indent . '</' . $obj->getName() . '>' . $this->options['linebreak'];
                    } else {
                        $string .= '/>' . $this->options['linebreak'];
                    }
                } else {
                    if (!empty($this->options['name'])) {
                        $string .= '</' . $this->options['name'] . '>' . $this->options['linebreak'];
                    }
                }
                break;
            default:
                $string = '';
        }
        if (!$obj->isRoot()) {
            $this->_deep--;
        }

        return $string;
    }

    /**
     * Handler for the xml-data
     *
     * @param mixed  $xp      ignored
     * @param string $elem    name of the element
     * @param array  $attribs attributes for the generated node
     */
    private function startHandler($xp, $elem, &$attribs): void
    {
        $container = new Container('section', $elem, null, $attribs);
        $this->containers[] = $container;
    }

    /**
     * Handler for the xml-data
     *
     * @param mixed  $xp   ignored
     * @param string $elem name of the element
     *
     * @throws Exception
     */
    private function endHandler($xp, $elem): void
    {
        $count = count($this->containers);
        $container = $this->containers[$count - 1];
        $currentSection = $this->containers[$count - 2];

        if ($container->countChildren() === 0) {
            $container->setType('directive');
            $container->setContent(trim($this->cdata));
        }

        $currentSection->addItem($container);
        array_pop($this->containers);
        $this->cdata = null;
    }

    /**
     * The xml character data handler
     *
     * @param mixed  $xp   ignored
     * @param string $data PCDATA between tags
     *
     * @access private
     */
    private function cdataHandler($xp, string $cdata)
    {
        $this->cdata .= $cdata;
    }
}