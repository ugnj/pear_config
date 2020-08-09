<?php
namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Exception;

/**
 * Config parser for PHP constant files
 * @category Configuration
 * @package  Config
 * @author   Phillip Oertel <me@phillipoertel.com>
 * @license  http://www.php.net/license PHP License
 * @link     http://pear.php.net/package/Config
 */
class PHPConstants extends AbstractDriver implements DriverInterface, WritableInterface
{
    /**
     * Constructor
     *
     * @param array $options [optional] Options to be used by renderer
     *
     * @access public
     */
    public function __construct(array $options = [])
    {
        $options = array_merge(['lowercase' => false], $options);
        parent::__construct($options);
    }

    /**
     * Parses the data of the given configuration file
     *
     * @param string $dataSrc Path to the configuration file
     * @param Config $obj     Reference to a config object
     *
     * @return mixed PEAR_ERROR, if error occurs or true if ok
     * @throws Exception
     */
    public function parseData($dataSrc, Config $obj): bool
    {
        $return = true;

        if (!file_exists($dataSrc)) {
            throw new Exception('Datasource file does not exist.');
        }

        $fileContent = file_get_contents($dataSrc, true);

        if (!$fileContent) {
            throw new Exception("File '$dataSrc' could not be read.");
        }

        $rows = explode("\n", $fileContent);

        foreach ($rows as $i => $iValue) {
            $line = $iValue;

            //blanks?

            // sections
            if (preg_match("/^\/\/\s*$/", $line)) {
                preg_match("/^\/\/\s*(.+)$/", $rows[$i + 1], $matches);
                $obj->getContainer()->createSection(trim($matches[1]));
                continue;
            }

            // comments
            if (preg_match("/^\/\/\s*(.+)$/", $line, $matches)
                || preg_match("/^#\s*(.+)$/", $line, $matches)
            ) {
                $obj->getContainer()->createComment(trim($matches[1]));
                continue;
            }

            // directives
            $regex = "/^\s*define\s*\('([A-Z1-9_]+)',\s*'*(.[^\']*)'*\)/";
            preg_match($regex, $line, $matches);
            if (!empty($matches)) {
                $name = trim($matches[1]);
                if ($this->getOptions()['lowercase']) {
                    $name = strtolower($name);
                }
                $obj->getContainer()->createDirective(
                    $name,
                    trim($matches[2])
                );
            }
        }

        return $return;
    }

    /**
     * Writes the configuration to a file
     *
     * @param mixed  $fileName Info on datasource such as path to the file
     * @param Container $obj      Configuration object to write
     *
     * @return mixed PEAR_Error on failure or boolean true if all went well
     * @access public
     * @throws Exception
     */
    public function writeData(string $fileName, Container $obj)
    {
        $fp = @fopen($fileName, 'wb');
        if (!$fp) {
            throw new Exception('Cannot open datasource for writing.');
        }

        $string = "<?php";
        $string .= "\n\n";
        $string .= '/**' . chr(10);
        $string .= ' *' . chr(10);
        $string .= ' * AUTOMATICALLY GENERATED CODE - DO NOT EDIT BY HAND' . chr(10);
        $string .= ' *' . chr(10);
        $string .= '**/' . chr(10);
        $string .= $this->toString($obj);
        $string .= "\n?>"; // <? : Fix my syntax coloring

        $len = strlen($string);
        @flock($fp, LOCK_EX);
        @fwrite($fp, $string, $len);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        // need an error check here

        return true;
    } // end func toString

    /**
     * Returns a formatted string of the object
     *
     * @param Container $obj Container object to be output as string
     *
     * @return string
     */
    public function toString(Container $obj): string
    {
        $string = '';

        switch ($obj->getType()) {
            case 'blank':
                $string = "\n";
                break;

            case 'comment':
                $string = '// ' . $obj->getContent() . "\n";
                break;

            case 'directive':
                $content = $obj->getContent();
                // don't quote numeric values, true/false and constants
                if (is_bool($content)) {
                    $content = var_export($content, true);
                } else if (!is_numeric($content)
                           && !in_array($content, ['false', 'true'])
                           && !preg_match('/^[A-Z_]+$/', $content)
                ) {
                    $content = "'" . str_replace("'", '\\\'', $content) . "'";
                }
                $string = 'define('
                          . '\'' . strtoupper($obj->getName()) . '\''
                          . ', ' . $content . ');'
                          . chr(10);
                break;

            case 'section':
                if (!$obj->isRoot()) {
                    $string = chr(10);
                    $string .= '//' . chr(10);
                    $string .= '// ' . $obj->getName() . chr(10);
                    $string .= '//' . chr(10);
                }
                if ($obj->countChildren() > 0) {
                    for ($i = 0, $max = $obj->countChildren(); $i < $max; $i++) {
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