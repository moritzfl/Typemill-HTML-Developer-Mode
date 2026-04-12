<?php

namespace Plugins\sitefiles;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Typemill\Models\Navigation;
use Typemill\Models\Sitemap;
use Typemill\Models\StorageWrapper;
use Typemill\Plugin;

class sitefiles extends Plugin
{
    public static function setPremiumLicense()
    {
        return false;
    }

    public static function getSubscribedEvents()
    {
        return [];
    }

    public static function addNewRoutes()
    {
        return [
            [
                'httpMethod' => 'get',
                'route' => '/robots.txt',
                'name' => 'sitefiles.robots',
                'class' => 'Plugins\sitefiles\sitefiles:robots',
            ],
            [
                'httpMethod' => 'get',
                'route' => '/sitemap.xml',
                'name' => 'sitefiles.sitemap',
                'class' => 'Plugins\sitefiles\sitefiles:sitemap',
            ],
        ];
    }

    public function robots(Request $request, Response $response, $args)
    {
        $settings = $this->getPluginSettings() ?: [];
        $baseurl = rtrim($this->urlinfo()['baseurl'] ?? '', '/');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /tm/',
        ];

        $extraRules = trim((string) ($settings['extra_rules'] ?? ''));
        if ($extraRules !== '') {
            $lines[] = '';
            foreach (preg_split('/\R+/', $extraRules) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        if ($baseurl !== '') {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $baseurl . '/sitemap.xml';
        }

        $response->getBody()->write(implode("\n", $lines) . "\n");

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    public function sitemap(Request $request, Response $response, $args)
    {
        $storage = new StorageWrapper('\Typemill\Models\Storage');

        $filename = $this->resolveSitemapFilename($storage);
        $sitemap = $filename ? $storage->getFile('cacheFolder', '', $filename) : false;

        if ($sitemap === false) {
            $this->generateSitemap();
            $filename = $this->resolveSitemapFilename($storage);
            $sitemap = $filename ? $storage->getFile('cacheFolder', '', $filename) : false;
        }

        if ($sitemap === false) {
            $response->getBody()->write('Sitemap is not available.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        $response->getBody()->write($sitemap);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    private function generateSitemap(): void
    {
        $settings = $this->getSettings();
        $urlinfo = $this->urlinfo();

        $navigation = new Navigation();
        $liveNavigation = $navigation->getLiveNavigation($urlinfo, $settings['langattr'] ?? '');

        if (!$liveNavigation) {
            return;
        }

        $sitemap = new Sitemap();
        $sitemap->updateSitemap($liveNavigation, $urlinfo);
    }

    private function resolveSitemapFilename(StorageWrapper $storage): ?string
    {
        if ($storage->checkFile('cacheFolder', '', 'sitemap.xml')) {
            return 'sitemap.xml';
        }

        $cacheFolder = rtrim($storage->getFolderPath('cacheFolder'), DIRECTORY_SEPARATOR);
        $matches = glob($cacheFolder . DIRECTORY_SEPARATOR . 'sitemap-*.xml') ?: [];

        if (count($matches) === 1) {
            return basename($matches[0]);
        }

        return null;
    }
}
