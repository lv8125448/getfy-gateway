<?php

namespace App\Services;

use App\Models\MemberCommunityPage;
use App\Models\MemberCommunityPost;
use App\Models\MemberModule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;

class StorageUrlNormalizer
{
    private string $appHost = '';

    private string $storagePathPrefix = '/storage/';

    public function __construct()
    {
        $appUrl = config('app.url', '');
        $parsed = parse_url($appUrl);
        $this->appHost = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost');
        if (! empty($parsed['port'])) {
            $this->appHost .= ':' . $parsed['port'];
        }
        $this->appHost = rtrim($this->appHost, '/');
    }

    /**
     * If the value is a string that looks like a local storage URL (our app + /storage/...),
     * return the relative path; otherwise return the value unchanged.
     */
    public function toRelativePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        $parsed = parse_url($value);
        if (! isset($parsed['path']) || ! str_starts_with($parsed['path'], $this->storagePathPrefix)) {
            return $value;
        }
        $parsedHost = ($parsed['scheme'] ?? '') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $parsedHost .= ':' . $parsed['port'];
        }
        $appParsed = parse_url($this->appHost);
        $appHostNorm = ($appParsed['host'] ?? '');
        $valueHostNorm = $parsed['host'] ?? '';
        if (strtolower($valueHostNorm) !== strtolower($appHostNorm)) {
            return $value;
        }
        $path = substr($parsed['path'], strlen($this->storagePathPrefix));

        return ltrim($path, '/');
    }

    /**
     * Return true if the string looks like a local storage URL from our app.
     */
    public function isLocalStorageUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        $parsed = parse_url($value);
        if (! isset($parsed['path']) || ! str_starts_with($parsed['path'], $this->storagePathPrefix)) {
            return false;
        }
        $valueHostNorm = strtolower($parsed['host'] ?? '');
        $appParsed = parse_url($this->appHost);
        $appHostNorm = strtolower($appParsed['host'] ?? '');

        return $valueHostNorm === $appHostNorm;
    }

    /**
     * Recursively walk an array and convert any string that is a local storage URL to relative path.
     * Modifies the array in place; returns true if any change was made.
     */
    public function normalizeArray(array &$arr): bool
    {
        $changed = false;
        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                if ($this->isLocalStorageUrl($v)) {
                    $arr[$k] = $this->toRelativePath($v);
                    $changed = true;
                }
            } elseif (is_array($v)) {
                if ($this->normalizeArray($v)) {
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    /**
     * Normalize all stored references that are local storage URLs to relative paths.
     * Returns ['updated' => count of records updated, 'details' => [...]].
     *
     * @return array{updated: int, details: array<string, int>}
     */
    public function normalizeAll(): array
    {
        $details = [];
        $totalUpdated = 0;

        // Products: checkout_config, member_area_config
        $products = Product::query()->get();
        $count = 0;
        foreach ($products as $product) {
            $changed = false;
            if ($product->checkout_config && is_array($product->checkout_config)) {
                $cfg = $product->checkout_config;
                if ($this->normalizeArray($cfg)) {
                    $product->checkout_config = $cfg;
                    $changed = true;
                }
            }
            if ($product->member_area_config && is_array($product->member_area_config)) {
                $cfg = $product->member_area_config;
                if ($this->normalizeArray($cfg)) {
                    $product->member_area_config = $cfg;
                    $changed = true;
                }
            }
            if ($product->image && is_string($product->image) && $this->isLocalStorageUrl($product->image)) {
                $product->image = $this->toRelativePath($product->image);
                $changed = true;
            }
            if ($changed) {
                $product->save();
                $count++;
            }
        }
        $details['products'] = $count;
        $totalUpdated += $count;

        // ProductOffer: checkout_config
        $offers = ProductOffer::query()->get();
        $count = 0;
        foreach ($offers as $offer) {
            if ($offer->checkout_config && is_array($offer->checkout_config)) {
                $cfg = $offer->checkout_config;
                if ($this->normalizeArray($cfg)) {
                    $offer->checkout_config = $cfg;
                    $offer->save();
                    $count++;
                }
            }
        }
        $details['product_offers'] = $count;
        $totalUpdated += $count;

        // SubscriptionPlan: checkout_config
        $plans = SubscriptionPlan::query()->get();
        $count = 0;
        foreach ($plans as $plan) {
            if ($plan->checkout_config && is_array($plan->checkout_config)) {
                $cfg = $plan->checkout_config;
                if ($this->normalizeArray($cfg)) {
                    $plan->checkout_config = $cfg;
                    $plan->save();
                    $count++;
                }
            }
        }
        $details['subscription_plans'] = $count;
        $totalUpdated += $count;

        // Users: avatar
        $count = 0;
        User::query()->whereNotNull('avatar')->where('avatar', '!=', '')->chunk(100, function ($users) use (&$count) {
            foreach ($users as $user) {
                $v = $user->avatar;
                if (is_string($v) && $this->isLocalStorageUrl($v)) {
                    $user->avatar = $this->toRelativePath($v);
                    $user->save();
                    $count++;
                }
            }
        });
        $details['users'] = $count;
        $totalUpdated += $count;

        // MemberModule: thumbnail
        $count = 0;
        MemberModule::query()->whereNotNull('thumbnail')->where('thumbnail', '!=', '')->chunk(100, function ($modules) use (&$count) {
            foreach ($modules as $module) {
                $v = $module->thumbnail;
                if (is_string($v) && $this->isLocalStorageUrl($v)) {
                    $module->thumbnail = $this->toRelativePath($v);
                    $module->save();
                    $count++;
                }
            }
        });
        $details['member_modules'] = $count;
        $totalUpdated += $count;

        // MemberCommunityPage: banner
        $count = 0;
        MemberCommunityPage::query()->whereNotNull('banner')->where('banner', '!=', '')->chunk(100, function ($pages) use (&$count) {
            foreach ($pages as $page) {
                $v = $page->banner;
                if (is_string($v) && $this->isLocalStorageUrl($v)) {
                    $page->banner = $this->toRelativePath($v);
                    $page->save();
                    $count++;
                }
            }
        });
        $details['member_community_pages'] = $count;
        $totalUpdated += $count;

        // MemberCommunityPost: image
        $count = 0;
        MemberCommunityPost::query()->whereNotNull('image')->where('image', '!=', '')->chunk(100, function ($posts) use (&$count) {
            foreach ($posts as $post) {
                $v = $post->image;
                if (is_string($v) && $this->isLocalStorageUrl($v)) {
                    $post->image = $this->toRelativePath($v);
                    $post->save();
                    $count++;
                }
            }
        });
        $details['member_community_posts'] = $count;
        $totalUpdated += $count;

        return [
            'updated' => $totalUpdated,
            'details' => $details,
        ];
    }
}
