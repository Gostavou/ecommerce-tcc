<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerfilController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(
            $request->user()->only(['id', 'name', 'email', 'role'])
        );
    }

    public function update(Request $request)
    {
        $usuario = $request->user();

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
        ]);

        $usuario->update($dados);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'usuario' => $usuario->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    }
}
