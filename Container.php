<?php

namespace PEAR\Config;

use PEAR\Config\Driver\DriverInterface;
use PEAR\Config\Driver\WritableInterface;

/**
 * Interface for Config containers
 * @author   Bertrand Mansion <bmansion@mamasam.com>
 * @package  Config
 */
class Container
{
    /**
     * Container object type
     * Ex: section, directive, comment, blank
     * @var  string
     */
    private string $type;
    /**
     * Container object name
     * @var  string
     */
    private string $name;
    /**
     * Container object content
     * @var  string
     */
    private string $content;
    /**
     * Container object children
     * @var Container[]
     */
    private array $children = [];
    /**
     * Reference to container object's parent
     * @var  ?Container
     */
    private ?Container $parent;
    /**
     * Array of attributes for this item
     * @var  ?array
     */
    private ?array $attributes;
    /**
     * Unique id to differenciate nodes
     * This is used to compare nodes
     * Will not be needed anymore when this class will use ZendEngine 2
     * @var string
     */
    private string $_id;

    /**
     * Constructor
     *
     * @param string $type       Type of container object
     * @param string $name       Name of container object
     * @param string $content    Content of container object
     * @param ?array $attributes Array of attributes for container object
     */
    public function __construct(
        string $type = 'section',
        string $name = '',
        string $content = '',
        array $attributes = null
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->content = $content;
        $this->attributes = $attributes;
        $this->parent = null;
        $this->_id = uniqid($name . $type, true);
    }

    /**
     * Adds a comment to this item.
     * This is a helper method that calls createItem
     *
     * @param string     $content Object content
     * @param string     $where   Position : 'top', 'bottom', 'before', 'after'
     * @param ?Container $target  Needed when $where is 'before' or 'after'
     *
     * @return Container reference to new item or Pear_Error
     * @throws Exception
     */
    public function createComment(string $content = '', string $where = 'bottom', Container $target = null): Container
    {
        return $this->createItem('comment', null, $content, null, $where, $target);
    }

    /**
     * Create a child for this item.
     *
     * @param string     $type       type of item: directive, section, comment, blank...
     * @param string     $name       item name
     * @param string     $content    item content
     * @param ?array     $attributes item attributes
     * @param string     $where      choose a position 'bottom', 'top', 'after', 'before'
     * @param ?Container $target     needed if you choose 'before' or 'after' for where
     *
     * @return Container  reference to new item or Pear_Error
     * @throws Exception
     */
    public function createItem(
        string $type,
        string $name,
        string $content,
        array $attributes = null,
        string $where = 'bottom',
        Container $target = null
    ): Container {
        $item = new Container($type, $name, $content, $attributes);

        return $this->addItem($item, $where, $target);
    }

    /**
     * Adds an item to this item.
     *
     * @param Container  $item   a container object
     * @param string     $where  choose a position 'bottom', 'top', 'after', 'before'
     * @param ?Container $target needed if you choose 'before' or 'after' in $where
     *
     * @return mixed    reference to added container on success, Pear_Error on error
     * @throws Exception
     */
    public function addItem(Container $item, string $where = 'bottom', Container $target = null)
    {
        if ($this->type !== 'section') {
            throw new Exception('Container::addItem must be called on a section type object.');
        }

        if ($target === null) {
            $target = $this;
        }

        switch ($where) {
            case 'before':
                $index = $target->getItemIndex();
                break;
            case 'after':
                $index = $target->getItemIndex() + 1;
                break;
            case 'top':
                $index = 0;
                break;
            case 'bottom':
                $index = -1;
                break;
            default:
                throw new Exception('Use only top, bottom, before or after in Container::addItem.');
        }

        if (isset($index) && $index >= 0) {
            array_splice($this->children, $index, 0, 'tmp');
        } else {
            $index = count($this->children);
        }

        $this->children[$index] = $item;
        $this->children[$index]->parent = $this;

        return $item;
    }

