<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $pengelola = User::whereIn('role', array_keys(User::rolesPengelola()))
            ->orderByRaw('FIELD(role, ?, ?)', [User::ROLE_DEVELOPER, User::ROLE_PANITIA])
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'role_label' => $u->roleLabel(),
                'phone' => $u->phone,
                'created_at' => $u->created_at->translatedFormat('d M Y'),
                'is_self' => $u->id === $request->user()->id,
            ]);

        return Inertia::render('Panitia/Users', [
            'users' => $pengelola,
            'roles' => collect(User::rolesPengelola())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'pesertaCount' => User::where('role', User::ROLE_PESERTA)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(array_keys(User::rolesPengelola()))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], $this->labels());

        // Akun pengelola dibuatkan developer, bukan mendaftar sendiri — tidak ada
        // email verifikasi yang dikirim ke mereka, jadi ditandai terverifikasi
        // sejak awal supaya tidak terjebak di halaman verifikasi.
        User::create([...$data, 'email_verified_at' => now()]);

        return back()->with('success', "Akun {$data['name']} dibuat sebagai ".User::rolesPengelola()[$data['role']].'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->pastikanPengelola($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(array_keys(User::rolesPengelola()))],
            // Kosongkan kalau tidak ingin mengganti kata sandi.
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [], $this->labels());

        // Jangan sampai developer terakhir menurunkan dirinya sendiri jadi panitia
        // dan tidak ada lagi yang bisa membuka halaman ini.
        if ($user->isDeveloper() && $data['role'] !== User::ROLE_DEVELOPER) {
            $this->pastikanBukanDeveloperTerakhir($user, 'role');
        }

        $user->fill(collect($data)->except('password')->all());

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('success', "Data {$user->name} diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->pastikanPengelola($user);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'Kamu tidak bisa menghapus akunmu sendiri.',
            ]);
        }

        if ($user->isDeveloper()) {
            $this->pastikanBukanDeveloperTerakhir($user, 'user');
        }

        $nama = $user->name;
        $user->delete();

        return back()->with('success', "Akun {$nama} dihapus.");
    }

    /** Halaman ini hanya untuk akun pengelola — akun peserta tidak boleh disentuh. */
    private function pastikanPengelola(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }

    private function pastikanBukanDeveloperTerakhir(User $user, string $field): void
    {
        $sisa = User::where('role', User::ROLE_DEVELOPER)->where('id', '!=', $user->id)->count();

        if ($sisa === 0) {
            throw ValidationException::withMessages([
                $field => 'Ini satu-satunya akun developer. Buat developer lain dulu sebelum mengubah atau menghapusnya.',
            ]);
        }
    }

    private function labels(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor WhatsApp',
            'role' => 'peran',
            'password' => 'kata sandi',
        ];
    }
}
