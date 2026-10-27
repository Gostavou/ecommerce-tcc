<?php

namespace App\Http\Controllers;

use App\Models\Endereco;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnderecoController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()
                ->enderecos()
                ->orderByDesc('principal')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request)
    {

        $usuario = $request->user();

        if ($usuario->enderecos()->count() >= 5) {
            return response()->json([
                'message' => 'Você pode cadastrar no máximo 5 endereços.',
            ], 422);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],            'rua' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'size:2'],
            'principal' => ['sometimes', 'boolean'],
        ]);

        $usuario = $request->user();

        $endereco = DB::transaction(function () use ($usuario, $dados) {
            $definirPrincipal = $dados['principal'] ?? false;

            if ($definirPrincipal || !$usuario->enderecos()->exists()) {
                $usuario->enderecos()->update(['principal' => false]);
                $dados['principal'] = true;
            }

            return $usuario->enderecos()->create($dados);
        });

        return response()->json([
            'message' => 'Endereço cadastrado com sucesso.',
            'endereco' => $endereco,
        ], 201);
    }

    public function show(Request $request, Endereco $endereco)
    {
        $this->autorizarDono($request, $endereco);

        return response()->json($endereco);
    }

    public function update(Request $request, Endereco $endereco)
    {
        $this->autorizarDono($request, $endereco);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'rua' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'size:2'],
            'principal' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $endereco, $dados) {
            if (($dados['principal'] ?? false) === true) {
                $request->user()->enderecos()->update(['principal' => false]);
            }

            $endereco->update($dados);
        });

        return response()->json([
            'message' => 'Endereço atualizado com sucesso.',
            'endereco' => $endereco->fresh(),
        ]);
    }

    public function destroy(Request $request, Endereco $endereco)
    {
        $this->autorizarDono($request, $endereco);

        $eraPrincipal = $endereco->principal;
        $usuario = $request->user();

        DB::transaction(function () use ($endereco, $eraPrincipal, $usuario) {
            $endereco->delete();

            if ($eraPrincipal) {
                $proximo = $usuario->enderecos()->orderBy('id')->first();

                if ($proximo) {
                    $proximo->update(['principal' => true]);
                }
            }
        });

        return response()->json([
            'message' => 'Endereço excluído com sucesso.',
        ]);
    }

    private function autorizarDono(Request $request, Endereco $endereco): void
    {
        abort_unless(
            $endereco->user_id === $request->user()->id,
            403,
            'Você não tem permissão para acessar este endereço.'
        );
    }
}
