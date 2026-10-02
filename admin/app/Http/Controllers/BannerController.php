<?php

namespace App\Http\Controllers;

use App\DataTables\BannerDataTable;
use App\Http\Requests\Banners\BannerRequest;
use App\Models\Banner;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Home carousel of the app: slides with an optional image and a link to a section, a tournament,
 * a sports center or an external URL.
 */
class BannerController extends Controller
{
    public function index(BannerDataTable $dataTable): mixed
    {
        return $dataTable->render('banners.index');
    }

    public function create(): View
    {
        $nextOrder = (int) Banner::query()->max('sort_order') + 10;

        return view('banners.create', [
            'banner' => new Banner(['is_active' => true, 'link_type' => 'reserve_courts', 'background_color' => '#4F46E5', 'sort_order' => $nextOrder]),
            ...$this->formOptions(),
        ]);
    }

    public function store(BannerRequest $request): RedirectResponse
    {
        $banner = Banner::query()->create($request->bannerData());
        if ($request->hasFile('image')) {
            $banner->update(['image_path' => $request->file('image')->store("banners/{$banner->id}", CourtPhoto::DISK)]);
        }

        return redirect()->route('banners.index')->with('success', "Banner \"{$banner->title}\" creado.");
    }

    public function edit(Banner $banner): View
    {
        return view('banners.edit', ['banner' => $banner, ...$this->formOptions()]);
    }

    public function update(BannerRequest $request, Banner $banner): RedirectResponse
    {
        $attributes = $request->bannerData();
        $replaceImage = $request->hasFile('image') || $request->boolean('remove_image');
        if ($replaceImage) {
            $banner->deleteImage();
            $attributes['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store("banners/{$banner->id}", CourtPhoto::DISK)
                : null;
        }

        $banner->update($attributes);

        return redirect()->route('banners.index')->with('success', "Banner \"{$banner->title}\" actualizado.");
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->deleteImage();
        $banner->delete();

        return response()->json(['message' => "Banner \"{$banner->title}\" eliminado."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'tournaments' => Tournament::query()->orderByDesc('starts_on')->get(['id', 'name', 'status']),
            'courts' => Court::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
