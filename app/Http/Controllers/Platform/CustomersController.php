<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : '';
        $search = $search !== '' ? $search : null;

        $query = User::query()
            ->whereHas('orders', fn ($q) => $q->where('status', 'completed'))
            ->withCount(['orders as purchases_count' => fn ($q) => $q->where('status', 'completed')])
            ->orderByDesc('id');

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        $users = $query->paginate(30)->withQueryString();

        return Inertia::render('Platform/Customers/Index', [
            'users' => $users,
            'q' => $search,
            'pageTitle' => 'Clientes',
        ]);
    }
}
