<?php

namespace SilverStripe\TemplateEngine;

use InvalidArgumentException;
use Masterminds\HTML5;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Core\Path;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\TemplateEngine\Middleware\CMSPreviewMiddleware;
use SilverStripe\View\Exception\MissingTemplateException;
use SilverStripe\View\SSViewer;
use SilverStripe\View\TemplateEngine;
use SilverStripe\View\ThemeResourceLoader;
use SilverStripe\View\ViewLayerData;

/**
 * Parses template files with an *.ss file extension, or strings representing templates in that format.
 *
 * In addition to a full template in the templates/ folder, a template in
 * templates/Content or templates/Layout will be rendered into `$Content` and
 * `$Layout`, respectively.
 *
 * A single template can be parsed by multiple nested SSTemplateEngine instances
 * through `$Layout`/`$Content` placeholders, as well as `<% include MyTemplateFile %>` template commands.
 *
 * <b>Caching</b>
 *
 * Compiled templates are cached, usually on the filesystem.
 * If you put ?flush=1 on your URL, it will force the template to be recompiled.
 *
 */
class SSTemplateEngine implements TemplateEngine, Flushable
{
    use Injectable;
    use Configurable;

    /**
     * Default prepended cache key for partial caching
     */
    private static string $global_key = '$CurrentReadingMode, $CurrentUser.ID';

    /**
     * List of models being processed
     */
    protected static array $topLevel = [];

    /**
     * @internal
     */
    private static bool $template_cache_flushed = false;

    /**
     * @internal
     */
    private static bool $cacheblock_cache_flushed = false;

    private ?CacheInterface $partialCacheStore = null;

    private ?TemplateParser $parser = null;

    /**
     * A template or pool of candidate templates to choose from.
     */
    private string|array $templateCandidates = [];

    /**
     * Absolute path to chosen template file which will be used in the call to render()
     */
    private ?string $chosen = null;

    /**
     * Templates to use when looking up 'Layout' or 'Content'
     */
    private array $subTemplates = [];

    public function __construct(string|array $templateCandidates = [])
    {
        if (!empty($templateCandidates)) {
            $this->setTemplate($templateCandidates);
        }
    }

    /**
     * Execute the given template, passing it the given data.
     * Used by the <% include %> template tag to process included templates.
     *
     * @param array $overlay Associative array of fields (e.g. args into an include template) to inject into the
     * template as properties. These override properties and methods with the same name from $data and from global
     * template providers.
     */
    public static function execute_template(
        array|string $template,
        ViewLayerData $data,
        array $overlay = [],
        ?ScopeManager $scope = null
    ): string {
        $engine = static::create($template);
        return $engine->render($data, $overlay, $scope);
    }

    /**
     * Triggered early in the request when someone requests a flush.
     */
    public static function flush(): void
    {
        SSTemplateEngine::flushTemplateCache(true);
        SSTemplateEngine::flushCacheBlockCache(true);
    }

    /**
     * Clears all parsed template files in the cache folder.
     *
     * @param bool $force Set this to true to force a re-flush. If left to false, flushing
     * will only be performed once a request.
     */
    public static function flushTemplateCache(bool $force = false): void
    {
        if (!SSTemplateEngine::$template_cache_flushed || $force) {
            $dir = dir(TEMP_PATH);
            while (false !== ($file = $dir->read())) {
                if (strstr($file ?? '', '.cache')) {
                    unlink(TEMP_PATH . DIRECTORY_SEPARATOR . $file);
                }
            }
            SSTemplateEngine::$template_cache_flushed = true;
        }
    }

    /**
     * Clears all partial cache blocks.
     *
     * @param bool $force Set this to true to force a re-flush. If left to false, flushing
     * will only be performed once a request.
     */
    public static function flushCacheBlockCache(bool $force = false): void
    {
        if (!SSTemplateEngine::$cacheblock_cache_flushed || $force) {
            $cache = Injector::inst()->get(CacheInterface::class . '.cacheblock');
            $cache->clear();
            SSTemplateEngine::$cacheblock_cache_flushed = true;
        }
    }

    public function hasTemplate(array|string $templateCandidates): bool
    {
        return (bool) $this->findTemplate($templateCandidates);
    }