    /**
     * Returns the item index in its parent children array.
     * @return int returns int or null if root object
     */
    public function getItemIndex(): int
    {
        if (is_object($this->parent)) {
            $children = $this->parent->children;

            foreach ($children as $i => $iValue) {
                if ($iValue->_id === $this->_id) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * Adds a blank line to this item.
     * This is a helper method that calls createItem
     *
     * @param string     $where
     * @param ?Container $target
     *
     * @return Container reference to new item or Pear_Error
     * @throws Exception
     */
    public function createBlank(string $where = 'bottom', Container $target = null): Container
    {
        return $this->createItem('blank', '', '', null, $where, $target);
    }

    /**
     * Adds a section to this item.
     * This is a helper method that calls createItem
     * If the section already exists, it won't create a new one.
     * It will return reference to existing item.
     *
     * @param string     $name       Name of new section
     * @param ?array     $attributes Section attributes
     * @param string     $where      Position : 'top', 'bottom', 'before', 'after'
     * @param ?Container $target     Needed when $where is 'before' or 'after'
     *
     * @return Container reference to new item or Pear_Error
     * @throws Exception
     */
    public function createSection(
        string $name,
        array $attributes = null,
        string $where = 'bottom',
        Container $target = null
    ): Container {
        return $this->createItem('section', $name, '', $attributes, $where, $target);
    }

    /**
     * Return a child directive's content.
     * This method can use two different search approach, depending on
     * the parameter it is given. If the parameter is an array, it will use
     * the {@link Container::searchPath()} method. If it is a string,
     * it will use the {@link Container::getItem()} method.
     * Example:
     * <code>
     * require_once 'Config.php';
     * $ini = new Config();
     * $conf =& $ini->parseConfig('/path/to/config.ini', 'inicommented');
     * // Will return the value found at :
     * // [Database]
     * // host=localhost
     * echo $conf->directiveContent(array('Database', 'host')));
     * // Will return the value found at :
     * // date="dec-2004"
     * echo $conf->directiveContent('date');
     * </code>
     *
     * @param mixed   Search path and attributes or a directive name
     * @param int     Index of the item in the returned directive list.
     *                   Eventually used if args is a string.
     *
     * @return   mixed   Content of directive or false if not found.
     * @throws Exception
     */
    public function directiveContent($args, $index = -1)
    {
        $item = is_array($args)
            ? $this->searchPath($args)
            : $this->getItem('directive', $args, null, null, $index);

        if ($item) {
            return $item->getContent();
        }

        return false;
    }

    /**
     * Finds a node using XPATH like format.
     * The search format is an array:
     * array(item1, item2, item3, ...)
     * Each item can be defined as the following:
     * item = 'string' : will match the container named 'string'
     * item = array('string', array('name' => 'xyz'))
     * will match the container name 'string' whose attribute name is equal to "xyz"
     * For example : <string name="xyz">
     *
     * @param array Search path and attributes
     *
     * @return   mixed   Container object, array of Container objects or false on failure.
     * @throws Exception
     */
    public function searchPath(array $args)
    {
        if ($this->type !== 'section') {
            throw new Exception('Container::searchPath must be called on a section type object.');
        }

        $arg = array_shift($args);

        if (is_array($arg)) {
            [$name, $attributes] = $arg;
        } else {
            $name = $arg;
            $attributes = null;
        }

        // find all the matches for first..
        $match = $this->getItem(null, $name, null, $attributes);

        if (!$match) {
            return false;
        }

        if (!empty($args)) {
            return $match->searchPath($args);
        }

        return $match;
    }

    /**
     * Tries to find the specified item(s) and returns the objects.
     * Examples:
     * $directives =& $obj->getItem('directive');
     * $directive_bar_4 =& $obj->getItem('directive', 'bar', null, 4);
     * $section_foo =& $obj->getItem('section', 'foo');
     * This method can only be called on an object of type 'section'.
     * Note that root is a section.
     * This method is not recursive and tries to keep the current structure.
     * For a deeper search, use searchPath()
     *
     * @param ?string $type       Type of item: directive, section, comment, blank...
     * @param ?string $name       Item name
     * @param ?string $content    Find item with this content
     * @param ?array  $attributes Find item with attribute set to the given value
     * @param int     $index      Index of the item in the returned object list. If it is not set, will try to return
     *                            the last item with this name.
     *
     * @return mixed  reference to item found or false when not found
     * @throws Exception
     * @see &searchPath()
     */
    public function getItem(
        string $type = null,
        string $name = null,
        string $content = null,
        array $attributes = null,
        int $index = -1
    )
    {
        if ($this->type !== 'section') {
            throw new Exception('Container::getItem must be called on a section type object.');
        }
        $testFields = [];

        if (!is_null($type)) {
            $testFields[] = 'type';
        }

        if (!is_null($name)) {
            $testFields[] = 'name';
        }

        if (!is_null($content)) {
            $testFields[] = 'content';
        }

        if (!is_null($attributes)) {
            $testFields[] = 'attributes';
        }

        $itemsArr = [];
        $fieldsToMatch = count($testFields);

        for ($i = 0, $count = count($this->children); $i < $count; $i++) {
            $match = 0;
            reset($testFields);

            foreach ($testFields as $field) {
                if ($field !== 'attributes') {
                    if ($this->children[$i]->$field === ${$field}) {
                        $match++;
                    }
                } else {
                    // Look for attributes in array
                    $attrToMatch = count($attributes);
                    $attrMatch = 0;
                    foreach ($attributes as $key => $value) {
                        if (isset($this->children[$i]->attributes[$key])
                            && $this->children[$i]->attributes[$key]
                               === $value) {
                            $attrMatch++;
                        }
                    }

                    if ($attrMatch === $attrToMatch) {
                        $match++;
                    }
                }
            }

            if ($match === $fieldsToMatch) {
                $itemsArr[] =& $this->children[$i];
            }
        }

        if ($index >= 0) {
            return $itemsArr[$index] ?? false;
        }

        if ($count = count($itemsArr)) {
            return $itemsArr[$count - 1];
        }

        return false;
    }

    /**
     * Returns how many children this container has
     *
     * @param ?string $type type of children counted
     * @param ?string $name name of children counted
     *
     * @return int number of children found
     */
    public function countChildren(string $type = null, string $name = null): int
    {
        if (is_null($type) && is_null($name)) {
            return count($this->children);
        }

        $count = 0;

        if (isset($name, $type)) {
            for ($i = 0, $children = count($this->children); $i < $children; $i++) {
                if ($this->children[$i]->name === $name && $this->children[$i]->type === $type) {
                    $count++;
                }
            }

            return $count;
        }

        if (isset($type)) {
            for ($i = 0, $children = count($this->children); $i < $children; $i++) {
                if ($this->children[$i]->type === $type) {
                    $count++;
                }
            }

            return $count;
        }

        if (isset($name)) {
            // Some directives can have the same name
            for ($i = 0, $children = count($this->children); $i < $children; $i++) {
                if ($this->children[$i]->name === $name) {
                    $count++;
                }
            }

            return $count;
        }

        return $count;
    }

    /**
     * Deletes an item (section, directive, comment...) from the current object
     * TODO: recursive remove in sub-sections
     * @return bool true if object was removed, false if not
     * @throws Exception
     */
    public function removeItem(): bool
    {
        if ($this->isRoot()) {
            throw new Exception('Cannot remove root item in Container::removeItem.');
        }

        $index = $this->getItemIndex();

        if (!is_null($index)) {
            array_splice($this->parent->children, $index, 1);

            return true;
        }

        return false;
    }

    /**
     * Is this item root, in a config container object
     * @return bool true if item is root
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Returns the item rank in its parent children array
     * according to other items with same type and name.
     *
     * @param bool $byType items differently by type
     *
     * @return int returns int or null if root object
     */
    public function getItemPosition(bool $byType = true): int
    {
        if (is_object($this->parent)) {
            $children = $this->parent->children;
            $obj = [];

            for ($i = 0, $count = count($children); $i < $count; $i++) {
                if ($children[$i]->name === $this->name) {
                    if ($byType) {
                        if ($children[$i]->type === $this->type) {
                            $obj[] = $children[$i];
                        }
                    } else {
                        $obj[] = $children[$i];
                    }
                }
            }

            for ($i = 0, $count = count($obj); $i < $count; $i++) {
                if ($obj[$i]->_id === $this->_id) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * Returns the item parent object.
     * @return ?Container returns reference to parent object or null if root object
     */
    public function getParent(): ?Container
    {
        return $this->parent;
    }

    /**
     * Returns the item parent object.
     *
     * @param int $index
     *
     * @return ?Container returns reference to child object or null if child does not exist
     */
    public function getChild(int $index = 0): ?Container
    {
        return $this->children[$index] ?? null;
    }

    /**
     * Get this item's name.
     * @return string    item's name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set this item's name.
     *
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get this item's content.
     * @return string    item's content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set this item's content.
     *
     * @param string $content
     *
     * @return void
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * Get this item's type.
     * @return string    item's type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set this item's type.
     *
     * @param string $type
     *
     * @return void
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Set this item's attributes.
     *
     * @param array $attributes Array of attributes
     *
     * @return void
     */
    public function updateAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Get this item's attributes.
     * @return array    item's attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set this item's attributes.
     *
     * @param array $attributes Array of attributes
     *
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * Get one attribute value of this item
     *
     * @param string $attribute Attribute key
     *
     * @return mixed    item's attribute value
     */
    public function getAttribute(string $attribute)
    {
        return $this->attributes[$attribute] ?? null;
    }

    /**
     * Set a children directive content.
     * This is an helper method calling getItem and addItem or setContent for you.
     * If the directive does not exist, it will be created at the bottom.
     *
     * @param string $name            Name of the directive to look for
     * @param string $content         New content
     * @param int    $index           Index of the directive to set,
     *                                in case there are more than one directive
     *                                with the same name
     *
     * @return Container newly set directive
     * @throws Exception
     */
    public function setDirective(string $name, string $content, int $index = -1): Container
    {
        try {
            $item = $this->getItem('directive', $name, null, null, $index);
            $item->setContent($content);
        } catch (Exception $e) {
            return $this->createDirective($name, $content, null);
        }

        return $item;
    }

    /**
     * Adds a directive to this item.
     * This is a helper method that calls createItem
     *
     * @param string     $name       Name of new directive
     * @param string     $content    Content of new directive
     * @param ?array     $attributes Directive attributes
     * @param string     $where      Position : 'top', 'bottom', 'before', 'after'
     * @param ?Container $target     Needed when $where is 'before' or 'after'
     *
     * @return Container reference to new item or Pear_Error
     * @throws Exception
     */
    public function createDirective(
        string $name,
        string $content,
        array $attributes = null,
        string $where = 'bottom',
        Container $target = null
    ): Container
    {
        return $this->createItem('directive', $name, $content, $attributes, $where, $target);
    }

    /**
     * Returns a key/value pair array of the container and its children.
     * Format : section[directive][index] = value
     * If the container has attributes, it will use '@' and '#'
     * index is here because multiple directives can have the same name.
     *
     * @param bool $useAttr Whether to return the attributes too
     *
     * @return array
     */
    public function toArray(bool $useAttr = true): array
    {
        $array[$this->name] = [];
        switch ($this->type) {
            case 'directive':
                if ($useAttr && count($this->attributes) > 0) {
                    $array[$this->name]['#'] = $this->content;
                    $array[$this->name]['@'] = $this->attributes;
                } else {
                    $array[$this->name] = $this->content;
                }
                break;
            case 'section':
                if ($useAttr && count($this->attributes) > 0) {
                    $array[$this->name]['@'] = $this->attributes;
                }

                if ($count = count($this->children)) {
                    foreach ($this->children as $iValue) {
                        $newArr = $iValue->toArray($useAttr);

                        if ($newArr !== null) {
                            foreach ($newArr as $key => $value) {
                                if (isset($array[$this->name][$key])) {
                                    // duplicate name/type
                                    if (is_array($array[$this->name][$key]) && isset($array[$this->name][$key][0])) {
                                        $array[$this->name][$key][] = $value;
                                        continue;
                                    }

                                    $old = $array[$this->name][$key];
                                    unset($array[$this->name][$key]);
                                    $array[$this->name][$key] = [$old, $value];

                                } else {
                                    $array[$this->name][$key] = $value;
                                }
                            }
                        }
                    }
                }
                break;
            default:
                return [];
        }

        return $array;
    }

    /**
     * Writes the configuration to a file
     *
     * @param string  $fileName Info on datasource such as path to the configuraton file or dsn...
     * @param DriverInterface $driver   Type of configuration
     *
     * @return mixed     true on success
     * @throws Exception
     */
    public function writeData(string $fileName, DriverInterface $driver): bool
    {
        if ($driver instanceof WritableInterface) {
            return $driver->writeData($fileName, $this);
        }

        // Default behaviour
        $fp = @fopen($fileName, 'wb');
        if ($fp) {
            $string = $this->toString($driver);
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
     * Call the toString methods in the container plugin
     *
     * @param DriverInterface $driver
     *
     * @return string
     */
    public function toString(DriverInterface $driver): string
    {
        return $driver->toString($this);
    }
}