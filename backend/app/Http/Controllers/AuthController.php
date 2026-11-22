<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Role is NEVER taken from input: public sign-up always creates a student.
        $user = User::create([...$data, 'role' => 'student']);

        return response()->json($this->payload($user), 201);
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }
        return $this->payload($user);
    }

    public function me(Request $r) { return $this->userArray($r->user()); }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    private function payload(User $u): array
    {
        return ['token' => $u->createToken('spa')->plainTextToken, 'user' => $this->userArray($u)];
    }

    private function userArray(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role];
    }
}
