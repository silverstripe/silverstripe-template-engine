<?php

namespace SilverStripe\TemplateEngine\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Security\Permission;

class CMSPreviewMiddleware implements HTTPMiddleware
{
    private static bool $isCmsPreview = false;

    public function process(HTTPRequest $request, callable $delegate)
    {
        if ($request->getVar('CMSPreview')) {
            self::$isCmsPreview = true;
        }
        return $delegate($request);
    }

    public static function isCmsPreview(): bool
    {
        return self::$isCmsPreview && Permission::check('CMS_ACCESS');
    }
}
