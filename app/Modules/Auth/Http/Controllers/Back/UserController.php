<?php

namespace App\Modules\Auth\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\StoreUserRequest;
use App\Modules\Auth\Http\Requests\UpdateUserRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private UserService $users
    ) {
    }

    public function index()
    {
        return $this->backView('back.users.index', [
            'pageTitle' => 'Utilisateurs',
            'users' => $this->users->paginate(20),
            'roles' => User::ROLES,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $this->users->create($data);

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function update(UpdateUserRequest $request, string $user)
    {
        $model = User::findOrFail($user);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $this->users->update($model, $data);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, string $user)
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $model = User::findOrFail($user);

        if ((string) $model->getKey() === (string) $request->user()->getKey()) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $this->users->delete($model);

        return back()->with('success', 'Utilisateur supprimé.');
    }
}
