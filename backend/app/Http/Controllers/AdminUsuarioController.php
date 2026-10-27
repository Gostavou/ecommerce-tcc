<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'created_at',
            ])
            ->orderBy('name')
            ->get();

        return response()->json($usuarios);
    }

    public function update(Request $request, User $usuario)
    {
        $dados = $request->validate([
            'role' => [
                'required',
                'string',
                'in:cliente,admin',
            ],
        ]);

        if (
            $request->user()->is($usuario)
            && $dados['role'] !== 'admin'
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'Você não pode remover sua própria permissão administrativa.',
                ],
            ]);
        }

        $usuarioAtualizado = DB::transaction(
            function () use ($usuario, $dados) {
                $usuarioBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($usuario->id);

                if (
                    $usuarioBloqueado->role === 'admin'
                    && $dados['role'] !== 'admin'
                    && User::where('role', 'admin')->count() <= 1
                ) {
                    throw ValidationException::withMessages([
                        'role' => [
                            'Não é possível remover o último administrador.',
                        ],
                    ]);
                }

                $usuarioBloqueado->role = $dados['role'];
                $usuarioBloqueado->save();

                return $usuarioBloqueado;
            }
        );

        return response()->json([
            'message' => 'Perfil do usuário atualizado com sucesso.',
            'usuario' => $usuarioAtualizado->only([
                'id',
                'name',
                'email',
                'role',
                'created_at',
            ]),
        ]);
    }


    public function destroy(Request $request, User $usuario)
    {
        if ($request->user()->is($usuario)) {
            throw ValidationException::withMessages([
                'usuario' => [
                    'Você não pode excluir sua própria conta.',
                ],
            ]);
        }

        DB::transaction(function () use ($usuario) {
            $usuarioBloqueado = User::query()
                ->lockForUpdate()
                ->findOrFail($usuario->id);

            if (
                $usuarioBloqueado->role === 'admin'
                && User::where('role', 'admin')->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'usuario' => [
                        'Não é possível excluir o último administrador.',
                    ],
                ]);
            }

            $usuarioBloqueado->delete();
        });

        return response()->json([
            'message' => 'Usuário excluído com sucesso.',
        ]);
    }
}
