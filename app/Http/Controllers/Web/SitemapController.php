<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Webinar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $content = Cache::remember('sitemap_xml_data', 86400, function () {
            $baseUrl = rtrim(url('/'), '/');
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            // Static pages
            $staticUrls = [
                '/',
                '/classes',
                '/blog',
                '/contact',
                '/instructors',
                '/organizations',
                '/become-instructor',
            ];

            foreach ($staticUrls as $path) {
                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars($baseUrl . $path, ENT_XML1, 'UTF-8') . '</loc>';
                $xml .= '<changefreq>daily</changefreq>';
                $xml .= '<priority>' . ($path === '/' ? '1.0' : '0.8') . '</priority>';
                $xml .= '</url>';
            }

            // Categories
            try {
                $categories = Category::query()->select('id', 'slug')->get();
                foreach ($categories as $category) {
                    if (!empty($category->slug)) {
                        $xml .= '<url>';
                        $xml .= '<loc>' . htmlspecialchars($baseUrl . '/categories/' . $category->slug, ENT_XML1, 'UTF-8') . '</loc>';
                        $xml .= '<changefreq>weekly</changefreq>';
                        $xml .= '<priority>0.7</priority>';
                        $xml .= '</url>';
                    }
                }
            } catch (\Exception $e) {}

            // Courses (Webinars)
            try {
                $webinars = Webinar::query()
                    ->where('status', Webinar::$active)
                    ->where('private', false)
                    ->select('id', 'slug', 'updated_at')
                    ->get();

                foreach ($webinars as $webinar) {
                    if (!empty($webinar->slug)) {
                        $lastmod = !empty($webinar->updated_at) ? date('Y-m-d', $webinar->updated_at) : date('Y-m-d');
                        $xml .= '<url>';
                        $xml .= '<loc>' . htmlspecialchars($baseUrl . '/course/' . $webinar->slug, ENT_XML1, 'UTF-8') . '</loc>';
                        $xml .= '<lastmod>' . $lastmod . '</lastmod>';
                        $xml .= '<changefreq>weekly</changefreq>';
                        $xml .= '<priority>0.8</priority>';
                        $xml .= '</url>';
                    }
                }
            } catch (\Exception $e) {}

            // Bundles
            try {
                $bundles = Bundle::query()
                    ->where('status', Bundle::$active)
                    ->select('id', 'slug')
                    ->get();

                foreach ($bundles as $bundle) {
                    if (!empty($bundle->slug)) {
                        $xml .= '<url>';
                        $xml .= '<loc>' . htmlspecialchars($baseUrl . '/bundles/' . $bundle->slug, ENT_XML1, 'UTF-8') . '</loc>';
                        $xml .= '<changefreq>weekly</changefreq>';
                        $xml .= '<priority>0.8</priority>';
                        $xml .= '</url>';
                    }
                }
            } catch (\Exception $e) {}

            // Blog Posts
            try {
                $posts = Blog::query()
                    ->where('status', 'publish')
                    ->select('id', 'slug', 'updated_at')
                    ->get();

                foreach ($posts as $post) {
                    if (!empty($post->slug)) {
                        $lastmod = !empty($post->updated_at) ? date('Y-m-d', $post->updated_at) : date('Y-m-d');
                        $xml .= '<url>';
                        $xml .= '<loc>' . htmlspecialchars($baseUrl . '/blog/' . $post->slug, ENT_XML1, 'UTF-8') . '</loc>';
                        $xml .= '<lastmod>' . $lastmod . '</lastmod>';
                        $xml .= '<changefreq>monthly</changefreq>';
                        $xml .= '<priority>0.6</priority>';
                        $xml .= '</url>';
                    }
                }
            } catch (\Exception $e) {}

            $xml .= '</urlset>';
            return $xml;
        });

        return Response::make($content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8'
        ]);
    }
}
