<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * Home carousel: visible banners whose link still leads somewhere.
     */
    public function index(): JsonResponse
    {
        $banners = Banner::query()
            ->visible()
            ->get()
            ->filter(fn (Banner $banner) => $banner->hasValidTarget())
            ->values();

        return response()->json([
            'data' => $banners->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'button_label' => $banner->button_label,
                'image_url' => $banner->imageUrl(),
                'background_color' => $banner->background_color,
                'link' => [
                    'type' => $banner->link_type,
                    'id' => in_array($banner->link_type, Banner::RECORD_LINKS, true) ? $banner->link_id : null,
                    'url' => $banner->link_type === 'url' ? $banner->link_url : null,
                ],
            ]),
        ]);
    }
}
