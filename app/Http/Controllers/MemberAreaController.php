<?php

namespace App\Http\Controllers;

use App\Events\MemberAreaLoaded;
use App\Models\Product;
use App\Services\MemberAreaResolver;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaController extends Controller
{
    public function index(MemberAreaResolver $resolver): Response
    {
        $user = auth()->user();
        $produtos = $user->products()->orderBy('name')->get();
        event(new MemberAreaLoaded($user, $produtos));

        $produtosArray = $produtos->map(function (Product $p) use ($resolver) {
            $item = [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'image_url' => $p->image ? app(\App\Services\StorageService::class)->url($p->image) : null,
            ];
            if ($p->type === Product::TYPE_AREA_MEMBROS && $p->checkout_slug) {
                $item['member_area_url'] = $resolver->baseUrlForProduct($p);
                $item['checkout_slug'] = $p->checkout_slug;
            }
            return $item;
        })->values()->all();

        return Inertia::render('MemberArea/Index', ['produtos' => $produtosArray]);
    }
}
