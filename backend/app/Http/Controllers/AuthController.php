<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registrar(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $usuario = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => $dados['password'],
            'role' => 'cliente',
        ]);

        return response()->json([
            'message' => 'Usuário cadastrado com sucesso.',
            'usuario' => $usuario,
        ], 201);
    }

    public function login(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($dados)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        $usuario = $request->user();

        $token = $usuario->createToken('siciliana64-app')->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'usuario' => $usuario,
            'token' => $token,
        ]);
    }
}
