<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Implements PageLegacyInterface
 */
trait PageLegacyTrait
{
    private $_content_meta;
    private $_metadata;

    /**
     * Initializes the page instance variables based on a file
     *
     * @param  \SplFileInfo $file The file information for the .md file that the page represents
     * @param  string $extension
     *
     * @return $this
     */
    public function init(\SplFileInfo $file, $extension = null)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the raw data
     *
     * @param  string $var Raw content string
     *
     * @return string      Raw content string
     */
    public function raw($var = null): string
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        $storage = $this->getFlexDirectory()->getStorage();
        if (method_exists($storage, 'readRaw')) {
            return $storage->readRaw($this->getStorageKey());
        }

        $array = $this->prepareStorage();
        $formatter = new MarkdownFormatter();

        return $formatter->encode($array);
    }

    /**
     * Gets and Sets the page frontmatter
     *
     * @param string|null $var
     *
     * @return string
     */
    public function frontmatter($var = null): string
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        $storage = $this->getFlexDirectory()->getStorage();
        if (method_exists($storage, 'readRaw')) {
            return $storage->readFrontmatter($this->getStorageKey());
        }

        $array = $this->prepareStorage();
        $formatter = new YamlFormatter();

        return $formatter->encode($array['header'] ?? []);
    }

    /**
     * Modify a header value directly
     *
     * @param $key
     * @param $value
     */
    public function modifyHeader($key, $value): void
    {
        $this->setNestedProperty("header.{$key}", $value);
    }

    /**
     * @return int
     */
    public function httpResponseCode(): int
    {
        $code = (int)$this->getNestedProperty('header.http_response_code');

        return $code ?: 200;
    }

    public function httpHeaders(): array
    {
        $headers = [];

        $format = $this->templateFormat();
        $cache_control = $this->cacheControl();
        $expires = $this->expires();

        // Set Content-Type header.
        $headers['Content-Type'] = Utils::getMimeByExtension($format, 'text/html');

        // Calculate Expires Headers if set to > 0.
        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            if (!$cache_control) {
                $headers['Cache-Control'] = 'max-age=' . $expires;
            }
            $headers['Expires'] = $expires_date;
        }

        // Set Cache-Control header.
        if ($cache_control) {
            $headers['Cache-Control'] = strtolower($cache_control);
        }

        // Set Last-Modified header.
        if ($this->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $this->modified()) . ' GMT';
            $headers['Last-Modified'] = $last_modified_date;
        }

        // Calculate ETag based on the serialized page and modified time.
        if ($this->eTag()) {
            $headers['ETag'] = '"' . md5(json_encode($this) . $this->modified()).'"';
        }

        // Set Vary: Accept-Encoding header.
        $grav = Grav::instance();
        if ($grav['config']->get('system.pages.vary_accept_encoding', false)) {
            $headers['Vary'] = 'Accept-Encoding';
        }

        return $headers;
    }

    /**
     * Get the contentMeta array and initialize content first if it's not already
     *
     * @return array
     */
    public function contentMeta(): array
    {
        // Content meta is generated during the content is being rendered, so make sure we have done it.
        $this->content();

        return $this->getContentMeta();
    }

    /**
     * Add an entry to the page's contentMeta array
     *
     * @param string $name
     * @param string $value
     */
    public function addContentMeta($name, $value): void
    {
        $this->_content_meta[$name] = $value;
    }

    /**
     * Return the whole contentMeta array as it currently stands
     *
     * @param string|null $name
     *
     * @return string|array|null
     */
    public function getContentMeta($name = null)
    {
        if ($name) {
            return $this->_content_meta[$name] ?? null;
        }

        return $this->_content_meta ?? [];
    }

    /**
     * Sets the whole content meta array in one shot
     *
     * @param array $content_meta
     *
     * @return array
     */
    public function setContentMeta($content_meta): array
    {
        return $this->_content_meta = $content_meta;
    }

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     */
    public function cachePageContent(): void
    {
        $value = [
            'checksum' => $this->getCacheChecksum(),
            'content' => $this->_content,
            'content_meta' => $this->_content_meta
        ];

        $cache = $this->getCache('render');
        $key = md5($this->getCacheKey() . '-content');

        $cache->set($key, $value);
    }

    /**
     * Get file object to the page.
     *
     * @return MarkdownFile|null
     */
    public function file(): ?MarkdownFile
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    abstract public function save($reorder = true);

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     *
     * @return $this
     */
    public function move(PageInterface $parent)
    {
        if ($this->route() === $parent->route()) {
            throw new \RuntimeException('Failed: Cannot set page parent to self');
        }
        if (Utils::startsWith($parent->rawRoute(), $this->rawRoute())) {
            throw new \RuntimeException('Failed: Cannot set page parent to a child of current page');
        }

        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Prepare a copy from the page. Copies also everything that's under the current page.
     *
     * Returns a new Page object for the copy.
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface|null $parent New parent page.
     *
     * @return $this
     */
    public function copy(PageInterface $parent = null)
    {
        $parentStorageKey = ltrim(dirname("/{$this->getStorageKey(true)}"), '/');
        $relocate = false;

        /** @var FlexIndexInterface $index */
        $index = $this->getFlexDirectory()->getIndex();

        if ($parent) {
            if ($parent instanceof FlexObjectInterface) {
                $k = $parent->getStorageKey(true);
                if ($k !== $parentStorageKey) {
                    $parentStorageKey = $k;
                    $relocate = true;
                }
            } else {
                throw new \RuntimeException('Cannot copy page, parent is of unknown type');
            }
        } else {
            $parent = $parentStorageKey
                ? $this->getFlexDirectory()->getObject($parentStorageKey, 'storage_key')
                : $index->getRoot();
        }

        // Find non-existing key.
        $key = trim($parent->getKey() . '/' . basename($this->getKey()), '/');
        $key = preg_replace('/-\d+$/', '', $key);
        $i = 1;
        do {
            $i++;
            $test = "{$key}-{$i}";
        } while ($index->containsKey($test));
        $key = $test;
        $folder = basename($key);

        // Get the folder name.
        $order = $this->getProperty('order');
        if ($order) {
            $order++;
        }

        $parts = [];
        if ($parentStorageKey !== '') {
            $parts[] = $parentStorageKey;
        }
        $parts[] = $order ? sprintf('%02d.%s', $order, $folder) : $folder;

        // Finally update the object.
        $this->setKey($key);
        $this->setStorageKey(implode('/', $parts));

        $this->markAsCopy();

        return $this;
    }

    abstract public function blueprints();

    /**
     * Get the blueprint name for this page.  Use the blueprint form field if set
     *
     * @return string
     */
    public function blueprintName(): string
    {
        $blueprint_name = filter_input(INPUT_POST, 'blueprint', FILTER_SANITIZE_STRING) ?: $this->template();

        return $blueprint_name;
    }

    /**
     * Validate page header.
     *
     * @throws Exception
     */
    public function validate(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->validate($this->toArray());
    }

    /**
     * Filter page header from illegal contents.
     */
    public function filter(): void
    {
        $blueprints = $this->getBlueprint();
        $values = $blueprints->filter($this->toArray());
        if ($values && isset($values['header'])) {
            $this->header($values['header']);
        }
    }

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra(): array
    {
        $data = $this->prepareStorage();

        return $this->getBlueprint()->extra((array)($data['header'] ?? []), 'header.');
    }

    /**
     * Convert page to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'header' => (array)$this->header(),
            'content' => (string)$this->value('content')
        ];
    }

    /**
     * Convert page to YAML encoded string.
     *
     * @return string
     */
    public function toYaml(): string
    {
        return Yaml::dump($this->toArray(), 20);
    }

    /**
     * Convert page to JSON encoded string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string $var The name of this page.
     *
     * @return string      The name of this page.
     */
    public function name($var = null): string
    {
        return $this->loadProperty(
            'name',
            $var,
            function ($value) {
                $value = $value ?? $this->getMetaData()['template'] ?? 'default';
                if (!preg_match('/\.md$/', $value)) {
                    $language = $this->language();
                    if ($language) {
                        // TODO: better language support
                        $value .= ".{$language}";
                    }
                    $value .= '.md';
                }
                $value = preg_replace('|^modular/|', '', $value);

                $this->unsetProperty('template');

                return $value;
            }
        );
    }

    /**
     * Returns child page type.
     *
     * @return string
     */
    public function childType(): string
    {
        return (string)$this->getNestedProperty('header.child_type');
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string $var the template name
     *
     * @return string      the template name
     */
    public function template($var = null): string
    {
        return $this->loadHeaderProperty(
            'template',
            $var,
            function($value) {
                return trim($value ?? (($this->modular() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name())));
            }
        );
    }

    /**
     * Allows a page to override the output render format, usually the extension provided in the URL.
     * (e.g. `html`, `json`, `xml`, etc).
     *
     * @param string|null $var
     *
     * @return string
     */
    public function templateFormat($var = null): string
    {
        return $this->loadHeaderProperty(
            'template_format',
            $var,
            function($value) {
                return ltrim($value ?? $this->getNestedProperty('header.append_url_extension') ?: Utils::getPageFormat(), '.');
            }
        );
    }

    /**
     * Gets and sets the extension field.
     *
     * @param string|null $var
     *
     * @return string
     */
    public function extension($var = null): string
    {
        if (null !== $var) {
            $this->setProperty('format', $var);
        }

        $language = $this->language();
        if ($language) {
            $language = '.' . $language;
        }
        $format = '.' . ($this->getProperty('format') ?? pathinfo($this->name(), PATHINFO_EXTENSION));

        return $language . $format;
    }

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  int $var The new expires value.
     *
     * @return int      The expires value
     */
    public function expires($var = null): int
    {
        return $this->loadHeaderProperty(
            'expires',
            $var,
            static function($value) {
                return (int)($value ?? Grav::instance()['config']->get('system.pages.expires'));
            }
        );
    }

    /**
     * Gets and sets the cache-control property.  If not set it will return the default value (null)
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control for more details on valid options
     *
     * @param string|null $var
     * @return string|null
     */
    public function cacheControl($var = null): ?string
    {
        return $this->loadHeaderProperty(
            'cache_control',
            $var,
            static function($value) {
                return ((string)($value ?? Grav::instance()['config']->get('system.pages.cache_control'))) ?: null;
            }
        );
    }

    public function ssl($var = null): ?bool
    {
        return $this->loadHeaderProperty(
            'ssl',
            $var,
            static function($value) {
                return $value ? (bool)$value : null;
            }
        );
    }

    /**
     * Returns the state of the debugger override setting for this page
     *
     * @return bool
     */
    public function debugger(): bool
    {
        return (bool)$this->getNestedProperty('header.debugger', true);
    }

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array $var an Array of metadata values to set
     *
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null): array
    {
        if ($var !== null) {
            $this->_metadata = (array)$var;
        }

        // if not metadata yet, process it.
        if (null === $this->_metadata) {
            $this->_metadata = [];

            // Set the Generator tag
            $defaultMetadata = ['generator' => 'GravCMS'];
            $siteMetadata = Grav::instance()['config']->get('site.metadata', []);
            $headerMetadata = $this->getNestedProperty('header.metadata', []);

            // Get initial metadata for the page
            $metadata = array_merge($defaultMetadata, $siteMetadata, $headerMetadata);

            $header_tag_http_equivs = ['content-type', 'default-style', 'refresh', 'x-ua-compatible'];

            // Build an array of meta objects..
            foreach ($metadata as $key => $value) {
                // Lowercase the key
                $key = strtolower($key);

                // If this is a property type metadata: "og", "twitter", "facebook" etc
                // Backward compatibility for nested arrays in metas
                if (is_array($value)) {
                    foreach ($value as $property => $prop_value) {
                        $prop_key = $key . ':' . $property;
                        $this->_metadata[$prop_key] = [
                            'name' => $prop_key,
                            'property' => $prop_key,
                            'content' => htmlspecialchars($prop_value, ENT_QUOTES, 'UTF-8')
                        ];
                    }
                } elseif ($value) {
                    // If it this is a standard meta data type
                    if (\in_array($key, $header_tag_http_equivs, true)) {
                        $this->_metadata[$key] = [
                            'http_equiv' => $key,
                            'content' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                        ];
                    } elseif ($key === 'charset') {
                        $this->_metadata[$key] = ['charset' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')];
                    } else {
                        // if it's a social metadata with separator, render as property
                        $separator = strpos($key, ':');
                        $hasSeparator = $separator && $separator < strlen($key) - 1;
                        $entry = [
                            'content' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                        ];

                        if ($hasSeparator && !Utils::startsWith($key, 'twitter')) {
                            $entry['property'] = $key;
                        } else {
                            $entry['name'] = $key;
                        }

                        $this->_metadata[$key] = $entry;
                    }
                }
            }
        }

        return $this->_metadata;
    }

    /**
     * Reset the metadata and pull from header again
     */
    public function resetMetadata(): void
    {
        $this->_metadata = null;
    }

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  bool $var show etag header
     *
     * @return bool      show etag header
     */
    public function eTag($var = null): bool
    {
        return $this->loadHeaderProperty(
            'etag',
            $var,
            static function($value) {
                return (bool)($value ?? Grav::instance()['config']->get('system.pages.last_modified'));
            }
        );
    }

    /**
     * Gets and sets the path to the .md file for this Page object.
     *
     * @param  string $var the file path
     *
     * @return string|null      the file path
     */
    public function filePath($var = null): ?string
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        $folder = $this->getStorageFolder();
        if (!$folder) {
            return null;
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator->findResource($folder, true, true) . '/' . ($this->isPage() ? $this->name() : 'default.md');
    }

    /**
     * Gets the relative path to the .md file
     *
     * @return string|null The relative file path
     */
    public function filePathClean(): ?string
    {
        $folder = $this->getStorageFolder();
        if (!$folder) {
            return null;
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator->findResource($folder, false, true) .  '/' . ($this->isPage() ? $this->name() : 'default.md');
    }

    /**
     * Gets and sets the order by which any sub-pages should be sorted.
     *
     * @param  string $var the order, either "asc" or "desc"
     *
     * @return string      the order, either "asc" or "desc"
     */
    public function orderDir($var = null): string
    {
        return $this->loadHeaderProperty(
            'order_dir',
            $var,
            static function($value) {
                return strtolower(trim($value) ?: Grav::instance()['config']->get('system.pages.order.dir')) === 'desc' ? 'desc' : 'asc';
            }
        );
    }

    /**
     * Gets and sets the order by which the sub-pages should be sorted.
     *
     * default - is the order based on the file system, ie 01.Home before 02.Advark
     * title - is the order based on the title set in the pages
     * date - is the order based on the date set in the pages
     * folder - is the order based on the name of the folder with any numerics omitted
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     *
     * @return string      supported options include "default", "title", "date", and "folder"
     */
    public function orderBy($var = null): string
    {
        return $this->loadHeaderProperty(
            'order_by',
            $var,
            static function($value) {
                return trim($value) ?: Grav::instance()['config']->get('system.pages.order.by');
            }
        );
    }

    /**
     * Gets the manual order set in the header.
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     *
     * @return array
     */
    public function orderManual($var = null): array
    {
        return $this->loadHeaderProperty(
            'order_manual',
            $var,
            static function($value) {
                return (array)$value;
            }
        );
    }

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int $var the maximum number of sub-pages
     *
     * @return int      the maximum number of sub-pages
     */
    public function maxCount($var = null): int
    {
        return $this->loadHeaderProperty(
            'max_count',
            $var,
            static function($value) {
                return (int)($value ?? Grav::instance()['config']->get('system.pages.list.count'));
            }
        );
    }

    /**
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modular($var = null): bool
    {
        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null): bool
    {
        if ($var !== null) {
            $this->setProperty('modular_twig', (bool)$var);
            if ($var) {
                $this->visible(false);
            }
        }

        return (bool)($this->getProperty('modular_twig') ?? strpos($this->slug(), '_') === 0);
    }

    /**
     * Returns children of this page.
     *
     * @return PageCollectionInterface
     */
    public function children()
    {
        $storage = $this->getStorage();
        $keys = array_keys($storage['children'] ?? []);
        $prefix = $this->getStorageKey(true);
        if ($prefix) {
            foreach ($keys as &$key) {
                $key = $prefix . '/' . $key;
            }
            unset($key);
        }

        return $this->getFlexDirectory()->getIndex($keys, 'storage_key');
    }

    /**
     * Check to see if this item is the first in an array of sub-pages.
     *
     * @return bool True if item is first.
     */
    public function isFirst(): bool
    {
        $parent = $this->parent();

        return $parent ? $parent->children()->withKeyField()->isFirst($this->getKey()) : true;
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return bool True if item is last
     */
    public function isLast(): bool
    {
        $parent = $this->parent();

        return $parent ? $parent->children()->withKeyField()->isLast($this->getKey()) : true;
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @return PageInterface|false the previous Page item
     */
    public function prevSibling()
    {
        return $this->adjacentSibling(-1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @return PageInterface|false the next Page item
     */
    public function nextSibling()
    {
        return $this->adjacentSibling(1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  int $direction either -1 or +1
     *
     * @return PageInterface|bool             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        $parent = $this->parent();

        return $parent ? $parent->children()->withKeyField()->adjacentSibling($this->getKey(), $direction) : false;
    }

    /**
     * Helper method to return an ancestor page.
     *
     * @param bool $lookup Name of the parent folder
     *
     * @return PageInterface|null page you were looking for if it exists
     */
    public function ancestor($lookup = null)
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->ancestor($this->getProperty('parent_route'), $lookup);
    }

    /**
     * Helper method to return an ancestor page to inherit from. The current
     * page object is returned.
     *
     * @param string $field Name of the parent folder
     *
     * @return PageInterface|null
     */
    public function inherited($field)
    {
        [$inherited, $currentParams] = $this->getInheritedParams($field);

        $this->modifyHeader($field, $currentParams);

        return $inherited;
    }

    /**
     * Helper method to return an ancestor field only to inherit from. The
     * first occurrence of an ancestor field will be returned if at all.
     *
     * @param string $field Name of the parent folder
     *
     * @return array
     */
    public function inheritedField($field): array
    {
        [$inherited, $currentParams] = $this->getInheritedParams($field);

        return $currentParams;
    }

    /**
     * Method that contains shared logic for inherited() and inheritedField()
     *
     * @param string $field Name of the parent folder
     *
     * @return array
     */
    protected function getInheritedParams($field): array
    {
        $pages = Grav::instance()['pages'];

        /** @var Pages $pages */
        $inherited = $pages->inherited($this->getProperty('parent_route'), $field);
        $inheritedParams = $inherited ? (array)$inherited->value('header.' . $field) : [];
        $currentParams = (array)$this->value('header.' . $field);
        if ($inheritedParams && is_array($inheritedParams)) {
            $currentParams = array_replace_recursive($inheritedParams, $currentParams);
        }

        return [$inherited, $currentParams];
    }

    /**
     * Helper method to return a page.
     *
     * @param string $url the url of the page
     * @param bool $all
     *
     * @return PageInterface|null page you were looking for if it exists
     */
    public function find($url, $all = false)
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->find($url, $all);
    }

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param bool $pagination
     *
     * @return Collection
     * @throws \InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        if (is_string($params)) {
            // Look into a page header field.
            $params = (array)$this->value('header.' . $params);
        } elseif (!is_array($params)) {
            throw new \InvalidArgumentException('Argument should be either header variable name or array of parameters');
        }

        if (!$pagination) {
            $params['pagination'] = false;
        }
        $context = [
            'pagination' => $pagination,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
    }

    /**
     * @param string|array $value
     * @param bool $only_published
     * @return Collection
     */
    public function evaluate($value, $only_published = true)
    {
        $params = [
            'items' => $value,
            'published' => $only_published
        ];
        $context = [
            'event' => false,
            'pagination' => false,
            'url_taxonomy_filters' => false,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
    }

    /**
     * Returns whether or not the current folder exists
     *
     * @return bool
     */
    public function folderExists(): bool
    {
        return $this->exists() || is_dir($this->getStorageFolder() ?? '');
    }

    /**
     * Gets the Page Unmodified (original) version of the page.
     *
     * @return PageInterface The original version of the page.
     */
    public function getOriginal()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the action.
     *
     * @return string|null The Action string.
     */
    public function getAction(): ?string
    {
        $meta = $this->getMetaData();
        if (!empty($meta['copy'])) {
            return 'copy';
        }
        if (isset($meta['storage_key']) && $this->getStorageKey() !== $meta['storage_key']) {
            return 'move';
        }

        return null;
    }

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    abstract public function getCache(string $namespace = null);

    abstract protected function exists();
    abstract protected function getStorageFolder();
    abstract protected function loadHeaderProperty(string $property, $var, callable $filter);
}