    public function renderString(
        string $template,
        ViewLayerData $model,
        array $overlay = [],
        bool $cache = true
    ): string {
        $hash = sha1($template);
        $cacheFile = TEMP_PATH . DIRECTORY_SEPARATOR . ".cache.$hash";

        // Generate a file whether we're caching or not.
        // This is an inefficiency that's required due to the way rendered templates get processed.
        if (!file_exists($cacheFile) || Injector::inst()->get(Kernel::class)->isFlushed()) {
            $content = $this->parseTemplateContent($template, "string sha1=$hash");
            $fh = fopen($cacheFile, 'w');
            fwrite($fh, $content);
            fclose($fh);
        }

        $output = $this->includeGeneratedTemplate($cacheFile, $model, $overlay, []);

        if (!$cache) {
            unlink($cacheFile);
        }

        return $output;
    }

    public function render(ViewLayerData $model, array $overlay = [], ?ScopeManager $scope = null): string
    {
        SSTemplateEngine::$topLevel[] = $model;
        $template = $this->chosen;

        // If there's no template, throw an exception
        if (!$template) {
            if (empty($this->templateCandidates)) {
                throw new MissingTemplateException(
                    'No template to render. '
                    . 'Try calling setTemplate() or passing template candidates into the constructor.'
                );
            }
            $message = 'None of the following templates could be found: ';
            $message .= print_r($this->templateCandidates, true);
            $themes = SSViewer::get_themes();
            if (!$themes) {
                $message .= ' (no theme in use)';
            } else {
                $message .= ' in themes "' . print_r($themes, true) . '"';
            }
            throw new MissingTemplateException($message);
        }

        $cacheFile = TEMP_PATH . DIRECTORY_SEPARATOR . '.cache'
            . str_replace(['\\','/',':'], '.', Director::makeRelative(realpath($template ?? '')) ?? '');
        $lastEdited = filemtime($template ?? '');

        if (!file_exists($cacheFile ?? '') || filemtime($cacheFile ?? '') < $lastEdited) {
            $content = file_get_contents($template ?? '');
            $content = $this->parseTemplateContent($content, $template);

            $fh = fopen($cacheFile ?? '', 'w');
            fwrite($fh, $content ?? '');
            fclose($fh);
        }

        $underlay = ['I18NNamespace' => basename($template ?? '')];

        // Makes the rendered sub-templates available on the parent model,
        // through $Content and $Layout placeholders.
        foreach (['Content', 'Layout'] as $subtemplate) {
            // Detect sub-template to use
            $sub = $this->getSubtemplateFor($subtemplate);
            if (!$sub) {
                continue;
            }

            // Create lazy-evaluated underlay for this subtemplate
            $underlay[$subtemplate] = function () use ($model, $overlay, $sub) {
                $subtemplateViewer = clone $this;
                // Select the right template and render if the template exists
                $subtemplateViewer->setTemplate($sub);
                // If there's no template for that underlay, just don't render anything.
                // This mirrors how ScopeManager handles null values.
                if (!$subtemplateViewer->chosen) {
                    return null;
                }
                // Render and wrap in DBHTMLText so it doesn't get escaped
                return DBHTMLText::create()->setValue($subtemplateViewer->render($model, $overlay));
            };
        }

        $output = $this->includeGeneratedTemplate($cacheFile, $model, $overlay, $underlay, $scope);

        if (CMSPreviewMiddleware::isCmsPreview()) {
            // Remove the nasty comments inside elements.
            // @TODO This is a really awfully bad nasty way to remove the comments from attributes... It'll do for now.
            //       The main downsides to this approach are:
            //          1. It messes with the output - notably it excludes the <!DOCTYPE html> tag for some reason.
            //          2. It means we can't use the template rendered for non-HTML which isn't common but is sort-of valid currently.
            //          3. It means if there's malformed HTML for whatever reason, we're messing with it in a way that may not be predictable.
            //       There's an alternative way in the JS below but that means the document loads with a bunch of invalid
            //       attribute values initially which has its own problems.
            //       Ultimately it would be good to instead be parsing the HTML as we go inside SSTemplateParser and
            //       only include the preview data comments when it's safe to do so - but that's a lot more work than
            //       I wanna do for a POC.
            $html5 = new HTML5();
            // disable_html_ns must be true or else we get a bunch of extra <br> that weren't there before...
            // probably that is preventable in other ways but this worked for now.
            $dom = $html5->loadHTMLFragment($output, ['disable_html_ns' => true]);
            $nodeStack = [$dom];
            do {
                $currNode = array_pop($nodeStack);
                if ($currNode->hasAttributes()) {
                    foreach ($currNode->attributes as $attribute) {
                        $attribute->value = preg_replace(['/<!-- _SS_PREVIEW_DATA_START [^>]+>/', '/<!-- _SS_PREVIEW_DATA_END [^>]+>/'], '', $attribute->value);
                    }
                }
                // The title gets messed up. Just avoid that the hacky way for now.
                if (strtolower($currNode->nodeName) === 'title') {
                        $currNode->textContent = preg_replace(['/<!-- _SS_PREVIEW_DATA_START [^>]+>/', '/<!-- _SS_PREVIEW_DATA_END [^>]+>/'], '', $currNode->textContent);
                }
                if ($currNode->hasChildNodes()) {
                    $nodeStack = array_merge($nodeStack, iterator_to_array($currNode->childNodes->getIterator()));
                }
            } while (!empty($nodeStack));
            $output = $dom->ownerDocument->saveHTML($dom);


            // When previewing, inject some JavaScript that does two main things:
            // 1. ~~Remove preview data from inside HTML element's attributes. It shouldn't be there in the first place, this is just a POC hack.~~
            // 2. Build a map of preview data comments and allow fetching elements that need to be updated for a given field on a given record.
            // 3. Listens for changes to fields and updates the DOM appropriately
            // Number 1 will not be used in a real world, because we'd implement the injection in a more robust way to start with.
            // Numbers 2 and 3 probably belongs in silverstripe/admin or at the very least should be in its own .js file and included with the requirements API.
            $output = str_replace('</body>', PHP_EOL .
            <<<'EOL'
                <script>
                // Not needed with the HTML parsing above - but that HTML parsing has its downsides too.
                // // This will remove the preview data from inside HTML attributes.
                // // It would be better to just not have them there - one way to avoid them being there
                // // would be to parse the HTML after rendering it which might be a better option
                // // We probably want smarter behaviour for adding the extra preview data in the first place
                // // but for the POC this will do.
                // //
                // // THEORETICALLY we could use this to update usage of fields in attributes as well
                // // but at least for the initial launch of the feature that's probably not worth the hassle.
                // elems = document.getElementsByTagName("*");
                // for (const el of elems) {
                //     if (!el.hasAttributes()) {
                //         continue;
                //     }
                //     for (const attr of el.attributes) {
                //         el.setAttribute(attr.name, el.getAttribute(attr.name).replace(/<!-- _SS_PREVIEW_DATA_START [^>]+>/, '').replace(/<!-- _SS_PREVIEW_DATA_END [^>]+>/, ''));
                //     }
                // }

                // This will build a map of all preview data comments so we can easily find relevant bits to update
                function _SS_PREVIEW_DATA_getKey(data) {
                    return Object.entries(data).flat().join();
                }
                const _SS_PREVIEW_DATA_COMMENTS = {};
                commentsIterator = document.createTreeWalker(
                    document,
                    NodeFilter.SHOW_COMMENT,
                    null,
                    false
                );
                curnode = null;
                while (currnode = commentsIterator.nextNode()) {
                    const startMatches = currnode.textContent?.match(/^\s*_SS_PREVIEW_DATA_START data-class=`(?<classname>[^`]*)` data-id=`(?<id>[^`]*)` data-field=`(?<field>[^`]*)`/);
                    if (startMatches) {
                        if (!_SS_PREVIEW_DATA_COMMENTS[_SS_PREVIEW_DATA_getKey(startMatches.groups)]) {
                            _SS_PREVIEW_DATA_COMMENTS[_SS_PREVIEW_DATA_getKey(startMatches.groups)] = [];
                        }
                        _SS_PREVIEW_DATA_COMMENTS[_SS_PREVIEW_DATA_getKey(startMatches.groups)].push(currnode);
                    }
                }

                // This function allows us to get all nodes for a given field that need to be updated.
                // It's a multi-dimensional array.
                // The top layer represents each instance of the field being rendered.
                // The second layer is all the elements for that rendering.
                // e.g. if Content has multiple paragraphs but is only used once in the template, you may get this structure returned:
                //      [{comment: commentnode, instances: [<p></p>,<p></p>] }]
                function _SS_PREVIEW_DATA_getElementsToUpdate(classname, id, field) {
                    const datakey = _SS_PREVIEW_DATA_getKey({classname, id, field});
                    const comments = _SS_PREVIEW_DATA_COMMENTS[datakey];
                    if (!comments) {
                        return [];
                    }
                    let sibling = null;
                    const elems = [];
                    for (const comment of comments) {
                        const commentElems = [];
                        sibling = comment.nextSibling;
                        // NOTE: They may be nested but that's not in the sibling so this should still work.
                        while (sibling && (sibling.nodeType !== document.COMMENT_NODE || !sibling.textContent?.match(/^\s*_SS_PREVIEW_DATA_END/))) {
                            commentElems.push(sibling);
                            sibling = sibling.nextSibling;
                        }
                        elems.push({comment, elements: commentElems});
                    }
                    return elems;
                }

                // This sets up messaging using the PostMessage API so that the CMS can tell us (in the iframe) what has updated.
                // This should probably be replaced with the Channel Messaging API (see https://javascriptbit.com/transfer-data-between-parent-window-iframe-channel-messaging-api/)
                // which is more robust (e.g. we know the message comes from the CMS and not from something else in the project) and slightly more secure
                // But it also requires a bit more boiler plate so I skipped that for this POC.
                window.addEventListener('message', function(event) {
                    // @TODO Better handling for the unlikely case where there's other messages passed.
                    // e.g. one of my react dev tools extensions uses the 'message' event.
                    if (!event.data.className) {
                        return;
                    }
                    console.log({messageRecieved: event.data}); // debugging
                    const toUpdate = _SS_PREVIEW_DATA_getElementsToUpdate(event.data.className, event.data.id, event.data.field);
                    for (const instance of toUpdate) {
                        // If there's multiple nodes in this instance, get rid of the extras
                        if (instance.elements.length > 1) {
                            for (const $i = 1; $i < instance.elements.length; $i++) {
                                instance.elements[$i].remove();
                            }
                        }
                        // If there's no nodes, the value would have been null before - so we have to add it as a sibling
                        if (instance.elements.length === 0) {
                            instance.comment.after(event.data.value);
                            return;
                        }
                        // Replace with the value we set in the CMS.
                        // @TODO This is pretty garbo lol. We'll want to do a few things:
                        // 1. probably set up a low-touch endpoint to hydrate the record with the new value and ask it to give us the rendered value back (e.g. so it has the correct casting)
                        // 2. Handle HTML vs non-HTML values
                        // ??? probably other improvements.
                        instance.elements[0].replaceWith(event.data.value);
                    }
                });
                </script>
            EOL . PHP_EOL . '</body>', $output);
        }

        array_pop(SSTemplateEngine::$topLevel);

        return $output;
    }

    public function setTemplate(string|array $templateCandidates): static
    {
        $this->templateCandidates = $templateCandidates;
        $this->chosen = $this->findTemplate($templateCandidates);
        $this->subTemplates = [];
        return $this;
    }

    /**
     * Set the template parser that will be used in template generation
     */
    public function setParser(TemplateParser $parser): static
    {
        $this->parser = $parser;
        return $this;
    }

    /**
     * Returns the parser that is set for template generation
     */
    public function getParser(): TemplateParser
    {
        if (!$this->parser) {
            $this->setParser(Injector::inst()->get(SSTemplateParser::class));
        }
        return $this->parser;
    }

    /**
     * Set the cache object to use when storing / retrieving partial cache blocks.
     */
    public function setPartialCacheStore(CacheInterface $cache): static
    {
        $this->partialCacheStore = $cache;
        return $this;
    }

    /**
     * Get the cache object to use when storing / retrieving partial cache blocks.
     */
    public function getPartialCacheStore(): CacheInterface
    {
        if (!$this->partialCacheStore) {
            $this->partialCacheStore = Injector::inst()->get(CacheInterface::class . '.cacheblock');
        }
        return $this->partialCacheStore;
    }

    /**
     * An internal utility function to set up variables in preparation for including a compiled
     * template, then do the include
     *
     * @param string $cacheFile The path to the file that contains the template compiled to PHP
     * @param ViewLayerData $model The model to use as the root scope for the template
     * @param array $overlay Any variables to layer on top of the scope
     * @param array $underlay Any variables to layer underneath the scope
     * @param ScopeManager|null $inheritedScope The current scope of a parent template including a sub-template
     */
    protected function includeGeneratedTemplate(
        string $cacheFile,
        ViewLayerData $model,
        array $overlay,
        array $underlay,
        ?ScopeManager $inheritedScope = null
    ): string {
        if (isset($_GET['showtemplate']) && $_GET['showtemplate'] && Permission::check('ADMIN')) {
            $lines = file($cacheFile ?? '');
            echo "<h2>Template: $cacheFile</h2>";
            echo '<pre>';
            foreach ($lines as $num => $line) {
                echo str_pad($num + 1, 5) . htmlentities($line, ENT_COMPAT, 'UTF-8');
            }
            echo '</pre>';
        }

        $cache = $this->getPartialCacheStore();
        $scope = new ScopeManager($model, $overlay, $underlay, $inheritedScope);
        $val = '';

        // Placeholder for values exposed to $cacheFile
        [$cache, $scope, $val];
        include($cacheFile);

        return $val;
    }

    /**
     * Get the appropriate template to use for the named sub-template, or null if none are appropriate
     */
    protected function getSubtemplateFor(string $subtemplate): ?array
    {
        // Get explicit subtemplate name
        if (isset($this->subTemplates[$subtemplate])) {
            return $this->subTemplates[$subtemplate];
        }

        // Don't apply sub-templates if type is already specified (e.g. 'Includes')
        if (isset($this->templateCandidates['type'])) {
            return null;
        }

        // Filter out any other typed templates as we can only add, not change type
        $templates = array_filter(
            (array) $this->templateCandidates,
            function ($template) {
                return !isset($template['type']);
            }
        );
        if (empty($templates)) {
            return null;
        }

        // Set type to subtemplate
        $templates['type'] = $subtemplate;
        return $templates;
    }

    /**
     * Parse given template contents
     *
     * @param string $content The template contents
     * @param string $template The template file name
     */
    protected function parseTemplateContent(string $content, string $template = ""): string
    {
        return $this->getParser()->compileString(
            $content,
            $template,
            Director::isDev() && SSViewer::config()->uninherited('source_file_comments')
        );
    }

    /**
     * Attempts to find possible candidate templates from a set of template
     * names from modules, current theme directory and finally the application
     * folder.
     *
     * The template names can be passed in as plain strings, or be in the
     * format "type/name", where type is the type of template to search for
     * (e.g. Includes, Layout).
     *
     * The results of this method will be cached for future use.
     *
     * @param string|array $template Template name, or template spec in array format with the keys
     * 'type' (type string) and 'templates' (template hierarchy in order of precedence).
     * If 'templates' is omitted then any other item in the array will be treated as the template
     * list, or list of templates each in the array spec given.
     * Templates with an .ss extension will be treated as file paths, and will bypass
     * theme-coupled resolution.
     * @param array $themes List of themes to use to resolve themes. Defaults to {@see SSViewer::get_themes()}
     * @return string Absolute path to resolved template file, or null if not resolved.
     * File location will be in the format themes/<theme>/templates/<directories>/<type>/<basename>.ss
     * Note that type (e.g. 'Layout') is not the root level directory under 'templates'.
     * Returns null if no template was found.
     */
    private function findTemplate(string|array $template, array $themes = []): ?string
    {
        if (empty($themes)) {
            $themes = SSViewer::get_themes();
        }

        $cacheAdapter = ThemeResourceLoader::inst()->getCache();
        $cacheKey = 'findTemplate_' . md5(json_encode($template) . json_encode($themes));

        // Look for a cached result for this data set
        if ($cacheAdapter->has($cacheKey)) {
            return $cacheAdapter->get($cacheKey);
        }

        $type = '';
        if (is_array($template)) {
            // Check if templates has type specified
            if (array_key_exists('type', $template ?? [])) {
                $type = $template['type'];
                unset($template['type']);
            }
            // Templates are either nested in 'templates' or just the rest of the list
            $templateList = array_key_exists('templates', $template ?? []) ? $template['templates'] : $template;
        } else {
            $templateList = [$template];
        }

        $themePaths = ThemeResourceLoader::inst()->getThemePaths($themes);
        $baseDir = ThemeResourceLoader::inst()->getBase();
        foreach ($templateList as $i => $template) {
            // Check if passed list of templates in array format
            if (is_array($template)) {
                $path = $this->findTemplate($template, $themes);
                if ($path) {
                    $cacheAdapter->set($cacheKey, $path);
                    return $path;
                }
                continue;
            }

            // If we have an .ss extension, this is a path, not a template name. We should
            // pass in templates without extensions in order for template manifest to find
            // files dynamically.
            if (substr($template ?? '', -3) == '.ss' && file_exists($template ?? '')) {
                $cacheAdapter->set($cacheKey, $template);
                return $template;
            }

            // Check string template identifier
            $template = str_replace('\\', '/', $template ?? '');
            $parts = explode('/', $template ?? '');

            $tail = array_pop($parts);
            $head = implode('/', $parts);
            foreach ($themePaths as $themePath) {
                // Join path
                $pathParts = [ $baseDir, $themePath, 'templates', $head, $type, $tail ];
                try {
                    $path = Path::join($pathParts) . '.ss';
                    if (file_exists($path ?? '')) {
                        $cacheAdapter->set($cacheKey, $path);
                        return $path;
                    }
                } catch (InvalidArgumentException $e) {
                    // No-op
                }
            }
        }

        // No template found
        $cacheAdapter->set($cacheKey, null);
        return null;
    }
}
